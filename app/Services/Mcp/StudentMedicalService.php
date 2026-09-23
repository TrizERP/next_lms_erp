<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student medical records: infirmary visits, vaccinations, growth measurements and health
 * notes.
 *
 * THIS IS THE MOST SENSITIVE DATA IN THE PLATFORM AND IT IS TREATED THAT WAY
 *
 * `student_infirmary` holds a complaint, symptoms, a disease and a treatment against a
 * named child. That is clinical information about a minor. Three rules follow, and they
 * are enforced here rather than left to a prompt:
 *
 *   1. NOTHING IS INFERRED. The service reports what a nurse wrote and never derives a
 *      condition, a severity, a pattern or a risk from it. There is no "students with
 *      recurring illness" read, because the table records visits and the judgement that
 *      several visits mean something is a clinician's, not a query's.
 *   2. THE COHORT READ CARRIES NO CLINICAL FREE TEXT. `visits()` returns the complaint
 *      and disease fields only when the caller has narrowed to one student. A list across
 *      a class returns who was seen, when, and by which doctor — enough to run an
 *      infirmary, not enough to broadcast a class's ailments into a summary or a report.
 *   3. NO OTHER MODULE MAY REACH IT. These tools are bound to `student_medical` alone in
 *      `config/ai.php`, and the module binds no student-directory tool in return, so the
 *      relationship is one-way by construction and not by filtering.
 *
 * WHAT ENFORCES ACCESS
 *
 * The MCP role gate plus `required_permission` on each tool, which is the same mechanism
 * every other tool in this platform uses, over the same menu rights the Student Medical
 * screens are already protected by. This service adds the data-shaping rules above on top
 * of it; it does not replace the permission check and does not weaken it.
 *
 * SCOPING
 *
 * All four tables carry `sub_institute_id` and `syear`, both matched against the caller's
 * token, and the student join is scoped to the institute as well — so a record naming a
 * child of another school cannot be returned whatever the id says.
 */
class StudentMedicalService
{
    /**
     * Infirmary visits.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function visits(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('student_infirmary')) {
            return ['count' => 0, 'visits' => [], 'note' => 'Infirmary visits are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $studentId = (int) ($filters['student_id'] ?? 0);

        // The clinical columns are read only for a single named child. See rule 2 above.
        $oneStudent = $studentId > 0;

        $query = $this->query($context, 'student_infirmary', 'm');

        if ($oneStudent) {
            $query->where('m.student_id', $studentId);
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            if (! empty($filters[$filter])) {
                $query->whereDate('m.date', $operator, (string) $filters[$filter]);
            }
        }

        if (! empty($filters['open_only'])) {
            // A case with no close date is still open, which is the one operational
            // question a list across a class legitimately answers.
            $query->where(function ($inner) {
                $inner->whereNull('m.medical_close_date')->orWhere('m.medical_close_date', '');
            });
        }

        $total = (clone $query)->count();
        $open = (clone $query)->where(function ($inner) {
            $inner->whereNull('m.medical_close_date')->orWhere('m.medical_close_date', '');
        })->count();

        $clinical = $oneStudent
            ? ', m.complaint, m.symptoms, m.disease, m.treatments'
            // Selected as NULL rather than omitted, so the row shape is the same either way
            // and a caller never has to branch on which keys exist.
            : ', NULL AS complaint, NULL AS symptoms, NULL AS disease, NULL AS treatments';

        $rows = $query
            ->selectRaw(
                'm.id, m.student_id, m.date, m.medical_close_date, m.medical_case_no,
                 m.doctor_name, m.doctor_contact, m.health_center, m.syear'
                .$clinical.', '.$this->studentColumns()
            )
            ->orderByDesc('m.date')
            ->orderByDesc('m.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'open_cases' => $open,
            'academic_year' => $context->academicYear,
            'clinical_detail_included' => $oneStudent,
            'visits' => $rows->map(static fn ($row) => [
                'visit_id' => (int) $row->id,
                'student_id' => (int) $row->student_id,
                'student_name' => trim((string) $row->student_name) ?: null,
                'enrollment_no' => $row->enrollment_no,
                'standard_name' => $row->standard_name,
                'division_name' => $row->division_name,
                'case_no' => $row->medical_case_no,
                'visit_date' => $row->date,
                'closed_on' => trim((string) $row->medical_close_date) ?: null,
                'open' => trim((string) $row->medical_close_date) === '',
                'doctor_name' => $row->doctor_name,
                'doctor_contact' => $row->doctor_contact,
                'health_center' => $row->health_center,
                // Present only for a single-student read. Null here means withheld by rule,
                // not absent from the record.
                'complaint' => $row->complaint,
                'symptoms' => $row->symptoms,
                'disease' => $row->disease,
                'treatments' => $row->treatments,
                'academic_year' => $row->syear,
            ])->all(),
            'rule' => $oneStudent
                ? 'Clinical detail is included because this read names one student. Nothing about the '
                    .'visits is interpreted: no condition, severity or pattern is derived from them.'
                : 'Clinical detail — complaint, symptoms, disease and treatment — is withheld from a '
                    .'read across more than one student and returned as null. Ask about one named student '
                    .'to see it. The fields are recorded; they are not missing.',
        ];
    }

    /**
     * Vaccinations recorded.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function vaccinations(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('student_vaccination')) {
            return ['count' => 0, 'vaccinations' => [], 'note' => 'Vaccinations are not recorded in this estate.'];
        }

        $query = $this->query($context, 'student_vaccination', 'm');

        if (! empty($filters['student_id'])) {
            $query->where('m.student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['vaccination_type'])) {
            $query->where('m.vaccination_type', 'like', '%'.$filters['vaccination_type'].'%');
        }

        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw(
                'm.id, m.student_id, m.vaccination_type, m.date, m.note, m.doctor_name, m.doctor_contact, m.syear, '
                .$this->studentColumns()
            )
            ->orderByDesc('m.date')
            ->limit(min(max((int) ($filters['limit'] ?? 50), 1), 200))
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'vaccinations' => $rows->map(static fn ($row) => [
                'record_id' => (int) $row->id,
                'student_id' => (int) $row->student_id,
                'student_name' => trim((string) $row->student_name) ?: null,
                'enrollment_no' => $row->enrollment_no,
                'standard_name' => $row->standard_name,
                'division_name' => $row->division_name,
                'vaccination_type' => $row->vaccination_type,
                'given_on' => $row->date,
                'note' => $row->note,
                'doctor_name' => $row->doctor_name,
                'academic_year' => $row->syear,
            ])->all(),
            'rule' => 'A student with no row here has no vaccination RECORDED. That is not the same as '
                .'unvaccinated, and nothing may report it as such.',
        ];
    }

    /**
     * Height and weight measurements.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function growth(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('student_height_weight')) {
            return ['count' => 0, 'measurements' => [], 'note' => 'Growth measurements are not recorded in this estate.'];
        }

        $query = $this->query($context, 'student_height_weight', 'm');

        if (! empty($filters['student_id'])) {
            $query->where('m.student_id', (int) $filters['student_id']);
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            if (! empty($filters[$filter])) {
                $query->whereDate('m.date', $operator, (string) $filters[$filter]);
            }
        }

        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw(
                'm.id, m.student_id, m.height, m.weight, m.date, m.doctor_name, m.syear, '.$this->studentColumns()
            )
            ->orderByDesc('m.date')
            ->limit(min(max((int) ($filters['limit'] ?? 50), 1), 200))
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'measurements' => $rows->map(static fn ($row) => [
                'record_id' => (int) $row->id,
                'student_id' => (int) $row->student_id,
                'student_name' => trim((string) $row->student_name) ?: null,
                'enrollment_no' => $row->enrollment_no,
                'standard_name' => $row->standard_name,
                'division_name' => $row->division_name,
                // Reported exactly as recorded, with no unit conversion and no derived
                // index. The columns carry no unit, so a BMI computed here would be a
                // number whose meaning nobody could check.
                'height' => $row->height,
                'weight' => $row->weight,
                'measured_on' => $row->date,
                'doctor_name' => $row->doctor_name,
                'academic_year' => $row->syear,
            ])->all(),
            'rule' => 'Height and weight are reported as recorded. The columns carry no unit, so no index '
                .'is calculated and no child is described as under or over any weight.',
        ];
    }

    /**
     * General health notes, and whether a document is attached.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function healthRecords(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('student_health')) {
            return ['count' => 0, 'records' => [], 'note' => 'Health notes are not recorded in this estate.'];
        }

        $query = $this->query($context, 'student_health', 'm');

        if (! empty($filters['student_id'])) {
            $query->where('m.student_id', (int) $filters['student_id']);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw(
                'm.id, m.student_id, m.date, m.remarks, m.file, m.file_type, m.file_size,
                 m.doctor_name, m.syear, '.$this->studentColumns()
            )
            ->orderByDesc('m.date')
            ->limit(min(max((int) ($filters['limit'] ?? 50), 1), 200))
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'records' => $rows->map(static fn ($row) => [
                'record_id' => (int) $row->id,
                'student_id' => (int) $row->student_id,
                'student_name' => trim((string) $row->student_name) ?: null,
                'enrollment_no' => $row->enrollment_no,
                'standard_name' => $row->standard_name,
                'division_name' => $row->division_name,
                'recorded_on' => $row->date,
                'remarks' => $row->remarks,
                'doctor_name' => $row->doctor_name,
                // Reported as present, never returned or linked. A medical attachment is
                // opened on the Student Health screen, behind its own permission check.
                'document_attached' => trim((string) $row->file) !== '',
                'document_type' => $row->file_type,
                'academic_year' => $row->syear,
            ])->all(),
            'rule' => 'An attached medical document is reported as present and never returned. Opening it '
                .'is done on the Student Health screen, where the existing permission check applies.',
        ];
    }

    /**
     * The join every medical read uses, scoped to institute and year on both sides.
     *
     * The student join is an inner join and is the tenant boundary: a record naming a
     * child this institute does not have cannot come back, whatever the record's own
     * institute column says.
     */
    private function query(McpRequestContext $context, string $table, string $alias): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table($table.' as '.$alias)
            ->join('tblstudent as s', function ($join) use ($alias, $institute) {
                $join->on('s.id', '=', $alias.'.student_id')
                    ->where('s.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tblstudent_enrollment as e', function ($join) use ($alias, $institute) {
                $join->on('e.student_id', '=', 's.id')
                    ->whereColumn('e.syear', '=', $alias.'.syear')
                    ->where('e.sub_institute_id', '=', $institute);
            })
            ->leftJoin('standard as std', 'std.id', '=', 'e.standard_id')
            ->leftJoin('division as div', 'div.id', '=', 'e.section_id')
            ->where($alias.'.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            $query->where($alias.'.syear', $context->academicYear);
        }

        return $query;
    }

    /** The identifying columns every medical read carries. Never more than these. */
    private function studentColumns(): string
    {
        return "s.enrollment_no,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                std.name AS standard_name, div.name AS division_name";
    }
}
