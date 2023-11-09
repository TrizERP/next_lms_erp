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
use GenTux\Jwt\GetsJwtToken;

class feesReportController extends Controller
{
    use GetsJwtToken;
    
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

        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }

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
        // echo "<pre>";print_r($request->all());exit;
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $client_id = $request->session()->get('client_id');
        $marking_period_id = session()->get('term_id');
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        
        $extra_fp = "  AND fp.syear = '" . $syear . "' AND  fp.sub_institute_id = '" . $sub_institute_id . "' AND fp.is_deleted = 'N' ";
        $extra_fo = "  AND fo.syear = '" . $syear . "' AND fo.sub_institute_id = '" . $sub_institute_id . "' AND fo.is_deleted = 'N' ";
      
        if (!empty($grade)) {
            $extra_fp .= " AND te.grade_id IN ('" . implode("','", $grade) . "')"; 
            $extra_fo .= " AND te.grade_id IN ('" . implode("','", $grade) . "')"; 
        }

        if (!empty($standard)) {
            $extra_fp .= " AND te.standard_id IN ('" . implode("','", $standard) . "')"; 
            $extra_fo .= " AND te.standard_id IN ('" . implode("','", $standard) . "')"; 
        }

        if (!empty($division)) {
            $extra_fp .= " AND te.section_id IN ('" . implode("','", $division) . "')"; 
            $extra_fo .= " AND te.section_id IN ('" . implode("','", $division) . "')"; 
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
        //DB::enableQueryLog();
        $data = DB::table(function ($query) use ($sub_institute_id, $syear, $extra_fo, $extra_fp) {
            $query->selectRaw('t.id as student_id, t.enrollment_no, t.roll_no, t.uniqueid, t.place_of_birth, '
                . DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as student_name") . ', g.title as grade, s.name as standard_name, d.name as division_name, fp.created_date, '
                . DB::raw('CONCAT_WS(" ", u.first_name, u.last_name) AS user_name, fp.term_id, fp.receiptdate, fp.receipt_no, fp.payment_mode, '
                . 'fp.cheque_bank_name, fp.bank_branch, fp.cheque_no, fp.cheque_date, b.title as batch, sq.title as quota, fp.remarks, '
                . 'IFNULL(fp.amount, 0) AS actual_amountpaid'))
                ->from('tblstudent as t')
                ->join('tblstudent_enrollment as te', function ($join) use($syear){
                    $join->on('te.student_id', '=', 't.id')->where('te.syear',$syear);
                })
                ->leftJoin('academic_section as g', 'g.id', '=', 'te.grade_id')
                ->leftJoin('standard as s', 's.id', '=', 'te.standard_id')
                ->leftJoin('division as d', 'd.id', '=', 'te.section_id')
                ->leftJoin('student_quota as sq', 'sq.id', '=', 'te.student_quota')
                ->leftjoin('batch as b', function ($join) {
                    $join->on('b.standard_id', '=', 'te.standard_id')
                        ->whereRaw('b.division_id = te.section_id')
                        ->whereRaw('b.id = t.studentbatch')
                        ->whereRaw('b.syear = te.syear');
                })
                ->Join('fees_collect as fp', 'fp.student_id', '=', 'te.student_id')
                ->leftJoin('tbluser as u', 'fp.created_by', '=', 'u.id')
                ->whereRaw("1=1 " . $extra_fp)

                ->unionAll(function ($query) use ($sub_institute_id, $syear, $extra_fo, $extra_fp) {
                    $query->selectRaw('t.id as student_id, t.enrollment_no, t.roll_no, t.uniqueid, t.place_of_birth, '
                        . DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as student_name") . ', g.title as grade, s.name as standard_name, d.name as division_name, NULL AS created_date, '
                        . DB::raw('CONCAT_WS(" ", u.first_name, u.last_name) AS user_name, fo.month_id AS term_id, fo.receiptdate AS receiptdate, fo.reciept_id AS receipt_no, fo.payment_mode AS payment_mode, '
                        . 'fo.bank_name as cheque_bank_name, fo.bank_branch, fo.cheque_dd_no as cheque_no, fo.cheque_dd_date AS cheque_date, b.title as batch, sq.title as quota, NULL as remarks, '
                        . 'IFNULL(fo.actual_amountpaid, 0) AS actual_amountpaid'))->from('tblstudent as t')
                        ->join('tblstudent_enrollment as te', function ($join) use($syear){
                            $join->on('te.student_id', '=', 't.id')->where('te.syear',$syear);
                        })
                        ->leftJoin('academic_section as g', 'g.id', '=', 'te.grade_id')
                        ->leftJoin('standard as s', 's.id', '=', 'te.standard_id')
                        ->leftJoin('division as d', 'd.id', '=', 'te.section_id')
                        ->leftJoin('student_quota as sq', 'sq.id', '=', 'te.student_quota')
                        ->leftjoin('batch as b', function ($join) {
                            $join->on('b.standard_id', '=', 'te.standard_id')
                                ->whereRaw('b.division_id = te.section_id')
                                ->whereRaw('b.id = t.studentbatch')
                                ->whereRaw('b.syear = te.syear');
                        })
                        ->leftJoin('fees_paid_other as fo', 'fo.student_id', '=', 'te.student_id')
                        ->leftJoin('tbluser as u', 'fo.created_by', '=', 'u.id')
                        ->whereRaw("1=1 " . $extra_fo);
                });
        })
            ->selectRaw('student_id, enrollment_no, roll_no, uniqueid, place_of_birth, student_name, grade,standard_name, division_name,created_date, user_name, GROUP_CONCAT(term_id) AS term_ids, receiptdate, receipt_no,  payment_mode, cheque_bank_name, bank_branch, cheque_no, cheque_date, batch, quota, remarks, SUM(IFNULL(actual_amountpaid, 0)) AS actual_amountpaid')
            ->groupBy(['student_id', 'receipt_no', 'receiptdate', 'payment_mode', 'cheque_no']);
            
        $data = $data->get()->toArray();
        //dd(DB::getQueryLog($data));
        $feesData = json_decode(json_encode($data), true);
        
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
        $res['months'] = FeeMonthId($syear,$sub_institute_id);
        // echo "<pre>";print_r($res['fees_data']);exit;
        return is_mobile($type, "fees/fees_report/index", $res, "view");
    }
    
}
