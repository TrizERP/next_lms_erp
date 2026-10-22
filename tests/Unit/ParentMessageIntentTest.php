<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\IntentClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Which sentences are a request to message parents, and which belong to someone else.
 *
 * The classifier is estate-wide, so this intent has to be narrow: it must never take a fee reminder,
 * a fee follow-up or an ordinary question.
 */
class ParentMessageIntentTest extends TestCase
{
    private function key(string $sentence): string
    {
        return (new IntentClassifier())->classify($sentence)->key;
    }

    /** @return array<string, array{0:string}> */
    public static function parentMessages(): array
    {
        return [
            'sms with class' => ['Send an SMS to the parents of class 10 A saying school is closed tomorrow.'],
            'whatsapp to parents' => ['Send a WhatsApp message to the parents of standard 8 about the exam timetable'],
            'notify parents' => ['Notify the parents of grade 5 that the picnic is cancelled'],
            "common word 'due'" => ['Send an SMS to the parents of class 9 saying school is closed due to heavy rain'],
            'whatsapp to a class' => ['WhatsApp class 7 B that the PTM is on Friday'],
            'message the parents' => ['Message the parents of Aarav Shah about his result'],
            'option by name: sms' => ['Send SMS'],
            'option by name: whatsapp' => ['Send WhatsApp Message'],
            'sms straight to a student' => ['Send SMS to Aarav Shah saying he may collect his result'],
            'whatsapp straight to a student' => ['Send a WhatsApp message to Meena Joshi'],
        ];
    }

    #[DataProvider('parentMessages')]
    public function test_a_request_to_message_parents_is_recognised(string $sentence): void
    {
        $this->assertSame('parent_message_send', $this->key($sentence));
    }

    /** @return array<string, array{0:string}> */
    public static function notParentMessages(): array
    {
        return [
            'a fee reminder' => ['Send an SMS fee reminder to the parents of students who owe fees'],
            'fees due to parents' => ['Send an SMS to the parents of defaulters about the unpaid fees'],
            'a fee follow-up with no parents named' => ['send an SMS to all 10 by 31 October'],
            'a question about sent messages' => ['How many SMS messages were sent this week?'],
            'a lookup' => ['Which students are in class 10 A?'],
            'a fee follow-up to everyone' => ['send an SMS to all 10 by 31 October'],
            'a fee follow-up to them' => ['send an SMS to them'],
            'sms to the top students' => ['send an sms to the top 3'],
        ];
    }

    #[DataProvider('notParentMessages')]
    public function test_other_sentences_are_left_alone(string $sentence): void
    {
        $this->assertNotSame('parent_message_send', $this->key($sentence));
    }
    /** @return array<string, array{0:string}> */
    public static function statusQuestions(): array
    {
        return [
            'message status' => ['Show message status'],
            'sms status' => ['Show SMS status'],
            'whatsapp delivered' => ['Was the WhatsApp delivered?'],
            'were the messages sent' => ['Were the SMS messages sent?'],
            'did it reach' => ['Did the message reach the parent?'],
            'whatsapp history' => ['Show the WhatsApp message history'],
        ];
    }

    #[DataProvider('statusQuestions')]
    public function test_a_question_about_messages_already_sent_is_a_status_question(string $sentence): void
    {
        $this->assertSame('parent_message_status', $this->key($sentence));
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function neverStatus(): array
    {
        return [
            'a fee reminder status' => ['Show reminder status.', 'fees_reminder_status'],
            'were the reminders sent' => ['Were the reminders sent?', 'fees_reminder_status'],
            'sending a message' => ['Send SMS to Aarav Shah saying hello', 'parent_message_send'],
            'a whatsapp fee reminder' => ["Prepare a WhatsApp fee reminder for this student's parent.", 'fees_send_reminders'],
            'draft an sms fee reminder' => ['Draft an SMS fee reminder for class 6', 'fees_send_reminders'],
            'check whatsapp configuration' => ['Check whether the WhatsApp configuration and template are valid.', 'channel_health'],
            'check sms configuration' => ['Check whether the SMS configuration is valid', 'channel_health'],
            'is whatsapp configured' => ['Is WhatsApp configured properly?', 'channel_health'],
            'message did not arrive' => ['The WhatsApp message did not arrive', 'channel_health'],
        ];
    }

    #[DataProvider('neverStatus')]
    public function test_status_never_takes_a_fee_reminder_or_a_new_message(string $sentence, string $expected): void
    {
        $this->assertSame($expected, $this->key($sentence));
    }
}
