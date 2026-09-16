<?php

namespace Tests\Feature\AI\Fees;

use App\Domain\AI\Conversation\IntentClassifier;
use PHPUnit\Framework\TestCase;

class FeesIntentTest extends TestCase
{
    private IntentClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new IntentClassifier();
    }

    /**
     * @dataProvider feePhrasings
     */
    public function test_fee_phrases_match_the_fees_intent(string $question, string $expected): void
    {
        $intent = $this->classifier->classify($question);

        $this->assertSame($expected, $intent->key, "Question: {$question}");
        $this->assertGreaterThanOrEqual(0.34, $intent->confidence, "Confidence too low for: {$question}");
    }

    /**
     * @return array<int, array{0:string, 1:string}>
     */
    public static function feePhrasings(): array
    {
        return [
            ['How much fee is pending for student 42?', 'fees_query'],
            ['What are the pending fees for class 5?', 'fees_query'],
            ['Show me students with unpaid fees', 'fees_query'],
            ['Are the fees still pending?', 'fees_query'],
            ['Show me the fee collection report', 'fees_query'],
            ['What is the total fee collection?', 'fees_query'],
            ['Show fee summary for this institute', 'fees_query'],
            ['Send a fee reminder to student 42', 'fees_query'],
            ['Who has outstanding dues?', 'fees_query'],
            ['How much is the fee?', 'fees_query'],
            ['Any unpaid invoices?', 'fees_query'],
            ['Are there any defaulters this month?', 'fees_query'],
            ['List all fee payments received', 'fees_query'],
            ['Show me fee receipts', 'fees_query'],
            ['What fees are due?', 'fees_query'],
            ['How many students have arrears?', 'fees_query'],
            ['Generate fee collection report', 'fees_query'],
            ['Show me the fee summary', 'fees_query'],
        ];
    }

    /**
     * @dataProvider feeSlotExtraction
     */
    public function test_fee_phrases_extract_student_id_slot(string $question, int $expectedId): void
    {
        $intent = $this->classifier->classify($question);

        $this->assertSame('fees_query', $intent->key);
        $this->assertSame($expectedId, $intent->slot('student_id'));
    }

    /**
     * @return array<int, array{0:string, 1:int}>
     */
    public static function feeSlotExtraction(): array
    {
        return [
            ['Fee details for student 42', 42],
            ['Student 100 has pending fees', 100],
            ['What is the balance of student 7?', 7],
        ];
    }

    public function test_non_fee_question_does_not_match_fees_intent(): void
    {
        $intent = $this->classifier->classify('How are the students doing in maths?');

        $this->assertNotSame('fees_query', $intent->key);
    }
}