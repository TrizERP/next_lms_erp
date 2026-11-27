<?php

namespace App\Services\PAL\ContextGraph;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Course Context — the course-grain sibling of LearnerContextGraphService.
 *
 * "Course" here means a (standard, subject) pair, matching how the rest of
 * this LMS scopes teaching (timetable, chapter_master, question_paper are all
 * keyed this way). Same layered, independently-degradable pattern as the
 * learner-level service: each layer can come back empty/unavailable without
 * failing the whole response.
 *
 * All SQL, by design — the knowledge-graph-backed hops this layer could use
 * (subject->chapter->concept, exam_paper->question) are read through the
 * ontology/ GraphQueryService layer only where that is the ONLY way to answer
 * (see AssessmentContextService's question list); here, every table queried
 * already has a direct, unambiguous SQL join, so there is no honesty cost to
 * reading it directly.
 */
class CourseContextService
{
    /**
     * @return array{available: bool, message?: string, ...}
     */
    public function build(int $standardId, int $subjectId, ?int $tenantId = null): array
    {
        $standard = DB::table('standard')
            ->where('id', $standardId)
            ->when($tenantId, fn ($q) => $q->where('sub_institute_id', $tenantId))
            ->first(['id', 'name', 'sub_institute_id']);

        // `display_name` is the platform convention for a subject's label,
        // but is absent on `subject` in some tenant schemas (migration
        // drift, not a code decision) - select it only when it exists.
        $subjectColumns = ['id', 'subject_name'];
        if (Schema::hasColumn('subject', 'display_name')) {
            $subjectColumns[] = 'display_name';
        }

        $subject = DB::table('subject')->where('id', $subjectId)->first($subjectColumns);

        if ($standard === null || $subject === null) {
            return [
                'available' => false,
                'message' => "Standard {$standardId} / subject {$subjectId} could not be resolved.",
            ];
        }

        $subInstituteId = (int) $standard->sub_institute_id;

        return [
            'available' => true,
            'standard' => ['id' => (int) $standard->id, 'name' => $standard->name],
            'subject' => ['id' => (int) $subject->id, 'name' => ($subject->display_name ?? null) ?: $subject->subject_name],
            'sub_institute_id' => $subInstituteId,
            'enrolled_students' => $this->resolveEnrolledStudents($standardId, $subInstituteId),
            'content' => $this->resolveContent($standardId, $subjectId, $subInstituteId),
            'assessments' => $this->resolveAssessments($standardId, $subjectId, $subInstituteId),
            'attendance_summary' => $this->resolveAttendanceSummary($standardId, $subInstituteId),
            'timetable' => $this->resolveTimetable($standardId, $subjectId, $subInstituteId),
            'exam_performance' => $this->resolveExamPerformance($standardId, $subjectId, $subInstituteId),
            'assembled_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Identity layer: who is currently enrolled in this standard.
     */
    private function resolveEnrolledStudents(int $standardId, int $subInstituteId): array
    {
        if (! Schema::hasTable('tblstudent_enrollment')) {
            return [];
        }

        return DB::table('tblstudent_enrollment as se')
            ->join('tblstudent as s', 's.id', '=', 'se.student_id')
            ->where('se.standard_id', $standardId)
            ->where('se.sub_institute_id', $subInstituteId)
            ->orderByDesc('se.syear')
            ->limit(200)
            ->get(['s.id', 's.first_name', 's.last_name', 'se.section_id', 'se.roll_no', 'se.syear'])
            ->map(fn ($r) => [
                'student_id' => (int) $r->id,
                'name' => trim("{$r->first_name} {$r->last_name}"),
                'section_id' => $r->section_id !== null ? (int) $r->section_id : null,
                'roll_no' => $r->roll_no,
                'syear' => (int) $r->syear,
            ])
            ->all();
    }

    /**
     * Content & assessments layer: chapters/concepts/resources for this
     * subject, and the question papers set for it.
     */
    private function resolveContent(int $standardId, int $subjectId, int $subInstituteId): array
    {
        $chapters = [];
        if (Schema::hasTable('chapter_master')) {
            $chapters = DB::table('chapter_master')
                ->where('standard_id', $standardId)
                ->where('subject_id', $subjectId)
                ->where('sub_institute_id', $subInstituteId)
                ->get(['id', 'chapter_name'])
                ->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->chapter_name])
                ->all();
        }

        $chapterIds = array_column($chapters, 'id');

        $conceptCount = 0;
        if ($chapterIds !== [] && Schema::hasTable('lms_concept')) {
            $conceptCount = DB::table('lms_concept')->whereIn('chapter_id', $chapterIds)->count();
        }

        $resourceCount = 0;
        if ($chapterIds !== [] && Schema::hasTable('content_master')) {
            $resourceCount = DB::table('content_master')->whereIn('chapter_id', $chapterIds)->count();
        }

        return [
            'chapters' => $chapters,
            'concept_count' => $conceptCount,
            'resource_count' => $resourceCount,
        ];
    }

    /**
     * Content & assessments layer, part 2: the question papers set for this
     * (standard, subject) — the "exam_paper" ontology entity's source table.
     */
    private function resolveAssessments(int $standardId, int $subjectId, int $subInstituteId): array
    {
        if (! Schema::hasTable('question_paper')) {
            return [];
        }

        return DB::table('question_paper')
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->where('sub_institute_id', $subInstituteId)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'paper_name', 'exam_type', 'total_marks', 'total_ques'])
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'paper_name' => $p->paper_name,
                'exam_type' => $p->exam_type,
                'total_marks' => $p->total_marks !== null ? (int) $p->total_marks : null,
                'total_questions' => $p->total_ques !== null ? (int) $p->total_ques : null,
            ])
            ->all();
    }

    /**
     * Attendance layer: a simple present/absent rollup for this standard —
     * cohort-wide (not per-student), matching
     * FeesCrossModuleInsightRepository::feeAttendanceCorrelation()'s own
     * precedent for avoiding an N+1 per-student loop.
     */
    private function resolveAttendanceSummary(int $standardId, int $subInstituteId): array
    {
        if (! Schema::hasTable('attendance_student')) {
            return ['available' => false];
        }

        $totals = DB::table('attendance_student')
            ->where('standard_id', $standardId)
            ->where('sub_institute_id', $subInstituteId)
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN attendance_code = 'P' THEN 1 ELSE 0 END) AS present")
            ->first();

        $total = (int) ($totals->total ?? 0);

        if ($total === 0) {
            return ['available' => false];
        }

        $present = (int) ($totals->present ?? 0);

        return [
            'available' => true,
            'total_marks' => $total,
            'present' => $present,
            'attendance_rate' => round(($present / $total) * 100, 1),
        ];
    }

    /**
     * Timetable layer: which periods/teacher this subject is taught in, for
     * this standard.
     */
    private function resolveTimetable(int $standardId, int $subjectId, int $subInstituteId): array
    {
        if (! Schema::hasTable('timetable')) {
            return [];
        }

        return DB::table('timetable')
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->where('sub_institute_id', $subInstituteId)
            ->orderByDesc('syear')
            ->limit(50)
            ->get(['id', 'division_id', 'period_id', 'teacher_id', 'week_day', 'syear'])
            ->map(fn ($t) => [
                'division_id' => $t->division_id !== null ? (int) $t->division_id : null,
                'period_id' => $t->period_id !== null ? (int) $t->period_id : null,
                'teacher_id' => $t->teacher_id !== null ? (int) $t->teacher_id : null,
                'week_day' => $t->week_day,
            ])
            ->all();
    }

    /**
     * Exam & historical performance layer: online-exam attempts for this
     * (standard, subject)'s question papers, aggregated — the same
     * Student-[ATTEMPTED]->Assessment data the exam_assessment ontology
     * wiring exposes on the graph side, read here directly in SQL since the
     * join (lms_online_exam.question_paper_id -> question_paper.id) is
     * unambiguous.
     */
    private function resolveExamPerformance(int $standardId, int $subjectId, int $subInstituteId): array
    {
        if (! Schema::hasTable('lms_online_exam') || ! Schema::hasTable('question_paper')) {
            return ['available' => false];
        }

        $stats = DB::table('lms_online_exam as e')
            ->join('question_paper as p', 'p.id', '=', 'e.question_paper_id')
            ->where('p.standard_id', $standardId)
            ->where('p.subject_id', $subjectId)
            ->where('p.sub_institute_id', $subInstituteId)
            ->selectRaw('COUNT(*) AS attempts, AVG(e.obtain_marks) AS avg_marks')
            ->first();

        $attempts = (int) ($stats->attempts ?? 0);

        if ($attempts === 0) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'attempts' => $attempts,
            'average_marks' => $stats->avg_marks !== null ? round((float) $stats->avg_marks, 2) : null,
        ];
    }
}
