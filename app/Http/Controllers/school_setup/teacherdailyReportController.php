<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;


class teacherdailyReportController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['status_code'] = 1;

        return is_mobile($type, 'school_setup/show_teacherdailyreport', $data, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return tbluserModel::select('tbluser.*',
            DB::raw('concat(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as teacher_name'))
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', "=", 'tbluser.user_profile_id')
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher'])
            ->get();
    }

    public function getTeacherDailyReport(Request $request)
    {
        $date = $request->get('date');
        $status = $request->get('status');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $extra_query = '';

        // $data = SearchStudent($grade, $standard, $division);
        $sql = "SELECT DISTINCT ts.id as TEACHER_ID, CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS TEACHER, 
                ss.id as section_id, ss.name as division_name, cs.id as std_id,cs.name AS STD,sg.id AS grade_id,sg.short_name,
                '' S_NO,IF(((
                SELECT COUNT(*)
                FROM homework
                WHERE created_by = ts.id AND created_on LIKE '%".$date."%')) > 0,'Yes','No') AS HOMEWORK_ASSIGN, 
                IF(((
                SELECT COUNT(*)
                FROM homework
                WHERE created_by = ts.id AND submission_date LIKE '%".$date."%' AND completion_status = 'Y')) > 0,'Yes','No') AS HOMEWORK_CHECK, 
                IF(((
                SELECT COUNT(*)
                FROM parent_communication
                WHERE reply_by = ts.id AND created_at LIKE '%".$date."%')) > 0,'Yes','No') AS PARENT_COMM, 
                IF(((
                SELECT COUNT(*)
                FROM leave_applications
                WHERE reply = ts.id AND apply_date LIKE '%".$date."%')) > 0,'Yes','No') AS STUDENT_LEAVE, 
                IF(((
                SELECT COUNT(*)
                FROM attendance_student
                WHERE created_by = ts.id AND created_on LIKE '%".$date."%')) > 0,'Yes','No') AS STUDENT_ATTE
                FROM tbluser ts
                INNER JOIN tbluserprofilemaster tup on tup.id = ts.user_profile_id AND tup.name = 'Teacher'
                JOIN timetable tt ON ts.id = tt.teacher_id
                JOIN standard cs ON cs.id = tt.standard_id
                JOIN division ss ON ss.id = tt.division_id
                JOIN academic_section sg ON sg.id = tt.academic_section_id
                WHERE 1=1 AND tt.syear='".$syear."' AND ts.sub_institute_id = '".$sub_institute_id."'
                ";

        if ($status == 'N') {
            $sql .= "AND (((
                    SELECT COUNT(*)
                    FROM homework
                    WHERE created_by = ts.id AND created_on LIKE '%".$date."%') = 0) AND ((
                    SELECT COUNT(*)
                    FROM homework
                    WHERE created_by = ts.id AND submission_date LIKE '%".$date."%' AND completion_status = 'Y') = 0) AND ((
                    SELECT COUNT(*)
                    FROM parent_communication
                    WHERE reply_by = ts.id AND created_at LIKE '%".$date."%') = 0) AND ((
                    SELECT COUNT(*)
                    FROM leave_applications
                    WHERE reply = ts.id AND apply_date LIKE '%".$date."%') = 0) AND ((
                    SELECT COUNT(*)
                    FROM attendance_student
                    WHERE created_by = ts.id AND created_on LIKE '%".$date."%') = 0)
                    )";
        }
        if ($status == 'Y') {
            $sql .= "AND (((
                    SELECT COUNT(*)
                    FROM homework
                    WHERE created_by = ts.id AND created_on LIKE '%".$date."%') > 0) OR ((
                    SELECT COUNT(*)
                    FROM homework
                    WHERE created_by = ts.id AND submission_date LIKE '%".$date."%' AND completion_status = 'Y') > 0) OR ((
                    SELECT COUNT(*)
                    FROM parent_communication
                    WHERE reply_by = ts.id AND created_at LIKE '%".$date."%') > 0) OR ((
                    SELECT COUNT(*)
                    FROM leave_applications
                    WHERE reply = ts.id AND apply_date LIKE '%".$date."%') > 0) OR ((
                    SELECT COUNT(*)
                    FROM attendance_student
                    WHERE created_by = ts.id AND created_on LIKE '%".$date."%') > 0)
                    )";
        }

        $sql .= "GROUP BY tt.teacher_id
                ORDER BY ts.id";

        $result = DB::select($sql);

        $type = $request->input('type');
        $data['data'] = $result;
        $data['date_selected'] = $date;
        $data['status'] = $status;

        return is_mobile($type, 'school_setup/show_teacherdailyreport', $data, "view");
    }

    public function getTeacherDailyDetailsReport(Request $request)
    {
        // echo "asd";
        $date = $request->get('date');
        $teacher_id = $request->get('teacher_id');
        $action = $request->get('action');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        if ($action == 'homework_assign') {
            $RET = DB::table('homework as h')
                ->join('tbluser as ts', function ($join) {
                    $join->whereRaw('h.created_by = ts.id');
                })->join('timetable as tt', function ($join) {
                    $join->whereRaw('ts.id = tt.teacher_id');
                })->join('standard as cs', function ($join) {
                    $join->whereRaw('cs.id = h.standard_id');
                })->join('subject as s', function ($join) {
                    $join->whereRaw('s.id = h.subject_id');
                })->join('division as ss', function ($join) {
                    $join->whereRaw('ss.id = h.division_id');
                })->selectRaw("'' S_NO,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS teacher, 
					ss.name as div_name,cs.name AS STD,h.title,h.description, 
					date_format(h.created_on,'%d-%m-%Y') AS homework_date,h.image AS ATTACHMENT,s.subject_name AS SUBJECT")
                ->where('h.syear', $syear)
                ->where('h.created_on', 'LIKE', '%'.$date.'%')
                ->where('ts.id', $teacher_id)
                ->where('h.sub_institute_id', $sub_institute_id)
                ->groupByRaw('h.standard_id,h.division_id,h.title')
                ->distinct()
                ->get()->toArray();
        }

        if ($action == 'homework_check') {
            $RET = DB::table('homework as h')
                ->join('tbluser as ts', function ($join) {
                    $join->whereRaw('h.reply_by = ts.id');
                })->join('timetable as t', function ($join) {
                    $join->whereRaw('t.teacher_id = ts.id');
                })->join('standard as cs', function ($join) {
                    $join->whereRaw('cs.id = t.standard_id');
                })->join('subject as s', function ($join) {
                    $join->whereRaw('s.id = h.subject_id');
                })->join('division as ss', function ($join) {
                    $join->whereRaw('ss.id = t.division_id');
                })->selectRaw("'' S_NO,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS teacher, 
					ss.name as div_name,cs.name AS STD,h.title,h.description,date_format(h.created_on,'%d-%m-%Y') AS homework_date,
					date_format(h.submission_date,'%d-%m-%Y') AS submission_date,h.image AS ATTACHMENT,s.subject_name AS SUBJECT,
					h.submission_remarks")
                ->where('h.syear', $syear)
                ->where('h.submission_date', 'LIKE', '%'.$date.'%')
                ->where('ts.id', $teacher_id)
                ->where('h.sub_institute_id', $sub_institute_id)
                ->where('h.completion_status', '=', 'Y')
                ->groupByRaw('h.standard_id,h.division_id,h.title')
                ->distinct()
                ->get()->toArray();
        }

        if ($action == 'attedance') {
            $RET = DB::table('attendance_student as h')
                ->join('tbluser as ts', function ($join) {
                    $join->whereRaw('h.created_by = ts.id');
                })->join('standard as cs', function ($join) {
                    $join->whereRaw('cs.id = t.standard_id');
                })->join('division as ss', function ($join) {
                    $join->whereRaw('ss.id = t.division_id');
                })->selectRaw("'' S_NO, CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS teacher, 
					ss.name AS div_name,cs.name AS STD,h.attendance_code,DATE_FORMAT(h.created_on,'%d-%m-%Y') AS created_date,
					DATE_FORMAT(h.attendance_date,'%d-%m-%Y') AS attendance_date")
                ->where('h.syear', $syear)
                ->where('h.created_on', 'LIKE', '%'.$date.'%')
                ->where('ts.id', $teacher_id)
                ->where('h.sub_institute_id', $sub_institute_id)
                ->groupByRaw('h.standard_id,h.section_id')
                ->distinct()
                ->get()->toArray();
        }

        if ($action == 'parent_comm') {
            $RET = DB::table('parent_communication as h')
                ->join('tbluser as ts', function ($join) {
                    $join->whereRaw('h.reply_by = ts.id');
                })->join('timetable as t', function ($join) {
                    $join->whereRaw('t.teacher_id = ts.id');
                })->join('standard as cs', function ($join) {
                    $join->whereRaw('cs.id = t.standard_id');
                })->join('division as ss', function ($join) {
                    $join->whereRaw('ss.id = t.division_id');
                })->selectRaw("'' S_NO, CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS teacher, 
					ss.name AS div_name,cs.name AS STD,h.message,h.reply,DATE_FORMAT(h.created_at,'%d-%m-%Y') AS created_date,
					DATE_FORMAT(h.reply_on,'%d-%m-%Y') AS reply_date")
                ->where('h.syear', $syear)
                ->where('h.created_at', 'LIKE', '%'.$date.'%')
                ->where('ts.id', $teacher_id)
                ->where('h.sub_institute_id', $sub_institute_id)
                ->groupBy('h.id')
                ->distinct()
                ->get()->toArray();
        }

        if ($action == 'student_leave') {
            $RET = DB::table('leave_applications as h')
                ->join('tbluser as ts', function ($join) {
                    $join->whereRaw('h.reply = ts.id');
                })->join('timetable as t', function ($join) {
                    $join->whereRaw('t.teacher_id = ts.id');
                })->join('standard as cs', function ($join) {
                    $join->whereRaw('cs.id = t.standard_id');
                })->join('division as ss', function ($join) {
                    $join->whereRaw('ss.id = t.division_id');
                })->selectRaw("'' S_NO, CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS teacher, 
					ss.name AS div_name,cs.name AS STD,h.message,h.files,h.status,
					DATE_FORMAT(h.apply_date,'%d-%m-%Y') AS apply_date")
                ->where('h.syear', $syear)
                ->where('h.apply_date', 'LIKE', '%'.$date.'%')
                ->where('ts.id', $teacher_id)
                ->where('h.sub_institute_id', $sub_institute_id)
                ->groupBy('h.id')
                ->distinct()
                ->get()->toArray();
        }


        $type = $request->input('type');
        $data['data'] = $RET;
        $data['request_action'] = $request->get('action');

        return is_mobile($type, 'school_setup/show_teacherdailydetailsreport', $data, "view");
    }

}
