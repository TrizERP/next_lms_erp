<?php

namespace App\Http\Controllers\fees\fees_cancel;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\FeeMonthId;
use function App\Helpers\FeeBreakoffHeadWise;
use function App\Helpers\FeeBreackoff;
use App\Models\fees\feesReceiptBookMasterModel;
use App\Models\fees\tblfeesConfigModel;
use App\Models\student\tblstudentModel;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use App\Models\fees\bank_master\bankmasterModel;
use App\Http\Controllers\fees\other_fees_collect\other_fees_collect_controller;

class feesRefundController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $sql = "SELECT fc.* ,frc.css
                FROM  fees_config_master fc
                INNER JOIN fees_receipt_css frc ON frc.receipt_id = fc.fees_receipt_template
                WHERE fc.sub_institute_id = '".$sub_institute_id."' AND fc.syear = '".$syear."' ";
        $sql = preg_replace('/\n+/', '', $sql);
        $fees_config = DB::select($sql);

        if (count($fees_config) > 0)
        {
            $receipt_css = $fees_config[0]->css;
            $paper_size = $fees_config[0]->fees_receipt_template;
        } 
        else 
        {
            $sql = "SELECT frc.css FROM fees_receipt_css frc WHERE frc.receipt_id = 'A5' ";
            $sql = preg_replace('/\n+/', '', $sql);
            $fees_config = DB::select($sql);
            $receipt_css = $fees_config[0]->css;
            $paper_size = 'A5';
        }

        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['receipt_css_data'] = $receipt_css;
        $res['paper_size'] = $paper_size;

        return is_mobile($type, "fees/fees_cancel/fees_refund", $res , "view");
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Request $request)
    {
        // echo '<pre>'; print_r($_REQUEST); exit; 
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $fees_controller = new fees_collect_controller;

        $getBk = $fees_controller->getBk($request, $id);

        $fees_title = $getBk['final_fee_name'];

        $fees_paid_data = DB::SELECT("SELECT fc.*,s.enrollment_no
                                    FROM tblstudent s
                                    INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id AND se.syear = '".$syear."'
                                    INNER JOIN fees_collect fc ON fc.student_id = s.id AND fc.sub_institute_id = s.sub_institute_id AND fc.syear = '".$syear."'
                                    WHERE s.id = '".$id."' AND s.sub_institute_id = '".$sub_institute_id."' ");

        $PAID_DATA = json_decode(json_encode($fees_paid_data),true);

        $paid_data_title_wise = array();
        foreach ($PAID_DATA as $key => $val) 
        {
            foreach ($fees_title as $fees_title_name => $fees_title_id) 
            {
              $paid_data_title_wise[$fees_title_id] =  $val[$fees_title_id].'/'.$fees_title_name;
            }
        }
        $res['stu_data'] = $getBk['stu_data'];
        $res['paid_data_title_wise'] = $paid_data_title_wise;
        $res['bank_data'] = bankmasterModel::get()->toArray();
        $res['fees_config_data'] = tblfeesConfigModel::where(['sub_institute_id' => $sub_institute_id,'syear' => $syear])->get()->toArray();
        // dd($res);
        if(count($res['fees_config_data']) > 0)
        {
            $res['fees_config_data'] = $res['fees_config_data'][0];
            $type = "web";
            return is_mobile($type, "fees/fees_cancel/fees_refund_add", $res, "view");
        }else{
            $type = "web";
            $res = array(
                    "status_code" => 0,
                    "message" => "Fees config master setting is missing",
                );
             return is_mobile($type, "fees_refund.index", $res, "redirect");
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function showFees(Request $request)
    {      
        // dd($request); 
        $type = $request->input("type");
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $enrollment_no = $request->input('enrollment_no');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $extraSearchArray = $other_extraSearchArray = array();
        $other_extraSearchArrayRaw = " 1 = 1 ";
        $extraSearchArrayRaw = " fees_collect.is_deleted = 'N' ";

        if($grade != '')
        {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
            $other_extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
        }
    
        if($standard != '')
        {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
            $other_extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }

        if($division != '')
        {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
            $other_extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        if($enrollment_no != '')
        {
            $extraSearchArray['tblstudent.enrollment_no'] = $enrollment_no;
            $other_extraSearchArray['tblstudent.enrollment_no'] = $enrollment_no;
        }

        if($from_date != '')
        {
            $extraSearchArrayRaw .= "  AND date_format(fees_collect.created_date,'%Y-%m-%d') >= '".$from_date."'";
            $other_extraSearchArrayRaw .= " AND date_format(fees_paid_other.created_date,'%Y-%m-%d') >= '".$from_date."'";
        }

        if($to_date != '')
        {
            $extraSearchArrayRaw .= "  AND date_format(fees_collect.created_date,'%Y-%m-%d') <= '".$to_date."'";
            $other_extraSearchArrayRaw .= "  AND date_format(fees_paid_other.created_date,'%Y-%m-%d') <= '".$to_date."'";
        }

        $extraSearchArray['fees_collect.syear'] = $syear;
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;        
        $extraSearchArray['fees_collect.sub_institute_id'] = $sub_institute_id;

        $other_extraSearchArray['fees_paid_other.syear'] = $syear;
        $other_extraSearchArray['tblstudent_enrollment.syear'] = $syear;        
        $other_extraSearchArray['fees_paid_other.sub_institute_id'] = $sub_institute_id;

        $other_fees_paid = $feesData = tblstudentModel::selectRaw("CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,date_format(fees_paid_other.created_date,'%Y-%m-%d %H:%i:%s') as created_on,tblstudent.id as student_id")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('fees_paid_other', 'fees_paid_other.student_id', '=', 'tblstudent.id')
            ->where($other_extraSearchArray)
            ->whereRaw($other_extraSearchArrayRaw)
            ->groupby('tblstudent.id');

        $feesData = tblstudentModel::selectRaw("CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,date_format(fees_collect.created_date,'%Y-%m-%d %H:%i:%s') as created_on,tblstudent.id as student_id")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', 'standard.id', '=', 'tblstudent_enrollment.standard_id')
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('fees_collect', 'fees_collect.student_id', '=', 'tblstudent.id')
            ->where($extraSearchArray)
            ->whereRaw($extraSearchArrayRaw)
            ->groupby('tblstudent.id')
            ->union($other_fees_paid)
            ->get()
            ->toArray();

        if(count($feesData) == 0)
        {
            $res['status_code'] = 0;
            $res['message'] = "No Fees Receipt Found Please Search Again";
            return is_mobile($type, "fees_cancel.index", $res);
        }

        $sql = "SELECT fc.* ,frc.css
                FROM  fees_config_master fc
                INNER JOIN fees_receipt_css frc ON frc.receipt_id = fc.fees_receipt_template
                WHERE fc.sub_institute_id = '".$sub_institute_id."' AND fc.syear = '".$syear."' ";
        $sql = preg_replace('/\n+/', '', $sql);
        $fees_config = DB::select($sql);

        if (count($fees_config) > 0)
        {
            $receipt_css = $fees_config[0]->css;
            $paper_size = $fees_config[0]->fees_receipt_template;
        } 
        else 
        {
            $sql = "SELECT frc.css FROM fees_receipt_css frc WHERE frc.receipt_id = 'A5' ";
            $sql = preg_replace('/\n+/', '', $sql);
            $fees_config = DB::select($sql);
            $receipt_css = $fees_config[0]->css;
            $paper_size = 'A5';
        }
            
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $feesData;
        $res['receipt_css_data'] = $receipt_css;
        $res['paper_size'] = $paper_size;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        // dd($res);
        return is_mobile($type, "fees/fees_cancel/fees_refund", $res , "view");
    }

    public function saveFeesRefund(Request $request)
    {
        // dd($request);
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $refund_amount = $request->input('refund_amount');
        $grade_id = $request->input('grade_id');
        $standard_id = $request->input('standard_id');
        $div_id = $request->input('div_id');
        $student_id = $request->input('student_id');
        $enrollment = $request->input('enrollment');
        $payment_mode = $request->input('PAYMENT_MODE');
        $receiptdate = $request->input('receiptdate');
        $cheque_date = $request->input('cheque_date');
        $cheque_no = $request->input('cheque_no');
        $bank_name = $request->input('bank_name');
        $bank_branch = $request->input('bank_branch');
        $refund_remark = $request->input('refund_remark');
        
        $fees_controller = new fees_collect_controller;
        $getBk = $fees_controller->getBk($request, $student_id);

        $fees_title = $getBk['final_fee_name'];

        if(count($refund_amount) < 0)
        {
            $res['status_code'] = 0;
            $res['message'] = "Please enter amount for refund fees.";
            return is_mobile($type, "fees_refund.index", $res);
        }

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

        $sql = "SELECT *,GROUP_CONCAT(fees_head_id) heads
            FROM fees_receipt_book_master
            WHERE syear = '".$syear."'
            AND sub_institute_id = '".$sub_institute_id."'
            GROUP BY receipt_line_1,receipt_line_2,receipt_line_3,
            receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number";
            $sql = preg_replace('/\n+/', '', $sql);
            $result = DB::select($sql);
            
            $get_receipt_id = "SELECT IFNULL(MAX(CONVERT(SUBSTRING_INDEX(receipt_no,'/',-1), UNSIGNED)),0) AS rid
                            FROM fees_refund
                            WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$syear."' ";
                      
            $sql_receipt = preg_replace('/\n+/', '', $get_receipt_id);
            $RECEIPT_NO_result = DB::select($sql_receipt);
            $RECEIPT_NO = $syear.'/'.($RECEIPT_NO_result[0]->rid + 1);

            $student_sql = "SELECT s.id,CONCAT_WS(' ',s.first_name,s.last_name) AS stu_name,
                            CONCAT_WS('/',st.name,d.name) AS std_name,s.enrollment_no,s.mobile
                            FROM tblstudent s 
                            INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND s.sub_institute_id = se.sub_institute_id
                            INNER JOIN academic_section aa ON aa.id = se.grade_id
                            INNER JOIN standard st ON st.id = se.standard_id AND st.sub_institute_id = se.sub_institute_id
                            INNER JOIN division d ON d.id = se.section_id AND d.sub_institute_id = se.sub_institute_id
                            WHERE s.id = '".$student_id."' AND se.syear = '".$syear."' AND se.end_date IS NULL 
                            AND s.sub_institute_id = '".$sub_institute_id."'";
            $stu_data = DB::select($student_sql);               

            $receipt_book_arr = array();
            foreach ($result as $temp_id => $receipt_detail) 
            {
                $receipt_book_arr = $receipt_detail;
            }

            $image_path = "http://" . $_SERVER['HTTP_HOST']."/storage/fees/" . $receipt_book_arr->receipt_logo;
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
            $recHtml .= '   <label class="receipt-hd">Fees Refund</label>';
            $recHtml .= '</td>';
            $recHtml .= '</tr>';

            $syear2 = $syear + 1;
            $edu_year = "$syear-$syear2";

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
            $recHtml .= '       Gr.No. : <label><b>' .$stu_data[0]->enrollment_no. '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td colspan="2" align="right">';
            $recHtml .= '       Date : <label><b>'.date('d-m-Y').'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="3" align="left">';
            $recHtml .= '       Name : <label><b>'.$stu_data[0]->stu_name.'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td colspan="2" align="right">';
            $recHtml .= '       Mobile : <label><b>' .$stu_data[0]->mobile. '</b></label>';
            $recHtml .= '   </td>';            
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" align="left">';
            $recHtml .= '       Std/Div. : <label><b>'.$stu_data[0]->std_name.'</b></label>';
            $recHtml .= '   </td>';          
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" valign="top">';
            $recHtml .= '       <table class="particulars" width="100%" border="0">';
            $recHtml .= '       <tr>';
            $recHtml .= '               <td colspan="3"><b>Description</b></td>';
            $recHtml .= '               <td style="white-space:nowrap;"><b>Received (Rs.)</b></td>  ';
            $recHtml .= '           </tr>';

            $total_refund_amt = 0;
            foreach ($fees_title as $fees_title_name => $fees_title_id) 
            {
                // $feesRefundLog[$fees_title_id] = $refund_amount[$fees_title_id];
                $recHtml .= '           <tr>';
                $recHtml .= '               <td align="left" colspan="3">'.$fees_title_name.'</td>';
                $recHtml .= '               <td align="right" >'.$refund_amount[$fees_title_id].'</td>';
                $recHtml .= '           </tr>';
                $total_refund_amt = $total_refund_amt + $refund_amount[$fees_title_id];

            }
            
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3"><b>Total</b></td>';
            $recHtml .= '               <td align="right" ><b>'.$total_refund_amt.'</b></td>';
            $recHtml .= '           </tr>';
            $recHtml .= '       </table>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $other_fees_controller = new other_fees_collect_controller;

            $total_amount_in_words = ucwords($other_fees_controller->convert_number_to_words($total_refund_amt));
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

            $FEES_NOTE = "THIS IS A COMPUTER GENERATED RECEIPT.";
            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="3"><b>' . $FEES_NOTE . '</b></td>';
            $recHtml .= '   <td class="logo-width"><label style="text-align:center;">' . session()->get('name') . '<br>Signature</label></td>';
            $recHtml .= '</tr>';

            $recHtml .= '</table>';
            $recHtml_for_insert = $recHtml;

        $feesRefundLog = array();
        $feesRefundLog['receipt_no'] = $RECEIPT_NO;
        $feesRefundLog['syear'] = $syear;
        $feesRefundLog['sub_institute_id'] = $sub_institute_id;
        $feesRefundLog['student_id'] = $student_id;
        $feesRefundLog['fees_html'] = $style.$recHtml_for_insert;
        $feesRefundLog['payment_mode'] = $payment_mode;
        $feesRefundLog['receipt_date'] = $receiptdate;
        $feesRefundLog['cheque_date'] = $cheque_date;
        $feesRefundLog['cheque_no'] = $cheque_no;
        $feesRefundLog['bank_name'] = $bank_name;
        $feesRefundLog['bank_branch'] = $bank_branch;
        $feesRefundLog['refund_remarks'] = $refund_remarks;

        foreach ($fees_title as $fees_title_name => $fees_title_id) 
        {
            $feesRefundLog[$fees_title_id] = $refund_amount[$fees_title_id];
        }

        $feesRefundLog['amount'] = $total_refund_amt;
        $feesRefundLog['created_date'] = date('Y-m-d h:i:s');
        $feesRefundLog['created_by'] = $user_id;
        $feesRefundLog['created_ip_address'] = $_SERVER['REMOTE_ADDR'];

        DB::table('fees_refund')->insert($feesRefundLog);
        $last_inserted_id = DB::getPdo()->lastInsertId();

        $new_html .= '<div class="row">'.$style.$recHtml_for_insert.'</div>
        <div class="pagebreak"></div> <br><br>';
        
        $res['status_code'] = 1;
        $res['str'] = $new_html;
        $res['paper'] = 'A5';
        $res['receipt_id_html'] = $last_inserted_id;
        $res['student_id'] = $student_id;
        $res['message'] = "Fees Refund Successfully";
        return is_mobile($type, "fees/fees_cancel/receipt_view", $res, "view");
    }
}
