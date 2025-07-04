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
use App\Traits\Helpers;
use function App\Helpers\countDays;
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
        $res['allyears'] = Helpers::getPairYears();
        $res['departments']=$departments;
        $res['employee_id']=$employee_id;
        $res['department_id']=$department_id;
        $res['years']=date('Y');

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
        $type = $request->type;
        if ($type == 'API') {
            $sub_institute_id = $request->get('sub_institute_id');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }

        // $from_date = $request->get('from_date');
        // $to_date = $request->get('to_date');
        $department_id = $request->get('department_id');
	    $employee_id = $request->get('emp_id');
	    $years = $request->get('years');
        // $both_years = explode(" ", $years);
        // $year1 = $both_years[0];
        
        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        // employees
        $employeesQuery = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('status', 1);
        if ($department_id!=0) {
            $employeesQuery->where('department_id', $department_id);
                  }
        $employees = $employeesQuery->get()->toArray();  // 23-04-24 by uma

        $get_hrms_leave_types = DB::table('hrms_leave_types')->where('sub_institute_id', $sub_institute_id)->where('status',1)->orderBy('sort_order')->get()->toArray();
        
        // get_hrms_leave_allocations
        $leaveAllocationsQuery = DB::table('hrms_leave_allocation')->whereNull('employee_id')->where('sub_institute_id', $sub_institute_id);
        /*if (isset($employee_id)) {
            $leaveAllocationsQuery->where('employee_id', $employee_id);
        }*/
        if ($department_id!=0) {
            $leaveAllocationsQuery->where('department_id', $department_id);
        }
        $get_hrms_leave_allocations = $leaveAllocationsQuery->get()->toArray();
                   
        $get_employee_leave_lists = DB::table('hrms_emp_leaves as hel')
            ->selectRaw("hel.*, u.*,CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS employee_name, group_concat(hlt.leave_type) as leave_type,group_concat(hel.status) as leave_status,group_concat(hel.from_date) as leave_from_date,group_concat(hel.to_date) as leave_to_date, hlt.id as leave_id, hel.status as hel_status, group_concat(hel.day_type) as total_day_type, hd.department as department_name,hd.id as department_id,u.openingleave,u.CL_opening_leave,u.probation_period_from,u.probation_period_to")
            ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
            ->join('hrms_leave_types as hlt', function($join) use ($sub_institute_id) {
                $join->on('hlt.id', '=', 'hel.leave_type_id')
                     ->where('hlt.sub_institute_id', '=', $sub_institute_id);
            })
            ->join('hrms_departments as hd', 'hd.id', '=', 'u.department_id')
            ->where('hel.sub_institute_id', $sub_institute_id)
            ->where('u.status', 1)
            // ->whereYear('hel.from_date', '=', $years)
            ->where('hel.from_date','>=',$years.'-04-01')
            ->where('hel.to_date','<=',($years+1).'-03-31')
            ->when($employee_id!=0, function ($query) use ($employee_id) {
                return $query->where('hel.user_id', $employee_id);
            })
            ->when($department_id!=0, function ($query) use ($department_id) {
                return $query->where('u.department_id', $department_id);
            })
            ->groupBy('hel.user_id')
            ->get()
            ->toArray();

        $new_data = [];
        $op_data = [];
        $maximumOpeningLeave = number_format(180,2); // levae should not be greater then this
        $date = date('Y-m-d');
        foreach($get_employee_leave_lists as $key => $value)
        {
            $Casual_Leave = explode(',', $value->leave_type);
            $leave_status = explode(',', $value->leave_status);
            $day_type = explode(',', $value->total_day_type);
            $leave_from_date = explode(',', $value->leave_from_date);
            $leave_to_date = explode(',', $value->leave_to_date);
            $value_exits =$sum_exists = [];

            foreach($Casual_Leave as $key2 => $value2)
            {
               // leave without pay
               if($leave_status[$key2] =="approved_lwp"){
                    $counday = countDays($leave_from_date[$key2],$leave_to_date[$key2],$day_type[$key2],'');
                    $sum_exists["Leave Without Pay"][] = $counday;

                    $new_data["Leave Without Pay"][$value->id]= $counday;

                    $new_data["Leave Without Pay"][$value->id]= array_sum($sum_exists['Leave Without Pay']);
                }
                else if(in_array($leave_status[$key2],['approved','pending'])){
                    $sum = $day_type[$key2];
                    // echo "from_date=".$leave_from_date[$key2].' to_date='.$leave_to_date[$key2].'<br>';
                    $counday = countDays($leave_from_date[$key2],$leave_to_date[$key2],$day_type[$key2],'');
                    $sum_exists[$value2][] = $counday;
                    $new_data[$value2][$value->id]= $counday;
                    // echo $counday.'<br>';
                    if(in_array($value2, $value_exits))
                    {
                        $new_data[$value2][$value->id]= array_sum($sum_exists[$value2]);
                    }
                    else
                    {
                        $value_exits[] = $value2;
                    }
                }
                
            }
            foreach($get_hrms_leave_types as $key => $value2)
            {
                $op_datas = DB::table('hrms_leave_allocation')->where(['sub_institute_id'=>$sub_institute_id, 'leave_type_id'=>$value2->id])
                ->where('department_id',$value->department_id ?? 0)
                ->whereNull('employee_id')
                ->where('year',$request->years)
                ->first();

                $empEarnedLeave = DB::table('hrms_leave_allocation')->where(['sub_institute_id'=>$sub_institute_id, 'leave_type_id'=>9])
                ->where('department_id',$value->department_id ?? 0)
                ->where('employee_id',$value->user_id ?? 0)
                ->where('year',$request->years)
                ->first();

                $empCasualLeave = DB::table('hrms_leave_allocation')->where(['sub_institute_id'=>$sub_institute_id, 'leave_type_id'=>1])
                ->where('department_id',$value->department_id ?? 0)
                ->where('employee_id',$value->user_id ?? 0)
                ->where('year',$request->years)
                ->first();
                // prohabition to date 
                $probationto= $value->probation_period_to;
                $leaveTypeId = $value2->leave_type_id ?? '-';
                // $elLeave = $value->openingleave ?? 0; // commented on 09-05-2025 comming from tbluser
                // $clLeave = $value->CL_opening_leave ?? 0; // commented on 09-05-2025 comming from tbluser
                $elLeave = $empEarnedLeave->value ?? 0; // added on 09-05-2025 comming from leave allocation for yearwise
                $clLeave = $empCasualLeave->value ?? 0; // added on 09-05-2025 comming from leave allocation for yearwise
                $depLeave = $op_datas->value ?? 0;

                $mainLeave = number_format($depLeave,2) ?? 0;
                // LTY009 earned Leave openingleave
                if($elLeave > 0 && isset($leaveTypeId) && $leaveTypeId=='LTY009' && strtotime($probationto) > strtotime($date)){
                    $mainLeave = $elLeave;
                } 
                else if(isset($leaveTypeId) && $leaveTypeId=='LTY009'){
                    $mainLeave = ($depLeave + $elLeave);
                }
                // for casual leave if your have CL leave in profile but probation is greater then current date then it's profile value will be count otherwise departmentwise leave will br allocated
                // LTY001 causual leave CL_opening_leave
                if($clLeave > 0 && isset($leaveTypeId) && $leaveTypeId=='LTY001' && strtotime($probationto) > strtotime($date)){
                    $mainLeave = $clLeave; 
                }

                // leave should not be greater then set maixmum leave 
                if($mainLeave > $maximumOpeningLeave){
                    $mainLeave = $maximumOpeningLeave;
                }
                // store leave value in user_id
                $op_data[$value2->leave_type][$value->user_id] = number_format($mainLeave,2);
            } 

        }
        // echo "<pre>";print_r($op_data);exit;
        $res['allyears'] = Helpers::getPairYears();
        $res['employee_id']=$employee_id;
        $res['department_id']=$department_id;
        $res['get_employee_leave_lists']=$get_employee_leave_lists;
        $res['get_hrms_leave_types']=$get_hrms_leave_types;
        $res['new_data']=$new_data;
        $res['op_data']=$op_data;
        $res['years']=$years;
      
        return is_mobile($type,'leave/leave_summary_report/index',$res,'view');
     
    }

    public function leaveLists(Request $request){
        $type=$request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
        }
        $employee_id = $request->emp_id;
        $department_id = $request->department_id;
        $leaveTypeId = $request->leave_type_id;
        $leaveType = $request->leaveType;
        $year = $request->year;
        // DB::enableQueryLog();
        $get_employee_leave_lists = DB::table('hrms_emp_leaves as hel')
        ->selectRaw("hel.*, CONCAT_WS(' ',u.first_name,u.last_name) AS employee_name, hlt.leave_type,u.department_id")
        ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
        ->join('hrms_leave_types as hlt', function($join) use ($sub_institute_id) {
            $join->on('hlt.id', '=', 'hel.leave_type_id')
                 ->where('hlt.sub_institute_id', '=', $sub_institute_id);
        })
        ->where('hel.sub_institute_id', $sub_institute_id)
        // ->whereYear('hel.from_date', '>=', $year)
        ->where('hel.from_date','>=',$year.'-04-01')
        ->where('hel.to_date','<=',($year+1).'-03-31')
        ->when($leaveType!='lwp',function($q) use($leaveTypeId,$employee_id){
            $q->where('hel.leave_type_id',$leaveTypeId)
            ->where('hel.user_id',$employee_id)
            ->whereIn('hel.status',['approved','pending']);
        },function($q) use($leaveTypeId,$employee_id) {
            $q->whereRaw("(hel.status = 'approved_lwp' OR hel.leave_type_id = {$leaveTypeId}) and hel.user_id = {$employee_id}");
        })
        ->get()->toArray();
        // dd(DB::getQueryLog($get_employee_leave_lists));
        return $get_employee_leave_lists;
    }

    public function MyLeave(Request $request){

        $customRequest = new \Illuminate\Http\Request();
        $customRequest->replace([
            'sub_institute_id' => $request->sub_institute_id,
            'department_id' => $request->department_id,
            'emp_id' => $request->employee_id,  
            'years' => $request->years,
            'type' => 'API'
        ]);



        $leaveData = $this->leaveSummaryReportShow($customRequest);

        $jsonString = (string) $leaveData; 
        $jsonData = json_decode($jsonString, true); 

        $usedLeaves = $jsonData['new_data'] ?? [];
        $totalLeaves = $jsonData['op_data'] ?? [];
        $employeeId = (string) $request->employee_id;

        $allLeaveTypes = array_unique(array_merge(array_keys($usedLeaves), array_keys($totalLeaves)));
        $mergedLeaves = $overallCount = [];
        $overallTotal = $overallUsed = $overallRemain = 0;
        foreach ($allLeaveTypes as $leaveType) {
            $used = isset($usedLeaves[$leaveType][$employeeId]) ? $usedLeaves[$leaveType][$employeeId] : 0;
            $total = isset($totalLeaves[$leaveType][$employeeId]) ? $totalLeaves[$leaveType][$employeeId] : 0;

            $remain = $total > 0 ? ($total - $used) : 0;
            if($total > 0 && $used >0 && $remain>0 && in_array($leaveType,["Casual Leave"])){
                $overallTotal +=$total;
                $overallUsed +=$used;
                $overallRemain +=$remain;
            }
            $mergedLeaves[$leaveType] = [
                'total' => $total,
                'used' => $used,
                'remain' => $remain
            ];
        }
        $overallCount = [
                'overallTotal' => $overallTotal,
                'overallUsed' => $overallUsed,
                'overallRemain' => $overallRemain
            ];
            $mainArray = [
                'overall_leave'=>$overallCount,
                'leave_types'=>$mergedLeaves
            ];
        return response()->json($mainArray);
    }
}
