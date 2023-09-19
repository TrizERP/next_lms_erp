<?php

namespace App\Http\Controllers\fees\fees_cancel;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class feesCancelController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return false|Application|Factory|View|RedirectResponse|string
     */
    /*public function index(Request $request)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $fees_config = DB::table('fees_config_master as fc')
            ->join('fees_receipt_css as frc', function ($join) {
                $join->whereRaw('frc.receipt_id = fc.fees_receipt_template');
            })
            ->selectRaw('fc.* ,frc.css')
            ->where('fc.sub_institute_id', $sub_institute_id)
            ->where('fc.syear', $syear)->get()->toArray();

        if (count($fees_config) > 0) {
            $receipt_css = $fees_config[0]->css;
            $paper_size = $fees_config[0]->fees_receipt_template;
        } else {
            $fees_config = DB::table('fees_receipt_css')->select('css')->where('receipt_id', 'A5')->get();
            $receipt_css = $fees_config[0]->css;
            $paper_size = 'A5';
        }

        $res['status_code'] = "1";
        $res['message'] = "Success";
        $res['receipt_css_data'] = $receipt_css;
        $res['paper_size'] = $paper_size;

        return is_mobile($type, "fees/fees_cancel/index", $res, "view");
    }*/

    public function index(Request $request)
    {
        $type = $request->input('type');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        // Retrieve fees config data with a single query
        $feesConfig = DB::table('fees_config_master as fc')
            ->leftJoin('fees_receipt_css as frc', 'frc.receipt_id', '=', 'fc.fees_receipt_template')
            ->select('fc.fees_receipt_template', 'frc.css')
            ->where('fc.sub_institute_id', $sub_institute_id)
            ->where('fc.syear', $syear)
            ->get()
            ->first();

        if ($feesConfig) {
            $receipt_css = $feesConfig->css;
            $paper_size = $feesConfig->fees_receipt_template;
        } else {
            // If no fees config found, use default values
            $defaultFeesConfig = DB::table('fees_receipt_css')
                ->select('css')
                ->where('receipt_id', 'A5')
                ->first();

            $receipt_css = $defaultFeesConfig->css;
            $paper_size = 'A5';
        }

        $res = [
            'status_code' => '1',
            'message' => 'Success',
            'receipt_css_data' => $receipt_css,
            'paper_size' => $paper_size,
        ];

        return is_mobile($type, 'fees/fees_cancel/index', $res, 'view');
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
     * @param  Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        //
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
     * @return void
     */
    public function edit($id)
    {
        //
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

    public function showFees(Request $request)
    {
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

        $extraSearchArray = $other_extraSearchArray = [];
        $other_extraSearchArrayRaw = " fees_paid_other.is_deleted = 'N'  ";
        $extraSearchArrayRaw = " fees_collect.is_deleted = 'N' ";

        if ($grade != '') {
            $extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
            $other_extraSearchArray['tblstudent_enrollment.grade_id'] = $grade;
        }

        if ($standard != '') {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
            $other_extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }

        if ($division != '') {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
            $other_extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        if ($enrollment_no != '') {
            $extraSearchArray['tblstudent.enrollment_no'] = $enrollment_no;
            $other_extraSearchArray['tblstudent.enrollment_no'] = $enrollment_no;
        }

        if ($receipt_no != '') {
            $extraSearchArray['fees_collect.receipt_no'] = $receipt_no;
            $other_extraSearchArray['fees_paid_other.reciept_id'] = $receipt_no;
        }

        if ($from_date != '') {
            $extraSearchArrayRaw .= "  AND date_format(fees_collect.receiptdate,'%Y-%m-%d') >= '" . $from_date . "'";
            $other_extraSearchArrayRaw .= " AND date_format(fees_paid_other.receiptdate,'%Y-%m-%d') >= '" . $from_date . "'";
        }

        if ($to_date != '') {
            $extraSearchArrayRaw .= "  AND date_format(fees_collect.receiptdate,'%Y-%m-%d') <= '" . $to_date . "'";
            $other_extraSearchArrayRaw .= "  AND date_format(fees_paid_other.receiptdate,'%Y-%m-%d') <= '" . $to_date . "'";
        }

        if ($sub_institute_id == 200) {
            $extraSearchArrayRaw .= " AND fees_collect.standard_id = tblstudent_enrollment.standard_id ";
            //$other_extraSearchArrayRaw .= "  AND date_format(fees_paid_other.created_date,'%Y-%m-%d') <= '".$to_date."'";
        }

        $extraSearchArray['fees_collect.syear'] = $syear;
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $extraSearchArray['fees_collect.sub_institute_id'] = $sub_institute_id;

        $other_extraSearchArray['fees_paid_other.syear'] = $syear;
        $other_extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        $other_extraSearchArray['fees_paid_other.sub_institute_id'] = $sub_institute_id;

        $other_fees_paid = $feesData = tblstudentModel::selectRaw("'OTHER' as fees_type,fees_paid_other.id,fees_paid_other.reciept_id as
            receipt_no,fees_paid_other.paid_fees_html,fees_paid_other.receiptdate,fees_paid_other.payment_mode,fees_paid_other.month_id as month_id,
            SUM(fees_paid_other.actual_amountpaid) as total_amount,CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,
            tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,division.name as
            division_name,tblstudent.enrollment_no,date_format(fees_paid_other.created_date,'%Y-%m-%d %H:%i:%s') as created_on,
            tblstudent.id as student_id")
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
            ->where($other_extraSearchArray)
            ->whereRaw($other_extraSearchArrayRaw)
            ->groupby('fees_paid_other.syear', 'fees_paid_other.reciept_id', 'fees_paid_other.student_id');

        $feesData = tblstudentModel::selectRaw("'REGULAR' as fees_type,fees_collect.id,fees_collect.receipt_no,fees_collect.fees_html,fees_collect.receiptdate,fees_collect.payment_mode ,fees_collect.term_id as month_id,
            SUM(fees_collect.amount) as total_amount,CONCAT_WS(' ',tblstudent.first_name,tblstudent.middle_name,tblstudent.last_name) AS student_name,academic_section.title as grade,standard.name as standard_name,division.name as division_name,tblstudent.enrollment_no,date_format(fees_collect.created_date,'%Y-%m-%d %H:%i:%s') as created_on,tblstudent.id as student_id")
            ->join('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id')
            ->join('academic_section', 'academic_section.id', '=', 'tblstudent_enrollment.grade_id')
            ->join('standard', function ($join) use($marking_period_id){
                $join->on('standard.id', '=', 'tblstudent_enrollment.standard_id');
                // ->when($marking_period_id,function($query) use ($marking_period_id){
                //     $query->where('standard.marking_period_id',$marking_period_id);
                // });
            })
            ->join('division', 'division.id', '=', 'tblstudent_enrollment.section_id')
            ->join('fees_collect', 'fees_collect.student_id', '=', 'tblstudent.id')
            ->where($extraSearchArray)
            ->whereRaw($extraSearchArrayRaw)
            ->groupby('fees_collect.syear', 'fees_collect.receipt_no', 'fees_collect.student_id')
            ->union($other_fees_paid)
            ->get()
            ->toArray();

        if (count($feesData) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No Fees Receipt Found Please Search Again";

            return is_mobile($type, "fees_cancel.index", $res);
        }

        $feesCancelType = DB::table('fees_cancel_type')->pluck('title', 'id');

        $fees_config = DB::table('fees_config_master as fc')
            ->join('fees_receipt_css as frc', function ($join) {
                $join->whereRaw('frc.receipt_id = fc.fees_receipt_template');
            })->selectRaw('fc.* ,frc.css')
            ->where('fc.sub_institute_id', $sub_institute_id)
            ->where('fc.syear', $syear)->get()->toArray();

        if (count($fees_config) > 0) {
            $receipt_css = $fees_config[0]->css;
            $paper_size = $fees_config[0]->fees_receipt_template;
        } else {
            $fees_config = DB::table('fees_receipt_css')->select('css')->where('receipt_id', 'A5')->get()->toArray();
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
        $res['receipt_no'] = $receipt_no;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;
        $res['fees_cancel_type'] = $feesCancelType;

        return is_mobile($type, "fees/fees_cancel/index", $res, "view");
    }

    public function cancelFees(Request $request)
    {
        $type = $request->input('type');
        $receipt_nos_a = $request->input('receipt_no');
        $cancel_type = $request->input('cancel_type');
        $student_id = $request->input('student_id');
        $total_amount = $request->input('totAmt');
        $month_id = $request->input('month_id');
        $cancel_remark = $request->input('cancel_remark');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        // return $receipt_nos;exit;

        if($receipt_nos_a == '') {
            $res['status_code'] = 0;
            $res['message'] = "Please select receipt no to cancel fees";

            return is_mobile($type, "fees_cancel.index", $res);
        }

        foreach ($receipt_nos_a as $key => $value)
        {
            $parts = explode('####', $value);
            $student_ids = $parts[1];
            $receipt_nos = $parts[0];

            $student_id_value = $student_id[$student_ids];
            //echo "<pre>";print_r($student_id_value);exit;
            if (in_array($student_id_value, $parts))
            {
                $extraSearchArray1['tblstudent_enrollment.syear'] = $syear;
                $extraSearchArray1['fees_paid_other.syear'] = $syear;
                $extraSearchArray1['fees_paid_other.is_deleted'] = 'N';
                $extraSearchArray1['fees_paid_other.sub_institute_id'] = $sub_institute_id;
                $extraSearchArray1['fees_paid_other.reciept_id'] = $receipt_nos;
                $extraSearchArray1['fees_paid_other.student_id'] = $student_id_value;
                $extraSearchArray1['fees_paid_other.actual_amountpaid'] = $total_amount[$receipt_nos] ?? '';

                $feesDetails1 = DB::table('fees_paid_other')->selectRaw("fees_paid_other.*,SUM(fees_paid_other.actual_amountpaid) as total_amount,
                tblstudent_enrollment.standard_id")
                ->join('tblstudent_enrollment', 'fees_paid_other.student_id', '=', 'tblstudent_enrollment.student_id')
                ->where($extraSearchArray1)->get()->toArray();

                //echo "<pre>";print_r($feesDetails1);exit;

                if(isset($feesDetails1) && $feesDetails1[0]->student_id != null && !empty($feesDetails1))
                {
                    $feesDetails = $feesDetails1[0];

                    $feesCancelLog['reciept_id'] = $receipt_nos;
                    $feesCancelLog['syear'] = $syear;
                    $feesCancelLog['sub_institute_id'] = $sub_institute_id;
                    $feesCancelLog['student_id'] = $feesDetails->student_id;
                    $feesCancelLog['standard_id'] = $feesDetails->standard_id;
                    $feesCancelLog['term_id'] = $feesDetails->month_id;
                    $feesCancelLog['amountpaid'] = $feesDetails->total_amount;
                    $feesCancelLog['received_date'] = $feesDetails->receiptdate;
                    $feesCancelLog['cancel_date'] = date('Y-m-d H:i:s');
                    $feesCancelLog['cancel_type'] = $cancel_type[$receipt_nos] ?? null;
                    $feesCancelLog['cancel_remark'] = $cancel_remark[$receipt_nos] ?? null;
                    $feesCancelLog['cancelled_by'] = $user_id;
                    $feesCancelLog['ip_address'] = $_SERVER['REMOTE_ADDR'];
                    // print_r($feesCancelLog);exit;

                    DB::table('fees_cancel')->insert($feesCancelLog);

                    DB::table('fees_paid_other')
                    ->where(['reciept_id' => $receipt_nos, 'syear' => $syear, 'sub_institute_id' => $sub_institute_id, 'student_id' => $feesDetails->student_id])
                    ->update(['is_deleted' => 'Y']);
                }
                else
                {
                    $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
                    $extraSearchArray['fees_collect.syear'] = $syear;
                    $extraSearchArray['fees_collect.is_deleted'] = 'N';
                    $extraSearchArray['fees_collect.sub_institute_id'] = $sub_institute_id;
                    $extraSearchArray['fees_collect.receipt_no'] = $receipt_nos;
                    $extraSearchArray['fees_collect.student_id'] = $student_id[$student_ids];

                    $feesDetails = DB::table('fees_collect')->selectRaw("fees_collect.*,SUM(fees_collect.amount) as total_amount,tblstudent_enrollment.standard_id")
                    ->join('tblstudent_enrollment', 'fees_collect.student_id', '=', 'tblstudent_enrollment.student_id')
                    ->where($extraSearchArray)->get()->toArray();
                   //echo "<pre>";print_r($feesDetails);
                    $feesCancelLog = [];
                    foreach($feesDetails as $feesDetails)
                    {
                        $feesCancelLog['reciept_id'] = $receipt_nos;
                        $feesCancelLog['syear'] = $syear;
                        $feesCancelLog['sub_institute_id'] = $sub_institute_id;
                        $feesCancelLog['student_id'] = $feesDetails->student_id;
                        $feesCancelLog['standard_id'] = $feesDetails->standard_id;
                        $feesCancelLog['term_id'] = $feesDetails->term_id;
                        $feesCancelLog['amountpaid'] = $feesDetails->total_amount;
                        $feesCancelLog['received_date'] = $feesDetails->receiptdate;
                        $feesCancelLog['cancel_date'] = date('Y-m-d H:i:s');
                        //$feesCancelLog['cancel_type'] = $cancel_type[$receipt_nos."/".$student_id_value] ?? null;
                        $feesCancelLog['cancel_type'] = str_replace('/', '', $cancel_type[$receipt_nos."/".$student_id_value]) ?? null;
                        $feesCancelLog['cancel_remark'] = $cancel_remark[$receipt_nos."/".$student_id_value] ?? null;
                        $feesCancelLog['cancelled_by'] = $user_id;
                        $feesCancelLog['ip_address'] = $_SERVER['REMOTE_ADDR'];
                        // print_r($cancel_remark[$value]);
                        // print_r($feesCancelLog);exit;

                        //echo("<pre>");print_r($feesCancelLog);

                        if ($feesDetails->student_id !== null) {
                            // Your insertion logic here
                            DB::table('fees_cancel')->insert($feesCancelLog);
                        }

                        $modified_receipt_key = str_replace('/', '', $receipt_nos);
                        $feesCancelLog['cancel_type'] = $cancel_type[$modified_receipt_key."/".$student_id_value] ?? null;

                        DB::table('fees_collect')
                            ->where(['receipt_no' => $receipt_nos, 'student_id' => $student_id[$student_ids], 'syear' => $syear, 'sub_institute_id' => $sub_institute_id, 'student_id' => $feesDetails->student_id, 'standard_id' => $feesDetails->standard_id])
                            ->update(['is_deleted' => 'Y', 'is_waved' => $feesCancelLog['cancel_type']]);
                    }
                }
            }
            else
            {

            }
        }

        // print_r($feesCancelLog);

        // exit;

        $res['status_code'] = 1;
        $res['message'] = "Fees Deleted Successfully";

        return is_mobile($type, "fees_cancel.index", $res);
    }
}
