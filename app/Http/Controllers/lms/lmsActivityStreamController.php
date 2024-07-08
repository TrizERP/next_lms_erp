<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\student\tblstudentEnrollmentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\getStudents;
use function App\Helpers\SearchStudent;

class lmsActivityStreamController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
   /* public function index(Request $request)
    {
        $type = $request->input('type');
       
        $data = $this->getData($request);
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['activitystream_today_data'] = $data['activitystream_today_data'];
        $res['activitystream_upcoming_data'] = $data['activitystream_upcoming_data'];

        // return is_mobile($type, 'lms/show_lmsActivityStream', $res, "view");
    } */

    // new index 
    public function index(Request $request)
    {
        $type = $request->input('type');
      
        $request=$request->merge([
            'sub_institute_id' => session()->get('sub_institute_id'),
            'syear' => session()->get('syear'),
            'user_id' => session()->get('user_id'),
            'user_profile' => session()->get('user_profile_name'),
            'user_profile_id'=> session()->get('user_profile_id'),
            'term_id'=> session()->get('term_id'),
        ]);
        // echo "<pre>";print_r(session()->all());exit;
        $res['todaytitle'] = date('l, M d, Y');
        $res['upcoming'] = $this->upcomingActivity($request);
        $res['today'] = $this->todayActivity($request);
        $res['recent'] = $this->recentActivity($request);

        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, 'lms/newActivityStream', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data['activitystream_upcoming_data'] = $data['activitystream_today_data'] = array();

        if (strtoupper(session()->get('user_profile_name')) == "STUDENT") {
            $student_id = session()->get('user_id');
            $stu_data = tblstudentEnrollmentModel::select('standard_id')->where([
                'student_id' => $student_id, "syear" => $syear,
            ])->get()->toArray();

            //START Today's Event Query
           /* $data['activitystream_today_data'] = DB::select("
                SELECT * FROM (
                SELECT 'Virtual Classroom' AS action,v.id,v.standard_id,room_name AS title,description,
                url,event_date,v.subject_id,s.display_name AS subject_name
                FROM lms_virtual_classroom v
                INNER JOIN sub_std_map s ON s.subject_id = v.subject_id and s.standard_id = v.standard_id
                WHERE event_date = CURRENT_DATE() AND v.sub_institute_id = '".$sub_institute_id."' AND v.standard_id = '".$stu_data[0]['standard_id']."'

                UNION

                SELECT 'Homework' as action,h.id,h.standard_id,title,description,'',submission_date AS event_date,h.subject_id,s.display_name AS subject_name
                FROM homework h
                INNER JOIN sub_std_map s ON s.subject_id = h.subject_id and s.standard_id = h.standard_id
                WHERE h.sub_institute_id = '".$sub_institute_id."' AND h.standard_id = '".$stu_data[0]['standard_id']."'  AND submission_date = CURRENT_DATE()
                ) AS a
                ORDER BY event_date
                ");*/

            $data['activitystream_today_data'] = DB::table('lms_virtual_classroom as v')
                ->select(
                    DB::raw("'Virtual Classroom' AS action"),
                    'v.id',
                    'v.standard_id',
                    'room_name AS title',
                    'description',
                    'url',
                    'event_date',
                    'v.subject_id',
                    DB::raw('s.display_name AS subject_name')
                )
                ->join('sub_std_map as s', function ($join) {
                    $join->on('s.subject_id', '=', 'v.subject_id')
                        ->on('s.standard_id', '=', 'v.standard_id');
                })
                ->whereDate('event_date', now())
                ->where('v.sub_institute_id', $sub_institute_id)
                ->where('v.standard_id', $stu_data[0]['standard_id'])
                ->unionAll(
                    DB::table('homework as h')
                        ->select(
                            DB::raw("'Homework' AS action"),
                            'h.id',
                            'h.standard_id',
                            'title',
                            'description',
                            DB::raw("'' AS url"),
                            'submission_date AS event_date',
                            'h.subject_id',
                            DB::raw('s.display_name AS subject_name')
                        )
                        ->join('sub_std_map as s', function ($join) {
                            $join->on('s.subject_id', '=', 'h.subject_id')
                                ->on('s.standard_id', '=', 'h.standard_id');
                        })
                        ->where('h.sub_institute_id', $sub_institute_id)
                        ->where('h.standard_id', $stu_data[0]['standard_id'])
                        ->whereDate('submission_date', now())
                )
                ->orderBy('event_date')
                ->get();

            //END Today's Event Query

            //START Upcoming Event Query
            $data['activitystream_upcoming_data'] = DB::select("
                SELECT * FROM (
                SELECT 'Virtual Classroom' AS action,v.id,v.standard_id,room_name AS title,description,
                url,event_date,v.subject_id,s.display_name AS subject_name
                FROM lms_virtual_classroom v
                INNER JOIN sub_std_map s ON s.subject_id = v.subject_id and s.standard_id = v.standard_id
                WHERE event_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) AND v.sub_institute_id = '".$sub_institute_id."' AND v.standard_id = '".$stu_data[0]['standard_id']."'

                UNION

                SELECT 'Homework' as action,h.id,h.standard_id,title,description,'',submission_date AS event_date,h.subject_id,s.display_name as subject_name
                FROM homework h
                INNER JOIN sub_std_map s ON s.subject_id = h.subject_id and s.standard_id = h.standard_id
                WHERE h.sub_institute_id = '".$sub_institute_id."' AND h.standard_id = '".$stu_data[0]['standard_id']."'
                AND submission_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
                ) AS a
                ORDER BY event_date
                ");

            //END Upcoming Event Query
        }

        return $data;
    }

    public function upcomingActivity(Request $request)
    {
        $profileName = $request->user_profile;
        $profileId = $request->user_profile_id;
        $sub_institute_id = $request->sub_institute_id;
        $syear = $request->syear;
        $user_id = $request->user_id;
        $term_id = $request->term_id;
        
        $searchDate = date('Y-m-d', strtotime('+1 day'));
        $dayOfWeek =  date('l', strtotime('+1 day'));
        $firstLetter = substr($dayOfWeek, 0, 1);
        if($dayOfWeek=="Thursday"){
            $firstLetter = "H";
        }

        $standard_id = $division_id = $classStdId = $classDivId = '';
        if($profileName=="Student"){
            $studentData = $this->studentData($user_id,$sub_institute_id,$syear);
            $classStdId = $standard_id = $studentData['standard_id'];
            $classDivId = $division_id = $studentData['section_id'];
        }else if ($profileName=="Teacher"){
            $getTeacherData = DB::table('timetable as h')->where('h.teacher_id',$user_id)
            ->selectRaw('GROUP_CONCAT(DISTINCT h.standard_id) AS standard_id,GROUP_CONCAT(DISTINCT h.division_id) AS division_id')->where(['h.sub_institute_id'=>$sub_institute_id,'h.syear'=>$syear])->groupBy('h.teacher_id')->first();
            $standard_id = $getTeacherData->standard_id;
            $division_id = $getTeacherData->division_id;
            $classTeacher = DB::table('class_teacher')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->first();
            $classStdId = ($classTeacher->standard_id) ? : '';
            $classDivId = ($classTeacher->division_id) ? : '';
        }

        // class schedule data from timetable
        $classSchedule = DB::table('timetable as tt')
        ->join('period as p',function($join) use($sub_institute_id,$syear,$dayOfWeek){
            $join->on('p.id','=','tt.period_id')->where(['p.sub_institute_id'=>$sub_institute_id,'p.used_for_attendance'=>'Yes']);
        })
        ->selectRaw("tt.*,p.*,(SELECT name FROM standard where id = tt.standard_id) as standard,(SELECT name FROM division WHERE id = tt.division_id) as division")
        ->where(['tt.sub_institute_id'=>$sub_institute_id,'tt.syear'=>$syear])
        ->where('tt.week_day',$firstLetter)
        // when profile is student
        ->when($profileName=='Student',function($query) use($standard_id,$division_id){
            $query->where('tt.standard_id',$standard_id)->where('tt.division_id',$division_id);
        }) 
       // for teacher
        ->when($profileName=='Teacher',function($query) use($user_id){
            $query->where('tt.teacher_id',$user_id);
        })
        ->orderBy('p.sort_order')
        ->get()->toArray();
      
        // get Tomorrow homework
        $homework = $this->getHomeWork($profileName,$user_id,$sub_institute_id,$syear,$standard_id,$division_id,$searchDate,'upcoming');

        // get Event Calender
        $eventCalender = $this->getEventCalender($sub_institute_id,$syear,$standard_id,$searchDate,'upcoming');

        $announcementNotice = $this->getAnnouncementNotice($sub_institute_id,$syear,$searchDate,$profileName,$profileId,'upcoming');
            
        $dueBooks = $this->getDueBooks($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$standard_id,$division_id,'upcoming');

        $studentProgress = $this->getStudentProgress($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$classStdId,$classDivId,'upcoming');

        $ptm = $this->getPTM($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$classStdId,$classDivId,'upcoming');

        $lessonPlan = $this->getLessonPlan($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$classStdId,$classDivId,'upcoming');

        // Not for students
        $hrmsPunchInOut = $proxyLecture = $examMarks = $studentAttendance = $taskAssigned = $parentCommunication = $studentLeave = [];
        if($profileName!="Student"){
            $hrmsPunchInOut = $this->getHrmsPunchInOut($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$profileId,'upcoming');
            $proxyLecture = $this->getProxyLecture($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$profileId,$dayOfWeek,'upcoming');
            $examMarks = $this->getExamMarks($sub_institute_id,$syear,$searchDate,$user_id,$term_id,$classStdId,'upcoming');
            $studentAttendance = $this->getStudentAttendance($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'upcoming');
            $taskAssigned = $this->getTaskAssigned($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'upcoming');
            $parentCommunication = $this->getParentCommunication($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'upcoming');
            $studentLeave = $this->getStudentLeave($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'upcoming');
        }
        $res['class_schedule'] = $classSchedule;
        $res['homework'] = $homework;
        $res['eventCalender'] = $eventCalender;
        $res['announcementNotice'] = $announcementNotice;
        $res['dueBooks'] = $dueBooks;
        $res['studentProgress'] = $studentProgress;
        $res['ptm'] = $ptm;
        $res['lessonPlan'] = $lessonPlan;
        $res['hrmsPunchInOut'] = $hrmsPunchInOut;
        $res['proxyLecture'] = $proxyLecture;
        $res['examMarks'] = $examMarks;
        $res['studentAttendance'] = $studentAttendance;
        $res['taskAssigned'] = $taskAssigned;
        $res['parentCommunication'] = $parentCommunication;
        $res['studentLeave'] = $studentLeave;
        
        return $res;
    }

  
    public function todayActivity(Request $request)
    {
        $profileName = $request->user_profile;
        $profileId = $request->user_profile_id;
        $sub_institute_id = $request->sub_institute_id;
        $syear = $request->syear;
        $user_id = $request->user_id;
        $term_id = $request->term_id;
        
        $searchDate = date('Y-m-d');
        $dayOfWeek =  date('l');
        $firstLetter = substr($dayOfWeek, 0, 1);
        if($dayOfWeek=="Thursday"){
            $firstLetter = "H";
        }

        $standard_id = $division_id = $classStdId = $classDivId = '';
        if($profileName=="Student"){
            $studentData = $this->studentData($user_id,$sub_institute_id,$syear);
            $classStdId = $standard_id = $studentData['standard_id'];
            $classDivId = $division_id = $studentData['section_id'];
        }else if ($profileName=="Teacher"){
            $getTeacherData = DB::table('timetable as h')->where('h.teacher_id',$user_id)
            ->selectRaw('GROUP_CONCAT(DISTINCT h.standard_id) AS standard_id,GROUP_CONCAT(DISTINCT h.division_id) AS division_id')->where(['h.sub_institute_id'=>$sub_institute_id,'h.syear'=>$syear])->groupBy('h.teacher_id')->first();
            $standard_id = $getTeacherData->standard_id;
            $division_id = $getTeacherData->division_id;
            $classTeacher = DB::table('class_teacher')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->first();
            $classStdId = ($classTeacher->standard_id) ? : '';
            $classDivId = ($classTeacher->division_id) ? : '';
        }

        // class schedule data from timetable
        $classSchedule = DB::table('timetable as tt')
        ->join('period as p',function($join) use($sub_institute_id,$syear,$dayOfWeek){
            $join->on('p.id','=','tt.period_id')->where(['p.sub_institute_id'=>$sub_institute_id,'p.used_for_attendance'=>'Yes']);
        })
        ->leftJoin('attendance_student as att',function($join) use($standard_id,$division_id,$sub_institute_id,$syear){
            $join->when($standard_id,function($q) use($standard_id){
                $q->whereRaw('att.standard_id in ('.$standard_id.')');
            })
            ->when($division_id,function($q) use($division_id){
                $q->whereRaw('att.section_id in ('.$division_id.')');
            })->where(['att.sub_institute_id'=>$sub_institute_id,'att.syear'=>$syear]);
        })
        ->selectRaw("tt.*,p.*,(SELECT name FROM standard where id = tt.standard_id) as standard,(SELECT name FROM division WHERE id = tt.division_id) as division,count(att.id) as att,att.attendance_date")
        ->where(['tt.sub_institute_id'=>$sub_institute_id,'tt.syear'=>$syear])
        ->where('tt.week_day',$firstLetter)
        // when profile is student
        ->when($profileName=='Student',function($query) use($standard_id,$division_id){
            $query->where('tt.standard_id',$standard_id)->where('tt.division_id',$division_id);
        }) 
       // for teacher
        ->when($profileName=='Teacher',function($query) use($user_id){
            $query->where('tt.teacher_id',$user_id);
        })
        ->where(['tt.sub_institute_id'=>$sub_institute_id,'tt.syear'=>$syear])
        ->orderBy('p.sort_order')
        ->groupBy('tt.id')
        ->get()->toArray();
      
        // get Tomorrow homework
        $homework = $this->getHomeWork($profileName,$user_id,$sub_institute_id,$syear,$standard_id,$division_id,$searchDate,'today');

        // get Event Calender
        $eventCalender = $this->getEventCalender($sub_institute_id,$syear,$standard_id,$searchDate,'today');

        $announcementNotice = $this->getAnnouncementNotice($sub_institute_id,$syear,$searchDate,$profileName,$profileId,'today');
            
        $dueBooks = $this->getDueBooks($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$standard_id,$division_id,'today');

        $studentProgress = $this->getStudentProgress($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$classStdId,$classDivId,'today');
        
        $ptm = $this->getPTM($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$classStdId,$classDivId,'today');

        $lessonPlan = $this->getLessonPlan($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$classStdId,$classDivId,'today');

        // Not for students
        $hrmsPunchInOut = $proxyLecture = $examMarks = $studentAttendance = $taskAssigned = $parentCommunication = $studentLeave = [];
        if($profileName!="Student"){
            $hrmsPunchInOut = $this->getHrmsPunchInOut($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$profileId,'today');
            $proxyLecture = $this->getProxyLecture($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$profileId,$dayOfWeek,'today');
            $examMarks = $this->getExamMarks($sub_institute_id,$syear,$searchDate,$user_id,$term_id,$classStdId,'today');
            $studentAttendance = $this->getStudentAttendance($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'today');
            $taskAssigned = $this->getTaskAssigned($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'today');
            $parentCommunication = $this->getParentCommunication($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'today');
            $studentLeave = $this->getStudentLeave($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'today');
        }
        $res['class_schedule'] = $classSchedule;
        $res['homework'] = $homework;
        $res['eventCalender'] = $eventCalender;
        $res['announcementNotice'] = $announcementNotice;
        $res['dueBooks'] = $dueBooks;
        $res['studentProgress'] = $studentProgress;
        $res['ptm'] = $ptm;
        $res['lessonPlan'] = $lessonPlan;
        $res['hrmsPunchInOut'] = $hrmsPunchInOut;
        $res['proxyLecture'] = $proxyLecture;
        $res['examMarks'] = $examMarks;
        $res['studentAttendance'] = $studentAttendance;
        $res['taskAssigned'] = $taskAssigned;
        $res['parentCommunication'] = $parentCommunication;
        $res['studentLeave'] = $studentLeave;
        return $res;
    }

    public function recentActivity(Request $request)
    {
        $profileName = $request->user_profile;
        $profileId = $request->user_profile_id;
        $sub_institute_id = $request->sub_institute_id;
        $syear = $request->syear;
        $user_id = $request->user_id;
        $term_id = $request->term_id;
        
        $searchDate = date('Y-m-d');
        $dayOfWeek =  date('l');
        $firstLetter = substr($dayOfWeek, 0, 1);
        if($dayOfWeek=="Thursday"){
            $firstLetter = "H";
        }

        $standard_id = $division_id = $classStdId = $classDivId = '';
        if($profileName=="Student"){
            $studentData = $this->studentData($user_id,$sub_institute_id,$syear);
            $classStdId = $standard_id = $studentData['standard_id'];
            $classDivId = $division_id = $studentData['section_id'];
        }else if ($profileName=="Teacher"){
            $getTeacherData = DB::table('timetable as h')->where('h.teacher_id',$user_id)
            ->selectRaw('GROUP_CONCAT(DISTINCT h.standard_id) AS standard_id,GROUP_CONCAT(DISTINCT h.division_id) AS division_id')->where(['h.sub_institute_id'=>$sub_institute_id,'h.syear'=>$syear])->groupBy('h.teacher_id')->first();
            $standard_id = $getTeacherData->standard_id;
            $division_id = $getTeacherData->division_id;
            $classTeacher = DB::table('class_teacher')->where(['sub_institute_id'=>$sub_institute_id,'syear'=>$syear])->first();
            $classStdId = ($classTeacher->standard_id) ? : '';
            $classDivId = ($classTeacher->division_id) ? : '';
        }

        // class schedule data from timetable
        $classSchedule = DB::table('timetable as tt')
        ->join('period as p',function($join) use($sub_institute_id,$syear,$dayOfWeek){
            $join->on('p.id','=','tt.period_id')->where(['p.sub_institute_id'=>$sub_institute_id,'p.used_for_attendance'=>'Yes']);
        })
        ->leftJoin('attendance_student as att',function($join) use($standard_id,$division_id,$sub_institute_id,$syear){
            $join->when($standard_id,function($q) use($standard_id){
                $q->whereRaw('att.standard_id in ('.$standard_id.')');
            })
            ->when($division_id,function($q) use($division_id){
                $q->whereRaw('att.section_id in ('.$division_id.')');
            })->where(['att.sub_institute_id'=>$sub_institute_id,'att.syear'=>$syear]);
        })
        ->selectRaw("tt.*,p.*,(SELECT name FROM standard where id = tt.standard_id) as standard,(SELECT name FROM division WHERE id = tt.division_id) as division,count(att.id) as att,att.attendance_date")
        ->where(['tt.sub_institute_id'=>$sub_institute_id,'tt.syear'=>$syear])
        ->where('tt.week_day',$firstLetter)
        // when profile is student
        ->when($profileName=='Student',function($query) use($standard_id,$division_id){
            $query->where('tt.standard_id',$standard_id)->where('tt.division_id',$division_id);
        }) 
       // for teacher
        ->when($profileName=='Teacher',function($query) use($user_id){
            $query->where('tt.teacher_id',$user_id);
        })
        ->where(['att.sub_institute_id'=>$sub_institute_id,'att.syear'=>$syear])
        ->groupBy('tt.id')
        ->orderBy('p.sort_order')
        ->get()->toArray();
      
        // get Tomorrow homework
        $homework = $this->getHomeWork($profileName,$user_id,$sub_institute_id,$syear,$standard_id,$division_id,$searchDate,'recent');

        // get Event Calender
        $eventCalender = $this->getEventCalender($sub_institute_id,$syear,$standard_id,$searchDate,'recent');

        $announcementNotice = $this->getAnnouncementNotice($sub_institute_id,$syear,$searchDate,$profileName,$profileId,'recent');
            
        $dueBooks = $this->getDueBooks($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$standard_id,$division_id,'recent');

        $studentProgress = $this->getStudentProgress($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$classStdId,$classDivId,'recent');

        $ptm = $this->getPTM($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$classStdId,$classDivId,'recent');

        $lessonPlan = $this->getLessonPlan($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$classStdId,$classDivId,'recent');

        // Not for students
        $hrmsPunchInOut = $proxyLecture = $examMarks = $studentAttendance = $taskAssigned = $parentCommunication = $studentLeave = [];
        if($profileName!="Student"){
            $hrmsPunchInOut = $this->getHrmsPunchInOut($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$profileId,'recent');
            $proxyLecture = $this->getProxyLecture($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$profileId,$dayOfWeek,'recent');
            $examMarks = $this->getExamMarks($sub_institute_id,$syear,$searchDate,$user_id,$term_id,$classStdId,'recent');
            $studentAttendance = $this->getStudentAttendance($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'recent');
            $taskAssigned = $this->getTaskAssigned($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'recent');
            $parentCommunication = $this->getParentCommunication($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'recent');
            $studentLeave = $this->getStudentLeave($sub_institute_id,$syear,$searchDate,$user_id,$classDivId,$classStdId,'recent');
        }
        $res['class_schedule'] = $classSchedule;
        $res['homework'] = $homework;
        $res['eventCalender'] = $eventCalender;
        $res['announcementNotice'] = $announcementNotice;
        $res['dueBooks'] = $dueBooks;
        $res['studentProgress'] = $studentProgress;
        $res['ptm'] = $ptm;
        $res['lessonPlan'] = $lessonPlan;
        $res['hrmsPunchInOut'] = $hrmsPunchInOut;
        $res['proxyLecture'] = $proxyLecture;
        $res['examMarks'] = $examMarks;
        $res['studentAttendance'] = $studentAttendance;
        $res['taskAssigned'] = $taskAssigned;
        $res['parentCommunication'] = $parentCommunication;
        $res['studentLeave'] = $studentLeave;
        return $res;    
    }
    // get student data
    public function studentData($user_id,$sub_institute_id,$syear){
        $studentID[0] = $user_id;
        $getStudentData = getStudents($studentID,$sub_institute_id,$syear);
        if(isset($getStudentData[$user_id])){
            $data = $getStudentData[$user_id];
        }else{
            $data = '';
        }
        return $data;
    }

    function getHomeWork($profileName,$user_id,$sub_institute_id,$syear,$standard_id,$division_id,$searchDate,$activityType=''){
        return DB::table('homework as h')
        ->join('sub_std_map as ssm','h.subject_id','=','ssm.subject_id')
        ->selectRaw("h.id,h.title,h.`description`,h.student_id,h.created_by,h.image as attachment,h.date,h.submission_date,h.subject_id,h.completion_status,h.created_on,ssm.display_name,(SELECT name FROM standard where id = h.standard_id) as standard,(SELECT name FROM division WHERE id = h.division_id) as division")
        // when profile is student
        ->when($profileName=='Teacher',function($query) use($standard_id,$division_id){
            $query->whereRaw("h.standard_id IN (".$standard_id.") AND h.division_id IN (".$division_id.")");
        }) 
        // for teacher
        ->when($profileName=='Student',function($query) use($user_id){
            $query->where('h.student_id',$user_id);
        })
        // for upcoming
        ->when($activityType=='upcoming',function($q) use($searchDate){
            $q->where('h.submission_date','>=',$searchDate);
        })
        // for today
        ->when($activityType=='today',function($q) use($searchDate){
            $q->where('h.submission_date',$searchDate);
        }) 
         // for recent
         ->when($activityType=='recent',function($q) use($searchDate){
            $q->where('h.submission_date','<',$searchDate);
        }) 
        ->where(['h.sub_institute_id'=>$sub_institute_id,'h.syear'=>$syear])
        // when profile is student
        ->when($profileName=='Teacher',function($query) use($standard_id){
            $query->groupByRaw('h.standard_id,h.division_id,h.subject_id');
        }) 
        // for teacher
        ->when($profileName=='Student',function($query) use($user_id){
            $query->limit(10)->groupBy('h.id');
        })
        ->get()->toArray();
    }

    function getEventCalender($sub_institute_id,$syear,$standard_id,$searchDate,$activityType=""){
       return DB::table('calendar_events as ce')
            ->where('ce.sub_institute_id',$sub_institute_id)
            ->where('ce.syear',$syear)
            ->when($standard_id,function($q) use($standard_id){
                // $q->whereRaw('FIND_IN_SET("'.$standard_id.'",ce.standard)');
                $standard_ids = explode(',',$standard_id);
                foreach ($standard_ids as $std) {
                    $q->orWhereRaw('FIND_IN_SET(?, ce.standard)', [$std]);
                }
            })
            // for upcoming
            ->when($activityType=='upcoming',function($q) use($searchDate){
                $q->where('ce.school_date','>=',$searchDate);
            })
              // for today
            ->when($activityType=='today',function($q) use($searchDate){
                $q->where('ce.school_date',$searchDate);
            }) 
            // for recent
            ->when($activityType=='recent',function($q) use($searchDate){
                $q->where('ce.school_date','<',$searchDate);
            }) 
            ->orderBy('ce.school_date')
            ->limit(10)
            ->get()->toArray();
    }

    function getAnnouncementNotice($sub_institute_id,$syear,$searchDate,$profileName,$profileId,$activityType=""){
        return DB::table('announcement as a')
                ->where('a.sub_institute_id',$sub_institute_id)
                ->where('a.syear',$syear)
                ->when($activityType=='upcoming',function($q) use($searchDate){
                    $q->where('a.from_date','>=',$searchDate);
                })
                  // for today
                ->when($activityType=='today',function($q) use($searchDate){
                    $q->where('a.from_date',$searchDate);
                }) 
                // for recent
                ->when($activityType=='recent',function($q) use($searchDate){
                    $q->where('a.from_date','<',$searchDate);
                }) 
                ->when($profileName!="Admin",function($q) use($profileId){
                    $q->where('a.user_profile_id',$profileId);
                })
                ->orderBy('a.from_date')
                ->limit(10)
                ->get()->toArray();
    }
     
    function getDueBooks($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$standard_id,$division_id,$activityType=""){
        return DB::table('library_book_circulations as lbc')
                ->Leftjoin('library_items as li','li.item_code','=','lbc.item_code')
                ->join('library_books as lb','lb.id','=','lbc.book_id')
                ->join('tblstudent_enrollment as se','se.student_id','=','lbc.student_id')
                ->selectRaw("lbc.*,lb.id,lb.title as book_name,se.standard_id,se.section_id")
                ->when($activityType=='upcoming',function($q) use($searchDate){
                    $q->where('lbc.due_date','>=',$searchDate);
                })
                  // for today
                  ->when($activityType=='today',function($q) use($searchDate){
                    $q->where('lbc.due_date',$searchDate);
                }) 
                // for recent
                ->when($activityType=='recent',function($q) use($searchDate){
                    $q->where('lbc.due_date','<',$searchDate);
                }) 
                ->where('lbc.sub_institute_id',$sub_institute_id)
                // when profile is student
                ->when($profileName=='Teacher',function($query) use($standard_id,$division_id){
                    $query->whereRaw("se.standard_id IN (".$standard_id.") AND se.section_id IN (".$division_id.")");
                }) 
                // for teacher
                ->when($profileName=='Student',function($query) use($user_id){
                    $query->where('lbc.student_id',$user_id);
                })
                ->groupBy('lbc.id')
                ->orderBy('lbc.due_date')
                ->limit(10)
                ->get()->toArray();
    }
    // student progress report
    function getStudentProgress($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$standard_id,$division_id,$activityType=''){
       return DB::table('tblstudent as s')
                            ->join('tblstudent_enrollment as se','se.student_id','=','s.id')
                            ->join('standard as std','std.id','=','se.standard_id')
                            ->join('division as d','se.section_id','=','d.id')
                            ->selectRaw('s.id,se.standard_id,std.name,se.section_id as division_id,d.name as division')
                            ->where(['s.sub_institute_id'=>$sub_institute_id,'se.syear'=>$syear])
                            // for admin / teacher
                            ->when($standard_id,function($q) use($standard_id){
                                $q->whereRaw('se.standard_id in ('.$standard_id.')');
                            })
                            ->when($division_id,function($q) use($division_id){
                                $q->whereRaw('se.section_id in ('.$division_id.')');
                            })
                            // for student
                            ->when($profileName=="Student",function($q) use($user_id){
                                $q->where('s.id',$user_id);
                            })
                            ->groupByRaw('se.standard_id,se.section_id')
                            ->get()->toArray();
    }
    // ptm 
    function getPTM($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$standard_id,$division_id,$activityType=""){
        return DB::table('ptm_time_slots_master as ptm')
                ->join('standard as std','std.id','=','ptm.standard_id')
                ->join('division as d','ptm.division_id','=','d.id')
                ->selectRaw('ptm.id,ptm.standard_id,std.name as standard,ptm.division_id,d.name as division,ptm.title as ptmTitle,ptm.from_time,ptm.to_time,ptm.ptm_date')
                ->where(['ptm.sub_institute_id'=>$sub_institute_id,'ptm.syear'=>$syear])
                // for admin / teacher and student
                ->when($standard_id,function($q) use($standard_id){
                    $q->whereRaw('ptm.standard_id in ('.$standard_id.')');
                })
                ->when($division_id,function($q) use($division_id){
                    $q->whereRaw('ptm.division_id in ('.$division_id.')');
                })
                ->when($activityType=='upcoming',function($q) use($searchDate){
                    $q->where('ptm.ptm_date','>=',$searchDate);
                })
                  // for today
                  ->when($activityType=='today',function($q) use($searchDate){
                    $q->where('ptm.ptm_date',$searchDate);
                }) 
                // for recent
                ->when($activityType=='recent',function($q) use($searchDate){
                    $q->where('ptm.ptm_date','<',$searchDate);
                }) 
                ->groupByRaw('ptm.standard_id,ptm.division_id')
                ->get()->toArray();
    }
    // lesson plan 
    function getLessonPlan($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$standard_id,$division_id,$activityType=""){
        return DB::table('lms_lesson_plan as llp')
                ->join('standard as std','std.id','=','llp.standard_id')
                ->join('chapter_master as cm','cm.id','=','llp.chapter_id')
                ->selectRaw('llp.*,std.name as standard,cm.chapter_name')
                // for admin / teacher and student
                ->when($standard_id,function($q) use($standard_id){
                    $q->whereRaw('llp.standard_id in ('.$standard_id.')');
                })
                ->where(['llp.sub_institute_id'=>$sub_institute_id,'llp.syear'=>$syear])
                ->groupByRaw('llp.standard_id')
                ->get()->toArray();
    }
    // hrms punch in and punch out 
    function getHrmsPunchInOut($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$profileId,$activityType=''){
        $getDate = strtotime($searchDate);
        $getDay = strtolower(date('l', $getDate));
        // return $getDay;
        if($activityType!="upcoming"){
            return DB::table('hrms_attendances as ha')
            ->join('tbluser as u','ha.user_id','=','u.id')
            ->selectRaw('ha.*,u.id,u.user_name,u.'.$getDay.'_in_date as punch_in,u.'.$getDay.'_out_date as punch_out')
            ->when($profileName=='Teacher',function($query) use($user_id){
                $query->where('ha.user_id',$user_id);
            }) 
            ->where('ha.sub_institute_id',$sub_institute_id)->where('ha.day',$searchDate)->get()->toArray();
        }else{
            return DB::table('tbluser as u')->selectRaw('u.id,u.user_name,u.'.$getDay.'_in_date as punch_in,u.'.$getDay.'_out_date as punch_out')->where('u.id',$user_id)->where('u.sub_institute_id',$sub_institute_id)->get()->toArray();
        }
    }
    // proxy lectures 
    function getProxyLecture($sub_institute_id,$syear,$searchDate,$user_id,$profileName,$profileId,$dayOfWeek,$activityType=''){
        return DB::table('proxy_master as a')
            ->join('period as p', 'a.period_id', '=', 'p.id')
            ->join('standard as s', 's.id', '=', 'a.standard_id')
            ->join('division as d', 'd.id', '=', 'a.division_id')
            ->join('sub_std_map as ssm', 'ssm.subject_id', '=', 'a.subject_id')
            ->join('tbluser as u', 'u.id', '=', 'a.teacher_id')
            ->select('a.id','a.timetable_id','a.week_day','a.proxy_date','a.teacher_id','u.id as user_id','u.user_name','a.period_id','p.title as periods','p.start_time','p.end_time','s.name as standard','d.name as division')
            ->where([['a.sub_institute_id', '=', $sub_institute_id],['a.syear', '=', $syear]])
            ->when($activityType=='upcoming',function($q) use($searchDate){
                $q->where('a.proxy_date','>=',$searchDate);
            })
              // for today
              ->when($activityType=='today',function($q) use($searchDate){
                $q->where('a.proxy_date',$searchDate);
            }) 
            // for recent
            ->when($activityType=='recent',function($q) use($searchDate){
                $q->where('a.proxy_date','<',$searchDate);
            }) 
            ->groupBy('a.id')
            ->orderBy('a.proxy_date')
            ->limit(10)
            ->get()->toArray();
    }
    // exam marks entered or not
    function getExamMarks($sub_institute_id,$syear,$searchDate,$user_id,$term_id,$standard_id,$activityType=""){
        return DB::table('result_create_exam as rce')
                ->join('standard as s', 's.id', '=', 'rce.standard_id')
                ->leftJoin('result_marks as rm','rm.exam_id','=','rce.id')
                ->selectRaw("rce.*,COUNT(rm.id) AS marks,s.name as standard")
                ->when($standard_id,function($q) use($standard_id){
                    $q->whereRaw('rce.standard_id in ('.$standard_id.')');
                })
                ->where(['rce.sub_institute_id'=>$sub_institute_id,'rce.syear'=>$syear,'rce.term_id'=>$term_id])
                // for today
                ->when($activityType=='today',function($q) use($searchDate){
                    $q->where('rm.created_at',$searchDate);
                }) 
                // for recent
                ->when($activityType=='recent',function($q) use($searchDate){
                    $q->where('rm.created_at','<',$searchDate);
                }) 
                ->groupBy('rce.standard_id')
                ->get()->toArray();
    }

    function getStudentAttendance($sub_institute_id,$syear,$searchDate,$user_id,$division_id,$standard_id,$activityType=""){
        return DB::table('attendance_student as att')
                ->join('standard as s', 's.id', '=', 'att.standard_id')
                ->selectRaw('att.*,s.name as standard')
                ->where(['att.sub_institute_id'=>$sub_institute_id,'att.syear'=>$syear])
                ->when($standard_id,function($q) use($standard_id){
                    $q->whereRaw('att.standard_id in ('.$standard_id.')');
                })
                ->when($division_id,function($q) use($division_id){
                    $q->whereRaw('att.section_id in ('.$division_id.')');
                })
                ->when($activityType=='upcoming',function($q) use($searchDate){
                    $q->where('att.attendance_date','>=',$searchDate);
                })
                  // for today
                  ->when($activityType=='today',function($q) use($searchDate){
                    $q->where('att.attendance_date',$searchDate);
                }) 
                // for recent
                ->when($activityType=='recent',function($q) use($searchDate){
                    $q->where('att.attendance_date','<',$searchDate);
                }) 
                ->groupByRaw('att.standard_id,att.section_id')
                ->limit(10)
                ->get()->toArray();
    }

    // task assigned 
    function getTaskAssigned($sub_institute_id,$syear,$searchDate,$user_id,$division_id,$standard_id,$activityType=''){
        return DB::table('task as t')
        ->join('tbluser as u', 'u.id', '=', 't.TASK_ALLOCATED_TO')
        ->selectRaw('t.*,u.user_name as task_user_name')
        ->where(['t.sub_institute_id'=>$sub_institute_id,'t.syear'=>$syear])
        ->when($activityType=='upcoming',function($q) use($searchDate){
            $q->where('t.TASK_DATE','>=',$searchDate)->where('t.STATUS','PENDING');
        }) // for today
        ->when($activityType=='today',function($q) use($searchDate){
          $q->where('t.TASK_DATE',$searchDate);
        }) 
        // for recent
        ->when($activityType=='recent',function($q) use($searchDate){
            $q->where('t.TASK_DATE','<',$searchDate);
        }) 
        ->where('t.TASK_ALLOCATED_TO',$user_id)
        ->get()->toArray();
    }

    // parent communication 
    function getParentCommunication($sub_institute_id,$syear,$searchDate,$user_id,$division_id,$standard_id,$activityType=''){
        return DB::table('parent_communication as pc')
        ->join('tblstudent_enrollment as se',function($q) use($sub_institute_id,$syear,$standard_id,$division_id){
            $q->on('se.student_id','=','pc.student_id')->where(['se.sub_institute_id'=>$sub_institute_id,'se.syear'=>$syear])
            ->when($standard_id,function($query) use($standard_id){
                $query->whereRaw('se.standard_id in ('.$standard_id.')');
            })
            ->when($division_id,function($query) use($division_id){
                $query->whereRaw('se.section_id in ('.$division_id.')');
            });
        })
        ->where(['pc.sub_institute_id'=>$sub_institute_id,'pc.syear'=>$syear])
        ->when($activityType=='upcoming',function($q) use($searchDate){
            $q->where('pc.date_','>=',$searchDate);
        }) // for today
        ->when($activityType=='today',function($q) use($searchDate){
          $q->where('pc.date_',$searchDate);
        }) 
        // for recent
        ->when($activityType=='recent',function($q) use($searchDate){
            $q->where('pc.date_','<',$searchDate);
        }) 
        // ->limit(10)
        ->get()->toArray();
    }
    // student leave 
    function getStudentLeave($sub_institute_id,$syear,$searchDate,$user_id,$division_id,$standard_id,$activityType=''){
        return DB::table('leave_applications as la')
                ->join('tblstudent_enrollment as se',function($q) use($sub_institute_id,$syear,$standard_id,$division_id){
                    $q->on('se.student_id','=','la.student_id')->where(['se.sub_institute_id'=>$sub_institute_id,'se.syear'=>$syear])->when($standard_id,function($query) use($standard_id){
                        $query->whereRaw('se.standard_id in ('.$standard_id.')');
                    })
                    ->when($division_id,function($query) use($division_id){
                        $query->whereRaw('se.section_id in ('.$division_id.')');
                    });
                })
                ->join('standard as s', 's.id', '=', 'se.standard_id')
                ->selectRaw('la.*,s.name as standard')
                    ->where(['la.sub_institute_id'=>$sub_institute_id,'la.syear'=>$syear])
                    ->when($activityType=='upcoming',function($q) use($searchDate){
                        $q->where('la.apply_date','>=',$searchDate);
                    }) // for today
                    ->when($activityType=='today',function($q) use($searchDate){
                    $q->where('la.apply_date',$searchDate);
                    }) 
                    // for recent
                    ->when($activityType=='recent',function($q) use($searchDate){
                        $q->where('la.apply_date','<',$searchDate);
                    }) 
                ->get()->toArray();
    }
}
