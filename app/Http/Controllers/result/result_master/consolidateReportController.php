<?php

namespace App\Http\Controllers\result\result_master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Support\Facades\DB;

class consolidateReportController extends Controller
{
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }
        $type = $request->input('type');
        $res = array();
        return is_mobile($type, "result/result_master/consolidateReport", $res, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $grade = $request->grade;
        $standard = $request->standard;
        $division = $request->division;

        $studentData = SearchStudent($grade, $standard, $division);

        // Get term list with term_id and title
        $getTerm = DB::table('academic_year')
            ->where([
                'sub_institute_id' => $sub_institute_id, 
                'syear' => $syear
            ])
            ->orderBy('sort_order')
            ->get()
            ->pluck('title', 'term_id');

        // $getExamMasters = DB::table('result_create_exam as rce')
        //     ->join('result_exam_master as rem', 'rem.id', '=', 'rce.exam_id')
        //     ->where([
        //         'rce.sub_institute_id' => $sub_institute_id, 
        //         'rce.syear' => $syear, 
        //         'rce.standard_id' => $standard
        //     ])
        //     ->where('rce.report_card_status','Y')
        //     ->orderByRaw('rem.SortOrder')
        //     ->pluck('rem.ExamTitle', 'rem.Id');
        
        // // return $getExamMasters;

        // $getSubjectList = DB::table("sub_std_map as ssm")
        //     ->join('subject as sub', 'ssm.subject_id', '=', 'sub.id')
        //     ->selectRaw('ssm.id as map_id,sub.id as subject_id,ssm.display_name as subject_name,ssm.elective_subject,ssm.allow_grades,ssm.optional_type')
        //     ->where([
        //         'ssm.sub_institute_id' => $sub_institute_id, 
        //         'ssm.standard_id' => $standard, 
        //         'allow_grades' => "Yes"
        //     ])
        //     ->orderBy('ssm.sort_order')
        //     ->get()
        //     ->pluck('subject_name', 'subject_id');

        $examData = DB::table('result_create_exam as rce')
            ->join('result_exam_master as rem', 'rem.id', '=', 'rce.exam_id')
            ->join('sub_std_map as ssm', function($q) {
                $q->on('ssm.subject_id', '=', 'rce.subject_id')
                  ->on('ssm.standard_id', '=', 'rce.standard_id');
            })
            ->where('rce.report_card_status', 'Y')
            ->where([
                'rce.sub_institute_id' => $sub_institute_id,
                'rce.syear'            => $syear,
                'rce.standard_id'      => $standard
            ])
            ->selectRaw('rce.id, rce.title, rce.term_id, rce.standard_id, rem.weightage, rem.ExamTitle,
                         rce.subject_id, rce.points, rce.con_point, rem.Id as ExamId, rce.exam_id,
                         ssm.display_name, ssm.elective_subject, ssm.allow_grades, ssm.optional_type')
            ->orderByRaw('rce.term_id, ssm.sort_order, rem.SortOrder, rce.sort_order')
            ->get()
            ->toArray();
        
        $examMasterWise = $studentMarks = [];
        $createExamCount = 0;
        
        // Build examMasterWise as [term_id][ExamTitle][display_name][title] => exams
        foreach ($examData as $exam) {
            if (!isset($examMasterWise[$exam->term_id][$exam->ExamTitle][$exam->display_name][$exam->title])) {
                $examMasterWise[$exam->term_id][$exam->ExamTitle][$exam->display_name][$exam->title] = $exam;
                $createExamCount++;
            }
        }
     
        // Initialize marks for all students
        foreach ($studentData as $student) {
            $studentMarks[$student['student_id']] = [
                'id'            => $student['id'],
                'first_name'    => $student['first_name'],
                'middle_name'   => $student['middle_name'], 
                'last_name'     => $student['last_name'],
                'enrollment_no' => $student['enrollment_no'],
                'roll_no'       => $student['roll_no'],
            ];
        }

        // Build exam structure and get marks
        foreach ($examMasterWise as $termId => $examTitles) {
            foreach ($examTitles as $examTitle => $subjects) {
                foreach ($subjects as $subjectName => $titles) {
                    foreach ($titles as $title => $exam) {
                        // Get marks for all students for this exam
                        $allMarks = DB::table('result_marks')
                            ->whereIn('student_id', array_column($studentData, 'student_id'))
                            ->where('exam_id', $exam->id)
                            ->get();

                        // Create lookup array of marks by student
                        $marksLookup = [];
                        foreach ($allMarks as $mark) {
                            $marks = 0;
                            if ($mark->points) {
                                $marks = $mark->points;
                            } elseif ($mark->is_absent != '') {
                                $marks = $mark->is_absent;
                            }
                            $marksLookup[$mark->student_id] = $marks;
                        }

                        // Assign marks to student structure with term_id separation
                        foreach ($studentData as $student) {
                            if (!isset($studentMarks[$student['student_id']]['terms'][$termId])) {
                                $studentMarks[$student['student_id']]['terms'][$termId] = [
                                    'title' => $getTerm[$termId] ?? 'Unknown Term',
                                    'exams' => []
                                ];
                            }
                            
                            $studentMarks[$student['student_id']]['terms'][$termId]['exams'][$examTitle][$subjectName][$title] = [
                                'exam_details' => $exam,
                                'ob_marks' => $marksLookup[$student['student_id']] ?? 0
                            ];
                        }
                    }
                }
            }
        }

        // echo "<pre>";
        // print_r($studentMarks);   
        // exit;
        $res['status_code'] = 0;
        $res['message'] = "Failed to get Data";
        $res['totalExams'] = $createExamCount;
        //$res['examMasters'] = $getExamMasters;
        $res['examMasterWise'] = $examMasterWise;
        $res['termList'] = $getTerm;
        //$res['subjectList'] = $getSubjectList;
        $res['studentMarks'] = $studentMarks;
        $res['grade_id'] = $request->grade;
        $res['standard_id'] = $request->standard;
        $res['division_id'] = $request->division;

        return is_mobile($type, "result/result_master/consolidateReport", $res, "view");
    }
}