<?php

namespace App\Http\Controllers\api\TalentManagement\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\TalentAcquisitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ported 1:1 from hp_erp's
 * `App\Http\Controllers\talent\TalentAcquisition\CandidateDropoffController`
 * and `App\Http\Controllers\talent\TalentAcquisition\TalentAcquisitionController`
 * — merged into one controller here since both are the "Acquisition"
 * dashboard/analytics surface of the same feature area and take the same
 * request shape (`department_id`, `experience` filters + tenant scope).
 *
 * Auth/tenant: mirrors `OnboardingApiController` — `session()->get('sub_institute_id')`,
 * never request input.
 */
class AcquisitionController extends Controller
{
    private function subInstituteId(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function maskSensitiveData(array $data): array
    {
        $masked = $data;
        $sensitiveFields = ['token', 'password', 'secret'];

        foreach ($sensitiveFields as $field) {
            if (isset($masked[$field])) {
                $masked[$field] = '***MASKED***';
            }
        }

        return $masked;
    }

    /**
     * POST /talent-acquisition/dropoff
     * Ported from CandidateDropoffController@getDropoff.
     */
    public function getDropoff(Request $request)
    {
        try {
            Log::info('Talent Acquisition Dropoff request', [
                'payload' => $this->maskSensitiveData($request->all()),
            ]);

            $subInstituteId = $this->subInstituteId();
            $departmentId = $request->input('department_id');
            $experience = $request->input('experience');

            $data = DB::table('talent_job_applications as a')
                ->join('talent_job_postings as jp', 'a.job_id', '=', 'jp.id')
                ->select(
                    DB::raw("CASE
                        WHEN a.status = 'Pending Review' THEN 'Application'
                        WHEN a.status = 'Shortlisted' THEN 'Shortlist'
                        WHEN a.status = 'Completed' THEN 'Interview'
                        WHEN a.status = 'Hired' THEN 'Offer'
                        WHEN a.status = 'Rejected' THEN 'Rejected'
                        ELSE a.status
                    END as stage"),
                    DB::raw('0 as voluntary'),
                    DB::raw('COUNT(*) as involuntary')
                )
                ->where('a.sub_institute_id', $subInstituteId)
                ->whereIn('a.status', ['Pending Review', 'Under Review', 'Shortlisted', 'Completed', 'Hired', 'Rejected'])
                ->when($departmentId, function ($query) use ($departmentId) {
                    return $query->where('jp.department_id', $departmentId);
                })
                ->when($experience, function ($query) use ($experience) {
                    return $query->where('a.experience', 'like', '%' . $experience . '%');
                })
                ->groupBy(DB::raw("CASE
                    WHEN a.status = 'Pending Review' THEN 'Application'
                    WHEN a.status = 'Shortlisted' THEN 'Shortlist'
                    WHEN a.status = 'Completed' THEN 'Interview'
                    WHEN a.status = 'Hired' THEN 'Offer'
                    WHEN a.status = 'Rejected' THEN 'Rejected'
                    ELSE a.status
                END"))
                ->orderByRaw("FIELD(CASE
                    WHEN a.status = 'Pending Review' THEN 'Application'
                    WHEN a.status = 'Shortlisted' THEN 'Shortlist'
                    WHEN a.status = 'Completed' THEN 'Interview'
                    WHEN a.status = 'Hired' THEN 'Offer'
                    WHEN a.status = 'Rejected' THEN 'Rejected'
                    ELSE a.status
                END, 'Application', 'Shortlist', 'Interview', 'Offer', 'Rejected')")
                ->get();

            $allStages = ['Application', 'Shortlist', 'Interview', 'Offer', 'Rejected'];
            $stageData = [];
            foreach ($allStages as $stage) {
                $existing = $data->firstWhere('stage', $stage);
                $stageData[] = [
                    'stage' => $stage,
                    'voluntary' => $existing ? $existing->voluntary : 0,
                    'involuntary' => $existing ? $existing->involuntary : 0,
                ];
            }

            return response()->json([
                'status' => true,
                'data' => $stageData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to load drop-off data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /talent-acquisition/funnel
     * Ported from CandidateDropoffController@getFunnelData.
     */
    public function getFunnelData(Request $request)
    {
        try {
            Log::info('Talent Acquisition Funnel request', [
                'payload' => $this->maskSensitiveData($request->all()),
            ]);

            $subInstituteId = $this->subInstituteId();
            $departmentId = $request->input('department_id');
            $experience = $request->input('experience');

            $baseQuery = DB::table('talent_job_applications as a')
                ->join('talent_job_postings as jp', 'a.job_id', '=', 'jp.id')
                ->where('a.sub_institute_id', $subInstituteId);

            if ($departmentId) {
                $baseQuery->where('jp.department_id', $departmentId);
            }

            if ($experience) {
                $baseQuery->where('a.experience', 'like', '%' . $experience . '%');
            }

            $applications = (clone $baseQuery)->count();

            $shortlisted = (clone $baseQuery)
                ->where('a.status', 'Shortlisted')
                ->count();

            $interviewed = (clone $baseQuery)
                ->where('a.status', 'Completed')
                ->count();

            $offers = (clone $baseQuery)
                ->where('a.status', 'offered')
                ->count();

            $hired = (clone $baseQuery)
                ->where('a.status', 'Hired')
                ->count();

            $funnel = [
                ['name' => 'Applications', 'value' => $applications],
                ['name' => 'Shortlisted', 'value' => $shortlisted],
                ['name' => 'Interviewed', 'value' => $interviewed],
                ['name' => 'Offers', 'value' => $offers],
                ['name' => 'Hired', 'value' => $hired],
            ];

            return response()->json([
                'success' => true,
                'data' => $funnel,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error while fetching recruitment funnel.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /talent-acquisition/requisitions
     * Ported from CandidateDropoffController@getRequisitions.
     * (page/limit/sortBy/order read from the request body, same as source —
     * POST with a JSON/form body rather than query string.)
     */
    public function getRequisitions(Request $request)
    {
        try {
            Log::info('Talent Acquisition Requisitions request', [
                'payload' => $this->maskSensitiveData($request->all()),
            ]);

            $subInstituteId = $this->subInstituteId();

            $page = $request->get('page', 1);
            $limit = $request->get('limit', 10);

            $department = $request->get('department', 'all-dept');
            $location = $request->get('location', 'all-loc');
            $timePeriod = $request->get('timePeriod', 'monthly');
            $jobLevel = $request->get('jobLevel', 'all-level');
            $diversity = $request->get('diversity', 'all-gender');
            $status = $request->get('status', 'active');
            $experience = $request->get('experience', null);

            $sortBy = $request->get('sortBy', 'age');
            $order = $request->get('order', 'desc');

            $query = DB::table('talent_job_postings as r')
                ->selectRaw('
                    r.id,
                    r.title,
                    d.department as department,
                    r.location,
                    r.priority_level as job_level,
                    r.status,
                    DATEDIFF(CURDATE(), r.created_at) AS age,
                    COUNT(DISTINCT i.id) AS interviewed,
                    COUNT(DISTINCT o.id) AS offers,
                    COUNT(DISTINCT h.id) AS hires
                ')
                ->leftJoin('hrms_departments as d', 'r.department_id', '=', 'd.id')
                ->leftJoin('talent_interview_schedules as i', 'i.job_id', '=', 'r.id')
                ->leftJoin('talent_offers as o', 'o.job_id', '=', 'r.id')
                ->leftJoin('talent_job_applications as h', function ($join) {
                    $join->on('h.job_id', '=', 'r.id')
                        ->where('h.status', '=', 'Hired');
                })
                ->where('r.sub_institute_id', $subInstituteId)
                ->groupBy('r.id', 'd.department', 'r.title', 'r.location', 'r.priority_level', 'r.status', 'r.created_at');

            if ($department !== 'all-dept') {
                $query->where('d.department', $department);
            }

            if ($location !== 'all-loc') {
                $query->where('r.location', $location);
            }

            if ($jobLevel !== 'all-level') {
                $query->where('r.priority_level', $jobLevel);
            }

            if ($status !== 'all') {
                $query->where('r.status', ucfirst($status));
            }

            if ($experience) {
                $query->where('r.experience', 'like', '%' . $experience . '%');
            }

            if ($timePeriod === 'weekly') {
                $query->where('r.created_at', '>=', now()->subDays(7));
            }
            if ($timePeriod === 'monthly') {
                $query->where('r.created_at', '>=', now()->subDays(30));
            }
            if ($timePeriod === 'quarterly') {
                $query->where('r.created_at', '>=', now()->subDays(90));
            }

            $allowedSort = [
                'age' => 'age',
                'title' => 'r.title',
                'interviewed' => 'interviewed',
                'offers' => 'offers',
                'hires' => 'hires',
            ];

            $sortColumn = $allowedSort[$sortBy] ?? 'age';

            $offset = ($page - 1) * $limit;

            $countQuery = clone $query;
            $total = $countQuery->count(DB::raw('distinct r.id'));

            $query->orderBy($sortColumn, $order);

            $records = $query
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'data' => $records,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch requisitions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /talent-acquisition/kpis
     * Ported from TalentAcquisitionController@getKpis. Not in the
     * ground-truth endpoint list, but part of the same source controller
     * pairing — ported for completeness.
     */
    public function getKpis(Request $request, TalentAcquisitionService $service)
    {
        try {
            Log::info('Talent Acquisition KPIs request', [
                'payload' => $this->maskSensitiveData($request->all()),
            ]);

            $subInstituteId = $this->subInstituteId();

            $data = $service->getKpiMetrics($subInstituteId);

            return response()->json($data, 200);
        } catch (\Exception $e) {
            Log::error('Talent Acquisition KPIs error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
