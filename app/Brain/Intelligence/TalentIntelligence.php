<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Talent Management Intelligence — one institute, all-time (not year-scoped).
 *
 * ── WHAT THIS PORTS, AND FROM WHERE ─────────────────────────────────────────
 *
 * `App\Http\Controllers\api\TalentManagement\TalentDashboardController` already
 * computes a real, tenant-scoped aggregate across recruitment, performance,
 * onboarding, mobility and offboarding. This class reuses that same query
 * logic — same tables, same status vocabularies where they are still
 * accurate — reshaped into the Brain Intelligence L0/L1/L2/L3 pattern
 * (`coverage()` / `position()` / breakdowns / `dataQuality()`) that
 * {@see AttendanceIntelligence} establishes. `TalentDashboardController`
 * itself is untouched: it is read-only reference here.
 *
 * ── THE DEPLOYMENT HAS MOVED SINCE THAT CONTROLLER WAS WRITTEN ──────────────
 *
 * `TalentDashboardController`'s class doc says most of its target tables "do
 * not exist in this repo yet" and guards every one of them with
 * `Schema::hasTable()`. That was true when it was written. It is not true now:
 * every recruitment, onboarding, performance and offboarding table it
 * references has since been migrated and exists in this deployment. This
 * class was written after checking `information_schema.tables` directly
 * rather than trusting that docblock, and two things fell out of that check
 * that change what this class queries:
 *
 * 1. THE MOBILITY TABLE THE DASHBOARD READS DOES NOT EXIST.
 *    `TalentDashboardController` sources its `mobility` KPI from
 *    `talent_mobility_requests`. That table was never created — Internal
 *    Mobility & Succession landed under a different name entirely
 *    ({@see self::MOBILITY_TRANSFERS_TABLE}, {@see self::MOBILITY_PROMOTIONS_TABLE},
 *    {@see self::MOBILITY_APPLICATIONS_TABLE}, `s_mobility_jobs`,
 *    `s_mobility_succession_plans`, `s_mobility_talent_pools`). Because that
 *    dashboard guards the read with `Schema::hasTable('talent_mobility_requests')`,
 *    its mobility KPI silently and permanently reads zero in this deployment
 *    rather than erroring — which is the correct degrade, but it is degrading
 *    over a real absence, not a temporary one. This class reads the tables
 *    that actually exist instead.
 *
 * 2. THE OFFBOARDING STATUS VALUES THE DASHBOARD FILTERS ON DO NOT MATCH THE
 *    COLUMN. `TalentDashboardController::OFFBOARDING_ACTIVE_STATUSES` is
 *    `['initiated', 'notice-period', 'clearance', 'exit-interview',
 *    'awaiting-fnf']` — lowercase, hyphenated. The migration that actually
 *    creates `talent_offboarding_cases.status`
 *    (`2026_08_18_114000_create_talent_offboarding_tables.php`) defaults it
 *    to `'Resignation Submitted'` and documents the real value set as
 *    `'Resignation Submitted' | 'Notice Period' | 'Clearance' |
 *    'Exit Interview' | 'Awaiting F&F' | 'Closed'` — title case, spaced. A
 *    live row in this database carries `status = 'Notice Period'`, which
 *    matches none of the dashboard's five strings. So that KPI's `whereIn`
 *    can never match a real row here either. This class filters on the
 *    values the migration and the live data actually carry
 *    ({@see self::OFFBOARDING_OPEN_STATUS}).
 *
 * Both are reported honestly rather than silently: {@see self::dataQuality()}
 * names the absent `talent_mobility_requests` / `talent_offboarding_clearances`
 * tables so a reader comparing this screen against the older dashboard
 * understands why the two disagree, instead of assuming one of them is wrong
 * by accident.
 *
 * ── WHY NOTHING HERE IS FILTERED BY ACADEMIC YEAR ───────────────────────────
 *
 * Every recruitment, onboarding, performance, mobility and offboarding table
 * this class touches carries `sub_institute_id` and NOT `syear` — confirmed
 * against every migration that creates them. HR activity (a candidate
 * applying, an employee's clearance, a performance cycle) is not scoped to an
 * academic year the way a student's attendance is. The constructor still
 * accepts `$syear`, for the same reason every other Brain Intelligence class
 * does — the finding ledger and {@see ModuleLoop} tag signals with a year for
 * cross-module consistency — but it is never used to filter a query here, and
 * {@see self::coverage()} does not require it to be present.
 */
final class TalentIntelligence
{
    /* --------------------------------------------------------- recruitment */

    private const JOB_POSTINGS_TABLE = 'talent_job_postings';

    private const JOB_APPLICATIONS_TABLE = 'talent_job_applications';

    private const INTERVIEW_SCHEDULES_TABLE = 'talent_interview_schedules';

    private const OFFERS_TABLE = 'talent_offers';

    /* ---------------------------------------------------------- onboarding */

    private const ONBOARDING_JOURNEYS_TABLE = 'talent_onboarding_journeys';

    /* --------------------------------------------------------- performance */

    private const PERFORMANCE_CYCLES_TABLE = 's_performance_cycles';

    private const PERFORMANCE_REVIEWS_TABLE = 's_performance_reviews';

    /* ------------------------------------------------------------ mobility */

    /**
     * The real Internal Mobility & Succession tables. NOT `talent_mobility_requests`
     * — see the class docblock for why.
     */
    private const MOBILITY_JOBS_TABLE = 's_mobility_jobs';

    private const MOBILITY_APPLICATIONS_TABLE = 's_mobility_applications';

    private const MOBILITY_TRANSFERS_TABLE = 's_mobility_transfers';

    private const MOBILITY_PROMOTIONS_TABLE = 's_mobility_promotions';

    /* ----------------------------------------------------------- offboarding */

    private const OFFBOARDING_CASES_TABLE = 'talent_offboarding_cases';

    /* --------------------------------------------------------------- shared */

    private const DEPARTMENT_TABLE = 'hrms_departments';

    /**
     * Tables `TalentDashboardController` reads from that were never created in
     * this deployment — referenced here only so {@see self::dataQuality()} can
     * say so.
     */
    private const REFERENCED_BUT_ABSENT_TABLES = [
        'talent_mobility_requests' => 'Internal mobility is tracked in s_mobility_applications / '
            .'s_mobility_transfers / s_mobility_promotions instead; this table was never created.',
        'talent_offboarding_clearances' => 'The clearance checklist is stored as JSON on '
            .'talent_offboarding_cases.clearance_tasks instead; this table was never created.',
    ];

    /**
     * Application statuses that mean "still being screened", exactly as
     * validated by `talent_jobapplicationcontroller` and read by
     * {@see \App\Http\Controllers\api\TalentManagement\TalentDashboardController}.
     */
    public const SCREENING_STATUSES = ['Pending Review', 'Under Review', 'Shortlisted'];

    public const INTERVIEW_STATUS = 'Interview Scheduled';

    public const HIRED_STATUS = 'Hired';

    /** The only status this schema's offboarding cases treat as closed. */
    public const OFFBOARDING_CLOSED_STATUS = 'Closed';

    /** Onboarding journey statuses that mean the hire has not finished onboarding. */
    public const ONBOARDING_ACTIVE_STATUSES = ['not-started', 'in-progress', 'on-hold'];

    /** A department must carry at least this much talent activity to appear on its own row. */
    public const MIN_DEPARTMENT_ACTIVITY = 1;

    private readonly string $tenantId;

    private readonly ?string $syear;

    /** @var array<string,mixed> */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /* -------------------------------------------------------- L0: coverage */

    /** @return array<string,mixed> */
    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    /** @return array<string,mixed> */
    private function computeCoverage(): array
    {
        $postings = $this->count(self::JOB_POSTINGS_TABLE);
        $applications = $this->count(self::JOB_APPLICATIONS_TABLE);
        $offers = $this->count(self::OFFERS_TABLE);
        $journeys = $this->count(self::ONBOARDING_JOURNEYS_TABLE);
        $offboarding = $this->count(self::OFFBOARDING_CASES_TABLE);
        $reviews = $this->count(self::PERFORMANCE_REVIEWS_TABLE);
        $mobility = $this->count(self::MOBILITY_APPLICATIONS_TABLE)
            + $this->count(self::MOBILITY_TRANSFERS_TABLE)
            + $this->count(self::MOBILITY_PROMOTIONS_TABLE);

        $sources = [
            'recruitment' => $postings > 0 || $applications > 0,
            'onboarding' => $journeys > 0,
            'performance' => $reviews > 0,
            'mobility' => $mobility > 0,
            'offboarding' => $offboarding > 0,
        ];

        $counts = [
            'postings' => $postings,
            'applications' => $applications,
            'offers' => $offers,
            'onboardingJourneys' => $journeys,
            'offboardingCases' => $offboarding,
            'performanceReviews' => $reviews,
            'mobilityActivity' => $mobility,
        ];

        $available = in_array(true, $sources, true);

        return [
            'available' => $available,
            'reason' => $available
                ? null
                : "No recruitment, onboarding, performance, mobility or offboarding record exists for "
                    ."sub_institute_id {$this->tenantId}.",
            'sources' => $sources,
            'counts' => $counts,
        ];
    }

    /* -------------------------------------------------------- L1: position */

    /** @return array<string,mixed>|null */
    public function position(): ?array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    /** @return array<string,mixed>|null */
    private function computePosition(): ?array
    {
        if (! $this->coverage()['available']) {
            return null;
        }

        $postings = $this->postingsQuery();
        $totalPostings = $postings !== null ? (clone $postings)->count() : 0;
        $openPostings = $postings !== null
            ? (clone $postings)
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('deadline')->orWhereDate('deadline', '>=', now()->toDateString()))
                ->count()
            : 0;

        $applications = $this->applicationsQuery();
        $totalApplications = $applications !== null ? (clone $applications)->count() : 0;
        $screening = $applications !== null
            ? (clone $applications)->whereIn('status', self::SCREENING_STATUSES)->count()
            : 0;
        $interviewing = $applications !== null
            ? (clone $applications)->where('status', self::INTERVIEW_STATUS)->count()
            : 0;
        $hired = $applications !== null
            ? (clone $applications)->where('status', self::HIRED_STATUS)->count()
            : 0;

        $offersSent = SchemaCache::hasTable(self::OFFERS_TABLE)
            ? $this->scoped(self::OFFERS_TABLE)->where('status', 'sent')->count()
            : 0;

        $onboardingActive = SchemaCache::hasTable(self::ONBOARDING_JOURNEYS_TABLE)
            ? $this->scoped(self::ONBOARDING_JOURNEYS_TABLE)
                ->whereIn('status', self::ONBOARDING_ACTIVE_STATUSES)
                ->count()
            : 0;

        $activeMobility = $this->activeMobilityCount();

        $activeOffboarding = SchemaCache::hasTable(self::OFFBOARDING_CASES_TABLE)
            ? $this->scoped(self::OFFBOARDING_CASES_TABLE)
                ->where('status', '!=', self::OFFBOARDING_CLOSED_STATUS)
                ->count()
            : 0;

        $pendingReviews = SchemaCache::hasTable(self::PERFORMANCE_REVIEWS_TABLE)
            ? $this->scoped(self::PERFORMANCE_REVIEWS_TABLE)->where('status', 'pending')->count()
            : 0;

        $totalReviews = SchemaCache::hasTable(self::PERFORMANCE_REVIEWS_TABLE)
            ? $this->scoped(self::PERFORMANCE_REVIEWS_TABLE)->count()
            : 0;

        return [
            'postings' => $totalPostings,
            'openPostings' => $openPostings,
            'applications' => $totalApplications,
            'screeningApplications' => $screening,
            'interviewApplications' => $interviewing,
            'hiredApplications' => $hired,
            'screeningToInterviewRate' => $screening + $interviewing + $hired > 0
                ? round(($interviewing + $hired) / ($screening + $interviewing + $hired) * 100, 1)
                : null,
            'offersSent' => $offersSent,
            'activeOnboardingJourneys' => $onboardingActive,
            'activeMobilityRequests' => $activeMobility,
            'activeOffboardingCases' => $activeOffboarding,
            'pendingPerformanceReviews' => $pendingReviews,
            'totalPerformanceReviews' => $totalReviews,
            'pendingReviewShare' => $totalReviews > 0 ? round($pendingReviews / $totalReviews * 100, 1) : null,
        ];
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Every application this tenant has, grouped by its current status.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byApplicationStatus(): array
    {
        return $this->memo['byApplicationStatus'] ??= $this->computeByApplicationStatus();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByApplicationStatus(): array
    {
        $applications = $this->applicationsQuery();
        if ($applications === null) {
            return [];
        }

        $rows = (clone $applications)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $total = (int) $rows->sum('total');
        if ($total === 0) {
            return [];
        }

        return $rows->map(fn ($row) => [
            'key' => (string) ($row->status ?? 'unknown'),
            'label' => $row->status !== null && $row->status !== '' ? (string) $row->status : 'No status recorded',
            'applications' => (int) $row->total,
            'share' => round((int) $row->total / $total * 100, 1),
        ])->all();
    }

    /**
     * Talent activity by department — one row per department carrying at
     * least one open posting, active onboarding journey, active offboarding
     * case or pending performance review.
     *
     * Four different tables carry `department_id` and none of them reliably
     * carries every department, so this is assembled in PHP from four
     * independent counts against {@see self::DEPARTMENT_TABLE} rather than
     * one join across tables of unrelated shape — the same choice
     * `TalentDashboardController::activity()` makes for its feed, for the
     * same reason.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byDepartment(): array
    {
        return $this->memo['byDepartment'] ??= $this->computeByDepartment();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByDepartment(): array
    {
        if (! SchemaCache::hasTable(self::DEPARTMENT_TABLE)) {
            return [];
        }

        $rows = [];

        $postings = $this->postingsQuery();
        if ($postings !== null) {
            $this->accumulateByDepartment($rows, (clone $postings)
                ->whereNotNull('department_id')
                ->select('department_id', DB::raw('COUNT(*) as total'))
                ->groupBy('department_id'), 'openPostings');
        }

        if (SchemaCache::hasTable(self::ONBOARDING_JOURNEYS_TABLE)) {
            $this->accumulateByDepartment($rows, $this->scoped(self::ONBOARDING_JOURNEYS_TABLE)
                ->whereNotNull('department_id')
                ->whereIn('status', self::ONBOARDING_ACTIVE_STATUSES)
                ->select('department_id', DB::raw('COUNT(*) as total'))
                ->groupBy('department_id'), 'activeOnboarding');
        }

        if (SchemaCache::hasTable(self::OFFBOARDING_CASES_TABLE)) {
            $this->accumulateByDepartment($rows, $this->scoped(self::OFFBOARDING_CASES_TABLE)
                ->whereNotNull('department_id')
                ->where('status', '!=', self::OFFBOARDING_CLOSED_STATUS)
                ->select('department_id', DB::raw('COUNT(*) as total'))
                ->groupBy('department_id'), 'activeOffboarding');
        }

        if (SchemaCache::hasTable(self::PERFORMANCE_REVIEWS_TABLE)) {
            $this->accumulateByDepartment($rows, $this->scoped(self::PERFORMANCE_REVIEWS_TABLE)
                ->whereNotNull('department_id')
                ->where('status', 'pending')
                ->select('department_id', DB::raw('COUNT(*) as total'))
                ->groupBy('department_id'), 'pendingReviews');
        }

        if ($rows === []) {
            return [];
        }

        $names = DB::table(self::DEPARTMENT_TABLE)
            ->whereIn('id', array_keys($rows))
            ->pluck('department', 'id');

        $out = [];
        foreach ($rows as $departmentId => $counts) {
            $total = array_sum($counts);
            if ($total < self::MIN_DEPARTMENT_ACTIVITY) {
                continue;
            }

            $out[] = [
                'key' => (string) $departmentId,
                'label' => (string) ($names[$departmentId] ?? "Department #{$departmentId}"),
                'openPostings' => $counts['openPostings'] ?? 0,
                'activeOnboarding' => $counts['activeOnboarding'] ?? 0,
                'activeOffboarding' => $counts['activeOffboarding'] ?? 0,
                'pendingReviews' => $counts['pendingReviews'] ?? 0,
                'totalActivity' => $total,
            ];
        }

        usort($out, static fn ($a, $b) => $b['totalActivity'] <=> $a['totalActivity']);

        return $out;
    }

    /**
     * @param  array<int,array<string,int>>  $rows
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function accumulateByDepartment(array &$rows, $query, string $key): void
    {
        foreach ($query->get() as $row) {
            $departmentId = (int) $row->department_id;
            $rows[$departmentId] ??= [];
            $rows[$departmentId][$key] = (int) $row->total;
        }
    }

    /* ------------------------------------------------------------ the DQ ledger */

    /** @return array<string,mixed> */
    public function dataQuality(): array
    {
        return $this->memo['dataQuality'] ??= $this->computeDataQuality();
    }

    /** @return array<string,mixed> */
    private function computeDataQuality(): array
    {
        $coverage = $this->coverage();

        $absentTables = [];
        foreach (self::REFERENCED_BUT_ABSENT_TABLES as $table => $note) {
            if (! SchemaCache::hasTable($table)) {
                $absentTables[] = "{$table} ({$note})";
            }
        }

        $applications = $this->applicationsQuery();
        $noStatus = $applications !== null
            ? (clone $applications)->where(fn ($q) => $q->whereNull('status')->orWhere('status', ''))->count()
            : 0;
        $totalApplications = $applications !== null ? (clone $applications)->count() : 0;

        $orphanApplications = ($applications !== null && SchemaCache::hasTable(self::JOB_POSTINGS_TABLE))
            ? (clone $applications)
                ->whereNotNull('job_id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from(self::JOB_POSTINGS_TABLE.' as jp')
                        ->whereColumn('jp.id', self::JOB_APPLICATIONS_TABLE.'.job_id')
                        ->where('jp.sub_institute_id', $this->tenantId);
                })
                ->count()
            : 0;

        $postings = $this->postingsQuery();
        $postingsWithoutDepartment = $postings !== null
            ? (clone $postings)->whereNull('department_id')->count()
            : 0;
        $totalPostings = $postings !== null ? (clone $postings)->count() : 0;

        $offboardingWithoutDepartment = SchemaCache::hasTable(self::OFFBOARDING_CASES_TABLE)
            ? $this->scoped(self::OFFBOARDING_CASES_TABLE)->whereNull('department_id')->count()
            : 0;
        $totalOffboarding = (int) $coverage['counts']['offboardingCases'];

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'referenced_tables_absent',
                    'label' => 'Tables the earlier Talent dashboard reads that do not exist here',
                    'value' => count($absentTables),
                    'format' => 'count',
                    'sharePercent' => null,
                    'shareLabel' => null,
                    'state' => $absentTables !== [] ? 'attention' : 'ok',
                    'note' => $absentTables !== []
                        ? 'This screen reads the tables that actually hold this data instead: '.implode('; ', $absentTables)
                        : 'Every table the earlier dashboard references also exists here.',
                ],
                [
                    'key' => 'applications_without_status',
                    'label' => 'Applications with no status recorded',
                    'value' => $noStatus,
                    'format' => 'count',
                    'sharePercent' => $totalApplications > 0 ? round($noStatus / $totalApplications * 100, 2) : null,
                    'shareLabel' => 'of applications',
                    'state' => $noStatus > 0 ? 'attention' : 'ok',
                    'note' => $noStatus > 0
                        ? 'These applications are counted in the total but appear in no funnel stage above.'
                        : 'Every application carries a status.',
                ],
                [
                    'key' => 'orphan_applications',
                    'label' => 'Applications whose posting no longer exists for this tenant',
                    'value' => $orphanApplications,
                    'format' => 'count',
                    'sharePercent' => $totalApplications > 0 ? round($orphanApplications / $totalApplications * 100, 2) : null,
                    'shareLabel' => 'of applications',
                    'state' => $orphanApplications > 0 ? 'attention' : 'ok',
                    'note' => $orphanApplications > 0
                        ? 'The posting these applications point to was deleted or belongs to another tenant; they '
                            .'still count towards the application total above.'
                        : 'Every application with a posting id points to a real posting for this tenant.',
                ],
                [
                    'key' => 'postings_without_department',
                    'label' => 'Job postings with no department',
                    'value' => $postingsWithoutDepartment,
                    'format' => 'count',
                    'sharePercent' => $totalPostings > 0 ? round($postingsWithoutDepartment / $totalPostings * 100, 2) : null,
                    'shareLabel' => 'of postings',
                    'state' => $postingsWithoutDepartment > 0 ? 'attention' : 'ok',
                    'note' => $postingsWithoutDepartment > 0
                        ? 'These postings count towards the institute totals but appear in no department in the '
                            .'breakdown below.'
                        : 'Every posting is assigned to a department.',
                ],
                [
                    'key' => 'offboarding_without_department',
                    'label' => 'Offboarding cases with no department',
                    'value' => $offboardingWithoutDepartment,
                    'format' => 'count',
                    'sharePercent' => $totalOffboarding > 0
                        ? round($offboardingWithoutDepartment / $totalOffboarding * 100, 2)
                        : null,
                    'shareLabel' => 'of offboarding cases',
                    'state' => $offboardingWithoutDepartment > 0 ? 'attention' : 'ok',
                    'note' => $offboardingWithoutDepartment > 0
                        ? 'These cases count towards the active total but appear in no department in the breakdown '
                            .'below.'
                        : 'Every offboarding case is assigned to a department.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    private function count(string $table): int
    {
        return SchemaCache::hasTable($table) ? $this->scoped($table)->count() : 0;
    }

    private function postingsQuery(): ?\Illuminate\Database\Query\Builder
    {
        return SchemaCache::hasTable(self::JOB_POSTINGS_TABLE) ? $this->scoped(self::JOB_POSTINGS_TABLE) : null;
    }

    private function applicationsQuery(): ?\Illuminate\Database\Query\Builder
    {
        return SchemaCache::hasTable(self::JOB_APPLICATIONS_TABLE) ? $this->scoped(self::JOB_APPLICATIONS_TABLE) : null;
    }

    /**
     * Requests still in motion across every real mobility table: an open
     * internal application, a transfer awaiting approval, a promotion
     * awaiting approval.
     */
    private function activeMobilityCount(): int
    {
        $total = 0;

        if (SchemaCache::hasTable(self::MOBILITY_APPLICATIONS_TABLE)) {
            $total += (int) $this->scoped(self::MOBILITY_APPLICATIONS_TABLE)
                ->whereIn('status', ['Applied', 'Screening', 'Interviewing'])
                ->count();
        }

        if (SchemaCache::hasTable(self::MOBILITY_TRANSFERS_TABLE)) {
            $total += (int) $this->scoped(self::MOBILITY_TRANSFERS_TABLE)
                ->where('status', 'Pending')
                ->count();
        }

        if (SchemaCache::hasTable(self::MOBILITY_PROMOTIONS_TABLE)) {
            $total += (int) $this->scoped(self::MOBILITY_PROMOTIONS_TABLE)
                ->where('status', 'Pending')
                ->count();
        }

        return $total;
    }

    /** Every query in this class starts from one of these two tenant-scoped helpers. */
    private function scoped(string $table): \Illuminate\Database\Query\Builder
    {
        $query = DB::table($table)->where('sub_institute_id', $this->tenantId);
        if (SchemaCache::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }
}
