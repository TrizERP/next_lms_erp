<?php

namespace Tests\Unit;

use App\Domain\K12\AcademicRisk\HomeworkCompletion;
use PHPUnit\Framework\TestCase;

/**
 * The one rule that decides whether homework counts as done.
 *
 * Worth pinning precisely, because it is read by two surfaces that must never disagree:
 * the detector that can flag a child for missing work, and the MCP tool that lists
 * homework in a reply a teacher acts on.
 *
 * The first test is the load-bearing one, and it is written from real data: this estate
 * populates `submission_date` even for work the school has explicitly marked `'N'`. A
 * rule that trusted the date would report every one of those as handed in.
 */
class HomeworkCompletionTest extends TestCase
{
    public function test_an_explicit_not_done_status_beats_a_present_submission_date(): void
    {
        $item = (object) [
            'completion_status' => 'N',
            'submission_date' => '2026-07-05',
            'date' => '2026-07-05',
        ];

        $this->assertTrue(HomeworkCompletion::isMissed($item));
    }

    public function test_an_explicit_done_status_wins_even_with_no_submission_date(): void
    {
        $item = (object) ['completion_status' => 'Y', 'submission_date' => null, 'date' => '2026-07-05'];

        $this->assertFalse(HomeworkCompletion::isMissed($item));
    }

    public function test_status_matching_is_case_insensitive_and_trimmed(): void
    {
        foreach (['y', ' Y ', 'Completed', 'SUBMITTED', 'done', '1'] as $done) {
            $this->assertFalse(
                HomeworkCompletion::isMissed((object) ['completion_status' => $done, 'submission_date' => null]),
                "\"{$done}\" should mean done"
            );
        }

        foreach (['n', 'No', 'PENDING', 'incomplete', 'not submitted', '0'] as $notDone) {
            $this->assertTrue(
                HomeworkCompletion::isMissed((object) ['completion_status' => $notDone, 'submission_date' => '2026-01-01']),
                "\"{$notDone}\" should mean not done"
            );
        }
    }

    public function test_an_unrecognised_status_falls_through_to_the_submission_date(): void
    {
        // A school using its own vocabulary should not be silently misread in either
        // direction — the date is the fallback, not a guess at what the word meant.
        $withDate = (object) ['completion_status' => 'partially-marked', 'submission_date' => '2026-01-01'];
        $withoutDate = (object) ['completion_status' => 'partially-marked', 'submission_date' => null];

        $this->assertFalse(HomeworkCompletion::isMissed($withDate));
        $this->assertTrue(HomeworkCompletion::isMissed($withoutDate));
    }

    public function test_an_empty_status_falls_through_to_the_submission_date(): void
    {
        $this->assertTrue(HomeworkCompletion::isMissed((object) ['completion_status' => '', 'submission_date' => null]));
        $this->assertFalse(HomeworkCompletion::isMissed((object) ['completion_status' => null, 'submission_date' => '2026-01-01']));
    }

    public function test_it_accepts_an_array_as_readily_as_an_object(): void
    {
        // The detector passes query result objects; the tool passes normalised arrays.
        $this->assertTrue(HomeworkCompletion::isMissed(['completion_status' => 'N', 'submission_date' => '2026-01-01']));
    }

    public function test_only_work_whose_due_date_has_passed_is_called_overdue(): void
    {
        $future = now()->addDays(7)->toDateString();
        $past = now()->subDays(7)->toDateString();

        $this->assertSame(
            'pending',
            HomeworkCompletion::label(['completion_status' => 'N', 'submission_date' => null, 'date' => $future])
        );

        $this->assertSame(
            'overdue',
            HomeworkCompletion::label(['completion_status' => 'N', 'submission_date' => null, 'date' => $past])
        );
    }

    public function test_submitted_work_is_labelled_by_how_it_was_recorded(): void
    {
        $this->assertSame(
            'submitted',
            HomeworkCompletion::label(['completion_status' => 'Y', 'submission_date' => '2026-01-01', 'date' => '2026-01-01'])
        );

        $this->assertSame(
            'completed',
            HomeworkCompletion::label(['completion_status' => 'Y', 'submission_date' => null, 'date' => '2026-01-01'])
        );
    }
}
