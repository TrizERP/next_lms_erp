<?php

namespace App\Http\Controllers\api\TalentManagement\Onboarding\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Onboarding\Concerns\ResolvesOnboardingContext`.
 *
 * The source trait resolved tenant/actor identity per-request via
 * `ResolvesApiIdentity` (Sanctum token passed as the `token` query param).
 * This project's session already carries that identity — populated by the
 * `api.session` middleware — so `onboardingContext()` reads it directly via
 * `session()->get(...)` (mirrors `OnboardingApiController`, this project's
 * pattern for tenant/actor resolution) instead of re-resolving a token.
 * Every other helper (paging, sorting, filter normalization, response
 * envelopes, activity logging, the tenant directory lookup, date/label
 * formatting, journey code minting) is an as-is port of the source's logic
 * and keeps the exact same method names/signatures so the ported controller
 * bodies do not need to change, only this trait's internals.
 *
 * IMPORTANT - the `user_id` trap. On every /api/onboarding/* call the actor
 * (session user) is never the subject of a write. A controller that writes an
 * owner column must take the subject from `owner_id` / `employee_id`, never
 * from the session's `user_id` - writing the actor into an owner column
 * silently reassigns the record to whoever pressed the button.
 */
trait ResolvesOnboardingContext
{
    /** The workstream vocabulary behind the "Onboarding Integrations & Tasks" cards. */
    public const WORKSTREAMS = [
        'it'         => ['title' => 'IT Provisioning',     'icon' => 'it'],
        'learning'   => ['title' => 'Learning & Training', 'icon' => 'learning'],
        'payroll'    => ['title' => 'Payroll Setup',       'icon' => 'payroll'],
        'benefits'   => ['title' => 'Benefits Enrollment', 'icon' => 'benefits'],
        'compliance' => ['title' => 'Compliance',          'icon' => 'compliance'],
    ];

    /** Every category a task may carry - the workstreams plus the two admin buckets. */
    public const TASK_CATEGORIES = [
        'documents'  => 'Documents',
        'compliance' => 'Compliance',
        'it'         => 'IT',
        'learning'   => 'Learning',
        'payroll'    => 'Payroll',
        'benefits'   => 'Benefits',
        'personal'   => 'Personal',
    ];

    /** The seven default steps seeded onto a new journey, in order. */
    public const DEFAULT_STAGES = [
        ['stage_key' => 'offer_accepted',   'title' => 'Offer Accepted'],
        ['stage_key' => 'preboarding',      'title' => 'Preboarding'],
        ['stage_key' => 'first_day',        'title' => 'First Day'],
        ['stage_key' => 'orientation',      'title' => 'Orientation & Training'],
        ['stage_key' => 'team_integration', 'title' => 'Team Integration'],
        ['stage_key' => 'probation',        'title' => 'Probation Period'],
        ['stage_key' => 'confirmation',     'title' => 'Confirmation'],
    ];

    /**
     * Tenant + actor context for one request. Unlike the source trait this
     * never itself returns an error response - session hydration is handled
     * upstream by the `api.session` middleware - but the array return keeps
     * every call site (`if (!is_array($context)) { return $context; }`)
     * identical to the source for an as-is port.
     *
     * @return array{sub_institute_id:int, user_id:int|null}
     */
    protected function onboardingContext(Request $request)
    {
        return [
            'sub_institute_id' => (int) session()->get('sub_institute_id'),
            'user_id'          => session()->get('user_id') !== null ? (int) session()->get('user_id') : null,
        ];
    }

    /** Treat 'all', '0' and empty string as "no filter". */
    protected function activeOnbFilter($value): ?string
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

    /** page / per_page, clamped so a hostile per_page cannot dump the table. */
    protected function onboardingPaging(Request $request, int $defaultPerPage = 25): array
    {
        $page = (int) ($request->input('page') ?: 1);
        $perPage = (int) ($request->input('per_page') ?: $defaultPerPage);

        return [
            'page'     => max(1, $page),
            'per_page' => min(200, max(5, $perPage)),
        ];
    }

    /** Whitelisted sorting - never interpolate a client string into ORDER BY. */
    protected function onboardingSort(Request $request, array $allowed, string $default, string $defaultDir = 'desc'): array
    {
        $column = (string) $request->input('sort_by', $default);
        $direction = strtolower((string) $request->input('sort_dir', $defaultDir)) === 'asc' ? 'asc' : 'desc';

        return [
            in_array($column, $allowed, true) ? $column : $default,
            $direction,
        ];
    }

    /** The {status,message,data} envelope every other module in this API returns. */
    protected function onboardingResponse($data, string $message = 'Success', int $code = 200, array $extra = [])
    {
        return response()->json(array_merge([
            'status'  => 1,
            'message' => $message,
            'data'    => $data,
        ], $extra), $code);
    }

    protected function onboardingError(string $message, int $code = 400, array $extra = [])
    {
        return response()->json(array_merge([
            'status'  => 0,
            'message' => $message,
        ], $extra), $code);
    }

    /**
     * Append a row to the onboarding activity feed (Lifecycle Timeline + audit).
     *
     * @param array<int, array{field:string, label:string, old:mixed, new:mixed}>|null $changes
     */
    protected function logOnboardingActivity(
        int $subInstituteId,
        ?int $actorId,
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectName = null,
        ?array $changes = null,
        ?int $journeyId = null
    ): void {
        DB::table('talent_onboarding_activity_log')->insert([
            'sub_institute_id' => $subInstituteId,
            'user_id'          => $actorId,
            'actor_name'       => $this->resolveOnbActorName($actorId),
            'action'           => $action,
            'description'      => $description,
            'subject_type'     => $subjectType,
            'subject_id'       => $subjectId,
            'subject_name'     => $subjectName !== null ? mb_substr($subjectName, 0, 191) : null,
            'changes'          => ($changes !== null && $changes !== []) ? json_encode(array_values($changes)) : null,
            'journey_id'       => $journeyId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /** Display name for the feed's actor column, from the single user master. */
    protected function resolveOnbActorName(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $user = DB::table('tbluser')->where('id', $userId)->first(['first_name', 'last_name', 'user_name']);

        if (!$user) {
            return null;
        }

        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : ($user->user_name ?? null);
    }

    /**
     * Field-level diff for the audit trail. Only columns present in $after AND
     * actually different are returned.
     *
     * @param  object|array<string, mixed> $before
     * @param  array<string, mixed>        $after
     * @param  array<string, string>       $labels column => display label
     * @return array<int, array{field:string, label:string, old:mixed, new:mixed}>
     */
    protected function diffOnboardingChanges($before, array $after, array $labels): array
    {
        $before = is_object($before) ? (array) $before : $before;
        $changes = [];

        foreach ($after as $column => $newValue) {
            if (!array_key_exists($column, $labels)) {
                continue;
            }

            $oldValue = $before[$column] ?? null;

            // Loose-but-safe: everything arrives as a string, so 3 vs '3' is not a change.
            if ((string) ($oldValue ?? '') === (string) ($newValue ?? '')) {
                continue;
            }

            $changes[] = [
                'field' => $column,
                'label' => $labels[$column],
                'old'   => $oldValue,
                'new'   => $newValue,
            ];
        }

        return $changes;
    }

    /* ------------------------------------------------------------------ *
     * Shared lookups and display helpers
     * ------------------------------------------------------------------ */

    /**
     * Employee display bundle for the profile card, owner column and contacts,
     * resolved from tbluser + hrms_departments + org_designation.
     *
     * @param  array<int, int> $userIds
     * @return array<int, array{id:int, name:string, employee_no:?string, email:?string, mobile:?string, initials:string, department_id:?int, department:?string, designation:?string, joined_date:?string, image:?string}>
     */
    protected function onboardingDirectory(int $subInstituteId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if (empty($userIds)) {
            return [];
        }

        $users = DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('id', $userIds)
            ->get([
                'id', 'first_name', 'last_name', 'user_name', 'employee_no', 'email', 'mobile',
                'department_id', 'joined_date', 'image', 'city',
                'probation_period_from', 'probation_period_to',
            ]);

        $departmentIds = $users->pluck('department_id')->filter()->unique()->values()->all();

        $departments = empty($departmentIds)
            ? collect()
            : DB::table('hrms_departments')->whereIn('id', $departmentIds)->pluck('department', 'id');

        $designations = DB::table('org_designation')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('user_id', $userIds)
            ->pluck('designation', 'user_id');

        $directory = [];

        foreach ($users as $user) {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $name = $name !== '' ? $name : ($user->user_name ?? 'Unknown');

            $directory[(int) $user->id] = [
                'id'                    => (int) $user->id,
                'name'                  => $name,
                'employee_no'           => $user->employee_no,
                'email'                 => $user->email,
                'mobile'                => $user->mobile,
                'initials'              => $this->onbInitialsOf($name),
                'department_id'         => $user->department_id ? (int) $user->department_id : null,
                'department'            => $user->department_id ? ($departments[$user->department_id] ?? null) : null,
                'designation'           => $designations[$user->id] ?? null,
                'joined_date'           => $user->joined_date,
                'location'              => $user->city,
                'image'                 => $user->image,
                'probation_period_from' => $user->probation_period_from,
                'probation_period_to'   => $user->probation_period_to,
            ];
        }

        return $directory;
    }

    protected function onbInitialsOf(?string $name): string
    {
        if (!$name) {
            return '--';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first . $last) ?: '--';
    }

    /**
     * The hire's display identity for a journey row.
     *
     * A journey may point at a tbluser (post joining) or only carry candidate_*
     * details (preboarding, before the employee record exists), so both paths
     * have to produce the same shape for the table and the sidebar.
     *
     * @param  array<int, array<string, mixed>> $directory
     * @return array{name:string, initials:string, email:?string, employee_no:?string, image:?string}
     */
    protected function journeyIdentity($journey, array $directory): array
    {
        // Callers pass either a full Eloquent model or a partially-selected
        // stdClass row (the filters endpoint only needs a handful of columns), so
        // every candidate_* read below must tolerate an absent property.
        $employee = ($journey->employee_id ?? null) ? ($directory[(int) $journey->employee_id] ?? null) : null;

        if ($employee) {
            return [
                'name'        => $employee['name'],
                'initials'    => $employee['initials'],
                'email'       => $employee['email'],
                'employee_no' => $employee['employee_no'],
                'image'       => $employee['image'],
            ];
        }

        $name = ($journey->candidate_name ?? null) ?: 'Unnamed hire';

        return [
            'name'        => $name,
            'initials'    => $this->onbInitialsOf($name),
            'email'       => $journey->candidate_email ?? null,
            'employee_no' => null,
            'image'       => null,
        ];
    }

    /** "15 May 2025" - the date format every column and card on the screen uses. */
    protected function onbDateLabel($date): ?string
    {
        if (!$date) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** "05 May 2025, 11:30 AM" - the activity feed format. */
    protected function onbDateTimeLabel($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d M Y, h:i A');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** "02 May - 15 May 2025" - the journey timeline's step subtitle. */
    protected function onbRangeLabel($start, $end): ?string
    {
        $startLabel = $this->onbDateLabel($start);
        $endLabel = $this->onbDateLabel($end);

        if ($startLabel && $endLabel) {
            if ($startLabel === $endLabel) {
                return $startLabel;
            }

            // A back-dated offer can leave a stage whose stored start is later
            // than its end; render the span in order rather than reversed.
            return strtotime((string) $start) > strtotime((string) $end)
                ? $endLabel . ' - ' . $startLabel
                : $startLabel . ' - ' . $endLabel;
        }

        return $startLabel ?: $endLabel;
    }

    /** Human label for the raw status / stage enums the screen renders. */
    protected function onbStatusLabel(?string $status): ?string
    {
        return match ($status) {
            'not-started'  => 'Not Started',
            'in-progress'  => 'In Progress',
            'in_progress'  => 'In Progress',
            'on-hold'      => 'On Hold',
            'pending'      => 'Pending',
            'sent'         => 'Sent',
            'received'     => 'Received',
            'verified'     => 'Verified',
            'rejected'     => 'Rejected',
            'blocked'      => 'Blocked',
            'completed'    => 'Completed',
            'cancelled'    => 'Cancelled',
            'skipped'      => 'Skipped',
            'confirmed'    => 'Confirmed',
            'extended'     => 'Extended',
            'terminated'   => 'Terminated',
            default        => $status ? ucwords(str_replace(['_', '-'], ' ', $status)) : null,
        };
    }

    protected function onbStageLabel(?string $stage): ?string
    {
        return match ($stage) {
            'offer_accepted'   => 'Offer Accepted',
            'preboarding'      => 'Preboarding',
            'first_day'        => 'First Day',
            'orientation'      => 'Orientation & Training',
            'team_integration' => 'Team Integration',
            'probation'        => 'Probation Period',
            'confirmation'     => 'Confirmation',
            'confirmed'        => 'Confirmed',
            'exited'           => 'Exited',
            default            => $stage ? ucwords(str_replace('_', ' ', $stage)) : null,
        };
    }

    protected function onbCategoryLabel(?string $category): ?string
    {
        if (!$category) {
            return null;
        }

        return self::TASK_CATEGORIES[$category] ?? ucwords(str_replace('_', ' ', $category));
    }

    /**
     * Completed-task percentage for a set of journeys, in one grouped query.
     *
     * @param  array<int, int> $journeyIds
     * @return array<int, array{total:int, completed:int, pct:int}>
     */
    protected function taskProgressFor(int $subInstituteId, array $journeyIds): array
    {
        $journeyIds = array_values(array_unique(array_filter($journeyIds)));

        if (empty($journeyIds)) {
            return [];
        }

        $rows = DB::table('talent_onboarding_tasks')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->whereIn('journey_id', $journeyIds)
            ->select(
                'journey_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(status = "completed") as completed')
            )
            ->groupBy('journey_id')
            ->get();

        $progress = [];

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $completed = (int) $row->completed;

            $progress[(int) $row->journey_id] = [
                'total'     => $total,
                'completed' => $completed,
                'pct'       => $total > 0 ? (int) round($completed / $total * 100) : 0,
            ];
        }

        return $progress;
    }

    /**
     * Next unique journey code for the tenant (OHB-2025-0001).
     *
     * Derived from the highest existing sequence rather than a row count, so a
     * deleted journey can never hand its code to a new one.
     */
    protected function nextJourneyCode(int $subInstituteId): string
    {
        $year = now()->year;
        $prefix = 'OHB-' . $year . '-';

        $last = DB::table('talent_onboarding_journeys')
            ->where('sub_institute_id', $subInstituteId)
            ->where('journey_code', 'like', $prefix . '%')
            ->orderByDesc('journey_code')
            ->value('journey_code');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Locate a journey inside the tenant, or return the 404 envelope.
     *
     * @return \App\Models\TalentManagement\TalentOnboardingJourney|\Illuminate\Http\JsonResponse
     */
    protected function findJourney(int $subInstituteId, $journeyId)
    {
        $journey = \App\Models\TalentManagement\TalentOnboardingJourney::where('sub_institute_id', $subInstituteId)
            ->find($journeyId);

        return $journey ?: $this->onboardingError('Onboarding journey not found', 404);
    }
}
