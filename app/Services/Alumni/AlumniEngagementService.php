<?php

namespace App\Services\Alumni;

use Illuminate\Support\Facades\DB;

/**
 * Engagement ledger and score (spec sections 21-22).
 *
 * An engagement is an alumni_activity row whose activity_type is one of TYPES and whose `points` come from the
 * tenant's weights (alumni_settings 'engagement_weights', defaulting to the spec's examples). The score is
 * the sum of points inside a rolling window (default 24 months), capped at 100, so recent involvement counts
 * and one very generous year cannot hide a quiet decade. Levels are thresholds on that score.
 *
 * record() is IDEMPOTENT for automatic sources: pass refType + refId (a mentoring session, an event
 * attendance, a donation) and recording the same source twice adds nothing, so a retried job or a double click
 * cannot inflate a score. Manual entries (no ref) always add a row.
 */
class AlumniEngagementService
{
    /** key => label (spec section 21) */
    public const TYPES = [
        'event_participation' => 'Event participation', 'guest_lecture' => 'Guest lecture', 'mentoring' => 'Mentoring',
        'internship_support' => 'Internship support', 'job_placement_support' => 'Job placement support',
        'student_guidance' => 'Student guidance', 'donation' => 'Donation', 'scholarship_sponsorship' => 'Scholarship sponsorship',
        'campus_visit' => 'Campus visit', 'career_counselling' => 'Career counselling', 'industry_collaboration' => 'Industry collaboration',
        'volunteering' => 'Volunteering', 'referral' => 'Referral', 'content_contribution' => 'Content contribution',
    ];

    /** Spec examples where it gives one, otherwise a modest default. */
    public const DEFAULT_WEIGHTS = [
        'event_participation' => 10, 'guest_lecture' => 25, 'mentoring' => 20, 'internship_support' => 25,
        'job_placement_support' => 25, 'student_guidance' => 10, 'donation' => 30, 'scholarship_sponsorship' => 30,
        'campus_visit' => 5, 'career_counselling' => 15, 'industry_collaboration' => 20, 'volunteering' => 10,
        'referral' => 15, 'content_contribution' => 10,
    ];

    public const DEFAULT_LEVELS = ['highly_active' => 70, 'active' => 40, 'occasional' => 1];

    public const LEVEL_LABELS = ['highly_active' => 'Highly active', 'active' => 'Active', 'occasional' => 'Occasional', 'dormant' => 'Dormant'];

    public function __construct(private AlumniService $alumni)
    {
    }

    /** @return array<string, int> */
    public function weights(int $tenant): array
    {
        $out = self::DEFAULT_WEIGHTS;
        foreach ((array) $this->alumni->setting($tenant, 'engagement_weights', []) as $type => $pts) {
            if (isset($out[$type]) && is_numeric($pts) && $pts >= 0 && $pts <= 100) {
                $out[$type] = (int) $pts;
            }
        }

        return $out;
    }

    public function windowMonths(int $tenant): int
    {
        $m = (int) $this->alumni->setting($tenant, 'engagement_window_months', 24);

        return $m >= 1 && $m <= 120 ? $m : 24;
    }

    /**
     * Record an engagement. Returns the activity id, or null when the same source was already recorded.
     */
    public function record(int $tenant, int $alumniId, string $type, string $title, ?int $userId, ?string $occurredOn = null,
        ?int $points = null, ?string $refType = null, ?int $refId = null, ?string $detail = null): ?int
    {
        if (! isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException('Unknown engagement type.');
        }
        if ($refType !== null && $refId !== null) {
            $exists = DB::table('alumni_activity')->where('sub_institute_id', $tenant)->where('alumni_id', $alumniId)
                ->where('activity_type', $type)->where('ref_type', $refType)->where('ref_id', $refId)->exists();
            if ($exists) {
                return null;
            }
        }
        $points = $points ?? ($this->weights($tenant)[$type] ?? 0);
        $when = $occurredOn ? date('Y-m-d H:i:s', strtotime($occurredOn)) : now();
        $id = DB::table('alumni_activity')->insertGetId([
            'sub_institute_id' => $tenant, 'alumni_id' => $alumniId, 'activity_type' => $type,
            'title' => mb_substr($title, 0, 255), 'detail' => $detail, 'points' => max(0, min(100, $points)),
            'ref_type' => $refType, 'ref_id' => $refId, 'occurred_at' => $when,
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('alumni')->where('sub_institute_id', $tenant)->where('id', $alumniId)->update(['last_activity_at' => now()]);

        return $id;
    }

    /**
     * Score for each alumnus (0-100) inside the window.
     *
     * @param  array<int, int>|null  $alumniIds  null = everyone in the tenant that has any engagement
     * @return array<int, int> alumni_id => score
     */
    public function scores(int $tenant, ?array $alumniIds = null): array
    {
        $since = now()->subMonths($this->windowMonths($tenant));
        $q = DB::table('alumni_activity')->where('sub_institute_id', $tenant)
            ->whereIn('activity_type', array_keys(self::TYPES))->where('points', '>', 0)->where('occurred_at', '>=', $since);
        if ($alumniIds !== null) {
            if ($alumniIds === []) {
                return [];
            }
            $q->whereIn('alumni_id', $alumniIds);
        }
        $out = [];
        foreach ($q->groupBy('alumni_id')->selectRaw('alumni_id, SUM(points) as pts')->get() as $r) {
            $out[(int) $r->alumni_id] = (int) min(100, $r->pts);
        }
        if ($alumniIds !== null) {
            foreach ($alumniIds as $id) {
                $out[(int) $id] = $out[(int) $id] ?? 0;
            }
        }

        return $out;
    }

    public function level(int $tenant, int $score): string
    {
        $t = array_merge(self::DEFAULT_LEVELS, array_intersect_key((array) $this->alumni->setting($tenant, 'engagement_levels', []), self::DEFAULT_LEVELS));
        if ($score >= $t['highly_active']) {
            return 'highly_active';
        }
        if ($score >= $t['active']) {
            return 'active';
        }

        return $score >= $t['occasional'] ? 'occasional' : 'dormant';
    }
}
