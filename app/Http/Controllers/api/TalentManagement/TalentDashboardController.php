<?php

namespace App\Http\Controllers\api\TalentManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talent Management dashboard - one aggregate for the whole executive overview.
 *
 * Ported 1:1 from hp_erp's `App\Http\Controllers\Api\TalentDashboardController`.
 * A single endpoint rather than extending the existing talent-acquisition
 * endpoints, for three reasons. First, those return shapes are already consumed
 * by the previous frontend's Acquisition Dashboard, and widening them risks
 * breaking a consumer for a metric only this page wants. Second, the funnel
 * card's first stage is "Requisition" - a count of job postings - while
 * /api/talent-acquisition/funnel's first stage is "Applications", so mapping it
 * positionally would print an application count under a requisition label.
 * Third, the page spans recruitment, performance, onboarding, mobility and
 * offboarding - five modules - so one call replaces five round trips.
 *
 * Every number here is computed from real tables. Where a figure the design
 * asks for genuinely cannot be derived, the field is null rather than
 * estimated, so the UI renders "no data" instead of a confident wrong number.
 *
 * Filters (department, location, date range) apply consistently to every
 * section, so the page never mixes a filtered card with an unfiltered one.
 *
 * Auth: mirrors `OnboardingApiController` exactly - the `api.session`
 * middleware performs real JWT validation and hydrates the legacy session
 * keys (`sub_institute_id`, `syear`) from the validated token payload, so the
 * tenant scope is read from `session()`, never from request input.
 *
 * Table availability: none of the Talent Management tables this dashboard
 * aggregates (talent_job_postings, talent_job_applications, talent_offers,
 * talent_onboarding_journeys, talent_onboarding_tasks, talent_mobility_requests,
 * talent_offboarding_cases, talent_offboarding_clearances,
 * talent_interview_schedules, talent_evaluation_form, s_performance_reviews,
 * s_performance_cycles, s_performance_activity_log) exist in this repo yet -
 * the Recruitment/Performance/Onboarding/Mobility/Offboarding migrations for
 * this same porting effort land elsewhere. Every query against one of those
 * tables is therefore guarded with `Schema::hasTable()` and falls back to the
 * same "no data" defaults the original controller returns for an empty tenant
 * (0 counts, null rates, empty lists) rather than hard-failing the endpoint.
 * `hrms_departments` and `tbluser` already exist, so those parts run unguarded,
 * exactly as ported.
 */
class TalentDashboardController extends Controller
{
    /*
     * Application statuses grouped into the funnel stages the card shows.
     *
     * These are the exact values talent_jobapplicationcontroller validates
     * against (Pending Review, Under Review, Shortlisted, Interview Scheduled,
     * Rejected, Hired). 'Completed' is deliberately absent: it is an
     * interview-schedule status, never an application one, so including it
     * would match nothing.
     */
    private const SCREENING_STATUSES = ['Pending Review', 'Under Review', 'Shortlisted'];
    private const INTERVIEW_STATUSES = ['Interview Scheduled'];

    /**
     * Inlined from hp_erp's `App\Models\talent\MobilityRequest::ACTIVE_STATUSES`
     * and `App\Models\talent\OffboardingCase::ACTIVE_STATUSES` - those models
     * (and their migrations) do not exist in this repo yet, so the values are
     * copied here verbatim rather than referencing classes that don't exist.
     * Once the Mobility/Offboarding migrations + models land, these two
     * constants can be replaced with the model constants again.
     */
    private const MOBILITY_ACTIVE_STATUSES = ['pending', 'in-review'];
    private const OFFBOARDING_ACTIVE_STATUSES = ['initiated', 'notice-period', 'clearance', 'exit-interview', 'awaiting-fnf'];

    /** Tenant + academic year, taken from the JWT-hydrated session only. */
    private function context(): array
    {
        return [
            (int) session()->get('sub_institute_id'),
            (int) session()->get('syear'),
        ];
    }

    /** Treat 'all', '0' and empty string as "no filter". */
    private function activeTalentFilter($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = array_values(array_filter(
                $value,
                fn ($item) => $item !== null && $item !== '' && $item !== '0' && $item !== 'all'
            ));

            return empty($value) ? null : implode(',', $value);
        }

        $value = trim((string) $value);

        return ($value === '' || $value === '0' || strtolower($value) === 'all') ? null : $value;
    }

    /**
     * The active filter window.
     *
     * Defaults to the current month, matching the design's date control.
     * Returned to the client so the button can show the real range rather than
     * a hardcoded label.
     */
    private function window(Request $request): array
    {
        $from = $this->activeTalentFilter($request->input('from')) ?: now()->startOfMonth()->toDateString();
        $to = $this->activeTalentFilter($request->input('to')) ?: now()->endOfMonth()->toDateString();

        return [$from, $to];
    }

    /* --- KPI cards ---------------------------------------------------------- */

    private function kpis(Request $request, int $sid, string $from, string $to): array
    {
        $departmentId = $this->activeTalentFilter($request->input('department_id'));
        $location = $this->activeTalentFilter($request->input('location'));

        $hasPostings = Schema::hasTable('talent_job_postings');
        $hasApplications = Schema::hasTable('talent_job_applications');
        $hasJourneys = Schema::hasTable('talent_onboarding_journeys');
        $hasReviews = Schema::hasTable('s_performance_reviews');
        $hasMobility = Schema::hasTable('talent_mobility_requests');
        $hasOffboarding = Schema::hasTable('talent_offboarding_cases');

        $postings = fn () => DB::table('talent_job_postings')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($location, fn ($q) => $q->where('location', $location));

        /*
         * "Open" means live and still accepting applications, which is how
         * TalentAcquisitionService::getKpiMetrics already defines it - kept
         * identical so the dashboard and the recruitment screen never disagree.
         * status is stored lowercase ('active'); the comparison is
         * case-insensitive under this schema's collation either way.
         */
        $openPositions = $hasPostings
            ? $postings()
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('deadline')->orWhereDate('deadline', '>=', now()->toDateString());
                })
                ->count()
            : 0;

        // The design labels this "Critical". talent_job_postings.priority_level
        // holds High/Medium/Low - written by the job posting form's "urgency"
        // field - so High is the criticality signal.
        $criticalPositions = $hasPostings
            ? $postings()->where('status', 'active')->where('priority_level', 'High')->count()
            : 0;

        $applications = fn () => DB::table('talent_job_applications as a')
            ->leftJoin('talent_job_postings as jp', 'jp.id', '=', 'a.job_id')
            ->where('a.sub_institute_id', $sid)
            ->when($departmentId, fn ($q) => $q->where('jp.department_id', $departmentId))
            ->when($location, fn ($q) => $q->where('jp.location', $location));

        $candidates = $hasApplications ? $applications()->count() : 0;

        /*
         * Trend against the preceding window of equal length, so the "vs last
         * 30 days" line is honest whatever range the user picked. Null when
         * there is no prior activity to compare against - a percentage against
         * zero is meaningless, and the UI hides the line rather than printing
         * a fabricated one.
         */
        $days = max(1, now()->parse($from)->diffInDays(now()->parse($to)) + 1);
        $priorFrom = now()->parse($from)->subDays($days)->toDateString();

        $currentWindow = $hasApplications
            ? $applications()->whereBetween('a.created_at', [$from, $to . ' 23:59:59'])->count()
            : 0;
        $priorWindow = $hasApplications
            ? $applications()->whereBetween('a.created_at', [$priorFrom, $from])->count()
            : 0;
        $candidateTrend = $priorWindow > 0
            ? round((($currentWindow - $priorWindow) / $priorWindow) * 100, 1)
            : null;

        $journeys = fn () => DB::table('talent_onboarding_journeys')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($location, fn ($q) => $q->where('location', $location));

        /*
         * Reviews are not department-filtered here even though the table has a
         * department_id: the Performance module scopes by cycle, and applying
         * the recruitment filter to it would make this card disagree with
         * /api/performance/overview for the same tenant.
         */
        $reviews = fn () => DB::table('s_performance_reviews')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        $mobility = fn () => DB::table('talent_mobility_requests')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        $offboarding = fn () => DB::table('talent_offboarding_cases')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId));

        return [
            'open_positions'        => $openPositions,
            'critical_positions'    => $criticalPositions,

            'candidates'            => $candidates,
            'candidate_trend_pct'   => $candidateTrend,

            // Active onboarding = anyone not finished yet.
            'onboarding'            => $hasJourneys ? $journeys()->where('status', '!=', 'completed')->count() : 0,
            'preboarding'           => $hasJourneys ? $journeys()->where('stage', 'preboarding')->count() : 0,

            'performance'           => $hasReviews ? $reviews()->count() : 0,
            'pending_reviews'       => $hasReviews ? $reviews()->where('status', 'pending')->count() : 0,

            'mobility'              => $hasMobility ? $mobility()->whereIn('status', self::MOBILITY_ACTIVE_STATUSES)->count() : 0,
            'mobility_applications' => $hasMobility ? $mobility()->where('request_type', 'internal-application')->count() : 0,

            'offboarding'           => $hasOffboarding ? $offboarding()->whereIn('status', self::OFFBOARDING_ACTIVE_STATUSES)->count() : 0,
            'clearances_pending'    => $this->pendingClearances($sid),
        ];
    }

    /* --- Hiring pipeline ---------------------------------------------------- */

    /**
     * The five funnel stages as the card labels them.
     *
     * Each stage is computed from the source that actually matches its label:
     * Requisition counts postings, the middle stages count applications by
     * status, Offer counts talent_offers rows, and Hired counts applications
     * that reached the Hired status.
     */
    private function pipeline(Request $request, int $sid): array
    {
        $departmentId = $this->activeTalentFilter($request->input('department_id'));
        $location = $this->activeTalentFilter($request->input('location'));

        $hasPostings = Schema::hasTable('talent_job_postings');
        $hasApplications = Schema::hasTable('talent_job_applications');
        $hasOffers = Schema::hasTable('talent_offers');

        $requisitions = $hasPostings
            ? DB::table('talent_job_postings')
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
                ->when($location, fn ($q) => $q->where('location', $location))
                ->count()
            : 0;

        $applications = fn () => DB::table('talent_job_applications as a')
            ->leftJoin('talent_job_postings as jp', 'jp.id', '=', 'a.job_id')
            ->where('a.sub_institute_id', $sid)
            ->when($departmentId, fn ($q) => $q->where('jp.department_id', $departmentId))
            ->when($location, fn ($q) => $q->where('jp.location', $location));

        $offers = fn () => DB::table('talent_offers as o')
            ->leftJoin('talent_job_postings as jp', 'jp.id', '=', 'o.job_id')
            ->where('o.sub_institute_id', $sid)
            ->when($departmentId, fn ($q) => $q->where('jp.department_id', $departmentId))
            ->when($location, fn ($q) => $q->where('jp.location', $location));

        $hired = $hasApplications ? $applications()->where('a.status', 'Hired')->count() : 0;
        $totalOffers = $hasOffers ? $offers()->count() : 0;

        /*
         * Offer acceptance. talent_offers has no 'accepted' state - the enum is
         * draft/sent/rejected/expired - so acceptance is read from the linked
         * application instead: an offer whose candidate reached 'Hired' was
         * accepted. That is a real signal, unlike "not rejected", which would
         * count drafts and expired offers as wins.
         *
         * The denominator is offers actually extended (sent / rejected /
         * expired); a draft has not been put to anyone yet. Null when nothing
         * has been extended, rather than 0%, which would read as "every offer
         * was refused".
         */
        $extendedOffers = $hasOffers ? $offers()->whereIn('o.status', ['sent', 'rejected', 'expired'])->count() : 0;

        $acceptedOffers = ($hasOffers && $hasApplications)
            ? $offers()
                ->join('talent_job_applications as a', 'a.id', '=', 'o.application_id')
                ->whereIn('o.status', ['sent', 'rejected', 'expired'])
                ->where('a.status', 'Hired')
                ->count()
            : 0;

        $acceptanceRate = $extendedOffers > 0
            ? round(($acceptedOffers / $extendedOffers) * 100, 1)
            : null;

        /*
         * Time to hire: days from application to the hire being recorded.
         * There is no hired_at column, so updated_at - the moment the row
         * reached its final state - is the closest real signal. It is a proxy:
         * a later edit to a hired row will inflate it. Null when nothing has
         * been hired.
         */
        $avgTimeToHire = $hasApplications
            ? $applications()
                ->where('a.status', 'Hired')
                ->whereNotNull('a.created_at')
                ->whereNotNull('a.updated_at')
                ->selectRaw('AVG(DATEDIFF(a.updated_at, a.created_at)) as days')
                ->value('days')
            : null;

        return [
            'stages' => [
                ['key' => 'requisition', 'label' => 'Requisition', 'value' => $requisitions],
                ['key' => 'screening',   'label' => 'Screening',   'value' => $hasApplications ? $applications()->whereIn('a.status', self::SCREENING_STATUSES)->count() : 0],
                ['key' => 'interview',   'label' => 'Interview',   'value' => $hasApplications ? $applications()->whereIn('a.status', self::INTERVIEW_STATUSES)->count() : 0],
                ['key' => 'offer',       'label' => 'Offer',       'value' => $totalOffers],
                ['key' => 'hired',       'label' => 'Hired',       'value' => $hired],
            ],
            'avg_time_to_hire_days'   => $avgTimeToHire !== null ? (int) round((float) $avgTimeToHire) : null,
            'offer_acceptance_rate'   => $acceptanceRate,
            'offer_acceptance_basis'  => $extendedOffers,
        ];
    }

    /* --- Donuts ------------------------------------------------------------- */

    /**
     * Review completion for the newest running cycle, or the newest overall.
     *
     * Buckets map onto the real s_performance_reviews enum - pending /
     * in_progress / completed - which is what PerformanceReviewController
     * validates against.
     */
    private function performanceCycle(int $sid): array
    {
        $hasCycles = Schema::hasTable('s_performance_cycles');
        $hasReviews = Schema::hasTable('s_performance_reviews');

        $cycle = $hasCycles
            ? DB::table('s_performance_cycles')
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->orderByRaw("FIELD(status, 'active', 'calibration', 'draft', 'closed')")
                ->orderByDesc('id')
                ->first(['id', 'name', 'period_start', 'period_end', 'status'])
            : null;

        $reviews = fn () => DB::table('s_performance_reviews')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->when($cycle, fn ($q) => $q->where('cycle_id', $cycle->id));

        $completed = $hasReviews ? $reviews()->where('status', 'completed')->count() : 0;
        $inProgress = $hasReviews ? $reviews()->where('status', 'in_progress')->count() : 0;
        $notStarted = $hasReviews ? $reviews()->where('status', 'pending')->count() : 0;
        $total = $completed + $inProgress + $notStarted;

        return [
            'cycle_id'      => $cycle->id ?? null,
            'cycle_name'    => $cycle->name ?? null,
            'cycle_status'  => $cycle->status ?? null,
            'period_start'  => $cycle->period_start ?? null,
            'period_end'    => $cycle->period_end ?? null,
            'completed'     => $completed,
            'in_progress'   => $inProgress,
            'not_started'   => $notStarted,
            'total'         => $total,
            'completed_pct' => $total > 0 ? (int) round($completed / $total * 100) : 0,
        ];
    }

    private function onboardingProgress(int $sid, ?string $departmentId): array
    {
        $hasJourneys = Schema::hasTable('talent_onboarding_journeys');

        $journeys = fn () => DB::table('talent_onboarding_journeys')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId));

        $completed = $hasJourneys ? $journeys()->where('status', 'completed')->count() : 0;
        $inProgress = $hasJourneys ? $journeys()->where('status', 'in-progress')->count() : 0;
        $notStarted = $hasJourneys ? $journeys()->where('status', 'not-started')->count() : 0;
        $total = $completed + $inProgress + $notStarted;

        // Days from joining to completion, across finished journeys only.
        $avgDays = $hasJourneys
            ? $journeys()
                ->where('status', 'completed')
                ->whereNotNull('joining_date')
                ->whereNotNull('completed_at')
                ->selectRaw('AVG(DATEDIFF(completed_at, joining_date)) as days')
                ->value('days')
            : null;

        return [
            'completed'           => $completed,
            'in_progress'         => $inProgress,
            'not_started'         => $notStarted,
            'total'               => $total,
            'completed_pct'       => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'avg_completion_days' => $avgDays !== null ? (int) round((float) $avgDays) : null,
        ];
    }

    /* --- Action items & activity -------------------------------------------- */

    /**
     * The six rows of "My Action Items", each a real outstanding count plus the
     * module the UI should navigate to.
     *
     * The design's first row is "Approve Job Requisitions". There is no
     * approval workflow on talent_job_postings - status is only
     * active/inactive, and talent_jobpostingcontroller@index flips expired
     * postings to 'inactive' automatically - so counting inactive rows would
     * report expired jobs as awaiting approval. The row is therefore "Job
     * Openings Closing This Week", which is a real, actionable deadline count
     * from the same table.
     */
    private function actionItems(int $sid): array
    {
        $today = now()->toDateString();
        $weekOut = now()->addDays(7)->toDateString();

        $closingSoon = Schema::hasTable('talent_job_postings')
            ? DB::table('talent_job_postings')
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->whereNotNull('deadline')
                ->whereBetween(DB::raw('DATE(deadline)'), [$today, $weekOut])
                ->count()
            : 0;

        /*
         * Interview feedback pending: an interview whose date has passed with
         * no evaluation form recorded against it. talent_evaluation_form is the
         * feedback table - job_id + candidate_id + recommendation - so its
         * absence is the pending signal, not the schedule's own status.
         */
        $interviewFeedback = (Schema::hasTable('talent_interview_schedules') && Schema::hasTable('talent_evaluation_form'))
            ? DB::table('talent_interview_schedules as i')
                ->where('i.sub_institute_id', $sid)
                ->whereDate('i.interview_date', '<=', $today)
                ->whereNotIn('i.status', ['Rejected'])
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('talent_evaluation_form as f')
                        ->whereColumn('f.candidate_id', 'i.applicant_id')
                        ->whereColumn('f.job_id', 'i.job_id');
                })
                ->count()
            : 0;

        // 'sent' is the only pre-terminal offer state: extended, not yet answered.
        $offerApprovals = Schema::hasTable('talent_offers')
            ? DB::table('talent_offers')
                ->where('sub_institute_id', $sid)
                ->where('status', 'sent')
                ->count()
            : 0;

        $reviewsPending = Schema::hasTable('s_performance_reviews')
            ? DB::table('s_performance_reviews')
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->where('status', 'pending')
                ->count()
            : 0;

        $onboardingTasks = (Schema::hasTable('talent_onboarding_tasks') && Schema::hasTable('talent_onboarding_journeys'))
            ? DB::table('talent_onboarding_tasks as t')
                ->join('talent_onboarding_journeys as j', 'j.id', '=', 't.journey_id')
                ->where('t.sub_institute_id', $sid)
                ->whereNull('t.deleted_at')
                ->whereNull('j.deleted_at')
                ->whereIn('t.status', ['pending', 'in-progress'])
                ->count()
            : 0;

        return [
            ['key' => 'requisitions', 'label' => 'Job Openings Closing This Week', 'count' => $closingSoon,       'tone' => 'danger',  'menu' => 'recruitment'],
            ['key' => 'interviews',   'label' => 'Interview Feedback Pending',     'count' => $interviewFeedback, 'tone' => 'warning', 'menu' => 'recruitment'],
            ['key' => 'offers',       'label' => 'Offer Approvals',                'count' => $offerApprovals,    'tone' => 'danger',  'menu' => 'recruitment'],
            ['key' => 'reviews',      'label' => 'Performance Reviews Pending',    'count' => $reviewsPending,    'tone' => 'warning', 'menu' => 'performance'],
            ['key' => 'onboarding',   'label' => 'Onboarding Tasks Pending',       'count' => $onboardingTasks,   'tone' => 'primary', 'menu' => 'onboarding'],
            ['key' => 'clearances',   'label' => 'Clearances Pending',             'count' => $this->pendingClearances($sid), 'tone' => 'danger', 'menu' => 'offboarding'],
        ];
    }

    /** Open clearance sign-offs, excluding any whose case was deleted. */
    private function pendingClearances(int $sid): int
    {
        if (!Schema::hasTable('talent_offboarding_clearances') || !Schema::hasTable('talent_offboarding_cases')) {
            return 0;
        }

        return DB::table('talent_offboarding_clearances as cl')
            ->join('talent_offboarding_cases as c', 'c.id', '=', 'cl.case_id')
            ->where('cl.sub_institute_id', $sid)
            ->whereNull('cl.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereIn('cl.status', ['pending', 'in-progress'])
            ->count();
    }

    /**
     * Cross-module activity feed.
     *
     * Assembled by merging the newest rows from each module in PHP rather than
     * SQL: the source tables have unrelated shapes and a UNION would force
     * every column into one layout. The volume is small so the cost is a
     * handful of indexed reads.
     */
    private function activity(int $sid, int $limit = 10): array
    {
        $feed = [];

        if (Schema::hasTable('talent_job_applications')) {
            $applications = DB::table('talent_job_applications as a')
                ->leftJoin('talent_job_postings as jp', 'jp.id', '=', 'a.job_id')
                ->where('a.sub_institute_id', $sid)
                ->orderByDesc('a.updated_at')
                ->limit($limit)
                ->get([
                    'a.id',
                    'a.status',
                    'a.updated_at',
                    'jp.title',
                    // talent_job_applications has no `name` column - the candidate
                    // name is stored across three parts.
                    DB::raw("TRIM(CONCAT_WS(' ', a.first_name, a.middle_name, a.last_name)) as candidate_name"),
                ]);

            foreach ($applications as $row) {
                $feed[] = [
                    'id'      => 'app-' . $row->id,
                    'type'    => 'application',
                    'text'    => trim(($row->candidate_name ?: 'A candidate') . ' moved to ' . ($row->status ?: 'a new stage')),
                    'context' => $row->title,
                    'tone'    => $row->status === 'Hired' ? 'success' : 'neutral',
                    'at'      => $row->updated_at,
                ];
            }
        }

        if (Schema::hasTable('talent_offers')) {
            $offers = DB::table('talent_offers')
                ->where('sub_institute_id', $sid)
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get(['id', 'position', 'status', 'updated_at']);

            foreach ($offers as $row) {
                $feed[] = [
                    'id'      => 'offer-' . $row->id,
                    'type'    => 'offer',
                    'text'    => 'Offer ' . ($row->status ?: 'updated'),
                    'context' => $row->position,
                    'tone'    => $row->status === 'rejected' ? 'danger' : 'primary',
                    'at'      => $row->updated_at,
                ];
            }
        }

        if (Schema::hasTable('s_performance_activity_log')) {
            $logs = DB::table('s_performance_activity_log')
                ->where('sub_institute_id', $sid)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(['id', 'description', 'action', 'actor_name', 'created_at']);

            foreach ($logs as $row) {
                $feed[] = [
                    'id'      => 'perf-' . $row->id,
                    'type'    => 'performance',
                    'text'    => trim(($row->actor_name ? $row->actor_name . ' ' : '') . ($row->description ?: $row->action)),
                    'context' => 'Performance',
                    'tone'    => 'neutral',
                    'at'      => $row->created_at,
                ];
            }
        }

        if (Schema::hasTable('talent_onboarding_journeys')) {
            $journeys = DB::table('talent_onboarding_journeys as j')
                ->leftJoin('tbluser as u', 'u.id', '=', 'j.employee_id')
                ->where('j.sub_institute_id', $sid)
                ->whereNull('j.deleted_at')
                ->orderByDesc('j.updated_at')
                ->limit($limit)
                ->get([
                    'j.id', 'j.stage', 'j.status', 'j.position', 'j.updated_at',
                    DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as employee_name"),
                ]);

            foreach ($journeys as $row) {
                $feed[] = [
                    'id'      => 'onb-' . $row->id,
                    'type'    => 'onboarding',
                    'text'    => ($row->employee_name ?: 'A new hire') . ' is at ' . str_replace('-', ' ', (string) $row->stage),
                    'context' => $row->position ?: 'Onboarding',
                    'tone'    => $row->status === 'completed' ? 'success' : 'neutral',
                    'at'      => $row->updated_at,
                ];
            }
        }

        if (Schema::hasTable('talent_mobility_requests')) {
            $mobility = DB::table('talent_mobility_requests as m')
                ->leftJoin('tbluser as u', 'u.id', '=', 'm.employee_id')
                ->where('m.sub_institute_id', $sid)
                ->whereNull('m.deleted_at')
                ->orderByDesc('m.updated_at')
                ->limit($limit)
                ->get([
                    'm.id', 'm.request_type', 'm.status', 'm.updated_at',
                    DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as employee_name"),
                ]);

            foreach ($mobility as $row) {
                $feed[] = [
                    'id'      => 'mob-' . $row->id,
                    'type'    => 'mobility',
                    'text'    => ($row->employee_name ?: 'An employee') . ' - ' . str_replace('-', ' ', (string) $row->request_type) . ' ' . $row->status,
                    'context' => 'Mobility',
                    'tone'    => $row->status === 'rejected' ? 'danger' : ($row->status === 'approved' ? 'success' : 'neutral'),
                    'at'      => $row->updated_at,
                ];
            }
        }

        if (Schema::hasTable('talent_offboarding_cases')) {
            $exits = DB::table('talent_offboarding_cases as c')
                ->leftJoin('tbluser as u', 'u.id', '=', 'c.employee_id')
                ->where('c.sub_institute_id', $sid)
                ->whereNull('c.deleted_at')
                ->orderByDesc('c.created_at')
                ->limit($limit)
                ->get([
                    'c.id', 'c.exit_type', 'c.created_at',
                    DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as employee_name"),
                ]);

            foreach ($exits as $row) {
                $feed[] = [
                    'id'      => 'exit-' . $row->id,
                    'type'    => 'offboarding',
                    'text'    => ($row->employee_name ?: 'An employee') . ' submitted ' . str_replace('-', ' ', (string) $row->exit_type),
                    'context' => 'Offboarding',
                    'tone'    => 'danger',
                    'at'      => $row->created_at,
                ];
            }
        }

        usort($feed, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($feed, 0, $limit);
    }

    /* --- Endpoints ---------------------------------------------------------- */

    /**
     * GET api/talent/dashboard
     *
     * Query (beyond the JWT-hydrated session): department_id?, location?, from?, to?
     */
    public function index(Request $request): JsonResponse
    {
        [$sid] = $this->context();

        [$from, $to] = $this->window($request);

        try {
            return $this->success('Success', [
                'kpis'                => $this->kpis($request, $sid, $from, $to),
                'pipeline'            => $this->pipeline($request, $sid),
                'performance_cycle'   => $this->performanceCycle($sid),
                'onboarding_progress' => $this->onboardingProgress($sid, $this->activeTalentFilter($request->input('department_id'))),
                'action_items'        => $this->actionItems($sid),
                'activity'            => $this->activity($sid),
                'meta'                => ['from' => $from, 'to' => $to],
            ]);
        } catch (\Exception $e) {
            return $this->failure('Failed to load the talent dashboard', 500, ['error' => $e->getMessage()]);
        }
    }

    /**
     * GET api/talent/dashboard/filters
     *
     * Real values for the Business Unit / Department / Location selects.
     *
     * Business Unit has no table anywhere in this schema, so it is returned as
     * an empty list with a flag rather than being faked from departments - the
     * UI disables that select and says why.
     */
    public function filters(Request $request): JsonResponse
    {
        [$sid] = $this->context();

        $departments = DB::table('hrms_departments')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->orderBy('department')
            ->get(['id', 'department']);

        // Locations are free text on job postings; distinct values are the only
        // authoritative list, since no location master table exists.
        // talent_job_postings does not exist in this repo yet - see class docblock.
        $locations = Schema::hasTable('talent_job_postings')
            ? DB::table('talent_job_postings')
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->whereNotNull('location')
                ->where('location', '!=', '')
                ->distinct()
                ->orderBy('location')
                ->pluck('location')
            : collect();

        return $this->success('Success', [
            'departments'              => $departments,
            'locations'                => $locations,
            'business_units'           => [],
            'business_units_available' => false,
            'business_units_reason'    => 'No business unit master exists in this schema.',
        ]);
    }

    // ------------------------------------------------------------------
    // Response helpers - same shape as OnboardingApiController's own.
    // ------------------------------------------------------------------

    private function success(string $message, array $data): JsonResponse
    {
        return response()->json(['status' => 1, 'status_code' => 1, 'message' => $message, 'data' => $data]);
    }

    private function failure(string $message, int $code = 422, $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 0,
            'status_code' => 0,
            'message' => $message,
            'errors' => $errors,
            'data' => [],
        ], $code);
    }
}
