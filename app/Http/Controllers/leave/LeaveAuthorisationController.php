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

        $from_date_formatted = Carbon::now()->format('Y-m-d');
        $to_date_formatted = Carbon::now()->format('Y-m-d');

        return view('leave.leave_authorisation', compact('from_date_formatted', 'to_date_formatted'));
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function leaveAuthorisation(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $get_leave_status = $request->get('leave_status');
        
        $from_date_formatted = Carbon::createFromFormat('Y-m-d', $from_date)->format('Y-m-d');
        $to_date_formatted = Carbon::createFromFormat('Y-m-d', $to_date)->format('Y-m-d');

        $get_employee_leave_lists = DB::table('hrms_emp_leaves as hel')
        ->selectRaw("hel.*, CONCAT_WS(' ',u.first_name,u.last_name) AS employee_name, hlt.leave_type")
        ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
        ->join('hrms_leave_types as hlt', 'hlt.id', '=', 'hel.leave_type_id')
        ->where('hel.sub_institute_id', $sub_institute_id)
        ->where('hel.from_date', '>=', $from_date_formatted)
        ->where('hel.to_date', '<=', $to_date_formatted)
        ->whereIn('hel.status', $get_leave_status)
        ->get()->toArray();
        
        return view('leave.leave_authorisation', compact('get_employee_leave_lists', 'from_date_formatted', 'to_date_formatted', 'get_leave_status'));
    }

    public function leaveAuthorisationStore(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $comments = $request->get('comment');
        $hodComments = $request->get('hod_comment');
        $hrRemarks = $request->get('hr_remarks');
        $leaveStatuses = $request->get('single_leave_status');
        $employee_ids = $request->get('employee_id');

        $user_name = DB::table('tbluser')
            ->selectRaw("CONCAT_WS(' ',first_name,last_name) AS employee_name")
            ->where('sub_institute_id', $sub_institute_id)
            ->where('id', $user_id)->first();

        foreach($employee_ids as $key => $value)
        {
            DB::table('hrms_emp_leaves')
                ->where('id', $value)
                ->update([
                    'comment' => $comments[$value],
                    'hod_comment' => $hodComments[$value],
                    'hod_comment_date' => now(),
                    'hr_remarks' => $hrRemarks[$value],
                    'hr_remark_date' => now(),
                    'approved_by' => $user_name->employee_name,
                    'status' => $leaveStatuses[$value],
                ]);
        }

        $request->session()->flash('success', 'Leave records updated successfully.');
        
        return redirect()->route('leave-authorisation.index');
    }
}
