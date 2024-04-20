<?php

namespace App\Http\Controllers\fees\cheque_reconciliation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Models\fees\fees_collect\fees_collect;
use App\Models\fees\fees_cancel\feesCancelModel;
use DB;

class ChequeReconciliationController extends Controller
{
    //
    public function index(Request $request)
    {
        $res = "";
        $type = $request->input('type');
        return is_mobile($type, 'fees.cheque_reconciliation.show', $res, 'view');
    }

    public function create(Request $request)
    {
        // return session()->get('term_id');exit;
        $from_date1 = $request->from_date;
        $from_date = date("Y-m-d", strtotime($from_date1));
        $marking_period_id = session()->get('term_id');
        $to_date1 = $request->to_date;
        $to_date = date("Y-m-d", strtotime($to_date1));

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $type = $request->input('type');

        $query = DB::table('fees_collect as fc')
            ->join('tblstudent as s', function ($join) {
                $join->whereRaw('s.id = fc.student_id');
            })->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw('se.student_id = fc.student_id AND se.sub_institute_id = fc.sub_institute_id');
            })->Join('academic_year as t', function ($join) {
                $join->whereRaw('t.term_id = se.term_id AND t.sub_institute_id = se.sub_institute_id');
            })->join('standard as st', function ($join) use ($marking_period_id) {
                $join->on('st.id', '=', 'se.standard_id');
                    // ->when($marking_period_id, function ($query) use ($marking_period_id) {
                    //     $query->where('st.marking_period_id', $marking_period_id);
                    // });
            })->leftJoin('division as d', function ($join) {
                $join->whereRaw('d.id = se.section_id');
            })->selectRaw("fc.id as collect_id,fc.standard_id,fc.student_id,fc.term_id,fc.created_by,fc.payment_mode,fc.bank_branch,fc.receiptdate,fc.receipt_no,fc.cheque_no,fc.bank_name,fc.cheque_date,fc.cheque_bank_name,fc.amount,fc.is_deleted,fc.fine,fc.fees_discount,fc.is_waved,fc.created_by,s.enrollment_no,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,s.mobile,se.roll_no,st.medium,st.name as standard_name,d.id,d.name as divison_name,t.title as term_name,se.term_id as sterm_id")
            ->whereRaw('fc.payment_mode = "cheque" AND fc.sub_institute_id = "' . $sub_institute_id . '" AND fc.syear = "' . $syear . '" AND fc.is_deleted = "N" ')
            ->whereBetween("fc.cheque_date", [$from_date, $to_date])->get()->toArray();

        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['details'] = $query;
        // echo "<pre>";print_r($query);exit;
        return is_mobile($type, 'fees.cheque_reconciliation.show', $res, 'view');
        // return $query;
    }

    public function store(Request $request)
    {
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');

        $remark = array_values(array_filter($request->remark)) ?? [];
        $mode = array_values(array_filter($request->mode)) ?? [];
        $condate = array_values(array_filter($request->confirm_date)) ?? [];
        $cheque = $request->cheque ?? [];

        // Create an array of data to update for each ID
        $get_index = array_filter($request->mode);

        foreach ($get_index as $key => $id) {
        // echo "<pre>";print_r($key);  
            $receipt_id[] = $request->receipt_id[$key] ?? null;
            $student_id[] = $request->student_id[$key] ?? null;
            $standard_id[] = $request->standard_id[$key] ?? null;
            $term_id[] = $request->term_id[$key] ?? null;
            $amountpaid[] = $request->amountpaid[$key] ?? null;
            $recivied_date[] = $request->received_date[$key] ?? null;
            $cancel_type[] = $request->cancel_type[$key] ?? null;
            $cancel_by[] = $request->cancel_by[$key] ?? null;
        }
        // foreach ($mode as $element) {

            // if($element=="clear"){
        if (in_array("clear", $mode) && !empty($cheque)) {
            // return "Clear";exit;
            $idsToUpdate = $request->cheque;

                // Create an array of data to update for each ID
            $dataToUpdate = [];
            foreach ($idsToUpdate as $id) {
                $index = array_search($id, $request->cheque);
                $dataToUpdate[] = [
                    'id' => $id,
                    'is_deleted' => 'Y',
                    'remark' => $remark[$index] ?? null,
                ];
            }
            foreach ($dataToUpdate as $key => $data) {

                $query = DB::table('fees_collect')
                    ->where('id', $data['id'])
                    ->update([
                        "is_deleted" => $data['is_deleted'],
                        "remarks" => $data['remark'],

                    ]);
            }
            if ($query == 1) {
                $ind = "success";

                $mes = "Updated Successfully";
            } else {
                $ind = "failed";

                $mes = "Failed To Updated";
            }
        }

        if (in_array("return", $mode) && !empty($cheque)) {
                // return "return";exit;
            $idsToUpdate = $request->cheque;

                // Create an array of data to update for each ID 59969
            $dataToUpdate = [];

            foreach ($idsToUpdate as $key => $id) {
                $dataToUpdate[] = [
                    "id" => $id,
                    "reciept_id" => $receipt_id[$key] ?? null,
                    "syear" => $syear,
                    "sub_institute_id" => $sub_institute_id,
                    "student_id" => $student_id[$key] ?? null,
                    "standard_id" => $standard_id[$key] ?? null,
                    "term_id" => $term_id[$key] ?? null,
                    "amountpaid" => $amountpaid[$key] ?? null,
                    "received_date" => $recivied_date[$key] ?? null,
                    "cancel_date" => $condate[$key] ?? date("Y-m-d H:i:s"),
                    "cancel_type" => $cancel_type[$key] ?? null,
                    "cancel_remark" => $remark[$key] ?? null,
                    "cancel_by" => $cancel_by[$key] ?? null,
                ];
            }
            foreach ($dataToUpdate as $key => $data) {
                $query = feesCancelModel::create([
                    "reciept_id" => $data['reciept_id'],
                    "syear" => $data['syear'],
                    "sub_institute_id" => $data['sub_institute_id'],
                    "student_id" => $data['student_id'],
                    "standard_id" => $data['standard_id'],
                    "term_id" => $data['term_id'],
                    "amountpaid" => $data['amountpaid'],
                    "received_date" => $data['received_date'],
                    "cancel_date" => $data['cancel_date'],
                    "cancel_type" => $data['cancel_type'],
                    "cancel_remark" => $data['cancel_remark'],
                    "cancelled_by" => $data['cancel_by'],
                ]);
            }
            if ($dataToUpdate) {
                $ind = "success";
                $mes = "Added Successfully";

            } else {
                $ind = "failed";
                $mes = "Failed to Add";
            }
        }
        if (empty($mode) || empty($cheque)) {
            $ind = "failed";
            $mes = "Please Select Payment Option or Checkbox";
        }
        $res = "";
        $type = $request->input('type');
        return back()->with($ind, $mes);
    }

    public function show_details(Request $request)
    {

        $res = "";
        $type = $request->input('type');
        return is_mobile($type, 'fees.cheque_reconciliation.report', $res, 'view');
    }
    public function search_details(Request $request)
    {
        $from_date1 = $request->from_date;
        $from_date = date("Y-m-d", strtotime($from_date1));
        $marking_period_id = session()->get('term_id');
        $to_date1 = $request->to_date;
        $to_date = date("Y-m-d", strtotime($to_date1));

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $type = $request->input('type');

        if ($request->mode == "clear") {
            $query = fees_collect::select('id', 'student_id', 'standard_id', 'payment_mode', 'syear', 'sub_institute_id')->whereRaw('payment_mode = "cheque" AND sub_institute_id = "' . $sub_institute_id . '" AND syear = "' . $syear . '" AND is_deleted = "Y" ')->whereBetween("cheque_date", [$from_date, $to_date])->get()->toArray();
            $query = DB::table('fees_collect as fc')
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw('s.id = fc.student_id');
                })->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = fc.student_id AND se.sub_institute_id = fc.sub_institute_id');
                })->Join('academic_year as t', function ($join) {
                    $join->whereRaw('t.term_id = se.term_id AND t.sub_institute_id = se.sub_institute_id');
                })->join('standard as st', function ($join) use ($marking_period_id) {
                    $join->on('st.id', '=', 'se.standard_id');
                        // ->when($marking_period_id, function ($query) use ($marking_period_id) {
                        //     $query->where('st.marking_period_id', $marking_period_id);
                        // });
                })->leftJoin('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id');
                })->selectRaw("fc.id as collect_id,fc.standard_id,fc.student_id,fc.term_id,fc.created_by,fc.payment_mode,fc.bank_branch,fc.receiptdate,fc.receipt_no,fc.cheque_no,fc.bank_name,fc.cheque_date,fc.cheque_bank_name,fc.amount as amountpaid,fc.payment_mode as cancel_type,fc.created_date as cancel_date,fc.remarks as cancel_remark,fc.is_deleted,fc.fine,fc.fees_discount,fc.is_waved,fc.created_by,s.enrollment_no,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,s.mobile,se.roll_no,st.medium,st.name as standard_name,d.id,d.name as divison_name,t.title as term_name,se.term_id as sterm_id")
                ->whereRaw('fc.payment_mode = "cheque" AND fc.sub_institute_id = "' . $sub_institute_id . '" AND fc.syear = "' . $syear . '" AND fc.is_deleted = "Y" ')
                ->whereBetween("fc.cheque_date", [$from_date, $to_date])->get()->toArray();

        } else {
            $query = DB::table('fees_cancel as fc')
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw('s.id = fc.student_id');
                })->Join('fees_collect as fct', function ($join) {
                    $join->whereRaw('fct.student_id = fc.student_id');
                })->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = fc.student_id AND se.sub_institute_id = fc.sub_institute_id');
                })->Join('academic_year as t', function ($join) {
                    $join->whereRaw('t.term_id = se.term_id AND t.sub_institute_id = se.sub_institute_id');
                })->join('standard as st', function ($join) use ($marking_period_id) {
                    $join->on('st.id', '=', 'se.standard_id');
                        // ->when($marking_period_id, function ($query) use ($marking_period_id) {
                        //     $query->where('st.marking_period_id', $marking_period_id);
                        // });
                })->leftJoin('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id');
                })->selectRaw("fc.*,fct.cheque_bank_name,fct.bank_branch,fct.cheque_no,fct.cheque_date,s.enrollment_no,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,s.mobile,se.roll_no,st.medium,st.name as standard_name,d.id,d.name as divison_name,t.title as term_name,se.term_id as sterm_id")
                ->where(['fc.sub_institute_id' => $sub_institute_id, 'fc.syear' => $syear])
                ->whereBetween("fc.cancel_date", [$from_date, $to_date])
                ->get()->toArray();
        }
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['details'] = $query;
        $res['mode'] = $request->mode;
        // echo "<pre>";print_r($query1);exit;
        return is_mobile($type, 'fees.cheque_reconciliation.report', $res, 'view');

    }

}
