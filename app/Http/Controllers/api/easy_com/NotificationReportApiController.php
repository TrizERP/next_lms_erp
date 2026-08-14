<?php

namespace App\Http\Controllers\api\easy_com;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Notification Report  (app_notification)
 *
 * Fixed relative to the Blade flow (notification_report_controller::create):
 *  - the API branch read sub_institute_id / syear straight off the request
 *    ($request->sub_institute_id), so any client could report on another
 *    institute. Tenant context now comes from the validated JWT session;
 *  - the payload key was misspelt NOTOFICATION_DATE, and the remaining keys
 *    (stu_name / std_name / div_name / aca_sec / mobile_no / curr_version)
 *    did not match anything the Next.js table asked for, so every column
 *    rendered as an em dash. Keys are normalised here;
 *  - the academic-year filter was applied twice with different meanings - once
 *    against s.admission_year and once against YEAR(an.NOTIFICATION_DATE) - so
 *    selecting a year returned rows only when both happened to agree. The
 *    admission-year filter is now the single, explicit meaning;
 *  - the to_date bound is inclusive to 23:59:59;
 *  - joins to standard/academic_section/division are now tenant-scoped.
 */
class NotificationReportApiController extends BaseEasyComApiController
{
    /** GET /api/easy_com/reports/notification/options */
    public function options(Request $request): JsonResponse
    {
        return $this->run(function () {
            $years = DB::table('academic_year')
                ->where('sub_institute_id', $this->subInstituteId())
                ->groupBy('syear')
                ->orderBy('syear', 'desc')
                ->pluck('syear');

            return $this->success(['academicYears' => $years], 'Success');
        });
    }

    /**
     * GET /api/easy_com/reports/notification
     *     ?from_date=&to_date=&mobile_no=&academic_year=&grade=&standard=&division=
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
            $academicYear = $this->filter($request, 'academic_year');
            $grade = $this->filter($request, 'grade');
            $standard = $this->filter($request, 'standard');
            $division = $this->filter($request, 'division');

            $query = DB::table('app_notification as an')
                ->join('tblstudent as s', function ($join) use ($subInstituteId) {
                    $join->on('s.id', '=', 'an.STUDENT_ID')
                        ->where('s.sub_institute_id', '=', $subInstituteId);
                })
                ->join('tblstudent_enrollment as se', function ($join) use ($syear, $subInstituteId) {
                    $join->on('se.student_id', '=', 's.id')
                        ->where('se.syear', '=', $syear)
                        ->where('se.sub_institute_id', '=', $subInstituteId);
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
                ->leftJoin('gcm_users as gu', function ($join) use ($subInstituteId) {
                    $join->on('gu.mobile_no', '=', 's.mobile')
                        ->where('gu.sub_institute_id', '=', $subInstituteId);
                })
                ->selectRaw("an.id                as id,
                    s.id                          as student_id,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) as student_name,
                    s.enrollment_no               as enrollment_no,
                    aa.title                      as grade_name,
                    ss.name                       as standard_name,
                    dd.name                       as division_name,
                    an.NOTIFICATION_TYPE          as notification_type,
                    DATE_FORMAT(an.NOTIFICATION_DATE, '%d-%m-%Y') as notification_date,
                    an.NOTIFICATION_DESCRIPTION   as notification_text,
                    CASE WHEN an.STATUS = 1 THEN 'Read'
                         WHEN an.STATUS = 0 THEN 'Un-Read'
                         ELSE 'N/A' END           as read_status,
                    gu.imei_no                    as imei_no,
                    gu.curr_version               as current_version,
                    gu.new_version                as new_version,
                    s.mobile                      as mobile_no,
                    DATE_FORMAT(an.CREATED_AT, '%d-%m-%Y %r') as created_on")
                ->where('an.sub_institute_id', $subInstituteId);

            if ($academicYear !== '') {
                $query->where('s.admission_year', $academicYear);
            }

            if ($mobileNo !== '') {
                $query->where('s.mobile', $mobileNo);
            }

            if ($fromDate !== '') {
                $query->where('an.NOTIFICATION_DATE', '>=', $fromDate);
            }

            if ($toDate !== '') {
                $query->where('an.NOTIFICATION_DATE', '<=', $this->endOfDay($toDate));
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
                ->groupBy([
                    'an.id', 'an.STUDENT_ID', 'an.NOTIFICATION_TYPE', 'an.NOTIFICATION_DATE',
                    'an.NOTIFICATION_DESCRIPTION', 'gu.imei_no',
                ])
                ->orderByDesc('an.id')
                ->limit(5000)
                ->get();

            return $this->success($rows, 'Success', 200, ['count' => $rows->count()]);
        });
    }
}
