<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Online exam performance, read the same way the LMS Result Dashboard reads it.
 *
 * Two tables carry everything, exactly as `LmsResultDashboardApiController` (the
 * teacher-facing Result Dashboard's own source) documents: `question_paper` (one row per
 * exam a teacher published) and `lms_online_exam` (one row per student attempt). This
 * service copies that controller's scoping and percentage formula rather than deriving
 * its own — `oe.obtain_marks / NULLIF(qp.total_marks, 0) * 100`, `question_paper.exam_type
 * = 'PAL'` excluded because those papers are generated per-learner by the PAL engine
 * rather than published by a teacher — so an MCP answer can never disagree with the
 * dashboard a teacher already reads.
 *
 * WHY THE SCOPE JOIN IS THROUGH `question_paper`, NOT `lms_online_exam` ITSELF
 *
 * `lms_online_exam` carries no `sub_institute_id` of its own — confirmed against its
 * creating migration, not assumed. Every read here starts from `question_paper`, which
 * does, and joins attempts onto it; a query that filtered `lms_online_exam` directly
 * would have no tenant boundary at all.
 *
 * 40% is `LmsResultDashboardApiController::AT_RISK_PERCENT` — the bar this estate's own
 * Result Dashboard already uses for "needs attention". Reused here rather than a fresh
 * number, so a low-score finding in the AI Stack agrees with the dashboard's own reading.
 */
class OnlineExamReportService
{
    private const EXCLUDED_EXAM_TYPE = 'PAL';

    private const PERCENT_SQL = '(oe.obtain_marks / NULLIF(qp.total_marks, 0) * 100)';

    public const DEFAULT_AT_RISK_PERCENT = 40.0;

    /**
     * @param  array<string, mixed>  $filters  standard_id, subject_id, student_id,
     *                                         at_risk_percent, limit.
     * @return array<string, mixed>
     */
    public function summary(McpRequestContext $context, array $filters = []): array
    {
        if (! Schema::hasTable('question_paper') || ! Schema::hasTable('lms_online_exam')) {
            return ['exams_published' => 0, 'attempts_recorded' => 0, 'at_risk_attempts' => [],
                'note' => 'Online exams are not recorded in this estate.'];
        }

        $atRisk = $this->atRiskPercent($filters);
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $summary = $this->joinAttempts($this->scopedPapers($context, $filters), $filters)
            ->selectRaw('COUNT(DISTINCT qp.id) AS exams_published,
                COUNT(oe.id) AS attempts_recorded,
                COUNT(DISTINCT oe.student_id) AS students_assessed,
                AVG(' . self::PERCENT_SQL . ') AS average_score,
                SUM(CASE WHEN ' . self::PERCENT_SQL . ' < ? THEN 1 ELSE 0 END) AS needs_attention', [$atRisk])
            ->first();

        $atRiskRows = $this->joinAttempts($this->scopedPapers($context, $filters), $filters, forceInner: true)
            ->leftJoin('tblstudent as s', 's.id', '=', 'oe.student_id')
            ->whereRaw(self::PERCENT_SQL . ' < ?', [$atRisk])
            ->selectRaw("oe.id AS attempt_id, oe.student_id,
                TRIM(CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name)) AS student_name,
                qp.id AS exam_id, qp.paper_name, qp.subject_id, qp.standard_id,
                oe.obtain_marks, qp.total_marks,
                ROUND(" . self::PERCENT_SQL . ', 1) AS percent,
                oe.created_at AS attempted_at')
            ->orderByDesc('oe.created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'attempt_id' => (int) $row->attempt_id,
                'student_id' => (int) $row->student_id,
                'student_name' => trim((string) $row->student_name) ?: null,
                'exam_id' => (int) $row->exam_id,
                'paper_name' => $row->paper_name,
                'subject_id' => $row->subject_id ? (int) $row->subject_id : null,
                'standard_id' => $row->standard_id ? (int) $row->standard_id : null,
                'obtain_marks' => $row->obtain_marks === null ? null : (float) $row->obtain_marks,
                'total_marks' => $row->total_marks === null ? null : (float) $row->total_marks,
                'percent' => $row->percent === null ? null : (float) $row->percent,
                'attempted_at' => $row->attempted_at,
            ])
            ->all();

        return [
            'exams_published' => (int) ($summary->exams_published ?? 0),
            'attempts_recorded' => (int) ($summary->attempts_recorded ?? 0),
            'students_assessed' => (int) ($summary->students_assessed ?? 0),
            'average_score' => $summary->average_score === null ? null : round((float) $summary->average_score, 1),
            'needs_attention' => (int) ($summary->needs_attention ?? 0),
            'at_risk_percent' => $atRisk,
            'at_risk_attempts' => $atRiskRows,
            'rule' => 'Papers with exam_type = PAL (generated per-learner by the PAL engine, not published by '
                . 'a teacher) are excluded, and the percentage is obtain_marks over the paper\'s own total_marks — '
                . 'the same figures the LMS Result Dashboard shows.',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function atRiskPercent(array $filters): float
    {
        $given = $filters['at_risk_percent'] ?? null;

        if (! is_numeric($given)) {
            return self::DEFAULT_AT_RISK_PERCENT;
        }

        return max(0.0, min(100.0, (float) $given));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function scopedPapers(McpRequestContext $context, array $filters)
    {
        return DB::table('question_paper as qp')
            ->where('qp.sub_institute_id', $context->selectedInstituteId)
            ->when($context->academicYear !== null, fn ($query) => $query->where('qp.syear', $context->academicYear))
            ->where(function ($query) {
                $query->where('qp.exam_type', '<>', self::EXCLUDED_EXAM_TYPE)->orWhereNull('qp.exam_type');
            })
            ->when(! empty($filters['standard_id']), fn ($query) => $query->where('qp.standard_id', (int) $filters['standard_id']))
            ->when(! empty($filters['subject_id']), fn ($query) => $query->where('qp.subject_id', (int) $filters['subject_id']));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function joinAttempts($query, array $filters, bool $forceInner = false)
    {
        $studentId = ! empty($filters['student_id']) ? (int) $filters['student_id'] : null;
        $inner = $forceInner || $studentId !== null;

        $query = $inner
            ? $query->join('lms_online_exam as oe', 'oe.question_paper_id', '=', 'qp.id')
            : $query->leftJoin('lms_online_exam as oe', 'oe.question_paper_id', '=', 'qp.id');

        if ($studentId !== null) {
            $query = $query->where('oe.student_id', $studentId);
        }

        return $query;
    }
}
