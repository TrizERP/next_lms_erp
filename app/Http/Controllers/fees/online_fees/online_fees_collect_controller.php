<?php

namespace App\Http\Controllers\fees\online_fees;

use App\Classes\AesForJava;
use App\Http\Controllers\AJAXController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Http;
use App\Models\fees\tblfeesConfigModel;
use Illuminate\Support\Str;
use function App\Helpers\fees_config;
use Illuminate\Support\Facades\Crypt;
use function App\Helpers\FeeBreackoff;
use function App\Helpers\OtherBreackOff;
use function App\Helpers\OtherBreackOfMonth;
use function App\Helpers\OtherBreackOfMonthHead;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\FeeMonthId;
use Validator;

class online_fees_collect_controller extends Controller
{
    public function index(Request $request)
    {
        // echo '<pre>'; print_r($_REQUEST); exit;
        $school_data = array();
        $school_data["website"] = $this->site_name();
        $type = "web";
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/search_student", $school_data, "view");
    }

    public function site_name()
    {
        $site_name = env('APP_URL');
        return $site_name;
    }

    public function get_fees(Request $request)
    {
        // echo '<pre>'; print_r($_REQUEST); exit;
        $all_student = DB::table("tblstudent as s")
            ->join('fees_online_maping as fo', 'fo.sub_institute_id', '=', 's.sub_institute_id')
            ->select(
                DB::raw("CONCAT(s.first_name,' ',s.last_name) AS name"),
                's.id',
                'fo.bank_name',
                's.sub_institute_id',
                'fo.fees_type'
            )
            ->where("s.id", $_REQUEST["student_id"])
            ->get();
            // added school_setup join on 15-03-2025 for lions fees issue
        $get_syear = DB::select("SELECT s.id,s.mobile,se.syear,se.sub_institute_id,s.admission_under
                                FROM tblstudent s
                                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id
                                INNER JOIN school_setup ss ON se.sub_institute_id = ss.Id AND ss.syear = se.syear
                                WHERE s.id = '" . $_REQUEST["student_id"] . "'
                                ORDER BY se.syear desc");
        if (isset($_REQUEST["syear"])) {
            $year = $_REQUEST["syear"];
        } else {
            $year = $get_syear['0']->syear; //date("Y");
        }
        // echo '<pre>'; print_r($_REQUEST); exit;
        $CurruntYear = $get_syear['0']->syear; //date("Y");
        $admission_under = $get_syear['0']->admission_under; 
        $controller = new fees_collect_controller;
        // echo '<pre>'; print_r($_REQUEST); exit;
        if($all_student[0]->sub_institute_id != 48 && $all_student[0]->sub_institute_id != 61){
            $OldData = $controller->getOnlinebk($request, $all_student[0]->sub_institute_id, $year - 1, $_REQUEST["student_id"]);
        }
        $data = $controller->getOnlinebk($request, $all_student[0]->sub_institute_id, $year, $_REQUEST["student_id"]);
        
        // echo $year;
        $fees_amt = 0;
        // echo '<pre>'; print_r($data); exit;
        if (isset($OldData["final_fee"])) {
            $get_old_data = 0;
            if($OldData["final_fee"]['Total']!=0){
                    $get_old_data = 1;    
                    $fees_amt = $OldData["final_fee"]['Total'];            
            }
            // foreach ($OldData["final_fee"] as $id => $arr) {
            //     if ($arr["month"] == "Total" && $arr["remain"] != 0) {
            //         $get_old_data = 1;
            //         $fees_amt = $arr["remain"];
            //     }
            // }
            
            // Start Add code for showing previous year fees bf 25-11-2021
            if ($get_old_data == 1) {
                $data = $OldData;
            }
            // End Add code for showing previous year fees bf 25-11-2021
        }
        $dd_arr = array();
        if ($fees_amt != 0) {
            $data["error"] = "Old Fees Remainig";
            for ($i = ($year - 1); $i <= $CurruntYear; $i++) {
                $dd_arr[$i] = $i;
            }
        } else {
            $data["error"] = "";
            for ($i = ($year); $i <= $CurruntYear; $i++) {
                $dd_arr[$i] = $i;
            }
        }

        $data['fees_config_data'] = tblfeesConfigModel::where([
            'sub_institute_id' => $all_student[0]->sub_institute_id, 'syear' => $year,
        ])->get()->toArray();

        $data["redirect_url"] = $_SERVER["HTTP_ORIGIN"] . $_SERVER["REQUEST_URI"];
        $data["dd_arr"] = $dd_arr;
        $data["student_id"] = $_REQUEST["student_id"];
        $data["cur_year"] = $year;
        // echo '<pre>'; print_r($dd_arr); exit;
        $data["fees_type"] = $all_student[0]->fees_type;
        $data["syear"] = $year;
        $data["admission_under"] = $admission_under;

        $data["discountData"] = DB::table('general_data')->where('sub_institute_id',$all_student[0]->sub_institute_id)->where('fieldname','fees_bulk_discount')->first();

        $data["currentMonth"] = date('n').date('Y');
        return $data;
    }

    public function hdfc(Request $request)
    {
        $data = $this->get_fees($request);
        $type = "web";
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_fees", $data, "view");
    }

    public function hdfc_request_handler(Request $request)
    {
        // echo '<pre>';
        // print_r($_REQUEST);
        // print_r(session()->all());
        // exit;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $searchArr = array(' ','"',"\'",',',"'");
        $replaceArr = array('','',"",' ',"");
        $student_id = $_REQUEST["student_id"];
        $fine = isset($_REQUEST["fees_data"]["fine"]) ? $_REQUEST["fees_data"]["fine"] : 0;
        $discount = isset($_REQUEST["totalDis"]) ? $_REQUEST["totalDis"] : 0;
        $medium_data = DB::select("SELECT a.*,e.grade_id,s.name AS standard, d.name AS division,CONCAT_WS('_',t.first_name,t.middle_name,t.last_name) AS student_name,t.address,t.state,t.city,t.pincode,t.mobile,t.enrollment_no, t.email,ifnull(b.title,0) AS batch 
            FROM tblstudent_enrollment e
            inner join academic_section a on e.grade_id = a.id
            inner join standard s on e.standard_id = s.id
            inner join division d on e.section_id = d.id
            INNER JOIN tblstudent t ON t.id=e.student_id
            LEFT JOIN batch b ON b.id=t.studentbatch
            INNER JOIN fees_online_maping fom ON fom.syear=e.syear AND fom.sub_institute_id=e.sub_institute_id
            WHERE e.student_id = '" . $student_id . "' ORDER BY e.syear DESC LIMIT 1");

        $billing_name = str_replace($searchArr, $replaceArr, $medium_data[0]->student_name);

        $billing_address = isset($medium_data[0]->address) ? $medium_data[0]->address : 'NA';
        $billing_address = str_replace($searchArr, $replaceArr, $billing_address);
        
        $billing_city = isset($medium_data[0]->city) ? $medium_data[0]->city : 'NA';
        $billing_state = isset($medium_data[0]->state) ? $medium_data[0]->state : 'GJ';
        $billing_zip = isset($medium_data[0]->pincode) ? $medium_data[0]->pincode : 'NA';
        $billing_country = 'India';
        $billing_tel = isset($medium_data[0]->mobile) ? $medium_data[0]->mobile : '9999999999';
        $billing_email = isset($medium_data[0]->email) ? $medium_data[0]->email : $student_id.'@gmail.com';

        $get_map_bank_data = DB::table("fees_online_maping")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        $payment_acsept_type = $get_map_bank_data[0]->fees_type;
        $get_map_bank_detail = DB::table("fees_hdffc")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();

        $amount = 0;
        if ($payment_acsept_type == "fix") {
            $amount = number_format(floatval($_REQUEST["total"]), 0, '.', '');
        } else {
            $amount = number_format(floatval($_REQUEST["pay_amount"]), 0, '.', '');
        }

        $txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        $orderId = $_REQUEST["student_id"] . (mt_rand(10, 10000000000));
        
        $working_key = $get_map_bank_detail[0]->working_code; //Shared by CCAVENUES
        // $working_key = "94C918B28626FB1A085AAB522E32A402"; //Shared by CCAVENUES
        $access_code = $get_map_bank_detail[0]->access_code;
        // $access_code = "AVPL86GG59BJ25LPJB";
        
        // get break off 28-04-2025
        $school_amount = $mission_amount = 0;

        // get receipt book master for sub account id
        $receipt_book_master = DB::table('fees_receipt_book_master')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'standard_id'=>$_REQUEST['standard_id'],'status'=>1])->where('sort_order','!=',2)->first();

        $schoolSubAccountID  = '';
        $missionSubAccountID = 'SHRISWAMI1';
        if(!empty($receipt_book_master) && isset($receipt_book_master->id)){
            if($receipt_book_master->sort_order==1){
                $schoolSubAccountID = 'SHARIACADE1';
            }else{
                $schoolSubAccountID = 'SPRISCHOOL1';
            }
        }

        $studentBk = $this->breakOffAmounts($_REQUEST,$student_id,$sub_institute_id,$syear);
        
        if(isset($studentBk['typewise_total']['school'])){
            $school_amount = $studentBk['typewise_total']['school'];
        }

        if(isset($studentBk['typewise_total']['mission'])){
            $mission_amount = $studentBk['typewise_total']['mission'];
        }
        // end of 28-04-2025 
        $return_url = $this->site_name() . "fees/hdfc/online_fees_hdfcResponseHandler";
        $send_arr = array(
            "merchant_id" => $get_map_bank_detail[0]->merchant_id,
            "order_id" => $orderId,
            "currency" => "INR",
            "amount" => $amount,
            "redirect_url" => $return_url,
            "cancel_url" => $return_url,
            "language" => "en",
            "billing_name" => $billing_name,
            "billing_address" => $billing_address,
            "billing_city" => $billing_city,
            "billing_state" => $billing_state,
            "billing_zip" => $billing_zip,
            "billing_country" => $billing_country,
            "billing_tel" => $billing_tel,
            "billing_email" => $billing_email,
            "merchant_param1" => $_REQUEST["student_id"],
            "merchant_param2" => session()->get("syear"),
            "merchant_param3" => $txnid,                                                                        
            "merchant_param4" => session()->get("sub_institute_id"),
            "merchant_param5" => $fine,
            "tid" => strtotime(date('Y-m-d H:i:s')),
        );
        $merchant_data = "";
        foreach ($send_arr as $key => $value) {
            $merchant_data .= $key . '=' . $value . '&';
        }
        $merchant_data = rtrim($merchant_data, '&');
        $encrypted_data = $this->hdfc_encrypt($merchant_data, $working_key); // Method for encrypting the data.

        //Insert Raw data in fees_payment table
        $in_arr = array(
            "student_id" => $_REQUEST["student_id"],
            "syear" => session()->get("syear"),
            "amount" => $amount,
            "fine" => $fine,
            "discount" => $discount,
            "hdfc_order_id" => $orderId,
            "hdfc_transaction_id" => $txnid,
            "hdfc_plain_request" => $merchant_data,
            "hdfc_encrypt_request" => $encrypted_data,
            "hdfc_payment_status" => "PR",
            "hdfc_payment_date" => now(),
            "sub_institute_id" => session()->get("sub_institute_id"),
            "created_at" => now(),
            "updated_at" => now()
        );
         // 28-04-2025 to store split amount in database to easy fetch
         if($sub_institute_id==76){
            $in_arr['axis_order_id'] = 0; //split amount school
            $in_arr['icici_order_id'] = $school_amount; //split amount school
            $in_arr['icici_encrypt_request']=$mission_amount; // mission amount
            $in_arr['icici_plain_request']=$schoolSubAccountID; // school account
            $in_arr['icici_payment_status']=$missionSubAccountID; // mission account
        }
        // 28-04-2025 end 
        DB::table("fees_payment")
            ->insert($in_arr);
        
        $type = "web";
        $data = array(
            "merchant_data" => $encrypted_data,
            "ac_code" => $access_code
        );
        // echo '<pre>'; print_r(session()->all()); exit;
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/hdfc_RequestHandler", $data, "view");
        // echo '<pre>'; print_r($data); exit;
    }

    public function hdfc_response_handler(Request $request)
    {
        // echo '<pre>';
        // print_r($_REQUEST);
        // print_r(session()->all());
        // exit;
        $searchArr = array('"', "'");
        $replaceArr = array('\"', "\'");
        $get_map_bank_detail = DB::table("fees_hdffc")
            ->where(["sub_institute_id" => 76])
            ->get();//session()->get("sub_institute_id")
        //$working_key = "585414BED625F7D522B38C014074BE28"; //Shared by CCAVENUES 48 CMA
        $working_key = $get_map_bank_detail[0]->working_code; //Shared by CCAVENUES
        // $access_code = "AVPL86GG59BJ25LPJB";
        // $workingKey = WORKING_CODE; //Working Key should be provided here.
        $encResponse = $_POST["encResp"]; //This is the response sent by the CCAvenue Server
        $rcvdString = $this->hdfc_decrypt($encResponse, $working_key); //Crypto Decryption used as per the specified working key.
        $order_status = "";

        $decryptValues = explode('&', $rcvdString);
        $dataSize = sizeof($decryptValues);
        for ($i = 0; $i < $dataSize; $i++) {
            $information = explode('=', $decryptValues[$i]);
            if ($i == 0) {
                $order_id = $information[1];
            } else if ($i == 1) {
                $tracking_id = $information[1];
            } else if ($i == 2) {
                $bank_ref_no = $information[1];
            } else if ($i == 3) {
                $order_status = $information[1];
            } else if ($i == 4) {
                $failure_message = $information[1];
            } else if ($i == 5) {
                $payment_mode = $information[1];
            } else if ($i == 7) {
                $status_code = $information[1];
            } else if ($i == 8) {
                $status_message = str_replace($searchArr, $replaceArr, $information[1]);
            } else if ($i == 10) {
                $amount = $information[1];
            } else if ($i == 26) {
                $student_id = $information[1];
            } else if ($i == 27) {
                $syear = $information[1];
            } else if ($i == 28) {
                $txnid = $information[1];
            } else if ($i == 29) {
                $sub_institute_id = $information[1];
            } else if ($i == 30) {
                $fine = $information[1];
            } else if ($i == 35) {
                $mer_amount = $information[1];
            }
        }
        $res_arr = array(
            "order_id" => $order_id,
            "tracking_id" => $tracking_id,
            "bank_ref_no" => $bank_ref_no,
            "order_status" => $order_status,
            "failure_message" => $failure_message,
            "payment_mode" => $payment_mode,
            "status_code" => $status_code,
            "status_message" => $status_message,
            "amount" => $amount,
            "student_id" => $student_id,
            "syear" => $syear,
            "txnid" => $txnid,
            "sub_institute_id" => $sub_institute_id,
            "fine" => $fine,
            "mer_amount" => $mer_amount,
        );
        $res_josn = json_encode($res_arr);

        $get_all_data = DB::table("fees_payment")
            ->where(["hdfc_order_id" => $order_id])
            ->get();
        $payment_status = "PF";
        if ($order_status == "Success") {
            $payment_status = "PS";
        }
        $update_arr = array(
            "hdfc_payment_status" => $payment_status,
            "hdfc_bank_res" => $res_josn,
            "aggre_pay_order_id" => $tracking_id,
            "updated_at" => now()
        );
        $where_arr = array(
            "sub_institute_id" => $get_all_data[0]->sub_institute_id,
            "syear" => $get_all_data[0]->syear,
            "hdfc_order_id" => $order_id
        );

//START RAJESH 30-07-2024 = prevent second time success
        if($get_all_data[0]->hdfc_payment_status == 'PS'){
            $school_data = array();
            $school_data["website"] = $this->site_name();
            $type = "web";
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/search_student", $school_data, "view");
        }
//END RAJESH 30-07-2024

        // echo '<pre>'; print_r($where_arr); exit;
        DB::table("fees_payment")
            ->where($where_arr)
            ->update($update_arr);
        if ($order_status == "Success") {
            $data = $this->pay_fees($request, $get_all_data[0]->student_id, $get_all_data[0]->syear, $get_all_data[0]->sub_institute_id, $mer_amount, $order_id,"","","","In Processed");
            $type = $request->input('type');
            // echo "<pre>";print_r($data);exit;
            // return is_mobile($type, "fees/fees_collect/add", $res, "view");
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/receipt_view", $data, "view");
        } else {
            // echo '<pre>'; print_r(session()->all()); exit;
            $type = $request->input('type');
            $school_data = array();
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_error", $school_data, "view");
            // return \App\Helpers\is_mobile($type, "fees/online_fees_collect/axis_RequestHandler", $data, "view");
        }
    }

    public function getUTR(Request $request)
    {
        $working_code = '0E3D6A504AEB9E4D89A972E55771E378';
        $access_code = 'AVDY63MD40AA59YDAA';

        $school_account = ['SHARIACADE1', 'SPRISCHOOL1'];
        $mission_account = ['SHRISWAMI1'];

        // Step 1: Call payoutSummary API
        $payoutPayload = json_encode(['settlement_date' => date('d-m-Y')]);//date('03-05-2025')
        $encRequest = $this->hdfc_encrypt($payoutPayload, $working_code);

        $summaryResponse = $this->callCCAvenueAPI($encRequest, $access_code, $working_code, 'payoutSummary');

        if (!$summaryResponse || !isset($summaryResponse['Payout_Summary_Result']['payout_summary_list']['payout_summary_details'])) {
            return response()->json(['mesaage' => 'No payout summary'], 400);
        }

        $summary_list = $summaryResponse['Payout_Summary_Result']['payout_summary_list']['payout_summary_details'] ?? [];

        // Normalize: make it always an array of records
        $summaryDetails = isset($summary_list[0]) ? $summary_list : [$summary_list];
//echo "<pre>";print_r($summaryDetails);exit();

        // Step 2: Loop over payout_summary_details
        foreach ($summaryDetails as $summary) {
            $payId = $summary['pay_Id'];
            $subAccId = $summary['sub_acc_Id'];
            $utrNo = $summary['utr_no'];

            $payIdPayload = json_encode(['pay_id' => $payId]);
            $encPayIdRequest = $this->hdfc_encrypt($payIdPayload, $working_code);

            // Step 3: Call payIdDetails API
            $payIdResponse = $this->callCCAvenueAPI($encPayIdRequest, $access_code, $working_code,'payIdDetails');
//echo "<pre>";print_r($payIdResponse);exit();

            if (!$payIdResponse || !isset($payIdResponse['pay_id_details_Result']['pay_id_txn_details_list']['pay_id_txn_details'])) {
                continue;
            }
            
            // Normalize: make it always an array of records
            $txn_list = $payIdResponse['pay_id_details_Result']['pay_id_txn_details_list']['pay_id_txn_details'] ?? [];
            $txnDetails = isset($txn_list[0]) ? $txn_list : [$txn_list];

            //Step 3. Determine bank_name
            if (in_array($subAccId, $school_account)) {
                $bankName = 'SCHOOL';
            } elseif (in_array($subAccId, $mission_account)) {
                $bankName = 'MISSION';
            } else {
                continue; // skip if unknown account type
            }
//echo "<pre>";print_r($txnDetails);exit();

           // 4. Update DB records
            foreach ($txnDetails as $txn) {
                //echo "Rajesh".$txn['order_no'];exit();
                $chequeNo = $txn['order_no'];
                $variable = $subAccId.'-'.$payId;

//echo "Rajesh";echo "chequeNo-".$chequeNo;echo "remarks-".$remarks;die();

                $query = DB::table('fees_collect')
                    ->where([
                        ['sub_institute_id', '=', 76],
                        ['cheque_no', '=', $chequeNo],
                        ['bank_name', '=', $bankName],
                        ['payment_mode', '=', 'Online'],
                    ]);

//echo "SQL Query: " . $query->toSql() . "<br>";
//echo "Bindings: ";//print_r($query->getBindings());
//die();
                // Execute the update
                $query->update(['remarks' => $utrNo,'created_ip_address' => $variable]);
            }
        }
        return response()->json(['status' => 'success']);
    }

    // Helper function to send encrypted requests to CCAvenue
    public function callCCAvenueAPI($encRequest, $accessCode, $workingCode, $command)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.ccavenue.com/apis/servlet/DoWebTrans');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'enc_request' => $encRequest,
            'access_code' => $accessCode,
            'request_type' => 'JSON',
            'command' => $command,
            'version' => '1.2',
        ]));

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response !== false) {
            parse_str($response, $responseData);
            if (isset($responseData['status']) && $responseData['status'] == '0') {
                $encType = str_replace(["\r", "\n", ' '], '', $responseData['enc_response']);
                $decResponse = $this->hdfc_decrypt($encType, $workingCode);
                return json_decode($decResponse, true);
            }
        }

        return null;
    }

    public function createSplitPayout(Request $request)
    {
        $allResponse = [];
        $sub_institute_id = $request->sub_institute_id ?? 76;
    
        // Get HDFC data with trimmed access code
        $hdfcData = DB::table('fees_hdffc')
            ->where('sub_institute_id', $sub_institute_id)
            ->first();
        
        if (!$hdfcData) {
            return ['error' => 'HDFC configuration not found'];
        }
    
        $access_code = trim($hdfcData->access_code);
        $working_key = trim($hdfcData->working_code);

        // Verify the access code and working key are not empty
        if (empty($access_code) || empty($working_key)) {
            return ['error' => 'Access code or working key is empty'];
        }
    
        $getPaymentsPS = DB::table('fees_payment')
            ->where('sub_institute_id', $sub_institute_id)
            ->where(function($query) {
		        $query->whereIn('hdfc_payment_status', ['PS'])
		              ->orWhereIn('axis_payment_status', ['Shipped']);
		    })
            ->where('axis_order_id',0)
            ->whereBetween('created_at', [now()->subDays(3), now()->subMinutes(30)])
            ->get();
         //echo "<pre>";print_r($getPaymentsPS);exit;
        foreach ($getPaymentsPS as $payment) {
            try {

                $splitDataList = [];

                if ($payment->icici_order_id > 0) {
                    $splitDataList[] = [
                        'splitAmount' => $payment->icici_order_id,
                        'subAccId' => $payment->icici_plain_request
                    ];
                }

                if ($payment->icici_encrypt_request > 0) {
                    $splitDataList[] = [
                        'splitAmount' => $payment->icici_encrypt_request,
                        'subAccId' => $payment->icici_payment_status
                    ];
                }

                // Build the request data
                $requestData = [
                    'reference_no' => $payment->aggre_pay_order_id,
                    'split_tdr_charge_type' => 'M',
                    'merComm' => '0.0',
                    'split_data_list' => $splitDataList
                ];
    
                // Convert to merchant data string
                $merchant_data = json_encode($requestData);
                // echo "<pre>";print_r($merchant_data);exit;
                // Encrypt the data
                $encRequest = $this->hdfc_encrypt($merchant_data, $working_key);
    
                $final_data = "request_type=JSON&version=1.2&access_code=" . $access_code . "&command=createSplitPayout&response_type=JSON&enc_request=" . $encRequest;
    
                // Send request
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => 'https://api.ccavenue.com/apis/servlet/DoWebTrans',
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $final_data,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT => 30
                ]);
    
                $response = curl_exec($ch);
                $error = curl_error($ch);
                curl_close($ch);
    
                if ($error) {
                    $allResponse[] = ['error' => $error];
                    continue;
                }
    
                // Parse response
                parse_str($response, $parsedResponse);
    
                if (isset($parsedResponse['enc_response'])) {
                    $decryptedResponse = $this->hdfc_decrypt_ssmission($parsedResponse['enc_response'], $working_key);
                    $data = json_decode($decryptedResponse, true);
                    $status = $data['Create_Split_Payout_Result']['status'] ?? 1;
                    $reference_no = $data['Create_Split_Payout_Result']['reference_no'] ?? $payment->aggre_pay_order_id;
                    
                    if($status==0){
                        $allResponse[] = [
                            'status'=>'success',
                            'reference_no'=>$reference_no,
                            'decrypted_response'=>$decryptedResponse,
                        ];   
                        $update = DB::table('fees_payment')->where('id',$payment->id)->update(['axis_order_id'=>1]); // update table for fees receipt
                    }else{
                        $allResponse[] = [
                            'status'=>'failed',
                            'reference_no'=>$reference_no,
                            'decrypted_response'=>$decryptedResponse,
                        ];
                    }

                } else {
                    $allResponse[] = [
                        'status' => 'error',
                        'message' => 'Invalid API response',
                        'raw_response' => $response
                    ];
                }
            } catch (\Exception $e) {
                $allResponse[] = [
                    'status' => 'exception',
                    'message' => $e->getMessage()
                ];
            }
        }
        return $allResponse;
    }

    public function hdfc_decrypt_ssmission($encryptedText, $key)
    {
        $key = $this->hdfc_hextobin_ssmission(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07,
                                0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);

        $encryptedBinary = $this->hdfc_hextobin_ssmission($encryptedText); // Convert hex to binary
        $decrypted = openssl_decrypt($encryptedBinary, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);

        return rtrim($decrypted, "\x00..\x10"); // Optional: remove padding
    }

    public function hdfc_hextobin_ssmission($hexString)
    {
        // Sanitize and validate input
        $hexString = trim($hexString);

        if (!ctype_xdigit($hexString)) {
            return "-";
        }
    
        return pack("H*", $hexString);
    }
    public function hdfc_encrypt_ssmission($plainText, $key)
    {
        $key = $this->hdfc_hextobin_ssmission(md5($key)); // Convert key to binary
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07,
                                0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);

        $plainText = $this->hdfc_pkcs5_pad_ssmission($plainText, 16); // AES block size = 16
        $encrypted = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);

        return bin2hex($encrypted); // Convert binary to hex
    }

    public function hdfc_pkcs5_pad_ssmission($plainText, $blockSize)
    {
        $pad = $blockSize - (strlen($plainText) % $blockSize);
        return $plainText . str_repeat(chr($pad), $pad);
    }


    public function hdfc_request_handler_ssmission(Request $request)
    {
        // echo '<pre>';
        // print_r($_REQUEST);
        // print_r(session()->all());
        // exit;
        $searchArr = array(' ','"',"\'",',',"'");
        $replaceArr = array('','',"",' ',"");
        $student_id = $_REQUEST["student_id"];
        $fine = isset($_REQUEST["fees_data"]["fine"]) ? $_REQUEST["fees_data"]["fine"] : 0;
        $discount = isset($_REQUEST["totalDis"]) ? $_REQUEST["totalDis"] : 0;
        $medium_data = DB::select("SELECT a.*,e.grade_id,s.name AS standard, d.name AS division,CONCAT_WS('_',t.first_name,t.middle_name,t.last_name) AS student_name,t.address,t.state,t.city,t.pincode,t.mobile,t.enrollment_no, t.email,ifnull(b.title,0) AS batch 
            FROM tblstudent_enrollment e
            inner join academic_section a on e.grade_id = a.id
            inner join standard s on e.standard_id = s.id
            inner join division d on e.section_id = d.id
            INNER JOIN tblstudent t ON t.id=e.student_id
            LEFT JOIN batch b ON b.id=t.studentbatch
            INNER JOIN fees_online_maping fom ON fom.syear=e.syear AND fom.sub_institute_id=e.sub_institute_id
            WHERE e.student_id = '" . $student_id . "' ORDER BY e.syear DESC LIMIT 1");

        $billing_name = str_replace($searchArr, $replaceArr, $medium_data[0]->student_name);

        $billing_address = isset($medium_data[0]->address) ? $medium_data[0]->address : 'NA';
        $billing_address = str_replace($searchArr, $replaceArr, $billing_address);
        
        $billing_city = isset($medium_data[0]->city) ? $medium_data[0]->city : 'NA';
        $billing_state = isset($medium_data[0]->state) ? $medium_data[0]->state : 'GJ';
        $billing_zip = isset($medium_data[0]->pincode) ? $medium_data[0]->pincode : 'NA';
        $billing_country = 'India';
        $billing_tel = isset($medium_data[0]->mobile) ? $medium_data[0]->mobile : '9999999999';
        $billing_email = isset($medium_data[0]->email) ? $medium_data[0]->email : $student_id.'@gmail.com';

        $get_map_bank_data = DB::table("fees_online_maping")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        $payment_acsept_type = $get_map_bank_data[0]->fees_type;
        $get_map_bank_detail = DB::table("fees_hdffc")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
// echo '<pre>';
//         print_r($get_map_bank_detail);
//         exit;
        $amount = 0;
        if ($payment_acsept_type == "fix") {
            $amount = number_format(floatval($_REQUEST["total"]), 0, '.', '');
        } else {
            $amount = number_format(floatval($_REQUEST["pay_amount"]), 0, '.', '');
        }

        
            // get break off 29-03-2025
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
            $school_amount = $mission_amount = 0;

            // get receipt book master for sub account id
            $receipt_book_master = DB::table('fees_receipt_book_master')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'standard_id'=>$_REQUEST['standard_id'],'status'=>1])->where('sort_order','!=',2)->first();

            $schoolSubAccountID = '';
            $missionSubAccountID = 'SHRISWAMI1';
            if(!empty($receipt_book_master) && isset($receipt_book_master->id)){
                if($receipt_book_master->sort_order==1){
                    $schoolSubAccountID = 'SHARIACADE1';
                }else{
                    $schoolSubAccountID = 'SPRISCHOOL1';
                }
            }

            $studentBk = $this->breakOffAmounts($_REQUEST,$student_id,$sub_institute_id,$syear);
            
            if(isset($studentBk['typewise_total']['school'])){
                $school_amount = $studentBk['typewise_total']['school'];
            }

            if(isset($studentBk['typewise_total']['mission'])){
                $mission_amount = $studentBk['typewise_total']['mission'];
            }

            $spliArr = [];
            if($school_amount!=0 && $mission_amount!=0){
                $spliArr = array(
                        [
                            'splitAmount'=>$school_amount,
                            'subAccId'=>$schoolSubAccountID,
                        ],
                        [
                            'splitAmount'=>$mission_amount,
                            'subAccId'=>$missionSubAccountID,
                        ]
                    );
            }
            elseif($school_amount>0 && $mission_amount==0){
                $spliArr = array(
                        [
                            'splitAmount'=>$school_amount,
                            'subAccId'=>$schoolSubAccountID,
                        ]
                    );
            }
            elseif($school_amount==0 && $mission_amount>0){
                $spliArr = array(
                        [
                            'splitAmount'=>$mission_amount,
                            'subAccId'=>$missionSubAccountID
                        ]
                    );
            }
           
            $split_json = json_encode([
                "split_tdr_charge_type"=>'M',
                'merComm'=>'0.0',
                'split_data_list'=>$spliArr
            ]);
            // echo '<pre>';
            // print_r($split_json);
            // print_r($amount);

            // exit;
            // breakoffend 29-03-2025

        $txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        $orderId = $_REQUEST["student_id"] . (mt_rand(10, 10000000000));
        
        $working_key = $get_map_bank_detail[0]->working_code; //Shared by CCAVENUES
        // $working_key = "94C918B28626FB1A085AAB522E32A402"; //Shared by CCAVENUES
        $access_code = $get_map_bank_detail[0]->access_code;
        // $access_code = "AVPL86GG59BJ25LPJB";
        $return_url = $this->site_name()."fees/hdfc/online_fees_hdfcResponseHandler_ssmission";
        $send_arr = array(
            "merchant_id" => $get_map_bank_detail[0]->merchant_id,
            "order_id" => $orderId,
            "currency" => "INR",
            "amount" => $amount,
            "redirect_url" => $return_url,
            "cancel_url" => $return_url,
            "language" => "en",
            "billing_name" => $billing_name,
            "billing_address" => $billing_address,
            "billing_city" => $billing_city,
            "billing_state" => $billing_state,
            "billing_zip" => $billing_zip,
            "billing_country" => $billing_country,
            "billing_tel" => $billing_tel,
            "billing_email" => $billing_email,
            "merchant_param1" => $_REQUEST["student_id"],
            "merchant_param2" => session()->get("syear"),
            "merchant_param3" => $txnid,                                                                        
            "merchant_param4" => session()->get("sub_institute_id"),
            "merchant_param5" => $fine,
            "tid" => strtotime(date('Y-m-d H:i:s')),
        );
        // "split_data"=>$split_json,

        $merchant_data = "";
        foreach ($send_arr as $key => $value) {
            $merchant_data .= $key . '=' . $value . '&';
        }
        $merchant_data = rtrim($merchant_data, '&');
        $encrypted_data = $this->hdfc_encrypt($merchant_data, $working_key); // Method for encrypting the data.
        // echo '<pre>';
        //     print_r($send_arr);
        //     print_r($merchant_data);
        //     exit;
        //Insert Raw data in fees_payment table
        $in_arr = array(
            "student_id" => $_REQUEST["student_id"],
            "syear" => session()->get("syear"),
            "amount" => $amount,
            "fine" => $fine,
            "discount" => $discount,
            "hdfc_order_id" => $orderId,
            "hdfc_transaction_id" => $txnid,
            "hdfc_plain_request" => $merchant_data,
            "hdfc_encrypt_request" => $encrypted_data,
            "hdfc_payment_status" => "PR",
            "hdfc_payment_date" => now(),
            "sub_institute_id" => session()->get("sub_institute_id"),
            "created_at" => now(),
            "updated_at" => now()
        );
        DB::table("fees_payment")
            ->insert($in_arr);
        
        $type = "web";
        $data = array(
            "merchant_data" => $encrypted_data,
            "ac_code" => $access_code
        );
        // echo '<pre>'; print_r($); exit;
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/hdfc_RequestHandler_ssmission", $data, "view");
        // echo '<pre>'; print_r($data); exit;
    }

    public function hdfc_response_handler_ssmission(Request $request)
    {
        // echo '<pre>';
        // print_r($_REQUEST);
        // print_r(session()->all());
        // exit;
        $searchArr = array('"', "'");
        $replaceArr = array('\"', "\'");
        $get_map_bank_detail = DB::table("fees_hdffc")
            ->where(["sub_institute_id" => 76])
            ->get();//session()->get("sub_institute_id")
        //$working_key = "585414BED625F7D522B38C014074BE28"; //Shared by CCAVENUES 48 CMA
        $working_key = $get_map_bank_detail[0]->working_code; //Shared by CCAVENUES
        // $access_code = "AVPL86GG59BJ25LPJB";
        // $workingKey = WORKING_CODE; //Working Key should be provided here.
        $encResponse = $_POST["encResp"]; //This is the response sent by the CCAvenue Server
        $rcvdString = $this->hdfc_decrypt($encResponse, $working_key); //Crypto Decryption used as per the specified working key.
        $order_status = "";

        $decryptValues = explode('&', $rcvdString);
        $dataSize = sizeof($decryptValues);
        for ($i = 0; $i < $dataSize; $i++) {
            $information = explode('=', $decryptValues[$i]);
            if ($i == 0) {
                $order_id = $information[1];
            } else if ($i == 1) {
                $tracking_id = $information[1];
            } else if ($i == 2) {
                $bank_ref_no = $information[1];
            } else if ($i == 3) {
                $order_status = $information[1];
            } else if ($i == 4) {
                $failure_message = $information[1];
            } else if ($i == 5) {
                $payment_mode = $information[1];
            } else if ($i == 7) {
                $status_code = $information[1];
            } else if ($i == 8) {
                $status_message = str_replace($searchArr, $replaceArr, $information[1]);
            } else if ($i == 10) {
                $amount = $information[1];
            } else if ($i == 26) {
                $student_id = $information[1];
            } else if ($i == 27) {
                $syear = $information[1];
            } else if ($i == 28) {
                $txnid = $information[1];
            } else if ($i == 29) {
                $sub_institute_id = $information[1];
            } else if ($i == 30) {
                $fine = $information[1];
            } else if ($i == 35) {
                $mer_amount = $information[1];
            }
        }
        $res_arr = array(
            "order_id" => $order_id,
            "tracking_id" => $tracking_id,
            "bank_ref_no" => $bank_ref_no,
            "order_status" => $order_status,
            "failure_message" => $failure_message,
            "payment_mode" => $payment_mode,
            "status_code" => $status_code,
            "status_message" => $status_message,
            "amount" => $amount,
            "student_id" => $student_id,
            "syear" => $syear,
            "txnid" => $txnid,
            "sub_institute_id" => $sub_institute_id,
            "fine" => $fine,
            "mer_amount" => $mer_amount,
        );
        $res_josn = json_encode($res_arr);

        $get_all_data = DB::table("fees_payment")
            ->where(["hdfc_order_id" => $order_id])
            ->get();
        $payment_status = "PF";
        if ($order_status == "Success") {
            $payment_status = "PS";
        }
        $update_arr = array(
            "hdfc_payment_status" => $payment_status,
            "hdfc_bank_res" => $res_josn,
            "updated_at" => now()
        );
        $where_arr = array(
            "sub_institute_id" => $get_all_data[0]->sub_institute_id,
            "syear" => $get_all_data[0]->syear,
            "hdfc_order_id" => $order_id
        );

//START RAJESH 30-07-2024 = prevent second time success
        if($get_all_data[0]->hdfc_payment_status == 'PS'){
            $school_data = array();
            $school_data["website"] = $this->site_name();
            $type = "web";
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/search_student", $school_data, "view");
        }
//END RAJESH 30-07-2024

        // echo '<pre>'; print_r($where_arr); exit;
        DB::table("fees_payment")
            ->where($where_arr)
            ->update($update_arr);
            // (Request $request, $student_id, $syear, $sub_institute_id, $amount, $cheque_no = "",$fine="",$payment_mode = "",$discount="")
        if ($order_status == "Success") {
            $data = $this->pay_fees($request, $get_all_data[0]->student_id, $get_all_data[0]->syear, $get_all_data[0]->sub_institute_id, $mer_amount, $order_id,"","","","In Processed");
            $type = $request->input('type');
            // return is_mobile($type, "fees/fees_collect/add", $res, "view");
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/receipt_view", $data, "view");
        } else {
            // echo '<pre>'; print_r(session()->all()); exit;
            $type = $request->input('type');
            $school_data = array();
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_error", $school_data, "view");
            // return \App\Helpers\is_mobile($type, "fees/online_fees_collect/axis_RequestHandler", $data, "view");
        }
    }

    // end of ssmission split pyment 14-04-2025 
    public function hdfc_fetch_payment_status(Request $request)
    {
        $payment_data = DB::table('fees_payment AS fp')
            ->select('fp.id', 'fp.student_id', 'fi.merchant_id', 'fi.access_code', 'fi.working_code', 'fp.hdfc_order_id', 'tse.syear', 'fp.sub_institute_id', 'fp.amount', 'fp.fine', 'fp.discount', 'fp.hdfc_bank_res')
            ->join('tblstudent_enrollment AS tse', function ($join) {
                $join->on('tse.student_id', '=', 'fp.student_id')
                    ->on('tse.syear', '=', 'fp.syear')
                    ->on('tse.sub_institute_id', '=', 'fp.sub_institute_id');
            })
            ->join('academic_section AS a', 'a.id', '=', 'tse.grade_id')
            ->join('fees_hdffc AS fi', function ($join) {
                $join->on('fi.medium', '=', 'a.medium')
                    ->on('fi.sub_institute_id', '=', 'tse.sub_institute_id');
            })
            ->where(function ($query) {
                $query->where('fp.hdfc_payment_status', '!=', 'PS')
                    ->where(function ($query) {
                        $query->whereNotIn('fp.axis_payment_status', ['Shipped','Cancelled','Aborted','Invalid','Unsuccessful'])
                            ->orWhereNull('fp.axis_payment_status');
                    });
            })
            ->whereNotNull('fp.hdfc_order_id')
            ->whereBetween('fp.created_at', [now()->subDays(3), now()->subMinutes(30)])
            // ->whereIn('fp.id', [38326])
            ->groupBy('fp.id')
            ->get();

        if (!empty($payment_data)) {
            foreach ($payment_data as $data) {
                $id = $data->id;
                $merchant_id = $data->merchant_id;
                $access_code = $data->access_code;
                $working_code = $data->working_code;
                $order_no = $data->hdfc_order_id;
                $fine = $data->fine;
                $discount = $data->discount;
                $amt = $data->amount;
                // $send_arr = [[
                //      'order_no' => $order_no,
                //  ]];//
               $order_list = [
                       'order_no' => $order_no,
               ];
                
                // Convert the order list into a JSON format
                $request_payload = json_encode($order_list);
                
                // $request_payload = http_build_query($send_arr);
                $enc_request = $this->hdfc_encrypt($request_payload, $working_code);

                // Initialize cURL session
                $ch = curl_init();

                // Set the URL
                curl_setopt($ch, CURLOPT_URL, 'https://api.ccavenue.com/apis/servlet/DoWebTrans'); 
                // curl_setopt($ch, CURLOPT_URL, 'https://api.ccavenue.com/apis/servlet/DoWebTrans'); 

                // Set the cURL options
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'enc_request' => $enc_request,
                    'access_code' => $access_code,
                    'request_type' => 'JSON',
                    'command' => 'orderStatusTracker',
                    'order_no' => $order_no,
                    'version' => '1.2',
                ]));//'reference_no' => $order_no,

                // Execute the cURL request
                $response = curl_exec($ch);

                // Check for errors
                if (curl_errno($ch)) {
                    $error_msg = curl_error($ch);
                    Log::error("cURL error: " . $error_msg);
                    continue;
                }

                // Close the cURL session
                curl_close($ch);
// echo "-<pre>".$order_no."<br/>";
// print_r($response);
// exit();
                // Process the response
                if ($response !== false) {
                    parse_str($response, $response_data);

                    if (isset($response_data['status']) && $response_data['status'] == '0') {
                        // $enc_type = $response_data['enc_response'];
                        $enc_type = str_replace(array("\r", "\n", ' '), '', $response_data['enc_response']);
                        $dec_response = $this->hdfc_decrypt($enc_type, $working_code);
                        $dec_response = json_decode($dec_response, true);
                        //echo "<pre>";
                        //print_r($dec_response);
                        //echo $dec_response['order_status'];
                        //exit;
                        if(isset($dec_response['order_status'])){
                            $status = $dec_response['order_status'] ?? '';
                            $paydate = strtotime($dec_response['order_status_date_time']);
                            $trandate = date("Y-m-d", $paydate);
                            $PaymentMode = $dec_response['order_option_type'] ?? '-';
                            $reference_no = $dec_response['reference_no'] ?? '-';

                            $update_arr = [
                                "axis_encrypt_request" => "cron",
                                "axis_payment_status" => $status,
                                "axis_bank_res" => $trandate,
                                "axis_plain_request" => $PaymentMode,
                                "aggre_pay_order_id" => $reference_no,
                                "hdfc_bank_res" => json_encode($dec_response),
                                "updated_at" => now()
                            ];

                            DB::table("fees_payment")
                                ->where('id', $id)
                                ->update($update_arr);

                            $request->merge([
                                '_key' => csrf_token(),
                                'student_id' => $data->student_id,
                                'inserted_id' => $id,
                                'hdfc_payment_id' => $order_no,
                                'syear' => $data->syear,
                                'sub_institute_id' => $data->sub_institute_id
                            ]);

                            if ($status == 'Shipped') {
                                $check = DB::table('fees_collect')->whereRaw('cheque_no='.$order_no.' AND student_id='.$data->student_id.' AND syear='.$data->syear.' AND sub_institute_id='.$data->sub_institute_id)->get()->toArray();

                                if (count($check) == 0) {
                                    //echo "Done-".$data->student_id."<br/>";
                                    $this->pay_fees($request, $data->student_id, $data->syear, $data->sub_institute_id, $data->amount, $order_no,$fine,$PaymentMode,$discount,"In Processed");
                                }
                            }
                        }
                    } else {
                        echo "<br/>CCAvenue API ".$response;
                    }
                } else {
                    echo "<br/>CCAvenue API Request Failed";
                }
            }
        }else{
            echo "<br/>Failed to find payment data";
        }
        // exit;
    }

    public function icici(Request $request)
    {
        $data = $this->get_fees($request);
    //    dd($data);exit;
        $type = "web";
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_icici_fees", $data, "view");
    }

    public function icici_request_handler(Request $request)
    {
        // echo '<pre>'; print_r($_REQUEST); exit;
        $student_id = $_REQUEST["student_id"];
        $fine = isset($_REQUEST["fees_data"]["fine"]) ? $_REQUEST["fees_data"]["fine"] : 0;
        $discount = isset($_REQUEST["totalDis"]) ? $_REQUEST["totalDis"] : 0;
        //echo '<pre>'; print_r($fine); exit;
        $medium_data = DB::select("SELECT a.*,e.grade_id,s.name AS standard, d.name AS division,CONCAT_WS('_',t.first_name,t.middle_name,t.last_name) AS student_name, t.mobile, t.enrollment_no, t.email,ifnull(b.title,0) AS batch FROM tblstudent_enrollment e
            inner join academic_section a on e.grade_id = a.id
            inner join standard s on e.standard_id = s.id
            inner join division d on e.section_id = d.id
            INNER JOIN tblstudent t ON t.id=e.student_id
            LEFT JOIN batch b ON b.id=t.studentbatch
            INNER JOIN fees_online_maping fom ON fom.syear=e.syear AND fom.sub_institute_id=e.sub_institute_id
            WHERE e.student_id = '" . $student_id . "' ORDER BY e.syear DESC LIMIT 1");

        $get_map_bank_data = DB::table("fees_online_maping")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();

        $payment_acsept_type = null;
        if (!empty($get_map_bank_data[0])) {
            $payment_acsept_type = $get_map_bank_data[0]->fees_type;
        }

        $get_map_bank_detail = DB::table("fees_icici")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->when(!empty($medium_data[0]), function ($query) use ($medium_data) {
                return $query->where('medium', $medium_data[0]->medium);
            })
            ->get();
            
        $amount = 0;
        if ($payment_acsept_type == "fix") {
            $amount = number_format(floatval($_REQUEST["total"]), 0, '.', '');
        } else {
            $amount = number_format(floatval($_REQUEST["pay_amount"]), 0, '.', '');
        }

        $where_arr = array(
            "sub_institute_id" => session()->get("sub_institute_id"),
            "id" => $student_id
        );
        $get_mobile = DB::table("tblstudent")
            ->where($where_arr)
            ->get();
        $mobile_number = $get_mobile[0]->mobile;
        $orderId = $student_id . (mt_rand(100000, 10000000000));
        $MerId = $get_map_bank_detail[0]->merchant_id;
        $EncKey = $get_map_bank_detail[0]->enc_key;
        $SubMerId = (isset($get_map_bank_detail[0]->sub_merchant_id) ? $get_map_bank_detail[0]->sub_merchant_id : rand(10, 99));
        $PgRefNo = $orderId;
        $PayMode = 9;
        //$return_url = $this->site_name() . "fees/icici/online_fees_iciciResponseHandler";
        $return_url = $this->site_name() . "fees/online_fees_iciciresponsehandler";
        
        if (!empty($medium_data[0])) {
            if($medium_data[0]->sub_institute_id == 2440){
                $action_url = "https://eazypayuat.icicibank.com/EazyPG?merchantid=$MerId";//https://eazypayuat.icicibank.com
                $simple_action_url = "https://eazypayuat.icicibank.com/EazyPG?merchantid=$MerId";//https://eazypayuat.icicibank.com
            }else{
                $action_url = "https://eazypay.icicibank.com/EazyPG?merchantid=$MerId";//https://eazypayuat.icicibank.com
                $simple_action_url = "https://eazypay.icicibank.com/EazyPG?merchantid=$MerId";//https://eazypayuat.icicibank.com
            }

            if($medium_data[0]->sub_institute_id == 61){
                $M_FIELDS = $PgRefNo . '|' . $SubMerId . '|' . $amount. '|' . $medium_data[0]->student_name . '|'.$student_id.'@email.com|' . $medium_data[0]->mobile . '|' . $medium_data[0]->enrollment_no;
                $simple_optionalfields = $student_id;
            }else{

                $M_FIELDS = $PgRefNo . '|' . $SubMerId . '|' . $amount ;
                
                if($medium_data[0]->sub_institute_id == 257){
                    $simple_optionalfields = $medium_data[0]->student_name .'|'. $medium_data[0]->mobile .'|'. $student_id.'@gmail.com|' . $medium_data[0]->enrollment_no .'|'. $medium_data[0]->standard .'|'. $medium_data[0]->division .'|'. $medium_data[0]->batch. '|'. $student_id;
                }else{
                    $simple_optionalfields = $medium_data[0]->student_name .'|'. $medium_data[0]->mobile .'|'. $student_id.'@gmail.com|' . $medium_data[0]->enrollment_no .'|'. $medium_data[0]->standard .'|'. $medium_data[0]->division .'|'. $student_id;
                }
            }
        }else{
            $school_data = array();
            $school_data["website"] = $this->site_name();
            $type = "web";
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/search_student", $school_data, "view");
        }
        
        $mandatoryfields = $M_FIELDS;
        $simple_returnurl = $return_url;
        $simple_ReferenceNo = $orderId;
        $simple_submerchantid = $SubMerId;
        $simple_transactionamount = $amount;
        $simple_paymode = $PayMode;
        $simple_action_url .= "&mandatory fields=" . $mandatoryfields .
            "&optional fields=" . $simple_optionalfields .
            "&returnurl=" . $simple_returnurl .
            "&Reference No=" . $simple_ReferenceNo .
            "&submerchantid=" . $simple_submerchantid .
            "&transaction amount=" . $simple_transactionamount .
            "&paymode=" . $simple_paymode;
        $mandatoryfields = $this->icici_aes128Encrypt($M_FIELDS, $EncKey);
        $optionalfields = $this->icici_aes128Encrypt($simple_optionalfields, $EncKey);//$student_id
        $returnurl = $this->icici_aes128Encrypt($return_url, $EncKey);
        $ReferenceNo = $this->icici_aes128Encrypt($orderId, $EncKey);
        $submerchantid = $this->icici_aes128Encrypt($SubMerId, $EncKey);
        $transactionamount = $this->icici_aes128Encrypt($amount, $EncKey);
        $paymode = $this->icici_aes128Encrypt($PayMode, $EncKey);
        $action_url .= "&mandatory fields=" . $mandatoryfields .
            "&optional fields=" . $optionalfields .
            "&returnurl=" . $returnurl .
            "&Reference No=" . $ReferenceNo .
            "&submerchantid=" . $submerchantid .
            "&transaction amount=" . $transactionamount .
            "&paymode=" . $paymode;

        // Assuming $amount and $fine are supposed to be numeric values
        $amount = intval($amount); // Convert $amount to int
        $fine = intval($fine);
        $discount = intval($discount);  

        $in_arr = array(
            "student_id" => $_REQUEST["student_id"],
            "syear" => session()->get("syear"),
            "amount" => ($amount - $fine),
            "fine" => $fine,
            "discount" => $discount,
            "icici_order_id" => $orderId,
            "icici_plain_request" => $simple_action_url,
            "icici_encrypt_request" => $action_url,
            "icici_payment_status" => "PR",
            "icici_payment_date" => now(),
            "sub_institute_id" => session()->get("sub_institute_id"),
            "created_at" => now(),
            "updated_at" => now()
        );
        DB::table("fees_payment")
            ->insert($in_arr);
        $type = "web";
        $data = array(
            "send_data" => $action_url,
        );
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/icici_RequestHandler", $data, "view");
    }

        /**
     * Fetch payment status from RAZORPAY
     */
    public function icici_fetch_payment_status(Request $request) {

        // get payment data if payment status is not captured and is not null and order id is not null
        //$limit = 2; // Set the desired limit here
//DB::enableQueryLog();
//$ids = [61,244,246,247,248];

        $payment_data = DB::table('fees_payment AS fp')
            ->select('fp.id', 'fp.student_id', 'fi.merchant_id', 'fi.enc_key', 'fp.icici_order_id', 'tse.syear', 'fp.sub_institute_id', 'fp.amount', 'fp.fine','fp.discount','fp.icici_bank_res')
            ->join('tblstudent_enrollment AS tse', function ($join) {
                $join->on('tse.student_id', '=', 'fp.student_id')
                    ->on('tse.syear', '=', 'fp.syear')
                    ->on('tse.sub_institute_id', '=', 'fp.sub_institute_id');
            })
            ->join('academic_section AS a', 'a.id', '=', 'tse.grade_id')
            ->join('fees_icici AS fi', function ($join) {
                $join->on('fi.medium', '=', 'a.medium')
                    ->on('fi.sub_institute_id', '=', 'tse.sub_institute_id');
            })
            ->where(function ($query) {
                $query->where('fp.icici_payment_status', '!=', 'PS')
                      ->where(function ($query) {
                            $query->whereNotIn('fp.razorpay_payment_status', ['Success']) //'NotInitiated', 'FAILED', 
                                  ->orWhereNull('fp.razorpay_payment_status');
                      });
            })
            ->whereNotNull('fp.icici_order_id')
            //->where('fp.created_at', '>=', now()->subDays(3))
            ->whereBetween('fp.created_at', [now()->subDays(3), now()->subMinutes(30)])
//            ->whereIn('fp.sub_institute_id', [253])
//            ->whereIn('fp.student_id', [199428,199461,195283,195156,195227])
            ->groupBy('fp.id')
            // ->orderBy('fp.id','DESC')
            //->limit($limit)
            ->get();
//dd(DB::getQueryLog());
//return $payment_data;exit;

            $check = [];
        if ( !empty($payment_data) ) {

            foreach ( $payment_data as $data ) {
                $id = $data->id;
                $key_id = $data->merchant_id;
                $key_secret = $data->enc_key;
                $payment_id = $data->icici_order_id;
                $student_id = $data->student_id;
                $amount = $data->amount;
                $fine = $data->fine;
                $discount = $data->discount;
                $res = $data->icici_bank_res;
                // initial icici status api
                $url = "https://eazypay.icicibank.com/EazyPGVerify?merchantid=".$key_id."&pgreferenceno=".$payment_id."&dstatus=Y";
                $payment_status = Http::get($url);
                $payment_ex = explode('&', $payment_status);
                //echo "<pre>"; print_r($payment_ex);exit;

                $payment = [];
                foreach ($payment_ex as $item) {
                    // Skip empty or invalid items
                    if (empty($item) || strpos($item, '=') === false) {
                        continue; // Skip this iteration
                    }

                    $itemParts = explode('=', $item);
                    $key = $itemParts[0];
                    $value = $itemParts[1];
                    $payment[$key] = $value;
                }

                if ( !empty( $payment ) ) {
                    $status = $payment['status'];
                    $paydate = strtotime($payment['trandate']);
                    $PaymentMode = $payment['PaymentMode'];
                    $trandate = date("Y-m-d", $paydate);
                   
                    $json_response = $this->icici_payment_response_data_to_array($payment);

                    $update_arr = array(
                        "razorpay_payment_status" => $status,
                        "razorpay_bank_res" => $trandate,
                        "aggre_pay_bank_res" => "cron",
                        "icici_bank_res" => $payment_status,
                        "razorpay_dashboard_ps" => $PaymentMode,
                        "updated_at" => now()
                    );
/* echo "<pre>"; print_r($update_arr);
exit; */
                    DB::table("fees_payment")
                    ->where('id', $id)
                    ->update($update_arr);
                
                    $request->merge([
                        '_key' => csrf_token(),
                        'student_id' => $student_id,
                        'inserted_id' => $id,
                        'icici_payment_id' => $payment_id,
                        'syear' => $data->syear,
                        'sub_institute_id' => $data->sub_institute_id
                    ]);

//                    echo "<pre>"; print_r($request->all()); exit;
                    if($status == 'Success'){
                        $check = DB::table('fees_collect')->whereRaw('cheque_no='.$payment_id.' AND student_id='.$student_id.' AND syear='.$data->syear.' AND sub_institute_id='.$data->sub_institute_id)->get()->toArray();

                        if(count($check) == 0){
                            $schooldata = $this->pay_fees($request, $data->student_id, $data->syear, $data->sub_institute_id, $amount, $payment_id,$fine,$PaymentMode,$discount);
                        }
                    }
                }
            }
        }
    }

    public function icici_response_handler(Request $request)
    {
        $get_map_bank_detail = DB::table("fees_icici")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        $response = $_REQUEST;
        //echo "<pre>"; print_r($response); exit;
        $res_josn = json_encode($response);
        $get_all_data = DB::table("fees_payment")
            ->where(["icici_order_id" => $response["ReferenceNo"]])
            ->get();
        $payment_status = "PF";
        if ($response["Response_Code"] == "E000") {
            $payment_status = "PS";
        }
        $update_arr = array(
            "icici_payment_status" => $payment_status,
            "icici_bank_res" => $res_josn,
            "updated_at" => now()
        );
        $where_arr = array(
            "sub_institute_id" => $get_all_data[0]->sub_institute_id,
            "syear" => $get_all_data[0]->syear,
            "icici_order_id" => $response["ReferenceNo"]
        );

//START RAJESH 27-05-2023 = prevent second time success
        if($get_all_data[0]->icici_payment_status == 'PS'){
            $school_data = array();
            $school_data["website"] = $this->site_name();
            $type = "web";
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/search_student", $school_data, "view");
        }
//END RAJESH 27-05-2023

        // echo '<pre>'; print_r($where_arr); exit;
        DB::table("fees_payment")
            ->where($where_arr)
            ->update($update_arr);
        if ($payment_status == "PS") {
            $data = $this->pay_fees($request, $get_all_data[0]->student_id, $get_all_data[0]->syear, $get_all_data[0]->sub_institute_id, $get_all_data[0]->amount,$response["ReferenceNo"],$get_all_data[0]->fine,$response["Payment_Mode"]);
            $type = $request->input('type');
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/receipt_view", $data, "view");
        } else {
            $type = $request->input('type');
            $school_data = array();
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_error", $school_data, "view");
            // return \App\Helpers\is_mobile($type, "fees/online_fees_collect/axis_RequestHandler", $data, "view");
        }
    }

    public function icici_payment_response_data_to_array($response)
    {

        if (!empty($response)) {
            $data = [];
            foreach ($response as $key => $value) {
                $data[$key] = $value;
            }

            // echo "<pre>"; print_r(json_encode($data)); exit;
            return json_encode($data);
        }
    }


    public function axis(Request $request)
    {
        // echo '<pre>'; print_r($_REQUEST);
        $data = $this->get_fees($request);
        //  print_r($data); exit;
        $type = "web";
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_axis_fees", $data, "view");
        // echo '<pre>'; print_r($data); exit;
    }

    public function axis_request_handler(Request $request)
    {
        // echo '<pre>';
        // print_r($_REQUEST);
        // print_r(session()->all());
        // exit;
        $get_map_bank_detail = DB::table("fees_axis")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        //Change Encryption Key as provided by EasyPay Team
        // $encryption_key = 'axisbank12345678';
        $encryption_key = $get_map_bank_detail[0]->encryption_key;
        //Change Checksum Key as provided by EasyPay Team
        // $checksum_key = "axis";
        $checksum_key = $get_map_bank_detail[0]->checksum_key;
        $get_map_bank_data = DB::table("fees_online_maping")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        $payment_acsept_type = $get_map_bank_data[0]->fees_type;
        $amount = 0;
        if ($payment_acsept_type == "fix") {
            $amount = number_format(floatval($_REQUEST["total"]), 0, '.', '');
        } else {
            $amount = number_format(floatval($_REQUEST["pay_amount"]), 0, '.', '');
        }
        $student_id = $_REQUEST["student_id"];
        $where_arr = array(
            "sub_institute_id" => session()->get("sub_institute_id"),
            "id" => $student_id
        );
        $get_mobile = DB::table("tblstudent")
            ->where($where_arr)
            ->get();
        $mobile_number = $get_mobile[0]->mobile;
        $orderId = $student_id . (mt_rand(100000, 10000000000));
        // $cid = "5952";
        $cid = $get_map_bank_detail[0]->cid;
        $rid = "$orderId"; //rand(9999, 999999);
        $crn = rand(9999, 999999);
        $amt = $amount;
        $ver = "1.0";
        $typ = "Test";
        $cny = "INR";
        $rtu = $this->site_name() . "fees/axis/online_fees_axisResponseHandler";
        $ppi = "$mobile_number|$student_id|$amount";
        $re1 = "MN";
        $re2 = "";
        $re3 = "";
        $re4 = "";
        $re5 = "";
        $cks = hash("sha256", $cid . $rid . $crn . $amt . $checksum_key);
        $str = 'CID=' . $cid . '&RID=' . $rid . '&CRN=' . $crn . '&AMT=' . $amt . '&VER=' . $ver . '&TYP=' . $typ . '&CNY=' . $cny . '&RTU=' . $rtu . '&PPI=' . $ppi . '&RE1=&RE2=&RE3=&RE4=&RE5=&CKS=' . $cks;
        $aesJava = new AesForJava();
        $i = $aesJava->encrypt(urldecode($str), $encryption_key, 128);
        $txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        $in_arr = array(
            "student_id" => $_REQUEST["student_id"],
            "syear" => session()->get("syear"),
            "amount" => $amount,
            "axis_order_id" => $orderId,
            "axis_plain_request" => $str,
            "axis_encrypt_request" => $i,
            "axis_payment_status" => "PR",
            "axis_payment_date" => now(),
            "sub_institute_id" => session()->get("sub_institute_id"),
            "created_at" => now(),
            "updated_at" => now()
        );
        DB::table("fees_payment")
            ->insert($in_arr);
        $type = "web";
        $data = array(
            "send_data" => $i,
        );
        // echo '<pre>'; print_r(session()->all()); exit;
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/axis_RequestHandler", $data, "view");
        // echo '<pre>'; print_r($data); exit;
    }

    public function axis_response_handler(Request $request)
    {
        // echo ('<pre>');
        // print_r($_REQUEST);
        // print_r(session()->all());
        // exit;
        $get_map_bank_detail = DB::table("fees_axis")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        //Change Encryption Key as provided by EasyPay Team
        // $encryption_key = 'axisbank12345678';
        $encryption_key = $get_map_bank_detail[0]->encryption_key;
        //Change Checksum Key as provided by EasyPay Team
        // $checksum_key = "axis";
        $checksum_key = $get_map_bank_detail[0]->checksum_key;
        define('ENCRYPTION_KEY', $encryption_key);
        preg_match_all('/(\w+)=([^&]+)/', $_SERVER["QUERY_STRING"], $pairs);
        $_GET = array_combine($pairs[1], $pairs[2]);
        // include_once 'AesForJava.php';
        $aes = new AesForJava();
        $qStr = $aes->decrypt(urldecode($_GET['i']), ENCRYPTION_KEY, 128);
        $temp_arr = explode("&", $qStr);
        $res_arr = array();
        foreach ($temp_arr as $id => $val) {
            $t_arr = explode("=", $val);
            $res_arr[$t_arr[0]] = $t_arr[1];
        }
        // echo '<pre>'; print_r($res_arr); exit;
        //temp changing responce
        //        $res_arr["RID"] = "168499530737327";
        //        $res_arr["AMT"] = "33420";
        //        $res_arr["RMK"] = "success";
        $res_josn = json_encode($res_arr);
        // echo '<pre>';
        // print_r($res_arr);
        // exit;
        // echo '<pre>';
        // print_r($res_josn);
        // exit;
        $get_all_data = DB::table("fees_payment")
            ->where(["axis_order_id" => $res_arr["RID"]])
            ->get();
        $payment_status = "PF";
        if ($res_arr["RMK"] == "success") {
            $payment_status = "PS";
        }
        $update_arr = array(
            "axis_payment_status" => $payment_status,
            "axis_bank_res" => $res_josn,
            "updated_at" => now()
        );
        $where_arr = array(
            "sub_institute_id" => $get_all_data[0]->sub_institute_id,
            "syear" => $get_all_data[0]->syear,
            "axis_order_id" => $res_arr["RID"]
        );
        // echo '<pre>'; print_r($where_arr); exit;
        DB::table("fees_payment")
            ->where($where_arr)
            ->update($update_arr);
        if ($payment_status == "PS") {
            $data = $this->pay_fees($request, $get_all_data[0]->student_id, $get_all_data[0]->syear, $get_all_data[0]->sub_institute_id, $res_arr["AMT"], $res_arr["RID"]);
            $type = $request->input('type');
            // return is_mobile($type, "fees/fees_collect/add", $res, "view");
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/receipt_view", $data, "view");
        } else {
            // echo '<pre>'; print_r(session()->all()); exit;
            $type = $request->input('type');
            $school_data = array();
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_error", $school_data, "view");
            // return \App\Helpers\is_mobile($type, "fees/online_fees_collect/axis_RequestHandler", $data, "view");
        }
        // echo '<pre>'; print_r($data); exit;
    }

    public function aggre_pay(Request $request)
    {
        $data = $this->get_fees($request);
        $type = "web";
        // echo "<pre>";print_r($data);exit;
        if(isset($data['stu_data']) && !empty($data['stu_data'])){
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_aggre_pay_fees", $data, "view");
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to find Breakoff";
            return \App\Helpers\is_mobile($type, "online_fees_collect.index", $res);
        }
        // echo '<pre>'; print_r($data); exit;
    }

    public function aggre_pay_request_handler(Request $request)
    {
        // echo '<pre>';
        // print_r($_REQUEST);
        // print_r(session()->all());
        // exit;
        $get_map_bank_detail = DB::table("fees_aggre_pay")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        $get_map_bank_data = DB::table("fees_online_maping")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        $payment_acsept_type = $get_map_bank_data[0]->fees_type;
        $amount = 0;
        if ($payment_acsept_type == "fix") {
            $amount = number_format(floatval($_REQUEST["total"]), 0, '.', '');
        } else {
            $amount = number_format(floatval($_REQUEST["pay_amount"]), 0, '.', '');
        }
        $student_id = $_REQUEST["student_id"];
        $where_arr = array(
            "sub_institute_id" => session()->get("sub_institute_id"),
            "id" => $student_id
        );
        $get_mobile = DB::table("tblstudent")
            ->where($where_arr)
            ->get();
        $mobile_number = $get_mobile[0]->mobile;
        $student_name = $get_mobile[0]->first_name . ' ' . $get_mobile[0]->middle_name . ' ' . $get_mobile[0]->last_name;
        $orderId = $student_id . (mt_rand(100000, 10000000000));
        // dd($get_mobile);
        $_POST['return_url'] = $this->site_name() . "fees/aggre_pay/online_fees_aggre_payResponseHandler";
        $_POST['mode'] = "LIVE";
        $_POST['order_id'] = $orderId;
        $_POST['amount'] = $amount;
        $_POST['currency'] = "INR";
        $_POST['description'] = "Online fees payment by Aggre Pay";
        $_POST['name'] = $student_name;
        $_POST['email'] = $get_mobile[0]->email;
        $_POST['phone'] = $mobile_number;
        $_POST['address_line_1'] = $get_mobile[0]->address;
        $_POST['address_line_2'] = '';
        $_POST['city'] = $get_mobile[0]->city;
        $_POST['state'] = $get_mobile[0]->state;
        $_POST['zip_code'] = $get_mobile[0]->pincode;
        $_POST['country'] = "IND";
        $_POST['udf1'] = "";
        $_POST['udf2'] = "";
        $_POST['udf3'] = "";
        $_POST['udf4'] = "";
        $_POST['udf5'] = "";
        $salt = $get_map_bank_detail[0]->salt_key; //Pass your SALT here
        $_POST['api_key'] = $get_map_bank_detail[0]->api_key; //Pass your API KEY here
        $hash = $this->aggre_pay_for_request_hashCalculate($salt, $_POST);
        $str = 'API_KEY=' . $_POST['api_key'] . '&RETURN_URL=' . $_POST['return_url'] . '&MODE=' . $_POST['mode'] . '&ORDER_ID=' . $_POST['order_id'] . '&AMOUNT=' . $_POST['amount'] . '&CURRENCY=' . $_POST['currency'] . '&DESCRIPTION=' . $_POST['description'] . '&NAME=' . $_POST['name'] . '&EMAIL=' . $_POST['email'] . '&PHONE=' . $_POST['phone'] . '&ADDRESS_LINE_1=' . $_POST['address_line_1'] . '&CITY=' . $_POST['city'] . '&STATE=' . $_POST['state'] . '&ZIP_CODE=' . $_POST['zip_code'] . '&COUNTRY=' . $_POST['country'] . '&udf1=&udf2=&udf3=&udf4=&udf5=';
        $in_arr = array(
            "student_id" => $_REQUEST["student_id"],
            "syear" => session()->get("syear"),
            "amount" => $amount,
            "aggre_pay_order_id" => $_POST['order_id'],
            "aggre_pay_plain_request" => $str,
            "axis_payment_status" => "PR",
            "aggre_pay_payment_date" => now(),
            "sub_institute_id" => session()->get("sub_institute_id"),
            "created_at" => now(),
            "updated_at" => now()
        );
        DB::table("fees_payment")
            ->insert($in_arr);
        $type = "web";
        $data = array(
            "hash" => $hash,
            "api_key" => $_POST['api_key'],
            "return_url" => $_POST['return_url'],
            "mode" => $_POST['mode'],
            "order_id" => $_POST['order_id'],
            "amount" => $_POST['amount'],
            "currency" => $_POST['currency'],
            "description" => $_POST['description'],
            "name" => $_POST['name'],
            "email" => $_POST['email'],
            "phone" => $_POST['phone'],
            "address_line_1" => $_POST['address_line_1'],
            "address_line_2" => $_POST['address_line_2'],
            "city" => $_POST['city'],
            "state" => $_POST['state'],
            "zip_code" => $_POST['zip_code'],
            "country" => $_POST['country'],
            "udf1" => $_POST['udf1'],
            "udf2" => $_POST['udf2'],
            "udf3" => $_POST['udf3'],
            "udf4" => $_POST['udf4'],
            "udf5" => $_POST['udf5']
        );
        // echo '<pre>'; print_r(session()->all()); exit;
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/aggre_pay_RequestHandler", $data, "view");
    }

    public function aggre_pay_response_handler(Request $request)
    {
        // echo ('<pre>');
        // print_r($_POST);
        // print_r(session()->all());
        // exit;
        $get_map_bank_detail = DB::table("fees_aggre_pay")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        if (isset($_POST)) {
            $response = $_POST;
            /* It is very important to calculate the hash using the returned value and compare it against the hash that was sent while payment request, to make sure the response is legitimate */
            $salt = $get_map_bank_detail[0]->salt_key; /* put your salt provided by aggrepay here */
            if (isset($salt) && !empty($salt)) {
                $response['calculated_hash'] = $this->aggre_pay_for_response_hashCalculate($salt, $response);
                $response['valid_hash'] = ($response['hash'] == $response['calculated_hash']) ? 'Yes' : 'No';
            } else {
                $response['valid_hash'] = 'Set your salt in return_page.php to do a hash check on receiving response from Aggrepay';
            }
        }
        // echo '<pre>';
        // print_r($response);
        // exit;
        $res_josn = json_encode($response);
        // echo '<pre>';
        // print_r($res_josn);
        // exit;
        $get_all_data = DB::table("fees_payment")
            ->where(["aggre_pay_order_id" => $response["order_id"]])
            ->get();
        $payment_status = "PF";
        if ($response["response_message"] == "Transaction successful") {
            $payment_status = "PS";
        }
        $update_arr = array(
            "aggre_pay_payment_status" => $payment_status,
            "aggre_pay_bank_res" => $res_josn,
            "updated_at" => now()
        );
        $where_arr = array(
            "sub_institute_id" => $get_all_data[0]->sub_institute_id,
            "syear" => $get_all_data[0]->syear,
            "aggre_pay_order_id" => $response["order_id"]
        );
        // echo '<pre>'; print_r($where_arr); exit;
        DB::table("fees_payment")
            ->where($where_arr)
            ->update($update_arr);
        if ($payment_status == "PS") {
            $data = $this->pay_fees($request, $get_all_data[0]->student_id, $get_all_data[0]->syear, $get_all_data[0]->sub_institute_id, $response["amount"], $response["order_id"]);
            $type = $request->input('type');
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/receipt_view", $data, "view");
        } else {
            // echo '<pre>'; print_r(session()->all()); exit;
            $type = $request->input('type');
            $school_data = array();
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_error", $school_data, "view");
            // return \App\Helpers\is_mobile($type, "fees/online_fees_collect/axis_RequestHandler", $data, "view");
        }
        // echo '<pre>'; print_r($data); exit;
    }

    public function pay_fees(Request $request, $student_id, $syear, $sub_institute_id, $amount, $cheque_no = "",$fine="",$payment_mode = "",$discount="",$inProcess="")// added inProcess on 29-04-2025 for ssmission 
    {
        //echo "<pre>"; print_r($request->all()); exit;
        $get_map_bank_data = DB::table("fees_online_maping")
            ->where(["sub_institute_id" => $sub_institute_id])
            ->get();
        $payment_acsept_type = $get_map_bank_data[0]->fees_type;
        $controller = new fees_collect_controller;
        // $data = $controller->getOnlinebk($request,$all_student[0]->,2020,16849);
        $fees_bk_data = $controller->getOnlinebk($request, $sub_institute_id, $syear, $student_id);
        //echo '<pre>';
        //print_r($fees_bk_data);
        //exit;
        $fees_config = DB::table('fees_config_master')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->first();

        $ajx_controller = new AJAXController;
        if ($payment_acsept_type == "fix") {
            // creating month arr
            $temp_amount = 0;
            $pay_month = array();
            if (isset($fees_bk_data["total_fees"])) {
                foreach ($fees_bk_data["total_fees"] as $id => $arr) {
                    if ($arr["month"] == "Total") {
                        continue;
                    }
                    $temp_amount = $temp_amount + $arr["remain"];
                    $pay_month[$arr["month_id"]] = $arr["month_id"];
                    if ($amount == $temp_amount) {
                        break;
                    }
                }
            }
            //echo '<pre>'; print_r($pay_month); exit;
            // creating final fee arr
            $arr["student_id"] = $student_id;
            $arr["months"] = $pay_month;
            $fees_month = $ajx_controller->getOnlineFeesMonth($arr);
            $final_fees_arr = array();
            if (!empty($fees_month)) {

                $total_fees = $fees_month["Total"];
                unset($fees_month["Total"]);
                if (!empty($fees_month)) {
                    foreach ($fees_month as $id => $val) {
                        $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] = $val;
                    }
                }
                $final_fees_arr["fine"] = "";
            }
            //echo '<pre>'; print_r($final_fees_arr); exit;
            //creating discount data and fine arr
            $discount_data_arr = array();
            $fine_data_arr = array();
            foreach ($final_fees_arr as $id => $val) {
                $discount_data_arr[$id] = 0;
                $fine_data_arr[$id] = 0;
            }
            // echo '<pre>';
            // print_r($fees_bk_data);
            // print_r($fine_data_arr);
            // exit;
            // $_REQUEST['uniqueid']
            // $_REQUEST['enrollment']
            // $_REQUEST['mobile']
            // creating final send arr
            $send_sms ='';
            if(isset($fees_config->send_sms) && $fees_config->send_sms == 1){
               $send_sms = "on";
           }
            $send_arr = array(
                "grade_id" => $fees_bk_data["stu_data"]["grade_id"],
                "standard_id" => $fees_bk_data["stu_data"]["std_id"],
                "div_id" => $fees_bk_data["stu_data"]["div_id"],
                "student_id" => $fees_bk_data["stu_data"]["student_id"],
                "std_div" => $fees_bk_data["stu_data"]["stddiv"],
                "full_name" => $fees_bk_data["stu_data"]["name"],
                "enrollment" => $fees_bk_data["stu_data"]["enrollment"],
                "mobile" => $fees_bk_data["stu_data"]["mobile"],
                "uniqueid" => $fees_bk_data["stu_data"]["uniqueid"],
                "roll_no" => $fees_bk_data["stu_data"]["roll_no"],
                "father_name" => $fees_bk_data["stu_data"]["father_name"],
                "mother_name" => $fees_bk_data["stu_data"]["mother_name"],
                "student_batch" => $fees_bk_data["stu_data"]["student_batch"],
                "months" => $pay_month,
                "fees_data" => $final_fees_arr,
                "discount_data" => $discount_data_arr,
                "fine_data" => $fine_data_arr,
                "total" => $total_fees,
                "totalDis" => $discount,
                "totalFin" => 0,
                "PAYMENT_MODE" => "Online",
                "receiptdate" => date("Y-m-d"),
                "cheque_date" => "",
                "cheque_no" => $cheque_no,
                "bank_name" => $payment_mode,
                "bank_branch" => "",
                "send_sms"=>$send_sms,
                "inprocess"=>$inProcess, // added on 29-04-2025 for ssmission 
                "submit" => "Save",
            );
            // echo '<pre>';
            // print_r($send_arr);
            // print_r($_REQUEST);
            // die;
            $_REQUEST = $send_arr;
            $paid_fees = $controller->pay_fees($request);
            return $paid_fees;
            // print_r($paid_fees);
            // exit;
        } else {
            //$amount = 11141;
            //creating month arr
            $temp_amount = 0;
            $pay_month = array();
            // echo '<pre>'; print_r($fees_bk_data); exit;
            foreach ($fees_bk_data["total_fees"] as $id => $arr) {
                if ($arr["month"] == "Total") {
                    continue;
                }
                $temp_amount = $temp_amount + $arr["remain"];
                $pay_month[$arr["month_id"]] = $arr["month_id"];
                if ($amount <= $temp_amount) {
                    break;
                }
            }
            // echo '<pre>'; print_r($pay_month); exit;
            // creating final fee arr
            $arr["student_id"] = $student_id;
            $arr["months"] = $pay_month;
            $final_fees_arr = array();
            $temp_paid_amount = $amount;
            foreach ($pay_month as $id => $month) {
                $month_arr = array($month);
                $arr["months"] = $month_arr;
                $fees_data = $ajx_controller->getOnlineFeesMonth($arr);
                unset($fees_data["Total"]);
                foreach ($fees_data as $id => $val) {
                    if ($temp_paid_amount > 0) {
                        if ($temp_paid_amount >= $val) {
                            if (isset($final_fees_arr[$fees_bk_data["final_fee_name"][$id]])) {
                                $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] = $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] + $val;
                            } else {
                                $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] = $val;
                            }
                        } else {
                            if (isset($final_fees_arr[$fees_bk_data["final_fee_name"][$id]])) {
                                $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] = $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] + $temp_paid_amount;
                            } else {
                                $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] = $temp_paid_amount;
                            }
                        }
                        $temp_paid_amount = $temp_paid_amount - $val;
                    }
                }
            }
            $total_fees = 0;
            foreach ($final_fees_arr as $id => $val) {
                $total_fees = $total_fees + $val;
            }
            $final_fees_arr["fine"] = $fine;
            /* echo '<pre>';
            print_r($final_fees_arr);
            print_r($total_fees); 
            exit; */
            //creating discount data and fine arr
            $discount_data_arr = array();
            $fine_data_arr = array();
            foreach ($final_fees_arr as $id => $val) {
                $discount_data_arr[$id] = 0;
                $fine_data_arr[$id] = 0;
            }
             /* echo '<pre>';
             print_r($discount_data_arr);
             print_r($fine_data_arr);
             exit; */
             $send_sms ='';
             if(isset($fees_config->send_sms) && $fees_config->send_sms == 1){
                $send_sms = "on";
            }
            // creating final send arr
            $send_arr = array(
                "grade_id" => $fees_bk_data["stu_data"]["grade_id"],
                "standard_id" => $fees_bk_data["stu_data"]["std_id"],
                "div_id" => $fees_bk_data["stu_data"]["div_id"],
                "student_id" => $fees_bk_data["stu_data"]["student_id"],
                "std_div" => $fees_bk_data["stu_data"]["stddiv"],
                "full_name" => $fees_bk_data["stu_data"]["name"],
                "enrollment" => $fees_bk_data["stu_data"]["enrollment"],
                "mobile" => $fees_bk_data["stu_data"]["mobile"],
                "uniqueid" => $fees_bk_data["stu_data"]["uniqueid"],
                "roll_no" => $fees_bk_data["stu_data"]["roll_no"],
                "father_name" => $fees_bk_data["stu_data"]["father_name"],
                "mother_name" => $fees_bk_data["stu_data"]["mother_name"],
                "student_batch" => $fees_bk_data["stu_data"]["student_batch"],
                "months" => $pay_month,
                "fees_data" => $final_fees_arr,
                "discount_data" => $discount_data_arr,
                "fine_data" => $fine_data_arr,
                "total" => $total_fees,
                "fine" => $final_fees_arr["fine"],
                "totalDis" => $discount,
                "totalFin" => 0,
                "PAYMENT_MODE" => "Online",
                "receiptdate" => date("Y-m-d"),
                "cheque_date" => "",
                "cheque_no" => $cheque_no,
                "bank_name" => $payment_mode,
                "bank_branch" => "",
                "send_sms"=>$send_sms,
                "inprocess"=>$inProcess,// added on 29-04-2025 for ssmission 
                "submit" => "Save",
            );
            /* echo '<pre>';
             print_r($send_arr);
             exit; */
            $_REQUEST = $send_arr;
            $paid_fees = $controller->pay_fees($request);
            return $paid_fees;
        }
    }

    // gpay fees
    public function OnlineReceipt(Request $request)
    {
        $student_id = $request->student_id;
        $syear = $request->syear;
        $sub_institute_id= $request->sub_institute_id;
        $amount= $request->amount;
        $transactionid= $request->transactionid;
        $fine= $request->fine;  
        $bank_name= $request->bank_name ?? 'GPAY';
        
        // validate requried fields 
        $validate = Validator::make($request->all(), [
            'student_id' => 'required',
            'syear' => 'required',
            'sub_institute_id' => 'required',
            'amount' => 'required',
            'token' => 'required',
        ]);

        if($validate->fails()){
            $errorMessages = $validate->errors()->all();
            return response()->json([
                'status' => 0,
                'message' => implode(' ', $errorMessages)
            ]); 
        }

        // match token with student id 
        $token = $request->token;
        $token_json = base64_decode($token);
        $token_data = json_decode($token_json, true);
        $token_student_id = Crypt::decryptString($token_data['student_id']);
        // $token_student_name = Crypt::decryptString($token['student_name']);
        if(($token_student_id!=$student_id) || empty($token)){
            $response = [
                'status'=>0,
                'message' => 'Token failed',
            ];
            return response()->json($response);
        }else{
            $controller = new fees_collect_controller;

            $fees_bk_data = $controller->getOnlinebk($request, $sub_institute_id, $syear, $student_id);
            
            $ajx_controller = new AJAXController;
    
            $temp_amount = 0;
            $pay_month = array();
    
            foreach ($fees_bk_data["total_fees"] as $id => $arr) {
                if ($arr["month"] == "Total") {
                    continue;
                }
                $temp_amount = $temp_amount + $arr["remain"];
                $pay_month[$arr["month_id"]] = $arr["month_id"];
                if ($amount <= $temp_amount) {
                    break;
                }
            }
    
            $arr["student_id"] = $student_id;
            $arr["months"] = $pay_month;
            $final_fees_arr = array();
            $temp_paid_amount = $amount;
            foreach ($pay_month as $id => $month) {
                $month_arr = array($month);
                $arr["months"] = $month_arr;
                $fees_data = $ajx_controller->getOnlineFeesMonth($arr);
                unset($fees_data["Total"]);
                foreach ($fees_data as $id => $val) {
                    if ($temp_paid_amount > 0) {
                        if ($temp_paid_amount >= $val) {
                            if (isset($final_fees_arr[$fees_bk_data["final_fee_name"][$id]])) {
                                $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] = $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] + $val;
                            } else {
                                $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] = $val;
                            }
                        } else {
                            if (isset($final_fees_arr[$fees_bk_data["final_fee_name"][$id]])) {
                                $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] = $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] + $temp_paid_amount;
                            } else {
                                $final_fees_arr[$fees_bk_data["final_fee_name"][$id]] = $temp_paid_amount;
                            }
                        }
                        $temp_paid_amount = $temp_paid_amount - $val;
                    }
                }
            }
    
            $total_fees = 0;
            foreach ($final_fees_arr as $id => $val) {
                $total_fees = $total_fees + $val;
            }
            $final_fees_arr["fine"] = $fine;
            
            $discount_data_arr = array();
            $fine_data_arr = array();

            $fees_config = DB::table('fees_config_master')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->first();
            foreach ($final_fees_arr as $id => $val) {
                $discount_data_arr[$id] = 0;
                $fine_data_arr[$id] = 0;
            }
            
            $send_sms ='';
            if(isset($fees_config->send_sms) && $fees_config->send_sms == 1){
                $send_sms = "on";
            }
            // creating final send arr
            $send_arr = array(
                "grade_id" => $fees_bk_data["stu_data"]["grade_id"],
                "standard_id" => $fees_bk_data["stu_data"]["std_id"],
                "div_id" => $fees_bk_data["stu_data"]["div_id"],
                "student_id" => $fees_bk_data["stu_data"]["student_id"],
                "std_div" => $fees_bk_data["stu_data"]["stddiv"],
                "full_name" => $fees_bk_data["stu_data"]["name"],
                "enrollment" => $fees_bk_data["stu_data"]["enrollment"],
                "mobile" => $fees_bk_data["stu_data"]["mobile"],
                "uniqueid" => $fees_bk_data["stu_data"]["uniqueid"],
                "roll_no" => $fees_bk_data["stu_data"]["roll_no"],
                "father_name" => $fees_bk_data["stu_data"]["father_name"],
                "mother_name" => $fees_bk_data["stu_data"]["mother_name"],
                "student_batch" => $fees_bk_data["stu_data"]["student_batch"],
                "months" => $pay_month,
                "fees_data" => $final_fees_arr,
                "discount_data" => $discount_data_arr,
                "fine_data" => $fine_data_arr,
                "total" => $total_fees,
                "fine" => $final_fees_arr["fine"],
                "totalDis" => 0,
                "totalFin" => 0,
                "PAYMENT_MODE" => 'Online',
                "receiptdate" => date("Y-m-d"),
                "cheque_date" => "",
                "cheque_no" => $transactionid,
                "bank_name" => $bank_name,
                "bank_branch" => "",
                "send_sms"=>$send_sms,
                "submit" => "Save",
            );
            
            $_REQUEST = $send_arr;
            $paid_fees = $controller->pay_fees($request);

            if(isset($paid_fees['receipt_id_html']) && $paid_fees['receipt_id_html']!=''){
                $response = [
                    'status'=>1,
                    'message'=>'success',
                    'receipt_no'=>$paid_fees['receipt_id_html'],
                    'receipt_html'=>$paid_fees['data'],
                ];
            }else{
                $response = [
                    'status'=>0,
                    'message'=>'Failed to get payment data',
                ];
            }
           
            return response()->json($response);
        }
        
    }

    public function aggre_pay_for_request_hashCalculate($salt, $input)
    {
        /* Columns used for hash calculation, Donot add or remove values from $hash_columns array */
        $hash_columns = ['address_line_1', 'address_line_2', 'amount', 'api_key', 'city', 'country', 'currency', 'description', 'email', 'mode', 'name', 'order_id', 'phone', 'return_url', 'state', 'udf1', 'udf2', 'udf3', 'udf4', 'udf5', 'zip_code',];
        /*Sort the array before hashing*/
        sort($hash_columns);
        /*Create a | (pipe) separated string of all the $input values which are available in $hash_columns*/
        $hash_data = $salt;
        foreach ($hash_columns as $column) {
            if (isset($input[$column])) {
                if (strlen($input[$column]) > 0) {
                    $hash_data .= '|' . trim($input[$column]);
                }
            }
        }
        // echo '<pre>';
        // print_r($hash_data);
        // die;
        $hash = strtoupper(hash("sha512", $hash_data));
        return $hash;
    }

    public function aggre_pay_for_response_hashCalculate($salt, $input)
    {
        /* Remove hash key if it is present */
        unset($input['hash']);
        /*Sort the array before hashing*/
        ksort($input);
        /*first value of hash data will be salt*/
        $hash_data = $salt;
        /*Create a | (pipe) separated string of all the $input values which are available in $hash_columns*/
        foreach ($input as $key => $value) {
            if (strlen($value) > 0) {
                $hash_data .= '|' . $value;
            }
        }
        $hash = null;
        if (strlen($hash_data) > 0) {
            $hash = strtoupper(hash("sha512", $hash_data));
        }
        return $hash;
    }

    public function hdfc_encrypt($plainText, $key)
    {
        $key = $this->hdfc_hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $openMode = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
        $encryptedText = bin2hex($openMode);
        return $encryptedText;
    }

    public function hdfc_decrypt($encryptedText, $key)
    {
        $key = $this->hdfc_hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText = $this->hdfc_hextobin($encryptedText);
        $decryptedText = openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
        return $decryptedText;
    }

    //*********** Padding public Function hdfc_*********************
    public function hdfc_pkcs5_pad($plainText, $blockSize)
    {
        $pad = $blockSize - (strlen($plainText) % $blockSize);
        return $plainText . str_repeat(chr($pad), $pad);
    }

    //********** Hexadecimal to Binary public function hdfc_for php 4.0 version ********
    public function hdfc_hextobin($hexString)
    {
        $length = strlen($hexString);
        $binString = "";
        $count = 0;
        while ($count < $length) {
            $subString = substr($hexString, $count, 2);
            $packedString = pack("H*", $subString);
            if ($count == 0) {
                $binString = $packedString;
            } else {
                $binString .= $packedString;
            }
            $count += 2;
        }
        return $binString;
    }

    public function icici_aes128Encrypt($str, $key)
    {
        // $plaintext = "1|1|1";
        $cipher = "AES-128-ECB";
        // $cipher = "aes-128-cbc";
        // print_r(openssl_get_cipher_methods() );
        // $key = "1211141980601518";
        // echo strlen($cipher);exit;
        if (in_array($cipher, openssl_get_cipher_methods())) {
            if(openssl_cipher_iv_length($cipher)>0){
                $ivlen = openssl_cipher_iv_length($cipher);
                // echo $ivlen;exit;
                $iv = openssl_random_pseudo_bytes($ivlen);
                $ciphertext = openssl_encrypt($str, $cipher, $key, $options = 0, $iv);
            }else{
                $ciphertext = openssl_encrypt($str, $cipher, $key, $options = 0);
            }
           return $ciphertext; //."n";
            exit;
        }
        return 1;
    }

    public function icici_encrypt($plainText, $key)
    {
        $secretKey = hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
        $blockSize = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
        $plainPad = pkcs5_pad($plainText, $blockSize);
        if (mcrypt_generic_init($openMode, $secretKey, $initVector) != -1) {
            $encryptedText = mcrypt_generic($openMode, $plainPad);
            mcrypt_generic_deinit($openMode);
        }
        return bin2hex($encryptedText);
    }

    public function icici_decrypt($encryptedText, $key)
    {
        $secretKey = hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText = hextobin($encryptedText);
        $openMode = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
        mcrypt_generic_init($openMode, $secretKey, $initVector);
        $decryptedText = mdecrypt_generic($openMode, $encryptedText);
        $decryptedText = rtrim($decryptedText, "\0");
        mcrypt_generic_deinit($openMode);
        return $decryptedText;
    }

    //*********** Padding public function icici_*********************
    public function icici_pkcs5_pad($plainText, $blockSize)
    {
        $pad = $blockSize - (strlen($plainText) % $blockSize);
        return $plainText . str_repeat(chr($pad), $pad);
    }

    //********** Hexadecimal to Binary public function icici_for php 4.0 version ********
    public function icici_hextobin($hexString)
    {
        $length = strlen($hexString);
        $binString = "";
        $count = 0;
        while ($count < $length) {
            $subString = substr($hexString, $count, 2);
            $packedString = pack("H*", $subString);
            if ($count == 0) {
                $binString = $packedString;
            } else {
                $binString .= $packedString;
            }
            $count += 2;
        }
        return $binString;
    }

    public function razorpay(Request $request)
    {
        $data = $this->get_fees($request);
        // dd($data);
        $type = "web";
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_razorpay_fees", $data, "view");
    }

    /**
     * Razorpay
     * Payment
     */
    public function razorpay_request_handler(Request $request)
    {
        $get_map_bank_data = DB::table("fees_online_maping")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        $payment_acsept_type = $get_map_bank_data[0]->fees_type;
        $amount = 0;

        if ($payment_acsept_type == "fix") {
            $amount = number_format(floatval($_REQUEST["total"]) * 100, 0, '.', '');
        } else {
            $amount = number_format(floatval($_REQUEST["pay_amount"]) * 100, 0, '.', '');
        }

        $student_id = $_REQUEST["student_id"];
        $medium_data = DB::select("SELECT a.*,e.grade_id,CONCAT_WS('_',t.first_name,t.middle_name,t.last_name) AS student_name, t.mobile,CONCAT_WS('_',t.first_name,t.middle_name,t.last_name,t.uniqueid) AS uniqueid FROM tblstudent_enrollment e
        inner join academic_section a on e.grade_id = a.id
        INNER JOIN tblstudent t ON t.id=e.student_id
        INNER JOIN fees_online_maping fom ON fom.syear=e.syear AND fom.sub_institute_id=e.sub_institute_id
        WHERE e.student_id = '" . $student_id . "' ORDER BY e.syear DESC LIMIT 1");

        $months = $_REQUEST["months"];

        $get_map_bank_detail = DB::table("fees_razorpay")
            ->where(["sub_institute_id" => session()->get("sub_institute_id"), "medium" => $medium_data[0]->medium])
            ->get();
        //echo '<pre>RAJESH'; print_r($get_map_bank_detail); exit;

        $api = new Api($get_map_bank_detail[0]->key_id, $get_map_bank_detail[0]->key_secret);

        try {
            $orderData = [
                'receipt'         => 'order_' . uniqid(),
                'amount'          => $amount, // Convert amount to paise for Razorpay
                'currency'        => 'INR',
                'payment_capture' => 1 // Auto capture
            ];

            $razorpayOrder = $api->order->create($orderData);

            $request->session()->put('razorpay_order_id', $razorpayOrder['id']);

        } catch (\Exception $e) {
            Session::put('error', $e->getMessage());
            $school_data = array();
            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_error", $school_data, "view");
        }

        $in_arr = array(
            "student_id" => $_REQUEST["student_id"],
            "syear" => session()->get("syear"),
            "amount" => $amount,
            "razorpay_order_id" => $razorpayOrder['id'],
            "razorpay_payment_status" => "PR",
            "razorpay_payment_date" => now(),
            "sub_institute_id" => session()->get("sub_institute_id"),
            "created_at" => now(),
            "updated_at" => now()
        );
        DB::table("fees_payment")
            ->insert($in_arr);
        $id = DB::getPdo()->lastInsertId();

        $data = array(
            "student_id" => $student_id,
            "months" => $months,
            "amount" => $amount,
            "key" => $get_map_bank_detail[0]->key_id,
            "inserted_id" => $id,
            "student_name" => $medium_data[0]->student_name,
            "medium" => $medium_data[0]->uniqueid,
            "order_id" => $razorpayOrder['id'],
        );
        // echo '<pre>'; print_r($id); exit;
        $type = "web";
        // $data = array(
        //     "send_data" => $data_send,
        // );
        //echo '<pre>'; print_r($data); exit;
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/razorpay23_RequestHandler", $data, "view");


        // return \App\Helpers\is_mobile($type, "fees/online_fees_collect/icici_RequestHandler", $data, "view");
    }

    /**
     * Fetch payment status from RAZORPAY
     */
    public function razorpay_fetch_payment_status(Request $request) {

        // get payment data if payment status is not captured and is not null and order id is not null
        $payment_data = DB::table('fees_payment AS fp')
            ->select('fp.id', 'fp.student_id', 'fr.key_id', 'fr.key_secret', 'fp.razorpay_order_id', 'tse.syear', 'fp.sub_institute_id', 'fp.amount')
            ->join('tblstudent_enrollment AS tse', function ($join) {
                $join->on('tse.student_id', '=', 'fp.student_id')
                    ->on('tse.syear', '=', 'fp.syear')
                    ->on('tse.sub_institute_id', '=', 'fp.sub_institute_id');
            })
            ->join('academic_section AS a', 'a.id', '=', 'tse.grade_id')
            ->join('fees_razorpay AS fr', function ($join) {
                $join->on('fr.medium', '=', 'a.medium')
                    ->on('fr.sub_institute_id', '=', 'tse.sub_institute_id');
            })
            ->where(function ($query) {
                $query->whereNotIn('fp.razorpay_dashboard_ps', ['captured', 'refunded', 'rajesh','failed'])
                      ->orWhereNull('fp.razorpay_dashboard_ps');
            })
            ->whereNotNull('fp.razorpay_order_id')
            ->whereBetween('fp.created_at', [now()->subDays(3), now()->subMinutes(30)])
            //->whereIn('fp.id', [55436,55442])
            //->where('fp.razorpay_order_id', 'LIKE', 'order_%')
            ->groupBy('fp.id')
            ->get(); // ->on('tse.syear', '=', 'fr.syear')

            //->whereIn('fp.sub_institute_id', [253])
            //->limit($limit)

        if ( !empty($payment_data) ) {

            foreach ( $payment_data as $data ) {
                $id = $data->id;
                $key_id = $data->key_id;
                $key_secret = $data->key_secret;
                $order_id = $data->razorpay_order_id;
                $student_id = $data->student_id;
                $amount = round($data->amount,0);

                // initial razorpay api
                $api = new Api($key_id, $key_secret);

$payment = null;
$status = null;
$payment_id = null;
$json_response = [];
$is_valid = false;

// Detect ID type and fetch payment(s)
if (Str::startsWith($order_id, 'pay_')) {
    $payment = $api->payment->fetch($order_id);
    if (!empty($payment) && $payment['id']) {
        $is_valid = true;
    }
} elseif (Str::startsWith($order_id, 'order_')) {
    $paymentCollection = $api->order->fetch($order_id)->payments();
    if (!empty($paymentCollection) && $paymentCollection['count'] > 0) {
        $payment = $paymentCollection['items'][0]; // First payment
        $is_valid = true;
    }
}
                //echo "<pre>";
                //print_r($payment);
                //exit();

                if ($is_valid && !empty($payment)) {
                    
                    $status = $payment['status'];
                    $payment_id = $payment['id'];
                    $json_response = $this->razorpay_payment_response_data_to_array($payment);

                    $update_arr = array(
                        "razorpay_dashboard_ps" => $status,
                        "icici_bank_res" => "cron",
                        "razorpay_bank_res" => $json_response,
                        'razorpay_order_id' => $payment_id,
                        "updated_at" => now()
                    );
                //echo "<pre>IF-PAY"; print_r($data); exit;
                  DB::table("fees_payment")
                    ->where('id', $id)
                    ->update($update_arr);
                
                    $request->merge([
                        '_key' => csrf_token(),
                        'student_id' => $student_id,
                        'inserted_id' => $id,
                        'razorpay_payment_id' => $payment_id,
                        'syear' => $data->syear,
                        'sub_institute_id' => $data->sub_institute_id
                    ]);

                    // echo "<pre>"; print_r($request->all()); exit;
                    if($status == 'captured')
                        $schooldata = $this->pay_fees($request, $data->student_id, $data->syear, $data->sub_institute_id, ($amount/100), $payment_id);
                }
                else{
                    $update_arr = array(
                        "razorpay_dashboard_ps" => 'cron',
                        "icici_bank_res" => "cron",
                        "updated_at" => now()
                        );
                //echo "<pre>IF-PAY"; print_r($data); exit;
                  DB::table("fees_payment")
                    ->where('id', $id)
                    ->update($update_arr);

                }
            }
        }
    }

    public function razorpay_response_handler(Request $request)
    {
        $input = $request->all();
        // echo "<pre>";
        // print_r($input);
        // exit;

        $student_id = $_REQUEST["student_id"];
        $medium_data = DB::select("SELECT a.*,e.grade_id,CONCAT_WS('_',t.first_name,t.middle_name,t.last_name) AS student_name, t.mobile FROM tblstudent_enrollment e
        inner join academic_section a on e.grade_id = a.id
        INNER JOIN fees_online_maping fom ON fom.syear=e.syear AND fom.sub_institute_id=e.sub_institute_id
        INNER JOIN tblstudent t ON t.id=e.student_id

        WHERE e.student_id = '" . $student_id . "' ORDER BY e.syear DESC LIMIT 1");

        $get_map_bank_detail = DB::table("fees_razorpay")
            ->where(["sub_institute_id" => session()->get("sub_institute_id"), "medium" => $medium_data[0]->medium])
            ->get();

        $update_arr = array(
            "razorpay_order_id" => $input['razorpay_payment_id'],
            "updated_at" => now()
        );

        $where_arr = array(
            "id" => $_REQUEST["inserted_id"]
        );
        // echo "<pre>"; print_r($response); exit;
        DB::table("fees_payment")
            ->where($where_arr)
            ->update($update_arr);

        $api = new Api($get_map_bank_detail[0]->key_id, $get_map_bank_detail[0]->key_secret);
        $payment = $api->payment->fetch($input['razorpay_payment_id']);
        if (count($input) && !empty($input['razorpay_payment_id'])) {
            try {

                //Fetch payment information by razorpay_payment_id
                //$response = $api->payment->fetch($input['razorpay_payment_id'])->capture(array('amount' => $payment['amount'], 'currency' => 'INR'));
                $response = $api->payment->fetch($input['razorpay_payment_id']);

                $json_response = $this->razorpay_payment_response_data_to_array($response);

                $res_josn = json_encode($response);
                $get_all_data = DB::table("fees_payment")
                    ->where(["id" => $_REQUEST["inserted_id"]])
                    ->get();
                $payment_status_res = $response['status'];
                $payment_status = ($payment_status_res == "captured") ? 'PS' : 'PR';


                $update_arr = array(
                    "razorpay_order_id" => $input['razorpay_payment_id'],
                    "razorpay_payment_status" => $payment_status,
                    "razorpay_dashboard_ps" => $payment_status_res,
                    "icici_bank_res" => $payment_status_res,
                    "razorpay_bank_res" => $json_response,
                    "updated_at" => now()
                );

                $where_arr = array(
                    "id" => $_REQUEST["inserted_id"]
                );


                //START RAJESH 08-04-2025 = prevent second time success
                if($get_all_data[0]->razorpay_payment_status == 'PS'){
                    $school_data = array();
                    $school_data["website"] = $this->site_name();
                    $type = "web";
                    return \App\Helpers\is_mobile($type, "fees/online_fees_collect/search_student", $school_data, "view");
                }
                //END RAJESH 08-04-2025

                // echo "<pre>"; print_r($response); exit;
                DB::table("fees_payment")
                    ->where($where_arr)
                    ->update($update_arr);

                if($payment_status_res == 'captured'){
                    $data = $this->pay_fees($request, $get_all_data[0]->student_id, $get_all_data[0]->syear, $get_all_data[0]->sub_institute_id, ($get_all_data[0]->amount / 100), $input['razorpay_payment_id']);
                    $type = $request->input('type');
                    return \App\Helpers\is_mobile($type, "fees/online_fees_collect/receipt_view", $data, "view");
                }
                else 
                {
                    $type = $request->input('type');
                    $school_data = array();
                    return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_error", $school_data, "view");
                }

            } catch (Exception $e) {
                return $e->getMessage();
                $res_josn = json_encode($e->getMessage());
                $get_all_data = DB::table("fees_payment")
                    ->where(["id" => $_REQUEST["inserted_id"]])
                    ->get();
                $payment_status = "PF";

                $update_arr = array(
                    "razorpay_payment_status" => $payment_status,
                    "razorpay_bank_res" => $res_josn,
                    "updated_at" => now()
                );
                $where_arr = array(
                    "id" => $_REQUEST["inserted_id"]
                );
                // echo '<pre>'; print_r($where_arr); exit;
                DB::table("fees_payment")
                    ->where($where_arr)
                    ->update($update_arr);
                Session::put('error', $e->getMessage());
                $school_data = array();
                return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_error", $school_data, "view");
                // return redirect()->back();
            }
        }
        // Session::put('success', 'Payment successful');
        // return redirect()->back();
    }

    public function razorpay_payment_response_data_to_array($response)
    {

        if (!empty($response)) {
            $data = [];
            foreach ($response as $key => $value) {
                $data[$key] = $value;
            }

            // echo "<pre>"; print_r(json_encode($data)); exit;
            return json_encode($data);
        }
    }

    public function payphi(Request $request)
    {
        $data = $this->get_fees($request);
        $type = "web";

        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_payphi_fees", $data, "view");
    }

    public function payphi_request_handler(Request $request)
    {
        $student_id = $_REQUEST["student_id"];
        $fine = isset($_REQUEST["fees_data"]["fine"]) ? $_REQUEST["fees_data"]["fine"] : 0;
        $medium_data = DB::select("SELECT a.*,e.grade_id,s.name AS standard, d.name AS division,CONCAT_WS('_',t.first_name,t.middle_name,t.last_name) AS student_name, t.mobile, t.enrollment_no, t.email,ifnull(b.title,0) AS batch FROM tblstudent_enrollment e
            inner join academic_section a on e.grade_id = a.id
            inner join standard s on e.standard_id = s.id
            inner join division d on e.section_id = d.id
            INNER JOIN tblstudent t ON t.id=e.student_id
            LEFT JOIN batch b ON b.id=t.studentbatch
            INNER JOIN fees_online_maping fom ON fom.syear=e.syear AND fom.sub_institute_id=e.sub_institute_id
            WHERE e.student_id = '" . $student_id . "' ORDER BY e.syear DESC LIMIT 1");

        $get_map_bank_data = DB::table("fees_online_maping")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();

        $payment_acsept_type = null;
        if (!empty($get_map_bank_data[0])) {
            $payment_acsept_type = $get_map_bank_data[0]->fees_type;
        }

        $get_map_bank_detail = DB::table("fees_payphi")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
            
        $amount = 0;
        if ($payment_acsept_type == "fix") {
            $amount = number_format(floatval($_REQUEST["total"]), 0, '.', '');
        } else {
            $amount = number_format(floatval($_REQUEST["pay_amount"]), 0, '.', '');
        }

        $where_arr = array(
            "sub_institute_id" => session()->get("sub_institute_id"),
            "id" => $student_id
        );
        
        $get_mobile = DB::table("tblstudent")
            ->where($where_arr)
            ->get();
        
        $data = '';
        $mobile_number = $get_mobile[0]->mobile;
        $orderId = $student_id . (mt_rand(100000, 10000000000));
        $merchantId = $get_map_bank_detail[0]->merchant_id;
        $key = $get_map_bank_detail[0]->key;
        $merchantTxnNo = Str::random(15);
        $currencyCode = "356";
        $payType = '0'; // 1 for Direct
        $transactionType = "SALE";
        $txnDate = date('YmdHis');
        $PgRefNo = $orderId;
        $returnURL = $this->site_name() . "fees/payphi/online_fees_payphiResponseHandler";
        $amount = number_format($amount, 2, '.', '');
        $customerEmailID = $student_id.'@gmail.com';

        // Step 1: Concatenate the parameter values in ascending order of parameter names
        $fields = array(
            'amount' => $amount,
            'currencyCode' => $currencyCode,
            'customerEmailID' => $customerEmailID,
            'merchantID' => $merchantId,
            'merchantTxnNo' => $merchantTxnNo,
            'payType' =>  $payType,
            'returnURL' => $returnURL,
            'transactionType' => $transactionType,
            'txnDate' => $txnDate,
        );

        // form the string for hash input
        ksort($fields);

        $hash_input = '';
        foreach($fields as $key_val=>$value) {
            if (strlen($value) > 0) { 
                $hash_input .= $value; 
            }
        }

        // calculate the hmac 256 signature
        // use the secret key corresponding to your merchantid
        $sig = hash_hmac('sha256', $hash_input, $key);
        
        $secureHash = $sig;

        $str = $medium_data[0]->student_name .'|'. $medium_data[0]->mobile .'|'. $student_id.'@gmail.com|' . $medium_data[0]->enrollment_no .'|'. $medium_data[0]->standard .'|'. $medium_data[0]->division .'|'. $medium_data[0]->batch .'|'. $student_id .'|'. $merchantId .'|'. $merchantTxnNo .'|'. $currencyCode .'|'. $amount .'|'.$payType .'|'. $transactionType .'|'. $txnDate .'|'. $returnURL .'|'. $secureHash;

        $curl = curl_init();
//CURLOPT_URL => 'https://qa.phicommerce.com/pg/api/v2/initiateSale',
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://secure-ptg.payphi.com/pg/api/v2/initiateSale',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                "merchantId": "' . $merchantId . '",
                "merchantTxnNo": "' . $merchantTxnNo . '",
                "amount": "' . $amount . '",
                "currencyCode": "' . $currencyCode . '",
                "payType": "' . $payType . '",
                "customerEmailID": "' . $student_id . '@gmail.com",
                "transactionType": "' . $transactionType . '",
                "txnDate": "' . $txnDate . '",
                "returnURL": "' . $returnURL . '",
                "secureHash": "' . $secureHash . '"
            }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $responseArray = json_decode($response, true);

        $in_arr = array(
            "student_id" => $_REQUEST["student_id"],
            "syear" => session()->get("syear"),
            "amount" => ($amount - $fine),
            "fine" => $fine,
            "payphi_order_id" => $merchantTxnNo,
            "payphi_request" => $str,
            "payphi_payment_status" => "PR",
            "payphi_payment_date" => now(),
            "sub_institute_id" => session()->get("sub_institute_id"),
            "created_at" => now(),
            "updated_at" => now()
        );

        DB::table("fees_payment")
            ->insert($in_arr);
        $type = "web";

        $data = array(
            "send_response" => $responseArray,
        );

        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/payphi_RequestHandler", $data, "view");
    }

    public function payphi_fetch_payment_status(Request $request) 
    {
        $payment_data = DB::table('fees_payment AS fp')
            ->select('fp.id', 'fp.student_id', 'fi.key', 'fi.merchant_id', 'fp.payphi_order_id', 'tse.syear', 'fp.sub_institute_id', 'fp.amount', 'fp.fine','fp.payphi_response')
            ->join('tblstudent_enrollment AS tse', function ($join) {
                $join->on('tse.student_id', '=', 'fp.student_id')
                    ->on('tse.syear', '=', 'fp.syear')
                    ->on('tse.sub_institute_id', '=', 'fp.sub_institute_id');
            })
            ->join('academic_section AS a', 'a.id', '=', 'tse.grade_id')
            ->join('fees_payphi AS fi', function ($join) {
                $join->on('fi.sub_institute_id', '=', 'tse.sub_institute_id');
            })
            ->where(function ($query) {
                $query->where('fp.payphi_payment_status', '!=', 'PS')
                    ->where(function ($query) {
                        $query->whereNotIn('fp.payphi_payment_status', ['NotInitiated', 'REJ', 'SUC'])
                            ->orWhereNull('fp.payphi_payment_status');
                    });
            })
            ->whereNotNull('fp.payphi_order_id')
            ->whereBetween('fp.created_at', [now()->subDays(3), now()->subMinutes(30)])
            ->groupBy('fp.id')
            ->get();
            
        $check = [];
        if ( !empty($payment_data) ) 
        {
            foreach ( $payment_data as $data ) 
            {
                $id = $data->id;
                $key_id = $data->merchant_id;
                $key = $data->key;
                $payment_id = $data->payphi_order_id;
                $student_id = $data->student_id;
                $amount = number_format($data->amount, 2, '.', '');
                $fine = $data->fine;
                $merchantTxnNo = $payment_id;
                $originalTxnNo = $merchantTxnNo;
                $transactionType = 'STATUS';

                $fields = array(
                    'amount' => $amount,
                    'merchantID' => $key_id,
                    'merchantTxnNo' => $merchantTxnNo,
                    'originalTxnNo' => $originalTxnNo,
                    'transactionType' =>  $transactionType,
                );
                
                // form the string for hash input
                ksort($fields);
        
                $hash_input = '';
                foreach($fields as $key_val=>$value) {
                    if (strlen($value) > 0) { 
                        $hash_input .= $value; 
                    }
                }
        
                // calculate the hmac 256 signature
                // use the secret key corresponding to your merchantid
                $sig = hash_hmac('sha256', $hash_input, $key);
                $secureHash = $sig;
            
                // initial payphi status api
                $curl = curl_init();

                $postFields = http_build_query(array(
                    'merchantId' => $key_id,
                    'merchantTxnNo' => $merchantTxnNo,
                    'originalTxnNo' => $originalTxnNo,
                    'amount' => $amount,
                    'transactionType' => $transactionType,
                    'secureHash' => $secureHash,
                ));
//                    CURLOPT_URL => 'https://qa.phicommerce.com/pg/api/command',
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://secure-ptg.payphi.com/pg/api/command',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => $postFields,
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/x-www-form-urlencoded'
                    ),
                ));

                $response = curl_exec($curl);
              
                curl_close($curl);

                $fetchResponseArray = json_decode($response, true);

                if (!empty($response)) 
                {
                    $status = isset($fetchResponseArray['txnStatus']) ? $fetchResponseArray['txnStatus'] : 'PR';
                    $txnResponseCode = isset($fetchResponseArray['txnResponseCode']) ? $fetchResponseArray['txnResponseCode'] : $fetchResponseArray['responseCode'];
                   
                    $update_arr = array(
                        "payphi_payment_status" => $status,
                        "payphi_response" => $response,
                        "updated_at" => now()
                    );

                    DB::table("fees_payment")
                    ->where('id', $id)
                    ->update($update_arr);

                    $request->merge([
                        '_key' => csrf_token(),
                        'student_id' => $student_id,
                        'inserted_id' => $id,
                        'payphi_payment_id' => $payment_id,
                        'syear' => $data->syear,
                        'sub_institute_id' => $data->sub_institute_id
                    ]);

                    if($status == 'SUC' && $txnResponseCode == "0000")
                    {
                        $check = DB::table('fees_collect')->whereRaw('cheque_no="'.$payment_id.'" AND student_id='.$student_id.' AND syear='.$data->syear.' AND sub_institute_id='.$data->sub_institute_id)->get()->toArray();

                        if(count($check) == 0)
                        {
                            $schooldata = $this->pay_fees($request, $data->student_id, $data->syear, $data->sub_institute_id, $amount, $payment_id,$fine);
                        }
                    }
                }
            }
        }
    }
    
    public function payphi_response_handler(Request $request)
    {
        $get_map_bank_detail = DB::table("fees_payphi")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();

        $response = $_REQUEST;
       
        $res_josn = json_encode($response);

        $get_all_data = DB::table("fees_payment")
            ->where(["payphi_order_id" => $response["merchantTxnNo"]])
            ->get();

        $payment_status = "PF";
        if ($response["responseCode"] == "0000") {
            $payment_status = "PS";
        }

        $update_arr = array(
            "payphi_payment_status" => $payment_status,
            "payphi_response" => $res_josn,
            "updated_at" => now()
        );
        
        $where_arr = array(
            "sub_institute_id" => $get_all_data[0]->sub_institute_id,
            "syear" => $get_all_data[0]->syear,
            "payphi_order_id" => $response["merchantTxnNo"]
        );

        //START RAJESH 27-05-2023 = prevent second time success
            if($get_all_data[0]->payphi_payment_status == 'PS'){
                $school_data = array();
                $school_data["website"] = $this->site_name();
                $type = "web";
                return \App\Helpers\is_mobile($type, "fees/online_fees_collect/search_student", $school_data, "view");
            }
        //END RAJESH 27-05-2023

        // echo '<pre>'; print_r($where_arr); exit;
        DB::table("fees_payment")
            ->where($where_arr)
            ->update($update_arr);
            
        if ($payment_status == "PS") 
        {
            $data = $this->pay_fees($request, $get_all_data[0]->student_id, $get_all_data[0]->syear, $get_all_data[0]->sub_institute_id, $get_all_data[0]->amount,$response["merchantTxnNo"],$get_all_data[0]->fine);
            $type = $request->input('type');

            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/receipt_view", $data, "view");
        } 
        else 
        {
            $type = $request->input('type');
            $school_data = array();

            return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_error", $school_data, "view");
        }
    }

    public function abcmapp_response_handler(Request $request)
    {
        $response = $_REQUEST;
        print_r($response);
        echo "ABCM APP";
    }

    // 31-03-2025 get split amounts 
    public function breakOffAmounts($request,$student_id,$sub_institute_id,$syear){
        $stu_arr[0] = $student_id;

        // get all month name with month_id
        $month_arr = FeeMonthId($syear,$sub_institute_id);
        $currunt_month = date('m');
        $currunt_year = date('Y');
        $currunt_month_id = $currunt_month . $currunt_year;

        $search_ids = [];
        foreach ($month_arr as $id => $arr) {
            if ($id == $currunt_month_id) {
                $search_ids[] = $id;
                // break;
            } else {
                $search_ids[] = $id;
            }
        }

        $reg_bk_off = FeeBreackoff($stu_arr,'',$syear,$sub_institute_id);

        // OtherBreackOff is for additional fee from helper.php
        $other_bk_off = OtherBreackOff($stu_arr, $search_ids,'', null,null, $syear,$sub_institute_id);

        //  OtherBreackOfMonth get additional fees monthwise from helper.php
        $other_bk_off_month_wise = OtherBreackOfMonth($stu_arr,$syear,$sub_institute_id);

        //  OtherBreackOfMonthHead additional fees_title from helper.php
        $other_bk_off_month_head_wise = OtherBreackOfMonthHead($stu_arr, $search_ids);

        //  FeeBreakoffHeadWise get fees_title from helper.php
        $head_wise_fees = FeeBreakoffHeadWise($stu_arr, null, null, null, $syear,'',$sub_institute_id);

        $ret_heds_with_id = DB::table('fees_title')->selectRaw('id,fees_title,display_name')
        ->where('SUB_INSTITUTE_ID', $sub_institute_id)
        ->where('syear', $syear)
        ->orderBy('sort_order')->get()->toArray();

        // changes fees heads 
        $newFeesHeads = [];
        foreach ($ret_heds_with_id as $key => $value) {
           if(isset($request['fees_heads'][$value->display_name])){
            $newFeesHeads[$value->fees_title] = $request['fees_heads'][$value->display_name];
           }
        }
        // $newFeesHeads[$value->fees_title]
    // echo "<pre>";print_r($newFeesHeads);exit;
        $reg_fee_heads = $reg_fee_bk = [];
        foreach ($head_wise_fees as $student_id => $detail_arr) {
            $reg_fee_bk = $detail_arr['breakoff'];
            foreach ($detail_arr['breakoff'] as $id => $arr) {
                foreach ($arr as $head_name => $vals) {
                    if (!in_array($head_name, $reg_fee_heads)) {
                        $reg_fee_heads[] = $head_name;
                    }
                }
            }
        }

        $other_fee_heads = [];
        foreach ($newFeesHeads as $id => $vals) {
            if (!in_array($id, $reg_fee_heads)) {
                $other_fee_heads[] = $id;
            }
        }
        //getting reg fee month_id that we need to pay
        $reg_months_pay = [];
        foreach ($reg_fee_bk as $month_id => $arr) {
            if (in_array($month_id, $request['months'])) {
                $reg_months_pay[] = $month_id;
            }
        }

        $oth_months_pay = [];
        foreach ($other_bk_off_month_wise as $month_id => $arr) {
            if (in_array($month_id, $_REQUEST['months'])) {
                $oth_months_pay[] = $month_id;
            }
        }

        $reg_insert_arr = [];
        foreach ($reg_fee_bk as $month => $bk_off) {
            if (in_array($month, $reg_months_pay)) {
                foreach ($bk_off as $title => $arr) {
                    if (array_key_exists($title, $newFeesHeads)) {
                        $insert_amount = 0;
                        if ($newFeesHeads[$title] > $arr['amount']) {
                            $newFeesHeads[$title] = $newFeesHeads[$title] - $arr['amount'];
                            $insert_amount = $arr['amount'];
                        } else {
                            $insert_amount = $newFeesHeads[$title];
                            $newFeesHeads[$title] = 0;
                        }
                        $reg_insert_arr[$month][$title] = $insert_amount;
                    }
                }
            }
        }
        $controller = new fees_collect_controller;

        $receipt_number = $controller->gunrate_receipt_number($sub_institute_id,$syear);

      
        $heds_with_id = [];
        foreach ($ret_heds_with_id as $id => $arr) {
            $heds_with_id[$arr->fees_title] = $arr->id;
        }
        $new_insert_arr = [];
        $sort_orderFess = [];
        foreach ($reg_insert_arr as $month_id => $arr) {
            foreach ($arr as $id => $val) {
                $head_id = $heds_with_id[$id];
                foreach ($receipt_number as $temp_id => $arr_head_rid) {
                    $heds = explode(',', $arr_head_rid['heds']);
                    if (in_array($head_id, $heds)) {
                        $receipt_number[$temp_id]['used'] = 1;
                        $new_insert_arr[$month_id][$arr_head_rid['rid'] . '_' . $temp_id][$id] = $val;

                        if($temp_id==2){
                            if(!isset($sort_orderFess['mission'])){
                                $sort_orderFess['mission'] = 0;
                            }
                            $sort_orderFess['mission'] += $val;
                        }else{
                            if(!isset($sort_orderFess['school'])){
                                $sort_orderFess['school'] = 0;
                            }
                            $sort_orderFess['school'] += $val; 
                        }
                    }
                }
            }
        }
        $res['typewise'] = $new_insert_arr;
        $res['typewise_total'] = $sort_orderFess;
        return $res;
    }
}
