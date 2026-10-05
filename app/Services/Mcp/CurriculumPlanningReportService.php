<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Curriculum coverage and learning outcomes for the Curriculum Planning AI Stack.
 *
 * Reads the same real tables `CurriculumPlanningApiController` reads
 * (`lms_curriculum`, `lms_units`, `chapter_master`, `lms_learning_outcomes`), summarised
 * for a conversational/report read rather than the full planner grid that controller
 * builds. Nothing here is stored or computed ahead of time — every figure is a live count
 * against the institute's own rows.
 */
class CurriculumPlanningReportService
{
    /**
     * Curriculum -> unit -> chapter coverage for a subject/standard, this school year.
     */
    public function status(McpRequestContext $context, array $args): array
    {
        $tenant = $context->selectedInstituteId;
        $syear = isset($args['syear']) && $args['syear'] !== ''
            ? (int) $args['syear']
            : ($context->academicYear ?? (int) date('Y'));

        $curricula = DB::table('lms_curriculum as cur')
            ->join('subject as sub', 'sub.id', '=', 'cur.subject_id')
            ->leftJoin('standard as std', 'std.id', '=', 'cur.standard_id')
            ->where('cur.sub_institute_id', $tenant)
            ->where('cur.syear', $syear)
            ->when(! empty($args['standard_id']), fn ($q) => $q->where('cur.standard_id', $args['standard_id']))
            ->when(! empty($args['subject_id']), fn ($q) => $q->where('cur.subject_id', $args['subject_id']))
            ->orderBy('std.sort_order')
            ->orderBy('sub.subject_name')
            ->select('cur.id', 'cur.subject_id', 'sub.subject_name', 'cur.standard_id', 'std.name as standard_name', 'cur.total_marks')
            ->get();

        if ($curricula->isEmpty()) {
            return [
                'sub_institute_id' => $tenant,
                'syear' => $syear,
                'curricula' => [],
                'note' => 'No curriculum recorded for this institute, year and filters.',
            ];
        }

        $curriculumIds = $curricula->pluck('id')->all();

        $units = DB::table('lms_units')->whereIn('curriculum_id', $curriculumIds)->get()->groupBy('curriculum_id');
        $unitIds = $units->flatten()->pluck('id')->all();

        $chapters = $unitIds === []
            ? collect()
            : DB::table('chapter_master')->whereIn('unit_id', $unitIds)->get()->groupBy('unit_id');

        $result = $curricula->map(function ($curriculum) use ($units, $chapters) {
            $curriculumUnits = $units->get($curriculum->id, collect());
            $unitCount = $curriculumUnits->count();
            $chapterCount = $curriculumUnits->sum(fn ($u) => $chapters->get($u->id, collect())->count());
            $completedChapters = $curriculumUnits->sum(
                fn ($u) => $chapters->get($u->id, collect())->where('status', 'completed')->count()
            );

            return [
                'curriculum_id' => $curriculum->id,
                'subject_id' => $curriculum->subject_id,
                'subject_name' => $curriculum->subject_name,
                'standard_id' => $curriculum->standard_id,
                'standard_name' => $curriculum->standard_name,
                'units' => $unitCount,
                'chapters' => $chapterCount,
                'chapters_completed' => $completedChapters,
                'coverage_pct' => $chapterCount > 0 ? round(($completedChapters / $chapterCount) * 100, 1) : 0.0,
            ];
        })->values()->all();

        return [
            'sub_institute_id' => $tenant,
            'syear' => $syear,
            'curricula' => $result,
        ];
    }

    /**
     * Learning outcomes / competencies declared against a curriculum.
     */
    public function outcomes(McpRequestContext $context, array $args): array
    {
        $tenant = $context->selectedInstituteId;

        if (empty($args['curriculum_id'])) {
            return [
                'sub_institute_id' => $tenant,
                'outcomes' => [],
                'note' => 'A curriculum_id is required — use curriculum_planning.status to find one.',
            ];
        }

        $curriculum = DB::table('lms_curriculum')
            ->where('id', $args['curriculum_id'])
            ->where('sub_institute_id', $tenant)
            ->first();

        if ($curriculum === null) {
            return [
                'sub_institute_id' => $tenant,
                'outcomes' => [],
                'note' => 'No curriculum with that id for this institute.',
            ];
        }

        $outcomes = Schema::hasColumn('lms_learning_outcomes', 'curriculum_id')
            ? DB::table('lms_learning_outcomes')
                ->where('curriculum_id', $args['curriculum_id'])
                ->orderBy('code')
                ->get(['id', 'parent_id', 'code', 'type', 'description'])
            : collect();

        return [
            'sub_institute_id' => $tenant,
            'curriculum_id' => (int) $args['curriculum_id'],
            'outcomes' => $outcomes->map(fn ($o) => [
                'id' => $o->id,
                'parent_id' => $o->parent_id,
                'code' => $o->code,
                'type' => $o->type,
                'description' => $o->description,
            ])->values()->all(),
        ];
    }
}
