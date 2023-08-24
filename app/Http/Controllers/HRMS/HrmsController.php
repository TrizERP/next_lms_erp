<?php

namespace App\Http\Controllers\HRMS;

use App\Http\Controllers\Controller;
use App\Models\HrmsAttendance;
use App\Models\HrmsInOutTime;
use App\Models\HrmsJobTitle;
use App\Models\PayrollType;
use App\Models\user\tbluserModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;


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

        //return $request->all();
        if ($request->indate && $request->intime) {
            $hrmsInOutTime = new HrmsInOutTime();
            $hrmsInOutTime->user_id = $userId;
            $hrmsInOutTime->in_time = Carbon::now()->format('h:i:s');
            $hrmsInOutTime->day = Carbon::parse($request->indate)->format('Y-m-d');
            $hrmsInOutTime->client_id = $clientId;
            $hrmsInOutTime->sub_institute_id = $subInstituteId;
            $hrmsInOutTime->save();
        }
        return is_mobile($type, "hrms_inout_time.index", null, "redirect");
        //return redirect('hrms-inout-time')->with(['message' =>'check In successfully']);
    }

    public function hrmsOutTimeStore(Request $request)
    {
        $type = $request->input('type');
        if ($type == 'API') $userId = $request->input('user_id');
        else $userId = $request->session()->get('user_id');
        $hrmsInOutTime = HrmsInOutTime::where([['user_id', $userId], ['day', Carbon::now()->format('Y-m-d')], ['out_time', null]])->first();
        if ($hrmsInOutTime) {
            $hrmsInOutTime->out_time = Carbon::now()->format('h:i:s');
            $hrmsInOutTime->save();
        }
        return is_mobile($type, "hrms_inout_time.index", null, "redirect");
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

        $employeeLists = tbluserModel::where('sub_institute_id', $subInstituteId)->get();

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

    public function hrmsAttendanceReport(Request $request) {

        $type = $request->input('type');
        if ($type == 'API') {
            $sub_institute_id = $request->input('sub_institute_id');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }
        $employees = $employeeLists = tbluserModel::where('sub_institute_id', $sub_institute_id)->get();
        $hrmsList = HrmsAttendance::with('getUser');
        if ($request->from_date && $request->end_date) {
            $hrmsList = $hrmsList->where('punchin_time', '>=', $request->from_date . ' 00:00:00')->where('punchout_time', '<=', $request->end_date . ' 23:59:59');
            $from_date = $request->from_date;
            $end_date = $request->end_date;
        } else {
            $from_date = Carbon::now();
            $end_date = Carbon::now();
        }
        if($request->employee_id) {
            $employees = $employees->where('id', $request->employee_id);
            $hrmsList = $hrmsList->where('user_id',$request->employee_id);
        }
        $hrmsList = $hrmsList->get();

        //return json_decode($employeeSalaryStructures[0]['employee_salary_data'], true);
//        return view('HRMS.hrms_attendance_report.index', compact('employees', 'employeeLists','from_date','end_date','hrmsList'));
        return is_mobile($type, "HRMS.hrms_attendance_report.index", compact('employees','employeeLists','from_date','end_date','hrmsList'), "view",'compact');

    }

    public function earlyGoingHrmsAttendanceReport(Request $request) {
        $employee_id = 0;
        $type = $request->input('type');
        if ($type == 'API') {
            $sub_institute_id = $request->input('sub_institute_id');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }
        $employees = $employeeLists = tbluserModel::where('sub_institute_id', $sub_institute_id)->get();
        $hrmsList = HrmsAttendance::with('getUser');
        $date = $request->date ?? Carbon::now();
        $timestamp = strtotime($date);
        $day = date('D', $timestamp);
//        return  $request->all();

        if($request->employee_id) {
            $employee_id = $request->employee_id;
            $hrmsList = $hrmsList->where('day', $date)->where('user_id',$request->employee_id)->get();
        }else{
            $hrmsList = $hrmsList->where('day', $date)->get();
//            return $hrmsList;
        }

        $hrmsList = $hrmsList->map(function ($e) use ($day){
            if($day =='Mon' && !$e->getUser['monday']) {
                if($e->getUser['monday_out_date'] &&  $e->getUser['monday_out_date'] >=  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['monday_out_date'];
                }
            }
            if($day =='Tue' && !$e->getUser['tuesday']) {
                if($e->getUser['tuesday_out_date'] &&  $e->getUser['tuesday_out_date'] >=  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['tuesday_out_date'];
                }
            }
            if($day =='Wed' && !$e->getUser['wednesday']) {
                if($e->getUser['wednesday_out_date'] &&  $e->getUser['wednesday_out_date'] >=  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['wednesday_out_date'];
                }
            }
            if($day =='Thu' && !$e->getUser['thursday']) {
                if($e->getUser['thursday_out_date'] &&  $e->getUser['thursday_out_date'] >=  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['saturday_out_date'];
                }
            }
            if($day =='Fri' && !$e->getUser['friday']) {
                if($e->getUser['friday_out_date'] &&  $e->getUser['friday_out_date'] >=  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['friday_out_date'];
                }
            }
            if($day =='Sat' && !$e->getUser['saturday']) {
                if($e->getUser['saturday_out_date'] &&  $e->getUser['saturday_out_date'] >=  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['saturday_out_date'];
                }
            }
            if($day =='Sun' && !$e->getUser['sunday']) {
                if($e->getUser['sunday_out_date'] &&  $e->getUser['sunday_out_date'] >=  date('H:i:s',strtotime($e->punchout_time))) {
                    $e['is_late'] = 1;
                    $e['expected_time'] = $e->getUser['sunday_out_date'];
                }
            }
            return $e;
        })->where('is_late',1);
        return is_mobile($type, "HRMS.hrms_attendance_report.early_going_report", compact('employees','employee_id','employeeLists','date','hrmsList'), "view",'compact');

       // return view('HRMS.hrms_attendance_report.early_going_report', compact('employees','employee_id', 'employeeLists','date','hrmsList'));
    }
}
