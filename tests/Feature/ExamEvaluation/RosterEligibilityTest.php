<?php

namespace Tests\Feature\ExamEvaluation;

use App\Services\Evaluation\SheetIdentity;
use App\Services\Evaluation\StudentMatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Who is ELIGIBLE for an exam -- the roster everything else is checked against -- on the data
 * as it really is, not as the schema suggests. The cases here come from inspecting the shared
 * database read-only: `drop_code` holds 0 as well as NULL for a current student; GR numbers are
 * repeated across the school; roll numbers restart in every section of a class; and some papers
 * name no class at all.
 */
class RosterEligibilityTest extends ExamEvaluationTestCase
{
    use IdentityTestHelpers;

    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaper();
        $this->batchId = $this->makeBatch(['status' => 'Review']);
        Queue::fake();
    }

    /** @return array<int,int> */
    private function rosterIds(?int $tenant = null, int $standard = 1): array
    {
        return array_column((new StudentMatcherService())->roster($tenant ?? self::TENANT, self::SYEAR, $standard, 1), 'student_id');
    }

    // -- the drop code ---------------------------------------------------------------------

    public function test_a_student_whose_drop_code_is_zero_is_still_enrolled_and_so_eligible(): void
    {
        // 1,932 enrollments in one school's 2026 data look like this: no drop remark, no other row.
        $this->addStudent(110, 'Zero', 'Coded', 'GR110', '10', 1, '0');

        $this->assertContains(110, $this->rosterIds());
    }

    public function test_a_real_drop_code_means_dropped(): void
    {
        $this->addStudent(111, 'Dropped', 'Three', 'GR111', '11', 1, '3');
        $this->addStudent(112, 'Dropped', 'Left', 'GR112', '12', 1, 'LEFT');

        $this->assertNotContains(111, $this->rosterIds());
        $this->assertNotContains(112, $this->rosterIds());
        $this->assertSame([101, 102], $this->rosterIds());
    }

    public function test_a_student_with_one_dropped_row_and_one_current_row_is_still_eligible_once(): void
    {
        $this->addStudent(113, 'Moved', 'Terms', 'GR113', '13', 1, '5');
        DB::table('tblstudent_enrollment')->insert(['student_id' => 113, 'syear' => self::SYEAR, 'standard_id' => 1, 'grade_id' => 1, 'section_id' => 1, 'roll_no' => '13', 'drop_code' => null, 'term_id' => 2, 'sub_institute_id' => self::TENANT]);

        $this->assertSame(1, count(array_keys($this->rosterIds(), 113, true)));
    }

    public function test_a_zero_coded_student_can_be_assigned_confirmed_and_approved_like_anyone_else(): void
    {
        $this->addStudent(110, 'Zero', 'Coded', 'GR110', '10', 1, '0');
        $sheetId = $this->awaitingSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);

        $this->review($sheetId, ['student_id' => 110])->assertOk();

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame(110, (int) $sheet->student_id);
        $this->assertSame('Pending', $sheet->status);
    }

    public function test_a_dropped_student_still_cannot_be_assigned(): void
    {
        $this->addStudent(111, 'Dropped', 'Three', 'GR111', '11', 1, '3');
        $sheetId = $this->awaitingSheet($this->batchId, ['student_id' => null, 'identity_source' => 'unmatched']);

        $this->review($sheetId, ['student_id' => 111])->assertStatus(422);

        $this->assertNull(DB::table('exam_evaluation_sheet')->find($sheetId)->student_id);
    }

    public function test_the_section_shown_for_a_sheet_is_found_for_a_zero_coded_student_too(): void
    {
        $this->addStudent(110, 'Zero', 'Coded', 'GR110', '10', 1, '0', 2);
        $sheetId = $this->makeSheet($this->batchId, ['student_id' => 110, 'identity_confirmed_at' => now()]);

        $this->getJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT)
            ->assertOk()->assertJsonPath('data.context.section_name', 'B');
    }

    // -- scope ---------------------------------------------------------------------------------

    public function test_the_roster_is_this_school_this_year_and_this_class_only(): void
    {
        $this->addStudent(120, 'Other', 'Year', 'GR120', '20');
        DB::table('tblstudent_enrollment')->where('student_id', 120)->update(['syear' => self::SYEAR - 1]);
        $this->addStudent(121, 'Other', 'Class', 'GR121', '21', 2);
        $this->addStudent(122, 'Other', 'School', 'GR122', '22', 1, null, 1, self::OTHER_TENANT);

        $this->assertSame([101, 102], $this->rosterIds());
        $this->assertSame([121], $this->rosterIds(null, 2));
    }

    public function test_roster_rows_say_which_division_a_student_is_in_so_similar_students_can_be_told_apart(): void
    {
        $roster = (new StudentMatcherService())->roster(self::TENANT, self::SYEAR, 1, 1);

        $this->assertSame(['A', 'B'], array_column($roster, 'section_name'));
    }

    // -- ambiguity as the real data has it ---------------------------------------------------------

    public function test_a_gr_number_repeated_elsewhere_in_the_school_still_finds_its_student_in_this_class(): void
    {
        // The same GR number belongs to a Class 8 student too -- repeated GR numbers are common in the data.
        $this->addStudent(130, 'Elsewhere', 'Same', 'GR101', '1', 2);
        $matcher = new StudentMatcherService();

        $match = $matcher->match(['roll_no' => '', 'enrollment_no' => 'GR101', 'name' => '', 'confidence' => 90.0], $matcher->roster(self::TENANT, self::SYEAR, 1, 1));

        $this->assertSame(101, $match['student_id'], 'only this class\'s list is searched');
    }

    public function test_a_gr_number_repeated_inside_the_class_is_ambiguous_not_a_guess(): void
    {
        $this->addStudent(131, 'Twin', 'Gr', 'GR101', '9');
        $matcher = new StudentMatcherService();

        $match = $matcher->match(['roll_no' => '', 'enrollment_no' => 'GR101', 'name' => '', 'confidence' => 95.0], $matcher->roster(self::TENANT, self::SYEAR, 1, 1));

        $this->assertNull($match['student_id']);
        $this->assertSame(SheetIdentity::ISSUE_AMBIGUOUS, $match['issue']);
        $this->assertEqualsCanonicalizing([101, 131], $match['candidates']);
    }

    public function test_a_roll_number_that_restarts_in_every_section_is_ambiguous_and_names_both_students(): void
    {
        // Roll 1 in section A (Asha, seeded) and roll 1 in section B: the paper belongs to the class, not a section.
        $this->addStudent(132, 'Section', 'Bee', 'GR132', '1', 1, null, 2);
        $matcher = new StudentMatcherService();

        $match = $matcher->match(['roll_no' => '1', 'enrollment_no' => '', 'name' => '', 'confidence' => 90.0], $matcher->roster(self::TENANT, self::SYEAR, 1, 1));

        $this->assertNull($match['student_id']);
        $this->assertSame(SheetIdentity::ISSUE_AMBIGUOUS, $match['issue']);
        $this->assertEqualsCanonicalizing([101, 132], $match['candidates']);
    }

    public function test_a_unique_gr_number_settles_a_roll_number_that_is_shared_between_sections(): void
    {
        $this->addStudent(132, 'Section', 'Bee', 'GR132', '1', 1, null, 2);
        $matcher = new StudentMatcherService();

        $match = $matcher->match(['roll_no' => '1', 'enrollment_no' => 'GR132', 'name' => '', 'confidence' => 90.0], $matcher->roster(self::TENANT, self::SYEAR, 1, 1));

        $this->assertSame(132, $match['student_id']);
        $this->assertSame('enrollment_no', $match['identity_source']);
    }

    // -- the answer-key summary tells the teacher who is eligible before any scanning ------------------

    public function test_the_summary_says_the_exam_is_tied_to_a_class_and_how_many_students_are_eligible(): void
    {
        $this->addStudent(110, 'Zero', 'Coded', 'GR110', '10', 1, '0');
        $this->addStudent(111, 'Dropped', 'Three', 'GR111', '11', 1, '3');

        $response = $this->getJson('/api/exam-evaluation/answer-key/1?sub_institute_id=' . self::TENANT)->assertOk();

        $response->assertJsonPath('data.applies_to_class', true)->assertJsonPath('data.eligible_students', 3);
        $this->assertStringNotContainsString('Asha', $response->getContent(), 'a count, never a name');
    }

    public function test_a_paper_that_names_no_class_is_flagged_because_everyone_enrolled_would_be_eligible(): void
    {
        DB::table('question_paper')->where('id', 1)->update(['standard_id' => 0, 'grade_id' => 0]);
        $this->addStudent(121, 'Other', 'Class', 'GR121', '21', 2);

        $response = $this->getJson('/api/exam-evaluation/answer-key/1?sub_institute_id=' . self::TENANT)->assertOk();

        $response->assertJsonPath('data.applies_to_class', false);
        $this->assertSame(3, $response->json('data.eligible_students'), 'the whole school for the year: 101, 102 and the Class 8 student');
    }

    public function test_the_summary_counts_the_year_it_is_asked_about(): void
    {
        $this->addStudent(120, 'Other', 'Year', 'GR120', '20');
        DB::table('tblstudent_enrollment')->where('student_id', 120)->update(['syear' => self::SYEAR - 1]);

        $this->getJson('/api/exam-evaluation/answer-key/1?sub_institute_id=' . self::TENANT . '&syear=' . (self::SYEAR - 1))
            ->assertOk()->assertJsonPath('data.eligible_students', 1);
    }
}
