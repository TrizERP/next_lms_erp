<?php

namespace App\CareerIntelligence\Ingestion;

use Illuminate\Support\Facades\DB;

/**
 * The only class allowed to query the ERP's subject-enrolment tables for
 * Career Intelligence. Everything else in CAI reads a DeclaredPlan, never
 * `sub_std_map`/`subject`/`student_optional_subject` directly.
 *
 * Data model (confirmed against real enrolment data, not the schema alone):
 *   - tblstudent_enrollment: student -> standard_id for a given syear.
 *   - standard: the class/section master. grade_id is a grade-BAND grouping
 *     id, NOT the class number — the class number is embedded in
 *     standard.name (e.g. "9", "CBSE-9", "11 Sci."). This ERP has no
 *     first-class "stream" attribute either: grades 11-12 encode stream as a
 *     name suffix (Sci./Com./Arts/Hum.); grades 9-10 never carry one, since
 *     CBSE streaming starts after the Grade 10 boards — a null stream for
 *     those grades is the correct, expected answer, not a failure.
 *   - sub_std_map: which subjects apply to a standard, and whether each is
 *     compulsory or elective for that class (elective_subject Yes/No) and
 *     whether it actually counts for grading (allow_grades Yes/No) — the
 *     same allow_grades='Yes' filter existing report-card code
 *     (marks_entry_controller.php, studentResultController.php) already
 *     uses to decide which subjects are real academic subjects versus
 *     administrative/co-curricular rows (library periods, PT, value
 *     education, periodic-test placeholders, ...).
 *   - student_optional_subject: which elective a specific student actually
 *     opted into, for compulsory subjects every student in the standard
 *     takes it automatically.
 */
class ErpSubjectEnrolmentAdapter implements SubjectEnrolmentAdapter
{
    public function __construct(
        private readonly SubjectNormaliser $normaliser = new ErpSubjectNormaliser(),
    ) {
    }

    public function fetch(string $studentId, string $academicYear): DeclaredPlan
    {
        $syear = $this->resolveSyear($academicYear);

        $enrollment = DB::table('tblstudent_enrollment as se')
            ->join('standard as st', 'st.id', '=', 'se.standard_id')
            ->where('se.student_id', $studentId)
            ->where('se.syear', $syear)
            ->orderByDesc('se.id') // most recent row wins on a mid-year class change
            ->select('se.sub_institute_id', 'se.standard_id', 'st.name as standard_name')
            ->first();

        if (! $enrollment) {
            return $this->unresolved(
                $studentId,
                $academicYear,
                grade: 0,
                stream: null,
                reason: "No class enrolment found for student {$studentId} in {$academicYear}.",
                raw: [],
            );
        }

        $grade = $this->resolveGrade($enrollment->standard_name);
        if ($grade === null) {
            return $this->unresolved(
                $studentId,
                $academicYear,
                grade: 0,
                stream: null,
                reason: "Could not determine a numeric grade from the class name \"{$enrollment->standard_name}\".",
                raw: [(array) $enrollment],
            );
        }

        $stream = $this->resolveStream($enrollment->standard_name);
        $raw = [(array) $enrollment];

        $subjectRows = DB::table('sub_std_map as sm')
            ->join('subject as sub', 'sub.id', '=', 'sm.subject_id')
            ->where('sm.sub_institute_id', $enrollment->sub_institute_id)
            ->where('sm.standard_id', $enrollment->standard_id)
            ->where('sm.allow_grades', 'Yes')
            // `allow_grades='Yes'` alone isn't sufficient to mean "this is a
            // curriculum subject the student studies" — this ERP also uses
            // `sub_std_map.subject_category` for non-curriculum catalog rows
            // (confirmed: 'STEM Resources' rows exist with allow_grades='Yes'
            // but zero real question/exam-paper content anywhere in the ERP,
            // e.g. "Physical Science"/"Life Science"/"Earth & Space Science"/
            // "Engineering Design" on standard_id=42 — these are supplementary
            // resource-library entries, not subjects ever actually taught or
            // assessed, and their unmapped labels were hard-failing every
            // DeclaredPlan for standards that carry them). Excluded by
            // blocklisting the specific known-non-curriculum value rather than
            // allow-listing 'My Course', since `subject_category` is a
            // free-text field with real variance across tenants (e.g. a
            // 'My Courses' typo-variant elsewhere in this same table) — an
            // allow-list would silently drop other tenants' real curriculum
            // subjects. NULL-safe: `subject_category` is NULL for most
            // tenants' rows (697 gradeable rows ERP-wide) and must stay
            // included.
            ->where(function ($query) {
                $query->whereNull('sm.subject_category')
                    ->orWhere('sm.subject_category', '!=', 'STEM Resources');
            })
            ->select('sub.id as subject_id', 'sub.subject_name', 'sm.elective_subject')
            ->get();

        $canonicalSubjects = [];
        $unresolvedLabels = [];

        foreach ($subjectRows as $row) {
            if ($row->elective_subject === 'Yes') {
                $optedIn = DB::table('student_optional_subject')
                    ->where('student_id', $studentId)
                    ->where('subject_id', $row->subject_id)
                    ->where('syear', $syear)
                    ->exists();

                if (! $optedIn) {
                    continue; // offered to the class, not chosen by this student
                }
            }

            $raw[] = (array) $row;

            $canonical = $this->normaliser->toCanonical($row->subject_name);
            if ($canonical === null) {
                $unresolvedLabels[] = $row->subject_name;
                continue;
            }

            $canonicalSubjects[$canonical] = true; // dedupe, e.g. two rows mapping to SCIENCE
        }

        if (! empty($unresolvedLabels)) {
            $labels = implode(', ', array_map(
                fn (string $label) => '"' . $label . '"',
                array_unique($unresolvedLabels)
            ));

            return $this->unresolved(
                $studentId,
                $academicYear,
                $grade,
                $stream,
                reason: "Could not map subject label(s) to a canonical subject: {$labels}.",
                raw: $raw,
                subjects: array_keys($canonicalSubjects),
            );
        }

        if (empty($canonicalSubjects)) {
            return $this->unresolved(
                $studentId,
                $academicYear,
                $grade,
                $stream,
                reason: "No gradeable subjects are configured for this student's class ({$enrollment->standard_name}).",
                raw: $raw,
            );
        }

        return new DeclaredPlan(
            studentId: $studentId,
            academicYear: $academicYear,
            grade: $grade,
            stream: $stream,
            subjects: array_keys($canonicalSubjects),
            resolved: true,
            unresolvedReason: null,
            raw: $raw,
        );
    }

    private function unresolved(
        string $studentId,
        string $academicYear,
        int $grade,
        ?string $stream,
        string $reason,
        array $raw,
        array $subjects = [],
    ): DeclaredPlan {
        return new DeclaredPlan(
            studentId: $studentId,
            academicYear: $academicYear,
            grade: $grade,
            stream: $stream,
            subjects: $subjects,
            resolved: false,
            unresolvedReason: $reason,
            raw: $raw,
        );
    }

    /**
     * The ERP stores a single 4-digit year (e.g. "2022"); CAI's academicYear
     * convention is a range (e.g. "2026-2027"). Take the first 4-digit
     * number found — the same convention the frontend already uses
     * (normalizeAcademicYear in lib/erp-client.ts).
     */
    private function resolveSyear(string $academicYear): string
    {
        return preg_match('/\d{4}/', $academicYear, $matches) ? $matches[0] : $academicYear;
    }

    private function resolveGrade(string $standardName): ?int
    {
        return preg_match('/(\d+)/', $standardName, $matches) ? (int) $matches[1] : null;
    }

    private function resolveStream(string $standardName): ?string
    {
        $upper = strtoupper($standardName);

        if (preg_match('/\bSCI\b|SCIENCE/', $upper)) {
            return 'SCIENCE';
        }
        if (preg_match('/\bCOM\b|COMMERCE/', $upper)) {
            return 'COMMERCE';
        }
        if (preg_match('/\bARTS?\b|\bHUM\b|HUMANITIES/', $upper)) {
            return 'ARTS';
        }

        return null;
    }
}
