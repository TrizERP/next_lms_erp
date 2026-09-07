<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Assessment campaigns (`s_competency_assessment_cycles`) - the Assessment
 * Workspace screen's metrics card, campaigns table, and top tabs
 * (Participant Ratings / Calibration / Approvals / Closed Campaigns).
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\Competency\AssessmentCycleController`,
 * trimmed to exactly the actions the frontend's
 * `services/competency/assessment-workspace.ts` calls (confirmed by reading
 * that file before porting, per the package brief) - `getMetrics`,
 * `getCampaigns`, `getParticipants`, `createCampaign`,
 * `getParticipantRatings`, `getCalibration`, `getApprovals`,
 * `getClosedCampaigns`, `reviewAssessment`. The source controller also has a
 * campaign detail panel (show/update/ratings/calibrationQueue/auditTrail)
 * and a View Configuration panel - neither is called by the ported frontend
 * screen and both depend on out-of-scope tables
 * (`s_competency_frameworks`, `s_proficiency_levels`, a settings store,
 * `s_competency_activity_log`), so they are not ported here.
 *
 * Adaptations from the source (all schema-driven, not stylistic):
 *   - `framework_name` is always null: `s_competency_frameworks` is out of
 *     scope for this package (see the cycles table migration's docblock),
 *     so a `framework_id` cannot be resolved to a name here.
 *   - No `s_competency_activity_log` write: out of scope. `createCampaign`
 *     and `reviewAssessment` instead log to `system_audit_logs` via
 *     `App\Models\AuditLog::record()`, module `g2g_lms_assessments`.
 */
class AssessmentCycleController extends Controller
{
    use ResolvesLmsIdentity;

    private function audit(Request $request, string $entityType, $entityId, string $action, array $changes = []): void
    {
        $context = $this->lmsContext($request);

        AuditLog::record([
            'sub_institute_id' => $context['sub_institute_id'],
            'actor_id'         => $context['user_id'] ?: null,
            'module'           => 'g2g_lms_assessments',
            'action'           => $entityType . '.' . $action,
            'entity_type'      => $entityType,
            'entity_id'        => (string) $entityId,
            'new_values'       => $changes ?: null,
        ]);
    }

    /** GET /assessment-cycles */
    public function index(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];

        $query = DB::table('s_competency_assessment_cycles')->where('sub_institute_id', $sid)->whereNull('deleted_at');

        if ($status = trim((string) $request->input('status', ''))) {
            if ($status === 'progress') {
                $query->whereIn('status', ['active', 'scheduled']);
            } else {
                $query->where('status', $status);
            }
        }

        $cycles = $query->orderByDesc('id')->get();

        $cycleIds = $cycles->pluck('id')->toArray();
        $assessments = DB::table('s_competency_assessments')
            ->where('sub_institute_id', $sid)
            ->whereIn('cycle_id', $cycleIds ?: [0])
            ->whereNull('deleted_at')
            ->select('cycle_id', 'status')->get();

        $stats = [];
        foreach ($assessments as $a) {
            $stats[$a->cycle_id] ??= ['total' => 0, 'completed' => 0];
            $stats[$a->cycle_id]['total']++;
            if ($a->status === 'completed') {
                $stats[$a->cycle_id]['completed']++;
            }
        }

        $data = $cycles->map(function ($c) use ($stats) {
            $total = $stats[$c->id]['total'] ?? 0;
            $completed = $stats[$c->id]['completed'] ?? 0;
            $completion = $total > 0 ? round(($completed / $total) * 100) : 0;

            return [
                'id'             => (string) $c->id,
                'name'           => $c->name,
                'framework_id'   => $c->framework_id ? (int) $c->framework_id : null,
                // s_competency_frameworks is out of scope for this package -
                // see class docblock. Never invented from a lookup that
                // does not exist.
                'framework_name' => null,
                'type'           => $c->type,
                'type_is_set'    => $c->type !== null && $c->type !== '',
                'participants'   => $total,
                'completion'     => $completion,
                'status'         => $c->status === 'closed' ? 'Completed' : 'In Progress',
                'date'           => $c->end_date ? date('d M Y', strtotime($c->end_date)) : 'N/A',
                'start_date'     => $c->start_date,
            ];
        });

        return response()->json(['status' => 1, 'message' => 'Campaigns fetched successfully', 'data' => $data]);
    }

    /** POST /assessment-cycles */
    public function store(Request $request)
    {
        $context = $this->lmsContext($request);

        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:191',
            'type'         => 'nullable|string|max:100',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date',
            'framework_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $id = DB::table('s_competency_assessment_cycles')->insertGetId([
            'sub_institute_id' => $context['sub_institute_id'],
            'name'             => $request->input('name'),
            'type'             => $request->input('type'),
            'framework_id'     => $request->input('framework_id'),
            'start_date'       => $request->input('start_date'),
            'end_date'         => $request->input('end_date'),
            'status'           => 'active',
            'created_by'       => $context['user_id'] ?: null,
            'updated_by'       => $context['user_id'] ?: null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->audit($request, 'assessment_cycle', $id, 'create', ['name' => $request->input('name')]);

        return response()->json(['status' => 1, 'message' => 'Campaign created successfully', 'data' => ['id' => $id]], 201);
    }

    /** GET /assessment-cycles/metrics */
    public function metrics(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];

        $activeCampaigns = DB::table('s_competency_assessment_cycles')
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->whereIn('status', ['active', 'scheduled'])->count();

        $assessments = DB::table('s_competency_assessments')
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->select('status', 'review_status')->get();

        $totalAssessments     = $assessments->count();
        $completedAssessments = $assessments->where('status', 'completed')->count();
        $overallCompletionPercent = $totalAssessments > 0 ? round(($completedAssessments / $totalAssessments) * 100) : 0;

        $pendingManagerRatings = $assessments->where('status', 'completed')->whereNull('review_status')->count();
        $pendingCalibration    = $assessments->where('status', 'completed')->where('review_status', 'pending_review')->count();

        return response()->json([
            'status'  => 1,
            'message' => 'Metrics fetched successfully',
            'data'    => [
                'active_campaigns'           => $activeCampaigns,
                'overall_completion_percent' => $overallCompletionPercent,
                'completed_assessments'      => $completedAssessments,
                'total_assessments'          => $totalAssessments,
                'pending_manager_ratings'    => $pendingManagerRatings,
                'pending_calibration'        => $pendingCalibration,
            ],
        ]);
    }

    /* ─── Workspace top-tab lists (all over s_competency_assessments) ───────── */

    private function assessmentBase(int $sid)
    {
        return DB::table('s_competency_assessments as a')
            ->leftJoin('tbluser as u', 'a.user_id', '=', 'u.id')
            ->leftJoin('s_competency_assessment_cycles as c', 'a.cycle_id', '=', 'c.id')
            ->where('a.sub_institute_id', $sid)
            ->whereNull('a.deleted_at')
            ->select(
                'a.id as assessment_id', 'a.user_id', 'a.jobrole', 'a.status', 'a.review_status',
                'a.score', 'a.completed_at', 'a.due_date',
                'u.first_name', 'u.last_name', 'u.employee_no',
                'c.name as cycle_name'
            );
    }

    private function mapAssessment($a): array
    {
        $fname = $a->first_name ?: 'Unknown';
        $lname = $a->last_name ?: '';

        return [
            'assessment_id' => (string) $a->assessment_id,
            'name'          => trim($fname . ' ' . $lname),
            'initials'      => strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1)),
            'emp_id'        => $a->employee_no ?: '',
            'role'          => $a->jobrole ?: 'N/A',
            'campaign'      => $a->cycle_name ?: '—',
            'self'          => in_array($a->status, ['completed', 'overdue'], true),
            'manager'       => $a->review_status === 'reviewed',
            'score'         => $a->score !== null ? (float) $a->score : null,
            'status'        => $a->status,
            'review_status' => $a->review_status,
            'date'          => $a->completed_at
                ? date('d M Y', strtotime($a->completed_at))
                : ($a->due_date ? date('d M Y', strtotime($a->due_date)) : null),
        ];
    }

    /** GET /assessment-cycles/participant-ratings */
    public function participantRatings(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];
        $rows = $this->assessmentBase($sid)->orderByDesc('a.id')->limit(300)->get();

        return response()->json(['status' => 1, 'message' => 'Participant ratings fetched successfully', 'data' => $rows->map(fn ($a) => $this->mapAssessment($a))->all()]);
    }

    /** GET /assessment-cycles/calibration */
    public function calibration(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];
        $rows = $this->assessmentBase($sid)
            ->where('a.status', 'completed')->where('a.review_status', 'pending_review')
            ->orderByDesc('a.id')->limit(300)->get();

        return response()->json(['status' => 1, 'message' => 'Calibration queue fetched successfully', 'data' => $rows->map(fn ($a) => $this->mapAssessment($a))->all()]);
    }

    /** GET /assessment-cycles/approvals */
    public function approvals(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];
        $rows = $this->assessmentBase($sid)
            ->where('a.status', 'completed')->whereNull('a.review_status')
            ->orderByDesc('a.id')->limit(300)->get();

        return response()->json(['status' => 1, 'message' => 'Approvals queue fetched successfully', 'data' => $rows->map(fn ($a) => $this->mapAssessment($a))->all()]);
    }

    /** GET /assessment-cycles/closed */
    public function closed(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];

        $cycles = DB::table('s_competency_assessment_cycles')
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')->where('status', 'closed')
            ->orderByDesc('id')->get();

        $cycleIds = $cycles->pluck('id')->toArray();
        $assessments = DB::table('s_competency_assessments')
            ->where('sub_institute_id', $sid)->whereIn('cycle_id', $cycleIds ?: [0])->whereNull('deleted_at')
            ->select('cycle_id', 'status')->get();

        $stats = [];
        foreach ($assessments as $a) {
            $stats[$a->cycle_id] ??= ['total' => 0, 'completed' => 0];
            $stats[$a->cycle_id]['total']++;
            if ($a->status === 'completed') {
                $stats[$a->cycle_id]['completed']++;
            }
        }

        $data = $cycles->map(function ($c) use ($stats) {
            $total = $stats[$c->id]['total'] ?? 0;
            $completed = $stats[$c->id]['completed'] ?? 0;
            return [
                'id'           => (string) $c->id,
                'name'         => $c->name,
                'type'         => $c->type ?: 'Self + Manager',
                'participants' => $total,
                'completion'   => $total > 0 ? round(($completed / $total) * 100) : 0,
                'status'       => 'Completed',
                'start_date'   => $c->start_date ? date('d M Y', strtotime($c->start_date)) : null,
                'date'         => $c->end_date ? date('d M Y', strtotime($c->end_date)) : 'N/A',
            ];
        });

        return response()->json(['status' => 1, 'message' => 'Closed campaigns fetched successfully', 'data' => $data]);
    }

    /** GET /assessment-cycles/{id}/participants */
    public function participants(Request $request, $id)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];

        $assessments = DB::table('s_competency_assessments as a')
            ->leftJoin('tbluser as u', 'a.user_id', '=', 'u.id')
            ->where('a.sub_institute_id', $sid)->where('a.cycle_id', $id)->whereNull('a.deleted_at')
            ->select('a.id as assessment_id', 'a.user_id', 'a.jobrole', 'a.status', 'a.review_status', 'a.completed_at', 'u.first_name', 'u.last_name', 'u.employee_no')
            ->get();

        $data = $assessments->map(function ($a) {
            $fname = $a->first_name ?: 'Unknown';
            $lname = $a->last_name ?: '';
            $selfCompleted = in_array($a->status, ['completed', 'overdue'], true);
            $managerCompleted = $a->review_status === 'reviewed';

            $statusLabel = 'Not Started';
            if ($managerCompleted) {
                $statusLabel = 'Completed';
            } elseif ($a->review_status === 'pending_review' || ($selfCompleted && !$managerCompleted)) {
                $statusLabel = 'Pending Manager';
            } elseif ($a->status === 'in_progress') {
                $statusLabel = 'In Progress';
            } elseif ($a->status === 'overdue') {
                $statusLabel = 'Overdue';
            }

            return [
                'id'            => (string) $a->user_id,
                'assessment_id' => (string) $a->assessment_id,
                'name'          => trim($fname . ' ' . $lname),
                'initials'      => strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1)),
                'emp_id'        => $a->employee_no ?: '',
                'role'          => $a->jobrole ?: 'N/A',
                'self'          => $selfCompleted,
                'manager'       => $managerCompleted,
                'status'        => $statusLabel,
                'self_date'     => $a->completed_at ? date('d M', strtotime($a->completed_at)) : null,
                'manager_date'  => null,
            ];
        });

        return response()->json(['status' => 1, 'message' => 'Participants fetched successfully', 'data' => $data]);
    }

    /**
     * PUT /assessment-cycles/assessments/{id}/review
     *
     * Sets review_status only - same as the source, this does NOT move
     * anyone's proficiency: `s_competency_assessments` scores a framework,
     * not a single competency, so approving here records the review
     * decision without inventing a competency-level rating.
     */
    public function reviewAssessment(Request $request, $id)
    {
        $context = $this->lmsContext($request);
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), ['action' => 'required|in:approve,calibrate,reject']);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $assessment = DB::table('s_competency_assessments')->where('id', $id)->where('sub_institute_id', $sid)->whereNull('deleted_at')->first();
        if (!$assessment) {
            return response()->json(['status' => 0, 'message' => 'Assessment not found'], 404);
        }

        $action = $request->input('action');
        $reviewStatus = $action === 'reject' ? 'pending_review' : 'reviewed';

        DB::table('s_competency_assessments')->where('id', $id)->update([
            'review_status' => $reviewStatus,
            'updated_by'    => $context['user_id'] ?: null,
            'updated_at'    => now(),
        ]);

        $this->audit($request, 'assessment', $id, $action, ['review_status' => $reviewStatus]);

        return response()->json([
            'status'             => 1,
            'message'            => 'Assessment ' . $action . 'd successfully',
            'review_status'      => $reviewStatus,
            'proficiency_moved'  => false,
            'proficiency_reason' => 'This assessment scores a framework, not a single competency, so approving it records the review decision without changing any proficiency rating.',
        ]);
    }
}
