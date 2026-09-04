<?php

namespace App\Services\Eso;

use App\Models\Eso\DecisionLog;
use App\Models\Eso\LearnerNodeState;
use App\Models\Eso\ResponseLog;
use App\Models\PAL\ConceptNode;
use App\Models\PAL\ConceptRelation;
use App\Models\PAL\MisconceptionLibrary;
use App\Models\PAL\QuestionMetadata;
use App\Services\PAL\Content\MisconceptionLibraryService;
use App\Services\PAL\Runtime\PalEvidenceRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Learning ESO resolver — Adaptive Learning Engine Developer Brief v1.
 *
 * Universal Policy + Concept Intelligence + Learner State = Resolved ESO
 * (the next best learning action for THIS student, NOW). This class IS that
 * resolver: nextAction() is the single "what should this student see next"
 * entry point, implementing D1-D5 in sequence exactly as specified in the
 * brief's §5. It never calls an LLM — the engine decides WHAT/WHETHER to
 * teach; EsoPalRenderer (a separate class) is the only thing that talks to
 * an LLM, and only to phrase what this class already decided.
 *
 * Deliberately does not reuse BktEngine (real BKT) or pal_competencies —
 * see docs/ADAPTIVE_LEARNING_ENGINE_IMPLEMENTATION_PLAN.md §L.1/§L.5. The
 * mastery update rule here is the brief's own simple ±0.2 clamped rule.
 */
class EsoPolicyService
{
    /** D1 — node mastery at/above this on entry is skip-eligible. */
    public const SKIP_THRESHOLD = 0.80;

    /** D2 — a prerequisite concept's average node mastery below this blocks the main concept. */
    public const PREREQUISITE_THRESHOLD = 0.75;

    /** D4 — mastery verdict thresholds, read off K-type and A-type nodes respectively. */
    public const KNOWLEDGE_MASTERY_THRESHOLD = 0.80;

    public const APPLICATION_MASTERY_THRESHOLD = 0.70;

    /** D4 — guided practice advances to independent after this many correct in a row. */
    public const CONSECUTIVE_CORRECT_TO_ADVANCE = 2;

    /** D5 — brief specifies "3-5 days"; 4 is the midpoint. */
    public const RETRIEVAL_DELAY_DAYS = 4;

    /** D5 — brief specifies "2-3 items". */
    public const RETRIEVAL_ITEM_COUNT = 3;

    /** Simple update rule: correct +0.2, wrong -0.2, clamped 0-1. */
    protected const MASTERY_STEP = 0.2;

    /** Chapter dashboard — a node needs at least this many attempts before its mastery signal is shown at all. */
    public const MIN_ATTEMPTS_FOR_EVIDENCE = 1;

    public function __construct(
        protected MisconceptionLibraryService $misconceptions,
        protected PalEvidenceRepository $evidence,
    ) {
    }

    // ── D1: diagnostic ──────────────────────────────────────────────────

    /**
     * 5-8 diagnostic items spread across a concept's K/A/S nodes.
     *
     * Returns [] when no question in this concept's node set has been tagged
     * with a node_id yet — that is real, honest "Phase 0 tagging not done for
     * this concept" state, not a bug to paper over.
     *
     * Fetches every servable candidate per node and shuffles/hydrates in PHP
     * (matching practiceItem()'s pattern) rather than a DB-level
     * `limit($perNode)` sample — a node's tagged pool is a mix of MCQ and
     * narrative items (hydrateQuestion() only returns MCQ), and a narrow
     * pre-hydration sample can land entirely on narrative ids, silently
     * yielding fewer items than intended for that node, or none.
     */
    public function diagnosticItems(int $conceptId, int $subInstituteId, int $totalItems = 8): array
    {
        $nodes = $this->nodesForConcept($conceptId, $subInstituteId);
        if ($nodes->isEmpty()) {
            return [];
        }

        $perNode = max(1, intdiv($totalItems, $nodes->count()));
        $items = [];

        foreach ($nodes as $node) {
            $candidates = QuestionMetadata::forNode($node->id)
                ->forTenant($subInstituteId)
                ->servable()
                ->get(['question_id', 'item_type'])
                ->shuffle();

            $collected = 0;
            foreach ($candidates as $q) {
                if ($collected >= $perNode) {
                    break;
                }

                $hydrated = $this->hydrateQuestion((int) $q->question_id);
                if ($hydrated === null) {
                    continue;
                }

                $items[] = array_merge($hydrated, [
                    'node_id' => (int) $node->id,
                    'node_type' => $node->node_type,
                    'item_type' => $q->item_type,
                ]);
                $collected++;
            }
        }

        return $items;
    }

    /**
     * One practice question for a node the student has not already answered
     * correctly, for the "teach"/"practice" step of nextAction(). Only MCQ
     * items (question_type_id = 1) are servable here — D3's distractor-based
     * misconception detection is inherently MCQ-only (§Phase 0 tagging scope
     * for Chapter 3: 50 of 220 questions), and scoring a free-text answer
     * server-side is out of v1 scope.
     */
    public function practiceItem(int $nodeId, int $subInstituteId): ?array
    {
        // v1 picks any tagged, servable MCQ for the node at random rather than
        // tracking per-student exposure — the pilot's tagged pool per node is
        // small, and an occasional repeat beats a "no items left" dead end.
        $candidates = QuestionMetadata::forNode($nodeId)
            ->forTenant($subInstituteId)
            ->servable()
            ->pluck('question_id');

        foreach ($candidates->shuffle() as $questionId) {
            // hydrateQuestion() itself enforces MCQ-only and non-empty options.
            $hydrated = $this->hydrateQuestion((int) $questionId);
            if ($hydrated !== null && $hydrated['options'] !== []) {
                return array_merge($hydrated, ['node_id' => $nodeId]);
            }
        }

        return null;
    }

    /**
     * The question text and its answer options, WITHOUT the answer key or
     * misconception mapping — this is served to the student, so neither may
     * leak. Correctness is always determined server-side from
     * `answer_master_id` (see isAnswerCorrect()), never trusted from the client.
     *
     * MCQ only (question_type_id = 1) — narrative/free-text items
     * (question_type_id = 2) have no discrete `answer_master` options, so
     * "hydrating" one would silently produce a question with zero answerable
     * options. Every caller (diagnostic, practice, retrieval) needs a
     * question the student can actually click an answer for, so this is
     * enforced once, here, rather than per call site.
     */
    protected function hydrateQuestion(int $questionId): ?array
    {
        $question = DB::table('lms_question_master')->where('id', $questionId)->first(['id', 'question_title', 'question_type_id']);
        if ($question === null || (int) $question->question_type_id !== 1) {
            return null;
        }

        $options = DB::table('answer_master')
            ->where('question_id', $questionId)
            ->get(['id', 'answer'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'answer' => $row->answer])
            ->all();

        return [
            'question_id' => (int) $question->id,
            'title' => $question->question_title,
            'options' => $options,
        ];
    }

    /**
     * Look up whether a chosen option is correct, server-side — the one and
     * only source of truth for "was this response right or wrong". Callers
     * must never accept a client-supplied correctness flag instead.
     */
    protected function isAnswerCorrect(int $answerMasterId): bool
    {
        return (int) (DB::table('answer_master')->where('id', $answerMasterId)->value('correct_answer') ?? 0) === 1;
    }

    /**
     * One append-only eso_response_log row per scored response (diagnostic
     * item, practice attempt, or retrieval-check item) — the "Mastery
     * details" screen's support split and recent-responses list read this;
     * nothing else in this class needs it, so every call site logs after it
     * has already resolved correctness for its own purposes rather than
     * this method re-deriving it.
     */
    protected function logResponse(
        int $studentId,
        int $conceptId,
        int $nodeId,
        int $subInstituteId,
        int $answerMasterId,
        bool $correct,
        bool $hintUsed = false,
        ?string $mode = null
    ): void {
        $questionId = DB::table('answer_master')->where('id', $answerMasterId)->value('question_id');

        ResponseLog::create([
            'student_id' => $studentId,
            'concept_id' => $conceptId,
            'node_id' => $nodeId,
            'sub_institute_id' => $subInstituteId,
            'question_id' => $questionId,
            'correct' => $correct,
            'hint_used' => $hintUsed,
            'mode' => $mode,
        ]);
    }

    /**
     * Score a diagnostic and set every node's initial state (D1, weighted double
     * per the brief). Nodes at/above SKIP_THRESHOLD are marked mastered and
     * skipped; everything else starts in "learning". Correctness is resolved
     * server-side from each response's `answer_master_id` — a client cannot
     * self-report "correct".
     *
     * @param  array<int, array{node_id:int, answer_master_id:int}>  $responses
     * @return array<int, array{node_id:int, mastery_estimate:float, skip:bool}>
     */
    public function scoreDiagnostic(int $studentId, int $conceptId, int $subInstituteId, array $responses): array
    {
        $byNode = collect($responses)->groupBy('node_id');
        $results = [];

        foreach ($byNode as $nodeId => $nodeResponses) {
            $nodeId = (int) $nodeId;
            $state = $this->stateFor($studentId, $nodeId, $subInstituteId);

            foreach ($nodeResponses as $response) {
                $answerMasterId = (int) $response['answer_master_id'];
                $correct = $this->isAnswerCorrect($answerMasterId);
                $this->applyUpdate($state, $correct, weight: 2.0);
                $this->logResponse($studentId, $conceptId, $nodeId, $subInstituteId, $answerMasterId, $correct);
            }

            $skip = $state->mastery_estimate >= self::SKIP_THRESHOLD;
            $state->status = $skip ? LearnerNodeState::STATUS_MASTERED : LearnerNodeState::STATUS_LEARNING;
            // D1 skip is a mastery path exactly like the D4 verdict (masteryVerdict()
            // schedules D5 retrieval the same way on that path) — without this, a node
            // that skips straight from diagnostic never gets a next_review_at, so D5
            // silently never fires for it. Only reachable end-to-end once every node in
            // a concept has answerable content, which is what surfaced it here.
            if ($skip) {
                $state->next_review_at = now()->addDays(self::RETRIEVAL_DELAY_DAYS);
            }
            $state->save();

            $this->log(
                $studentId,
                $conceptId,
                $nodeId,
                $subInstituteId,
                ['mastery_estimate' => $state->mastery_estimate, 'attempts' => $state->attempts],
                $skip
                    ? sprintf('D1: node mastery %.2f >= %.2f, skip-eligible', $state->mastery_estimate, self::SKIP_THRESHOLD)
                    : sprintf('D1: node mastery %.2f < %.2f, needs instruction', $state->mastery_estimate, self::SKIP_THRESHOLD),
                $skip ? 'skip_instruction' : 'needs_instruction'
            );

            $results[] = ['node_id' => $nodeId, 'mastery_estimate' => $state->mastery_estimate, 'skip' => $skip];
        }

        return $results;
    }

    // ── The resolver ─────────────────────────────────────────────────────

    /**
     * The next best learning action for this student, on this concept, now.
     * Runs D1 (entry) -> D2 (prerequisite gate) -> per-node D3/teach/practice
     * -> D4 (mastery verdict) in that order, and writes exactly one
     * eso_decision_log row for whichever decision it returns.
     */
    /**
     * $silent suppresses every eso_decision_log write this call (and its
     * delegates) would otherwise make — for read-only callers like the
     * chapter dashboard that need "what would happen next" without producing
     * an audit-log entry on every page view. The real per-concept flow never
     * passes this (default false), so its logging is unchanged.
     */
    public function nextAction(int $studentId, int $conceptId, int $subInstituteId, bool $silent = false): array
    {
        $nodes = $this->nodesForConcept($conceptId, $subInstituteId);

        if ($nodes->isEmpty()) {
            return $this->respond(
                'no_nodes_defined',
                $conceptId,
                null,
                'ESO: concept has no K/A/S nodes authored yet',
                null
            );
        }

        $states = LearnerNodeState::forStudent($studentId)
            ->whereIn('node_id', $nodes->pluck('id'))
            ->get()
            ->keyBy('node_id');

        // D1 entry: nothing has been diagnosed for this concept yet.
        if ($states->isEmpty()) {
            if (! $silent) {
                $this->log($studentId, $conceptId, null, $subInstituteId, [], 'D1: concept entry, no diagnostic on file', 'diagnostic');
            }

            return $this->respond('diagnostic', $conceptId, null, 'D1', null);
        }

        // D2: prerequisite gate.
        $gate = $this->prerequisiteGate($studentId, $conceptId, $subInstituteId, $silent);
        if ($gate !== null) {
            return $gate;
        }

        foreach ($nodes as $node) {
            $state = $states->get($node->id) ?? $this->stateFor($studentId, $node->id, $subInstituteId);

            if ($state->status === LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED) {
                return $this->reserveContrastPairAction($studentId, $conceptId, $node, $state, $subInstituteId, $silent);
            }

            if ($state->status === LearnerNodeState::STATUS_MASTERED
                && $state->next_review_at !== null
                && $state->next_review_at->lte(now())) {
                return $this->retrievalDueAction($studentId, $conceptId, $node, $subInstituteId, $silent);
            }

            if ($state->isMastered() || $this->hasSatisfiedOwnThreshold($node, $state)) {
                continue;
            }

            return $this->teachOrPracticeAction($studentId, $conceptId, $node, $state, $subInstituteId, $silent);
        }

        // Every node mastered or retained: run the concept-level D4 verdict
        // (idempotent — if already mastered, this just confirms/stops practice).
        return $this->masteryVerdict($studentId, $conceptId, $subInstituteId, $silent);
    }

    /**
     * A K or A node whose OWN mastery_estimate already clears D4's per-type
     * threshold — KNOWLEDGE_MASTERY_THRESHOLD / APPLICATION_MASTERY_THRESHOLD,
     * the exact same constants masteryVerdict() judges the concept against,
     * not a new threshold — needs no further practice right now, even though
     * its `status` has not been bulk-flipped to 'mastered' yet (that only
     * happens once the WHOLE concept's D4 verdict passes, via masteryVerdict(),
     * or once at diagnostic-time skip, via scoreDiagnostic()). Without this,
     * nextAction()'s per-node loop kept re-serving practice for an
     * already-saturated node forever: `isMastered()` only reads `status`, so a
     * node reaching mastery_estimate 1.0 through ordinary practice attempts
     * (never diagnosed as skip-eligible) stayed 'learning' indefinitely
     * whenever a sibling node hadn't yet cleared its own threshold — and
     * being first in sort order, it was always what got served next, forever
     * blocking the loop from ever reaching the sibling that actually needed
     * the practice.
     *
     * S-type nodes have no accuracy threshold defined anywhere in this
     * policy (masteryVerdict() does not gate on them at all), so this
     * deliberately returns false for them — inventing a threshold here would
     * be a new rule, not a reuse of an existing one.
     */
    protected function hasSatisfiedOwnThreshold(ConceptNode $node, LearnerNodeState $state): bool
    {
        return match ($node->node_type) {
            'K' => $state->mastery_estimate >= self::KNOWLEDGE_MASTERY_THRESHOLD,
            'A' => $state->mastery_estimate >= self::APPLICATION_MASTERY_THRESHOLD,
            default => false,
        };
    }

    // ── D2: prerequisite gate ────────────────────────────────────────────

    /**
     * Reads prerequisites via ConceptMetadata::prerequisites() convention:
     * pal_concept_relations rows where from_concept_id = this concept and
     * relation_type = 'requires'; to_concept_id is the prerequisite concept.
     */
    protected function prerequisiteGate(int $studentId, int $conceptId, int $subInstituteId, bool $silent = false): ?array
    {
        foreach ($this->unmetPrerequisiteConceptIds($conceptId, $studentId, $subInstituteId) as $prerequisiteConceptId) {
            $mastery = $this->conceptMasteryAverage($studentId, $prerequisiteConceptId, $subInstituteId);
            $rule = sprintf('D2: prerequisite concept %d mastery %.2f < %.2f', $prerequisiteConceptId, $mastery, self::PREREQUISITE_THRESHOLD);

            if (! $silent) {
                $this->log(
                    $studentId,
                    $conceptId,
                    null,
                    $subInstituteId,
                    ['prerequisite_concept_id' => $prerequisiteConceptId, 'prerequisite_mastery' => $mastery],
                    $rule,
                    'remediate_prerequisite'
                );
            }

            return [
                'action' => 'remediate_prerequisite',
                'concept_id' => $conceptId,
                'prerequisite_concept_id' => $prerequisiteConceptId,
                'rule_fired' => 'D2',
                'llm_instruction' => null,
            ];
        }

        return null;
    }

    /**
     * The concept's `requires` prerequisites whose average node mastery is
     * below PREREQUISITE_THRESHOLD. A prerequisite with no K/A/S nodes
     * authored yet is skipped, not blocked — can't gate on data that doesn't
     * exist. Shared by prerequisiteGate() (D2, logs + builds an action) and
     * prerequisitesMet() (a plain read for the chapter dashboard's lock
     * icons) so both agree on exactly the same rule.
     */
    protected function unmetPrerequisiteConceptIds(int $conceptId, int $studentId, int $subInstituteId): Collection
    {
        $prerequisiteConceptIds = ConceptRelation::where('from_concept_id', $conceptId)
            ->where('relation_type', 'requires')
            ->forTenant($subInstituteId)
            ->pluck('to_concept_id');

        return $prerequisiteConceptIds
            ->map(fn ($id) => (int) $id)
            ->filter(function (int $prerequisiteConceptId) use ($studentId, $subInstituteId) {
                $mastery = $this->conceptMasteryAverage($studentId, $prerequisiteConceptId, $subInstituteId);

                return $mastery !== null && $mastery < self::PREREQUISITE_THRESHOLD;
            })
            ->values();
    }

    /** Plain read: does this concept have any unmet prerequisite right now? No logging, no action payload. */
    public function prerequisitesMet(int $studentId, int $conceptId, int $subInstituteId): bool
    {
        return $this->unmetPrerequisiteConceptIds($conceptId, $studentId, $subInstituteId)->isEmpty();
    }

    /** Average mastery across a concept's node states; unseen nodes count as 0. */
    protected function conceptMasteryAverage(int $studentId, int $conceptId, int $subInstituteId): ?float
    {
        $nodeIds = $this->nodesForConcept($conceptId, $subInstituteId)->pluck('id');
        if ($nodeIds->isEmpty()) {
            return null;
        }

        $estimates = LearnerNodeState::forStudent($studentId)
            ->whereIn('node_id', $nodeIds)
            ->pluck('mastery_estimate', 'node_id');

        $sum = 0.0;
        foreach ($nodeIds as $nodeId) {
            $sum += (float) ($estimates[$nodeId] ?? 0.0);
        }

        return $sum / $nodeIds->count();
    }

    // ── D3: misconception response ───────────────────────────────────────

    /**
     * Record one practice/quiz attempt against a node. `answer_master_id` is
     * the option the student picked — correctness is resolved server-side
     * from it (isAnswerCorrect()); a client-supplied "correct" flag is never
     * trusted, since a client could otherwise self-report mastery it did not
     * earn. Applies the mastery update rule, runs D3 (misconception detection
     * / clean-retest clearing) and then D4 (mastery verdict) as appropriate,
     * logging exactly one decision and returning it.
     *
     * @param  array{answer_master_id:int, hint_used?:bool, mode?:string}  $attempt
     */
    public function recordAttempt(int $studentId, int $nodeId, int $conceptId, int $subInstituteId, array $attempt): array
    {
        $node = ConceptNode::findOrFail($nodeId);
        $state = $this->stateFor($studentId, $nodeId, $subInstituteId);
        $wasFlagged = $state->status === LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED;

        $answerMasterId = (int) $attempt['answer_master_id'];
        $correct = $this->isAnswerCorrect($answerMasterId);
        $hintUsed = (bool) ($attempt['hint_used'] ?? false);
        $mode = $attempt['mode'] ?? $state->practice_mode ?? LearnerNodeState::MODE_GUIDED;
        $this->logResponse($studentId, $conceptId, $nodeId, $subInstituteId, $answerMasterId, $correct, $hintUsed, $mode);

        // "Independent-practice correctness only counts hint-free."
        $countsForMastery = ! ($mode === LearnerNodeState::MODE_INDEPENDENT && $hintUsed);

        if ($hintUsed) {
            $state->hint_used_count++;
        }

        if ($countsForMastery) {
            $this->applyUpdate($state, $correct);
        } else {
            $state->attempts++;
            $state->last_seen_at = now();
            $state->save();
        }

        if ($wasFlagged) {
            if ($correct) {
                // Clean retest — mark corrected only now, per the brief.
                $state->status = LearnerNodeState::STATUS_LEARNING;
                $state->active_misconception_id = null;
                $state->save();

                $this->log(
                    $studentId,
                    $conceptId,
                    $nodeId,
                    $subInstituteId,
                    ['mastery_estimate' => $state->mastery_estimate],
                    'D3: clean retest after contrast pair, misconception corrected',
                    'misconception_corrected'
                );
                // fall through to D4 below — a corrected node may now qualify.
            } else {
                // Still wrong after the contrast pair: re-serve it, do not re-flag as new.
                return $this->reserveContrastPairAction($studentId, $conceptId, $node, $state, $subInstituteId);
            }
        } elseif (! $correct) {
            $misconceptionAction = $this->checkMisconception($studentId, $conceptId, $node, $state, $answerMasterId, $subInstituteId);
            if ($misconceptionAction !== null) {
                return $misconceptionAction;
            }
        }

        return $this->evaluateProgress($studentId, $conceptId, $subInstituteId);
    }

    /**
     * The distractor the student picked is directly mapped to a misconception
     * (answer_master.misconception_id) — fires immediately, no need to wait
     * for a second occurrence, because a structured distractor mapping is
     * already a certain diagnosis (unlike the text-pattern matching
     * MisconceptionLibraryService::detectAndRoute() does for free-text
     * answers, which the brief's "or M flagged twice" clause is written for).
     */
    protected function checkMisconception(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $answerMasterId, int $subInstituteId): ?array
    {
        $misconceptionId = DB::table('answer_master')->where('id', $answerMasterId)->value('misconception_id');

        if ($misconceptionId === null) {
            return null; // generic error — D3 does not fire
        }

        $misconceptionId = (int) $misconceptionId;

        $state->status = LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED;
        $state->active_misconception_id = $misconceptionId;
        $state->save();

        return $this->contrastPairAction($studentId, $conceptId, $node, $misconceptionId, $subInstituteId, firstFlag: true);
    }

    /** Re-serve (or first-serve) the contrast pair for the node's active misconception. */
    protected function reserveContrastPairAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, bool $silent = false): array
    {
        if ($state->active_misconception_id === null) {
            // Defensive: flagged with no recorded misconception id should not happen,
            // but fail safe into ordinary teaching rather than a dead end.
            $state->status = LearnerNodeState::STATUS_LEARNING;
            $state->save();

            return $this->teachOrPracticeAction($studentId, $conceptId, $node, $state, $subInstituteId, $silent);
        }

        return $this->contrastPairAction($studentId, $conceptId, $node, (int) $state->active_misconception_id, $subInstituteId, firstFlag: false, silent: $silent);
    }

    protected function contrastPairAction(int $studentId, int $conceptId, ConceptNode $node, int $misconceptionId, int $subInstituteId, bool $firstFlag, bool $silent = false): array
    {
        $misconception = MisconceptionLibrary::find($misconceptionId);
        $corrective = $this->misconceptions->selectCorrective($misconceptionId, $studentId, $subInstituteId);

        $instruction = EsoPalRenderer::contrastPairInstruction($node, $misconception, $corrective);

        if (! $silent) {
            $this->log(
                $studentId,
                $conceptId,
                $node->id,
                $subInstituteId,
                ['misconception_id' => $misconceptionId, 'tag' => $misconception?->tag],
                $firstFlag ? "D3: distractor mapped to misconception {$misconceptionId}" : "D3: misconception {$misconceptionId} still active, re-serving contrast pair",
                'serve_contrast_pair',
                $instruction
            );
        }

        return [
            'action' => 'serve_contrast_pair',
            'node_id' => $node->id,
            'concept_id' => $conceptId,
            'misconception_id' => $misconceptionId,
            'contrast_pair' => $corrective,
            'rule_fired' => 'D3',
            'llm_instruction' => $instruction,
        ];
    }

    // ── D4: practice gating + mastery verdict ───────────────────────────

    protected function teachOrPracticeAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, bool $silent = false): array
    {
        $firstExposure = $state->attempts === 0;
        $instruction = EsoPalRenderer::teachInstruction($node, $state, $this->priorNodeLabels($node));

        $action = $firstExposure ? 'teach' : 'practice';
        $rule = $firstExposure
            ? 'D1: node not yet taught'
            : sprintf('D4: mastery %.2f, mode=%s, continue practice', $state->mastery_estimate, $state->practice_mode);

        if (! $silent) {
            $this->log(
                $studentId,
                $conceptId,
                $node->id,
                $subInstituteId,
                ['mastery_estimate' => $state->mastery_estimate, 'attempts' => $state->attempts, 'practice_mode' => $state->practice_mode],
                $rule,
                $action,
                $instruction
            );
        }

        return [
            'action' => $action,
            'node_id' => $node->id,
            'concept_id' => $conceptId,
            'practice_mode' => $state->practice_mode,
            'rule_fired' => $firstExposure ? 'D1' : 'D4',
            'llm_instruction' => $instruction,
        ];
    }

    /**
     * After an attempt: what should the student see next? Delegates to the
     * full resolver rather than re-serving the node just attempted — that
     * node may already have satisfied its own D4 threshold (see
     * hasSatisfiedOwnThreshold()) while a sibling node still needs work, and
     * only nextAction()'s per-node loop knows how to route to it correctly.
     * This also keeps the decision log honest: logging "continue practice"
     * against a node that in fact needs no more practice would be a
     * misleading audit entry, not just a UI inconvenience.
     */
    protected function evaluateProgress(int $studentId, int $conceptId, int $subInstituteId): array
    {
        return $this->nextAction($studentId, $conceptId, $subInstituteId);
    }

    /**
     * Mastery = knowledge accuracy >= 0.80 AND application accuracy >= 0.70
     * AND no critical misconception active. Knowledge/application accuracy are
     * read directly off the concept's K-type and A-type node mastery — see the
     * class docblock's K/A/S design note for why no separate accuracy
     * tracking table is needed.
     */
    public function masteryVerdict(int $studentId, int $conceptId, int $subInstituteId, bool $silent = false): array
    {
        $nodes = $this->nodesForConcept($conceptId, $subInstituteId);
        $nodeIds = $nodes->pluck('id');

        $states = LearnerNodeState::forStudent($studentId)->whereIn('node_id', $nodeIds)->get()->keyBy('node_id');

        $kNodes = $nodes->where('node_type', 'K');
        $aNodes = $nodes->where('node_type', 'A');

        $kMastery = $this->averageMasteryOf($kNodes, $states);
        $aMastery = $this->averageMasteryOf($aNodes, $states);

        $misconceptionActive = $states->contains(fn (LearnerNodeState $s) => $s->status === LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED);

        $knowledgeOk = $kNodes->isEmpty() || ($kMastery !== null && $kMastery >= self::KNOWLEDGE_MASTERY_THRESHOLD);
        $applicationOk = $aNodes->isEmpty() || ($aMastery !== null && $aMastery >= self::APPLICATION_MASTERY_THRESHOLD);

        $mastered = $knowledgeOk && $applicationOk && ! $misconceptionActive;

        $rule = sprintf(
            'D4: knowledge=%.2f (need %.2f: %s), application=%.2f (need %.2f: %s), misconception_active=%s',
            $kMastery ?? 0.0,
            self::KNOWLEDGE_MASTERY_THRESHOLD,
            $knowledgeOk ? 'pass' : 'fail',
            $aMastery ?? 0.0,
            self::APPLICATION_MASTERY_THRESHOLD,
            $applicationOk ? 'pass' : 'fail',
            $misconceptionActive ? 'yes' : 'no'
        );

        $action = $mastered ? 'mastered_stop_practice' : 'continue_practice';

        if ($mastered) {
            foreach ($nodes as $node) {
                $state = $states->get($node->id);
                if ($state && ! $state->isMastered()) {
                    $state->status = LearnerNodeState::STATUS_MASTERED;
                    $state->next_review_at = now()->addDays(self::RETRIEVAL_DELAY_DAYS);
                    $state->save();
                }
            }
        }

        if (! $silent) {
            $this->log(
                $studentId,
                $conceptId,
                null,
                $subInstituteId,
                ['knowledge_mastery' => $kMastery, 'application_mastery' => $aMastery, 'misconception_active' => $misconceptionActive],
                $rule,
                $action
            );
        }

        return [
            'action' => $action,
            'concept_id' => $conceptId,
            'mastered' => $mastered,
            'knowledge_mastery' => $kMastery,
            'application_mastery' => $aMastery,
            'rule_fired' => 'D4',
            'llm_instruction' => null,
        ];
    }

    protected function averageMasteryOf(Collection $nodes, Collection $statesByNodeId): ?float
    {
        if ($nodes->isEmpty()) {
            return null;
        }

        $sum = 0.0;
        foreach ($nodes as $node) {
            $state = $statesByNodeId->get($node->id);
            $sum += $state ? (float) $state->mastery_estimate : 0.0;
        }

        return $sum / $nodes->count();
    }

    // ── D5: delayed retrieval ────────────────────────────────────────────

    /**
     * The resolver's D5 branch: a mastered node's scheduled review has come
     * due, so nextAction() surfaces it instead of silently skipping past a
     * mastered node forever. This is what actually connects D5 into the
     * "next best action" flow — retrievalCheck()/dueForRetrieval() alone are
     * reachable but were never wired into nextAction() itself until this.
     */
    protected function retrievalDueAction(int $studentId, int $conceptId, ConceptNode $node, int $subInstituteId, bool $silent = false): array
    {
        if (! $silent) {
            $this->log(
                $studentId,
                $conceptId,
                $node->id,
                $subInstituteId,
                ['next_review_at' => now()->toDateTimeString()],
                'D5: scheduled retrieval check is due',
                'retrieval_due'
            );
        }

        return [
            'action' => 'retrieval_due',
            'node_id' => $node->id,
            'concept_id' => $conceptId,
            'rule_fired' => 'D5',
            'llm_instruction' => null,
        ];
    }

    /** 2-3 fresh items for a node's delayed retrieval check. */
    public function retrievalItems(int $nodeId, int $subInstituteId): array
    {
        $candidates = QuestionMetadata::forNode($nodeId)
            ->forTenant($subInstituteId)
            ->servable()
            ->pluck('question_id')
            ->shuffle();

        $items = [];
        foreach ($candidates as $questionId) {
            // hydrateQuestion() itself enforces MCQ-only and non-empty options.
            $hydrated = $this->hydrateQuestion((int) $questionId);
            if ($hydrated !== null && $hydrated['options'] !== []) {
                $items[] = array_merge($hydrated, ['node_id' => $nodeId]);
            }
            if (count($items) >= self::RETRIEVAL_ITEM_COUNT) {
                break;
            }
        }

        return $items;
    }

    /** Nodes whose scheduled retrieval check is due now, for a student. */
    public function dueForRetrieval(int $studentId, ?int $subInstituteId = null): Collection
    {
        return LearnerNodeState::forStudent($studentId)
            ->where('status', LearnerNodeState::STATUS_MASTERED)
            ->whereNotNull('next_review_at')
            ->where('next_review_at', '<=', now())
            ->when($subInstituteId !== null, fn ($q) => $q->where('sub_institute_id', $subInstituteId))
            ->get();
    }

    /**
     * Score a 2-3 item retrieval check. All items correct -> Retained.
     * Any wrong -> re-loop this node only (status back to learning); the rest
     * of the chapter is untouched. Correctness is resolved server-side from
     * each response's `answer_master_id`, same as recordAttempt().
     *
     * @param  array<int, array{answer_master_id:int}>  $responses
     */
    public function retrievalCheck(int $studentId, int $nodeId, int $conceptId, int $subInstituteId, array $responses): array
    {
        $state = $this->stateFor($studentId, $nodeId, $subInstituteId);
        $correctness = collect($responses)->map(function ($r) use ($studentId, $conceptId, $nodeId, $subInstituteId) {
            $answerMasterId = (int) $r['answer_master_id'];
            $correct = $this->isAnswerCorrect($answerMasterId);
            $this->logResponse($studentId, $conceptId, $nodeId, $subInstituteId, $answerMasterId, $correct);

            return $correct;
        });
        $allCorrect = $correctness->every(fn (bool $correct) => $correct);

        if ($allCorrect) {
            $state->status = LearnerNodeState::STATUS_RETAINED;
            $state->next_review_at = null;
            $state->save();

            $rule = 'D5: retrieval check passed';
            $action = 'retained';
        } else {
            $state->status = LearnerNodeState::STATUS_LEARNING;
            $state->mastery_estimate = max(0.0, $state->mastery_estimate - self::MASTERY_STEP);
            $state->next_review_at = null;
            $state->save();

            $rule = 'D5: retrieval check failed, re-loop this node only';
            $action = 'reloop_node';
        }

        $this->log($studentId, $conceptId, $nodeId, $subInstituteId, ['responses' => $responses], $rule, $action);

        return [
            'action' => $action,
            'node_id' => $nodeId,
            'concept_id' => $conceptId,
            'status' => $state->status,
            'rule_fired' => 'D5',
            'llm_instruction' => null,
        ];
    }

    // ── Student dashboard — chapterDashboard() with the chapter auto-picked ──

    /**
     * The main-dashboard variant of chapterDashboard(): no {chapterId} in the
     * URL, so this picks the single most relevant chapter across the
     * student's whole enrollment for the given academic year — the first
     * ESO-ready chapter (subject order, then chapter sort_order) that still
     * has open work, falling back to the first ESO-ready chapter at all if
     * every one of them is already complete — then returns the exact same
     * shape chapterDashboard() does.
     *
     * Returns null when the student has no enrollment for $syear.
     * Returns ['no_content' => true] when the student's curriculum has no
     * ESO-ready chapter anywhere yet (a real, honest state — Phase 0 tagging
     * hasn't reached any of their subjects — not an error).
     */
    public function studentDashboard(int $studentId, int $subInstituteId, string $syear): ?array
    {
        $standardId = DB::table('tblstudent_enrollment')
            ->where('student_id', $studentId)
            ->where('syear', $syear)
            ->whereNull('end_date')
            ->value('standard_id');

        if ($standardId === null) {
            return null;
        }

        $subjectIds = DB::table('sub_std_map')
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->orderBy('sort_order')
            ->pluck('subject_id')
            ->all();

        if ($subjectIds === []) {
            return ['no_content' => true];
        }

        // Preserve subject order (sub_std_map.sort_order), then chapter
        // sort_order within each subject — matches PalWorkspaceController::
        // workspace()'s subject/chapter resolution so "current chapter" here
        // agrees with how the student's own /pal subject list is ordered.
        $chapterIds = DB::table('chapter_master')
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $standardId)
            ->whereIn('subject_id', $subjectIds)
            ->orderByRaw('FIELD(subject_id, ' . implode(',', $subjectIds) . ')')
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        if ($chapterIds === []) {
            return ['no_content' => true];
        }

        $readyChapterIds = array_flip(
            $this->esoReadyConceptsForChapters($chapterIds, $subInstituteId)->pluck('chapter_id')->unique()->all()
        );
        $orderedReadyChapterIds = array_values(array_filter($chapterIds, fn ($id) => isset($readyChapterIds[$id])));

        if ($orderedReadyChapterIds === []) {
            return ['no_content' => true];
        }

        $fallback = null;
        foreach ($orderedReadyChapterIds as $chapterId) {
            $dashboard = $this->chapterDashboard($studentId, $chapterId, $subInstituteId);
            if ($dashboard === null) {
                continue;
            }
            if ($fallback === null) {
                $fallback = $dashboard;
            }
            if (! $dashboard['chapter_complete']) {
                return $dashboard;
            }
        }

        return $fallback ?? ['no_content' => true];
    }

    // ── Chapter dashboard — read-only aggregate for the "where am I" screen ──

    /**
     * Everything the chapter-level student dashboard needs in one call:
     * chapter identity, every ESO-ready concept in it with a lock/mastery
     * status, the current concept's next action (via nextAction(...,
     * silent: true) — the exact resolver the per-concept flow uses, without
     * writing a decision-log row for a plain page view), response counts,
     * and the best-effort mastery-signal panel (see masterySignals()).
     * Returns null when the chapter itself doesn't exist.
     */
    public function chapterDashboard(int $studentId, int $chapterId, int $subInstituteId): ?array
    {
        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first(['id', 'chapter_name', 'subject_id', 'standard_id']);
        if ($chapter === null) {
            return null;
        }

        $subjectName = DB::table('subject')->where('id', $chapter->subject_id)->value('subject_name');
        $readyConcepts = $this->esoReadyConceptsForChapters([$chapterId], $subInstituteId);

        $sections = [];
        $currentConceptId = null;

        foreach ($readyConcepts as $concept) {
            $conceptId = (int) $concept->id;
            $classification = $this->conceptStatusFor($studentId, $conceptId, $subInstituteId);

            $sections[] = [
                'concept_id' => $conceptId,
                'name' => $concept->name,
                'status' => $classification['status'],
                'knowledge_mastery' => $classification['knowledge_mastery'],
                'application_mastery' => $classification['application_mastery'],
            ];

            if ($currentConceptId === null && ! in_array($classification['status'], ['locked', 'mastered'], true)) {
                $currentConceptId = $conceptId;
            }
        }

        // Every concept locked or mastered: fall back to the last ready
        // concept so the page still has something to show.
        if ($currentConceptId === null && $readyConcepts->isNotEmpty()) {
            $currentConceptId = (int) $readyConcepts->last()->id;
        }

        $nextStep = null;
        $currentConceptName = null;
        $responsesOnCurrentConcept = 0;
        $masterySignals = [];
        $chapterComplete = $sections !== [] && collect($sections)->every(fn (array $s) => $s['status'] === 'mastered');

        if ($currentConceptId !== null) {
            $currentConceptName = optional($readyConcepts->firstWhere('id', $currentConceptId))->name;
            $action = $this->nextAction($studentId, $currentConceptId, $subInstituteId, silent: true);
            $prerequisiteName = isset($action['prerequisite_concept_id'])
                ? DB::table('lms_concept')->where('id', $action['prerequisite_concept_id'])->value('name')
                : null;
            $responsesOnCurrentConcept = $this->conceptAttemptCount($studentId, $currentConceptId, $subInstituteId);

            $nextStep = array_merge(
                EsoPalRenderer::dashboardNextStep($action['action'], $currentConceptName, $prerequisiteName),
                [
                    'action' => $action['action'],
                    'rule_fired' => $action['rule_fired'],
                    'has_evidence' => $responsesOnCurrentConcept > 0,
                ]
            );
            $masterySignals = $this->masterySignals($studentId, $currentConceptId, $subInstituteId);
        }

        [$masteredInCurriculum, $totalInCurriculum] = $this->curriculumMasteryCount(
            $studentId,
            (int) $chapter->subject_id,
            (int) $chapter->standard_id,
            $subInstituteId
        );

        return [
            'chapter_id' => $chapterId,
            'chapter_name' => $chapter->chapter_name,
            'subject_id' => (int) $chapter->subject_id,
            'subject_name' => $subjectName,
            'chapter_complete' => $chapterComplete,
            'current_concept_id' => $currentConceptId,
            'current_concept_name' => $currentConceptName,
            'mastered_concepts' => $masteredInCurriculum,
            'total_concepts_in_curriculum' => $totalInCurriculum,
            'responses_on_current_concept' => $responsesOnCurrentConcept,
            'all_responses' => $this->allResponsesCount($studentId),
            'next_step' => $nextStep,
            'chapter_sections' => $sections,
            'mastery_signals' => $masterySignals,
        ];
    }

    /**
     * Real ESO status for one concept, for one student — locked / not_started
     * / in_progress / mastered, or 'not_ready' when the concept has no K/A/S
     * nodes authored at all (nothing to gate or master yet — a real, honest
     * state, not an error). Shared by chapterDashboard() and knowledgeMap()
     * so both agree on exactly the same classification.
     */
    protected function conceptStatusFor(int $studentId, int $conceptId, int $subInstituteId): array
    {
        $nodes = $this->nodesForConcept($conceptId, $subInstituteId);
        if ($nodes->isEmpty()) {
            return [
                'status' => 'not_ready',
                'knowledge_mastery' => null,
                'application_mastery' => null,
                'responses' => 0,
            ];
        }

        $locked = ! $this->prerequisitesMet($studentId, $conceptId, $subInstituteId);
        $verdict = $locked ? null : $this->masteryVerdict($studentId, $conceptId, $subInstituteId, silent: true);
        $mastered = $verdict['mastered'] ?? false;
        $responses = $this->conceptAttemptCount($studentId, $conceptId, $subInstituteId);

        return [
            'status' => $locked ? 'locked' : ($mastered ? 'mastered' : ($responses > 0 ? 'in_progress' : 'not_started')),
            'knowledge_mastery' => $verdict['knowledge_mastery'] ?? null,
            'application_mastery' => $verdict['application_mastery'] ?? null,
            'responses' => $responses,
        ];
    }

    // ── Knowledge map — the whole chapter's real concept-relationship graph ──

    /**
     * The whole chapter's ESO-ready concepts as one connected graph, with the
     * requested concept marked `is_current` — matches the shape of a real
     * curriculum map (a single concept's 2-4 immediate neighbors reads as a
     * stub, not a map). Edges are read straight from `pal_concept_relations`,
     * scoped to pairs that are BOTH in this chapter (same "no link crosses a
     * chapter boundary" convention the existing Coherence Map already uses on
     * this data) — `requires` becomes a direct-prerequisite edge (drawn
     * prerequisite → dependent), `cross_curricular` becomes an undirected
     * related edge. Every concept's status/response/misconception counts are
     * real, computed the same way chapterDashboard() computes them
     * (conceptStatusFor(), the same ESO pipeline the rest of the student
     * dashboard uses — not the separate BKT/Coherence-Map mastery pipeline).
     *
     * Returns null when the concept doesn't exist or isn't itself ESO-ready.
     */
    public function chapterKnowledgeMap(int $studentId, int $conceptId, int $subInstituteId): ?array
    {
        $conceptRow = DB::table('lms_concept')->where('id', $conceptId)->first(['id', 'chapter_id']);
        if ($conceptRow === null) {
            return null;
        }

        $chapterId = (int) $conceptRow->chapter_id;
        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first(['id', 'chapter_name', 'chapter_desc']);
        if ($chapter === null) {
            return null;
        }

        $readyConcepts = $this->esoReadyConceptsForChapters([$chapterId], $subInstituteId);
        $conceptIds = $readyConcepts->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! in_array($conceptId, $conceptIds, true)) {
            return null;
        }

        $misconceptionCounts = MisconceptionLibrary::whereIn('concept_ref_id', $conceptIds)
            ->selectRaw('concept_ref_id, count(*) as n')
            ->groupBy('concept_ref_id')
            ->pluck('n', 'concept_ref_id');

        $classifications = [];
        foreach ($conceptIds as $id) {
            $classifications[$id] = $this->conceptStatusFor($studentId, $id, $subInstituteId);
        }

        $relations = ConceptRelation::whereIn('from_concept_id', $conceptIds)
            ->whereIn('to_concept_id', $conceptIds)
            ->whereIn('relation_type', ['requires', 'cross_curricular'])
            ->forTenant($subInstituteId)
            ->get(['from_concept_id', 'to_concept_id', 'relation_type']);

        // `from_concept_id` requires `to_concept_id`: from = the dependent
        // concept, to = its prerequisite. Drawn prerequisite -> dependent.
        $prerequisitesOf = [];
        $edges = [];
        $seenEdgeKeys = [];
        foreach ($relations as $r) {
            $dependent = (int) $r->from_concept_id;
            $prerequisite = (int) $r->to_concept_id;

            if ($r->relation_type === 'requires') {
                $prerequisitesOf[$dependent][] = $prerequisite;
                $key = "prereq:{$prerequisite}:{$dependent}";
                if (! isset($seenEdgeKeys[$key])) {
                    $seenEdgeKeys[$key] = true;
                    $edges[] = ['from_concept_id' => $prerequisite, 'to_concept_id' => $dependent, 'type' => 'direct_prerequisite'];
                }
            } else {
                // cross_curricular has no meaningful direction for display — dedupe both orders.
                $key = 'related:' . min($dependent, $prerequisite) . ':' . max($dependent, $prerequisite);
                if (! isset($seenEdgeKeys[$key])) {
                    $seenEdgeKeys[$key] = true;
                    $edges[] = ['from_concept_id' => $dependent, 'to_concept_id' => $prerequisite, 'type' => 'related'];
                }
            }
        }

        // Longest prerequisite chain beneath each concept — the vertical
        // layout axis. Cycle-safe: pal_concept_relations is not guaranteed
        // acyclic (see CoherenceMapRepository's own note on this same table),
        // so a node currently being visited contributes depth 0, not infinity.
        $depthMemo = [];
        $computeDepth = function (int $id, array $visiting = []) use (&$computeDepth, &$depthMemo, $prerequisitesOf): int {
            if (isset($depthMemo[$id])) {
                return $depthMemo[$id];
            }
            if (isset($visiting[$id])) {
                return 0;
            }
            $visiting[$id] = true;

            $depth = 0;
            foreach ($prerequisitesOf[$id] ?? [] as $prerequisiteId) {
                $depth = max($depth, 1 + $computeDepth($prerequisiteId, $visiting));
            }

            return $depthMemo[$id] = $depth;
        };

        // "Right now X stays closed, because it needs Y" — real, from the
        // same prerequisite check nextAction()/prerequisitesMet() already
        // use. Computed per concept (for its own card's reason) AND
        // aggregated (for the page-level summary sentence).
        $lockedNames = [];
        $blockingNames = [];
        $blockingNamesByConceptId = [];
        foreach ($conceptIds as $id) {
            if ($classifications[$id]['status'] !== 'locked') {
                continue;
            }
            $unmetIds = $this->unmetPrerequisiteConceptIds($id, $studentId, $subInstituteId);
            $names = $unmetIds->isEmpty() ? [] : DB::table('lms_concept')->whereIn('id', $unmetIds)->pluck('name')->all();
            $blockingNamesByConceptId[$id] = $names;
            $blockingNames = array_merge($blockingNames, $names);
        }

        $concepts = [];
        foreach ($readyConcepts as $concept) {
            $id = (int) $concept->id;
            $classification = $classifications[$id];
            $status = $classification['status'];

            // A "mastered" concept whose every node has independently survived
            // a D5 spaced-retrieval check reads as "Retained" rather than
            // "Mastered" — same underlying eso_learner_node_state.status D5
            // already writes (STATUS_RETAINED), just surfaced as a distinct
            // concept-level label here. Does not change chapterDashboard()'s
            // own status contract or any D1-D5 behavior.
            if ($status === 'mastered' && $this->isConceptRetained($studentId, $id, $subInstituteId)) {
                $status = 'retained';
            }

            $lockedNames[] = $status === 'locked' ? $concept->name : null;

            $concepts[] = [
                'concept_id' => $id,
                'name' => $concept->name,
                'status' => $status,
                'responses' => $classification['responses'],
                'misconception_count' => (int) ($misconceptionCounts[$id] ?? 0),
                'depth' => $computeDepth($id),
                'is_current' => $id === $conceptId,
                'blocking_prerequisite_names' => $blockingNamesByConceptId[$id] ?? [],
            ];
        }
        $lockedNames = array_values(array_unique(array_filter($lockedNames)));

        return [
            'chapter_id' => $chapterId,
            'chapter_name' => $chapter->chapter_name,
            'chapter_description' => $chapter->chapter_desc !== null && trim((string) $chapter->chapter_desc) !== '' ? $chapter->chapter_desc : null,
            'current_concept_id' => $conceptId,
            'concepts' => $concepts,
            'edges' => $edges,
            'locked_concept_names' => $lockedNames,
            'blocking_prerequisite_names' => array_values(array_unique($blockingNames)),
            'stats' => [
                'concepts' => count($concepts),
                'direct_prerequisites' => count(array_filter($edges, fn (array $e) => $e['type'] === 'direct_prerequisite')),
                'related' => count(array_filter($edges, fn (array $e) => $e['type'] === 'related')),
                'misconceptions' => array_sum(array_column($concepts, 'misconception_count')),
            ],
        ];
    }

    /**
     * True when every node of this concept has independently reached
     * STATUS_RETAINED (survived a D5 spaced-retrieval check) — a real,
     * already-written signal, just read here rather than mutated. A concept
     * with zero nodes, or any node not yet retained, is not retained.
     */
    protected function isConceptRetained(int $studentId, int $conceptId, int $subInstituteId): bool
    {
        $nodeIds = $this->nodesForConcept($conceptId, $subInstituteId)->pluck('id');
        if ($nodeIds->isEmpty()) {
            return false;
        }

        $retainedCount = LearnerNodeState::forStudent($studentId)
            ->whereIn('node_id', $nodeIds)
            ->where('status', LearnerNodeState::STATUS_RETAINED)
            ->count();

        return $retainedCount === $nodeIds->count();
    }

    /**
     * ESO-ready concepts (>=1 pal_concept_nodes row) across a set of
     * chapters, in lms_concept.id order. Shared by chapterDashboard() and
     * EsoEngineController::chapterConcepts() so both agree on the exact same
     * "is this concept ESO-ready" join instead of duplicating it.
     */
    public function esoReadyConceptsForChapters(array $chapterIds, ?int $subInstituteId): Collection
    {
        $concepts = collect($this->evidence->conceptsForChapters($chapterIds, $subInstituteId));
        if ($concepts->isEmpty()) {
            return collect();
        }

        $readyIds = ConceptNode::whereIn('concept_id', $concepts->pluck('id'))
            ->forTenant($subInstituteId)
            ->distinct()
            ->pluck('concept_id')
            ->all();

        return $concepts->whereIn('id', $readyIds)->sortBy('id')->values()->map(fn ($c) => (object) $c);
    }

    /** Total attempts recorded across a concept's node states for one student. */
    protected function conceptAttemptCount(int $studentId, int $conceptId, int $subInstituteId): int
    {
        $nodeIds = $this->nodesForConcept($conceptId, $subInstituteId)->pluck('id');
        if ($nodeIds->isEmpty()) {
            return 0;
        }

        return (int) LearnerNodeState::forStudent($studentId)->whereIn('node_id', $nodeIds)->sum('attempts');
    }

    /** Total attempts recorded across every node state for one student, any concept. */
    protected function allResponsesCount(int $studentId): int
    {
        return (int) LearnerNodeState::forStudent($studentId)->sum('attempts');
    }

    /** [masteredCount, totalCount] of ESO-ready concepts across every chapter of a subject+standard. */
    protected function curriculumMasteryCount(int $studentId, int $subjectId, int $standardId, int $subInstituteId): array
    {
        $chapterIds = DB::table('chapter_master')
            ->where('subject_id', $subjectId)
            ->where('standard_id', $standardId)
            ->pluck('id')
            ->all();

        if ($chapterIds === []) {
            return [0, 0];
        }

        $concepts = $this->esoReadyConceptsForChapters($chapterIds, $subInstituteId);
        $mastered = 0;

        foreach ($concepts as $concept) {
            $verdict = $this->masteryVerdict($studentId, (int) $concept->id, $subInstituteId, silent: true);
            if ($verdict['mastered']) {
                $mastered++;
            }
        }

        return [$mastered, $concepts->count()];
    }

    /**
     * Best-effort "What PAL has seen so far" panel. None of these six labels
     * correspond to a field the schema tracks directly — each is a
     * deliberate mapping onto real per-node evidence (documented per-signal
     * below), not a literal existing metric. A signal reports `value: null`
     * ("not enough evidence") until at least one node it reads from has
     * MIN_ATTEMPTS_FOR_EVIDENCE attempts.
     */
    protected function masterySignals(int $studentId, int $conceptId, int $subInstituteId): array
    {
        $nodes = $this->nodesForConcept($conceptId, $subInstituteId);
        $states = LearnerNodeState::forStudent($studentId)->whereIn('node_id', $nodes->pluck('id'))->get()->keyBy('node_id');

        $statesOfType = fn (string $type) => $nodes->where('node_type', $type)
            ->map(fn (ConceptNode $n) => $states->get($n->id))
            ->filter();

        $kStates = $statesOfType('K');
        $aStates = $statesOfType('A');
        $sStates = $statesOfType('S');
        $allStates = $states->values();

        return [
            // Procedural correctness on the Knowledge node.
            $this->signal('getting_method_right', 'Getting the method right', 'Carrying out the method accurately.', $this->averageEstimate($kStates), $this->totalAttempts($kStates)),
            // Conceptual correctness on the Application node.
            $this->signal('understanding_the_idea', 'Understanding the idea', 'Knowing why the method works, not just that it does.', $this->averageEstimate($aStates), $this->totalAttempts($aStates)),
            // Correctness on the Skill node (the concept's "demonstrate it" practice).
            $this->signal('explaining_your_thinking', 'Explaining your thinking', 'Justifying, critiquing and convincing.', $this->averageEstimate($sStates), $this->totalAttempts($sStates)),
            // Share of Application attempts made once practice had already advanced to independent mode (no guided scaffolding).
            $this->signal('using_it_somewhere_new', 'Using it somewhere new', 'Applying the idea in a new or unfamiliar context.', $this->independentShare($aStates), $this->totalAttempts($aStates)),
            // 1 - hint rate, across every node of the concept.
            $this->signal('working_without_support', 'Working without support', 'Getting it right without hints or scaffolding.', $this->hintFreeShare($allStates), $this->totalAttempts($allStates)),
            // Share of nodes that survived a spaced-retrieval check, falling back to the current correct-streak ratio before any node has been retested.
            $this->signal('doing_it_reliably', 'Doing it reliably', 'Getting it right consistently, not just once.', $this->reliabilityShare($allStates), $this->totalAttempts($allStates)),
        ];
    }

    protected function signal(string $key, string $label, string $description, ?float $value, int $responseCount): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'value' => $value,
            'has_evidence' => $value !== null,
            'response_count' => $responseCount,
        ];
    }

    /** @param  Collection<int, LearnerNodeState>  $states */
    protected function totalAttempts(Collection $states): int
    {
        return (int) $states->sum('attempts');
    }

    /** @param  Collection<int, LearnerNodeState>  $states */
    protected function evidencedStates(Collection $states): Collection
    {
        return $states->filter(fn (LearnerNodeState $s) => $s->attempts >= self::MIN_ATTEMPTS_FOR_EVIDENCE);
    }

    protected function averageEstimate(Collection $states): ?float
    {
        $evidenced = $this->evidencedStates($states);

        return $evidenced->isEmpty() ? null : round((float) $evidenced->avg('mastery_estimate'), 3);
    }

    protected function independentShare(Collection $states): ?float
    {
        $evidenced = $this->evidencedStates($states);
        if ($evidenced->isEmpty()) {
            return null;
        }

        $independent = $evidenced->filter(fn (LearnerNodeState $s) => $s->practice_mode === LearnerNodeState::MODE_INDEPENDENT)->count();

        return round($independent / $evidenced->count(), 3);
    }

    protected function hintFreeShare(Collection $states): ?float
    {
        $evidenced = $this->evidencedStates($states);
        if ($evidenced->isEmpty()) {
            return null;
        }

        $totalAttempts = $evidenced->sum('attempts');
        if ($totalAttempts <= 0) {
            return null;
        }

        return round(max(0.0, 1 - ($evidenced->sum('hint_used_count') / $totalAttempts)), 3);
    }

    protected function reliabilityShare(Collection $states): ?float
    {
        $evidenced = $this->evidencedStates($states);
        if ($evidenced->isEmpty()) {
            return null;
        }

        $retained = $evidenced->filter(fn (LearnerNodeState $s) => $s->status === LearnerNodeState::STATUS_RETAINED)->count();
        if ($retained > 0) {
            return round($retained / $evidenced->count(), 3);
        }

        // No node has survived a spaced-retrieval check yet — the correct-
        // in-a-row streak against the practice-mode-advance threshold is an
        // interim reliability signal instead of reporting "no evidence" the
        // moment a student starts practicing.
        $streakRatio = $evidenced->avg(fn (LearnerNodeState $s) => min(1.0, $s->consecutive_correct / self::CONSECUTIVE_CORRECT_TO_ADVANCE));

        return round((float) $streakRatio, 3);
    }

    // ── Concept mastery details — the "Mastery details" modal ───────────

    /**
     * Everything the "Mastery details" modal for one concept needs: status,
     * an honest confidence note, the same 6 mastery signals chapterDashboard()
     * uses (with description + response_count), a guided-vs-independent
     * support split, this concept's misconception history, and its most
     * recent individual responses. Returns null when the concept doesn't
     * exist or has no K/A/S nodes authored yet.
     */
    public function conceptMasteryDetails(int $studentId, int $conceptId, int $subInstituteId): ?array
    {
        $conceptRow = DB::table('lms_concept')->where('id', $conceptId)->first(['id', 'name', 'chapter_id']);
        if ($conceptRow === null) {
            return null;
        }

        $nodes = $this->nodesForConcept($conceptId, $subInstituteId);
        if ($nodes->isEmpty()) {
            return null;
        }

        $locked = ! $this->prerequisitesMet($studentId, $conceptId, $subInstituteId);
        $verdict = $locked ? null : $this->masteryVerdict($studentId, $conceptId, $subInstituteId, silent: true);
        $mastered = $verdict['mastered'] ?? false;
        $responses = $this->conceptAttemptCount($studentId, $conceptId, $subInstituteId);

        $status = $locked ? 'locked' : ($mastered ? 'mastered' : ($responses > 0 ? 'in_progress' : 'not_started'));

        $support = ResponseLog::forStudent($studentId)->forConcept($conceptId)->get(['hint_used', 'correct']);
        $withHint = $support->where('hint_used', true);
        $independent = $support->where('hint_used', false);

        return [
            'concept_id' => $conceptId,
            'concept_name' => $conceptRow->name,
            'chapter_id' => (int) $conceptRow->chapter_id,
            'status' => $status,
            'responses_on_concept' => $responses,
            'confidence_note' => $this->confidenceNote($responses),
            'mastery_signals' => $this->masterySignals($studentId, $conceptId, $subInstituteId),
            'support_with_hint' => ['count' => $withHint->count(), 'correct' => $withHint->where('correct', true)->count()],
            'support_independent' => ['count' => $independent->count(), 'correct' => $independent->where('correct', true)->count()],
            'misconceptions' => $this->misconceptionHistory($studentId, $conceptId),
            'recent_responses' => $this->recentResponses($studentId, $conceptId),
        ];
    }

    /**
     * Plain, honest evidence framing — no invented confidence tiers, and
     * deliberately no recency-decay claim (the update rule has none, see
     * applyUpdate()'s docblock — a "recent work counts for more" line would
     * misrepresent how mastery_estimate actually moves).
     */
    protected function confidenceNote(int $responses): string
    {
        $base = $responses === 0
            ? 'Based on 0 recorded responses on this concept — no confidence yet.'
            : sprintf('Based on %d recorded response%s on this concept.', $responses, $responses === 1 ? '' : 's');

        return $base . ' This is an inference from your responses, not a measurement of what you know.';
    }

    /**
     * `misconception_corrected` rows don't carry a misconception_id in their
     * own state_snapshot (only `serve_contrast_pair` does — see
     * checkMisconception()/contrastPairAction()), so a correction is
     * correlated to its misconception via node_id instead: walk the concept's
     * D3 decisions chronologically, tracking which misconception is
     * currently active on each node, and flip that misconception's
     * `corrected` flag when its node's contrast pair is cleanly resolved.
     *
     * @return array<int, array{description:string, corrected:bool, detected_at:?string}>
     */
    protected function misconceptionHistory(int $studentId, int $conceptId): array
    {
        $rows = DecisionLog::forStudent($studentId)
            ->forConcept($conceptId)
            ->whereIn('action', ['serve_contrast_pair', 'misconception_corrected'])
            ->orderBy('id') // chronological — oldest first
            ->get(['action', 'node_id', 'state_snapshot', 'created_at']);

        $activeMisconceptionByNode = [];
        $entries = [];

        foreach ($rows as $row) {
            if ($row->action === 'serve_contrast_pair') {
                $misconceptionId = $row->state_snapshot['misconception_id'] ?? null;
                if ($misconceptionId === null) {
                    continue;
                }
                $activeMisconceptionByNode[$row->node_id] = $misconceptionId;
                if (! isset($entries[$misconceptionId])) {
                    $library = MisconceptionLibrary::find($misconceptionId);
                    $entries[$misconceptionId] = [
                        'description' => $library->description ?? 'A specific mix-up in this concept.',
                        'corrected' => false,
                        'detected_at' => optional($row->created_at)->toIso8601String(),
                    ];
                }
            } elseif (isset($activeMisconceptionByNode[$row->node_id], $entries[$activeMisconceptionByNode[$row->node_id]])) {
                $entries[$activeMisconceptionByNode[$row->node_id]]['corrected'] = true;
            }
        }

        return array_values($entries);
    }

    /** Last 10 individual responses for this concept, newest first. */
    protected function recentResponses(int $studentId, int $conceptId): array
    {
        $rows = ResponseLog::forStudent($studentId)
            ->forConcept($conceptId)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['question_id', 'correct', 'created_at']);

        $questionIds = $rows->pluck('question_id')->filter()->unique()->all();
        $titles = $questionIds === [] ? [] : DB::table('lms_question_master')
            ->whereIn('id', $questionIds)
            ->pluck('question_title', 'id')
            ->all();

        return $rows->map(fn ($row) => [
            'question' => $titles[$row->question_id] ?? 'Question',
            'correct' => (bool) $row->correct,
            'at' => optional($row->created_at)->toIso8601String(),
        ])->all();
    }

    // ── shared internals ─────────────────────────────────────────────────

    protected function applyUpdate(LearnerNodeState $state, bool $correct, float $weight = 1.0): void
    {
        $delta = ($correct ? self::MASTERY_STEP : -self::MASTERY_STEP) * $weight;
        $state->mastery_estimate = max(0.0, min(1.0, $state->mastery_estimate + $delta));
        $state->attempts++;

        if ($correct) {
            $state->consecutive_correct++;
            if ($state->consecutive_correct >= self::CONSECUTIVE_CORRECT_TO_ADVANCE
                && $state->practice_mode === LearnerNodeState::MODE_GUIDED) {
                $state->practice_mode = LearnerNodeState::MODE_INDEPENDENT;
            }
        } else {
            $state->consecutive_correct = 0;
        }

        if ($state->status === LearnerNodeState::STATUS_UNSEEN) {
            $state->status = LearnerNodeState::STATUS_LEARNING;
        }

        $state->last_seen_at = now();
        $state->save();
    }

    protected function stateFor(int $studentId, int $nodeId, int $subInstituteId): LearnerNodeState
    {
        return LearnerNodeState::firstOrCreate(
            ['student_id' => $studentId, 'node_id' => $nodeId],
            [
                'sub_institute_id' => $subInstituteId,
                'status' => LearnerNodeState::STATUS_UNSEEN,
                'practice_mode' => LearnerNodeState::MODE_GUIDED,
                // Explicit, not left to the DB default: firstOrCreate() returns
                // the in-memory model without re-reading DB defaults, so an
                // omitted 'attempts' here is null (not 0) until the next
                // reload — and teachOrPracticeAction()'s `attempts === 0`
                // first-exposure check is a strict comparison that null fails,
                // misrouting a node's first appearance to 'practice' instead
                // of 'teach'.
                'attempts' => 0,
                'consecutive_correct' => 0,
            ]
        );
    }

    protected function nodesForConcept(int $conceptId, int $subInstituteId): Collection
    {
        return ConceptNode::forConcept($conceptId)->forTenant($subInstituteId)->orderBy('sort_order')->get();
    }

    protected function priorNodeLabels(ConceptNode $node): Collection
    {
        return ConceptNode::where('concept_id', $node->concept_id)
            ->where('sort_order', '<', $node->sort_order)
            ->orderBy('sort_order')
            ->pluck('label');
    }

    protected function respond(string $action, ?int $conceptId, ?int $nodeId, string $ruleFired, ?string $llmInstruction): array
    {
        return [
            'action' => $action,
            'concept_id' => $conceptId,
            'node_id' => $nodeId,
            'rule_fired' => $ruleFired,
            'llm_instruction' => $llmInstruction,
        ];
    }

    /** Write one decision-log row. Public so controllers can log outside a resolved action too (e.g. render events). */
    public function log(int $studentId, ?int $conceptId, ?int $nodeId, int $subInstituteId, array $stateSnapshot, string $ruleFired, string $action, ?string $llmInstruction = null): DecisionLog
    {
        return DecisionLog::create([
            'student_id' => $studentId,
            'concept_id' => $conceptId,
            'node_id' => $nodeId,
            'sub_institute_id' => $subInstituteId,
            'state_snapshot' => $stateSnapshot,
            'rule_fired' => $ruleFired,
            'action' => $action,
            'llm_instruction' => $llmInstruction,
        ]);
    }
}
