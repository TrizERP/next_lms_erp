<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Ported 1:1 from hp_erp's `App\Services\TalentAcquisitionService`.
 * Backs `AcquisitionController@getKpis` (POST /talent-acquisition/kpis).
 */
class TalentAcquisitionService
{
    public function getKpiMetrics($subInstituteId)
    {
        $data = [];

        // Open Positions
        $openPositions = DB::table('talent_job_postings')->where('status', 'active')->whereDate('deadline', '>=', Carbon::today())->where('sub_institute_id', $subInstituteId)->count();
        $data['open_positions'] = $openPositions;

        // Interview-to-Offer Ratio
        $interviews = DB::table('talent_job_applications')
            ->whereNotNull('created_at')
            ->where('sub_institute_id', $subInstituteId)
            ->count();

        $offers = DB::table('talent_job_applications')
            ->where('status', 'Hired')
            ->where('sub_institute_id', $subInstituteId)
            ->count();

        $data['interview_to_offer_ratio'] = $interviews > 0
            ? round($offers / $interviews, 2)
            : 0;

        // Drop-off Rate
        $totalCandidates = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->count();
        $droppedCandidates = DB::table('talent_job_applications')->where('status', 'Rejected')->where('sub_institute_id', $subInstituteId)->count();
        $data['drop_off_rate'] = $totalCandidates > 0 ? ($droppedCandidates / $totalCandidates) * 100 : 0;

        return $data;
    }
}
