<?php

namespace App\Services\PAL\ContextGraph;

use App\Domain\KnowledgeGraph\GraphQueryService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Assessment/Exam Context — the per-assessment sibling of
 * LearnerContextGraphService/CourseContextService.
 *
 * $assessmentId is a `question_paper.id` (the "exam_paper" ontology entity,
 * Neo4j's :Assessment node). Deliberately does NOT fold in result_marks or
 * offline exams: no established join exists from result_marks.exam_id to
 * question_paper.id, and inventing one here would misrepresent data that was
 * never actually connected — left as a known non-goal, not silently guessed.
 */
class AssessmentContextService
{
    public function __construct(private readonly GraphQueryService $graph)
    {
    }

    public function build(int $assessmentId, ?int $tenantId = null): array
    {
        $paper = DB::table('question_paper')
            ->where('id', $assessmentId)
            ->when($tenantId, fn ($q) => $q->where('sub_institute_id', $tenantId))
            ->first(['id', 'paper_name', 'exam_type', 'total_marks', 'total_ques', 'standard_id', 'subject_id', 'sub_institute_id']);

        if ($paper === null) {
            return [
                'available' => false,
                'message' => "Assessment (question paper) {$assessmentId} could not be resolved.",
            ];
        }

        $subInstituteId = (int) $paper->sub_institute_id;
        $standardId = $paper->standard_id !== null ? (int) $paper->standard_id : null;
        $subjectId = $paper->subject_id !== null ? (int) $paper->subject_id : null;

        $standardName = $standardId ? DB::table('standard')->where('id', $standardId)->value('name') : null;

        // `display_name` is the platform convention for a subject's label,
        // but is absent on `subject` in some tenant schemas (migration
        // drift, not a code decision) - select it only when it exists.
        $subjectColumns = ['subject_name'];
        if (Schema::hasColumn('subject', 'display_name')) {
            $subjectColumns[] = 'display_name';
        }

        $subjectRow = $subjectId ? DB::table('subject')->where('id', $subjectId)->first($subjectColumns) : null;

        $questions = $this->resolveQuestions($assessmentId, $subInstituteId);
        $conceptIds = array_values(array_unique(array_filter(array_column($questions, 'concept_id'))));

        [$participants, $results] = $this->resolveParticipantsAndResults($assessmentId);

        return [
            'available' => true,
            'assessment' => [
                'id' => (int) $paper->id,
                'paper_name' => $paper->paper_name,
                'exam_type' => $paper->exam_type,
                'total_marks' => $paper->total_marks !== null ? (int) $paper->total_marks : null,
                'total_questions' => $paper->total_ques !== null ? (int) $paper->total_ques : null,
            ],
            'course' => [
                'standard_id' => $standardId,
                'standard_name' => $standardName,
                'subject_id' => $subjectId,
                'subject_name' => $subjectRow ? (($subjectRow->display_name ?? null) ?: $subjectRow->subject_name) : null,
            ],
            'questions' => $questions,
            'participants' => $participants,
            'results' => $results,
            'activity_history' => $this->resolveActivityHistory(array_column($participants, 'student_id'), $conceptIds),
            'assembled_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The only non-duplicated way to resolve "questions in this paper" without
     * re-implementing question_paper.question_ids CSV parsing: the graph-only
     * exam_paper -[has_question]-> question ontology edge. No SQL fallback
     * exists for this edge by design (see the exam_assessment ontology
     * migration) — a graph failure here legitimately means "unavailable",
     * not "fall back silently to something else".
     *
     * @return array<int, array{id:int, title:?string, points:?int, chapter_id:?int, concept_id:?int}>
     */
    private function resolveQuestions(int $assessmentId, int $subInstituteId): array
    {
        try {
            $context = new McpRequestContext(
                userId: 0,
                role: 'admin',
                selectedInstituteId: $subInstituteId,
                allowedInstituteIds: [$subInstituteId],
                userProfileId: null,
                clientId: null,
                academicYear: null,
                termId: null,
                isAdmin: true,
                isStudent: false,
            );

            $nodes = $this->graph->neighbours('exam_paper', $assessmentId, 'has_question', $context, 500);
        } catch (Throwable) {
            return [];
        }

        return array_map(fn ($n) => [
            'id' => is_numeric($n->id) ? (int) $n->id : $n->id,
            'title' => $n->attributes['question_title'] ?? $n->label,
            'points' => isset($n->attributes['points']) ? (int) $n->attributes['points'] : null,
            'chapter_id' => isset($n->attributes['chapter_id']) ? (int) $n->attributes['chapter_id'] : null,
            'concept_id' => isset($n->attributes['concept_id']) ? (int) $n->attributes['concept_id'] : null,
        ], $nodes);
    }

    /**
     * @return array{0: array<int, array>, 1: array{available: bool, attempts?: int, average_marks?: ?float}}
     */
    private function resolveParticipantsAndResults(int $assessmentId): array
    {
        if (! Schema::hasTable('lms_online_exam')) {
            return [[], ['available' => false]];
        }

        $attempts = DB::table('lms_online_exam')
            ->where('question_paper_id', $assessmentId)
            ->get(['id', 'student_id', 'obtain_marks', 'total_right', 'total_wrong']);

        if ($attempts->isEmpty()) {
            return [[], ['available' => false]];
        }

        $participants = $attempts->map(fn ($a) => [
            'student_id' => (int) $a->student_id,
            'obtain_marks' => $a->obtain_marks !== null ? (float) $a->obtain_marks : null,
            'total_right' => $a->total_right !== null ? (int) $a->total_right : null,
            'total_wrong' => $a->total_wrong !== null ? (int) $a->total_wrong : null,
        ])->all();

        $scored = $attempts->pluck('obtain_marks')->filter(fn ($m) => $m !== null)->map(fn ($m) => (float) $m);

        $results = [
            'available' => true,
            'attempts' => $attempts->count(),
            'average_marks' => $scored->isEmpty() ? null : round($scored->avg(), 2),
        ];

        return [$participants, $results];
    }

    /**
     * Engagement activity (practice/quiz) on the concepts this paper's
     * questions assess, for the students who attempted it — plain SQL via
     * the already-correct ENGAGED_WITH source table, not the graph (no
     * per-paper graph edge exists from engagement into a specific assessment).
     */
    private function resolveActivityHistory(array $studentIds, array $conceptIds): array
    {
        if ($studentIds === [] || $conceptIds === [] || ! Schema::hasTable('lms_student_engagement')) {
            return ['available' => false];
        }

        $count = DB::table('lms_student_engagement')
            ->whereIn('student_id', $studentIds)
            ->whereIn('activity_id', $conceptIds)
            ->whereIn('activity_type', ['practice', 'quiz'])
            ->count();

        return [
            'available' => true,
            'related_engagement_events' => $count,
        ];
    }
}
