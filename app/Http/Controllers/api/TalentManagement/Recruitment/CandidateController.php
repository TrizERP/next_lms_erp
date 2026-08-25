<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported 1:1 from hp_erp's `App\Http\Controllers\talent\candidate\candidateController`.
 *
 * Auth/tenant: mirrors `OnboardingApiController` — `session()->get('sub_institute_id')`,
 * never request input.
 *
 * NOTE on shape: the porting brief's ground-truth endpoint list describes
 * `GET /candidate` as returning a `CandidateKanbanResponse` with
 * `data`/`stage_counts`/`total`/`filters`. That shape does not exist anywhere
 * in hp_erp — `candidateController@getCandidate` (the only implementation of
 * this route, bound at `Route::get('/candidate', ...)`) returns a flat
 * `{status, message, data}` array of candidate rows with no stage_counts,
 * total or filters wrapper. This is ported EXACTLY as hp_erp implements it
 * today (flat list), not as the frontend type describes it — the type appears
 * to be aspirational/out of sync with the actual backend, and inventing a
 * kanban aggregation here would not be a port, it would be new functionality.
 * Flagged in the porting report.
 */
class CandidateController extends Controller
{
    private function subInstituteId(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    /**
     * GET /candidate
     * Ported from candidateController@getCandidate.
     */
    public function getCandidate(Request $request)
    {
        $subInstituteId = $this->subInstituteId();

        $data = DB::table('talent_job_applications as tja')
            ->leftJoin('talent_interview_schedules as tis', function ($join) {
                $join->on('tja.id', '=', 'tis.applicant_id')
                    ->whereRaw('tis.round_no = (SELECT MAX(round_no) FROM talent_interview_schedules WHERE applicant_id = tja.id)');
            })
            ->leftJoin('talent_job_postings as tjp', 'tja.job_id', '=', 'tjp.id')
            ->leftJoin('talent_interview_panel as tip', 'tis.panel_id', '=', 'tip.id')
            ->select(
                'tja.id as candidate_id',
                DB::raw("CONCAT(tja.first_name,' ',tja.last_name,' ',tja.email) AS candidate_name"),
                'tjp.id as position_id',
                'tjp.title as position',
                'tja.status',
                'tis.status as stage',
                'tja.applied_date',
                DB::raw("CONCAT(tis.interview_date,' ',tis.time) AS next_interview"),
                'tis.rating as score',
                'tis.panel_id',
                'tip.panel_name'
            )
            ->where('tja.sub_institute_id', $subInstituteId)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Candidate List',
            'data' => $data,
        ], 200);
    }
}
