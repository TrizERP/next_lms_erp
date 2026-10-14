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
 *
 * The same is true when the identifiers DISAGREE. A GR number that belongs to
 * one student and a roll number that belongs to another is not "a GR match with
 * a typo"; it is a sheet nobody can safely name without looking at it, so it is
 * left unmatched with both students listed as the candidates.
 *
 * Whatever this returns is a SUGGESTION. It becomes an assignment only when a
 * teacher confirms it (see SheetIdentity). The roster is also the definition of
 * who is ELIGIBLE for the exam, and is what a teacher's manual choice is checked
 * against -- the matcher and the picker can never disagree about who is on the
 * paper's class list.
 */
class StudentMatcherService
{
    /** Below this, the sheet is shown as unmatched even if a row came back. */
    public const MIN_NAME_CONFIDENCE = 60.0;

    /** How many students a "could be any of these" answer names. */
    private const MAX_CANDIDATES = 6;

    /**
     * The class an exam is set for: what decides who is eligible to sit it.
     *
     * @return array{standard_id:int, grade_id:int, paper_name:string, standard_name:string, grade_name:string, subject_name:string}|null
     */
    public function paperScope(int $tenantId, int $paperId): ?array
    {
        $row = DB::table('question_paper as p')
            ->leftJoin('standard as st', 'st.id', '=', 'p.standard_id')
            ->leftJoin('academic_section as sec', 'sec.id', '=', 'p.grade_id')
            ->leftJoin('subject as sub', 'sub.id', '=', 'p.subject_id')
            ->where('p.id', $paperId)
            ->where('p.sub_institute_id', $tenantId)
            ->first(['p.standard_id', 'p.grade_id', 'p.paper_name', 'st.name as standard_name', 'sec.title as grade_name', 'sub.subject_name']);

        if (! $row) {
            return null;
        }

        return [
            'standard_id' => (int) $row->standard_id,
            'grade_id' => (int) $row->grade_id,
            'paper_name' => (string) ($row->paper_name ?? ''),
            'standard_name' => (string) ($row->standard_name ?? ''),
            'grade_name' => (string) ($row->grade_name ?? ''),
            'subject_name' => (string) ($row->subject_name ?? ''),
        ];
    }

    /**
     * Everyone eligible to sit a given paper in a given year.
     *
     * @return array<int,array{student_id:int, roll_no:string, enrollment_no:string, name:string, section_name:string}>
     */
    public function rosterForPaper(int $tenantId, int $syear, int $paperId): array
    {
        $scope = $this->paperScope($tenantId, $paperId);

        // A paper that cannot be found has no class list. Returning an empty one
        // (rather than the whole school) means nobody is eligible for it.
        return $scope === null
            ? []
            : $this->roster($tenantId, $syear, $scope['standard_id'], $scope['grade_id']);
    }

    /**
     * @param  array<int,array<string,mixed>>  $roster
     * @return array<string,mixed>|null The roster row for this student, or null if they are not eligible.
     */
    public function findEligible(array $roster, int $studentId): ?array
    {
        foreach ($roster as $student) {
            if ((int) $student['student_id'] === $studentId) {
                return $student;
            }
        }

        return null;
    }

    /**
     * Narrows an enrollment query to enrollments that have not been dropped.
     *
     * `drop_code` is NULL for an enrollment that is still on, but the real data also holds 0
     * for it: in 2026 one school's 1,932 enrollments carry 0, with no drop remark and almost
     * none with an end date, and they hold no other row -- they are current students. Reading
     * only NULL as "enrolled" would leave that school's students on no class list at all, so
     * the matcher could never find them and a teacher could not assign a sheet to them.
     * Any other value is a real drop code.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public static function stillEnrolled($query, string $alias = 'e')
    {
        return $query->where(static function ($inner) use ($alias) {
            $inner->whereNull("{$alias}.drop_code")->orWhere("{$alias}.drop_code", 0);
        });
    }

    /**
     * @return array<int,array{student_id:int, roll_no:string, enrollment_no:string, name:string, section_name:string}>
     */
    public function roster(int $tenantId, int $syear, int $standardId, int $gradeId): array
    {
        $query = DB::table('tblstudent_enrollment as e')
            ->join('tblstudent as s', 's.id', '=', 'e.student_id')
            ->leftJoin('division as d', 'd.id', '=', 'e.section_id')
            ->where('e.sub_institute_id', $tenantId)
            ->where('e.syear', $syear);

        self::stillEnrolled($query);

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
            ->get(['s.id', 'e.roll_no', 's.enrollment_no', 's.first_name', 's.middle_name', 's.last_name', 'd.name as section_name'])
            // A student can hold one enrollment row per term in a year. Each is
            // the same child, so the roster lists them once -- otherwise a
            // perfectly unique GR number would look like two hits.
            ->unique('id')
            ->map(fn ($row) => [
                'student_id' => (int) $row->id,
                'roll_no' => trim((string) ($row->roll_no ?? '')),
                'enrollment_no' => trim((string) ($row->enrollment_no ?? '')),
                'name' => trim(preg_replace('/\s+/u', ' ', "{$row->first_name} {$row->middle_name} {$row->last_name}") ?? ''),
                'section_name' => trim((string) ($row->section_name ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{roll_no:string, enrollment_no:string, name:string, confidence:float}  $detected
     * @param  array<int,array<string,mixed>>  $roster
     * @return array{student_id:int|null, identity_source:string, identity_confidence:float, issue:string|null, candidates:array<int,int>}
     */
    public function match(array $detected, array $roster): array
    {
        $readConfidence = max(0.0, min(100.0, (float) ($detected['confidence'] ?? 0)));

        $enrollmentNeedle = $this->normalizeCode((string) ($detected['enrollment_no'] ?? ''));
        $rollNeedle = $this->normalizeCode((string) ($detected['roll_no'] ?? ''));
        $nameNeedle = $this->normalizeName((string) ($detected['name'] ?? ''));

        if ($enrollmentNeedle === '' && $rollNeedle === '' && $nameNeedle === '') {
            return $this->unmatched($readConfidence, SheetIdentity::ISSUE_UNREADABLE);
        }

        if ($roster === []) {
            return $this->unmatched($readConfidence, SheetIdentity::ISSUE_NOT_ON_ROLL);
        }

        // A handwritten name is only evidence when the read of it was confident.
        $nameUsable = $nameNeedle !== '' && $readConfidence >= self::MIN_NAME_CONFIDENCE;

        $byEnrollment = $this->hits($roster, 'enrollment_no', $enrollmentNeedle, fn (string $v) => $this->normalizeCode($v));
        $byRoll = $this->hits($roster, 'roll_no', $rollNeedle, fn (string $v) => $this->normalizeCode($v));
        $byName = $nameUsable ? $this->hits($roster, 'name', $nameNeedle, fn (string $v) => $this->normalizeName($v)) : [];

        // Every student a field points at ON ITS OWN. A field that matches two
        // students (a roll number shared by two sections) points at no one, but
        // it still counts against anyone it excludes -- see below.
        $pointed = [];

        foreach ([$byEnrollment, $byRoll, $byName] as $hits) {
            if (count($hits) === 1) {
                $pointed[] = $hits[0];
            }
        }

        $pointed = array_values(array_unique($pointed));

        if (count($pointed) > 1) {
            return $this->unmatched($readConfidence, SheetIdentity::ISSUE_CONFLICT, $pointed);
        }

        if (count($pointed) === 1) {
            $student = $pointed[0];

            // The one student the strongest field names -- unless another field
            // names SOMEONE ELSE (even among several), in which case the sheet
            // contradicts itself and is not safe to name.
            foreach ([$byEnrollment, $byRoll, $byName] as $hits) {
                if ($hits !== [] && ! in_array($student, $hits, true)) {
                    return $this->unmatched(
                        $readConfidence,
                        SheetIdentity::ISSUE_CONFLICT,
                        array_values(array_unique(array_merge([$student], $hits)))
                    );
                }
            }

            // A GR number is the strongest evidence available, a roll number the
            // next, a name the weakest and is labelled as such. NONE of them is
            // guaranteed unique in the data (GR numbers repeat in thousands of
            // cases, roll numbers repeat across the sections of a class), which is
            // why a field only counts when it names exactly ONE student on this
            // roster, and why two students sharing a value is "ambiguous", never a
            // pick.
            if ($byEnrollment === [$student]) {
                return $this->suggest($student, 'enrollment_no', max($readConfidence, 90.0));
            }

            if ($byRoll === [$student]) {
                return $this->suggest($student, 'roll_no', max($readConfidence, 85.0));
            }

            return $this->suggest($student, 'name', min($readConfidence, 80.0));
        }

        // Nobody pointed at on their own. Say why, with the students in the running.
        $several = array_values(array_unique(array_merge(
            count($byEnrollment) > 1 ? $byEnrollment : [],
            count($byRoll) > 1 ? $byRoll : [],
            count($byName) > 1 ? $byName : []
        )));

        if ($several !== []) {
            return $this->unmatched($readConfidence, SheetIdentity::ISSUE_AMBIGUOUS, $several);
        }

        // Only a name was read, and not confidently enough to act on. It is still
        // a useful hint for whoever picks the student by hand.
        if ($enrollmentNeedle === '' && $rollNeedle === '' && ! $nameUsable) {
            return $this->unmatched(
                $readConfidence,
                SheetIdentity::ISSUE_LOW_CONFIDENCE,
                $this->hits($roster, 'name', $nameNeedle, fn (string $v) => $this->normalizeName($v))
            );
        }

        return $this->unmatched($readConfidence, SheetIdentity::ISSUE_NOT_ON_ROLL);
    }

    /**
     * @return array{student_id:int, identity_source:string, identity_confidence:float, issue:null, candidates:array<int,int>}
     */
    private function suggest(int $studentId, string $source, float $confidence): array
    {
        return [
            'student_id' => $studentId,
            'identity_source' => $source,
            'identity_confidence' => $confidence,
            'issue' => null,
            'candidates' => [],
        ];
    }

    /**
     * @param  array<int,int>  $candidates
     * @return array{student_id:null, identity_source:string, identity_confidence:float, issue:string, candidates:array<int,int>}
     */
    private function unmatched(float $confidence, string $issue, array $candidates = []): array
    {
        return [
            'student_id' => null,
            'identity_source' => 'unmatched',
            'identity_confidence' => $confidence,
            'issue' => $issue,
            'candidates' => array_slice(array_values($candidates), 0, self::MAX_CANDIDATES),
        ];
    }

    /**
     * Every distinct student on the roster whose value for this field equals the
     * needle. Empty when the needle is empty: "nothing was read" matches no one.
     *
     * @param  array<int,array<string,mixed>>  $roster
     * @return array<int,int>
     */
    private function hits(array $roster, string $field, string $needle, callable $normalize): array
    {
        if ($needle === '') {
            return [];
        }

        $hits = [];

        foreach ($roster as $student) {
            $value = $normalize((string) $student[$field]);

            if ($value !== '' && $value === $needle) {
                $hits[] = (int) $student['student_id'];
            }
        }

        return array_values(array_unique($hits));
    }

    /**
     * Roll and GR numbers are compared without their formatting, because a
     * student writes "07" where the register holds 7, and a GR number is
     * printed with slashes the student does not always copy.
     *
     * Letters and digits are otherwise kept exactly: a code that differs in a
     * digit is a different code. If two roster entries collapse to the same
     * form, the match is ambiguous and left to a teacher, never guessed.
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
        // mb_ throughout: names on a sheet can be in Gujarati or Devanagari,
        // and a byte-wise lower-case would mangle them.
        $value = mb_strtolower(preg_replace('/[^\p{L}\p{M}\s]/u', ' ', $value) ?? $value);
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
