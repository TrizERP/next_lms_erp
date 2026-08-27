<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\MappingReviewController`.
 *
 * Confirmed in scope by reading the G2G frontend's `services/competency/studio.ts`
 * (the Competency Framework screen's service layer): `listReviews`/
 * `submitReview`/`reviewAction`/`bulkApprove` are called from the Role Mapping
 * Matrix's Workflow & Review tab, so this controller is ported alongside
 * Framework/Studio rather than skipped.
 *
 * Role-mapping change approvals, backed by `s_competency_mapping_reviews`
 * (created by this port's `2026_08_24_090000_create_competency_framework_studio_tables.php`
 * migration - no new table needed here). As-is port: same logic, field names
 * and response shapes as the source - only tenant/actor context resolution
 * was adapted, per `ResolvesCompetencyContext`.
 */
class MappingReviewController extends Controller
{
    use ResolvesCompetencyContext;

    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);
        $page = max((int) $request->input('page', 1), 1);
        $status = $this->activeFilter($request->input('status')) ?? 'pending';

        $base = DB::table('s_competency_mapping_reviews')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at');

        // Tab counts (independent of the current filter).
        $counts = [
            'pending'  => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ];

        $query = (clone $base)->where('status', $status);
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $perPage)->get();

        $data = $rows->map(fn ($r) => [
            'id'                => (int) $r->id,
            'jobrole'           => $r->jobrole,
            'department'        => $r->department,
            'framework_id'      => $r->framework_id ? (int) $r->framework_id : null,
            'submitted_by_name' => $r->submitted_by_name,
            'status'            => $r->status,
            'changes_count'     => (int) $r->changes_count,
            'changes'           => $r->changes,
            'note'              => $r->note,
            'submitted_at'      => $r->created_at ? Carbon::parse($r->created_at)->format('M j, Y') : null,
            'reviewed_at'       => $r->reviewed_at ? Carbon::parse($r->reviewed_at)->format('M j, Y') : null,
        ])->all();

        return response()->json([
            'status'     => 1,
            'message'    => 'Mapping reviews fetched successfully',
            'data'       => $data,
            'counts'     => $counts,
            'pagination' => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $total,
                'last_page' => (int) max(ceil($total / $perPage), 1),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'jobrole'       => 'required|string|max:191',
            'department'    => 'nullable|string|max:191',
            'department_id' => 'nullable|integer',
            'framework_id'  => 'nullable|integer',
            'changes_count' => 'nullable|integer|min:0',
            'changes'       => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Resolve the submitter's display name for the queue card.
        $submitterName = null;
        if ($context['user_id']) {
            $user = DB::table('tbluser')->where('id', $context['user_id'])->first();
            if ($user) {
                $submitterName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                $submitterName = $submitterName !== '' ? $submitterName : ($user->user_name ?? null);
            }
        }

        $id = DB::table('s_competency_mapping_reviews')->insertGetId([
            'sub_institute_id'  => $sid,
            'jobrole'           => $request->input('jobrole'),
            'department'        => $request->input('department'),
            'department_id'     => $request->input('department_id'),
            'framework_id'      => $request->input('framework_id'),
            'submitted_by'      => $context['user_id'],
            'submitted_by_name' => $submitterName,
            'status'            => 'pending',
            'changes_count'     => (int) $request->input('changes_count', 0),
            'changes'           => $request->input('changes'),
            'created_by'        => $context['user_id'],
            'updated_by'        => $context['user_id'],
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            'submitted_mapping_review',
            'Submitted role mapping for "' . $request->input('jobrole') . '" for review',
            'mapping_review',
            $id,
            $request->input('jobrole')
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Mapping submitted for review',
            'data'    => ['id' => $id],
        ], 201);
    }

    /** Approve or reject one review. */
    public function update(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'note'   => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $review = DB::table('s_competency_mapping_reviews')
            ->where('id', $id)
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->first();
        if (!$review) {
            return response()->json(['status' => 0, 'message' => 'Review not found'], 404);
        }

        $status = $request->input('action') === 'approve' ? 'approved' : 'rejected';

        DB::table('s_competency_mapping_reviews')->where('id', $id)->update([
            'status'      => $status,
            'note'        => $request->input('note'),
            'reviewer_id' => $context['user_id'],
            'reviewed_at' => now(),
            'updated_by'  => $context['user_id'],
            'updated_at'  => now(),
        ]);

        $this->logCompetencyActivity(
            $sid,
            $context['user_id'],
            $status . '_mapping_review',
            ucfirst($status) . ' role mapping for "' . $review->jobrole . '"',
            'mapping_review',
            (int) $id,
            $review->jobrole,
            $this->diffChanges($review, ['status' => $status], ['status' => 'Review Status'])
        );

        return response()->json(['status' => 1, 'message' => 'Review ' . $status . ' successfully']);
    }

    /** Approve many at once (explicit ids, or all pending when none given). */
    public function bulkApprove(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }
        $sid = $context['sub_institute_id'];

        $query = DB::table('s_competency_mapping_reviews')
            ->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')
            ->where('status', 'pending');

        $ids = $request->input('ids');
        if (is_array($ids) && !empty($ids)) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            $query->whereIn('id', empty($ids) ? [0] : $ids);
        }

        // Capture the roles before the update so the audit entry can name them.
        $roles = (clone $query)->limit(20)->pluck('jobrole')->all();

        $affected = $query->update([
            'status'      => 'approved',
            'reviewer_id' => $context['user_id'],
            'reviewed_at' => now(),
            'updated_by'  => $context['user_id'],
            'updated_at'  => now(),
        ]);

        if ($affected > 0) {
            $this->logCompetencyActivity(
                $sid,
                $context['user_id'],
                'bulk_approved_mapping_reviews',
                'Approved ' . $affected . ' role mapping review' . ($affected === 1 ? '' : 's')
                    . ($roles ? ' (' . implode(', ', array_slice($roles, 0, 3))
                        . (count($roles) > 3 ? ', ...' : '') . ')' : ''),
                'mapping_review',
                null,
                $affected === 1 && $roles ? $roles[0] : ($affected . ' reviews')
            );
        }

        return response()->json([
            'status'  => 1,
            'message' => $affected . ' review(s) approved',
            'data'    => ['approved' => $affected],
        ]);
    }
}
