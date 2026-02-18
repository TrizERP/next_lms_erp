<?php

namespace App\Http\Controllers\fees\college_fees_collect;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;

class college_fees_collect_controller extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = array();
        $type = $request->input('type');

        return is_mobile($type, "fees/college_fees_collect/show", $school_data, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    public function show_student()
    {
        $responce_arr = [];

        $requestData = $_REQUEST;
        $marking_period_id = session()->get('term_id');

        $result = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = s.id');
            })->join('academic_section as g', function ($join) {
                $join->whereRaw('g.id = se.grade_id');
            })->join('standard as st', function ($join) use($marking_period_id){
                $join->whereRaw('st.id = se.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('st.marking_period_id',$marking_period_id);
                // });
            })->leftJoin('division as d', function ($join) {
                $join->whereRaw('d.id = se.section_id');
            })->selectRaw("s.*,se.syear,se.student_id,se.grade_id,se.standard_id,se.section_id,se.student_quota,se.start_date,
                se.end_date,se.enrollment_code,se.drop_code,se.drop_remarks,se.drop_remarks,se.term_id,se.remarks,se.admission_fees,
                se.house_id,se.lc_number,st.name standard_name,d.name as division_name")
            ->where('s.sub_institute_id', session()->get('sub_institute_id'))
            ->where('se.syear', session()->get('syear'))
            ->where(function ($q) use ($requestData, &$responce_arr, &$grade_val) {
                if (isset($requestData['mobile']) && $requestData['mobile'] != '') {
                    $q->where('s.mobile', $requestData['mobile']);
                    $responce_arr['mobile'] = $requestData['mobile'];
                }
                if (isset($requestData['grno']) && $requestData['grno'] != '') {
                    $q->where('se.enrollment_code', $requestData['grno']);
                    $responce_arr['grno'] = $requestData['grno'];
                }
                if (isset($requestData['grade']) && $requestData['grade'] != '') {
                    $grade_val = $requestData['grade'];
                    $q->where('se.grade_id', $requestData['grade']);
                }
                if (isset($requestData['standard']) && $requestData['standard'] != '') {
                    $q->where('se.standard_id', $requestData['standard']);
                    $responce_arr['standard'] = $requestData['standard'];
                }
                if (isset($requestData['division']) && $requestData['division'] != '') {
                    $q->where('se.section_id', $requestData['division']);
                    $responce_arr['division'] = $requestData['division'];
                }
                if (isset($requestData['stu_name']) && $requestData['stu_name'] != '') {
                    $q->where(function ($query) use ($requestData) {
                        $query->where('s.first_name', 'like', '%'.$requestData['stu_name'].'%')
                            ->orWhere('s.middle_name', 'like', '%'.$requestData['stu_name'].'%')
                            ->orWhere('s.last_name', 'like', '%'.$requestData['stu_name'].'%');
                    });
                    $responce_arr['stu_name'] = $requestData['stu_name'];
                }
            })->groupBy('s.id')->get()->toArray();

        $responce_arr['stu_data'] = $result;

        $type = $_REQUEST['type'] ?? "";

        return is_mobile($type, "fees/college_fees_collect/show", $responce_arr, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function store(Request $request)
    {
        $receipt_number = $this->gunrate_receipt_number();

        $ret_heds_with_id = DB::table('fees_title')->selectRaw('id,fees_title')
            ->where('SUB_INSTITUTE_ID', session()->get('sub_institute_id'))
            ->where('syear', session()->get('syear'))->orderBy('sort_order')->get()->toArray();

        $heds_with_id = [];

        foreach ($ret_heds_with_id as $id => $arr) {
            $heds_with_id[$arr->fees_title] = $arr->id;
        }

        $insert_arr = [];

        foreach ($receipt_number as $sort_order => $heads_arr) {
            $all_heads = explode(',', $heads_arr['heds']);
            $temp_id = 0;
            $total_amount = 0;
            foreach ($heds_with_id as $head => $key) {
                if (in_array($key, $all_heads)) {
                    $insert_arr[$temp_id]['sort_order'] = $sort_order;
                    $insert_arr[$temp_id]['receipt_no'] = $heads_arr['rid'];
                    $insert_arr[$temp_id]['fees_data'][$head] = $_REQUEST['fees_data'][$head];
                    $total_amount += $_REQUEST['fees_data'][$head];
                    unset($heds_with_id[$head]);
                }
            }

            $insert_arr[$temp_id]['amount'] = $total_amount;
            // if total amount is zero then we dont need to insert that
            if ($total_amount == 0) {
                unset($insert_arr[$temp_id]);
            } else {
                $receipt_number[$sort_order]['used'] = 1;
            }
        }

        foreach ($insert_arr as $r_id => $vals) {
            $receipt_id = $vals['receipt_no'];
            $insert_arrs = [
                'student_id'       => $_REQUEST['student_id'],
                'term_id'          => "",
                'syear'            => session()->get('syear'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'payment_mode'     => $_REQUEST['PAYMENT_MODE'],
                'created_date'     => date('Y-m-d'),
                'bank_branch'      => $_REQUEST['bank_branch'],
                'receiptdate'      => date('Y-m-d'),
                'cheque_no'        => $_REQUEST['cheque_no'],
                'cheque_date'      => $_REQUEST['cheque_date'],
                'cheque_bank_name' => $_REQUEST['bank_name'],
                'receipt_no'       => $receipt_id,
                'remarks'          => $_REQUEST['remarks'],
                'created_by'       => session()->get('user_id'),
                'amount'           => $vals['amount'],
            ];

            $insert_arrs = array_merge($insert_arrs, $vals['fees_data']);
            $insert_id = DB::table('fees_collect')->insertGetId($insert_arrs);

            $regular_insert_arr[] = $insert_id;
        }

        $fees_receipt_insert = array();
        foreach ($receipt_number as $id => $arr) {
            if (isset($arr['used'])) {
                $fees_receipt_insert['RECEIPT_ID_'.$id] = $arr['rid'];
            }
        }
        $fees_receipt_insert['FEES_ID'] = implode(',', $regular_insert_arr);
        $fees_receipt_insert['SUB_INSTITUTE_ID'] = session()->get('sub_institute_id');
        $fees_receipt_insert['STANDARD'] = $_REQUEST['standard_id'];
        $fees_receipt_insert['CREATED_ON'] = date('Y-m-d');

        $insert_id = DB::table('fees_receipt')->insertGetId($fees_receipt_insert);

        $ret_heds_with_id = DB::table('fees_title')->selectRaw('id,fees_title')
            ->where('SUB_INSTITUTE_ID', session()->get('sub_institute_id'))
            ->where('syear', session()->get('syear'))->orderBy('sort_order')->get()->toArray();

        $heds_with_id = [];
        foreach ($ret_heds_with_id as $id => $arr) {
            $heds_with_id[$arr->fees_title] = $arr->id;
        }
        $receipt_html = $this->gunrate_receipt($insert_id, $receipt_number, $heds_with_id);

        $fees_config = DB::table('fees_receipt_css')->select('css')
            ->where('receipt_id', 'A5')->get()->toArray();

        $res = [
            "data"  => $receipt_html,
            "paper" => "A5",
            "css"   => $fees_config[0]->css,
        ];

        $type = $request->input('type');

        return is_mobile($type, "fees/college_fees_collect/receipt_view", $res, "view");
    }

    public function add_discount($fees_arr, $insert_table)
    {
        $discount_field = "";
        $total_field = "";

        if ($insert_table == "college_fees_collect") {
            $discount_field = "fees_discount";
            $total_field = "amount";
        } else {
            $discount_field = "fees_discount";
            $total_field = "actual_amountpaid";
        }

        foreach ($fees_arr as $month_id => $detail_arr) {
            foreach ($detail_arr as $receipt_id => $arr) {
                $sum = array_sum($arr);
                if ($sum == 0) {
                    unset($fees_arr[$month_id][$receipt_id]);
                }
            }
            if (count($fees_arr[$month_id]) == 0) {
                unset($fees_arr[$month_id]);
            }
        }

        foreach ($fees_arr as $month_id => $detail_arr) {
            foreach ($detail_arr as $receipt_id => $arr) {
                $fees_arr[$month_id][$receipt_id][$discount_field] = 0;
                foreach ($arr as $title => $val) {
                    if (isset($_REQUEST['discount_data'][$title])) {
                        $dis = 0;

                        if ($val > $_REQUEST['discount_data'][$title] || $val == $_REQUEST['discount_data'][$title]) {
                            $dis = $_REQUEST['discount_data'][$title];
                            $_REQUEST['discount_data'][$title] = 0;
                            unset($_REQUEST['discount_data'][$title]);
                        } else {
                            $dis = $val;
                            $_REQUEST['discount_data'][$title] = $_REQUEST['discount_data'][$title] - $val;
                        }
                        $fees_arr[$month_id][$receipt_id][$discount_field] += $dis;
                    }
                }
            }
        }

        foreach ($fees_arr as $month_id => $detail_arr) {
            foreach ($detail_arr as $receipt_id => $arr) {
                $sum = 0;
                foreach ($arr as $id => $val) {
                    if ($id != $discount_field) {
                        $sum += $val;
                    } else {
                        $sum -= $val;
                    }
                }
                $fees_arr[$month_id][$receipt_id][$total_field] = $sum;
            }
        }

        return $fees_arr;
    }

    public function add_fine($fees_arr)
    {
        $discount_field = "";
        $total_field = "";

        foreach ($_REQUEST['fine_data'] as $id => $val) {
            if ($val == 0) {
                unset($_REQUEST['fine_data'][$id]);
            }
        }

        if (count($_REQUEST['fine_data']) > 0) {
            foreach ($fees_arr as $month_id => $detail_arr) {
                foreach ($detail_arr as $receipt_id => $arr) {
                    $fees_arr[$month_id][$receipt_id]['fine'] = 0;
                    foreach ($arr as $title => $val) {
                        if (isset($_REQUEST['fine_data'][$title])) {
                            $fin = $_REQUEST['fine_data'][$title];
                            if (! isset($fees_arr[$month_id][$receipt_id]['fine'])) {
                                $fees_arr[$month_id][$receipt_id]['fine'] = 0;
                            }
                            $fees_arr[$month_id][$receipt_id]['fine'] += $fin;
                            unset($_REQUEST['fine_data'][$title]);
                        }
                    }
                }
            }
        }

        return $fees_arr;
    }

    public function gunrate_receipt_number()
    {
        $result = DB::table('fees_receipt_book_master')->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
            ->where('grade_id', $_REQUEST['grade_id'])
            ->where('standard_id', $_REQUEST['standard_id'])
            ->where('syear', session()->get('syear'))
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->groupBy('receipt_id')->get()->toArray();

        $id_arr = [];

        foreach ($result as $id => $arr) {
            $result_id = DB::table('fees_receipt')->selectRaw('ifnull(max(cast(RECEIPT_ID_".$arr->sort_order." as UNSIGNED)),0) rid')
                ->where('STANDARD', $arr->standard_id)
                ->where('SUB_INSTITUTE_ID', session()->get('sub_institute_id'))
                ->get()->toArray();

            $id_arr[$arr->sort_order]['heds'] = $arr->heads;
            if ($result_id[0]->rid == 0) {
                $id_arr[$arr->sort_order]['rid'] = $arr->last_receipt_number;
            } else {
                $id_arr[$arr->sort_order]['rid'] = $result_id[0]->rid + 1;
            }
        }

        return $id_arr;
    }

    public function gunrate_receipt($receipt_id, $receipt_arr, $id_heads)
    {
        $fees_paid = DB::table('fees_collect as fc')
            ->join('fees_receipt as fr', function ($join) {
                $join->whereRaw('find_in_set(fc.id,fr.FEES_ID)');
            })->selectRaw('fc.*')->where('fr.id', $receipt_id)->get()->toArray();

        $ret_heds_with_id = DB::table('fees_title')
            ->where('SUB_INSTITUTE_ID', session()->get('sub_institute_id'))
            ->where('syear', session()->get('syear'))->orderBy('sort_order')->get()->toArray();

        $other_fees_heads = [];
        $reg_fees_heads = [];

        foreach ($ret_heds_with_id as $id => $arr) {
            $reg_fees_heads[] = $arr;
        }

        $fees_arr = [];
        $insert_html_ids = [];

        foreach ($receipt_arr as $sort_order => $arr) {
            $heads_arr = explode(',', $arr['heds']);
            $insert_html_ids[$sort_order] = array();
            foreach ($heads_arr as $id => $head_id) {
                $head = "REG";

                if ($head == "REG") {
                    $head_name = "";
                    foreach ($id_heads as $ids => $val) {
                        if ($val == $head_id) {
                            $head_name = $ids;
                        }
                    }
                    // echo $head_name;
                    if ($head_name != "") {
                        $total = 0;
                        foreach ($fees_paid as $ids => $arrs) {
                            if ($arrs->$head_name != null && $arrs->$head_name != '' && $arrs->$head_name != 0) {
                                echo "asd";
                                if (isset($insert_html_ids[$sort_order]['REG'])) {
                                    if (! in_array($arrs->id, $insert_html_ids[$sort_order]['REG'])) {
                                        $insert_html_ids[$sort_order]['REG'][] = $arrs->id;
                                    }
                                } else {
                                    $insert_html_ids[$sort_order]['REG'][] = $arrs->id;
                                }
                            }
                            $total += $arrs->$head_name;
                        }
                    }
                    // finding display name
                    $diplay_name = "";
                    foreach ($reg_fees_heads as $ids => $arrs) {
                        if ($head_id == $arrs->id) {
                            $diplay_name = $arrs->display_name;
                        }
                    }

                    $fees_arr[$arr['rid']."_".$sort_order][$diplay_name] = $total;
                }
            }
        }

        foreach ($insert_html_ids as $sort_order => $arr) {
            $total_discount = 0;
            $total_fine = 0;
            foreach ($arr as $key => $detai_arr) {
                if ($key == 'REG') {
                    $paid_result = DB::table('tblstudent as s')
                        ->join('fees_collect as fc', function ($join) {
                            $join->whereRaw("(fc.student_id = s.id AND fc.sub_institute_id = '".session()->get('sub_institute_id')."')");
                        })->selectRaw("SUM(fc.fees_discount) amount,SUM(fc.fine) fine_amount")
                        ->where('s.sub_institute_id', session()->get('sub_institute_id'))
                        ->whereIn('fc.id', $detai_arr)->get()->toArray();

                    $total_discount += $paid_result[0]->amount;
                    $total_fine += $paid_result[0]->fine_amount;
                } else {
                    $paid_result = DB::table('tblstudent as s')
                        ->join('fees_paid_other as fpo', function ($join) {
                            $join->whereRaw("fpo.student_id = s.id");
                        })->selectRaw("SUM(fpo.fees_discount) amount,SUM(fpo.fine) fine_amount")
                        ->where('s.sub_institute_id', session()->get('sub_institute_id'))
                        ->whereIn('fc.id', $detai_arr)->get()->toArray();
                    $total_discount += $paid_result[0]->amount;
                    $total_fine += $paid_result[0]->fine_amount;
                }
            }

            foreach ($fees_arr as $sort_order_id => $arr) {
                $order_id = explode('_', $sort_order_id);
                if ($order_id[1] == $sort_order) {
                    $fees_arr[$sort_order_id]['Fine'] = $total_fine;

                    $fees_arr[$sort_order_id]['Discount'] = $total_discount;
                }
            }
        }

        //removing all balnk array
        $new_fees_arr = [];
        foreach ($fees_arr as $id => $arr) {
            foreach ($arr as $head_id => $amount) {
                if ($amount != 0) {
                    $new_fees_arr[$id][$head_id] = $amount;
                }
            }
        }

        foreach ($new_fees_arr as $id => $arr) {
            if (count($arr) == 0) {
                unset($new_fees_arr[$id]);
            }
        }

        $fees_arr = $new_fees_arr;


        $result = DB::table('fees_receipt_book_master')
            ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
            ->where('grade_id', $_REQUEST['grade_id'])
            ->where('standard_id', $_REQUEST['standard_id'])
            ->where('syear', session()->get('syear'))
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->groupBy('receipt_line_1,receipt_line_2,receipt_line_3,
                receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')->get()->toArray();

        $final_html = "";

        foreach ($fees_arr as $id => $arr) {
            $id_arr = explode('_', $id);
            $RECEIPT_NO = $id_arr[0];
            $sort_order = $id_arr[1];

            $receipt_book_arr = [];
            foreach ($result as $temp_id => $receipt_detail) {
                if ($sort_order == $receipt_detail->sort_order) {
                    $receipt_book_arr = $receipt_detail;
                }
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
            $recHtml .= '<td colspan="3" align="center">	';
            if ($receipt_book_arr->receipt_line_1 != '') {
                $recHtml .= '	<span class="sc-hd">'.$receipt_book_arr->receipt_line_1.'</span><br>';
            }
            if ($receipt_book_arr->receipt_line_2 != '') {
                $recHtml .= '	<span class="ma-hd">'.$receipt_book_arr->receipt_line_2.'</span><br>';
            }
            if ($receipt_book_arr->receipt_line_3 != '') {
                $recHtml .= '	<span class="rg-hd">'.$receipt_book_arr->receipt_line_3.'</span><br>';
            }
            if ($receipt_book_arr->receipt_line_4 != '') {
                $recHtml .= '	<span class="rg-hd">'.$receipt_book_arr->receipt_line_4.'</span><br>';
            }
            $recHtml .= '</td>';
            $recHtml .= '</tr>';
            $recHtml .= '<tr>';
            $recHtml .= '<td class="mg-top" colspan="4" align="center" style="padding-bottom:20px;">';
            $recHtml .= '	<label class="receipt-hd">Fee Receipt</label>';
            $recHtml .= '</td>';
            $recHtml .= '</tr>';

            $syear1 = session()->get('syear');
            $syear2 = $syear1 + 1;
            $edu_year = "$syear1-$syear2";

            $recHtml .= '<tr>';
            $recHtml .= '   <td align="left">';
            $recHtml .= '       Edu Year : <label><b>'.$edu_year.'</b></label>';
            $recHtml .= '	</td>';
            $recHtml .= '	<td>&nbsp;</td>';
            $recHtml .= '	<td align="right" colspan="2">';
            $recHtml .= '       Receipt No. : <label><b>'.$RECEIPT_NO.'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td align="left" colspan="2" style="white-space:nowrap;">';
            $recHtml .= '       Std/Div. : <label><b>'.$_REQUEST['std_div'].'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '   <td align="right" colspan="2">';
            $recHtml .= '       Date : <label><b>'.date("d-m-Y", strtotime($_REQUEST['receiptdate'])).'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="4">';
            $recHtml .= '       <b>Name : <label><b>'.$_REQUEST['full_name'].'</b></label>';
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';


            $recHtml .= '<tr>';
            $recHtml .= '	<td colspan="4" valign="top">';
            $recHtml .= '       <table class="particulars" width="100%" border="0">';
            $recHtml .= '		<tr>';
            $recHtml .= '               <td colspan="3"><b>Fee Description</b></td>';
            $recHtml .= '               <td style="white-space:nowrap;"><b>Received (Rs.)</b></td>	';
            $recHtml .= '           </tr>';

            $rwspan = count($fees_arr);
            $recTotal = 0;

            foreach ($arr as $key => $pval) {
                if ($key == 'Discount') {
                    $recTotal = $recTotal - $pval;
                } else {
                    $recTotal = $recTotal + $pval;
                }
            }

            foreach ($arr as $pkey => $pval) {
                $recHtml .= '           <tr>';
                $recHtml .= '               <td colspan="3" align="left">'.$pkey.'</td>'; //&nbsp;(' . $TERM_SHORT_NAME . ')
                $recHtml .= '               <td align="right">'.$pval.'</td>'; //&nbsp;(' . $TERM_SHORT_NAME . ')
                $recHtml .= '           </tr>';
            }

            $recHtml .= '           <tr>';
            $recHtml .= '               <td align="right" colspan="3">Total</td>';
            $recHtml .= '               <td align="right" >'.$recTotal.'</td>';
            $recHtml .= '           </tr>';
            $recHtml .= '       </table>';
            $recHtml .= '	</td>';
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

            $payMethod = $_REQUEST['PAYMENT_MODE'];
            $REMARKS = "";
            $recHtml .= '<tr>';
            if ($REMARKS != '' && $REMARKS != '-') {
                $recHtml .= '   <td colspan="4">';
            } else {
                $recHtml .= '   <td colspan="4" class="padding">';
            }

            $recHtml .= '       <label><b>Payment By : </b></label>';
            if ($payMethod == '') {
                $recHtml .= '       <span><u>'.$payMethod.'</u></span>';
            } else {
                $recHtml .= '       <span><u>'.$payMethod.'</u> '.strtoupper($_REQUEST['bank_name']).' - '.$_REQUEST['cheque_no'].'</span>';
            }
            $recHtml .= '   </td>';
            $recHtml .= '</tr>';

            if ($REMARKS != '' && $REMARKS != '-') {
                $recHtml .= '<tr>';
                $recHtml .= '   <td colspan="4" class="padding">';
                $recHtml .= '   <label><b>Remarks : </b></label><span>'.$REMARKS.'</span>';
                $recHtml .= '   </td>';
                $recHtml .= '</tr>';
            }


            $FEES_NOTE = "THIS IS COMPUTER GENERATED RECEIPT.";
            $recHtml .= '<tr>';
            $recHtml .= '   <td colspan="3"><b>'.$FEES_NOTE.'</b></td>';
            $recHtml .= '   <td class="logo-width"><label style="text-align:center;">Signature <br></label></td>';
            $recHtml .= '</tr>';

            $recHtml .= '</table><br>';
            $sArr = ['"', "'"];
            $rArr = ['\"', "\'"];

            foreach ($insert_html_ids as $sort_order_id => $other_reg) {
                if ($sort_order == $sort_order_id) {
                    foreach ($other_reg as $identifiyer => $vals) {
                        if ($identifiyer == "OTHER") {
                            DB::table('fees_paid_other')
                                ->where('id', $vals)
                                ->update(['paid_fees_html' => str_replace($sArr, $rArr, $recHtml)]);
                        } else {
                            DB::table('fees_collect')
                                ->where('id', $vals)
                                ->update(['fees_html' => str_replace($sArr, $rArr, $recHtml)]);
                        }
                    }
                }
            }
            $final_html .= $recHtml;
        }

        return $final_html;
    }

    public function convert_number_to_words($number)
    {
        $hyphen = '-';
        $conjunction = ' and ';
        $separator = ', ';
        $negative = 'negative ';
        $decimal = ' point ';
        $dictionary = [
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
        ];

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
            $words = [];
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }

    public function get_receipt_id()
    {
        $result = DB::table('college_fees_collect')
            ->selectRaw("ifnull(max(receipt_no),1)+1 maxid")
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->get()->toArray();

        return $result[0]->maxid;

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
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function edit($id, Request $request)
    {
        $stu_arr = [
            "0" => $id,
        ];
        $request->session()->put('stu_arr', $stu_arr);

        $student_id = $id;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $marking_period_id = session()->get('term_id');

        $stu_result = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = s.id');
            })->join('academic_section as g', function ($join) {
                $join->whereRaw('g.id = se.grade_id');
            })->join('standard as st', function ($join) use($marking_period_id){
                $join->whereRaw('st.id = se.standard_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('st.marking_period_id',$marking_period_id);
                // });
            })->leftJoin('division as d', function ($join) {
                $join->whereRaw('d.id = se.section_id');
            })->selectRaw("s.*,se.syear,se.student_id,se.grade_id,se.standard_id,se.section_id,se.student_quota,se.start_date, 
                se.drop_remarks,se.term_id,se.remarks,se.admission_fees,se.house_id,se.lc_number,st.name standard_name,
                d.name as division_name")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('se.syear', $syear)
            ->where('s.id', $student_id)->groupBy('s.id')->get()->toArray();

        $head_result = DB::table('fees_title')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereRaw("id in (select fees_head_id from fees_receipt_book_master where sub_institute_id = $sub_institute_id 
                and grade_id = '".$stu_result[0]->grade_id."' and standard_id = '".$stu_result[0]->standard_id."')")
            ->orderBy('sort_order')->get()->toArray();

        $all_heads = [];
        foreach ($head_result as $id => $arr) {
            $all_heads[$arr->fees_title] = $arr->display_name;
        }

        $stu_detail = [
            "student_id" => $stu_result[0]->student_id,
            "enrollment" => $stu_result[0]->enrollment_code,
            "name"       => $stu_result[0]->first_name." ".$stu_result[0]->middle_name." ".$stu_result[0]->last_name,
            "stddiv"     => $stu_result[0]->standard_name."/".$stu_result[0]->division_name,
            "admission"  => $stu_result[0]->admission_year,
            "email"      => $stu_result[0]->email,
            "mobile"     => $stu_result[0]->mobile,
            "std_id"     => $stu_result[0]->standard_id,
            "grade_id"   => $stu_result[0]->grade_id,
            "div_id"     => $stu_result[0]->section_id,
        ];

        $type = "web";
        $res['stu_data'] = $stu_detail;
        $res['all_head'] = $all_heads;

        return is_mobile($type, "fees/college_fees_collect/college_fees_collect", $res, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }
}
