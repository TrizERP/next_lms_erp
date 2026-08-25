<?php

namespace App\Http\Controllers\api\TalentManagement\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;
use App\Http\Controllers\api\TalentManagement\Onboarding\Concerns\ResolvesOnboardingContext;
use App\Models\AuditLog;
use App\Models\TalentManagement\TalentOnboardingJourney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Onboarding\OnboardingProbationController`.
 *
 * The Probation & Confirmation tab.
 *
 * The probation WINDOW already lives on tbluser (probation_period_from /
 * probation_period_to) for a joined employee; what the HR master has nowhere to
 * store is the DECISION - confirmed, extended or terminated, by whom and when.
 * The journey carries the decision and mirrors the window back onto tbluser when
 * the hire has an employee record, so the two never disagree.
 *
 * Writes to tbluser are confined to probation_period_from / probation_period_to
 * and only happen on an explicit confirm / extend, never on a read.
 */
class OnboardingProbationController extends Controller
{
    use ResolvesOnboardingContext;
    use RequiresTalentAdmin;

    private const SORTABLE = ['probation_end', 'joining_date', 'confirmation_status', 'created_at'];

    /**
     * GET /api/onboarding/probation
     *
     * The tab's table. Defaults to everything still undecided; `due_in_days`
     * narrows it to the window the "Probation Due" KPI counts.
     *
     * Query: confirmation_status, department_id, due_in_days, search, overdue_only,
     *        page, per_page, sort_by, sort_dir
     */
    public function index(Request $request)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        [$sortBy, $sortDir] = $this->onboardingSort($request, self::SORTABLE, 'probation_end', 'asc');
        $paging = $this->onboardingPaging($request);

        $status = $this->activeOnbFilter($request->input('confirmation_status'));
        $departmentId = $this->activeOnbFilter($request->input('department_id'));
        $dueInDays = $this->activeOnbFilter($request->input('due_in_days'));
        $search = $this->activeOnbFilter($request->input('search'));
        $today = now()->toDateString();

        $query = TalentOnboardingJourney::where('sub_institute_id', $tenant)
            // A journey only reaches this tab once a probation window exists.
            ->whereNotNull('probation_end')
            ->when($status, fn ($q) => $q->where('confirmation_status', $status))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($dueInDays, fn ($q) => $q->whereBetween('probation_end', [
                $today,
                now()->addDays((int) $dueInDays)->toDateString(),
            ]))
            ->when($request->boolean('overdue_only'), fn ($q) => $q
                ->where('confirmation_status', 'pending')
                ->whereDate('probation_end', '<', $today))
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('candidate_name', 'like', "%{$search}%")
                    ->orWhere('journey_code', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%");
            }));

        $total = (clone $query)->count();

        $summary = (clone $query)
            ->selectRaw('
                COUNT(*) as total,
                SUM(confirmation_status = "pending") as pending,
                SUM(confirmation_status = "confirmed") as confirmed,
                SUM(confirmation_status = "extended") as extended,
                SUM(confirmation_status = "terminated") as terminated_count
            ')
            ->first();

        $journeys = $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->forPage($paging['page'], $paging['per_page'])
            ->get();

        $directory = $this->onboardingDirectory($tenant, array_merge(
            $journeys->pluck('employee_id')->all(),
            $journeys->pluck('manager_id')->all(),
            $journeys->pluck('confirmed_by')->all()
        ));

        $data = $journeys->map(fn ($journey) => $this->presentProbation($journey, $directory))->all();

        return $this->onboardingResponse($data, 'Success', 200, [
            'pagination' => [
                'page'      => $paging['page'],
                'per_page'  => $paging['per_page'],
                'total'     => $total,
                'last_page' => max(1, (int) ceil($total / $paging['per_page'])),
            ],
            'summary' => [
                'total'      => (int) ($summary->total ?? 0),
                'pending'    => (int) ($summary->pending ?? 0),
                'confirmed'  => (int) ($summary->confirmed ?? 0),
                'extended'   => (int) ($summary->extended ?? 0),
                // Aliased away from `terminated`, which MariaDB reserves.
                'terminated' => (int) ($summary->terminated_count ?? 0),
            ],
        ]);
    }

    /**
     * PUT /api/onboarding/probation/{journeyId}
     *
     * Sets or edits the probation window before a decision is taken. Mirrored
     * onto tbluser.probation_period_from/to when the hire has an employee record.
     */
    public function update(Request $request, $journeyId)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $journeyId);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $validated = $request->validate([
            'probation_start' => 'required|date',
            'probation_end'   => 'required|date|after_or_equal:probation_start',
        ]);

        $changes = $this->diffOnboardingChanges($journey->getOriginal(), $validated, [
            'probation_start' => 'Probation Start',
            'probation_end'   => 'Probation End',
        ]);

        $journey->fill($validated);
        $journey->updated_by = $context['user_id'];
        $journey->save();

        $this->mirrorToEmployee($tenant, $journey);

        if (!empty($changes)) {
            $this->logOnboardingActivity(
                $tenant,
                $context['user_id'],
                'updated_probation',
                'set probation window for ' . $journey->journey_code,
                'probation',
                $journey->id,
                $journey->candidate_name,
                $changes,
                (int) $journey->id
            );

            AuditLog::record([
                'module'      => 'talent_management',
                'action'      => 'onboarding_probation_window_updated',
                'entity_type' => 'onboarding_journey',
                'entity_id'   => $journey->id,
                'new_values'  => $validated,
            ]);
        }

        $directory = $this->onboardingDirectory($tenant, [$journey->employee_id, $journey->manager_id]);

        return $this->onboardingResponse($this->presentProbation($journey, $directory), 'Probation window updated');
    }

    /**
     * POST /api/onboarding/probation/{journeyId}/confirm
     *
     * Confirms the employee. This is the terminal happy path: the journey closes
     * and its stage moves to confirmed.
     */
    public function confirm(Request $request, $journeyId)
    {
        return $this->decide($request, $journeyId, 'confirmed');
    }

    /**
     * POST /api/onboarding/probation/{journeyId}/extend
     *
     * Extends probation to `extension_end`; the journey stays open and the row
     * returns to the pending queue against the new date.
     */
    public function extend(Request $request, $journeyId)
    {
        return $this->decide($request, $journeyId, 'extended');
    }

    /** POST /api/onboarding/probation/{journeyId}/terminate */
    public function terminate(Request $request, $journeyId)
    {
        return $this->decide($request, $journeyId, 'terminated');
    }

    /* ------------------------------------------------------------------ *
     * Internals
     * ------------------------------------------------------------------ */

    private function decide(Request $request, $journeyId, string $decision)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $journeyId);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        if ($journey->confirmation_status === 'confirmed') {
            return $this->onboardingError('This employee is already confirmed', 422);
        }

        if ($journey->confirmation_status === 'terminated') {
            return $this->onboardingError('This journey was terminated and cannot be changed', 422);
        }

        $rules = [
            'effective_date' => 'nullable|date',
            'notes'          => 'nullable|string',
        ];

        // An extension is meaningless without the date it runs to.
        if ($decision === 'extended') {
            $rules['extension_end'] = 'required|date|after:' . ($journey->probation_end?->toDateString() ?? 'today');
        }

        $validated = $request->validate($rules);

        $previous = $journey->confirmation_status;

        $journey->confirmation_status = $decision;
        $journey->confirmed_on = $validated['effective_date'] ?? now()->toDateString();
        $journey->confirmed_by = $context['user_id'];
        $journey->confirmation_notes = $validated['notes'] ?? $journey->confirmation_notes;

        if ($decision === 'extended') {
            $journey->extension_end = $validated['extension_end'];
            // The extension becomes the live probation end, so the KPI and the
            // pending queue both track the new date.
            $journey->probation_end = $validated['extension_end'];
            // An extension is not an outcome - the decision is pending again.
            $journey->confirmation_status = 'extended';
        }

        if ($decision === 'confirmed') {
            $journey->stage = 'confirmed';
            $journey->status = 'completed';
            $journey->completed_at = $journey->completed_at ?? now()->toDateString();
        }

        if ($decision === 'terminated') {
            $journey->status = 'cancelled';
        }

        $journey->updated_by = $context['user_id'];
        $journey->save();

        if ($decision === 'confirmed') {
            $this->closeStages($journey, $context['user_id']);
        }

        $this->mirrorToEmployee($tenant, $journey);

        $this->logOnboardingActivity(
            $tenant,
            $context['user_id'],
            $decision . '_probation',
            $decision . ' probation for ' . ($journey->candidate_name ?: $journey->journey_code),
            'probation',
            $journey->id,
            $journey->candidate_name,
            [['field' => 'confirmation_status', 'label' => 'Confirmation', 'old' => $previous, 'new' => $journey->confirmation_status]],
            (int) $journey->id
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'onboarding_probation_' . $decision,
            'entity_type' => 'onboarding_journey',
            'entity_id'   => $journey->id,
            'new_values'  => array_merge($validated, [
                'confirmation_status' => ['old' => $previous, 'new' => $journey->confirmation_status],
            ]),
        ]);

        $directory = $this->onboardingDirectory($tenant, [
            $journey->employee_id, $journey->manager_id, $journey->confirmed_by,
        ]);

        return $this->onboardingResponse(
            $this->presentProbation($journey, $directory),
            match ($decision) {
                'confirmed'  => 'Employee confirmed',
                'extended'   => 'Probation extended',
                'terminated' => 'Probation terminated',
            }
        );
    }

    /**
     * Mirror the probation window onto the HR master.
     *
     * Only runs for a journey that already points at a tbluser row, and only ever
     * touches the two probation columns.
     */
    private function mirrorToEmployee(int $tenant, TalentOnboardingJourney $journey): void
    {
        if (!$journey->employee_id) {
            return;
        }

        $updates = array_filter([
            'probation_period_from' => optional($journey->probation_start)->toDateString(),
            'probation_period_to'   => optional($journey->probation_end)->toDateString(),
        ], fn ($value) => $value !== null);

        if (empty($updates)) {
            return;
        }

        DB::table('tbluser')
            ->where('sub_institute_id', $tenant)
            ->where('id', $journey->employee_id)
            ->update($updates);
    }

    /** Confirming an employee closes every remaining step on the timeline. */
    private function closeStages(TalentOnboardingJourney $journey, ?int $actorId): void
    {
        DB::table('talent_onboarding_journey_stages')
            ->where('journey_id', $journey->id)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['completed', 'skipped'])
            ->update([
                'status'       => 'completed',
                'completed_at' => now(),
                'updated_by'   => $actorId,
                'updated_at'   => now(),
            ]);
    }

    /** @param array<int, array<string, mixed>> $directory */
    private function presentProbation(TalentOnboardingJourney $journey, array $directory): array
    {
        $identity = $this->journeyIdentity($journey, $directory);
        $employee = $journey->employee_id ? ($directory[(int) $journey->employee_id] ?? null) : null;
        $today = now()->startOfDay();

        $daysRemaining = $journey->probation_end
            ? (int) $today->diffInDays($journey->probation_end->startOfDay(), false)
            : null;

        return [
            'id'                  => (int) $journey->id,
            'journey_code'        => $journey->journey_code,
            'name'                => $identity['name'],
            'initials'            => $identity['initials'],
            'email'               => $identity['email'],
            'employee_id'         => $journey->employee_id ? (int) $journey->employee_id : null,
            'employee_no'         => $identity['employee_no'],
            'position'            => $journey->position ?: ($employee['designation'] ?? null),
            'department'          => $employee['department'] ?? null,
            'department_id'       => $journey->department_id ? (int) $journey->department_id : null,
            'manager_name'        => $journey->manager_id ? ($directory[(int) $journey->manager_id]['name'] ?? null) : null,
            'joining_date'        => optional($journey->joining_date)->toDateString(),
            'joining_date_label'  => $this->onbDateLabel($journey->joining_date),
            'probation_start'     => optional($journey->probation_start)->toDateString(),
            'probation_end'       => optional($journey->probation_end)->toDateString(),
            'probation_label'     => $this->onbRangeLabel($journey->probation_start, $journey->probation_end),
            'extension_end'       => optional($journey->extension_end)->toDateString(),
            'days_remaining'      => $daysRemaining,
            'is_overdue'          => $journey->confirmation_status === 'pending' && $daysRemaining !== null && $daysRemaining < 0,
            'confirmation_status' => $journey->confirmation_status,
            'confirmation_label'  => $this->onbStatusLabel($journey->confirmation_status),
            'confirmed_on'        => optional($journey->confirmed_on)->toDateString(),
            'confirmed_on_label'  => $this->onbDateLabel($journey->confirmed_on),
            'confirmed_by'        => $journey->confirmed_by ? (int) $journey->confirmed_by : null,
            'confirmed_by_name'   => $journey->confirmed_by ? ($directory[(int) $journey->confirmed_by]['name'] ?? null) : null,
            'confirmation_notes'  => $journey->confirmation_notes,
        ];
    }
}
