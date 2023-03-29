<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use App\Models\easy_com\manage_sms_api\manage_sms_api;
use App\Models\fees\fees_title\fees_title;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class feesStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $months = FeeMonthId();
        $feesHead = fees_title::where(['sub_institute_id' => $sub_institute_id, 'other_fee_id' => 0])->pluck('display_name', 'fees_title')->toArray();


        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['months'] = $months;
        $res['fees_heads'] = $feesHead;

        return is_mobile($type, "fees/fees_report/status_report", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function feesStatusReport(Request $request)
    {
        $type = $request->input('type');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $month = $request->input('month');
        $fees_head = $request->input('fees_head');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $months = FeeMonthId();

        $feesHead = fees_title::where(['sub_institute_id' => $sub_institute_id, 'other_fee_id' => 0])->pluck('display_name', 'fees_title')->toArray();
        $studentData = SearchStudent($grade, $standard, $division);

        if (count($studentData) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No student found please check your search panel";
            return is_mobile($type, "fees_status_report.index", $res);
        }

        foreach ($studentData as $key => $values) {
            $student_ids[] = $values['student_id'];
        }

        $displayBreakoff = array();

        //$student_ids = array("0"=>"17142","1"=>"17141");//16988,16849
        $data = FeeBreakoffHeadWise($student_ids);

        $whereRaw = "1 = 1 ";
        if ($month != null) {
            $whereRaw .= " AND `term_id` IN (" . implode(",", $month) . ")";
        }
        $whereRaw .= " AND sub_institute_id = " . $sub_institute_id . " AND syear = " . $syear . " AND student_id IN (" . implode(",", $student_ids) . ") AND is_deleted != 'Y'";
        $feesPaidRaw = DB::table("fees_collect")
            ->whereRaw($whereRaw)
            ->get()
            ->toArray();

        $feesPaid = array();
        foreach ($feesPaidRaw as $fid => $fvalue) {
            foreach ($fees_head as $head => $headDisplay) {
                if (isset($feesPaid[$fvalue->student_id][$fvalue->term_id][$headDisplay])) {
                    $feesPaid[$fvalue->student_id][$fvalue->term_id][$headDisplay] += $fvalue->$headDisplay;
                } else {
                    $feesPaid[$fvalue->student_id][$fvalue->term_id][$headDisplay] = $fvalue->$headDisplay;
                }
            }
        }

        foreach ($student_ids as $key => $student_id) {
            $amountLogs = 0;
            if (isset($data[$student_id]['breakoff'])) {
                foreach ($data[$student_id]['breakoff'] as $key => $value) {
                    if (in_array($key, $month)) {
                        foreach ($value as $head => $valueArray) {
                            if (in_array($head, $fees_head)) {
                                if (isset($displayBreakoff[$student_id][$valueArray['title']])) {
                                    $displayBreakoff[$student_id][$valueArray['title']] = $valueArray['amount'] + $displayBreakoff[$student_id][$valueArray['title']];
                                } else {

                                    //if(isset($valueArray['amount']))
                                    //{
                                    $displayBreakoff[$student_id][$valueArray['title']] = $valueArray['amount'];
                                    //}
                                }
                                // if(isset($feesPaid[$student_id][$key][$head]))
                                // {
                                // 	$displayBreakoff[$student_id][$valueArray['title']] = $displayBreakoff[$student_id][$valueArray['title']] - $feesPaid[$student_id][$key][$head];
                                // }

                                $amountLogs += $displayBreakoff[$student_id][$valueArray['title']];
                            }
                        }
                    }
                }
            }
            // if($amountLogs <= 0)
            // {
            // 	unset($data[$student_id]);
            // }
        }

        // dd($data);
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['months'] = $months;
        $res['month'] = $month;
        $res['fees_heads'] = $feesHead;
        $res['fees_head'] = $fees_head;
        $res['fees_data'] = $data;
        $res['fees_details'] = $displayBreakoff;

        return is_mobile($type, "fees/fees_report/status_report", $res, "view");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function ajaxRemainFeesSMSsend(Request $request)
    {
        if ($request->ajax()) {
            $studentsData = $request->studentsData;

            $sub_institute_id = session()->get('sub_institute_id');
            $message_sent = [];
            foreach ($studentsData as $student) {

                $id = $student['student_id'];
                $name = $student['student_name'];
                $mobile = $student['student_mobile'];
                $remain_fees = $student['student_remain_fees'];

                $message = "Dear $name, Your pending fees is Rs.$remain_fees, So paid it.....";

                $responce = $this->sendSMS($mobile, $message, $sub_institute_id);
                if ($responce['error'] == 1) {
                    $response = ['status' => 400, 'msg' => 'SMS sent failed.'];
                    break;
                } else {
                    array_push($message_sent, $id);
                    $response = ['status' => 200, 'msg' => 'SMS sent successfull.'];
                    // $student_id = 0;
                    // foreach ($student_data as $id => $arr) {
                    // 	if ($arr['mobile'] == $number) {
                    // 		$student_id = $arr['student_id'];
                    // 	}
                    // }
                    // $this->saveParentLog($student_id, $text, $number,$sub_institute_id,$syear);
                }
            }
            if ($response['status'] == 200) {
                // Session::put('success', 'SMS sent');
                Session::flash('success', 'SMS sent');
            }
            echo json_encode($response);
        }
    }

    public function sendSMS($mobile, $text, $sub_institute_id)
    {
        //$sub_institute_id = session()->get('sub_institute_id');
        $data = manage_sms_api::where(['sub_institute_id' => $sub_institute_id])
            ->get()->first();
        // ->toArray();
        $isError = 0;
        // if($data){

        //     echo '<pre>'; print_r($data); exit;
        // }
        if ($data) {
            $data = $data->toArray();
            $isError = 0;
            $errorMessage = true;

            $text = urlencode($text);
            $data['last_var'] = urlencode($data['last_var']);

            $url = $data['url'] . $data['pram'] . $data['mobile_var'] . $mobile . $data['text_var'] . $text . $data['last_var'];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($ch);

            // print "<pre>";
            // print_r(curl_getinfo($ch));
            // echo '<pre>';
            // print_r($_REQUEST);
            // echo 'out put '.$output ;
            // exit;

            //Ignore SSL certificate verification
            // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // curl_setopt($ch, CURLOPT_URL, $url);
            // $output = curl_exec($ch);

            //Print error if any
            if (curl_errno($ch)) {
                $isError = true;
                $errorMessage = curl_error($ch);
            }
            curl_close($ch);
        } else {
            $isError = 1;
            $errorMessage = "Please add api details first.";
        }
        $responce = array();
        if ($isError) {
            $responce = array('error' => 1, 'message' => $errorMessage);
        } else {
            $responce = array('error' => 0);
        }
        return $responce;
    }
    /*
    Changed on 23 April 2021

    1) Commented feesPaid code on line no 181-184
    2) Commented amountlogs condition on line no 192-195
    3) Added new whereRaw condition form line no 133-137
    4) Changed in helper.php (FeeBreakoffHeadWise) changed student condition on line no 753
    5) Changed in helper.php (FeeBreakoffHeadWise) commented condition if ($value->amount != 0) on line no 758
    6) Changed in helper.php (SearchStudent) commented condition from line no 502-505
    */
}
