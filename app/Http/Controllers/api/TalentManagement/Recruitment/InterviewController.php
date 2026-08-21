<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\TalentManagement\TalentEvaluationForm;
use App\Models\TalentManagement\TalentJobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported 1:1 from hp_erp's `App\Http\Controllers\talent\InterviewController`.
 *
 * Auth/tenant: mirrors `OnboardingApiController` — `session()->get('sub_institute_id')`,
 * never request input.
 */
class InterviewController extends Controller
{
    private function subInstituteId(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    /**
     * GET /positions
     * Ported from InterviewController@getPositions (API branch only).
     */
    public function getPositions(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $positions = DB::table('talent_job_postings')
            ->where('sub_institute_id', $subInstituteId)
            ->select('id', 'title')
            ->distinct()
            ->get();

        return response()->json([
            'status' => 1,
            'data' => $positions,
        ]);
    }

    /**
     * GET /interviewers
     * Ported from InterviewController@getInterviewers (API branch only).
     */
    public function getInterviewers(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $candidates = DB::table('talent_job_applications')
            ->whereRaw('status = "Shortlisted"')
            ->where('sub_institute_id', $subInstituteId)
            ->select(
                'id',
                'job_id',
                DB::raw("CONCAT(first_name, ' ', last_name) as candidate_name"),
                'email',
                'mobile'
            )
            ->get();

        return response()->json([
            'status' => 1,
            'data' => $candidates,
        ]);
    }

    /**
     * POST /interviews/{id}/decision
     * Ported from InterviewController@recordDecision (API branch only).
     */
    public function recordDecision(Request $request, $id)
    {
        $subInstituteId = $this->subInstituteId();

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Under Review,Hired,Rejected',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $evaluation = TalentEvaluationForm::where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (!$evaluation) {
            return response()->json([
                'status' => false,
                'message' => 'Evaluation form not found',
            ], 404);
        }

        $evaluationStatus = '';
        if ($request->status === 'Hired') {
            $evaluationStatus = 'Hired';
        } elseif ($request->status === 'Rejected') {
            $evaluationStatus = 'Rejected';
        } elseif ($request->status === 'Completed') {
            $evaluationStatus = 'Completed';
        }
        $updateData = ['status' => $evaluationStatus];
        if ($request->has('notes')) {
            $updateData['notes'] = $request->notes;
        }
        $evaluation->update($updateData);

        TalentJobApplication::where('id', $evaluation->candidate_id)->update(['status' => $request->status]);

        return response()->json([
            'status' => true,
            'message' => 'Hiring decision recorded successfully',
        ], 200);
    }
}
