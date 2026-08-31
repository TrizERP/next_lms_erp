<?php

namespace App\Http\Controllers\api\TalentManagement\Performance\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\Concerns\ResolvesPerformanceContext`.
 *
 * The source trait resolved tenant/actor identity per-request via a Sanctum
 * token (`ResolvesApiIdentity`). This project's Performance & Rewards Center
 * mirrors `OnboardingApiController` / the already-ported Offboarding Center
 * instead: identity already lives in the hydrated session
 * (`session()->get('sub_institute_id')` / `session()->get('user_id')`),
 * populated by the `api.session` middleware, so `performanceContext()` reads
 * it directly rather than re-resolving a token. Every other helper (filters,
 * paging, sorting, response envelope, activity logging, employee directory,
 * label formatting) is unchanged from the source so every ported controller
 * body works against this trait with no structural changes.
 *
 * IMPORTANT - the `user_id` trap. On every /api/performance/* call `user_id`
 * is the CONTEXT ACTOR (whoever pressed the button), never the subject. A
 * controller that writes an owner column must take the subject from an
 * explicit request field (e.g. `user_id_target`), never from the context.
 */
trait ResolvesPerformanceContext
{
    /**
     * Tenant + actor context for one request. Unlike the source trait this
     * never itself returns an error response — session hydration is handled
     * upstream by the `api.session` middleware — but the array return keeps
     * every call site (`if (!is_array($context)) { return $context; }`)
     * identical to the source for an as-is port.
     *
     * @return array{sub_institute_id:int, user_id:int|null}
     */
    protected function performanceContext(Request $request)
    {
        return [
            'sub_institute_id' => (int) session()->get('sub_institute_id'),
            'user_id'          => session()->get('user_id') !== null ? (int) session()->get('user_id') : null,
        ];
    }

    /**
     * The screen's shared filter bar: cycle, department, manager, status, stage
     * and the employee search box, plus the More-Filters sheet dimensions.
     *
     * @return array<string, string|null>
     */
    protected function performanceFilters(Request $request): array
    {
        return [
            'cycle_id'       => $this->activePerfFilter($request->input('cycle_id')),
            'department_id'  => $this->activePerfFilter($request->input('department_id')),
            'manager_id'     => $this->activePerfFilter($request->input('manager_id')),
            'status'         => $this->activePerfFilter($request->input('status')),
            'stage'          => $this->activePerfFilter($request->input('stage')),
            'search'         => $this->activePerfFilter($request->input('search')),
            'user_id_filter' => $this->activePerfFilter($request->input('user_id_filter')),
            // More Filters sheet
            'rating_min'     => $this->activePerfFilter($request->input('rating_min')),
            'rating_max'     => $this->activePerfFilter($request->input('rating_max')),
            'overdue_only'   => $this->activePerfFilter($request->input('overdue_only')),
            'jobrole'        => $this->activePerfFilter($request->input('jobrole')),
            'recommendation' => $this->activePerfFilter($request->input('recommendation')),
            'category'       => $this->activePerfFilter($request->input('category')),
            'bonus_type'     => $this->activePerfFilter($request->input('bonus_type')),
            'revision_type'  => $this->activePerfFilter($request->input('revision_type')),
            'payout_month'   => $this->activePerfFilter($request->input('payout_month')),
        ];
    }

    /** Treat 'all', '0' and empty string as "no filter". */
    protected function activePerfFilter($value): ?string
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
    protected function performancePaging(Request $request, int $defaultPerPage = 25): array
    {
        $page = (int) ($request->input('page') ?: 1);
        $perPage = (int) ($request->input('per_page') ?: $defaultPerPage);

        return [
            'page'     => max(1, $page),
            'per_page' => min(200, max(5, $perPage)),
        ];
    }

    /** Whitelisted sorting - never interpolate a client string into ORDER BY. */
    protected function performanceSort(Request $request, array $allowed, string $default, string $defaultDir = 'desc'): array
    {
        $column = (string) $request->input('sort_by', $default);
        $direction = strtolower((string) $request->input('sort_dir', $defaultDir)) === 'asc' ? 'asc' : 'desc';

        return [
            in_array($column, $allowed, true) ? $column : $default,
            $direction,
        ];
    }

    /** The {status,message,data} envelope every other module in this API returns. */
    protected function performanceResponse($data, string $message = 'Success', int $code = 200, array $extra = [])
    {
        return response()->json(array_merge([
            'status'  => 1,
            'message' => $message,
            'data'    => $data,
        ], $extra), $code);
    }

    protected function performanceError(string $message, int $code = 400, array $extra = [])
    {
        return response()->json(array_merge([
            'status'  => 0,
            'message' => $message,
        ], $extra), $code);
    }

    /**
     * Append a row to the Performance activity feed (Activity Feed + Audit Trail).
     *
     * $changes is the field-level diff the Audit Trail renders as its change
     * summary; $reviewId scopes the feed to one employee review so the screen's
     * Activity Feed panel can show just that row's history.
     *
     * @param array<int, array{field:string, label:string, old:mixed, new:mixed}>|null $changes
     */
    protected function logPerformanceActivity(
        int $subInstituteId,
        ?int $actorId,
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectName = null,
        ?array $changes = null,
        ?int $reviewId = null,
        ?int $cycleId = null
    ): void {
        DB::table('s_performance_activity_log')->insert([
            'sub_institute_id' => $subInstituteId,
            'user_id'          => $actorId,
            'actor_name'       => $this->resolveActorName($actorId),
            'action'           => $action,
            'description'      => $description,
            'subject_type'     => $subjectType,
            'subject_id'       => $subjectId,
            'subject_name'     => $subjectName !== null ? mb_substr($subjectName, 0, 191) : null,
            'changes'          => ($changes !== null && $changes !== []) ? json_encode(array_values($changes)) : null,
            'review_id'        => $reviewId,
            'cycle_id'         => $cycleId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /** Display name for the feed's actor column, from the single user master. */
    protected function resolveActorName(?int $userId): ?string
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
     * Build the field-level diff the Audit Trail renders as "Change Summary".
     * Only columns present in $after AND actually different are returned.
     *
     * @param  object|array<string, mixed> $before
     * @param  array<string, mixed>        $after
     * @param  array<string, string>       $labels column => display label
     * @return array<int, array{field:string, label:string, old:mixed, new:mixed}>
     */
    protected function diffPerformanceChanges($before, array $after, array $labels): array
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
     * Employee display bundle for every table row and the sidebar, resolved from
     * the single user master (tbluser) + department master (hrms_departments).
     *
     * @param  array<int, int> $userIds
     * @return array<int, array{id:int, name:string, employee_no:?string, initials:string, department_id:?int, department:?string, designation:?string, joined_date:?string, image:?string}>
     */
    protected function employeeDirectory(int $subInstituteId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if (empty($userIds)) {
            return [];
        }

        $users = DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId)
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'last_name', 'user_name', 'employee_no', 'department_id', 'joined_date', 'image', 'jobtitle_id']);

        $departmentIds = $users->pluck('department_id')->filter()->unique()->values()->all();

        $departments = empty($departmentIds)
            ? collect()
            : DB::table('hrms_departments')->whereIn('id', $departmentIds)->pluck('department', 'id');

        // No standalone `org_designation` table on this target - designation
        // is resolved via `tbluser.jobtitle_id` -> `s_user_jobrole.jobrole`,
        // matching every other ported Talent Management controller.
        $jobroleIds = $users->pluck('jobtitle_id')->filter()->unique()->values()->all();

        $designationsByJobrole = empty($jobroleIds)
            ? collect()
            : DB::table('s_user_jobrole')->whereIn('id', $jobroleIds)->whereNull('deleted_at')->pluck('jobrole', 'id');

        $designations = $users->mapWithKeys(
            fn ($user) => [(int) $user->id => $user->jobtitle_id ? ($designationsByJobrole[$user->jobtitle_id] ?? null) : null]
        );

        $directory = [];

        foreach ($users as $user) {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $name = $name !== '' ? $name : ($user->user_name ?? 'Unknown');

            $directory[(int) $user->id] = [
                'id'            => (int) $user->id,
                'name'          => $name,
                'employee_no'   => $user->employee_no,
                'initials'      => $this->initialsOf($name),
                'department_id' => $user->department_id ? (int) $user->department_id : null,
                'department'    => $user->department_id ? ($departments[$user->department_id] ?? null) : null,
                'designation'   => $designations[$user->id] ?? null,
                'joined_date'   => $user->joined_date,
                'image'         => $user->image,
            ];
        }

        return $directory;
    }

    protected function initialsOf(?string $name): string
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
     * "4 - Exceeds" - the label the table's Overall Rating column renders.
     * Bands follow the 5-point scale the cycle's rating_scale_max defaults to.
     */
    protected function ratingLabel(?float $rating): ?string
    {
        if ($rating === null) {
            return null;
        }

        $rounded = (int) round($rating);

        $bands = [
            5 => 'Outstanding',
            4 => 'Exceeds',
            3 => 'Meets',
            2 => 'Needs Improvement',
            1 => 'Below Expectations',
        ];

        $rounded = max(1, min(5, $rounded));

        // Show one decimal only when the rating is not a whole number.
        $display = floor($rating) == $rating ? (string) (int) $rating : number_format($rating, 1);

        return $display . ' - ' . $bands[$rounded];
    }

    /** "15 May 2025" - the date format every column and card on the screen uses. */
    protected function dateLabel($date): ?string
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

    /** "05 May 2025, 11:30 AM" - the Last Updated / activity feed format. */
    protected function dateTimeLabel($value): ?string
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

    /** Human label for the raw stage / status enums stored on a review. */
    protected function stageLabel(?string $stage): ?string
    {
        return match ($stage) {
            'self_review'    => 'Self Review',
            'manager_review' => 'Manager Review',
            'calibration'    => 'Calibration',
            'final_review'   => 'Final Review',
            'completed'      => 'Completed',
            default          => $stage ? ucwords(str_replace('_', ' ', $stage)) : null,
        };
    }

    protected function statusLabel(?string $status): ?string
    {
        return match ($status) {
            'pending'          => 'Pending',
            'in_progress'      => 'In Progress',
            'completed'        => 'Completed',
            'draft'            => 'Draft',
            'pending_approval' => 'Pending Approval',
            'approved'         => 'Approved',
            'rejected'         => 'Rejected',
            'paid'             => 'Paid',
            default            => $status ? ucwords(str_replace('_', ' ', $status)) : null,
        };
    }
}
