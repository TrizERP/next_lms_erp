<?php

namespace App\Services\PAL\ContextGraph;

use App\Services\Mcp\CurriculumPlanningReportService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;

/**
 * Curriculum Planning Context — the curriculum-grain sibling of
 * CourseContextService/AssessmentContextService.
 *
 * Deliberately reuses CurriculumPlanningReportService's existing, already-
 * tested coverage/pacing/outcomes logic rather than re-deriving it — that
 * service is also what the Curriculum Planning AI Stack and
 * `CurriculumPlanningAgent` call, so this stays consistent with whatever
 * those report rather than computing a second, possibly-drifting answer to
 * the same question.
 */
class CurriculumContextService
{
    public function __construct(private readonly CurriculumPlanningReportService $report)
    {
    }

    public function build(int $curriculumId, ?int $tenantId = null): array
    {
        $curriculum = DB::table('lms_curriculum')
            ->where('id', $curriculumId)
            ->when($tenantId, fn ($q) => $q->where('sub_institute_id', $tenantId))
            ->first(['id', 'curriculum_name', 'board', 'framework', 'standard_id', 'subject_id', 'syear', 'total_marks', 'status', 'sub_institute_id']);

        if ($curriculum === null) {
            return [
                'available' => false,
                'message' => "Curriculum {$curriculumId} could not be resolved.",
            ];
        }

        $subInstituteId = (int) $curriculum->sub_institute_id;
        $standardId = $curriculum->standard_id !== null ? (int) $curriculum->standard_id : null;
        $subjectId = $curriculum->subject_id !== null ? (int) $curriculum->subject_id : null;
        $syear = $curriculum->syear !== null ? (int) $curriculum->syear : (int) date('Y');

        $context = new McpRequestContext(
            userId: 0,
            role: 'admin',
            selectedInstituteId: $subInstituteId,
            allowedInstituteIds: [$subInstituteId],
            userProfileId: null,
            clientId: null,
            academicYear: $syear,
            termId: null,
            isAdmin: true,
            isStudent: false,
        );

        $args = array_filter([
            'standard_id' => $standardId,
            'subject_id' => $subjectId,
            'syear' => $syear,
        ]);

        $standardName = $standardId ? DB::table('standard')->where('id', $standardId)->value('name') : null;
        $subjectColumns = ['id', 'subject_name'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('subject', 'display_name')) {
            $subjectColumns[] = 'display_name';
        }
        $subjectRow = $subjectId ? DB::table('subject')->where('id', $subjectId)->first($subjectColumns) : null;

        return [
            'available' => true,
            'curriculum' => [
                'id' => (int) $curriculum->id,
                'name' => $curriculum->curriculum_name,
                'board' => $curriculum->board,
                'framework' => $curriculum->framework,
                'total_marks' => $curriculum->total_marks !== null ? (int) $curriculum->total_marks : null,
                'status' => $curriculum->status,
            ],
            'course' => [
                'standard_id' => $standardId,
                'standard_name' => $standardName,
                'subject_id' => $subjectId,
                'subject_name' => $subjectRow ? (($subjectRow->display_name ?? null) ?: $subjectRow->subject_name) : null,
                'syear' => $syear,
            ],
            'structure_and_coverage' => $this->resolveStructureAndCoverage($context, $args, $curriculumId),
            'pacing' => $this->resolvePacing($context, $args),
            'outcomes' => $this->resolveOutcomes($context, $curriculumId),
            'recent_pacing_signals' => $this->resolveRecentSignals($subInstituteId, $curriculumId),
            'assembled_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Reuses CurriculumPlanningReportService::status() -- which scopes by
     * (standard, subject, syear) and may return more than one curriculum for
     * that scope -- and picks out the one row matching this curriculum id.
     */
    private function resolveStructureAndCoverage(McpRequestContext $context, array $args, int $curriculumId): array
    {
        try {
            $status = $this->report->status($context, $args);
        } catch (\Throwable) {
            return ['available' => false];
        }

        $match = collect($status['curricula'] ?? [])->firstWhere('curriculum_id', $curriculumId);

        if ($match === null) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'units' => $match['units'],
            'chapters' => $match['chapters'],
            'chapters_completed' => $match['chapters_completed'],
            'coverage_pct' => $match['coverage_pct'],
        ];
    }

    /** Reuses CurriculumPlanningReportService::lessonPace(), same scope. */
    private function resolvePacing(McpRequestContext $context, array $args): array
    {
        try {
            $pace = $this->report->lessonPace($context, $args);
        } catch (\Throwable) {
            return ['available' => false];
        }

        if (($pace['total_periods'] ?? 0) === 0) {
            return ['available' => false, 'note' => $pace['note'] ?? 'No lesson plan periods found for this curriculum scope.'];
        }

        return [
            'available' => true,
            'total_periods' => $pace['total_periods'],
            'completion_rate_pct' => $pace['completion_rate_pct'],
            'behind_schedule_count' => $pace['behind_schedule_count'],
            'behind_schedule_periods' => array_slice($pace['behind_schedule_periods'] ?? [], 0, 10),
            'upcoming_periods' => array_slice($pace['upcoming_periods'] ?? [], 0, 10),
        ];
    }

    /** Reuses CurriculumPlanningReportService::outcomes(), curriculum_id-scoped. */
    private function resolveOutcomes(McpRequestContext $context, int $curriculumId): array
    {
        try {
            $outcomes = $this->report->outcomes($context, ['curriculum_id' => $curriculumId]);
        } catch (\Throwable) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'total_outcomes' => $outcomes['total_outcomes'] ?? 0,
            'outcomes' => array_slice($outcomes['outcomes'] ?? [], 0, 20),
        ];
    }

    /**
     * Governed pacing-gap signals/cases the CurriculumPlanningAgent has
     * raised for this curriculum, if any. Honestly reports zero rather than
     * inferring a pacing problem itself -- that inference is the agent's
     * job, not this context assembler's.
     */
    private function resolveRecentSignals(int $subInstituteId, int $curriculumId): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ai_signals')) {
            return ['available' => false];
        }

        $signals = DB::table('ai_signals')
            ->where('subject_entity_key', 'curriculum')
            ->where('subject_id', $curriculumId)
            ->where('sub_institute_id', $subInstituteId)
            ->orderByDesc('detected_at')
            ->limit(5)
            ->get(['id', 'signal_key', 'severity', 'score', 'status', 'detected_at']);

        return [
            'available' => true,
            'count' => $signals->count(),
            'signals' => $signals->map(fn ($s) => [
                'id' => (int) $s->id,
                'signal_key' => $s->signal_key,
                'severity' => $s->severity,
                'score' => $s->score !== null ? (float) $s->score : null,
                'status' => $s->status,
                'detected_at' => $s->detected_at,
            ])->all(),
        ];
    }
}
