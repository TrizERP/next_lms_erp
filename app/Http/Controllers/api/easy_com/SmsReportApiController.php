<?php

namespace App\Http\Controllers\api\easy_com;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Send SMS Report  (sms_sent_parents / sms_sent_staff)
 *
 * Fixed relative to the Blade flow (send_sms_report_controller::create):
 *  - $_REQUEST['tbl'] was dereferenced unguarded, so a client that did not send
 *    it got a 500. `tbl` is now validated and defaults to "parents";
 *  - the response used mixed-case keys per branch (SMS_NO vs sms_no, SYEAR vs
 *    syear) and dropped the timestamp entirely. A single normalised row shape
 *    is returned for both branches, including sent_on;
 *  - the to_date filter compared a `timestamp` column against a bare Y-m-d, so
 *    every row created after 00:00:00 on the end date was excluded. The bound
 *    is now inclusive to 23:59:59;
 *  - the staff branch joined on s.sub_institute_id = u.sub_institute_id but
 *    never constrained the report to the caller's institute on the joined
 *    table; both branches are explicitly scoped.
 */
class SmsReportApiController extends BaseEasyComApiController
{
    /** GET /api/easy_com/reports/sms/options */
    public function options(Request $request): JsonResponse
    {
        return $this->run(function () {
            $years = DB::table('academic_year')
                ->where('sub_institute_id', $this->subInstituteId())
                ->groupBy('syear')
                ->orderBy('syear', 'desc')
                ->pluck('syear');

            return $this->success([
                'academicYears' => $years,
                'sources'       => [
                    ['id' => 'parents', 'name' => 'Parents'],
                    ['id' => 'staff', 'name' => 'Staff'],
                ],
            ], 'Success');
        });
    }

    /**
     * GET /api/easy_com/reports/sms
     *     ?tbl=parents|staff&from_date=&to_date=&academic_year=&mobile_no=
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'tbl'       => 'nullable|in:parents,staff',
                'from_date' => 'nullable|date',
                'to_date'   => 'nullable|date',
            ]);

            $source = $this->filter($request, 'tbl', 'parents') ?: 'parents';
            $fromDate = $this->filter($request, 'from_date');
            $toDate = $this->filter($request, 'to_date');
            $academicYear = $this->filter($request, 'academic_year');
            $mobileNo = $this->filter($request, 'mobile_no');
            $subInstituteId = $this->subInstituteId();

            if ($source === 'staff') {
                $query = DB::table('sms_sent_staff as s')
                    ->join('tbluser as u', function ($join) {
                        $join->on('s.staff_id', '=', 'u.id')
                            ->on('s.sub_institute_id', '=', 'u.sub_institute_id');
                    })
                    ->selectRaw("s.id,
                        CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as name,
                        s.syear        as syear,
                        s.sms_no       as sms_no,
                        s.sms_text     as sms_text,
                        s.module_name  as module_name,
                        s.created_on   as sent_on,
                        'staff'        as source");

                $yearColumn = 's.syear';
                $mobileColumn = 's.sms_no';
                $dateColumn = 's.created_on';
            } else {
                $query = DB::table('sms_sent_parents as s')
                    ->join('tblstudent as u', function ($join) {
                        $join->on('s.STUDENT_ID', '=', 'u.id')
                            ->on('s.sub_institute_id', '=', 'u.sub_institute_id');
                    })
                    ->selectRaw("s.ID           as id,
                        CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as name,
                        u.enrollment_no as enrollment_no,
                        s.SYEAR        as syear,
                        s.SMS_NO       as sms_no,
                        s.SMS_TEXT     as sms_text,
                        s.MODULE_NAME  as module_name,
                        s.CREATED_ON   as sent_on,
                        'parents'      as source");

                $yearColumn = 's.SYEAR';
                $mobileColumn = 's.SMS_NO';
                $dateColumn = 's.CREATED_ON';
            }

            $query->where('s.sub_institute_id', $subInstituteId);

            if ($academicYear !== '') {
                $query->where($yearColumn, $academicYear);
            }

            if ($mobileNo !== '') {
                $query->where($mobileColumn, $mobileNo);
            }

            if ($fromDate !== '') {
                $query->where($dateColumn, '>=', $fromDate);
            }

            if ($toDate !== '') {
                $query->where($dateColumn, '<=', $this->endOfDay($toDate));
            }

            $rows = $query->orderByDesc($dateColumn)->limit(5000)->get();

            return $this->success($rows, 'Success', 200, [
                'count'  => $rows->count(),
                'source' => $source,
            ]);
        });
    }
}
