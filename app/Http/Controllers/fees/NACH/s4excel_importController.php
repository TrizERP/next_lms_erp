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
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use App\Http\Controllers\AJAXController;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\FeeMonthId;
use App\Models\easy_com\manage_sms_api\manage_sms_api;

require('excel_upload/PHPExcel/IOFactory.php');
require('excel_upload/PHPExcel/Shared/Date.php');
require('excel_upload/PHPExcel/Cell.php');
use PHPExcel_IOFactory;
use PHPExcel_Shared_Date;
use PHPExcel_Cell;
// 9724348847

class s4excel_importController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) 
	{
		$type = $request->input('type');		
		$res = array();		
		$res['fee_month'] = FeeMonthId();
		
		return is_mobile($type, "fees/NACH/show_s4_excel_import", $res, "view");
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
		$user_id = $request->session()->get('user_id');
		$MONTH_ID = $request->input('month_id');

		$NACH_master = DB::select("select * from fees_config_master f where f.sub_institute_id = '".$sub_institute_id."'");
		$NACH_master = json_decode(json_encode($NACH_master[0]),true);

		define('FAILEDCHARGE', $NACH_master['nach_failed_charge']);
		define('TRANSACTIONCHARGE', $NACH_master['nach_transaction_charge']);
		define('REGISTRATIONAMT', $NACH_master['nach_registration_charge']);

		$DEPOSITED_BANK_ACCOUNT_ID_CONST = '1';
		$PAYMENT_MODE_CONST = 'NACH';	

		$searchArr = array("'", '"', ',');
		$replaceArr = array("\'", '\"', '');

		$successStatusArr = array(
		    'REALISED', 'SUCCESS', 'Completed'
		);

		$falilureStatusArr = array(
		    'RETURN', 'failure', 'failed','Returned','NPCI Reject'
		);
		if (!file_exists('storage/NachExcel/Uploads/')) {
			mkdir('storage/NachExcel/Uploads/', 0777, true);
		}
		
		if($request->hasFile('s4file'))
        {
			$file = $request->file('s4file');
			if ($file->isValid()) {
            $originalname = $file->getClientOriginalName();
            $name = "NACH_S4_Import_".date('Y_m_d_H_i_s');
            $ext = \File::extension($originalname);
			$file_name = $name . "." . $ext;

			$path = $file->storeAs('public/NachExcel/Uploads/',$file_name);    

			$filePath = 'NachExcel/Uploads/' . $file_name;
			$inputFileName = storage_path('app/public/' . $filePath);

			$inputFileType = PHPExcel_IOFactory::identify($inputFileName);
			$objReader = PHPExcel_IOFactory::createReader($inputFileType);
			$objPHPExcel = $objReader->load($inputFileName);					
			
            $worksheet = $objPHPExcel->getSheet(0);
	        $highestRow = $worksheet->getHighestRow();
	        $highestColumn = $worksheet->getHighestColumn();
	        $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
	        $full_array = array();
	        for ($row = 1; $row <= $highestRow; $row++) {
	            for ($col = 0; $col < $highestColumnIndex; $col++) {
	                $cell = $worksheet->getCellByColumnAndRow($col, $row);
	                $val = $cell->getValue();
	                $full_array[$row][$col] = $val;
	            }
	        }
	        $dataArr = array();
	        foreach ($full_array as $key => $subValue) {
	            $subValue = array_filter($subValue);
	            if (isset($subValue) && !empty($subValue)) {
	                $dataArr[$key] = $subValue;
	            }
	        }

	        $dataArr = array_values($dataArr);
	        
	        if (!empty($dataArr)) 
	        {
	            $failed_str = $failed_bnk_str = $style_str = $not_found_str = $fees_paid_str = "";

	            
	            $titleArr = $dataArr[0];

	            $not_found_str.="<div class=cls_not_found_whole_str>";
	            $not_found_str.="<div class=cls_not_found_print_str>";
	            // $not_found_str.='<div><center><input type="button" onclick="javascript:printreport_title(\'id_not_found_list\', \'\');" value="Print" name="pdf" class="btn btn-success"></center></div>';
	            $not_found_str .='<div id=fee_print></div>';
	            $not_found_str.="<div id=fee_print2></div>";
	            $not_found_str.="<div style=clear:both;>&nbsp;</div>";
	            $not_found_str.="</div>";

	            $not_found_str.="<div id=id_not_found_list >";
	            $not_found_str.=$style_str;
	            $not_found_str.="<table class='table table-bordered table-striped'>";
	            $not_found_str.="<tr>";
	            $not_found_str.="<th colspan=30><b>Not Found Students List</b></th>";
	            $not_found_str.="</tr>";
	            $not_found_str.="<tr class=cls_not_found_headings_lbl>";
	            foreach ($titleArr as $value) {
	                $not_found_str .= "<td><b>$value</b></td>";
	            }
	            $not_found_str.="</tr>";

	            $fees_paid_str.="<div class=cls_fees_paid_whole_str>";
	            $fees_paid_str.="<div class=cls_fees_paid_print_str>";
	            // $fees_paid_str.='<div> <center><input type="button" onclick="javascript:printreport_title(\'id_fees_already_paid_list\', \'\');" value="Print" name="pdf" class="btn btn-success"></center></div>';
	            $fees_paid_str .='<div id=fee_print></div>';
	            $fees_paid_str.="<div id=fee_print2></div>";
	            $fees_paid_str.="<div style=clear:both;>&nbsp;</div>";
	            $fees_paid_str.="</div>";

	            $fees_paid_str.="<div id=id_fees_already_paid_list >";
	            $fees_paid_str.=$style_str;
	            $fees_paid_str.="<table class='table table-bordered table-striped'>";
	            $fees_paid_str.="<tr>";
	            $fees_paid_str.="<th colspan=30><b>Fees Already Paid Students List</b></th>";
	            $fees_paid_str.="</tr>";
	            $fees_paid_str.="<tr class=cls_fees_paid_headings_lbl>";
	            foreach ($titleArr as $value) {
	                $fees_paid_str .= "<td><b>$value</b></td>";
	            }
	            $fees_paid_str.="</tr>";


	            $failed_str.="<div class=cls_not_found_whole_str>";
	            $failed_str.="<div class=cls_not_found_print_str>";
	            // $failed_str.='<div><center><input type="button" onclick="javascript:printreport_title(\'id_not_found_list\', \'\');" value="Print" name="pdf" class="btn btn-success"></center></div>';
	            $failed_str .='<div id=fee_print></div>';
	            $failed_str.="<div id=fee_print2></div>";
	            $failed_str.="<div style=clear:both;>&nbsp;</div>";
	            $failed_str.="</div>";

	            $failed_str.="<div id=id_not_found_list >";
	            $failed_str.=$style_str;
	            $failed_str.="<table class='table table-bordered table-striped'>";
	            $failed_str.="<tr>";
	            $failed_str.="<th colspan=30><b>Not Found Students List</b></th>";
	            $failed_str.="</tr>";
	            $failed_str.="<tr class=cls_not_found_headings_lbl>";
	            foreach ($titleArr as $value) {
	                $failed_str .= "<td><b>$value</b></td>";
	            }
	            $failed_str.="</tr>";


	            $failed_bnk_str.="<div class=cls_not_found_whole_str>";
	            $failed_bnk_str.="<div class=cls_not_found_print_str>";
	            // $failed_bnk_str.='<div><center><input type="button" onclick="javascript:printreport_title(\'id_not_found_list\', \'\');" value="Print" name="pdf" class="btn btn-success"></center></div>';
	            $failed_bnk_str .='<div id=fee_print></div>';
	            $failed_bnk_str.="<div id=fee_print2></div>";
	            $failed_bnk_str.="<div style=clear:both;>&nbsp;</div>";
	            $failed_bnk_str.="</div>";

	            $failed_bnk_str.="<div id=id_not_found_list >";
	            $failed_bnk_str.=$style_str;
	            $failed_bnk_str.="<table class='table table-bordered table-striped'>";
	            $failed_bnk_str.="<tr>";
	            $failed_bnk_str.="<th colspan=30><b>Returned Students List</b></th>";
	            $failed_bnk_str.="</tr>";
	            $failed_bnk_str.="<tr class=cls_not_found_headings_lbl>";
	            foreach ($titleArr as $value) {
	                $failed_bnk_str .= "<td><b>$value</b></td>";
	            }
	            $failed_bnk_str.="</tr>";

	            $m = 0;
	            $failed_chk_flg = 0;
	            $failed_bnk_chk_flg = 0;
	            $not_found_chk_flg = 0;
	            $fees_paid_chk_flg = 0;

	            $totalRecords = count($dataArr) - 1;
	            
	            $successCnt = $failureCnt = $notFoundCnt = $paidCnt = $maxCnt = $returned = 0;
	     
	            foreach ($dataArr as $value) 
	            {	    	           
                	if ($m == 0) 
                	{
						$maxCnt = count($titleArr);
                	}
                	if ($m >= 1) 
                	{
	                    $AC_HOLDER_NAME = isset($value[5]) ? str_replace($searchArr, $replaceArr, $value[5]) : '';
	                    
	                    $DATE = isset($value[6]) ? $value[6] : '';
	                    $YEAR = substr(trim($DATE),0,4);
	                    $MONTH = substr(substr(trim($DATE),4),0,2);
	                    $DAY = substr(trim($DATE),-2);
	                    $FEES_DATE_VALUES_DB = $YEAR."-".$MONTH."-".$DAY." 12:00:00";
	                    $FEES_CHEQUE_DD_DATE_VALUES_DB = $YEAR."-".$MONTH."-".$DAY;
	                    //$MONTH_ID = ltrim($MONTH, '0').$YEAR;	                   

						$STUDENT_FEES_AMOUNT = isset($value[10]) ? str_replace($searchArr, $replaceArr, $value[10]) : 0;
	                    
	                    $IFSC_CODE = isset($value[15]) ? $value[15] : '';
	                    $AC_NUMBER = isset($value[16]) ? $value[16] : '';
	                    $SPONSOR_IFSC_CODE = isset($value[17]) ? str_replace($searchArr, $replaceArr, $value[17]) : '';
	                    $TRANSACTION_REF = isset($value[19]) ? str_replace($searchArr, $replaceArr, $value[19]) : '';

	                    $TRANSACTION_STATUS = isset($value[23]) ? str_replace($searchArr, $replaceArr, $value[23]) : '';
	                    $TRANSACTION_CODE = isset($value[24]) ? str_replace($searchArr, $replaceArr, $value[24]) : '';
	                    $TRANSACTION_REMARKS = isset($value[25]) ? str_replace($searchArr, $replaceArr, $value[25]) : '';

	                    $TRANSACTION_REF_ARR = explode(' ', $TRANSACTION_REF);

	                    $STUDENT_GR_NO = $TRANSACTION_REF_ARR[0];
	                    $STUDENT_NAME = trim(str_replace($TRANSACTION_REF_ARR[0], '', $TRANSACTION_REF));
	                    $REMARKS = $TRANSACTION_REMARKS;
	                    if ($REMARKS == '') {
	                        $REMARKS = '-';
	                    }

	                    $STUDENT_DETAILS = $this->get_students_general_details_with_multiple_parameters($STUDENT_NAME, $STUDENT_GR_NO, $sub_institute_id, $syear);
						$STUDENT_ID =  "";
						// echo "<pre>";print_r($STUDENT_DETAILS);
                    	if (in_array($TRANSACTION_STATUS, $successStatusArr)) 
                    	{
                       		
	                        if (empty($STUDENT_DETAILS)) 
	                        {
	                            // Get Not Found Students
	                            $not_found_str.="<tr>";
	                            for ($i = 0; $i < $maxCnt; $i++) 
	                            {
	                                $not_found_str.="<td>" . (isset($value[$i]) ? $value[$i] : '') . "</td>";
	                            }
	                            $not_found_str.="</tr>";
	                            $not_found_chk_flg = 1;
	                            $notFoundCnt++;
	                        } 
	                        else
	                        {
								$STUDENT_ID = $STUDENT_DETAILS['STUDENT_ID'];
	                            // Get Already Paid Fees Students
								$fees_paid_chk = $this->is_fees_paid_chk($STUDENT_ID,$MONTH_ID,$syear);

	                            if (!empty($fees_paid_chk) || $fees_paid_chk !== "") 
	                            {
	                                $fees_paid_str.="<tr>";
	                                //echo $maxCnt;
	                                for ($i = 0; $i < $maxCnt; $i++) 
	                                {
	                                    $fees_paid_str.="<td>" . (isset($value[$i]) ? $value[$i] : '') . "</td>";
	                                }
	                                $fees_paid_str.="</tr>";

	                                $fees_paid_chk_flg = 1;
	                                $STUDENT_ID = "";
	                                $paidCnt++;
	                            }	                            
	                        }

	                        if ($STUDENT_ID != "") 
	                        {
	                            $pay_month = array($MONTH_ID => $MONTH_ID);

	                            //START Fees paid code
	                            $controller = new fees_collect_controller;
	                            $ajx_controller = new AJAXController;

	                            $arr["student_id"] = $STUDENT_ID;
					            $arr["months"] = $pay_month;

					            $fees_bk_data = $controller->getOnlinebk($request, $sub_institute_id, $syear, $STUDENT_ID);
								$fees_month = $ajx_controller->getOnlineFeesMonth($arr);
								// echo "<pre>";print_r($fees_bk_data);								
								
							if (!empty($fees_bk_data)) {
					            
					            $total_fees = $fees_month["Total"];
					            unset($fees_month["Total"]);
					            $final_fees_arr = array();
					            foreach ($fees_month as $id => $val) 
					            {
					                $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] = $val;
					            }

					            $failedCnt = 0;
					            /*$failSql = "SELECT COUNT(id) as failedCnt FROM STUDENT_FEES_FAILURE WHERE student_id =  $STUDENT_ID AND marking_period_id = '" . UserMP() . "' AND syear = '" . UserSyear() . "'";
	                            $failSqlRet = DBGet(DBQuery($failSql));
	                            $failSqlRet = $failSqlRet[1];
	                            $failedCnt = 0;
	                            if (!empty($failSqlRet)) {
	                                $failedCnt = $failSqlRet['FAILEDCNT'];
	                            }*/
	                            $stuSql = "SELECT * FROM tblstudent_bank_detail WHERE student_id = '".$STUDENT_ID."' AND sub_institute_id = '".$sub_institute_id."'";
	                            $stuSqlRet = DB::select($stuSql);
								$stuSqlRet = json_decode(json_encode($stuSqlRet),true);

								$registrationAmt = 0;
								if (!empty($stuSqlRet) && isset($stuSqlRet[0])) {
								    $stuSqlRet = $stuSqlRet[0];
								    if ($stuSqlRet['is_registered'] == 'N') 
		                            {
		                                $registrationAmt = REGISTRATIONAMT;
		                            }
								}
	                            //dd($stuSqlRet);

	                            $failedAmt = $failedCnt * FAILEDCHARGE;	                            
	                            $FINE = $failedAmt + TRANSACTIONCHARGE + $registrationAmt;
	                            if ($FINE == "") 
	                            {
	                                $FINE = 0;
	                            }
					            $final_fees_arr["fine"] = $FINE;

					            $k=1;
					            foreach ($final_fees_arr as $id => $val) 
					            {
					                $discount_data_arr[$id] = 0;
					                if($k == 1)
					                {
					                	$fine_data_arr[$id] = $FINE;
					                }
					                else
					                {
					                	$fine_data_arr[$id] = 0;
					                }
					                $k++;
					            }

					            
					            
	                            $send_arr = array(
					                "grade_id" => $STUDENT_DETAILS['GRADE_ID'],
					                "standard_id" => $STUDENT_DETAILS['STANDARD_ID'],
					                "div_id" => $STUDENT_DETAILS['SECTION_ID'],
					                "student_id" => $STUDENT_DETAILS['STUDENT_ID'],
					                "std_div" => $STUDENT_DETAILS['BRANCH']."/".$STUDENT_DETAILS['SECTION_NAME'],
					                "full_name" => $STUDENT_DETAILS['FULL_NAME'],
					                "enrollment" => $STUDENT_DETAILS['ENROLLMENT_NO'],
					                "mobile" => $STUDENT_DETAILS['MOBILE_NUMBER'],
					                "uniqueid" => $STUDENT_DETAILS['UNIQUEID'],
					                "months" => $pay_month,
					                "fees_data" => $final_fees_arr,	
					                "discount_data" =>  $discount_data_arr, 
                					"fine_data" => $fine_data_arr,				                
					                "total" => $total_fees,
					                "totalDis" => 0,
					                "totalFin" => 0,
					                "PAYMENT_MODE" => "NACH",
					                "receiptdate" => $FEES_CHEQUE_DD_DATE_VALUES_DB, // date("Y-m-d"),
					                "cheque_date" => "",
					                "cheque_no" => "",
					                "bank_name" => "",
					                "bank_branch" => "",
					                "submit" => "Save",
					            );

								$_REQUEST = $send_arr;
								//echo "<pre>";
								//print_r($_REQUEST);
								//die();exit();
 								$paid_fees =  $controller->pay_fees($request);
								// echo '<pre>';
								// print_r($paid_fees);
							if (!empty($fees_bk_data) || $fees_bk_data !== "") {
									$successCnt++;
									$upSql = "UPDATE tblstudent_bank_detail SET is_registered = 'Y' WHERE student_id = '".$STUDENT_ID."'";
                                    DB::select($upSql);
								}
								else 
								{
									$failureCnt++;
									$failed_str.="<tr>";
									for ($i = 0; $i < $maxCnt; $i++) {
										$failed_str.="<td>" . (isset($value[$i]) ? $value[$i] : '') . "</td>";
									}
									$failed_str.="</tr>";

									$failed_chk_flg = 1;
								}
							}else 
							{
								$not_found_str.="<tr>";
	                            for ($i = 0; $i < $maxCnt; $i++) 
	                            {
	                                $not_found_str.="<td>" . (isset($value[$i]) ? $value[$i] : '') . "</td>";
	                            }
	                            $not_found_str.="</tr>";
	                            $not_found_chk_flg = 1;
	                            $notFoundCnt++;
							}
								
                        }
                    } 
                 if (in_array($TRANSACTION_STATUS, $falilureStatusArr)) 
                    {      
                        if (empty($STUDENT_DETAILS)) 
	                        {
	                            // Get Not Found Students
	                            $not_found_str.="<tr>";
	                            for ($i = 0; $i < $maxCnt; $i++) 
	                            {
	                                $not_found_str.="<td>" . (isset($value[$i]) ? $value[$i] : '') . "</td>";
	                            }
	                            $not_found_str.="</tr>";
	                            $not_found_chk_flg = 1;
	                            $notFoundCnt++;
	                        } 
	                        else 
						{
						$STUDENT_ID = $STUDENT_DETAILS['STUDENT_ID'];
							
							$check = DB::table('tblstudent_fees_failure')->whereRaw("student_id='".$STUDENT_ID."' AND month_id='".$MONTH_ID."' AND  syear='".$syear."' AND sub_institute_id='".$sub_institute_id."' AND amount='".$STUDENT_FEES_AMOUNT."' AND DATE_FORMAT(created_on, '%Y-%m-%d') = '".$FEES_CHEQUE_DD_DATE_VALUES_DB."' ")->get()->toArray();
							if(empty($check)){
								$failInsSql = "INSERT INTO tblstudent_fees_failure
								(student_id,month_id, syear, sub_institute_id,amount, remarks, created_by)
								VALUES('".$STUDENT_ID."', '".$MONTH_ID."', '".$syear."','".$sub_institute_id."','".$STUDENT_FEES_AMOUNT."',
								'".$REMARKS."','".$user_id."')";
								DB::select($failInsSql);
							}
							$returned++;							

							$sms_text = "Dear Parents, Your Monthly Fee NACH is returned from the bank. Please arrange Sufficient Funds";
							$send_sms = $this->sendSMS($STUDENT_DETAILS['MOBILE_NUMBER'], $sms_text, $sub_institute_id);
							if (isset($send_sms['error']) && $send_sms['error'] == 1) {
							 	break;
							 } else {
							 	DB::table('sms_sent_parents')->insert([
							 		'SYEAR'            => $syear,
							 		'STUDENT_ID'       => $STUDENT_DETAILS['STUDENT_ID'],
							 		'SMS_TEXT'         => $sms_text,
							 		'SMS_NO'           => $STUDENT_DETAILS['MOBILE_NUMBER'],
							 		'MODULE_NAME'      => 'S4 NACH',
							 		'sub_institute_id' => $sub_institute_id,
							 	]);
							 }
						 // print_R($send_sms);
							
                            $failed_bnk_chk_flg = 1;
							
                            $failed_bnk_str.="<tr>";
                            for ($i = 0; $i < $maxCnt; $i++) {
                                $failed_bnk_str.="<td>" . (isset($value[$i]) ? $value[$i] : '') . "</td>";
                            }
							$failed_bnk_str.="</tr>";
							

                            if ($TRANSACTION_STATUS == 'account mismatch') 
							{
                                $upSql = "UPDATE tblstudent_bank_detail  SET is_registered = 'N' WHERE student_id = '".$STUDENT_ID."'";
                                DB::select($upSql);
                            } else 
							{
                                $upSql = "UPDATE tblstudent_bank_detail SET is_registered = 'Y' WHERE student_id = '".$STUDENT_ID."'";
                                DB::select($upSql);
                            }
//                            $mess = "<center><font color=red>Fees has been paid successfully.</font></center>";
                        }
					}
					
                  
                }
                $m++;
				}
				// exit;
					$not_found_str.="</table>";
					//$not_found_str.="<div style=clear:both;>&nbsp;</div>";
					$not_found_str.="</div>";
					$not_found_str.="</div>";

					$fees_paid_str.="</table>";
					//$fees_paid_str.="<div style=clear:both;>&nbsp;</div>";
					$fees_paid_str.="</div>";
					$fees_paid_str.="</div>";

					$failed_str.="</table>";
					//$failed_str.="<div style=clear:both;>&nbsp;</div>";
					$failed_str.="</div>";
					$failed_str.="</div>";

					$failed_bnk_str.="</table>";
					//$failed_bnk_str.="<div style=clear:both;>&nbsp;</div>";
					$failed_bnk_str.="</div>";
					$failed_bnk_str.="</div>";

					$mess = '<font color=red>Fees has been imported successfully.<br/>'
							. '<br/>Total Records : ' . $totalRecords . '<br/>'
							. '<br/>Success Records : ' . $successCnt . '<br/>'
							. '<br/>Failed Records : ' . $failureCnt . '<br/>'
							. '<br/>Returned Failed Records : ' . $returned . '<br/>'							
							. '<br/>Not Found Records : ' . $notFoundCnt . '<br/>'
							. '<br/>Already Paid Records : ' . $paidCnt . '<br/>'
							. '</font>';

					if (!empty($mess)) {
						//$mess.="<div style=clear:both;>&nbsp;</div>";						
					}

					if ($not_found_chk_flg == 1) {
						$mess.= $not_found_str;
					}

					if ($fees_paid_chk_flg == 1) {
						$mess.= $fees_paid_str;
					}

					if ($failed_chk_flg == 1) {
						$mess.= $failed_str;
					}

					if ($failed_bnk_chk_flg == 1) {
						$mess.= $failed_bnk_str;
					}
					
					$res['status_code'] = 1;
					$res['message'] = $mess;					
			}
		} else {
			die('Invalid file upload.');
		}
    	}
		else
		{
			$res['status_code'] = 0;
			$res['message'] = "Please select file to import fees";
		}
		$res['fee_month'] = FeeMonthId();
		return is_mobile($type, "fees/NACH/show_s4_excel_import", $res, "view");
	}
	
	public function get_students_general_details_with_multiple_parameters($STUDENT_FULL_NAME,$STUDENT_GR_NO,$sub_institute_id,$syear)
	{           
        $studet_sql = 
        	"SELECT CONCAT_WS(' ',s.first_name,s.last_name) AS FULL_NAME ,s.enrollment_no as ENROLLMENT_NO,s.id AS STUDENT_ID
			,s.admission_year,a.title AS ACADEMIC_YEAR,st.name AS BRANCH,sq.title AS STUDENT_QUOTA,se.standard_id as STANDARD_ID,
			d.name AS SECTION_NAME,se.section_id as SECTION_ID,se.student_quota AS STUDENT_QUOTA1,se.start_date AS STUDENT_ENROLLMENT_DATE,
			se.roll_no AS STUDENT_ROLL_NO,s.gender AS STUDENT_GENDER,se.grade_id as GRADE_ID,s.mobile as MOBILE_NUMBER,
			s.uniqueid as UNIQUEID
			FROM tblstudent s
			INNER JOIN tblstudent_enrollment se ON s.id = se.student_id
			INNER JOIN academic_section a ON a.id = se.grade_id 
			INNER JOIN standard st ON st.id = se.standard_id
			LEFT JOIN division d ON d.id = se.section_id
			LEFT JOIN student_quota sq ON sq.id = se.student_quota
			WHERE s.sub_institute_id = '".$sub_institute_id."' AND se.syear = '".$syear."' AND s.enrollment_no = '".$STUDENT_GR_NO."' 
			AND if (se.END_DATE IS NOT NULL,se.END_DATE >= CURDATE(),se.END_DATE IS NULL)
			"; //AND (CONCAT_WS(' ',FIRST_NAME,LAST_NAME) LIKE CONCAT('%','$STUDENT_FULL_NAME','%')) 
// echo $studet_sql;
// die();
		$stud_data = DB::select($studet_sql);
		$stud_data = json_decode(json_encode($stud_data),true);
// echo "<pre>";print_r($stud_data);
// die();
		// dd($syear);
		if(count($stud_data) > 0)
		{
			$return_arr = $stud_data[0];
		}
		else
		{
			$return_arr = "";
		}
	    return $return_arr;
	}

	public function is_fees_paid_chk($STUDENT_ID, $MARKING_PERIOD_ID, $SYEAR)
	{   
	    $sql = "SELECT * FROM fees_collect
	            WHERE student_id='".$STUDENT_ID."'
	            AND term_id='".$MARKING_PERIOD_ID."'
	            AND syear ='".$SYEAR."' AND is_deleted = 'N'
	           ";
	   $fees_paid_details = DB::select($sql);   
	   $fees_paid_details = json_decode(json_encode($fees_paid_details),true);
	   if(count($fees_paid_details) > 0)
	   {
	   		$return_arr = $fees_paid_details[0]; 
	   }
	   else
	   {
	   		$return_arr = ""; 
	   }
	   	   
	   return $return_arr;
	}
	public function sendSMS($mobile, $text, $sub_institute_id)
    {
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first();

        if ($data) {
            $data = $data->toArray();
            $isError = 0;
            $errorMessage = true;

            $text = urlencode($text);
            $data['last_var'] = urlencode($data['last_var']);

            $url = $data['url'].$data['pram'].$data['mobile_var'].$mobile.$data['text_var'].$text.$data['last_var'];
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            $output = curl_exec($ch);

            if (curl_errno($ch)) {
                $isError = true;
                $errorMessage = curl_error($ch);
            }
            curl_close($ch);
        } else {
			$isError = 1;
			$errorMessage = "Please add api details first.";
        }
        $responce = [];
        if ($isError) {
            $responce = ['error' => 1, 'message' => $errorMessage];
		}
		 else {
            $responce = ['message' => "Sent Message Successfully!!"];
        }

        return $responce;
    }
}
