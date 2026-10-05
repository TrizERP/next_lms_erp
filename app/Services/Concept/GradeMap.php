<?php

namespace App\Services\Concept;

use Illuminate\Support\Facades\DB;

/**
 * Turns a `standard` row into the class number 6-10.
 *
 * WHY THIS IS NOT A COLUMN LOOKUP
 * `standard_id` is a per-tenant surrogate - the same Class 6 is a different id on
 * every sub-institute - and there is no `grade` table on this estate. `grade_id`
 * exists in two unrelated places that share nothing but the column name:
 *   - `standard.grade_id`          the class hierarchy, beside `academic_section`
 *   - `grade_master_data.grade_id` a REPORT-CARD GRADING SCALE (A-E, with a
 *     percentage breakoff and a grade point)
 * Resolving a class through the second would map Class 6 to a letter grade and
 * silently invert every grade-order check. The standard's NAME is the only thing
 * here that states the class, which is why this parses it.
 *
 * REFUSING IS THE POINT
 * A name that yields no number returns null and is reported, never defaulted.
 * A wrongly-placed concept passes the order check while teaching Class 9 before
 * Class 6, and says nothing.
 */
class GradeMap
{
    /** Longest first, so VIII is not read as VI. */
    private const ROMAN = [
        'XII' => 12, 'XI' => 11, 'VIII' => 8, 'VII' => 7, 'VI' => 6,
        'IX' => 9, 'X' => 10, 'IV' => 4, 'V' => 5, 'III' => 3, 'II' => 2, 'I' => 1,
    ];

    /**
     * Class number from a standard's name, or null when it cannot be read.
     *
     * Handles '6', 'Class 6', 'Grade 10', 'Std 8', '7th', 'VI', 'Class IX',
     * and any of those carrying a stream or medium suffix.
     */
    public function fromName(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        $clean = strtoupper(trim($name));

        if ($clean === '') {
            return null;
        }

        // Arabic first - unambiguous and the common case here. The first standalone
        // 1-2 digit run wins, so 'Class 6 2026-27' reads 6 and not 2026.
        if (preg_match('/\b(\d{1,2})\s*(?:ST|ND|RD|TH)?\b/', $clean, $m)) {
            $n = (int) $m[1];

            if ($n >= 1 && $n <= 12) {
                return $n;
            }
        }

        // Roman as a whole word, so the X in XAVIER is not mistaken for a class.
        foreach (self::ROMAN as $roman => $n) {
            if (preg_match('/\b'.$roman.'\b/', $clean)) {
                return $n;
            }
        }

        return null;
    }

    /**
     * standard_id => class number for one tenant.
     *
     * Standards whose name yields nothing are omitted rather than included as null,
     * so a caller cannot treat "unknown" as a grade.
     */
    public function forTenant(int $subInstituteId): array
    {
        $map = [];

        $rows = DB::table('standard')
            ->where('sub_institute_id', $subInstituteId)
            ->select('id', 'name', 'short_name')
            ->get();

        foreach ($rows as $row) {
            $grade = $this->fromName($row->name) ?? $this->fromName($row->short_name);

            if ($grade !== null) {
                $map[(int) $row->id] = $grade;
            }
        }

        return $map;
    }

    /** concept_id => class number, for the concepts named. */
    public function forConcepts(array $conceptIds, int $subInstituteId): array
    {
        if ($conceptIds === []) {
            return [];
        }

        $standards = $this->forTenant($subInstituteId);
        $out = [];

        $rows = DB::table('lms_concept')
            ->whereIn('id', $conceptIds)
            ->select('id', 'standard_id')
            ->get();

        foreach ($rows as $row) {
            $grade = $standards[(int) $row->standard_id] ?? null;

            if ($grade !== null) {
                $out[(int) $row->id] = $grade;
            }
        }

        return $out;
    }
}
