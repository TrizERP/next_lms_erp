<?php

namespace Tests\Unit\Evaluation;

use App\Services\Evaluation\StudentMatcherService;
use PHPUnit\Framework\TestCase;

/**
 * Identity is a suggestion until it is verified against the class roll: these pin
 * when the matcher is allowed to make one, and when it must leave the sheet for a
 * teacher. Pure; the roster is passed in.
 */
class StudentMatcherServiceTest extends TestCase
{
    /** @return array<int,array<string,mixed>> */
    private function roster(): array
    {
        return [
            ['student_id' => 101, 'roll_no' => '1', 'enrollment_no' => 'GR/101', 'name' => 'Asha Patel'],
            ['student_id' => 102, 'roll_no' => '2', 'enrollment_no' => 'GR/102', 'name' => 'Ravi Shah'],
            ['student_id' => 103, 'roll_no' => '3', 'enrollment_no' => 'GR/103', 'name' => 'Ravi Shah'],
        ];
    }

    private function detected(array $over = []): array
    {
        return $over + ['roll_no' => '', 'enrollment_no' => '', 'name' => '', 'confidence' => 90.0];
    }

    public function test_the_gr_number_is_the_strongest_evidence_and_ignores_formatting(): void
    {
        $match = (new StudentMatcherService())->match($this->detected(['enrollment_no' => 'gr 101']), $this->roster());

        $this->assertSame(101, $match['student_id']);
        $this->assertSame('enrollment_no', $match['identity_source']);
    }

    public function test_a_roll_number_matches_within_the_class_and_forgives_leading_zeros(): void
    {
        $match = (new StudentMatcherService())->match($this->detected(['roll_no' => '02']), $this->roster());

        $this->assertSame(102, $match['student_id']);
        $this->assertSame('roll_no', $match['identity_source']);
    }

    public function test_a_name_alone_matches_only_when_the_read_was_confident_and_is_labelled_as_the_weakest_source(): void
    {
        $matcher = new StudentMatcherService();

        $confident = $matcher->match($this->detected(['name' => 'Patel Asha', 'confidence' => 85.0]), $this->roster());
        $unsure = $matcher->match($this->detected(['name' => 'Asha Patel', 'confidence' => 40.0]), $this->roster());

        $this->assertSame(101, $confident['student_id']);
        $this->assertSame('name', $confident['identity_source']);
        $this->assertLessThanOrEqual(80.0, $confident['identity_confidence']);
        $this->assertNull($unsure['student_id']);
    }

    public function test_two_students_with_the_same_name_are_never_guessed_between(): void
    {
        $match = (new StudentMatcherService())->match($this->detected(['name' => 'Ravi Shah']), $this->roster());

        $this->assertNull($match['student_id']);
        $this->assertSame('unmatched', $match['identity_source']);
    }

    public function test_a_number_that_is_not_on_the_roll_is_not_matched_to_someone_else(): void
    {
        $match = (new StudentMatcherService())->match($this->detected(['enrollment_no' => 'GR/999', 'roll_no' => '9']), $this->roster());

        $this->assertNull($match['student_id']);
    }

    public function test_an_empty_roll_never_matches(): void
    {
        $this->assertNull((new StudentMatcherService())->match($this->detected(['enrollment_no' => 'GR/101']), [])['student_id']);
    }

    public function test_a_student_listed_twice_still_counts_as_one_unique_hit(): void
    {
        // One child with a row per term: the same GR number twice is one student, not an ambiguity.
        $roster = [
            ['student_id' => 101, 'roll_no' => '1', 'enrollment_no' => 'GR/101', 'name' => 'Asha Patel'],
            ['student_id' => 101, 'roll_no' => '1', 'enrollment_no' => 'GR/101', 'name' => 'Asha Patel'],
        ];

        $match = (new StudentMatcherService())->match($this->detected(['enrollment_no' => 'GR/101']), $roster);

        $this->assertSame(101, $match['student_id']);
    }
}
