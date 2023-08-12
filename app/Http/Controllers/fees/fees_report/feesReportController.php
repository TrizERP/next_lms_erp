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

        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');

        $get_users = DB::table('fees_collect as fc')
        ->join('tbluser as u', 'fc.created_by', '=', 'u.id')
        ->where('fc.syear', '=', $syear)
        ->where('fc.sub_institute_id', '=', $sub_institute_id)
        ->selectRaw('u.id, u.user_name')
        ->groupBy('fc.created_by')
        ->get()->toArray();
        //echo "<pre>";print_r($get_users);exit;

        $res['status_code'] = "1";
        $res['get_users'] = $get_users;
        $res['message'] = "Success";

        return is_mobile($type, "fees/fees_report/index", $res, "view");
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
        $payment_mode = $request->input('payment_mode');
        $selected_user_name = $request->input('user_name');
        //echo "<pre>";print_r($selected_user_name);exit;
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $client_id = $request->session()->get('client_id');
        $marking_period_id = session()->get('term_id');

        $extra_fp = "  AND fp.syear = '" . $syear . "' AND te.syear = '" . $syear . "' AND t.sub_institute_id = '" . $sub_institute_id . "' AND fp.sub_institute_id = '" . $sub_institute_id . "' AND fp.is_deleted = 'N' ";
        $extra_fo = "  AND fo.syear = '" . $syear . "' AND te.syear = '" . $syear . "' AND t.sub_institute_id = '" . $sub_institute_id . "' AND fo.sub_institute_id = '" . $sub_institute_id . "' AND fo.is_deleted = 'N' ";
        // $extra_fo = '';
        // $extra_fp='';
        if (!empty($grade)) {
            $extra_fp .= " AND te.grade_id IN ('" . implode("','", $grade) . "')"; // Use IN operator for multiple values
            $extra_fo .= " AND te.grade_id IN ('" . implode("','", $grade) . "')"; // Use IN operator for multiple values
        }

        if (!empty($standard)) {
            $extra_fp .= " AND te.standard_id IN ('" . implode("','", $standard) . "')"; // Use IN operator for multiple values
            $extra_fo .= " AND te.standard_id IN ('" . implode("','", $standard) . "')"; // Use IN operator for multiple values
        }

        if (!empty($division)) {
            $extra_fp .= " AND te.section_id IN ('" . implode("','", $division) . "')"; // Use IN operator for multiple values
            $extra_fo .= " AND te.section_id IN ('" . implode("','", $division) . "')"; // Use IN operator for multiple values
        }

        if ($enrollment_no != '') {
            $extra_fp .= " AND t.enrollment_no = '" . $enrollment_no . "'";
            $extra_fo .= " AND t.enrollment_no = '" . $enrollment_no . "'";
        }

        if ($name != '') {
            $extra_fp .= " AND (t.first_name = '" . $name . "' OR t.last_name = '" . $name . "' OR t.middle_name = '" . $name . "') ";
            $extra_fo .= " AND (t.first_name = '" . $name . "' OR t.last_name = '" . $name . "' OR t.middle_name = '" . $name . "')";
        }

        if ($mb_no != '') {
            $extra_fp .= " AND t.mobile = '" . $mb_no . "'";
            $extra_fo .= " AND t.mobile = '" . $mb_no . "'";
        }
        // ->whereRaw("DATE_FORMAT(fp.receiptdate,'%Y-%m-%d') between '" . $from_date . "' AND '" . $to_date . "'");

        if ($from_date != '' && $to_date != '') {
            $extra_fp .= " AND DATE_FORMAT(fp.receiptdate,'%Y-%m-%d') between '" . $from_date . "' AND '" . $to_date . "' ";
            $extra_fo .= " AND DATE_FORMAT(fo.receiptdate,'%Y-%m-%d') between '" . $from_date . "' AND '" . $to_date . "'";
        }

        if ($client_id == 6) {
            $extra_fp .= " AND fp.standard_id=te.standard_id ";
        }

        if ($selected_user_name != '') {
            $extra_fp .= " AND u.id = '" . $selected_user_name . "'";
            $extra_fo .= " AND u.id = '" . $selected_user_name . "'";
        }

        if ($payment_mode != '') {
            $extra_fp .= " AND fp.payment_mode = '" . $payment_mode . "'";
            $extra_fo .= " AND fo.payment_mode = '" . $payment_mode . "'";
        }

        // $M = DB::table('tblstudent as t')
        //     ->selectRaw("fp.student_id, t.enrollment_no, t.roll_no, t.uniqueid, CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) AS student_name, t.mobile, ac.title AS grade, s.name AS standard_name, d.name AS division_name, fp.created_date, CONCAT_WS(' ', u.first_name, u.last_name) AS user_name, fp.term_id, fp.receiptdate, fp.receipt_no, fp.payment_mode, fp.cheque_bank_name, fp.bank_branch, fp.cheque_no, fp.cheque_date, SUM(IFNULL(fp.amount, 0)) AS amount,b.title as batch,sq.title as quota")
        //     ->join('tblstudent_enrollment as te', 't.id', '=', 'te.student_id')
        //     ->join('academic_section as ac', 'ac.id', '=', 'te.grade_id')
        //     ->join('standard as s', function($join) use($marking_period_id){
        //         $join->on('s.id', '=', 'te.standard_id');
        //         // ->when($marking_period_id,function($query) use ($marking_period_id){
        //         //     $query->where('s.marking_period_id',$marking_period_id);
        //         // });
        //     })
        //     ->join('division as d', 'd.id', '=', 'te.section_id')
        //     ->join('student_quota as sq', 'sq.id', '=', 'te.student_quota')            
        //     ->leftjoin('batch as b',function($join) {
        //         $join->on('b.standard_id', '=', 'te.standard_id')
        //         ->whereRaw('b.division_id = te.section_id')
        //         ->whereRaw('b.id = t.studentbatch')
        //         ->whereRaw('b.syear = te.syear');
        //     })  
        //     ->join('fees_collect as fp', 'fp.student_id', '=', 'te.student_id')
        //     ->leftJoin('tbluser as u', 'fp.created_by', '=', 'u.id')
        //     ->whereRaw("1=1 {$extra_fp}")
        //     ->groupBy('fp.student_id', 'fp.receipt_no', 'fp.syear', 'fp.receiptdate', 'fp.payment_mode', 'fp.cheque_no');

        // $N = DB::table('tblstudent as t')
        //     ->selectRaw("fo.student_id, SUM(IFNULL(fo.actual_amountpaid, 0)) AS actual_amountpaid, fo.reciept_id")
        //     ->join('tblstudent_enrollment as te', 't.id', '=', 'te.student_id')
        //     ->join('academic_section as ac', 'ac.id', '=', 'te.grade_id')
        //     ->join('standard as s', function($join) use($marking_period_id){
        //         $join->on('s.id', '=', 'te.standard_id');
        //         // ->when($marking_period_id,function($query) use ($marking_period_id){
        //         //     $query->where('s.marking_period_id',$marking_period_id);
        //         // });
        //     })
        //     ->join('division as d', 'd.id', '=', 'te.section_id')
        //     ->join('fees_paid_other as fo', 'fo.student_id', '=', 'te.student_id')
        //     ->whereRaw("1=1 {$extra_fo}")
        //     ->groupBy('fo.student_id', 'fo.reciept_id', 'fo.syear', 'fo.receiptdate', 'fo.payment_mode', 'fo.cheque_dd_no');

        // $query = DB::table(DB::raw("({$M->toSql()}) as M"))
        //     ->selectRaw("M.student_id, M.enrollment_no, M.roll_no, M.uniqueid, M.student_name, M.mobile, M.grade, M.standard_name, M.division_name, M.created_date, M.user_name, M.term_id, M.receiptdate, M.receipt_no, M.payment_mode, M.cheque_bank_name, M.bank_branch, M.cheque_no, M.cheque_date, (IFNULL(M.amount, 0) + IFNULL(N.actual_amountpaid, 0)) AS actual_amountpaid,M.batch,M.quota")
        //     ->leftJoin(DB::raw("({$N->toSql()}) as N"), function ($join) {
        //         $join->on('M.student_id', '=', 'N.student_id')
        //             ->on('M.receipt_no', '=', 'N.reciept_id');
        //     })
        //     ->mergeBindings($M)
        //     ->mergeBindings($N)
        //     ->havingNotNull('M.receiptdate')
        //     ->orderByRaw("M.receiptdate, CAST(M.receipt_no AS SIGNED)");


        $queryCollect = DB::table('tblstudent as t')
        ->join('tblstudent_enrollment as te', function ($join) {
            $join->on('te.student_id', '=', 't.id');
        })
        ->join('academic_section as g','g.id','=','te.grade_id')        
            ->join('standard as s','s.id','=','te.standard_id')
            ->join('division as d','d.id','=','te.section_id')  
            ->join('student_quota as sq','sq.id','=','te.student_quota') 
            ->leftjoin('batch as b',function($join) {
                $join->on('b.standard_id', '=', 'te.standard_id')
                ->whereRaw('b.division_id = te.section_id')
                ->whereRaw('b.id = t.studentbatch')
                ->whereRaw('b.syear = te.syear');
            })  
            ->Join('fees_collect as fp', 'fp.student_id', '=', 'te.student_id')
            ->leftJoin('tbluser as u', 'fp.created_by', '=', 'u.id')
        
            ->selectRaw('t.id as student_id, t.enrollment_no, t.roll_no, t.uniqueid, t.place_of_birth, '
                .DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as student_name").', g.title as grade, s.name as standard_name, d.name as division_name, fp.created_date, '
                .DB::raw('CONCAT_WS(" ", u.first_name, u.last_name) AS user_name, fp.term_id, fp.receiptdate, fp.receipt_no, fp.payment_mode, '
                .'fp.cheque_bank_name, fp.bank_branch, fp.cheque_no, fp.cheque_date, b.title as batch, sq.title as quota, '
                .'IFNULL(fp.amount, 0) AS actual_amountpaid')
            )
            ->whereRaw("1=1 " . $extra_fp);
        
        
        $queryOther = DB::table('tblstudent as t')
            ->join('tblstudent_enrollment as te', function ($join) {
                $join->on('te.student_id', '=', 't.id');
            })
        ->join('academic_section as g','g.id','=','te.grade_id')        
            ->join('standard as s','s.id','=','te.standard_id')
            ->join('division as d','d.id','=','te.section_id')  
            ->join('student_quota as sq','sq.id','=','te.student_quota') 
            ->leftjoin('batch as b',function($join) {
                $join->on('b.standard_id', '=', 'te.standard_id')
                ->whereRaw('b.division_id = te.section_id')
                ->whereRaw('b.id = t.studentbatch')
                ->whereRaw('b.syear = te.syear');
            })  
        
        ->leftJoin('fees_paid_other as fo', 'fo.student_id', '=', 'te.student_id')
        ->leftJoin('tbluser as u', 'fo.created_by', '=', 'u.id')
        ->selectRaw('t.id as student_id, t.enrollment_no, t.roll_no, t.uniqueid, t.place_of_birth, '
            .DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as student_name").', g.title as grade, s.name as standard_name, d.name as division_name, NULL AS created_date, '
            .DB::raw('CONCAT_WS(" ", u.first_name, u.last_name) AS user_name, fo.month_id AS term_id, fo.receiptdate AS receiptdate, fo.reciept_id AS receipt_no, fo.payment_mode AS payment_mode, '
            .'fo.bank_name as cheque_bank_name, fo.bank_branch, fo.cheque_dd_no as cheque_no, fo.cheque_dd_date AS cheque_date, b.title as batch, sq.title as quota, '
            .'IFNULL(fo.actual_amountpaid, 0) AS actual_amountpaid')
        )
        ->whereRaw("1=1 " . $extra_fo);
    
        $feesData = $queryCollect->union($queryOther)->get()->toArray();
    
        $feesData = json_decode(json_encode($feesData), true);
        
        $get_users = DB::table('fees_collect as fc')
        ->join('tbluser as u', 'fc.created_by', '=', 'u.id')
        ->where('fc.syear', '=', $syear)
        ->where('fc.sub_institute_id', '=', $sub_institute_id)
        ->selectRaw('u.id, u.user_name')
        ->groupBy('fc.created_by')
        ->get()->toArray();

        //echo "<pre>";print_r($collected_by);exit;
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
        $res['payment_mode'] = $payment_mode;
        $res['selected_user_name'] = $selected_user_name;
        $res['get_users'] = $get_users;
        $res['months'] = FeeMonthId();

        return is_mobile($type, "fees/fees_report/index", $res, "view");
    }
}
