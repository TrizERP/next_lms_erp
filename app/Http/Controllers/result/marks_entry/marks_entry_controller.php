<?php

namespace App\Http\Controllers\result\marks_entry;

use App\Http\Controllers\Controller;
use App\Models\result\create_exam\exam_creation;
use App\Models\result\marks_entry\marks_entry;
use App\Models\result\std_grd_mapping\std_grd_maping;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class marks_entry_controller extends Controller
{
    use GetsJwtToken;

    /**
     * Display a listing of the resource.
     *
     * @return Response
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

        $data['data'] = [];
        $type = $request->input('type');

        return is_mobile($type, "result/marks_entry/show", $data, "view");
    }

    public function get_marks_dd()
    {
        $sub_institute_id = $_REQUEST["sub_institute_id"];
        $syear = $_REQUEST["syear"];
        $student_id = $_REQUEST["student_id"];

        $result = DB::table("result_exam_type_master as etm")
            ->join('result_exam_master as em', function ($join) {
                $join->whereRaw("em.ExamType = etm.Id");
            })
            ->join('result_create_exam as rce', function ($join) {
                $join->whereRaw("rce.exam_id = em.Id");
            })
            ->join('result_marks as rm', function ($join) use ($student_id) {
                $join->whereRaw("rm.student_id = $student_id");
            })
            ->selectRaw('etm.ExamType,em.ExamTitle,rce.title')
            ->where("rm.sub_institute_id", "=", $sub_institute_id)
            ->where("rce.syear", "=", $syear)
            ->groupBy('em.ExamType')
            ->get()->toarray();

//        echo ('<pre>');print_r($query);exit;

//         SELECT s.subject_name,rce.points f_marks,rm.points g_marks, SUM(rce.points) tf_marks, SUM(rm.points) tg_marks,
        // (100 * SUM(rm.points))/ SUM(rce.points) AVG
        // FROM result_create_exam rce
        // INNER JOIN subject s ON s.id = rce.subject_id
        // INNER JOIN result_marks rm ON rm.exam_id = rce.id
        // WHERE title = "Test Exam" AND rm.student_id = 3117

        // $data = json_encode($result);

        $responce_arr = [
            "status"  => "1",
            "message" => "Sucsess",
            "data"    => $result,
        ];

        echo json_encode($responce_arr);
        exit;
    }

    public function get_co_scholastic_marks_dd(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 200);
        }
        $response = ['data' => '', 'status' => '0', 'message' => 'Failuer'];
        $validator = Validator::make($request->all(), [
            'teacher_id'       => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {
            $sub_institute_id = $_REQUEST["sub_institute_id"];
            $teacher_id = $_REQUEST["teacher_id"];

            $result = DB::table("timetable as t")
                ->join('standard as s', function ($join) {
                    $join->whereRaw("s.id = t.standard_id");
                })
                ->join('division as d', function ($join) {
                    $join->whereRaw("d.id = t.division_id");
                })
                ->join('result_co_scholastic_parent as rcp', function ($join) {
                    $join->whereRaw("rcp.sub_institute_id = 46");
                })
                ->join('result_co_scholastic as rc', function ($join) {
                    $join->whereRaw("rc.parent_id = rcp.id");
                })
                ->join('result_co_scholastic_grades', function ($join) {
                    $join->whereRaw("");
                })
                ->join('academic_year as ay', function ($join) {
                    $join->whereRaw("ay.term_id = rc.term_id");
                })
                ->selectRaw('CONCAT_WS(' / ',s.name,d.name,ay.title,rcp.title,rc.title) resp_data,
    s.id AS standard_id, d.id AS division_id,rc.mark_type,rc.term_id,rc.id co_scholastic_id, s.grade_id acdemic_section_id,
    rc.co_grade,rc.max_mark')
                ->where("t.sub_institute_id", "=", $sub_institute_id)
                ->where("t.teacher_id", "=", $teacher_id)
                ->groupByRaw('t.standard_id,t.division_id,rc.term_id')
                ->orderBy('t.standard_id')
                ->get()->toarray();

            $send_data = $result;

            foreach ($result as $id => $arr) {
                if ($arr->mark_type == 'GRADE') {
                    $map_id = $arr->co_grade;

                    $grade_result = DB::table('result_co_scholastic_grades')->where('map_id',
                        $map_id)->get()->toArray();

                    $send_data[$id]->grades = $grade_result;
                } else {
                    $send_data[$id]->grades = [];
                }
            }
        }

        $responce_arr = [
            "status"  => "1",
            "message" => "Sucsess",
            "data"    => $send_data,
        ];

        echo json_encode($responce_arr);
        exit;
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
        $where = [
            'id' => $_REQUEST["exam"],
        ];
        $working_day = exam_creation::select('points')
            ->where($where)->get()->toArray();

        $student_data = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        $grd_data = $this->getGreadData($_REQUEST['standard']);
        //marks_entry
        $type = $request->input('type');

        $where = [
            'exam_id'          => $_REQUEST["exam"],
            'sub_institute_id' => session()->get('sub_institute_id'),
        ];

        $marks_entry = marks_entry::where($where)->get()->toArray();

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
        if (! empty($student_data)) {
            foreach ($student_data as $id => $arr) {
                $temp_arr = [];
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
                $responce_arr['exam_dd'] = $this->getExamDD($_REQUEST["term"], $_REQUEST["standard"],
                    $_REQUEST['subject']);
                $responce_arr['exam'] = $_REQUEST['exam'];
                $responce_arr['grd_data'] = $grd_data;

                $get_elective_subjects = DB::table("sub_std_map as sm")
                    ->selectRaw('sm.subject_id,sm.standard_id,sm.allow_grades,sm.elective_subject,sm.display_name')
                    ->where("sm.sub_institute_id", "=", session()->get('sub_institute_id'))
                    ->where("sm.standard_id", "=", $_REQUEST["standard"])
                    ->where("sm.subject_id", "=", $_REQUEST["subject"])
                    ->where("sm.allow_grades", "=", 'Yes')
                    ->get()->toArray();

                $get_elective_subjects = json_decode(json_encode($get_elective_subjects), true);
                // $check_map_student = 0;
                if ($get_elective_subjects[0]['elective_subject'] == 'Yes') {
                    $check_optional_subject_with_student = DB::table("student_optional_subject")
                        ->where("student_id", "=", $arr['student_id'])
                        ->where("subject_id", "=", $_REQUEST['subject'])
                        ->where("syear", "=", session()->get('syear'))
                        ->get()->toArray();

                    $check_optional_subject_with_student = json_decode(json_encode($check_optional_subject_with_student),
                        true);

                    if (count($check_optional_subject_with_student) != 0) {
                        // $check_map_student = 1;
                        $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
                        $responce_arr['stu_data'][$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];
                        $responce_arr['stu_data'][$id]['roll_no'] = $arr['roll_no'];

                        if (count($temp_arr) > 0) {
                            //START BY RAJESH REMOVE DECIMAL .00
                            if ($temp_arr["points"] == '-1') {
                                $points = '*';
                            } elseif (strpos($temp_arr["points"], '.')) {
                                $points = rtrim(rtrim($temp_arr["points"], '0'), '.');
                            } else {
                                $points = $temp_arr["points"];
                            }
                            //END BY RAJESH REMOVE DECIMAL .00

                            if ($temp_arr['is_absent'] == "AB") {
                                $responce_arr['stu_data'][$id]['points'] = $temp_arr['is_absent'];
                            }elseif($temp_arr['is_absent'] == "N.A."){
                                $responce_arr['stu_data'][$id]['points'] = $temp_arr['is_absent'];

                            }elseif($temp_arr['is_absent'] == "EX"){
                                $responce_arr['stu_data'][$id]['points'] = $temp_arr['is_absent'];
                                
                            }else {
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
                    $responce_arr['stu_data'][$id]['name'] = $arr['first_name'].' '.$arr['middle_name'].' '.$arr['last_name'];
                    $responce_arr['stu_data'][$id]['roll_no'] = $arr['roll_no'];

                    if (count($temp_arr) > 0) {
                        //START BY RAJESH REMOVE DECIMAL .00
                        if ($temp_arr["points"] == '-1') {
                            $points = '*';
                        } elseif (strpos($temp_arr["points"], '.')) {
                            $points = rtrim(rtrim($temp_arr["points"], '0'), '.');
                        } else {
                            $points = $temp_arr["points"];
                        }
                        //END BY RAJESH REMOVE DECIMAL .00

                        if ($temp_arr['is_absent'] == "AB") {
                            $responce_arr['stu_data'][$id]['points'] = $temp_arr['is_absent'];
                        }elseif($temp_arr['is_absent'] == "N.A."){
                             $responce_arr['stu_data'][$id]['points'] = $temp_arr['is_absent'];
                        }elseif($temp_arr['is_absent'] == "EX"){
                             $responce_arr['stu_data'][$id]['points'] = $temp_arr['is_absent'];
                        }else {
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

        return is_mobile($type, "result/marks_entry/add", $responce_arr, "view");
    }

    public function getSubjectDD($std)
    {
        $where = [
            "sub_std_map.sub_institute_id" => session()->get('sub_institute_id'),
            "sub_std_map.allow_grades"     => "Yes",
        ];

        $where['sub_std_map.standard_id'] = $std;

        return DB::table('subject')
            ->join('sub_std_map', 'subject.id', '=', 'sub_std_map.subject_id')
            ->where($where)
            ->orderBy('sub_std_map.sort_order')
            ->pluck('sub_std_map.display_name', 'subject.id');
    }

    public function getExamDD($term, $std, $sub)
    {
        $where = [
            "re.sub_institute_id" => session()->get('sub_institute_id'),
            "re.syear"            => session()->get('syear'),
            "re.term_id"          => $term,
            "re.standard_id"      => $std,
            "re.subject_id"       => $sub,
        ];

        return DB::table('result_create_exam as re')
            ->where($where)
            ->pluck('re.title', 're.id');
    }

    public function get_result(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];

                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];

            return response()->json($response, 200);
        }
        $response = ['data' => '', 'status' => '0', 'message' => 'Failuer'];
        $validator = Validator::make($request->all(), [
            'student_id'       => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
        ]);
        $send_data = [];
        if ($validator->fails()) {
            $response['message'] = $validator->messages();
        } else {
            $sub_institute_id = $_REQUEST["sub_institute_id"];
            $student_id = $_REQUEST["student_id"];

            $exams = DB::table("result_exam_type_master as etm")
                ->join('result_exam_master as em', function ($join) {
                    $join->whereRaw("em.ExamType = etm.Id");
                })
                ->join('result_create_exam as rcm', function ($join) {
                    $join->whereRaw("rce.exam_id = em.Id");
                })
                ->join('subject as s', function ($join) {
                    $join->whereRaw("s.id = rce.subject_id");
                })
                ->join('result_marks as rm', function ($join) {
                    $join->whereRaw("rm.exam_id = rce.id");
                })
                ->selectRaw('CONCAT_WS(' - ',etm.ShortName,em.ExamTitle,rce.title) exam_name,
                etm.Id etmid,em.Id emid,rce.id rceid,rce.title')
                ->where("em.SubInstituteId", "=", $sub_institute_id)
                ->where("rm.student_id", "=", $student_id)
                ->groupBy('rce.title')
                ->get()->toArray();

            $exams = json_encode($exams);
            $exams = json_decode($exams, 1);

            $send_data = [];
            $i = 0;

            foreach ($exams as $id => $arr) {
                $exam_name = $arr["title"];

                $ret_result = DB::table("result_exam_type_master as etm")
                    ->join('result_exam_master as em', function ($join) {
                        $join->whereRaw("em.ExamType = etm.Id");
                    })
                    ->join('result_create_exam as rce', function ($join) {
                        $join->whereRaw("rce.exam_id = em.Id");
                    })
                    ->join('subject as s', function ($join) {
                        $join->whereRaw("s.id = rce.subject_id");
                    })
                    ->join('result_marks as rm', function ($join) {
                        $join->whereRaw("rm.exam_id = rce.id");
                    })
                    ->selectRaw('s.subject_name, rce.points f_marks, rm.points g_marks, SUM(rce.points) total_marks, 
                SUM(rm.points) totalk_get_marks,(SUM(rm.points)*100/ SUM(rce.points)) avge')
                    ->where("em.SubInstituteId", "=", $sub_institute_id)
                    ->where("rm.student_id", "=", $student_id)
                    ->where("rce.title", "=", $exam_name)
                    ->groupBy('s.subject_name')
                    ->get()->toArray();

                $send_data[$i]["exam_data"] = $arr;
                $send_data[$i]["exam_data"]["result"] = $ret_result;
                $i++;

            }

            $response = [
                "status"  => "1",
                "message" => "Sucsess",
                "data"    => $send_data,
            ];
        }

        echo json_encode($response);
        exit;
    }

    public function getGreadData($std)
    {
        $join = [
            "gd.grade_id"         => "rm.grade_scale",
            "gd.sub_institute_id" => "rm.sub_institute_id",
        ];
        $where = [
            "rm.sub_institute_id" => session()->get('sub_institute_id'),
            "rm.standard"         => $std,
            "gd.syear"            => session()->get('syear'),
        ];

        $data = std_grd_maping::from("result_std_grd_maping as rm")
            ->join("grade_master_data as gd", $join)
            ->where($where)->get()->toArray();
        $final_arr = [];

        if (count($data) > 0) {
            $temp_arr = [];
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
                        --$new_bk;
                        $final_arr[$val] = range($bk, $new_bk);
                    } else {
                        $final_arr[$val] = range($bk, 100);
                    }
                }
            }
        }

        return $final_arr;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @return Response
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
        $all_data = [];

        if (isset($_REQUEST["type"]) && $_REQUEST["type"] == "API") {
            $sub_institute_id = $_REQUEST["sub_institute_id"];
            $all_data = json_decode($_REQUEST["data"], 1);
        } else {
            $all_data = $_REQUEST['values'];
        }

        foreach ($all_data as $student_id => $arr) {
            marks_entry::where([
                'sub_institute_id' => $sub_institute_id,
                'student_id'       => $student_id,
                'exam_id'          => $arr['exam_id'],
            ])->delete();
            if ($arr['points'] != '') {
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
                    elseif (strtoupper($arr['points']) == "N.A.") {
                         $data = new marks_entry([
                            'student_id' => $student_id,
                            'exam_id' => $arr['exam_id'],
                            'points' => 0,
                            'per' => 0,
                            'grade' => '-',
                            'comment' => $arr['comment'],
                            'is_absent' => "N.A.",
                            'sub_institute_id' => $sub_institute_id,
                        ]);
                        $data->save();
                    
                    }
                    elseif (strtoupper($arr['points']) == "EX") {
                         $data = new marks_entry([
                            'student_id' => $student_id,
                            'exam_id' => $arr['exam_id'],
                            'points' => 0,
                            'per' => 0,
                            'grade' => '-',
                            'comment' => $arr['comment'],
                            'is_absent' => "EX",
                            'sub_institute_id' => $sub_institute_id,
                        ]);
                        $data->save();
                    
                    }
                } else {
                    $arr['per'] = rtrim($arr['per'], '%');
                    $data = new marks_entry([
                        'student_id'       => $student_id,
                        'exam_id'          => $arr['exam_id'],
                        'points'           => $arr['points'],
                        'per'              => $arr['per'],
                        'grade'            => $arr['grade'],
                        'comment'          => $arr['comment'],
                        'sub_institute_id' => $sub_institute_id,
                    ]); //'is_absent' => "-",
                    $data->save();
                }
            }
        }
        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
            "class"       => "success",
        ];

        $type = $request->input('type');

        return is_mobile($type, "marks_entry.index", $res, "redirect");
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
}
