<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Teacher-facing Result Dashboard for LMS > Test > Exam.
 *
 * Answers the "Results dashboard" tab beside "Exams" on the Learning
 * management screen. Shaped to match RoleDashboardApiController::teacherSummary
 * (the LMS Teacher Dashboard) so the frontend can reuse the same dashboard
 * primitives.
 *
 * Two tables carry everything:
 *   question_paper    one row per exam a teacher published
 *   lms_online_exam   one row per student attempt at such a paper
 *
 * A percentage is always obtain_marks / question_paper.total_marks, guarded
 * with NULLIF because papers exist with total_marks = 0 and MySQL would
 * otherwise return NULL rows that silently skew the averages.
 *
 * Scope. A teacher sees the exams they authored (question_paper.created_by);
 * any other staff profile that reaches this endpoint (Admin, School Admin)
 * sees the whole institute. Both are always pinned to the session's
 * sub_institute_id and syear, and both exclude exam_type = 'PAL': those papers
 * are generated per-learner by the PAL engine rather than published by a
 * teacher, so counting them here would drown the teacher's own exams.
 *
 * FILTERS. The four the screen offers do not all bite in the same place,
 * because question_paper records only grade_id and standard_id - it has no
 * division column, and obviously no student column:
 *
 *   section (grade_id), standard  ->  narrow the PAPERS
 *   division, student             ->  narrow the ATTEMPTS
 *
 * A division/student filter is resolved once into a list of student ids
 * (restrictedStudentIds) that every attempt query then filters on. It also
 * flips the paper join from LEFT to INNER: once a teacher asks about one
 * student or one division, "exams published" should mean the exams those
 * students actually sat, not every paper on the shelf.
 *
 * The route sits behind ['api.session', 'staff.only'], so identity comes from
 * the verified JWT and a student token is rejected before reaching this class.
 */
class LmsResultDashboardApiController extends Controller
{
    /** Attempts below this percentage are the ones a teacher needs to act on. */
    private const AT_RISK_PERCENT = 40;

    /** Papers the PAL engine generates per learner, not teacher-published exams. */
    private const EXCLUDED_EXAM_TYPE = 'PAL';

    /** Percentage expression, reused so rounding is identical everywhere. */
    private const PERCENT_SQL = '(oe.obtain_marks / NULLIF(qp.total_marks, 0) * 100)';

    public function summary(Request $request): JsonResponse
    {
        $subInstituteId = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $userId = session()->get('user_id');
        $profileName = strtolower(trim((string) session()->get('user_profile_name')));

        $ownExamsOnly = in_array($profileName, ['teacher', 'lms teacher'], true);
        $filters = $this->filters($request);

        // null = no attempt-level filter; [] = a filter that matched nobody,
        // which must yield empty results rather than silently matching all.
        $studentIds = $this->restrictedStudentIds($subInstituteId, $syear, $filters);

        $context = [
            'sub_institute_id' => $subInstituteId,
            'syear' => $syear,
            'user_id' => $userId,
            'own_exams_only' => $ownExamsOnly,
            'filters' => $filters,
            'student_ids' => $studentIds,
        ];

        $summary = $this->summaryRow($context);

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'scope' => $ownExamsOnly ? 'own_exams' : 'institute',
            'filters' => $filters,
            'summary' => [
                'exams_published' => (int) $summary->exams_published,
                'attempts_recorded' => (int) $summary->attempts_recorded,
                'students_assessed' => (int) $summary->students_assessed,
                'average_score' => $summary->average_score === null ? null : round((float) $summary->average_score, 1),
                'needs_attention' => (int) $summary->needs_attention,
            ],
            'recent_exams' => $this->recentExams($context),
            'score_distribution' => $this->scoreDistribution($context),
            'subject_performance' => $this->subjectPerformance($context),
            'students_to_watch' => $this->studentsToWatch($context),
            // The Student dropdown's options. Deliberately built from
            // section/standard/division only, never from student_id, so
            // picking a student does not collapse the list to that student.
            'student_options' => $this->studentOptions($subInstituteId, $syear, $filters),
        ], 200);
    }

    /** Blank, 0 and "all" all mean "no filter"; anything else must be a positive id. */
    private function filters(Request $request): array
    {
        $read = function (string $key) use ($request) {
            $value = $request->input($key);
            if ($value === null || $value === '' || $value === 'all') {
                return null;
            }

            return (int) $value > 0 ? (int) $value : null;
        };

        return [
            // "Section" in the academic dropdowns is the grade.
            'grade_id' => $read('grade_id') ?? $read('section_id'),
            'standard_id' => $read('standard_id'),
            'division_id' => $read('division_id'),
            'student_id' => $read('student_id'),
        ];
    }

    /**
     * The attempt-level filter, resolved to student ids.
     *
     * Returns null when neither division nor student is set, which leaves the
     * attempt queries unrestricted. A division resolves through the enrollment
     * table, since question_paper has no division of its own.
     */
    private function restrictedStudentIds($subInstituteId, $syear, array $filters): ?array
    {
        if ($filters['student_id']) {
            return [$filters['student_id']];
        }

        if (! $filters['division_id']) {
            return null;
        }

        return DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear)
            ->whereNull('end_date')
            ->where('section_id', $filters['division_id'])
            ->when($filters['standard_id'], fn ($query, $id) => $query->where('standard_id', $id))
            ->when($filters['grade_id'], fn ($query, $id) => $query->where('grade_id', $id))
            ->pluck('student_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Every query below starts here, so the scope rule lives in exactly one
     * place and cannot drift between the headline numbers and the panels.
     */
    private function scopedPapers(array $context)
    {
        $filters = $context['filters'];

        return DB::table('question_paper as qp')
            ->where('qp.sub_institute_id', $context['sub_institute_id'])
            ->where('qp.syear', $context['syear'])
            ->where(function ($query) {
                $query->where('qp.exam_type', '<>', self::EXCLUDED_EXAM_TYPE)
                    ->orWhereNull('qp.exam_type');
            })
            ->when($context['own_exams_only'], fn ($query) => $query->where('qp.created_by', $context['user_id']))
            ->when($filters['grade_id'], fn ($query, $id) => $query->where('qp.grade_id', $id))
            ->when($filters['standard_id'], fn ($query, $id) => $query->where('qp.standard_id', $id));
    }

    /**
     * Join the attempts onto the papers.
     *
     * With no attempt-level filter the join is LEFT, so an exam published but
     * not yet attempted still counts towards exams_published. With one, it is
     * INNER: a teacher asking about one student wants the exams that student
     * sat, not every paper with a null row hanging off it. Panels that are
     * meaningless without an attempt (the chart, subject averages, the at-risk
     * list) always pass $forceInner.
     */
    private function joinAttempts($query, array $context, bool $forceInner = false)
    {
        $studentIds = $context['student_ids'];
        $inner = $forceInner || $studentIds !== null;

        $query = $inner
            ? $query->join('lms_online_exam as oe', 'oe.question_paper_id', '=', 'qp.id')
            : $query->leftJoin('lms_online_exam as oe', 'oe.question_paper_id', '=', 'qp.id');

        if ($studentIds !== null) {
            // An empty list is a filter that matched nobody - whereIn([]) is
            // correctly false, which is what we want, not "no restriction".
            $query = $query->whereIn('oe.student_id', $studentIds);
        }

        return $query;
    }

    private function summaryRow(array $context)
    {
        return $this->joinAttempts($this->scopedPapers($context), $context)
            ->selectRaw('COUNT(DISTINCT qp.id) AS exams_published,
                COUNT(oe.id) AS attempts_recorded,
                COUNT(DISTINCT oe.student_id) AS students_assessed,
                AVG(' . self::PERCENT_SQL . ') AS average_score,
                SUM(CASE WHEN ' . self::PERCENT_SQL . ' < ? THEN 1 ELSE 0 END) AS needs_attention', [self::AT_RISK_PERCENT])
            ->first();
    }

    /**
     * The latest exams in scope, each with how they went.
     *
     * Eight is a dashboard glance. With one student picked the panel stops
     * being a glance and becomes that student's record, so it opens up.
     */
    private function recentExams(array $context): array
    {
        $limit = $context['filters']['student_id'] ? 50 : 8;

        $rows = $this->joinAttempts($this->scopedPapers($context), $context)
            ->leftJoin('standard as st', 'st.id', '=', 'qp.standard_id')
            ->leftJoin('subject as sub', 'sub.id', '=', 'qp.subject_id')
            ->selectRaw('qp.id, qp.paper_name, qp.exam_type, qp.total_marks, qp.total_ques,
                DATE_FORMAT(qp.close_date, "%d-%m-%Y") AS close_date,
                st.name AS standard_name, sub.subject_name,
                COUNT(oe.id) AS attempts,
                ROUND(AVG(' . self::PERCENT_SQL . '), 1) AS average_percent,
                ROUND(MAX(' . self::PERCENT_SQL . '), 1) AS highest_percent,
                ROUND(MIN(' . self::PERCENT_SQL . '), 1) AS lowest_percent')
            ->groupBy('qp.id', 'qp.paper_name', 'qp.exam_type', 'qp.total_marks', 'qp.total_ques',
                'qp.close_date', 'st.name', 'sub.subject_name')
            ->orderByDesc('qp.created_on')
            ->limit($limit)
            ->get()->all();

        return $this->castRows($rows,
            ['id', 'total_marks', 'total_ques', 'attempts'],
            ['average_percent', 'highest_percent', 'lowest_percent']);
    }

    /**
     * How the cohort is spread across score bands - the chart that tells a
     * teacher whether a low average is everyone or a struggling tail.
     * Bands are returned in a fixed order with explicit zeros, so the chart
     * never reorders itself or drops an empty band between refreshes.
     */
    private function scoreDistribution(array $context): array
    {
        $bands = ['0-39', '40-59', '60-74', '75-89', '90-100'];

        $counted = $this->joinAttempts($this->scopedPapers($context), $context, true)
            ->whereRaw(self::PERCENT_SQL . ' IS NOT NULL')
            ->selectRaw('CASE
                    WHEN ' . self::PERCENT_SQL . ' < 40 THEN "0-39"
                    WHEN ' . self::PERCENT_SQL . ' < 60 THEN "40-59"
                    WHEN ' . self::PERCENT_SQL . ' < 75 THEN "60-74"
                    WHEN ' . self::PERCENT_SQL . ' < 90 THEN "75-89"
                    ELSE "90-100"
                END AS band, COUNT(*) AS attempts')
            ->groupBy('band')
            ->pluck('attempts', 'band');

        return array_map(fn ($band) => [
            'label' => $band,
            'attempts' => (int) ($counted[$band] ?? 0),
        ], $bands);
    }

    /** Weakest subjects first: that is the list a teacher acts on. */
    private function subjectPerformance(array $context): array
    {
        $rows = $this->joinAttempts($this->scopedPapers($context), $context, true)
            ->join('subject as sub', 'sub.id', '=', 'qp.subject_id')
            ->whereRaw(self::PERCENT_SQL . ' IS NOT NULL')
            ->selectRaw('sub.id AS subject_id, sub.subject_name,
                COUNT(oe.id) AS attempts,
                ROUND(AVG(' . self::PERCENT_SQL . '), 1) AS average_percent')
            ->groupBy('sub.id', 'sub.subject_name')
            ->orderBy('average_percent')
            ->limit(8)
            ->get()->all();

        return $this->castRows($rows, ['subject_id', 'attempts'], ['average_percent']);
    }

    /** Most recent at-risk attempts, named, so the teacher can follow up. */
    private function studentsToWatch(array $context): array
    {
        $rows = $this->joinAttempts($this->scopedPapers($context), $context, true)
            ->leftJoin('tblstudent as s', 's.id', '=', 'oe.student_id')
            ->whereRaw(self::PERCENT_SQL . ' < ?', [self::AT_RISK_PERCENT])
            ->selectRaw('oe.id, oe.student_id,
                TRIM(CONCAT_WS(" ", s.first_name, s.middle_name, s.last_name)) AS student_name,
                qp.paper_name, oe.obtain_marks, qp.total_marks,
                ROUND(' . self::PERCENT_SQL . ', 1) AS percent,
                DATE_FORMAT(oe.created_at, "%d-%m-%Y") AS attempted_on')
            ->orderByDesc('oe.created_at')
            ->limit(10)
            ->get()->all();

        return $this->castRows($rows,
            ['id', 'student_id', 'obtain_marks', 'total_marks'], ['percent']);
    }

    /**
     * Students the teacher may pick from, for the current class selection.
     *
     * Returns nothing until a standard or division is chosen: an institute has
     * thousands of enrolled students and a dropdown of all of them is useless.
     * The frontend reads the empty list as "narrow the class first".
     */
    private function studentOptions($subInstituteId, $syear, array $filters): array
    {
        if (! $filters['standard_id'] && ! $filters['division_id']) {
            return [];
        }

        $rows = DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as s', 's.id', '=', 'se.student_id')
            ->leftJoin('standard as st', 'st.id', '=', 'se.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->selectRaw('s.id, TRIM(CONCAT_WS(" ", s.first_name, s.middle_name, s.last_name)) AS student_name,
                se.roll_no, se.standard_id, st.name AS standard_name,
                se.section_id AS division_id, d.name AS division_name')
            ->where('se.sub_institute_id', $subInstituteId)
            ->where('se.syear', $syear)
            ->whereNull('se.end_date')
            ->when($filters['grade_id'], fn ($query, $id) => $query->where('se.grade_id', $id))
            ->when($filters['standard_id'], fn ($query, $id) => $query->where('se.standard_id', $id))
            ->when($filters['division_id'], fn ($query, $id) => $query->where('se.section_id', $id))
            ->groupBy('s.id', 's.first_name', 's.middle_name', 's.last_name', 'se.roll_no',
                'se.standard_id', 'st.name', 'se.section_id', 'd.name')
            ->orderBy('se.roll_no')
            ->orderBy('student_name')
            ->limit(500)
            ->get()->all();

        return $this->castRows($rows, ['id', 'roll_no', 'standard_id', 'division_id'], []);
    }

    /**
     * MySQL hands ROUND()/COUNT() back as strings over PDO, which would make
     * the frontend re-parse every figure. Cast the numeric columns once here
     * so the JSON contract is numbers, and keep NULL as null (no attempts yet)
     * rather than flattening it to a misleading 0.
     */
    private function castRows(array $rows, array $intKeys, array $floatKeys): array
    {
        return array_map(function ($row) use ($intKeys, $floatKeys) {
            $row = (array) $row;
            foreach ($intKeys as $key) {
                if (array_key_exists($key, $row)) {
                    $row[$key] = $row[$key] === null ? null : (int) $row[$key];
                }
            }
            foreach ($floatKeys as $key) {
                if (array_key_exists($key, $row)) {
                    $row[$key] = $row[$key] === null ? null : (float) $row[$key];
                }
            }

            return $row;
        }, $rows);
    }
}
