<?php

namespace App\Http\Controllers\result\cbse_result;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class WRT_report_controller extends Controller
{


    use GetsJwtToken;

    public function index(Request $request)
    {
        $data['data'] = [];
        $type = $request->input('type');

        return is_mobile($type, "result/WRT_report/search", $data, "view");
    }

    public function show_result(Request $request)
    {
        $type = $request->input('type');
        $all_student = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division']);

        $students_data = [];
        foreach ($all_student as $key => $value) {
            $students_data[$value['id']] = $value;
        }

        $syear = session()->get('syear');
        $next_year = session()->get('syear') + 1;
        $result_year = $syear."-".$next_year;

        //getting all exam master heading        
        $all_exam_master = $this->getAllExamMaster($_REQUEST['standard'], $_REQUEST['from_date'], $_REQUEST['to_date'],
            $type);

        //getting all exam marks        
        $all_WRT_data = $this->getWRTData($all_student, $_REQUEST['standard'], $type, $_REQUEST['from_date'],
            $_REQUEST['to_date']);

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

        return is_mobile($type, "result/WRT_report/WRT_show", $data, "view");
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

    public function getWRTData($all_student, $standard_id, $type, $from_date = null, $to_date = null)
    {
        if ($type == 'API') {
            $syear = $_REQUEST['syear'];
            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $term_id = 149;
            $from_date = $_REQUEST['from_date'];
            $to_date = $_REQUEST['to_date'];
        } else {
            $syear = session()->get('syear');
            $sub_institute_id = session()->get('sub_institute_id');
            $term_id = session()->get('term_id');
        }

        $student_id_arr = [];
        foreach ($all_student as $id => $arr) {
            $student_id_arr[] = $arr['student_id'];
        }
        $student_id = implode(',', $student_id_arr);

        $result = DB::table("result_create_exam as e")
            ->join('sub_std_map as s', function ($join) {
                $join->whereRaw("s.subject_id = e.subject_id AND s.sub_institute_id = e.sub_institute_id AND s.standard_id = e.standard_id");
            })
            ->leftJoin('result_marks as rm', function ($join) {
                $join->whereRaw("rm.sub_institute_id = e.sub_institute_id AND rm.exam_id = e.id");
            })
            ->selectRaw("e.title as ExamTitle, IF((e.con_point IS NULL) OR (e.con_point = ''),
                    e.points,e.con_point) AS total_points,e.subject_id,s.display_name as subject_name,
                    date_format(e.exam_date,'%d-%m-%Y') as exam_date,dayname(e.exam_date) as exam_day,rm.student_id,
                    rm.points as obtained_points,rm.is_absent")
            ->where("e.term_id", "=", $term_id)
            ->where("e.sub_institute_id", "=", $sub_institute_id)
            ->where("e.syear", "=", $syear)
            ->where("e.standard_id", "=", $standard_id)
            ->whereIn("student_id", $student_id_arr)
            ->whereBetween("e.exam_date", [$from_date, $to_date])
            ->orderBy('e.title')
            ->get()->toArray();

        $result = json_decode(json_encode($result), true);

        // getting data and making readable format student wise
        $marks_arr = [];

        foreach ($result as $id => $arr) {
            $per = (($arr['obtained_points'] * 100) / $arr['total_points']);
            $per = number_format($per, 2);
            $arr['percentage'] = $per;
            $marks_arr[$arr['student_id']][$arr['ExamTitle']][] = $arr;
        }

        return $marks_arr;
    }

    public function getAllExamMaster($standard_id, $from_date, $to_date, $type)
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
            ->where("standard_id", "=", $standard_id)
            ->where("term_id", "=", $term_id)
            ->where("syear", "=", $syear)
            ->where("r.exam_date", [$from_date, $to_date])
            ->groupBy('title')
            ->get()->toArray();

        return json_decode(json_encode($result), true);
    }

}
