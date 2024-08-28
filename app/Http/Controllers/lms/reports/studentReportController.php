<?php

namespace App\Http\Controllers\lms\reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\getStudents;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class studentReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request){
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        return is_mobile($type,'lms/reports/show_student_report',$res,"view");
    }

    public function create(Request $request){
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $subject = $request->input('subject');
        $type = $request->input('type');
        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        $data = SearchStudent($grade, $standard, $division, $sub_institute_id, $syear);

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $data;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;

        return is_mobile($type, "lms/reports/show_student_report", $res, "view");
    }

    public function edit(Request $request,$id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        /* START Get Student Details */
        $student_id_arr = [
            0 => $id,
        ];
        $student_data = getStudents($student_id_arr);
        $student_data = $student_data[$id];
        /* END Get Student Details */

        /* START Get All Subject Details */
        $standard_id = $student_data['standard_id'];

        $result = DB::table('sub_std_map')->where('standard_id', $standard_id)
            ->where('sub_institute_id', $sub_institute_id)->get()->toArray();
        $all_subject_arr = json_decode(json_encode($result), true);

        if ($request->has('subject_id')) {
            $current_subject = $request->get('subject_id');
        } else {
            $current_subject = $all_subject_arr[0]['subject_id'];
        }

        /* END Get All Subject Details */

        /* START Get All Attempted Paper of Selected Suject */
        $exam_result = DB::table('question_paper as q')
            ->leftJoin('lms_online_exam as e', function ($join) use ($id) {
                $join->whereRaw("e.question_paper_id = q.id and e.student_id = '".$id."'");
            })->selectRaw("q.id as paper_id,q.paper_name,(sum(e.obtain_marks)/sum(q.total_marks)*100) as obtained_percentage,
                sum(e.obtain_marks) as obtained_marks,sum(q.total_marks) as total_marks,group_concat(e.obtain_marks) as all_marks,
                group_concat(e.id) as online_exam_ids")
            ->where('q.sub_institute_id', $sub_institute_id)
            ->where('q.syear', $syear)
            ->where('q.subject_id', $current_subject)
            ->groupBy('q.id')->get()->toArray();


        $exam_arr = json_decode(json_encode($exam_result), true);
        $grand_total = $grand_obtained = 0;
        $linechart_data = "";
        if (count($exam_arr) > 0) {
            foreach ($exam_arr as $key => $val) {
                $linechart_data .= "{";
                $linechart_data .= "name:'".$val['paper_name']."',";
                $all_marks = explode(",", $val['all_marks']);
                $linechart_data .= "data:[";
                $mdata = "";
                foreach($all_marks as $k => $v) {
                    $mdata .= $v.",";
                }
                $linechart_data .= rtrim($mdata, ',');
                $linechart_data .= "]";
                $linechart_data .= "},";

                $grand_total += $val['total_marks'];
                $grand_obtained += $val['obtained_marks'];
            }
        }
        /* END Get All Attempted Paper of Selected Suject */

        /* START Get Cumulative LO/LI percentage */
        /*$lo_arr = array();
        if (count($exam_arr) > 0) {
            foreach ($exam_arr as $key => $val) {
                if ($val['online_exam_ids'] != "") {
                    $lo_sql = "SELECT *,'100' AS total_percentage, ifnull(ROUND(((a.right_answer*100)/total_question),2),0) AS obtained_percentage
                    FROM (
                        SELECT lt.id,lt.name, COUNT(mapping_type_id) AS total_question, GROUP_CONCAT(e.question_id) AS ques_list,
                        SUM((CASE WHEN e.ans_status = 'right' THEN '1' END)) AS right_answer
                        FROM lms_question_mapping l
                        INNER JOIN lms_mapping_type lt ON lt.id = l.mapping_value_id
                        LEFT JOIN lms_online_exam_answer e ON e.question_id = l.questionmaster_id AND e.question_paper_id = '".$val['paper_id']."' AND
                         e.student_id = '".$id."' AND e.online_exam_id in (".$val['online_exam_ids'].")
                        WHERE questionmaster_id IN
                        (
                            SELECT question_id
                            FROM lms_online_exam_answer
                            WHERE question_paper_id = '".$val['paper_id']."' AND student_id = '".$id."' AND online_exam_id in (".$val['online_exam_ids'].")
                        )
                        GROUP BY mapping_value_id
                        ORDER BY mapping_type_id,mapping_value_id
                    ) AS a";

                    $lo_result = DB::select($lo_sql);
                    $arr = json_decode(json_encode($lo_result),true);
                    foreach ($arr as $k => $v) {
                        $lo_data[$v['name']] = $v['obtained_percentage'];
                    }
                    $lo_arr[$val['paper_name']] = $lo_data;
                }
            }
        }*/

        $lo_arr = [];

        if (count($exam_arr) > 0) {
            foreach ($exam_arr as $key => $val) {
                if ($val['online_exam_ids'] != "") {
                    $lo_result = DB::table('lms_question_mapping as l')
                        ->selectRaw("lt.id, lt.name, COUNT(mapping_type_id) AS total_question,
                    GROUP_CONCAT(e.question_id) AS ques_list,
                    IFNULL(ROUND(((SUM(CASE WHEN e.ans_status = 'right' THEN 1 ELSE 0 END) * 100) / COUNT(*)), 2), 0) AS obtained_percentage")
                        ->join('lms_mapping_type as lt', 'lt.id', '=', 'l.mapping_value_id')
                        ->leftJoin('lms_online_exam_answer as e', function ($join) use ($val, $id) {
                            $join->on('e.question_id', '=', 'l.questionmaster_id')
                                ->on('e.question_paper_id', '=', DB::raw("'" . $val['paper_id'] . "'"))
                                ->on('e.student_id', '=', DB::raw("'" . $id . "'"))
                                ->whereIn('e.online_exam_id', explode(",", $val['online_exam_ids']));
                        })
                        ->whereIn('questionmaster_id', function ($query) use ($val, $id) {
                            $query->select('question_id')
                                ->from('lms_online_exam_answer')
                                ->where('question_paper_id', $val['paper_id'])
                                ->where('student_id', $id)
                                ->whereIn('online_exam_id', explode(",", $val['online_exam_ids']));
                        })
                        ->groupBy('mapping_value_id')
                        ->orderBy('mapping_type_id')
                        ->orderBy('mapping_value_id')
                        ->get();

                    $lo_data = [];

                    foreach ($lo_result as $k => $v) {
                        $lo_data[$v->name] = $v->obtained_percentage;
                    }

                    $lo_arr[$val['paper_name']] = $lo_data;
                }
            }
        }


        /* END Get Cumulative LO/LI percentage */


        $data['student_id'] = $id;
        $data['student_data'] = $student_data;
        $data['all_subject_arr'] = $all_subject_arr;
        $data['exam_arr'] = $exam_arr;
        $data['grand_total'] = $grand_total;
        $data['grand_obtained'] = $grand_obtained;
        $data['linechart_data'] = $linechart_data;
        $data['current_subject'] = $current_subject;
        $data['lo_arr'] = [];//$lo_arr;

        $type = $request->input('type');

        return is_mobile($type, "lms/reports/final_student_report", $data, "view");
    }

}
