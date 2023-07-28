<?php

namespace App\Http\Controllers\fees\NACH;

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
use function App\Helpers\FeeMonthId;

require('excel_upload/PHPExcel.php');
require('excel_upload/PHPExcel/Writer/Excel2007.php');
use PhpExcel;
use PHPExcel_Writer_Excel2007;
use function League\Flysystem\isDir;


class s3excel_exportController extends Controller {
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
        $res['fee_month'] = FeeMonthId();
		$res['status'] = 1;
		$res['message'] = "Success";
		
		return is_mobile($type, "fees/NACH/show_s3_excel_export", $res, "view");
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
		$grade = $request->input('grade');
        $standard = $request->input('standard'); 
        $division = $request->input('division'); 
		$month_id = $request->input('month_id'); 

        $extra = "";
		if($grade != null)
		{
			$extra .= " AND f.grade_id = '".$grade."'";
		}
        if($standard != null)
        {
            $extra .= " AND f.standard_id = '".$standard."'";
        }
        if($division != null)
        {
            $extra .= " AND se.section_id = '".$division."'";
        }
        $NachData = DB::table('NACH_MASTER')->where('sub_institute_id',$sub_institute_id)->get()->toArray();
        $NachData = $NachData[0];        

        $sql = "
            SELECT 'ACH Transaction Code (2) M' as ACH_TRANSACTION_CODE,'Control (9) O' as CONTROL_1,'Destination Account Type (2) O' as DESTINATION_AC_TYPE,
            'Ledger Folio Number (3) O' as LEDGER_FOLIO_NUMBER,'Control (15) O' as CONTROL_2, 'Beneficiary Account Holder\'s Name (40) M' as BENEFICIARY_AC_HOLDER_NAME,
            'Control (9) O' as CONTROL_3,'Control (7) O' as CONTROL_4,'User Name / Narration (20) O' as USER_NAME,'Control (13) O' as CONTROL_5,
            'Amount (13) M' as AMOUNT,'Reserved (ACH Item Seq No.) (10) O' as RESERVED_ACH_ITEM_SEQ_NO,'Reserved (Checksum) (10) O' as RESERVED_CHECKSUM,
            'Reserved (Flag for success / return) (1) O' as RESERVED_FLAG_SUCCESS_RETURN,'Reserved (Reason Code) (2) O' as RESERVED_REASON_CODE,
            'Destination Bank IFSC / MICR / IIN (11) M' as DESTINATION_BANK_IFSC_CODE,'Beneficiary\'s Bank Account number (35) M' as DESTINATION_BANK_AC_NUMBER,
            'Sponsor Bank IFSC / MICR / IIN (11) M' as SPONSOR_BANK_IFSC_CODE,'User Number (18) M' as USER_NUMBER,'Transaction Reference (30) M' as TRANSACTION_REFERENCE,
            'Product Type (3) M' as PRODUCT_TYPE,'Beneficiary Aadhaar Number (15) M for APBS' as BENEFICIARY_ADHAAR_NUMBER,'UMRN (20) M' as UMRN FROM DUAL

            UNION 
           

            SELECT '56' AS ACH_TRANSACTION_CODE,'' AS CONTROL_1,'".$NachData->name_of_utility."' AS DESTINATION_AC_TYPE,'' AS LEDGER_FOLIO_NUMBER,'' AS CONTROL_2, 
            '' AS BENEFICIARY_AC_HOLDER_NAME,'' AS CONTROL_3,'' AS CONTROL_4,'' AS USER_NAME,'' AS CONTROL_5, DATE_FORMAT(NOW(),'%d%m%Y') AS AMOUNT,
            '' AS RESERVED_ACH_ITEM_SEQ_NO, '' AS RESERVED_CHECKSUM,'' AS RESERVED_FLAG_SUCCESS_RETURN,'NACH00000000005440' AS RESERVED_REASON_CODE,
            'MILL0000000000001' AS DESTINATION_BANK_IFSC_CODE, 'KKBK0RTGSMI' AS DESTINATION_BANK_AC_NUMBER,
            '00000000000000000000000008711234288' AS SPONSOR_BANK_IFSC_CODE,'000000007' AS USER_NUMBER, '' AS TRANSACTION_REFERENCE,'' AS PRODUCT_TYPE,
            '' AS BENEFICIARY_ADHAAR_NUMBER,'' AS UMRN
            FROM DUAL 

            UNION
            
            SELECT '67' AS ACH_TRANSACTION_CODE,'' AS CONTROL_1,AC_TYPE AS DESTINATION_AC_TYPE,'' AS LEDGER_FOLIO_NUMBER,'' AS CONTROL_2,
            M.ac_holder_name AS BENEFICIARY_AC_HOLDER_NAME,'' AS CONTROL_3,'' AS CONTROL_4,'".$NachData->name_of_utility."' AS USER_NAME,
            '' AS CONTROL_5,(M.totalFees + 0) AS AMOUNT,'' AS RESERVED_ACH_ITEM_SEQ_NO,'' AS RESERVED_CHECKSUM,'' AS RESERVED_FLAG_SUCCESS_RETURN,
            '' AS RESERVED_REASON_CODE,M.ifsc_code AS DESTINATION_BANK_IFSC_CODE,M.ac_number AS DESTINATION_BANK_AC_NUMBER,
            'KKBK0RTGSMI' AS SPONSOR_BANK_IFSC_CODE,'' AS USER_NUMBER,SUBSTRING(CONCAT(M.enrollment_no,' ', UPPER(M.full_name)),1,30) AS TRANSACTION_REFERENCE,
            '' AS PRODUCT_TYPE,'' AS BENEFICIARY_ADHAAR_NUMBER,M.UMRN
            FROM (
            
                SELECT s.id,s.enrollment_no,CONCAT_WS(' ',s.first_name,s.last_name) AS full_name,
                se.student_quota,f.month_id,bd.ac_type,bd.ac_holder_name,bd.ifsc_code,bd.ac_number,bd.UMRN,
                f.amount AS totalFees,fc.amount AS paid_amount from tblstudent s 
                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id 
                INNER JOIN tblstudent_bank_detail bd ON bd.student_id = se.student_id AND bd.sub_institute_id = '".$sub_institute_id."'
                INNER JOIN fees_breackoff f ON f.standard_id = se.standard_id AND f.grade_id = se.grade_id AND f.admission_year = s.admission_year AND se.student_quota = f.quota AND f.sub_institute_id = '".$sub_institute_id."' AND f.syear = '".$syear."' 
                INNER JOIN tblstudent_payment_method_mapping spm ON spm.student_id = se.student_id AND spm.sub_institute_id = '".$sub_institute_id."'
                LEFT JOIN fees_collect fc ON fc.student_id = s.id AND fc.term_id = f.month_id
                WHERE s.sub_institute_id = '".$sub_institute_id."' AND se.syear = '".$syear."' AND se.end_date IS NULL
                AND f.month_id = '".$month_id."' AND spm.payment_method = 'NHCS'
                AND (CURRENT_DATE <= spm.payment_date OR spm.payment_date IS NULL) ".$extra."
                GROUP BY s.id
                HAVING fc.amount IS NULL
            ) 
            as M";           

        $studentData = DB::select($sql);
        $studentData = json_decode(json_encode($studentData),true);
		$excelFile_path = $this->getExcelFile($studentData);

		$res['status_code'] = 1;
		$res['message'] = "Success";
		$res['student_data'] = $studentData;
		$res['excelFile_path'] = $excelFile_path;
        $res['division'] = $division;
        $res['standard'] = $standard;
        $res['grade'] = $grade;
		$res['month_id'] = $month_id;
        $res['fee_month'] = FeeMonthId();
		
		return is_mobile($type, "fees/NACH/show_s3_excel_export", $res, "view");
	}

	public function getExcelFile($studentRet)
	{                                                        
		$excel_header = array(
        'A' => 'ACH Transaction Code (2) M',
        'B' => 'Control (9) O',
        'C' => 'Destination Account Type (2) O',
        'D' => 'Ledger Folio Number (3) O',
        'E' => 'Control (15) O',
        'F' => 'Beneficiary Account Holders Name (40) M',
        'G' => 'Control (9) O',
        'H' => 'Control (7) O',
        'I' => 'User Name / Narration (20) O',
        'J' => 'Control (13) O',
        'K' => 'Amount (13) M',
        'L' => 'Reserved (ACH Item Seq No.) (10) O',
        'M' => 'Reserved (Checksum) (10) O',
        'N' => 'Reserved (Flag for success / return) (1) O',
        'O' => 'Reserved (Reason Code) (2) O',
        'P' => 'Destination Bank IFSC / MICR / IIN (11) M',
        'Q' => 'Beneficiarys Bank Account number (35) M',
        'R' => 'Sponsor Bank IFSC / MICR / IIN (11) M',
        'S' => 'User Number (18) M',
        'T' => 'Transaction Reference (30) M',
        'U' => 'Product Type (3) M',
        'V' => 'Beneficiary Aadhaar Number (15) M for APBS',
        'W' => 'UMRN (20) M',
        );

    $excel_skip = array('A', 'B', 'C', 'F', 'G', 'N', 'O', 'W', 'Y', 'Z');

    $objPHPExcel = new PHPExcel();
    $objPHPExcel->setActiveSheetIndex(0);
    $rowCount = 0;
    /*foreach ($excel_header as $id => $val) {
        $objPHPExcel->getActiveSheet()->SetCellValue($id . $rowCount, $val);
    }*/

    foreach ($studentRet as $cnt => $arr) {
         $rowCount = $rowCount + 1;
         foreach ($excel_header as $id => $val) {
            $set_value = "";
            switch ($id) {
                case "A":
                    $set_value = $arr['ACH_TRANSACTION_CODE'];
                    break;
                case "B":
                    $set_value = $arr['CONTROL_1'];
                    break;
                case "C":
                    $set_value = $arr['DESTINATION_AC_TYPE'];
                    break;
                case "D":
                    $set_value = $arr['LEDGER_FOLIO_NUMBER'];
                    break;
                case "E":
                    $set_value = $arr['CONTROL_2'];
                    break;
                case "F":
                    $set_value = $arr['BENEFICIARY_AC_HOLDER_NAME'];
                    break;
                case "G":
                    $set_value = $arr['CONTROL_3'];
                    break;
                case "H":
                    $set_value = $arr['CONTROL_4'];
                    break;
                case "I":
                    $set_value = $arr['USER_NAME'];
                    break;
                case "J":
                    $set_value = $arr['CONTROL_5'];
                    break;
                case "K":
                    $set_value = $arr['AMOUNT'];
                    break;
                case "L":
                    $set_value = $arr['RESERVED_ACH_ITEM_SEQ_NO'];
                    break;
                case "M":
                    $set_value = $arr['RESERVED_CHECKSUM'];
                    break;
                case "N":
                    $set_value = $arr['RESERVED_FLAG_SUCCESS_RETURN'];
                    break;
                case "O":
                    $set_value = $arr['RESERVED_REASON_CODE'];
                    break;
                case "P":
                    $set_value = $arr['DESTINATION_BANK_IFSC_CODE'];
                    break;
                case "Q":
                    $set_value = $arr['DESTINATION_BANK_AC_NUMBER'];
                    break;
                case "R":
                    $set_value = $arr['SPONSOR_BANK_IFSC_CODE'];
                    break;
                case "S":
                    $set_value = $arr['USER_NUMBER'];
                    break;
                case "T":
                    $set_value = $arr['TRANSACTION_REFERENCE'];
                    break;
                case "U":
                    $set_value = $arr['PRODUCT_TYPE'];
                    break;
                case "V":
                    $set_value = $arr['BENEFICIARY_ADHAAR_NUMBER'];
                    break;
                case "W":
                    $set_value = $arr['UMRN'];
                    break;
                default:
                    $set_value = "";
            }
            //$objPHPExcel->getActiveSheet()->SetCellValue($id . $rowCount, $set_value);
            $objPHPExcel->getActiveSheet()->setCellValueExplicit($id . $rowCount, $set_value,'s');
        }
    }
    $objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);


    $name = "NACH_S3_EXPORT_".date("Y_m_d_H_i_s");
    if(!file_exists('storage/NachExcel')){
        mkdir('storage/NachExcel/',0777);
    }
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
