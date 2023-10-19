<?php namespace App\Http\Controllers\fees\NACH;

use App\Http\Controllers\Controller;
use App\Models\fees\other_fees_title\other_fees_title;
use App\Models\fees\other_fees_collect\other_fees_collect;
use App\Models\fees\other_fees_cancel\other_fees_cancel;
use App\Models\student\tblstudentModel;
use App\Models\fees\bank_master\bankmasterModel;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require('excel_upload/PHPExcel.php');
require('excel_upload/PHPExcel/Writer/Excel2007.php');
use PhpExcel;
use PHPExcel_Writer_Excel2007;


class s1excel_exportController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) 
	{
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = session()->get('sub_institute_id');
		$syear = session()->get('syear');
		// $res['status'] = 1;
		// $res['message'] = "Success";
        $res = array();

		return is_mobile($type, "fees/NACH/show_s1_excel_export", $res, "view");
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) 
	{
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');
		$from_date = $request->input('from_date');
		$to_date = $request->input('to_date'); 

        $extra = "1=1";
        $sql_enroll = "SELECT GROUP_CONCAT(DISTINCT SCHEME_PLAN_REFERENCE_NO) ENR FROM S2_LOG";
        $ret_enroll = DB::select($sql_enroll);        
        $ret_enroll = $ret_enroll[0];
        $ret_enroll = json_decode(json_encode($ret_enroll),true);

        if($ret_enroll['ENR'] != ''){
            $extra .= " AND s.enrollment_no NOT IN (".$ret_enroll['ENR'].")";
        }           
		
		if($from_date != null  && $to_date != null)
		{
			$extra .= " AND bd.registration_date between '".$from_date."' AND '".$to_date."'";
		}

        // $studentData = DB::select("SELECT bd.*,pm.payment_method,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name,s.id as student_id, 
		// s.enrollment_no,s.mobile
		// FROM tblstudent_payment_method_mapping pm 
		// INNER JOIN tblstudent_bank_detail bd ON bd.student_id = pm.student_id
		// INNER JOIN tblstudent s ON s.id = pm.student_id
		// WHERE s.sub_institute_id = '".$sub_institute_id."' ".$extra."
		// GROUP BY pm.student_id");
		// $studentData = json_decode(json_encode($studentData),true);

        // $NachData = DB::select("SELECT * FROM NACH_MASTER WHERE sub_institute_id = '".$sub_institute_id."'");
        // $NachData = json_decode(json_encode($NachData),true);
        $marking_period_id = session()->get('term_id');
        $studentData = DB::table('tblstudent_payment_method_mapping as pm')
        ->selectRaw('bd.*, pm.payment_method, CONCAT_WS(" ", s.first_name, s.last_name) as student_name, s.id as student_id,ac.type_name AS ac_type, s.enrollment_no, s.mobile')
        ->join('tblstudent_bank_detail as bd', 'bd.student_id', '=', 'pm.student_id')
        ->join('NACH_ac_type as ac', 'ac.type_id', '=', 'bd.ac_type')
        ->join('tblstudent as s',function($join) use($marking_period_id){
            $join->on('s.id', '=', 'pm.student_id');
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('marking_period_id',$marking_period_id);
            // });
        })
        ->where('s.sub_institute_id', '=', $sub_institute_id)
        ->when($extra, function ($query) use ($extra) {
            return $query->whereRaw($extra);
        })
        ->groupBy('pm.student_id')
        ->get();

    $studentData = json_decode(json_encode($studentData), true);

    $NachData = DB::table('NACH_MASTER')
        ->where('sub_institute_id', '=', $sub_institute_id)
        ->get();

    $NachData = json_decode(json_encode($NachData), true);

        if(count($NachData) <= 0 )
        {            
            $res['status_code'] = 0;
            $res['message'] = "Missing NACH Settings.";
        }
        else
        {
            $excelFile_path = $this->getExcelFile($studentData,$NachData[0]);

            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['student_data'] = $studentData;
            $res['excelFile_path'] = $excelFile_path;
            $res['from_date'] = $from_date;
            $res['to_date'] = $to_date;                  
        }

        //return is_mobile($type, "NACH_s1excel_export.index", $res, "redirect");
		return is_mobile($type, "fees/NACH/show_s1_excel_export", $res, "view");
	}

	public function getExcelFile($studentRet,$NachData)
	{

		$excel_header = array(
        'A' => 'Message ID (Bank to fill)',
        'B' => 'Message Creation Date Time (Bank to fill)',
        'C' => 'Initiating Party ID',
        'D' => 'Instructing Agent Member ID',
        'E' => 'Instructed Agent Member ID',
        'F' => 'Instructed Agent Name',
        'G' => 'Mandate Request ID',
        'H' => 'Mandate Category',
        'I' => 'Mandate Category Name',
        'J' => 'TXN type',
        'K' => 'Recurring or One-Off (RCUR, OOFF)',
        'L' => 'Frequency',
        'M' => 'First Collection Date',
        'N' => 'Final Collection Date',
        'O' => 'Collection Amount',
        'P' => 'Maximum Amount',
        'Q' => 'Name of Utility/ Biller/ Bank/ Company',
        'R' => 'Utility Code',
        'S' => 'Sponsor Bank Code',
        'T' => 'Debtor Name/Name of Account Holder',
        'U' => 'Consumer Reference No',
        'V' => 'Scheme/Plan Reference No',
        'W' => 'Debtor Telephone No',
        'X' => 'Debtor Mobile No',
        'Y' => 'Debtor Email Add',
        'Z' => 'Debtor other details',
        'AA' => 'Destination Bank Account Number/ Legal Account Number',
        'AB' => 'Destination Bank Account Type',
        'AC' => 'Destination Bank IFSC/MICR code',
        'AD' => 'Destination Bank Name',
    );

    $excel_skip = array('A', 'B', 'C', 'F', 'G', 'N', 'O', 'W', 'Y', 'Z');

    $objPHPExcel = new PHPExcel();
    $objPHPExcel->setActiveSheetIndex(0);
    $rowCount = 1;
    foreach ($excel_header as $id => $val) {
        $objPHPExcel->getActiveSheet()->SetCellValue($id . $rowCount, $val,'s');
    }

    foreach ($studentRet as $cnt => $arr) {
         $rowCount = $rowCount + 1;
         foreach ($excel_header as $id => $val) {
            $set_value = "";
            switch ($id) {
                case "D":
                    $set_value = $NachData['instructing_agent_member_id'];//"KKBK0RTGSMI";
                    break;
                case "H":
                    $set_value = $NachData['mandate_category'];//"E001";
                    break;
                case "I":
                    $set_value = $NachData['mandate_category_name'];//"Education Fees";
                    break;
                case "J":
                    $set_value = "DEBIT";
                    break;
                case "K":
                    $set_value = "RCUR";
                    break;
                case "L":
                    $set_value = "ADHO";
                    break;
                case "M":
                    $set_value = "11022017";
                    break;
                case "P":
                    $set_value = "25000.00";
                    break;
                case "Q":
                    $set_value = $NachData['name_of_utility'];//"HILLS HIGH SCHOOL";
                    break;
                case "R":
                    $set_value = $NachData['utility_code'];//"NACH00000000005162";
                    break;
                case "S":
                    $set_value = $NachData['sponsor_bank_code'];//"KKBK0RTGSMI";
                    break;
                 case "T":
                    $set_value = $arr['ac_holder_name'];
                    break;
                case "U":
                    $set_value = $arr['student_name'];
                    break;
                case "V":
                    $set_value = $arr['enrollment_no'];
                    break;
                case "X":
                    $set_value = $arr['mobile'];
                    break;
                case "AA":
                    $set_value = $arr['ac_number'];
                    break;
                case "AB":
                    $set_value = $arr['ac_type'];
                    break;
                case "AC":
                    $set_value = $arr['ifsc_code'];
                    break;
                case "AD":
                    $set_value = $arr['bank_name'];
                    break;    
                default:
                    $set_value = "";
            }
            //$objPHPExcel->getActiveSheet()->SetCellValue($id . $rowCount, $set_value);
            $objPHPExcel->getActiveSheet()->setCellValueExplicit($id . $rowCount, $set_value,'s');
        }
    }
    $objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);


    $name = "NACH_S1_EXPORT_".date("Y_m_d_H_i_s");
    $objWriter->save("storage/NachExcel/$name.xlsx");

    //echo "<br><br><a href='../storage/NachExcel/$name.xlsx' class=btn_medium>Download Excel</a>";
    return "../storage/NachExcel/$name.xlsx";

	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function show($id) {
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function edit($id) {
		//
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id) {
		//
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id) {
		//
	}

}
