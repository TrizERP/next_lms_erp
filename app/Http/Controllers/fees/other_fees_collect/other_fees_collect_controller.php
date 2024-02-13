<?php

namespace App\Http\Controllers\fees\other_fees_collect;

use App\Http\Controllers\Controller;
use App\Models\fees\bank_master\bankmasterModel;
use App\Models\fees\other_fees_collect\other_fees_collect;
use App\Models\fees\other_fees_title\other_fees_title;
use App\Models\student\tblstudentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class other_fees_collect_controller extends Controller
{
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
        $res['status'] = 1;
        $res['message'] = "Success";

        $other_fees_title = other_fees_title::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'status' => '1'])->get()->toArray();
        $res['other_fees_title'] = $other_fees_title;

        return is_mobile($type, "fees/other_fees_collect/show_other_fees_collect", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $enrollment_no = $request->input('enrollment_no');
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $mobile_no = $request->input('mobile_no');
        $uniqueid = $request->input('uniqueid');
        $other_fees_title_selected = $request->input('other_fees_title');
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');

        $extraSearchArray = array();
        $extraSearchArrayRaw = " 1=1 ";

        if ($grade != '') {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
        }

        if ($standard != '') {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }

        if ($division != '') {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        if ($enrollment_no != '') {
            $extraSearchArray['tblstudent.enrollment_no'] = $enrollment_no;
        }

        if ($mobile_no != '') {
            $extraSearchArray['tblstudent.mobile'] = $mobile_no;
        }

        if ($uniqueid != '') {
            $extraSearchArray['tblstudent.uniqueid'] = $uniqueid;
        }

        if ($first_name != '') {
            $extraSearchArrayRaw .= "  AND tblstudent.first_name like '%" . $first_name . "%' ";
        }

        if ($last_name != '') {
            $extraSearchArrayRaw .= "  AND tblstudent.last_name like '%" . $last_name . "%' ";
        }
        $extraSearchArrayRaw .= "  AND tblstudent_enrollment.end_date IS NULL ";
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['student_quota.sub_institute_id'] = $sub_institute_id;

        $studentData = tblstudentModel::selectRaw("tblstudent.id AS student_id,CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,tblstudent.mobile,tblstudent.uniqueid,student_quota.title as stu_quota,
            IFNULL(SUM(fees_other_collection.deduction_amount),0) as paid_amt,(fees_other_head.amount - IFNULL(SUM(fees_other_collection.deduction_amount),0)) AS remaining_amt,fees_other_head.amount,fees_other_head.display_name,fees_other_collection.deduction_head_id")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            /*,fees_paid_other.is_deleted AS fees_paid_other_is_deleted*/
            ->leftjoin('fees_other_head', 'fees_other_head.id', '=', DB::raw("'" . $other_fees_title_selected . "'"))
            ->leftjoin('fees_other_collection', function ($join) use ($other_fees_title_selected) {
                $join->on('fees_other_collection.student_id', '=', 'tblstudent.id')
                    ->on('fees_other_collection.deduction_head_id', '=', DB::raw("'" . $other_fees_title_selected . "'"))
                    ->on('fees_other_collection.is_deleted', '=', DB::raw("'N'"));
            })
            /*->join('fees_paid_other', function ($join) use ($other_fees_title_selected) {
                $join->on('fees_paid_other.student_id', '=', 'tblstudent.id')
                    ->on('fees_paid_other.sub_institute_id', '=', 'tblstudent.sub_institute_id')
                    ->on('fees_paid_other.is_deleted', '=', DB::raw("'N'"));
            })*/
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', function ($join) use($marking_period_id) {
                $join->on('standard.id', '=', 'tblstudent_enrollment.standard_id');
                    // ->when($marking_period_id, function($query) use($marking_period_id) {
                    //     $query->where('standard.marking_period_id',$marking_period_id);
                    // });
            })         
             ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('student_quota', 'student_quota.id', '=', 'tblstudent_enrollment.student_quota')
            ->where($extraSearchArray)
            ->whereRaw($extraSearchArrayRaw)
            ->groupby('tblstudent.id')
            ->get()
            ->toArray();

        $other_fees_title = other_fees_title::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'status' => '1'])->get()->toArray();

        $get_amount_of_head = other_fees_title::where(['id' => $other_fees_title_selected, 'sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $studentData;
        $res['other_fees_title_selected'] = $other_fees_title_selected;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['first_name'] = $first_name;
        $res['last_name'] = $last_name;
        $res['mobile_no'] = $mobile_no;
        $res['uniqueid'] = $uniqueid;
        $res['get_amount_of_head'] = $get_amount_of_head[0]['amount'];
        $res['get_name_of_head'] = $get_amount_of_head[0]['display_name'];
        $res['other_fees_title'] = $other_fees_title;
        $res['bank_data'] = bankmasterModel::get()->toArray();

        return is_mobile($type, "fees/other_fees_collect/show_other_fees_collect", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->get('type');
        $students = $request->get('students');
        $division_id = $request->get('division_id');
        $standard_id = $request->get('standard_id');
        $other_fees_title_name = $request->get('other_fees_title_name');
        $deduction_head_id = $request->get('other_fees_title');
        $deduction_date = $request->get('deduction_date');
        $payment_mode = $request->get('payment_mode');
        $remarks = $request->get('remarks');
        $bank_name = $request->get('bank_name');
        $bank_branch = $request->get('bank_branch');
        $cheque_no = $request->get('cheque_no');
        $cheque_date = $request->input('cheque_date');
        $amount_of_deduction = $request->input('amount_of_deduction');
        $created_by = session()->get('user_id');
        $created_ip = $_SERVER['REMOTE_ADDR'];

        $new_html = '';

        $style = '<style type="text/css">
        	body {
        		background: #ffffff;
    		}
		    table.fees-receipt {
                border-collapse: inherit !important;
            }
            .fees-receipt {
                border: 1px solid #888;
                height: 510px;
                overflow: hidden
            }
            .particulars {
                border-collapse: collapse !important;
            }
            .particulars td {
                border: 1px solid #888;
                border-collapse: inherit !important;
            }
            .fees-receipt td {
                font-family: Arial, Helvetica, sans-serif !important;
                padding: 6px 8px;
                font-size: 13px
            }
            .fees-receipt img.logo {
                width: 100px;
                height: 90px;
                margin: 0
            }
            .double-border {
                border-bottom: 1px double #000;
                border-width: 3px;
            }
            .particulars {
                overflow: hidden;
                display: block;
                vertical-align: top
            }
            .particulars td {
                width: 100%;
                height: 20px;
                font-size: 12px
            }
            .mg-top {
                top: 10px;
                position: relative
            }
            .mg-top label {
                border-radius: 3px;
                font-weight: 700;
                font-size: 14px;
                top: 5px;
                position: relative
            }
            .receipt-hd {
                border: 1px solid #000;
                padding: 5px 15px;
                margin-top: 15px
            }
            .sc-hd {
                font-size: 26px;
                font-weight: 700;
                font-family: Arial, Helvetica, sans-serif !important
            }
            .ma-hd {
                font-size: 18px;
                font-weight: 700;
                font-family: Arial, Helvetica, sans-serif !important
            }
            .rg-hd {
                font-size: 14px;
                font-weight: 600;
                font-family: Arial, Helvetica, sans-serif !important
            }
            .padding {
                padding-bottom: 20px !important
            }
            .logo-width {
                width: 165px;
                text-align: center
            }
            br {
                display: block;
            }
		</style>';


        $all_inserted_id = '';
        foreach ($students as $key => $student_id) {
            $result = DB::table('fees_receipt_book_master')
                ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
                ->where('syear', session()->get('syear'))
                ->where('sub_institute_id', session()->get('sub_institute_id'))
                ->groupByRaw('receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')
                ->get()->toArray();

            $RECEIPT_NO_result = DB::table('fees_other_collection')
                ->selectRaw("IFNULL(MAX(CONVERT(SUBSTRING_INDEX(receipt_id,'/',-1), UNSIGNED)),0) as rid")
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)->get()->toArray();
            $RECEIPT_NO = $syear . '/' . ($RECEIPT_NO_result[0]->rid + 1);

            $stu_data = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = s.id AND s.sub_institute_id = se.sub_institute_id');
                })->join('academic_section as aa', function ($join) {
                    $join->whereRaw('aa.id = se.grade_id');
                })->join('standard as st', function ($join) {
                    $join->whereRaw('st.id = se.standard_id AND st.sub_institute_id = se.sub_institute_id');
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id AND d.sub_institute_id = se.sub_institute_id');
                })->selectRaw("s.id,CONCAT_WS(' ',s.first_name,s.last_name) AS stu_name,
                    CONCAT_WS('/',st.name,d.name) AS std_name,s.enrollment_no,s.mobile")
                ->where('s.id', $student_id)
                ->where('se.syear', $syear)
                ->whereNull('se.end_date')
                ->where('s.sub_institute_id', $sub_institute_id)->get()->toArray();

            $receipt_book_arr = array();
            foreach ($result as $temp_id => $receipt_detail) {
                $receipt_book_arr = $receipt_detail;
            }

            $image_path = "http://" . $_SERVER['HTTP_HOST'] . "/storage/fees/" . $receipt_book_arr->receipt_logo;
            $recHtml = '
                    <br><br><table class="fees-receipt" style="margin:0 auto;" width="80%">
                    <tbody>
      					<tr class="double-border">
         					<td class="logo-width" align="left">';

            $recHtml .= '    <img class="logo" src="' . $image_path . '" alt="SCHOOL LOGO">';
            $recHtml .= '</td>';
            $recHtml .= '<td colspan="3" style="text-align:center !important;" align="center"> ';
            if ($receipt_book_arr->receipt_line_1 != '') {
                $recHtml .= '<span class="sc-hd">' . $receipt_book_arr->receipt_line_1 . '</span><br>';
            }
            if ($receipt_book_arr->receipt_line_2 != '') {
                $recHtml .= '<span class="ma-hd">' . $receipt_book_arr->receipt_line_2 . '</span><br>';
            }
            if ($receipt_book_arr->receipt_line_3 != '') {
                $recHtml .= '<span class="rg-hd">' . $receipt_book_arr->receipt_line_3 . '</span><br>';
            }
            if ($receipt_book_arr->receipt_line_4 != '') {
                $recHtml .= '<span class="rg-hd">' . $receipt_book_arr->receipt_line_4 . '</span><br>';
            }
            $recHtml .= '</td>';
            $recHtml .= '</tr>';
            $recHtml .= '<tr>';
            $recHtml .= '<td class="mg-top" colspan="4" style="padding-bottom:20px;text-align:center !important;border-top: 2px double black !important;padding-top: 5px;" align="center">';
            $recHtml .= '   <label class="receipt-hd">' . $other_fees_title_name . '</label>';
            $recHtml .= '</td>';
            $recHtml .= '</tr>';

            $syear1 = session()->get('syear');
            $syear2 = $syear1 + 1;
            $edu_year = "$syear1-$syear2";

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="2" style="white-space:nowrap;" align="left">';
            $recHtml .= '       Receipt No. : <label><b>' . $RECEIPT_NO . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td colspan="2" align="right">';
            $recHtml .= '       Academic Year : <label><b>' . $edu_year . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="2" align="left">';
            $recHtml .= '       Gr.No. : <label><b>' . $stu_data[0]->enrollment_no . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td colspan="2" align="right">';
            $recHtml .= '       Date : <label><b>' . $deduction_date . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="3" align="left">';
            $recHtml .= '       Name : <label><b>' . $stu_data[0]->stu_name . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td colspan="2" align="right">';
            $recHtml .= '       Mobile : <label><b>' . $stu_data[0]->mobile . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" align="left">';
            $recHtml .= '       Std/Div. : <label><b>' . $stu_data[0]->std_name . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recTotal = $amount_of_deduction[$student_id];

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" valign="top">';
            $recHtml .= '       <table class="particulars" width="100%" border="0">';
            $recHtml .= '       <tr>';
            $recHtml .= '               <td colspan="3"><b>Description</b></td>';
            $recHtml .= '               <td style="white-space:nowrap;"><b>Received (Rs.)</b></td>  ';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3">' . $other_fees_title_name . '</td>';
            $recHtml .= '               <td align="right" >' . $amount_of_deduction[$student_id] . '</td>';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3"><b>Total</b></td>';
            $recHtml .= '               <td align="right" ><b>' . $recTotal . '</b></td>';
            $recHtml .= '           </tr>';
            $recHtml .= '       </table>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $total_amount_in_words = ucwords($this->convert_number_to_words($recTotal));
            if ($total_amount_in_words != "") {
                $total_amount_in_words_str = "Rupees " . $total_amount_in_words . " Only";
            } else {
                $total_amount_in_words_str = "";
            }

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" style="text-align:left !important;">';
            $recHtml .= '       <label><b>In Words : </b></label>';
            $recHtml .= '       <span>' . $total_amount_in_words_str . '</span>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $payMethod = $payment_mode;
            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" class="padding" style="text-align:left !important;"><p><label><b>Payment By : </b></label>    <span><u>';
            $recHtml .= '       <label><b>Payment By : </b></label>';
            if ($payMethod == 'Cash') {
                $recHtml .= '       <span><u>' . $payMethod . '</u></span>';
            } else {
                $recHtml .= '       <span><u>' . $payment_mode . ' - ' . strtoupper($bank_name) . ' - ' . $cheque_no . '</u></span>';
            }
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $FEES_NOTE = "THIS IS A COMPUTER GENERATED RECEIPT.";
            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="3"><b>' . $FEES_NOTE . '</b></td>';
            $recHtml .= '   <td class="logo-width"><label style="text-align:center;">' . session()->get('name') . '<br>Signature</label></td>';
            $recHtml .= '</tr>';

            $recHtml .= '</table>';
            // $sArr = array('"', "'");
            // $rArr = array('\"', "\'");
            // $recHtml_for_insert = str_replace($sArr, $rArr, $recHtml);
            $recHtml_for_insert = $recHtml;

            $otherFeesArray = array(
                'receipt_id' => $RECEIPT_NO,
                'syear' => $syear,
                'sub_institute_id' => $sub_institute_id,
                'student_id' => $student_id,
                'deduction_date' => $deduction_date,
                'deduction_head_id' => $deduction_head_id,
                'deduction_remarks' => $remarks,
                'deduction_amount' => $amount_of_deduction[$student_id],
                'payment_mode' => $payment_mode,
                'bank_name' => $bank_name,
                'bank_branch' => $bank_branch,
                'cheque_dd_no' => $cheque_no,
                'cheque_dd_date' => $cheque_date,
                'paid_fees_html' => $style . $recHtml_for_insert,
                'created_by' => $created_by,
                'created_on' => now(),
                'created_ip' => $created_ip
            );
            other_fees_collect::insert($otherFeesArray);
            $last_inserted_id = DB::getPdo()->lastInsertId();

            $all_inserted_id .= $last_inserted_id . ',';

            $new_html .= '<div class="row">' . $style . $recHtml_for_insert . '</div>
            <div class="pagebreak"></div> <br><br>';
        }
        $inserted_ids = rtrim($all_inserted_id, ',');

        $res['status'] = "1";
        $res['str'] = $new_html;
        $res['last_inserted_ids'] = $inserted_ids;
        $res['message'] = "Other fees collect successfully";
        return is_mobile($type, "fees/other_fees_collect/receipt_view", $res, "view");
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

    public function convert_number_to_words($number)
    {
        $hyphen = '-';
        $conjunction = ' and ';
        $separator = ', ';
        $negative = 'negative ';
        $decimal = ' point ';
        $dictionary = array(
            0 => 'zero',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
            20 => 'twenty',
            30 => 'thirty',
            40 => 'fourty',
            50 => 'fifty',
            60 => 'sixty',
            70 => 'seventy',
            80 => 'eighty',
            90 => 'ninety',
            100 => 'hundred',
            1000 => 'thousand',
            1000000 => 'million',
            1000000000 => 'billion',
            1000000000000 => 'trillion',
            1000000000000000 => 'quadrillion',
            1000000000000000000 => 'quintillion'
        );

        if (!is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int)$number < 0) || (int)$number < 0 - PHP_INT_MAX) {
            // overflow
            trigger_error(
                'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
                E_USER_WARNING
            );
            return false;
        }

        if ($number < 0) {
            return $negative . $this->convert_number_to_words(abs($number));
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
                $tens = ((int)($number / 10)) * 10;
                $units = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= $conjunction . $this->convert_number_to_words($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int)($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = $this->convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= $this->convert_number_to_words($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = array();
            foreach (str_split((string)$fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }

}
