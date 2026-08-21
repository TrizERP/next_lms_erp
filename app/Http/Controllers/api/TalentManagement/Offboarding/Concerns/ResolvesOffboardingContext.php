<?php

namespace App\Http\Controllers\api\TalentManagement\Offboarding\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Offboarding\Concerns\ResolvesOffboardingContext`.
 *
 * The source trait resolved tenant/actor identity per-request via
 * `ResolvesApiIdentity` (token-based). This project's Offboarding Center
 * mirrors `OnboardingApiController` instead: identity already lives in the
 * hydrated session (`session()->get('sub_institute_id')` /
 * `session()->get('user_id')`), populated by the `api.session` middleware, so
 * `offboardingContext()` reads it directly rather than re-resolving a token.
 * Every other helper (paging, sorting, filter normalization, the tenant
 * directory lookup, response envelopes) is unchanged from the source.
 */
trait ResolvesOffboardingContext
{
    /**
     * Tenant + actor context for one request. Unlike the source trait this
     * never itself returns an error response — session hydration is handled
     * upstream by the `api.session` middleware — but the array return keeps
     * every call site (`if (!is_array($context)) { return $context; }`)
     * identical to the source for an as-is port.
     */
    protected function offboardingContext(Request $request)
    {
        return [
            'sub_institute_id' => (int) session()->get('sub_institute_id'),
            'user_id' => session()->get('user_id') !== null ? (int) session()->get('user_id') : null,
        ];
    }

    protected function activeOffbFilter($value): ?string
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

    protected function offboardingPaging(Request $request, int $defaultPerPage = 25): array
    {
        $page = (int) ($request->input('page') ?: 1);
        $perPage = (int) ($request->input('per_page') ?: $defaultPerPage);

        return [
            'page'     => max(1, $page),
            'per_page' => min(200, max(5, $perPage)),
        ];
    }

    protected function offboardingSort(Request $request, array $allowed, string $default, string $defaultDir = 'desc'): array
    {
        $column = (string) $request->input('sort_by', $default);
        $direction = strtolower((string) $request->input('sort_dir', $defaultDir)) === 'asc' ? 'asc' : 'desc';

        return [
            in_array($column, $allowed, true) ? $column : $default,
            $direction,
        ];
    }

    protected function offboardingResponse($data, string $message = 'Success', int $code = 200, array $extra = [])
    {
        return response()->json(array_merge([
            'status'  => 1,
            'message' => $message,
            'data'    => $data,
        ], $extra), $code);
    }

    protected function offboardingError(string $message, int $code = 400, array $extra = [])
    {
        return response()->json(array_merge([
            'status'  => 0,
            'message' => $message,
        ], $extra), $code);
    }

    protected function offboardingDirectory(int $subInstituteId, array $userIds): array
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
                'department_id', 'joined_date', 'image', 'city', 'jobtitle_id'
            ]);

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
                'email'         => $user->email,
                'mobile'        => $user->mobile,
                'initials'      => $this->offbInitialsOf($name),
                'department_id' => $user->department_id ? (int) $user->department_id : null,
                'department'    => $user->department_id ? ($departments[$user->department_id] ?? null) : null,
                'designation'   => $designations[$user->id] ?? null,
                'joined_date'   => $user->joined_date,
                'location'      => $user->city,
                'image'         => $user->image,
            ];
        }

        return $directory;
    }

    protected function offbInitialsOf(?string $name): string
    {
        if (!$name) return '??';
        $parts = array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($name))));
        if (empty($parts)) return '??';
        if (count($parts) === 1) return strtoupper(substr($parts[0], 0, 2));
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }
}
