<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use App\Models\fees\other_fees_title\other_fees_title;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class imprestRefundReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = "1";
        $res['message'] = "Success";
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $feesOtherHead_data = other_fees_title::select("*")
            ->where(["sub_institute_id" => $sub_institute_id])
            ->where("status", '=', '1')
            ->get()
            ->toArray();

        $res['feesOtherHead_data'] = $feesOtherHead_data;

        return is_mobile($type, "fees/fees_report/show_imprest_refund_fees_report", $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input("type");
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $otherfeeshead = $request->input('otherfeeshead');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $extraSearch = " ";

        if ($grade != '') {
            $extraSearch .= " AND se.grade_id = '" . $grade . "'";
        }

        if ($standard != '') {
            $extraSearch .= " AND se.standard_id = '" . $standard . "'";
        }

        if ($division != '') {
            $extraSearch .= " AND se.section_id = '" . $division . "'";
        }

        if ($from_date != '' && $to_date != '') {
            $extraSearch .= " AND c.cancel_date between '" . $from_date . "' AND '" . $to_date . "' ";
        }


        $refund_feesData = DB::select("SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,
            s.enrollment_no,s.mobile,c.student_id, st.name AS standard_name,
            d.name AS division_name,c.cancel_date,c.cancel_remark,c.cancel_amount,c.cancel_type,
            c.reciept_id, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS cancelled_by
            FROM imprest_fees_cancel c
            INNER JOIN tblstudent s ON s.id = c.student_id AND c.sub_institute_id = s.sub_institute_id
            INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.syear = c.syear
            INNER JOIN standard st ON st.id = se.standard_id
            INNER JOIN division d ON se.section_id = d.id
            INNER JOIN tbluser u ON u.id = c.cancelled_by AND u.sub_institute_id = c.sub_institute_id
            WHERE c.sub_institute_id = '" . $sub_institute_id . "' AND c.syear = '" . $syear . "' " . $extraSearch . "
            ORDER BY c.cancel_date
        ");

        $refund_feesData = json_decode(json_encode($refund_feesData), true);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['refund_feesData'] = $refund_feesData;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

        return is_mobile($type, "fees/fees_report/show_imprest_refund_fees_report", $res, "view");
    }
}
