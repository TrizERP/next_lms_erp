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
        $get_syear = DB::select("SELECT s.id,s.mobile,se.syear,se.sub_institute_id,s.admission_under
                                FROM tblstudent s
                                INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id
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
        $OldData = $controller->getOnlinebk($request, $all_student[0]->sub_institute_id, $year - 1, $_REQUEST["student_id"]);
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
        return $data;
    }

    public function hdfc(Request $request)
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
        $year = date("Y");
        $controller = new fees_collect_controller;
        // $data = $controller->getOnlinebk($request,$all_student[0]->,2020,16849);
        $data = $controller->getOnlinebk($request, $all_student[0]->sub_institute_id, $year, $_REQUEST["student_id"]);
        $data["fees_type"] = $all_student[0]->fees_type;
        $type = "web";
        // echo '<pre>'; print_r(session()->all()); exit;
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_fees", $data, "view");
        // echo '<pre>'; print_r($data); exit;
    }

    public function hdfc_request_handler(Request $request)
    {
        // echo '<pre>';
        // print_r($_REQUEST);
        // print_r(session()->all());
        // exit;
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
        $in_arr = array(
            "student_id" => $_REQUEST["student_id"],
            "syear" => session()->get("syear"),
            "amount" => $amount,
            "hdfc_order_id" => $orderId,
            "hdfc_transaction_id" => $txnid,
            "hdfc_payment_status" => "PR",
            "hdfc_payment_date" => now(),
            "sub_institute_id" => session()->get("sub_institute_id"),
            "created_at" => now(),
            "updated_at" => now()
        );
        DB::table("fees_payment")
            ->insert($in_arr);
        $working_key = $get_map_bank_detail[0]->working_code; //Shared by CCAVENUES
        // $working_key = "94C918B28626FB1A085AAB522E32A402"; //Shared by CCAVENUES
        $access_code = $get_map_bank_detail[0]->access_code;
        // $access_code = "AVPL86GG59BJ25LPJB";
        $return_url = $this->site_name() . "fees/hdfc/online_fees_hdfcResponseHandler";
        $send_arr = array(
            "tid" => strtotime(date('Y-m-d H:i:s')),
            "sub_institute_id" => session()->get("sub_institute_id"),
            "merchant_id" => $get_map_bank_detail[0]->merchant_id,
            "language" => "EN",
            "order_id" => $orderId,
            "amount" => $amount,
            "currency" => "INR",
            "redirect_url" => $return_url,
            "cancel_url" => $return_url,
            "merchant_param1" => $_REQUEST["student_id"],
            "merchant_param2" => session()->get("syear"),
            "merchant_param3" => $txnid,
        );
        $merchant_data = "";
        foreach ($send_arr as $key => $value) {
            $merchant_data .= $key . '=' . $value . '&';
        }
        $encrypted_data = $this->hdfc_encrypt($merchant_data, $working_key); // Method for encrypting the data.
        $type = "web";
        $data = array(
            "merchant_data" => $encrypted_data,
            "ac_code" => $access_code
        );
        // echo '<pre>'; print_r(session()->all()); exit;
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/hdfc_RequestHandler", $data, "view");
        // echo '<pre>'; print_r($data); exit;
    }

    public function hdfc_responce_handler(Request $request)
    {
        // echo '<pre>';
        // print_r($_REQUEST);
        // print_r(session()->all());
        // exit;
        $searchArr = array('"', "'");
        $replaceArr = array('\"', "\'");
        $get_map_bank_detail = DB::table("fees_hdffc")
            ->where(["sub_institute_id" => session()->get("sub_institute_id")])
            ->get();
        // $working_key = "94C918B28626FB1A085AAB522E32A402"; //Shared by CCAVENUES
        $working_key = $get_map_bank_detail[0]->working_code; //Shared by CCAVENUES
        // $access_code = "AVPL86GG59BJ25LPJB";
        // $workingKey = WORKING_CODE; //Working Key should be provided here.
        $encResponse = $_POST["encResp"]; //This is the response sent by the CCAvenue Server
        $rcvdString = hdfc_decrypt($encResponse, $working_key); //Crypto Decryption used as per the specified working key.
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
                $curMp = $information[1];
            } else if ($i == 29) {
                $student_name = $information[1];
            } else if ($i == 30) {
                $txnid = $information[1];
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
            "curMp" => $curMp,
            "student_name" => $student_name,
            "txnid" => $txnid,
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
            "hdfc_order_id" => $res_arr["RID"]
        );
        // echo '<pre>'; print_r($where_arr); exit;
        DB::table("fees_payment")
            ->where($where_arr)
            ->update($update_arr);
        if ($order_status == "Success") {
            $data = $this->pay_fees($request, $get_all_data[0]->student_id, $get_all_data[0]->syear, $get_all_data[0]->sub_institute_id, $res_arr["AMT"], $order_id);
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

    public function icici(Request $request)
    {
        $data = $this->get_fees($request);
       //dd($data);
        $type = "web";
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_icici_fees", $data, "view");
    }

    public function icici_request_handler(Request $request)
    {
        //echo '<pre>'; print_r($_REQUEST); exit;
        $student_id = $_REQUEST["student_id"];
        $fine = isset($_REQUEST["fees_data"]["fine"]) ? $_REQUEST["fees_data"]["fine"] : 0;
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
        $fine = intval($fine);     // Convert $fine to int

        $in_arr = array(
            "student_id" => $_REQUEST["student_id"],
            "syear" => session()->get("syear"),
            "amount" => ($amount - $fine),
            "fine" => $fine,
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
            ->select('fp.id', 'fp.student_id', 'fi.merchant_id', 'fi.enc_key', 'fp.icici_order_id', 'tse.syear', 'fp.sub_institute_id', 'fp.amount', 'fp.fine','fp.icici_bank_res')
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
                            $query->whereNotIn('fp.razorpay_payment_status', ['NotInitiated', 'FAILED', 'Success'])
                                  ->orWhereNull('fp.razorpay_payment_status');
                      });
            })
            ->whereNotNull('fp.icici_order_id')
//            ->whereIn('fp.sub_institute_id', $ids)
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
                $res = $data->icici_bank_res;
                // initial icici status api
                $url = "https://eazypay.icicibank.com/EazyPGVerify?merchantid=".$key_id."&pgreferenceno=".$payment_id."&dstatus=Y";
                $payment_status = Http::get($url);
                $payment_ex = explode('&', $payment_status);
                //echo "<pre>"; print_r($payment_status);exit;

                $payment = [];
                foreach ($payment_ex as $item) {
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
                            $schooldata = $this->pay_fees($request, $data->student_id, $data->syear, $data->sub_institute_id, $amount, $payment_id,$fine,$PaymentMode);
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
        return \App\Helpers\is_mobile($type, "fees/online_fees_collect/show_aggre_pay_fees", $data, "view");
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

    public function pay_fees(Request $request, $student_id, $syear, $sub_institute_id, $amount, $cheque_no = "",$fine="",$payment_mode = "")
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
                "months" => $pay_month,
                "fees_data" => $final_fees_arr,
                "discount_data" => $discount_data_arr,
                "fine_data" => $fine_data_arr,
                "total" => $total_fees,
                "totalDis" => 0,
                "totalFin" => 0,
                "PAYMENT_MODE" => "Online",
                "receiptdate" => date("Y-m-d"),
                "cheque_date" => "",
                "cheque_no" => $cheque_no,
                "bank_name" => $payment_mode,
                "bank_branch" => "",
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
                "months" => $pay_month,
                "fees_data" => $final_fees_arr,
                "discount_data" => $discount_data_arr,
                "fine_data" => $fine_data_arr,
                "total" => $total_fees,
                "fine" => $final_fees_arr["fine"],
                "totalDis" => 0,
                "totalFin" => 0,
                "PAYMENT_MODE" => "Online",
                "receiptdate" => date("Y-m-d"),
                "cheque_date" => "",
                "cheque_no" => $cheque_no,
                "bank_name" => $payment_mode,
                "bank_branch" => "",
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
            $amount = number_format(floatval($_REQUEST["total"]) * 100, 2, '.', '');
        } else {
            $amount = number_format(floatval($_REQUEST["pay_amount"]) * 100, 2, '.', '');
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

        $in_arr = array(
            "student_id" => $_REQUEST["student_id"],
            "syear" => session()->get("syear"),
            "amount" => $amount,
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
                $query->where('fp.razorpay_dashboard_ps', '!=', 'captured')
                    ->where('fp.razorpay_dashboard_ps', '!=', 'refunded')
                    ->orWhereNull('fp.razorpay_dashboard_ps');
            })
            ->whereNotNull('fp.razorpay_order_id')
            ->groupBy('fp.id')
            ->get();

        if ( !empty($payment_data) ) {

            foreach ( $payment_data as $data ) {
                $id = $data->id;
                $key_id = $data->key_id;
                $key_secret = $data->key_secret;
                $payment_id = $data->razorpay_order_id;
                $student_id = $data->student_id;
                $amount = round($data->amount,0);

                // initial razorpay api
                $api = new Api($key_id, $key_secret);
                $payment = $api->payment->fetch($payment_id);

                if ( !empty( $payment ) ) {
                    $status = $payment['status'];
                    $json_response = $this->razorpay_payment_response_data_to_array($payment);

                    $update_arr = array(
                        "razorpay_dashboard_ps" => $status,
                        "icici_bank_res" => "cron",
                        "razorpay_bank_res" => $json_response,
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
                $response = $api->payment->fetch($input['razorpay_payment_id'])->capture(array('amount' => $payment['amount'], 'currency' => 'INR'));

                $json_response = $this->razorpay_payment_response_data_to_array($response);

                $res_josn = json_encode($response);
                $get_all_data = DB::table("fees_payment")
                    ->where(["id" => $_REQUEST["inserted_id"]])
                    ->get();
                $payment_status_res = $response['status'];
                $payment_status = "PS";


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
                // echo "<pre>"; print_r($response); exit;
                DB::table("fees_payment")
                    ->where($where_arr)
                    ->update($update_arr);


                $data = $this->pay_fees($request, $get_all_data[0]->student_id, $get_all_data[0]->syear, $get_all_data[0]->sub_institute_id, ($payment['amount'] / 100), $input['razorpay_payment_id']);
                $type = $request->input('type');


                return \App\Helpers\is_mobile($type, "fees/online_fees_collect/receipt_view", $data, "view");
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
        $returnURL = $this->site_name() . "/fees/payphi/online_fees_payphiResponseHandler";
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
        foreach($fields as $key=>$value) {
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

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://qa.phicommerce.com/pg/api/v2/initiateSale',
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
                foreach($fields as $key=>$value) {
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

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://qa.phicommerce.com/pg/api/command',
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
                    $status = $fetchResponseArray['txnStatus'];
                    $txnResponseCode = $fetchResponseArray['txnResponseCode'];
                   
                    $update_arr = array(
                        "payphi_payment_status" => $status,
                        "payphi_response" => $fetchResponseArray,
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
                        $check = DB::table('fees_collect')->whereRaw('cheque_no='.$payment_id.' AND student_id='.$student_id.' AND syear='.$data->syear.' AND sub_institute_id='.$data->sub_institute_id)->get()->toArray();

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
}
