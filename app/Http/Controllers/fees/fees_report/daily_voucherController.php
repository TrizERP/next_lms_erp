<?php

namespace App\Http\Controllers\fees\fees_report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use function App\Helpers\getStudents;
use App\Models\fees\other_fees_title\other_fees_title;
use Illuminate\Support\Facades\DB;

class daily_voucherController extends Controller
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

        return is_mobile($type, "fees/fees_report/show_daily_voucher", $res , "view");
    }
    
    public function create(Request $request)
    {            
        $type = $request->input("type");
        $to_date = $request->input('to_date');        
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');  

        $fees_head = DB::select("SELECT * FROM fees_title WHERE sub_institute_id = '".$sub_institute_id."' AND 
            fees_title NOT IN ('tution_fee','term_fee')");                                  
        $fees_head = json_decode(json_encode($fees_head),true);

        $sql = "";
        foreach($fees_head as $key => $val)
        {    
            if($val['other_fee_id'] == 0)
            {          
                $sql .= "
                SELECT '".strtoupper($val['display_name'])."' AS FEES_TYPE, IFNULL(SUM(fp.".$val['fees_title']."),0) AS AMOUNT
                FROM fees_collect fp
                INNER JOIN tblstudent_enrollment se ON se.student_id=fp.student_id
                INNER JOIN tblstudent s ON s.id=se.student_id
                INNER JOIN standard st ON st.id = se.standard_id
                WHERE fp.syear = '".$syear."' AND se.syear = '".$syear."' AND (fp.is_deleted = 'N' OR fp.is_waved = 'Cheque Return') 
                AND fp.sub_institute_id = '".$sub_institute_id."' AND DATE_FORMAT(fp.receiptdate,'%Y-%m-%d') = '".$to_date."'
                UNION";            
            }
            else{
                $sql .= "
                SELECT '".strtoupper($val['display_name'])."' AS FEES_TYPE, IFNULL(SUM(fp.".$val['fees_title']."),0) AS AMOUNT
                FROM fees_paid_other fp
                INNER JOIN tblstudent_enrollment se ON se.student_id=fp.student_id
                INNER JOIN tblstudent s ON s.id=se.student_id
                INNER JOIN standard st ON st.id = se.standard_id
                WHERE fp.syear = '".$syear."' AND se.syear = '".$syear."'
                AND fp.sub_institute_id = '".$sub_institute_id."' AND DATE_FORMAT(fp.receiptdate,'%Y-%m-%d') = '".$to_date."'
                UNION";               
            }
        }

        $school_stream_arr = DB::select("SELECT * FROM standard s WHERE s.sub_institute_id = '".$sub_institute_id."' 
            AND school_stream IS NOT NULL
            GROUP BY school_stream");                                  
        $school_stream_arr = json_decode(json_encode($school_stream_arr),true);

        foreach($school_stream_arr as $skey => $sval)
        {              
            $sql .= "
                SELECT '".$sval['school_stream']." TUITION FEE' AS FEES_TYPE, IFNULL(SUM(fp.tution_fee),0) AS AMOUNT
                FROM fees_collect fp
                INNER JOIN tblstudent_enrollment se ON se.student_id=fp.student_id
                INNER JOIN tblstudent s ON s.id=se.student_id
                INNER JOIN standard st ON st.id = se.standard_id
                WHERE fp.syear = '".$syear."' AND se.syear = '".$syear."' AND (fp.is_deleted = 'N' OR fp.is_waved = 'Cheque Return') 
                AND fp.sub_institute_id = '".$sub_institute_id."' AND DATE_FORMAT(fp.receiptdate,'%Y-%m-%d') = '".$to_date."'
                AND st.school_stream='".$sval['school_stream']."'
                UNION";            
        }

        $sql = substr($sql, 0, -5);         

        $fees_data = DB::select($sql);           
        $fees_data = json_decode(json_encode($fees_data),true);

        $receipt_head = DB::select("SELECT *
                        FROM fees_receipt_book_master
                        WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$syear."'
                        GROUP BY receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,
                        receipt_prefix,receipt_logo,last_receipt_number");
        $receipt_head = json_decode(json_encode($receipt_head),true);       

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $fees_data;        
        $res['receipt_data'] = $receipt_head[0];        
        $res['to_date'] = $to_date;        
        
        return is_mobile($type, "fees/fees_report/show_daily_voucher", $res , "view");
    }                
}
