<?php

namespace App\Services\PAL\H5P;

use App\Models\PAL\FrameworkProgress;
use App\Models\PAL\LearningEvent;
use App\Models\PAL\LearningEvidence;
use App\Models\PAL\TelemetryEvent;
use App\Services\PAL\Intelligence\MisconceptionIntelligenceEngine;
use App\Services\PAL\Pedagogy\PedagogyOrchestrationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * §8.2 — the xAPI → PAL event pipeline.
 *
 * An H5P activity emits an xAPI statement; this turns it into a PAL learning
 * event, enriches it with everything the H5P Model knows about the node it came
 * from, computes fluency, persists it, and feeds the downstream processors.
 *
 * The spec sketches five queue jobs (StoreLearningEvent, UpdateBKT,
 * CheckMisconception, UpdateEngagement, UpdateRIASEC). This ERP has no such
 * queue, and shipping five empty job classes would be worse than useless — so
 * each is run against the engine that actually exists, and the response reports
 * per processor what ran, what it did, and what is genuinely not wired yet.
 * `processors` in the return value is the honest record of that.
 *
 * Identity note: `object_id` is written as the node key ("h5p_type:id"). That
 * is what makes engagement metrics traceable back to the exact scenario, video
 * or card a learner touched — see H5PEngagementService::forNodes().
 */
class H5PXapiPipeline
{
    public function __construct(
        protected H5PModelRegistry $registry,
        protected H5PContentRepository $repository,
        protected H5PTaggingService $tagging,
        protected PedagogyOrchestrationService $pedagogy,
        protected MisconceptionIntelligenceEngine $misconceptions
    ) {
    }

    /**
     * Process one statement.
     *
     * @param  array     $statement  raw xAPI
     * @param  int       $learnerId  authenticated learner (never the mbox)
     * @param  int|null  $sessionId  numeric pal_learning_sessions id
     * @param  array     $context    tenant + curriculum scope
     */
    public function process(array $statement, int $learnerId, ?int $sessionId, array $context = []): array
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);

        $verb = $this->resolveVerb($statement, $tenant);
        $eventType = $this->registry->verbEventTypeMap($tenant)[$verb] ?? $verb;
        $extensions = (array) ($statement['context']['extensions'] ?? []);

        // ── Resolve the node this statement is about ─────────────────────────
        $nodeKey = $this->resolveNodeKey($statement, $extensions, $tenant);
        $node = $nodeKey ? $this->loadNode($nodeKey, $context) : null;
        $tags = $node ? ($this->tagging->tagNode($node, $context)['values'] ?? []) : [];

        // A statement may name its own tags; the node's stored/derived tags win
        // because they have been reviewed and the client's have not.
        $pedagogyTag = $tags['pedagogy_tag']
            ?? $this->registry->normalize('pedagogy_tags', $extensions['pedagogy_tag'] ?? null, $tenant);
        $h5pType = $node['h5p_type']
            ?? $this->registry->normalize('h5p_types', $extensions['h5p_type'] ?? null, $tenant);

        $result = (array) ($statement['result'] ?? []);
        $duration = $this->durationSeconds($result, $extensions);
        $correct = $this->correctness($result);
        $scoreRaw = $this->numeric($result['score']['raw'] ?? null);
        $scoreMax = $this->numeric($result['score']['max'] ?? null);

        // ── The enriched PAL event (§8.2 palEvent) ───────────────────────────
        $palEvent = [
            'event_id' => (string) Str::uuid(),
            'user_id' => $learnerId,
            'session_id' => $sessionId,
            'event_type' => $eventType,
            'verb' => $verb,
            'timestamp' => $statement['timestamp'] ?? now()->toIso8601String(),

            'h5p_node_key' => $nodeKey,
            'h5p_content_id' => $node['id'] ?? $this->numericId($extensions['h5p_content_id'] ?? null),
            'h5p_type' => $h5pType,

            'correct' => $correct,
            'score_raw' => $scoreRaw,
            'score_max' => $scoreMax,
            'score_scaled' => $this->scaledScore($result, $scoreRaw, $scoreMax, $correct),
            'time_taken_seconds' => $duration,
            'completion' => $this->completion($result, $eventType, $tenant),

            'concept_id' => $tags['concept_ref_id'] ?? $this->numericId($extensions['concept_id'] ?? null),
            'chapter_id' => $node['chapter_id'] ?? $this->numericId($context['chapter_id'] ?? null),
            'bloom_level' => $tags['bloom_level'] ?? null,
            'difficulty' => $tags['difficulty_1_to_5'] ?? null,
            'pedagogy_tag' => $pedagogyTag,
            'cultural_context' => $tags['cultural_context'] ?? null,

            'framework_tags' => $this->frameworkTags($tags, $extensions, $tenant),
            'gardner_intelligence' => array_values((array) ($tags['gardner_intelligence'] ?? [])),
            'riasec_signal' => $tags['riasec_signal'] ?? $this->registry->normalize('riasec_signals', $extensions['riasec_signal'] ?? null, $tenant),
            'hpc_lens' => $tags['hpc_lens_primary'] ?? null,
            'misconception_data' => (array) ($extensions['misconception_data'] ?? []),

            'language' => $extensions['language'] ?? ($statement['context']['language'] ?? null),
            'platform' => $extensions['platform'] ?? ($statement['context']['platform'] ?? null),
        ];

        // ── Fluency (§8.2): correct-per-second and error-per-second ──────────
        $palEvent += $this->fluency($palEvent, $h5pType, $tenant);

        // ── Run the processors ───────────────────────────────────────────────
        $jobs = $this->jobsFor($verb, $tenant);
        $processors = [];

        $event = $this->storeEvent($statement, $palEvent, $learnerId, $sessionId);
        $processors['store_event'] = ['ran' => true, 'detail' => "telemetry event #{$event->id}"];

        $processors['store_learning_event'] = $this->storeLearningEvent($palEvent, $learnerId, $sessionId);

        if (in_array('update_bkt', $jobs, true)) {
            $processors['update_bkt'] = $this->recordMasteryEvidence($palEvent, $learnerId, $sessionId);
        }
        if (in_array('check_misconception', $jobs, true)) {
            $processors['check_misconception'] = $this->checkMisconception($palEvent, $learnerId);
        }
        if (in_array('update_engagement', $jobs, true)) {
            $processors['update_engagement'] = $this->recordPedagogyEffectiveness($palEvent, $learnerId, $sessionId);
        }
        if (in_array('update_riasec', $jobs, true)) {
            $processors['update_riasec'] = $this->updateFrameworkProgress($palEvent, $learnerId);
        }

        return [
            'event_id' => $event->id,
            'pal_event' => $palEvent,
            'processors' => $processors,
            'node_resolved' => $node !== null,
            'unresolved_reason' => $node === null
                ? ($nodeKey === null
                    ? 'The statement did not identify an H5P node. Send object.id as "<h5p_type>:<id>" or context.extensions.h5p_node_key.'
                    : "No H5P node matches {$nodeKey} in this chapter/institute.")
                : null,
        ];
    }

    /** Process a batch, isolating failures so one bad statement is not fatal. */
    public function processBatch(array $statements, int $learnerId, ?int $sessionId, array $context = []): array
    {
        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($statements as $index => $statement) {
            try {
                $this->process((array) $statement, $learnerId, $sessionId, $context);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['index' => $index, 'message' => $e->getMessage()];
                Log::error('H5P xAPI statement failed', ['index' => $index, 'error' => $e->getMessage()]);
            }
        }

        return ['processed' => $processed, 'failed' => $failed, 'errors' => $errors];
    }

    // ── Resolution ──────────────────────────────────────────────────────────

    /** Verb IRI → short code, via the registry (§8.2 verbMap). */
    protected function resolveVerb(array $statement, int $tenant): string
    {
        $raw = (string) ($statement['verb']['id'] ?? $statement['verb'] ?? '');
        $map = $this->registry->verbIriMap($tenant);

        if (isset($map[$raw])) {
            return $map[$raw];
        }

        // Already-short verbs ("answered") are accepted as-is when registered.
        $short = strtolower(trim($raw));

        return isset($this->registry->verbEventTypeMap($tenant)[$short]) ? $short : ($short !== '' ? $short : 'experienced');
    }

    /**
     * The node key, from (in order) an explicit extension, an object.id already
     * in "type:id" form, or an object.id URL/URN ending in one.
     */
    protected function resolveNodeKey(array $statement, array $extensions, int $tenant): ?string
    {
        $candidates = [
            $extensions['h5p_node_key'] ?? null,
            $statement['object']['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }
            if (preg_match('/([A-Za-z0-9_]+):(\d+)\s*$/', $candidate, $matches)) {
                $type = $this->registry->normalize('h5p_types', $matches[1], $tenant);
                if ($type !== null) {
                    return "{$type}:{$matches[2]}";
                }
            }
        }

        // A statement may instead name the type and the row id separately.
        $type = $this->registry->normalize('h5p_types', $extensions['h5p_type'] ?? null, $tenant);
        $id = $this->numericId($extensions['h5p_content_id'] ?? ($extensions['content_id'] ?? null));

        return $type !== null && $id !== null ? "{$type}:{$id}" : null;
    }

    protected function loadNode(string $nodeKey, array $context): ?array
    {
        [$type, $id] = array_pad(explode(':', $nodeKey, 2), 2, null);
        if ($type === null || ! is_numeric($id)) {
            return null;
        }

        // Ingest must not be narrowed by the caller's chapter filter — a
        // statement can legitimately arrive for a node in another chapter of
        // the same institute.
        $scope = ['sub_institute_id' => $context['sub_institute_id'] ?? null];

        return $this->repository->node($type, (int) $id, $scope);
    }

    // ── Derived measures ────────────────────────────────────────────────────

    /**
     * §8.2 fluency: correct answers per second and errors per second. Only
     * emitted for types the registry marks fluency-trackable — a portfolio
     * submission has no meaningful rate.
     */
    protected function fluency(array $palEvent, ?string $h5pType, int $tenant): array
    {
        $trackable = $h5pType
            ? (($this->registry->type($h5pType, $tenant)['metadata']['fluency_trackable'] ?? 'no') !== 'no')
            : false;

        $seconds = (int) ($palEvent['time_taken_seconds'] ?? 0);

        if (! $trackable || $palEvent['correct'] === null || $seconds <= 0) {
            return ['fluency_trackable' => $trackable, 'fluency_correct' => null, 'fluency_error' => null];
        }

        return [
            'fluency_trackable' => true,
            'fluency_correct' => $palEvent['correct'] ? round(1 / $seconds, 6) : 0.0,
            'fluency_error' => $palEvent['correct'] ? 0.0 : round(1 / $seconds, 6),
        ];
    }

    /** Framework tags in the shape FrameworkProgress accrues against. */
    protected function frameworkTags(array $tags, array $extensions, int $tenant): array
    {
        $fields = [
            'casel' => ['casel_domain', 'casel_domains'],
            'ngss' => ['ngss_practice', 'ngss_practices'],
            'ncdg' => ['ncdg_goal', 'ncdg_goals'],
            'music' => ['music_domain', 'music_domains'],
            'sports' => ['sports_domain', 'sports_domains'],
            'finance' => ['finance_level', 'finance_levels'],
        ];

        $out = [];
        foreach ($fields as $framework => [$field, $domain]) {
            $value = $tags[$field] ?? $this->registry->normalize($domain, $extensions[$field] ?? null, $tenant);
            if ($value !== null && $value !== '') {
                $out[$framework] = $value;
            }
        }

        return $out;
    }

    protected function durationSeconds(array $result, array $extensions): int
    {
        foreach ([$result['duration_seconds'] ?? null, $extensions['duration_seconds'] ?? null] as $value) {
            if (is_numeric($value)) {
                return max(0, (int) $value);
            }
        }

        $duration = $result['duration'] ?? null;
        if (is_string($duration) && $duration !== '') {
            try {
                $interval = new \DateInterval($duration);

                return ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
            } catch (\Throwable $e) {
                return 0;
            }
        }

        return 0;
    }

    /** null when the statement carries no judgement — not false. */
    protected function correctness(array $result): ?bool
    {
        return array_key_exists('success', $result) && $result['success'] !== null
            ? (bool) $result['success']
            : null;
    }

    protected function completion(array $result, string $eventType, int $tenant): bool
    {
        if (array_key_exists('completion', $result)) {
            return (bool) $result['completion'];
        }

        return in_array($eventType, ['content_completed', 'portfolio_submitted', 'content_passed'], true);
    }

    protected function scaledScore(array $result, ?float $raw, ?float $max, ?bool $correct): ?float
    {
        if (isset($result['score']['scaled']) && is_numeric($result['score']['scaled'])) {
            return round((float) $result['score']['scaled'], 4);
        }
        if ($raw !== null && $max !== null && $max > 0) {
            return round($raw / $max, 4);
        }
        if ($correct !== null) {
            return $correct ? 1.0 : 0.0;
        }

        return null;
    }

    // ── Processors ──────────────────────────────────────────────────────────

    /** Which processors this verb feeds, per the registry's `jobs` metadata. */
    protected function jobsFor(string $verb, int $tenant): array
    {
        $term = $this->registry->term('xapi_verbs', $verb, $tenant);

        return array_values((array) ($term['metadata']['jobs'] ?? ['store_event']));
    }

    protected function storeEvent(array $statement, array $palEvent, int $learnerId, ?int $sessionId): TelemetryEvent
    {
        return TelemetryEvent::create([
            'actor_id' => $learnerId,
            'session_id' => $sessionId,
            'verb' => $palEvent['verb'],
            // The node key, so engagement can be attributed per node.
            'object_id' => $palEvent['h5p_node_key'] ?? ($statement['object']['id'] ?? null),
            'content_id' => $palEvent['h5p_content_id'],
            'concept_id' => $palEvent['concept_id'],
            'pedagogy_tag' => $palEvent['pedagogy_tag'],
            'h5p_type' => $palEvent['h5p_type'],
            'framework_tags' => $palEvent['framework_tags'],
            'context_id' => $statement['context']['registration'] ?? null,
            'result' => $statement['result'] ?? null,
            'duration_seconds' => $palEvent['time_taken_seconds'],
            'raw_statement' => $statement,
            'timestamp' => $palEvent['timestamp'],
        ]);
    }

    protected function storeLearningEvent(array $palEvent, int $learnerId, ?int $sessionId): array
    {
        $event = LearningEvent::create([
            'learner_id' => $learnerId,
            'event_type' => $palEvent['event_type'],
            'content_id' => $palEvent['h5p_content_id'],
            'concept_id' => $palEvent['concept_id'],
            'session_id' => $sessionId,
            'pedagogy_tag' => $palEvent['pedagogy_tag'],
            'h5p_type' => $palEvent['h5p_type'],
            'framework_tags' => $palEvent['framework_tags'],
            'score' => $palEvent['score_scaled'] !== null ? $palEvent['score_scaled'] * 100 : 0,
            'duration_seconds' => $palEvent['time_taken_seconds'],
            'completion' => $palEvent['completion'],
            'source' => 'h5p_xapi',
            'language' => $palEvent['language'],
            'platform' => $palEvent['platform'],
            'riasec_signal' => $palEvent['riasec_signal'],
            'gardner_intelligence' => $palEvent['gardner_intelligence'],
            'misconception_data' => $palEvent['misconception_data'],
            'event_data' => $palEvent,
        ]);

        return ['ran' => true, 'detail' => "learning event #{$event->id}"];
    }

    /**
     * Mastery evidence.
     *
     * There is no Bayesian update engine in this codebase yet — the comparison
     * sheet lists BKT as unbuilt — so this records the scored evidence a BKT
     * pass will consume (`pal_learning_evidence`) instead of pretending to
     * compute a posterior.
     */
    protected function recordMasteryEvidence(array $palEvent, int $learnerId, ?int $sessionId): array
    {
        if ($palEvent['score_scaled'] === null && $palEvent['correct'] === null) {
            return ['ran' => false, 'detail' => 'No score or correctness on the statement — nothing to score against.'];
        }

        $evidence = LearningEvidence::create([
            'learner_id' => $learnerId,
            'content_id' => $palEvent['h5p_content_id'],
            'concept_id' => $palEvent['concept_id'],
            'session_id' => $sessionId,
            'pedagogy_tag' => $palEvent['pedagogy_tag'],
            'h5p_type' => $palEvent['h5p_type'],
            'evidence_type' => $palEvent['verb'],
            'framework_tags' => $palEvent['framework_tags'],
            'score' => ($palEvent['score_scaled'] ?? 0) * 100,
            'duration_seconds' => $palEvent['time_taken_seconds'],
            'completion' => $palEvent['completion'],
            'evidence_source' => 'h5p_xapi',
            'context_data' => $palEvent,
            'recorded_at' => now(),
        ]);

        return [
            'ran' => true,
            'detail' => "mastery evidence #{$evidence->id} recorded",
            'note' => 'Bayesian mastery update is not implemented in this backend; evidence is stored for it.',
        ];
    }

    /** Wrong answers with a known concept go to the misconception engine. */
    protected function checkMisconception(array $palEvent, int $learnerId): array
    {
        if ($palEvent['correct'] !== false) {
            return ['ran' => false, 'detail' => 'Answer was not incorrect.'];
        }
        if (empty($palEvent['concept_id'])) {
            return ['ran' => false, 'detail' => 'No concept is tagged on this node, so no misconception pattern can be matched.'];
        }

        try {
            $analysis = $this->misconceptions->analyze($learnerId, (int) $palEvent['concept_id'], [
                'content_id' => $palEvent['h5p_content_id'],
                'h5p_type' => $palEvent['h5p_type'],
                'time_taken_seconds' => $palEvent['time_taken_seconds'],
                'misconception_data' => $palEvent['misconception_data'],
            ]);

            return ['ran' => true, 'detail' => 'Misconception analysis completed.', 'result' => $analysis];
        } catch (\Throwable $e) {
            return ['ran' => false, 'detail' => 'Misconception analysis failed: ' . $e->getMessage()];
        }
    }

    /** Completion of a pedagogy-tagged node is an effectiveness observation. */
    protected function recordPedagogyEffectiveness(array $palEvent, int $learnerId, ?int $sessionId): array
    {
        if (empty($palEvent['pedagogy_tag'])) {
            return ['ran' => false, 'detail' => 'Node has no pedagogy tag.'];
        }
        if (! $palEvent['completion']) {
            return ['ran' => false, 'detail' => 'Statement is not a completion.'];
        }

        $scaled = $palEvent['score_scaled'];
        $outcome = match (true) {
            $scaled === null => 'partial',
            $scaled >= 0.7 => 'success',
            $scaled >= 0.4 => 'partial',
            default => 'failure',
        };

        try {
            $this->pedagogy->trackEffectiveness($learnerId, $palEvent['pedagogy_tag'], $outcome, [
                'session_id' => $sessionId,
                'concept_id' => $palEvent['concept_id'],
                'content_id' => $palEvent['h5p_content_id'],
                'effectiveness_score' => $scaled !== null ? round($scaled * 100, 2) : null,
                'context_data' => [
                    'h5p_type' => $palEvent['h5p_type'],
                    'node_key' => $palEvent['h5p_node_key'],
                    'duration_seconds' => $palEvent['time_taken_seconds'],
                ],
            ]);

            return ['ran' => true, 'detail' => "Pedagogy effectiveness recorded as {$outcome}."];
        } catch (\Throwable $e) {
            return ['ran' => false, 'detail' => 'Pedagogy tracking failed: ' . $e->getMessage()];
        }
    }

    /**
     * Framework progress accrual — the running average per (learner,
     * framework, tag) that the CASEL / NGSS / NCDG reports read.
     */
    protected function updateFrameworkProgress(array $palEvent, int $learnerId): array
    {
        $tags = $palEvent['framework_tags'];
        if ($palEvent['riasec_signal']) {
            $tags['riasec'] = $palEvent['riasec_signal'];
        }
        foreach ($palEvent['gardner_intelligence'] as $intelligence) {
            $tags['gardner'] = $intelligence;
            break;
        }

        if ($tags === []) {
            return ['ran' => false, 'detail' => 'Node carries no framework tags to accrue against.'];
        }

        $score = $palEvent['score_scaled'] !== null
            ? $palEvent['score_scaled'] * 100
            : ($palEvent['completion'] ? 100 : 0);

        foreach ($tags as $frameworkType => $frameworkTag) {
            $row = FrameworkProgress::firstOrNew([
                'learner_id' => $learnerId,
                'framework_type' => $frameworkType,
                'framework_tag' => $frameworkTag,
            ]);

            $count = (int) ($row->evidence_count ?? 0);
            $row->progress_score = round(((($row->progress_score ?? 0) * $count) + $score) / max(1, $count + 1), 2);
            $row->evidence_count = $count + 1;
            $row->last_evidenced_at = now();
            $row->status = $row->progress_score >= 75 ? 'mastered' : ($row->progress_score >= 40 ? 'developing' : 'emerging');
            $row->metadata = [
                'pedagogy_tag' => $palEvent['pedagogy_tag'],
                'h5p_type' => $palEvent['h5p_type'],
                'node_key' => $palEvent['h5p_node_key'],
            ];
            $row->save();
        }

        return ['ran' => true, 'detail' => 'Framework progress updated for: ' . implode(', ', array_keys($tags))];
    }

    // ── Small helpers ───────────────────────────────────────────────────────

    protected function numeric($value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected function numericId($value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/(\d+)\s*$/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
