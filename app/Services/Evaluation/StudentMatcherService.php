<?php

namespace App\Services\Evaluation;

use Illuminate\Support\Facades\DB;

/**
 * Works out whose answer sheet this is.
 *
 * The sheets arrive as a bulk scan, so nothing in the upload says who each one
 * belongs to -- only what the reader saw in the identity block. That is matched
 * against the students actually enrolled in the paper's class for the year,
 * never against the whole school: a roll number is only unique within a class,
 * and matching school-wide would happily hand Class 9's sheet to a Class 4
 * student with the same roll number.
 *
 * Matching is tried strongest first -- GR/enrollment number, then roll number,
 * then name -- and STOPS at the first rule that yields exactly one student.
 * An ambiguous match is not a match: two students with the same normalised name
 * leave the sheet unmatched for a teacher to assign, which is the right answer,
 * because silently picking one of them puts a child's marks on another child's
 * report card.
 */
class StudentMatcherService
{
    /** Below this, the sheet is shown as unmatched even if a row came back. */
    public const MIN_NAME_CONFIDENCE = 60.0;

    /**
     * @return array<int,array{student_id:int, roll_no:string, enrollment_no:string, name:string}>
     */
    public function roster(int $tenantId, int $syear, int $standardId, int $gradeId): array
    {
        $query = DB::table('tblstudent_enrollment as e')
            ->join('tblstudent as s', 's.id', '=', 'e.student_id')
            ->where('e.sub_institute_id', $tenantId)
            ->where('e.syear', $syear)
            ->whereNull('e.drop_code');

        if ($standardId > 0) {
            $query->where('e.standard_id', $standardId);
        }

        if ($gradeId > 0) {
            $query->where('e.grade_id', $gradeId);
        }

        return $query
            // Roll number lives on the ENROLLMENT, not the student: it is
            // assigned per class per year and is only unique within one. The
            // `roll_no_1` column on `tblstudent` is a different, historical
            // field and is deliberately not used here.
            ->orderByRaw('CAST(e.roll_no AS UNSIGNED), e.roll_no')
            ->get(['s.id', 'e.roll_no', 's.enrollment_no', 's.first_name', 's.middle_name', 's.last_name'])
            ->map(fn ($row) => [
                'student_id' => (int) $row->id,
                'roll_no' => trim((string) ($row->roll_no ?? '')),
                'enrollment_no' => trim((string) ($row->enrollment_no ?? '')),
                'name' => trim(preg_replace('/\s+/u', ' ', "{$row->first_name} {$row->middle_name} {$row->last_name}") ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{roll_no:string, enrollment_no:string, name:string, confidence:float}  $detected
     * @param  array<int,array<string,mixed>>  $roster
     * @return array{student_id:int|null, identity_source:string, identity_confidence:float}
     */
    public function match(array $detected, array $roster): array
    {
        $unmatched = [
            'student_id' => null,
            'identity_source' => 'unmatched',
            'identity_confidence' => (float) ($detected['confidence'] ?? 0),
        ];

        if ($roster === []) {
            return $unmatched;
        }

        $readConfidence = max(0.0, min(100.0, (float) ($detected['confidence'] ?? 0)));

        // A GR number is unique per school by database constraint, so an exact
        // hit on it is the strongest evidence available.
        $byEnrollment = $this->uniqueBy(
            $roster,
            'enrollment_no',
            $this->normalizeCode((string) ($detected['enrollment_no'] ?? '')),
            fn (string $value) => $this->normalizeCode($value)
        );

        if ($byEnrollment !== null) {
            return [
                'student_id' => $byEnrollment,
                'identity_source' => 'enrollment_no',
                'identity_confidence' => max($readConfidence, 90.0),
            ];
        }

        // Roll numbers are unique within a class, which is exactly the scope
        // this roster was built at.
        $byRoll = $this->uniqueBy(
            $roster,
            'roll_no',
            $this->normalizeCode((string) ($detected['roll_no'] ?? '')),
            fn (string $value) => $this->normalizeCode($value)
        );

        if ($byRoll !== null) {
            return [
                'student_id' => $byRoll,
                'identity_source' => 'roll_no',
                'identity_confidence' => max($readConfidence, 85.0),
            ];
        }

        $byName = $this->uniqueBy(
            $roster,
            'name',
            $this->normalizeName((string) ($detected['name'] ?? '')),
            fn (string $value) => $this->normalizeName($value)
        );

        if ($byName !== null && $readConfidence >= self::MIN_NAME_CONFIDENCE) {
            return [
                'student_id' => $byName,
                // A handwritten name is the weakest of the three, so it is
                // labelled as such and the review screen can say so.
                'identity_source' => 'name',
                'identity_confidence' => min($readConfidence, 80.0),
            ];
        }

        return $unmatched;
    }

    /**
     * The student id when EXACTLY one roster row matches, otherwise null.
     *
     * @param  array<int,array<string,mixed>>  $roster
     */
    private function uniqueBy(array $roster, string $field, string $needle, callable $normalize): ?int
    {
        if ($needle === '') {
            return null;
        }

        $hits = [];

        foreach ($roster as $student) {
            $value = $normalize((string) $student[$field]);

            if ($value !== '' && $value === $needle) {
                $hits[] = (int) $student['student_id'];
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    /**
     * Roll and GR numbers are compared without their formatting, because a
     * student writes "07" where the register holds 7, and a GR number is
     * printed with slashes the student does not always copy.
     */
    private function normalizeCode(string $value): string
    {
        $value = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');

        if ($value === '') {
            return '';
        }

        // Purely numeric codes lose their leading zeros so "07" finds "7".
        return ctype_digit($value) ? ltrim($value, '0') ?: '0' : $value;
    }

    private function normalizeName(string $value): string
    {
        $value = strtolower(preg_replace('/[^\p{L}\s]/u', ' ', $value) ?? $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($value === '') {
            return '';
        }

        // Indian school registers order names inconsistently -- first/last on
        // the sheet, last/first in the register -- so compare the parts as a
        // set rather than as a sequence.
        $parts = explode(' ', $value);
        sort($parts);

        return implode(' ', $parts);
    }
}
