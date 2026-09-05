<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * G2G LMS migration — Package 2 (Assignments, Sessions & Calendar).
 *
 * Ported from G2G's `App\Http\Controllers\lms\assignment\assignmentController`
 * (hp_erp). Business logic, validation and response shapes are preserved
 * as-is; only the identity/tenant resolution and route registration changed:
 *
 *   - hp_erp resolved identity per-method via `ResolvesLmsIdentity`
 *     (`guardLmsToken`/`lmsTenantId`/`contextUserId`/`guardLmsProfile`), a
 *     Sanctum-token trait. That trait has no equivalent in this project yet
 *     (checked before writing this file — `App\Http\Controllers\G2gLms\
 *     Concerns\ResolvesLmsIdentity` does not exist). Rather than block on a
 *     shared trait another package may or may not add, this controller
 *     reads identity directly from the session the `api.session` middleware
 *     (`routes/g2g_lms.php`'s route group) already hydrates from the
 *     verified JWT — `session('sub_institute_id')` / `session('user_id')` /
 *     `session('user_profile_name')` / `session('is_admin')` — the same
 *     values `ResolvesCompetencyContext` (this project's closest sibling
 *     port, `App\Http\Controllers\api\TalentManagement\Competency\Concerns`)
 *     already reads this way. If/when a shared `ResolvesLmsIdentity` trait
 *     is added under `App\Http\Controllers\G2gLms\Concerns`, these private
 *     helpers can be swapped for it with no change to the action methods.
 *   - Token/type=API validation is NOT re-checked per method here (unlike
 *     hp_erp's `validateToken()`): the whole `g2g-lms` route group already
 *     runs behind `['api.session', 'staff.only']`, so every request
 *     reaching these actions is already authenticated and already
 *     restricted to a staff profile.
 *   - `EnrolmentWriter` (hp_erp's `App\Services\Lms\EnrolmentWriter`, which
 *     mirrors every assignment/approval into `lms_course_enroll` so it is
 *     visible in My Learning) is reproduced inline as `ensureEnrolment()`/
 *     `revokeEnrolment()` below, but GUARDED behind
 *     `Schema::hasTable('lms_course_enroll')`: that table does not exist in
 *     this project yet (confirmed before writing this file — it is owned by
 *     the Learning Dashboard/Catalog/My Learning package, not this one). The
 *     assignment/approval write always succeeds and `lms_assignments`
 *     always reflects the truth; the enrolment mirror silently no-ops until
 *     that table exists, so nothing here needs to change once it lands.
 *   - `learners()` and `store()` read `tbluser` exactly as hp_erp did.
 *   - `courses()` is NEW (not a 1:1 port): G2G's Assign Learning dialog
 *     searched courses via the separate Learning Catalog service, a sibling
 *     package's screen. This package cannot depend on that landing first,
 *     so a small picker-scoped endpoint is added here instead, reading the
 *     same `sub_std_map` table Learning Catalog will eventually own more of.
 *   - `requestEnrollment` (`POST /lmsAssignment/request` in hp_erp — a
 *     learner self-requesting a course) is NOT ported: the ported frontend
 *     slice (`components/domain/lms/assignments/learning-assignments.tsx`)
 *     never calls it — there is no "request a course" UI in this screen —
 *     so it would be dead code. Noted in this package's final report.
 */
class AssignmentsController extends Controller
{
    /** Profiles allowed to review approval requests and see enrollments. */
    private function isAdminOrHr(): bool
    {
        $profile = strtolower((string) session()->get('user_profile_name'));
        $isAdmin = (bool) session()->get('is_admin');

        return $isAdmin || str_contains($profile, 'admin') || str_contains($profile, 'hr');
    }

    private function guardAdminOrHr()
    {
        if ($this->isAdminOrHr()) {
            return null;
        }

        return response()->json([
            'status' => false,
            'message' => 'Your profile is not permitted to review enrolment requests.',
        ], 403);
    }

    private function tenantId(): ?int
    {
        $value = session()->get('sub_institute_id');

        return $value !== null ? (int) $value : null;
    }

    private function actingUserId(): ?int
    {
        $value = session()->get('user_id');

        return $value !== null ? (int) $value : null;
    }

    /**
     * Mirrors hp_erp's `EnrolmentWriter::ensureEnrolment()`. No-ops (returns
     * null, writes nothing) when `lms_course_enroll` does not exist yet —
     * see this class's docblock.
     */
    private function ensureEnrolment(int $userId, int $courseId, int $tenant, string $status = 'enrolled'): ?int
    {
        if (!Schema::hasTable('lms_course_enroll')) {
            return null;
        }

        $courseOwned = DB::table('sub_std_map')
            ->where('id', $courseId)
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->exists();

        if (!$courseOwned) {
            return null;
        }

        $userOwned = DB::table('tbluser')->where('id', $userId)->where('sub_institute_id', $tenant)->exists();

        if (!$userOwned) {
            return null;
        }

        $existing = DB::table('lms_course_enroll')
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();

        if ($existing) {
            // Never downgrade a finished/active enrolment; the one promotion
            // allowed is pending -> enrolled, which is what approving means.
            if (($existing->status ?? null) === 'pending' && $status === 'enrolled') {
                DB::table('lms_course_enroll')->where('id', $existing->id)->update([
                    'status' => 'enrolled',
                    'updated_at' => now(),
                ]);
            }

            return (int) $existing->id;
        }

        return (int) DB::table('lms_course_enroll')->insertGetId([
            'user_id' => $userId,
            'course_id' => $courseId,
            'status' => $status,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'sub_institute_id' => $tenant,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Mirrors hp_erp's `EnrolmentWriter::revokeEnrolment()`. Same no-op guard. */
    private function revokeEnrolment(int $userId, int $courseId, int $tenant): void
    {
        if (!Schema::hasTable('lms_course_enroll')) {
            return;
        }

        DB::table('lms_course_enroll')
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->where('status', '<>', 'completed')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    /** GET api/g2g-lms/assignments — list all assignments for this tenant. */
    public function index(Request $request)
    {
        $subInstituteId = $this->tenantId();

        $query = DB::table('lms_assignments as a')
            ->join('sub_std_map as c', 'a.course_id', '=', 'c.id')
            ->join('tbluser as u', 'a.user_id', '=', 'u.id')
            ->where('a.sub_institute_id', $subInstituteId)
            ->whereNull('a.deleted_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('c.display_name', 'like', "%{$search}%")
                    ->orWhere('u.first_name', 'like', "%{$search}%")
                    ->orWhere('u.last_name', 'like', "%{$search}%")
                    ->orWhere('u.employee_no', 'like', "%{$search}%");
            });
        }

        // The Assignment Queue shows live assignments; the Approval Queue asks
        // for approval_status=pending. Defaults to everything already approved
        // so a pending self-request never looks like an active assignment.
        $approvalStatus = $request->input('approval_status', 'approved');
        if ($approvalStatus !== 'all') {
            $query->where('a.approval_status', $approvalStatus);
        }

        $assignments = $query->select(
            'a.id',
            DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as learner_name"),
            'c.display_name as course_name',
            'c.subject_type as type',
            'a.assignment_type',
            'a.due_date',
            'a.status',
            'a.approval_status',
            'a.requested_by',
            'a.reviewed_at',
            'a.review_note',
            'a.progress',
            'a.assigned_by',
            'a.assigned_on',
            'a.source',
            'a.competency_id',
            'a.development_plan_id'
        )->get();

        $assignments->transform(function ($item) {
            $parts = explode(' ', $item->learner_name ?? '');
            $initials = '';
            foreach ($parts as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
            $item->initials = substr($initials, 0, 2);

            return $item;
        });

        return response()->json(['status' => true, 'data' => $assignments]);
    }

    /** GET api/g2g-lms/assignments/stats */
    public function stats(Request $request)
    {
        $subInstituteId = $this->tenantId();
        $query = DB::table('lms_assignments')->where('sub_institute_id', $subInstituteId)->whereNull('deleted_at');

        $total = (clone $query)->count();
        $inProgress = (clone $query)->where('status', 'In Progress')->count();
        $completed = (clone $query)->where('status', 'Completed')->count();

        $overdue = (clone $query)->where(function ($q) {
            $q->where('status', 'Overdue')
                ->orWhere(function ($q2) {
                    $q2->whereNotNull('due_date')
                        ->where('due_date', '<', now())
                        ->where('status', '!=', 'Completed');
                });
        })->count();

        $pendingApproval = (clone $query)->where('approval_status', 'pending')->count();

        return response()->json([
            'status' => true,
            'data' => [
                'total_assigned' => $total,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'overdue' => $overdue,
                'pending_approval' => $pendingApproval,
            ],
        ]);
    }

    /** POST api/g2g-lms/assignments — create one or more assignments. */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'course_id' => 'required|integer',
            'assignment_type' => 'required|string',
            'due_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $subInstituteId = $this->tenantId();
        if (!$subInstituteId) {
            return response()->json(['message' => 'Unable to resolve your organisation', 'status' => false], 401);
        }

        $actingUserId = $this->actingUserId();
        $assignedBy = $actingUserId
            ? (DB::table('tbluser')
                ->where('id', $actingUserId)
                ->selectRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) as full_name")
                ->value('full_name') ?: 'Admin')
            : 'Admin';

        $courseOwned = DB::table('sub_std_map')
            ->where('id', $request->course_id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$courseOwned) {
            return response()->json(['message' => 'Course not found', 'status' => false], 404);
        }

        $requested = array_values(array_unique(array_map('intval', (array) $request->user_ids)));
        $validUserIds = DB::table('tbluser')
            ->whereIn('id', $requested)
            ->where('sub_institute_id', $subInstituteId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($validUserIds)) {
            return response()->json([
                'message' => 'None of those employees are in your organisation',
                'status' => false,
            ], 422);
        }

        $assignments = [];
        foreach ($validUserIds as $userId) {
            $assignments[] = [
                'user_id' => $userId,
                'course_id' => $request->course_id,
                'assignment_type' => $request->assignment_type,
                'due_date' => $request->due_date,
                'status' => 'Not Started',
                'progress' => 0,
                'assigned_by' => $assignedBy,
                'assigned_on' => now(),
                'sub_institute_id' => $subInstituteId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($assignments, $validUserIds, $request, $subInstituteId) {
            DB::table('lms_assignments')->insert($assignments);

            foreach ($validUserIds as $userId) {
                $this->ensureEnrolment($userId, (int) $request->course_id, $subInstituteId);
            }
        });

        $skipped = count($requested) - count($validUserIds);

        return response()->json([
            'message' => 'Assignments created successfully',
            'status' => true,
            'assigned' => count($validUserIds),
            'skipped' => $skipped,
        ]);
    }

    /** POST api/g2g-lms/assignments/{id}/status — update a single assignment. */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['status' => 'required|string']);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $subInstituteId = $this->tenantId();
        if (!$subInstituteId) {
            return response()->json(['message' => 'Unable to resolve your organisation', 'status' => false], 401);
        }

        $assignment = DB::table('lms_assignments')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::table('lms_assignments')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Status updated successfully', 'status' => true]);
    }

    /** POST api/g2g-lms/assignments/bulk-status — batch status update. */
    public function bulkUpdateStatus(Request $request)
    {
        $subInstituteId = $this->tenantId();

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'status' => 'required|in:Not Started,In Progress,Completed,Overdue',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $status = $request->input('status');
        $update = ['status' => $status, 'updated_at' => now()];

        // Keep `progress` in step with `status` at the unambiguous ends.
        if ($status === 'Completed') {
            $update['progress'] = 100;
        } elseif ($status === 'Not Started') {
            $update['progress'] = 0;
        }

        $affected = DB::table('lms_assignments')
            ->whereIn('id', $request->ids)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->update($update);

        return response()->json([
            'message' => $affected.' assignment(s) updated.',
            'status' => true,
            'data' => ['affected' => $affected, 'skipped' => count($request->ids) - $affected],
        ]);
    }

    /** POST api/g2g-lms/assignments/{id}/review — approve or reject one request. */
    public function review(Request $request, $id)
    {
        if ($denied = $this->guardAdminOrHr()) {
            return $denied;
        }

        $subInstituteId = $this->tenantId();

        $validator = Validator::make($request->all(), [
            'decision' => 'required|in:approved,rejected',
            'review_note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $assignment = DB::table('lms_assignments')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->first();

        if (!$assignment) {
            return response()->json(['status' => false, 'message' => 'Request not found'], 404);
        }

        if ($assignment->approval_status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'That request has already been reviewed.',
            ], 422);
        }

        DB::transaction(function () use ($id, $request, $assignment, $subInstituteId) {
            DB::table('lms_assignments')->where('id', $id)->update([
                'approval_status' => $request->decision,
                'reviewed_by' => $this->actingUserId(),
                'reviewed_at' => now(),
                'review_note' => $request->review_note,
                'updated_at' => now(),
            ]);

            if ($request->decision === 'approved') {
                $this->ensureEnrolment((int) $assignment->user_id, (int) $assignment->course_id, (int) $subInstituteId);
            } else {
                $this->revokeEnrolment((int) $assignment->user_id, (int) $assignment->course_id, (int) $subInstituteId);
            }
        });

        return response()->json([
            'status' => true,
            'message' => $request->decision === 'approved' ? 'Request approved' : 'Request rejected',
            'data' => DB::table('lms_assignments')->find($id),
        ]);
    }

    /** POST api/g2g-lms/assignments/bulk-review — decide on several at once. */
    public function bulkReview(Request $request)
    {
        if ($denied = $this->guardAdminOrHr()) {
            return $denied;
        }

        $subInstituteId = $this->tenantId();

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'decision' => 'required|in:approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $affected = DB::table('lms_assignments')
            ->whereIn('id', $request->ids)
            ->where('sub_institute_id', $subInstituteId)
            ->where('approval_status', 'pending')
            ->whereNull('deleted_at')
            ->update([
                'approval_status' => $request->decision,
                'reviewed_by' => $this->actingUserId(),
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'status' => true,
            'message' => $affected.' request(s) '.$request->decision.'.',
            'data' => ['affected' => $affected, 'skipped' => count($request->ids) - $affected],
        ]);
    }

    /**
     * GET api/g2g-lms/assignments/enrollments — learner-initiated enrolments.
     * No-ops to an empty list when `lms_course_enroll` does not exist yet.
     */
    public function enrollments(Request $request)
    {
        $subInstituteId = $this->tenantId();

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        if (!Schema::hasTable('lms_course_enroll')) {
            return response()->json(['status' => true, 'data' => []]);
        }

        $query = DB::table('lms_course_enroll as e')
            ->join('sub_std_map as c', 'e.course_id', '=', 'c.id')
            ->join('tbluser as u', 'e.user_id', '=', 'u.id')
            ->where('e.sub_institute_id', $subInstituteId)
            ->whereNull('e.deleted_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('c.display_name', 'like', "%{$search}%")
                    ->orWhere('u.first_name', 'like', "%{$search}%")
                    ->orWhere('u.last_name', 'like', "%{$search}%")
                    ->orWhere('u.employee_no', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('e.status', $status);
        }

        $enrollments = $query
            ->orderByDesc('e.created_at')
            ->limit(min(max((int) $request->input('limit', 200), 1), 500))
            ->get([
                'e.id',
                'e.course_id',
                'e.user_id',
                DB::raw("TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) as learner_name"),
                'u.employee_no',
                'c.display_name as course_name',
                'c.subject_type as type',
                'e.status',
                'e.start_date',
                'e.end_date',
                'e.created_at as enrolled_on',
            ])
            ->map(function ($row) {
                $initials = '';
                foreach (explode(' ', $row->learner_name ?? '') as $part) {
                    $initials .= strtoupper(substr($part, 0, 1));
                }
                $row->initials = substr($initials, 0, 2);

                return $row;
            });

        return response()->json(['status' => true, 'data' => $enrollments]);
    }

    /** GET api/g2g-lms/assignments/learners — learner picker for the Assign Learning dialog. */
    public function learners(Request $request)
    {
        $subInstituteId = $this->tenantId();

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        $learners = DB::table('tbluser')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_no', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->limit(min(max((int) $request->input('limit', 50), 1), 200))
            ->get([
                'id',
                'employee_no',
                'email',
                DB::raw("TRIM(CONCAT_WS(' ', first_name, last_name)) as name"),
            ]);

        return response()->json(['status' => true, 'data' => $learners]);
    }

    /**
     * GET api/g2g-lms/assignments/courses — course picker for the Assign
     * Learning dialog. NEW (see this class's docblock): scoped to just what
     * the picker needs, reading `sub_std_map`.
     */
    public function courses(Request $request)
    {
        $subInstituteId = $this->tenantId();

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        $courses = DB::table('sub_std_map')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->when($request->boolean('status', true), fn ($q) => $q->where('status', 1))
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('display_name', 'like', "%{$search}%")
                        ->orWhere('subject_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('display_name')
            ->limit(min(max((int) $request->input('limit', 20), 1), 100))
            ->get(['id', 'display_name', 'subject_code', 'subject_type']);

        return response()->json(['status' => true, 'data' => $courses]);
    }

    /** POST api/g2g-lms/assignments/import — bulk-create assignments from a CSV. */
    public function import(Request $request)
    {
        $subInstituteId = $this->tenantId();

        $validator = Validator::make(
            array_merge($request->all(), ['sub_institute_id' => $subInstituteId]),
            [
                'sub_institute_id' => 'required|integer',
                'file' => 'required|file|mimes:csv,txt|max:5120',
                'assignment_type' => 'nullable|string|max:100',
                'skip_invalid' => 'nullable|boolean',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->messages()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $rows = array_map('str_getcsv', file($request->file('file')->getRealPath()));
        $rows = array_values(array_filter($rows, fn ($r) => count(array_filter($r, fn ($v) => trim((string) $v) !== '')) > 0));

        if (count($rows) < 2) {
            return response()->json([
                'status' => false,
                'message' => 'The file needs a header row and at least one data row.',
            ], 422);
        }

        $header = array_map(
            fn ($h) => strtolower(trim(str_replace([' ', '-'], '_', (string) $h))),
            array_shift($rows)
        );

        $columnOf = function (array $candidates) use ($header) {
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $header, true);
                if ($index !== false) {
                    return $index;
                }
            }

            return null;
        };

        $learnerCol = $columnOf(['employee_no', 'employee_number', 'emp_no', 'email', 'user_email', 'user_id', 'learner']);
        $courseCol = $columnOf(['course_code', 'subject_code', 'course', 'course_name', 'course_id']);
        $dueCol = $columnOf(['due_date', 'due', 'deadline']);
        $typeCol = $columnOf(['assignment_type', 'type']);

        if ($learnerCol === null || $courseCol === null) {
            return response()->json([
                'status' => false,
                'message' => 'The file needs a learner column (employee_no, email or user_id) and a course column (course_code or course_name).',
                'data' => ['detected_headers' => $header],
            ], 422);
        }

        $defaultType = $request->input('assignment_type', 'Mandatory');
        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $learnerKey = trim((string) ($row[$learnerCol] ?? ''));
            $courseKey = trim((string) ($row[$courseCol] ?? ''));

            if ($learnerKey === '' || $courseKey === '') {
                $errors[] = ['line' => $line, 'message' => 'Learner and course are both required.'];
                continue;
            }

            $user = DB::table('tbluser')
                ->where('sub_institute_id', $subInstituteId)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($learnerKey) {
                    $q->where('employee_no', $learnerKey)->orWhere('email', $learnerKey);
                    if (ctype_digit($learnerKey)) {
                        $q->orWhere('id', (int) $learnerKey);
                    }
                })
                ->first(['id']);

            if (!$user) {
                $errors[] = ['line' => $line, 'message' => "No learner matches \"{$learnerKey}\"."];
                continue;
            }

            $course = DB::table('sub_std_map')
                ->where('sub_institute_id', $subInstituteId)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($courseKey) {
                    $q->where('subject_code', $courseKey)->orWhere('display_name', $courseKey);
                    if (ctype_digit($courseKey)) {
                        $q->orWhere('id', (int) $courseKey);
                    }
                })
                ->first(['id']);

            if (!$course) {
                $errors[] = ['line' => $line, 'message' => "No course matches \"{$courseKey}\"."];
                continue;
            }

            $dueDate = null;
            if ($dueCol !== null && ($rawDue = trim((string) ($row[$dueCol] ?? ''))) !== '') {
                foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
                    $parsed = \DateTime::createFromFormat('!'.$format, $rawDue);
                    if ($parsed && $parsed->format($format) === $rawDue) {
                        $dueDate = $parsed->format('Y-m-d');
                        break;
                    }
                }

                if ($dueDate === null) {
                    try {
                        $dueDate = \Carbon\Carbon::parse($rawDue)->toDateString();
                    } catch (\Exception $e) {
                        $errors[] = ['line' => $line, 'message' => "Could not read the due date \"{$rawDue}\"."];
                        continue;
                    }
                }
            }

            $alreadyAssigned = DB::table('lms_assignments')
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($alreadyAssigned) {
                $errors[] = ['line' => $line, 'message' => 'This learner is already assigned to that course.'];
                continue;
            }

            $valid[] = [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'assignment_type' => $typeCol !== null && trim((string) ($row[$typeCol] ?? '')) !== ''
                    ? trim((string) $row[$typeCol])
                    : $defaultType,
                'due_date' => $dueDate,
            ];
        }

        $skipInvalid = filter_var($request->input('skip_invalid', false), FILTER_VALIDATE_BOOLEAN);

        if (!empty($errors) && !$skipInvalid) {
            return response()->json([
                'status' => false,
                'message' => count($errors).' row(s) could not be imported. Nothing was saved.',
                'data' => ['valid_rows' => count($valid), 'errors' => $errors],
            ], 422);
        }

        if (empty($valid)) {
            return response()->json([
                'status' => false,
                'message' => 'No importable rows were found.',
                'data' => ['errors' => $errors],
            ], 422);
        }

        try {
            $actingUserId = $this->actingUserId();
            $assignedBy = $actingUserId
                ? (DB::table('tbluser')
                    ->where('id', $actingUserId)
                    ->selectRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) as full_name")
                    ->value('full_name') ?: 'Admin')
                : 'Admin';

            $now = now();
            DB::table('lms_assignments')->insert(array_map(fn ($row) => $row + [
                'status' => 'Not Started',
                'progress' => 0,
                'assigned_by' => $assignedBy,
                'assigned_on' => $now,
                'sub_institute_id' => $subInstituteId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $valid));

            return response()->json([
                'status' => true,
                'message' => count($valid).' assignment(s) imported'
                    .(empty($errors) ? '.' : ', '.count($errors).' row(s) skipped.'),
                'data' => ['imported' => count($valid), 'skipped' => count($errors), 'errors' => $errors],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to import the assignments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
