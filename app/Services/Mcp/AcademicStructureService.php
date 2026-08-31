<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The shape of the school: grades, the classes inside them, the sections inside those,
 * and the subjects taught.
 *
 * This is the tool almost every other question needs first. "Which students in 8B have
 * low attendance?" cannot be answered until "8B" is a standard id and a section id, and
 * before this existed the only way to resolve that was a JWT-gated admin endpoint the
 * lifecycle could not call.
 *
 * A note on naming, because it is genuinely confusing in this schema and getting it
 * wrong produces empty results rather than errors: the grade level is `academic_section`
 * (title, e.g. "Secondary"), the class is `standard` (name, e.g. "8"), and the section
 * is `division` (name, e.g. "B"). `grade_master` exists but is not what
 * `tblstudent_enrollment.grade_id` points at — the enrolment joins `academic_section`,
 * which is what the fee reports and the admin API both use.
 */
class AcademicStructureService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function structure(McpRequestContext $context, array $filters): array
    {
        $gradeId = isset($filters['grade_id']) ? (int) $filters['grade_id'] : null;
        $standardId = isset($filters['standard_id']) ? (int) $filters['standard_id'] : null;

        $grades = $this->grades($context);
        $standards = $this->standards($context, $gradeId);
        $divisions = $this->divisions($context);

        // Divisions are institute-wide rather than per-standard in this schema, so the
        // honest thing is to say so rather than imply a nesting that is not there.
        return [
            'grades' => $grades,
            'standards' => $standardId === null
                ? $standards
                : array_values(array_filter($standards, static fn (array $s) => $s['standard_id'] === $standardId)),
            'divisions' => $divisions,
            'counts' => [
                'grades' => count($grades),
                'standards' => count($standards),
                'divisions' => count($divisions),
            ],
            'note' => 'Divisions are defined for the institute rather than per standard, so a '
                . 'standard and a division are combined at enrolment rather than pre-paired here.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function grades(McpRequestContext $context): array
    {
        if (! Schema::hasTable('academic_section')) {
            return [];
        }

        return DB::table('academic_section')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'short_name', 'medium', 'shift'])
            ->map(static fn ($row) => [
                'grade_id' => (int) $row->id,
                'title' => $row->title,
                'short_name' => $row->short_name,
                'medium' => $row->medium,
                'shift' => $row->shift,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function standards(McpRequestContext $context, ?int $gradeId): array
    {
        if (! Schema::hasTable('standard')) {
            return [];
        }

        $query = DB::table('standard')
            ->where('sub_institute_id', $context->selectedInstituteId);

        if ($gradeId !== null && $gradeId > 0) {
            $query->where('grade_id', $gradeId);
        }

        return $query
            ->orderBy('sort_order')
            ->get(['id', 'grade_id', 'name', 'short_name', 'medium'])
            ->map(static fn ($row) => [
                'standard_id' => (int) $row->id,
                'grade_id' => $row->grade_id ? (int) $row->grade_id : null,
                'name' => $row->name,
                'short_name' => $row->short_name,
                'medium' => $row->medium,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function divisions(McpRequestContext $context): array
    {
        if (! Schema::hasTable('division')) {
            return [];
        }

        return DB::table('division')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn ($row) => [
                'division_id' => (int) $row->id,
                'name' => $row->name,
            ])
            ->all();
    }

    /**
     * The subjects taught, optionally narrowed by a text match.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function subjects(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('subject')) {
            return ['count' => 0, 'subjects' => []];
        }

        $query = DB::table('subject')
            ->where('sub_institute_id', $context->selectedInstituteId);

        // `status` is an int flag; only filter when the caller asks, because a school
        // that has never set it would otherwise get an empty catalogue.
        if (($filters['active_only'] ?? false) === true) {
            $query->where('status', 1);
        }

        $search = trim((string) ($filters['query'] ?? ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('subject_name', 'like', '%' . $search . '%')
                    ->orWhere('subject_code', 'like', '%' . $search . '%')
                    ->orWhere('short_name', 'like', '%' . $search . '%');
            });
        }

        $subjects = $query
            ->orderBy('subject_name')
            ->limit(min(max((int) ($filters['limit'] ?? 100), 1), 200))
            ->get(['id', 'subject_name', 'subject_code', 'subject_type', 'short_name', 'status'])
            ->map(static fn ($row) => [
                'subject_id' => (int) $row->id,
                'subject_name' => $row->subject_name,
                'subject_code' => $row->subject_code,
                'subject_type' => $row->subject_type,
                'short_name' => $row->short_name,
                'active' => (int) $row->status === 1,
            ])
            ->all();

        return [
            'query' => $search,
            'count' => count($subjects),
            'subjects' => $subjects,
        ];
    }
}
