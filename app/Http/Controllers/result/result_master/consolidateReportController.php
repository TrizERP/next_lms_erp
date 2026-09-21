<?php

namespace App\Http\Controllers\result\result_master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Every mark for this class, from BOTH tables that hold marks, in two
     * queries rather than one per exam.
     *
     * ── WHY TWO TABLES ──────────────────────────────────────────────────────
     *
     * This report used to read `result_marks` alone. That table holds TWELVE
     * ROWS IN THE ENTIRE DATABASE — nine at demo institute 1 and three at
     * institute 341 — because it is written by the marks-entry screen, which
     * almost nobody uses. The 1.3 million marks that real institutes actually
     * have live in `result_personalize_marks`, which is where the report card
     * and the student lookup API both read from. So this report rendered a
     * complete, correctly-structured grid of zeros for every real institute.
     *
     * Both are read here and `result_marks` wins where it has a row, so no
     * report that works today changes, and the ones that showed nothing now
     * show the marks that were always there.
     *
     * ── WHERE IT STILL SHOWS NOTHING, AND WHY THAT IS CORRECT ───────────────
     *
     * `result_personalize_marks.exam_id` is the join to this report's exam
     * structure. Measured across this database it is:
     *
     *   - fully populated AND matching `result_create_exam.id` at institute 195
     *     (10,123 of 10,123 rows in 2022), which is the workflow this report
     *     was built for;
     *   - populated but matching NOTHING at institute 47, whose 122,009 marks
     *     carry ids from whatever system they were imported from;
     *   - entirely NULL at institute 254, whose marks are keyed only by
     *     enrolment number, subject name and exam name as free text.
     *
     * For the last two the marks cannot be hung on this report's exam
     * definitions at all, because those institutes never created any — so the
     * cells are empty rather than zero, which is the honest rendering of "this
     * institute's marks were imported outside the exam structure this report
     * reads". Nothing is invented to fill them.
     *
     * @param  array<int,mixed>  $examIds
     * @param  array<int,array<string,mixed>>  $studentData
     * @return array<int|string, array<int|string, mixed>>  [exam_id][student_id|'enr:<no>'] => mark
     */
    private function marksFor($subInstituteId, $syear, array $examIds, array $studentData): array
    {
        $examIds = array_values(array_unique(array_filter($examIds)));
        if ($examIds === []) {
            return [];
        }

        $studentIds = array_values(array_filter(array_column($studentData, 'student_id')));
        $enrolments = array_values(array_filter(array_map(
            static fn ($s) => isset($s['enrollment_no']) ? trim((string) $s['enrollment_no']) : '',
            $studentData,
        )));

        $byExam = [];

        // 1. The imported/report-card marks. Lower precedence, so the
        //    marks-entry table below can overwrite a cell it also holds.
        if (Schema::hasTable('result_personalize_marks')) {
            $query = DB::table('result_personalize_marks')
                ->where('sub_institute_id', $subInstituteId)
                ->where('syear', $syear)
                ->whereIn('exam_id', $examIds);

            $query->where(function ($q) use ($studentIds, $enrolments) {
                if ($studentIds !== []) {
                    $q->whereIn('student_id', $studentIds);
                }
                if ($enrolments !== []) {
                    $q->orWhereIn('enrollment_no', $enrolments);
                }
            });

            foreach ($query->get(['exam_id', 'student_id', 'enrollment_no', 'obtain']) as $row) {
                if ($row->obtain === null) {
                    continue;
                }

                // Keyed by student id where there is one, and ALSO by enrolment
                // number, because at several institutes `student_id` is 0 on
                // every mark row and the enrolment number is the only key.
                if ((int) $row->student_id > 0) {
                    $byExam[$row->exam_id][(int) $row->student_id] = $row->obtain;
                }
                $enrolment = trim((string) $row->enrollment_no);
                if ($enrolment !== '') {
                    $byExam[$row->exam_id]['enr:'.$enrolment] = $row->obtain;
                }
            }
        }

        // 2. The marks-entry screen's own table. Read second so it wins.
        if (Schema::hasTable('result_marks') && $studentIds !== []) {
            $rows = DB::table('result_marks')
                ->where('sub_institute_id', $subInstituteId)
                ->whereIn('student_id', $studentIds)
                ->whereIn('exam_id', $examIds)
                ->get(['exam_id', 'student_id', 'points', 'is_absent']);

            foreach ($rows as $mark) {
                // Preserved exactly as it was: a mark, or the absence code where
                // the child did not sit the paper.
                $value = null;
                if ($mark->points && ! in_array($mark->is_absent, ['AB', 'N.A.', 'EX'], true)) {
                    $value = $mark->points;
                } elseif ((string) $mark->is_absent !== '') {
                    $value = $mark->is_absent;
                }

                if ($value !== null) {
                    $byExam[$mark->exam_id][(int) $mark->student_id] = $value;
                }
            }
        }

        return $byExam;
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

        // Every mark for this class, fetched ONCE — see marksFor().
        $marksByExam = $this->marksFor(
            $sub_institute_id,
            $syear,
            array_column($examData, 'id'),
            $studentData,
        );

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
                        // Already fetched, for every exam at once, before this
                        // loop was entered. It used to run one query per
                        // (term x exam x subject x paper) cell — 777 queries for
                        // a single class at one institute in this database.
                        $marksLookup = $marksByExam[$exam->id] ?? [];

                        // Assign marks to student structure with term_id separation
                        foreach ($studentData as $student) {
                            if (!isset($studentMarks[$student['student_id']]['terms'][$termId])) {
                                $studentMarks[$student['student_id']]['terms'][$termId] = [
                                    'title' => $getTerm[$termId] ?? 'Unknown Term',
                                    'exams' => []
                                ];
                            }
                            
                            // NULL, NOT ZERO, where no mark exists. A child who
                            // scored nothing and a child whose mark was never
                            // entered are different facts, and rendering both as
                            // "0" on a consolidated report tells a parent the
                            // first one. The consuming page already renders null
                            // as an empty cell.
                            $studentMarks[$student['student_id']]['terms'][$termId]['exams'][$examTitle][$subjectName][$title] = [
                                'exam_details' => $exam,
                                'ob_marks' => $marksLookup[$student['student_id']]
                                    ?? $marksLookup['enr:'.($student['enrollment_no'] ?? '')]
                                    ?? null,
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