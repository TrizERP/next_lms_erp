<?php

namespace Tests\Unit;

use App\Services\PAL\Questions\PalInteractiveAnswers;
use PHPUnit\Framework\TestCase;

/**
 * Reading the answers that are not a choice.
 *
 * What is worth guarding is the PARSE, not the marking: a malformed entry that
 * silently becomes "correct" hands a learner a mark they did not earn, and one
 * that silently becomes "wrong" takes one away. Both happen at submission time
 * and neither shows up on screen, so both are found in a report weeks later or
 * not at all.
 *
 * `verify()` reads the database and belongs in a feature test; everything here
 * is pure.
 */
class PalInteractiveAnswersTest extends TestCase
{
    public function test_it_reads_the_json_a_browser_sends(): void
    {
        $parsed = PalInteractiveAnswers::parse(
            json_encode(['correct' => 1, 'response' => 'Paris', 'score' => 1, 'max_score' => 1]),
            '88'
        );

        $this->assertSame(88, $parsed['question_id']);
        $this->assertTrue($parsed['client_correct']);
        $this->assertSame('Paris', $parsed['response']);
        $this->assertSame(1, $parsed['score']);
        $this->assertSame(1, $parsed['max_score']);
    }

    public function test_an_already_decoded_array_reads_the_same_way(): void
    {
        $parsed = PalInteractiveAnswers::parse(['correct' => 0, 'response' => 'lake'], 12);

        $this->assertFalse($parsed['client_correct']);
        $this->assertSame('lake', $parsed['response']);
    }

    public function test_a_missing_verdict_is_wrong_not_right(): void
    {
        // Defaulting the other way would give a mark for sending an empty
        // object, which is the one failure mode worth being certain about.
        $parsed = PalInteractiveAnswers::parse(['response' => 'something'], 3);

        $this->assertFalse($parsed['client_correct']);
    }

    public function test_a_partial_score_survives(): void
    {
        // Four blanks, three right: the paper's mark is still pass/fail, but
        // the attempt row keeps what was actually earned.
        $parsed = PalInteractiveAnswers::parse(
            json_encode(['correct' => 0, 'response' => '3/4', 'score' => 3, 'max_score' => 4]),
            9
        );

        $this->assertSame(3, $parsed['score']);
        $this->assertSame(4, $parsed['max_score']);
    }

    public function test_unreadable_input_is_dropped_rather_than_defaulted(): void
    {
        $this->assertNull(PalInteractiveAnswers::parse('not json at all', 5));
        $this->assertNull(PalInteractiveAnswers::parse(null, 5));
    }

    public function test_a_dropped_entry_does_not_appear_in_the_bag(): void
    {
        $bag = PalInteractiveAnswers::readAll([
            '10' => json_encode(['correct' => 1, 'response' => 'Paris']),
            '11' => 'garbage',
            '12' => json_encode(['correct' => 0, 'response' => 'Lyon']),
        ]);

        // 11 is absent, so `store()` records it through the unanswered branch
        // -- unattempted, which is the honest reading of input this server
        // cannot interpret.
        $this->assertSame([10, 12], array_keys($bag));
        $this->assertTrue($bag[10]['client_correct']);
        $this->assertFalse($bag[12]['client_correct']);
    }

    public function test_a_non_array_bag_is_no_answers_rather_than_an_error(): void
    {
        $this->assertSame([], PalInteractiveAnswers::readAll(null));
        $this->assertSame([], PalInteractiveAnswers::readAll('answer_interactive'));
    }

    public function test_a_response_is_truncated_to_fit_the_column(): void
    {
        $parsed = PalInteractiveAnswers::parse(
            ['correct' => 1, 'response' => str_repeat('a', 900)],
            1
        );

        $this->assertSame(500, mb_strlen($parsed['response']));
    }

    public function test_a_structured_response_is_flattened_not_dropped(): void
    {
        // A matching activity reports pairs. Storing "Array" would lose what
        // the learner actually did, which is the only reason the field exists.
        $parsed = PalInteractiveAnswers::parse(
            ['correct' => 1, 'response' => ['Delhi - India', 'Paris - France']],
            2
        );

        $this->assertSame('Delhi - India, Paris - France', $parsed['response']);
    }
}
