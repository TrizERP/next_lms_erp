<?php

namespace App\Services\CoursePresentation;

/**
 * What a teacher is told about how a generation run is getting on. Everything here is derived from facts the run itself
 * recorded (the pipeline's own stage lines, real timestamps, the durations of earlier finished runs): no percentage is
 * invented and no stage is called done unless a later one was seen. Pure: it reads nothing and writes nothing.
 */
class GenerationProgress
{
    /** Fewest finished comparable runs before a time estimate is offered. */
    public const MIN_SAMPLES = 3;

    /**
     * The pipeline's own stage word (the first word of each progress line) -> the stage it belongs to. The order of
     * stages() is the order of the pipeline.
     */
    private const SLIDES = [
        'prepare' => ['load', 'context', 'questions', 'map'],
        'content' => ['plan', 'content'],
        'images' => ['images', 'pictures'],
        'interact' => ['interact', 'activities'],
        'validate' => ['validate'],
    ];

    private const STORY = [
        'prepare' => ['load', 'context', 'choose'],
        'content' => ['plan', 'write', 'fix', 'story'],
        'images' => ['pictures', 'images'],
        'validate' => ['validate'],
    ];

    private const LABELS = [
        'queued' => ['Request accepted', 'The request is saved and waiting for a background worker to pick it up.'],
        'prepare' => ['Preparing chapter content', 'Reading the chapter\'s concepts, questions and learning map.'],
        'content' => ['Generating content', 'Writing the slides in the shape of the teaching method you chose.'],
        'images' => ['Planning and resolving images', 'Finding or drawing a picture for each slide and checking it can be read.'],
        'interact' => ['Choosing interactions', 'Deciding which slides earn an interaction and placing the practice questions.'],
        'validate' => ['Validating the presentation', 'Checking the draft against the pipeline\'s own rules.'],
        'verify' => ['Final verification', 'Checking pictures, coverage of the chapter\'s concepts and presentation quality.'],
        'ready' => ['Ready for review', 'The draft passed every check and waits for your review.'],
    ];

    /** @return array<int,string> stage keys in pipeline order */
    public static function stageKeys(): array
    {
        return ['queued', 'prepare', 'content', 'images', 'interact', 'validate', 'verify', 'ready'];
    }

    /** The stage a pipeline progress line belongs to, or null for a line that is not a stage line. */
    public static function stageOfLine(string $line, string $format): ?string
    {
        if (!preg_match('/^([a-z]+)\s/i', trim($line), $m)) {
            return null;
        }
        $word = strtolower($m[1]);
        foreach ($format === 'branching_scenario' ? self::STORY : self::SLIDES as $stage => $words) {
            if (in_array($word, $words, true)) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * Moves a run's recorded stage forward from new pipeline lines. A stage never moves backwards (the pipeline revisits
     * "images" while repairing), and the first time each stage is seen is kept for timing.
     *
     * @param array<string,mixed> $status
     * @param array<int,string> $lines
     */
    public static function advance(array &$status, array $lines, ?int $now = null): void
    {
        $now ??= time();
        $order = self::stageKeys();
        $current = array_search((string) ($status['stage'] ?? 'queued'), $order, true);
        $current = $current === false ? 0 : $current;
        foreach ($lines as $line) {
            $stage = self::stageOfLine($line, (string) ($status['format'] ?? 'interactive_slides'));
            if ($stage === null) {
                continue;
            }
            $at = array_search($stage, $order, true);
            if ($at !== false && $at > $current) {
                $current = $at;
                $status['stage'] = $stage;
                $status['stage_times'][$stage] = date('c', $now);
            }
        }
    }

    /**
     * Seconds the finished runs took, newest comparable evidence first: the same chapter, teaching method and format; else the
     * same teaching method and format; else the same format. Only a tier with at least MIN_SAMPLES runs is used.
     *
     * @param array<int,array<string,mixed>> $finished earlier runs of the school that finished a draft, each with duration_seconds
     * @return array{durations:array<int,int>,basis:string}|null
     */
    public static function comparable(array $finished, int $chapterId, string $method, string $format): ?array
    {
        $format = $format ?: 'interactive_slides';
        $ok = fn (array $r) => (int) ($r['duration_seconds'] ?? 0) > 0 && in_array($r['state'] ?? '', ['ready', 'stored'], true)
            && (string) ($r['format'] ?? 'interactive_slides') === $format;
        $tiers = [
            ['this chapter, teaching method and format', fn (array $r) => $ok($r) && (int) $r['chapter_id'] === $chapterId && (string) ($r['method'] ?? '') === $method],
            ['this teaching method and format', fn (array $r) => $ok($r) && (string) ($r['method'] ?? '') === $method],
            ['this format', fn (array $r) => $ok($r)],
        ];
        foreach ($tiers as [$basis, $filter]) {
            $durations = array_values(array_map(fn ($r) => (int) $r['duration_seconds'], array_filter($finished, $filter)));
            if (count($durations) >= self::MIN_SAMPLES) {
                return ['durations' => $durations, 'basis' => $basis];
            }
        }

        return null;
    }

    /**
     * A time range from earlier finished runs, never a single promise. Needs a started run and enough history.
     *
     * @param array{durations:array<int,int>,basis:string}|null $history
     * @return array{available:bool,min_seconds?:int,max_seconds?:int,overdue?:bool,samples?:int,basis?:string}
     */
    public static function estimate(?array $history, ?int $elapsed): array
    {
        if ($history === null || $elapsed === null) {
            return ['available' => false];
        }
        $d = $history['durations'];
        sort($d);
        $low = self::percentile($d, 0.25);
        $high = max(self::percentile($d, 0.75), $low + 60);
        $base = ['available' => true, 'samples' => count($d), 'basis' => $history['basis']];
        if ($elapsed >= $high) {
            return $base + ['overdue' => true, 'min_seconds' => 0, 'max_seconds' => 0];
        }

        return $base + ['overdue' => false, 'min_seconds' => max(0, $low - $elapsed), 'max_seconds' => max(0, $high - $elapsed)];
    }

    /** @param array<int,int> $sorted */
    private static function percentile(array $sorted, float $p): int
    {
        $i = $p * (count($sorted) - 1);
        $lo = (int) floor($i);
        $hi = (int) ceil($i);

        return (int) round($sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($i - $lo));
    }

    /**
     * Where a run's time went, from the times the run itself recorded: the wait for a worker, each stage, the total, and whether the
     * target was kept. A stage lasts until the next one began; the last one until the run finished.
     *
     * @param array<string,mixed> $status
     * @return array<string,mixed>
     */
    public static function timings(array $status, int $target = 0): array
    {
        $at = fn ($v) => $v ? strtotime((string) $v) : false;
        $created = $at($status['created_at'] ?? null);
        $started = $at($status['started_at'] ?? null);
        $finished = $at($status['finished_at'] ?? null);
        $seen = [];
        foreach (self::stageKeys() as $key) {
            if ($key !== 'queued' && ($t = $at($status['stage_times'][$key] ?? null)) !== false) {
                $seen[$key] = $t;
            }
        }
        $keys = array_keys($seen);
        $stages = [];
        foreach ($keys as $i => $key) {
            $end = $keys[$i + 1] ?? null;
            $to = $end !== null ? $seen[$end] : $finished;
            $stages[$key] = ['started_at' => date('c', $seen[$key]), 'seconds' => $to === false || $key === 'ready' ? null : max(0, $to - $seen[$key])];
        }
        $total = $started !== false && $finished !== false ? max(0, $finished - $started) : null;
        $failed = ($status['state'] ?? '') === 'failed';

        return [
            'queue_wait_seconds' => $created !== false && $started !== false ? max(0, $started - $created) : null,
            'stages' => $stages,
            'total_seconds' => $total,
            'target_seconds' => $target > 0 ? $target : null,
            'within_target' => $target > 0 && $total !== null && !$failed ? $total <= $target : null,
            'failed_stage' => $failed ? (string) ($status['stage'] ?? '') : null,
            'failure' => $failed ? mb_substr((string) ($status['error'] ?? ''), 0, 300) : null,
        ];
    }

    /**
     * What went wrong, in the teacher's terms, and whether starting a new draft may succeed. Never decides from a code the
     * server does not have: anything it cannot place is "unknown" and offered a retry, because nothing was stored.
     *
     * @return array{kind:string,retryable:bool,hint:string}|null
     */
    public static function classifyError(?string $error): ?array
    {
        if ($error === null || trim($error) === '') {
            return null;
        }
        $map = [
            ['rate_limit', '/per-minute rate limit was reached/i', true, 'The AI model\'s per-minute rate limit was reached and the wait did not fit in the time allowed. Try again in a few minutes.'],
            ['overload', '/overloaded|high demand|UNAVAILABLE/i', true, 'The AI provider is overloaded right now. This usually clears within minutes: try again shortly. Your chapter and settings are fine.'],
            ['quota', '/quota|rate.?limit|429|exhaust|too many requests|resource.?exhausted/i', true, 'The AI model is at its usage limit. Try again later.'],
            ['budget', '/time allowed for this generation ran out|no time left for another repair/i', true, 'The generation ran out of its time allowance. Try again; a busy AI model is usually the cause.'],
            ['queue', '/worker did not start|stopped reporting progress|queue/i', true, 'The background worker did not run this. Try again; if it keeps happening, ask an administrator to check the queue worker.'],
            ['images', '/image|picture|photograph|diagram/i', true, 'Pictures could not be found or read. A new draft may find different ones.'],
            ['storage', '/could not be stored|not linked|duplicate|already stored/i', false, 'Saving did not complete. Nothing was published.'],
            ['validation', '/validation|quality rules|did not meet|did not pass|teaching method/i', false, 'The draft did not meet the presentation rules for this chapter and method.'],
        ];
        foreach ($map as [$kind, $pattern, $retryable, $hint]) {
            if (preg_match($pattern, $error)) {
                return ['kind' => $kind, 'retryable' => $retryable, 'hint' => $hint];
            }
        }

        return ['kind' => 'unknown', 'retryable' => true, 'hint' => 'Nothing was stored. You can start a new draft.'];
    }

    /**
     * @param array<string,mixed> $status a run's status file
     * @param array{durations:array<int,int>,basis:string}|null $history see comparable()
     * @param string $worker WorkerHeartbeat::state() (only meaningful while queued)
     * @return array<string,mixed>
     */
    public static function describe(array $status, ?array $history, string $worker, ?int $now = null): array
    {
        $now ??= time();
        $state = (string) ($status['state'] ?? '');
        $saving = !empty($status['saving']);
        $lifecycle = match (true) {
            $state === 'queued' => 'queued',
            $state === 'running' => 'processing',
            $state === 'ready' && $saving => 'saving',
            in_array($state, ['ready', 'stored'], true) => 'completed',
            $state === 'failed' => 'failed',
            $state === 'discarded' => 'cancelled',
            default => $state,
        };

        $startedAt = isset($status['started_at']) ? strtotime((string) $status['started_at']) : false;
        $createdAt = strtotime((string) ($status['created_at'] ?? ''));
        $finishedAt = isset($status['finished_at']) ? strtotime((string) $status['finished_at']) : false;
        $running = $state === 'running' && $startedAt !== false;
        $elapsed = match (true) {
            $startedAt === false => null,
            $finishedAt !== false => max(0, $finishedAt - $startedAt),
            $running => max(0, $now - $startedAt),
            default => isset($status['duration_seconds']) ? (int) $status['duration_seconds'] : null,
        };

        $order = self::stageKeys();
        $stage = (string) ($status['stage'] ?? ($state === 'queued' ? 'queued' : 'prepare'));
        if (in_array($state, ['ready', 'stored'], true)) {
            $stage = 'ready';
        }
        $at = array_search($stage, $order, true);
        $at = $at === false ? 0 : $at;
        $stages = [];
        foreach ($order as $i => $key) {
            $done = $i < $at || ($key === 'ready' && $at === $i && in_array($state, ['ready', 'stored'], true)) || ($key === 'queued' && $state !== 'queued');
            $stages[] = [
                'key' => $key,
                'label' => self::LABELS[$key][0],
                'description' => self::LABELS[$key][1],
                'status' => $state === 'failed' && !$done && $i === $at ? 'failed' : ($done ? 'done' : ($i === $at && in_array($state, ['queued', 'running'], true) ? 'current' : 'pending')),
                'at' => $status['stage_times'][$key] ?? ($key === 'queued' ? ($status['created_at'] ?? null) : null),
            ];
        }
        $doneCount = count(array_filter($stages, fn ($s) => $s['status'] === 'done'));
        $percent = match (true) {
            in_array($state, ['ready', 'stored'], true) => 100,
            $state === 'queued' => 0,
            $state === 'running' => (int) floor(100 * $doneCount / count($order)),
            default => null,
        };

        $error = $state === 'failed' ? ($status['error'] ?? null) : null;
        $out = [
            'lifecycle' => $lifecycle,
            'stage' => $stage,
            'stages' => $stages,
            'percent' => $percent,
            'percent_basis' => 'stages',
            'started_at' => $startedAt === false ? null : date('c', $startedAt),
            'finished_at' => $finishedAt === false ? null : date('c', $finishedAt),
            'elapsed_seconds' => $elapsed,
            'duration_seconds' => isset($status['duration_seconds']) ? (int) $status['duration_seconds'] : null,
            'estimate' => $running ? self::estimate($history, $elapsed) : ['available' => false],
            'saving' => $saving,
            'queue' => $state === 'queued'
                ? ['waiting_seconds' => $createdAt === false ? null : max(0, $now - $createdAt), 'worker' => $worker]
                : null,
            'failure' => self::classifyError($error),
            'timings' => $status['timings'] ?? null,
        ];

        return $out;
    }
}
