<?php

namespace Tests\Unit;

use App\Domain\AI\Lifecycle\Flows\FeesStudentDetailFlow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Which sentences are about what ONE student PAID on a date — as opposed to what they owe, or what the
 * institute collected. Pure: no database, no model.
 */
class FeesStudentPaymentQuestionTest extends TestCase
{
    /** @return array<string, array{0:string,1:string}> */
    public static function paymentQuestions(): array
    {
        return [
            'yesterday' => ['How much fee did Greeva Rafaliya pay yesterday?', 'Greeva Rafaliya'],
            'explicit date' => ['How much did Pari Dipak Vyas pay on 30 September 2026?', 'Pari Dipak Vyas'],
            'receipt details with a trailing date' => ['Show receipt details of Pari Dipak Vyas on 30 September 2026', 'Pari Dipak Vyas'],
            'last 7 days' => ['How much did Sonika Pansuriya pay in the last 7 days?', 'Sonika Pansuriya'],
            'a month' => ['How much did Sonika P Pansuriya pay in September 2026?', 'Sonika P Pansuriya'],
            'between' => ['How much did Pari Dipak Vyas pay between 1 September and 30 September 2026?', 'Pari Dipak Vyas'],
            'has paid' => ['Has Pari Dipak Vyas paid on 13 October 2026?', 'Pari Dipak Vyas'],
        ];
    }

    #[DataProvider('paymentQuestions')]
    public function test_a_student_and_a_date_asked_about_paying_is_a_payment_question(string $question, string $name): void
    {
        $target = FeesStudentDetailFlow::paymentTarget($question, []);

        $this->assertNotNull($target, $question);
        $this->assertSame($name, $target['name'], 'the date must not stay on the name');
    }

    /** @return array<string, array{0:string}> */
    public static function notPaymentQuestions(): array
    {
        return [
            'what they owe' => ['How much does Pari Dipak Vyas owe?'],
            'fee details' => ['Show the fee details for Pari Dipak Vyas'],
            'balance on a date is still the ledger' => ['What is the outstanding balance of Pari Dipak Vyas?'],
            'institute-wide collection' => ['Show me the fees collected yesterday'],
            'no student named' => ['How much was collected in the last 7 days?'],
            // Dues, not receipts: these keep the ledger answer.
            'has paid, no date' => ['Has Pari Dipak Vyas paid?'],
        ];
    }

    public function test_how_much_did_a_student_pay_with_no_day_asks_for_the_period_instead_of_describing_a_balance(): void
    {
        $target = FeesStudentDetailFlow::paymentTarget('How much did Pari Dipak Vyas pay?', []);

        $this->assertSame(['name' => 'Pari Dipak Vyas', 'needs_period' => true], $target);
    }

    #[DataProvider('notPaymentQuestions')]
    public function test_other_fee_questions_do_not_take_the_payment_route(string $question): void
    {
        $this->assertNull(FeesStudentDetailFlow::paymentTarget($question, []), $question);
    }

    public function test_a_follow_up_with_a_date_alone_is_about_the_student_the_thread_was_on(): void
    {
        $memory = ['last_period' => ['from' => '2026-10-13', 'to' => '2026-10-13', 'label' => '13 October 2026', 'tool' => 'fees.collection_report', 'student_id' => 97926]];

        foreach (['And on 14 August?', 'What about last month?', 'How much in cash?', 'How much did she pay in the last 7 days?', 'Who paid?'] as $question) {
            $this->assertSame(['id' => 97926], FeesStudentDetailFlow::paymentTarget($question, $memory), $question);
        }
    }

    public function test_an_institute_wide_question_is_not_taken_over_by_the_student_the_thread_was_on(): void
    {
        $memory = ['last_period' => ['from' => '2026-10-13', 'to' => '2026-10-13', 'label' => '13 October 2026', 'tool' => 'fees.collection_report', 'student_id' => 97926]];

        foreach (['Show me the fees collected yesterday', 'How much was collected in total last month?', 'Which students have pending fees?'] as $question) {
            $this->assertNull(FeesStudentDetailFlow::paymentTarget($question, $memory), $question);
        }
    }

    public function test_a_follow_up_with_no_student_on_the_thread_names_no_one(): void
    {
        $this->assertNull(FeesStudentDetailFlow::paymentTarget('And on 14 August?', []));
    }

    public function test_a_pronoun_is_never_taken_for_a_name(): void
    {
        $memory = ['last_period' => ['from' => '2026-10-13', 'to' => '2026-10-13', 'tool' => 'fees.collection_report', 'student_id' => 7]];

        $this->assertSame(['id' => 7], FeesStudentDetailFlow::paymentTarget('How much did he pay yesterday?', $memory));
        $this->assertNull(FeesStudentDetailFlow::paymentTarget('How much did he pay yesterday?', []), 'no student on the thread, so no one to mean');
    }

    public function test_a_comparison_names_both_students_and_needs_a_date(): void
    {
        $this->assertSame(
            ['compare' => ['Pari Dipak Vyas', 'Sonika P Pansuriya']],
            FeesStudentDetailFlow::paymentTarget('Compare what Pari Dipak Vyas and Sonika P Pansuriya paid in September 2026', [])
        );
        $this->assertNull(FeesStudentDetailFlow::paymentTarget('Compare what Pari Dipak Vyas and Sonika P Pansuriya paid', []));
        $this->assertNull(FeesStudentDetailFlow::comparisonNames('Compare what Pari and Pari paid in September 2026'), 'the same student twice is not a comparison');
    }
}
