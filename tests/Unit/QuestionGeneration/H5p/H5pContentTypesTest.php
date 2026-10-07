<?php

namespace Tests\Unit\QuestionGeneration\H5p;

use App\Services\QuestionGeneration\H5p\H5pMultipleChoice;
use App\Services\QuestionGeneration\H5p\H5pTrueFalse;
use App\Services\QuestionGeneration\QuestionFormatRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;
use Tests\Unit\QuestionGeneration\Support\TestableGenerationService;

/**
 * The H5P-specific validators and the adapter from a validated question to the row the
 * existing pipeline takes. NO DATABASE, no network.
 */
class H5pContentTypesTest extends TestCase
{
    private const SLOT = ['level' => 'Apply', 'dok' => 2, 'difficulty' => 'Medium', 'points' => 1];

    public static function mcq(array $override = []): array
    {
        return $override + [
            'type' => 'mcq',
            'question' => 'What is the sign of the product of a positive integer and a negative integer?',
            'options' => [
                ['text' => 'Positive', 'is_correct' => false],
                ['text' => 'Negative', 'is_correct' => true],
                ['text' => 'Zero', 'is_correct' => false],
                ['text' => 'It depends on the larger number', 'is_correct' => false],
            ],
            'explanation' => 'A positive times a negative integer always gives a negative integer, so the sign is negative.',
            'hint' => 'Think about repeated addition of a negative number.',
            'knowledge_refs' => ['Product of integers with unlike signs is negative'],
            'learning_outcome' => ['Multiply integers'],
        ];
    }

    public static function trueFalse(array $override = []): array
    {
        return $override + [
            'type' => 'true_false',
            'statement' => 'The product of a positive integer and a negative integer is a negative integer.',
            'answer' => true,
            'explanation' => 'Multiplying unlike signs always gives a negative result, for example 3 x (-2) = -6.',
            'hint' => 'Think about repeated addition.',
            'knowledge_refs' => ['Product of integers with unlike signs is negative'],
            'learning_outcome' => ['Multiply integers'],
        ];
    }

    private function mcqWith(array $options): array
    {
        return self::mcq(['options' => $options]);
    }

    // -- Multiple choice: validation ---------------------------------------------

    public function test_a_well_formed_mcq_is_accepted(): void
    {
        $this->assertNull((new H5pMultipleChoice())->validateQuestion(self::mcq()));
    }

    public static function badMcqs(): array
    {
        $opt = fn (string $t, bool $c = false) => ['text' => $t, 'is_correct' => $c];

        return [
            'unexpected type' => [self::mcq(['type' => 'true_false']), 'type must be "mcq"'],
            'no type' => [array_diff_key(self::mcq(), ['type' => 1]), 'type must be "mcq"'],
            'question missing' => [array_diff_key(self::mcq(), ['question' => 1]), 'question is missing'],
            'question empty' => [self::mcq(['question' => '   ']), 'question is missing'],
            'options missing' => [array_diff_key(self::mcq(), ['options' => 1]), 'exactly 4 options'],
            'options not a list' => [self::mcq(['options' => ['a' => $opt('x', true)]]), 'exactly 4 options'],
            'three options' => [self::mcq(['options' => [$opt('A one', true), $opt('B two'), $opt('C three')]]), 'exactly 4 options, got 3'],
            'five options' => [self::mcq(['options' => [$opt('A one', true), $opt('B two'), $opt('C three'), $opt('D four'), $opt('E five')]]), 'exactly 4 options, got 5'],
            'empty option text' => [self::mcq(['options' => [$opt('', true), $opt('B two'), $opt('C three'), $opt('D four')]]), 'option 1 has no text'],
            'option not an object' => [self::mcq(['options' => ['A', 'B', 'C', 'D']]), 'option 1 is not an object'],
            'is_correct is a string' => [self::mcq(['options' => [$opt('A one'), ['text' => 'B two', 'is_correct' => 'true'], $opt('C three'), $opt('D four')]]), 'option 2 must have a true/false is_correct'],
            'no correct option' => [self::mcq(['options' => [$opt('A one'), $opt('B two'), $opt('C three'), $opt('D four')]]), 'no option is marked correct'],
            'two correct options' => [self::mcq(['options' => [$opt('A one', true), $opt('B two', true), $opt('C three'), $opt('D four')]]), 'exactly one option must be correct, 2'],
            'duplicate options' => [self::mcq(['options' => [$opt('Negative', true), $opt('Positive'), $opt('negative!'), $opt('Zero')]]), 'options 1 and 3 are duplicates'],
            'all of the above' => [self::mcq(['options' => [$opt('Positive'), $opt('Negative', true), $opt('Zero'), $opt('All of the above')]]), 'all/none of the above'],
            'no explanation' => [self::mcq(['explanation' => 'Short.']), 'explanation is required'],
            'no knowledge refs' => [self::mcq(['knowledge_refs' => []]), 'knowledge_refs must be a non-empty list'],
            'blank outcome' => [self::mcq(['learning_outcome' => ['  ']]), 'learning_outcome must contain only non-empty strings'],
        ];
    }

    #[DataProvider('badMcqs')]
    public function test_a_malformed_mcq_is_rejected(array $question, string $expected): void
    {
        $reason = (new H5pMultipleChoice())->validateQuestion($question);

        $this->assertNotNull($reason, 'the malformed question was accepted');
        $this->assertStringContainsString($expected, $reason);
    }

    public function test_two_identical_mcq_stems_are_rejected_as_a_set(): void
    {
        $type = new H5pMultipleChoice();

        $this->assertNull($type->validateSet([self::mcq(), self::mcq(['question' => 'Which sign does 5 x (-3) have, and why does it have it?'])]));
        $this->assertStringContainsString('same question', (string) $type->validateSet([self::mcq(), self::mcq()]));
    }

    // -- Multiple choice: row shape ----------------------------------------------

    public function test_an_mcq_becomes_a_row_the_existing_mcq_validation_accepts(): void
    {
        $row = (new H5pMultipleChoice())->toRow(self::mcq(), self::SLOT, 'ans-2.0');

        $this->assertSame(0, $row['multiple_answer']);
        $this->assertSame(1, $row['points']);
        $this->assertSame('mcq', $row['answer']['question_type']);
        $this->assertSame('MCQ', $row['answer']['sub_type']);
        $this->assertSame('B', $row['answer']['correct_option']);
        $this->assertSame(['A', 'B', 'C', 'D'], array_column($row['answer']['options'], 'label'));
        $this->assertSame([false, true, false, false], array_column($row['answer']['options'], 'is_correct'));
        // Bloom, DOK and difficulty come from the slot, not from the model.
        $this->assertSame(['Apply', 2, 'Medium'], [$row['answer']['bloom_level'], $row['answer']['dok_level'], $row['answer']['difficulty']]);

        $result = $this->service()->validate('mcq', [$row], $this->registry()->get('mcq'));
        $this->assertSame([], $result['skipped'], implode('; ', $result['skipped']));
        $this->assertCount(1, $result['valid']);
    }

    public function test_the_correct_option_carries_the_explanation_as_feedback_and_distractors_none(): void
    {
        $row = (new H5pMultipleChoice())->toRow(self::mcq(), self::SLOT, 'ans-2.0');
        $rows = $this->service()->answerRows(900, $row['answer'], ['sub_institute_id' => 7, 'created_by' => 55]);

        $this->assertCount(4, $rows);
        $this->assertSame([0, 1, 0, 0], array_column($rows, 'correct_answer'));
        $this->assertStringContainsString('always gives a negative', (string) $rows[1]['feedback']);
        $this->assertNull($rows[0]['feedback']);
    }

    public function test_a_remember_slot_is_not_hinted(): void
    {
        $slot = ['level' => 'Remember'] + self::SLOT;

        $this->assertNull((new H5pMultipleChoice())->toRow(self::mcq(), $slot, 'ans-2.0')['hint_text']);
        $this->assertSame('Think about repeated addition of a negative number.', (new H5pMultipleChoice())->toRow(self::mcq(), self::SLOT, 'ans-2.0')['hint_text']);
    }

    // -- True/False: validation --------------------------------------------------

    public function test_a_well_formed_true_false_is_accepted_for_either_verdict(): void
    {
        $type = new H5pTrueFalse();

        $this->assertNull($type->validateQuestion(self::trueFalse()));
        $this->assertNull($type->validateQuestion(self::trueFalse([
            'statement' => 'The product of a positive integer and a negative integer is a positive integer.',
            'answer' => false,
            'explanation' => 'Unlike signs give a negative product, so the product here is negative and not positive.',
        ])));
    }

    public static function badTrueFalse(): array
    {
        $long = implode(' ', array_fill(0, 46, 'word'));

        return [
            'unexpected type' => [self::trueFalse(['type' => 'mcq']), 'type must be "true_false"'],
            'statement missing' => [array_diff_key(self::trueFalse(), ['statement' => 1]), 'statement is missing'],
            'ends with a question mark' => [self::trueFalse(['statement' => 'Is the product of unlike signs negative?']), 'not a question'],
            'opens like a question' => [self::trueFalse(['statement' => 'Does a positive times a negative give a negative number']), 'not a question'],
            'more than 45 words' => [self::trueFalse(['statement' => $long]), 'longer than 45 words'],
            'answer is a string' => [self::trueFalse(['answer' => 'True']), 'JSON boolean'],
            'answer missing' => [array_diff_key(self::trueFalse(), ['answer' => 1]), 'JSON boolean'],
            'answer is null' => [self::trueFalse(['answer' => null]), 'JSON boolean'],
            'double negative' => [self::trueFalse(['statement' => 'It is not true that a negative times a positive is not negative.']), 'more than one negative'],
            'gives the verdict away' => [self::trueFalse(['statement' => 'A negative times a positive is always a negative number.']), '"always"'],
            'explanation missing' => [self::trueFalse(['explanation' => 'Yes.']), 'explanation is required'],
            'explanation contradicts a true verdict' => [self::trueFalse(['explanation' => 'This statement is false because unlike signs give a negative product.']), 'says the statement is false but the answer is true'],
            'explanation contradicts a false verdict' => [self::trueFalse(['answer' => false, 'explanation' => 'True. The product of unlike signs is indeed a negative number here.']), 'says the statement is true but the answer is false'],
            'no knowledge refs' => [self::trueFalse(['knowledge_refs' => 'Product of unlike signs']), 'knowledge_refs must be a non-empty list'],
        ];
    }

    #[DataProvider('badTrueFalse')]
    public function test_a_malformed_true_false_is_rejected(array $question, string $expected): void
    {
        $reason = (new H5pTrueFalse())->validateQuestion($question);

        $this->assertNotNull($reason, 'the malformed question was accepted');
        $this->assertStringContainsString($expected, $reason);
    }

    public function test_several_true_false_statements_must_mix_the_verdicts(): void
    {
        $type = new H5pTrueFalse();
        $true = self::trueFalse();
        $false = self::trueFalse(['statement' => 'The product of two negative integers is a negative integer.', 'answer' => false]);

        $this->assertNull($type->validateSet([$true, $false]));
        $this->assertStringContainsString('do not mix', (string) $type->validateSet([$true, self::trueFalse(['statement' => 'Zero multiplied by any integer gives zero as the product.'])]));
        $this->assertStringContainsString('same question', (string) $type->validateSet([$true, $true]));
    }

    // -- True/False: row shape ---------------------------------------------------

    public function test_a_true_false_becomes_a_row_the_existing_format_prepares_and_validates(): void
    {
        $row = (new H5pTrueFalse())->toRow(self::trueFalse(['answer' => false]), ['level' => 'Understand'] + self::SLOT, 'ans-2.0');

        $this->assertSame('False', $row['answer']['model_answer']);
        $this->assertSame('true_false', $row['answer']['question_type']);

        $format = $this->registry()->get('true_false');
        $result = $this->service()->validate('narrative', [$row], $format);
        $this->assertSame([], $result['skipped'], implode('; ', $result['skipped']));

        $prepared = $format->prepareRow($result['valid'][0]);
        $this->assertFalse($prepared['answer']['statement_truth']);
        $this->assertSame('B', $prepared['answer']['correct_option']);
        $this->assertSame(['True', 'False'], array_column($prepared['answer']['options'], 'text'));
        $this->assertSame([false, true], array_column($prepared['answer']['options'], 'is_correct'));
    }

    // -- plumbing ----------------------------------------------------------------

    private function registry(): QuestionFormatRegistry
    {
        return new QuestionFormatRegistry(FakeCatalogue::live());
    }

    private function service(): TestableGenerationService
    {
        return new TestableGenerationService($this->registry());
    }
}
