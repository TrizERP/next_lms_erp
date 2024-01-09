<?php

namespace App\Http\Controllers\leave\leave_report;

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


class LeaveReportController extends Controller
{
    public function leaveReport(Request $request) 
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

        $from_date_formatted = Carbon::now()->format('Y-m-d');
        $to_date_formatted = Carbon::now()->format('Y-m-d');

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');
        
        return view('leave.leave_report.index', compact('from_date_formatted', 'to_date_formatted', 'departments', 'employee_id', 'department_id'));
    }

    public function getEmployeeLists(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $department_id = $request->input('department_id');
	    $employee_id = $request->get('employee_id');
	
	    $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('department_id', $department_id)->get()->toArray();

        return response()->json(['employees' => $employees, 'department_id' => $department_id, 'employee_id' =>$employee_id]);
    }

    public function leaveReportShow(Request $request) 
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
	    $get_leave_status = $request->input('leave_status');
        
        $from_date_formatted = Carbon::createFromFormat('Y-m-d', $from_date)->format('Y-m-d');
        $to_date_formatted = Carbon::createFromFormat('Y-m-d', $to_date)->format('Y-m-d');

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('department_id', $department_id)->get()->toArray();

        $get_employee_leave_lists = DB::table('hrms_emp_leaves as hel')
        ->selectRaw("hel.*, CONCAT_WS(' ',u.first_name,u.last_name) AS employee_name, hlt.leave_type")
        ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
        ->join('hrms_leave_types as hlt', 'hlt.id', '=', 'hel.leave_type_id')
        ->where('hel.sub_institute_id', $sub_institute_id)
        ->where('hel.from_date', '>=', $from_date_formatted)
        ->where('hel.to_date', '<=', $to_date_formatted)
        ->where('hel.user_id', $employee_id)
        ->whereIn('hel.status', $get_leave_status)
        ->get()->toArray();

       return view('leave.leave_report.index', compact('employees', 'from_date_formatted', 'to_date_formatted', 'get_leave_status', 'employee_id', 'department_id', 'departments', 'get_employee_leave_lists'));
    }
}
