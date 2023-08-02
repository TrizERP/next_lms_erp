<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use App\Models\admission\admissionEnquiryModel;
use App\Models\castModel;
use App\Models\fees\fees_circular\feesCircularMasterModel;
use App\Models\school_setup\SchoolModel;
use App\Models\school_setup\standardModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\sendSMS;

class admissionEnquiryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id=session()->get('marking_period_id');

        $data = DB::table('admission_enquiry')
            ->leftJoin('admission_form as af', function ($join) {
                $join->whereRaw('af.enquiry_id = admission_enquiry.id AND af.sub_institute_id = admission_enquiry.sub_institute_id');
            })->leftJoin('tblstudent', function ($join) use($marking_period_id) {
                $join->whereRaw('`tblstudent`.`admission_id` = `admission_enquiry`.`id`')->when($marking_period_id,function($query) use ($marking_period_id){
                    $query->where('tblstudent.marking_period_id',$marking_period_id);
                });
            })->leftJoin('standard', function ($join)use($marking_period_id) {
                $join->whereRaw('`standard`.`id` = `admission_enquiry`.`admission_standard`')->when($marking_period_id,function($query) use ($marking_period_id){
                    $query->where('standard.marking_period_id',$marking_period_id);
                });
            })->leftJoin('follow_up as fu', function ($join) {
                $join->whereRaw('fu.id = (SELECT id FROM follow_up AS fu1 WHERE fu1.enquiry_id = admission_enquiry.id ORDER BY fu1.id DESC LIMIT 1)');
            })
            ->selectRaw('admission_enquiry.*, CASE WHEN admission_enquiry.followup_date = DATE_FORMAT(NOW(),"%Y-%m-%d") THEN "#f5f777"
                WHEN fu.follow_up_date = DATE_FORMAT(NOW(),"%Y-%m-%d") THEN "#f5f777"
                END AS current_status_color,
                COUNT(tblstudent.id) AS total_student_count,standard.name as std_name,
                IF(fu.status = "close","1","0") as enquiry_status,fu.status as display_enquiry_status,
                if(fu.status = "close","pink","") as enq_color,DATE_FORMAT(fu.follow_up_date,"%d-%m-%Y") as next_follow_up_date,
                af.form_no as form_number,
                if(fu.follow_up_date = DATE_FORMAT(NOW(),"%Y-%m-%d"),"#0aa884","") as todays_next_followup ')
            ->where('admission_enquiry.sub_institute_id', $sub_institute_id)
            ->where('admission_enquiry.syear', $syear)
            ->groupBy('admission_enquiry.id')
            ->orderByRaw('admission_enquiry.followup_date = DATE_FORMAT(NOW(),"%Y-%m-%d") desc')
            ->get()->toArray();
        $data = json_decode(json_encode($data), true);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['data'] = $data;

        return is_mobile($type, 'admission/enquiry/show_admission_enquiry', $res, 'view');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');

        $category = castModel::get()->toArray();

        $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_enquiry"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1)')
            ->get();

        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = array();
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        $FORM_NO = $this->get_enquiry_no($sub_institute_id, $syear);

        $standard = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        // return $standard;exit;
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['enquiry_no'] = $FORM_NO;
        $res['standard'] = $standard;
        $res['custom_fields'] = $dataCustomFields;
        if (count($finalfieldsData) > 0) {
            $res['data_fields'] = $finalfieldsData;
        }

        if (count($category) > 0) {
            $res['category'] = $category;
        }

        return is_mobile($type, 'admission/enquiry/add_admission_enquiry', $res, 'view');
    }

    public function convert_number_to_words($number)
    {
        $hyphen = '-';
        $conjunction = ' and ';
        $separator = ', ';
        $negative = 'negative ';
        $decimal = ' point ';
        $dictionary = array(
            0                   => 'zero',
            1                   => 'one',
            2                   => 'two',
            3                   => 'three',
            4                   => 'four',
            5                   => 'five',
            6                   => 'six',
            7                   => 'seven',
            8                   => 'eight',
            9                   => 'nine',
            10                  => 'ten',
            11                  => 'eleven',
            12                  => 'twelve',
            13                  => 'thirteen',
            14                  => 'fourteen',
            15                  => 'fifteen',
            16                  => 'sixteen',
            17                  => 'seventeen',
            18                  => 'eighteen',
            19                  => 'nineteen',
            20                  => 'twenty',
            30                  => 'thirty',
            40                  => 'fourty',
            50                  => 'fifty',
            60                  => 'sixty',
            70                  => 'seventy',
            80                  => 'eighty',
            90                  => 'ninety',
            100                 => 'hundred',
            1000                => 'thousand',
            1000000             => 'million',
            1000000000          => 'billion',
            1000000000000       => 'trillion',
            1000000000000000    => 'quadrillion',
            1000000000000000000 => 'quintillion',
        );

        if (! is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
            // overflow
            trigger_error(
                'convert_number_to_words only accepts numbers between -'.PHP_INT_MAX.' and '.PHP_INT_MAX,
                E_USER_WARNING
            );

            return false;
        }

        if ($number < 0) {
            return $negative.$this->convert_number_to_words(abs($number));
        }

        $string = $fraction = null;

        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens = ((int) ($number / 10)) * 10;
                $units = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen.$dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds].' '.$dictionary[100];
                if ($remainder) {
                    $string .= $conjunction.$this->convert_number_to_words($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = $this->convert_number_to_words($numBaseUnits).' '.$dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= $this->convert_number_to_words($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = array();
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        // dd($request);
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $user_id = $request->session()->get("user_id");
        $syear = $request->session()->get("syear");
        $data = $request->except([
            '_method', '_token', 'submit', 'type', 'receipt_id', 'receipt_html', 'hidden_std_id', 'original_fees_bf',
        ]);

        $data['syear'] = $syear;
        $data['created_by'] = $user_id;
        $data['created_on'] = date('Y-m-d H:i:s');
        $data['sub_institute_id'] = $sub_institute_id;
        $data['enquiry_no'] = $this->get_enquiry_no($sub_institute_id, $syear);

        $standard = standardModel::where([
            'id' => $data['admission_standard'], 'sub_institute_id' => $sub_institute_id,
        ])->get()->toArray();
        $standard_name = $standard[0]['name'];

        if ($sub_institute_id == 198) // For Admission Registration Receipt (Maheshvari)
        {
            $result = DB::table('fees_receipt_book_master')
                ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
                ->where('syear', session()->get('syear'))
                ->where('sub_institute_id', session()->get('sub_institute_id'))
                ->groupByRaw('receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')
                ->get()->toArray();

            $RECEIPT_NO_result = DB::table('admission_enquiry')
                ->selectRaw('(IFNULL(MAX(CAST(receipt_id AS UNSIGNED)),1) + 1) as rid')
                ->where('SUB_INSTITUTE_ID', $sub_institute_id)->get()->toArray();
            $RECEIPT_NO = $RECEIPT_NO_result[0]->rid;

            $receipt_book_arr = array();
            foreach ($result as $temp_id => $receipt_detail) {
                $receipt_book_arr = $receipt_detail;
            }

            $image_path = "/storage/fees/".$receipt_book_arr->receipt_logo;
            $recHtml = '
                    <table class="fees-receipt" border-collapse="collapse" style="margin:0 auto;" width="100%">
                    <tbody>
                        <tr class="double-border">
                            <td class="logo-width" align="left">
                ';
            $recHtml .= '    <img class="logo" src="'.$image_path.'" alt="SCHOOL LOGO">';
            $recHtml .= '</td>';
            $recHtml .= '<td colspan="3" align="center">    ';
            if ($receipt_book_arr->receipt_line_1 != '') {
                $recHtml .= '   <span class="ma-hd">'.$receipt_book_arr->receipt_line_1.'</span><br>';
            }
            if ($receipt_book_arr->receipt_line_2 != '') {
                $recHtml .= '   <span class="sc-hd">'.$receipt_book_arr->receipt_line_2.'</span><br>';
            }
            if ($receipt_book_arr->receipt_line_3 != '') {
                $recHtml .= '   <span class="rg-hd">'.$receipt_book_arr->receipt_line_3.'</span><br>';
            }
            if ($receipt_book_arr->receipt_line_4 != '') {
                $recHtml .= '   <span class="rg-hd">'.$receipt_book_arr->receipt_line_4.'</span><br>';
            }
            $recHtml .= '</td>';
            $recHtml .= '</tr>';
            $recHtml .= '<tr>';
            $recHtml .= '<td class="mg-top" colspan="4" align="center" style="padding-bottom:20px;">';
            $recHtml .= '   <label class="receipt-hd">Admission Registration Receipt</label>';
            $recHtml .= '</td>';
            $recHtml .= '</tr>';

            $syear1 = session()->get('syear');
            $syear2 = $syear1 + 1;
            $edu_year = "$syear1-$syear2";

            $recHtml .= '<tr>';
            $recHtml .= '   <td align="left" colspan="2" style="white-space:nowrap;">';
            $recHtml .= '       Receipt No. : <label><b>'.$RECEIPT_NO.'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td align="right" colspan="2">';
            $recHtml .= '       Edu Year/Session : <label><b>'.$edu_year.'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td align="left" colspan="2">';
            $recHtml .= '       Name : <label><b>'.$data['first_name'].' '.$data['last_name'].'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td align="right" colspan="2">';
            $recHtml .= '       Date : <label><b>'.date("d-m-Y").'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td align="left" colspan="2">';
            $recHtml .= '       Std : <label><b>'.$standard_name.'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td align="right" colspan="2">';
            $recHtml .= '       Mobile : <label><b>'.$data['mobile'].'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recTotal = $data['admission_fees'];

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" valign="top">';
            $recHtml .= '       <table class="particulars" width="100%" border="0">';
            $recHtml .= '       <tr>';
            $recHtml .= '               <td colspan="3"><b>Description</b></td>';
            $recHtml .= '               <td style="white-space:nowrap;"><b>Received (Rs.)</b></td>  ';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3">Admission Registration Fees</td>';
            $recHtml .= '               <td align="right" >'.$data['admission_fees'].'</td>';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3"><b>Total</b></td>';
            $recHtml .= '               <td align="right" ><b>'.$recTotal.'</b></td>';
            $recHtml .= '           </tr>';
            $recHtml .= '       </table>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $total_amount_in_words = ucwords($this->convert_number_to_words($recTotal));
            if ($total_amount_in_words != "") {
                $total_amount_in_words_str = "Rupees ".$total_amount_in_words." Only";
            } else {
                $total_amount_in_words_str = "";
            }

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4">';
            $recHtml .= '       <label><b>In Words : </b></label>';
            $recHtml .= '       <span>'.$total_amount_in_words_str.'</span>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $payMethod = 'Cash';
            $REMARKS = "";
            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" class="padding">';
            $recHtml .= '       <label><b>Payment By : </b></label>';
            if ($payMethod == '') {
                $recHtml .= '       <span><u>'.$payMethod.'</u></span>';
            } else {
                $recHtml .= '       <span><u>'.$payMethod.'</u></span>';
            }
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $FEES_NOTE = "THIS IS A COMPUTER GENERATED RECEIPT.";
            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="3"><b>'.$FEES_NOTE.'</b></td>';
            $recHtml .= '   <td class="logo-width"><label style="text-align:center;">'.session()->get('name').'<br>Signature</label></td>';
            $recHtml .= '</tr>';

            $recHtml .= '</table><br>';
            $recHtml_for_insert = $recHtml;

            $data['receipt_id'] = $RECEIPT_NO;
            $data['receipt_html'] = $recHtml_for_insert;
        }

        if ($sub_institute_id == 201 || $sub_institute_id == 202 || $sub_institute_id == 203 || $sub_institute_id == 204) {
            $check_exist_no = DB::table('admission_enquiry')
                ->selectRaw('count(*) as total_no_exist')
                ->where('fees_circular_form_no', $request->get('fees_circular_form_no'))
                ->whereIn('sub_institute_id', [201, 202, 203, 204])->get()->toArray();

            if ($check_exist_no[0]->total_no_exist > 0) {
                $res['status_code'] = "0";
                $res['message'] = "Fees Circular Form No is already exists.";

                return is_mobile($type, "admission_enquiry.index", $res);
            } else {
                admissionEnquiryModel::insert($data);
                $last_inserted_id = DB::getPdo()->lastInsertId();
                if ($data['send_sms'] == 1) {
                    $response1 = sendSMS($data['mobile'], $data['sms_message'], $sub_institute_id);
                    if ($response1['error'] != 1) {
                        DB::table('sms_sent_parents')->insert([
                            'SYEAR'            => $syear,
                            'STUDENT_ID'       => '',
                            'SMS_TEXT'         => $data['sms_message'],
                            'SMS_NO'           => $data['mobile'],
                            'MODULE_NAME'      => 'Admission Enquiry',
                            'sub_institute_id' => $sub_institute_id,
                        ]);
                    }
                }
            }
        } else {
            admissionEnquiryModel::insert($data);
            $last_inserted_id = DB::getPdo()->lastInsertId();
            if ($data['send_sms'] == 1) {
                $response1 = sendSMS($data['mobile'], $data['sms_message'], $sub_institute_id);
                if ($response1['error'] != 1) {
                    DB::table('sms_sent_parents')->insert([
                        'SYEAR'            => $syear,
                        'STUDENT_ID'       => '',
                        'SMS_TEXT'         => $data['sms_message'],
                        'SMS_NO'           => $data['mobile'],
                        'MODULE_NAME'      => 'Admission Enquiry',
                        'sub_institute_id' => $sub_institute_id,
                    ]);
                }
            }
        }

        if ($sub_institute_id == 198) // For Admission Registration Receipt (Maheshvari)
        {
            $fees_config = DB::table('fees_receipt_css')
                ->select('css')->where('receipt_id', 'A5')->get()->toArray();

            $res['status_code'] = "1";
            $res['data'] = $data['receipt_html'];
            $res['css'] = $fees_config[0]->css;
            $res['paper'] = 'A5';
            $res['message'] = "Added successfully";

            return is_mobile($type, "admission/enquiry/receipt_view", $res, "view");

        } elseif ($sub_institute_id == 201 || $sub_institute_id == 202 || $sub_institute_id == 203 || $sub_institute_id == 204) // For Prior fees collection from Admission inquiry (hillshigh)
        {
            $whereArray = array();
            $whereArray['syear'] = $syear;
            $whereArray['sub_institute_id'] = $sub_institute_id;

            if ($data['admission_standard'] != '') {
                $whereArray['standard_id'] = $data['admission_standard'];
            }

            $feesCircularMaster = feesCircularMasterModel::where($whereArray)->get()->toArray();

            if (! isset($feesCircularMaster[0]['id'])) {
                $res['status_code'] = 0;
                $res['message'] = "Please enter fees circular master to view fees circular";

                return is_mobile($type, "admission_enquiry.index", $res);
            }

            if (count($feesCircularMaster) > 0) {
                $res['feesCircularMaster'] = $feesCircularMaster[0];
            }

            $get_term = DB::table("academic_year")
                ->where([
                    "sub_institute_id" => session()->get('sub_institute_id'),
                    "syear"            => session()->get('syear'),
                    "term_id"          => session()->get('term_id'),
                ])->get();

            $res['data'] = $data;
            $res['get_term_name'] = 'First Term';//$get_term[0]->title;
            $res['standard_name'] = $standard_name;
            $res['last_inserted_id'] = $last_inserted_id;
            $res['status_code'] = "1";
            $res['message'] = "Added successfully";

            return is_mobile($type, "admission/enquiry/show_circular", $res, "view");
        } else {

            $res['status_code'] = "1";
            $res['message'] = "Added successfully";

            return is_mobile($type, "admission_enquiry.index", $res);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $editData = admissionEnquiryModel::where(['id' => $id])->get()->toArray();

        $category = castModel::get()->toArray();

        $dataCustomFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_enquiry"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1)')
            ->get();

        $fieldsData = tblfields_dataModel::get()->toArray();
        $i = 0;
        $finalfieldsData = array();
        foreach ($fieldsData as $key => $value) {
            $finalfieldsData[$value['field_id']][$i]['display_text'] = $value['display_text'];
            $finalfieldsData[$value['field_id']][$i]['display_value'] = $value['display_value'];
            $i++;
        }

        $standard = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['status_code'] = "1";
        $res['message'] = "Successfully";
        $res['editData'] = $editData['0'];
        $res['standard'] = $standard;
        $res['custom_fields'] = $dataCustomFields;
        if (count($finalfieldsData) > 0) {
            $res['data_fields'] = $finalfieldsData;
        }
        if (count($category) > 0) {
            $res['category'] = $category;
        }

        return is_mobile($type, 'admission/enquiry/edit_admission_enquiry', $res, 'view');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $type = $request->input("type");
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $user_id = $request->session()->get("user_id");
        $syear = $request->session()->get("syear");
        $data = $request->except(['_method', '_token', 'submit']);

        $data['syear'] = $syear;
        $data['created_by'] = $user_id;
        $data['created_on'] = date('Y-m-d H:i:s');
        $data['sub_institute_id'] = $sub_institute_id;

        admissionEnquiryModel::where(['id' => $id])->update($data);

        $res['status_code'] = "1";
        $res['message'] = "Updated successfully";

        return is_mobile($type, "admission_enquiry.index", $res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        admissionEnquiryModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Deleted successfully";

        return is_mobile($type, "admission_enquiry.index", $res);
    }

    public function onlineEnquiry(Request $request, $id, $title)
    {
        $type = $request->input('type');

        $schoolData = SchoolModel::where(['Id' => $id])->get()->toArray();

        $res = $schoolData['0'];

        $res['status_code'] = "1";
        $res['message'] = "Successfully";
        $res['title'] = $title;

        return is_mobile($type, "online_admission.enquiry_form", $res, "view");
    }

    public function get_enquiry_no($sub_institute_id, $syear)
    {

        $GET_ENQUIRY_No = admissionEnquiryModel::select(DB::raw('cast(substring(max(enquiry_no),6)as int) as LAST_FORM_NO1,
            MAX(CAST(SUBSTRING(enquiry_no,6) AS INT)) as LAST_FORM_NO'))
            ->where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        $FORM_NO = $GET_ENQUIRY_No[0]['LAST_FORM_NO'] + 1;

        if ($sub_institute_id == 46) // MLZS ERP
        {
            if (strlen($FORM_NO) == 1) {
                $FORM_NO = "M".$syear."00".$FORM_NO;
            } else {
                if (strlen($FORM_NO) == 2) {
                    $FORM_NO = "M".$syear."0".$FORM_NO;
                } else {
                    if (strlen($FORM_NO) == 3) {
                        $FORM_NO = "M".$syear.$FORM_NO;
                    }
                }
            }
        } else {
            $FORM_NO = $syear."00".$FORM_NO;
        }

        return $FORM_NO;
    }

    public function ajax_getFeesBreakoff(Request $request)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $standard_id = $request->get('standard_id');

        $total_breakoff = 0;
        if (isset($standard_id) && $standard_id != '') {
            $FeesBreakoff_Data = DB::table('fees_breackoff as fb')
                ->join('standard as s', function ($join) {
                    $join->whereRaw('s.id = fb.standard_id AND s.grade_id = fb.grade_id AND s.sub_institute_id = fb.sub_institute_id');
                })->join('student_quota as sq', function ($join) {
                    $join->whereRaw('sq.id = fb.quota AND sq.sub_institute_id = fb.sub_institute_id');
                })
                ->selectRaw('SUM(amount) as total_breakoff,sq.title,fb.quota')
                ->where('fb.standard_id', $standard_id)
                ->where('fb.sub_institute_id', $sub_institute_id)
                ->where('fb.syear', $syear)
                ->where('fb.admission_year', $syear)
                ->where('s.id', $standard_id)
                ->where('fb.month_id', 'like', '%4202%')
                ->where('sq.title', 'like', '%General%')
                ->groupBy('fb.quota')->get()->toArray();

            if (count($FeesBreakoff_Data) > 0) {
                $total_breakoff = $FeesBreakoff_Data[0]->total_breakoff;
            }
        }

        return $total_breakoff;

    }

    public function ajax_listCalendarData(Request $request)
    {
        $syear = session()->get("syear");
        $sub_institute_id = session()->get("sub_institute_id");
        $followup_date = $request->input("followup_date");

        $check_calendar_data = DB::table('calendar_events')->where('school_date', $followup_date)
            ->where('sub_institute_id', $sub_institute_id)->selectRaw('COUNT(*) as total_rec')->get()->toArray();

        return $check_calendar_data[0]->total_rec;

    }
}
