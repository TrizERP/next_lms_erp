<?php

namespace App\Http\Controllers\hrms;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use App\Traits\Helpers;
use App\Models\AuditLog;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Support\Facades\Validator;
use DB;

class HrmsLeaveController extends Controller
{
    //
    use GetsJwtToken;

    public function index(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
    
        if($type=="API"){
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = $request->syear;
        }

        $res['departmentData']=DB::table('hrms_leave_allocation as hla')
                        ->join('hrms_departments as hd',function($join) use($sub_institute_id){
                            $join->on('hd.id','=','hla.department_id')->where('hd.sub_institute_id',$sub_institute_id)->where('status',1)->whereNull('deleted_at');
                        })
                        ->join('hrms_leave_types as hlt','hlt.id','=','hla.leave_type_id')
                        ->selectRaw('hla.*,hlt.*,hd.*,hla.id as id')
                        ->where('hla.sub_institute_id',$sub_institute_id)
                        ->whereNull('hla.employee_id')
                        ->where('hla.year',$syear)->get()->toArray();

        $res['employeeData']=DB::table('hrms_leave_allocation as hla')
                        ->join('hrms_departments as hd',function($join) use($sub_institute_id){
                            $join->on('hd.id','=','hla.department_id')->where('hd.sub_institute_id',$sub_institute_id)->where('status',1)->whereNull('deleted_at');
                        })
                        ->join('tbluser as tu','tu.id','=','hla.employee_id')
                        ->join('hrms_leave_types as hlt','hlt.id','=','hla.leave_type_id')
                        ->selectRaw('hla.*,hlt.*,hd.*,hla.id as id,concat_ws(" ",COALESCE(tu.first_name,"-"),COALESCE(tu.middle_name,"-"),COALESCE(tu.last_name,"-")) as employee_name,tu.employee_no')
                        ->where('hla.sub_institute_id',$sub_institute_id)
                        ->whereNotNull('hla.employee_id')
                        ->where('hla.year',$syear)->get()->toArray();

        return is_mobile($type, "HRMS.hrms_leave.hrms_leave_allocation.show", $res, "view");
    }

    public function create(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
    
        if($type=="API"){
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = $request->syear;
        }

        $res['departments'] = DB::table('hrms_departments')->where('sub_institute_id',$sub_institute_id)->where('status',1)->whereNull('deleted_at')->pluck('department','id');
        $res['leave_types'] = DB::table('hrms_leave_types')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->pluck('leave_type','id');
        $res['years']= Helpers::getPairYears();
        $res['selYear'] = date('Y');
        $res['view'] = $request->view;

        return is_mobile($type, "HRMS.hrms_leave.hrms_leave_allocation.add", $res, "view");
    }

    public function store(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $formType = $request->has('emp_id') && !empty($request->emp_id) ? 'employee' : 'department';
        $res['formType'] = $formType;

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
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = $request->get('syear');
        }

        $res['department_id'] = $department_ids = $request->department_id;
        $res['leave_type_ids'] = $leave_type_ids = $request->leave_type_ids;
        $res['year'] = $year = $request->year;
        $res['days'] = $days = $request->days;
        $res['emp_id'] = $emp_ids = $request->emp_id ?? [];

        $validator = Validator::make($request->all(), [
            'leave_type_ids' => 'required|array',
            'year' => 'required',
            'days' => 'required',
        ]);

        if ($validator->fails()) {
            $res['status_code'] = 0;
            $res['message'] = $validator->messages()->first();
            return is_mobile($type, "designation_leave.index", $res);
        }

        $insert = 0;
        $departmentArray = is_array($department_ids) ? $department_ids : [$department_ids];
        $employeeArray = is_array($emp_ids) ? $emp_ids : [];
        
        if($formType=="department"){
            if(in_array("All", $departmentArray)){
                $departmentAll = DB::table('hrms_departments')->where('sub_institute_id',$sub_institute_id)->where('status',1)->whereNull('deleted_at')->get()->toArray();
                foreach($departmentAll as $value){
                    foreach($leave_type_ids as $leaveTypeId){
                        $insert += $this->insertData($value->id, $leaveTypeId, $year, $days, $sub_institute_id);
                    }
                }
            } else {
                foreach($departmentArray as $deptId){
                    foreach($leave_type_ids as $leaveTypeId){
                        $insert += $this->insertData($deptId, $leaveTypeId, $year, $days, $sub_institute_id);
                    }
                }
            }
        }
        elseif($formType=="employee" && !empty($employeeArray)){
            foreach($employeeArray as $empId){
                $empDeptId = DB::table('tbluser')->where('id', $empId)->value('department_id');
                
                foreach($leave_type_ids as $leaveTypeId){
                    $insert += $this->insertData($empDeptId, $leaveTypeId, $year, $days, $sub_institute_id, $empId);
                }
            }
        }

        if($insert==0){
            $res['status_code'] = 0;
            $res['message']="Failed to Add";
        }else{
            $res['status_code'] = 1;
            $res['message']="Added Successfully";
        }
        return is_mobile($type, "designation_leave.index", $res);
    }

    public function insertData($department_id, $leave_type_id, $year, $days, $sub_institute_id, $emp_id = null)
    {
        if(is_null($emp_id)){
            $check = DB::table('hrms_leave_allocation')
                ->where('sub_institute_id', $sub_institute_id)
                ->where(['department_id' => $department_id, 'year' => $year, 'leave_type_id' => $leave_type_id])
                ->whereNull('employee_id')
                ->first();
            
            if(empty($check)){
                $newId = DB::table('hrms_leave_allocation')->insertGetId([
                    'department_id' => $department_id,
                    'leave_type_id' => $leave_type_id,
                    'year' => $year,
                    'value' => $days,
                    'sub_institute_id' => $sub_institute_id,
                    'created_at' => now()
                ]);

                AuditLog::record([
                    'module' => 'hrms',
                    'action' => 'leave_allocation_added',
                    'entity_type' => 'hrms_leave_allocation',
                    'entity_id' => $newId,
                    'new_values' => [
                        'department_id' => $department_id,
                        'leave_type_id' => $leave_type_id,
                        'year' => $year,
                        'value' => $days,
                        'sub_institute_id' => $sub_institute_id,
                    ],
                ]);
                return 1;
            }
        }
        else{
            $check = DB::table('hrms_leave_allocation')
                ->where('sub_institute_id', $sub_institute_id)
                ->where(['department_id' => $department_id, 'employee_id' => $emp_id, 'year' => $year, 'leave_type_id' => $leave_type_id])
                ->first();
            
            if(empty($check)){
                $newId = DB::table('hrms_leave_allocation')->insertGetId([
                    'department_id' => $department_id,
                    'employee_id' => $emp_id,
                    'leave_type_id' => $leave_type_id,
                    'year' => $year,
                    'value' => $days,
                    'sub_institute_id' => $sub_institute_id,
                    'created_at' => now()
                ]);

                AuditLog::record([
                    'module' => 'hrms',
                    'action' => 'leave_allocation_added',
                    'entity_type' => 'hrms_leave_allocation',
                    'entity_id' => $newId,
                    'new_values' => [
                        'department_id' => $department_id,
                        'employee_id' => $emp_id,
                        'leave_type_id' => $leave_type_id,
                        'year' => $year,
                        'value' => $days,
                        'sub_institute_id' => $sub_institute_id,
                    ],
                ]);
                return 1;
            }
        }

        return 0;
    }

    public function edit(Request $request,$id){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
    
        if($type=="API"){
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = $request->syear;
        }
        $res['editData'] = DB::table('hrms_leave_allocation')->where('id',$id)->first();
        $res['departments'] = DB::table('hrms_departments')->where('sub_institute_id',$sub_institute_id)->where('status',1)->whereNull('deleted_at')->pluck('department','id');
        $res['leave_types'] = DB::table('hrms_leave_types')->where('sub_institute_id',$sub_institute_id)->orderBy('sort_order')->pluck('leave_type','id');
        $res['years']= Helpers::getPairYears();
        $res['view'] = $request->view;
        return is_mobile($type, "HRMS.hrms_leave.hrms_leave_allocation.edit", $res, "view");
    }

    public function update(Request $request,$id){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $formType = $request->formType;
    
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
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = $request->get('syear');
        }

        $res['department_ids'] = $department_ids = $request->department_id;
        $res['leave_type_ids'] = $leave_type_ids = $request->leave_type_ids;
        $res['year'] = $year = $request->year;
        $res['days'] = $days = $request->days;

        $validator = Validator::make($request->all(), [
            'formType' => 'required|in:department,employee',
            'department_id' => 'required',
            'leave_type_ids' => 'required|array',
            'year' => 'required',
            'days' => 'required',
        ]);

        if ($validator->fails()) {
            $res['status_code'] = 0;
            $res['message'] = $validator->messages()->first();
            return is_mobile($type, "designation_leave.index", $res);
        }

        $updateData = null;

        if($formType=="department"){
            $updateData = [
                'department_id'=>$department_ids,
                'leave_type_id'=>$leave_type_ids,
                'year'=>$year,
                'value'=>$days,
                'sub_institute_id'=>$sub_institute_id,
                'updated_at'=>now()
            ];
            $update = DB::table('hrms_leave_allocation')->where('id',$id)->update($updateData);
        }
        else if($formType=="employee"){
            $updateData = [
                'department_id'=>$department_ids,
                'employee_id'=>$request->emp_id ?? 0,
                'leave_type_id'=>$leave_type_ids,
                'year'=>$year,
                'value'=>$days,
                'sub_institute_id'=>$sub_institute_id,
                'updated_at'=>now()
            ];
            $update = DB::table('hrms_leave_allocation')->where('id',$id)->update($updateData);
        }

        if($update){
            $res['status_code'] = 1;
            $res['message'] = "Updated SuccessFully";

            AuditLog::record([
                'module' => 'hrms',
                'action' => 'leave_allocation_updated',
                'entity_type' => 'hrms_leave_allocation',
                'entity_id' => $id,
                'new_values' => $updateData,
            ]);
        }else{
            $res['status_code'] = 0;
            $res['message'] = "Failed to Update";
        }
        return is_mobile($type, "designation_leave.index", $res);
    }

    public function destroy(Request $request,$id){
        $type=$request->type;

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
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = $request->get('syear');
        }

        $delete = DB::table('hrms_leave_allocation')->where('id',$id)->delete();

        if ($delete) {
            AuditLog::record([
                'module' => 'hrms',
                'action' => 'leave_allocation_deleted',
                'entity_type' => 'hrms_leave_allocation',
                'entity_id' => $id,
            ]);
        }

        $res['status_code'] = 1;
        $res['message'] = "Deleted SuccessFully";

        return is_mobile($type, "designation_leave.index", $res);
    }

    // 1. MY LEAVE SUMMARY API
    public function getLeaveDashboard($employeeId)
    {
        // Assume employee validation fails
        if (!$employeeId) {
            return response()->json([
                'status' => "0",
                'message' => "Invalid employee ID",
                'data' => []
            ]);
        }

        // Sample data
        $leaveSummary = [
            'total_leaves' => '20',
            'used_leaves' => '5',
            'remaining_leaves' => '15',
        ];

        $leaveTypes = [
            ['leave_type' => 'Casual Leave', 'used' => '7', 'total' => '14'],
            ['leave_type' => 'Medical Leave', 'used' => '10', 'total' => '14'],
            ['leave_type' => 'Earn Leave', 'used' => '40', 'total' => '60'],
            ['leave_type' => 'Compassionate', 'used' => '3', 'total' => '3'],
            ['leave_type' => 'Maternity', 'used' => '60', 'total' => '60'],
            ['leave_type' => 'Replacement', 'used' => '1', 'total' => '1'],
        ];

        return response()->json([
            'status' => "1",
            'message' => "Success",
            'data' => [
                'leave_summary' => $leaveSummary,
                'leave_types' => $leaveTypes
            ]
        ]);
    }

    // 2. LEAVE HISTORY API
    public function getLeaveHistory($employeeId)
    {
        if (!$employeeId) {
            return response()->json([
                'status' => "0",
                'message' => "Invalid employee ID",
                'data' => []
            ]);
        }

        // Example response; replace with actual DB query
        $history = [
            [
                'from_date' => '2025-01-15',
                'to_date' => '2025-01-16',
                'leave_type' => 'Medical Leave',
                'status' => 'Approved',
                'status_color' => '#009900',
                'day_type' => '2',
                'comment' => 'Fever and rest',
            ],
            [
                'from_date' => '2025-03-02',
                'to_date' => '2025-01-02',
                'leave_type' => 'Casual Leave',
                'status' => 'Rejected',
                'status_color' => '#ffc14d',
                'day_type' => '1',
                'comment' => 'Personal work',
            ],
            [
                'from_date' => '2025-04-10',
                'to_date' => '2025-01-14',
                'leave_type' => 'Earn Leave',
                'status' => 'Pending',
                'status_color' => '#009900',
                'day_type' => '5',
                'comment' => 'Own Marriage',
            ],
            [
                'from_date' => '2025-04-10',
                'to_date' => '2025-01-14',
                'leave_type' => 'Casual Leave',
                'status' => 'Cancelled',
                'status_color' => '#ff3333',
                'day_type' => '5',
                'comment' => 'Own Marriage',
            ],
        ];

        return response()->json([
            'status' => "1",
            'message' => "Success",
            'data' => $history
        ]);
    }
}
