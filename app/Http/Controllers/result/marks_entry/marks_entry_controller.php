<?php

namespace App\Http\Controllers\result\marks_entry;

use App\Http\Controllers\Controller;
use App\Models\result\create_exam\exam_creation;
use App\Models\result\marks_entry\marks_entry;
use App\Models\result\std_grd_mapping\std_grd_maping;
use DB;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class marks_entry_controller extends Controller
{
    use GetsJwtToken;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
            if (isset($data_arr['class'])) {
                $data['class'] = $data_arr['class'];
            }
        }

        $data['data'] = array();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result/marks_entry/show", $data, "view");
    }
    public function get_marks_dd()
    {
        //Std-5/A/Term-1/Exam-type/Exam-name

        $sub_institute_id = $_REQUEST["sub_institute_id"];
        $syear = $_REQUEST["syear"];
        $student_id = $_REQUEST["student_id"];

        $query = "SELECT etm.ExamType,em.ExamTitle,rce.title
        FROM result_exam_type_master etm
        INNER JOIN result_exam_master em ON em.ExamType = etm.Id
        INNER JOIN result_create_exam rce ON rce.exam_id = em.Id
        INNER JOIN result_marks rm ON rm.student_id = $student_id
        WHERE rm.sub_institute_id = $sub_institute_id AND rce.syear = $syear
        GROUP BY em.ExamType
       ";

        echo ('<pre>');print_r($query);exit;
        $query = preg_replace('/\n+/', '', $query);
        $result = DB::select($query);

//         SELECT s.subject_name,rce.points f_marks,rm.points g_marks, SUM(rce.points) tf_marks, SUM(rm.points) tg_marks,
        // (100 * SUM(rm.points))/ SUM(rce.points) AVG
        // FROM result_create_exam rce
        // INNER JOIN subject s ON s.id = rce.subject_id
        // INNER JOIN result_marks rm ON rm.exam_id = rce.id
        // WHERE title = "Test Exam" AND rm.student_id = 3117

        // $data = json_encode($result);

        $responce_arr = array(
            "status" => "1",
            "message" => "Sucsess",
            "data" => $result,
        );

        echo json_encode($responce_arr);
        exit;
    }
    public function get_co_scholastic_marks_dd(Request $request)
    {
        //Std-5/A/Term-1/Exam-type/Exam-name
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 200);
        }
        $response = array('data' => '', 'status' => '0', 'message' => 'Failuer');
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {
            $sub_institute_id = $_REQUEST["sub_institute_id"];
            $teacher_id = $_REQUEST["teacher_id"];

            $query = "SELECT CONCAT_WS('/',s.name,d.name,ay.title,rcp.title,rc.title) resp_data,
                s.id AS standard_id, d.id AS division_id,rc.mark_type,rc.term_id,rc.id co_scholastic_id, s.grade_id acdemic_section_id,
                rc.co_grade,rc.max_mark
            FROM timetable t
            INNER JOIN standard s ON s.id = t.standard_id
            INNER JOIN division d ON d.id = t.division_id
            INNER JOIN result_co_scholastic_parent rcp ON rcp.sub_institute_id = 46
            INNER JOIN result_co_scholastic rc ON rc.parent_id = rcp.id
            INNER JOIN result_co_scholastic_grades
            INNER JOIN academic_year ay ON ay.term_id = rc.term_id
            WHERE t.sub_institute_id=$sub_institute_id AND t.teacher_id=$teacher_id
            GROUP BY t.standard_id,t.division_id,rc.term_id
            ORDER BY t.standard_id
            ";
            $query = preg_replace('/\n+/', '', $query);
            $result = DB::select($query);

            $send_data = $result;

            foreach ($result as $id => $arr) {
                if ($arr->mark_type == 'GRADE') {
                    $map_id = $arr->co_grade;
                    $query = "SELECT *
                FROM result_co_scholastic_grades
                WHERE map_id = $map_id
            ";
                    $query = preg_replace('/\n+/', '', $query);
                    $grade_result = DB::select($query);
                    // echo ('<pre>');print_r($grade_result);exit;
                    $send_data[$id]->grades = $grade_result;
                } else {
                    $send_data[$id]->grades = array();
                }
            }
        }
        // $data = json_encode($send_data);
        // echo ('<pre>');print_r($data);exit;

        $responce_arr = array(
            "status" => "1",
            "message" => "Sucsess",
            "data" => $send_data,
        );

        echo json_encode($responce_arr);
        exit;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
//        echo "<pre>";
        //        print_r($_REQUEST);
        //        exit;

        $where = array(
            'id' => $_REQUEST["exam"],
        );
        $working_day = exam_creation::
            select('points')
            ->where($where)->get()->toArray();

//        echo "<pre>";
        //        print_r($working_day);
        //        exit;
        $student_data = \App\Helpers\SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        $grd_data = $this->getGreadData($_REQUEST['standard']);
        //marks_entry
        $type = $request->input('type');
        $where = array(
            'exam_id' => $_REQUEST["exam"],
            'sub_institute_id' => session()->get('sub_institute_id'),
        );
        $marks_entry = marks_entry::where($where)->get()->toArray();
// dd($student_data);

        // $responce_arr = array();
        $responce_arr['term_id'] = $_REQUEST["term"];
        $responce_arr['standard'] = $_REQUEST["standard"];
        $responce_arr['grade'] = $_REQUEST['grade'];
        $responce_arr['division'] = $_REQUEST['division'];
        $responce_arr['subject_dd'] = $this->getSubjectDD($_REQUEST["standard"]);
        $responce_arr['subject'] = $_REQUEST['subject'];
        $responce_arr['exam_dd'] = $this->getExamDD($_REQUEST["term"], $_REQUEST["standard"], $_REQUEST['subject']);
        $responce_arr['exam'] = $_REQUEST['exam'];
        $responce_arr['grd_data'] = $grd_data;
        $attendance_data = "";
        if (!empty($student_data)) {
            foreach ($student_data as $id => $arr) {
                $temp_arr = array();
                foreach ($marks_entry as $data_id => $data_arr) {
                    if ($data_arr['student_id'] == $arr['student_id']) {
                        $temp_arr = $data_arr;
                    }
                }

                $responce_arr['term_id'] = $_REQUEST["term"];
                $responce_arr['standard'] = $_REQUEST["standard"];
                $responce_arr['grade'] = $_REQUEST['grade'];
                $responce_arr['division'] = $_REQUEST['division'];
                $responce_arr['subject_dd'] = $this->getSubjectDD($_REQUEST["standard"]);
                $responce_arr['subject'] = $_REQUEST['subject'];
                $responce_arr['exam_dd'] = $this->getExamDD($_REQUEST["term"], $_REQUEST["standard"], $_REQUEST['subject']);
                $responce_arr['exam'] = $_REQUEST['exam'];
                $responce_arr['grd_data'] = $grd_data;

                $get_elective_subjects = DB::select("SELECT sm.subject_id,sm.standard_id,sm.allow_grades,sm.elective_subject,sm.display_name
                                        FROM sub_std_map sm
                                        WHERE sm.sub_institute_id = '" . session()->get('sub_institute_id') . "' AND sm.standard_id = '" . $_REQUEST["standard"] . "' AND sm.subject_id = '" . $_REQUEST['subject'] . "' AND sm.allow_grades = 'Yes'
                                        ");

                $get_elective_subjects = json_decode(json_encode($get_elective_subjects), true);
                // $check_map_student = 0;
                if ($get_elective_subjects[0]['elective_subject'] == 'Yes') {
                    $check_optional_subject_with_student = DB::select("SELECT * FROM student_optional_subject
                                                                        WHERE student_id = '" . $arr['student_id'] . "'
                                                                        AND subject_id = '" . $_REQUEST['subject'] . "'
                                                                        AND syear = '" . session()->get('syear') . "' ");

                    $check_optional_subject_with_student = json_decode(json_encode($check_optional_subject_with_student), true);

                    if (count($check_optional_subject_with_student) != 0) {
                        // $check_map_student = 1;
                        $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
                        $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];
                        $responce_arr['stu_data'][$id]['roll_no'] = $arr['roll_no'];

                        if (count($temp_arr) > 0) {
                            //START BY RAJESH REMOVE DECIMAL .00
                            if($temp_arr["points"]=='-1')
                                $points = '*';
                            elseif(strpos($temp_arr["points"],'.'))
                                $points = rtrim(rtrim($temp_arr["points"],'0'),'.');
                            else
                                $points = $temp_arr["points"];
                            //END BY RAJESH REMOVE DECIMAL .00

                            if ($temp_arr['is_absent'] == "AB") {
                                $responce_arr['stu_data'][$id]['points'] = $temp_arr['is_absent'];
                            } else {
                                $responce_arr['stu_data'][$id]['points'] = $points;
                            }
                            $responce_arr['stu_data'][$id]['outof'] = $working_day[0]["points"];
                            $responce_arr['stu_data'][$id]['per'] = $temp_arr["per"];
                            $responce_arr['stu_data'][$id]['grade'] = $temp_arr["grade"];
                            $responce_arr['stu_data'][$id]['comment'] = $temp_arr["comment"];
                        } else {
                            $responce_arr['stu_data'][$id]['points'] = 0;
                            $responce_arr['stu_data'][$id]['outof'] = $working_day[0]["points"];
                            $responce_arr['stu_data'][$id]['per'] = 0;
                            $responce_arr['stu_data'][$id]['grade'] = "-";
                            $responce_arr['stu_data'][$id]['comment'] = "";
                        }
                        $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
                    }

                } else {
                    // $check_map_student = 0;
                    $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
                    $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];
                    $responce_arr['stu_data'][$id]['roll_no'] = $arr['roll_no'];

                    if (count($temp_arr) > 0) {
                        //START BY RAJESH REMOVE DECIMAL .00
                        if($temp_arr["points"]=='-1')
                            $points = '*';
                        elseif(strpos($temp_arr["points"],'.'))
                            $points = rtrim(rtrim($temp_arr["points"],'0'),'.');
                        else
                            $points = $temp_arr["points"];
                        //END BY RAJESH REMOVE DECIMAL .00

                        if ($temp_arr['is_absent'] == "AB") {
                            $responce_arr['stu_data'][$id]['points'] = $temp_arr['is_absent'];
                        } else {
                            $responce_arr['stu_data'][$id]['points'] = $points;
                        }
                        $responce_arr['stu_data'][$id]['outof'] = $working_day[0]["points"];
                        $responce_arr['stu_data'][$id]['per'] = $temp_arr["per"];
                        $responce_arr['stu_data'][$id]['grade'] = $temp_arr["grade"];
                        $responce_arr['stu_data'][$id]['comment'] = $temp_arr["comment"];
                    } else {
                        $responce_arr['stu_data'][$id]['points'] = 0;
                        $responce_arr['stu_data'][$id]['outof'] = $working_day[0]["points"];
                        $responce_arr['stu_data'][$id]['per'] = 0;
                        $responce_arr['stu_data'][$id]['grade'] = "-";
                        $responce_arr['stu_data'][$id]['comment'] = "";
                    }
                    $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
                }
            }
        }
        // dd($responce_arr);
        return \App\Helpers\is_mobile($type, "result/marks_entry/add", $responce_arr, "view");
    }

    public function getSubjectDD($std)
    {
        $where = array(
            "sub_std_map.sub_institute_id" => session()->get('sub_institute_id'),
            "sub_std_map.allow_grades" => "Yes",
        );
        $where['sub_std_map.standard_id'] = $std;
        $std_sub_map = DB::table('subject')
            ->join('sub_std_map', 'subject.id', '=', 'sub_std_map.subject_id')
            ->where($where)
            ->orderBy('sub_std_map.sort_order')
            ->pluck('sub_std_map.display_name', 'subject.id');
        return $std_sub_map;
    }

    public function getExamDD($term, $std, $sub)
    {
        $where = array(
            "re.sub_institute_id" => session()->get('sub_institute_id'),
            "re.syear" => session()->get('syear'),
            "re.term_id" => $term,
            "re.standard_id" => $std,
            "re.subject_id" => $sub,
        );

        $std_sub_map = DB::table('result_create_exam as re')
            ->where($where)
            ->pluck('re.title', 're.id');

        return $std_sub_map;
    }
    public function get_result(Request $request)
    {
        try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 200);
        }
        $response = array('data' => '', 'status' => '0', 'message' => 'Failuer');
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'syear' => 'required|numeric',
        ]);
        $send_data = array();
        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {
            $sub_institute_id = $_REQUEST["sub_institute_id"];
            $student_id = $_REQUEST["student_id"];

            $query = "SELECT CONCAT_WS('-',etm.ShortName,em.ExamTitle,rce.title) exam_name,
            etm.Id etmid,em.Id emid,rce.id rceid,rce.title
            FROM result_exam_type_master etm
            INNER JOIN result_exam_master em ON em.ExamType = etm.Id
            INNER JOIN result_create_exam rce ON rce.exam_id = em.Id
            INNER JOIN subject s ON s.id = rce.subject_id
            INNER JOIN result_marks rm ON rm.exam_id = rce.id
            WHERE em.SubInstituteId = $sub_institute_id AND rm.student_id = $student_id
            GROUP BY rce.title
            ";
            $query = preg_replace('/\n+/', '', $query);
            $exams = DB::select($query);
            $exams = json_encode($exams);
            $exams = json_decode($exams, 1);

            $send_data = array();
            $i = 0;
            foreach ($exams as $id => $arr) {
                $exam_name = $arr["title"];
                $sql_result = "SELECT s.subject_name, rce.points f_marks, rm.points g_marks, SUM(rce.points) total_marks, SUM(rm.points) totalk_get_marks,
                (SUM(rm.points)*100/ SUM(rce.points)) avge
                FROM result_exam_type_master etm
                INNER JOIN result_exam_master em ON em.ExamType = etm.Id
                INNER JOIN result_create_exam rce ON rce.exam_id = em.Id
                INNER JOIN subject s ON s.id = rce.subject_id
                INNER JOIN result_marks rm ON rm.exam_id = rce.id
                WHERE em.SubInstituteId = $sub_institute_id
                AND rm.student_id = $student_id AND rce.title = '$exam_name'
                group by s.subject_name
                ";
                $sql_result = preg_replace('/\n+/', '', $sql_result);
                $ret_result = DB::select($sql_result);
                $send_data[$i]["exam_data"] = $arr;
                $send_data[$i]["exam_data"]["result"] = $ret_result;
                $i++;

            }
            // $send_data = json_encode($send_data);
            // echo ('<pre>');print_r($send_data);exit;

            // $send_data = $result;

            $response = array(
                "status" => "1",
                "message" => "Sucsess",
                "data" => $send_data,
            );
        }
        // $data = json_encode($send_data);
        // echo ('<pre>');print_r($data);exit;

        echo json_encode($response);
        exit;

    }
    public function getGreadData($std)
    {
        $join = array(
            "gd.grade_id" => "rm.grade_scale",
            "gd.sub_institute_id" => "rm.sub_institute_id",
        );
        $where = array(
            "rm.sub_institute_id" => session()->get('sub_institute_id'),
            "rm.standard" => $std,
            "gd.syear" => session()->get('syear'),
        );
        $data = std_grd_maping::from("result_std_grd_maping as rm")
            ->join("grade_master_data as gd", $join)
            ->where($where)->get()->toArray();
        $final_arr = array();

        if (count($data) > 0) {
            $temp_arr = array();
            foreach ($data as $id => $arr) {
                $temp_arr[$arr['breakoff']] = $arr['title'];
            }
            ksort($temp_arr);
            $i = 1;
            foreach ($temp_arr as $id => $val) {
                $farr[$i][$id] = $val;
                $i++;
            }

            $cnt = 1;
            $last_id = 0;
            // foreach ($farr as $id => $arrs) {
            //     foreach ($arrs as $bk => $val) {
            //         if ($id == 1) {
            //             $final_arr[$val] = range(0, $bk);
            //             continue;
            //         }
            //         if ($id == count($farr)) {
            //             $final_arr[$val] = range($bk + 1, 100);
            //             continue;
            //         }
            //         foreach ($farr[$id - 1] as $last_val => $vals) {
            //             $final_arr[$val] = range($last_val + 1, $bk);
            //         }
            //     }
            // }

            foreach ($farr as $id => $arrs) {
                foreach ($arrs as $bk => $val) {
                    if ($id != 1) {
                        $bk = $bk; // + 1
                    }
                    if ($id != count($farr)) {
                        $new_bk = key($farr[$id + 1]);
                        $new_bk = $new_bk - 1;
                        $final_arr[$val] = range($bk, $new_bk);
                    } else {
                        $final_arr[$val] = range($bk, 100);
                    }
                }
            }
        }

        return $final_arr;
//        return response()->json($final_arr);
        //        echo "<pre>";
        //        print_r($data);
        //        exit;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        // $new_data = array(
        //     "3117" => array(
        //             "exam_id" => 206,
        //             "points" => 10,
        //             "per" => "10.00%",
        //             "grade" => "A2",
        //             "comment" => "",
        //         ),
        //     "3118" => array(
        //             "exam_id" => 206,
        //             "points" => 10,
        //             "per" => "10.00%",
        //             "grade" => "A2",
        //             "comment" => "",
        //         ),
        // );
        // echo ('<pre>');print_r(json_encode($new_data));exit;
        //
        //        echo "<pre>";
        //        print_r($_REQUEST);
        //        exit;
        //         'id',
        //        'student_id',
        //        'exam_id',
        //        'points',
        //        'grade',
        //        'per',
        //        'comment',
        //        'is_absent',
        //        'sub_institute_id'
        $sub_institute_id = session()->get('sub_institute_id');
        $all_data = array();
        if (isset($_REQUEST["type"]) && $_REQUEST["type"] == "API") {
            $sub_institute_id = $_REQUEST["sub_institute_id"];
            $all_data = json_decode($_REQUEST["data"], 1);
        } else {
            $all_data = $_REQUEST['values'];
        }
        foreach ($all_data as $student_id => $arr) {
            marks_entry::where([
                'sub_institute_id' => $sub_institute_id,
                'student_id' => $student_id,
                'exam_id' => $arr['exam_id'],
            ])->delete();
            if ($arr['points'] != ''
            ) {
                if (preg_match("/[a-z]/i", $arr['points'])) {
                    if (strtoupper($arr['points']) == "AB") {
                        $data = new marks_entry([
                            'student_id' => $student_id,
                            'exam_id' => $arr['exam_id'],
                            'points' => 0,
                            'per' => 0,
                            'grade' => '-',
                            'comment' => $arr['comment'],
                            'is_absent' => "AB",
                            'sub_institute_id' => $sub_institute_id,
                        ]);
                        $data->save();
                    }
                } else {
                    $arr['per'] = rtrim($arr['per'], '%');
                    $data = new marks_entry([
                        'student_id' => $student_id,
                        'exam_id' => $arr['exam_id'],
                        'points' => $arr['points'],
                        'per' => $arr['per'],
                        'grade' => $arr['grade'],
                        'comment' => $arr['comment'],
                        'sub_institute_id' => $sub_institute_id,
                    ]); //'is_absent' => "-",
                    $data->save();
                }
            }
        }
        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
            "class" => "success",
        );

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "marks_entry.index", $res, "redirect");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}