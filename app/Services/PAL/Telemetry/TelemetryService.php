<?php

namespace App\Services\PAL\Telemetry;

use App\Models\PAL\TelemetryEvent;
use Illuminate\Support\Facades\Log;

/**
 * xAPI Telemetry Service
 * Handles H5P event ingestion and interaction analytics
 */
class TelemetryService
{
    /**
     * Process an xAPI statement for an authenticated learner.
     *
     * The learner id is supplied by the controller (already ownership-checked by
     * PalApiAuth) and stored as the numeric `actor_id`. The client-supplied xAPI
     * `actor` (an mbox/account, often an email) is NOT trusted for identity — it
     * is preserved only inside `raw_statement`.
     *
     * @param  array     $statement
     * @param  int       $learnerId   authenticated learner (numeric)
     * @param  int|null  $sessionId   optional numeric learning-session id
     * @return TelemetryEvent
     */
    public function processStatement(array $statement, int $learnerId, ?int $sessionId = null): TelemetryEvent
    {
        $normalized = $this->normalizeStatement($statement);

        return TelemetryEvent::create([
            'actor_id' => $learnerId,
            'session_id' => $sessionId,
            'verb' => $normalized['verb'],
            'object_id' => $normalized['object'],
            'context_id' => $normalized['context'],
            'result' => $normalized['result'],
            'duration_seconds' => $normalized['duration_seconds'],
            'raw_statement' => $statement,
            'timestamp' => $normalized['timestamp'],
        ]);
    }

    /**
     * Process multiple statements in batch for one authenticated learner.
     *
     * @param  array     $statements
     * @param  int       $learnerId
     * @param  int|null  $sessionId
     * @return array
     */
    public function processBatch(array $statements, int $learnerId, ?int $sessionId = null): array
    {
        $processed = 0;
        $failed = 0;

        foreach ($statements as $statement) {
            try {
                $this->processStatement((array) $statement, $learnerId, $sessionId);
                $processed++;
            } catch (\Exception $e) {
                Log::error('xAPI statement processing failed', [
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    /**
     * Get session telemetry summary
     * @param int $sessionId
     * @return array
     */
    public function getSessionSummary(int $sessionId): array
    {
        $events = TelemetryEvent::where('session_id', $sessionId)->get();

        return [
            'session_id' => $sessionId,
            'event_count' => $events->count(),
            'verbs' => $events->groupBy('verb')->map(fn($g) => $g->count())->toArray(),
            'first_event' => $events->min('timestamp'),
            'last_event' => $events->max('timestamp'),
            'duration_seconds' => $this->calculateDuration($events),
        ];
    }

    /**
     * Get time-on-task intelligence
     * @param int $learnerId
     * @param string $period
     * @return array
     */
    public function getTimeOnTask(int $learnerId, string $period = 'day'): array
    {
        $days = match($period) {
            'day' => 1,
            'week' => 7,
            'month' => 30,
            default => 7,
        };

        $events = TelemetryEvent::where('actor_id', $learnerId)
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        return [
            'learner_id' => $learnerId,
            'period' => $period,
            'total_time_seconds' => $events->sum('duration_seconds'),
            'total_events' => $events->count(),
            'avg_session_time' => $events->count() > 0
                ? $events->sum('duration_seconds') / max(1, $events->groupBy('session_id')->count())
                : 0,
            'time_by_verb' => $events->groupBy('verb')
                ->map(fn($g) => $g->sum('duration_seconds'))
                ->toArray(),
        ];
    }

    /**
     * Get important learning events
     * @param int $learnerId
     * @return array
     */
    public function getImportantEvents(int $learnerId): array
    {
        $events = TelemetryEvent::where('actor_id', $learnerId)
            ->whereIn('verb', [
                'video_paused',
                'video_replayed', 
                'hint_opened',
                'rapid_guessing',
                'repeated_failure',
                'inactivity',
                'section_revisit',
            ])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return [
            'events' => $events->map(fn($e) => [
                'verb' => $e->verb,
                'object' => $e->object_id,
                'timestamp' => $e->timestamp,
            ]),
            'count' => $events->count(),
        ];
    }

    protected function normalizeStatement(array $statement): array
    {
        return [
            'verb' => $this->normalizeVerb($statement['verb']['id'] ?? ''),
            'object' => $statement['object']['id'] ?? null,
            'context' => $statement['context']['registration'] ?? null,
            'result' => $statement['result'] ?? null,
            'duration_seconds' => $this->extractDurationSeconds($statement['result'] ?? null),
            'timestamp' => $statement['timestamp'] ?? now(),
        ];
    }

    /**
     * Best-effort duration in seconds from an xAPI result: a numeric
     * `duration_seconds`, or an ISO-8601 `duration` (e.g. "PT1M30S"). Returns 0
     * when absent or unparseable.
     */
    protected function extractDurationSeconds($result): int
    {
        if (! is_array($result)) {
            return 0;
        }
        if (isset($result['duration_seconds']) && is_numeric($result['duration_seconds'])) {
            return max(0, (int) $result['duration_seconds']);
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

    protected function normalizeVerb(string $verb): string
    {
        $verbMap = [
            'http://adlnet.gov/expapi/verbs/initialized' => 'initialized',
            'http://adlnet.gov/expapi/verbs/completed' => 'completed',
            'http://adlnet.gov/expapi/verbs/passed' => 'passed',
            'http://adlnet.gov/expapi/verbs/failed' => 'failed',
            'http://adlnet.gov/expapi/verbs/attempted' => 'attempted',
            'http://adlnet.gov/expapi/verbs/interacted' => 'interacted',
            'http://adlnet.gov/expapi/verbs/suspended' => 'suspended',
            'http://adlnet.gov/expapi/verbs/resumed' => 'resumed',
            'http://adlnet.gov/expapi/verbs/progressed' => 'progressed',
            'http://adlnet.gov/expapi/verbs/answered' => 'answered',
            'http://adlnet.gov/expapi/verbs/experienced' => 'experienced',
            // Custom verbs
            'https://pedagogy.ai/verbs/paused' => 'video_paused',
            'https://pedagogy.ai/verbs/replayed' => 'video_replayed',
            'https://pedagogy.ai/verbs/hint_used' => 'hint_opened',
            'https://pedagogy.ai/verbs/guessed_rapidly' => 'rapid_guessing',
            'https://pedagogy.ai/verbs/failed_repeatedly' => 'repeated_failure',
            'https://pedagogy.ai/verbs/became_inactive' => 'inactivity',
            'https://pedagogy.ai/verbs/revisited' => 'section_revisit',
        ];

        return $verbMap[$verb] ?? $verb;
    }

    protected function calculateDuration($events): int
    {
        if ($events->isEmpty()) return 0;

        $first = $events->min('timestamp');
        $last = $events->max('timestamp');

        if (!$first || !$last) return 0;

        return strtotime($last) - strtotime($first);
    }
}