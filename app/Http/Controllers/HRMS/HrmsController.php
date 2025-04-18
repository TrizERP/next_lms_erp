<?php

namespace App\Http\Controllers\HRMS;

use App\Http\Controllers\Controller;
use App\Models\HrmsAttendance;
use App\Models\HrmsDepartment;
use App\Models\HrmsInOutTime;
use App\Models\HrmsJobTitle;
use App\Models\PayrollType;
use App\Models\general_dataModel;
use App\Models\user\tbluserModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\employeeDetails;
use function App\Helpers\getSubCordinates;
use DB;
use Illuminate\Support\Facades\Validator;

class HrmsController extends Controller
{
    public function hrmsJobTitle(Request $request)
    {
        $data['data'] = HrmsJobTitle::all();
//        return $data;
        $type = $request->input('type');
        return is_mobile($type, "HRMS.hrms_job_title.index", $data, "view");
//     return view('HRMS.hrms_job_title.index', ["data" => $data]);
    }

    public function hrmsCreate(Request $request, $id = 0)
    {
        $type = $request->input('type');
        if ($id) {
            $res = HrmsJobTitle::find($id);
            return is_mobile($type, "HRMS.hrms_job_title.create", $res, "view");
            //return view('HRMS.hrms_job_title.create', compact('hrmsJobTitle'));
        }
        $hrmsJobTitle['title'] = '';
        $hrmsJobTitle['description'] = '';
        $hrmsJobTitle['is_active'] = 1;
        $hrmsJobTitle['id'] = 0;
        return is_mobile($type, "HRMS.hrms_job_title.create", $hrmsJobTitle, "view");
        //return view('HRMS.hrms_job_title.create', compact('hrmsJobTitle'));
    }

    public function hrmsStore(Request $request)
    {

        $clientId = $request->session()->get('client_id');
        $subInstituteId = $request->session()->get('sub_institute_id');
        $type = $request->input('type');
        $request->validate([
            'title' => 'required|unique:hrms_job_titles,title,' . $request->id,
            'status' => 'required',
        ]);

        if ($request->id > 0) {
            $hrmsJobTitle = HrmsJobTitle::find($request->id);
        } else {
            $hrmsJobTitle = new HrmsJobTitle();
        }
        $hrmsJobTitle->title = $request->title;
        $hrmsJobTitle->description = $request->description;
        $hrmsJobTitle->sub_institute_id = $subInstituteId;
        $hrmsJobTitle->client_id = $clientId;
        $hrmsJobTitle->is_active = $request->status;
        $hrmsJobTitle->save();
        return is_mobile($type, "hrms_job_title.index", null, "redirect");
//        return redirect('hrms-job-title');
    }

    public function hrmsDestroy(Request $request, $id)
    {
        $type = $request->input('type');
        if ($id > 0) {
            HrmsJobTitle::where('id', $id)->delete();
        }
        return is_mobile($type, "hrms-job-title", null, "redirect");
//        return redirect('hrms-job-title');
    }

    public function hrmsInOutTime(Request $request)
    {
        $type = $request->input('type');
        // echo "<pre>";print_r(session()->get('data'));exit;
        if ($type == 'API') $userId = $request->input('user_id');
        else $userId = $request->session()->get('user_id');
        $hrmsInOutTimeDetails = HrmsAttendance::where([['user_id', $userId], ['day', Carbon::now()->format('Y-m-d')]])->get();

        if (count($hrmsInOutTimeDetails) == 1) {
            $hrmsInOutTimeDetails = $hrmsInOutTimeDetails->first();
            $hrmsInOutTime['hrms_attendance'] = $hrmsInOutTimeDetails;
            $hrmsInOutTime['button'] = 'out';
            if ($hrmsInOutTimeDetails->punchout_time != null) {
                $hrmsInOutTime['time'] = $hrmsInOutTimeDetails->punchout_time;
                $hrmsInOutTime['button_disable'] = true;
            } 
            else 
            {
                $hrmsInOutTime['time'] = Carbon::now()->format('H:i:s');
                $hrmsInOutTime['button_disable'] = false;
            }

        } 
        else 
        {
            $hrmsInOutTime['hrms_attendance'] = $hrmsInOutTimeDetails->isEmpty() ? null : $hrmsInOutTimeDetails;
            $hrmsInOutTime['button'] = 'in';
            $hrmsInOutTime['time'] = Carbon::now()->format('H:i:s');
            $hrmsInOutTime['button_disable'] = false;
        }
        $hrmsInOutTime['date'] = Carbon::now()->format('d-m-Y');
        $hrmsInOutTime['id'] = 0;

        /* echo("<pre>");
        print_r($hrmsInOutTime);
        echo("</pre>");
        die; */
        
        //return is_mobile($type, "HRMS.hrms_inout_time.index", compact('hrmsInOutTime'), "view",'compact');
       
        return is_mobile($type, "HRMS.hrms_inout_time.index", $hrmsInOutTime, "view");
        //return view('HRMS.hrms_inout_time.index', compact('hrmsInOutTime'));
    }

    public function hrmsInTimeStore(Request $request)
    {

        $type = $request->input('type');
        if ($type == 'API'){
            $userId = $request->input('user_id');
            $clientId = $request->input('client_id');
            $subInstituteId = $request->input('sub_institute_id');

            $validator = Validator::make($request->all(), [
                'sub_institute_id'=>'required|numeric',
                'client_id'=>'required|numeric',
                'user_id'=>'required|numeric',
                'indate'=>'required',
                'intime'=>'required',
            ]);

            if ($validator->fails()) {
                $res['status'] = 0;
                $res['message'] = $validator->messages()->first();
                return is_mobile($type, "hrms_inout_time.index", $res, "redirect");
            } 
    
        } else{
            $userId = $request->session()->get('user_id');
            $clientId = $request->session()->get('client_id');
            $subInstituteId = $request->session()->get('sub_institute_id');
        }
        
        $res['status_code']=0;
        $res['message']="Failed to time in";
        //return $request->all();
        if ($request->indate && $request->intime) 
        {
            $hrmsInOutTime = new HrmsAttendance();
            $hrmsInOutTime->user_id = $userId;
            $hrmsInOutTime->day = Carbon::parse($request->indate)->format('Y-m-d');
            $hrmsInOutTime->punchin_time = Carbon::now()->format('Y-m-d H:i:s');
            $hrmsInOutTime->client_id = $clientId;
            $hrmsInOutTime->sub_institute_id = $subInstituteId;
            $hrmsInOutTime->save();

            $res['status_code']=1;
            $res['message']="Success to time in";
        }
        
        return is_mobile($type, "hrms_inout_time.index", $res, "redirect");
        //return redirect('hrms-inout-time')->with(['message' =>'check In successfully']);
    }

    public function hrmsOutTimeStore(Request $request)
    {
        $type = $request->input('type');
        if ($type == 'API'){
            $validator = Validator::make($request->all(), [
                'sub_institute_id'=>'required|numeric',
                'client_id'=>'required|numeric',
                'user_id'=>'required|numeric',
                'outdate'=>'required',
                'outtime'=>'required',
            ]);
            $userId = $request->input('user_id');
            if ($validator->fails()) {
                $res['status'] = 0;
                $res['message'] = $validator->messages()->first();
                return is_mobile($type, "hrms_inout_time.index", $res, "redirect");
            } 
        } 
        else{
            $userId = $request->session()->get('user_id');
        } 
        $hrmsInOutTime = HrmsAttendance::where([['user_id', $userId], ['day', Carbon::now()->format('Y-m-d')], ['punchout_time', null]])->first();
        
        $res['status_code']=0;
        $res['message']="Failed to time out";
        if ($hrmsInOutTime) 
        {
            $punchout_time = Carbon::parse($request->outdate.''.$request->outtime);
            $punchin_time = Carbon::parse($hrmsInOutTime->punchin_time);

            $hrmsInOutTime->punchout_time = Carbon::parse($request->outdate .' '.$request->outtime)->format('Y-m-d H:i:s');
            $Min = $punchout_time->diffInMinutes($punchin_time);
            $diff= date('H:i', mktime(0,$Min));
            $hrmsInOutTime->timestamp_diff = $diff;
            $hrmsInOutTime->save();

            $res['status_code']=1;
            $res['message']="Success to time out";
        }
        return is_mobile($type, "hrms_inout_time.index", $res, "view");
        //return redirect('hrms-inout-time')->with(['message' =>'check Out successfully']);
    }

    public function hrmsAttendance(Request $request)
    {
        $type = $request->input('type');
        // $hrmsAttendanceDetails = '';

        if ($type == 'API') $subInstituteId = $request->input('sub_institute_id');
        else   $subInstituteId = $request->session()->get('sub_institute_id');

        if ($request->employee_id) 
        {
            $hrmsAttendanceInOutTime['employee_id'] = $request->employee_id;
            $date = $request->date ? Carbon::parse($request->date)->format('Y-m-d') : Carbon::now()->format('Y-m-d');

            if ($date) 
            {
                $hrmsAttendanceInOutTime['date'] = Carbon::parse($request->date);

                $hrmsAttendanceDetails = HrmsAttendance::where([['user_id', $request->employee_id], ['day', $date]])->first();

                if ($hrmsAttendanceDetails) 
                {
                    $hrmsAttendanceInOutTime['hrms_attendance'] = $hrmsAttendanceDetails ? $hrmsAttendanceDetails : null;
                    $hrmsAttendanceInOutTime['button'] = 'out';
                    $hrmsAttendanceInOutTime['note'] = 2;
                } 
                else 
                {
                    $hrmsAttendanceInOutTime['hrms_attendance'] = $hrmsAttendanceDetails ? $hrmsAttendanceDetails : null;
                    $hrmsAttendanceInOutTime['button'] = 'in';
                    $hrmsAttendanceInOutTime['note'] = 1;
                    //$hrmsAttendanceInOutTime['date'] = Carbon::now();
                }
            }
        } else {
            $hrmsAttendanceInOutTime['button'] = 'in';
            $hrmsAttendanceInOutTime['note'] = 1;
            $hrmsAttendanceInOutTime['employee_id'] = 0;
            $hrmsAttendanceInOutTime['date'] = Carbon::now();
        }

        $employeeLists = tbluserModel::where('sub_institute_id', $subInstituteId)->where('status', 1)->orderBy('first_name')->get();

        $hrmsAttendanceInOutTime['id'] = 0;
        $hrmsAttendanceInOutTime['time'] = Carbon::now()->format('H:i:s');
        $hrmsAttendanceInOutTime['employeeLists'] = $employeeLists;
        //return $hrmsAttendanceInOutTime;
        //return is_mobile($type, "HRMS.hrms_attendance.index", compact('hrmsAttendanceInOutTime','employeeLists'), "view",'compact');
        return is_mobile($type, "HRMS.hrms_attendance.index", $hrmsAttendanceInOutTime, "view");
        //return view('HRMS.hrms_attendance.index', compact('hrmsAttendanceInOutTime', 'employeeLists'));
    }

    public function hrmsAttendanceInTimeStore(Request $request)
    {
        $request->validate([
            'employee' => 'required',
            'indate' => 'required',
            'intime' => 'required'
        ]);
        //return $request->all();
        //return Carbon::parse($request->indate)->format('Y-m-d');
        $type = $request->input('type');
        if ($type == 'API') {
            $clientId = $request->input('client_id');
            $subInstituteId = $request->input('sub_institute_id');
        } else {
            $clientId = $request->session()->get('client_id');
            $subInstituteId = $request->session()->get('sub_institute_id');
        }
        $hrmsAttendanceInTime = new HrmsAttendance();
        $hrmsAttendanceInTime->user_id = $request->employee;
        $hrmsAttendanceInTime->punchin_time = Carbon::parse($request->indate .' '.$request->intime)->format('Y-m-d H:i:s');
        //return $hrmsAttendanceInTime->punchin_time;
        $hrmsAttendanceInTime->day = Carbon::parse($request->indate)->format('Y-m-d');
        $hrmsAttendanceInTime->in_note = 1;
        $hrmsAttendanceInTime->ipaddress_in = $request->ip();
        $hrmsAttendanceInTime->client_id = $clientId;
        $hrmsAttendanceInTime->sub_institute_id = $subInstituteId;
        $hrmsAttendanceInTime->save();

        return is_mobile($type, "hrms_attendance.index", null, "redirect");
        //return redirect('hrms-attendance')->with(['message' =>'check In successfully']);
    }

    public function hrmsAttendanceOutTimeStore(Request $request) {
        $type = $request->input('type');
        $request->validate([
            'employee' => 'required',
            'outdate' => 'required',
            'outtime' => 'required'
        ]);
        $hrmsAttendanceOutTime = HrmsAttendance::where([['user_id', $request->employee],['punchout_time', null],['day' ,Carbon::parse($request->outdate)->format('Y-m-d') ]])->first();
        if ($hrmsAttendanceOutTime) {
//            return $request->all();
            $punchout_time = Carbon::parse($request->outdate.''.$request->outtime);
//            return $punchout_time;
            $punchin_time = Carbon::parse($hrmsAttendanceOutTime->punchin_time);
            $hrmsAttendanceOutTime->punchout_time =  Carbon::parse($request->outdate .' '.$request->outtime)->format('Y-m-d H:i:s');;
            $hrmsAttendanceOutTime->ipaddress_out = $request->ip();
            $Min = $punchout_time->diffInMinutes($punchin_time);
            $diff= date('H:i', mktime(0,$Min));
//            return $diff;
            $hrmsAttendanceOutTime->out_note = 1;
            $hrmsAttendanceOutTime->timestamp_diff = $diff;
            $hrmsAttendanceOutTime->save();
        }
        return is_mobile($type, "hrms_attendance.index", null, "redirect");
       // return redirect('hrms-attendance')->with(['message' =>'check Out successfully']);
    }

    public function hrmsAttendanceReportIndex(Request $request) 
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

	    $res['employee_id'] = $employee_id = $request->get('employee_id');
        $res['department_id'] = $department_id = $request->get('department_id');

        $res['from_date_formatted'] = $from_date_formatted = Carbon::now()->format('Y-m-d');
        $res['to_date_formatted'] = $to_date_formatted = Carbon::now()->format('Y-m-d');

        $res['departments'] = $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        // return view('HRMS.hrms_attendance_report.index', compact('from_date_formatted', 'to_date_formatted', 'departments', 'employee_id', 'department_id'));
        return is_mobile($type, "HRMS/hrms_attendance_report/index", $res, "view");
    }

    public function getEmployeeLists(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $department_id = $request->input('department_id');
	    $employee_id = $request->get('employee_id');
	
	    $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('status',1)->where('department_id', $department_id)->get()->toArray(); // 23-04-24 by uma

        return response()->json(['employees' => $employees, 'department_id' => $department_id, 'employee_id' =>$employee_id]);
    }

    public function hrmsAttendanceReport(Request $request) 
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
	    $employee_id = $request->get('emp_id');

        $from_date_formatted = Carbon::createFromFormat('Y-m-d', $from_date)->format('Y-m-d');
        $to_date_formatted = Carbon::createFromFormat('Y-m-d', $to_date)->format('Y-m-d');

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('status',1)->where('department_id', $department_id)->get()->toArray();  // 23-04-24 by uma
        
        $hrmsList = DB::table('hrms_attendances as ha')
        ->join('tbluser as u', 'u.id', '=', 'ha.user_id')
        ->selectRaw("DISTINCT ha.*, ha.id as atten_id,  u.*, CONCAT_WS(' ',COALESCE(u.first_name,'-'),COALESCE(u.middle_name,'-'),COALESCE(u.last_name,'-')) AS employee_name ")
        ->where('ha.sub_institute_id', $sub_institute_id)
        ->whereBetween('ha.day', [$from_date_formatted, $to_date_formatted])
        ->where('ha.user_id', $employee_id)
        ->where('u.status',1)  // 23-04-24 by uma
        ->get()
        ->toArray();

        $get_hrms_emp_leaves = DB::table('hrms_emp_leaves as hel')
        ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
        ->join('hrms_leave_types as hlt', 'hlt.id', '=', 'hel.leave_type_id')
        ->selectRaw("hel.*, hlt.*, u.*,CONCAT_WS(' ',COALESCE(u.first_name,'-'),COALESCE(u.middle_name,'-'),COALESCE(u.last_name,'-')) AS employee_name ,hel.leave_type_id as leave_id")
        ->where('hel.sub_institute_id', $sub_institute_id)
        ->where('hel.from_date','>=',$from_date_formatted)
        ->where('hel.to_date','<=',$to_date_formatted)
        ->where('hel.user_id', $employee_id)
        ->where('u.status',1)  // 23-04-24 by uma
        ->get()->toArray();
        
        $get_hrms_holidays = DB::table('hrms_holidays')
        ->where('sub_institute_id', $sub_institute_id)
        ->where('from_date','>=',$from_date_formatted)
        ->where('to_date','<=',$to_date_formatted)
        ->get()->toArray();

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        foreach ($hrmsList as $key => $value) 
        {
           $hrms_date = $value->day;
           $hrms_date = Carbon::createFromFormat('Y-m-d', $hrms_date);

            $day_name =lcfirst($hrms_date->format('l')); 
            $hrmsList[$value->day][]=$value;
            
            $punchin_time = $value->punchin_time;
            if (!is_null($punchin_time)) {
                $punchin_time = Carbon::createFromFormat('Y-m-d H:i:s', $punchin_time);
                $punchin_time = strtolower($punchin_time->format('H:i:s'));
            } else {
                $punchin_time = 'null'; // Or handle as needed
            }

            $punchout_time = $value->punchout_time;
            if (!is_null($punchout_time)) {
                $punchout_time = Carbon::createFromFormat('Y-m-d H:i:s', $punchout_time);
                $punchout_time = strtolower($punchout_time->format('H:i:s'));
            } else {
                $punchout_time = 'null'; // Or handle as needed
            }


            $user_day_in = $day_name.'_in_date';
            $user_in_set_time = $value->$user_day_in;
            
            $user_day_out = $day_name.'_out_date';
            $user_out_set_time = $value->$user_day_out; 
         }

        foreach ($get_hrms_emp_leaves as $key => $value) 
        {
            $get_hrms_emp_leaves[$value->from_date][]=$value;
        }

        foreach ($get_hrms_holidays as $key => $value) 
        {
            $get_hrms_holidays[$value->from_date][]=$value;
        }
        
        $report_data=[];
        $i=0;
        $from_date_new = $from_date_formatted;

        while (strtotime($from_date_new) <= strtotime($to_date_formatted)) 
        {
            $i++;

            if (array_key_exists($from_date_new, $hrmsList)) 
            {
                $report_data[$from_date_new] = $hrmsList[$from_date_new];
            }
            else 
            {
                $report_data[$from_date_new] = array();
            }

            if (array_key_exists($from_date_new, $get_hrms_emp_leaves)) 
            {
                $report_data[$from_date_new]['leave'] = $get_hrms_emp_leaves[$from_date_new];
            }

            if (array_key_exists($from_date_new, $get_hrms_holidays)) 
            {
                $report_data[$from_date_new]['holiday'] = $get_hrms_holidays[$from_date_new];
            }

            $from_date_new = date("Y-m-d", strtotime("+1 day", strtotime($from_date_new)));
        }
        
        $res['employees'] = $employees;
        $res['from_date_formatted'] = $from_date_formatted;
        $res['to_date_formatted'] = $to_date_formatted;
        $res['report_data'] = $report_data;
        $res['selEmp'] = $employee_id;
        $res['selDept'] = $department_id;
        $res['departments'] = $departments;
 
        //return view('HRMS.hrms_attendance_report.index', compact('employees', 'from_date_formatted', 'to_date_formatted', 'report_data', 'employee_id', 'department_id', 'departments'));
        return is_mobile($type, "HRMS/hrms_attendance_report/index", $res, "view");
    }

    public function generalSettingIndex(Request $request) 
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

        $get_sandwich_leave_data = DB::table('general_data')->where(['fieldname' => 'sandwich_leave', 'sub_institute_id' => $sub_institute_id])->first();

        $get_casual_leave_data = DB::table('general_data')->where(['fieldname' => 'casual_leave_apply', 'sub_institute_id' => $sub_institute_id])->first();

        $get_earned_leave_data = DB::table('general_data')->where(['fieldname' => 'earned_leave_apply', 'sub_institute_id' => $sub_institute_id])->first();


        $get_parent_communication = DB::table('general_data')->where(['fieldname' => 'parent_communication', 'sub_institute_id' => $sub_institute_id])->first();

        $get_multi_login = DB::table('general_data')->where(['fieldname' => 'multi_login', 'sub_institute_id' => $sub_institute_id])->first();

        $get_timetable_teacher = DB::table('general_data')->where(['fieldname' => 'timetable_teacher', 'sub_institute_id' => $sub_institute_id])->first();
        
        $get_timetable_ai = DB::table('general_data')->where(['fieldname' => 'timetable_ai', 'sub_institute_id' => $sub_institute_id])->first();

        $get_bulkDiscount = DB::table('general_data')->where(['fieldname' => 'fees_bulk_discount', 'sub_institute_id' => $sub_institute_id])->first();

        $get_studentName = DB::table('general_data')->where(['fieldname' => 'student_name', 'sub_institute_id' => $sub_institute_id])->first();
        $get_previousAdmission = DB::table('general_data')->where(['fieldname' => 'previous_year_admission', 'sub_institute_id' => $sub_institute_id])->first();

        $res['get_sandwich_leave_data'] = $get_sandwich_leave_data;
        $res['get_casual_leave_data'] = $get_casual_leave_data;
        $res['get_earned_leave_data'] = $get_earned_leave_data;
        $res['get_parent_communication']=$get_parent_communication;
        $res['get_multi_login']=$get_multi_login;
        $res['get_timetable_teacher']=$get_timetable_teacher;
        $res['get_timetable_ai']=$get_timetable_ai;
        $res['get_bulkDiscount']=$get_bulkDiscount;
        $res['get_studentName']=$get_studentName;
        $res['get_previousAdmission']=$get_previousAdmission;
         
        // echo "<pre>";print_r($res);exit;  

        return is_mobile($type, "HRMS/general_setting/general_setting", $res, "view");
    }

    public function generalSettingStore(Request $request)
    {
        // echo "<pre>";print_r($request->all());exit;        
        $type = $request->input('type');
        if ($type == 'API'){
            $userId = $request->input('user_id');
            $clientId = $request->input('client_id');
            $subInstituteId = $request->input('sub_institute_id');
        } else{
            $userId = $request->session()->get('user_id');
            $clientId = $request->session()->get('client_id');
            $subInstituteId = $request->session()->get('sub_institute_id');
        }
        
        $sandwich_leave = $request->input('sandwich_leave');
        $casual_leave_at_one_time = $request->input('casual_leave_at_one_time');
        $earned_leave_days = $request->input('earned_leave_days');
        $parent_communication = $request->input('parent_communication');
        $multi_login = $request->input('multi_login');   
        $timetable_teacher = $request->input('timetable_teacher'); 
        $bulkDiscount = $request->input('bulkDiscount');    
        $bulkDiscountAmt = isset($request->bulkDiscountAmt) ? $request->bulkDiscountAmt : 0;                     
        
        $studentName = $request->input('studentName');   
        $previousAdmission = $request->previousAdmission; 

        if ($sandwich_leave !== null) {
            // Check if a record with fieldname 'sandwich_leave' and sub_institute_id exists
            $existingSandwichLeave = general_dataModel::where('fieldname', 'sandwich_leave')
                ->where('sub_institute_id', $subInstituteId)
                ->first();
        
            if ($existingSandwichLeave) {
                // If exists, update the record
                $existingSandwichLeave->fieldvalue = $sandwich_leave;
                $existingSandwichLeave->save();
            } else {
                // If not exists, insert a new record
                $general_data = new general_dataModel();
                $general_data->fieldname = 'sandwich_leave';
                $general_data->fieldvalue = $sandwich_leave;
                $general_data->sub_institute_id = $subInstituteId;
                $general_data->client_id = $clientId;
                $general_data->type = 'hrms';
                $general_data->save();
            }
        }
        
        if ($casual_leave_at_one_time !== null) {
            // Check if a record with fieldname 'casual_leave_apply' and sub_institute_id exists
            $existingCasualLeaveApply = general_dataModel::where('fieldname', 'casual_leave_apply')
                ->where('sub_institute_id', $subInstituteId)
                ->first();
        
            if ($existingCasualLeaveApply) {
                // If exists, update the record
                $existingCasualLeaveApply->fieldvalue = $casual_leave_at_one_time;
                $existingCasualLeaveApply->save();
            } else {
                // If not exists, insert a new record
                $general_data = new general_dataModel();
                $general_data->fieldname = 'casual_leave_apply';
                $general_data->fieldvalue = $casual_leave_at_one_time ?? 0;
                $general_data->sub_institute_id = $subInstituteId;
                $general_data->client_id = $clientId;
                $general_data->type = 'hrms';
                $general_data->save();
            }
        }
        // earned_leave_days
        if ($earned_leave_days !== null) {
            // Check if a record with fieldname 'earned_leave_apply' and sub_institute_id exists
            $existingearnedLeaveApply = general_dataModel::where('fieldname', 'earned_leave_apply')
                ->where('sub_institute_id', $subInstituteId)
                ->first();
        
            if ($existingearnedLeaveApply) {
                // If exists, update the record
                $existingearnedLeaveApply->fieldvalue = $earned_leave_days;
                $existingearnedLeaveApply->save();
            } else {
                // If not exists, insert a new record
                $general_data = new general_dataModel();
                $general_data->fieldname = 'earned_leave_apply';
                $general_data->fieldvalue = $earned_leave_days ?? 0;
                $general_data->sub_institute_id = $subInstituteId;
                $general_data->client_id = $clientId;
                $general_data->type = 'hrms';
                $general_data->save();
            }
        }
        // for parent communication 
        if ($parent_communication !== "Y") {
            $parent_communication = 'N';
        }
        $existingParentCommunication = general_dataModel::where('fieldname', 'parent_communication')
        ->where('sub_institute_id', $subInstituteId)
        ->first();
        $general_data = new general_dataModel();
        
        if($existingParentCommunication){
            $existingParentCommunication->fieldvalue = $parent_communication;
            $existingParentCommunication->save();
        }else{
            $general_data->fieldname = 'parent_communication';
            $general_data->fieldvalue = $parent_communication;
            $general_data->sub_institute_id = $subInstituteId;
            $general_data->client_id = $clientId;
            $general_data->type = 'hrms';
            $general_data->save();        
        }

        if ($multi_login !== "No") {
            $multi_login = 'Yes';
        }
        $existingmulti_login = general_dataModel::where('fieldname', 'multi_login')
        ->where('sub_institute_id', $subInstituteId)
        ->first();
        $general_data = new general_dataModel();
        
        if($existingmulti_login){
            $existingmulti_login->fieldvalue = $multi_login;
            $existingmulti_login->save();
        }else{
            $general_data->fieldname = 'multi_login';
            $general_data->fieldvalue = $multi_login;
            $general_data->sub_institute_id = $subInstituteId;
            $general_data->client_id = $clientId;
            $general_data->type = 'hrms';
            $general_data->save();        
        }
        // get_timetable_teacher
        $existingTimetableTeacher = general_dataModel::where('fieldname', 'timetable_teacher')
        ->where('sub_institute_id', $subInstituteId)
        ->first();
        $general_data = new general_dataModel();
        
        if($existingTimetableTeacher){
            $existingTimetableTeacher->fieldvalue = $timetable_teacher;
            $existingTimetableTeacher->save();
        }else{
            $general_data->fieldname = 'timetable_teacher';
            $general_data->fieldvalue = ($timetable_teacher=='Yes') ? $timetable_teacher : 'No';
            $general_data->sub_institute_id = $subInstituteId;
            $general_data->client_id = $clientId;
            $general_data->type = 'hrms';
            $general_data->save();        
        }

        // timetable AI
         // get_timetable_teacher
         $existingTimetableTeacher = general_dataModel::where('fieldname', 'timetable_ai')
         ->where('sub_institute_id', $subInstituteId)
         ->first();
         $general_data = new general_dataModel();
         
         if($existingTimetableTeacher){
             $existingTimetableTeacher->fieldvalue = $request->timetable_ai;
             $existingTimetableTeacher->save();
         }else{
             $general_data->fieldname = 'timetable_ai';
             $general_data->fieldvalue = $request->timetable_ai;
             $general_data->sub_institute_id = $subInstituteId;
             $general_data->client_id = $clientId;
             $general_data->type = 'hrms';
             $general_data->save();        
         }

         // Fees Bulk Discount
         $existingTimetableTeacher = general_dataModel::where('fieldname', 'fees_bulk_discount')
         ->where('sub_institute_id', $subInstituteId)
         ->first();
         $general_data = new general_dataModel();
         
         if($existingTimetableTeacher){
             $existingTimetableTeacher->fieldvalue = $bulkDiscount;
             $existingTimetableTeacher->extra_field1 = $bulkDiscountAmt;
             $existingTimetableTeacher->save();
         }else{
             $general_data->fieldname = 'fees_bulk_discount';
             $general_data->fieldvalue = $bulkDiscount;
             $general_data->extra_field1 = $bulkDiscountAmt;
             $general_data->sub_institute_id = $subInstituteId;
             $general_data->client_id = $clientId;
             $general_data->type = 'hrms';
             $general_data->save();        
         }

          // Student Name
          $existingTimetableTeacher = general_dataModel::where('fieldname', 'student_name')
          ->where('sub_institute_id', $subInstituteId)
          ->first();
          $general_data = new general_dataModel();
          
          if($existingTimetableTeacher){
              $existingTimetableTeacher->fieldvalue = $studentName;
              $existingTimetableTeacher->extra_field1 = null;
              $existingTimetableTeacher->save();
          }else{
              $general_data->fieldname = 'student_name';
              $general_data->fieldvalue = $studentName;
              $general_data->extra_field1 = null;
              $general_data->sub_institute_id = $subInstituteId;
              $general_data->client_id = $clientId;
              $general_data->type = 'hrms';
              $general_data->save();        
          }

           // Allow previous year admission
           $existingTimetableTeacher = general_dataModel::where('fieldname', 'previous_year_admission')
           ->where('sub_institute_id', $subInstituteId)
           ->first();
           $general_data = new general_dataModel();
           
           if($existingTimetableTeacher){
               $existingTimetableTeacher->fieldvalue = $previousAdmission;
               $existingTimetableTeacher->extra_field1 = null;
               $existingTimetableTeacher->save();
           }else{
               $general_data->fieldname = 'previous_year_admission';
               $general_data->fieldvalue = $previousAdmission;
               $general_data->extra_field1 = null;
               $general_data->sub_institute_id = $subInstituteId;
               $general_data->client_id = $clientId;
               $general_data->type = 'hrms';
               $general_data->save();        
           }
 
        $res['status_code']=1;
        $res['message']="General setting information add/updated successfully";
        
        return is_mobile($type, "hrms_general_setting.index", $res, "redirect");
    }

    public function earlyGoingHrmsAttendanceReportIndex(Request $request) 
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

        $date_formatted = Carbon::now()->format('Y-m-d');

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        $res['employee_id'] = $employee_id;
        $res['department_id'] = $department_id;
        $res['date_formatted'] = $date_formatted;
        $res['departments'] = $departments;
        
        // return view('HRMS.hrms_attendance_report.early_going_report', compact('date_formatted', 'departments', 'employee_id', 'department_id'));
        return is_mobile($type, "HRMS/hrms_attendance_report/early_going_report", $res, "view");
    }

    public function earlyGoingHrmsAttendanceReport(Request $request) {
        // echo "<pre>";print_r($request->all());exit; 
        $type = $request->input('type');
        if ($type == 'API') {
            $sub_institute_id = $request->input('sub_institute_id');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }

        $department_id = ($request->department_id!=0) ? implode(',',$request->department_id) : 0;
	    $employee_id = ($request->emp_id!=0) ? implode(',',$request->emp_id) : 0;
	    $date = $request->date;
        
        $date_formatted = Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        $timestamp = strtotime($date_formatted);
        $day = date('D', $timestamp);

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        $employeeDep = DB::table('tbluser')->where('sub_institute_id', $sub_institute_id)->when($department_id!=0,function($q) use($department_id){
            $q->whereRaw('department_id in ('.$department_id.')');
        })->where('status', 1)->selectRaw('GROUP_CONCAT(DISTINCT id) as user_id')->groupBy('sub_institute_id')->first();
        // echo "<pre>";print_r($employeeDep->user_id);exit;  

        $hrmsList = HrmsAttendance::join('tbluser as u','u.id','=','hrms_attendances.user_id')->where('hrms_attendances.sub_institute_id',$sub_institute_id);
        
        if($employee_id!=0){
            echo $employee_id;
            $hrmsList = $hrmsList->when(isset($employee_id),function($q) use($employee_id){
                $q->whereRaw('user_id in ('.$employee_id.')');
            });
        }
        else if($department_id!=0) {
            echo "else";

            $hrmsList = $hrmsList->when(isset($employeeDep->user_id),function($q) use($employeeDep){
                $q->whereRaw('user_id in ('.$employeeDep->user_id.')');
            });
        }

        $hrmsList = $hrmsList->where('day', $date_formatted)->whereNotNull('punchout_time')->get();
        // echo "<pre>";print_r($hrmsList);exit;
        $hrmsList = $hrmsList->map(function ($e) use ($day)
        {
            if($day =='Mon' && isset($e->getUser['monday'])  && $e->getUser['monday']==0) {
                if($e->getUser['monday_out_date'] &&  $e->getUser['monday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['monday_out_date'];
                }
            }
            if($day =='Tue' && isset($e->getUser['tuesday']) && $e->getUser['tuesday']==0) {
                if($e->getUser['tuesday_out_date'] &&  $e->getUser['tuesday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['tuesday_out_date'];
                }
            }
            if($day =='Wed' && isset($e->getUser['wednesday'])  && $e->getUser['wednesday']==0) {
                if($e->getUser['wednesday_out_date'] &&  $e->getUser['wednesday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['wednesday_out_date'];
                }
            }
            if($day =='Thu' && isset($e->getUser['thursday']) && $e->getUser['thursday']==0) {
                if($e->getUser['thursday_out_date'] &&  $e->getUser['thursday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['saturday_out_date'];
                }
            }
            if($day =='Fri' && isset($e->getUser['friday']) && $e->getUser['friday']==0) {
                if($e->getUser['friday_out_date'] &&  $e->getUser['friday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['friday_out_date'];
                }
            }
            if($day =='Sat' && isset($e->getUser['saturday']) && $e->getUser['saturday']==0) {
                if($e->getUser['saturday_out_date'] &&  $e->getUser['saturday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['saturday_out_date'];
                }
            }
            if($day =='Sun' && $e->getUser['sunday']==0) {
                if($e->getUser['sunday_out_date'] &&  $e->getUser['sunday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['sunday_out_date'];
                }
            }
            return $e;
        })->where('is_late',1);

        $res['employees'] = $employeeDep;
        $res['date_formatted'] = $date_formatted;
        $res['hrmsList'] = $hrmsList;
        $res['employee_id'] = $employee_id;
        $res['selEmp'] = $request->emp_id;
        $res['department_id'] = $request->department_id;
        $res['departments'] = $departments;
 
        //return view('HRMS.hrms_attendance_report.early_going_report', compact('employees', 'employee_id', 'date_formatted', 'hrmsList', 'type', 'departments', 'department_id'));
        return is_mobile($type, "HRMS/hrms_attendance_report/early_going_report", $res, "view");
    }

    public function departmentAttendanceReport(Request $request){
        $type = $request->type;
        $res = session()->get('data');
        $sub_institute_id = session()->get('sub_institute_id');
        $start_date = now()->startOfMonth();
        $res['start_date'] = $start_date->format('Y-m-d');
        $res['end_date'] = now();

        $res['department'] = DB::table('hrms_departments')->where('sub_institute_id',$sub_institute_id)->where('status',1)->whereNull('deleted_at')->pluck('department','id');
      
        return is_mobile($type, "HRMS/hrms_attendance_report/departmentwiseReport", $res, "view");
    }

    public function departmentAttendanceReportCreate(Request $request){
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        if($type=="API"){
            $sub_institute_id = $request->sub_institute_id;
        }
        $res['selDepartments'] = $department_ids = $request->department_id;
        $res['emp_id'] = $emp_id = $request->emp_id;
        $res['selectedFromDate'] = $from_date = $request->from_date;
        $res['selectedToDate'] = $to_date = $request->to_date;

        $empData = DB::table('tbluser as tu')
        ->join('tbluserprofilemaster as upm', 'upm.id', '=', 'tu.user_profile_id')
        ->leftJoin('hrms_attendances as ha',function($join) use($from_date,$to_date,$sub_institute_id){
            $join->on('tu.id', '=', 'ha.user_id')->whereBetween('ha.day',[$from_date,$to_date])->where(['ha.sub_institute_id'=>$sub_institute_id,"ha.status"=>1]);
        })
        ->leftJoin('hrms_emp_leaves as hel',function($join) use($from_date,$to_date,$sub_institute_id){
            $join->on('hel.user_id','=','ha.user_id')->where('hel.from_date','>=',$from_date)->where('hel.to_date','<=',$to_date)->where('hel.sub_institute_id',$sub_institute_id)->where('hel.status','approved');
        })
        ->join('hrms_departments as hd','tu.department_id','=','hd.id')
        ->leftJoin('hrms_holidays as hh',function($join) use($from_date,$to_date,$sub_institute_id){
            $join->on('hh.department','=','hd.id')->where('hh.from_date','>=',$from_date)->where('hh.to_date','<=',$to_date)->where(['hh.sub_institute_id'=>$sub_institute_id]);
        })
        ->selectRaw('tu.id as user_id, tu.employee_no, CONCAT_WS(" ", COALESCE(tu.first_name, "-"), COALESCE(tu.middle_name, "-"),COALESCE(tu.last_name, "-")) as full_name, tu.sub_institute_id, IFNULL(upm.name, "-") as user_profile, hd.department, COUNT(DISTINCT ha.id) as total_att_day, GROUP_CONCAT(DISTINCT ha.id) as worked_days, COUNT(DISTINCT hel.id) as total_ab_day, GROUP_CONCAT(DISTINCT hel.id) as ab_days, COUNT(DISTINCT hh.id) as total_holidays, GROUP_CONCAT(DISTINCT hh.id) as holidays,GROUP_CONCAT(DISTINCT hd.id) as department_id')
        ->where('tu.sub_institute_id', $sub_institute_id)
        ->when($department_ids != 0, function ($q) use ($department_ids) {
            $q->whereRaw('tu.department_id in (' . implode(',', $department_ids) . ')');
        })
        ->where('tu.status', 1)
        ->when($emp_id!=0,function($q) use($emp_id){
            $q->where('tu.id',$emp_id);
        })
        ->orderBy('tu.first_name')
        ->groupBy('tu.id')->get()->toArray();
        
        $newEmpData = [];
        foreach ($empData as $key => $value) {
            $newEmpData[] = $value;
            // add half days 
            $ab = $value->ab_days ?? 0;
            $totAb= $value->total_ab_day ?? 0;
            $getHlafDays = DB::table('hrms_emp_leaves')->whereRaw('id in ('.$ab.')')->where('day_type','0.5')->count();
            $newEmpData[$key]->half_day = $getHlafDays ?? 0;
            // add late comes 
            $wkDay = $value->worked_days ?? 0;
            $getPunchTime = DB::table('hrms_attendances')->whereRaw('id in ('.$wkDay.')')->get()->toArray();
            // get user working time 
            $late = 0;
            $lateArr = $punchDates = [];
            foreach ($getPunchTime as $punchkey => $punchvalue) {
                $dayOfWeek = Carbon::parse($punchvalue->day)->dayOfWeek; 
                $dayName = strtolower(Carbon::parse($punchvalue->day)->format('l'));
                
                $getUserInTime = DB::table('tbluser')->where('id',$value->user_id)->value($dayName.'_in_date') ?? 0;
                $punchInTime = Carbon::parse($punchvalue->punchin_time)->toTimeString();

                $punchDates[] = $punchvalue->day;

                if(isset($getUserInTime) && $getUserInTime!=0){
                   
                $punchInTimeCarbon = Carbon::createFromFormat('H:i:s', $punchInTime);
                $getUserInTimeCarbon = Carbon::createFromFormat('H:i:s', $getUserInTime);

                if($punchInTimeCarbon > $getUserInTimeCarbon){
                    $late++;
                    $lateArr[]=$punchvalue->id;
                }
                }
            }
            // exit;
            $newEmpData[$key]->late = $late;
            $newEmpData[$key]->lateAtt = $lateArr;

             // week off days sunday 
            $startDate = Carbon::parse($from_date);
            $endDate = Carbon::parse($to_date);

            $countSundays = $totalDays = $attAb = 0;

            for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
                $totalDays++;

                if ($date->isSunday()) {
                    $countSundays++;
                }
                else {
                    // Check if the current date is in $punchDates
                    $found = false;
                    foreach ($punchDates as $punchDate) {
                        if ($date->isSameDay($punchDate)) {
                            $found = true;
                            break;
                        }
                    }
            
                    if (!$found) {
                        $attAb++;
                    }
                }
            }
            $holidays = $value->total_holidays ?? 0;
            $newEmpData[$key]->weekday_off = $countSundays;
            $newEmpData[$key]->totalDays = $totalDays;
            $newEmpData[$key]->workingDays = ($totalDays - $countSundays - $holidays);
            $newEmpData[$key]->total_ab_day = ($totAb + $attAb);
        }

        if(!empty($newEmpData)){
            $res['status_code'] = 1;
            $res['message'] = "Success";
        }else{
            $res['status_code'] = 0;
            $res['message'] = "No Employee Found";
        }
       
        $res['empData'] = $newEmpData;
        // echo "<pre>";print_r($newEmpData);exit;

        return is_mobile($type, "department_attendance_report.index", $res);
    }

    public function getHolidays(Request $request){
        $sub_institute_id = session()->get('sub_institute_id');
        $data = DB::table('hrms_holidays')->where('sub_institute_id',$sub_institute_id)->where(['department_id'=>$request->department_id,'from_date'=>$request->from_date,'to_date'=>$request->to_date])->get()->toArray();
        return $data;
    }

    public function getPresentDays(Request $request){
        $sub_institute_id = session()->get('sub_institute_id');
        $data = DB::table('hrms_attendances as ha')
        ->join('tbluser as tu','ha.user_id','=','tu.id')
        ->join('hrms_departments as hd','tu.department_id','=','hd.id')
        ->selectRaw('ha.*,hd.department,tu.employee_no,CONCAT_WS(" ", COALESCE(tu.first_name, "-"), COALESCE(tu.last_name, "-")) as full_name')
        ->where('ha.user_id',$request->user_id)->whereBetween('ha.day',[$request->from_date,$request->to_date])->where(['ha.sub_institute_id'=>$sub_institute_id,"ha.status"=>1])->get()->toArray();
        return $data;
    }

    public function getAbsentDays(Request $request){
        $sub_institute_id = session()->get('sub_institute_id');
        $data = DB::table('hrms_emp_leaves as hel')
        ->join('tbluser as tu','hel.user_id','=','tu.id')
        ->join('hrms_departments as hd','tu.department_id','=','hd.id')
        ->join('hrms_leave_types as hlt','hel.leave_type_id','=','hlt.id')
        ->selectRaw('hel.*,hd.department,tu.employee_no,CONCAT_WS(" ", COALESCE(tu.first_name, "-"), COALESCE(tu.last_name, "-")) as full_name,hel.day_type,hlt.leave_type')
        ->where('hel.user_id',$request->user_id)->where('hel.from_date','>=',$request->from_date)->where('hel.to_date','<=',$request->to_date)->where('hel.sub_institute_id',$sub_institute_id)->where('hel.status','approved')->get()->toArray();
        return $data;
    }

    public function getHalfDays(Request $request){
        $sub_institute_id = session()->get('sub_institute_id');
        $data = DB::table('hrms_emp_leaves as hel')
        ->join('tbluser as tu','hel.user_id','=','tu.id')
        ->join('hrms_departments as hd','tu.department_id','=','hd.id')
        ->join('hrms_leave_types as hlt','hel.leave_type_id','=','hlt.id')
        ->join('hrms_attendances as ha',function($q) use($request){
            $q->on('ha.user_id','=','hel.user_id')->whereRaw('ha.day BETWEEN hel.from_date AND hel.to_date')->where('ha.user_id',$request->user_id);
        })
        ->selectRaw('hel.*,ha.*,hd.department,tu.employee_no,CONCAT_WS(" ", COALESCE(tu.first_name, "-"), COALESCE(tu.last_name, "-")) as full_name,hel.day_type,hlt.leave_type')
        ->where('hel.user_id',$request->user_id)->where('hel.from_date','>=',$request->from_date)->where('hel.to_date','<=',$request->to_date)->where('hel.sub_institute_id',$sub_institute_id)->where('hel.status','approved')
        ->where('hel.day_type','0.5')
        ->get()->toArray();

        return $data;
    }
    // multi Employee Attendance Report
    public function multipleAttendanceReportIndex(Request $request){
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $res = session()->get('data');
        return is_mobile($type, "HRMS.hrms_attendance_report.multiEmpAttendanceReport", $res,'view');
    }

    public function multipleAttendanceReportCreate(Request $request){
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $userId= session()->get('user_id');
        $userProfileName= session()->get('user_profile_name');

        $department_id = ($request->department_id !=0) ? implode(',',$request->department_id) : 0;
        $employee_id = ($request->emp_id !=0) ? implode(',',$request->emp_id) : 0;
        $from_date = $request->from_date;
        $to_date = $request->to_date;
        $currentMonth = '';

        // sub cordinates 02-08-2024
        $SubCordinates =[];
        $profileArr = ["Admin","Super Admin","School Admin","Assistant Admin"];
        if($employee_id==0 && !in_array($userProfileName,$profileArr)){
            $SubCordinates = getSubCordinates($sub_institute_id,$userId);
            if(!empty($SubCordinates)){
                $employee_id = implode(',',$SubCordinates);
            }
        }
        // echo "<pre>"; print_r($SubCordinates);exit;
        // end  02-08-2024
        $from_date_formatted = (isset($from_date)) ? Carbon::createFromFormat('Y-m-d', $from_date)->format('Y-m-d') : date('Y-m-d');
        $to_date_formatted = (isset($to_date)) ? Carbon::createFromFormat('Y-m-d', $to_date)->format('Y-m-d') : date('Y-m-d');
        // echo $from_date_formatted;exit;
        $hrmsAtt = DB::table('hrms_attendances as ha')
        ->join('tbluser as u',function($join) use($sub_institute_id) {
            $join->on('u.id','=','ha.user_id')->where(['u.sub_institute_id'=>$sub_institute_id,'u.status'=>1]);
        })
        ->leftJoin('hrms_departments as hd',function($join) use($sub_institute_id) {
            $join->on('hd.id','=','u.department_id')->where('hd.status',1)->where('hd.sub_institute_id',$sub_institute_id);
        })
        ->where(['ha.sub_institute_id'=>$sub_institute_id,'ha.status'=>1])
        ->whereBetween('day',[$from_date_formatted,$to_date_formatted])
        ->when($department_id!=0,function($query) use($department_id){
            $query->whereRaw('u.department_id in ('.$department_id.')');
        })
        ->when($employee_id!=0,function($query) use($employee_id){
            $query->whereRaw('u.id in ('.$employee_id.')');
        })
        ->selectRaw("ha.*,u.id AS empId,CONCAT_WS(' ',COALESCE(u.first_name,'-'),COALESCE(u.middle_name,'-'),COALESCE(u.last_name,'-')) AS full_name,COALESCE(hd.department,'-') AS depName,u.monday,u.tuesday,u.wednesday,u.thursday,u.friday,u.saturday,u.sunday,u.monday_in_date,u.tuesday_in_date,u.wednesday_in_date,u.thursday_in_date,u.friday_in_date,u.saturday_in_date,u.sunday_in_date,u.monday_out_date,u.tuesday_out_date,u.wednesday_out_date,u.thursday_out_date,u.friday_out_date,u.saturday_out_date,u.sunday_out_date")
        ->groupBy('ha.id')
        ->orderBy('ha.day')
        ->orderBy('u.first_name')
        ->get()->toArray();
        // echo "<pre>";print_r($hrmsAtt);exit;

        $get_hrms_emp_leaves = DB::table('hrms_emp_leaves as hel')
        ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
        ->join('hrms_leave_types as hlt', 'hlt.id', '=', 'hel.leave_type_id')
        ->selectRaw("hel.*, hlt.*, u.*, CONCAT_WS(' ',COALESCE(u.first_name,'-'),COALESCE(u.middle_name,'-'),COALESCE(u.last_name,'-')) AS employee_name ,hel.leave_type_id as leave_id")
        ->where('hel.sub_institute_id', $sub_institute_id)
        ->where('hel.from_date','>=',$from_date_formatted)
        ->where('hel.to_date','<=',$to_date_formatted)
        // ->where('hel.user_id', $employee_id)
        ->when($employee_id!=0,function($query) use($employee_id){
            $query->whereRaw('hel.user_id in ('.$employee_id.')');
        })
        ->where('u.status',1)  // 23-04-24 by uma
        ->get()->toArray();
        // echo "<pre>";print_r($get_hrms_emp_leaves);exit;
        
        $get_hrms_holidays = DB::table('hrms_holidays')
        ->where('sub_institute_id', $sub_institute_id)
        ->where('from_date','>=',$from_date_formatted)
        ->where('to_date','<=',$to_date_formatted)
        ->get()->toArray();

        $newHrmsAtt = [];

        foreach ($hrmsAtt as $key => $value) {
            $newHrmsAtt[$value->empId][$value->day] = $value;

            $hrms_date = $value->day;
            $hrms_date = Carbon::createFromFormat('Y-m-d', $hrms_date);

            $day_name =lcfirst($hrms_date->format('l')); 
            
            $punchin_time = $value->punchin_time;
            if (!is_null($punchin_time)) {
                $punchin_time = Carbon::createFromFormat('Y-m-d H:i:s', $punchin_time);
                $punchin_time = strtolower($punchin_time->format('H:i:s'));
            } else {
                $punchin_time = 'null'; // Or handle as needed
            }

            $punchout_time = $value->punchout_time;
            if (!is_null($punchout_time)) {
                $punchout_time = Carbon::createFromFormat('Y-m-d H:i:s', $punchout_time);
                $punchout_time = strtolower($punchout_time->format('H:i:s'));
            } else {
                $punchout_time = 'null'; // Or handle as needed
            }
            $newHrmsAtt[$value->empId][$value->day]->att_punch_in = $punchin_time;
            $newHrmsAtt[$value->empId][$value->day]->att_punch_out = $punchout_time;
            
            $newHrmsAtt[$value->empId][$value->day]->day_name = $day_name;
            $user_day_in = $day_name.'_in_date';
            $newHrmsAtt[$value->empId][$value->day]->in_time = $value->$user_day_in;
            
            $user_day_out = $day_name.'_out_date';
            $newHrmsAtt[$value->empId][$value->day]->out_time = $value->$user_day_out; 

            $newHrmsAtt[$value->empId][$value->day]->is_late = 0;
            if($punchin_time > $user_day_in){
                $newHrmsAtt[$value->empId][$value->day]->is_late = 1;
            }
                 
        }
        $empLeaves = $empHolidays = [];
        foreach ($get_hrms_emp_leaves as $value) {
            $empLeaves[$value->user_id][$value->from_date][] = (array) $value;
        }
        
        foreach ($get_hrms_holidays as $value) {
            $depExplode = explode(',',$value->department);
            foreach ($depExplode as $key => $values) {
                $empHolidays[$values][$value->from_date][] = (array) $value;
            }
        }
    //    echo "<pre>";print_r($empHolidays);exit;
        $getUsers = DB::table('tbluser as u')->leftJoin('hrms_departments as hd',function($join) use($sub_institute_id) {
                $join->on('hd.id','=','u.department_id')->where('hd.status',1)->where('hd.sub_institute_id',$sub_institute_id);
            })
            ->selectRaw("u.*,CONCAT_WS(' ',COALESCE(u.first_name,'-'),COALESCE(u.middle_name,'-'),COALESCE(u.last_name,'-')) AS full_name,COALESCE(hd.department,'-') AS depName")
            ->where(['u.sub_institute_id'=>$sub_institute_id,'u.status'=>1])
            ->when($department_id!=0,function($query) use($department_id){
                $query->whereRaw('u.department_id in ('.$department_id.')');
            })
            ->when($employee_id!=0,function($query) use($employee_id){
                $query->whereRaw('u.id in ('.$employee_id.')');
            })
            ->groupBy('u.id')->orderBy('u.first_name')->get()->toArray();
         
        // echo "<pre>";print_r($getUsers);exit;

            $report_data = [];
            foreach ($getUsers as $key => $value) {
                $i = 0;
                $from_date_new = $from_date_formatted;
                while (strtotime($from_date_new) <= strtotime($to_date_formatted)) {
                    $i++;
                    if (isset($newHrmsAtt[$value->id]) && array_key_exists($from_date_new, $newHrmsAtt[$value->id])) {
                        $report_data[$from_date_new][$value->id] = (array) $newHrmsAtt[$value->id][$from_date_new];
                    } else {
                        $report_data[$from_date_new][$value->id] = (array) $value;
                    }
                    $day_name =lcfirst(Carbon::parse($from_date_new)->format('l')); 
                    // when they have day off 
                    $report_data[$from_date_new][$value->id]['day_status'] = 'day';
                    if($value->$day_name == 0){
                        $report_data[$from_date_new][$value->id]['day_status'] = 'offday';
                    }

                    if (isset($empLeaves[$value->id]) && array_key_exists($from_date_new, $empLeaves[$value->id])) 
                    {
                        $report_data[$from_date_new][$value->id]['leave'] = $empLeaves[$value->id][$from_date_new];
                    }
                    if (isset($empHolidays[$value->department_id]) && array_key_exists($from_date_new, $empHolidays[$value->department_id])) 
                    {
                        $report_data[$from_date_new][$value->id]['holiday'] = $empHolidays[$value->department_id][$from_date_new];
                    }
                    $from_date_new = date("Y-m-d", strtotime("+1 day", strtotime($from_date_new)));
                }
            }
        
            $res['users'] = $getUsers;
            $res['allData'] = $report_data;
            $res['leaveData'] = $empLeaves;
            $res['holidayData'] = $empHolidays;
            $res['from_date'] = $request->from_date;
            $res['to_date'] = $request->to_date;
            $res['selEmp'] = $request->emp_id;
            $res['selDept'] = $request->department_id;
        // echo "<pre>";print_r($report_data);exit;
        return is_mobile($type, "HRMS.hrms_attendance_report.multiEmpAttendanceReport", $res,'view');
    }

    public function getAttandanceData(Request $request){

        $attId = isset($request->attId) ? explode(',',$request->attId) : [];
        $sub_institute_id = session()->get('sub_institute_id');

       $attData = DB::table('hrms_attendances as ha')
                ->join('tbluser as u','u.id','=','ha.user_id')
                ->join('hrms_departments as hd','hd.id','=','u.department_id')
                ->selectRaw('ha.*,CONCAT_WS(" ", COALESCE(u.first_name, "-"), COALESCE(u.last_name, "-")) as full_name,hd.department')
                ->where('ha.sub_institute_id',$sub_institute_id)->whereIn('ha.id',$attId)->get()->toArray();
       
       return $attData;
    }

      // for employees day wise Attendance A/P/L/H 02-09-2024
      public function DaywiseAttendanceReportIndex(Request $request){
        $type= $request->type;
        $res['from_date'] = Carbon::now()->startOfMonth()->toDateString();
        $res['to_date'] = Carbon::now()->toDateString();
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, "HRMS.hrms_attendance_report.daywiseAttendanceReport", $res,'view');
    }

    public function DaywiseAttendanceportCreate(Request $request){
        $type= $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        if($type=='API'){
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }
        $department_id = ($request->department_id!=0) ? implode(',',$request->department_id) : '';
        $employee_id = ($request->emp_id!=0) ? implode(',',$request->emp_id) : '';

        $res['from_date'] = $from_date = $request->from_date;
        $res['to_date'] = $to_date = $request->to_date;
        $res['department_id'] = $request->department_id;
        $res['employee_id'] = $request->emp_id;
        // get users to get details
        $getUsers =employeeDetails($sub_institute_id,$employee_id,'',$department_id);

        $get_hrms_holidays = DB::table('hrms_holidays')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereBetween('from_date',[$from_date,$to_date])
            ->oRwhereBetween('to_date',[$from_date,$to_date])
            ->get()->toArray();
        $holidays = [];
        foreach ($get_hrms_holidays as $key => $value) {
            $hfrom_date = $value->from_date;
            $ht_date = $value->to_date;
            while (strtotime($hfrom_date) <= strtotime($ht_date)) {
                $holidays[] = $hfrom_date;
                $hfrom_date = date("Y-m-d", strtotime("+1 day", strtotime($hfrom_date)));
            }
        }
            // echo "<pre>";print_r($employee_id);exit;
        $getLeave = DB::table('hrms_emp_leaves')->where('sub_institute_id',$sub_institute_id)
        ->whereBetween('from_date',[$from_date,$to_date])
        ->oRwhereBetween('to_date',[$from_date,$to_date])
        ->when($employee_id!='',function($q) use($employee_id){
            $q->whereRaw('user_id IN ('.$employee_id.')');
        })
        ->get()->toArray();
        $leaveUsers = [];
       
        foreach($getLeave as $key=>$value){
            $leaveDates = $value->from_date;
            while (strtotime($leaveDates) <= strtotime($value->to_date)) {
                $leaveUsers[$value->user_id][$leaveDates] = $value;
                $leaveDates = date("Y-m-d", strtotime("+1 day", strtotime($leaveDates)));
            }
        }
        // echo "<pre>";print_r($leaveUsers);exit; 
        $selDates= $selDays = [];
        foreach ($getUsers as $key => $value) {
            $attData = [];
            $from_date_new=$from_date;
            while (strtotime($from_date_new) <= strtotime($to_date)) {
                $date = Carbon::createFromFormat('Y-m-d', $from_date_new);
                $dayName =strtolower($date->format('l')); 

                $inDay = $dayName.'_in_date';
                $outDay =$dayName.'_out_date';
                $userPunchIn = Carbon::parse($value[$inDay])->format('H:i:S');
                $userPunchOut = Carbon::parse($value[$outDay])->format('H:i:s');
                $att=DB::table('hrms_attendances')->where(['sub_institute_id'=>$sub_institute_id,'user_id'=>$value['id']])->where('day',$from_date_new)->first();
                // echo "<pre>";print_r($value['id']);
                $thisDate = Carbon::parse($from_date_new);
                if($att){
                    $attPunchIn = Carbon::parse($att->punchin_time)->format('H:i:s');
                    $attPunchOut = Carbon::parse($att->punchout_time)->format('H:i:s');
                   
                    $attData[$value['id']][$from_date_new] = "P";
                    // late Come or half day  leave
                    if($userPunchIn < $attPunchIn && $attPunchIn!=null && $attPunchIn!=''){
                        if (isset($leaveUsers[$value['id']][$from_date_new]) ){
                            if($leaveUsers[$value['id']][$from_date_new]->day_type=="0.5"){
                                $attData[$value['id']][$from_date_new] = "HD";
                            }else{
                                $attData[$value['id']][$from_date_new] = "LT";
                            }
                        }else{
                            $attData[$value['id']][$from_date_new] = "LT";
                        }
                    }
                    // early going 
                    if($userPunchOut > $attPunchOut && $attPunchOut!=null && $attPunchOut!=''){
                        $attData[$value['id']][$from_date_new] = "ED";
                    }
                }
                else if (isset($leaveUsers[$value['id']][$from_date_new]) ){
                    if($leaveUsers[$value['id']][$from_date_new]->day_type=="0.5"){
                        $attData[$value['id']][$from_date_new] = "HD";
                    }else{
                        $attData[$value['id']][$from_date_new] = "A";
                    }
                }
                else if(in_array($from_date_new,$holidays)  && !in_array($from_date_new,$attData)){
                    $attData[$value['id']][$from_date_new] = "-";
                }
                else if ($thisDate->isSunday() && !in_array($from_date_new,$attData)) {
                    $attData[$value['id']][$from_date_new] = "Sun";
                }
                else{
                    $attData[$value['id']][$from_date_new] = "N/A";
                }
                // fill array in user Data
                if(!in_array($from_date_new,$selDates)){
                    $selDates[$from_date_new]=carbon::parse($from_date_new)->format('d-m-Y');
                }
                if(!in_array($from_date_new,$selDays)){
                    $selDays[$from_date_new]=carbon::parse($from_date_new)->format('l');
                }
                $from_date_new = date("Y-m-d", strtotime("+1 day", strtotime($from_date_new)));
            }

            $getUsers[$key]['attData'] = $attData;

        }
        $res['selDates'] = $selDates;
        $res['selDays'] = $selDays;
        $res['attDetails'] = $getUsers;
        // echo "<pre>";print_r($res['attDetails']);
        // exit;
        return is_mobile($type, "HRMS.hrms_attendance_report.daywiseAttendanceReport", $res,'view');
    }
}
