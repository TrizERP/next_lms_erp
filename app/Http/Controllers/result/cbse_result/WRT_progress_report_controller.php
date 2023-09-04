<?php

namespace App\Http\Controllers\result\cbse_result;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class WRT_progress_report_controller extends Controller
{

    use GetsJwtToken;

    public function index(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $exam_master_sql = DB::table("result_exam_master as r")
            ->where("r.SubInstituteId", "=", $sub_institute_id)
            ->get()->toArray();

        $exam_master_data = json_decode(json_encode($exam_master_sql), true);

        $data['exam_master'] = $exam_master_data;
        $data['data'] = [];

        $type = $request->input('type');

        return is_mobile($type, "result/WRT_progress_report/search", $data, "view");
    }

    public function show_result(Request $request)
    {

        $type = $request->input('type');
        $exam_type = $request->input('exam_type');
        $all_student = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);

        $students_data = [];
        foreach ($all_student as $key => $value) {
            $students_data[$value['id']] = $value;
        }

        $syear = session()->get('syear');
        $next_year = session()->get('syear') + 1;
        $result_year = $syear."-".$next_year;

        //getting all exam master heading
        $all_exam_master = $this->getAllExamMaster($_REQUEST['standard'], $_REQUEST['from_date'], $_REQUEST['to_date'], $type, $_REQUEST['exam_type']);

        //getting all exam marks
        $all_WRT_data = $this->getWRTData($all_student, $_REQUEST['standard'], $type, $exam_type,
            $_REQUEST['from_date'], $_REQUEST['to_date']);

        //getting result header
        $header_data = $this->getHeader($_REQUEST['standard'], $type);

        $data['WRT_data'] = $all_WRT_data;
        $data['WRT_exam_master'] = $all_exam_master;

        $data['all_student'] = $students_data;
        $data['result_year'] = $result_year;
        $data['header_data'] = $header_data;
        $data['standard_id'] = $_REQUEST['standard'];
        $data['grade_id'] = $_REQUEST['grade'];
        $data['division_id'] = $_REQUEST['division'];
        $data['syear'] = session()->get('syear');
        $data['term_id'] = session()->get('term_id');

        return is_mobile($type, "result/WRT_progress_report/WRT_show", $data, "view");
    }

    public function getHeader($standard_id, $type)
    {
        if ($type == 'API') {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
        } else {
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $term_id = session()->get('term_id');
        }

        $result = DB::table("result_book_master as b")
            ->join('result_trust_master as t', function ($join) {
                $join->whereRaw("b.trust_id = t.id");
            })
            ->where("b.standard", "=", $standard_id)
            ->where("b.sub_institute_id", "=", $sub_institute_id)
            ->limit(1)
            ->get()->toArray();

        $result = json_decode(json_encode($result), true);

        return $result[0] ?? [];
    }

    public function getWRTData($all_student, $standard_id, $type, $exam_type = null, $from_date = null, $to_date = null)
    {
        if ($type == 'API') {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
            $standard_id = $all_student[0]['standard_id'];
            $division_id = $all_student[0]['division_id'];
            $from_date = $_REQUEST['from_date'];
            $to_date = $_REQUEST['to_date'];
            $exam_type = isset($_REQUEST['exam_type']) ? $_REQUEST['exam_type'] : null;
        } else {
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $term_id = session()->get('term_id');
            $standard_id = request()->input('standard');
            $division_id = request()->input('division');
        }

        $student_id_arr = [];
        foreach ($all_student as $id => $arr) {
            $student_id_arr[] = $arr['student_id'];
        }

        $result = DB::table("result_create_exam as e")
            ->join('sub_std_map as s', function ($join) {
                $join->whereRaw("s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id");
            })
            ->leftJoin('result_marks as rm', function ($join) {
                $join->whereRaw("rm.sub_institute_id = e.sub_institute_id AND rm.exam_id = e.id");
            })
            ->selectRaw("e.title as ExamTitle, IF((e.con_point IS NULL) OR (e.con_point = ''),e.points,e.con_point) AS total_points,
    e.subject_id,s.display_name as subject_name,date_format(e.exam_date,'%d-%m-%Y') as exam_date,
    dayname(e.exam_date) as exam_day,rm.student_id,rm.points as obtained_points,rm.is_absent")
            ->where("e.sub_institute_id", "=", $sub_institute_id)
            ->where("e.syear", "=", $syear)
            ->where("e.standard_id", "=", $standard_id)
            ->whereIn("student_id", $student_id_arr)
            ->whereBetween("e.exam_date", [$from_date, $to_date]);

        if ($exam_type != '') {
            $result = $result->where('e.exam_id', $exam_type);
        }

        $result = $result->orderBy('e.exam_date', 'ASC')
            ->get()->toarray();

        $result = json_decode(json_encode($result), true);

        $cbse_1t5_result_controller = new cbse_1t5_result_controller;
        $grade_arr = $cbse_1t5_result_controller->getGradeScale($standard_id, $type);

        // getting data and making readable format student wise
        $marks_arr = [];

        $rank = $this->getRank($standard_id, $division_id, $passing_ratio = 35, $type, $from_date, $to_date);
        foreach ($result as $id => $arr) {
            $grade_scale = $cbse_1t5_result_controller->getGrade($grade_arr, $arr['total_points'],
                $arr['obtained_points']);

            $per = (($arr['obtained_points'] * 100) / $arr['total_points']);
            $per = number_format($per, 2);
            $arr['percentage'] = $per;
            $arr['grade'] = $grade_scale;
            $arr['rank'] = $rank[$arr['student_id']];
            $marks_arr[$arr['student_id']][] = $arr;
        }
        $marks_arr['total_student'] = count($marks_arr);

        return $marks_arr;
    }

    public function getAllExamMaster($standard_id, $from_date, $to_date, $type, $exam_type=null)
    {
        if ($type == 'API') {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
        } else {
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $term_id = session()->get('term_id');
        }

        $result = DB::table("result_create_exam as r")
            ->selectRaw('*,title as ExamTitle')
            ->where("r.sub_institute_id", "=", $sub_institute_id)
            ->where("r.standard_id", "=", $standard_id)
            ->where("r.syear", "=", $syear)
            ->where("r.exam_id", "=", $exam_type)
            ->whereBetween("r.exam_date", [$from_date, $to_date])
            ->groupBy('title')
            ->get()->toArray();
        
        $result = json_decode(json_encode($result), true);

        return $result;
    }

    public static function getRank(
        $standard_id,
        $division_id,
        $passing_ratio,
        $type,
        $from_date = null,
        $to_date = null
    ) {
        if ($type == 'API') {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
        } else {
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $term_id = session()->get('term_id');
        }

        $rank_data = DB::table("tblstudent as s")
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->whereRaw("se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id");
            })
            ->join('result_marks as rm', function ($join) {
                $join->whereRaw("rm.student_id = s.id AND rm.sub_institute_id = s.sub_institute_id");
            })
            ->join('result_create_exam as rc', function ($join) use ($syear) {
                $join->whereRaw("rc.id = rm.exam_id AND rc.sub_institute_id = rm.sub_institute_id AND rc.standard_id = se.standard_id AND rc.syear = '".$syear."'");
            })
            ->selectRaw('s.id AS student_id,SUM(IFNULL(rm.points,0)) AS obtainedMarks,
                            SUM(IFNULL(rc.points,0)) AS totalMarks, ((SUM(IFNULL(rm.points,0))/ SUM(IFNULL(rc.points,0)))*100) AS percentage,
                            COUNT(if(((IFNULL(rm.points,0)/rc.points)*100) < " . $passing_ratio . ",1, NULL)) AS failed')
            ->where("se.syear", "=", $syear)
            ->where("se.standard_id", "=", $standard_id)
            ->where("se.section_id", "=", $division_id)
            ->whereNull("se.end_date")
            ->where("s.sub_institute_id", "=", $sub_institute_id);

        if (isset($from_date) && $from_date != '' && isset($to_date) && $to_date != '') {
            $rank_data = $rank_data->whereRaw("DATE_FORMAT(rc.exam_date, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."'");
        }

        $rank_data = $rank_data->groupBy("s.id")
            ->orderBy('percentage', 'DESC')->orderBy('roll_no', 'ASC') //26-08-2023 By Rajesh Rafaliya - Rank display wrong as per 
            ->get()->toArray();

        $rank_data = json_decode(json_encode($rank_data), true);

        $percentageArr = [];
        $i = 1;
        foreach ($rank_data as $key => $val) {
            if (! isset($percentageArr[$val['percentage']])) {
                $percentageArr[$val['percentage']] = $i;
                $i++;
            }
        }
        $rankArr = [];
        foreach ($rank_data as $key => $val) {
            $rankArr[$val['student_id']] = $percentageArr[$val['percentage']];
        }

        return $rankArr;
    }

}
