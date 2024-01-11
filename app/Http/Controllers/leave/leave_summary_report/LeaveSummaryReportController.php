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

    public function getEmployeeLists(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $department_id = $request->input('department_id');
	    $employee_id = $request->get('employee_id');
	
	    $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('department_id', $department_id)->get()->toArray();

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

        $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('department_id', $department_id)->get()->toArray();

        $get_hrms_leave_types = DB::table('hrms_leave_types')->get()->toArray();
        
        $get_hrms_leave_allocations = DB::table('hrms_leave_allocation')->where('sub_institute_id', $sub_institute_id)->get()->toArray();
                   
        $get_employee_leave_lists = DB::table('hrms_emp_leaves as hel')
        ->selectRaw("hel.*, u.*,CONCAT_WS(' ',u.first_name,u.last_name) AS employee_name, group_concat(hlt.leave_type) as leave_type, hlt.id as leave_id, hel.status as hel_status, group_concat(hel.day_type) as total_day_type, hd.department as department_name")
        ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
        ->join('hrms_leave_types as hlt', 'hlt.id', '=', 'hel.leave_type_id')
        ->join('hrms_departments as hd', 'hd.id', '=', 'u.department_id')
        ->where('hel.sub_institute_id', $sub_institute_id)
        ->where('hel.user_id', $employee_id)
        ->where(function ($query) use ($year1) {
            $query->whereYear('hel.from_date', '=', $year1);
        })
        ->groupBy('hel.user_id')
        ->get()->toArray();

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
                $new_data[$value2]= $sum;
                
                if(in_array($value2, $value_exits))
                {
                    $new_data[$value2]= array_sum($sum_exists[$value2]);
                }
                else
                {
                    $value_exits[] = $value2;
                }
            }
        }

        foreach($get_hrms_leave_types as $key => $value)
        {
            $op_datas = DB::table('hrms_leave_allocation')->where(['sub_institute_id'=>$sub_institute_id, 'employee_id'=>$employee_id, 'leave_type_id'=>$value->id])->first();

            $op_data[$value->leave_type] = $op_datas->value ?? 0;
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

        return is_mobile($type,'leave/leave_summary_report/index',$res,'view');
     
        // return view('leave.leave_summary_report.index', compact('employees', 'employee_id', 'department_id', 'departments', 'get_employee_leave_lists', 'get_hrms_leave_types','new_data', 'get_hrms_leave_allocations'));
    }
}
