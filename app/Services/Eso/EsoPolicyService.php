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
use App\Services\PAL\Gamification\BadgeService;
use App\Services\PAL\Gamification\StreakService;
use App\Services\PAL\Runtime\PalEvidenceRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /** D5 — brief specifies "3-5 days"; 4 is the midpoint. First rung of RETENTION_LADDER_DAYS supersedes this for scheduling. */
    public const RETRIEVAL_DELAY_DAYS = 4;

    /**
     * D5 — the spaced-retention ladder, in days from the moment a node is
     * scheduled: Day 2 → Week 1 → Month 1 → Month 2 → Month 6.
     *
     * `retention_stage` on learner_node_state is the index into this array of
     * the interval a node is CURRENTLY waiting out. Each passed retrieval
     * check advances one rung and schedules the next, longer interval; the
     * ladder is complete once the last rung passes (the node stays `retained`
     * with no further review scheduled). A FAILED check resets the stage to 0
     * — the node's status already drops back to `learning` on that path, so
     * restarting the ladder is the consistent companion behaviour.
     */
    public const RETENTION_LADDER_DAYS = [2, 7, 30, 60, 180];

    /**
     * D2 — evidence for a prerequisite older than this is treated as stale:
     * high enough to not block outright, old enough not to trust silently,
     * so a short probe is inserted instead (see prerequisiteGate()).
     */
    public const PREREQUISITE_STALE_AFTER_DAYS = 30;

    /** D5 — brief specifies "2-3 items". */
    public const RETRIEVAL_ITEM_COUNT = 3;

    /**
     * How many questions the Check-For-Understanding gate serves between
     * teaching a node and starting scored practice on it. Deliberately small —
     * CFU is a "did that land?" check, not an assessment.
     */
    public const CFU_ITEM_COUNT = 2;

    /**
     * Safety valve: after this many failed teach → CFU cycles on one node the
     * engine stops re-teaching and lets the student into ordinary guided
     * practice anyway.
     *
     * Without it a student who cannot pass the CFU is trapped in an infinite
     * reteach loop with no way to accumulate mastery evidence — and because
     * CFU deliberately records no mastery evidence, nothing else would ever
     * move them on. This is a loop guard, not a mastery rule: it decides which
     * screen is served, never whether anything is mastered.
     */
    public const CFU_MAX_CYCLES = 2;

    /** Simple update rule: correct +0.2, wrong -0.2, clamped 0-1. */
    protected const MASTERY_STEP = 0.2;

    /** Chapter dashboard — a node needs at least this many attempts before its mastery signal is shown at all. */
    public const MIN_ATTEMPTS_FOR_EVIDENCE = 1;

    public function __construct(
        protected MisconceptionLibraryService $misconceptions,
        protected PalEvidenceRepository $evidence,
        protected ConceptRelevanceResolver $relevance,
        protected BadgeService $badges,
        protected StreakService $streaks,
        protected EsoLearningContentResolver $learningContent,
        protected EsoEvidenceBridge $evidenceBridge,
        protected EsoEnrichmentResolver $enrichment,
    ) {
    }

    /**
     * Hand a completed ESO operation's scored responses to the school's shared
     * evidence ledger (pal_learning_evidence -> pal_concept_mastery -> Neo4j),
     * via EsoEvidenceBridge.
     *
     * Called at the three OPERATION boundaries — scoreDiagnostic(),
     * recordAttempt(), retrievalCheck() — and nowhere else. It is deliberately
     * not hooked into logResponse(), even though that is the single funnel
     * every scored response already passes through: batching per operation
     * means one BKT replay and one graph round-trip per submitted diagnostic
     * instead of eight.
     *
     * Check-of-understanding responses never reach here — recordCheckUnderstanding()
     * does not call this — which keeps "CFU is not mastery evidence" true all
     * the way out to the graph.
     *
     * @param  array<int, array{question_id:?int, correct:bool, misconception_tag?:?string}>  $responses
     */
    protected function publishEvidence(int $studentId, int $conceptId, int $subInstituteId, array $responses): void
    {
        $this->evidenceBridge->recordResponses($studentId, $conceptId, $subInstituteId, $responses);
    }

    /** The question behind an answer option, for the shared evidence ledger. */
    protected function questionIdFor(int $answerMasterId): ?int
    {
        $value = DB::table('answer_master')->where('id', $answerMasterId)->value('question_id');

        return $value === null ? null : (int) $value;
    }

    /**
     * Hand a just-earned ESO outcome to the existing PAL gamification system.
     *
     * BadgeService::evaluate()/StreakService::recompute() take only a learner
     * id — they re-derive their own evidence (now including this engine's
     * D4/D5 outcomes, see LearnerActivitySource::esoConceptsMastered()), so
     * this is a "something changed, re-check" nudge rather than an award call.
     * Recognition is never allowed to break a learning step: gamification is
     * a side effect of mastery, not a precondition for it, so any failure here
     * is swallowed rather than surfaced to the student mid-flow.
     */
    protected function awardGamification(int $studentId): void
    {
        try {
            $this->badges->evaluate($studentId);
            $this->streaks->recompute($studentId);
        } catch (\Throwable $e) {
            report($e);
        }
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
    public function practiceItem(int $nodeId, int $subInstituteId, ?LearnerNodeState $state = null): ?array
    {
        // v1 picks any tagged, servable MCQ for the node rather than tracking
        // per-student exposure — the pilot's tagged pool per node is small, and
        // an occasional repeat beats a "no items left" dead end. Ordering is
        // difficulty-aware when the caller passes the learner's state (see
        // orderCandidatesByDifficulty()); random otherwise.
        $candidates = QuestionMetadata::forNode($nodeId)
            ->forTenant($subInstituteId)
            ->servable()
            ->get(['question_id', 'difficulty_1_to_5']);

        foreach ($this->orderCandidatesByDifficulty($candidates, $state) as $questionId) {
            // hydrateQuestion() itself enforces MCQ-only and non-empty options.
            $hydrated = $this->hydrateQuestion((int) $questionId);
            if ($hydrated !== null && $hydrated['options'] !== []) {
                return array_merge($hydrated, ['node_id' => $nodeId]);
            }
        }

        return null;
    }

    /**
     * Order a node's servable candidates so practice climbs in difficulty as
     * the student's correct-streak grows, instead of sampling uniformly at
     * random forever.
     *
     * Reuses two things that already exist rather than adding a new axis:
     * `consecutive_correct` (the same field that already advances guided →
     * independent practice at CONSECUTIVE_CORRECT_TO_ADVANCE) and
     * `pal_question_metadata.difficulty_1_to_5`. Items in the preferred band
     * come first, everything else follows in random order — so a node whose
     * pool has no difficulty tagging, or nothing in the preferred band, still
     * serves an item instead of dead-ending.
     *
     * @param  Collection<int, object>  $candidates
     * @return Collection<int, int> question ids, best-fit first
     */
    protected function orderCandidatesByDifficulty(Collection $candidates, ?LearnerNodeState $state): Collection
    {
        if ($state === null) {
            return $candidates->shuffle()->pluck('question_id');
        }

        // 0-1 correct in a row → foundational (1-2); 2-3 → middle (3);
        // 4+ → stretch (4-5). Bands overlap nothing and cover 1-5 exactly.
        $streak = (int) $state->consecutive_correct;
        $preferred = match (true) {
            $streak >= 2 * self::CONSECUTIVE_CORRECT_TO_ADVANCE => [4, 5],
            $streak >= self::CONSECUTIVE_CORRECT_TO_ADVANCE => [3],
            default => [1, 2],
        };

        [$inBand, $rest] = $candidates->shuffle()->partition(
            fn ($row) => $row->difficulty_1_to_5 !== null && in_array((int) $row->difficulty_1_to_5, $preferred, true)
        );

        return $inBand->concat($rest)->pluck('question_id');
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
        $evidence = [];

        foreach ($byNode as $nodeId => $nodeResponses) {
            $nodeId = (int) $nodeId;
            $state = $this->stateFor($studentId, $nodeId, $subInstituteId);

            foreach ($nodeResponses as $response) {
                $answerMasterId = (int) $response['answer_master_id'];
                $correct = $this->isAnswerCorrect($answerMasterId);
                $this->applyUpdate($state, $correct, weight: 2.0);
                $this->logResponse($studentId, $conceptId, $nodeId, $subInstituteId, $answerMasterId, $correct);
                // Collected across every node, published once below — a whole
                // diagnostic is one operation, not eight.
                $evidence[] = ['question_id' => $this->questionIdFor($answerMasterId), 'correct' => $correct];
            }

            $skip = $state->mastery_estimate >= self::SKIP_THRESHOLD;
            $state->status = $skip ? LearnerNodeState::STATUS_MASTERED : LearnerNodeState::STATUS_LEARNING;
            // D1 skip is a mastery path exactly like the D4 verdict (masteryVerdict()
            // schedules D5 retrieval the same way on that path) — without this, a node
            // that skips straight from diagnostic never gets a next_review_at, so D5
            // silently never fires for it. Only reachable end-to-end once every node in
            // a concept has answerable content, which is what surfaced it here.
            // Enters the retention ladder at its first rung (stage 0 = Day 2).
            if ($skip) {
                $this->scheduleRetention($state);
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

        $this->publishEvidence($studentId, $conceptId, $subInstituteId, $evidence);

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

        $states = $this->statesForNodes($studentId, $nodes->pluck('id'))->keyBy('node_id');

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
                return $this->retrievalDueAction($studentId, $conceptId, $node, $state, $subInstituteId, $silent);
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
            // S-type has no accuracy threshold anywhere in this policy —
            // masteryVerdict() judges a concept on its K and A nodes only, and
            // sweeps S into `mastered` alongside them. So "done for now" for an
            // S node is having actually done the transfer task, not clearing a
            // score: the student meets it (attempts === 0 still routes to
            // 'teach' above), and after that it must stop blocking.
            //
            // Returning false here unconditionally — as this did — deadlocked
            // the engine: a fully-practised S node was never `status =
            // mastered` (only the verdict sets that), so the loop returned
            // practice for it forever and never fell through to the verdict
            // that would have mastered the concept and scheduled retention.
            // Reported from QA as "still practicing this node (mastery 100%,
            // 8 attempts)". Giving S its own score threshold would be a new
            // D4 rule; this reuses evidence the policy already records.
            'S' => $state->attempts > 0,
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
        // A prerequisite that CLEARS the threshold but whose evidence is older
        // than PREREQUISITE_STALE_AFTER_DAYS is neither trusted silently nor
        // blocked outright — a short probe re-establishes it first. Checked
        // before the unmet-prerequisite loop so a genuinely weak prerequisite
        // (below threshold) still takes precedence and goes to full remediation.
        $staleProbe = $this->stalePrerequisiteProbe($studentId, $conceptId, $subInstituteId, $silent);
        if ($staleProbe !== null) {
            return $staleProbe;
        }

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
        // Authored curriculum structure, never written by this engine — safe to
        // memoise for the request. The chapter dashboard resolves this once per
        // concept and was re-reading the same relation rows 17 times a page.
        $prerequisiteConceptIds = $this->memo(
            "prereq:{$conceptId}:{$subInstituteId}",
            fn () => ConceptRelation::where('from_concept_id', $conceptId)
                ->where('relation_type', 'requires')
                ->forTenant($subInstituteId)
                ->pluck('to_concept_id')
        );

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

    /**
     * D2, staleness branch: the first prerequisite that PASSES the mastery
     * threshold but whose supporting evidence has gone stale
     * (PREREQUISITE_STALE_AFTER_DAYS since the student last touched any of its
     * nodes). Returns a short `prerequisite_quick_probe` action — one or two
     * items from that prerequisite, not a full re-teach — so an old pass is
     * re-established rather than either trusted blindly or punished with full
     * remediation the student may not need.
     *
     * Returns null when nothing is stale, which is the overwhelmingly common
     * case (fresh evidence, or a prerequisite already below threshold and so
     * handled by the ordinary unmet-prerequisite path instead).
     */
    protected function stalePrerequisiteProbe(int $studentId, int $conceptId, int $subInstituteId, bool $silent = false): ?array
    {
        $prerequisiteConceptIds = ConceptRelation::where('from_concept_id', $conceptId)
            ->where('relation_type', 'requires')
            ->forTenant($subInstituteId)
            ->pluck('to_concept_id');

        foreach ($prerequisiteConceptIds as $prerequisiteConceptId) {
            $prerequisiteConceptId = (int) $prerequisiteConceptId;
            $mastery = $this->conceptMasteryAverage($studentId, $prerequisiteConceptId, $subInstituteId);

            // Not authored, or genuinely below threshold — not this branch's business.
            if ($mastery === null || $mastery < self::PREREQUISITE_THRESHOLD) {
                continue;
            }

            $lastSeen = $this->prerequisiteEvidenceLastSeen($studentId, $prerequisiteConceptId, $subInstituteId);
            if ($lastSeen === null || $lastSeen->gt(now()->subDays(self::PREREQUISITE_STALE_AFTER_DAYS))) {
                continue; // never practised (nothing to refresh) or still fresh
            }

            $item = $this->prerequisiteProbeItem($prerequisiteConceptId, $subInstituteId);
            if ($item === null) {
                continue; // no servable item to probe with — don't stall the student on a gate we can't test
            }

            $daysStale = (int) $lastSeen->diffInDays(now());
            $rule = sprintf(
                'D2: prerequisite concept %d mastery %.2f >= %.2f but last practised %d days ago, probing',
                $prerequisiteConceptId,
                $mastery,
                self::PREREQUISITE_THRESHOLD,
                $daysStale
            );

            if (! $silent) {
                $this->log(
                    $studentId,
                    $conceptId,
                    (int) $item['node_id'],
                    $subInstituteId,
                    [
                        'prerequisite_concept_id' => $prerequisiteConceptId,
                        'prerequisite_mastery' => $mastery,
                        'days_since_last_evidence' => $daysStale,
                    ],
                    $rule,
                    'prerequisite_quick_probe'
                );
            }

            return [
                'action' => 'prerequisite_quick_probe',
                'concept_id' => $conceptId,
                'prerequisite_concept_id' => $prerequisiteConceptId,
                'node_id' => (int) $item['node_id'],
                'days_since_last_evidence' => $daysStale,
                'item' => $item,
                'rule_fired' => 'D2',
                'llm_instruction' => null,
            ];
        }

        return null;
    }

    /** The most recent moment the student produced evidence on any node of a concept, or null if never. */
    protected function prerequisiteEvidenceLastSeen(int $studentId, int $conceptId, int $subInstituteId): ?\Illuminate\Support\Carbon
    {
        $nodeIds = $this->nodesForConcept($conceptId, $subInstituteId)->pluck('id');
        if ($nodeIds->isEmpty()) {
            return null;
        }

        $latest = LearnerNodeState::forStudent($studentId)
            ->whereIn('node_id', $nodeIds)
            ->whereNotNull('last_seen_at')
            ->max('last_seen_at');

        return $latest === null ? null : \Illuminate\Support\Carbon::parse($latest);
    }

    /** One servable item from any node of the prerequisite concept, for the quick probe. */
    protected function prerequisiteProbeItem(int $conceptId, int $subInstituteId): ?array
    {
        foreach ($this->nodesForConcept($conceptId, $subInstituteId)->shuffle() as $node) {
            $item = $this->practiceItem((int) $node->id, $subInstituteId);
            if ($item !== null) {
                return $item;
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

        $estimates = $this->statesForNodes($studentId, $nodeIds)
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
        $this->publishEvidence($studentId, $conceptId, $subInstituteId, [[
            'question_id' => $this->questionIdFor($answerMasterId),
            'correct' => $correct,
        ]]);

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

                // A remediation that actually worked is worth recording in the
                // shared ledger, not only in this engine's own decision log.
                $this->evidenceBridge->recordOutcome(
                    $studentId,
                    $conceptId,
                    EsoEvidenceBridge::OUTCOME_MISCONCEPTION_CORRECTED,
                    ['node_id' => $nodeId, 'rule' => 'D3']
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

        $this->evidenceBridge->recordOutcome(
            $studentId,
            $conceptId,
            EsoEvidenceBridge::OUTCOME_MISCONCEPTION_DETECTED,
            ['node_id' => $node->id, 'misconception_id' => $misconceptionId, 'rule' => 'D3']
        );

        return $this->contrastPairAction(
            $studentId,
            $conceptId,
            $node,
            $misconceptionId,
            $subInstituteId,
            firstFlag: true,
            chosenAnswerMasterId: $answerMasterId
        );
    }

    /**
     * The evidence behind a misconception call, for the student to actually
     * see: the answer they just picked, and whether this same misconception
     * has been flagged on this node before.
     *
     * "You have a misconception" with nothing shown is an assertion the
     * student has no way to check; showing the specific answer (and that it
     * has happened before, when it has) is the difference between a verdict
     * and evidence. Prior occurrences come from eso_decision_log, which
     * already records every serve_contrast_pair — nothing new is stored.
     *
     * @return array{chosen_answer:?string, previous_occurrences:int}
     */
    protected function misconceptionEvidence(int $studentId, int $nodeId, int $misconceptionId, ?int $chosenAnswerMasterId): array
    {
        $chosenAnswer = $chosenAnswerMasterId === null
            ? null
            : DB::table('answer_master')->where('id', $chosenAnswerMasterId)->value('answer');

        $previous = DecisionLog::forStudent($studentId)
            ->where('node_id', $nodeId)
            ->where('action', 'serve_contrast_pair')
            ->get(['state_snapshot'])
            ->filter(fn ($row) => (int) ($row->state_snapshot['misconception_id'] ?? 0) === $misconceptionId)
            ->count();

        return [
            'chosen_answer' => $chosenAnswer === null ? null : trim(strip_tags((string) $chosenAnswer)),
            'previous_occurrences' => $previous,
        ];
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

    protected function contrastPairAction(int $studentId, int $conceptId, ConceptNode $node, int $misconceptionId, int $subInstituteId, bool $firstFlag, bool $silent = false, ?int $chosenAnswerMasterId = null): array
    {
        $misconception = MisconceptionLibrary::find($misconceptionId);
        $corrective = $this->misconceptions->selectCorrective($misconceptionId, $studentId, $subInstituteId);
        // Read BEFORE this occurrence is logged below, so the count means
        // "times this was flagged before now", not including the current one.
        $evidence = $this->misconceptionEvidence($studentId, (int) $node->id, $misconceptionId, $chosenAnswerMasterId);

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
            'misconception_description' => $misconception?->description,
            'contrast_pair' => $corrective,
            // What the student can actually check this call against.
            'evidence' => $evidence,
            'rule_fired' => 'D3',
            'llm_instruction' => $instruction,
        ];
    }

    // ── D4: practice gating + mastery verdict ───────────────────────────

    /**
     * The teach → check-understanding → practice phase machine for one node.
     *
     * This is the ONLY branch point that decides between those three screens,
     * which is why the CFU gate is inserted here and nowhere else: the D2
     * prerequisite gate, the D3 misconception branch, the D5 retrieval branch
     * and the D4 concept verdict in nextAction() all run before this method is
     * ever reached, and none of them change.
     *
     * The phase is read from explicit markers (`taught_at`, `cfu_passed_at`)
     * rather than inferred from `attempts`. The old `attempts === 0` test
     * conflated "never taught" with "taught and now practising", and was in
     * practice almost unreachable — scoreDiagnostic() calls applyUpdate(), so
     * any node covered by a diagnostic already had attempts >= 1 and skipped
     * teaching entirely.
     */
    protected function teachOrPracticeAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, bool $silent = false): array
    {
        if ($state->taught_at === null) {
            return $this->teachAction($studentId, $conceptId, $node, $state, $subInstituteId, $silent);
        }

        // Taught, but the check of understanding has not been passed yet — and
        // the reteach loop guard has not been spent. See CFU_MAX_CYCLES.
        if ($state->cfu_passed_at === null && (int) $state->cfu_attempts < self::CFU_MAX_CYCLES) {
            return $this->checkUnderstandingAction($studentId, $conceptId, $node, $state, $subInstituteId, $silent);
        }

        return $this->practiceAction($studentId, $conceptId, $node, $state, $subInstituteId, $silent);
    }

    /**
     * Serve the explanation for a node the student has not been taught yet.
     *
     * Unlike the old combined teach/practice screen this carries NO scored
     * question — `expects: acknowledge` tells the UI to show a "ready to be
     * checked" affordance instead of an answer form. That separation is the
     * whole point of the CFU step: teaching is no longer practice attempt #1.
     */
    protected function teachAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, bool $silent = false): array
    {
        // The richest learning object that genuinely exists for this concept,
        // or null — in which case teaching stays exactly as it was.
        $content = $this->learningContent->forNode($node, $state, $subInstituteId);
        $instruction = EsoPalRenderer::teachInstruction($node, $state, $this->priorNodeLabels($node), $content);

        if (! $silent) {
            // Stamped on delivery, and only on a real (non-silent) resolve —
            // dashboards call nextAction(silent: true) purely to display a
            // next step and must never advance the student's phase. Idempotent:
            // re-polling the same action does not re-stamp.
            $state->taught_at = now();
            $state->save();

            $this->log(
                $studentId,
                $conceptId,
                $node->id,
                $subInstituteId,
                ['mastery_estimate' => $state->mastery_estimate, 'attempts' => $state->attempts],
                'D1: node not yet taught',
                'teach',
                $instruction
            );
        }

        return [
            'action' => 'teach',
            'node_id' => $node->id,
            'concept_id' => $conceptId,
            'practice_mode' => $state->practice_mode,
            'rule_fired' => 'D1',
            'llm_instruction' => $instruction,
            // No question on this screen — the student reads, then asks to be checked.
            'expects' => 'acknowledge',
            'learning_content' => $content,
            'motivation_instruction' => null,
            'motivation_fallback' => null,
        ];
    }

    /**
     * The Check-For-Understanding gate. Sits between teaching and scored
     * practice; its answers are graded but are NOT mastery evidence (see
     * recordCheckUnderstanding()).
     */
    protected function checkUnderstandingAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, bool $silent = false): array
    {
        $retry = (int) $state->cfu_attempts > 0;

        // On a retry the content resolver walks the content model's own
        // re-route ladder (see EsoLearningContentResolver::ladderFrom()), so
        // "explain it a different way" can mean a genuinely different FORMAT —
        // text+diagram, then video, then story/audio — rather than the same
        // words again. Null whenever nothing else is authored, which is the
        // common case today.
        $content = $retry ? $this->learningContent->forNode($node, $state, $subInstituteId) : null;

        // A second pass at the gate means the first explanation did not land,
        // so re-explain differently before checking again rather than serving
        // the identical teach text and the identical questions.
        $instruction = $retry
            ? EsoPalRenderer::reteachInstruction($node, $this->priorNodeLabels($node), (int) $state->cfu_attempts, $content)
            : EsoPalRenderer::checkUnderstandingInstruction($node, self::CFU_ITEM_COUNT);

        if (! $silent) {
            $this->log(
                $studentId,
                $conceptId,
                $node->id,
                $subInstituteId,
                ['cfu_attempts' => (int) $state->cfu_attempts, 'mastery_estimate' => $state->mastery_estimate],
                $retry
                    ? sprintf('D1-CFU: check not passed (%d attempt(s)), re-explaining differently', (int) $state->cfu_attempts)
                    : 'D1-CFU: node taught, understanding not yet checked',
                $retry ? 'reteach' : 'check_understanding',
                $instruction
            );
        }

        return [
            'action' => $retry ? 'reteach' : 'check_understanding',
            'node_id' => $node->id,
            'concept_id' => $conceptId,
            'practice_mode' => $state->practice_mode,
            'rule_fired' => 'D1-CFU',
            'llm_instruction' => $instruction,
            'expects' => 'check_understanding',
            'learning_content' => $content,
            'cfu_item_count' => self::CFU_ITEM_COUNT,
            'cfu_attempts' => (int) $state->cfu_attempts,
            'motivation_instruction' => null,
            'motivation_fallback' => null,
        ];
    }

    /**
     * Scored practice — the pre-existing D4 behaviour, unchanged apart from
     * having been split out of the old combined method.
     */
    protected function practiceAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, bool $silent = false): array
    {
        $instruction = EsoPalRenderer::teachInstruction($node, $state, $this->priorNodeLabels($node));

        $action = 'practice';
        $rule = sprintf('D4: mastery %.2f, mode=%s, continue practice', $state->mastery_estimate, $state->practice_mode);

        // The "activation energy" moment: exactly one attempt recorded means
        // this is the FIRST time this node resolves to practice rather than
        // teach — the student has understood it and now has to grind. Fires
        // once per node, not on every practice screen.
        $motivation = null;
        $motivationFallback = null;
        if ($state->attempts === 1) {
            $conceptName = (string) (DB::table('lms_concept')->where('id', $conceptId)->value('name') ?? 'this concept');
            $relevance = $this->relevance->forConcept($conceptId, $subInstituteId);
            $motivation = EsoPalRenderer::practiceMotivationInstruction($node, $conceptName, $relevance);
            // Shown as-is when Pal can't render — the instruction itself is
            // engine-facing and must never reach the student verbatim.
            $motivationFallback = EsoPalRenderer::practiceMotivationFallback($conceptName, $relevance);
        }

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
            'rule_fired' => 'D4',
            'llm_instruction' => $instruction,
            'expects' => 'answer',
            // Null except on the single first-practice call, and null even then
            // when the concept has no relevance data to draw on honestly.
            'motivation_instruction' => $motivation,
            'motivation_fallback' => $motivationFallback,
        ];
    }

    /**
     * The 1-CFU_ITEM_COUNT questions for a node's check of understanding.
     *
     * Reuses exactly the same servable-item machinery as practiceItem() and
     * retrievalItems() — the same tagged pool, the same MCQ-only hydration —
     * rather than requiring a separate authored "CFU question" content type
     * that nothing in the catalogue has. Shuffled so a reteach cycle does not
     * hand back the identical pair the student just failed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkUnderstandingItems(int $nodeId, int $subInstituteId): array
    {
        $candidates = QuestionMetadata::forNode($nodeId)
            ->forTenant($subInstituteId)
            ->servable()
            ->pluck('question_id')
            ->shuffle();

        $items = [];
        foreach ($candidates as $questionId) {
            $hydrated = $this->hydrateQuestion((int) $questionId);
            if ($hydrated !== null && $hydrated['options'] !== []) {
                $items[] = array_merge($hydrated, ['node_id' => $nodeId]);
            }
            if (count($items) >= self::CFU_ITEM_COUNT) {
                break;
            }
        }

        return $items;
    }

    /**
     * Grade a check of understanding.
     *
     * Deliberately does NOT call applyUpdate(): mastery_estimate, attempts and
     * consecutive_correct are untouched, so a CFU answer is never mastery
     * evidence and cannot move a student toward or away from a D4 verdict.
     * The existing D1-D5 policy defines mastery evidence as scored practice,
     * diagnostic and retrieval responses, and this does not extend that
     * definition. Responses are still written to eso_response_log with
     * mode='cfu' so the check is auditable and distinguishable from practice.
     *
     * D3 is explicitly preserved: a wrong CFU answer whose distractor maps to
     * answer_master.misconception_id runs the same unchanged checkMisconception()
     * that practice does. Losing that signal just because the wrong answer
     * happened during a check would be strictly worse than the old flow.
     *
     * @param  array<int, array{answer_master_id:int}>  $responses
     */
    public function recordCheckUnderstanding(int $studentId, int $nodeId, int $conceptId, int $subInstituteId, array $responses): array
    {
        $node = ConceptNode::findOrFail($nodeId);
        $state = $this->stateFor($studentId, $nodeId, $subInstituteId);

        $allCorrect = true;
        $wrongAnswerMasterIds = [];

        foreach ($responses as $response) {
            $answerMasterId = (int) $response['answer_master_id'];
            $correct = $this->isAnswerCorrect($answerMasterId);
            // mode='cfu' keeps these rows out of any guided/independent
            // practice-mode reading of the log.
            $this->logResponse($studentId, $conceptId, $nodeId, $subInstituteId, $answerMasterId, $correct, false, 'cfu');

            if (! $correct) {
                $allCorrect = false;
                $wrongAnswerMasterIds[] = $answerMasterId;
            }
        }

        if ($allCorrect && $responses !== []) {
            $state->cfu_passed_at = now();
            $state->last_seen_at = now();
            $state->save();

            $this->log(
                $studentId,
                $conceptId,
                $nodeId,
                $subInstituteId,
                ['cfu_attempts' => (int) $state->cfu_attempts],
                'D1-CFU: check of understanding passed, releasing to practice',
                'understood'
            );

            return $this->evaluateProgress($studentId, $conceptId, $subInstituteId);
        }

        $state->cfu_attempts = (int) $state->cfu_attempts + 1;
        $state->last_seen_at = now();
        $state->save();

        // D3 first — an identified misconception is a more specific diagnosis
        // than "didn't understand", and routing to the contrast pair is the
        // more targeted remediation of the two.
        foreach ($wrongAnswerMasterIds as $answerMasterId) {
            $misconceptionAction = $this->checkMisconception($studentId, $conceptId, $node, $state, $answerMasterId, $subInstituteId);
            if ($misconceptionAction !== null) {
                return $misconceptionAction;
            }
        }

        $this->log(
            $studentId,
            $conceptId,
            $nodeId,
            $subInstituteId,
            ['cfu_attempts' => (int) $state->cfu_attempts],
            (int) $state->cfu_attempts >= self::CFU_MAX_CYCLES
                ? sprintf('D1-CFU: check not passed after %d cycle(s), releasing to guided practice', self::CFU_MAX_CYCLES)
                : 'D1-CFU: check of understanding not passed, re-explaining',
            'not_understood'
        );

        // Re-resolves through nextAction(): either a reteach (valve open) or,
        // once the valve is spent, ordinary guided practice — never a dead end.
        return $this->evaluateProgress($studentId, $conceptId, $subInstituteId);
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

        $states = $this->statesForNodes($studentId, $nodeIds)->keyBy('node_id');

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
                    // Enters the retention ladder at whatever rung this node is
                    // already on — normally stage 0 (Day 2), but a node that
                    // previously climbed and was re-loop'd resumes from its reset
                    // stage rather than silently jumping back to a long interval.
                    $this->scheduleRetention($state);
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

            // Recognition runs AFTER the decision row is written: the badge
            // signals read `mastered_stop_practice` out of eso_decision_log,
            // so awarding before the log would evaluate against evidence that
            // does not exist yet.
            if ($mastered) {
                $this->awardGamification($studentId);

                // The concept-level verdict, into the school's shared evidence
                // ledger. Recorded as an OUTCOME, not a response, so it is
                // auditable without counting as an extra correct answer in the
                // BKT replay that drives pal_concept_mastery.
                $this->evidenceBridge->recordOutcome(
                    $studentId,
                    $conceptId,
                    EsoEvidenceBridge::OUTCOME_MASTERED,
                    ['knowledge_mastery' => $kMastery, 'application_mastery' => $aMastery, 'rule' => 'D4']
                );
            }
        }

        // What comes AFTER mastery: something to explore, and somewhere to go.
        //
        // Resolved only on a real (non-silent) verdict, and only when actually
        // mastered. That is not an optimisation — it is required for
        // termination. chapterDashboard() calls conceptStatusFor(), which calls
        // masteryVerdict(silent: true); nextEligibleConcept() calls
        // conceptStatusFor() in turn, so resolving it on the silent path would
        // recurse without bound. The silent path returns here having done
        // nothing, which bounds the recursion at one level.
        $enrichment = [];
        $nextConcept = null;
        $chapterComplete = false;

        if ($mastered && ! $silent) {
            $enrichment = $this->enrichment->forConcept($studentId, $conceptId, $subInstituteId);

            $chapterId = DB::table('lms_concept')->where('id', $conceptId)->value('chapter_id');
            if ($chapterId !== null) {
                $nextConcept = $this->nextEligibleConcept($studentId, (int) $chapterId, $subInstituteId, $conceptId);
                // Nothing left that is unmastered AND unlocked. Distinct from
                // "blocked": a concept still locked behind an unmet
                // prerequisite is not offered, and the chapter is not complete
                // either — chapterComplete only when nothing remains at all.
                $chapterComplete = $nextConcept === null;
            }
        }

        return [
            'action' => $action,
            'concept_id' => $conceptId,
            'mastered' => $mastered,
            'knowledge_mastery' => $kMastery,
            'application_mastery' => $aMastery,
            'rule_fired' => 'D4',
            'llm_instruction' => null,
            // Display-only. No existing D1-D5 rule makes exploratory content
            // evidence of anything, so this writes no state and is skippable.
            'enrichment' => $enrichment,
            'next_concept' => $nextConcept,
            'chapter_complete' => $chapterComplete,
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
    protected function retrievalDueAction(int $studentId, int $conceptId, ConceptNode $node, LearnerNodeState $state, int $subInstituteId, bool $silent = false): array
    {
        [$recap, $recapFallback, $daysSince] = $this->retentionRecap($node, $state, $conceptId, $subInstituteId);

        if (! $silent) {
            $this->log(
                $studentId,
                $conceptId,
                $node->id,
                $subInstituteId,
                ['next_review_at' => now()->toDateTimeString()],
                'D5: scheduled retrieval check is due',
                'retrieval_due',
                $recap
            );
        }

        return [
            'action' => 'retrieval_due',
            'node_id' => $node->id,
            'concept_id' => $conceptId,
            'rule_fired' => 'D5',
            // The recap, when there is real material to build one from. Null
            // otherwise — the student goes straight to the check rather than
            // reading a refresher of something nobody authored.
            'llm_instruction' => $recap,
            'recap_fallback' => $recapFallback,
            'days_since_last_evidence' => $daysSince,
            'retention_stage' => (int) $state->retention_stage,
        ];
    }

    /**
     * The short memory jog attached to a due spaced-review check.
     *
     * A student meeting a node again after 2 days needs little; one meeting it
     * after 180 days may not remember what the node label even referred to.
     * Rather than assume, this reuses the SAME approved material the teach step
     * serves — EsoLearningContentResolver first (the content model's own
     * variant body), then the concept's real-world hook or definition via
     * ConceptRelevanceResolver. Both are existing teaching information.
     *
     * Nothing is generated when neither has anything: an invented refresher
     * before a memory test would corrupt the very thing D5 is measuring.
     *
     * @return array{0:?string, 1:?string, 2:?int} [instruction, student-facing fallback, days since last evidence]
     */
    protected function retentionRecap(ConceptNode $node, LearnerNodeState $state, int $conceptId, int $subInstituteId): array
    {
        $daysSince = $state->last_seen_at === null ? null : (int) $state->last_seen_at->diffInDays(now());

        $conceptName = (string) (DB::table('lms_concept')->where('id', $conceptId)->value('name') ?? 'this concept');

        $content = $this->learningContent->forNode($node, $state, $subInstituteId);
        $material = trim((string) ($content['body'] ?? ''));

        if ($material === '') {
            $relevance = $this->relevance->forConcept($conceptId, $subInstituteId);
            $material = trim((string) ($relevance['text'] ?? ''));
        }

        if ($material === '') {
            return [null, null, $daysSince];
        }

        return [
            EsoPalRenderer::retentionSummaryInstruction($node, $conceptName, $daysSince ?? 1, $material),
            EsoPalRenderer::retentionSummaryFallback($conceptName, $material),
            $daysSince,
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

        // One publish for the whole check, not one per item.
        $this->publishEvidence($studentId, $conceptId, $subInstituteId, collect($responses)->map(fn ($r) => [
            'question_id' => $this->questionIdFor((int) $r['answer_master_id']),
            'correct' => $this->isAnswerCorrect((int) $r['answer_master_id']),
        ])->all());

        if ($allCorrect) {
            $state->status = LearnerNodeState::STATUS_RETAINED;
            // Climb one rung: the next check (if the ladder isn't finished) is
            // scheduled at a longer interval. advanceRetentionLadder() clears
            // next_review_at itself once the last rung is passed.
            $this->advanceRetentionLadder($state);
            $state->save();

            $rule = sprintf('D5: retrieval check passed, retention %s', $this->retentionStageLabel($state));
            $action = 'retained';
        } else {
            $state->status = LearnerNodeState::STATUS_LEARNING;
            $state->mastery_estimate = max(0.0, $state->mastery_estimate - self::MASTERY_STEP);
            $state->next_review_at = null;
            // Failing a spaced check means this didn't stick — the ladder starts
            // over rather than resuming at a long interval it hasn't earned.
            $state->retention_stage = 0;
            $state->save();

            $rule = 'D5: retrieval check failed, re-loop this node only, retention ladder reset';
            $action = 'reloop_node';
        }

        $this->log($studentId, $conceptId, $nodeId, $subInstituteId, [
            'responses' => $responses,
            'retention_stage' => (int) $state->retention_stage,
        ], $rule, $action);

        // "It stuck" — retention verified days or weeks later is its own,
        // stronger evidence than the original mastery. Awarded after the log
        // row exists, for the same reason as the D4 path above.
        if ($allCorrect) {
            $this->awardGamification($studentId);
        }

        $this->evidenceBridge->recordOutcome(
            $studentId,
            $conceptId,
            $allCorrect ? EsoEvidenceBridge::OUTCOME_RETAINED : EsoEvidenceBridge::OUTCOME_RETENTION_LAPSED,
            ['node_id' => $nodeId, 'retention_stage' => (int) $state->retention_stage, 'rule' => 'D5']
        );

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

        // Two queries up front for content the loop below would otherwise
        // fetch one concept at a time. Purely a warm-up of the same memo the
        // per-concept accessors already use — no behaviour depends on it.
        $this->primeConceptContent($readyConcepts->pluck('id')->all(), $subInstituteId);

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

            if ($currentConceptId === null && ! self::isConceptSettled($classification['status'])) {
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
            // Recognition and review state the student could previously only
            // find by visiting separate pages. All of it already existed in the
            // estate; none of it had ever been surfaced on this screen.
            'gamification' => $this->gamificationSummary($studentId),
            'reviews_due' => $this->dueForRetrieval($studentId, $subInstituteId)->count(),
            // Only meaningful once the current concept has actually been
            // cleared — otherwise there is nothing to enrich yet.
            'enrichment_available' => ($nextStep['action'] ?? null) === 'mastered_stop_practice',
        ];
    }

    /**
     * Streak and badge headline for the dashboard, from the EXISTING PAL
     * gamification tables — no new counters, no second badge system.
     *
     * Reads `pal_learner_badges` directly rather than calling
     * BadgeService::collection(), which re-evaluates the whole catalogue on
     * every call: a dashboard render is a read, and it must not silently
     * become an award pass. Awarding stays where it already happens — on a
     * real D4/D5 outcome, via awardGamification().
     *
     * Never throws: recognition must not be able to break the dashboard.
     *
     * @return array{streak_current:int, streak_headline:?string, badges_earned:int, recent_badge:?array{name:string, awarded_at:?string}}
     */
    protected function gamificationSummary(int $studentId): array
    {
        $empty = ['streak_current' => 0, 'streak_headline' => null, 'badges_earned' => 0, 'recent_badge' => null];

        try {
            $earned = 0;
            $recent = null;

            if (Schema::hasTable('pal_learner_badges')) {
                $query = DB::table('pal_learner_badges')
                    ->where('learner_id', $studentId)
                    ->whereNull('revoked_at');

                $earned = (clone $query)->count();

                $latest = (clone $query)->orderByDesc('awarded_at')->first(['badge_id', 'awarded_at']);
                if ($latest !== null) {
                    $name = Schema::hasTable('pal_badges')
                        ? DB::table('pal_badges')->where('badge_id', $latest->badge_id)->value('name')
                        : null;

                    $recent = [
                        'name' => (string) ($name ?? $latest->badge_id),
                        'awarded_at' => $latest->awarded_at,
                    ];
                }
            }

            $streak = $this->streaks->summary($studentId);

            return [
                'streak_current' => (int) ($streak['current_streak'] ?? 0),
                'streak_headline' => $streak['headline'] ?? null,
                'badges_earned' => $earned,
                'recent_badge' => $recent,
            ];
        } catch (\Throwable) {
            return $empty;
        }
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

        $retainedCount = $this->statesForNodes($studentId, $nodeIds)
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
    /**
     * A concept the student should not be routed to right now: either already
     * mastered, or locked because its prerequisites are unmet.
     *
     * The single definition shared by chapterDashboard()'s "where am I" pick
     * and nextEligibleConcept()'s "where next" pick, so the two can never
     * disagree about what counts as available.
     */
    protected static function isConceptSettled(string $status): bool
    {
        return in_array($status, ['locked', 'mastered'], true);
    }

    /**
     * The next concept in this chapter the student may legitimately start.
     *
     * Reuses the chapter dashboard's existing selection rather than adding a
     * second sequencer: walk the chapter's ESO-ready concepts and take the
     * first that is neither mastered nor locked. Because conceptStatusFor()
     * already reports `locked` whenever prerequisitesMet() fails, D2 is
     * honoured here for free — a concept whose prerequisites are unmet can
     * never be offered as "next".
     *
     * ORDERING — stated plainly, because it matters: `lms_concept` has no
     * sort_order column, so ascending id is the only total order available.
     * The prerequisite graph is the better signal but it is partial (Chapter
     * 1014 has 6 `requires` edges across 17 concepts), so it cannot order a
     * chapter on its own. It is therefore applied as a hard FILTER via
     * `locked`, with id as the tiebreak among concepts that are all equally
     * unblocked — not as a claim that id order is pedagogically correct.
     *
     * @return array{concept_id:int, name:?string}|null  null when the chapter has nothing left to offer
     */
    public function nextEligibleConcept(int $studentId, int $chapterId, int $subInstituteId, ?int $excludeConceptId = null): ?array
    {
        foreach ($this->esoReadyConceptsForChapters([$chapterId], $subInstituteId) as $concept) {
            $conceptId = (int) $concept->id;
            if ($excludeConceptId !== null && $conceptId === $excludeConceptId) {
                continue;
            }

            // silent: this is a lookup for a CTA, not a decision about the
            // student — it must not write an eso_decision_log row.
            if (! self::isConceptSettled($this->conceptStatusFor($studentId, $conceptId, $subInstituteId)['status'])) {
                return ['concept_id' => $conceptId, 'name' => $concept->name ?? null];
            }
        }

        return null;
    }

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

        return (int) $this->statesForNodes($studentId, $nodeIds)->sum('attempts');
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
        $states = $this->statesForNodes($studentId, $nodes->pluck('id'))->keyBy('node_id');

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

        // The verdict already computed these; they used to be thrown away, so
        // the "Mastery details" screen could not show the two numbers the D4
        // rule actually turns on.
        $states = $this->statesForNodes($studentId, $nodes->pluck('id'))->values();

        return [
            'concept_id' => $conceptId,
            'concept_name' => $conceptRow->name,
            'chapter_id' => (int) $conceptRow->chapter_id,
            'status' => $status,
            'knowledge_mastery' => $verdict['knowledge_mastery'] ?? null,
            'application_mastery' => $verdict['application_mastery'] ?? null,
            'knowledge_threshold' => self::KNOWLEDGE_MASTERY_THRESHOLD,
            'application_threshold' => self::APPLICATION_MASTERY_THRESHOLD,
            'attempts' => (int) $states->sum('attempts'),
            'retention' => $this->retentionSummaryFor($states),
            // Where the student can actually go from here, resolved through the
            // same chapter/prerequisite logic the mastery card uses — null
            // unless this concept is genuinely cleared.
            'next_concept' => $mastered
                ? $this->nextEligibleConcept($studentId, (int) $conceptRow->chapter_id, $subInstituteId, $conceptId)
                : null,
            'enrichment' => $mastered
                ? $this->enrichment->forConcept($studentId, $conceptId, $subInstituteId)
                : [],
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
    /**
     * Where this concept sits on the spaced-retention ladder, for display.
     *
     * The earliest scheduled review across the concept's nodes is the one that
     * matters to the student — that is when they will actually be asked to
     * come back. `due_now` is what the UI needs to offer "Quick review"
     * without re-deriving the D5 rule for itself.
     *
     * @param  Collection<int, LearnerNodeState>  $states
     * @return array{scheduled:bool, due_now:bool, stage:int, stage_label:?string, next_review_at:?string, nodes_retained:int}
     */
    protected function retentionSummaryFor(Collection $states): array
    {
        $scheduled = $states->filter(fn (LearnerNodeState $s) => $s->next_review_at !== null);
        $earliest = $scheduled->sortBy('next_review_at')->first();

        return [
            'scheduled' => $earliest !== null,
            'due_now' => $earliest !== null && $earliest->next_review_at->lte(now()),
            'stage' => $earliest !== null ? (int) $earliest->retention_stage : 0,
            'stage_label' => $earliest !== null ? $this->retentionStageLabel($earliest) : null,
            'next_review_at' => $earliest?->next_review_at?->toIso8601String(),
            'nodes_retained' => $states->where('status', LearnerNodeState::STATUS_RETAINED)->count(),
        ];
    }

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

    /**
     * Schedule this node's next spaced-retention check at the rung of
     * RETENTION_LADDER_DAYS its `retention_stage` currently points at, and
     * leave the stage where it is (advanceRetentionLadder() moves it).
     * Beyond the last rung the ladder is complete: no further review is
     * scheduled and next_review_at is cleared, so a fully-retained node is
     * never re-surfaced forever.
     *
     * Does not save — every caller is already mid-mutation on $state and
     * saves once itself.
     */
    protected function scheduleRetention(LearnerNodeState $state): void
    {
        $stage = (int) $state->retention_stage;

        if (! array_key_exists($stage, self::RETENTION_LADDER_DAYS)) {
            $state->next_review_at = null;

            return;
        }

        $state->next_review_at = now()->addDays(self::RETENTION_LADDER_DAYS[$stage]);
    }

    /** A passed retrieval check climbs one rung; the next schedule uses the new stage's interval. */
    protected function advanceRetentionLadder(LearnerNodeState $state): void
    {
        $state->retention_stage = (int) $state->retention_stage + 1;
        $this->scheduleRetention($state);
    }

    /** Human-readable "how far up the ladder" for the decision log / API — 1-based, capped at the ladder length. */
    protected function retentionStageLabel(LearnerNodeState $state): string
    {
        $total = count(self::RETENTION_LADDER_DAYS);
        $stage = min((int) $state->retention_stage, $total);

        return $stage >= $total ? 'ladder complete' : sprintf('stage %d of %d', $stage + 1, $total);
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

    /**
     * Per-request memo for reads that are stable for the lifetime of one
     * request but were being re-issued dozens of times.
     *
     * This exists because the database is REMOTE (see the performance
     * investigation): a round trip costs ~29ms regardless of how trivial the
     * query is, so the only lever that moves the needle is the NUMBER of
     * queries. chapterDashboard() was issuing 168 of them for one page — 73 of
     * which were the identical `pal_concept_nodes` lookup, because
     * conceptStatusFor() -> masteryVerdict() -> nodesForConcept() runs once per
     * concept and the dashboard walks 17 of them.
     *
     * Scope is deliberately the service instance, which Laravel resolves fresh
     * per request: a concept's authored K/A/S node list cannot change midway
     * through a single request, so nothing here can serve stale data to the
     * decision it is feeding. This is a read cache, never a write-through one —
     * no learner state is memoised.
     *
     * @var array<string, mixed>
     */
    protected array $requestMemo = [];

    /**
     * One learner's full node-state set, loaded once per request instead of
     * once per concept.
     *
     * The chapter dashboard walks 17 concepts and each one asked the database
     * separately for its own slice of this table — plus a separate SUM for the
     * attempt count. With a remote database at ~29ms a round trip that is over
     * a second of pure latency for rows that all come from a single learner's
     * partition of one table.
     *
     * SAFETY. This is a learner-state read, so unlike the content memo it can
     * be invalidated by this engine's own writes — including on a "silent"
     * dashboard resolve, because masteryVerdict() sweeps nodes to `mastered`
     * regardless of $silent. The cache is therefore versioned against
     * LearnerNodeState::writeVersion(), which the model bumps on every saved
     * and deleted event. Any write anywhere — including stateFor()'s
     * firstOrCreate and every $state->save() — invalidates it, so a stale row
     * can never reach a decision. Verified complete: nothing in app/ writes
     * this table through the query builder.
     *
     * @return \Illuminate\Support\Collection<int, LearnerNodeState> keyed by node_id
     */
    protected function learnerStates(int $studentId): Collection
    {
        $version = LearnerNodeState::writeVersion();

        if (($this->learnerStateCache['student'] ?? null) !== $studentId
            || ($this->learnerStateCache['version'] ?? null) !== $version) {
            $this->learnerStateCache = [
                'student' => $studentId,
                'version' => $version,
                'rows' => LearnerNodeState::forStudent($studentId)->get()->keyBy('node_id'),
            ];
        }

        return $this->learnerStateCache['rows'];
    }

    /**
     * The learner's states for a specific set of nodes, served from the
     * per-request batch. Identical result to querying with a whereIn — the
     * same rows, keyed the same way — but without the round trip.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>|array<int, mixed>  $nodeIds
     * @return \Illuminate\Support\Collection<int, LearnerNodeState>
     */
    protected function statesForNodes(int $studentId, $nodeIds): Collection
    {
        $wanted = collect($nodeIds)->map(fn ($id) => (int) $id)->flip();

        return $this->learnerStates($studentId)->filter(
            fn (LearnerNodeState $state) => $wanted->has((int) $state->node_id)
        );
    }

    /** @var array{student?:int, version?:int, rows?:Collection} */
    protected array $learnerStateCache = [];

    /** @param  callable():mixed  $resolve */
    protected function memo(string $key, callable $resolve): mixed
    {
        if (! array_key_exists($key, $this->requestMemo)) {
            $this->requestMemo[$key] = $resolve();
        }

        return $this->requestMemo[$key];
    }

    /**
     * Drop the per-request memo.
     *
     * Called whenever this service writes something that a memoised read
     * covers, so a long-lived instance (a queue worker, or a test that seeds
     * more content after a first call) can never observe its own stale view.
     */
    public function forgetMemoized(): void
    {
        $this->requestMemo = [];
    }

    /**
     * Load the K/A/S nodes and `requires` relations for a whole set of
     * concepts in two queries, and seed the per-concept memo with the result.
     *
     * The memo already collapsed the old 73-per-page node lookups down to one
     * per concept, but "one per concept" is still 17 round trips on a chapter
     * page — and on a remote database a round trip costs far more than the
     * handful of rows it returns. This turns those 34 trips into 2.
     *
     * Seeding the same memo the per-concept accessors read means callers are
     * unchanged and a concept that was NOT part of the prime still resolves
     * itself lazily; this is a warm-up, never a gate.
     *
     * @param  array<int, int>  $conceptIds
     */
    protected function primeConceptContent(array $conceptIds, int $subInstituteId): void
    {
        $conceptIds = array_values(array_unique(array_map('intval', $conceptIds)));
        if ($conceptIds === []) {
            return;
        }

        $nodesByConcept = ConceptNode::whereIn('concept_id', $conceptIds)
            ->forTenant($subInstituteId)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('concept_id');

        $relationsByConcept = ConceptRelation::whereIn('from_concept_id', $conceptIds)
            ->where('relation_type', 'requires')
            ->forTenant($subInstituteId)
            ->get()
            ->groupBy('from_concept_id');

        foreach ($conceptIds as $conceptId) {
            // An absent key is a real answer — the concept has no nodes, or no
            // prerequisites — so it is memoised as an empty collection rather
            // than left to fall through and re-query.
            $this->requestMemo["nodes:{$conceptId}:{$subInstituteId}"] =
                $nodesByConcept->get($conceptId, collect())->values();

            $this->requestMemo["prereq:{$conceptId}:{$subInstituteId}"] =
                $relationsByConcept->get($conceptId, collect())->pluck('to_concept_id');
        }
    }

    protected function nodesForConcept(int $conceptId, int $subInstituteId): Collection
    {
        return $this->memo(
            "nodes:{$conceptId}:{$subInstituteId}",
            fn () => ConceptNode::forConcept($conceptId)->forTenant($subInstituteId)->orderBy('sort_order')->get()
        );
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
