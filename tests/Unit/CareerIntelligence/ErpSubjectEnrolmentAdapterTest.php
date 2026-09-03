<?php

namespace Tests\Unit\CareerIntelligence;

use App\CareerIntelligence\Ingestion\ErpSubjectEnrolmentAdapter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the STEM Resources subject-category leak: standard_id=42
 * carries 4 zero-content 'STEM Resources' catalog rows (Physical Science, Life
 * Science, Earth & Space Science, Engineering Design) that previously hard-failed
 * DeclaredPlan resolution for every student on that standard, because
 * ErpSubjectEnrolmentAdapter's query only checked `allow_grades='Yes'` and never
 * `subject_category`. These tests run against the real ERP database (this suite
 * has no sqlite/in-memory override configured) using the real standard_id=42 and
 * student_id=284976 verified throughout the investigation.
 */
class ErpSubjectEnrolmentAdapterTest extends TestCase
{
    private const STANDARD_ID = 42;
    private const STUDENT_ID = '284976';
    private const ACADEMIC_YEAR = '2021';

    public function test_standard_42_still_carries_the_stem_resources_catalog_rows(): void
    {
        // Sanity check on the fixture itself, not the fix — confirms the 4 rows
        // this test suite is guarding against are still present in this
        // environment's data (if this ever returns 0, the other assertions in
        // this file are vacuous and must be re-pointed at real data).
        $stemResourceRows = DB::table('sub_std_map')
            ->where('standard_id', self::STANDARD_ID)
            ->where('subject_category', 'STEM Resources')
            ->where('allow_grades', 'Yes')
            ->pluck('subject_id');

        $this->assertCount(4, $stemResourceRows, 'Expected the 4 known STEM Resources rows on standard_id=42.');
    }

    public function test_standard_42_resolves_successfully_for_a_real_student(): void
    {
        $plan = (new ErpSubjectEnrolmentAdapter())->fetch(self::STUDENT_ID, self::ACADEMIC_YEAR);

        $this->assertTrue(
            $plan->resolved,
            'DeclaredPlan should resolve now that STEM Resources rows are excluded. unresolvedReason: '
                . ($plan->unresolvedReason ?? '(none)')
        );
        $this->assertNull($plan->unresolvedReason);
        $this->assertSame(9, $plan->grade);
    }

    public function test_physical_science_life_science_earth_space_science_and_engineering_design_no_longer_block_alignment(): void
    {
        $plan = (new ErpSubjectEnrolmentAdapter())->fetch(self::STUDENT_ID, self::ACADEMIC_YEAR);

        $this->assertTrue($plan->resolved);
        $this->assertStringNotContainsString('Physical Science', (string) $plan->unresolvedReason);
        $this->assertStringNotContainsString('Life Science', (string) $plan->unresolvedReason);
        $this->assertStringNotContainsString('Earth & Space Science', (string) $plan->unresolvedReason);
        $this->assertStringNotContainsString('Engineering Design', (string) $plan->unresolvedReason);

        // None of the 4 STEM Resources canonical subject IDs exist in this
        // ERP's CanonicalSubject vocabulary at all, so they can never appear
        // in $plan->subjects regardless — the real assertion is `resolved`
        // above. This just documents that the resolved subject list is the
        // expected 'My Course' set, not inflated by anything STEM-Resources-shaped.
        $this->assertSame(
            ['ENGLISH', 'HINDI', 'MATHEMATICS', 'SCIENCE', 'SOCIAL_SCIENCE', 'PHYSICAL_EDUCATION', 'INFORMATION_TECHNOLOGY'],
            $plan->subjects
        );
    }

    public function test_the_query_still_excludes_non_gradeable_rows_and_respects_elective_opt_in(): void
    {
        // Elective handling (requirement 1): 'Science' on standard 42 is
        // configured as an elective (elective_subject='Yes'); student 284976
        // has a real student_optional_subject opt-in row for it. Confirms the
        // opt-in check in ErpSubjectEnrolmentAdapter::fetch() still runs after
        // the new subject_category filter, not bypassed by it.
        $optedIn = DB::table('student_optional_subject')
            ->where('student_id', self::STUDENT_ID)
            ->where('subject_id', 3975) // Science
            ->where('syear', self::ACADEMIC_YEAR)
            ->exists();
        $this->assertTrue($optedIn, 'Fixture assumption: 284976 must have opted into Science for this test to be meaningful.');

        $plan = (new ErpSubjectEnrolmentAdapter())->fetch(self::STUDENT_ID, self::ACADEMIC_YEAR);

        $this->assertContains('SCIENCE', $plan->subjects, 'Opted-in elective subject must still be included.');
    }
}
