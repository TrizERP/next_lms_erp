<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\result\cbse_result\result_report_controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for Result Report (CBSE result reports).
 *
 * Delegates every operation to the existing web controller
 * (result\cbse_result\result_report_controller) so all report, grade,
 * percentage and rank calculations are reused 1:1 — never re-implemented.
 */
class ResultReportApiController extends BaseResultApiController
{
    /**
     * Report types handled by the legacy show_result_report() method.
     */
    private const REPORT_TYPES = [
        'overall_report',
        'merit_report',
        'subject_progress_report',
        'classwise_report',
        'classwise_grade_report',
        'marks_report',
        'weightage_conversion_report',
        'created_exam_report',
    ];

    /**
     * GET /api/result/result-report
     * Filter metadata for the report screen (exam master list of the institute).
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->delegate(result_report_controller::class, 'index', $request);

            return $this->success($data);
        });
    }

    /**
     * POST /api/result/result-report/show
     * Generate one of the result reports (structured JSON, same data the
     * Blade views receive). Body: report_of + grade/standard/division and
     * the report-specific filters (term, subject, additional_subjects,
     * exam_type, exam_create, top_students, roll_no, from_date, to_date).
     */
    public function show(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'report_of'           => 'required|in:' . implode(',', self::REPORT_TYPES),
                'grade'               => 'required',
                'standard'            => 'required',
                'division'            => 'required',
                'term'                => 'nullable',
                'subject'             => 'required_if:report_of,subject_progress_report,weightage_conversion_report',
                'additional_subjects' => 'required_if:report_of,marks_report|array',
                'exam_type'           => 'required_if:report_of,created_exam_report',
            ]);

            $this->hydrateSuperglobals($request);

            $data = $this->delegate(result_report_controller::class, 'show_result_report', $request);

            if ($request->input('report_of') === 'classwise_grade_report' && is_array($data)) {
                $data = $this->flattenClasswiseGradeReport($data);
            }

            return $this->success($data);
        });
    }

    /**
     * The `classwise_grade_report` branch of the legacy show_result_report()
     * returns data shaped for its Blade view (all_student keyed by student
     * id, WRT_data keyed by student id -> subject name, date_arr as column
     * headers) rather than a flat row list. Next.js needs one row per
     * student, so this reproduces the Blade's per-subject/per-student
     * total, percentage and grade computation
     * (resources/views/result/result_report/classwise_grade_report.blade.php)
     * without touching the legacy controller or its calculations.
     */
    private function flattenClasswiseGradeReport(array $data): array
    {
        $allStudent = $data['all_student'] ?? [];
        $dateArr    = $data['date_arr'] ?? [];
        $wrtData    = $data['WRT_data'] ?? [];
        $standard   = $data['standard_id'] ?? '';
        $gradeScale = \App\Helpers\getGradeScale($standard);
        $gradeModeStandards = [788, 789, 790, 791];

        $rows = [];
        foreach ($allStudent as $studentData) {
            $studentId = $studentData['id'];
            $total = 0;
            $obtainedTotal = 0;
            $percentage = 0;

            $row = [
                'id'           => $studentId,
                'standard'     => ($studentData['standard_name'] ?? '') . ' - ' . ($studentData['division_name'] ?? ''),
                'roll_no'      => $studentData['roll_no'] ?? '',
                'student_name' => \App\Helpers\sortStudentName(
                    '',
                    $studentData['first_name'] ?? '',
                    $studentData['middle_name'] ?? '',
                    $studentData['last_name'] ?? ''
                ),
            ];

            foreach ($dateArr as $subjectKey => $datePoint) {
                $cell = $wrtData[$studentId][$subjectKey] ?? null;
                $isGradeMode = isset($datePoint[1]) && $datePoint[1] === 'Yes';

                if (empty($cell)) {
                    $row[$subjectKey] = '-';
                    continue;
                }

                if ($cell['is_absent'] === 'AB') {
                    $row[$subjectKey] = $cell['is_absent'];
                    if (! $isGradeMode) {
                        $total += $cell['total_points'];
                        $obtainedTotal += $cell['obtained_points'];
                    }
                } elseif ($cell['is_absent'] === 'EX' || $cell['is_absent'] === 'N.A.') {
                    $row[$subjectKey] = $cell['is_absent'];
                    $obtainedTotal += $cell['obtained_points'];
                } else {
                    $subMark = $cell['obtained_points'];
                    if ($standard !== '' && in_array($standard, $gradeModeStandards) && ! $isGradeMode) {
                        if ($cell['total_points'] >= $cell['obtained_points']) {
                            $subMark = $cell['obtained_points'] . ' ' . \App\Helpers\getGrade($gradeScale, $cell['total_points'], $cell['obtained_points']);
                        }
                    } elseif ($isGradeMode) {
                        if ($cell['total_points'] >= $cell['obtained_points']) {
                            $subMark = \App\Helpers\getGrade($gradeScale, $cell['total_points'], $cell['obtained_points']);
                        }
                    }
                    $row[$subjectKey] = $subMark;

                    if (! $isGradeMode) {
                        $total += $cell['total_points'];
                        $obtainedTotal += $cell['obtained_points'];
                    }
                }

                $percentage = $total != 0 ? number_format(($obtainedTotal * 100) / $total, 2) : 0;
            }

            $row['total']      = $obtainedTotal . '/' . $total;
            $row['percentage'] = $percentage;
            $row['grade']      = \App\Helpers\getGrade($gradeScale, $total, $obtainedTotal);

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * GET /api/result/result-report/standardwise-subjects?std_id=
     * Subjects mapped to a standard (sub_std_map), for the report filters.
     */
    public function standardwiseSubjects(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['std_id' => 'required']);

            $rows = $this->delegate(result_report_controller::class, 'ajax_StandardwiseSubject', $request);

            return $this->listResponse($rows, $request, ['display_name', 'subject_name']);
        });
    }

    /**
     * GET /api/result/result-report/download-overall-excel?grade=&standard=&division=
     * Overall report Excel export (passthrough of the legacy .xls stream).
     *
     * The legacy export reads session('over_all_data') which the web flow
     * fills in a previous request; the API is stateless, so the overall
     * report is (re)generated in-memory first, then streamed.
     */
    public function downloadOverallExcel(Request $request)
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade'    => 'required',
                'standard' => 'required',
                'division' => 'required',
            ]);

            $request->merge(['report_of' => 'overall_report']);
            $this->hydrateSuperglobals($request);

            // Populates session('over_all_data') for this request only.
            $this->delegate(result_report_controller::class, 'show_result_report', $request);

            // Streams OverallReport.xls directly (echo + exit in legacy code).
            return app(result_report_controller::class)->downloadOverAllReportExcel();
        });
    }

    /**
     * The legacy report helpers (getRank/getClasswise/getWRTData/getMarkwise
     * and the overall/created-exam branches) read $_REQUEST directly in their
     * type=API branches. Requests with a JSON body do not populate $_REQUEST,
     * so mirror the needed inputs into the superglobal before delegating.
     */
    private function hydrateSuperglobals(Request $request): void
    {
        foreach (['grade', 'standard', 'division', 'exam_type', 'exam_create'] as $key) {
            if ($request->has($key)) {
                $_REQUEST[$key] = $request->input($key);
            }
        }

        $_REQUEST['syear']            = $request->input('syear', session('syear'));
        $_REQUEST['sub_institute_id'] = $request->input('sub_institute_id', session('sub_institute_id'));
    }
}
