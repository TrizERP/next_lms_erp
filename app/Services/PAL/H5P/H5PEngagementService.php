<?php

namespace App\Services\PAL\H5P;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H5P engagement metadata (§8.3) — computed, never authored.
 *
 * The spec lists per-content figures: `completion_rate`, `avg_engagement_score`,
 * `avg_session_duration_minutes`, `usage_count`. None of those are properties of
 * a content type; they are properties of how learners actually behaved on it.
 * So they are derived here from `pal_telemetry_events` (the xAPI store) with
 * `pal_learning_events` as the secondary source, and they are **null when there
 * is no telemetry** rather than a plausible-looking default.
 *
 * Only two numbers in the model are authored: `engagement_weight` and the four
 * signal weights of the engagement score. Both come from the registry, so a
 * deployment re-tunes them in data.
 *
 * Engagement score composition (PAL V4 AI Pedagogy Engine):
 *   time_on_task(0.30) + interaction_rate(0.25)
 *     + session_return(0.25) + voluntary_extension(0.20)
 * Each signal is normalised to 0–100 against the reference below and the
 * composite is multiplied by the type's engagement_weight, capped at 100.
 */
class H5PEngagementService
{
    /**
     * Normalisation references. A signal at or above its reference scores 100.
     * They are deliberately explicit so a reported score can be explained:
     * every response carries the reference it was computed against.
     */
    public const REFERENCE = [
        // A session at or past the type's expected duration is full marks.
        'time_on_task_default_minutes' => 8,
        // Interactions per session that count as a fully engaged session.
        'interaction_rate_target' => 6,
        // Distinct days a learner returns to the type within the window.
        'session_return_target_days' => 3,
        // Fraction over expected duration that counts as voluntary extension.
        'voluntary_extension_threshold' => 1.25,
    ];

    /** Rolling window for "current" engagement, in days. */
    public const WINDOW_DAYS = 90;

    public function __construct(protected H5PModelRegistry $registry)
    {
    }

    /**
     * Engagement metadata for every H5P type in a context.
     *
     * @return array<string,array> h5p_type => metadata block
     */
    public function forTypes(array $context, ?int $windowDays = null): array
    {
        $tenant = $this->tenant($context);
        $rows = $this->aggregate($context, 'h5p_type', $windowDays);
        $out = [];

        foreach ($this->registry->types($tenant) as $code => $type) {
            $out[$code] = $this->shape($code, $type, $rows[$code] ?? null, $windowDays);
        }

        return $out;
    }

    /** Engagement metadata for one type. */
    public function forType(string $h5pType, array $context, ?int $windowDays = null): ?array
    {
        $tenant = $this->tenant($context);
        $code = $this->registry->normalize('h5p_types', $h5pType, $tenant);
        if ($code === null) {
            return null;
        }

        $rows = $this->aggregate($context, 'h5p_type', $windowDays);

        return $this->shape($code, $this->registry->type($code, $tenant) ?? [], $rows[$code] ?? null, $windowDays);
    }

    /**
     * Engagement metadata per node, keyed by node key ("type:id").
     *
     * Telemetry addresses a node through `object_id`; the pipeline writes the
     * node key there, so an event can always be traced back to the exact
     * scenario / video / card it came from.
     */
    public function forNodes(array $nodeKeys, array $context, ?int $windowDays = null): array
    {
        $nodeKeys = array_values(array_unique(array_filter($nodeKeys)));
        if ($nodeKeys === []) {
            return [];
        }

        $rows = $this->aggregate($context, 'object_id', $windowDays, $nodeKeys);
        $tenant = $this->tenant($context);
        $out = [];

        foreach ($nodeKeys as $key) {
            $code = explode(':', $key)[0] ?? '';
            $type = $this->registry->type($code, $tenant) ?? [];
            $out[$key] = $this->shape($code, $type, $rows[$key] ?? null, $windowDays);
        }

        return $out;
    }

    /**
     * Estate-wide totals for the workspace header: how much telemetry exists
     * at all, and how much of it is attributable to a known H5P type.
     */
    public function summary(array $context, ?int $windowDays = null): array
    {
        $window = $windowDays ?? self::WINDOW_DAYS;

        if (! $this->telemetryReady()) {
            return [
                'available' => false,
                'reason' => 'The xAPI event store (pal_telemetry_events) is not present or not yet migrated on this database.',
                'window_days' => $window,
                'total_events' => 0,
                'typed_events' => 0,
                'learners' => 0,
                'sessions' => 0,
                'last_event_at' => null,
            ];
        }

        $base = $this->scopedTelemetry($context, $window);

        $totals = (clone $base)
            ->selectRaw('count(*) as total_events')
            ->selectRaw('count(distinct actor_id) as learners')
            ->selectRaw('count(distinct session_id) as sessions')
            ->selectRaw('max(`timestamp`) as last_event_at')
            ->first();

        $typed = (clone $base)->whereNotNull('h5p_type')->where('h5p_type', '!=', '')->count();

        return [
            'available' => true,
            'reason' => null,
            'window_days' => $window,
            'total_events' => (int) ($totals->total_events ?? 0),
            'typed_events' => $typed,
            'learners' => (int) ($totals->learners ?? 0),
            'sessions' => (int) ($totals->sessions ?? 0),
            'last_event_at' => $totals->last_event_at ?? null,
        ];
    }

    // ── Aggregation ─────────────────────────────────────────────────────────

    /**
     * One grouped pass over the xAPI store.
     *
     * `attempts`/`completions` are counted from the verbs the registry maps to
     * the attempted and completed PAL event types, so a deployment that adds a
     * custom completion verb is counted without a code change.
     *
     * @param  string  $groupBy  'h5p_type' | 'object_id'
     */
    protected function aggregate(array $context, string $groupBy, ?int $windowDays, ?array $only = null): array
    {
        if (! $this->telemetryReady()) {
            return [];
        }

        $window = $windowDays ?? self::WINDOW_DAYS;
        $tenant = $this->tenant($context);

        $completedVerbs = $this->verbsForEventTypes(['content_completed', 'portfolio_submitted', 'content_passed'], $tenant);
        $attemptedVerbs = $this->verbsForEventTypes(['content_attempted', 'content_started', 'content_experienced'], $tenant);

        $query = $this->scopedTelemetry($context, $window)
            ->whereNotNull($groupBy)
            ->where($groupBy, '!=', '');

        if ($only !== null) {
            $query->whereIn($groupBy, $only);
        }

        $completedIn = $this->inList($completedVerbs);
        $attemptedIn = $this->inList($attemptedVerbs);

        // A completion is either a verb the registry maps to a completed event
        // type, OR any statement whose xAPI result says completion: true —
        // H5P routinely reports completion on an `answered` statement, and
        // counting only the verb would under-report every such activity.
        $completedExpr = "(verb in ({$completedIn}) "
            . "or JSON_UNQUOTE(JSON_EXTRACT(result, '$.completion')) in ('true','1'))";

        $rows = $query
            ->groupBy($groupBy)
            ->selectRaw("{$groupBy} as bucket")
            ->selectRaw('count(*) as events')
            ->selectRaw('count(distinct actor_id) as learners')
            ->selectRaw('count(distinct session_id) as sessions')
            ->selectRaw('count(distinct date(`timestamp`)) as active_days')
            ->selectRaw('sum(duration_seconds) as total_seconds')
            ->selectRaw("sum(case when {$completedExpr} then 1 else 0 end) as completions")
            ->selectRaw("sum(case when verb in ({$attemptedIn}) then 1 else 0 end) as attempts")
            ->selectRaw('max(`timestamp`) as last_event_at')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->bucket] = $row;
        }

        return $out;
    }

    /**
     * Turn one aggregate row into the §8.3 metadata block.
     *
     * With no telemetry every computed field is null and `sample_size` is 0 —
     * the UI is expected to say "not measured yet", not render a zero.
     */
    protected function shape(string $code, array $type, $row, ?int $windowDays): array
    {
        $metadata = $type['metadata'] ?? [];
        $weight = (float) ($metadata['engagement_weight'] ?? 1.0);
        $expectedMinutes = (int) ($metadata['estimated_completion_minutes'] ?? self::REFERENCE['time_on_task_default_minutes']);

        $authored = [
            'h5p_type' => $code,
            'engagement_weight' => $weight,
            'fluency_trackable' => $metadata['fluency_trackable'] ?? 'no',
            'xapi_events_generated' => array_values((array) ($metadata['xapi_events'] ?? [])),
            'social_mode' => $metadata['social_mode'] ?? 'individual',
            'gamification_potential' => $metadata['gamification_potential'] ?? 'low',
            'offline_compatible' => (bool) ($metadata['offline_compatible'] ?? false),
            'mobile_optimised' => (bool) ($metadata['mobile_optimised'] ?? true),
            'retry_allowed' => (bool) ($metadata['retry_allowed'] ?? true),
            'expected_completion_minutes' => $expectedMinutes,
        ];

        if ($row === null) {
            return $authored + [
                'measured' => false,
                'sample_size' => 0,
                'window_days' => $windowDays ?? self::WINDOW_DAYS,
                'usage_count' => 0,
                'learners' => 0,
                'sessions' => 0,
                'completion_rate' => null,
                'avg_session_duration_minutes' => null,
                'avg_engagement_score' => null,
                'signals' => null,
                'last_event_at' => null,
            ];
        }

        $events = (int) $row->events;
        $sessions = max(1, (int) $row->sessions);
        $learners = max(1, (int) $row->learners);
        $attempts = (int) $row->attempts;
        $completions = (int) $row->completions;
        $totalSeconds = (int) $row->total_seconds;

        // Denominator: explicit attempts if the client sends them, otherwise
        // distinct sessions — a session that produced events but never a
        // completion is an incomplete attempt either way.
        $denominator = max($attempts, $sessions);
        $avgSessionMinutes = round(($totalSeconds / $sessions) / 60, 2);

        $signals = $this->signals(
            avgSessionMinutes: $avgSessionMinutes,
            expectedMinutes: max(1, $expectedMinutes),
            events: $events,
            sessions: $sessions,
            activeDays: (int) $row->active_days,
            learners: $learners
        );

        return $authored + [
            'measured' => true,
            'sample_size' => $events,
            'window_days' => $windowDays ?? self::WINDOW_DAYS,
            'usage_count' => $events,
            'learners' => (int) $row->learners,
            'sessions' => (int) $row->sessions,
            'completion_rate' => $denominator > 0 ? round(min(1.0, $completions / $denominator), 4) : null,
            'avg_session_duration_minutes' => $avgSessionMinutes,
            'avg_engagement_score' => $this->composite($signals, $weight),
            'signals' => $signals,
            'last_event_at' => $row->last_event_at,
        ];
    }

    /**
     * The four engagement signals, each 0–100, with the reference each was
     * scored against so the number can be defended in the UI.
     */
    protected function signals(
        float $avgSessionMinutes,
        int $expectedMinutes,
        int $events,
        int $sessions,
        int $activeDays,
        int $learners
    ): array {
        $timeRatio = $avgSessionMinutes / $expectedMinutes;
        $interactionRate = $events / $sessions;
        $returnDays = $activeDays / $learners;
        $extension = max(0.0, $timeRatio - self::REFERENCE['voluntary_extension_threshold']);

        return [
            'time_on_task' => [
                'score' => $this->scale($timeRatio, 1.0),
                'observed' => round($avgSessionMinutes, 2),
                'reference' => $expectedMinutes,
                'unit' => 'minutes per session',
            ],
            'interaction_rate' => [
                'score' => $this->scale($interactionRate, self::REFERENCE['interaction_rate_target']),
                'observed' => round($interactionRate, 2),
                'reference' => self::REFERENCE['interaction_rate_target'],
                'unit' => 'events per session',
            ],
            'session_return' => [
                'score' => $this->scale($returnDays, self::REFERENCE['session_return_target_days']),
                'observed' => round($returnDays, 2),
                'reference' => self::REFERENCE['session_return_target_days'],
                'unit' => 'active days per learner',
            ],
            'voluntary_extension' => [
                'score' => $this->scale($extension, 0.5),
                'observed' => round($extension, 2),
                'reference' => 0.5,
                'unit' => 'fraction of expected duration exceeded',
            ],
        ];
    }

    /** Weighted composite, scaled by the type's engagement weight, capped at 100. */
    protected function composite(array $signals, float $weight): float
    {
        $weights = $this->registry->engagementWeights();
        $score = 0.0;
        $applied = 0.0;

        foreach ($signals as $key => $signal) {
            $signalWeight = (float) ($weights[$key] ?? 0);
            if ($signalWeight <= 0) {
                continue;
            }
            $score += $signal['score'] * $signalWeight;
            $applied += $signalWeight;
        }

        if ($applied <= 0) {
            return 0.0;
        }

        return round(min(100.0, ($score / $applied) * $weight), 1);
    }

    protected function scale(float $observed, float $reference): float
    {
        if ($reference <= 0) {
            return 0.0;
        }

        return round(min(100.0, max(0.0, $observed / $reference * 100)), 1);
    }

    // ── Scoping ─────────────────────────────────────────────────────────────

    /**
     * Telemetry restricted to the caller's institute and, when the estate
     * records it, the chapter's learners. `pal_telemetry_events` has no tenant
     * column of its own — the PAL tables key on learner_id — so the tenant
     * boundary is applied by resolving actors through the learner tables, the
     * same rule PalApiAuth uses.
     */
    protected function scopedTelemetry(array $context, int $windowDays)
    {
        $query = DB::table('pal_telemetry_events')
            ->where('timestamp', '>=', now()->subDays($windowDays));

        // `actor_id` is the learner's user id; both students and staff can
        // generate H5P events, so both tables are consulted — the same
        // resolution PalApiAuth::resolveLearnerTenant() uses.
        $tenant = $this->tenant($context);
        if ($tenant !== null) {
            $learnerTables = array_values(array_filter(
                ['tblstudent', 'tbluser'],
                fn (string $table) => Schema::hasTable($table) && Schema::hasColumn($table, 'sub_institute_id')
            ));

            if ($learnerTables !== []) {
                $query->where(function ($outer) use ($learnerTables, $tenant) {
                    foreach ($learnerTables as $table) {
                        $outer->orWhereIn('actor_id', function ($sub) use ($table, $tenant) {
                            $sub->from($table)->select('id')->where('sub_institute_id', $tenant);
                        });
                    }
                });
            }
        }

        // A chapter-scoped read narrows to the nodes that chapter actually has.
        if (! empty($context['node_keys'])) {
            $query->whereIn('object_id', (array) $context['node_keys']);
        }

        return $query;
    }

    /** Registry verb codes whose PAL event type is one of the given types. */
    protected function verbsForEventTypes(array $eventTypes, ?int $tenant): array
    {
        $verbs = [];
        foreach ($this->registry->verbEventTypeMap($tenant) as $verb => $eventType) {
            if (in_array($eventType, $eventTypes, true)) {
                $verbs[] = $verb;
            }
        }

        return $verbs;
    }

    /** Quoted IN list; a sentinel keeps the SQL valid when no verb matches. */
    protected function inList(array $verbs): string
    {
        if ($verbs === []) {
            return "''";
        }

        return implode(',', array_map(fn (string $verb) => DB::getPdo()->quote($verb), $verbs));
    }

    protected function telemetryReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('pal_telemetry_events')
            && Schema::hasColumn('pal_telemetry_events', 'h5p_type');
    }

    protected function tenant(array $context): ?int
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);

        return $tenant > 0 ? $tenant : null;
    }
}
