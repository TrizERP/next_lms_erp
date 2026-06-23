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
     * Process xAPI statement
     * @param array $statement
     * @return TelemetryEvent
     */
    public function processStatement(array $statement): TelemetryEvent
    {
        $normalized = $this->normalizeStatement($statement);
        
        return TelemetryEvent::create([
            'actor_id' => $normalized['actor'],
            'verb' => $normalized['verb'],
            'object_id' => $normalized['object'],
            'context_id' => $normalized['context'],
            'result' => $normalized['result'],
            'raw_statement' => $statement,
            'timestamp' => $normalized['timestamp'],
        ]);
    }

    /**
     * Process multiple statements in batch
     * @param array $statements
     * @return array
     */
    public function processBatch(array $statements): array
    {
        $processed = 0;
        $failed = 0;

        foreach ($statements as $statement) {
            try {
                $this->processStatement($statement);
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
        $events = TelemetryEvent::where('context_id', $sessionId)->get();

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
                ? $events->sum('duration_seconds') / $events->groupBy('context_id')->count() 
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
            'actor' => $statement['actor']['mbox'] ?? $statement['actor']['account'] ?? null,
            'verb' => $this->normalizeVerb($statement['verb']['id'] ?? ''),
            'object' => $statement['object']['id'] ?? null,
            'context' => $statement['context']['registration'] ?? null,
            'result' => $statement['result'] ?? null,
            'timestamp' => $statement['timestamp'] ?? now(),
        ];
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