<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TalentManagement\TalentEvaluationForm;
use App\Models\TalentManagement\TalentInterviewSchedule;
use App\Models\TalentManagement\TalentJobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported 1:1 from hp_erp's `App\Http\Controllers\talent\feedback\feedbackController`.
 *
 * Auth/tenant: mirrors `OnboardingApiController` — `session()->get('sub_institute_id')`,
 * never request input. This mirrors the source's own G-SEC-23 fix note: the
 * tenant clause on `getAllFeedback` is not optional, it is the only thing
 * that keeps one school's feedback (including a candidate's email address)
 * from leaking into another's.
 */
class FeedbackController extends Controller
{
    private function subInstituteId(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    /**
     * GET /feedback
     * Ported from feedbackController@getAllFeedback.
     */
    public function getAllFeedback(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        if (!$subInstituteId) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $data = DB::table('talent_evaluation_form as tef')
            ->leftJoin('talent_job_postings as tjp', 'tef.job_id', '=', 'tjp.id')
            ->leftJoin('talent_job_applications as tja', 'tef.candidate_id', '=', 'tja.id')
            ->leftJoin('talent_interview_panel as tip', 'tef.panel_id', '=', 'tip.id')
            ->select(
                'tef.*',
                'tjp.title as job_title',
                DB::raw("CONCAT(tja.first_name, ' ', COALESCE(tja.middle_name, ''), ' ', tja.last_name) as candidate_name"),
                'tja.email as candidate_email',
                'tip.panel_name',
                'tja.status as application_status'
            )
            ->where('tef.sub_institute_id', $subInstituteId)
            ->orderBy('tef.created_at', 'DESC')
            ->get()
            ->map(function ($row) {
                // `DB::table()` bypasses TalentEvaluationForm's Eloquent
                // `'evaluation_criteria' => 'array'` cast, so this raw join
                // returns it as a JSON string - decode it here instead of
                // making every caller (feedback view tab, candidate profile
                // interview history) guess and crash on `.map()`/`.reduce()`.
                $row->evaluation_criteria = json_decode($row->evaluation_criteria ?? '', true) ?? [];

                return $row;
            });

        if ($data->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No feedback found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Feedback Found',
            'data' => $data,
        ], 200);
    }

    /**
     * GET /feedback/{candidateId}
     * Ported from feedbackController@getFeedback.
     */
    public function getFeedback(Request $request, $id)
    {
        $subInstituteId = $this->subInstituteId();

        $data = DB::table('talent_evaluation_form as tef')
            ->leftJoin('talent_job_postings as tjp', 'tef.job_id', '=', 'tjp.id')
            ->leftJoin('talent_job_applications as tja', 'tef.candidate_id', '=', 'tja.id')
            ->leftJoin('talent_interview_panel as tip', 'tef.panel_id', '=', 'tip.id')
            ->select(
                'tef.*',
                'tjp.title as job_title',
                DB::raw("CONCAT(tja.first_name, ' ', COALESCE(tja.middle_name, ''), ' ', tja.last_name) as candidate_name"),
                'tja.email as candidate_email',
                'tip.panel_name',
                'tja.status as application_status'
            )
            ->where('tef.candidate_id', $id)
            ->where('tef.sub_institute_id', $subInstituteId)
            ->orderBy('tef.created_at', 'DESC')
            ->first();

        if ($data) {
            // Same raw-query cast gap as getAllFeedback() above.
            $data->evaluation_criteria = json_decode($data->evaluation_criteria ?? '', true) ?? [];
        }

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'No feedback found for this candidate',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Feedback Found',
            'data' => $data,
        ], 200);
    }

    /**
     * POST /evaluation
     * Ported from feedbackController@storeFeedback.
     */
    public function storeFeedback(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        if (is_string($request->evaluation_criteria)) {
            $request->merge(['evaluation_criteria' => json_decode($request->evaluation_criteria, true)]);
        }

        $request->validate([
            'job_id' => 'required|integer',
            'candidate_id' => 'required|integer',
            'panel_id' => 'required|integer',

            'evaluation_criteria' => 'required|array',
            'evaluation_criteria.*.name' => 'required|string',
            'evaluation_criteria.*.score' => 'required|numeric|min:1|max:10',

            'recommendation' => 'nullable|string',
            'key_strengths' => 'nullable|string',
            'areas_of_concern' => 'nullable|string',
            'additional_comments' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:draft,submitted,approved,rejected',
        ]);

        $evaluation = TalentEvaluationForm::create([
            'job_id' => $request->job_id,
            'candidate_id' => $request->candidate_id,
            'panel_id' => $request->panel_id,
            'evaluation_criteria' => $request->evaluation_criteria,
            'recommendation' => $request->recommendation,
            'key_strengths' => $request->key_strengths,
            'areas_of_concern' => $request->areas_of_concern,
            'additional_comments' => $request->additional_comments,
            'sub_institute_id' => $subInstituteId,
            'notes' => $request->notes,
            'status' => $request->status ?? 'draft',
        ]);

        TalentJobApplication::where('id', $request->candidate_id)->update(['status' => 'Completed']);

        TalentInterviewSchedule::where('applicant_id', $request->candidate_id)
            ->where('job_id', $request->job_id)
            ->update(['status' => 'Completed']);

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'feedback_submitted',
            'entity_type' => 'evaluation_form',
            'entity_id' => $evaluation->id,
            'new_values' => [
                'job_id' => $request->job_id,
                'candidate_id' => $request->candidate_id,
                'panel_id' => $request->panel_id,
                'recommendation' => $request->recommendation,
                'status' => $request->status ?? 'draft',
            ],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Feedback saved successfully',
        ], 200);
    }

    /**
     * PUT /feedback/{id}
     * Ported from feedbackController@updateFeedback.
     */
    public function updateFeedback(Request $request, $id)
    {
        $subInstituteId = $this->subInstituteId();

        if (is_string($request->evaluation_criteria)) {
            $request->merge(['evaluation_criteria' => json_decode($request->evaluation_criteria, true)]);
        }

        $request->validate([
            'job_id' => 'required|integer',
            'candidate_id' => 'required|integer',
            'panel_id' => 'required|integer',

            'evaluation_criteria' => 'required|array',
            'evaluation_criteria.*.name' => 'required|string',
            'evaluation_criteria.*.score' => 'required|numeric|min:1|max:10',

            'recommendation' => 'nullable|string',
            'key_strengths' => 'nullable|string',
            'areas_of_concern' => 'nullable|string',
            'additional_comments' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:draft,submitted,approved,rejected',
        ]);

        $evaluation = TalentEvaluationForm::find($id);
        if (!$evaluation) {
            return response()->json([
                'status' => false,
                'message' => 'Feedback not found',
            ], 404);
        }

        $evaluation->update([
            'job_id' => $request->job_id,
            'candidate_id' => $request->candidate_id,
            'panel_id' => $request->panel_id,
            'evaluation_criteria' => $request->evaluation_criteria,
            'recommendation' => $request->recommendation,
            'key_strengths' => $request->key_strengths,
            'areas_of_concern' => $request->areas_of_concern,
            'additional_comments' => $request->additional_comments,
            'sub_institute_id' => $subInstituteId,
            'notes' => $request->notes,
            'status' => $request->status,
        ]);

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'feedback_updated',
            'entity_type' => 'evaluation_form',
            'entity_id' => $evaluation->id,
            'new_values' => [
                'job_id' => $request->job_id,
                'candidate_id' => $request->candidate_id,
                'panel_id' => $request->panel_id,
                'recommendation' => $request->recommendation,
                'status' => $request->status,
            ],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Feedback updated successfully',
        ], 200);
    }

    /**
     * DELETE /feedback/{id}
     *
     * NOT present anywhere in hp_erp: `feedbackController` has no destroy
     * method and no DELETE route is bound for `/feedback/{id}` there. This is
     * a reconstruction, not a port - a tenant-scoped soft delete of the
     * evaluation row, following the same `deleted_by`/soft-delete convention
     * every other destroy() in this feature uses. Flagged in the porting
     * report as not ported 1:1 (no source to port from).
     */
    public function destroy(Request $request, $id)
    {
        $subInstituteId = $this->subInstituteId();

        $evaluation = TalentEvaluationForm::where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$evaluation) {
            return response()->json([
                'status' => false,
                'message' => 'Feedback not found',
            ], 404);
        }

        $evaluation->deleted_by = session()->get('user_id');
        $evaluation->save();
        $evaluation->delete();

        AuditLog::record([
            'module' => 'talent_management',
            'action' => 'feedback_deleted',
            'entity_type' => 'evaluation_form',
            'entity_id' => $evaluation->id,
            'new_values' => [
                'candidate_id' => $evaluation->candidate_id,
                'job_id' => $evaluation->job_id,
            ],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Feedback deleted successfully',
        ], 200);
    }

    /**
     * GET /pending-feedback
     * Ported from feedbackController@getPendingFeedback (API branch only).
     */
    public function getPendingFeedback(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $data = DB::table('talent_interview_schedules as tis')
            ->leftJoin('talent_job_postings as tjp', 'tis.job_id', '=', 'tjp.id')
            ->leftJoin('talent_job_applications as tja', 'tis.applicant_id', '=', 'tja.id')
            ->leftJoin('talent_interview_panel as tip', 'tis.panel_id', '=', 'tip.id')
            ->select(
                'tis.*',
                'tjp.title as job_title',
                DB::raw("CONCAT(tja.first_name, ' ', COALESCE(tja.middle_name, ''), ' ', tja.last_name) as candidate_name"),
                'tja.email as candidate_email',
                'tip.panel_name'
            )
            ->where('tis.sub_institute_id', $subInstituteId)
            ->where('tis.status', '!=', 'Completed')
            ->orderBy('tis.created_at', 'DESC')
            ->get();

        $count = $data->count();

        if ($data->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No pending feedback found',
                'count' => 0,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Pending feedback found',
            'count' => $count,
            'data' => $data,
        ], 200);
    }
}
