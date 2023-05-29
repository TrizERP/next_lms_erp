<?php

namespace App\Http\Controllers\fees\fees_report;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\FeeMonthId;
use function App\Helpers\is_mobile;

class feesReportController extends Controller
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

        return is_mobile($type, "fees/fees_report/index", $res, "view");
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

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return void
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

    public function showFees(Request $request)
    {
        $type = $request->input("type");
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $enrollment_no = $request->input('enrollment_no');
        $name = $request->input('name');
        $mb_no = $request->input('mb_no');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $receipt_no = $request->input('receipt_no');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $client_id = $request->session()->get('client_id');

        $extra_fp = "  AND fp.syear = '" . $syear . "' AND te.syear = '" . $syear . "' AND t.sub_institute_id = '" . $sub_institute_id . "' AND fp.sub_institute_id = '" . $sub_institute_id . "' AND fp.is_deleted = 'N' ";

        $extra_fo = "  AND fo.syear = '" . $syear . "' AND te.syear = '" . $syear . "' AND t.sub_institute_id = '" . $sub_institute_id . "' AND fo.sub_institute_id = '" . $sub_institute_id . "' AND fo.is_deleted = 'N' ";

        /*if ($grade != '') {
            $extra_fp .= " AND te.grade_id = '" . $grade . "'";
            $extra_fo .= " AND te.grade_id = '" . $grade . "'";
        }*/
        if (!empty($grade)) {
            $gradeString = implode("','", $grade); // Convert the array to a comma-separated string
            $extra_fp .= " AND te.grade_id IN ('" . $gradeString . "')"; // Use IN operator for multiple values
            $extra_fo .= " AND te.grade_id IN ('" . $gradeString . "')"; // Use IN operator for multiple values
        }

        /*if ($standard != '') {
            $extra_fp .= " AND te.standard_id = '" . $standard . "'";
            $extra_fo .= " AND te.standard_id = '" . $standard . "'";
        }*/
        if (!empty($standard)) {
            $standardString = implode("','", $standard); // Convert the array to a comma-separated string
            $extra_fp .= " AND te.standard_id IN ('" . $standardString . "')"; // Use IN operator for multiple values
            $extra_fo .= " AND te.standard_id IN ('" . $standardString . "')"; // Use IN operator for multiple values
        }

        /*if ($division != '') {
            $extra_fp .= " AND te.section_id = '" . $division . "'";
            $extra_fo .= " AND te.section_id = '" . $division . "'";
        }*/
        if (!empty($division)) {
            $divisionString = implode("','", $division); // Convert the array to a comma-separated string
            $extra_fp .= " AND te.section_id IN ('" . $divisionString . "')"; // Use IN operator for multiple values
            $extra_fo .= " AND te.section_id IN ('" . $divisionString . "')"; // Use IN operator for multiple values
        }

        if ($enrollment_no != '') {
            $extra_fp .= " AND t.enrollment_no = '" . $enrollment_no . "'";
            $extra_fo .= " AND t.enrollment_no = '" . $enrollment_no . "'";
        }
        if ($name != '') {
            // if($name == "t.first_name"){
            //     $extra_fp .= " AND t.first_name = '".$name."'";
            //     $extra_fo .= " AND t.first_name = '".$name."'";
            // }elseif($name == "t.last_name"){
            //     $extra_fp .= " AND t.last_name = '".$name."'";
            //     $extra_fo .= " AND t.last_name = '".$name."'";
            // }elseif($name == "t.middle_name"){
            //     $extra_fp .= " AND t.middle_name = '".$name."'";
            //     $extra_fo .= " AND t.middle_name = '".$name."'";
            // }
            $extra_fp .= " AND (t.first_name = '" . $name . "' OR t.last_name = '" . $name . "' OR t.middle_name = '" . $name . "') ";
            $extra_fo .= " AND (t.first_name = '" . $name . "' OR t.last_name = '" . $name . "' OR t.middle_name = '" . $name . "')";
        }
        if ($mb_no != '') {
            $extra_fp .= " AND t.mobile = '" . $mb_no . "'";
            $extra_fo .= " AND t.mobile = '" . $mb_no . "'";
        }
        /*
                if($receipt_no != ''){
                    $extra_fp .= " AND fp.receipt_no = '".$receipt_no."'";
                    $extra_fo .= " AND fo.reciept_id = '".$receipt_no."'";
                }
        */
        if ($from_date != '') {
            $extra_fp .= " AND fp.receiptdate >= '" . $from_date . "'";
            $extra_fo .= " AND fo.receiptdate >= '" . $from_date . "'";
        }

        if ($to_date != '') {
            $extra_fp .= " AND fp.receiptdate <= '" . $to_date . "'";
            $extra_fo .= " AND fo.receiptdate <= '" . $to_date . "'";
        }
        if($client_id == 6){
            $extra_fp .= " AND fp.standard_id=te.standard_id ";
            //$extra_fo .= " AND fo.receiptdate <= '".$to_date."'";
        }

        $sql = "SELECT M.student_id,M.enrollment_no,M.roll_no,M.uniqueid,M.student_name,M.mobile,M.grade,M.standard_name,M.division_name,M.created_date,M.user_name,M.term_id,M.receiptdate,M.receipt_no,M.payment_mode,M.cheque_bank_name,M.bank_branch,M.cheque_no,M.cheque_date,
            (IFNULL(M.amount,0) + IFNULL(N.actual_amountpaid,0)) AS actual_amountpaid
            FROM (
            SELECT fp.student_id,t.enrollment_no,t.roll_no,t.uniqueid,CONCAT_WS(' ',t.first_name,t.middle_name,t.last_name) AS student_name,t.mobile,ac.title AS grade,s.name AS standard_name,d.name AS division_name,fp.created_date,CONCAT_WS(' ',u.first_name,u.last_name) AS user_name,fp.term_id,fp.receiptdate,fp.receipt_no,fp.payment_mode,fp.cheque_bank_name,fp.bank_branch,fp.cheque_no,fp.cheque_date,SUM(IFNULL(fp.amount,0)) AS amount
            FROM tblstudent t
            INNER JOIN tblstudent_enrollment te ON t.id = te.student_id
            INNER JOIN academic_section ac ON ac.id = te.grade_id
            INNER JOIN standard s ON s.id = te.standard_id
            INNER JOIN division d ON d.id = te.section_id
            INNER JOIN fees_collect fp ON fp.student_id = te.student_id
            LEFT JOIN tbluser u ON fp.created_by = u.id
            WHERE 1=1 $extra_fp
            GROUP BY fp.student_id, fp.receipt_no, fp.syear, fp.receiptdate, fp.payment_mode, fp.cheque_no
            ORDER BY fp.receiptdate ASC, fp.receipt_no ASC) AS M
            LEFT JOIN (
            SELECT fo.student_id, SUM(IFNULL(fo.actual_amountpaid,0)) AS actual_amountpaid
            FROM tblstudent t
            INNER JOIN tblstudent_enrollment te ON t.id = te.student_id
            INNER JOIN academic_section ac ON ac.id = te.grade_id
            INNER JOIN standard s ON s.id = te.standard_id
            INNER JOIN division d ON d.id = te.section_id
            INNER JOIN fees_paid_other fo ON fo.student_id = te.student_id
            WHERE 1=1 $extra_fo
            GROUP BY fo.student_id, fo.reciept_id, fo.syear, fo.receiptdate, fo.payment_mode, fo.cheque_dd_no
            ORDER BY fo.receiptdate ASC, fo.reciept_id ASC) AS N ON M.student_id = N.student_id
            HAVING (M.receiptdate IS NOT NULL)
            ORDER BY M.receiptdate,CAST(M.receipt_no AS SIGNED)";
            //            -- WHERE t.first_name = $name OR t.middle_name = $name OR t.last_name = $name SET LINE 180
//echo $sql;
//die();
        $result = DB::select(DB::raw($sql));
        $feesData = json_decode(json_encode($result), true);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fees_data'] = $feesData;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['enrollment_no'] = $enrollment_no;
        $res['receipt_no'] = $receipt_no;
        $res['name'] = $name;
        $res['mb_no'] = $mb_no;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['months'] = FeeMonthId();

        return is_mobile($type, "fees/fees_report/index", $res, "view");
    }
}
