<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentFeesDetailModel;
use App\Models\student\tblstudentPaymentMethodMappingModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class tblstudentFeesDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        //
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
     * @return Response
     */
    public function store(Request $request)
{
    $sub_institute_id = $request->session()->get('sub_institute_id');
    $user_id = $request->session()->get('user_id');
    $syear = $request->session()->get('syear');
    $type = $request->input('type');

    // START Insert or Update into Fees Details Table
    $data = [
        'student_id'        => $request->get('student_id'),
        'ac_type'           => $request->get('ac_type'),
        'ac_holder_name'    => $request->get('ac_holder_name'),
        'ac_number'         => $request->get('ac_number'),
        'bank_name'         => $request->get('bank_name'),
        'bank_branch'       => $request->get('bank_branch'),
        'ifsc_code'         => $request->get('ifsc_code'),
        'registration_date' => $request->get('registration_date'),
        'UMRN'              => $request->get('UMRN'),
        'closure_date'      => $request->get('date_'),
        'status'            => $request->get('status'),
        'reason'            => $request->get('rejected_reason'),
        'sub_institute_id'  => $sub_institute_id,
        'created_by'        => $user_id,
    ];

    // CHECK for existing record
    $studentfeesdetails = tblstudentFeesDetailModel::where([
        'sub_institute_id' => $sub_institute_id,
        'student_id'       => $request->get('student_id'),
    ])->get()->toArray();

    if (count($studentfeesdetails) > 0) { // Update
        $is_registered = '';
        if ($request->get('ac_number') != $studentfeesdetails[0]['ac_number']) {
            // BACKUP Old record and update new Account Number
            $is_registered = 'N';

            $insLogData = DB::table('tblstudent_bank_detail')
                ->where('student_id', $request->input('student_id'))
                ->get();

            if ($insLogData->isNotEmpty()) {
                $insLogData = $insLogData->first();
                $insLogId = DB::table('tblstudent_bank_detail_log')->insertGetId([
                    'student_id' => $insLogData->student_id,
                    'ac_holder_name' => $insLogData->ac_holder_name,
                    'ac_number' => $insLogData->ac_number,
                    'bank_name' => $insLogData->bank_name,
                    'bank_branch' => $insLogData->bank_branch,
                    'ifsc_code' => $insLogData->ifsc_code,
                    'is_registered' => $insLogData->is_registered,
                    'created_by' => $user_id,
                    'AC_TYPE' => $insLogData->ac_type,
                    'UMRN' => $insLogData->UMRN,
                    'closure_date' => $insLogData->closure_date,
                    'status' => $insLogData->status,
                    'reason' => $insLogData->reason,
                    'created_on' => $insLogData->created_on,
                    'registration_date' => $insLogData->registration_date,
                ]);
            }
        }

        if ($is_registered != '') {
            $data['is_registered'] = $is_registered;
        }

        tblstudentFeesDetailModel::where('student_id', $request->get('student_id'))->update($data);
    } else {
        tblstudentFeesDetailModel::insert($data);
    }
    // END Insert or Update into Fees Details Table

    // START Insert or Update into Payment Method Mapping Table 25-09-2024 start
    $payment_method = $request->get('payment_method');
    $month_date = $request->get('month_date');
    $month_remark = $request->get('month_remark');

    // ✅ FIXED: Run foreach only if payment_method is valid array
    if (!empty($payment_method) && is_array($payment_method)) {
        foreach ($payment_method as $monthid => $method) {
            // check entries exists or not with month and student_id
            $checkArr = [
                'sub_institute_id' => $sub_institute_id,
                'student_id'       => $request->get('student_id'),
                'month_id'         => $monthid
            ];
            $checkData = tblstudentPaymentMethodMappingModel::where($checkArr)->get()->toArray();

            // make data array to insert or update
            $remark_value = $date_value = null;
            if (isset($month_remark[$monthid]) && $month_remark[$monthid] != "") {
                $remark_value = $month_remark[$monthid];
            }

            if (isset($month_date[$monthid]) && $month_date[$monthid] != "") {
                $date_value = date("Y-m-d", strtotime($month_date[$monthid]));
            }

            $pdata = [
                'student_id'       => $request->get('student_id'),
                'syear'            => $syear,
                'sub_institute_id' => $sub_institute_id,
                'month_id'         => $monthid,
                'payment_method'   => $method,
                'payment_date'     => $date_value,
                'remarks'          => $remark_value,
                'created_by'       => $user_id,
            ];

            // if data exists then update data into table 
            if (!empty($checkData)) {
                tblstudentPaymentMethodMappingModel::where([
                    'student_id'       => $request->get('student_id'),
                    'sub_institute_id' => $sub_institute_id,
                    'month_id'         => $monthid,
                ])->update($pdata);
            }
            // if data not exists then insert data into table 
            else {
                tblstudentPaymentMethodMappingModel::insert($pdata);
            }
        }

        // ✅ success message for payment method mapping
        $payment_message = "Student Updated Successfully";

    } else {
        // ✅ skip message if no mapping data found
        $payment_message = "Student Updated Successfully .";
    }
    // 25-09-2024 end 

    // ✅ final response
    $res['status_code'] = 1;
    $res['message'] = $payment_message;
    $res['data'] = $data;

    return is_mobile($type, "search_student.index", $res);
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
}
