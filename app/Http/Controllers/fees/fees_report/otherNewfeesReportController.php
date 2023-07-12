<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use App\Models\fees\other_fees_title\other_fees_title;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\getStudents;
use function App\Helpers\is_mobile;

class otherNewfeesReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    public function index(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = "1";
        $res['message'] = "Success";
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $feesOtherHead_data = other_fees_title::select("*")
            ->where(["sub_institute_id" => $sub_institute_id])
            ->where("status", '=', '1')
            ->get()
            ->toArray();

        $res['feesOtherHead_data'] = $feesOtherHead_data;

        return is_mobile($type, "fees/fees_report/show_otherNew_fees_report", $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input("type");
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $otherfeeshead = $request->input('otherfeeshead');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id=session()->get('term_id');

        $extraSearch = " 1 = 1";

        if ($grade != '') {
            $extraSearch .= " AND se.grade_id = '" . $grade . "'";
        }

        if ($standard != '') {
            $extraSearch .= " AND se.standard_id = '" . $standard . "'";
        }

        if ($division != '') {
            $extraSearch .= " AND se.section_id = '" . $division . "'";
        }

        if ($from_date != '' && $to_date != '') {
            $extraSearch .= " AND c.deduction_date between '" . $from_date . "' AND '" . $to_date . "' ";
        }

        if ($otherfeeshead != '') {
            $extraSearch .= " AND deduction_head_id = '" . $otherfeeshead . "'";
        }

        $other_feesData = DB::table('fees_other_collection as c')
            ->join('fees_other_head as h', function ($join) {
                $join->whereRaw('c.deduction_head_id = h.id');
            })->join('tblstudent as s', function ($join) {
                $join->whereRaw('s.id = c.student_id AND c.sub_institute_id = s.sub_institute_id');
            })->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = s.id AND se.syear = c.syear AND se.end_date is null');
            })->join('standard as st', function ($join) use($marking_period_id){
                $join->whereRaw('st.id = se.standard_id')->when($marking_period_id,function($query) use($marking_period_id){
                    $query->where('st.marking_period_id',$marking_period_id);
                });
            })->join('division as d', function ($join) {
                $join->whereRaw('se.section_id = d.id');
            })->selectRaw("CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,s.enrollment_no,
                s.mobile,c.student_id,st.name as standard_name,d.name as division_name,h.display_name as fees_head,
                h.amount AS total_amt, c.deduction_amount,c.deduction_remarks,c.deduction_date,c.payment_mode,c.receipt_id,c.id,c.student_id")
            ->where('c.sub_institute_id', $sub_institute_id)
            ->where('c.syear', $syear)
            ->where('c.is_deleted', '=', 'N')
            ->whereRaw($extraSearch)
            ->orderBy('c.deduction_date')
            ->get()->toArray();

        $other_feesData = json_decode(json_encode($other_feesData), true);

        $other_fee_title = other_fees_title::select("*")
            ->where(["sub_institute_id" => $sub_institute_id])
            ->where("status", '=', '1')
            ->get()
            ->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['other_feesData'] = $other_feesData;
        $res['feesOtherHead_data'] = $other_fee_title;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['otherfeeshead'] = $otherfeeshead;

        return is_mobile($type, "fees/fees_report/show_otherNew_fees_report", $res, "view");
    }

    public function ajax_ledgerData(Request $request)
    {
        // dd($request);
        $student_id = $request->get("student_id");
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');

        $get_imprest_head = DB::select('SELECT fees_title,display_name FROM fees_title WHERE sub_institute_id = "' . $sub_institute_id . '" AND syear = "' . $syear . '" AND display_name LIKE "%Imprest%" AND other_fee_id != 0  ');    //"%Imprest Head%"
        $other_fees_title_id = $get_imprest_head[0]->fees_title;
        $other_fees_title = $get_imprest_head[0]->display_name;

        $sql = DB::select('SELECT fp.receiptdate AS tran_date,fp.reciept_id as receipt_id,
                    "' . $other_fees_title . '" AS particluars,
                    fp.' . $other_fees_title_id . ' AS credit_amt,"" AS debit_amt,"" AS balance
                    FROM fees_paid_other fp
                    WHERE fp.sub_institute_id = "' . $sub_institute_id . '" AND fp.student_id = "' . $student_id . '" AND fp.syear = "' . $syear . '"
                    UNION
                    SELECT fc.deduction_date AS tran_date,fc.receipt_id,
                    fh.display_name AS particluars,
                    "" AS credit_amt,fc.deduction_amount AS debit_amt,"" AS balance
                    FROM fees_other_collection fc
                    INNER JOIN fees_other_head fh ON fh.id = fc.deduction_head_id AND fh.include_imprest = "Y"
                    WHERE fc.sub_institute_id = "' . $sub_institute_id . '" AND fc.student_id = "' . $student_id . '" AND fc.syear = "' . $syear . '" ');

        $data = json_decode(json_encode($sql), true);
        $stu_arry = array($student_id);
        $student_data = getStudents($stu_arry);

        $new_html = '<div class="card">';
        $new_html .= ' <div class="col-md-4">';
        $new_html .= '<label style="display: block !important;"><b>Student Name : </b>' . $student_data[$student_id]['student_full_name'];
        $new_html .= '<br style="display: block !important;"></label>';
        $new_html .= '<label><b>Std/Div : </b>' . $student_data[$student_id]['standard_name'] . ' / ' . $student_data[$student_id]['division_name'] . '&nbsp;&nbsp;&nbsp;&nbsp;';
        $new_html .= '<label><b>Enrollment No.: </b>' . $student_data[$student_id]['enrollment_no'];
        $new_html .= '<br style="display: block !important;"></label>';
        $new_html .= '<label><b>Mobile No.: </b>' . $student_data[$student_id]['mobile'];
        $new_html .= '<br style="display: block !important;"></label>';
        $new_html .= '</div>';
        $new_html .= '</div>';
        $new_html .= '<br style="display: block !important;">';
        $new_html .= '<center><h4>Imprest Ledger Report</h4></center>';

        $new_html .= '<div class="table-responsive">
                        <table id="example" border="1px" style="width: 100%;border-collapse: collapse !important;width: 100% !important;" cellspacing="0" cellpadding="0" >
                            <thead>
                                <tr style="background: black;">
                                    <th style="color: white;">Sr No.</th>
                                    <th style="color: white;">Tansaction Date</th>
                                    <th style="color: white;">Receipt No.</th>
                                    <th style="color: white;">Particluars</th>
                                    <th style="color: white;">Credit</th>
                                    <th style="color: white;">Debit</th>
                                    <th style="color: white;">Balance</th>
                                </tr>
                            </thead>
                            <tbody>';


        $total_credit_amt = $total_debit_amt = $balance_amt = 0;
        $i = 1;
        foreach ($data as $key => $value) {
            $tran_date = isset($value['tran_date']) ? $value['tran_date'] : '-';
            $receipt_id = isset($value['receipt_id']) ? $value['receipt_id'] : '-';
            $particluars = isset($value['particluars']) ? $value['particluars'] : '-';
            $credit_amt = !empty($value['credit_amt']) ? $value['credit_amt'] : '0';
            $debit_amt = !empty($value['debit_amt']) ? $value['debit_amt'] : '0';

            if ($credit_amt != 0) {
                $balance_amt = $balance_amt + $credit_amt;
            }

            if ($debit_amt != 0) {
                $balance_amt = $balance_amt - $debit_amt;
            }

            $new_html .= '<tr>';
            $new_html .= '<td>' . $i++ . '</td>';
            $new_html .= '<td>' . date('d-m-Y', strtotime($tran_date)) . '</td>';
            $new_html .= '<td>' . $receipt_id . '</td>';
            $new_html .= '<td>' . $particluars . '</td>';
            $new_html .= '<td>' . $credit_amt . '</td>';
            $new_html .= '<td>' . $debit_amt . '</td>';
            $new_html .= '<td>' . $balance_amt . '</td>';
            $new_html .= '</tr>';

            $total_credit_amt += $credit_amt;
            $total_debit_amt += $debit_amt;

        }

        $new_html .= '<tr>';
        $new_html .= '<td class="font-weight-bold" colspan=4 align="right">Total Balance</td>';
        $new_html .= '<td class="font-weight-bold">' . $total_credit_amt . '</td>';
        $new_html .= '<td class="font-weight-bold">' . $total_debit_amt . '</td>';
        $new_html .= '<td class="font-weight-bold">' . ($total_credit_amt - $total_debit_amt) . '</td>';
        $new_html .= '</tr>';

        $new_html .= '</tbody>
                        </table>
                    </div>';
        return $new_html;
    }
}
