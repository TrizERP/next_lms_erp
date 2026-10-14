<?php

namespace Tests\Unit\Evaluation;

use App\Services\Evaluation\QuestionRubric;
use PHPUnit\Framework\TestCase;

/**
 * Pure: no application, no database.
 */
class QuestionRubricTest extends TestCase
{
    public function test_plain_text_is_a_model_answer_and_nothing_more(): void
    {
        $rubric = QuestionRubric::parse('  Plants make <b>glucose</b>&nbsp;using light.<br/>Oxygen is released.  ');

        $this->assertFalse($rubric['structured']);
        $this->assertSame('Plants make glucose using light. Oxygen is released.', $rubric['model_answer']);
        $this->assertSame([], $rubric['marking_points']);
    }

    public function test_an_empty_key_stays_empty_so_the_caller_can_say_there_is_none(): void
    {
        foreach (['', '   ', null] as $stored) {
            $rubric = QuestionRubric::parse((string) $stored);

            $this->assertFalse($rubric['structured']);
            $this->assertSame('', $rubric['model_answer']);
        }
    }

    public function test_a_generated_written_question_is_read_as_a_rubric(): void
    {
        $rubric = QuestionRubric::parse(json_encode([
            'sub_type' => 'short_answer',
            'model_answer' => 'Plants make glucose from CO2 and water using light.',
            'marking_points' => [
                ['mark' => 1, 'criterion' => 'Names carbon dioxide', 'accept' => ['CO2', 'carbon dioxide'], 'reject' => ['oxygen']],
                ['mark' => 1, 'criterion' => 'Names water', 'accept' => ['H2O'], 'reject' => []],
            ],
            'keywords' => [
                ['term' => 'chlorophyll', 'weight' => 0.8, 'synonyms' => ['green pigment']],
                ['term' => 'stomata', 'weight' => 7, 'synonyms' => []],
            ],
            'common_errors' => [['erroneous_answer' => 'Plants breathe in oxygen to make food', 'mark_ceiling' => 1]],
            'full_credit_threshold' => 2,
        ]));

        $this->assertTrue($rubric['structured']);
        $this->assertSame('Plants make glucose from CO2 and water using light.', $rubric['model_answer']);
        $this->assertCount(2, $rubric['marking_points']);
        $this->assertSame('Names carbon dioxide', $rubric['marking_points'][0]['criterion']);
        $this->assertSame(['CO2', 'carbon dioxide'], $rubric['marking_points'][0]['accept']);
        $this->assertSame(['oxygen'], $rubric['marking_points'][0]['reject']);
        $this->assertSame('green pigment', $rubric['keywords'][0]['synonyms'][0]);
        $this->assertSame(1.0, $rubric['keywords'][1]['weight'], 'a weight is held to 0..1');
        $this->assertSame(1, $rubric['common_errors'][0]['mark_ceiling']);
        $this->assertSame(2, $rubric['full_credit_threshold']);
    }

    public function test_marking_points_alone_still_give_the_grader_something_to_judge_against(): void
    {
        $rubric = QuestionRubric::parse(json_encode([
            'marking_points' => [['criterion' => 'States the law'], ['criterion' => 'Gives an example']],
        ]));

        $this->assertTrue($rubric['structured']);
        $this->assertSame('States the law Gives an example', $rubric['model_answer']);
    }

    public function test_bare_string_marking_points_from_older_rows_are_accepted(): void
    {
        $rubric = QuestionRubric::parse(json_encode(['model_answer' => 'x', 'marking_points' => ['First point', 'Second point']]));

        $this->assertSame(['First point', 'Second point'], array_column($rubric['marking_points'], 'criterion'));
    }

    public function test_an_envelope_that_is_not_a_written_rubric_is_left_exactly_as_found(): void
    {
        // A fill-in-the-blank envelope: the raw document IS the key, so it keeps going to the grader as text.
        $stored = json_encode(['blanks' => [['index' => 1, 'answer' => 'photosynthesis']], 'bloom_level' => 'Remember']);

        $rubric = QuestionRubric::parse($stored);

        $this->assertFalse($rubric['structured']);
        $this->assertSame($stored, $rubric['model_answer']);
    }

    public function test_text_that_merely_starts_with_a_brace_is_treated_as_text(): void
    {
        $rubric = QuestionRubric::parse('{not really json} the answer is 42');

        $this->assertFalse($rubric['structured']);
        $this->assertSame('{not really json} the answer is 42', $rubric['model_answer']);
    }

    public function test_nothing_is_invented_for_missing_parts(): void
    {
        $rubric = QuestionRubric::parse(json_encode(['model_answer' => 'Just this.', 'marking_points' => [['criterion' => '   '], 'nonsense' => 5], 'keywords' => 'oops']));

        $this->assertSame('Just this.', $rubric['model_answer']);
        $this->assertSame([], $rubric['marking_points']);
        $this->assertSame([], $rubric['keywords']);
        $this->assertSame([], $rubric['common_errors']);
        $this->assertNull($rubric['full_credit_threshold']);
    }
}
