<?php

namespace App\Http\Controllers\HRMS;

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
            $hrmsJobTitle = HrmsJobTitle::find($id);
            return is_mobile($type, "HRMS.hrms_job_title.create", compact('hrmsJobTitle'), "view",'compact');
            //return view('HRMS.hrms_job_title.create', compact('hrmsJobTitle'));
        }
        $hrmsJobTitle['title'] = '';
        $hrmsJobTitle['description'] = '';
        $hrmsJobTitle['is_active'] = 1;
        $hrmsJobTitle['id'] = 0;
        return is_mobile($type, "HRMS.hrms_job_title.create", compact('hrmsJobTitle'), "view",'compact');
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
        return is_mobile($type, "hrms-job-title", null, "redirect");
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
        $hrmsInOutTimeDetails = HrmsInOutTime::where([['user_id', $userId], ['day', Carbon::now()->format('Y-m-d')]])->get();
        if (count($hrmsInOutTimeDetails) == 1) {
            $hrmsInOutTimeDetails = $hrmsInOutTimeDetails->first();
            $hrmsInOutTime['button'] = 'out';
            if ($hrmsInOutTimeDetails->out_time != null) {
                $hrmsInOutTime['time'] = $hrmsInOutTimeDetails->out_time;
                $hrmsInOutTime['button_disable'] = true;
            } else {
                $hrmsInOutTime['time'] = Carbon::now()->format('h:i:s');
                $hrmsInOutTime['button_disable'] = false;
            }

        } else {
            $hrmsInOutTime['button'] = 'in';
            $hrmsInOutTime['time'] = Carbon::now()->format('h:i:s');
            $hrmsInOutTime['button_disable'] = false;
        }
        $hrmsInOutTime['date'] = Carbon::now()->format('d-m-Y');
        $hrmsInOutTime['id'] = 0;
        //return is_mobile($type, "HRMS.hrms_inout_time.index", compact('hrmsInOutTime'), "view",'compact');
       
        return is_mobile($type, "HRMS.hrms_inout_time.index", $hrmsInOutTime, "view");
//        return view('HRMS.hrms_inout_time.index', compact('hrmsInOutTime'));
    }

    public function hrmsInTimeStore(Request $request)
    {

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
        $res['status_code']=0;
        $res['message']="Failed to time in";
        //return $request->all();
        if ($request->indate && $request->intime) {
            $hrmsInOutTime = new HrmsInOutTime();
            $hrmsInOutTime->user_id = $userId;
            $hrmsInOutTime->in_time = Carbon::now()->format('h:i:s');
            $hrmsInOutTime->day = Carbon::parse($request->indate)->format('Y-m-d');
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
        if ($type == 'API') $userId = $request->input('user_id');
        else $userId = $request->session()->get('user_id');
        $hrmsInOutTime = HrmsInOutTime::where([['user_id', $userId], ['day', Carbon::now()->format('Y-m-d')], ['out_time', null]])->first();
        
        $res['status_code']=0;
        $res['message']="Failed to time out";
        if ($hrmsInOutTime) {
            $hrmsInOutTime->out_time = Carbon::now()->format('h:i:s');
            $hrmsInOutTime->save();
            $res['status_code']=1;
            $res['message']="Success to time out";
        }
        return is_mobile($type, "hrms_inout_time.index", $res, "redirect");
        //return redirect('hrms-inout-time')->with(['message' =>'check Out successfully']);
    }

    public function hrmsAttendance(Request $request)
    {
        $type = $request->input('type');
        if ($type == 'API') $subInstituteId = $request->input('sub_institute_id');
        else   $subInstituteId = $request->session()->get('sub_institute_id');
        if ($request->employee_id) {
            $hrmsAttendanceInOutTime['employee_id'] = $request->employee_id;
            $date = $request->date ? Carbon::parse($request->date)->format('Y-m-d') : Carbon::now()->format('Y-m-d');
            if ($date) {
                $hrmsAttendanceDetails = HrmsAttendance::where([['user_id', $request->employee_id], ['day', $date]])->first();
                $hrmsAttendanceInOutTime['date'] = Carbon::parse($request->date);
                if ($hrmsAttendanceDetails) {
                    $hrmsAttendanceInOutTime['button'] = 'out';
                    $hrmsAttendanceInOutTime['note'] = 2;
                } else {
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

        $employeeLists = tbluserModel::where('sub_institute_id', $subInstituteId)->where('status', 1)->get();

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
//        return $request->all();
//       return Carbon::parse($request->indate)->format('Y-m-d');
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
//        return $hrmsAttendanceInTime->punchin_time;
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
	
	    $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('department_id', $department_id)->get()->toArray();

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
	    $employee_id = $request->get('employee_id');
        
        $from_date_formatted = Carbon::createFromFormat('Y-m-d', $from_date)->format('Y-m-d');
        $to_date_formatted = Carbon::createFromFormat('Y-m-d', $to_date)->format('Y-m-d');

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('department_id', $department_id)->get()->toArray();
        
        $hrmsList = DB::table('hrms_attendances as ha')
        ->join('tbluser as u', 'u.id', '=', 'ha.user_id')
        ->selectRaw("DISTINCT ha.*, ha.id as atten_id,  u.*, CONCAT_WS(' ',u.first_name,u.last_name) AS employee_name ")
        ->where('ha.sub_institute_id', $sub_institute_id)
        ->whereBetween('ha.day', [$from_date_formatted, $to_date_formatted])
        ->where('ha.user_id', $employee_id)
        ->get()
        ->toArray();

        $get_hrms_emp_leaves = DB::table('hrms_emp_leaves as hel')
        ->join('tbluser as u', 'u.id', '=', 'hel.user_id')
        ->join('hrms_leave_types as hlt', 'hlt.id', '=', 'hel.leave_type_id')
        ->selectRaw("hel.*, hlt.*, u.*, CONCAT_WS(' ',u.first_name,u.last_name) AS employee_name ,hel.leave_type_id as leave_id")
        ->where('hel.sub_institute_id', $sub_institute_id)
        ->where('hel.from_date','>=',$from_date_formatted)
        ->where('hel.to_date','<=',$to_date_formatted)
        ->where('hel.user_id', $employee_id)
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
            $punchin_time = Carbon::createFromFormat('Y-m-d H:i:s', $punchin_time);
            $punchin_time = strtolower($punchin_time->format('H:i:s'));

            $punchout_time = $value->punchout_time;
            $punchout_time = Carbon::createFromFormat('Y-m-d H:i:s', $punchout_time);
            $punchout_time = strtolower($punchout_time->format('H:i:s'));

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
        $res['employee_id'] = $employee_id;
        $res['department_id'] = $department_id;
        $res['departments'] = $departments;
 
        //return view('HRMS.hrms_attendance_report.index', compact('employees', 'from_date_formatted', 'to_date_formatted', 'report_data', 'employee_id', 'department_id', 'departments'));
        return is_mobile($type, "HRMS/hrms_attendance_report/index", $res, "view");
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
        $employee_id = 0;
        $type = $request->input('type');
        if ($type == 'API') {
            $sub_institute_id = $request->input('sub_institute_id');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }

        $department_id = $request->get('department_id');
	    $employee_id = $request->get('employee_id');
	    $date = $request->get('date');
        
        $date_formatted = Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        $timestamp = strtotime($date_formatted);
        $day = date('D', $timestamp);

        $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');

        $employees = tbluserModel::where('sub_institute_id', $sub_institute_id)->where('department_id', $department_id)->where('status', 1)->get();
        
        $hrmsList = HrmsAttendance::with('getUser');
        
        if($employee_id) {
            $hrmsList = $hrmsList->where('day', $date_formatted)->where('user_id', $employee_id)->get();
        }else{
            $hrmsList = $hrmsList->where('day', $date_formatted)->get();
        }
        
        $hrmsList = $hrmsList->map(function ($e) use ($day)
        {
            if($day =='Mon' && $e->getUser['monday']) {
                if($e->getUser['monday_out_date'] &&  $e->getUser['monday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['monday_out_date'];
                }
            }
            if($day =='Tue' && !$e->getUser['tuesday']) {
                if($e->getUser['tuesday_out_date'] &&  $e->getUser['tuesday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['tuesday_out_date'];
                }
            }
            if($day =='Wed' && $e->getUser['wednesday']) {
                if($e->getUser['wednesday_out_date'] &&  $e->getUser['wednesday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['wednesday_out_date'];
                }
            }
            if($day =='Thu' && $e->getUser['thursday']) {
                if($e->getUser['thursday_out_date'] &&  $e->getUser['thursday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['saturday_out_date'];
                }
            }
            if($day =='Fri' && $e->getUser['friday']) {
                if($e->getUser['friday_out_date'] &&  $e->getUser['friday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['friday_out_date'];
                }
            }
            if($day =='Sat' && $e->getUser['saturday']) {
                if($e->getUser['saturday_out_date'] &&  $e->getUser['saturday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['saturday_out_date'];
                }
            }
            if($day =='Sun' && $e->getUser['sunday']) {
                if($e->getUser['sunday_out_date'] &&  $e->getUser['sunday_out_date'] >  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['sunday_out_date'];
                }
            }
            return $e;
        })->where('is_late',1);

        $res['employees'] = $employees;
        $res['date_formatted'] = $date_formatted;
        $res['hrmsList'] = $hrmsList;
        $res['employee_id'] = $employee_id;
        $res['department_id'] = $department_id;
        $res['departments'] = $departments;
 
        //return view('HRMS.hrms_attendance_report.early_going_report', compact('employees', 'employee_id', 'date_formatted', 'hrmsList', 'type', 'departments', 'department_id'));
        return is_mobile($type, "HRMS/hrms_attendance_report/early_going_report", $res, "view");
    }
}
