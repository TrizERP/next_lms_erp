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

        //START Insert or Update into Fees Details Table
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

        //CHECK for existing record
        $studentfeesdetails = tblstudentFeesDetailModel::where([
            'sub_institute_id' => $sub_institute_id,
            'student_id'       => $request->get('student_id'),
        ])->get()->toArray();

        if (count($studentfeesdetails) > 0) { // Update
            $is_registered = '';
            if ($request->get('ac_number') != $studentfeesdetails[0]['ac_number']) {
                //BACKUP Old record and update new Account Number
                $is_registered = 'N';
                $insLogSql = "INSERT INTO tblstudent_bank_detail_log
                (student_id, ac_holder_name, ac_number, bank_name, bank_branch, ifsc_code, is_registered, created_by, AC_TYPE,UMRN,closure_date,status,reason,created_on,registration_date)
                SELECT student_id, ac_holder_name, ac_number, bank_name, bank_branch, ifsc_code, is_registered, '".$user_id."',
                    AC_TYPE,UMRN,closure_date,status,reason, created_on,registration_date
                FROM tblstudent_bank_detail WHERE student_id = '".$request->get('student_id')."'";
                DB::select($insLogSql);
            }

            if ($is_registered != '') {
                $data['is_registered'] = $is_registered;
            }

            tblstudentFeesDetailModel::where('student_id', $request->get('student_id'))->update($data);
        } else {
            tblstudentFeesDetailModel::insert($data);
        }
        //END Insert or Update into Fees Details Table

        //START Insert or Update into Payment Method Mapping Table
        $studentpaymentmapping = tblstudentPaymentMethodMappingModel::where([
            'sub_institute_id' => $sub_institute_id, 'student_id' => $request->get('student_id'),
        ])
            ->get()->toArray();
        if (count($studentpaymentmapping) > 0) {//Update 
            $payment_method = $request->get('payment_method');
            $month_date = $request->get('month_date');
            $month_remark = $request->get('month_remark');

            foreach ($payment_method as $monthid => $method) {
                $remark_value = $date_value = "";
                if (isset ($month_remark[$monthid]) && $month_remark[$monthid] != "") {
                    $remark_value = $month_remark[$monthid] ?? '';
                }

                $pdata = [
                    'student_id'       => $request->get('student_id'),
                    'syear'            => $syear,
                    'sub_institute_id' => $sub_institute_id,
                    'month_id'         => $monthid,
                    'payment_method'   => $method,
                    'remarks'          => $remark_value,
                    'created_by'       => $user_id,
                ];

                if (isset ($month_date[$monthid]) && $month_date[$monthid] != "" && $month_date[$monthid] != null) {
                    $pdata['payment_date'] = date("Y-m-d", strtotime($month_date[$monthid]));
                }

                tblstudentPaymentMethodMappingModel::where([
                    'student_id'       => $request->get('student_id'),
                    'sub_institute_id' => $sub_institute_id,
                    'month_id'         => $monthid,
                ])
                    ->update($pdata);
            }

        } else //Insert
        {
            $payment_method = $request->get('payment_method');
            $month_date = $request->get('month_date');
            $month_remark = $request->get('month_remark');

            foreach ($payment_method as $monthid => $method) {
                $remark_value = $date_value = "";
                if (isset ($month_remark[$monthid]) && $month_remark[$monthid] != "") {
                    $remark_value = $month_remark[$monthid];
                }

                $pdata = array(
                    'student_id'       => $request->get('student_id'),
                    'syear'            => $syear,
                    'sub_institute_id' => $sub_institute_id,
                    'month_id'         => $monthid,
                    'payment_method'   => $method,
                    'remarks'          => $remark_value,
                    'created_by'       => $user_id,
                );

                if (isset ($month_date[$monthid]) && $month_date[$monthid] != "" && $month_date[$monthid] != null) {
                    $pdata['payment_date'] = date("Y-m-d", strtotime($month_date[$monthid]));
                }

                tblstudentPaymentMethodMappingModel::insert($pdata);
            }
        }

        //ENd Insert or Update into Payment Method Mapping Table        


        $res['status_code'] = 1;
        $res['message'] = "Student Fees Details Successfully Updated.";
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
