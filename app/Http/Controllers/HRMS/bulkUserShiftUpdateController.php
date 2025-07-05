<?php

namespace App\Http\Controllers\HRMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;
use Illuminate\Support\Facades\Validator;
use App\Models\HRMS\userShiftMaster;
use App\Models\HRMS\userShiftRecord;
use function App\Helpers\employeeDetails;
use DB;

class bulkUserShiftUpdateController extends Controller
{
    use GetsJwtToken;  // used to check security token only for API

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if(in_array($type,["API","JSON"])){
            try {
                 // used to check security token only for API
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                  $validate = Validator::make($request->all(),[
                    'sub_institute_id'=>'required',
                    'syear'=>'required',
                ]);

                if($validate->fails()){
                    return response()->json(['status' =>0,'message'=>$validate->errors()->first()]);
                }

                $sub_institute_id = $request->sub_institute_id;
                $syear = $request->syear;
                
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }           
        }

        $res['shift_data'] = userShiftMaster::where('sub_institute_id',$sub_institute_id)->whereNull('deleted_at')->orderBy('id','DESC')->get()->toArray();

        $res['status'] = 1;
        $res['message'] = 'success';

        return is_mobile($type, "HRMS.user_shifts.shiftUpdate", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

        if(in_array($type,["API","JSON"])){
            try {
                 // used to check security token only for API
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                  $validate = Validator::make($request->all(),[
                    'sub_institute_id'=>'required',
                    'employee_id'=>'required',
                    'department_id'=>'required',
                ]);

                if($validate->fails()){
                    return response()->json(['status' =>0,'message'=>$validate->errors()->first()]);
                }

                $sub_institute_id = $request->sub_institute_id;
                
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }           
        }

        $employee_id= ($request->emp_id!=0) ? implode(',',$request->emp_id) : '';
        $department_id= ($request->department_id!=0) ? implode(',',$request->department_id) : '';

        $res['employeeLists']= employeeDetails($sub_institute_id,$employee_id,1,$department_id);

        $res['shiftData'] = userShiftMaster::where('sub_institute_id',$sub_institute_id)->where('id',$request->shift_id)->whereNull('deleted_at')->first();

        $res['shift_data'] = userShiftMaster::where('sub_institute_id',$sub_institute_id)->whereNull('deleted_at')->orderBy('id','DESC')->get()->toArray();

        $res['department_id'] = $request->department_id;
        $res['selEmp'] = $request->emp_id;
        $res['shift_id'] = $request->shift_id;
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, "HRMS.user_shifts.shiftUpdate", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $user_id = session()->get('user_id');

        if(in_array($type,["API","JSON"])){
            try {
                 // used to check security token only for API
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
                $validate = Validator::make($request->all(), [
                    'sub_institute_id' => 'required',
                    'user_id' => 'required',
                    'employees' => 'required|array|min:1',
                    'employees.*' => 'required|integer',
                    'start_time'=>'required',
                    'end_time'=>'required',
                ]);

                if($validate->fails()){
                    return response()->json(['status' =>0,'message'=>$validate->errors()->first()]);
                }

                $sub_institute_id = $request->sub_institute_id;
                $user_id = $request->user_id;
                
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }           
        }

        $employees = $request->employees;
        $start_time = $request->start_time;
        $end_time = $request->end_time;
        $i=0;
        foreach ($employees as $key => $empId) {
            $getEmployee = DB::table('tbluser')->where('id',$empId)->first();
            $days = ['monday','tuesday','wednesday','thursday','friday','saturday'];
            // $updateData['id'] = $empId;
            //          [monday_in_date] => 
            // [monday_out_date] => 
            if(isset($getEmployee) && isset($getEmployee)){
                $startData = $endData = [];
                foreach ($days as $key => $value) {
                    if(isset($getEmployee->{$value.'_in_date'})){
                        $startData[$value.'_in_date'] = $getEmployee->{$value.'_in_date'};
                    }
                    if(isset($getEmployee->{$value.'_out_date'})){
                        $endData[$value.'_out_date'] = $getEmployee->{$value.'_out_date'};
                    }
                    $updateData[$value.'_in_date'] = $start_time;
                    $updateData[$value.'_out_date'] = $end_time;
                }
                // echo "<pre>";print_r($startData);exit;
                // add previous records in shift_record table
                if(!empty($startData) || !empty($endData)){
                    $records = [
                            'department_id'=>$getEmployee->department_id,
                            'employee_id'=>$getEmployee->id,
                            'start_data'=>json_encode($startData),
                            'end_data'=>json_encode($endData),
                            'created_by'=>$user_id,
                            'created_at'=>now(),
                        ];
                    userShiftRecord::insert($records); 
                }
                // update shift time
                $update = DB::table('tbluser')->where('id',$getEmployee->id)->update($updateData);
                if($update){
                    $i++;
                }
            }
            // echo "<pre>";print_r($updateData);exit;
        }

        $res['status'] = 0;
        $res['message']='Failed to Update !';

        if($i>0){
            $res['status']=1;
            $res['message']='Updated Successfully !';
        }

        return is_mobile($type, "user_bulk_shift_update.store", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
