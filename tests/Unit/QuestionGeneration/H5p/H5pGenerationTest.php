<?php

namespace Tests\Unit\QuestionGeneration\H5p;

use App\Services\QuestionGeneration\H5p\H5pPrompts;
use App\Services\QuestionGeneration\QuestionFormatRegistry;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;
use Tests\Unit\QuestionGeneration\Support\TestableGenerationService;

/**
 * QuestionGenerationService::generate() for the H5P-driven formats, end to end:
 * request -> H5P prompt -> (fake) Claude -> H5P validation -> existing row validation
 * -> existing persistence. Nothing is saved without passing every check.
 *
 * NO DATABASE, no network: TestableGenerationService replaces the I/O edges, including
 * the Claude call, and the catalogue is FakeCatalogue::live().
 */
class H5pGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['question_formats.h5p' => [
            'enabled' => true, 'types' => ['mcq', 'true_false'], 'questions_per_type' => 2, 'max_attempts' => 3,
        ]]);
    }

    private function service(bool $withContent = true): TestableGenerationService
    {
        $service = new TestableGenerationService(new QuestionFormatRegistry(FakeCatalogue::live()));
        $service->forInstitute(7);
        $service->h5p = true;
        if ($withContent) {
            $service->intel = (object) [
                'knowledge' => json_encode([['knowledge' => 'Product of integers with unlike signs is negative']]),
                'learning_outcomes' => json_encode([['outcome' => 'Multiply integers']]),
            ];
        }

        return $service;
    }

    private function input(array $override = []): array
    {
        return $override + [
            'concept_id' => 123, 'subject_id' => 3, 'standard_id' => 8, 'chapter_id' => 11,
            'question_format_code' => 'mcq', 'total_questions' => 2,
            'sub_institute_id' => 7, 'created_by' => 55,
        ];
    }

    private function mcqs(): array
    {
        return [
            H5pContentTypesTest::mcq(),
            H5pContentTypesTest::mcq([
                'question' => 'What is the value of 6 x (-4)?',
                'options' => [
                    ['text' => '24', 'is_correct' => false],
                    ['text' => '-10', 'is_correct' => false],
                    ['text' => '-24', 'is_correct' => true],
                    ['text' => '10', 'is_correct' => false],
                ],
            ]),
        ];
    }

    private function trueFalses(): array
    {
        return [
            H5pContentTypesTest::trueFalse(),
            H5pContentTypesTest::trueFalse([
                'statement' => 'The product of two negative integers is a negative integer.',
                'answer' => false,
                'explanation' => 'Two negative signs cancel, so the product of two negative integers is positive.',
            ]),
        ];
    }

    private function reply(array $questions): array
    {
        return ['content' => json_encode(['questions' => $questions])];
    }

    // -- exactly two questions of the selected type -------------------------------

    public function test_h5p_multiple_choice_returns_two_valid_mcqs_saved_through_the_existing_pipeline(): void
    {
        $service = $this->service();
        $service->claudeResponses = [$this->reply($this->mcqs())];

        $result = $service->generate($this->input());

        $this->assertTrue($result['status'], $result['message']);
        $this->assertSame(2, $result['data']['requested']);
        $this->assertSame(2, $result['data']['generated']);
        $this->assertSame(2, $result['data']['inserted']);
        $this->assertSame('mcq', $result['data']['question_format_code']);
        $this->assertSame('fake-claude', $result['data']['model']);
        $this->assertSame([11, 22], [$result['data']['input_tokens'], $result['data']['output_tokens']]);
        $this->assertCount(1, $service->claudeCalls, 'one Claude call for one batch of two');
        $this->assertSame([], $service->calls, 'DeepSeek is not called for an H5P content type');

        $saved = $service->persisted[0];
        $this->assertSame('mcq', $saved['ctx']['format_code'], 'the selected type survives to the save');
        $this->assertSame(1, $saved['ctx']['question_type_id']);
        $this->assertSame('H5P.MultiChoice', $saved['meta']['h5p_type']);
        $this->assertCount(2, $saved['resp']['rows']);

        foreach ($saved['resp']['rows'] as $row) {
            $options = $row['answer']['options'];
            $this->assertCount(4, $options);
            $this->assertCount(1, array_filter($options, fn ($o) => $o['is_correct']));
            $this->assertCount(4, array_unique(array_column($options, 'text')));
            $this->assertContains($row['answer']['correct_option'], ['A', 'B', 'C', 'D']);
            $this->assertSame(0, $row['multiple_answer']);
            // The answer_master rows the existing save builds from it.
            $answers = $service->answerRows(1, $row['answer'], ['sub_institute_id' => 7, 'created_by' => 55]);
            $this->assertSame(1, array_sum(array_column($answers, 'correct_answer')));
        }
    }

    public function test_h5p_true_false_returns_two_valid_statements_saved_through_the_existing_pipeline(): void
    {
        $service = $this->service();
        $service->claudeResponses = [$this->reply($this->trueFalses())];

        $result = $service->generate($this->input(['question_format_code' => 'true_false']));

        $this->assertTrue($result['status'], $result['message']);
        $this->assertSame([2, 2, 2], [$result['data']['requested'], $result['data']['generated'], $result['data']['inserted']]);
        $this->assertSame('true_false', $result['data']['question_format_code']);
        $this->assertSame([], $service->calls);

        $saved = $service->persisted[0];
        $this->assertSame('true_false', $saved['ctx']['format_code']);
        $this->assertSame('H5P.TrueFalse', $saved['meta']['h5p_type']);

        $verdicts = [];
        foreach ($saved['resp']['rows'] as $row) {
            $this->assertContains($row['answer']['model_answer'], ['True', 'False']);
            $this->assertIsBool($row['answer']['statement_truth']);
            $this->assertStringEndsNotWith('?', $row['question_title']);
            $this->assertLessThanOrEqual(45, str_word_count($row['question_title']));
            $this->assertSame(['True', 'False'], array_column($row['answer']['options'], 'text'));
            $verdicts[] = $row['answer']['model_answer'];
        }
        sort($verdicts);
        $this->assertSame(['False', 'True'], $verdicts);
    }

    public function test_the_count_is_fixed_whatever_the_client_asks_for(): void
    {
        $service = $this->service();
        $service->claudeResponses = [$this->reply($this->mcqs())];

        $result = $service->generate($this->input([
            'total_questions' => 40,
            'quota' => [['level' => 'Create', 'count' => 40, 'difficulty' => 'Hard']],
        ]));

        $this->assertTrue($result['status'], $result['message']);
        $this->assertSame(2, $result['data']['requested']);
        $this->assertStringContainsString('Write 2 multiple-choice question(s)', $service->claudeCalls[0]['user']);
    }

    public function test_two_selected_types_write_two_each(): void
    {
        $service = $this->service();
        $service->claudeResponses = [$this->reply($this->mcqs()), $this->reply($this->trueFalses())];

        $result = $service->generate($this->input([
            'question_format_code' => null,
            'question_format_codes' => ['mcq', 'true_false'],
            'total_questions' => 4,
        ]));

        $this->assertTrue($result['status'], $result['message']);
        $this->assertSame([4, 4, 4], [$result['data']['requested'], $result['data']['generated'], $result['data']['inserted']]);
        $this->assertCount(2, $service->claudeCalls);
        $this->assertSame(['mcq', 'true_false'], array_column($result['data']['formats'], 'question_format_code'));
        $this->assertSame([2, 2], array_column($result['data']['formats'], 'requested'));
    }

    // -- the H5P type chooses the prompt ------------------------------------------

    public function test_the_prompt_is_the_one_for_the_selected_h5p_type(): void
    {
        $mcq = $this->service();
        $mcq->claudeResponses = [$this->reply($this->mcqs())];
        $mcq->generate($this->input());

        $tf = $this->service();
        $tf->claudeResponses = [$this->reply($this->trueFalses())];
        $tf->generate($this->input(['question_format_code' => 'true_false']));

        $mcqPrompt = $mcq->claudeCalls[0]['user'];
        $tfPrompt = $tf->claudeCalls[0]['user'];

        $this->assertStringContainsString('H5P "Multiple Choice" (H5P.MultiChoice)', $mcqPrompt);
        $this->assertStringContainsString('exactly four selectable options', $mcqPrompt);
        $this->assertStringContainsString('"const": "mcq"', $mcqPrompt);
        $this->assertStringNotContainsString('True/False', $mcqPrompt);
        $this->assertStringNotContainsString('statement', strtolower(explode('## H5P CONTENT TYPE', $mcqPrompt)[1]));

        $this->assertStringContainsString('H5P "True/False" (H5P.TrueFalse)', $tfPrompt);
        $this->assertStringContainsString('ONE declarative statement', $tfPrompt);
        $this->assertStringContainsString('"const": "true_false"', $tfPrompt);
        $this->assertStringNotContainsString('exactly four', $tfPrompt);

        foreach ([$mcq, $tf] as $service) {
            $this->assertSame(H5pPrompts::system(), $service->claudeCalls[0]['system']);
            $this->assertStringContainsString('Do not wrap the JSON in ```json fences', $service->claudeCalls[0]['user']);
        }
    }

    public function test_the_prompt_keeps_the_existing_context_the_deepseek_prompts_carried(): void
    {
        $service = $this->service();
        $service->corpus = ['An existing stem about integer signs'];
        $service->claudeResponses = [$this->reply($this->mcqs())];

        $service->generate($this->input(['diagnostic_stage' => 'concept_diagnostic']));
        $prompt = $service->claudeCalls[0]['user'];

        $this->assertStringContainsString('Multiplication of integers', $prompt, 'concept name');
        $this->assertStringContainsString('Product of integers with unlike signs is negative', $prompt, 'knowledge items');
        $this->assertStringContainsString('Multiply integers', $prompt, 'learning outcomes');
        $this->assertStringContainsString('An existing stem about integer signs', $prompt, 'dedup corpus');
        $this->assertStringContainsString('SEMANTIC_CONCEPT_KEY', $prompt);
        $this->assertStringContainsString('concept_diagnostic', $prompt, 'ESO stage');
        $this->assertMatchesRegularExpression("/Question 1 -> Bloom's level: \w+; difficulty: (Easy|Medium|Hard)/", $prompt);
        $this->assertMatchesRegularExpression("/Question 2 -> Bloom's level/", $prompt);
    }

    // -- invalid output is rejected, retried with the reason, never saved ----------

    public function test_invalid_output_is_retried_with_the_reason_then_saved_once_it_is_valid(): void
    {
        $three = $this->mcqs();
        $three[0]['options'] = array_slice($three[0]['options'], 0, 3);

        $service = $this->service();
        $service->claudeResponses = [$this->reply($three), $this->reply($this->mcqs())];

        $result = $service->generate($this->input());

        $this->assertTrue($result['status'], $result['message']);
        $this->assertCount(2, $service->claudeCalls);
        $retry = $service->claudeCalls[1]['user'];
        $this->assertStringContainsString('YOUR PREVIOUS ANSWER WAS REJECTED', $retry);
        $this->assertStringContainsString('question 1: there must be exactly 4 options, got 3', $retry);
        $this->assertStringStartsWith(explode('## YOUR PREVIOUS', $retry)[0], $retry);
        $this->assertCount(1, $service->persisted);
        $this->assertCount(2, $service->persisted[0]['resp']['rows']);
    }

    public function test_malformed_json_and_a_truncated_reply_are_retried(): void
    {
        $service = $this->service();
        $service->claudeResponses = [
            ['content' => 'Sure! Here are your questions: not json at all'],
            ['content' => '{"questions":[{"type":"mcq"', 'finish_reason' => 'length'],
            $this->reply($this->mcqs()),
        ];

        $result = $service->generate($this->input());

        $this->assertTrue($result['status'], $result['message']);
        $this->assertCount(3, $service->claudeCalls);
        $this->assertStringContainsString('not a single valid JSON object', $service->claudeCalls[1]['user']);
        $this->assertStringContainsString('cut off', $service->claudeCalls[2]['user']);
    }

    public function test_output_that_never_validates_is_not_saved_and_the_loop_is_bounded(): void
    {
        $bad = $this->mcqs();
        $bad[1]['options'][0]['is_correct'] = true; // two correct options

        $service = $this->service();
        $service->claudeResponses = [$this->reply($bad), $this->reply($bad), $this->reply($bad), $this->reply($this->mcqs())];

        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('failed validation after 3 attempt(s)', $result['message']);
        $this->assertStringContainsString('exactly one option must be correct', $result['message']);
        $this->assertCount(3, $service->claudeCalls, 'never more than max_attempts calls');
        $this->assertSame([], $service->persisted, 'nothing invalid was saved');
        $this->assertCount(1, $service->claudeResponses, 'the 4th reply was never requested');
    }

    public function test_the_attempt_limit_is_configurable(): void
    {
        config(['question_formats.h5p.max_attempts' => 1]);
        $service = $this->service();
        $service->claudeResponses = [$this->reply([]), $this->reply($this->mcqs())];

        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertCount(1, $service->claudeCalls);
    }

    public function test_the_wrong_number_of_questions_is_rejected(): void
    {
        $service = $this->service();
        $service->claudeResponses = [
            $this->reply([$this->mcqs()[0]]),
            $this->reply(array_merge($this->mcqs(), [H5pContentTypesTest::mcq(['question' => 'A third, unrequested question about integer products?'])])),
            $this->reply($this->mcqs()),
        ];

        $result = $service->generate($this->input());

        $this->assertTrue($result['status'], $result['message']);
        $this->assertCount(3, $service->claudeCalls);
        $this->assertStringContainsString('exactly 2 question(s) are required, got 1', $service->claudeCalls[1]['user']);
        $this->assertStringContainsString('exactly 2 question(s) are required, got 3', $service->claudeCalls[2]['user']);
    }

    public function test_a_question_of_another_type_is_rejected_not_converted(): void
    {
        $service = $this->service();
        $service->claudeResponses = [$this->reply($this->trueFalses()), $this->reply($this->trueFalses()), $this->reply($this->trueFalses())];

        $result = $service->generate($this->input()); // mcq selected, true/false returned

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('type must be "mcq"', $result['message']);
        $this->assertSame([], $service->persisted);
    }

    public function test_true_false_statements_that_share_a_verdict_are_rejected(): void
    {
        $both = [H5pContentTypesTest::trueFalse(), H5pContentTypesTest::trueFalse(['statement' => 'Zero multiplied by any integer gives zero as the product.'])];

        $service = $this->service();
        $service->claudeResponses = [$this->reply($both), $this->reply($this->trueFalses())];

        $result = $service->generate($this->input(['question_format_code' => 'true_false']));

        $this->assertTrue($result['status'], $result['message']);
        $this->assertCount(2, $service->claudeCalls);
        $this->assertStringContainsString('do not mix True and False', $service->claudeCalls[1]['user']);
    }

    public function test_a_provider_failure_is_reported_and_not_retried(): void
    {
        $service = $this->service();
        $service->claudeResponses = [['error' => 'The Claude API key was rejected. Check ANTHROPIC_API_KEY.'], $this->reply($this->mcqs())];

        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('API key was rejected', $result['message']);
        $this->assertCount(1, $service->claudeCalls);
        $this->assertSame([], $service->persisted);
    }

    public function test_a_concept_with_no_extracted_content_does_not_spend_a_call(): void
    {
        $service = $this->service(false);
        $service->claudeResponses = [$this->reply($this->mcqs())];

        $result = $service->generate($this->input());

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('no extracted knowledge', $result['message']);
        $this->assertSame([], $service->claudeCalls);
        $this->assertSame([], $service->persisted);
    }

    // -- everything else is untouched ----------------------------------------------

    public function test_a_format_without_an_h5p_type_still_goes_to_deepseek(): void
    {
        $service = $this->service();
        $service->responses = [['error' => 'stop here']];

        $service->generate($this->input(['question_format_code' => 'fill_blank']));

        $this->assertCount(1, $service->calls, 'DeepSeek was called');
        $this->assertSame([], $service->claudeCalls, 'Claude was not');
    }

    public function test_switching_the_layer_off_restores_the_deepseek_path_for_mcq(): void
    {
        config(['question_formats.h5p.enabled' => false]);
        $service = $this->service();
        $service->responses = [['error' => 'stop here']];

        $service->generate($this->input(['total_questions' => 9]));

        $this->assertCount(1, $service->calls);
        $this->assertSame([], $service->claudeCalls);
        $this->assertStringContainsString('## CONSTRUCTION RULES (MCQ)', $service->calls[0]['user'], 'the legacy MCQ prompt');
        $this->assertStringContainsString('Write 9 multiple-choice rows', $service->calls[0]['user'], "the client's total is used again");
    }
}
