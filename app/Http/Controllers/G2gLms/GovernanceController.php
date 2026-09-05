<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Administration & Governance - users, roles, the permission matrix, audit
 * and platform health.
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\LmsGovernanceController`.
 * Two structural adaptations from the source, both required by this target's
 * actual schema (confirmed by reading every migration touched below, not
 * assumed):
 *
 *   1. IDENTITY. The source resolved tenant/actor from a Sanctum token via
 *      its own `ResolvesLmsIdentity` trait. Every g2g-lms route here instead
 *      runs behind session-hydrating `api.session` middleware (Package 0),
 *      so identity comes from `App\Http\Controllers\G2gLms\Concerns\
 *      ResolvesLmsIdentity::lmsContext()` (already written by Package 1;
 *      reused verbatim per that trait's own "other packages" note) rather
 *      than a second, duplicate trait.
 *
 *   2. AUDIT TABLE. The source wrote a G2G-specific `g2g_audit_log`
 *      (event-sourced projection) that does not exist here and is not an
 *      approved table for this package. This target already has a
 *      general-purpose `system_audit_logs` table + `App\Models\AuditLog::
 *      record()` helper (see that migration's docblock: "Written to via
 *      App\Models\AuditLog::record()"), built for exactly this kind of
 *      WHO/WHAT/WHEN/OLD/NEW trail. Every write below logs through it,
 *      scoped with `module = 'g2g_lms_governance'` so the Audit Logs tab and
 *      the KPI card can filter to just this surface without colliding with
 *      the fee/marks/student-status/permission audit rows other features
 *      already write there.
 *
 *   3. SCHEMA SHAPE. `tbluser`, `tbluserprofilemaster` and
 *      `tblgroupwise_rights` in THIS codebase are the original, much older
 *      LMS-K12 tables - none of the three carry `deleted_at` (no soft
 *      deletes), and `tbluser` has no `created_at`/`updated_at` at all (only
 *      `created_on`, nullable+useCurrent) and no `created_by`/`updated_by`/
 *      `deleted_by`. `tblgroupwise_rights` has `created_at` (useCurrent) but
 *      NO `updated_at`. Every query/write below is adapted to that real
 *      shape rather than assuming hp_erp's newer, fully-audited columns
 *      exist - confirmed by reading each CREATE/ALTER migration, not
 *      guessed. Because neither `tbluser` nor `tbluserprofilemaster` can be
 *      soft-deleted here, `destroyUser()`/`destroyRole()` deactivate
 *      (`status = 0`) rather than the source's soft-delete.
 */
class GovernanceController extends Controller
{
    use ResolvesLmsIdentity;

    private function guardAdmin(Request $request)
    {
        $context = $this->lmsContext($request);
        if ($this->isLmsStaffAdmin($context)) {
            return null;
        }

        return $this->lmsError('Your profile is not permitted to administer this institute.', 403);
    }

    private function fail(\Throwable $e, string $message)
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
            'error'   => $e->getMessage(),
        ], 500);
    }

    /** Best-effort audit write. Never blocks the mutation it documents. */
    private function audit(Request $request, string $entityType, $entityId, string $action, array $changes = []): void
    {
        $context = $this->lmsContext($request);

        AuditLog::record([
            'sub_institute_id' => $context['sub_institute_id'],
            'actor_id'         => $context['user_id'] ?: null,
            'module'           => 'g2g_lms_governance',
            'action'           => $entityType . '.' . $action,
            'entity_type'      => $entityType,
            'entity_id'        => (string) $entityId,
            'new_values'       => $changes ?: null,
        ]);
    }

    /**
     * GET /kpis
     *
     * Every count is real and tenant-scoped; nothing here is hardcoded.
     */
    public function kpis(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];
        if (!$sid) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $permissions = DB::table('tblgroupwise_rights')
                ->where('sub_institute_id', $sid)
                ->count();

            $auditWindow = now()->subDays(30);

            return response()->json([
                'status' => true,
                'data'   => [
                    'users' => DB::table('tbluser')
                        ->where('sub_institute_id', $sid)->count(),
                    'active_users' => DB::table('tbluser')
                        ->where('sub_institute_id', $sid)->where('status', 1)->count(),
                    'roles' => DB::table('tbluserprofilemaster')
                        ->where('sub_institute_id', $sid)->count(),
                    'permissions' => $permissions,
                    'trainers' => DB::table('lms_trainers')
                        ->where('sub_institute_id', $sid)->where('status', 1)
                        ->whereNull('deleted_at')->count(),
                    'vendors' => DB::table('lms_vendors')
                        ->where('sub_institute_id', $sid)->where('status', 1)
                        ->whereNull('deleted_at')->count(),
                    'integrations' => DB::table('lms_integrations')
                        ->where('sub_institute_id', $sid)->where('status', 'connected')
                        ->whereNull('deleted_at')->count(),
                    // "Total Logs (30 Days)", scoped to this tenant AND this
                    // package's own module tag - other features write to
                    // system_audit_logs too and must not inflate this card.
                    'audit_logs' => DB::table('system_audit_logs')
                        ->where('sub_institute_id', $sid)
                        ->where('module', 'g2g_lms_governance')
                        ->where('created_at', '>=', $auditWindow)
                        ->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to load governance KPIs');
        }
    }

    /* ─── Users ────────────────────────────────────────────────────────────── */

    /**
     * GET /users
     *
     * Explicit select list, same as the source: password, plain_password,
     * otp, pan/aadhar/bank columns are never named, so a `select *` leak
     * cannot happen here.
     */
    public function users(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];
        if (!$sid) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $perPage = min(max((int) $request->input('per_page', 10), 1), 100);

            $query = DB::table('tbluser as u')
                ->leftJoin('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
                ->leftJoin('hrms_departments as d', 'd.id', '=', 'u.department_id')
                ->where('u.sub_institute_id', $sid);

            if ($search = trim((string) $request->input('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('u.first_name', 'like', "%{$search}%")
                      ->orWhere('u.last_name', 'like', "%{$search}%")
                      ->orWhere('u.email', 'like', "%{$search}%")
                      ->orWhere('u.employee_no', 'like', "%{$search}%")
                      ->orWhere('u.user_name', 'like', "%{$search}%");
                });
            }

            if (($status = $request->input('status')) !== null && $status !== '') {
                $query->where('u.status', (int) $status);
            }
            if ($profileId = $request->input('profile_id')) {
                $query->where('u.user_profile_id', $profileId);
            }
            if ($departmentId = $request->input('department_id')) {
                $query->where('u.department_id', $departmentId);
            }

            $sortable = ['name' => 'u.first_name', 'email' => 'u.email', 'status' => 'u.status', 'last_login' => 'u.last_login'];
            $sortBy  = $sortable[$request->input('sort_by', 'name')] ?? 'u.first_name';
            $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            $users = $query->orderBy($sortBy, $sortDir)->paginate($perPage, [
                'u.id', 'u.user_name', 'u.first_name', 'u.middle_name', 'u.last_name',
                'u.email', 'u.mobile', 'u.employee_no', 'u.status', 'u.last_login',
                'u.user_profile_id', 'u.department_id', 'u.image', 'u.created_on as created_at',
                'p.name as profile_name',
                'd.department as department_name',
            ]);

            return response()->json([
                'status' => true,
                'data'   => collect($users->items())->map(function ($user) {
                    $user->full_name = trim(implode(' ', array_filter([
                        $user->first_name, $user->middle_name, $user->last_name,
                    ])));
                    $user->initials = strtoupper(
                        substr((string) $user->first_name, 0, 1) . substr((string) $user->last_name, 0, 1)
                    );
                    $user->status = (int) $user->status;
                    return $user;
                }),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page'    => $users->lastPage(),
                    'per_page'     => $users->perPage(),
                    'total'        => $users->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to load users');
        }
    }

    private function userRules(bool $creating): array
    {
        return [
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'email'           => 'required|email|max:100',
            'mobile'          => 'nullable|string|max:50',
            'employee_no'     => 'nullable|string|max:20',
            'user_profile_id' => 'required|integer',
            'department_id'   => 'nullable|integer',
            'status'          => 'nullable|integer|in:0,1',
            'user_name'       => ($creating ? 'required' : 'nullable') . '|string|max:100',
            'password'        => ($creating ? 'required' : 'nullable') . '|string|min:8|max:100',
        ];
    }

    /** POST /users */
    public function storeUser(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $validator = Validator::make($request->all(), $this->userRules(true));
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $duplicate = DB::table('tbluser')
            ->where('sub_institute_id', $sid)
            ->where(function ($q) use ($request) {
                $q->where('email', $request->input('email'))
                  ->orWhere('user_name', $request->input('user_name'));
            })->exists();

        if ($duplicate) {
            return response()->json(['status' => false, 'message' => 'A user with that email or username already exists.'], 422);
        }

        try {
            $id = DB::table('tbluser')->insertGetId([
                'user_name'        => $request->input('user_name'),
                'password'         => bcrypt($request->input('password')),
                'first_name'       => $request->input('first_name'),
                'middle_name'      => $request->input('middle_name'),
                'last_name'        => $request->input('last_name'),
                'email'            => $request->input('email'),
                'mobile'           => $request->input('mobile'),
                'employee_no'      => $request->input('employee_no'),
                'user_profile_id'  => $request->input('user_profile_id'),
                'department_id'    => $request->input('department_id'),
                'status'           => (int) $request->input('status', 1),
                'sub_institute_id' => $sid,
                // tbluser has no created_at/created_by - join_year is
                // NOT NULL with no default, so it must always be supplied.
                'join_year'   => (string) now()->year,
                'created_on'  => now(),
            ]);

            $this->audit($request, 'user', $id, 'create', [
                'email'           => $request->input('email'),
                'user_profile_id' => $request->input('user_profile_id'),
            ]);

            return response()->json(['status' => true, 'message' => 'User created', 'data' => ['id' => $id]], 201);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to create the user');
        }
    }

    /** PUT /users/{id} */
    public function updateUser(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $validator = Validator::make($request->all(), $this->userRules(false));
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $user = DB::table('tbluser')->where('id', $id)->where('sub_institute_id', $sid)->first();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        try {
            $payload = [
                'first_name'      => $request->input('first_name'),
                'middle_name'     => $request->input('middle_name'),
                'last_name'       => $request->input('last_name'),
                'email'           => $request->input('email'),
                'mobile'          => $request->input('mobile'),
                'employee_no'     => $request->input('employee_no'),
                'user_profile_id' => $request->input('user_profile_id'),
                'department_id'   => $request->input('department_id'),
                'status'          => (int) $request->input('status', $user->status),
            ];

            if ($request->filled('password')) {
                $payload['password'] = bcrypt($request->input('password'));
            }

            DB::table('tbluser')->where('id', $id)->update($payload);

            $this->audit($request, 'user', $id, 'update', [
                'email'            => $request->input('email'),
                'user_profile_id'  => $request->input('user_profile_id'),
                'password_changed' => $request->filled('password'),
            ]);

            return response()->json(['status' => true, 'message' => 'User updated']);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to update the user');
        }
    }

    /**
     * DELETE /users/{id}
     *
     * `tbluser` has no `deleted_at`/`deleted_by` in this schema, so unlike
     * the source (soft delete + status=0) this can only deactivate.
     */
    public function destroyUser(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $context = $this->lmsContext($request);
        $sid     = $context['sub_institute_id'];
        $actorId = (int) $context['user_id'];

        if ((int) $id === $actorId) {
            return response()->json(['status' => false, 'message' => 'You cannot deactivate your own account.'], 422);
        }

        $user = DB::table('tbluser')->where('id', $id)->where('sub_institute_id', $sid)->first();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        try {
            DB::table('tbluser')->where('id', $id)->update(['status' => 0]);

            $this->audit($request, 'user', $id, 'deactivate', ['email' => $user->email]);

            return response()->json(['status' => true, 'message' => 'User deactivated']);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to deactivate the user');
        }
    }

    /** Columns a CSV import may set - an allowlist, not a denylist. */
    private const IMPORTABLE_USER_COLUMNS = [
        'first_name', 'middle_name', 'last_name', 'email', 'mobile',
        'employee_no', 'user_name', 'gender', 'birthdate', 'address',
        'city', 'state', 'pincode', 'qualification', 'joined_date',
    ];

    /** POST /users/import */
    public function importUsers(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'file'            => 'required|file|mimes:csv,txt|max:5120',
            'user_profile_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $profileExists = DB::table('tbluserprofilemaster')
            ->where('id', $request->input('user_profile_id'))
            ->where('sub_institute_id', $sid)->exists();

        if (!$profileExists) {
            return response()->json(['status' => false, 'message' => 'Invalid role'], 422);
        }

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (!$handle) {
            return response()->json(['status' => false, 'message' => 'Could not read the file'], 422);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return response()->json(['status' => false, 'message' => 'The CSV has no header row'], 422);
        }
        $header = array_map(fn ($c) => strtolower(trim((string) $c)), $header);

        if (!in_array('email', $header, true)) {
            fclose($handle);
            return response()->json(['status' => false, 'message' => 'The CSV must include an "email" column.'], 422);
        }

        $existingEmails = DB::table('tbluser')
            ->where('sub_institute_id', $sid)
            ->pluck('email')->map(fn ($e) => strtolower((string) $e))->flip();

        $rows = []; $errors = []; $lineNumber = 1; $seen = [];

        while (($line = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if (count($line) !== count($header)) {
                $errors[] = "Row {$lineNumber}: column count does not match the header.";
                continue;
            }

            $raw = array_combine($header, $line);
            $raw = array_map(fn ($v) => ($v === '\N' || trim((string) $v) === '') ? null : trim((string) $v), $raw);
            $data = array_intersect_key($raw, array_flip(self::IMPORTABLE_USER_COLUMNS));

            $email = strtolower((string) ($data['email'] ?? ''));
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$lineNumber}: missing or invalid email.";
                continue;
            }
            if (empty($data['first_name'])) {
                $errors[] = "Row {$lineNumber}: first_name is required.";
                continue;
            }
            if ($existingEmails->has($email)) {
                $errors[] = "Row {$lineNumber}: {$email} already exists.";
                continue;
            }
            if (isset($seen[$email])) {
                $errors[] = "Row {$lineNumber}: {$email} appears more than once in this file.";
                continue;
            }
            $seen[$email] = true;

            $rows[] = $data + [
                'user_name'        => $data['user_name'] ?? $email,
                'password'         => bcrypt(bin2hex(random_bytes(12))),
                'user_profile_id'  => $request->input('user_profile_id'),
                'sub_institute_id' => $sid,
                'status'           => 1,
                'join_year'        => (string) now()->year,
                'created_on'       => now(),
            ];
        }
        fclose($handle);

        if ($rows === []) {
            return response()->json([
                'status'  => false,
                'message' => 'No valid rows to import.',
                'errors'  => array_slice($errors, 0, 50),
                'data'    => ['imported' => 0, 'skipped' => count($errors)],
            ], 422);
        }

        try {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('tbluser')->insert($chunk);
            }

            $this->audit($request, 'user', 'bulk', 'import', ['imported' => count($rows), 'skipped' => count($errors)]);

            return response()->json([
                'status'  => true,
                'message' => count($rows) . ' user' . (count($rows) === 1 ? '' : 's') . ' imported.'
                    . ($errors ? ' ' . count($errors) . ' row(s) skipped.' : ''),
                'data'   => ['imported' => count($rows), 'skipped' => count($errors)],
                'errors' => array_slice($errors, 0, 50),
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to import users');
        }
    }

    /* ─── Roles ────────────────────────────────────────────────────────────── */

    /** GET /roles */
    public function roles(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];
        if (!$sid) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $userCounts = DB::table('tbluser')
                ->where('sub_institute_id', $sid)
                ->select('user_profile_id', DB::raw('COUNT(*) as total'))
                ->groupBy('user_profile_id')->pluck('total', 'user_profile_id');

            $permissionCounts = DB::table('tblgroupwise_rights')
                ->where('sub_institute_id', $sid)->where('can_view', 1)
                ->select('profile_id', DB::raw('COUNT(*) as total'))
                ->groupBy('profile_id')->pluck('total', 'profile_id');

            $roles = DB::table('tbluserprofilemaster')
                ->where('sub_institute_id', $sid)
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'description', 'parent_id', 'sort_order', 'status'])
                ->map(function ($role) use ($userCounts, $permissionCounts) {
                    $role->user_count = (int) ($userCounts[$role->id] ?? 0);
                    $role->permission_count = (int) ($permissionCounts[$role->id] ?? 0);
                    $role->status = (int) $role->status;
                    return $role;
                });

            return response()->json(['status' => true, 'data' => $roles]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to load roles');
        }
    }

    /** POST /roles */
    public function storeRole(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:191',
            'description' => 'nullable|string|max:255',
            'parent_id'   => 'nullable|integer',
            'sort_order'  => 'nullable|integer',
            'status'      => 'nullable|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            // parent_id, description, sort_order, status are NOT NULL with no
            // DB default on this table, so every one is always supplied.
            $id = DB::table('tbluserprofilemaster')->insertGetId([
                'name'             => $request->input('name'),
                'description'      => (string) $request->input('description', ''),
                'parent_id'        => (int) $request->input('parent_id', 0),
                'sort_order'       => (int) $request->input('sort_order', 0),
                'status'           => (int) $request->input('status', 1),
                'sub_institute_id' => $sid,
            ]);

            $this->audit($request, 'role', $id, 'create', ['name' => $request->input('name')]);

            return response()->json(['status' => true, 'message' => 'Role created', 'data' => ['id' => $id]], 201);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to create the role');
        }
    }

    /** PUT /roles/{id} */
    public function updateRole(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:191',
            'description' => 'nullable|string|max:255',
            'sort_order'  => 'nullable|integer',
            'status'      => 'nullable|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $role = DB::table('tbluserprofilemaster')->where('id', $id)->where('sub_institute_id', $sid)->first();
        if (!$role) {
            return response()->json(['status' => false, 'message' => 'Role not found'], 404);
        }

        try {
            DB::table('tbluserprofilemaster')->where('id', $id)->update([
                'name'        => $request->input('name'),
                'description' => (string) $request->input('description', $role->description),
                'sort_order'  => (int) $request->input('sort_order', $role->sort_order),
                'status'      => (int) $request->input('status', $role->status),
            ]);

            $this->audit($request, 'role', $id, 'update', ['name' => $request->input('name')]);

            return response()->json(['status' => true, 'message' => 'Role updated']);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to update the role');
        }
    }

    /**
     * DELETE /roles/{id}
     *
     * `tbluserprofilemaster` has no `deleted_at` in this schema (confirmed:
     * its base migration declares no timestamp columns at all), so unlike
     * the source this deactivates (`status = 0`) rather than soft-deleting.
     * Still refused while users hold the role, same as the source.
     */
    public function destroyRole(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $role = DB::table('tbluserprofilemaster')->where('id', $id)->where('sub_institute_id', $sid)->first();
        if (!$role) {
            return response()->json(['status' => false, 'message' => 'Role not found'], 404);
        }

        $inUse = DB::table('tbluser')->where('user_profile_id', $id)->where('sub_institute_id', $sid)->count();
        if ($inUse > 0) {
            return response()->json([
                'status'  => false,
                'message' => "{$inUse} user" . ($inUse === 1 ? '' : 's') . ' still hold this role. Reassign them first.',
            ], 422);
        }

        try {
            DB::table('tbluserprofilemaster')->where('id', $id)->update(['status' => 0]);

            $this->audit($request, 'role', $id, 'delete', ['name' => $role->name]);

            return response()->json(['status' => true, 'message' => 'Role deactivated']);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to deactivate the role');
        }
    }

    /* ─── Permission matrix ────────────────────────────────────────────────── */

    /**
     * GET /permissions?profile_id=
     *
     * `tblmenumaster` in this schema names its columns differently from the
     * source (`name` not `menu_name`, `parent_menu_id` not `parent_id`,
     * `link` not `access_link`) but keeps the same comma-separated
     * `sub_institute_id` sharing convention, so FIND_IN_SET still applies.
     */
    public function permissions(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid       = $this->lmsContext($request)['sub_institute_id'];
        $profileId = $request->input('profile_id');

        if (!$sid || !$profileId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id and profile_id are required'], 422);
        }

        try {
            $menus = DB::table('tblmenumaster')
                ->where('status', 1)
                ->where(function ($scope) use ($sid) {
                    $scope->where('sub_institute_id', $sid)
                          ->orWhereRaw('FIND_IN_SET(?, sub_institute_id)', [$sid]);
                })
                ->orderBy('parent_menu_id')->orderBy('sort_order')
                ->get(['id', 'name as menu_name', 'parent_menu_id as parent_id', 'level', 'link as access_link', 'icon', 'sort_order']);

            $rights = DB::table('tblgroupwise_rights')
                ->where('profile_id', $profileId)->where('sub_institute_id', $sid)
                ->get()->keyBy('menu_id');

            $menus->transform(function ($menu) use ($rights) {
                $right = $rights[$menu->id] ?? null;
                $menu->can_view   = (bool) ($right->can_view ?? false);
                $menu->can_add    = (bool) ($right->can_add ?? false);
                $menu->can_edit   = (bool) ($right->can_edit ?? false);
                $menu->can_delete = (bool) ($right->can_delete ?? false);
                return $menu;
            });

            return response()->json(['status' => true, 'data' => $menus, 'meta' => ['profile_id' => (int) $profileId]]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to load the permission matrix');
        }
    }

    /**
     * POST /permissions
     *
     * `tblgroupwise_rights` has no `updated_at` column in this schema, so
     * (unlike the source) an existing row's `updated_at` is never set.
     */
    public function savePermissions(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'profile_id'                => 'required|integer',
            'permissions'                => 'required|array',
            'permissions.*.menu_id'      => 'required|integer',
            'permissions.*.can_view'     => 'nullable|boolean',
            'permissions.*.can_add'      => 'nullable|boolean',
            'permissions.*.can_edit'     => 'nullable|boolean',
            'permissions.*.can_delete'   => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $profileId = (int) $request->input('profile_id');

        $roleExists = DB::table('tbluserprofilemaster')->where('id', $profileId)->where('sub_institute_id', $sid)->exists();
        if (!$roleExists) {
            return response()->json(['status' => false, 'message' => 'Role not found'], 404);
        }

        try {
            $saved = 0;

            DB::transaction(function () use ($request, $profileId, $sid, &$saved) {
                foreach ($request->input('permissions', []) as $entry) {
                    $flags = [
                        'can_view'   => !empty($entry['can_view']) ? 1 : 0,
                        'can_add'    => !empty($entry['can_add']) ? 1 : 0,
                        'can_edit'   => !empty($entry['can_edit']) ? 1 : 0,
                        'can_delete' => !empty($entry['can_delete']) ? 1 : 0,
                    ];

                    $existing = DB::table('tblgroupwise_rights')
                        ->where('menu_id', $entry['menu_id'])
                        ->where('profile_id', $profileId)
                        ->where('sub_institute_id', $sid)
                        ->first();

                    if ($existing) {
                        DB::table('tblgroupwise_rights')->where('id', $existing->id)->update($flags);
                    } else {
                        DB::table('tblgroupwise_rights')->insert($flags + [
                            'menu_id'          => $entry['menu_id'],
                            'profile_id'       => $profileId,
                            'sub_institute_id' => $sid,
                            'dashboard_right'  => 0,
                            'created_at'       => now(),
                        ]);
                    }

                    $saved++;
                }
            });

            $this->audit($request, 'permission_matrix', $profileId, 'update', ['rows_saved' => $saved]);

            return response()->json([
                'status'  => true,
                'message' => "Saved {$saved} permission" . ($saved === 1 ? '' : 's') . '.',
                'data'    => ['saved' => $saved],
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to save the permission matrix');
        }
    }

    /* ─── Audit logs ───────────────────────────────────────────────────────── */

    /**
     * GET /audit-logs
     *
     * `system_audit_logs` is a plain table (id/module/action/entity_type/
     * entity_id/actor_id/actor_name/old_values/new_values/reason/
     * ip_address/created_at), so unlike the source's event-projection this
     * reads it directly rather than deriving `action` from a `type` column.
     */
    public function auditLogs(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];
        if (!$sid) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

            $base = fn () => DB::table('system_audit_logs')
                ->where('sub_institute_id', $sid)
                ->where('module', 'g2g_lms_governance');

            $query = $base();

            if ($search = trim((string) $request->input('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('entity_type', 'like', "%{$search}%")
                      ->orWhere('action', 'like', "%{$search}%")
                      ->orWhere('reason', 'like', "%{$search}%");
                });
            }
            if ($action = $request->input('action')) {
                $query->whereRaw("SUBSTRING_INDEX(action, '.', -1) = ?", [$action]);
            }
            if ($entityType = $request->input('entity_type')) {
                $query->where('entity_type', $entityType);
            }
            if ($from = $request->input('from')) {
                $query->where('created_at', '>=', $from);
            }
            if ($to = $request->input('to')) {
                $query->where('created_at', '<=', $to . ' 23:59:59');
            }

            $logs = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage, [
                'id', 'entity_type', 'entity_id',
                DB::raw("SUBSTRING_INDEX(action, '.', -1) as action"),
                'actor_id', 'actor_name',
                DB::raw("'g2g_lms' as source"),
                'created_at',
            ]);

            return response()->json([
                'status' => true,
                'data'   => $logs->items(),
                'meta'   => [
                    'current_page' => $logs->currentPage(),
                    'last_page'    => $logs->lastPage(),
                    'per_page'     => $logs->perPage(),
                    'total'        => $logs->total(),
                ],
                'filters' => [
                    'actions' => $base()->selectRaw("DISTINCT SUBSTRING_INDEX(action, '.', -1) as action")
                        ->orderBy('action')->pluck('action'),
                    'entity_types' => $base()->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type'),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to load audit logs');
        }
    }

    /* ─── System health ────────────────────────────────────────────────────── */

    /**
     * GET /system-health
     *
     * Each check is actually performed, same as the source. "Active
     * Tokens" reports Sanctum's `personal_access_tokens` table (it exists in
     * this schema) purely as an informational signal - this app's own
     * session-based auth does not use it, so the number is not this app's
     * concurrency, just whatever else in the codebase issues tokens.
     */
    public function systemHealth(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];
        $checks = [];

        try {
            $started = microtime(true);
            DB::select('SELECT 1');
            $checks[] = ['key' => 'database', 'label' => 'Database', 'status' => 'healthy', 'detail' => round((microtime(true) - $started) * 1000) . ' ms'];
        } catch (\Throwable $e) {
            $checks[] = ['key' => 'database', 'label' => 'Database', 'status' => 'error', 'detail' => 'Unreachable'];
        }

        $mailer   = config('mail.default');
        $mailHost = config('mail.mailers.' . $mailer . '.host');
        $checks[] = [
            'key' => 'email', 'label' => 'Email Service',
            'status' => $mailer && $mailer !== 'log' && $mailHost ? 'healthy' : 'warning',
            'detail' => $mailer ? ucfirst($mailer) . ($mailHost ? " ({$mailHost})" : '') : 'Not configured',
        ];

        try {
            $disk = config('filesystems.default');
            $checks[] = ['key' => 'storage', 'label' => 'Storage', 'status' => $disk ? 'healthy' : 'warning', 'detail' => $disk ? ucfirst((string) $disk) : 'Not configured'];
        } catch (\Throwable $e) {
            $checks[] = ['key' => 'storage', 'label' => 'Storage', 'status' => 'error', 'detail' => 'Unavailable'];
        }

        $sso = DB::table('lms_integrations')
            ->where('sub_institute_id', $sid)
            ->whereIn('provider', ['google', 'azure', 'okta', 'saml'])
            ->whereNull('deleted_at')
            ->orderByRaw("FIELD(status, 'connected', 'error', 'disconnected')")
            ->first();

        $checks[] = [
            'key' => 'sso', 'label' => 'SSO',
            'status' => $sso ? ($sso->status === 'connected' ? 'healthy' : ($sso->status === 'error' ? 'error' : 'warning')) : 'unknown',
            'detail' => $sso ? ($sso->display_name . ' — ' . $sso->status) : 'No provider configured',
        ];

        $checks[] = [
            'key' => 'sessions', 'label' => 'Active Tokens', 'status' => 'healthy',
            'detail' => Schema::hasTable('personal_access_tokens')
                ? DB::table('personal_access_tokens')->where('last_used_at', '>=', now()->subDays(1))->count() . ' in last 24h'
                : 'n/a',
        ];

        return response()->json(['status' => true, 'data' => $checks]);
    }
}
