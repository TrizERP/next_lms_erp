<?php

namespace App\Http\Controllers\api\TalentManagement\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;
use App\Http\Controllers\api\TalentManagement\Onboarding\Concerns\ResolvesOnboardingContext;
use App\Models\AuditLog;
use App\Models\TalentManagement\TalentOnboardingJourney;
use App\Models\TalentManagement\TalentOnboardingJourneyStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Onboarding\OnboardingJourneyController`.
 *
 * Onboarding journeys - one per new hire.
 *
 * Backs the journey list sheet (opened from the search box and the KPI cards),
 * the profile sidebar, the Onboarding Journey Progress timeline, the Key
 * Contacts card and the Lifecycle Timeline tab.
 *
 * A journey can be created by hand or seeded from an accepted offer, which is
 * what "Start onboarding" on the Recruitment Center's offer row means.
 */
class OnboardingJourneyController extends Controller
{
    use ResolvesOnboardingContext;
    use RequiresTalentAdmin;

    /** Columns whose edits are recorded in the audit trail. */
    private const AUDIT_LABELS = [
        'candidate_name'      => 'Candidate Name',
        'candidate_email'     => 'Email',
        'candidate_phone'     => 'Phone',
        'employee_id'         => 'Employee',
        'department_id'       => 'Department',
        'location'            => 'Location',
        'position'            => 'Position',
        'joining_date'        => 'Joining Date',
        'stage'               => 'Stage',
        'status'              => 'Status',
        'buddy_id'            => 'Buddy',
        'manager_id'          => 'Reporting Manager',
        'probation_start'     => 'Probation Start',
        'probation_end'       => 'Probation End',
        'notes'               => 'Notes',
    ];

    private const SORTABLE = ['joining_date', 'created_at', 'stage', 'status', 'journey_code', 'position'];

    /**
     * GET /api/onboarding/journeys
     *
     * Query: search, stage, status, department_id, confirmation_status,
     *        joining_from, joining_to, probation_due (days), page, per_page,
     *        sort_by, sort_dir
     */
    public function index(Request $request)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        [$sortBy, $sortDir] = $this->onboardingSort($request, self::SORTABLE, 'joining_date');
        $paging = $this->onboardingPaging($request);

        $query = $this->filteredJourneys($request, $tenant);

        $total = (clone $query)->count();

        $journeys = $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->forPage($paging['page'], $paging['per_page'])
            ->get();

        $directory = $this->onboardingDirectory($tenant, $journeys->pluck('employee_id')->all());
        $progress = $this->taskProgressFor($tenant, $journeys->pluck('id')->all());

        $data = $journeys->map(fn ($journey) => $this->presentJourney($journey, $directory, $progress))->all();

        return $this->onboardingResponse($data, 'Success', 200, [
            'pagination' => [
                'page'      => $paging['page'],
                'per_page'  => $paging['per_page'],
                'total'     => $total,
                'last_page' => max(1, (int) ceil($total / $paging['per_page'])),
            ],
        ]);
    }

    /** GET /api/onboarding/journeys/{id} */
    public function show(Request $request, $id)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $id);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $directory = $this->onboardingDirectory($tenant, [
            $journey->employee_id, $journey->manager_id, $journey->buddy_id,
        ]);
        $progress = $this->taskProgressFor($tenant, [$journey->id]);

        return $this->onboardingResponse(
            $this->presentJourney($journey, $directory, $progress, true)
        );
    }

    /**
     * POST /api/onboarding/journeys
     *
     * Either an existing employee (employee_id) or a candidate the tenant has
     * only as a name/email must be supplied - a preboarding hire has no tbluser
     * row yet, so requiring employee_id would make the main use case impossible.
     */
    public function store(Request $request)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $validated = $request->validate([
            'employee_id'     => 'nullable|integer|required_without:candidate_name',
            'candidate_name'  => 'nullable|string|max:191|required_without:employee_id',
            'candidate_email' => 'nullable|email|max:191',
            'candidate_phone' => 'nullable|regex:/^\d{10}$/',
            'offer_id'        => 'nullable|integer',
            'application_id'  => 'nullable|integer',
            'department_id'   => 'nullable|integer',
            'location'        => 'nullable|string|max:191',
            'position'        => 'nullable|string|max:191',
            'joining_date'    => 'nullable|date',
            'stage'           => 'nullable|in:preboarding,first_day,orientation,team_integration,probation,confirmed,exited',
            'status'          => 'nullable|in:not-started,in-progress,completed,on-hold,cancelled',
            'buddy_id'        => 'nullable|integer',
            'manager_id'      => 'nullable|integer',
            'probation_start' => 'nullable|date',
            'probation_end'   => 'nullable|date|after_or_equal:probation_start',
            'notes'           => 'nullable|string',
            'seed_stages'     => 'nullable|boolean',
        ]);

        $journey = $this->createJourney($tenant, $context['user_id'], $validated);

        $directory = $this->onboardingDirectory($tenant, [
            $journey->employee_id, $journey->manager_id, $journey->buddy_id,
        ]);

        return $this->onboardingResponse(
            $this->presentJourney($journey, $directory, [], true),
            'Onboarding journey created',
            201
        );
    }

    /**
     * POST /api/onboarding/journeys/from-offer/{offerId}
     *
     * The Recruitment Center's "Start onboarding" action. Copies position,
     * joining date, manager and the candidate's contact details off the offer and
     * its application, so HR does not retype what recruitment already captured.
     */
    public function storeFromOffer(Request $request, $offerId)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $offer = DB::table('talent_offers as o')
            ->leftJoin('talent_job_applications as a', 'o.application_id', '=', 'a.id')
            ->leftJoin('talent_job_postings as p', 'o.job_id', '=', 'p.id')
            ->where('o.sub_institute_id', $tenant)
            ->where('o.id', $offerId)
            ->first([
                'o.id', 'o.position', 'o.start_date', 'o.reportmanager', 'o.application_id', 'o.status',
                'a.first_name', 'a.last_name', 'a.email', 'a.mobile', 'a.current_location',
                'p.department_id',
            ]);

        if (!$offer) {
            return $this->onboardingError('Offer not found', 404);
        }

        if (in_array($offer->status, ['rejected', 'expired'], true)) {
            return $this->onboardingError('This offer was ' . $offer->status . ' and cannot start onboarding', 422);
        }

        $existing = TalentOnboardingJourney::where('sub_institute_id', $tenant)
            ->where('offer_id', $offer->id)
            ->first();

        if ($existing) {
            return $this->onboardingError(
                'This offer already has an onboarding journey (' . $existing->journey_code . ')',
                422,
                ['journey_id' => (int) $existing->id]
            );
        }

        $name = trim(($offer->first_name ?? '') . ' ' . ($offer->last_name ?? ''));

        $journey = $this->createJourney($tenant, $context['user_id'], [
            'offer_id'        => (int) $offer->id,
            'application_id'  => $offer->application_id ? (int) $offer->application_id : null,
            'candidate_name'  => $name !== '' ? $name : 'Candidate #' . $offer->application_id,
            'candidate_email' => $offer->email,
            'candidate_phone' => $offer->mobile,
            'position'        => $offer->position,
            'location'        => $offer->current_location,
            'department_id'   => $offer->department_id ? (int) $offer->department_id : null,
            'joining_date'    => $offer->start_date,
            'manager_id'      => $offer->reportmanager ? (int) $offer->reportmanager : null,
        ]);

        $directory = $this->onboardingDirectory($tenant, [$journey->manager_id]);

        return $this->onboardingResponse(
            $this->presentJourney($journey, $directory, [], true),
            'Onboarding started for ' . $journey->candidate_name,
            201
        );
    }

    /** PUT /api/onboarding/journeys/{id} */
    public function update(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $id);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $validated = $request->validate([
            'employee_id'     => 'nullable|integer',
            'candidate_name'  => 'nullable|string|max:191',
            'candidate_email' => 'nullable|email|max:191',
            'candidate_phone' => 'nullable|regex:/^\d{10}$/',
            'department_id'   => 'nullable|integer',
            'location'        => 'nullable|string|max:191',
            'position'        => 'nullable|string|max:191',
            'joining_date'    => 'nullable|date',
            'stage'           => 'nullable|in:preboarding,first_day,orientation,team_integration,probation,confirmed,exited',
            'status'          => 'nullable|in:not-started,in-progress,completed,on-hold,cancelled',
            'buddy_id'        => 'nullable|integer',
            'manager_id'      => 'nullable|integer',
            'probation_start' => 'nullable|date',
            'probation_end'   => 'nullable|date|after_or_equal:probation_start',
            'notes'           => 'nullable|string',
        ]);

        $changes = $this->diffOnboardingChanges($journey->getOriginal(), $validated, self::AUDIT_LABELS);

        $journey->fill($validated);

        // Completing a journey stamps the date the timeline reads.
        if (($validated['status'] ?? null) === 'completed' && !$journey->completed_at) {
            $journey->completed_at = now()->toDateString();
        }

        $journey->updated_by = $context['user_id'];
        $journey->save();

        if (!empty($changes)) {
            $this->logOnboardingActivity(
                $tenant,
                $context['user_id'],
                'updated_journey',
                'updated onboarding journey ' . $journey->journey_code,
                'journey',
                $journey->id,
                $journey->candidate_name,
                $changes,
                $journey->id
            );

            AuditLog::record([
                'module'      => 'talent_management',
                'action'      => 'onboarding_journey_updated',
                'entity_type' => 'onboarding_journey',
                'entity_id'   => $journey->id,
                'new_values'  => $validated,
            ]);
        }

        $directory = $this->onboardingDirectory($tenant, [
            $journey->employee_id, $journey->manager_id, $journey->buddy_id,
        ]);
        $progress = $this->taskProgressFor($tenant, [$journey->id]);

        return $this->onboardingResponse(
            $this->presentJourney($journey, $directory, $progress, true),
            'Onboarding journey updated'
        );
    }

    /**
     * DELETE /api/onboarding/journeys/{id}
     *
     * Soft deletes the journey and everything hanging off it, so the sidebar
     * cannot end up showing orphan tasks or documents.
     */
    public function destroy(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $id);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $code = $journey->journey_code;
        $name = $journey->candidate_name;

        DB::transaction(function () use ($journey, $context) {
            $stamp = ['deleted_at' => now(), 'deleted_by' => $context['user_id']];

            foreach ([
                'talent_onboarding_tasks',
                'talent_onboarding_journey_stages',
                'talent_onboarding_documents',
                'talent_onboarding_notes',
            ] as $table) {
                DB::table($table)->where('journey_id', $journey->id)->whereNull('deleted_at')->update($stamp);
            }

            $journey->deleted_by = $context['user_id'];
            $journey->save();
            $journey->delete();
        });

        $this->logOnboardingActivity(
            $tenant,
            $context['user_id'],
            'deleted_journey',
            'deleted onboarding journey ' . $code,
            'journey',
            (int) $id,
            $name,
            null,
            (int) $id
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'onboarding_journey_deleted',
            'entity_type' => 'onboarding_journey',
            'entity_id'   => (int) $id,
            'new_values'  => ['journey_code' => $code, 'candidate_name' => $name],
        ]);

        return $this->onboardingResponse(['id' => (int) $id], 'Onboarding journey deleted');
    }

    /* ------------------------------------------------------------------ *
     * Stages - the "Onboarding Journey Progress" timeline
     * ------------------------------------------------------------------ */

    /** GET /api/onboarding/journeys/{journeyId}/stages */
    public function stages(Request $request, $journeyId)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $journeyId);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $stages = TalentOnboardingJourneyStage::where('journey_id', $journey->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $completed = $stages->where('status', 'completed')->count();

        return $this->onboardingResponse([
            'stages'       => $stages->map(fn ($stage) => $this->presentStage($stage))->all(),
            'total'        => $stages->count(),
            'completed'    => $completed,
            'progress_pct' => $stages->count() > 0 ? (int) round($completed / $stages->count() * 100) : 0,
        ]);
    }

    /** PUT /api/onboarding/stages/{id} */
    public function updateStage(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $stage = TalentOnboardingJourneyStage::where('sub_institute_id', $tenant)->find($id);

        if (!$stage) {
            return $this->onboardingError('Journey stage not found', 404);
        }

        $validated = $request->validate([
            'title'      => 'sometimes|required|string|max:191',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'status'     => 'nullable|in:pending,in_progress,completed,skipped',
            'notes'      => 'nullable|string',
        ]);

        $changes = $this->diffOnboardingChanges($stage->getOriginal(), $validated, [
            'title'      => 'Stage',
            'start_date' => 'Start Date',
            'end_date'   => 'End Date',
            'status'     => 'Status',
            'notes'      => 'Notes',
        ]);

        $stage->fill($validated);

        if (($validated['status'] ?? null) === 'completed' && !$stage->completed_at) {
            $stage->completed_at = now();
        } elseif (array_key_exists('status', $validated) && $validated['status'] !== 'completed') {
            $stage->completed_at = null;
        }

        $stage->updated_by = $context['user_id'];
        $stage->save();

        $this->syncJourneyStage($tenant, (int) $stage->journey_id, $context['user_id']);

        if (!empty($changes)) {
            $this->logOnboardingActivity(
                $tenant,
                $context['user_id'],
                'updated_stage',
                'updated journey stage ' . $stage->title,
                'stage',
                $stage->id,
                $stage->title,
                $changes,
                (int) $stage->journey_id
            );

            AuditLog::record([
                'module'      => 'talent_management',
                'action'      => 'onboarding_stage_updated',
                'entity_type' => 'onboarding_journey_stage',
                'entity_id'   => $stage->id,
                'new_values'  => $validated,
            ]);
        }

        return $this->onboardingResponse($this->presentStage($stage), 'Journey stage updated');
    }

    /** POST /api/onboarding/stages/{id}/complete */
    public function completeStage(Request $request, $id)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        $stage = TalentOnboardingJourneyStage::where('sub_institute_id', $tenant)->find($id);

        if (!$stage) {
            return $this->onboardingError('Journey stage not found', 404);
        }

        $reopen = $request->boolean('reopen');

        $stage->status = $reopen ? 'pending' : 'completed';
        $stage->completed_at = $reopen ? null : now();
        $stage->updated_by = $context['user_id'];
        $stage->save();

        $this->syncJourneyStage($tenant, (int) $stage->journey_id, $context['user_id']);

        $this->logOnboardingActivity(
            $tenant,
            $context['user_id'],
            $reopen ? 'reopened_stage' : 'completed_stage',
            ($reopen ? 'reopened' : 'completed') . ' journey stage ' . $stage->title,
            'stage',
            $stage->id,
            $stage->title,
            null,
            (int) $stage->journey_id
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => $reopen ? 'onboarding_stage_reopened' : 'onboarding_stage_completed',
            'entity_type' => 'onboarding_journey_stage',
            'entity_id'   => $stage->id,
            'new_values'  => ['status' => $stage->status],
        ]);

        return $this->onboardingResponse(
            $this->presentStage($stage),
            $reopen ? 'Stage reopened' : 'Stage marked complete'
        );
    }

    /* ------------------------------------------------------------------ *
     * Key Contacts and Lifecycle Timeline
     * ------------------------------------------------------------------ */

    /**
     * GET /api/onboarding/journeys/{journeyId}/contacts
     *
     * The sidebar Key Contacts card. Derived from real tbluser rows on the
     * journey - the reporting manager, the onboarding buddy and the HR owner who
     * created it - rather than a separate contacts table, so an email always
     * belongs to someone who actually exists in this tenant.
     */
    public function contacts(Request $request, $journeyId)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $journeyId);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $directory = $this->onboardingDirectory($tenant, [
            $journey->manager_id, $journey->buddy_id, $journey->created_by, $journey->employee_id,
        ]);

        $roles = [
            ['role' => 'Reporting Manager',  'user_id' => $journey->manager_id],
            ['role' => 'Onboarding Buddy',   'user_id' => $journey->buddy_id],
            ['role' => 'HR Onboarding Owner','user_id' => $journey->created_by],
        ];

        $contacts = [];

        foreach ($roles as $index => $role) {
            $user = $role['user_id'] ? ($directory[(int) $role['user_id']] ?? null) : null;

            if (!$user) {
                continue;
            }

            $contacts[] = [
                'id'          => $role['role'] . '-' . $user['id'],
                'role'        => $role['role'],
                'name'        => $user['name'],
                'email'       => $user['email'],
                'mobile'      => $user['mobile'],
                'initials'    => $user['initials'],
                'user_id'     => $user['id'],
                'designation' => $user['designation'],
                'department'  => $user['department'],
            ];
        }

        return $this->onboardingResponse($contacts);
    }

    /**
     * GET /api/onboarding/journeys/{journeyId}/timeline
     *
     * The Lifecycle Timeline tab: the journey's own milestones (offer, joining,
     * probation window, confirmation, exit) merged with the activity feed, newest
     * first. Employment dates come from tbluser once the hire has a record there.
     */
    public function timeline(Request $request, $journeyId)
    {
        $context = $this->onboardingContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $journey = $this->findJourney($tenant, $journeyId);
        if (!$journey instanceof TalentOnboardingJourney) {
            return $journey;
        }

        $milestones = [];

        $push = function (?string $date, string $title, string $type, ?string $detail = null) use (&$milestones) {
            if (!$date) {
                return;
            }

            $milestones[] = [
                'id'         => $type . '-' . $date,
                'type'       => $type,
                'title'      => $title,
                'detail'     => $detail,
                'date'       => $date,
                'date_label' => $this->onbDateLabel($date),
                'source'     => 'milestone',
            ];
        };

        if ($journey->offer_id) {
            $offer = DB::table('talent_offers')
                ->where('id', $journey->offer_id)
                ->first(['sent_at', 'position', 'status']);

            if ($offer && $offer->sent_at) {
                $push(substr((string) $offer->sent_at, 0, 10), 'Offer Issued', 'offer', $offer->position);
            }
        }

        $push(optional($journey->created_at)->toDateString(), 'Onboarding Started', 'journey', $journey->journey_code);
        $push(optional($journey->joining_date)->toDateString(), 'Date of Joining', 'joining', $journey->position);
        $push(optional($journey->probation_start)->toDateString(), 'Probation Started', 'probation', null);
        $push(optional($journey->probation_end)->toDateString(), 'Probation Ends', 'probation', null);
        $push(optional($journey->extension_end)->toDateString(), 'Probation Extended To', 'probation', null);
        $push(optional($journey->confirmed_on)->toDateString(), 'Confirmation ' . ucfirst((string) $journey->confirmation_status), 'confirmation', $journey->confirmation_notes);
        $push(optional($journey->completed_at)->toDateString(), 'Onboarding Completed', 'journey', null);

        // Employment dates the HR master already holds for a joined employee.
        if ($journey->employee_id) {
            $employee = DB::table('tbluser')
                ->where('sub_institute_id', $tenant)
                ->where('id', $journey->employee_id)
                ->first(['joined_date', 'probation_period_from', 'probation_period_to', 'terminated_date', 'relieving_date', 'termination_reason']);

            if ($employee) {
                $push($employee->terminated_date, 'Terminated', 'exit', $employee->termination_reason);
                $push($employee->relieving_date, 'Relieved', 'exit', null);
            }
        }

        $activity = DB::table('talent_onboarding_activity_log')
            ->where('sub_institute_id', $tenant)
            ->where('journey_id', $journey->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $events = $activity->map(fn ($row) => [
            'id'         => 'activity-' . $row->id,
            'type'       => $row->subject_type ?: 'activity',
            'title'      => ucfirst(str_replace('_', ' ', $row->action)),
            'detail'     => $row->description,
            'actor'      => $row->actor_name,
            'changes'    => $row->changes ? json_decode($row->changes, true) : null,
            'date'       => substr((string) $row->created_at, 0, 10),
            'date_label' => $this->onbDateTimeLabel($row->created_at),
            'source'     => 'activity',
        ])->all();

        // Newest first across both sources.
        usort($milestones, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $this->onboardingResponse([
            'milestones' => array_values($milestones),
            'activity'   => $events,
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Internals
     * ------------------------------------------------------------------ */

    /** Shared by store() and storeFromOffer(): insert + seed the seven stages. */
    private function createJourney(int $tenant, ?int $actorId, array $attributes): TalentOnboardingJourney
    {
        $seedStages = $attributes['seed_stages'] ?? true;
        unset($attributes['seed_stages']);

        return DB::transaction(function () use ($tenant, $actorId, $attributes, $seedStages) {
            $journey = TalentOnboardingJourney::create(array_merge($attributes, [
                'sub_institute_id'    => $tenant,
                'journey_code'        => $this->nextJourneyCode($tenant),
                'stage'               => $attributes['stage'] ?? 'preboarding',
                'status'              => $attributes['status'] ?? 'not-started',
                'confirmation_status' => 'pending',
                'created_by'          => $actorId,
                'updated_by'          => $actorId,
            ]));

            if ($seedStages) {
                $this->seedStages($journey, $actorId);
            }

            $this->logOnboardingActivity(
                $tenant,
                $actorId,
                'created_journey',
                'started onboarding journey ' . $journey->journey_code,
                'journey',
                $journey->id,
                $journey->candidate_name,
                null,
                $journey->id
            );

            AuditLog::record([
                'module'      => 'talent_management',
                'action'      => 'onboarding_journey_created',
                'entity_type' => 'onboarding_journey',
                'entity_id'   => $journey->id,
                'new_values'  => [
                    'journey_code'    => $journey->journey_code,
                    'candidate_name'  => $journey->candidate_name,
                    'offer_id'        => $journey->offer_id,
                    'employee_id'     => $journey->employee_id,
                    'stage'           => $journey->stage,
                    'status'          => $journey->status,
                ],
            ]);

            return $journey;
        });
    }

    /**
     * The seven default steps, anchored to whatever dates the journey knows.
     *
     * Only stages the journey actually has a date for get one - the rest are left
     * blank for HR to fill in, rather than being back-filled with invented offsets.
     */
    private function seedStages(TalentOnboardingJourney $journey, ?int $actorId): void
    {
        $joining = $journey->joining_date;
        $now = now();
        $rows = [];

        foreach (self::DEFAULT_STAGES as $index => $stage) {
            [$start, $end] = match ($stage['stage_key']) {
                'offer_accepted'   => [optional($journey->created_at)->toDateString() ?? $now->toDateString(), null],
                'preboarding'      => [optional($journey->created_at)->toDateString() ?? $now->toDateString(), optional($joining)->toDateString()],
                'first_day'        => [optional($joining)->toDateString(), optional($joining)->toDateString()],
                'probation'        => [optional($journey->probation_start)->toDateString(), optional($journey->probation_end)->toDateString()],
                'confirmation'     => [optional($journey->probation_end)->toDateString(), null],
                default            => [null, null],
            };

            $rows[] = [
                'journey_id'       => $journey->id,
                'sub_institute_id' => $journey->sub_institute_id,
                'stage_key'        => $stage['stage_key'],
                'title'            => $stage['title'],
                'start_date'       => $start,
                'end_date'         => $end,
                'status'           => $stage['stage_key'] === 'offer_accepted' ? 'completed' : 'pending',
                'completed_at'     => $stage['stage_key'] === 'offer_accepted' ? $now : null,
                'sort_order'       => $index,
                'created_by'       => $actorId,
                'updated_by'       => $actorId,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        DB::table('talent_onboarding_journey_stages')->insert($rows);
    }

    /**
     * Keep journey.stage in step with the furthest completed step, so the KPI
     * buckets and the profile badge cannot drift from the timeline.
     */
    private function syncJourneyStage(int $tenant, int $journeyId, ?int $actorId): void
    {
        $journey = TalentOnboardingJourney::where('sub_institute_id', $tenant)->find($journeyId);

        if (!$journey || in_array($journey->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $stages = TalentOnboardingJourneyStage::where('journey_id', $journeyId)
            ->orderBy('sort_order')
            ->get(['stage_key', 'status']);

        if ($stages->isEmpty()) {
            return;
        }

        // The current stage is the first one still open; all-complete means done.
        $current = $stages->first(fn ($stage) => $stage->status !== 'completed' && $stage->status !== 'skipped');

        $stageMap = [
            'offer_accepted'   => 'preboarding',
            'preboarding'      => 'preboarding',
            'first_day'        => 'first_day',
            'orientation'      => 'orientation',
            'team_integration' => 'team_integration',
            'probation'        => 'probation',
            'confirmation'     => 'probation',
        ];

        $journey->stage = $current ? ($stageMap[$current->stage_key] ?? $journey->stage) : 'confirmed';

        if (!$current) {
            $journey->status = 'completed';
            $journey->completed_at = $journey->completed_at ?? now()->toDateString();
        } elseif ($journey->status === 'not-started') {
            $journey->status = 'in-progress';
        }

        $journey->updated_by = $actorId;
        $journey->save();
    }

    /** @return \Illuminate\Database\Eloquent\Builder */
    private function filteredJourneys(Request $request, int $tenant)
    {
        $search = $this->activeOnbFilter($request->input('search'));
        $stage = $this->activeOnbFilter($request->input('stage'));
        $status = $this->activeOnbFilter($request->input('status'));
        $departmentId = $this->activeOnbFilter($request->input('department_id'));
        $confirmation = $this->activeOnbFilter($request->input('confirmation_status'));
        $joiningFrom = $this->activeOnbFilter($request->input('joining_from'));
        $joiningTo = $this->activeOnbFilter($request->input('joining_to'));
        $probationDue = $this->activeOnbFilter($request->input('probation_due'));

        return TalentOnboardingJourney::where('sub_institute_id', $tenant)
            ->when($stage, fn ($q) => $q->where('stage', $stage))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($confirmation, fn ($q) => $q->where('confirmation_status', $confirmation))
            ->when($joiningFrom, fn ($q) => $q->whereDate('joining_date', '>=', $joiningFrom))
            ->when($joiningTo, fn ($q) => $q->whereDate('joining_date', '<=', $joiningTo))
            ->when($probationDue, fn ($q) => $q
                ->where('confirmation_status', 'pending')
                ->whereNotNull('probation_end')
                ->whereBetween('probation_end', [
                    now()->toDateString(),
                    now()->addDays((int) $probationDue)->toDateString(),
                ]))
            ->when($search, function ($q) use ($search, $tenant) {
                // Search covers the candidate details on the journey plus the
                // employee master, since a joined hire's name lives in tbluser.
                $matchingUserIds = DB::table('tbluser')
                    ->where('sub_institute_id', $tenant)
                    ->where(function ($inner) use ($search) {
                        $inner->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_no', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->pluck('id')
                    ->all();

                $q->where(function ($inner) use ($search, $matchingUserIds) {
                    $inner->where('candidate_name', 'like', "%{$search}%")
                        ->orWhere('candidate_email', 'like', "%{$search}%")
                        ->orWhere('journey_code', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");

                    if (!empty($matchingUserIds)) {
                        $inner->orWhereIn('employee_id', $matchingUserIds);
                    }
                });
            });
    }

    /**
     * @param  array<int, array<string, mixed>>              $directory
     * @param  array<int, array{total:int,completed:int,pct:int}> $progress
     */
    private function presentJourney($journey, array $directory, array $progress, bool $detailed = false): array
    {
        $identity = $this->journeyIdentity($journey, $directory);
        $employee = $journey->employee_id ? ($directory[(int) $journey->employee_id] ?? null) : null;
        $counts = $progress[(int) $journey->id] ?? ['total' => 0, 'completed' => 0, 'pct' => 0];

        $row = [
            'id'                  => (int) $journey->id,
            'journey_code'        => $journey->journey_code,
            'name'                => $identity['name'],
            'initials'            => $identity['initials'],
            'email'               => $identity['email'],
            'employee_no'         => $identity['employee_no'],
            'image'               => $identity['image'],
            'employee_id'         => $journey->employee_id ? (int) $journey->employee_id : null,
            'offer_id'            => $journey->offer_id ? (int) $journey->offer_id : null,
            'application_id'      => $journey->application_id ? (int) $journey->application_id : null,
            'position'            => $journey->position ?: ($employee['designation'] ?? null),
            'department_id'       => $journey->department_id ? (int) $journey->department_id : null,
            'department'          => $employee['department'] ?? $this->departmentName($journey->department_id),
            'location'            => $journey->location ?: ($employee['location'] ?? null),
            'joining_date'        => optional($journey->joining_date)->toDateString(),
            'joining_date_label'  => $this->onbDateLabel($journey->joining_date),
            'stage'               => $journey->stage,
            'stage_label'         => $this->onbStageLabel($journey->stage),
            'status'              => $journey->status,
            'status_label'        => $this->onbStatusLabel($journey->status),
            'manager_id'          => $journey->manager_id ? (int) $journey->manager_id : null,
            'manager_name'        => $journey->manager_id ? ($directory[(int) $journey->manager_id]['name'] ?? null) : null,
            'buddy_id'            => $journey->buddy_id ? (int) $journey->buddy_id : null,
            'buddy_name'          => $journey->buddy_id ? ($directory[(int) $journey->buddy_id]['name'] ?? null) : null,
            'probation_start'     => optional($journey->probation_start)->toDateString(),
            'probation_end'       => optional($journey->probation_end)->toDateString(),
            'probation_label'     => $this->onbRangeLabel($journey->probation_start, $journey->probation_end),
            'extension_end'       => optional($journey->extension_end)->toDateString(),
            'confirmation_status' => $journey->confirmation_status,
            'confirmation_label'  => $this->onbStatusLabel($journey->confirmation_status),
            'confirmed_on'        => optional($journey->confirmed_on)->toDateString(),
            'completed_at'        => optional($journey->completed_at)->toDateString(),
            'task_total'          => $counts['total'],
            'task_completed'      => $counts['completed'],
            'progress_pct'        => $counts['pct'],
            'created_at'          => optional($journey->created_at)->toDateTimeString(),
        ];

        if ($detailed) {
            $row['candidate_name'] = $journey->candidate_name;
            $row['candidate_email'] = $journey->candidate_email;
            $row['candidate_phone'] = $journey->candidate_phone;
            $row['confirmation_notes'] = $journey->confirmation_notes;
            $row['notes'] = $journey->notes;
        }

        return $row;
    }

    private function presentStage(TalentOnboardingJourneyStage $stage): array
    {
        return [
            'id'           => (int) $stage->id,
            'journey_id'   => (int) $stage->journey_id,
            'stage_key'    => $stage->stage_key,
            'title'        => $stage->title,
            'start_date'   => optional($stage->start_date)->toDateString(),
            'end_date'     => optional($stage->end_date)->toDateString(),
            'date_label'   => $this->onbRangeLabel($stage->start_date, $stage->end_date),
            'status'       => $stage->status,
            'status_label' => $this->onbStatusLabel($stage->status),
            'sort_order'   => (int) $stage->sort_order,
            'completed_at' => optional($stage->completed_at)->toDateTimeString(),
            'notes'        => $stage->notes,
        ];
    }

    /** Department label for a journey whose hire has no tbluser row yet. */
    private function departmentName($departmentId): ?string
    {
        if (!$departmentId) {
            return null;
        }

        return DB::table('hrms_departments')->where('id', $departmentId)->value('department');
    }
}
