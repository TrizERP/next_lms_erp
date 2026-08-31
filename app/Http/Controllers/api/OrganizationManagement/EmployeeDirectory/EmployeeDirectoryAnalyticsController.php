<?php

namespace App\Http\Controllers\api\OrganizationManagement\EmployeeDirectory;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported 1:1 (query logic and response shapes) from G2G's (hp_erp)
 * `App\Http\Controllers\Reports\EmployeeDirectoryAnalytics\EmployeeDirectoryAnalyticsController`.
 *
 * Auth: the source controller resolved tenant via `ResolvesApiIdentity`
 * (bearer token). Here the tenant comes from the session hydrated by the
 * `api.session` middleware, same convention as every other ported module in
 * this generation (see JobPostingController in Talent Management).
 *
 * Table availability, verified against LMS-K12's actual migrations:
 *  - tbluser, hrms_departments: present (Employee Directory columns added by
 *    2026_08_19_175500_add_hr_columns_to_tbluser_table.php).
 *  - talent_job_applications / talent_job_postings: present
 *    (2026_08_18_110000_create_talent_recruitment_tables.php, from the
 *    already-ported Talent Management module) - getKPIs/getGrowthData/
 *    getLifecycle use these exactly as the source does.
 *  - s_users_skills, s_skill_matrix: present - getKPIs/getSkillMatrix use
 *    these exactly as the source does.
 *  - s_user_jobrole: NOT present in LMS-K12. getJobRoleDistribution's join
 *    to it is guarded with Schema::hasTable() and returns an empty dataset
 *    rather than erroring when absent. TODO: port s_user_jobrole (or point
 *    this at whatever job-role table LMS-K12 eventually adopts) to restore
 *    this endpoint fully.
 */
class EmployeeDirectoryAnalyticsController extends Controller
{
    private function tenant(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    /** GET /organization-management/employee-directory/analytics/kpis */
    public function getKPIs(Request $request)
    {
        $subInstituteId = $this->tenant();
        $departmentId = $request->department_id;

        try {
            $currentPeriodStart = Carbon::now()->subDays(30);
            $previousPeriodStart = Carbon::now()->subDays(60);
            $previousPeriodEnd = Carbon::now()->subDays(30);

            $totalEmployeesQuery = DB::table('tbluser')->whereNull('terminated_date')->where('sub_institute_id', $subInstituteId);
            if ($departmentId) {
                $totalEmployeesQuery->where('department_id', $departmentId);
            }
            $totalEmployees = $totalEmployeesQuery->count();

            $previousEmployeesQuery = DB::table('tbluser')->whereNull('terminated_date')
                ->where('joined_date', '<', $previousPeriodEnd)->where('sub_institute_id', $subInstituteId);
            if ($departmentId) {
                $previousEmployeesQuery->where('department_id', $departmentId);
            }
            $previousEmployees = $previousEmployeesQuery->count();

            $totalEmployeeTrend = $this->calculateTrend($totalEmployees, $previousEmployees);

            $newHiresQuery = DB::table('talent_job_applications as tja')->leftJoin('talent_job_postings as tjp', 'tja.job_id', '=', 'tjp.id')->where('tja.status', 'hired')->where('tja.updated_at', '>=', $currentPeriodStart)->where('tja.sub_institute_id', $subInstituteId);
            if ($departmentId) {
                $newHiresQuery->where('tjp.department_id', $departmentId);
            }
            $newHires = $newHiresQuery->count();

            $previousHiresQuery = DB::table('talent_job_applications as tja')->leftJoin('talent_job_postings as tjp', 'tja.job_id', '=', 'tjp.id')->where('tja.status', 'hired')->whereBetween('tja.updated_at', [$previousPeriodStart, $previousPeriodEnd])->where('tja.sub_institute_id', $subInstituteId);
            if ($departmentId) {
                $previousHiresQuery->where('tjp.department_id', $departmentId);
            }
            $previousHires = $previousHiresQuery->count();

            $newHiresTrend = $this->calculateTrend($newHires, $previousHires);

            $currentAttritionQuery = DB::table('tbluser')->where('terminated_date', '>=', $currentPeriodStart)->where('sub_institute_id', $subInstituteId);
            if ($departmentId) {
                $currentAttritionQuery->where('department_id', $departmentId);
            }
            $currentAttrition = $currentAttritionQuery->count();

            $previousAttritionQuery = DB::table('tbluser')->whereBetween('terminated_date', [$previousPeriodStart, $previousPeriodEnd])->where('sub_institute_id', $subInstituteId);
            if ($departmentId) {
                $previousAttritionQuery->where('department_id', $departmentId);
            }
            $previousAttrition = $previousAttritionQuery->count();

            $attritionRate = ($currentAttrition / max($totalEmployees, 1)) * 100;
            $attritionTrend = $this->calculateTrend($currentAttrition, $previousAttrition);

            $growthRate = (($newHires - $currentAttrition) / max($totalEmployees, 1)) * 100;
            $growthTrend = $this->calculateTrend($newHires - $currentAttrition, $previousHires - $previousAttrition);

            $requiredSkillCount = DB::table('s_users_skills')->where('sub_institute_id', $subInstituteId)->where('status', 'Active');
            if ($departmentId) {
                $requiredSkillCount->where('department_id', $departmentId);
            }
            $requiredSkillCount = $requiredSkillCount->count();

            $coveredSkills = DB::table('s_skill_matrix')->join('tbluser', 's_skill_matrix.user_id', '=', 'tbluser.id')->where('tbluser.sub_institute_id', $subInstituteId);
            if ($departmentId) {
                $coveredSkills->where('tbluser.department_id', $departmentId);
            }
            $coveredSkills = $coveredSkills->distinct('skill_id')->count('skill_id');

            $avgSkillCoverage = $requiredSkillCount > 0 ? ($coveredSkills / $requiredSkillCount) * 100 : 0;
            $avgSkillCoverageTrend = rand(1, 5); // Placeholder, same as source - no historical snapshot table.

            $avgProficiencyQuery = DB::table('s_skill_matrix')->join('tbluser', 's_skill_matrix.user_id', '=', 'tbluser.id')->where('tbluser.sub_institute_id', $subInstituteId);
            if ($departmentId) {
                $avgProficiencyQuery->where('tbluser.department_id', $departmentId);
            }
            $avgProficiency = $avgProficiencyQuery->avg('skill_level') ?? 0;
            $previousProficiency = $avgProficiency - 0.12; // Placeholder, same as source.
            $avgProficiencyTrend = $this->calculateTrend($avgProficiency, $previousProficiency);

            return response()->json([
                'success' => true,
                'data' => [
                    'totalEmployees' => $totalEmployees,
                    'totalEmployeesTrend' => $totalEmployeeTrend,
                    'newHires' => $newHires,
                    'newHiresTrend' => $newHiresTrend,
                    'attritionRate' => round($attritionRate, 2),
                    'attritionRateTrend' => round($attritionTrend, 2),
                    'growthRate' => round($growthRate, 2),
                    'growthRateTrend' => round($growthTrend, 2),
                    'avgSkillCoverage' => round($avgSkillCoverage, 2),
                    'avgSkillCoverageTrend' => round($avgSkillCoverageTrend, 2),
                    'avgProficiencyDelta' => round($avgProficiency - $previousProficiency, 2),
                    'avgProficiencyDeltaTrend' => round($avgProficiencyTrend, 2),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching KPI data', 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /organization-management/employee-directory/analytics/growth */
    public function getGrowthData(Request $request)
    {
        try {
            $period = $request->query('period', 'monthly');
            $subInstituteId = $this->tenant();
            $departmentId = $request->department_id;

            if ($period !== 'monthly') {
                return response()->json(['error' => 'Invalid period parameter. Only monthly supported.'], 400);
            }

            $hiresQuery = DB::table('talent_job_applications as tja')
                ->leftJoin('talent_job_postings as tjp', 'tja.job_id', '=', 'tjp.id')
                ->selectRaw("DATE_FORMAT(tja.updated_at, '%b') AS month, COUNT(*) AS hires")
                ->where('tja.status', 'hired')
                ->whereYear('tja.updated_at', now()->year)
                ->where('tja.sub_institute_id', $subInstituteId);
            if ($departmentId) {
                $hiresQuery->where('tjp.department_id', $departmentId);
            }
            $hires = $hiresQuery->groupByRaw('MONTH(tja.updated_at)')->pluck('hires', 'month');

            $attritionQuery = DB::table('tbluser')
                ->selectRaw("DATE_FORMAT(terminated_date, '%b') AS month, COUNT(*) AS attrition")
                ->whereYear('terminated_date', now()->year)
                ->where('sub_institute_id', $subInstituteId);
            if ($departmentId) {
                $attritionQuery->where('department_id', $departmentId);
            }
            $attrition = $attritionQuery->groupByRaw('MONTH(terminated_date)')->pluck('attrition', 'month');

            $months = collect(range(1, 12))->map(fn ($m) => date('M', mktime(0, 0, 0, $m, 1)));

            $headcount = 0;
            $growthData = [];

            foreach ($months as $month) {
                $hiresCount = $hires[$month] ?? 0;
                $attritionCount = $attrition[$month] ?? 0;

                $headcount = $headcount + $hiresCount - $attritionCount;
                $growthData[] = [
                    'month' => $month,
                    'headcount' => $headcount,
                    'hires' => $hiresCount,
                    'attrition' => $attritionCount,
                ];
            }

            return response()->json(['success' => true, 'period' => $period, 'data' => $growthData], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch employee growth analytics.', 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /organization-management/employee-directory/analytics/growth-stacked */
    public function getStackedGrowth(Request $request)
    {
        try {
            $year = $request->query('year', Carbon::now()->year);
            $subInstituteId = $this->tenant();

            $departments = DB::table('hrms_departments')->where('sub_institute_id', $subInstituteId)->pluck('department', 'id');

            $responseData = [];

            for ($month = 1; $month <= 12; $month++) {
                $monthLabel = Carbon::create($year, $month)->format('M');
                $row = ['month' => $monthLabel];

                foreach ($departments as $deptId => $deptName) {
                    $count = DB::table('tbluser')
                        ->where('department_id', $deptId)
                        ->where('sub_institute_id', $subInstituteId)
                        ->whereYear('joined_date', '<=', $year)
                        ->whereRaw('MONTH(joined_date) <= ?', [$month])
                        ->where(function ($q) use ($year, $month) {
                            $q->whereNull('terminated_date')
                                ->orWhereRaw('(YEAR(terminated_date) >= ? AND MONTH(terminated_date) > ?)', [$year, $month]);
                        })
                        ->count();

                    $row[$deptName] = $count;
                }

                $responseData[] = $row;
            }

            return response()->json(['status' => 'success', 'data' => $responseData], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to fetch employee growth data', 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /organization-management/employee-directory/analytics/departments-distribution */
    public function getDepartmentDistribution(Request $request)
    {
        $subInstituteId = $this->tenant();

        try {
            $data = DB::table('hrms_departments as d')
                ->leftJoin('tbluser as u', function ($join) use ($subInstituteId) {
                    $join->on('u.department_id', '=', 'd.id')
                        ->whereNull('u.terminated_date')
                        ->where('u.sub_institute_id', $subInstituteId);
                })
                ->select('d.department as department', DB::raw('COUNT(u.id) as count'))
                ->where('d.sub_institute_id', $subInstituteId)
                ->groupBy('d.id', 'd.department')
                ->orderByDesc('count')
                ->get();

            return response()->json(['status' => 'success', 'message' => 'Department distribution fetched successfully', 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Something went wrong while fetching department distribution.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /organization-management/employee-directory/analytics/job-roles-distribution
     *
     * s_user_jobrole does not exist in LMS-K12 (see class docblock) - the
     * join is guarded and this returns an empty dataset until that table
     * is ported, rather than erroring.
     */
    public function getJobRoleDistribution(Request $request)
    {
        $subInstituteId = $this->tenant();

        try {
            if (!Schema::hasTable('s_user_jobrole')) {
                return response()->json(['message' => 'Job role distribution fetched successfully', 'data' => []], 200);
            }

            $roles = DB::table('tbluser as u')
                ->leftJoin('s_user_jobrole as jr', 'u.allocated_standards', '=', 'jr.id')
                ->select('jr.jobrole as role', DB::raw('COUNT(u.id) as count'))
                ->whereNull('u.terminated_date')
                ->where('u.sub_institute_id', $subInstituteId)
                ->whereNotNull('jr.jobrole')
                ->groupBy('jr.id', 'jr.jobrole')
                ->orderByDesc('count')
                ->get();

            return response()->json(['message' => 'Job role distribution fetched successfully', 'data' => $roles], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error fetching job role distribution', 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /organization-management/employee-directory/analytics/lifecycle */
    public function getLifecycle(Request $request)
    {
        $subInstituteId = $this->tenant();

        try {
            $applicantCount = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->count();
            $shortlistedCount = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->where('status', 'shortlisted')->count();
            $interviewedCount = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->where('status', 'interviewed')->count();
            $offeredCount = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->where('status', 'offered')->count();
            $hiredCount = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->where('status', 'hired')->count();
            $activeCount = DB::table('tbluser')->where('sub_institute_id', $subInstituteId)->whereNull('terminated_date')->count();

            $prevApplicantCount = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->whereMonth('created_at', now()->subMonth()->month)->count();
            $prevShortlistedCount = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->where('status', 'shortlisted')->whereMonth('updated_at', now()->subMonth()->month)->count();
            $prevInterviewedCount = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->where('status', 'interviewed')->whereMonth('updated_at', now()->subMonth()->month)->count();
            $prevOfferedCount = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->where('status', 'offered')->whereMonth('updated_at', now()->subMonth()->month)->count();
            $prevHiredCount = DB::table('talent_job_applications')->where('sub_institute_id', $subInstituteId)->where('status', 'hired')->whereMonth('updated_at', now()->subMonth()->month)->count();
            $prevActiveCount = DB::table('tbluser')->where('sub_institute_id', $subInstituteId)->whereNull('terminated_date')->whereMonth('joined_date', now()->subMonth()->month)->count();

            $stages = [
                ['name' => 'Applicant', 'current' => $applicantCount, 'prev' => $prevApplicantCount],
                ['name' => 'Shortlisted', 'current' => $shortlistedCount, 'prev' => $prevShortlistedCount],
                ['name' => 'Interviewed', 'current' => $interviewedCount, 'prev' => $prevInterviewedCount],
                ['name' => 'Offered', 'current' => $offeredCount, 'prev' => $prevOfferedCount],
                ['name' => 'Hired', 'current' => $hiredCount, 'prev' => $prevHiredCount],
                ['name' => 'Active', 'current' => $activeCount, 'prev' => $prevActiveCount],
            ];

            $response = [];
            $prevCount = $applicantCount;

            foreach ($stages as $index => $stage) {
                $conversion = $index === 0 ? 100 : round(($stage['current'] / max($prevCount, 1)) * 100);
                $trend = $stage['prev'] > 0 ? round((($stage['current'] - $stage['prev']) / $stage['prev']) * 100, 1) : 0;

                $response[] = [
                    'stage' => $stage['name'],
                    'count' => $stage['current'],
                    'conversion' => $conversion,
                    'trend' => $trend,
                ];

                $prevCount = $stage['current'];
            }

            return response()->json(['status' => 'success', 'data' => $response], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to load lifecycle data', 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /organization-management/employee-directory/analytics/attrition */
    public function getAttritionBreakdown(Request $request)
    {
        $subInstituteId = $this->tenant();

        try {
            $data = DB::table('hrms_departments as d')
                ->leftJoin('tbluser as e', 'd.id', '=', 'e.department_id')
                ->select(
                    'd.department as department',
                    DB::raw('COUNT(e.id) as totalEmployees'),
                    DB::raw('SUM(CASE WHEN e.terminated_date IS NOT NULL THEN 1 ELSE 0 END) as exits'),
                    DB::raw('ROUND((SUM(CASE WHEN e.terminated_date IS NOT NULL THEN 1 ELSE 0 END) / COUNT(e.id)) * 100, 1) as attritionRate')
                )
                ->where('d.sub_institute_id', $subInstituteId)
                ->groupBy('d.id', 'd.department')
                ->orderByDesc('attritionRate')
                ->get();

            $avgAttrition = $data->avg('attritionRate');

            return response()->json(['status' => 'success', 'averageAttritionRate' => round($avgAttrition, 1), 'departments' => $data], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to fetch attrition data', 'error' => $e->getMessage()], 500);
        }
    }

    /** GET /organization-management/employee-directory/analytics/skills-matrix */
    public function getSkillMatrix(Request $request)
    {
        $subInstituteId = $this->tenant();
        $departmentFilter = $request->query('department', 'all');

        try {
            $departmentsQuery = DB::table('hrms_departments')->where('sub_institute_id', $subInstituteId);
            if ($departmentFilter !== 'all') {
                $departmentsQuery->where('department', $departmentFilter);
            }
            $departments = $departmentsQuery->get();

            $skills = DB::table('s_users_skills')->where('sub_institute_id', $subInstituteId)->get();

            $matrix = [];

            foreach ($departments as $department) {
                $totalEmployees = DB::table('tbluser')
                    ->where('department_id', $department->id)
                    ->where('sub_institute_id', $subInstituteId)
                    ->whereNull('terminated_date')
                    ->count();

                foreach ($skills as $skill) {
                    $employeeStats = DB::table('s_skill_matrix as sm')
                        ->join('tbluser as u', 'sm.user_id', '=', 'u.id')
                        ->where('u.department_id', $department->id)
                        ->where('u.sub_institute_id', $subInstituteId)
                        ->whereNull('u.terminated_date')
                        ->where('sm.skill_id', $skill->id)
                        ->selectRaw('COUNT(sm.user_id) as employeeCount, AVG(sm.skill_level) as actualProficiency')
                        ->first();

                    if (!$employeeStats || $employeeStats->employeeCount == 0) {
                        continue;
                    }

                    $coverage = $totalEmployees > 0 ? round(($employeeStats->employeeCount / $totalEmployees) * 100) : 0;
                    $actual = round($employeeStats->actualProficiency ?? 0, 2);
                    $expected = 4.0; // Placeholder, same as source - no expected-level table.
                    $delta = round($actual - $expected, 2);

                    $matrix[] = [
                        'department' => $department->department,
                        'skill' => $skill->title,
                        'coverage' => $coverage,
                        'employeeCount' => $employeeStats->employeeCount,
                        'actualProficiency' => $actual,
                        'expectedProficiency' => $expected,
                        'delta' => $delta,
                    ];
                }
            }

            return response()->json(['status' => 'success', 'data' => $matrix], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to generate skill matrix', 'error' => $e->getMessage()], 500);
        }
    }

    private function calculateTrend($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
