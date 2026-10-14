<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\ConversationStore;
use App\Domain\AI\Conversation\IntentClassifier;
use PHPUnit\Framework\TestCase;

/**
 * After a student's fee details, "send an SMS fee reminder to this student" must start the
 * reminder, not re-show the student; and "send an SMS to all 10" in a fee thread is a reminder too.
 */
class FeesReminderFollowUpRoutingTest extends TestCase
{
    private function memory(): array
    {
        return ['selected_record' => [
            'item' => ['position' => 1, 'id' => 9, 'title' => 'MARYA FIROZ KHAN PATHAN', 'row' => ['student_id' => 9]],
            'singular' => 'student', 'module' => 'fees', 'set' => null,
        ]];
    }

    private function resolve(string $question, array $memory): string
    {
        $intent = (new IntentClassifier())->classify($question);
        [$resolved] = (new ConversationStore())->resolveReferents($intent, $memory, $question);

        return $resolved->key;
    }

    public function test_the_reminder_request_is_not_turned_into_a_record_lookup(): void
    {
        $this->assertSame('fees_send_reminders', $this->resolve('Send an SMS fee reminder to this student.', $this->memory()));
    }

    public function test_a_reminder_with_no_fee_noun_in_a_fee_thread_is_still_the_reminder(): void
    {
        $this->assertSame('fees_send_reminders', $this->resolve('send an SMS to all 10 by 31 October', $this->memory()));
    }

    public function test_these_students_names_the_rows_just_listed_so_the_fee_screen_keeps_the_turn(): void
    {
        foreach (['Now show the details of these students.', 'Show the fee details of those students', 'details of the above students'] as $sentence) {
            $this->assertTrue(ConversationStore::namesTheListedRows($sentence), $sentence);
        }

        foreach (['Show students with pending fees', 'Which students owe the most?', 'Show the reminder status.'] as $sentence) {
            $this->assertFalse(ConversationStore::namesTheListedRows($sentence), $sentence);
        }
    }

    public function test_the_same_sentence_outside_a_fee_thread_is_left_alone(): void
    {
        $this->assertNotSame('fees_send_reminders', $this->resolve('send an SMS to all 10 by 31 October', []));
    }
}
