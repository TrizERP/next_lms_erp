<?php

namespace App\Http\Controllers\leave\leave_summary_report;

use App\Http\Controllers\Controller;
use App\Models\HrmsAttendance;
use App\Models\HrmsDepartment;
use App\Models\HrmsInOutTime;
use App\Models\HrmsJobTitle;
use App\Models\PayrollType;
use App\Models\user\tbluserModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use DB;

class LeaveSummaryReportController extends Controller
{
    public function leaveSummaryReport(Request $request) 
    {
        $type = $request->input('type');
        if ($type == 'API') 
        {
            $sub_institute_id = $request->input('sub_institute_id');
        } 
        else 
        {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }

	    $employee_id = $request->get('employee_id');
        $department_id = $request->get('department_id');

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');
        $res['departments']=$departments;
        $res['employee_id']=$employee_id;
        $res['department_id']=$department_id;

        return is_mobile($type,'leave/leave_summary_report/index',$res,'view');
        // return view('leave.leave_summary_report.index', compact('departments', 'employee_id', 'department_id'));
    }

    public function EmpLists(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $department_id = $request->input('department_id');
	    $employee_id = $request->get('employee_id');
	
	    $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('department_id', $department_id)->where('status',1)->get()->toArray();   // 23-04-24 by uma

        return response()->json(['employees' => $employees, 'department_id' => $department_id, 'employee_id' =>$employee_id]);
    }

    public function leaveSummaryReportShow(Request $request) 
    {
        $type = $request->input('type');
        if ($type == 'API') {
            $sub_institute_id = $request->input('sub_institute_id');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }

        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $department_id = $request->get('department_id');
	    $employee_id = $request->input('employee_id');
	    $years = $request->input('years');
        $both_years = explode(" ", $years);
        $year1 = $both_years[0];
        
        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        // employees
        $employeesQuery = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('status', 1);
        if (isset($department_id)) {
            $employeesQuery->where('department_id', $department_id);
        }
        $employees = $employeesQuery->get()->toArray();  // 23-04-24 by uma

        $get_hrms_leave_types = DB::table('hrms_leave_types')->get()->toArray();
        
        // get_hrms_leave_allocations
        $leaveAllocationsQuery = DB::table('hrms_leave_allocation')->where('sub_institute_id', $sub_institute_id);
        /*if (isset($employee_id)) {
            $leaveAllocationsQuery->where('employee_id', $employee_id);
        }*/
        if (isset($department_id)) {
            $leaveAllocationsQuery->where('department_id', $department_id);
        }
        $get_hrms_leave_allocations = $leaveAllocationsQuery->get()->toArray();
                   
        $get_employee_leave_lists = DB::table('hrms_emp_leaves as hel')
            ->selectRaw("hel.*, u.*,CONCAT_WS(' ',u.first_name,u.last_name) AS employee_name, group_concat(hlt.leave_type) as leave_type, hlt.id as leave_id, hel.status as hel_status, group_concat(hel.day_type) as total_day_type, hd.department as department_name,hd.id as department_id")
            ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
            ->join('hrms_leave_types as hlt', 'hlt.id', '=', 'hel.leave_type_id')
            ->join('hrms_departments as hd', 'hd.id', '=', 'u.department_id')
            ->where('hel.sub_institute_id', $sub_institute_id)
            ->where('u.status', 1)
            ->whereYear('hel.from_date', '=', $year1)
            ->when(isset($employee_id), function ($query) use ($employee_id) {
                return $query->where('hel.user_id', $employee_id);
            })
            ->when(isset($department_id), function ($query) use ($department_id) {
                return $query->where('u.department_id', $department_id);
            })
            ->groupBy('hel.user_id')
            ->get()
            ->toArray();

        $new_data = [];
        $op_data = [];

        foreach($get_employee_leave_lists as $key => $value)
        {
            $Casual_Leave = explode(',', $value->leave_type);
            $day_type = explode(',', $value->total_day_type);
            $value_exits =$sum_exists = [];

            foreach($Casual_Leave as $key2 => $value2)
            {
                $sum = $day_type[$key2];
                $sum_exists[$value2][] = $day_type[$key2];
                $new_data[$value2][$value->id]= $sum;
                
                if(in_array($value2, $value_exits))
                {
                    $new_data[$value2][$value->id]= array_sum($sum_exists[$value2]);
                }
                else
                {
                    $value_exits[] = $value2;
                }
            }
            foreach($get_hrms_leave_types as $key => $value2)
            {
                $op_datas = DB::table('hrms_leave_allocation')->where(['sub_institute_id'=>$sub_institute_id, 'leave_type_id'=>$value2->id])
                ->where('department_id',$value->department_id ?? 0)
                ->first();
    
                $op_data[$value2->leave_type][$value->department_id ?? 0] = $op_datas->value ?? 0;
            } 

        }

      

        $res['employees']=$employees;
        $res['employee_id']=$employee_id;
        $res['department_id']=$department_id;
        $res['departments']=$departments;
        $res['get_employee_leave_lists']=$get_employee_leave_lists;
        $res['get_hrms_leave_types']=$get_hrms_leave_types;
        $res['new_data']=$new_data;
        $res['op_data']=$op_data;
        $res['years']=$years;
        $res['employeget_hrms_leave_allocationses']=$get_hrms_leave_allocations;
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type,'leave/leave_summary_report/index',$res,'view');
     
        // return view('leave.leave_summary_report.index', compact('employees', 'employee_id', 'department_id', 'departments', 'get_employee_leave_lists', 'get_hrms_leave_types','new_data', 'get_hrms_leave_allocations'));
    }
}
