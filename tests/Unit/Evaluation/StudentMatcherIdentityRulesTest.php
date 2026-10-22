<?php

namespace Tests\Unit\Evaluation;

use App\Services\Evaluation\SheetIdentity;
use App\Services\Evaluation\StudentMatcherService;
use PHPUnit\Framework\TestCase;

/**
 * The rules that decide whether a sheet is offered a student, and when it must be
 * left for a teacher: exact GR, exact roll, an unambiguous name as the last
 * resort, and never a guess when the sheet contradicts itself.
 *
 * Pure -- the roster is passed in, nothing touches a database. This complements
 * StudentMatcherServiceTest (the basic priority order) with the cases that exist
 * to stop a sheet being filed under the wrong child.
 */
class StudentMatcherIdentityRulesTest extends TestCase
{
    /** @return array<int,array<string,mixed>> */
    private function roster(): array
    {
        return [
            ['student_id' => 101, 'roll_no' => '1', 'enrollment_no' => 'GR/101', 'name' => 'Asha Patel', 'section_name' => 'A'],
            ['student_id' => 102, 'roll_no' => '2', 'enrollment_no' => 'GR/102', 'name' => 'Ravi Shah', 'section_name' => 'A'],
            ['student_id' => 103, 'roll_no' => '3', 'enrollment_no' => 'GR/103', 'name' => 'Ravi Shah', 'section_name' => 'B'],
            ['student_id' => 104, 'roll_no' => '4', 'enrollment_no' => 'GR/1040', 'name' => 'Meera Joshi', 'section_name' => 'B'],
            ['student_id' => 105, 'roll_no' => '10', 'enrollment_no' => 'GR/105', 'name' => 'Dev Mehta', 'section_name' => 'B'],
        ];
    }

    private function detected(array $over = []): array
    {
        return $over + ['roll_no' => '', 'enrollment_no' => '', 'name' => '', 'confidence' => 90.0];
    }

    private function match(array $detected, ?array $roster = null): array
    {
        return (new StudentMatcherService())->match($detected, $roster ?? $this->roster());
    }

    // -- 1. exact enrollment-number match -----------------------------------

    public function test_an_exact_enrollment_number_is_a_clean_suggestion_with_its_method_and_confidence(): void
    {
        $match = $this->match($this->detected(['enrollment_no' => 'GR/101', 'confidence' => 70.0]));

        $this->assertSame(101, $match['student_id']);
        $this->assertSame('enrollment_no', $match['identity_source']);
        $this->assertGreaterThanOrEqual(90.0, $match['identity_confidence']);
        $this->assertNull($match['issue']);
        $this->assertSame([], $match['candidates']);
    }

    public function test_enrollment_numbers_ignore_case_spacing_and_punctuation_but_never_a_digit(): void
    {
        $this->assertSame(101, $this->match($this->detected(['enrollment_no' => ' gr 101 ']))['student_id']);
        $this->assertSame(101, $this->match($this->detected(['enrollment_no' => 'GR-101']))['student_id']);

        // GR/1010 is not GR/101 and must not be shortened into it; GR/10 is not either.
        $this->assertNull($this->match($this->detected(['enrollment_no' => 'GR/1010']))['student_id']);
        $this->assertNull($this->match($this->detected(['enrollment_no' => 'GR/10']))['student_id']);
        // And a roster number that merely STARTS the same is not a hit: GR/104 vs GR/1040.
        $this->assertNull($this->match($this->detected(['enrollment_no' => 'GR/104']))['student_id']);
    }

    // -- 2. exact roll-number match -----------------------------------------

    public function test_an_exact_roll_number_matches_and_forgives_leading_zeros_only(): void
    {
        $byRoll = $this->match($this->detected(['roll_no' => '02']));

        $this->assertSame(102, $byRoll['student_id']);
        $this->assertSame('roll_no', $byRoll['identity_source']);

        // "10" is roll ten, not roll one followed by a zero: the digits are kept.
        $this->assertSame(105, $this->match($this->detected(['roll_no' => '10']))['student_id']);
        $this->assertSame(101, $this->match($this->detected(['roll_no' => '1']))['student_id']);
        $this->assertNull($this->match($this->detected(['roll_no' => '11']))['student_id']);
    }

    public function test_a_roll_number_shared_by_two_students_is_ambiguous_not_a_guess(): void
    {
        $roster = $this->roster();
        $roster[] = ['student_id' => 106, 'roll_no' => '1', 'enrollment_no' => 'GR/106', 'name' => 'Isha Rao', 'section_name' => 'B'];

        $match = $this->match($this->detected(['roll_no' => '1']), $roster);

        $this->assertNull($match['student_id']);
        $this->assertSame(SheetIdentity::ISSUE_AMBIGUOUS, $match['issue']);
        $this->assertEqualsCanonicalizing([101, 106], $match['candidates']);
    }

    // -- 3. unambiguous name fallback ---------------------------------------

    public function test_a_name_is_only_the_last_resort_and_only_when_unambiguous_and_confidently_read(): void
    {
        $match = $this->match($this->detected(['name' => 'JOSHI meera', 'confidence' => 85.0]));

        $this->assertSame(104, $match['student_id']);
        $this->assertSame('name', $match['identity_source']);
        $this->assertLessThanOrEqual(80.0, $match['identity_confidence'], 'a name is the weakest evidence and is never reported as strong');
    }

    public function test_a_poorly_read_name_is_not_acted_on_but_is_offered_as_a_hint(): void
    {
        $match = $this->match($this->detected(['name' => 'Meera Joshi', 'confidence' => 40.0]));

        $this->assertNull($match['student_id']);
        $this->assertSame(SheetIdentity::ISSUE_LOW_CONFIDENCE, $match['issue']);
        $this->assertSame([104], $match['candidates']);
    }

    public function test_a_partial_name_does_not_match(): void
    {
        // "Meera" alone could be anyone called Meera: the whole name has to agree.
        $this->assertNull($this->match($this->detected(['name' => 'Meera']))['student_id']);
        $this->assertNull($this->match($this->detected(['name' => 'Meera Joshi Kumar']))['student_id']);
    }

    public function test_names_in_other_scripts_are_compared_properly(): void
    {
        $roster = [
            ['student_id' => 301, 'roll_no' => '1', 'enrollment_no' => 'GR/301', 'name' => 'આશા પટેલ', 'section_name' => 'A'],
            ['student_id' => 302, 'roll_no' => '2', 'enrollment_no' => 'GR/302', 'name' => 'રવિ શાહ', 'section_name' => 'A'],
        ];

        $this->assertSame(301, $this->match($this->detected(['name' => 'પટેલ  આશા']), $roster)['student_id']);
        $this->assertNull($this->match($this->detected(['name' => 'પટેલ']), $roster)['student_id']);
    }

    // -- 4. ambiguous names --------------------------------------------------

    public function test_two_students_with_the_same_name_are_never_guessed_between(): void
    {
        $match = $this->match($this->detected(['name' => 'Ravi Shah']));

        $this->assertNull($match['student_id']);
        $this->assertSame('unmatched', $match['identity_source']);
        $this->assertSame(SheetIdentity::ISSUE_AMBIGUOUS, $match['issue']);
        $this->assertEqualsCanonicalizing([102, 103], $match['candidates'], 'the teacher is shown exactly who it could be');
    }

    public function test_a_unique_number_settles_what_an_ambiguous_name_cannot(): void
    {
        // The name fits two students; the GR number fits one, and that one is among them.
        $match = $this->match($this->detected(['name' => 'Ravi Shah', 'enrollment_no' => 'GR/103']));

        $this->assertSame(103, $match['student_id']);
        $this->assertSame('enrollment_no', $match['identity_source']);
    }

    // -- 5. conflicting identifiers -----------------------------------------

    public function test_a_gr_number_and_a_roll_number_naming_different_students_conflict(): void
    {
        // GR/101 is Asha; roll 2 is Ravi. Which is right cannot be known from the text.
        $match = $this->match($this->detected(['enrollment_no' => 'GR/101', 'roll_no' => '2']));

        $this->assertNull($match['student_id'], 'a conflicting sheet must not be offered either student');
        $this->assertSame(SheetIdentity::ISSUE_CONFLICT, $match['issue']);
        $this->assertEqualsCanonicalizing([101, 102], $match['candidates']);
    }

    public function test_a_confident_name_that_belongs_to_someone_else_conflicts_with_the_number(): void
    {
        $match = $this->match($this->detected(['enrollment_no' => 'GR/101', 'name' => 'Meera Joshi', 'confidence' => 90.0]));

        $this->assertNull($match['student_id']);
        $this->assertSame(SheetIdentity::ISSUE_CONFLICT, $match['issue']);
        $this->assertEqualsCanonicalizing([101, 104], $match['candidates']);
    }

    public function test_a_field_that_excludes_the_matched_student_is_a_conflict_even_when_it_names_several(): void
    {
        // Roll 3 is Ravi (103); the name "Ravi Shah" is 102 or 103 -> consistent.
        $this->assertSame(103, $this->match($this->detected(['roll_no' => '3', 'name' => 'Ravi Shah']))['student_id']);

        // GR/101 is Asha; the name "Ravi Shah" is 102 or 103 -> neither is Asha.
        $match = $this->match($this->detected(['enrollment_no' => 'GR/101', 'name' => 'Ravi Shah']));

        $this->assertNull($match['student_id']);
        $this->assertSame(SheetIdentity::ISSUE_CONFLICT, $match['issue']);
    }

    public function test_an_identifier_that_matches_nobody_does_not_cancel_one_that_does(): void
    {
        // The GR number is on the roll; the roll number is a misread that matches nobody. Nothing contradicts the GR.
        $match = $this->match($this->detected(['enrollment_no' => 'GR/101', 'roll_no' => '99']));

        $this->assertSame(101, $match['student_id']);
    }

    // -- 6. missing or unreadable fields ------------------------------------

    public function test_a_sheet_with_nothing_readable_in_the_identity_block_is_unmatched_and_says_so(): void
    {
        foreach ([
            $this->detected(),
            $this->detected(['roll_no' => '  ', 'enrollment_no' => '', 'name' => '   ', 'confidence' => 0.0]),
            $this->detected(['roll_no' => '--', 'enrollment_no' => '/', 'name' => '??']),
        ] as $blank) {
            $match = $this->match($blank);

            $this->assertNull($match['student_id']);
            $this->assertSame('unmatched', $match['identity_source']);
            $this->assertSame(SheetIdentity::ISSUE_UNREADABLE, $match['issue']);
        }
    }

    public function test_numbers_that_are_not_on_the_class_list_are_reported_as_such(): void
    {
        $match = $this->match($this->detected(['enrollment_no' => 'GR/999', 'roll_no' => '77']));

        $this->assertNull($match['student_id']);
        $this->assertSame(SheetIdentity::ISSUE_NOT_ON_ROLL, $match['issue']);
    }

    public function test_an_empty_class_list_matches_nobody(): void
    {
        $match = $this->match($this->detected(['enrollment_no' => 'GR/101']), []);

        $this->assertNull($match['student_id']);
        $this->assertSame(SheetIdentity::ISSUE_NOT_ON_ROLL, $match['issue']);
    }

    // -- what "confirmed" means ---------------------------------------------

    public function test_a_suggestion_is_not_confirmed_until_a_teacher_says_so(): void
    {
        $suggested = ['student_id' => 101, 'identity_source' => 'enrollment_no', 'identity_confirmed_at' => null];

        $this->assertFalse(SheetIdentity::isConfirmed($suggested));
        $this->assertTrue(SheetIdentity::isConfirmed(['student_id' => 101, 'identity_source' => 'enrollment_no', 'identity_confirmed_at' => '2026-10-13 10:00:00']));
        // A hand-picked student has always meant "a teacher decided".
        $this->assertTrue(SheetIdentity::isConfirmed(['student_id' => 101, 'identity_source' => 'manual']));
        // And a confirmation with nobody to attach it to is not one.
        $this->assertFalse(SheetIdentity::isConfirmed(['student_id' => null, 'identity_source' => 'manual', 'identity_confirmed_at' => '2026-10-13 10:00:00']));
        $this->assertFalse(SheetIdentity::isConfirmed(null));
    }

    public function test_only_clean_number_matches_can_be_confirmed_in_bulk(): void
    {
        $base = ['status' => 'Awaiting identity', 'student_id' => 101, 'identity_source' => 'enrollment_no', 'identity_confirmed_at' => null, 'ai_result_json' => null];

        $this->assertTrue(SheetIdentity::isBulkConfirmable($base));
        $this->assertTrue(SheetIdentity::isBulkConfirmable(['identity_source' => 'roll_no'] + $base));
        $this->assertFalse(SheetIdentity::isBulkConfirmable(['identity_source' => 'name'] + $base), 'a handwritten name is confirmed one sheet at a time');
        $this->assertFalse(SheetIdentity::isBulkConfirmable(['student_id' => null, 'identity_source' => 'unmatched'] + $base));
        $this->assertFalse(SheetIdentity::isBulkConfirmable(['status' => 'Evaluated'] + $base));
        $this->assertFalse(SheetIdentity::isBulkConfirmable(['identity_confirmed_at' => '2026-10-13 10:00:00'] + $base));
        $this->assertFalse(SheetIdentity::isBulkConfirmable(
            ['ai_result_json' => json_encode(['identity' => ['issue' => 'conflict']])] + $base
        ), 'anything flagged is looked at individually');
    }
}
