<?php

namespace App\Http\Controllers\fees\imprest_fees_cancel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\fees\other_fees_collect\other_fees_collect_controller;
use App\Models\student\tblstudentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class imprestFeesCancelController extends Controller
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

        $fees_config = DB::table('fees_config_master as fc')
            ->join('fees_receipt_css as frc', function ($q) {
                $q->whereRaw('frc.receipt_id = fc.fees_receipt_template');
            })->selectRaw('fc.* ,frc.css')
            ->where('fc.sub_institute_id', $sub_institute_id)
            ->where('fc.syear', $syear)->get()->toArray();

        if (count($fees_config) > 0) {
            $receipt_css = $fees_config[0]->css;
            $paper_size = $fees_config[0]->fees_receipt_template;
        } else {
            $fees_config = DB::table('fees_receipt_css')->select(['css'])->where('receipt_id', 'A5')->get()->toArray();
            $receipt_css = $fees_config[0]->css;
            $paper_size = 'A5';
        }

        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['receipt_css_data'] = $receipt_css;
        $res['paper_size'] = $paper_size;

        return is_mobile($type, "fees/imprest_fees_cancel/index", $res , "view");
    }

    public function showImprestFees(Request $request)
    {
        // dd($request);
        $type = $request->input("type");
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $enrollment_no = $request->input('enrollment_no');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $receipt_no = $request->input('receipt_no');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');

        $other_extraSearchArray = array();
        $other_extraSearchArrayRaw = " fees_paid_other.is_deleted = 'N' ";
        if($grade != '')
        {
            $other_extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
        }

        if($standard != '')
        {
            $other_extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }

        if($division != '')
        {
            $other_extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        if($enrollment_no != '')
        {
            $other_extraSearchArray['tblstudent.enrollment_no'] = $enrollment_no;
        }

        if($receipt_no != '')
        {
            $other_extraSearchArray['fees_paid_other.reciept_id'] = $receipt_no;
        }

        if($from_date != '')
        {
            $other_extraSearchArrayRaw .= " AND date_format(fees_paid_other.created_date,'%Y-%m-%d') >= '".$from_date."'";
        }

        if($to_date != '')
        {
            $other_extraSearchArrayRaw .= "  AND date_format(fees_paid_other.created_date,'%Y-%m-%d') <= '".$to_date."'";
        }

        $other_extraSearchArray['fees_paid_other.syear'] = $syear;
        $other_extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $other_extraSearchArray['fees_paid_other.sub_institute_id'] = $sub_institute_id;

        $other_fees_paid = tblstudentModel::selectRaw("IF((SUM(fees_paid_other.actual_amountpaid) - SUM(IFNULL(fees_other_collection.deduction_amount,0))) < 0,'reamining_amt_minus','reamining_amt_plus') AS fees_type,fees_paid_other.id,fees_paid_other.reciept_id as receipt_no,fees_paid_other.paid_fees_html as fees_html,fees_paid_other.receiptdate,fees_paid_other.payment_mode,
            SUM(fees_paid_other.actual_amountpaid) as total_amount,fees_other_collection.deduction_head_id,fees_other_head.display_name, SUM(IFNULL(fees_other_collection.deduction_amount,0)) AS deduction_amount,
            (SUM(fees_paid_other.actual_amountpaid) - SUM(IFNULL(fees_other_collection.deduction_amount,0))) AS remaining_amount,
            CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,date_format(fees_paid_other.created_date,'%Y-%m-%d %H:%i:%s') as created_on,tblstudent.id as student_id,fees_paid_other.id as fees_paid_other_id")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', function ($join) use($marking_period_id){
                $join->on('standard.id', '=', 'tblstudent_enrollment.standard_id');
                // ->when($marking_period_id,function($query) use ($marking_period_id){
                //     $query->where('standard.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('fees_paid_other', 'fees_paid_other.student_id', '=', 'tblstudent.id')
            ->leftjoin('fees_other_collection', function ($join) {
                $join->on('fees_other_collection.student_id', '=', 'fees_paid_other.student_id')
                    ->on('fees_other_collection.sub_institute_id','=','fees_paid_other.sub_institute_id')
                    ->WHERE('fees_other_collection.is_deleted','N');
            })
            ->leftjoin('fees_other_head', function ($join) use ($syear) {
                $join->on('fees_other_head.id', '=', 'fees_other_collection.deduction_head_id')
                     ->on('fees_other_head.sub_institute_id','=','fees_paid_other.sub_institute_id')
                     ->WHERE('fees_other_head.include_imprest','Y')
                     ->WHERE('fees_other_head.syear','=',$syear);
            })
            ->where($other_extraSearchArray)
            ->whereRaw($other_extraSearchArrayRaw)
            ->groupby('fees_paid_other.reciept_id','fees_paid_other.student_id')
            ->get()
            ->toArray();


        if (count($other_fees_paid) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No Fees Receipt Found Please Search Again";
            return is_mobile($type, "imprest_fees_cancel.index", $res);
        }

        $feesCancelType = DB::table('fees_cancel_type')->pluck('title', 'id');

        $fees_config = DB::table('fees_config_master as fc')
            ->join('fees_receipt_css as frc', function ($q) {
                $q->whereRaw('frc.receipt_id = fc.fees_receipt_template');
            })->selectRaw('fc.* ,frc.css')
            ->where('fc.sub_institute_id', $sub_institute_id)
            ->where('fc.syear', $syear)->get()->toArray();


        if (count($fees_config) > 0) {
            $receipt_css = $fees_config[0]->css;
            $paper_size = $fees_config[0]->fees_receipt_template;
        } else {
            $fees_config = DB::table('fees_receipt_css')->select(['css'])->where('receipt_id', 'A5')->get()->toArray();
            $receipt_css = $fees_config[0]->css;
            $paper_size = 'A5';
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $other_fees_paid;
        $res['receipt_css_data'] = $receipt_css;
        $res['paper_size'] = $paper_size;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['receipt_no'] = $receipt_no;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['fees_cancel_type'] = $feesCancelType;

        return is_mobile($type, "fees/imprest_fees_cancel/index", $res , "view");
    }

    public function cancelImprestFees(Request $request)
    {

        $type = $request->input('type');
        $receipt_nos = $request->input('receipt_no');
        $cancel_type = $request->input('cancel_type');
        $cancel_remark = $request->input('cancel_remark');
        $cancel_amount = $request->input('cancel_amount');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $marking_period_id = session()->get('term_id');

        if($receipt_nos == '')
        {
            $res['status_code'] = 0;
            $res['message'] = "Please select receipt no to cancel fees";
            return is_mobile($type, "imprest_fees_cancel.index", $res);
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


        $all_inserted_id = '';

        foreach($receipt_nos as $fees_paid_other_id => $value)
        {
            $extraSearchArray['fees_paid_other.id'] = $fees_paid_other_id;
            $extraSearchArray['fees_paid_other.syear'] = $syear;
            $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
            $extraSearchArray['fees_paid_other.is_deleted'] = 'N';
            $extraSearchArray['fees_paid_other.sub_institute_id'] = $sub_institute_id;
            $extraSearchArray['fees_paid_other.reciept_id'] = $value;

            $feesDetails = DB::table('fees_paid_other')
                        ->selectRaw("fees_paid_other.*,SUM(fees_paid_other.actual_amountpaid) as total_amount,tblstudent_enrollment.standard_id")
                        ->join('tblstudent_enrollment', 'fees_paid_other.student_id', '=', 'tblstudent_enrollment.student_id')
                        ->where($extraSearchArray)
                        ->get()
                        ->toArray();

            $feesDetails = $feesDetails[0];

            $feesCancelLog = array();

            $sql = "SELECT *,GROUP_CONCAT(fees_head_id) heads
            FROM fees_receipt_book_master
            WHERE syear = '".$syear."'
            AND sub_institute_id = '".$sub_institute_id."'
            GROUP BY receipt_line_1,receipt_line_2,receipt_line_3,
            receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number";
            $sql = preg_replace('/\n+/', '', $sql);
            $result = DB::select($sql);

            $get_receipt_id = "SELECT IFNULL(MAX(CONVERT(SUBSTRING_INDEX(cancel_fees_receipt_id,'/',-1), UNSIGNED)),0) AS rid
                            FROM imprest_fees_cancel
                            WHERE sub_institute_id = '".$sub_institute_id."' AND syear = '".$syear."' ";

            $sql_receipt = preg_replace('/\n+/', '', $get_receipt_id);
            $RECEIPT_NO_result = DB::select($sql_receipt);
            $RECEIPT_NO = $syear.'/'.($RECEIPT_NO_result[0]->rid + 1);

            // $student_sql = "SELECT s.id,CONCAT_WS(' ',s.first_name,s.last_name) AS stu_name,
            //                 CONCAT_WS('/',st.name,d.name) AS std_name,s.enrollment_no,s.mobile
            //                 FROM tblstudent s
            //                 INNER JOIN tblstudent_enrollment se ON se.student_id = s.id AND s.sub_institute_id = se.sub_institute_id
            //                 INNER JOIN academic_section aa ON aa.id = se.grade_id
            //                 INNER JOIN standard st ON st.id = se.standard_id AND st.sub_institute_id = se.sub_institute_id
            //                 INNER JOIN division d ON d.id = se.section_id AND d.sub_institute_id = se.sub_institute_id
            //                 WHERE s.id = '" . $feesDetails->student_id . "' AND se.syear = '" . $syear . "' AND se.end_date IS NULL
            //                 AND s.sub_institute_id = '" . $sub_institute_id . "'";
            // $stu_data = DB::select($student_sql);
            $stu_data = DB::table('tblstudent as s')
            ->selectRaw('s.id, CONCAT_WS(" ", s.first_name, s.last_name) AS stu_name,
                CONCAT_WS("/", st.name, d.name) AS std_name, s.enrollment_no, s.mobile')
            ->join('tblstudent_enrollment as se', function ($join) use ($feesDetails, $syear, $sub_institute_id) {
                $join->on('se.student_id', '=', 's.id')
                    ->where('s.sub_institute_id', '=', $sub_institute_id)
                    ->where('s.id', '=', $feesDetails->student_id)
                    ->where('se.syear', '=', $syear)
                    ->whereNull('se.end_date');
            })
            ->join('academic_section as aa', 'aa.id', '=', 'se.grade_id')
            ->join('standard as st', function ($join) use($marking_period_id) {
                $join->on('st.id', '=', 'se.standard_id');
                    // ->when($marking_period_id, function($query) use($marking_period_id) {
                    //     $query->where('st.marking_period_id',$marking_period_id);
                    // });
            })
            ->join('division as d', function ($join) {
                $join->on('d.id', '=', 'se.section_id');
                        })
            ->where('s.sub_institute_id', '=', $sub_institute_id)
            ->get();
        // return $stu_data;exit;
            $receipt_book_arr = array();
            foreach ($result as $temp_id => $receipt_detail) {
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
            $recHtml .= '   <label class="receipt-hd">Imprest Fees Refund</label>';
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
            $recHtml .= '       Mobile : <label><b>' . $stu_data[0]->mobile . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" align="left">';
            $recHtml .= '       Std/Div. : <label><b>' . $stu_data[0]->std_name . '</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" valign="top">';
            $recHtml .= '       <table class="particulars" width="100%" border="0">';
            $recHtml .= '       <tr>';
            $recHtml .= '               <td colspan="3"><b>Description</b></td>';
            $recHtml .= '               <td style="white-space:nowrap;"><b>Received (Rs.)</b></td>  ';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3">Imprest Fees Refund</td>';
            $recHtml .= '               <td align="right" >'.$cancel_amount[$fees_paid_other_id].'</td>';
            $recHtml .= '           </tr>';
            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="left" colspan="3"><b>Total</b></td>';
            $recHtml .= '               <td align="right" ><b>'.$cancel_amount[$fees_paid_other_id].'</b></td>';
            $recHtml .= '           </tr>';
            $recHtml .= '       </table>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $other_fees_controller = new other_fees_collect_controller;

            $total_amount_in_words = ucwords($other_fees_controller->convert_number_to_words($cancel_amount[$fees_paid_other_id]));
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

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4" style="text-align:left !important;">';
            $recHtml .= '       <label><b>Cancel Remarks : </b></label>';
            $recHtml .= '       <span>' . $cancel_remark[$fees_paid_other_id] . '</span>';
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

            $feesCancelLog['fees_paid_other_id'] = $fees_paid_other_id;
            $feesCancelLog['reciept_id'] = $value;
            $feesCancelLog['syear'] = $syear;
            $feesCancelLog['sub_institute_id'] = $sub_institute_id;
            $feesCancelLog['student_id'] = $feesDetails->student_id;
            $feesCancelLog['standard_id'] = $feesDetails->standard_id;
            $feesCancelLog['term_id'] = $feesDetails->month_id;
            $feesCancelLog['amountpaid'] = $feesDetails->total_amount;
            $feesCancelLog['cancel_amount'] = $cancel_amount[$fees_paid_other_id];
            $feesCancelLog['received_date'] = $feesDetails->created_date;
            $feesCancelLog['cancel_date'] = date('Y-m-d H:i:s');
            $feesCancelLog['cancel_type'] = $cancel_type[$fees_paid_other_id];
            $feesCancelLog['cancel_remark'] = $cancel_remark[$fees_paid_other_id];
            $feesCancelLog['cancel_fees_receipt_id'] = $RECEIPT_NO;
            $feesCancelLog['cancel_fees_html'] = $style.$recHtml_for_insert;
            $feesCancelLog['cancelled_by'] = $user_id;
            $feesCancelLog['ip_address'] = $_SERVER['REMOTE_ADDR'];

            DB::table('imprest_fees_cancel')->insert($feesCancelLog);
            $last_inserted_id = DB::getPdo()->lastInsertId();

            $all_inserted_id .= $last_inserted_id.',';

            $new_html .= '<div class="row">'.$style.$recHtml_for_insert.'</div>
            <div class="pagebreak"></div> <br><br>';

            DB::table('fees_paid_other')
            ->where(['id' => $fees_paid_other_id,'reciept_id' => $value, 'syear' => $syear, 'sub_institute_id' => $sub_institute_id])
            ->update(['is_deleted' => 'Y','is_waved' => $cancel_type[$fees_paid_other_id]]);
        }
        $inserted_ids = rtrim($all_inserted_id,',');

        $res['status'] = "1";
        $res['str'] = $new_html;
        $res['last_inserted_ids'] = $inserted_ids;
        $res['message'] = "Imprest Fees Refunded Successfully";
        return is_mobile($type, "fees/imprest_fees_cancel/receipt_view", $res, "view");

        // $res['status_code'] = 1;
        // $res['message'] = "Imprest Fees Deleted Successfully";
        // return is_mobile($type, "imprest_fees_cancel.index", $res);
    }
}
