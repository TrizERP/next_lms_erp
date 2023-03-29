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

require('excel_upload/PHPExcel/IOFactory.php');
require('excel_upload/PHPExcel/Shared/Date.php');
use PHPExcel_IOFactory;
use PHPExcel_Shared_Date;


class s2excel_importController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) 
	{
		$type = $request->input('type');		
		$res['status'] = 1;
		$res['message'] = "Success";
		
		return is_mobile($type, "fees/NACH/show_s2_excel_import", $res, "view");
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) 
	{        
		$type = $request->input('type');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$syear = $request->session()->get('syear');

		if($request->hasFile('s2file'))
        {
            $file = $request->file('s2file');
            $originalname = $file->getClientOriginalName();
            $name = "NACH_S2_Import_".date('YmdHis');
            $ext = \File::extension($originalname);
            $file_name = $name . "." . $ext;
            $path = $file->storeAs('public/NachExcel/Uploads/',$file_name);        

            $inputFileName = 'storage/NachExcel/Uploads/'.$file_name;
            try {
                $inputFileType = PHPExcel_IOFactory::identify($inputFileName);
                $objReader = PHPExcel_IOFactory::createReader($inputFileType);
                $objPHPExcel = $objReader->load($inputFileName);
            } catch (Exception $e) {
                die('Error loading file "' . pathinfo($inputFileName, PATHINFO_BASENAME) . '": ' . $e->getMessage());
            }

            $sheet = $objPHPExcel->getSheet(0);
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();

            $count = 0;
            for ($row = 0; $row <= $highestRow; $row++) 
            {
                $count = $count + 1;
                if ($count > 2) {
                    $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);
                    $rowData = $rowData[0];                    
                    $rowData[2] = date('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($rowData[2]));
                    $rowData[13] = date('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($rowData[13]));
                    $rowData[35] = date('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($rowData[35]));
                    $this->insert_data($rowData,$sub_institute_id);
                }
            }
    	}	
		$res['status_code'] = 1;
		$res['message'] = "S2 File Imported Successfully";
		
		return is_mobile($type, "fees/NACH/show_s2_excel_import", $res, "view");
	}

	public function insert_data($arr,$sub_institute_id)
	{		
        $insert_qry = "INSERT INTO `S2_LOG` 
                (`LOT_NO`, `MESSAGE_ID`, `MESSAGE_CREATION`, `INITIATING_PARTY_ID`, `INSTRUCTING_AGENT_MEMBER_ID`, `INSTRUCTED_AGENT_MEMBER_ID`, 
                `INSTRUCTED_AGENT_NAME`, `MANDATE_REQUEST_ID`, `MANDATE_CATEGORY`, `MANDATE_CATEGORY_NAME`, `TXN_TYPE`, `RECURRING`, `FREQUENCY`, 
                `FIRST_COLLECTION_DATE`, `FINAL_COLLECTION_DATE`, `COLLECTION_AMOUNT`, `MAXIMUM_AMOUNT`, `NAME_OF_UTILITY`, `UTILITY_CODE`, 
                `SPONSOR_BANK_CODE`, `NAME_OF_ACCOUNT_HOLDER`, `CONSUMER_REFERENCE_NO`, `SCHEME_PLAN_REFERENCE_NO`, `DEBTOR_TELEPHONE_NO`, `DEBTOR_MOBILE_NO`,
                `DEBTOR_EMAIL_ADD`, `DEBTOR_OTHER_DETAILS`, `DESTINATION_BANK_ACCOUNT_NUMBER`, `DESTINATION_BANK_ACCOUNT_TYPE`, `DESTINATION_BANK_IFSC`, 
                `DESTINATION_BANK_NAME`, `UMRN_NO`, `STATUS_`, `RTN_CODE`, `REASON`, `CLOSURE_DATE`,`TRUST_ID`) 
                VALUES 
                ( ";           
            
        foreach ($arr as $id => $val) 
        {
            if ($id == '37')
                continue;
            $insert_qry .= "'" . $val . "',";
            if ($id == '31')//GET UMRN Number
            {
            	$UMRN_NO = $val;
            }
            if ($id == '22')//GET Student Enrollment (SCHEME_PLAN_REFERENCE_NO)
            {
            	$student_enrollment = $val;
            }
        }
        $insert_qry = rtrim($insert_qry, 'NULL,');
        $insert_qry .= ')';  

        DB::select($insert_qry);

        $update_qry = "UPDATE tblstudent_bank_detail s SET s.UMRN = '".$UMRN_NO."' ,s.is_registered='Y' where student_id = (
				SELECT id FROM tblstudent WHERE enrollment_no = '".$student_enrollment."' AND sub_institute_id = '".$sub_institute_id."'
        	)";   
        DB::select($update_qry);               
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
