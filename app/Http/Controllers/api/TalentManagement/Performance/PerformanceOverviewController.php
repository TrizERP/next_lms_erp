<?php

namespace App\Http\Controllers\api\TalentManagement\Performance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Performance\Concerns\ResolvesPerformanceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\PerformanceOverviewController`.
 *
 * The Performance & Rewards Center's header: the 7 KPI cards, the shared filter
 * options, the Team Comparison panel and the Cycle Timeline view.
 *
 * Every figure is computed from the s_performance_* tables joined to the
 * existing user (tbluser) and department (hrms_departments) masters - nothing
 * here is hardcoded. Logic, field names and response shapes are unchanged
 * from the source; only tenant/actor context resolution was adapted to the
 * hydrated session, per `ResolvesPerformanceContext`.
 */
class PerformanceOverviewController extends Controller
{
    use ResolvesPerformanceContext;

    /**
     * GET /api/performance/overview
     *
     * The 7 KPI cards. `cycle_id` scopes cards 2-7 to one cycle; omit it and
     * they cover every non-draft cycle in the tenant.
     */
    public function index(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $cycleId = $this->activePerfFilter($request->input('cycle_id'));
        $today = now()->startOfDay();

        /* 1. Active Review Cycles ---------------------------------------- */
        $activeCycles = DB::table('s_performance_cycles')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'calibration'])
            ->count();

        $closingThisMonth = DB::table('s_performance_cycles')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'calibration'])
            ->whereBetween('period_end', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();

        /* Shared review scope for cards 2, 3, 4, 6 ----------------------- */
        $reviewScope = fn () => DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId));

        /* 2. Employees in Cycle ------------------------------------------ */
        $employeesInCycle = (clone $reviewScope())->distinct()->count('user_id');

        $totalEmployees = DB::table('tbluser')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->count();

        $coveragePct = $totalEmployees > 0 ? (int) round($employeesInCycle / $totalEmployees * 100) : 0;

        /* 3. Self Reviews Pending ---------------------------------------- */
        $selfPending = (clone $reviewScope())->where('stage', 'self_review')->where('status', '!=', 'completed');
        $selfPendingCount = (clone $selfPending)->count();
        $selfDueSoon = (clone $selfPending)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
            ->count();

        /* 4. Manager Reviews Pending ------------------------------------- */
        $managerPending = (clone $reviewScope())->where('stage', 'manager_review')->where('status', '!=', 'completed');
        $managerPendingCount = (clone $managerPending)->count();
        $managerOverdue = (clone $managerPending)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today->toDateString())
            ->count();

        /* 5. Calibration Pending ----------------------------------------- */
        $calibrationScope = DB::table('s_performance_calibration_sessions')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId));

        $calibrationPending = (clone $calibrationScope)->count();
        $calibrationDueSoon = (clone $calibrationScope)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$today, $today->copy()->addDays(5)->endOfDay()])
            ->count();

        /* 6. Final Ratings Completed ------------------------------------- */
        $totalReviews = (clone $reviewScope())->count();
        $finalCompleted = (clone $reviewScope())->where('stage', 'completed')->count();
        $completedPct = $totalReviews > 0 ? (int) round($finalCompleted / $totalReviews * 100) : 0;

        /* 7. Reward Decisions Pending ------------------------------------ */
        $pendingStatuses = ['draft', 'pending_approval'];

        $compPending = DB::table('s_performance_compensation_revisions')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereIn('status', $pendingStatuses)
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->count();

        $bonusPending = DB::table('s_performance_bonus_awards')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereIn('status', $pendingStatuses)
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->count();

        return $this->performanceResponse([
            'kpis' => [
                [
                    'id'       => 'active_cycles',
                    'title'    => 'Active Review Cycles',
                    'value'    => (string) $activeCycles,
                    'subtitle' => $closingThisMonth . ' closing this month',
                    'icon'     => 'calendar',
                ],
                [
                    'id'       => 'employees_in_cycle',
                    'title'    => 'Employees in Cycle',
                    'value'    => (string) $employeesInCycle,
                    'subtitle' => $coveragePct . '% of total employees',
                    'icon'     => 'users',
                ],
                [
                    'id'       => 'self_reviews_pending',
                    'title'    => 'Self Reviews Pending',
                    'value'    => (string) $selfPendingCount,
                    'subtitle' => 'Due in next 7 days: ' . $selfDueSoon,
                    'icon'     => 'user-clock',
                ],
                [
                    'id'       => 'manager_reviews_pending',
                    'title'    => 'Manager Reviews Pending',
                    'value'    => (string) $managerPendingCount,
                    'subtitle' => 'Overdue: ' . $managerOverdue,
                    'icon'     => 'user-check',
                ],
                [
                    'id'       => 'calibration_pending',
                    'title'    => 'Calibration Pending',
                    'value'    => (string) $calibrationPending,
                    'subtitle' => $calibrationDueSoon . ' due in next 5 days',
                    'icon'     => 'scale',
                ],
                [
                    'id'       => 'final_ratings_completed',
                    'title'    => 'Final Ratings Completed',
                    'value'    => (string) $finalCompleted,
                    'subtitle' => $completedPct . '% completed',
                    'icon'     => 'check-circle',
                    'progress' => $completedPct,
                ],
                [
                    'id'       => 'reward_decisions_pending',
                    'title'    => 'Reward Decisions Pending',
                    'value'    => (string) ($compPending + $bonusPending),
                    'subtitle' => 'Not yet finalized',
                    'icon'     => 'gift',
                ],
            ],
            'totals' => [
                'total_employees'  => $totalEmployees,
                'total_reviews'    => $totalReviews,
                'comp_pending'     => $compPending,
                'bonus_pending'    => $bonusPending,
            ],
        ]);
    }

    /**
     * GET /api/performance/filters
     *
     * Options for the cycle selector and the Department / Manager / Status
     * filters, plus the employee and jobrole pickers used by the create dialogs.
     *
     * Departments and managers are drawn from the reviews that actually exist so
     * the dropdowns stay short; when a tenant has no reviews yet, departments
     * fall back to the department master.
     */
    public function filters(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $cycles = DB::table('s_performance_cycles')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->get(['id', 'name', 'code', 'cycle_type', 'status', 'period_start', 'period_end']);

        $cycleOptions = $cycles->map(fn ($cycle) => [
            'value'        => (string) $cycle->id,
            'label'        => $cycle->name,
            'status'       => $cycle->status,
            'cycle_type'   => $cycle->cycle_type,
            'period_label' => $this->periodLabel($cycle->period_start, $cycle->period_end),
        ])->all();

        // Departments that appear on reviews, resolved against the department master.
        $reviewDepartmentIds = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereNotNull('department_id')
            ->distinct()
            ->pluck('department_id')
            ->all();

        $departmentQuery = DB::table('hrms_departments')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at');

        if (!empty($reviewDepartmentIds)) {
            $departmentQuery->whereIn('id', $reviewDepartmentIds);
        } else {
            $departmentQuery->limit(200);
        }

        $departments = $departmentQuery->orderBy('department')->get(['id', 'department'])
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->department])
            ->all();

        // Managers named on reviews.
        $managerIds = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereNotNull('manager_id')
            ->distinct()
            ->pluck('manager_id')
            ->all();

        $managerDirectory = $this->employeeDirectory($tenant, $managerIds);
        $managers = collect($managerDirectory)
            ->sortBy('name')
            ->map(fn ($manager) => ['value' => (string) $manager['id'], 'label' => $manager['name']])
            ->values()
            ->all();

        // Employee picker for Create/Launch and the goal/appraisal dialogs.
        $employees = DB::table('tbluser')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->limit(500)
            ->get(['id', 'first_name', 'last_name', 'user_name', 'employee_no', 'department_id'])
            ->map(function ($user) {
                $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                $name = $name !== '' ? $name : ($user->user_name ?? 'Unknown');

                return [
                    'value'         => (string) $user->id,
                    'label'         => $name,
                    'employee_no'   => $user->employee_no,
                    'department_id' => $user->department_id ? (string) $user->department_id : null,
                ];
            })
            ->all();

        $jobroles = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereNotNull('jobrole')
            ->where('jobrole', '!=', '')
            ->distinct()
            ->orderBy('jobrole')
            ->pluck('jobrole')
            ->map(fn ($role) => ['value' => $role, 'label' => $role])
            ->all();

        return $this->performanceResponse([
            'cycles'      => $cycleOptions,
            'departments' => $departments,
            'managers'    => $managers,
            'employees'   => $employees,
            'jobroles'    => $jobroles,
            'stages'      => [
                ['value' => 'self_review', 'label' => 'Self Review'],
                ['value' => 'manager_review', 'label' => 'Manager Review'],
                ['value' => 'calibration', 'label' => 'Calibration'],
                ['value' => 'final_review', 'label' => 'Final Review'],
                ['value' => 'completed', 'label' => 'Completed'],
            ],
            'statuses' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'in_progress', 'label' => 'In Progress'],
                ['value' => 'completed', 'label' => 'Completed'],
            ],
            'decision_statuses' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'pending_approval', 'label' => 'Pending Approval'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'goal_categories' => [
                ['value' => 'kra', 'label' => 'KRA'],
                ['value' => 'kpi', 'label' => 'KPI'],
                ['value' => 'okr', 'label' => 'OKR'],
                ['value' => 'competency', 'label' => 'Competency'],
                ['value' => 'project', 'label' => 'Project'],
            ],
            'recommendations' => [
                ['value' => 'promote', 'label' => 'Promote'],
                ['value' => 'retain', 'label' => 'Retain'],
                ['value' => 'lateral_move', 'label' => 'Lateral Move'],
                ['value' => 'pip', 'label' => 'Performance Improvement Plan'],
                ['value' => 'exit', 'label' => 'Exit'],
            ],
            'revision_types' => [
                ['value' => 'merit', 'label' => 'Merit Increase'],
                ['value' => 'promotion', 'label' => 'Promotion'],
                ['value' => 'market_correction', 'label' => 'Market Correction'],
                ['value' => 'retention', 'label' => 'Retention'],
                ['value' => 'probation_confirmation', 'label' => 'Probation Confirmation'],
            ],
            'bonus_types' => [
                ['value' => 'performance', 'label' => 'Performance Bonus'],
                ['value' => 'retention', 'label' => 'Retention Bonus'],
                ['value' => 'spot', 'label' => 'Spot Award'],
                ['value' => 'festival', 'label' => 'Festival Bonus'],
                ['value' => 'referral', 'label' => 'Referral Bonus'],
                ['value' => 'project', 'label' => 'Project Bonus'],
            ],
            'cycle_types' => [
                ['value' => 'annual', 'label' => 'Annual'],
                ['value' => 'half_yearly', 'label' => 'Half-Yearly'],
                ['value' => 'quarterly', 'label' => 'Quarterly'],
                ['value' => 'probation', 'label' => 'Probation'],
                ['value' => 'project', 'label' => 'Project'],
            ],
        ]);
    }

    /**
     * GET /api/performance/team-comparison
     *
     * The sidebar's "Team Comparison (<Department>)" panel: average rating, the
     * share of top and low performers, and the department's calibration status.
     *
     * Requires department_id; cycle_id optionally narrows it to one cycle.
     */
    public function teamComparison(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $departmentId = $this->activePerfFilter($request->input('department_id'));
        $cycleId = $this->activePerfFilter($request->input('cycle_id'));

        if (!$departmentId) {
            return $this->performanceError('department_id is required', 400);
        }

        $scope = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->where('department_id', $departmentId)
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId));

        $rated = (clone $scope)->whereNotNull('overall_rating');
        $ratedCount = (clone $rated)->count();
        $average = $ratedCount > 0 ? round((float) (clone $rated)->avg('overall_rating'), 2) : null;

        // Top performer = 4.0 and above; low performer = below 2.5. Bands follow
        // the same 5-point scale ratingLabel() renders.
        $topCount = $ratedCount > 0 ? (clone $rated)->where('overall_rating', '>=', 4)->count() : 0;
        $lowCount = $ratedCount > 0 ? (clone $rated)->where('overall_rating', '<', 2.5)->count() : 0;

        $session = DB::table('s_performance_calibration_sessions')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->where('department_id', $departmentId)
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->orderByDesc('scheduled_at')
            ->first(['id', 'name', 'status', 'scheduled_at']);

        $department = DB::table('hrms_departments')->where('id', $departmentId)->value('department');

        // Rating distribution, used by the "View Details" drill-down.
        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $band) {
            $distribution[] = [
                'band'  => $band,
                'label' => $this->ratingLabel((float) $band),
                'count' => $ratedCount > 0
                    ? (clone $rated)->whereBetween('overall_rating', [$band - 0.5, $band + 0.49])->count()
                    : 0,
            ];
        }

        return $this->performanceResponse([
            'department_id'      => (int) $departmentId,
            'department'         => $department,
            'total_reviews'      => (clone $scope)->count(),
            'rated_count'        => $ratedCount,
            'average_rating'     => $average,
            'average_label'      => $average !== null ? $this->ratingBandName($average) : null,
            'top_performer_pct'  => $ratedCount > 0 ? (int) round($topCount / $ratedCount * 100) : 0,
            'low_performer_pct'  => $ratedCount > 0 ? (int) round($lowCount / $ratedCount * 100) : 0,
            'top_performers'     => $topCount,
            'low_performers'     => $lowCount,
            'distribution'       => $distribution,
            'calibration'        => $session ? [
                'id'             => (int) $session->id,
                'name'           => $session->name,
                'status'         => $session->status,
                'status_label'   => $this->statusLabel($session->status),
                'scheduled_at'   => $session->scheduled_at,
                'scheduled_label'=> $this->dateTimeLabel($session->scheduled_at),
            ] : null,
        ]);
    }

    /**
     * GET /api/performance/timeline
     *
     * The "Cycle Timeline" view: each cycle with its 4 stage milestones and how
     * many reviews have cleared each stage.
     */
    public function timeline(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $cycleId = $this->activePerfFilter($request->input('cycle_id'));

        $cycles = DB::table('s_performance_cycles')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->when($cycleId, fn ($q) => $q->where('id', $cycleId))
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->get();

        if ($cycles->isEmpty()) {
            return $this->performanceResponse([]);
        }

        // One grouped count query for every cycle on screen, rather than N per stage.
        $stageCounts = DB::table('s_performance_reviews')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereIn('cycle_id', $cycles->pluck('id')->all())
            ->select('cycle_id', 'stage', DB::raw('COUNT(*) as total'))
            ->groupBy('cycle_id', 'stage')
            ->get()
            ->groupBy('cycle_id');

        $today = now()->startOfDay();

        $data = $cycles->map(function ($cycle) use ($stageCounts, $today) {
            $counts = collect($stageCounts[$cycle->id] ?? [])->pluck('total', 'stage');
            $total = (int) $counts->sum();

            $milestones = [
                ['key' => 'self_review',    'label' => 'Self Review',    'due' => $cycle->self_review_due],
                ['key' => 'manager_review', 'label' => 'Manager Review', 'due' => $cycle->manager_review_due],
                ['key' => 'calibration',    'label' => 'Calibration',    'due' => $cycle->calibration_due],
                ['key' => 'final_review',   'label' => 'Final Review',   'due' => $cycle->final_review_due],
                ['key' => 'completed',      'label' => 'Completed',      'due' => $cycle->period_end],
            ];

            $stageIndex = array_search($cycle->status === 'calibration' ? 'calibration' : null, array_column($milestones, 'key'), true);

            return [
                'id'           => (int) $cycle->id,
                'name'         => $cycle->name,
                'code'         => $cycle->code,
                'cycle_type'   => $cycle->cycle_type,
                'status'       => $cycle->status,
                'status_label' => $this->statusLabel($cycle->status),
                'period_start' => $cycle->period_start,
                'period_end'   => $cycle->period_end,
                'period_label' => $this->periodLabel($cycle->period_start, $cycle->period_end),
                'launched_at'  => $cycle->launched_at,
                'total_reviews'=> $total,
                'milestones'   => array_map(function ($milestone) use ($counts, $total, $today, $stageIndex) {
                    $cleared = (int) ($counts[$milestone['key']] ?? 0);
                    $overdue = $milestone['due'] && $today->gt(\Carbon\Carbon::parse($milestone['due']));

                    return [
                        'key'        => $milestone['key'],
                        'label'      => $milestone['label'],
                        'due_date'   => $milestone['due'],
                        'due_label'  => $this->dateLabel($milestone['due']),
                        'at_stage'   => $cleared,
                        'pct'        => $total > 0 ? (int) round($cleared / $total * 100) : 0,
                        'is_overdue' => (bool) $overdue,
                    ];
                }, $milestones),
            ];
        })->all();

        return $this->performanceResponse($data);
    }

    /** "Apr'24-Mar'25" - the parenthetical on the cycle selector. */
    private function periodLabel($start, $end): ?string
    {
        if (!$start || !$end) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($start)->format("M'y") . '-' . \Carbon\Carbon::parse($end)->format("M'y");
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** "Meets" - the bare band name shown next to the team average. */
    private function ratingBandName(float $rating): string
    {
        $label = $this->ratingLabel($rating) ?? '';
        $parts = explode(' - ', $label, 2);

        return $parts[1] ?? '';
    }
}
