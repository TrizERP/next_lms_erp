<?php

namespace App\Http\Controllers\result\cbse_result;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class cbse_11_t2_result_controller extends Controller
{

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
        }

        $data['data'] = [];
        $type = $request->input('type');

        return is_mobile($type, "result/cbse_11_result/search", $data, "view");
    }

    public function show_result(Request $request)
    {

        $term = session()->get('term_id');

        $all_student = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);
        $responce_arr = [];

        $next_term = session()->get('term_id') + 1;
        $my_array = [
            'year' => session()->get('syear'),
        ];

        $syear = $my_array['year'];
        $next_year = $syear + 1;
        $result_year = $syear . "-" . $next_year;
        session()->put('standard', $_REQUEST['standard']);

        //getting year detail
        //getting all exam name with mark
        $all_exam = $this->getAllExam($_REQUEST['standard']);

        $all_subject_wise_exam = $this->getSubjectWiseAllExam();

        //getting all subject name
        $all_subject = $this->getAllSubject($_REQUEST['standard']);

        //getting all mark
        $all_subject_mark = $this->getAllMark($all_exam, $all_subject, $all_student);

        //getting Co Scholastic
        $all_co_data = $this->getCoArea($all_student);

        //getting attendance
        $all_att_data = $this->getAttendance($all_student);

        //getting scholastic grade range
        $all_grd_data = $this->getGradeRange();

        //getting currunt term name
        $term_name = $this->getTermName();


        //getting heading
        $header_data = $this->getHeader($_REQUEST['standard']);

        //get exam master settigs
        $footer_data = $this->getExamMasterSettigs($_REQUEST['standard']);


        //getting all student detail
        foreach ($all_student as $id => $arr) {
            $cur_student_id = $arr['student_id'];
            $responce_arr[$cur_student_id]['year'] = $result_year;
            // $responce_arr[$cur_student_id]['term'] = $term_name;
            // $responce_arr[$cur_student_id]['total_mark'] = $all_exam[count($all_exam) - 1]['mark'];
            $responce_arr[$cur_student_id]['term-1'] = $term;
            $responce_arr[$cur_student_id]['term-2'] = $next_term;
            $responce_arr[$cur_student_id]['total_mark'] = 100;
            $responce_arr[$cur_student_id]['name'] = $arr['first_name'] . " " . $arr['middle_name'] . " " . $arr['last_name'];
            $responce_arr[$cur_student_id]['roll_no'] = $arr['roll_no'];
            $responce_arr[$cur_student_id]['mother_name'] = $arr['mother_name'];
            $responce_arr[$cur_student_id]['class'] = $arr['standard_name'];
            $responce_arr[$cur_student_id]['medium'] = $arr['medium'];
            $responce_arr[$cur_student_id]['father_name'] = $arr['father_name'];
            $responce_arr[$cur_student_id]['division'] = $arr['division_name'];
            $responce_arr[$cur_student_id]['date_of_birth'] = date("d-m-Y", strtotime($arr['dob']));
            $responce_arr[$cur_student_id]['gr_no'] = $arr['enrollment_no'];
            $responce_arr[$cur_student_id]['image'] = $arr['image'];
            $responce_arr[$cur_student_id]['exam'] = isset($all_exam) ? $all_exam : '';
            $responce_arr[$cur_student_id]['exam_subject_wise'] = isset($all_subject_wise_exam) ? $all_subject_wise_exam : '';
            $responce_arr[$cur_student_id]['mark'] = isset($all_subject_mark[$cur_student_id]) ? $all_subject_mark[$cur_student_id] : '';
            $responce_arr[$cur_student_id]['per'] = $this->getPer($responce_arr[$cur_student_id]['total_mark'], $all_subject_mark[$cur_student_id]);
            $responce_arr[$cur_student_id]['final_grade'] = $this->getFinalGrade($responce_arr[$cur_student_id]['per']);
            if (isset($all_co_data[$cur_student_id])) {
                $responce_arr[$cur_student_id]['co_scholastic_area'] = $all_co_data[$cur_student_id];
            } else {
                $responce_arr[$cur_student_id]['co_scholastic_area'] = array();
            }
            $responce_arr[$cur_student_id]['att'] = '';
            $responce_arr[$cur_student_id]['total_working_day'] = '';
            $responce_arr[$cur_student_id]['teacher_remark'] = '';
            if (isset($all_att_data[$cur_student_id])) {
                $responce_arr[$cur_student_id]['att'] = $all_att_data[$cur_student_id]['attendance'];
                $responce_arr[$cur_student_id]['total_working_day'] = $all_att_data[$cur_student_id]['total_working_day'];
                $responce_arr[$cur_student_id]['teacher_remark'] = $all_att_data[$cur_student_id]['teacher_remark'];
            }
            // $responce_arr[$cur_student_id]['grade_range'] = $all_grd_data;
        }

        $data['data'] = $responce_arr;
        $data['header_data'] = $header_data;
        $data['footer_data'] = $footer_data;
        $type = $request->input('type');

        return is_mobile($type, "result/cbse_11_result/11_t2_show", $data, "view");
    }

    public function getAllExam($standard_id)
    {
        $result = DB::table("result_create_exam as e")
            ->join('result_exam_master as em', function ($join) {
                $join->whereRaw("em.Id = e.exam_id");
            })
            ->join('academic_year as ay', function ($join) {
                $join->whereRaw("ay.term_id = e.term_id");
            })
            ->selectRaw('em.ExamTitle,e.title as exam_title, IF((e.con_point IS NULL) OR (e.con_point = ""),e.points,e.con_point) AS points,em.Id,e.term_id,ay.title')
            ->where("e.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->where("e.syear", "=", session()->get('syear'))
            ->where("e.standard_id", "=", $standard_id)
            ->where("ay.syear", "=", session()->get('syear'))
            ->where("ay.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->groupByRaw('em.ExamTitle,e.term_id')
            ->orderByRaw('e.term_id,CAST(em.SortOrder AS UNSIGNED)')
            ->get()->toarray();

        $result = $this->objToArr($result);

        $responce = [];
        $total_mark = [];
        $ids = 0;

        foreach ($result as $id => $obj) {
            if (! isset($total_mark[$obj["term_id"]])) {
                $total_mark[$obj["term_id"]] = 0;
                $ids = 0;
            }
            $responce[$obj["term_id"]][$ids]['exam_id'] = $obj["Id"];
            $responce[$obj["term_id"]][$ids]['exam_title'] = $obj["exam_title"];
            $responce[$obj["term_id"]][$ids]['exam'] = $obj["ExamTitle"];
            $responce[$obj["term_id"]][$ids]['mark'] = $obj["points"];
            $responce[$obj["term_id"]][$ids]['term_id'] = $obj["term_id"];
            $responce[$obj["term_id"]][$ids]['term_name'] = $obj["title"];

            $total_mark[$obj["term_id"]] += $obj["points"];
            ++$ids;
        }

        foreach ($responce as $term_id => $arr) {
            $responce[$term_id][count($arr)]["exam"] = "Marks Obtained";
            $responce[$term_id][count($arr)]["mark"] = $total_mark[$term_id];
        }

        return $responce;
    }

    public function getSubjectWiseAllExam()
    {
        $result = DB::table("result_create_exam as e")
            ->join('result_exam_master as em', function ($join) {
                $join->whereRaw("em.Id = e.exam_id");
            })
            ->join('academic_year as ay', function ($join) {
                $join->whereRaw("ay.term_id = e.term_id");
            })
            ->join('sub_std_map as ssm', function ($join) {
                $join->whereRaw("ssm.subject_id = e.subject_id");
            })
            ->selectRaw('em.ExamTitle, IF((e.con_point IS NULL) OR (e.con_point = ""),e.points,e.con_point) AS points,
    em.Id,e.term_id,ay.title,ssm.display_name')
            ->where("e.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->where("e.syear", "=", session()->get('syear'))
            ->where("ay.syear", "=", session()->get('syear'))
            ->where("ay.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->groupByRaw('em.ExamTitle,e.term_id,e.subject_id')
            ->orderBy('e.term_id,CAST(em.SortOrder AS UNSIGNED)')
            ->get()->toarray();

        $result = $this->objToArr($result);

        $responce = [];
        $total_mark = [];
        $ids = 0;

        foreach ($result as $id => $obj) {
            if (! isset($total_mark[$obj["term_id"]])) {
                $total_mark[$obj["term_id"]] = 0;
                $ids = 0;
            }
            $responce[$obj["display_name"]][$obj["term_id"]][$ids]['exam_id'] = $obj["Id"];
            $responce[$obj["display_name"]][$obj["term_id"]][$ids]['exam'] = $obj["ExamTitle"];
            $responce[$obj["display_name"]][$obj["term_id"]][$ids]['mark'] = $obj["points"];
            $responce[$obj["display_name"]][$obj["term_id"]][$ids]['term_id'] = $obj["term_id"];
            $responce[$obj["display_name"]][$obj["term_id"]][$ids]['term_name'] = $obj["title"];

            $total_mark[$obj["term_id"]] += $obj["points"];
            ++$ids;
        }

        return $responce;
    }

    public function getAllSubject($std)
    {
        $result = DB::table("sub_std_map as ssm")
            ->join('standard as s', function ($join) {
                $join->whereRaw("s.id = ssm.standard_id");
            })
            ->selectRaw('ssm.display_name,ssm.subject_id,ssm.elective_subject')
            ->where("ssm.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->where("ssm.standard_id", "=", $std)
            ->where("ssm.allow_grades", "=", 'Yes')
            ->orderBy('ssm.sort_order')
            ->get()->toArray();

        $responce = [];
        foreach ($result as $id => $obj) {
//            $responce[] = $obj->display_name;
            $responce[$obj->subject_id] = $obj->display_name . '####' . $obj->elective_subject;
        }

        return $responce;
    }

    public function getAllMark($all_exam, $all_subject, $all_student)
    {
        $exam_id_arr = [];
        foreach ($all_exam as $term_id => $data_arr) {
            foreach ($data_arr as $id => $arr) {
                if ($id != count($data_arr) - 1) {
                    $exam_id_arr[] = $arr['exam_id'];
                }
            }
        }

        $exam_id = implode(',', $exam_id_arr);

        $student_id_arr = [];
        foreach ($all_student as $id => $arr) {
            $student_id_arr[] = $arr['student_id'];
        }

        $student_id = implode(',', $student_id_arr);

        $result = DB::table("result_marks as rm")
            ->join('result_create_exam as ex', function ($join) {
                $join->whereRaw("ex.id = rm.exam_id");
            })
            ->join('sub_std_map s', function ($join) {
                $join->whereRaw("s.subject_id = ex.subject_id AND s.standard_id = ex.standard_id");
            })
            ->selectRaw('ex.id,rm.student_id,s.subject_id,s.display_name,s.elective_subject,ex.title as exam_title,
                ex.points AS total_points, ex.con_point, rm.points AS points,rm.exam_id AS exam_id,ex.term_id,rm.is_absent')
            ->where("ex.syear", session()->get('syear'))
            ->whereNotNull('rm.points')
            ->whereIn("rm.student_id", $student_id_arr)
            ->groupByRaw('rm.student_id,s.display_name,ex.points,ex.title')
            ->orderByRaw('rm.student_id,s.display_name')
            ->get()->toarray();

        $marks_arr = array();
        foreach ($result as $id => $arr) {
            $temp_arr['id'] = $arr->id;
            $temp_arr['student_id'] = $arr->student_id;
            $temp_arr['subject_name'] = $arr->display_name;
            $temp_arr['exam_title'] = $arr->exam_title;
            $temp_arr['total_points'] = $arr->total_points;
            $temp_arr['con_point'] = $arr->con_point;
            $temp_arr['points'] = $arr->points;
            $temp_arr['exam_id'] = $arr->exam_id;
            $temp_arr['is_absent'] = $arr->is_absent;

            if ($arr->elective_subject == 'Yes') {
                $check_optional_subject_with_student = DB::select("SELECT * FROM student_optional_subject WHERE student_id = '" . $arr->student_id . "' AND subject_id = '" . $arr->subject_id . "' AND syear = '" . session()->get('syear') . "' ");

                if ((count($check_optional_subject_with_student) > 0)) {
                    $marks_arr[$arr->student_id][$arr->display_name][$arr->term_id][$arr->exam_title] = $temp_arr;
                }
            } else {
                $marks_arr[$arr->student_id][$arr->display_name][$arr->term_id][$arr->exam_title] = $temp_arr;
            }

        }
        // echo "<pre>";
        // print_r($temp_arr);
        // exit;
        //getting grade scale data
        $grade_arr = $this->getGradeScale();

        $responce_arr = array();
        foreach ($all_student as $students => $arr_student) {
            foreach ($all_subject as $subject_id => $subject) {
                $total_mark = 0;
                $subject_arr = explode("####", $subject);
                $subject = $subject_arr[0];
                $subject_elective = $subject_arr[1];
                $total_gain_mark = $total_con_point = 0;

                foreach ($all_exam as $term_id => $data_arr) {

                    $term_vise_gain_mark = 0;
                    foreach ($data_arr as $exam_id => $exam_detail) {
                        $abFlag = $naFlag = $exFlag = 0;

                        // last exam have total mark so calculate before it
                        if (count($data_arr) - 1 != $exam_id) {
                            $mark = 0;
                            // $total_mark = 0;
                            $con_point = 0;
                            $ab = false;

                            if (isset($marks_arr[$arr_student['student_id']][$subject][$term_id][$exam_detail['exam_title']])) {

                                $is_absent = $marks_arr[$arr_student['student_id']][$subject][$term_id][$exam_detail['exam_title']]['is_absent'];

                                if (isset($is_absent) && $is_absent == "N.A.") {
                                    $naFlag = 1;
                                    $total_mark = $marks_arr[$arr_student['student_id']][$subject][$term_id][$exam_detail['exam_title']]['total_points'];
                                    //continue;
                                } elseif (isset($is_absent) && $is_absent == "EX") {
                                    $exFlag = 1;
                                    $total_mark = $marks_arr[$arr_student['student_id']][$subject][$term_id][$exam_detail['exam_title']]['total_points'];

                                    //continue;
                                } elseif (isset($is_absent) && $is_absent == "AB") {
                                    $abFlag = 1;
                                    $mark = $marks_arr[$arr_student['student_id']][$subject][$term_id][$exam_detail['exam_title']]['points'];

                                    if ($con_point != NULL && $con_point != $total_mark) {
                                        $mark = ($con_point * $mark) / $total_mark;
                                    }
                                    $total_mark = $marks_arr[$arr_student['student_id']][$subject][$term_id][$exam_detail['exam_title']]['total_points'];

                                    $total_gain_mark = $total_gain_mark + $mark;
                                    $total_con_point = $total_con_point + $con_point;

                                } else {
                                    $mark = $marks_arr[$arr_student['student_id']][$subject][$term_id][$exam_detail['exam_title']]['points'];

                                    $total_mark = $marks_arr[$arr_student['student_id']][$subject][$term_id][$exam_detail['exam_title']]['total_points'];

                                    if ($con_point != NULL && $con_point != $total_mark) {
                                        $mark = ($con_point * $mark) / $total_mark;
                                    }
                                    $total_gain_mark = $total_gain_mark + $mark;
                                    $total_con_point = $total_con_point + $con_point;

                                }
                                $con_point = $marks_arr[$arr_student['student_id']][$subject][$term_id][$exam_detail['exam_title']]['con_point'];

                            }

                            if ($abFlag == 1) {
                                $e_points = "AB";
                            } elseif ($naFlag == 1) {
                                $e_points = "N.A.";
                            } elseif ($exFlag == 1) {
                                $e_points = "EX";
                            } else {
                                $e_points = number_format($mark, 0);
                            }

                            $responce_arr[$arr_student['student_id']][$subject][$term_id][$exam_detail['exam_title']] = $e_points;
                            $responce_arr[$arr_student['student_id']][$subject]['total_points'][$term_id][$exam_detail['exam_title']] = $total_mark;

                        } else {
                            $total_mark = $exam_detail['mark'];
                        }
                    }
                    if ($subject_elective == 'Yes') {
                        $check_optional_subject_with_student = DB::select("SELECT * FROM student_optional_subject
                        WHERE student_id = '" . $arr_student['student_id'] . "'
                        AND subject_id = '" . $subject_id . "' AND syear = '" . session()->get('syear') . "' ");

                        if ((count($check_optional_subject_with_student) == 0)) {
                            unset($responce_arr[$arr_student['student_id']][$subject]);
                        } else {
                            $responce_arr[$arr_student['student_id']][$subject]['TOTAL_GAIN'] = $total_gain_mark;
                            $responce_arr[$arr_student['student_id']][$subject]['GRADE'] = $this->getGrade($grade_arr, $total_mark, $total_gain_mark);
                        }
                    } else {
                        //echo "totoa-".$total_con_point."<br/>";die();
                        $responce_arr[$arr_student['student_id']][$subject]['TOTAL_GAIN'] = $total_gain_mark;
                        $responce_arr[$arr_student['student_id']][$subject]['GRADE'] = $this->getGrade($grade_arr, $total_mark, $total_gain_mark);
                    }

                }
            }
        }

        return $responce_arr;
    }

    public static function getGradeScale($standard_id = '', $type = '')
    {
        if ($type == 'API') {
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $standard_id = $standard_id;
        } else {
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
            $standard_id = $_REQUEST['standard'];
        }


        $ret_grade = DB::table("result_std_grd_maping as sgm")
            ->join('grade_master_data as dt', function ($join) {
                $join->whereRaw("dt.grade_id = sgm.grade_scale AND dt.syear = " . $syear . "");
            })
            ->where("sgm.standard", "=", $standard_id)
            ->where("sgm.sub_institute_id", "=", $sub_institute_id)
            ->borderBy('dt.breakoff', 'DESC')
            ->get()->toArray();

        //converting it into array
        $grade_arr = [];
        foreach ($ret_grade as $id => $arr) {
            $grade_arr[$id]['id'] = $arr->id;
            $grade_arr[$id]['grade_id'] = $arr->grade_id;
            $grade_arr[$id]['title'] = $arr->title;
            $grade_arr[$id]['breakoff'] = $arr->breakoff;
            $grade_arr[$id]['gp'] = $arr->gp;
            $grade_arr[$id]['sort_order'] = $arr->sort_order;
            $grade_arr[$id]['comment'] = $arr->comment;
            $grade_arr[$id]['sub_institute_id'] = $arr->sub_institute_id;
            $grade_arr[$id]['created_at'] = $arr->created_at;
            $grade_arr[$id]['updated_at'] = $arr->updated_at;
        }

        return $grade_arr;
    }

    public function getGrade($grade_arr, $total_mark, $total_gain_mark)
    {
        $per = 0;
        if ($total_mark != 0) {
            $per = (100 * $total_gain_mark) / $total_mark;
        }
        foreach ($grade_arr as $id => $data) {
            if (!isset($grade)) {
                if ($per >= $data['breakoff']) {
                    $grade = $data['title'];
                }
            }
        }

        if (!isset($grade)) {
            $grade = "-";
        }

        return $grade;
    }

    public function getCoArea($all_student)
    {
        $responce_arr = [];

        $ret_mark_grade = DB::table("result_co_scholastic")
            ->where("sub_institute_id", "=", session()->get('sub_institute_id'))
            ->where("term_id", "=", session()->get('term_id'))
            ->get()->toArray();


        if (count($ret_mark_grade) > 0) {
            $type = $ret_mark_grade[0]->mark_type;
            if ($type == "GRADE") {
                $ret_data = DB::table("result_co_scholastic_marks_entries as comark")
                    ->join('result_co_scholastic_grades as cograde', function ($join) {
                        $join->whereRaw("cograde.id = comark.grade");
                    })
                    ->join('result_co_scholastic as co', function ($join) {
                        $join->whereRaw("co.id = comark.co_scholastic_id");
                    })
                    ->join('result_co_scholastic_parent as cop', function ($join) {
                        $join->whereRaw("cop.id = co.parent_id");
                    })
                    ->selectRaw('comark.student_id,comark.co_scholastic_id, cop.title parent_title,co.title child_title,
    cograde.title obtain_grade,comark.term_id')
                    ->where("comark.syear", "=", session()->get('syear'))
                    ->where("comark.standard_id", "=", $_REQUEST['standard'])
                    ->where("comark.sub_institute_id", "=", session()->get('sub_institute_id'))
                    ->orderByRaw('comark.student_id,cop.sort_order,co.sort_order,comark.term_id')
                    ->get()->toarray();

                // converting data in array
                $data_arr = [];
                foreach ($ret_data as $id => $arr) {
                    $data_arr[$id]['student_id'] = $arr->student_id;
                    $data_arr[$id]['co_scholastic_id'] = $arr->co_scholastic_id;
                    $data_arr[$id]['parent_title'] = $arr->parent_title;
                    $data_arr[$id]['child_title'] = $arr->child_title;
                    $data_arr[$id]['obtain_grade'] = $arr->obtain_grade;
                    $data_arr[$id]['term_id'] = $arr->term_id;
                }

                foreach ($data_arr as $id => $arr) {
                    $responce_arr[$arr['student_id']]['co_area'][$arr['parent_title']][$arr['child_title']][$arr['term_id']] = $arr['obtain_grade'];
                }
            }
        }

        return $responce_arr;
    }

    public function getAttendance($all_student)
    {
        $ret_data = DB::table("result_student_attendance_master as atd")
            ->join('result_working_day_master as wrkd', function ($join) {
                $join->whereRaw("wrkd.standard = atd.standard and wrkd.sub_institute_id = atd.sub_institute_id");
            })
            ->selectRaw('atd.student_id,wrkd.total_working_day,atd.attendance,atd.teacher_remark')
            ->where("atd.standard", "=", $_REQUEST['standard'])
            ->where("atd.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->where("atd.syear", "=", session()->get('syear'))
            ->where("atd.term_id", "=", session()->get('term_id'))
            ->get()->toArray();

        $data_arr = [];
        foreach ($ret_data as $id => $arr) {
            $data_arr[$arr->student_id]['attendance'] = $arr->attendance ?? '0';
            $data_arr[$arr->student_id]['total_working_day'] = $arr->total_working_day ?? '0';
            $data_arr[$arr->student_id]['teacher_remark'] = $arr->teacher_remark ?? '-';
        }

        return $data_arr;
    }

    public function objToArr($result)
    {
        foreach ($result as $object) {
            $arrays[] = (array) $object;
        }

        return $arrays;
    }

    public function getGradeRange()
    {
        $grade_arr = $this->getGradeScale();

        $responce_arr = [];
        foreach ($grade_arr as $id => $arr) {
            if (! isset($last_breckoff)) {
                $last_breckoff = "100";
            }
            $responce_arr['mark_range']['SCHOLASTIC MARKS RANGE'][] = $arr['breakoff']."-".$last_breckoff;
            $responce_arr['mark_range']['GRADE'][] = $arr['title'];
            $last_breckoff = $arr['breakoff'] - 1;
        }

        return $responce_arr;
    }

    public function getTermName()
    {
        $result = DB::table("academic_year")
            ->where("term_id", "=", session()->get('term_id'))
            ->where("sub_institute_id", "=", session()->get('sub_institute_id'))
            ->get()->toArray();


        foreach ($result as $id => $obj) {
            $responce = $obj->title;
        }

        return $responce;
    }

    public function getPer($total_mark, $all_gain_mark)
    {
        $total_subject_mark = 0;
        $total_gain_mark = 0;
        foreach ($all_gain_mark as $id => $arr) {
            $total_subject_mark += $total_mark;
            $total_gain_mark += $arr['TOTAL_GAIN'];
        }
        if ($total_subject_mark == 0) {
            return 0;
        }

        return (100 * $total_gain_mark) / $total_subject_mark;
    }

    public function getFinalGrade($per)
    {
        $grade_arr = $this->getGradeScale();
        foreach ($grade_arr as $id => $data) {
            if (! isset($grade)) {
                if ($per >= $data['breakoff']) {
                    $grade = $data['title'];
                }
            }
        }
        if (! isset($grade)) {
            $grade = "-";
        }

        return $grade;
    }

    public function getHeadings()
    {
        $result = DB::table("result_book_master as rm")
            ->join('result_trust_master as rt', function ($join) {
                $join->whereRaw("rt.id = rm.trust_id");
            })
            ->selectRaw('rt.*')
            ->where("rm.standard", "=", $_REQUEST['standard'])
            ->where("rm.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->get()->toArray();

        $responce = [];
        foreach ($result as $id => $obj) {
            $responce['line1'] = $obj->line1;
            $responce['line2'] = $obj->line2;
            $responce['line3'] = $obj->line3;
            $responce['line4'] = $obj->line4;
        }

        return $responce;
    }

    public function getHeader($standard_id)
    {
        $str = "SELECT * from result_book_master b
                INNER JOIN result_trust_master t on b.trust_id = t.id
                WHERE b.standard = '" . $standard_id . "' AND b.sub_institute_id = '" . session()->get('sub_institute_id') . "'
                LIMIT 1";
        $result = DB::select(DB::raw($str));
        $result = json_decode(json_encode($result), true);

        return $result[0];
    }

    public function getExamMasterSettigs()
    {
        $result = DB::table("result_master_confrigration as rm")
            ->selectRaw('rm.*')
            ->where("rm.standard_id", "=", $_REQUEST['standard'])
            ->where("rm.sub_institute_id", "=", session()->get('sub_institute_id'))
            ->get()->toArray();

        $responce = [];
        foreach ($result as $id => $obj) {
            $responce['teacher_sign'] = $obj->teacher_sign;
            $responce['principal_sign'] = $obj->principal_sign;
            $responce['director_signatiure'] = $obj->director_signatiure;
            $responce['reopen_date'] = $obj->reopen_date;
        }

        return $responce;
    }
}
