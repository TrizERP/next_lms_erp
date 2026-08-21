<?php

namespace App\Http\Controllers\api\TalentManagement\Competency\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\Concerns\ResolvesCompetencyContext`.
 *
 * The source trait resolved tenant/actor identity per-request via a Sanctum
 * personal access token (`ResolvesApiIdentity`). This project's Competency
 * Management screens (Employee Profiles, Certifications, Development & Career
 * Paths) mirror the already-ported Talent Management modules instead
 * (`ResolvesPerformanceContext`, `OnboardingApiController`): identity already
 * lives in the hydrated session (`session()->get('sub_institute_id')` /
 * `session()->get('user_id')`), populated by the `api.session` middleware, so
 * `competencyContext()` reads it directly rather than re-resolving a token.
 *
 * Every other helper (ownership/elevated-role gate, filters, activity logging,
 * change-diffing) is unchanged in behaviour from the source, so every ported
 * controller body works against this trait with no structural changes.
 *
 * IMPORTANT - the `user_id` trap. On every /api/competency/* call `user_id` in
 * the context is the CONTEXT ACTOR (whoever is calling), never the subject of
 * a write. A controller that writes an owner column takes the subject from an
 * explicit request field (e.g. `user_id_target`) or a route parameter resolved
 * through `competencySubject()`, never from the context alone.
 */
trait ResolvesCompetencyContext
{
    /**
     * Tenant + actor context for one request. Unlike the source trait this
     * never itself returns an error response - session hydration is handled
     * upstream by the `api.session` middleware - but the array return keeps
     * every call site (`if (!is_array($context)) { return $context; }`)
     * identical to the source for an as-is port.
     *
     * @return array{sub_institute_id:int, user_id:int|null}
     */
    protected function competencyContext(Request $request)
    {
        return [
            'sub_institute_id' => (int) session()->get('sub_institute_id'),
            'user_id'          => session()->get('user_id') !== null ? (int) session()->get('user_id') : null,
        ];
    }

    /**
     * Roles that may act on somebody else's competency profile.
     *
     * Keyed on role_key, the stable machine name on tbluserprofilemaster - not
     * on a substring of the display name, which renames.
     *
     * department_head and reporting_manager are DELIBERATELY ABSENT, matching
     * the source: their legitimate scope is "my department" / "my team" and
     * neither can be evaluated while tbluser.reporting_manager_id coverage is
     * incomplete. Granting them org-wide access in the meantime would be a
     * wider grant than the source ever made.
     */
    private const COMPETENCY_ELEVATED = [
        'administrator', 'hr_manager', 'hr_executive', 'executive', 'auditor',
    ];

    /**
     * Resolve the SUBJECT of a competency request - the employee whose profile
     * is being read or written - and refuse when the caller may not act on them.
     *
     * Two checks, both required:
     *   1. the subject must belong to the CALLER'S OWN tenant, so an elevated
     *      role cannot reach across organisations;
     *   2. the caller must be the subject, or hold an elevated role.
     *
     * @return int|\Illuminate\Http\JsonResponse
     */
    protected function competencySubject(array $context, $requestedId)
    {
        $subjectId = (int) $requestedId;
        $callerId  = (int) ($context['user_id'] ?? 0);

        if ($subjectId <= 0 || $callerId <= 0) {
            return response()->json(['status' => 0, 'message' => 'Employee not found.'], 404);
        }

        // The subject must exist inside the caller's own organisation. Checked
        // before the ownership rule so a cross-tenant id cannot be probed for
        // existence by an elevated caller.
        $inTenant = DB::table('tbluser')
            ->where('id', $subjectId)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->exists();

        if (!$inTenant) {
            return response()->json(['status' => 0, 'message' => 'Employee not found.'], 404);
        }

        if ($subjectId === $callerId) {
            return $subjectId;
        }

        $roleKey = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('u.id', $callerId)
            ->value('p.role_key');

        if (in_array((string) $roleKey, self::COMPETENCY_ELEVATED, true)) {
            return $subjectId;
        }

        return response()->json([
            'status'  => 0,
            'message' => 'You may only access your own competency profile.',
        ], 403);
    }

    /**
     * The five command-center filter dimensions, normalised. Empty / 'all' / '0'
     * collapse to null so callers can skip them.
     *
     * @return array{department_id:?string, jobrole:?string, location:?string, business_unit:?string, job_family:?string}
     */
    protected function competencyFilters(Request $request): array
    {
        return [
            'department_id' => $this->activeFilter($request->input('department_id')),
            'jobrole'       => $this->activeFilter($request->input('jobrole')),
            'location'      => $this->activeFilter($request->input('location')),
            'business_unit' => $this->activeFilter($request->input('business_unit')),
            'job_family'    => $this->activeFilter($request->input('job_family')),
        ];
    }

    /** Treat 'all', '0' and empty string as "no filter". */
    protected function activeFilter($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = array_values(array_filter($value, fn ($item) => $item !== null && $item !== '' && $item !== '0' && $item !== 'all'));
            return empty($value) ? null : implode(',', $value);
        }

        $value = trim((string) $value);

        return ($value === '' || $value === '0' || strtolower($value) === 'all') ? null : $value;
    }

    /**
     * Append a row to the competency activity feed. Resolves the actor's display
     * name from tbluser so the Recent Activity feed reads naturally.
     *
     * @param array<int, array{field:string, label:string, old:mixed, new:mixed}>|null $changes
     */
    protected function logCompetencyActivity(
        int $subInstituteId,
        ?int $userId,
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectName = null,
        ?array $changes = null
    ): void {
        $actorName = null;

        if ($userId) {
            $user = DB::table('tbluser')->where('id', $userId)->first();
            if ($user) {
                $actorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                $actorName = $actorName !== '' ? $actorName : ($user->user_name ?? null);
            }
        }

        DB::table('s_competency_activity_log')->insert([
            'sub_institute_id' => $subInstituteId,
            'user_id'          => $userId,
            'actor_name'       => $actorName,
            'action'           => $action,
            'description'      => $description,
            'subject_type'     => $subjectType,
            'subject_id'       => $subjectId,
            'subject_name'     => $subjectName !== null ? mb_substr($subjectName, 0, 191) : null,
            'changes'          => ($changes !== null && $changes !== []) ? json_encode(array_values($changes)) : null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /**
     * Build the field-level diff the audit centre renders as "Change Summary".
     *
     * @param  object|array<string, mixed>   $before
     * @param  array<string, mixed>          $after
     * @param  array<string, string>         $labels  column => display label
     * @return array<int, array{field:string, label:string, old:mixed, new:mixed}>
     */
    protected function diffChanges($before, array $after, array $labels): array
    {
        $before = is_object($before) ? (array) $before : $before;
        $changes = [];

        foreach ($after as $column => $newValue) {
            if (!array_key_exists($column, $labels)) {
                continue;
            }

            $oldValue = $before[$column] ?? null;

            // Loose-but-safe comparison: everything reaches the API as a string,
            // so 3 and '3' must not read as a change.
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
}
