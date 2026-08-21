<?php

namespace App\Http\Controllers\api\TalentManagement\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Onboarding\Concerns\ResolvesOnboardingContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Onboarding\OnboardingOverviewController`.
 *
 * The header of Talent Management -> Onboarding & Employee Lifecycle Center:
 * the five KPI cards and every dropdown the screen's filter bar offers.
 */
class OnboardingOverviewController extends Controller
{
    use ResolvesOnboardingContext;

    /** Journeys that are still running - everything the KPIs count as "live". */
    private const OPEN_STATUSES = ['not-started', 'in-progress', 'on-hold'];

    /**
     * GET /api/onboarding/overview
     *
     * Query: department_id, stage, status, search (all optional)
     */
    public function index(Request $request)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $departmentId = $this->activeOnbFilter($request->input('department_id'));

        $scope = fn () => DB::table('talent_onboarding_journeys')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId));

        // 1. Preboarding Pending - accepted but not yet started on day one.
        $preboardingPending = (clone $scope())
            ->where('stage', 'preboarding')
            ->whereIn('status', self::OPEN_STATUSES)
            ->count();

        // 2. Active Onboarding - joined, journey still open.
        $activeOnboarding = (clone $scope())
            ->whereIn('stage', ['first_day', 'orientation', 'team_integration', 'probation'])
            ->whereIn('status', self::OPEN_STATUSES)
            ->count();

        // 3. First Day This Week - joining_date inside the current Mon-Sun week.
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        $firstDayThisWeek = (clone $scope())
            ->whereBetween('joining_date', [$weekStart, $weekEnd])
            ->count();

        // 4. Onboarding Completion - completed tasks over all tasks on open journeys.
        $openJourneyIds = (clone $scope())
            ->whereIn('status', self::OPEN_STATUSES)
            ->pluck('id')
            ->all();

        $taskTotals = empty($openJourneyIds)
            ? null
            : DB::table('talent_onboarding_tasks')
                ->where('sub_institute_id', $tenant)
                ->whereNull('deleted_at')
                ->whereIn('journey_id', $openJourneyIds)
                ->selectRaw('COUNT(*) as total, SUM(status = "completed") as completed')
                ->first();

        $taskTotal = (int) ($taskTotals->total ?? 0);
        $taskCompleted = (int) ($taskTotals->completed ?? 0);
        $completionPct = $taskTotal > 0 ? (int) round($taskCompleted / $taskTotal * 100) : 0;

        // 5. Probation Due - probation ending inside the next 30 days, undecided.
        $today = now()->toDateString();
        $in30Days = now()->addDays(30)->toDateString();

        $probationDue = (clone $scope())
            ->where('confirmation_status', 'pending')
            ->whereNotNull('probation_end')
            ->whereBetween('probation_end', [$today, $in30Days])
            ->count();

        $overdueTasks = empty($openJourneyIds)
            ? 0
            : DB::table('talent_onboarding_tasks')
                ->where('sub_institute_id', $tenant)
                ->whereNull('deleted_at')
                ->whereIn('journey_id', $openJourneyIds)
                ->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->count();

        $kpis = [
            [
                'id'       => 'preboarding-pending',
                'title'    => 'Preboarding Pending',
                'value'    => (string) $preboardingPending,
                'subtitle' => $preboardingPending === 1 ? '1 hire in preboarding' : $preboardingPending . ' hires in preboarding',
                'icon'     => 'users',
                'filter'   => (object) ['stage' => 'preboarding'],
            ],
            [
                'id'       => 'active-onboarding',
                'title'    => 'Active Onboarding',
                'value'    => (string) $activeOnboarding,
                'subtitle' => 'Joined and in progress',
                'icon'     => 'user-check',
                'filter'   => (object) ['status' => 'in-progress'],
            ],
            [
                'id'       => 'first-day-this-week',
                'title'    => 'First Day This Week',
                'value'    => (string) $firstDayThisWeek,
                'subtitle' => $this->onbDateLabel($weekStart) . ' - ' . $this->onbDateLabel($weekEnd),
                'icon'     => 'calendar',
                'filter'   => (object) ['joining_from' => $weekStart, 'joining_to' => $weekEnd],
            ],
            [
                'id'       => 'onboarding-completion',
                'title'    => 'Onboarding Completion',
                'value'    => $completionPct . '%',
                'subtitle' => $taskCompleted . ' of ' . $taskTotal . ' tasks complete',
                'icon'     => 'check-circle',
                'progress' => $completionPct,
                'filter'   => (object) [],
            ],
            [
                'id'       => 'probation-due',
                'title'    => 'Probation Due',
                'value'    => (string) $probationDue,
                'subtitle' => 'Ending in the next 30 days',
                'icon'     => 'shield',
                'filter'   => (object) ['probation_due' => '30'],
            ],
        ];

        return $this->onboardingResponse([
            'kpis'   => $kpis,
            'totals' => [
                'total_journeys'     => (clone $scope())->count(),
                'open_journeys'      => count($openJourneyIds),
                'total_tasks'        => $taskTotal,
                'completed_tasks'    => $taskCompleted,
                'overdue_tasks'      => $overdueTasks,
                'completion_pct'     => $completionPct,
                'probation_due'      => $probationDue,
                'first_day_thisweek' => $firstDayThisWeek,
            ],
        ]);
    }

    /**
     * GET /api/onboarding/filters
     *
     * Every dropdown on the screen: the journey picker, department, task owner,
     * category, status, stage, plus the Accepted offers the "Start from offer"
     * action can seed a journey from.
     */
    public function filters(Request $request)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $journeys = DB::table('talent_onboarding_journeys')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->orderByDesc('joining_date')
            ->orderByDesc('id')
            // candidate_email is selected because journeyIdentity() reads it for a
            // hire who has no tbluser row yet.
            ->get([
                'id', 'journey_code', 'employee_id', 'candidate_name', 'candidate_email',
                'position', 'stage', 'status', 'joining_date',
            ]);

        $directory = $this->onboardingDirectory($tenant, $journeys->pluck('employee_id')->all());

        $journeyOptions = $journeys->map(function ($journey) use ($directory) {
            $identity = $this->journeyIdentity($journey, $directory);

            return [
                'value'        => (string) $journey->id,
                'label'        => $identity['name'],
                'journey_code' => $journey->journey_code,
                'position'     => $journey->position,
                'stage'        => $journey->stage,
                'status'       => $journey->status,
                'joining_date' => $journey->joining_date,
            ];
        })->all();

        $departments = DB::table('hrms_departments')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->orderBy('department')
            ->get(['id', 'department'])
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->department])
            ->all();

        // Owners are the users already assigned to a task in this tenant, plus
        // the free-text team owners (HR Team / IT Team) the screen also shows.
        $ownerIds = DB::table('talent_onboarding_tasks')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereNotNull('owner_id')
            ->distinct()
            ->pluck('owner_id')
            ->all();

        $ownerDirectory = $this->onboardingDirectory($tenant, $ownerIds);

        $owners = array_values(array_map(
            fn ($owner) => ['value' => (string) $owner['id'], 'label' => $owner['name']],
            $ownerDirectory
        ));

        $ownerLabels = DB::table('talent_onboarding_tasks')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereNull('owner_id')
            ->whereNotNull('owner_label')
            ->distinct()
            ->pluck('owner_label')
            ->map(fn ($label) => ['value' => 'label:' . $label, 'label' => $label])
            ->all();

        // Employees who can be assigned as owner / manager / buddy.
        $employees = DB::table('tbluser')
            ->where('sub_institute_id', $tenant)
            ->orderBy('first_name')
            ->limit(500)
            ->get(['id', 'first_name', 'last_name', 'user_name', 'employee_no', 'department_id'])
            ->map(function ($user) {
                $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

                return [
                    'value'         => (string) $user->id,
                    'label'         => $name !== '' ? $name : ($user->user_name ?? 'Unknown'),
                    'employee_no'   => $user->employee_no,
                    'department_id' => $user->department_id ? (string) $user->department_id : null,
                ];
            })
            ->all();

        // Offers a journey can be seeded from: sent (i.e. not rejected/expired)
        // and not already attached to a journey.
        $linkedOfferIds = DB::table('talent_onboarding_journeys')
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->whereNotNull('offer_id')
            ->pluck('offer_id')
            ->all();

        $offers = DB::table('talent_offers as o')
            ->leftJoin('talent_job_applications as a', 'o.application_id', '=', 'a.id')
            ->leftJoin('talent_job_postings as p', 'o.job_id', '=', 'p.id')
            ->where('o.sub_institute_id', $tenant)
            ->where('o.status', 'sent')
            ->when(!empty($linkedOfferIds), fn ($q) => $q->whereNotIn('o.id', $linkedOfferIds))
            ->orderByDesc('o.id')
            ->limit(200)
            ->get([
                'o.id', 'o.position', 'o.start_date', 'o.reportmanager', 'o.application_id',
                'a.first_name', 'a.last_name', 'a.email as candidate_email',
                'a.mobile as candidate_phone', 'a.current_location',
                'p.department_id',
            ])
            ->map(function ($offer) {
                $name = trim(($offer->first_name ?? '') . ' ' . ($offer->last_name ?? ''));
                $name = $name !== '' ? $name : 'Candidate #' . $offer->application_id;

                return [
                    'value'           => (string) $offer->id,
                    'label'           => $name . ($offer->position ? ' - ' . $offer->position : ''),
                    'position'        => $offer->position,
                    'start_date'      => $offer->start_date,
                    'candidate_name'  => $name,
                    'candidate_email' => $offer->candidate_email,
                    'candidate_phone' => $offer->candidate_phone,
                    'location'        => $offer->current_location,
                    'department_id'   => $offer->department_id ? (string) $offer->department_id : null,
                    'manager_id'      => $offer->reportmanager ? (string) $offer->reportmanager : null,
                    'application_id'  => $offer->application_id ? (string) $offer->application_id : null,
                ];
            })
            ->all();

        // This target has no standalone `document_type` table. It reuses
        // `student_document_type` as the shared document-type master for both
        // students and staff (filtered by `user_type`) - see
        // EmployeeDirectoryController's `documentTypeLists`/`documentLists` for
        // the established convention this follows. No `deleted_at` column
        // exists on this table (unlike G2G's `document_type`).
        $documentTypes = DB::table('student_document_type')
            ->where('user_type', 'staff')
            ->where('status', 1)
            ->orderBy('document_type')
            ->get(['id', 'document_type'])
            ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->document_type])
            ->all();

        return $this->onboardingResponse([
            'journeys'      => $journeyOptions,
            'departments'   => $departments,
            'owners'        => array_merge($owners, $ownerLabels),
            'employees'     => $employees,
            'offers'        => $offers,
            'document_types'=> $documentTypes,
            'categories'    => array_map(
                fn ($key, $label) => ['value' => $key, 'label' => $label],
                array_keys(self::TASK_CATEGORIES),
                array_values(self::TASK_CATEGORIES)
            ),
            'task_statuses' => [
                ['value' => 'pending',     'label' => 'Pending'],
                ['value' => 'in_progress', 'label' => 'In Progress'],
                ['value' => 'sent',        'label' => 'Sent'],
                ['value' => 'completed',   'label' => 'Completed'],
                ['value' => 'blocked',     'label' => 'Blocked'],
            ],
            'journey_statuses' => [
                ['value' => 'not-started', 'label' => 'Not Started'],
                ['value' => 'in-progress', 'label' => 'In Progress'],
                ['value' => 'completed',   'label' => 'Completed'],
                ['value' => 'on-hold',     'label' => 'On Hold'],
                ['value' => 'cancelled',   'label' => 'Cancelled'],
            ],
            'stages' => array_map(
                fn ($stage) => ['value' => $stage['stage_key'], 'label' => $stage['title']],
                self::DEFAULT_STAGES
            ),
            'document_statuses' => [
                ['value' => 'pending',  'label' => 'Pending'],
                ['value' => 'sent',     'label' => 'Sent'],
                ['value' => 'received', 'label' => 'Received'],
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'confirmation_statuses' => [
                ['value' => 'pending',    'label' => 'Pending'],
                ['value' => 'confirmed',  'label' => 'Confirmed'],
                ['value' => 'extended',   'label' => 'Extended'],
                ['value' => 'terminated', 'label' => 'Terminated'],
            ],
        ]);
    }
}
