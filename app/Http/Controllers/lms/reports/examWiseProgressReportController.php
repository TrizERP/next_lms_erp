<?php

namespace App\Http\Controllers\lms\reports;

use App\Http\Controllers\Controller;
use App\Models\lms\questionpaperModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class examWiseProgressReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['subject_data'] = array();
        $res['exams_data'] = array();

        return is_mobile($type, 'lms/reports/show_examwise_progress_report', $res, "view");
    }

    public function create(Request $request)
    {
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $division = $request->input('division');
        $subject = $request->input('subject');
        $exams = $request->input('exam_id');
        $type = $request->input('type');
        $marking_period_id = session()->get('term_id');

        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        $student_data = SearchStudent($grade, $standard, $division, $sub_institute_id, $syear);

        $examData = questionpaperModel::where([
            'sub_institute_id' => $sub_institute_id, 'standard_id' => $standard, 'subject_id' => $subject, 'syear' => $syear,
        ])
            ->whereIn('id', $exams)
            ->orderby('id')
            ->get();

        $exam_ids = implode(',', $exams);

        $marks_array = $grade_array = $all_marks = array();
        /*$data = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                $join->whereRaw("se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id AND se.syear
                    = '".$syear."' AND se.end_date IS NULL");
            })->join('academic_section as ac', function ($join) {
                $join->whereRaw('ac.id = se.grade_id AND ac.sub_institute_id = se.sub_institute_id');
            })->join('standard as st', function ($join) use($marking_period_id) {
                $join->whereRaw('st.id = se.standard_id AND st.sub_institute_id = se.sub_institute_id');
                // ->when($marking_period_id,function($query) use($marking_period_id){
                //     $query->where('st.marking_period_id',$marking_period_id);
                // });
            })->leftJoin('division as d', function ($join) {
                $join->whereRaw('d.id = se.section_id AND d.sub_institute_id = se.sub_institute_id');
            })->join('question_paper as qp', function ($join) {
                $join->whereRaw('qp.standard_id = se.standard_id AND qp.grade_id = se.grade_id AND qp.sub_institute_id = s.sub_institute_id');
            })->leftJoin('lms_online_exam as le', function ($join) {
                $join->whereRaw('le.question_paper_id = qp.id AND le.student_id = s.id');
            })->selectRaw("s.id,s.enrollment_no,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) AS student_name,
                st.name AS std_name,d.name AS div_name,se.standard_id,se.grade_id,qp.id AS question_paper_id,qp.paper_name,
                qp.total_marks,ifnull(MAX(le.total_right),'-') AS obtain_marks,GROUP_CONCAT(IFNULL(le.total_right, '-')) as all_marks ")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('se.grade_id', $grade)
            ->where('se.standard_id', $standard)
            ->where('qp.id', $exams)
            ->groupByRaw('s.id,qp.id')
            ->orderByRaw('s.roll_no ASC')->get()->toArray();*/
            // echo "<pre>";print_r($data);exit;
//DB::enableQueryLog();
        $data = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) use ($syear,$sub_institute_id) {
                $join->on('se.student_id', '=', 's.id')
                    ->where('se.sub_institute_id', '=', $sub_institute_id)
                    ->where('se.syear', '=', $syear)
                    ->whereNull('se.end_date');
            })
            ->join('academic_section as ac', 'ac.id', '=', 'se.grade_id')
            ->join('standard as st', function ($join) use ($marking_period_id,$sub_institute_id) {
                $join->on('st.id', '=', 'se.standard_id')
                    ->where('st.sub_institute_id', '=', $sub_institute_id);
            })
            ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->join('question_paper as qp', function ($join) use ($sub_institute_id) {
                $join->on('qp.standard_id', '=', 'se.standard_id')
                    ->on('qp.grade_id', '=', 'se.grade_id')
                    ->where('qp.sub_institute_id', '=', $sub_institute_id);
            })
            ->leftJoin('lms_online_exam as le', function ($join) {
                $join->on('le.question_paper_id', '=', 'qp.id')
                    ->on('le.student_id', '=', 's.id');
            })
            ->selectRaw("s.id, s.enrollment_no, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                st.name AS std_name, d.name AS div_name, se.standard_id, se.grade_id, qp.id AS question_paper_id, qp.paper_name,
                qp.total_marks, ifnull(MAX(le.total_right), '-') AS obtain_marks, GROUP_CONCAT(IFNULL(le.total_right, '-')) as all_marks")
            ->where('s.sub_institute_id', $sub_institute_id)
            ->where('se.grade_id', $grade)
            ->where('se.standard_id', $standard)
            ->where('qp.id', $exams)
            ->groupBy('s.id', 'qp.id')
            ->orderBy('s.roll_no', 'ASC')
            ->get()->toArray();
//dd(DB::getQueryLog());

        $data = json_decode(json_encode($data), true);
        foreach ($data as $k => $v) {
            $marks_array[$v['id']][$v['question_paper_id']] = $v['obtain_marks'];
        }
        $maxCount = 0;

        foreach ($data as $k => $v) {
            $all_marks[$v['id']][$v['question_paper_id']] = $v['all_marks'];
            if (is_string($all_marks[$v['id']][$v['question_paper_id']])) {
                $elements = explode(',', $all_marks[$v['id']][$v['question_paper_id']]);
                $count = count($elements);
                $maxCount = max($maxCount, $count);
            }
        }

        $grade_data = DB::table('result_std_grd_maping as rgm')
            ->join('grade_master_data as gm', function ($join) {
                $join->whereRaw('gm.grade_id = rgm.grade_scale AND gm.sub_institute_id = rgm.sub_institute_id');
            })->selectRaw('gm.title,gm.breakoff')
            ->where('rgm.standard', $standard)
            ->where('rgm.sub_institute_id', $sub_institute_id)->get()->toArray();

        $grade_data = json_decode(json_encode($grade_data), true);

        $subject_data = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $standard])
            ->orderBy('display_name')->get()->toArray();

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['student_data'] = $student_data;
        $res['marks_data'] = $marks_array;
        $res['all_marks_col']= $maxCount;
        $res['all_marks'] = $all_marks;
        $res['grade_data'] = $grade_data;
        $res['grade_id'] = $grade;
        $res['standard_id'] = $standard;
        $res['division_id'] = $division;
        $res['subject_id'] = $subject;
        $res['exam_id'] = $exams;
        $res['exams_data'] = $examData;
        $res['subject_data'] = $subject_data;

        return is_mobile($type, "lms/reports/show_examwise_progress_report", $res, "view");
    }

    public function ajax_LMS_SubjectWiseExam(Request $request)
    {
        $std_id = $request->input("std_id");
        $sub_id = $request->input("sub_id");
        $sub_institute_id = session()->get("sub_institute_id");
        $syear = session()->get("syear");

        return questionpaperModel::where([
            'sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id, 'subject_id' => $sub_id, 'syear' => $syear,
        ])->get()->toArray();
    }

}
