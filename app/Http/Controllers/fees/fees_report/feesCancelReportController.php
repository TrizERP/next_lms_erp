<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class feesCancelReportController extends Controller
{
    public function feesCancelReportIndex(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        $feesCancelType = DB::table('fees_cancel_type')->pluck('title', 'id');

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['fees_cancel_type'] = $feesCancelType;

        return is_mobile($type, "fees/fees_report/show_fees_cancel_report", $res, "view");
    }

    public function feesCancelReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $cancel_type = $request->input('cancel_type');
        $marking_period_id = session()->get('term_id');

        $feesCancelType = DB::table('fees_cancel_type')->pluck('title', 'id');

        $result = DB::table('fees_cancel as fc')
            ->join('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = fc.student_id AND ts.sub_institute_id = fc.sub_institute_id');
            })->join('tblstudent_enrollment as te', function ($join) {
                $join->whereRaw('te.student_id = ts.id AND te.syear = fc.syear');
            })->join('student_quota as sq', function ($join) {
                $join->whereRaw('sq.id = te.student_quota AND ts.sub_institute_id = sq.sub_institute_id');
            })->join('standard as s', function ($join) use($marking_period_id) {
                $join->whereRaw('s.id = te.standard_id');
                // ->when($marking_period_id,function ($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })->join('tbluser as u', function ($join) {
                $join->whereRaw('u.id = fc.cancelled_by');
            })->selectRaw("fc.id,fc.reciept_id,ts.enrollment_no, CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name)
                AS student_name,ts.admission_year,te.student_quota,s.name as std_name,fc.amountpaid,fc.cancel_type,
                fc.cancel_remark, DATE_FORMAT(fc.cancel_date,'%d-%m-%Y') AS cancel_date, CONCAT_WS(' ',u.first_name,u.middle_name,
                u.last_name) AS cancelled_by,sq.title as student_quota_name")
            ->where('te.syear', $syear)
            ->where('fc.sub_institute_id', $sub_institute_id)
            ->where('fc.syear', $syear)
            ->where('fc.cancel_type', $cancel_type)
            ->whereRaw("date_format(fc.cancel_date,'%Y-%m-%d') BETWEEN '" . $from_date . "' AND '" . $to_date . "'");

        if ($standard != '') {
            $result = $result->where('fc.standard_id', $standard);
        }

        if ($division != '') {
            $result = $result->where('te.section_id', $division);
        }

        if ($grade != '') {
            $result = $result->where('te.grade_id', $grade);
        }

        $result = $result->get()->toArray();
        $result = array_map(function ($value) {
            return (array)$value;
        }, $result);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['report_data'] = $result;
        $res['fees_cancel_type'] = $feesCancelType;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['cancel_type'] = $cancel_type;

        return is_mobile($type, "fees/fees_report/show_fees_cancel_report", $res, "view");
    }
}
