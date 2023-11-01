<?php

namespace App\Http\Controllers\student;

use App\Http\Controllers\Controller;
use App\Models\school_setup\subjectModel;
use App\Models\student\studentHomeworkModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\getStudents;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use function App\Helpers\sendNotification;

class studentHomeworkController extends Controller
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
        if($type=="API"){
            $sub_institute_id = $request->input('sub_institute_id');
        }else{
            $sub_institute_id = $request->session()->get('sub_institute_id');            
        }
        $res['status_code'] = 1;
        $res['message'] = "Success";

        $subjects = subjectModel::select('id',
            'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['subjects'] = $subjects;

        return is_mobile($type, "student/homework/show_student_homework", $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
     */
    public function create(Request $request)
    {
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $subject = $request->input('subject');
        $type = $request->input('type');
        $marking_period_id = session()->get('term_id');
        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        $data = SearchStudent($grade, $standard, $division, $sub_institute_id, $syear);

        $subjects = subjectModel::select('id',
            'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $data;
        $res['subjects'] = $subjects;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;

        $res['subject'] = $subject;

        return is_mobile($type, "student/homework/show_student_homework", $res, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return false|string
     */
    public function fetchData(Request $request)
    {
        $response = ['response' => '', 'success' => false];
        $marking_period_id = session()->get('term_id');
        $validator = Validator::make($request->all(), [
            'student_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $student_id = $_REQUEST['student_id'];

            $result = DB::table('tblstudent as s')
                ->join('tblstudent_enrollment as se', function ($join) {
                    $join->whereRaw('se.student_id = s.id');
                })->join('academic_section as g', function ($join) {
                    $join->whereRaw('g.id = se.grade_id');
                })->join('standard as st', function ($join) use($marking_period_id) {
                    $join->whereRaw('st.id = se.standard_id');
                    // ->when($marking_period_id,function($query) use($marking_period_id) {
                    //     $query->where('st.marking_period_id',$marking_period_id);
                    // });
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = se.section_id');
                })->join('school_setup as ss', function ($join) {
                    $join->whereRaw('s.sub_institute_id = ss.Id');
                })->selectRaw('se.standard_id,se.section_id,se.grade_id')
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear)
                ->where('se.student_id', $student_id)->groupBy('s.id')->get()->toArray();

            if ($result) {
                $server = "http://".$_SERVER['HTTP_HOST'];

                $result_data = DB::table('homework as hm')
                    ->join('subject as s', function ($join) {
                        $join->whereRaw('s.id = hm.subject_id');
                    })
                    ->selectRaw("hm.id,hm.student_id,hm.sub_institute_id,hm.title,hm.description,hm.date,
                        if(hm.image IS NULL OR hm.image='','-',concat('$server/storage/student/',hm.image)) file,s.subject_name")
                    ->where('hm.student_id', $student_id)
                    ->where('hm.syear', $syear)
                    ->where('hm.type', '=', 'Homework')
                    ->where('hm.sub_institute_id', $sub_institute_id)->get()->toArray();

                $response['response'] = $result_data;
                $response['success'] = true;
            } else {
                $response['response'] = ["student_id" => ["No student found."]];
            }
        }

        return json_encode($response);
    }

    public function store(Request $request)
    {
        $type = $request->get('type');
        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        $students = $request->get('students');
        $student_details = getStudents($students, $sub_institute_id, $syear);
        $title = $request->get('title');
        $description = $request->get('description');
        $submission_date = $request->get('submission_date');
        $division_id = $request->get('division_id');
        $standard_id = $request->get('standard_id');
        $subject_id = $request->get('subject_id');
        $created_by = ($request->session()->get('user_id') ? $request->session()->get('user_id') : $request->get('teacher_id'));

        $file_name = $file_size = $ext = "";
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $originalname = $file->getClientOriginalName();
            $file_size = $file->getSize();
            $name = "homework-".$request->get('user_name').date('YmdHis');
            $ext = File::extension($originalname);
            $file_name = $name.'.'.$ext;
            $path = $file->storeAs('public/student/', $file_name);
        }

        foreach ($student_details as $id => $arr) {
            $student_id = $arr['id'];
            $standard_id = $arr['standard_id'];
            $division_id = $arr['section_id'];
            $addhomeworkArray = [];
            $addhomeworkArray['student_id'] = $student_id;
            $addhomeworkArray['sub_institute_id'] = $sub_institute_id;
            $addhomeworkArray['title'] = $title;
            $addhomeworkArray['description'] = $description;
            $addhomeworkArray['standard_id'] = $standard_id;
            $addhomeworkArray['division_id'] = $division_id;
            $addhomeworkArray['subject_id'] = $subject_id;
            $addhomeworkArray['date'] = date('Y-m-d');
            $addhomeworkArray['submission_date'] = $submission_date;
            $addhomeworkArray['syear'] = $syear;
            $addhomeworkArray['type'] = "Homework";
            $addhomeworkArray['image'] = $file_name;
            $addhomeworkArray['image_size'] = $file_size;
            $addhomeworkArray['image_type'] = $ext;
            $addhomeworkArray['created_ip'] = $_SERVER['REMOTE_ADDR'];
            $addhomeworkArray['created_by'] = $created_by;
            studentHomeworkModel::insert($addhomeworkArray);

            //START Send Notification Code
            $app_notification_content = [
                'NOTIFICATION_TYPE'        => 'Homework',
                'NOTIFICATION_DATE'        => date('Y-m-d'),
                'STUDENT_ID'               => $student_id,
                'NOTIFICATION_DESCRIPTION' => $title,
                'STATUS'                   => 0,
                'SUB_INSTITUTE_ID'         => $sub_institute_id,
                'SYEAR'                    => $syear,
                'SCREEN_NAME'              => 'home_work',
                'CREATED_BY'               => $created_by,
                'CREATED_IP'               => $_SERVER['REMOTE_ADDR'],
            ];
            sendNotification($app_notification_content);
            //END Send Notification Code
        }

        $res['status_code'] = "1";
        $res['message'] = "Homework Added successfully";

        return is_mobile($type, "student_homework.index", $res);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }

    public function studentHomeworkReportIndex(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $subjects = subjectModel::select('id',
            'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['subjects'] = $subjects;

        return is_mobile($type, "student/homework/show_student_homework_report", $res, "view");
    }

    public function studentHomeworkReport(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $subject = $request->input('subject');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $marking_period_id = session()->get('term_id');

        $subjects = subjectModel::select('id',
            'subject_name')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $result = DB::table('homework as h')
            ->join('tblstudent as ts', function ($join) {
                $join->whereRaw('ts.id = h.student_id AND ts.sub_institute_id = h.sub_institute_id');
            })->join('standard as s', function ($join) use($marking_period_id){
                $join->whereRaw('h.standard_id = s.id AND h.sub_institute_id = s.sub_institute_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('s.marking_period_id',$marking_period_id);
                // });
            })->join('division as d', function ($join) {
                $join->whereRaw('d.id = h.division_id AND h.sub_institute_id= d.sub_institute_id');
            })->join('subject as ss', function ($join) {
                $join->whereRaw('ss.id = h.subject_id AND ss.sub_institute_id = h.sub_institute_id');
            })->selectRaw("h.*,s.name as standard_name,d.name as division_name,ss.subject_name,
                CONCAT_WS(' ',ts.first_name,ts.last_name) as student_name, ts.id as student_id")
            ->where('h.sub_institute_id', $sub_institute_id)
            ->where('h.syear', $syear);

        if ($standard != '') {
            $result = $result->where('h.standard_id', $standard);
        }

        if ($subject != '') {
            $result = $result->where('h.subject_id', $subject);
        }

        if ($division != '') {
            $result = $result->where('h.division_id', $division);
        }

        if ($grade != '') {
            $result = $result->where('s.grade_id', $grade);
        }

        if ($from_date != '') {
            $result = $result->where('h.date', '>=', $from_date);
        }

        if ($to_date != '') {
            $result = $result->where('h.date', '<=', $to_date);
        }

        $result = $result->get()->toArray();

        $result = array_map(function ($value) {
            return (array) $value;
        }, $result);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['report_data'] = $result;
        $res['subjects'] = $subjects;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['subject'] = $subject;
        $res['from_date'] = $from_date;
        $res['to_date'] = $to_date;

        return is_mobile($type, "student/homework/show_student_homework_report", $res, "view");
    }

    public function teacherHomeworkAssignmentAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $teacher_id = $request->input("teacher_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $action = $request->input("action");
        $marking_period_id = session()->get('term_id');

        if ($teacher_id != "" && $sub_institute_id != "" && $syear != "" && $action != "") {
            $data = DB::table('homework as h')
                ->join('tblstudent as ts', function ($join) {
                    $join->whereRaw('ts.id = h.student_id AND ts.sub_institute_id = h.sub_institute_id');
                })->join('standard as s', function ($join) use($marking_period_id) {
                    $join->whereRaw('h.standard_id = s.id AND h.sub_institute_id = s.sub_institute_id');
                    // ->when($marking_period_id,function($query) use($marking_period_id){
                    //     $query->where('s.marking_period_id',$marking_period_id);
                    // });
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = h.division_id AND h.sub_institute_id= d.sub_institute_id');
                })->join('subject as ss', function ($join) {
                    $join->whereRaw('ss.id = h.subject_id AND ss.sub_institute_id = h.sub_institute_id');
                })->join('class_teacher as ct', function ($join) {
                    $join->whereRaw('ct.standard_id = h.standard_id AND ct.division_id = h.division_id');
                })->selectRaw("h.id,h.title,h.description,h.date,if(h.image = '','',
                    concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',h.image)) as file_name,s.name AS standard_name,
                    d.name AS division_name,ss.subject_name,CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,
                    ts.enrollment_no,ts.mobile,h.type")
                ->where('h.sub_institute_id', $sub_institute_id)
                ->where('h.syear', $syear)
                ->where('ct.teacher_id', $teacher_id)
                ->where('h.type', $action)->get()->toArray();

            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $data;
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function studentHomeworkAssignmentAPI(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 401);
        }

        $student_id = $request->input("student_id");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");
        $action = $request->input("action");
        $marking_period_id = session()->get('term_id');
        // echo("<pre>");print_r($sub_institute_id);die;

        if ($student_id != "" && $sub_institute_id != "" && $syear != "" && $action != "") {
            $data = DB::table('homework as h')
                ->join('tblstudent as ts', function ($join) {
                    $join->whereRaw('ts.id = h.student_id AND ts.sub_institute_id = h.sub_institute_id');
                })->join('standard as s', function ($join) use($marking_period_id) {
                    $join->whereRaw('h.standard_id = s.id AND h.sub_institute_id = s.sub_institute_id');
                    // ->when($marking_period_id,function($query) use($marking_period_id){
                    //     $query->where('s.marking_period_id',$marking_period_id);
                    // });
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = h.division_id AND h.sub_institute_id= d.sub_institute_id');
                })->join('subject as ss', function ($join) {
                    $join->whereRaw('ss.id = h.subject_id AND ss.sub_institute_id = h.sub_institute_id');
                })->leftJoin('tbluser as tu', function ($join) {
                    $join->whereRaw('tu.id = h.created_by');
                })->selectRaw("h.id,h.title,h.description,h.created_by,DATE_FORMAT(h.date,'%d-%m-%Y') AS date, 
                    if(h.image = '','',concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',h.image)) as file_name,
                    s.name AS standard_name,d.name AS division_name,ss.subject_name, 
                    CONCAT_WS(' ',ts.first_name,ts.middle_name,ts.last_name) AS student_name,ts.enrollment_no,ts.mobile,
                    h.type,if(tu.image != NULL,concat('https://".$_SERVER['SERVER_NAME']."/storage/student/',tu.image),
                    'https://".$_SERVER['SERVER_NAME']."/storage/student/noimages.png') as user_image")
                ->where('h.sub_institute_id', $sub_institute_id)
                ->where('h.syear', $syear)
                ->where('h.student_id', $student_id)
                ->where('h.type', $action)
                ->orderBy('h.date', 'DESC')
                ->get()->toArray();
            //echo("<pre>");print_r($data);die;
            if (count($data) > 0) {
                $res['status'] = 1;
                $res['message'] = "Success";
                $res['data'] = $data;
            } else {
                $res['status'] = 0;
                $res['message'] = "No homework found";
            }
        } else {
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }

        return json_encode($res);
    }

    public function studentSubjectAPI(Request $request)
    {

        try {
            if (! $this->jwtToken()->validate()) {
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

                $data = DB::table('timetable as t')
                    ->join('sub_std_map as s', function ($join) {
                        $join->where('s.subject_id = t.subject_id');
                    })->join('tbluser as tu', function ($join) {
                        $join->where('tu.id = t.teacher_id');
                    })->selectRaw("display_name AS subject_name,elective_subject,allow_grades,t.teacher_id,
                        concat_ws(' ',tu.first_name,tu.middle_name,tu.last_name) as teacher_name")
                    ->where('t.syear', $syear)
                    ->where('t.sub_institute_id', $sub_institute_id)
                    ->where('t.standard_id', $standard_id)
                    ->where('t.division_id', $section_id)
                    ->groupBy('t.subject_id')->orderBy('display_name')->get()->toArray();

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

    /**
     * @param  Request  $request
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @return mixed
     */
    public function ajax_getHomeworkSubjects(Request $request)
    {
        $syear = $request->session()->get('syear');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_profile_name = session()->get('user_profile_name');
        $profile_parent_id = session()->get('profile_parent_id');
        $teacher_id = $request->session()->get('user_id');
        $standard_id = $request->input('standard_id');

        if ($profile_parent_id == '1') {
            $subject_teacher_subjects_data = DB::table('sub_std_map as s')
                ->selectRaw("s.subject_id,s.display_name,s.standard_id, '' as academic_section_id,'' as division_id,'' as teacher_id")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('s.standard_id', $standard_id)
                ->groupByRaw('s.subject_id,s.standard_id')
                ->orderBy('s.display_name')->get()->toArray();
        } else {
            $subject_teacher_subjects_data = DB::table('sub_std_map as s')
                ->join('timetable as t', function ($join) {
                    $join->whereRaw('t.standard_id = s.standard_id AND t.sub_institute_id = s.sub_institute_id AND t.subject_id = s.subject_id');
                })
                ->selectRaw("s.subject_id,s.display_name,t.academic_section_id,t.standard_id,t.division_id,t.teacher_id")
                ->where('s.sub_institute_id', $sub_institute_id)
                ->where('s.standard_id', $standard_id)
                ->where('t.teacher_id', $teacher_id)
                ->groupByRaw('s.subject_id,s.standard_id')
                ->orderBy('s.display_name')->get()->toArray();
        }

        // $class_teacher_sql = "SELECT s.subject_id,s.display_name,ct.grade_id,ct.standard_id,ct.division_id,ct.teacher_id
        // 					FROM sub_std_map s
        // 					INNER JOIN class_teacher ct ON ct.standard_id = s.standard_id AND ct.sub_institute_id = s.sub_institute_id
        // 					WHERE s.sub_institute_id = '".$sub_institute_id."' AND s.standard_id = '".$standard_id."' AND ct.syear = '".$syear."' AND ct.teacher_id = '".$teacher_id."'
        // 					GROUP BY s.subject_id
        // 					ORDER BY s.display_name";					
        // $class_teacher_subjects_data = DB::select($class_teacher_sql);
        // $class_teacher_subjects_data = json_decode(json_encode($class_teacher_subjects_data),true);

        // $all_subjects = array_merge($subject_teacher_subjects_data,$class_teacher_subjects_data);

        return json_decode(json_encode($subject_teacher_subjects_data), true);
    }

    public function multipleDelete(Request $request) 
    {
        $type = $request->get('type');

        $selectedStudents = $request->input('selected_students');
    
        $selectedStudents = explode(',', $selectedStudents);
        
        DB::table('homework')->whereIn('id', $selectedStudents)->delete();
        
        $res['status_code'] = "1";
        $res['message'] = "Student Homework Deleted Successfully";
    
        return is_mobile($type, "student_homework_report_index", $res, "redirect");
    }
}
