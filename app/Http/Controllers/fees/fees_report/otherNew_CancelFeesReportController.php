<?php

namespace App\Http\Controllers\fees\fees_report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use App\Models\fees\other_fees_title\other_fees_title;
use Illuminate\Support\Facades\DB;

class otherNew_CancelFeesReportController extends Controller
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
        ->where(["sub_institute_id"=>$sub_institute_id])
        ->where("status",'=','1')
        ->get()
        ->toArray();

        $res['feesOtherHead_data'] = $feesOtherHead_data;

        return is_mobile($type, "fees/fees_report/show_otherNew_CancelFees_report", $res , "view");
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

        if($grade != '')
        {
            $extraSearch .= " AND se.grade_id = '".$grade."'";
        }
    
        if($standard != '')
        {
            $extraSearch .= " AND se.standard_id = '".$standard."'";           
        }

        if($division != '')
        {
            $extraSearch .= " AND se.section_id = '".$division."'";             
        }

        if($from_date != '' && $to_date != '')
        {
            $extraSearch .= " AND c.cancellation_date between '".$from_date."' AND '".$to_date."' ";             
        }

        if($otherfeeshead != '')
        {
            $extraSearch .= " AND deduction_head_id = '".$otherfeeshead."'";             
        }        
               
        $other_feesData = DB::select("SELECT CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,s.enrollment_no,s.mobile,c.student_id, st.name AS standard_name,
        d.name AS division_name,h.display_name AS fees_head,c.cancellation_date,c.cancellation_remarks,c.cancellation_amount,
        c.receipt_id, CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS cancelled_by
        FROM fees_other_cancel c
        INNER JOIN fees_other_head h ON c.deduction_head_id = h.id
        INNER JOIN tblstudent s ON s.id = c.student_id AND c.sub_institute_id = s.sub_institute_id
        INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.syear = c.syear
        INNER JOIN standard st ON st.id = se.standard_id
        INNER JOIN division d ON se.section_id = d.id
        INNER JOIN tbluser u ON u.id = c.created_by AND u.sub_institute_id = c.sub_institute_id
        WHERE c.sub_institute_id = '".$sub_institute_id."' AND c.syear = '".$syear."' ".$extraSearch."
        ORDER BY c.cancellation_date
        ");

        $other_feesData = json_decode(json_encode($other_feesData),true);

        $other_fee_title = other_fees_title::select("*")
        ->where(["sub_institute_id"=>$sub_institute_id])
        ->where("status",'=','1')
        ->get()
        ->toArray();        


        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['other_feesData'] = $other_feesData;
        $res['feesOtherHead_data'] = $other_fee_title;        
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['otherfeeshead'] = $otherfeeshead;
        
        return is_mobile($type, "fees/fees_report/show_otherNew_CancelFees_report", $res , "view");
    }
}
