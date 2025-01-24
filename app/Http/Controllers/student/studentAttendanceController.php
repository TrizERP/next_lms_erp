<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\getCountDays;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function Symfony\Component\HttpKernel\Profiler\read;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;
use App\Models\school_setup\SchoolModel;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\student\yearly_attendance_controller;

class studentAttendanceController extends Controller
{

    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $marking_period_id = session()->get('term_id');
        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
            $user_id = $request->input('user_id');

            $result = DB::table('class_teacher as ct')
            ->join('standard as s', function ($join) use ($syear) {
                $join->on('ct.standard_id', '=', 's.id')
                    ->where('ct.sub_institute_id', '=', 's.sub_institute_id')
                    ->where('syear', '=', $syear);
            })
            ->join('division as d', function ($join) {
                $join->on('d.id', '=', 'ct.division_id')
                    ->where('d.sub_institute_id', '=', 'ct.sub_institute_id');
            })
            ->select('ct.standard_id', 'ct.division_id', 's.name as standard_name', 'd.name as division_name')
            ->where('ct.sub_institute_id', $sub_institute_id)
            ->where('ct.teacher_id', $user_id)
            ->get()
            ->toArray();


            $result = array_map(function ($value) {
                return (array)$value;
            }, $result);

            $res['standardDivision'] = $result;
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "student/student_attendance", $res, "view");
    }

    public function showStudent(Request $request)
    {
        $type = $request->input('type');
        $date = $request->input('date');
       
        $term_id = $request->session()->get('term_id');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        if ($type == "API") {
            $term_id = $request->input('term_id');
            $syear = $request->input('syear');
            $sub_institute_id = $request->input('sub_institute_id');
        }

        $standard_division_orignal = $request->input('standard_division');
        list($standard, $division) = explode("||", $standard_division_orignal);
        $grade = '';

        $sundays = getCountDays($date, $date);
       
        $holidays = DB::table("calendar_events")
            ->where('school_date', '=', $date)
            ->whereIn('event_type',['holiday','event'])
            ->where('sub_institute_id', '=', $sub_institute_id)
            ->where('syear', '=', $syear)
            ->get()
            ->toArray();

        $single_standard = DB::table("standard")
            ->select('name')
            ->where('id', '=', $standard)
            ->where('sub_institute_id', '=',$sub_institute_id)
            ->first();
        
        $single_division = DB::table("division")
            ->select('name')
            ->where('id', '=', $division)
            ->where('sub_institute_id', '=', $sub_institute_id)
            ->first();
            
        if(!empty($sundays))
        {
            foreach ($sundays['S'] as $key => $value) {
                $sundays[$key] = (int)date('d', strtotime($value));
            }
        }

        unset($sundays['S']);

        // $student_data = SearchStudent($grade, $standard, $division, $sub_institute_id, $syear);

        $extraSearchArray = [];
        $extraSearchArray['tblstudent.sub_institute_id'] = $sub_institute_id;
        $extraSearchArray['tblstudent_enrollment.syear'] = $syear;
        //$extraSearchArray['tblstudent.status'] = 1;
        if ($standard != '') {
            $extraSearchArray['tblstudent_enrollment.standard_id'] = $standard;
        }
        if ($division != '') {
            $extraSearchArray['tblstudent_enrollment.section_id'] = $division;
        }

        $extraRaw = " 1 = 1 AND tblstudent_enrollment.end_date IS NULL ";
        // search by batch 
        if($request->has('batch_sel') && $request->input('batch_sel') != null){
            $batchs = DB::table('batch')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'standard_id'=>$standard,'division_id'=>$division])->get()->toArray();
            $res['batch_id'] = $request->batch_sel;    
            $res['batchs']=$batchs;    
            
            $extraRaw.=" AND batch.id='".$request->batch_sel."'";
        }
        //START Check for class teacher assigned standards

        $classTeacherStdArr = session()->get('classTeacherStdArr');
        if (isset($classTeacherStdArr)) {
            $extraRaw = "standard.id IN (' ')";            
            if (count($classTeacherStdArr) > 0) {
                $extraRaw = "standard.id IN (" . implode(",", $classTeacherStdArr) . ")";
            } 
        }

        $classTeacherDivArr = session()->get('classTeacherDivArr');
        if (isset($classTeacherStdArr)) {
            if (count($classTeacherDivArr) > 0) {
                $extraRaw .= " and division.id IN (" . implode(",", $classTeacherDivArr) . ")";
            }
        }
        //END Check for class teacher assigned standards

        $student_data = tblstudentModel::select('tblstudent_enrollment.*', 'tblstudent.*', 'standard.name as standard',
            'division.name as division', 'academic_section.title as grade','batch.id as batch_id','batch.title as batch_title')
            ->join("tblstudent_enrollment", function ($join) {
                $join->on("tblstudent_enrollment.student_id", "=", "tblstudent.id")
                    ->whereNull('tblstudent_enrollment.end_date');
            })
            ->join("academic_section", function ($join) {
                $join->on("academic_section.id", "=", "tblstudent_enrollment.grade_id");
            })
            ->join("standard", function ($join) {
                $join->on("standard.id", "=", "tblstudent_enrollment.standard_id");
            })
            ->join("division", function ($join) {
                $join->on("division.id", "=", "tblstudent_enrollment.section_id");
            })
            ->leftJoin('batch','batch.id','=','tblstudent.studentbatch')
            ->where($extraSearchArray)
            ->whereRaw($extraRaw)
            ->orderby('tblstudent_enrollment.roll_no')
            ->get()->toArray();

        if (count($student_data) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No Student Data Found";
            return is_mobile($type, "student_attendance.index", $res);
        }

        $attendanceArray = [];
        $attendanceArray['syear'] = $syear;
        $attendanceArray['sub_institute_id'] = $sub_institute_id;
        // $attendanceArray['term_id'] = $term_id;
        $attendanceArray['attendance_date'] = $date;
        $attendanceArray['standard_id'] = $standard;
        $attendanceArray['section_id'] = $division;

        $data = DB::table("attendance_student")->where($attendanceArray)->get()->toArray();
        $attendanceData = [];
        if (count($data) > 0) {
            foreach ($data as $key => $value) {
                $attendanceData[$value->student_id] = $value->attendance_code;
            }
        }

        if (!empty($holidays) && $holidays[0]->event_type === "holiday") 
        {
            $res['status_code'] = 0;
            $res['message'] = "$date is a holiday, so you can't take attendance for " . $single_standard->name . '/' . $single_division->name;

            return is_mobile($type, "student_attendance.index", $res);
        } 
        else if (!empty($sundays)) 
        {
            $res['status_code'] = 0;
            $res['message'] = "$date is sunday, so you can't take attendance for " . $single_standard->name . '/' . $single_division->name;

            return is_mobile($type, "student_attendance.index", $res);
        } 
        else 
        {
            $res['status_code'] = 1;
            $res['message'] = "Success";
            $res['student_data'] = $student_data;
        }
        
        $res['date'] = $date;
        $res['standard_division'] = $standard_division_orignal;
        $res['attendance_data'] = $attendanceData;

        return is_mobile($type, "student/student_attendance", $res, "view");
    }

    public function saveStudentAttendance(Request $request)
    {
        $dateTime = new \DateTime($request->input('date'));
        $date = $dateTime->format('Y-m-d');
        //$date = $request->input('date');
        $type = $request->input('type');
        $students = $request->input('student');

        if ($type != "API") {
            $syear = $request->session()->get('syear');
            $term_id = $request->session()->get('term_id');
            $user_id = $request->session()->get('user_id');
            $user_profile_id = $request->session()->get('user_profile_id');
            $sub_institute_id = $request->session()->get('sub_institute_id');
        } else {
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
                return response()->json($response, 401);
            }

            $syear = $request->input('syear');
            $user_id = $request->input('teacher_id');
            $user_profile_id = $request->input('user_profile_id');
            $sub_institute_id = $request->input('sub_institute_id');
            if ($syear == '' || $date == '' || $students == '' || $user_id == '' || $user_profile_id == '' || $sub_institute_id == '') {
                $res['status_code'] = 0;
                $res['message'] = "Parameter Missing.";

                return is_mobile($type, "student_attendance.index", $res);
            }
        }

        $standard_division_orignal = $request->input('standard_division');
        $standard_division = explode("||", $standard_division_orignal);
        $standard = $standard_division[0];
        $division = $standard_division[1];

        foreach ($students as $student_id => $attendance) {
            $attendanceArray = [];
            $attendanceArray['syear'] = $syear;
            $attendanceArray['sub_institute_id'] = $sub_institute_id;
            $attendanceArray['student_id'] = $student_id;
            //$attendanceArray['term_id'] = $term_id;
            $attendanceArray['attendance_date'] = $date;
            $attendanceArray['standard_id'] = $standard;
            $attendanceArray['section_id'] = $division;

            $data = DB::table("attendance_student")->where($attendanceArray)->get()->toArray();

            $attendanceArray['attendance_code'] = $attendance;
            $attendanceArray['teacher_id'] = $user_id;
            $attendanceArray['user_group_id'] = $user_profile_id;
            $attendanceArray['created_by'] = $user_id;

            if (count($data) > 0) {
                DB::table("attendance_student")->where(['id' => $data[0]->id])->update($attendanceArray);
            } else {
                DB::table("attendance_student")->insert($attendanceArray);

                $sendRequest = new Request(['student_id'=>$student_id,'attendance'=>$attendance,'date'=>$date,'created_by'=>$user_id,'syear'=>$syear,'sub_institute_id'=>$sub_institute_id]);
            
                if($date == date('Y-m-d')){
                    $sendNotification= $this->sendNotificationAtt($sendRequest);
                }
            }
        }

        $res['status_code'] = 1;
        $res['message'] = "Attendance successfully taken";

        return is_mobile($type, "student_attendance.index", $res);
    }

    /*Old Save Student Attendance function
    public function saveStudentAttendance(Request $request) {
        $date = $request->input('date');
        $type = $request->input('type');
        $students = $request->input('student');

        if ($type != "API") {
            $syear = $request->session()->get('syear');
            $term_id = $request->session()->get('term_id');
            $user_id = $request->session()->get('user_id');
            $user_profile_id = $request->session()->get('user_profile_id');
            $sub_institute_id = $request->session()->get('sub_institute_id');
        } else {
            $syear = $request->input('syear');
            $term_id = $request->input('term_id');
            $user_id = $request->input('user_id');
            $user_profile_id = $request->input('user_profile_id');
            $sub_institute_id = $request->input('sub_institute_id');
        }

        $standard_division_orignal = $request->input('standard_division');
        $standard_division = explode("||", $standard_division_orignal);
        $standard = $standard_division[0];
        $division = $standard_division[1];

        foreach ($students as $student_id => $attendance) {
            $attendanceArray = array();
            $attendanceArray['syear'] = $syear;
            $attendanceArray['sub_institute_id'] = $sub_institute_id;
            $attendanceArray['student_id'] = $student_id;
            $attendanceArray['term_id'] = $term_id;
            $attendanceArray['attendance_date'] = $date;
            $attendanceArray['standard_id'] = $standard;
            $attendanceArray['section_id'] = $division;

            $data = DB::table("attendance_student")->where($attendanceArray)->get()->toArray();

            $attendanceArray['attendance_code'] = $attendance;
            $attendanceArray['teacher_id'] = $user_id;
            $attendanceArray['user_group_id'] = $user_profile_id;
            $attendanceArray['created_by'] = $user_id;

            if (count($data) > 0) {
                DB::table("attendance_student")->where(['id' => $data[0]->id])->update($attendanceArray);
            } else {
                DB::table("attendance_student")->insert($attendanceArray);
            }

        }

        $res['status_code'] = 1;
        $res['message'] = "Attendance successfully taken";
        return is_mobile($type, "student_attendance.index", $res);
    }*/

    public function daywiseStudentAttendance(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "student/daywise_attendance_report", $res, "view");
    }

    public function get_batch(Request $request){
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $standard_id = $request->standard_id;
        $division_id = $request->division_id;
        
        $batch = DB::table('batch')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'standard_id'=>$standard_id,'division_id'=>$division_id])->get()->toArray();
        return $batch;
    }

    public function showDaywiseStudentAttendance(Request $request)
    {
        $type = $request->input('type');
        $date = $request->input('date');
        $taken = $request->input('taken');
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        if(in_array($type,["API","JSON"])){
            $sub_institute_id=$request->sub_institute_id;
            $syear = $request->syear;
        }
        $data = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                $join->whereRaw("s.id = se.student_id AND se.syear = '" . $syear . "' AND s.sub_institute_id = se.sub_institute_id
                AND se.end_date IS NULL");
            })->join('standard as sm', function ($join) {
                $join->whereRaw('se.standard_id = sm.id');
            })->join('division as dm', function ($join) {
                $join->on('se.section_id', '=', 'dm.id');
            })->leftJoin('attendance_student as a', function ($join) use ($date, $syear) {
                $join->on('s.id', '=', 'a.student_id')->whereRaw("sm.id = a.standard_id AND dm.id = a.section_id
                    AND s.sub_institute_id = a.sub_institute_id AND a.attendance_date = '" . $date . "' and a.syear = '" . $syear . "'");
            })->selectRaw("CONCAT_WS('/',sm.name,dm.name) AS standard_name, dm.name AS division_name,se.standard_id,
                se.section_id,se.student_id,a.attendance_code,s.gender, SUM(CASE WHEN s.gender = 'M' THEN 1 ELSE 0 END) AS BOY,
                SUM(CASE WHEN s.gender = 'F' THEN 1 ELSE 0 END) AS GIRL, SUM(CASE WHEN s.gender = 'M' AND a.attendance_code = 'P'
                THEN 1 ELSE 0 END) TBP, SUM(CASE WHEN s.gender = 'F' AND a.attendance_code = 'P' THEN 1 ELSE 0 END) TGP,
                SUM(CASE WHEN s.gender = 'M' AND a.attendance_code = 'A' THEN 1 ELSE 0 END) TBA, SUM(CASE WHEN s.gender = 'F'
                AND a.attendance_code = 'A' THEN 1 ELSE 0 END) TGA")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->groupBy('se.standard_id', 'se.section_id');

            if ($taken == 'no') {
                $data = $data->havingNull('attendance_code');
            } else if ($taken == 'yes'){
                $data = $data->havingNotNull('attendance_code');
            }
    
            $data = $data->orderBy('sm.sort_order', 'ASC')->orderBy('dm.id', 'ASC')->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['date'] = $date;
        $res['taken'] = $taken;
        $res['attendance_data'] = $data;

        return is_mobile($type, "student/daywise_attendance_report", $res, "view");
    }

    public function monthwiseStudentAttendance(Request $request)
    {
        $type = $request->input('type');

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "student/monthwise_attendance_report", $res, "view");
    }

    public function showMonthwiseStudentAttendance(Request $request)
    {
        $type = $request->input('type');
        $month = $res['month'] =  $request->input('month');
        $grade_id = $res['grade_id'] = $request->input("grade");
        $standard_id = $res['standard_id'] = $request->input("standard");
        $division_id = $res['division_id'] =  $request->input("division");
        $selected_year = $res['year'] = $request->input("year");
        $enroll = $res['enrollment_no'] = $request->input("enrollment_no");
        $syear = session()->get('syear');
        $sub_institute_id = session()->get('sub_institute_id');
        $batch="";

        if(in_array($type,["API","JSON"])){
            $syear = $request->get('syear');
            $sub_institute_id = $request->get('sub_institute_id');
        }

        if($request->has('batch_sel')){
            $batchs = DB::table('batch')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear,'standard_id'=>$standard_id,'division_id'=>$division_id])->get()->toArray();
            $res['batch_id'] = $request->batch_sel;    
            $res['batchs']=$batchs;    
            
            $batch=$request->batch_sel;
        }

        // get student list 
        $student_data = $res['student_data'] =  SearchStudent($grade_id, $standard_id, $division_id,"","", "","","", "", $enroll,"",$batch);

        $from_date = $selected_year . "-" . $month . "-01";
        $to_date = date('Y-m-t', strtotime($selected_year . "-" . $month));

        $sundays = getCountDays($from_date, $to_date);

        $whereAtt['syear'] = $syear;
        $whereAtt['sub_institute_id'] = $sub_institute_id;
       
        $holidays = DB::table("calendar_events")
            ->selectRaw("DATE_FORMAT(school_date,'%d') AS DATE")
            ->where($whereAtt)
            ->where('event_type', '=', 'holiday')
            ->whereRaw("month(school_date) = " . $month)
            ->pluck('DATE')
            ->toArray();
            
        $events = DB::table("calendar_events")
            ->selectRaw("DATE_FORMAT(school_date,'%d') AS DATE, event_type")
            ->where($whereAtt)
            ->where('event_type', '=', 'event')
            ->whereRaw("month(school_date) = " . $month)
            ->get();
        
        $eventsArray = [];
        foreach ($events as $event) {
            $eventsArray[] = $event->DATE; // Add event date to the array without event type
        }
   
        if(isset($standard_id)){
            $whereAtt['standard_id'] = $standard_id;
        }
        if(isset($division_id)){
            $whereAtt['section_id'] = $division_id;    
        }

        if(isset($enroll) && isset($student_data[0])){
            $whereAtt['student_id'] = $student_data[0]['id'];
        }
        $attendanceData = DB::table("attendance_student")
            ->where($whereAtt)
            ->whereBetween("attendance_date", [$from_date, $to_date])
            ->get()
            ->toArray();

        if (count($attendanceData) == 0) {
            $res['status_code'] = 0;
            $res['message'] = "No attendance taken in this month";
            return is_mobile($type, "monthwise_student_attendance_report", $res);
        }

        $finalAttendanceArray = array();
        foreach ($attendanceData as $key => $value) {
            $finalAttendanceArray[$value->student_id][(int)date('d', strtotime($value->attendance_date))] = $value->attendance_code;
        }

        foreach ($sundays['S'] as $key => $value) {
            $sundays[$key] = (int)date('d', strtotime($value));
        }

        unset($sundays['S']);
        // echo "<pre>";print_r($student_data);exit;
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['attendance_data'] = $finalAttendanceArray;
        $res['sundays'] = $sundays;
        $res['holidays'] = $holidays;
        $res['events'] = $eventsArray;
        $res['to_date'] = date('d', strtotime($to_date));

        return is_mobile($type, "student/monthwise_attendance_report", $res, "view");
    }

    public function studentAttendanceAPI(Request $request)
    {

        // return "hello";exit;
        try {
            if (!$this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        
        $attendace_data = $event_data = $holiday_data = $vacation_data = [];
        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $attendance_data = DB::table('attendance_student')->select('attendance_date', 'attendance_code')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('student_id', $student_id)
                ->where('syear', $syear)
                ->whereNotNull('attendance_date')
                ->orderBy('attendance_date')
                ->get()->toArray();

            $holiday_data = DB::table('tblstudent_enrollment as s')
                ->leftJoin('calendar_events as c', function ($join) {
                    $join->whereRaw('find_in_set(s.standard_id,c.standard) AND s.syear=c.syear');
                })
                ->selectRaw('c.school_date,c.title,c.description,c.event_type')
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('s.student_id', $student_id)
                ->where('event_type', '=', 'holiday')
                ->where('c.syear', $syear)->orderBy('school_date')->get()->toArray();

            $event_data = DB::table('tblstudent_enrollment as s')
                ->leftJoin('calendar_events as c', function ($join) {
                    $join->whereRaw('find_in_set(s.standard_id,c.standard) AND s.syear=c.syear');
                })
                ->selectRaw('c.school_date,c.title,c.description,c.event_type')
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('s.student_id', $student_id)
                ->where('event_type', '=', 'event')
                ->where('c.syear', $syear)->orderBy('school_date')->get()->toArray();

            $vacation_data = DB::table('tblstudent_enrollment as s')
                ->leftJoin('calendar_events as c', function ($join) {
                    $join->whereRaw('find_in_set(s.standard_id,c.standard) AND s.syear=c.syear');
                })
                ->selectRaw('c.school_date,c.title,c.description,c.event_type')
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('s.student_id', $student_id)
                ->where('event_type', '=', 'vacation')
                ->where('c.syear', $syear)->orderBy('school_date')->get()->toArray();

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data']['attendance_data'] = $attendance_data;
            $res['data']['calendar_data']['holiday'] = $holiday_data;
            $res['data']['calendar_data']['event'] = $event_data;
            $res['data']['calendar_data']['vacation'] = $vacation_data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function studentTeacherListAPI(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");

        if ($student_id != "" && $sub_institute_id != "" && $syear != "") {
            $stud_data = DB::table('tblstudent_enrollment')
                ->where('student_id', $student_id)
                ->where('syear', $syear)
                ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

            if (count($stud_data) > 0) {
                $standard_id = $stud_data[0]->standard_id;
                $section_id = $stud_data[0]->section_id;

                //DB::enableQueryLog();
                $data = DB::table('timetable as t')->select(
                    DB::raw("CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS teacher_name"),
                    DB::raw("IF(u.image = '', 'https://" . $_SERVER['SERVER_NAME'] . "/storage/student/noimages.png', CONCAT('https://" . $_SERVER['SERVER_NAME'] . "/storage/user/', u.image)) AS image"),
                    'u.mobile',
                    DB::raw("GROUP_CONCAT(DISTINCT s.display_name) AS subject_name")
                )->join('tbluser as u',function($join){
                    $join->on('u.id', '=', 't.teacher_id')->where('u.status',1);  // 23-04-24 by uma
                })
                ->join('sub_std_map as s', 's.subject_id', '=', 't.subject_id')
                ->where('t.syear', '=', $syear)
                ->where('t.sub_institute_id', '=', $sub_institute_id)
                ->where('t.standard_id', '=', $standard_id)
                ->where('t.division_id', '=', $section_id)
                ->groupBy('t.teacher_id')
                ->orderBy('teacher_name')
                ->get()->toArray();
                //dd(DB::getQueryLog($data));die();

                $res['status'] = 1;
                $res['message'] = "Success";
                $res['data'] = $data;
            } else {
                $res['status'] = 0;
                $res['message'] = "Wrong Parameters";
            }
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function studentAbsentListAPI(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
            return response()->json($response, 401);
        }

        $type = $request->input("type");
        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $standard_division = $request->input("standard_division");
        $start_date = $request->input("start_date");
        $end_date = $request->input("end_date");
        $syear = $request->input("syear");

        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "" && $standard_division != "" && $start_date != "" && $end_date != "") {
            $standard_division_orignal = $request->input('standard_division');
            $standard_division = explode("||", $standard_division_orignal);
            $standard = $standard_division[0];
            $division = $standard_division[1];

            $data = DB::table('attendance_student as a')
                ->join('tblstudent as s', function ($join) {
                    $join->whereRaw('s.id = a.student_id and s.sub_institute_id = a.sub_institute_id');
                })->selectRaw("attendance_code,attendance_date,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name")
                ->where('standard_id', $standard)
                ->where('section_id', $division)
                ->where('attendance_code', '=', 'A')
                ->where('teacher_id', $teacher_id)
                ->where('a.sub_institute_id', $sub_institute_id)
                ->whereBetween('attendance_date', [$start_date, $end_date])->get()->toArray();

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function sendNotificationAtt(Request $request){
        $sub_institute_id = $request->sub_institute_id;
        $syear = $request->syear;

        $getStudent = DB::table('tblstudent')->select('*',DB::raw('CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(middle_name,"-"),COALESCE(last_name,"-")) as student_name'))->where('id',$request->student_id)->where('sub_institute_id',$sub_institute_id)->first();
        // echo "<pre>";print_r($getStudent);exit;
        $getCreatedBy = DB::table('tbluser')->select('*',DB::raw('CONCAT_WS(" ",COALESCE(first_name,"-"),COALESCE(middle_name,"-"),COALESCE(last_name,"-")) as full_name'))->where('id',$request->created_by)->where('sub_institute_id',$sub_institute_id)->first();

        $text = '';
        if($request->attendance == "P"){
            $text .= $getStudent->student_name." is present on ".$request->date.', Attendance Taken by '.$getCreatedBy->full_name;
        }elseif($request->attendance == "A"){
            $text .= $getStudent->student_name." is absent on ".$request->date.', Attendance Taken by '.$getCreatedBy->full_name;
        }

           $app_notification_content = [
                'NOTIFICATION_TYPE'        => 'TakeAttendance',
                'NOTIFICATION_DATE'        => now(),
                'STUDENT_ID'               => $request->student_id,
                'NOTIFICATION_DESCRIPTION' => $text,
                'STATUS'                   => 0,
                'SUB_INSTITUTE_ID'         => $sub_institute_id,
                'SYEAR'                    => $syear,
                'SCREEN_NAME'              => 'student_attendance',
                'CREATED_BY'               => $request->created_by,
                'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
            ];

            $gcm_data = DB::table('gcm_users')->where('mobile_no', $getStudent->mobile)
                ->where('sub_institute_id', $sub_institute_id)->get()->toArray();

            $gcmRegIds = [];
            if (count($gcm_data) > 0) {
                foreach ($gcm_data as $key1 => $val1) {
                    $gcmRegIds[] = $val1->gcm_regid;
                }
            }

            $pushMessage = $text;

            $bunch_arr = array_chunk($gcmRegIds, 1000);

            $res = 0;
            $schoolData = SchoolModel::where(['id' => $sub_institute_id])->get()->toArray();

            $schoolName = $schoolData[0]['SchoolName'];
            $schoolLogo = $_SERVER['APP_URL'].'/admin_dep/images/'.$schoolData[0]['Logo'];

            if (!empty($bunch_arr)) {
                foreach ($bunch_arr as $val) {
                    if (isset($val, $pushMessage)) {
                        $type1 = 'Notification';
                        $message = [
                            'body'  => $pushMessage, 'TYPE' => $type1, 'USER_ID' => $request->student_id,
                            'title' => $schoolName, 'image' => $schoolLogo,
                        ];

                        if($request->date == date('Y-m-d')){
                            $pushStatus = send_FCM_Notification($val, $message, $sub_institute_id);
                        }

                        if($request->attendance == "A"){
                            sendNotification($app_notification_content);
                        }                        
                    }
                }
                
              $res = 1;
            }
        return $res;
    }

    public function studentAttendanceChatAPI(Request $request){
        try {
            if (!$this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
            return response()->json($response, 401);
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'enrollment_no'    => 'required',
        ]);

        if ($validator->fails()) {
            $error = $validator->messages();
            $res['status']=0;
            $res['message'] = $error->first();
        } else {
            // get student Data 
            $student_data = SearchStudent("", "", "","","","","","", "", $request->enrollment_no);

            // get monthly attendance
            $montRequest = new Request([
                "type"=>$request->type,
                "token"=>$request->token,
                "sub_institute_id"=>$request->sub_institute_id,
                "year"=>$request->syear,
                "syear"=>$request->syear,
                "month"=> date('m'),
                'enrollment_no'=>$request->enrollment_no,
            ]);
            $monthly = json_decode($this->showMonthwiseStudentAttendance($montRequest),true);
            $workingDay = $presentDays = $absentDays = 0;
            if(isset($monthly['attendance_data']) && !empty($monthly['attendance_data'])){
                $w = $p = $a =0;
                foreach ($monthly['attendance_data'] as $key => $value) {
                    foreach ($value as $key2 => $value2) {
                        $workingDay+=($w+1);
                        if($value2=="P"){
                            $presentDays +=($p+1);
                        }else{
                            $absentDays += ($a+1);
                        }
                    }
                }
            }
            $monthAtt = [
                'workingDays'=>$workingDay,
                'presentDays'=>$presentDays,
                'absentDays'=>$absentDays,
            ];
            
            // get yearly attendance
            $academicData = DB::table('academic_year')->where(['sub_institute_id'=>$request->sub_institute_id,'syear'=>$request->syear])->get()->toArray();
            $from_date = isset($academicData[0]->post_start_date) ? $academicData[0]->post_start_date : date('Y-m-d');
            $to_date = date('Y-m-d');
            if(isset($academicData[0]->post_start_date)){
                foreach ($academicData as $key => $value) {
                    $to_date = $value->post_end_date;
                }
            }
            $yearlyRequest = new Request([
                "type"=>$request->type,
                "token"=>$request->token,
                "sub_institute_id"=>$request->sub_institute_id,
                "year"=>$request->syear,
                "syear"=>$request->syear,
                "from_date"=>$from_date,
                "to_date"=>$to_date,
                'enrollment_no'=>$request->enrollment_no,
            ]);

            $yearlyController  = new yearly_attendance_controller;
            $yearly = json_decode($yearlyController->showYearlyStudentAttendance($yearlyRequest),true);

            $yworkingDay = $ypresentDays = $yabsentDays = 0;
            if(isset($yearly['attendance_data']) && !empty($yearly['attendance_data'])){
                $w = $p = $a =0;
                foreach ($yearly['attendance_data'] as $key => $value) {
                    foreach ($value as $key2 => $value2) {
                        $ypresentDays +=$value2;
                    }
                }
                foreach ($yearly['working_day'] as $key => $value) {
                    $yworkingDay+=$value;
                }
            }
            $yearlyAtt = [
                'workingDays'=>$yworkingDay,
                'presentDays'=>$ypresentDays,
                'absentDays'=>($yworkingDay - $ypresentDays),
            ];
            // return $yearlyAtt;exit;
            $res['status']=1;
            $res['message'] = "Success";
            $res['monthly'] = $monthAtt;
            $res['yearly'] = $yearlyAtt;
            $res['student_data'] = $student_data;
        }

        return $res;
    }
}
