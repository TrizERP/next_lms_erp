<?php

namespace App\Http\Controllers\leave;

use App\Http\Controllers\Controller;
use App\Imports\LeaveImport;
use App\Models\HrmsDepartment;
use App\Models\HrmsEmpLeave;
use App\Models\HrmsLeaveType;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use function App\Helpers\is_mobile;
use DB;

class LeaveAuthorisationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
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

        $from_date_formatted = Carbon::now()->subMonth()->format('Y-m-d');
        $to_date_formatted = Carbon::now()->format('Y-m-d');

        $get_employee_leave_lists = DB::table('hrms_emp_leaves as hel')
        ->selectRaw("hel.*, CONCAT_WS(' ',u.first_name,u.last_name) AS employee_name, hlt.leave_type,u.department_id")
        ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
        ->join('hrms_leave_types as hlt', function($join) use ($sub_institute_id) {
            $join->on('hlt.id', '=', 'hel.leave_type_id')
                 ->where('hlt.sub_institute_id', '=', $sub_institute_id);
        })
        ->where('hel.sub_institute_id', $sub_institute_id)
        ->where('hel.from_date', '>=', $from_date_formatted)
        ->where('hel.to_date', '<=', $to_date_formatted)
       ->where('hel.status','Pending')
        ->get()->toArray();

        $res['get_employee_leave_lists'] = $get_employee_leave_lists;
        $res['from_date_formatted'] = $from_date_formatted;
        $res['to_date_formatted'] = $to_date_formatted;
        $res['defaultSel'] = "Pending";
        // echo "<pre>";print_r($get_employee_leave_lists);exit;
        // return view('leave.leave_authorisation', compact('from_date_formatted', 'to_date_formatted'));
        return is_mobile($type, "leave/leave_authorisation", $res, "view");
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function leaveAuthorisation(Request $request)
    {
        $type = $request->input('type');
        if ($type == 'API') {
            $sub_institute_id = $request->input('sub_institute_id');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }

        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $get_leave_status = $request->get('leave_status');

        $from_date_formatted = Carbon::createFromFormat('Y-m-d', $from_date)->format('Y-m-d');
        $to_date_formatted = Carbon::createFromFormat('Y-m-d', $to_date)->format('Y-m-d');

        $get_employee_leave_lists = DB::table('hrms_emp_leaves as hel')
        ->selectRaw("hel.*, CONCAT_WS(' ',u.first_name,u.last_name) AS employee_name, hlt.leave_type,u.department_id")
        ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
        ->join('hrms_leave_types as hlt', function($join) use ($sub_institute_id) {
            $join->on('hlt.id', '=', 'hel.leave_type_id')
                 ->where('hlt.sub_institute_id', '=', $sub_institute_id);
        })
        ->where('hel.sub_institute_id', $sub_institute_id)
        ->where('hel.from_date', '>=', $from_date_formatted)
        ->where('hel.to_date', '<=', $to_date_formatted)
        // ->whereIn('hel.status', [$get_leave_status])
        ->when($type == "API", function ($query) use ($get_leave_status) {
            return $query->whereIn('hel.status', [$get_leave_status]);
        }, function ($query) use ($get_leave_status) {
            return $query->whereIn('hel.status', $get_leave_status);
        })
        ->get()->toArray();

        $res['get_employee_leave_lists'] = $get_employee_leave_lists;
        $res['from_date_formatted'] = $from_date_formatted;
        $res['to_date_formatted'] = $to_date_formatted;
        $res['get_leave_status'] = $get_leave_status;
        // echo "<pre>";print_r($get_employee_leave_lists);exit;

        // return view('leave.leave_authorisation', compact('get_employee_leave_lists', 'from_date_formatted', 'to_date_formatted', 'get_leave_status'));
        return is_mobile($type, "leave/leave_authorisation", $res, "view");
    }

    public function leaveAuthorisationStore(Request $request)
    {
        $type = $request->input('type');
        if ($type == 'API') {
            $sub_institute_id = $request->input('sub_institute_id');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }
        $user_id = $request->session()->get('user_id');
        $res_user_id = $request->get('user_id');
        $hodComments = $request->get('hod_comment');
        $hrRemarks = $request->get('hr_remarks');
        $leaveStatuses = $request->get('single_leave_status');
        $employee_ids = $request->get('employee_id');
        $checkedEmp = $request->checkedEmp;
        $leave_id = $request->get('id');
        
        // echo "<pre>";print_r($checkedEmp);exit;

        if($type == 'API')
        {
            $user_name = DB::table('tbluser')
            ->selectRaw("CONCAT_WS(' ',first_name,last_name) AS employee_name")
            ->where('sub_institute_id', $sub_institute_id)
            ->where('status',1)  // 23-04-24 by uma
            ->where('id', $res_user_id)->first();
        }
        else
        {
            $user_name = DB::table('tbluser')
            ->selectRaw("CONCAT_WS(' ',first_name,last_name) AS employee_name")
            ->where('sub_institute_id', $sub_institute_id)
            ->where('status',1)  // 23-04-24 by uma
            ->where('id', $user_id)->first();
        }
        
        if($type == 'API')
        {
            DB::table('hrms_emp_leaves')
                ->where('id', $leave_id)
                ->update([
                    'hod_comment' => $hodComments,
                    'hod_comment_date' => now(),
                    'hr_remarks' => $hrRemarks,
                    'hr_remark_date' => now(),
                    'approved_by' => $user_name->employee_name,
                    'status' => $leaveStatuses,
                ]);
        }
        else
        {
            foreach($checkedEmp as $id => $value)
            {
                DB::table('hrms_emp_leaves')
                    ->where('id', $id)
                    ->update([
                        'hod_comment' => $hodComments[$id],
                        'hod_comment_date' => now(),
                        'hr_remarks' => $hrRemarks[$id],
                        'hr_remark_date' => now(),
                        'approved_by' => $user_name->employee_name,
                        'status' => $leaveStatuses[$id],
                    ]);
            }
        }
        
        $request->session()->flash('success', 'Leave records updated successfully.');
        
        // return redirect()->route('leave-authorisation.index');
        return is_mobile($type, "leave-authorisation.index", null, "redirect");
    }
}
