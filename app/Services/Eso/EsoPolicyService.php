<?php

namespace App\Services\Eso;

use App\Models\Eso\DecisionLog;
use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\ConceptNode;
use App\Models\PAL\ConceptRelation;
use App\Models\PAL\MisconceptionLibrary;
use App\Models\PAL\QuestionMetadata;
use App\Services\PAL\Content\MisconceptionLibraryService;
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

    public function __construct(
        protected MisconceptionLibraryService $misconceptions,
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
                $correct = $this->isAnswerCorrect((int) $response['answer_master_id']);
                $this->applyUpdate($state, $correct, weight: 2.0);
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
    public function nextAction(int $studentId, int $conceptId, int $subInstituteId): array
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
            $this->log($studentId, $conceptId, null, $subInstituteId, [], 'D1: concept entry, no diagnostic on file', 'diagnostic');

            return $this->respond('diagnostic', $conceptId, null, 'D1', null);
        }

        // D2: prerequisite gate.
        $gate = $this->prerequisiteGate($studentId, $conceptId, $subInstituteId);
        if ($gate !== null) {
            return $gate;
        }

        foreach ($nodes as $node) {
            $state = $states->get($node->id) ?? $this->stateFor($studentId, $node->id, $subInstituteId);

            if ($state->status === LearnerNodeState::STATUS_MISCONCEPTION_FLAGGED) {
                return $this->reserveContrastPairAction($studentId, $conceptId, $node, $state, $subInstituteId);
            }

            if ($state->status === LearnerNodeState::STATUS_MASTERED
                && $state->next_review_at !== null
                && $state->next_review_at->lte(now())) {
                return $this->retrievalDueAction($studentId, $conceptId, $node, $subInstituteId);
            }

            if ($state->isMastered() || $this->hasSatisfiedOwnThreshold($node, $state)) {
                continue;
            }

            return $this->teachOrPracticeAction($studentId, $conceptId, $node, $state, $subInstituteId);
        }

        // Every node mastered or retained: run the concept-level D4 verdict
        // (idempotent — if already mastered, this just confirms/stops practice).
        return $this->masteryVerdict($studentId, $conceptId, $subInstituteId);
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
    protected function prerequisiteGate(int $studentId, int $conceptId, int $subInstituteId): ?array
    {
        $prerequisiteConceptIds = ConceptRelation::where('from_concept_id', $conceptId)
            ->where('relation_type', 'requires')
            ->forTenant($subInstituteId)
            ->pluck('to_concept_id');

        foreach ($prerequisiteConceptIds as $prerequisiteConceptId) {
            $prerequisiteConceptId = (int) $prerequisiteConceptId;
            $mastery = $this->conceptMasteryAverage($studentId, $prerequisiteConceptId, $subInstituteId);

            // No K/A/S nodes authored for the prerequisite yet — cannot gate on
            // data that doesn't exist; skip rather than block indefinitely.
            if ($mastery === null) {
                continue;
            }

            if ($mastery < self::PREREQUISITE_THRESHOLD) {
                $rule = sprintf('D2: prerequisite concept %d mastery %.2f < %.2f', $prerequisiteConceptId, $mastery, self::PREREQUISITE_THRESHOLD);

                $this->log(
                    $studentId,
                    $conceptId,
                    null,
                    $subInstituteId,
                    ['prerequisite_concept_id' => $prerequisiteConceptId, 'prerequisite_mastery' => $mastery],
                    $rule,
                    'remediate_prerequisite'
                );

                return [
                    'action' => 'remediate_prerequisite',
                    'concept_id' => $conceptId,
                    'prerequisite_concept_id' => $prerequisiteConceptId,
                    'rule_fired' => 'D2',
                    'llm_instruction' => null,
                ];
            }
        }

        return null;
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
    protected function reserveContrastPairAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId): array
    {
        if ($state->active_misconception_id === null) {
            // Defensive: flagged with no recorded misconception id should not happen,
            // but fail safe into ordinary teaching rather than a dead end.
            $state->status = LearnerNodeState::STATUS_LEARNING;
            $state->save();

            return $this->teachOrPracticeAction($studentId, $conceptId, $node, $state, $subInstituteId);
        }

        return $this->contrastPairAction($studentId, $conceptId, $node, (int) $state->active_misconception_id, $subInstituteId, firstFlag: false);
    }

    protected function contrastPairAction(int $studentId, int $conceptId, ConceptNode $node, int $misconceptionId, int $subInstituteId, bool $firstFlag): array
    {
        $misconception = MisconceptionLibrary::find($misconceptionId);
        $corrective = $this->misconceptions->selectCorrective($misconceptionId, $studentId, $subInstituteId);

        $instruction = EsoPalRenderer::contrastPairInstruction($node, $misconception, $corrective);

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

    protected function teachOrPracticeAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId): array
    {
        $firstExposure = $state->attempts === 0;
        $instruction = EsoPalRenderer::teachInstruction($node, $state, $this->priorNodeLabels($node));

        $action = $firstExposure ? 'teach' : 'practice';
        $rule = $firstExposure
            ? 'D1: node not yet taught'
            : sprintf('D4: mastery %.2f, mode=%s, continue practice', $state->mastery_estimate, $state->practice_mode);

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
    protected function retrievalDueAction(int $studentId, int $conceptId, ConceptNode $node, int $subInstituteId): array
    {
        $this->log(
            $studentId,
            $conceptId,
            $node->id,
            $subInstituteId,
            ['next_review_at' => now()->toDateTimeString()],
            'D5: scheduled retrieval check is due',
            'retrieval_due'
        );

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
        $allCorrect = collect($responses)->every(fn ($r) => $this->isAnswerCorrect((int) $r['answer_master_id']));

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
