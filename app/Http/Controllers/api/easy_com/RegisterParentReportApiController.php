<?php

namespace App\Http\Controllers\api\easy_com;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Register Parent Report  (gcm_users -> registered parent devices)
 *
 * Fixed relative to the Blade flow (register_parents_report_controller::create):
 *  - the joins interpolated $sub_institute_id straight into whereRaw() strings;
 *    they are parameter-bound here;
 *  - response keys (stu_name / std_name / div_name / aca_sec / CREATED_ON) did
 *    not match what the Next.js table requested, so every column rendered as an
 *    em dash. Keys are normalised. The Next.js page also asked for a
 *    `device_id` column, which does not exist on gcm_users - the real device
 *    identifier is imei_no, so that column is dropped rather than faked;
 *  - the to_date bound is inclusive to 23:59:59 (created_on is a timestamp);
 *  - academic section / standard / division filters are supported, matching the
 *    other reports.
 */
class RegisterParentReportApiController extends BaseEasyComApiController
{
    /**
     * GET /api/easy_com/reports/register-parent
     *     ?from_date=&to_date=&mobile_no=&grade=&standard=&division=
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'from_date' => 'nullable|date',
                'to_date'   => 'nullable|date',
            ]);

            $subInstituteId = $this->subInstituteId();
            $syear = $this->syear();

            $mobileNo = $this->filter($request, 'mobile_no');
            $fromDate = $this->filter($request, 'from_date');
            $toDate = $this->filter($request, 'to_date');
            $grade = $this->filter($request, 'grade');
            $standard = $this->filter($request, 'standard');
            $division = $this->filter($request, 'division');

            $query = DB::table('gcm_users as gu')
                ->join('tblstudent as s', function ($join) use ($subInstituteId) {
                    $join->on('s.mobile', '=', 'gu.mobile_no')
                        ->where('s.sub_institute_id', '=', $subInstituteId);
                })
                ->join('tblstudent_enrollment as se', function ($join) use ($subInstituteId) {
                    $join->on('se.student_id', '=', 's.id')
                        ->where('se.sub_institute_id', '=', $subInstituteId)
                        ->whereNull('se.end_date');
                })
                ->join('standard as ss', function ($join) use ($subInstituteId) {
                    $join->on('ss.id', '=', 'se.standard_id')
                        ->where('ss.sub_institute_id', '=', $subInstituteId);
                })
                ->join('academic_section as aa', function ($join) use ($subInstituteId) {
                    $join->on('aa.id', '=', 'ss.grade_id')
                        ->where('aa.sub_institute_id', '=', $subInstituteId);
                })
                ->join('division as dd', function ($join) use ($subInstituteId) {
                    $join->on('dd.id', '=', 'se.section_id')
                        ->where('dd.sub_institute_id', '=', $subInstituteId);
                })
                ->selectRaw("s.id            as student_id,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) as student_name,
                    s.enrollment_no          as enrollment_no,
                    aa.title                 as grade_name,
                    ss.name                  as standard_name,
                    dd.name                  as division_name,
                    gu.mobile_no             as mobile_no,
                    gu.imei_no               as imei_no,
                    gu.curr_version          as current_version,
                    gu.new_version           as new_version,
                    DATE_FORMAT(gu.created_on, '%d-%m-%Y %r') as registered_on")
                ->where('se.SYEAR', $syear)
                ->where('gu.sub_institute_id', $subInstituteId);

            if ($mobileNo !== '') {
                $query->where('s.mobile', $mobileNo);
            }

            if ($fromDate !== '') {
                $query->where('gu.created_on', '>=', $fromDate);
            }

            if ($toDate !== '') {
                $query->where('gu.created_on', '<=', $this->endOfDay($toDate));
            }

            if ($grade !== '') {
                $query->where('se.grade_id', $grade);
            }

            if ($standard !== '') {
                $query->where('se.standard_id', $standard);
            }

            if ($division !== '') {
                $query->where('se.section_id', $division);
            }

            $rows = $query
                ->groupBy('gu.imei_no', 's.id')
                ->orderByDesc('gu.created_on')
                ->limit(5000)
                ->get();

            return $this->success($rows, 'Success', 200, ['count' => $rows->count()]);
        });
    }
}
