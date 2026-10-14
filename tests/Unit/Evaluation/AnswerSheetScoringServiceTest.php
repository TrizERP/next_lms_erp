<?php

namespace Tests\Unit\Evaluation;

use App\Services\Evaluation\AnswerSheetScoringService;
use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\RateLimitedException;
use Illuminate\Container\Container;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\FakeGeminiClient;

/**
 * Scoring in isolation: no application, no database, no network. The model is a
 * script; the only framework piece is a null logger behind the Log facade so the
 * degraded paths (which log a warning) can run.
 */
class AnswerSheetScoringServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = new Container();
        $app->instance('log', new NullLogger());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function objective(int $no, array $correct, int $max = 2, bool $multiple = false): array
    {
        $options = [];

        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $options[] = ['id' => $no * 10 + count($options), 'letter' => $letter, 'text' => "Option {$letter}", 'is_correct' => in_array($letter, $correct, true)];
        }

        return [
            'question_no' => $no, 'question_id' => $no, 'question_type' => 'MCQ', 'question_title' => "Question {$no}",
            'kind' => 'objective', 'max_marks' => (float) $max, 'multiple_answer' => $multiple,
            'options' => $options, 'correct_letters' => $correct, 'model_answer' => '', 'rubric' => null,
        ];
    }

    /** @return array<string,mixed> */
    private function written(int $no, int $max = 5, string $modelAnswer = 'The model answer.', ?array $rubric = null): array
    {
        return [
            'question_no' => $no, 'question_id' => $no, 'question_type' => 'Short answer', 'question_title' => "Question {$no}",
            'kind' => 'subjective', 'max_marks' => (float) $max, 'multiple_answer' => false,
            'options' => [], 'correct_letters' => [], 'model_answer' => $modelAnswer, 'rubric' => $rubric,
        ];
    }

    /** @return array<string,mixed> */
    private function response(int $no, array $letters = [], string $text = '', bool $attempted = true): array
    {
        return ['question_no' => $no, 'attempted' => $attempted, 'selected_options' => $letters, 'answer_text' => $text, 'page' => 1, 'box_2d' => null];
    }

    private function graded(int $no, float $marks, float $confidence = 90, string $remark = 'ok'): array
    {
        return ['results' => [['question_no' => $no, 'marks' => $marks, 'confidence' => $confidence, 'remark' => $remark]]];
    }

    // -- Objective ----------------------------------------------------------

    public function test_a_correct_single_answer_earns_full_marks_without_calling_the_model(): void
    {
        $gemini = new FakeGeminiClient([]);

        $result = (new AnswerSheetScoringService($gemini))->score([$this->objective(1, ['B'])], [$this->response(1, ['B'])]);

        $this->assertEquals(2.0, $result['answers'][0]['ai_marks']);
        $this->assertSame('correct', $result['answers'][0]['status']);
        $this->assertSame([], $gemini->calls);
        $this->assertFalse($result['needs_review']);
    }

    public function test_a_wrong_or_double_marked_single_answer_earns_nothing(): void
    {
        $scorer = new AnswerSheetScoringService(new FakeGeminiClient([]));

        $wrong = $scorer->score([$this->objective(1, ['B'])], [$this->response(1, ['C'])]);
        $double = $scorer->score([$this->objective(1, ['B'])], [$this->response(1, ['B', 'C'])]);

        $this->assertEquals(0.0, $wrong['answers'][0]['ai_marks']);
        $this->assertSame('wrong', $wrong['answers'][0]['status']);
        $this->assertEquals(0.0, $double['answers'][0]['ai_marks']);
    }

    public function test_a_multiple_answer_question_earns_partial_marks_only_while_nothing_wrong_is_ticked(): void
    {
        $scorer = new AnswerSheetScoringService(new FakeGeminiClient([]));
        $question = $this->objective(1, ['A', 'B'], 4, true);

        $partial = $scorer->score([$question], [$this->response(1, ['A'])]);
        $spoiled = $scorer->score([$question], [$this->response(1, ['A', 'C'])]);

        $this->assertEquals(2.0, $partial['answers'][0]['ai_marks']);
        $this->assertSame('partially_correct', $partial['answers'][0]['status']);
        $this->assertEquals(0.0, $spoiled['answers'][0]['ai_marks']);
    }

    public function test_a_blank_answer_is_unattempted_and_not_flagged(): void
    {
        $result = (new AnswerSheetScoringService(new FakeGeminiClient([])))
            ->score([$this->objective(1, ['B'])], [$this->response(1, [], '', false)]);

        $this->assertSame('unattempted', $result['answers'][0]['status']);
        $this->assertEquals(0.0, $result['answers'][0]['ai_marks']);
    }

    public function test_a_question_the_reader_said_nothing_about_is_unattempted_not_invented(): void
    {
        $result = (new AnswerSheetScoringService(new FakeGeminiClient([])))->score([$this->objective(1, ['B']), $this->objective(2, ['A'])], [$this->response(1, ['B'])]);

        $this->assertSame('unattempted', $result['answers'][1]['status']);
        $this->assertEquals(2.0, $result['ai_total']);
        $this->assertEquals(4.0, $result['max_marks']);
    }

    // -- Written ------------------------------------------------------------

    public function test_a_written_answer_gets_the_ai_proposal_with_partial_marks_and_is_always_reviewable(): void
    {
        $gemini = new FakeGeminiClient([$this->graded(1, 3, 85, 'Two of five points.')]);

        $result = (new AnswerSheetScoringService($gemini))->score([$this->written(1)], [$this->response(1, [], 'Plants need light.')]);

        $answer = $result['answers'][0];
        $this->assertEquals(3.0, $answer['ai_marks']);
        $this->assertSame('partially_correct', $answer['status']);
        $this->assertEquals(85.0, $answer['ai_confidence']);
        $this->assertSame('Two of five points.', $answer['ai_remark']);
        $this->assertTrue($result['needs_review'], 'any written answer means a teacher looks before it becomes a result');
    }

    public function test_marks_are_clamped_to_what_the_question_is_worth_and_to_two_places(): void
    {
        $over = (new AnswerSheetScoringService(new FakeGeminiClient([$this->graded(1, 9)])))->score([$this->written(1, 5)], [$this->response(1, [], 'x')]);
        $under = (new AnswerSheetScoringService(new FakeGeminiClient([$this->graded(1, -2)])))->score([$this->written(1, 5)], [$this->response(1, [], 'x')]);
        $fraction = (new AnswerSheetScoringService(new FakeGeminiClient([$this->graded(1, 2.456)])))->score([$this->written(1, 5)], [$this->response(1, [], 'x')]);

        $this->assertEquals(5.0, $over['answers'][0]['ai_marks']);
        $this->assertSame('correct', $over['answers'][0]['status']);
        $this->assertEquals(0.0, $under['answers'][0]['ai_marks']);
        $this->assertEquals(2.46, $fraction['answers'][0]['ai_marks']);
    }

    public function test_confidence_is_held_to_zero_to_one_hundred(): void
    {
        $result = (new AnswerSheetScoringService(new FakeGeminiClient([$this->graded(1, 2, 250)])))->score([$this->written(1)], [$this->response(1, [], 'x')]);

        $this->assertEquals(100.0, $result['answers'][0]['ai_confidence']);
    }

    public function test_a_question_with_no_model_answer_is_not_graded_and_the_model_is_not_asked(): void
    {
        $gemini = new FakeGeminiClient([]);

        $result = (new AnswerSheetScoringService($gemini))->score([$this->written(1, 5, '')], [$this->response(1, [], 'something')]);

        $this->assertSame([], $gemini->calls, 'no call: there is nothing to grade against');
        $answer = $result['answers'][0];
        $this->assertEquals(0.0, $answer['ai_marks']);
        $this->assertEquals(0.0, $answer['ai_confidence']);
        $this->assertSame('wrong', $answer['status'], 'not "unattempted": the student did write something');
        $this->assertStringContainsString('No model answer is saved', $answer['ai_remark']);
    }

    public function test_only_questions_with_a_key_are_sent_to_the_grader(): void
    {
        $gemini = new FakeGeminiClient([$this->graded(2, 4)]);

        $result = (new AnswerSheetScoringService($gemini))->score(
            [$this->written(1, 5, ''), $this->written(2, 5, 'Has a key.')],
            [$this->response(1, [], 'a'), $this->response(2, [], 'b')]
        );

        $prompt = $gemini->promptText(0);
        $this->assertStringContainsString('### Question 2', $prompt);
        $this->assertStringNotContainsString('### Question 1', $prompt);
        $this->assertEquals(4.0, $result['answers'][1]['ai_marks']);
        $this->assertEquals(0.0, $result['answers'][0]['ai_confidence']);
    }

    public function test_a_written_answer_the_grader_left_out_is_flagged_rather_than_silently_zeroed(): void
    {
        $result = (new AnswerSheetScoringService(new FakeGeminiClient([$this->graded(99, 3)])))->score([$this->written(1)], [$this->response(1, [], 'x')]);

        $answer = $result['answers'][0];
        $this->assertEquals(0.0, $answer['ai_confidence']);
        $this->assertSame('wrong', $answer['status']);
        $this->assertStringContainsString('did not return a mark', $answer['ai_remark']);
    }

    public function test_the_rubric_reaches_the_grader_as_marking_points_not_as_json(): void
    {
        $rubric = [
            'structured' => true, 'model_answer' => 'Full answer.',
            'marking_points' => [
                ['criterion' => 'Names the input', 'accept' => ['CO2', 'carbon dioxide'], 'reject' => ['oxygen']],
                ['criterion' => 'Names the output', 'accept' => [], 'reject' => []],
            ],
            'keywords' => [['term' => 'chlorophyll', 'weight' => 1.0, 'synonyms' => ['green pigment']], ['term' => 'stomata', 'weight' => 1.0, 'synonyms' => []]],
            'common_errors' => [['erroneous_answer' => 'Confuses respiration with photosynthesis', 'mark_ceiling' => 1]],
            'full_credit_threshold' => 2,
        ];
        $gemini = new FakeGeminiClient([$this->graded(1, 2)]);

        (new AnswerSheetScoringService($gemini))->score([$this->written(1, 2, 'Full answer.', $rubric)], [$this->response(1, [], 'x')]);

        $prompt = $gemini->promptText(0);
        $this->assertStringContainsString('MARKING POINT 1 (1 mark): Names the input | ACCEPT: CO2; carbon dioxide | REJECT: oxygen', $prompt);
        $this->assertStringContainsString("MARKING POINT 2 (1 mark): Names the output\n", $prompt);
        $this->assertStringContainsString('KEYWORDS: chlorophyll (green pigment); stomata', $prompt);
        $this->assertStringContainsString('COMMON ERROR (at most 1 marks): Confuses respiration with photosynthesis', $prompt);
    }

    public function test_a_plain_text_key_adds_no_rubric_block(): void
    {
        $gemini = new FakeGeminiClient([$this->graded(1, 2)]);

        (new AnswerSheetScoringService($gemini))->score([$this->written(1)], [$this->response(1, [], 'x')]);

        $this->assertStringNotContainsString('MARKING POINT 1', $gemini->promptText(0));
        $this->assertStringContainsString('MODEL ANSWER: The model answer.', $gemini->promptText(0));
    }

    // -- Failure handling ---------------------------------------------------

    public function test_malformed_output_is_retried_once_and_the_second_try_is_used(): void
    {
        $gemini = new FakeGeminiClient(['not json {', $this->graded(1, 4)]);

        $result = (new AnswerSheetScoringService($gemini))->score([$this->written(1)], [$this->response(1, [], 'x')]);

        $this->assertCount(2, $gemini->calls);
        $this->assertGreaterThan($gemini->configs[0]['temperature'], $gemini->configs[1]['temperature']);
        $this->assertEquals(4.0, $result['answers'][0]['ai_marks']);
    }

    public function test_output_that_stays_malformed_leaves_written_answers_for_the_teacher_but_keeps_objective_marks(): void
    {
        $gemini = new FakeGeminiClient(['nope', 'still nope']);

        $result = (new AnswerSheetScoringService($gemini))->score(
            [$this->objective(1, ['B']), $this->written(2)],
            [$this->response(1, ['B']), $this->response(2, [], 'x')]
        );

        $this->assertEquals(2.0, $result['answers'][0]['ai_marks'], 'the objective half is not lost');
        $this->assertEquals(0.0, $result['answers'][1]['ai_confidence']);
        $this->assertStringContainsString('mark this one by hand', $result['answers'][1]['ai_remark']);
    }

    public function test_a_truncated_grading_response_is_not_retried_into_the_same_truncation(): void
    {
        $gemini = new FakeGeminiClient([
            ['__text' => '{"results": [{"question_no": 1,', 'finish_reason' => 'MAX_TOKENS'],
        ]);

        $result = (new AnswerSheetScoringService($gemini))->score([$this->written(1)], [$this->response(1, [], 'x')]);

        $this->assertCount(1, $gemini->calls, 'one call: a second would be cut off at the same place');
        $this->assertEquals(0.0, $result['answers'][0]['ai_confidence']);
        $this->assertStringContainsString('mark this one by hand', $result['answers'][0]['ai_remark']);
    }

    public function test_a_transient_failure_is_raised_when_the_caller_can_retry(): void
    {
        foreach ([new ProviderBusyException('busy'), new RateLimitedException('slow down'), new ConnectionException('timed out')] as $failure) {
            $scorer = new AnswerSheetScoringService(new FakeGeminiClient([$failure]));

            try {
                $scorer->score([$this->written(1)], [$this->response(1, [], 'x')], true);
                $this->fail(get_class($failure) . ' should have been raised');
            } catch (\Throwable $raised) {
                $this->assertSame($failure, $raised);
            }
        }
    }

    public function test_the_same_transient_failure_degrades_gracefully_when_the_caller_cannot_retry(): void
    {
        $result = (new AnswerSheetScoringService(new FakeGeminiClient([new ProviderBusyException('busy')])))
            ->score([$this->objective(1, ['B']), $this->written(2)], [$this->response(1, ['B']), $this->response(2, [], 'x')]);

        $this->assertEquals(2.0, $result['answers'][0]['ai_marks']);
        $this->assertEquals(0.0, $result['answers'][1]['ai_confidence']);
    }

    public function test_a_permanent_failure_is_never_raised_even_to_a_retrying_caller(): void
    {
        $scorer = new AnswerSheetScoringService(new FakeGeminiClient([new \RuntimeException('The AI provider rejected the configured credential.')]));

        $result = $scorer->score([$this->written(1)], [$this->response(1, [], 'x')], true);

        $this->assertEquals(0.0, $result['answers'][0]['ai_confidence']);
    }

    public function test_totals_add_up_across_a_mixed_paper(): void
    {
        $gemini = new FakeGeminiClient([$this->graded(3, 3.5)]);

        $result = (new AnswerSheetScoringService($gemini))->score(
            [$this->objective(1, ['A']), $this->objective(2, ['B']), $this->written(3, 5)],
            [$this->response(1, ['A']), $this->response(2, ['C']), $this->response(3, [], 'x')]
        );

        $this->assertEquals(5.5, $result['ai_total']);
        $this->assertEquals(9.0, $result['max_marks']);
    }
}
