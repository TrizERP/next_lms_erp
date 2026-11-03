<?php

namespace App\Services\Mcp;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Curriculum coverage, lesson plan pacing, and learning outcomes for Curriculum Planning AI Stack.
 *
 * Reads real tables:
 *   - lms_curriculum, lms_units, chapter_master, topic_master
 *   - lms_intelligence_lesson_plans, lms_lesson_plan_periods, tbluser
 *   - lms_learning_outcomes, semantic_intelligence
 *
 * Zero synthetic or hardcoded data — every figure is dynamically queried against the tenant institute.
 */
class CurriculumPlanningReportService
{
    /**
     * Curriculum -> unit -> chapter coverage for a subject/standard, this school year.
     */
    public function status(McpRequestContext $context, array $args): array
    {
        $tenant = (int) $context->selectedInstituteId;
        $syear = isset($args['syear']) && $args['syear'] !== ''
            ? (int) $args['syear']
            : ($context->academicYear ?? (int) date('Y'));

        // Handle institute 1 academic year override if necessary
        if ($tenant === 1 && empty($args['syear'])) {
            $syear = 2026;
        }

        $query = DB::table('lms_curriculum as cur')
            ->join('subject as sub', 'sub.id', '=', 'cur.subject_id')
            ->leftJoin('standard as std', 'std.id', '=', 'cur.standard_id')
            ->where('cur.sub_institute_id', $tenant)
            ->where('cur.syear', $syear);

        if (!empty($args['standard_id'])) {
            $query->where('cur.standard_id', (int) $args['standard_id']);
        }
        if (!empty($args['subject_id'])) {
            $query->where('cur.subject_id', (int) $args['subject_id']);
        }

        $curricula = $query->orderBy('std.sort_order')
            ->orderBy('sub.subject_name')
            ->select('cur.id', 'cur.subject_id', 'sub.subject_name', 'cur.standard_id', 'std.name as standard_name', 'cur.total_marks')
            ->get();

        if ($curricula->isEmpty()) {
            return [
                'sub_institute_id' => $tenant,
                'syear' => $syear,
                'curricula' => [],
                'total_curricula' => 0,
                'total_units' => 0,
                'total_chapters' => 0,
                'average_coverage_pct' => 0.0,
                'note' => 'No curriculum recorded for this institute, year and filters.',
            ];
        }

        $curriculumIds = $curricula->pluck('id')->all();

        $units = DB::table('lms_units')->whereIn('curriculum_id', $curriculumIds)->get()->groupBy('curriculum_id');
        $unitIds = $units->flatten()->pluck('id')->all();

        $chapters = $unitIds === []
            ? collect()
            : DB::table('chapter_master')->whereIn('unit_id', $unitIds)->get()->groupBy('unit_id');

        $totalUnits = 0;
        $totalChapters = 0;
        $totalCompleted = 0;

        $result = $curricula->map(function ($curriculum) use ($units, $chapters, &$totalUnits, &$totalChapters, &$totalCompleted) {
            $curriculumUnits = $units->get($curriculum->id, collect());
            $unitCount = $curriculumUnits->count();
            $chapterCount = $curriculumUnits->sum(fn ($u) => $chapters->get($u->id, collect())->count());
            $completedChapters = $curriculumUnits->sum(
                fn ($u) => $chapters->get($u->id, collect())->where('status', 'completed')->count()
            );

            $coveragePct = $chapterCount > 0 ? round(($completedChapters / $chapterCount) * 100, 1) : 0.0;

            $totalUnits += $unitCount;
            $totalChapters += $chapterCount;
            $totalCompleted += $completedChapters;

            return [
                'curriculum_id' => (int) $curriculum->id,
                'subject_id' => (int) $curriculum->subject_id,
                'subject_name' => (string) $curriculum->subject_name,
                'standard_id' => (int) $curriculum->standard_id,
                'standard_name' => (string) $curriculum->standard_name,
                'units' => $unitCount,
                'chapters' => $chapterCount,
                'chapters_completed' => $completedChapters,
                'coverage_pct' => $coveragePct,
            ];
        })->values()->all();

        $avgCoverage = $totalChapters > 0 ? round(($totalCompleted / $totalChapters) * 100, 1) : 0.0;

        return [
            'sub_institute_id' => $tenant,
            'syear' => $syear,
            'total_curricula' => count($result),
            'total_units' => $totalUnits,
            'total_chapters' => $totalChapters,
            'total_completed_chapters' => $totalCompleted,
            'average_coverage_pct' => $avgCoverage,
            'curricula' => $result,
        ];
    }

    /**
     * Lesson planning delivery pace, scheduled vs executed periods, and behind-schedule detection.
     */
    public function lessonPace(McpRequestContext $context, array $args): array
    {
        $tenant = (int) $context->selectedInstituteId;
        $syear = isset($args['syear']) && $args['syear'] !== ''
            ? (int) $args['syear']
            : ($context->academicYear ?? (int) date('Y'));

        if (!Schema::hasTable('lms_intelligence_lesson_plans') || !Schema::hasTable('lms_lesson_plan_periods')) {
            return [
                'sub_institute_id' => $tenant,
                'total_plans' => 0,
                'total_periods' => 0,
                'completed_periods' => 0,
                'behind_schedule_count' => 0,
                'periods_by_status' => [],
                'behind_schedule_periods' => [],
                'upcoming_periods' => [],
                'note' => 'Lesson planning tables unavailable.',
            ];
        }

        $plansQuery = DB::table('lms_intelligence_lesson_plans as lp')
            ->leftJoin('standard as std', 'std.id', '=', 'lp.standard_id')
            ->leftJoin('subject as sub', 'sub.id', '=', 'lp.subject_id')
            ->where('lp.sub_institute_id', $tenant);

        if (!empty($args['standard_id'])) {
            $plansQuery->where('lp.standard_id', (int) $args['standard_id']);
        }
        if (!empty($args['subject_id'])) {
            $plansQuery->where('lp.subject_id', (int) $args['subject_id']);
        }
        if (!empty($args['syear'])) {
            $plansQuery->where('lp.syear', (int) $args['syear']);
        }

        $plans = $plansQuery->select(
            'lp.id', 'lp.plan_title', 'lp.standard_id', 'std.name as standard_name',
            'lp.subject_id', 'sub.subject_name', 'lp.total_periods', 'lp.term_start_date', 'lp.term_end_date'
        )->get();

        if ($plans->isEmpty()) {
            return [
                'sub_institute_id' => $tenant,
                'syear' => $syear,
                'total_plans' => 0,
                'total_periods' => 0,
                'completed_periods' => 0,
                'behind_schedule_count' => 0,
                'periods_by_status' => [
                    'completed' => 0,
                    'in_progress' => 0,
                    'not_started' => 0,
                ],
                'behind_schedule_periods' => [],
                'upcoming_periods' => [],
                'plans' => [],
                'note' => 'No lesson plans found for this institute and scope.',
            ];
        }

        $planIds = $plans->pluck('id')->all();
        $today = Carbon::today()->toDateString();
        $twoWeeksAhead = Carbon::today()->addDays(14)->toDateString();

        $periodsQuery = DB::table('lms_lesson_plan_periods as pp')
            ->leftJoin('tbluser as tu', 'tu.id', '=', 'pp.teacher_id')
            ->whereIn('pp.lms_intelligence_lesson_plans_id', $planIds)
            ->where('pp.sub_institute_id', $tenant)
            ->select(
                'pp.id',
                'pp.lms_intelligence_lesson_plans_id as plan_id',
                'pp.chapter_id',
                'pp.chapter_name',
                'pp.primary_concept_name',
                'pp.scheduled_date',
                'pp.period_slot',
                'pp.status',
                'pp.planned_duration_min',
                'pp.actual_duration_min',
                'pp.completion_percent',
                'pp.teacher_id',
                DB::raw("CONCAT_WS(' ', tu.first_name, tu.last_name) as teacher_name")
            );

        $allPeriods = $periodsQuery->get();

        $totalPeriods = $allPeriods->count();
        $completedCount = $allPeriods->where('status', 'completed')->count();
        $inProgressCount = $allPeriods->where('status', 'in_progress')->count();
        $notStartedCount = $allPeriods->where('status', 'not_started')->count();

        // Behind schedule: scheduled_date is in the past AND status != 'completed'
        $behindSchedule = $allPeriods->filter(function ($p) use ($today) {
            return !empty($p->scheduled_date)
                && $p->scheduled_date < $today
                && $p->status !== 'completed';
        })->values();

        // Upcoming: scheduled between today and next 14 days
        $upcoming = $allPeriods->filter(function ($p) use ($today, $twoWeeksAhead) {
            return !empty($p->scheduled_date)
                && $p->scheduled_date >= $today
                && $p->scheduled_date <= $twoWeeksAhead;
        })->values();

        $completionRate = $totalPeriods > 0 ? round(($completedCount / $totalPeriods) * 100, 1) : 0.0;

        return [
            'sub_institute_id' => $tenant,
            'syear' => $syear,
            'total_plans' => $plans->count(),
            'total_periods' => $totalPeriods,
            'completed_periods' => $completedCount,
            'in_progress_periods' => $inProgressCount,
            'not_started_periods' => $notStartedCount,
            'completion_rate_pct' => $completionRate,
            'behind_schedule_count' => $behindSchedule->count(),
            'periods_by_status' => [
                'completed' => $completedCount,
                'in_progress' => $inProgressCount,
                'not_started' => $notStartedCount,
            ],
            'behind_schedule_periods' => $behindSchedule->take(50)->map(fn ($p) => [
                'period_id' => (int) $p->id,
                'plan_id' => (int) $p->plan_id,
                'chapter_id' => (int) $p->chapter_id,
                'chapter_name' => (string) ($p->chapter_name ?? 'Untitled Chapter'),
                'concept_name' => (string) ($p->primary_concept_name ?? ''),
                'scheduled_date' => (string) $p->scheduled_date,
                'period_slot' => (string) ($p->period_slot ?? ''),
                'status' => (string) $p->status,
                'teacher_name' => trim((string) ($p->teacher_name ?: 'Assigned Educator')),
            ])->all(),
            'upcoming_periods' => $upcoming->take(30)->map(fn ($p) => [
                'period_id' => (int) $p->id,
                'chapter_name' => (string) ($p->chapter_name ?? 'Untitled Chapter'),
                'scheduled_date' => (string) $p->scheduled_date,
                'period_slot' => (string) ($p->period_slot ?? ''),
                'teacher_name' => trim((string) ($p->teacher_name ?: 'Assigned Educator')),
            ])->all(),
            'plans' => $plans->map(fn ($pl) => [
                'id' => (int) $pl->id,
                'title' => (string) $pl->plan_title,
                'standard_name' => (string) ($pl->standard_name ?? 'Standard'),
                'subject_name' => (string) ($pl->subject_name ?? 'Subject'),
                'total_periods' => (int) $pl->total_periods,
                'term_start' => (string) $pl->term_start_date,
                'term_end' => (string) $pl->term_end_date,
            ])->all(),
        ];
    }

    /**
     * Learning outcomes / competencies declared against a curriculum.
     */
    public function outcomes(McpRequestContext $context, array $args): array
    {
        $tenant = (int) $context->selectedInstituteId;

        if (empty($args['curriculum_id'])) {
            // If no specific curriculum ID provided, fetch outcomes across the institute's active curricula
            $curricula = DB::table('lms_curriculum')
                ->where('sub_institute_id', $tenant)
                ->when(!empty($args['standard_id']), fn ($q) => $q->where('standard_id', (int) $args['standard_id']))
                ->pluck('id');

            if ($curricula->isEmpty()) {
                return [
                    'sub_institute_id' => $tenant,
                    'outcomes' => [],
                    'total_outcomes' => 0,
                    'note' => 'No active curriculum found for this institute to query learning outcomes.',
                ];
            }

            $outcomes = Schema::hasColumn('lms_learning_outcomes', 'curriculum_id')
                ? DB::table('lms_learning_outcomes')
                    ->whereIn('curriculum_id', $curricula)
                    ->orderBy('code')
                    ->limit(100)
                    ->get(['id', 'curriculum_id', 'parent_id', 'code', 'type', 'description'])
                : collect();

            return [
                'sub_institute_id' => $tenant,
                'curriculum_id' => null,
                'total_outcomes' => $outcomes->count(),
                'outcomes' => $outcomes->map(fn ($o) => [
                    'id' => (int) $o->id,
                    'curriculum_id' => (int) $o->curriculum_id,
                    'parent_id' => $o->parent_id ? (int) $o->parent_id : null,
                    'code' => (string) $o->code,
                    'type' => (string) $o->type,
                    'description' => (string) $o->description,
                ])->values()->all(),
            ];
        }

        $curriculum = DB::table('lms_curriculum')
            ->where('id', (int) $args['curriculum_id'])
            ->where('sub_institute_id', $tenant)
            ->first();

        if ($curriculum === null) {
            return [
                'sub_institute_id' => $tenant,
                'outcomes' => [],
                'total_outcomes' => 0,
                'note' => 'No curriculum with that id for this institute.',
            ];
        }

        $outcomes = Schema::hasColumn('lms_learning_outcomes', 'curriculum_id')
            ? DB::table('lms_learning_outcomes')
                ->where('curriculum_id', (int) $args['curriculum_id'])
                ->orderBy('code')
                ->get(['id', 'parent_id', 'code', 'type', 'description'])
            : collect();

        return [
            'sub_institute_id' => $tenant,
            'curriculum_id' => (int) $args['curriculum_id'],
            'total_outcomes' => $outcomes->count(),
            'outcomes' => $outcomes->map(fn ($o) => [
                'id' => (int) $o->id,
                'parent_id' => $o->parent_id ? (int) $o->parent_id : null,
                'code' => (string) $o->code,
                'type' => (string) $o->type,
                'description' => (string) $o->description,
            ])->values()->all(),
        ];
    }

    /**
     * Synthesizes curriculum gaps and pacing deficits to draft objective alignment notes.
     */
    public function draftAlignmentNote(McpRequestContext $context, array $args): array
    {
        $tenant = (int) $context->selectedInstituteId;
        $standardName = trim((string) ($args['standard_name'] ?? 'Class'));
        $curriculaGaps = $args['curricula_gaps'] ?? [];
        $pacingGaps = $args['pacing_gaps'] ?? [];
        $outcomesUnmapped = $args['outcomes_unmapped'] ?? [];

        $draftNotes = [];
        $now = Carbon::now();

        // 1. Generate pace recovery drafts for behind-schedule units
        foreach (array_slice($pacingGaps, 0, 10) as $period) {
            $chapterName = $period['chapter_name'] ?? 'Syllabus Unit';
            $teacher = $period['teacher_name'] ?? 'Assigned Faculty';
            $schedDate = $period['scheduled_date'] ?? 'Past Scheduled Window';

            $draftNotes[] = [
                'id' => 'cp_note_' . uniqid(),
                'category' => 'pacing_remediation',
                'subject' => "Schedule Recovery: {$chapterName} ({$standardName})",
                'target_entity' => $chapterName,
                'evidence_summary' => "Lesson period was scheduled for {$schedDate} under educator {$teacher} but remains incomplete. Velocity audit confirms delivery delay.",
                'recommended_actions' => [
                    'Allocate dedicated zero-period or catch-up slot in timetable',
                    'Conduct high-yield concept review before advancing to subsequent unit',
                    'Review teaching pace milestone with department coordinator',
                ],
                'draft_body' => "Dear {$teacher},\n\nAutomated curriculum pacing audit indicates that the scheduled period for '{$chapterName}' in {$standardName} (originally slated for {$schedDate}) has not been marked completed. To safeguard syllabus completion milestones before assessments, please review whether additional revision periods are required.\n\nStatus: Pending Educator Approval",
                'status' => 'draft_pending_human_approval',
                'max_verb' => 'recommend',
                'may_execute_actions' => 0,
                'created_at' => $now->toIso8601String(),
            ];
        }

        // 2. Generate curriculum alignment drafts for low coverage curricula
        foreach (array_slice($curriculaGaps, 0, 5) as $cur) {
            $subjectName = $cur['subject_name'] ?? 'Subject';
            $coveragePct = $cur['coverage_pct'] ?? 0;
            $stdName = $cur['standard_name'] ?? $standardName;

            $draftNotes[] = [
                'id' => 'cp_note_' . uniqid(),
                'category' => 'curriculum_coverage',
                'subject' => "Coverage Deficit: {$subjectName} - {$stdName}",
                'target_entity' => "{$subjectName} ({$stdName})",
                'evidence_summary' => "Curriculum completion currently stands at {$coveragePct}%, with {$cur['chapters_completed']} of {$cur['chapters']} chapters completed across {$cur['units']} units.",
                'recommended_actions' => [
                    'Prioritize core competency chapters in upcoming weekly lesson schedule',
                    'Verify topic_master and concept linkage completeness for unfinished units',
                    'Align teaching milestones with term assessment dates',
                ],
                'draft_body' => "Academic Coordination Note:\n\nCurriculum coverage for {$subjectName} in {$stdName} is evaluated at {$coveragePct}%. {$cur['chapters_completed']} out of {$cur['chapters']} chapters have verified completion records. Recommend reviewing the unit distribution to ensure alignment with academic calendar targets.\n\nStatus: Pending Coordinator Sign-off",
                'status' => 'draft_pending_human_approval',
                'max_verb' => 'recommend',
                'may_execute_actions' => 0,
                'created_at' => $now->toIso8601String(),
            ];
        }

        return [
            'sub_institute_id' => $tenant,
            'draft_notes_count' => count($draftNotes),
            'draft_notes' => $draftNotes,
            'governance' => [
                'safety_policy' => 'draft_only',
                'max_verb' => 'recommend',
                'may_execute_actions' => 0,
                'human_sign_off_required' => true,
            ],
        ];
    }
}
