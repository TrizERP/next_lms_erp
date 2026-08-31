<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\ApprovalController`.
 *
 * The competency module's approval queue — the submit-for-approval workflow
 * behind the Capability Library / Competency Framework screens' "Submit for
 * Approval" action.
 *
 * One inbox for everything that needs a second pair of eyes:
 *   - competency  -> s_users_skills.approve_status   Pending -> Approved
 *   - framework   -> s_competency_frameworks.status  draft   -> active
 *   - role-mapping-> s_competency_mapping_reviews    (existing queue, read-only here)
 *
 * Rejecting never destroys or archives the subject: it leaves it in its
 * unpublished state with the reviewer's note attached, so the submitter can fix
 * and resubmit. Archiving is a separate, deliberate action.
 *
 * `s_competency_approvals` did not exist in this target and is created by this
 * port's `2026_08_24_130000_create_s_competency_approvals_table.php`, schema
 * copied column-for-column from the source's own migration. `s_users_skills`
 * exists but its `approve_status` enum lacked the `Rejected` value this
 * controller writes (it only had `Approved, Pending, Cancelled`) - widened by
 * this port's `2026_08_24_130100_add_rejected_to_skills_approve_status.php`.
 * `s_competency_frameworks.status` (string, `draft`/`active`) and
 * `s_competency_mapping_reviews` (the read-only unioned queue) already exist
 * with the exact shape this controller needs.
 */
class CompetencyApprovalController extends Controller
{
    use ResolvesCompetencyContext;

    private const TABLE = 's_competency_approvals';

    /**
     * subject_type => [table, name column, pending value, approved value,
     *                  rejected value]
     *
     * `rejected` exists because a rejected subject used to be left sitting at
     * `pending`, and the Library hides "Submit for Approval" for pending items -
     * so a rejection could never be resubmitted. It must be a state that is
     * neither pending nor approved, so the author can revise and resubmit.
     */
    private const SUBJECTS = [
        'competency' => [
            'table'    => 's_users_skills',
            'name'     => 'title',
            'column'   => 'approve_status',
            'pending'  => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'label'    => 'Competency',
        ],
        'framework' => [
            'table'    => 's_competency_frameworks',
            'name'     => 'name',
            'column'   => 'status',
            'pending'  => 'draft',
            'approved' => 'active',
            // A framework's pre-submission state IS 'draft', so returning a
            // rejected one there is already resubmittable.
            'rejected' => 'draft',
            'label'    => 'Framework',
        ],
    ];

    private function subject(string $type): ?array
    {
        return self::SUBJECTS[$type] ?? null;
    }

    private function fail(string $message, int $status = 400)
    {
        return response()->json(['status' => 0, 'message' => $message], $status);
    }

    private function ok(string $message, $data = null, ?array $extra = null)
    {
        return response()->json(array_merge(
            ['status' => 1, 'message' => $message, 'data' => $data],
            $extra ?? []
        ));
    }

    private function actorName(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $user = DB::table('tbluser')->where('id', $userId)->first();
        if (!$user) {
            return null;
        }

        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : ($user->user_name ?? null);
    }

    /**
     * GET /api/competency/approvals
     *
     * The queue, plus a count per status so the tab strip can badge itself.
     * `subject_type` narrows to one kind; omit it for the whole inbox.
     */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $status = strtolower((string) $request->input('status', 'pending'));
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }
        $type = $this->activeFilter($request->input('subject_type'));

        $rows = collect();

        if ($type === null || isset(self::SUBJECTS[$type])) {
            $rows = DB::table(self::TABLE)
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->where('status', $status)
                ->when($type !== null, fn ($q) => $q->where('subject_type', $type))
                ->orderByDesc('submitted_at')
                ->limit(200)
                ->get()
                ->map(fn ($row) => [
                    'id'                => (int) $row->id,
                    'source'            => 'approval',
                    'subject_type'      => $row->subject_type,
                    'subject_id'        => (int) $row->subject_id,
                    'subject_name'      => $row->subject_name,
                    'status'            => $row->status,
                    'note'              => $row->note,
                    'submitted_by_name' => $row->submitted_by_name,
                    'submitted_at'      => $row->submitted_at,
                    'reviewer_name'     => $row->reviewer_name,
                    'reviewed_at'       => $row->reviewed_at,
                    'changes_count'     => null,
                ]);
        }

        // The mapping queue predates this table; unioned so the inbox is whole.
        if ($type === null || $type === 'role-mapping') {
            $mapping = DB::table('s_competency_mapping_reviews')
                ->where('sub_institute_id', $sid)
                ->whereNull('deleted_at')
                ->where('status', $status)
                ->orderByDesc('created_at')
                ->limit(200)
                ->get()
                ->map(fn ($row) => [
                    'id'                => (int) $row->id,
                    'source'            => 'mapping-review',
                    'subject_type'      => 'role-mapping',
                    'subject_id'        => (int) $row->id,
                    'subject_name'      => $row->jobrole,
                    'status'            => $row->status,
                    'note'              => $row->note,
                    'submitted_by_name' => $row->submitted_by_name,
                    'submitted_at'      => $row->created_at,
                    'reviewer_name'     => null,
                    'reviewed_at'       => $row->reviewed_at,
                    'changes_count'     => (int) ($row->changes_count ?? 0),
                ]);

            $rows = $rows->concat($mapping);
        }

        $rows = $rows->sortByDesc('submitted_at')->values();

        return $this->ok('Approvals fetched successfully', $rows, ['counts' => $this->counts($sid)]);
    }

    /** Pending / approved / rejected totals across both queues. */
    private function counts(int $sid): array
    {
        $own = DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        $mapping = DB::table('s_competency_mapping_reviews')
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        return [
            'pending'  => (int) ($own['pending'] ?? 0) + (int) ($mapping['pending'] ?? 0),
            'approved' => (int) ($own['approved'] ?? 0) + (int) ($mapping['approved'] ?? 0),
            'rejected' => (int) ($own['rejected'] ?? 0) + (int) ($mapping['rejected'] ?? 0),
        ];
    }

    /**
     * POST /api/competency/approvals
     *
     * Submit a competency or framework for review. The subject moves to its
     * unpublished state immediately - a record awaiting approval must not keep
     * reading as Approved while it sits in the queue.
     */
    public function store(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'subject_type' => 'required|string|in:competency,framework',
            'subject_id'   => 'required|integer|min:1',
            'note'         => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $type = $request->input('subject_type');
        $subject = $this->subject($type);
        $subjectId = (int) $request->input('subject_id');

        $row = DB::table($subject['table'])
            ->where('id', $subjectId)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->first();

        if (!$row) {
            return $this->fail($subject['label'] . ' not found.', 404);
        }

        $alreadyQueued = DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)
            ->where('subject_type', $type)
            ->where('subject_id', $subjectId)
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->exists();

        if ($alreadyQueued) {
            return $this->fail('This ' . strtolower($subject['label']) . ' is already awaiting approval.', 422);
        }

        $name = (string) $row->{$subject['name']};
        $actor = $this->actorName($context['user_id']);

        DB::table(self::TABLE)->insert([
            'sub_institute_id'  => $sid,
            'subject_type'      => $type,
            'subject_id'        => $subjectId,
            'subject_name'      => mb_substr($name, 0, 191),
            'status'            => 'pending',
            'note'              => $request->input('note'),
            'submitted_by'      => $context['user_id'],
            'submitted_by_name' => $actor,
            'submitted_at'      => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        DB::table($subject['table'])->where('id', $subjectId)->update([
            $subject['column'] => $subject['pending'],
            'updated_by'       => $context['user_id'],
            'updated_at'       => now(),
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'submitted_for_approval',
            'Submitted ' . strtolower($subject['label']) . ' "' . $name . '" for approval',
            $type,
            $subjectId,
            $name
        );

        return $this->ok('Submitted for approval', ['subject_id' => $subjectId]);
    }

    /**
     * PUT /api/competency/approvals/{id}
     *
     * Approve or reject. Approving publishes the subject; rejecting leaves it
     * unpublished with the note attached so it can be fixed and resubmitted.
     */
    public function update(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'decision' => 'required|string|in:approve,reject',
            'note'     => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $approval = DB::table(self::TABLE)
            ->where('id', (int) $id)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->first();

        if (!$approval) {
            return $this->fail('Approval request not found.', 404);
        }
        if ($approval->status !== 'pending') {
            return $this->fail('This request has already been ' . $approval->status . '.', 422);
        }

        $subject = $this->subject($approval->subject_type);
        if (!$subject) {
            return $this->fail('Unknown approval subject.', 422);
        }

        $approve = $request->input('decision') === 'approve';

        DB::table(self::TABLE)->where('id', $approval->id)->update([
            'status'        => $approve ? 'approved' : 'rejected',
            'reviewer_id'   => $context['user_id'],
            'reviewer_name' => $this->actorName($context['user_id']),
            'reviewed_at'   => now(),
            // Keep the submitter's note when the reviewer adds none.
            'note'          => $request->input('note') ?: $approval->note,
            'updated_at'    => now(),
        ]);

        // The subject moves on EITHER decision. Rejection previously left it at
        // 'Pending', and the Library hides "Submit for Approval" for pending
        // items - so a rejected competency could never be resubmitted. Moving it
        // to a distinct rejected state makes it resubmittable through the normal
        // route.
        DB::table($subject['table'])->where('id', $approval->subject_id)->update([
            $subject['column'] => $approve ? $subject['approved'] : $subject['rejected'],
            'updated_by'       => $context['user_id'],
            'updated_at'       => now(),
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            $approve ? 'approved_' . $approval->subject_type : 'rejected_' . $approval->subject_type,
            ($approve ? 'Approved ' : 'Rejected ') . strtolower($subject['label']) . ' "' . $approval->subject_name . '"',
            $approval->subject_type,
            (int) $approval->subject_id,
            $approval->subject_name
        );

        return $this->ok($approve ? 'Approved successfully' : 'Rejected successfully', ['id' => (int) $approval->id]);
    }

    /**
     * POST /api/competency/approvals/bulk-approve
     *
     * Approves every pending request, or just the ids given.
     */
    public function bulkApprove(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $ids = $request->input('ids');
        $ids = is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];

        $pending = DB::table(self::TABLE)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('status', 'pending')
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->get();

        if ($pending->isEmpty()) {
            return $this->ok('Nothing to approve', ['approved' => 0]);
        }

        $reviewer = $this->actorName($context['user_id']);

        foreach ($pending as $approval) {
            $subject = $this->subject($approval->subject_type);
            if (!$subject) {
                continue;
            }

            DB::table(self::TABLE)->where('id', $approval->id)->update([
                'status'        => 'approved',
                'reviewer_id'   => $context['user_id'],
                'reviewer_name' => $reviewer,
                'reviewed_at'   => now(),
                'updated_at'    => now(),
            ]);

            DB::table($subject['table'])->where('id', $approval->subject_id)->update([
                $subject['column'] => $subject['approved'],
                'updated_by'       => $context['user_id'],
                'updated_at'       => now(),
            ]);
        }

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'bulk_approved',
            'Approved ' . $pending->count() . ' pending ' . ($pending->count() === 1 ? 'request' : 'requests'),
            'approval',
            null,
            null
        );

        return $this->ok('Approved ' . $pending->count() . ' ' . ($pending->count() === 1 ? 'request' : 'requests'), [
            'approved' => $pending->count(),
        ]);
    }

    /**
     * GET /api/competency/approvals/for/{type}/{id}
     *
     * The approval trail for one subject, so its detail panel can show where it
     * stands and why it was last rejected.
     */
    public function forSubject(Request $request, $type, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        if (!$this->subject($type)) {
            return $this->fail('Unknown approval subject.', 404);
        }

        $rows = DB::table(self::TABLE)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('subject_type', $type)
            ->where('subject_id', (int) $id)
            ->whereNull('deleted_at')
            ->orderByDesc('submitted_at')
            ->limit(20)
            ->get();

        return $this->ok('Approval history fetched successfully', $rows);
    }
}
