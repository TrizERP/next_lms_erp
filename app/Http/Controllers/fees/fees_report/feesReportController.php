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
use Illuminate\Support\Facades\Schema;
use App\Models\fees\fees_title\fees_title;
use GenTux\Jwt\GetsJwtToken;
use Carbon\Carbon;

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
        ->where('u.status',1) // 23-04-24 by uma
        ->selectRaw('u.id, u.user_name')
        ->groupBy('fc.created_by')
        ->get()->toArray();
        //echo "<pre>";print_r($get_users);exit;

        $res['status_code'] = "1";
        $res['get_users'] = $get_users;
        $res['message'] = "Success";

        return is_mobile($type, "fees/fees_report/index", $res, "view");
    }

    public function create(Request $request)
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
        $table_year = $request->input('table_year');
        $groupby=$request->input('groupby');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $client_id = $request->session()->get('client_id');
        $marking_period_id = session()->get('term_id');
        
        if(in_array($type,["API","JSON"])){
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
        
        $extra_fp = "  AND fp.sub_institute_id = '" . $sub_institute_id . "' AND fp.syear = '" . $syear . "' AND fp.is_deleted = 'N' ";
        $extra_fo = "  AND fo.sub_institute_id = '" . $sub_institute_id . "' AND fo.syear = '" . $syear . "' AND fo.is_deleted = 'N' ";
      
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
        // DB::enableQueryLog();
        $data = DB::table(function ($query) use ($sub_institute_id, $syear, $extra_fo, $extra_fp) 
        {
            $query->selectRaw('t.id as student_id,te.syear, t.enrollment_no, te.roll_no, t.uniqueid, t.place_of_birth, '
                . DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as student_name") . ', g.title as grade, s.name as standard_name, d.name as division_name, fp.created_date, '
                . DB::raw('CONCAT_WS(" ", u.first_name, u.last_name) AS user_name, fp.term_id, fp.receiptdate, fp.receipt_no, fp.payment_mode, '
                . 'fp.cheque_bank_name, fp.bank_branch, fp.cheque_no, fp.cheque_date, b.title as batch, sq.title as quota, fp.remarks, '
                . 'IFNULL(fp.amount, 0) AS actual_amountpaid'))
                ->from('fees_collect as fp')
                ->Join('tblstudent as t', 'fp.student_id', '=', 't.id')
                ->join('tblstudent_enrollment as te', function ($join){
                    $join->on('te.student_id', '=', 't.id')
                    ->on('te.syear','=','fp.syear');
                })
                ->Join('academic_section as g', 'g.id', '=', 'te.grade_id')
                ->Join('standard as s', 's.id', '=', 'te.standard_id')
                ->Join('division as d', 'd.id', '=', 'te.section_id')
                ->Join('student_quota as sq', 'sq.id', '=', 'te.student_quota')
                ->leftJoin('batch as b', function ($join) {
                    $join->on('b.standard_id', '=', 'te.standard_id')
                        ->whereRaw('b.division_id = te.section_id')
                        ->whereRaw('b.id = t.studentbatch')
                        ->whereRaw('b.syear = te.syear');
                })
            // ->Join('fees_collect as fp', 'fp.student_id', '=', 'te.student_id')
            ->leftJoin('tbluser as u',function($join){
                $join->on('fp.created_by', '=', 'u.id')->where('u.status',1); // 23-04-24 by uma
            })
            ->whereRaw("1=1 " . $extra_fp)

            ->unionAll(function ($query) use ($sub_institute_id, $syear, $extra_fo, $extra_fp) {
                $query->selectRaw('t.id as student_id,te.syear, t.enrollment_no, te.roll_no, t.uniqueid, t.place_of_birth, '
                    . DB::raw("CONCAT_WS(' ', t.first_name, t.middle_name, t.last_name) as student_name") . ', g.title as grade, s.name as standard_name, d.name as division_name, NULL AS created_date, '
                    . DB::raw('CONCAT_WS(" ", u.first_name, u.last_name) AS user_name, fo.month_id AS term_id, fo.receiptdate AS receiptdate, fo.reciept_id AS receipt_no, fo.payment_mode AS payment_mode, '
                    . 'fo.bank_name as cheque_bank_name, fo.bank_branch, fo.cheque_dd_no as cheque_no, fo.cheque_dd_date AS cheque_date, b.title as batch, sq.title as quota, NULL as remarks, '
                    . 'IFNULL(fo.actual_amountpaid, 0) AS actual_amountpaid'))
                    ->from('fees_paid_other as fo')
                    ->Join('tblstudent as t', 'fo.student_id', '=', 't.id')
                    ->join('tblstudent_enrollment as te', function ($join){
                        $join->on('te.student_id', '=', 't.id')
                        ->on('te.syear','=','fo.syear');
                    })
                    ->Join('academic_section as g', 'g.id', '=', 'te.grade_id')
                    ->Join('standard as s', 's.id', '=', 'te.standard_id')
                    ->Join('division as d', 'd.id', '=', 'te.section_id')
                    ->Join('student_quota as sq', 'sq.id', '=', 'te.student_quota')
                    ->leftJoin('batch as b', function ($join) {
                        $join->on('b.standard_id', '=', 'te.standard_id')
                            ->whereRaw('b.division_id = te.section_id')
                            ->whereRaw('b.id = t.studentbatch')
                            ->whereRaw('b.syear = te.syear');
                    })
                // ->leftJoin('fees_paid_other as fo', 'fo.student_id', '=', 'te.student_id')
                ->leftJoin('tbluser as u',function($join){
                    $join->on('fo.created_by', '=', 'u.id')->where('u.status',1); // 23-04-24 by uma
                })
                ->whereRaw("1=1 " . $extra_fo);
            });
        })
        ->selectRaw('student_id, enrollment_no, roll_no, uniqueid, place_of_birth, student_name, grade,standard_name, division_name,created_date, user_name, GROUP_CONCAT(term_id) AS term_ids, receiptdate, receipt_no,  payment_mode, cheque_bank_name, bank_branch, cheque_no, cheque_date, batch, quota, remarks, SUM(IFNULL(actual_amountpaid, 0)) AS actual_amountpaid, syear')
        ->when($sub_institute_id==257,function($query){
            $query->orderBy('receipt_no');
        })
        ->groupBy(['syear', 'student_id','receiptdate','payment_mode','cheque_no']);
            
        $data = $data->get()->toArray();
        // dd(DB::getQueryLog($data));
        $feesData = json_decode(json_encode($data), true);
        
        $get_users = DB::table('fees_collect as fc')
        ->join('tbluser as u', 'fc.created_by', '=', 'u.id')
        ->where('fc.syear', '=', $syear)
        ->where('fc.sub_institute_id', '=', $sub_institute_id)
        ->where('u.status',1)  // 23-04-24 by uma
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
        $res['groupby'] = $groupby;        
        $res['months'] = FeeMonthId($syear,$sub_institute_id);
        $res['seprateDetails'] =$request->input('seprateDetails');

        // echo "<pre>";print_r($res['fees_data']);exit;
        return is_mobile($type, "fees/fees_report/index", $res, "view");
    }

    // added new report for ssmission
    public function datewiseReportIndex(Request $request){
        $type = $request->input("type");
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $payment_mode = $request->input('payment_mode');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        
        if(in_array($type,["API","JSON"])){
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

        $datewiseData = [];
        $selTitle = [];  

        if($request->has('search')){
            // echo "<pre>";print_r($request->all());exit;
            $fees_head_sum = "";
        
            $fees_columns = "";
            $other_columns = "";
            $columns = "";
            foreach ($request->fees_head as $key => $feeTitleId) {
                if($feeTitleId!=''){
                    $title = DB::table('fees_title')->where('id',$feeTitleId)->value('fees_title');
                    $selTitle[] = $title;
                    $feesTitleColumnExistsInCollect = Schema::hasColumn('fees_collect', $title);
                    $feesTitleColumnExistsInPaidOther = Schema::hasColumn('fees_paid_other', $title);
                    $columnAlias = is_numeric($title) ? $title : $title;
        
                    if ($feesTitleColumnExistsInCollect) {
                        $fees_columns .= "sum(fc.`". $title . "`) as total_" . $title . ",";
                        if($title == "tution_fee"){
                            $columns .= "IFNULL(SUM(total_" . $title . "),0) as total_" . $title . ",";
                        }else{
                            $columns .= "IFNULL(SUM(total_" . $title . "),0) as total_" . $title . ",";
                        }
                    } else {
                        $fees_columns .= "NULL as total_" . $columnAlias . ",";
                    }
        
                    if ($feesTitleColumnExistsInPaidOther) {
                        $other_columns .="sum(fp.`". $title . "`) as  total_" . $title . ",";
                        $columns .="IFNULL(SUM(`total_" . $title . "`),0) as  total_" . $title . ",";
                    } else {
                        $other_columns .= "NULL as total_" . $columnAlias . ",";
                    }
                }
            }

            $receipt_book= DB::table('fees_receipt_book_master')->where(['sub_institute_id' => $sub_institute_id,'status' => 1,'syear'=>$syear])
            ->selectRaw('*,GROUP_CONCAT(DISTINCT standard_id) as standards,GROUP_CONCAT(DISTINCT fees_head_id) as heads')    
            ->when($request->has('receipt_title'),function($q) use($request){
                 $q->where('sort_order',$request->receipt_title);
             })
             ->first();
             $searchedStandards = (isset($receipt_book->standards) && $receipt_book->standards!='') ? explode(',',$receipt_book->standards) : [];
            // echo "<pre>";print_r($fees_columns);exit;
            // DB::enableQueryLog();
            $fees_data = DB::table(function ($query)  use($fees_columns,$other_columns,$sub_institute_id,$syear,$request,$searchedStandards) {
                $query->from('fees_collect as fc')
                ->join('tblstudent as ts', function ($join) {
                    $join->whereRaw('ts.id = fc.student_id AND ts.sub_institute_id = fc.sub_institute_id');
                })->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = ts.id');
                })->join('student_quota as sq', function ($join) {
                    $join->whereRaw('sq.id = se.student_quota');
                })->join('academic_section as a', function ($join) {
                    $join->whereRaw('a.id = se.grade_id');
                })->join('standard as s', function ($join) {
                    $join->whereRaw('s.id = se.standard_id');
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id');
                })->leftjoin('batch as b', function ($join) {
                    $join->whereRaw('b.id = ts.studentbatch AND se.syear=b.syear');
                })
                ->leftJoin('tbluser as u',function($join){
                    $join->on('fc.created_by', '=', 'u.id')->where('u.status',1); // 23-04-24 by uma
                })
                ->selectRaw("fc.id,fc.student_id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,
                    ts.enrollment_no,ts.admission_year,ts.mobile,ts.email,date_format(ts.dob,'%d-%m-%Y') AS dob,a.title AS section,
                    s.name AS std_name,s.short_name as short_standard_name,d.name AS div_name,sq.title AS stu_qouta, $fees_columns
                    SUM(fc.fine) AS total_fine,SUM(fc.fees_discount) AS tot_disc,fc.receipt_no,sum(fc.amount) as total_amt,b.title as student_batch_name,date_format(fc.receiptdate,'%d-%m-%Y') AS receiptdate,fc.payment_mode,fc.cheque_bank_name,fc.bank_branch,fc.cheque_no,fc.cheque_date,fc.bank_name,concat_ws(' ',COALESCE(u.first_name,'-'),COALESCE(u.last_name,'-')) as user_name,fc.remarks")
                ->where('se.syear', $syear)
                ->where('fc.syear', $syear)
                ->where('s.sub_institute_id', $sub_institute_id)
                ->whereIn('fc.standard_id',$searchedStandards)
                ->when($request->has('payment_mode'),function($q) use($request){
                    $q->where('fc.payment_mode',$request->payment_mode);
                })
                ->when($request->has('from_date') && $request->has('to_date'),function($q) use($request){
                    $q->whereBetween('fc.receiptdate',[$request->from_date,$request->to_date]);
                })
                ->where('fc.is_deleted','N')->groupBy(['fc.student_id', 'fc.receipt_no'])
                ->unionAll(function ($query)  use($other_columns,$sub_institute_id,$syear,$request){
                    $query->selectRaw("fp.id,fp.student_id,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,
                    ts.enrollment_no,ts.admission_year,ts.mobile,ts.email,date_format(ts.dob,'%d-%m-%Y') AS dob,a.title AS section,
                    s.name AS std_name,s.short_name as short_standard_name,d.name AS div_name,sq.title AS stu_qouta, $other_columns
                    SUM(fp.fine) AS total_fine,SUM(fp.fees_discount) AS tot_disc,fp.reciept_id as receipt_no,sum(fp.actual_amountpaid) as total_amt,b.title as student_batch_name,date_format(fp.receiptdate,'%d-%m-%Y') AS receiptdate,fp.payment_mode,fp.bank_name AS cheque_bank_name,fp.bank_branch,fp.cheque_dd_no as cheque_no,fp.cheque_dd_date as cheque_date,'' as bank_name,concat_ws(' ',COALESCE(u.first_name,'-'),COALESCE(u.last_name,'-')) as user_name,fp.remarks")
                        ->from('fees_paid_other as fp')
                        ->join('tblstudent as ts', function ($join) {
                            $join->whereRaw('ts.id = fp.student_id AND ts.sub_institute_id = fp.sub_institute_id');
                        })->join('tblstudent_enrollment as se', function ($join) {
                            $join->whereRaw('se.student_id = ts.id');
                        })->join('student_quota as sq', function ($join) {
                            $join->whereRaw('sq.id = se.student_quota');
                        })->join('academic_section as a', function ($join) {
                            $join->whereRaw('a.id = se.grade_id');
                        })->join('standard as s', function ($join){
                            $join->whereRaw('s.id = se.standard_id');
                        })->join('division as d', function ($join) {
                            $join->whereRaw('d.id = se.section_id');
                        })->leftjoin('batch as b', function ($join) {
                            $join->whereRaw('b.id = ts.studentbatch');
                        })
                        ->leftJoin('tbluser as u',function($join){
                            $join->on('fp.created_by', '=', 'u.id')->where('u.status',1); // 23-04-24 by uma
                        })
                        ->where('se.syear', $syear)
                        ->where('fp.syear', $syear)
                        ->where('s.sub_institute_id', $sub_institute_id)
                        ->when($request->has($request->payment_mode),function($q) use($request){
                            $q->where('fp.payment_mode',$request->payment_mode);
                        })
                        ->when($request->has('from_date') && $request->has('to_date'),function($q) use($request){
                            $q->whereBetween('fp.receiptdate',[$request->from_date,$request->to_date]);
                        })
                        ->where('fp.is_deleted','N')->groupBy(['fp.student_id','fp.reciept_id']);
                });
            })
            ->selectRaw("id,student_id,student_name,
            enrollment_no,admission_year,mobile,email,dob,section,
           std_name,short_standard_name,div_name,stu_qouta, ".$columns."
           SUM(total_fine) as total_fine,SUM(tot_disc) as tot_disc,receipt_no,sum(total_amt) as amount,student_batch_name,receiptdate,payment_mode,cheque_bank_name,bank_branch,cheque_no,cheque_date,bank_name as institute_type,user_name,remarks,STR_TO_DATE(receiptdate, '%d-%m-%Y') as formatted_receiptdate")
            ->groupBy(['student_id','receiptdate','institute_type','cheque_bank_name','cheque_no'])
            ->orderByRaw('formatted_receiptdate,CAST(receipt_no AS UNSIGNED)')
            ->get()->toArray();
            // dd(DB::getQueryLog($fees_data));
            // echo "<pre>";print_r($fees_data);exit;
            // if(!empty($fees_data)){
            //     usort($fees_data, function ($a, $b) {
            //         return $a->receipt_no - $b->receipt_no;
            //     });
            // }
            
            foreach ($fees_data as $key => $value) {
                $value->total_amount = 0;
                foreach ($request->fees_head as $key => $feeTitleId) {
                    $feesTitle = DB::table('fees_title')->where('id',$feeTitleId)->value('fees_title');
                    $property = 'total_' . $feesTitle;

                    if (isset($value->$property)) {
                        $value->total_amount += $value->$property;
                    }
                }

                if($value->total_amount!=0){
                    $datewiseData[$value->receiptdate.'||'.$value->payment_mode][]=$value;
                }
            }
            // echo "<pre>";print_r($datewiseData);exit;
            if(empty($datewiseData)){
                $res['status']=0;
                $res['message']='No Data Found';
            }
            $res['school_details'] = $receipt_book;
        }

        if($sub_institute_id==76){
            $res['payment_mode'] = ['Cash'=>'CASH','Cheque'=>'CHEQUE','POS'=>'POS','Online'=>'ONLINE','UPI'=>'UPI','RTGS/NEFT'=>'RTGS/NEFT'];
        }
        else{
            $res['payment_mode'] = ['Cash'=>'Cash','Cheque'=>'Cheque','DD'=>'DD','Online'=>'Online','NACH'=>'NACH','UPI'=>'UPI','Swipe1'=>'Swipe1','Swipe2'=>'Swipe2','Swipe3'=>'Swipe3','POS'=>'POS'];
        }
        $res['receipt_title'] =  DB::table('fees_receipt_book_master')->where(['sub_institute_id' => $sub_institute_id,'status' => 1])
        ->selectRaw('*,GROUP_CONCAT(DISTINCT standard_id) as standards,GROUP_CONCAT(DISTINCT fees_head_id) as heads')    
        ->orderBy('sort_order', 'asc') 
        ->groupBy('sort_order')
        ->get()
        ->toArray();
    
        $res['feesHead'] = fees_title::where(['sub_institute_id' => $sub_institute_id,'syear' => $syear])
        ->orderBy('sort_order', 'asc') 
        ->pluck('display_name', 'fees_title')
        ->toArray();

        $res['selreceipt_title'] = isset($request->receipt_title) ? $request->receipt_title : '';
        $res['selPaymentMode'] = isset($request->payment_mode) ? $request->payment_mode : '';
        $res['selfeesHead'] = isset($request->fees_head) ? $request->fees_head : [];
        $res['selTitle'] = $selTitle;
        $res['selFromDate'] = isset($request->from_date) ? $request->from_date : now();
        $res['selToDate'] = isset($request->to_date) ? $request->to_date : now();
        $res['datewiseData'] = $datewiseData;
            // echo "<pre>";print_r($res);exit;

        return is_mobile($type, "fees/fees_report/datewiseFeesReport", $res, "view");
    } 

    public function getFeesTitle(Request $request){
        $type = $request->input("type");
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        // $standard = $request->standard;
        $heads = $request->heads;
        if(in_array($type,["API","JSON"])){
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

        $data = DB::table('fees_title')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])
        ->whereIn('id',explode(',',$heads))
        ->get()
        ->toArray();

        return $data;
    }

    public function fees_donation_records(Request $request){
        $type = $request->input("type");
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $bankName = 'MISSION';

        $records = DB::table('fees_collect as fc')
            ->select(
                'fc.receipt_no',
                't.pan_card',
                DB::raw("DATE_FORMAT(fc.receiptdate, '%d-%b-%Y') as receiptdate"),
                't.first_name',
                't.middle_name',
                't.last_name',
                't.address',
                'fc.payment_mode',
                'fc.amount'
            )
            ->join('tblstudent as t', 't.id', '=', 'fc.student_id')
            ->join('tblstudent_enrollment as te', function ($join) {
                $join->on('te.student_id', '=', 't.id')
                     ->on('te.syear', '=', 'fc.syear');
            })
            ->where([
                ['fc.sub_institute_id', '=', $sub_institute_id],
                ['fc.syear', '=', $syear],
                ['fc.bank_name', '=', $bankName],
                ['fc.amount', '>', 0],
                ['fc.IS_DELETED', '=', 'N'],
            ])
            ->orderByRaw('CAST(fc.receipt_no AS UNSIGNED)')
            ->get();

        //return is_mobile($type, "fees/fees_report/fees_donation_records", $records, "view");
        return view('fees/fees_report/fees_donation_records', ['records' => $records]);
    }
}
