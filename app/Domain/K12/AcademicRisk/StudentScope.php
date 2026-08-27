<?php

namespace App\Domain\K12\AcademicRisk;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves which students a detector may look at, and their names.
 *
 * Shared by every academic detector so the tenant filter is written once. It matters
 * more here than elsewhere: `lms_online_exam` has no `sub_institute_id` of its own, so
 * the only thing keeping one school's exam rows out of another school's analysis is
 * that the student id set was scoped first. Every detector starts from this class.
 */
class StudentScope
{
    /**
     * Students in scope, as id => display name.
     *
     * @return array<int, string>
     */
    public function students(McpRequestContext $context, ?array $studentIds = null, int $limit = 500): array
    {
        if (! Schema::hasTable('tblstudent')) {
            return [];
        }

        $query = DB::table('tblstudent')
            ->where('sub_institute_id', $context->selectedInstituteId);

        // Belt and braces: the selected institute has already been checked against the
        // allowed set by McpContextResolver, but re-stating it here means a detector
        // constructed with a hand-built context still cannot stray.
        if ($context->allowedInstituteIds !== []) {
            $query->whereIn('sub_institute_id', $context->allowedInstituteIds);
        }

        if (Schema::hasColumn('tblstudent', 'student_inactive')) {
            $query->where(function ($inner) {
                $inner->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1);
            });
        }

        if ($studentIds !== null) {
            if ($studentIds === []) {
                return [];
            }

            $query->whereIn('id', $studentIds);
        }

        // A student the caller cannot see is simply absent from the result, which is
        // what makes "detect for this student" safe to expose conversationally.
        return $query->limit($limit)
            ->get(['id', 'first_name', 'middle_name', 'last_name'])
            ->mapWithKeys(fn ($row) => [
                (int) $row->id => trim(implode(' ', array_filter([
                    $row->first_name,
                    $row->last_name,
                ]))) ?: ('Student #' . $row->id),
            ])
            ->all();
    }

    /**
     * A single student's name, or null when out of scope.
     */
    public function name(int $studentId, McpRequestContext $context): ?string
    {
        return $this->students($context, [$studentId], 1)[$studentId] ?? null;
    }

    /**
     * The student's current class placement, used to word explanations and to route
     * an intervention to the right teacher.
     *
     * @return array{standard_id:int|null, section_id:int|null, standard_name:string|null, division_name:string|null}|null
     */
    public function placement(int $studentId, McpRequestContext $context): ?array
    {
        if (! Schema::hasTable('tblstudent_enrollment')) {
            return null;
        }

        $query = DB::table('tblstudent_enrollment')
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $context->selectedInstituteId);

        if ($context->academicYear !== null) {
            $query->where('syear', $context->academicYear);
        }

        $enrollment = $query->orderByDesc('id')->first();

        if (! $enrollment) {
            return null;
        }

        $standardName = Schema::hasTable('standard')
            ? DB::table('standard')->where('id', $enrollment->standard_id)->value('name')
            : null;

        $divisionName = Schema::hasTable('division')
            ? DB::table('division')->where('id', $enrollment->section_id)->value('name')
            : null;

        return [
            'standard_id' => $enrollment->standard_id ? (int) $enrollment->standard_id : null,
            'section_id' => $enrollment->section_id ? (int) $enrollment->section_id : null,
            'standard_name' => $standardName,
            'division_name' => $divisionName,
        ];
    }
}
