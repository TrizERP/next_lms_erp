<?php

namespace App\Domain\AI\Lifecycle\Support;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageOutcome;

/**
 * What the assistant says at each step of a parent message (SMS or WhatsApp).
 *
 * Rendered from the flow's own state and the provider's own replies; nothing is inferred and no model
 * writes it. In particular a message is only ever described as "accepted" — by the SMS gateway or by
 * WhatsApp — never as delivered, because neither provider confirms that in the same breath.
 */
class ParentMessageAnswer
{
    public function __construct(private readonly AnswerComposer $compose, private readonly ?ChannelHealthAnswer $health = null)
    {
    }

    /**
     * @param  array<string, mixed>  $flow  `ParentMessageFlow` state.
     */
    public function compose(StageContext $context, array $flow): StageOutcome
    {
        $state = (string) ($flow['state'] ?? 'blocked');
        $data = is_array($flow['data'] ?? null) ? $flow['data'] : [];
        $message = (string) ($flow['message'] ?? '');

        // What the flow decided should be remembered — or forgotten — is carried out by the
        // orchestrator after the pipeline finishes.
        $context->set('pending_action_next', $flow['pending'] ?? null);

        return match ($state) {
            'need_channel' => $this->channelChoice($context, $message),
            'need_recipients' => $this->asking($context, $message, 'Who should get it', 'Name a student such as "Aarav Shah" (or "student 4512"), or a class such as "class 10 A". Say "cancel" to stop.', ['Class 10 A.', 'Cancel.']),
            'need_message' => $this->wording($context, $data, $message),
            'ready' => $this->ready($context, $data, $message),
            'sent' => $this->sent($context, $data, $message),
            'status' => $this->status($context, $data, $message),
            'health' => ($this->health ?? app(ChannelHealthAnswer::class))->compose($context, $data, $message),
            'cancelled' => $this->stopped($context),
            default => $this->settled($context, $message !== '' ? $message : 'The message could not be prepared.', $data, $state),
        };
    }

    /**
     * The two options, by name. Each is a button that sends the same words a person would type.
     */
    private function channelChoice(StageContext $context, string $message): StageOutcome
    {
        $context->setHeadline($message !== '' ? $message : 'How should I send it?');
        $context->addSection($this->compose->text(
            'Choose how to send it',
            "• Send SMS — through the school's SMS gateway, the same one Send SMS to Parents uses.\n"
            ."• Send WhatsApp Message — through the school's WhatsApp Business account.\n"
            .'Nothing is sent until you have reviewed the message and approved it. Say "cancel" to stop.'
        ));
        $context->addAction($this->compose->action('choose_sms', 'Send SMS', 'parent_message_send', ['utterance' => 'Send SMS'], 'primary'));
        $context->addAction($this->compose->action('choose_whatsapp', 'Send WhatsApp Message', 'parent_message_send', ['utterance' => 'Send WhatsApp Message'], 'default'));
        $context->suggestFollowUp('Send SMS', 'Send WhatsApp Message', 'Cancel.');

        return StageOutcome::pending('A parent message is waiting on the channel.', ['sent' => false]);
    }

    /**
     * The student is chosen and the channel is known: show who and through what, then ask for the wording.
     *
     * @param  array<string, mixed>  $data
     */
    private function wording(StageContext $context, array $data, string $message): StageOutcome
    {
        $details = is_array($data['details'] ?? null) ? $data['details'] : [];
        $students = is_array($details['students'] ?? null) ? $details['students'] : [];
        $template = is_array($details['template'] ?? null) ? $details['template'] : [];
        $label = (string) ($details['channel_label'] ?? 'Message');

        $context->setHeadline($message !== '' ? $message : 'What should the message say?');

        if ($students !== []) {
            $context->addSection($this->compose->records(
                count($students) === 1 ? 'Selected student' : 'Selected students',
                array_map(static fn (array $row) => [
                    'title' => (string) ($row['name'] ?? ''),
                    'lines' => array_values(array_filter([
                        ! empty($row['class']) ? 'Class '.$row['class'] : null,
                        ! empty($row['enrollment_no']) ? 'GR no '.$row['enrollment_no'] : null,
                        ! empty($row['contact_masked']) ? 'Registered mobile: '.$row['contact_masked'] : null,
                        ! empty($row['problem']) ? 'Will NOT be messaged: '.$row['problem'] : null,
                    ])),
                    'meta' => [],
                ], array_slice($students, 0, 25))
            ));
        }

        $context->addSection($this->compose->keyValues($label.' details', array_filter([
            'Sent through' => (string) ($details['provider']['name'] ?? '').(! empty($details['provider']['host']) ? ' ('.$details['provider']['host'].')' : ''),
            'Template' => $this->templateLine($template),
            'Longest message' => isset($details['limit']) ? $details['limit'].' characters' : null,
        ], static fn ($value) => $value !== null && $value !== '')));

        if (! empty($template['note'])) {
            $context->addSection($this->compose->text('About the template', (string) $template['note']));
        }

        $variables = is_array($details['variables'] ?? null) ? $details['variables'] : [];

        $context->addSection($this->compose->text(
            'What should it say?',
            'Reply with the wording. You can use '.($variables === [] ? 'no variables' : implode(', ', array_map(
                static fn (string $name, string $meaning) => $name.' ('.$meaning.')',
                array_keys($variables),
                array_values($variables)
            ))).', each filled in from the student\'s own record. You will see the finished message before anything is sent. Say "cancel" to stop.'
        ));

        $context->suggestFollowUp('Cancel.');

        return StageOutcome::pending('A parent message is waiting on its wording from the user.', ['sent' => false]);
    }

    /** @param  array<string, mixed>  $template */
    private function templateLine(array $template): string
    {
        return match ((string) ($template['kind'] ?? '')) {
            'signature' => 'Saved text added to every SMS: "'.($template['text'] ?? '').'"',
            'gateway_parameter' => 'Gateway template parameter: '.($template['name'] ?? ''),
            'cloud_template' => 'WhatsApp template: '.($template['name'] ?? '').(! empty($template['language']) ? ' — language '.$template['language'] : ''),
            'none' => 'None saved — sent exactly as written',
            default => '',
        };
    }

    /**
     * The saved record of parent messages: each one in full (student, recipient, wording, the provider's reply, the
     * delivery state) with the latest send first.
     *
     * @param  array<string, mixed>  $data
     */
    private function status(StageContext $context, array $data, string $message): StageOutcome
    {
        $messages = is_array($data['messages'] ?? null) ? $data['messages'] : [];

        if (! empty($data['asked_about_delivery']) && $messages !== []) {
            $latest = (string) ($messages[0]['batch'] ?? '');
            $mine = array_values(array_filter($messages, static fn (array $row) => (string) ($row['batch'] ?? '') === $latest));
            $by = array_count_values(array_column($mine, 'delivery_status'));
            $channel = ($mine[0]['channel'] ?? 'sms') === 'sms' ? 'SMS' : 'WhatsApp';

            $parts = array_filter([
                ($by['read'] ?? 0) > 0 ? sprintf('%d read', $by['read']) : null,
                ($by['delivered'] ?? 0) > 0 ? sprintf('%d delivered', $by['delivered']) : null,
                ($by['pending'] ?? 0) > 0 ? sprintf('%d accepted and still waiting for a delivery report', $by['pending']) : null,
                ($by['not_confirmed'] ?? 0) > 0 ? sprintf('%d accepted but NOT confirmed delivered — the provider has reported no delivery', $by['not_confirmed']) : null,
                ($by['unknown'] ?? 0) > 0 ? sprintf('%d accepted but delivery is unknown (no delivery report exists for it)', $by['unknown']) : null,
                ($by['failed'] ?? 0) + ($by['not_delivered'] ?? 0) > 0 ? sprintf('%d not delivered', ($by['failed'] ?? 0) + ($by['not_delivered'] ?? 0)) : null,
            ]);

            $message = sprintf('Latest %s send (%s): %s.', $channel, $latest !== '' ? $latest : 'most recent', implode('; ', $parts) ?: 'nothing was accepted');
        }

        $context->setHeadline($message);

        if ($messages !== []) {
            $delivery = is_array($data['delivery'] ?? null) ? $data['delivery'] : [];

            $context->addSection($this->compose->keyValues('Totals', array_filter([
                'Accepted by the provider' => (string) ($data['accepted'] ?? 0),
                'Failed (not accepted)' => (string) ($data['failed'] ?? 0),
                'Skipped' => (string) ($data['skipped'] ?? 0),
                'Delivered or read (WhatsApp confirmed)' => (string) (($delivery['delivered'] ?? 0) + ($delivery['read'] ?? 0)),
                'Waiting for a delivery report (recent)' => (string) ($delivery['pending'] ?? 0),
                'Accepted but NOT confirmed delivered' => (string) ($delivery['not_confirmed'] ?? 0),
                'Delivery unknown (no report exists)' => (string) ($delivery['unknown'] ?? 0),
            ], static fn ($value) => $value !== null)));

            $latest = (string) ($messages[0]['batch'] ?? '');
            $latestRows = array_values(array_filter($messages, static fn (array $row) => (string) ($row['batch'] ?? '') === $latest));
            $earlier = array_values(array_filter($messages, static fn (array $row) => (string) ($row['batch'] ?? '') !== $latest));

            $detail = static fn (array $row): array => [
                'title' => (string) ($row['student_name'] ?? ('Student #'.($row['student_id'] ?? ''))),
                'lines' => array_values(array_filter([
                    'Status: '.match ((string) ($row['status'] ?? '')) {
                        'sent' => 'Accepted by the provider',
                        'skipped' => 'Skipped',
                        default => 'Failed',
                    },
                    'Delivery: '.str_replace('_', ' ', (string) ($row['delivery_status'] ?? 'unknown')).(! empty($row['delivery_note']) ? ' — '.$row['delivery_note'] : ''),
                    'Channel: '.(($row['channel'] ?? 'sms') === 'sms' ? 'SMS' : 'WhatsApp').(! empty($row['sent_to']) ? ' to '.$row['sent_to'] : ''),
                    isset($row['student_id']) ? 'Student ID '.$row['student_id'] : null,
                    ! empty($row['message']) ? 'Message: '.$row['message'] : null,
                    ! empty($row['detail']) ? 'Provider response: '.$row['detail'] : null,
                    ! empty($row['provider_id']) ? 'Provider message id: '.$row['provider_id'] : null,
                    ! empty($row['batch']) ? 'Reference: '.$row['batch'] : null,
                    isset($row['recorded_at']) ? 'Recorded: '.$row['recorded_at'] : null,
                ])),
                'meta' => [],
            ];

            $context->addSection($this->compose->records(
                $earlier === [] ? 'Messages' : 'Latest send'.($latest !== '' ? ' ('.$latest.')' : ''),
                array_map($detail, array_slice($latestRows, 0, 50))
            ));

            if ($earlier !== []) {
                $context->addSection($this->compose->records('Earlier messages, newest first', array_map($detail, array_slice($earlier, 0, 50))));
            }
        }

        $context->addSection($this->compose->text(
            'How to read this',
            (string) ($data['rule'] ?? '"Accepted" means the provider took the message; it is not proof of delivery.')
            ."\n".'Accepted = the provider took the request. Delivered / read = WhatsApp confirmed it. Failed = not accepted. Unknown = an SMS the gateway accepted but sends no receipt for.'
        ));

        return StageOutcome::ran(
            sprintf('Read %d parent message record(s) from the saved record.', count($messages)),
            ['records' => count($messages)],
            ['table' => 'ai_audit_logs', 'ids' => array_values(array_filter(array_column($messages, 'record_id')))]
        );
    }

    private function stopped(StageContext $context): StageOutcome
    {
        $context->setHeadline('Stopped. Nothing was sent.');
        $context->addSection($this->compose->text('Nothing was sent', 'You stopped before approving, so no message left and nothing was recorded as sent.'));

        return StageOutcome::skipped('The user stopped the parent message before approving it.', ['sent' => false]);
    }

    /** @param  array<int, string>  $chips */
    private function asking(StageContext $context, string $message, string $title, string $help, array $chips): StageOutcome
    {
        $context->setHeadline($message);
        $context->addSection($this->compose->text($title, $help));
        $context->addSection($this->compose->text('Nothing has been sent', 'I will show you exactly who will be messaged and what it says, and nothing goes out until you approve it.'));
        $context->suggestFollowUp(...$chips);

        return StageOutcome::pending('A parent message is waiting on a detail from the user.', ['sent' => false]);
    }

    /** @param  array<string, mixed>  $data */
    private function ready(StageContext $context, array $data, string $message): StageOutcome
    {
        $recipients = is_array($data['recipients'] ?? null) ? $data['recipients'] : [];
        $unreachable = is_array($data['unreachable'] ?? null) ? $data['unreachable'] : [];
        $count = (int) ($data['recipient_count'] ?? count($recipients));
        $label = (string) ($data['channel_label'] ?? 'Message');
        $other = ($data['channel'] ?? 'sms') === 'sms' ? 'WhatsApp' : 'SMS';

        $context->setHeadline(sprintf('Review this %s to %d parent%s before anything is sent.', $label, $count, $count === 1 ? '' : 's'));

        if ($message !== '') {
            $context->addSection($this->compose->text('Please note', $message));
        }

        $context->addSection($this->compose->keyValues('Summary', array_filter([
            'Sent by' => $label.' — to the mobile number on each student\'s record'.(! empty($data['provider']['host']) ? ' (via '.$data['provider']['host'].')' : ''),
            'To' => (string) ($data['target_label'] ?? ''),
            'Parents who will be messaged' => (string) $count,
            'Will not be messaged' => $unreachable === [] ? null : sprintf('%d (no usable mobile number)', count($unreachable)),
            'Message length' => isset($data['message_length']) ? $data['message_length'].' characters' : null,
        ], static fn ($value) => $value !== null && $value !== '')));

        $context->addSection($this->compose->records(
            'Who will be messaged',
            array_map(static fn (array $row) => [
                'title' => (string) ($row['name'] ?? ''),
                'lines' => array_values(array_filter([
                    ! empty($row['class']) ? 'Class '.$row['class'] : null,
                    'Mobile: '.($row['contact_masked'] ?? ''),
                ])),
                'meta' => [],
            ], array_slice($recipients, 0, 25))
        ));

        if (count($recipients) > 25) {
            $context->addSection($this->compose->text('Not all shown', sprintf('Showing 25 of %d recipients; all %d will be messaged.', count($recipients), count($recipients))));
        }

        if ($unreachable !== []) {
            $context->addSection($this->compose->records(
                'Will NOT be messaged',
                array_map(static fn (array $row) => [
                    'title' => (string) ($row['name'] ?? ''),
                    'lines' => array_values(array_filter([! empty($row['class']) ? 'Class '.$row['class'] : null, (string) ($row['reason'] ?? 'No usable number.')])),
                    'meta' => [],
                ], array_slice($unreachable, 0, 25))
            ));
        }

        $template = is_array($data['template'] ?? null) ? $data['template'] : [];

        if ($template !== [] && $this->templateLine($template) !== '') {
            $context->addSection($this->compose->text('Template', $this->templateLine($template).(! empty($template['note']) ? "\n".$template['note'] : '')));
        }

        // The finished text, as the provider will receive it: the template's saved text included.
        $final = (string) ($data['final_message'] ?? $data['sample_message'] ?? '');

        $context->addSection($this->compose->text(
            'The message the first parent will receive',
            $final."\n\n(Variables such as {name} are filled in from each student's own record.)"
        ));

        $warnings = is_array($data['warnings'] ?? null) ? $data['warnings'] : [];

        if ($warnings !== []) {
            $context->addSection($this->compose->text('Before you approve', '• '.implode("\n• ", $warnings)));
        }

        $context->addSection($this->compose->text(
            'You can still change this',
            'Say make it "new wording", "send it by '.$other.' instead", or name a different student or class. Nothing changes until you approve.'
        ));

        $context->addSection($this->compose->text(
            'Nothing has been sent',
            'This is a preview. The messages go out only when you approve this exact list, and what the provider answers for each — accepted, failed or skipped — is recorded.'
        ));

        $context->addAction($this->compose->action(
            'send_parent_message',
            sprintf('Send %d %s message%s', $count, $label, $count === 1 ? '' : 's'),
            'parent_message_send',
            ['utterance' => 'Yes, send the messages.'],
            'primary'
        ));

        $context->suggestFollowUp('Send it by '.$other.' instead.', 'Cancel.');

        return StageOutcome::pending(
            sprintf('%d parent message%s are waiting for a person to approve the list.', $count, $count === 1 ? '' : 's'),
            ['recipients' => $count, 'unreachable' => count($unreachable), 'channel' => $data['channel'] ?? null, 'sent' => false],
            ['table' => 'tblstudent', 'ids' => array_values(array_filter(array_column($recipients, 'student_id')))]
        );
    }

    /** @param  array<string, mixed>  $data */
    private function sent(StageContext $context, array $data, string $message): StageOutcome
    {
        $results = is_array($data['results'] ?? null) ? $data['results'] : [];
        $whatsapp = ($data['channel'] ?? 'sms') === 'whatsapp';
        $by = $whatsapp ? 'WhatsApp' : 'the SMS gateway';

        $context->setHeadline($message);

        $context->addSection($this->compose->keyValues('Summary', array_filter([
            'Reference' => (string) ($data['reference'] ?? ''),
            'Sent by' => (string) ($data['channel_label'] ?? ''),
            'Accepted by '.$by => (string) ($data['sent'] ?? 0),
            'Failed' => (string) ($data['failed'] ?? 0),
            'Skipped' => (string) ($data['skipped'] ?? 0),
        ], static fn ($value) => $value !== null && $value !== '')));

        $context->addSection($this->compose->records('What happened to each', $this->rows($results)));

        $context->addSection($this->compose->text(
            'What "accepted" means',
            ucfirst($by).' took the message. That is not proof it reached the phone'
            .($whatsapp ? ', and WhatsApp reports delivered and read later — ask for the Communication messages to see the current status.' : ' — this gateway sends no delivery receipt back.')
        ));

        $context->suggestFollowUp($whatsapp ? 'Show the WhatsApp messages sent.' : 'Show the SMS messages sent.');

        return StageOutcome::ran(
            sprintf('%d message(s) accepted, %d failed, %d skipped.', (int) ($data['sent'] ?? 0), (int) ($data['failed'] ?? 0), (int) ($data['skipped'] ?? 0)),
            ['reference' => $data['reference'] ?? null, 'channel' => $data['channel'] ?? null, 'sent' => (int) ($data['sent'] ?? 0), 'failed' => (int) ($data['failed'] ?? 0)],
            ['table' => 'ai_audit_logs', 'ids' => []]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function settled(StageContext $context, string $headline, array $data = [], string $state = 'settled'): StageOutcome
    {
        $context->setHeadline($headline);

        $results = is_array($data['results'] ?? null) ? $data['results'] : [];

        if ($results !== []) {
            $context->addSection($this->compose->records('What happened to each', $this->rows($results)));
        }

        $context->addSection($this->compose->text('Nothing was sent', 'No message was accepted by the provider, so no parent was told anything. You can try again once the cause is fixed.'));

        return StageOutcome::blocked('No parent message was sent.', ['state' => $state]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $results): array
    {
        return array_map(static fn (array $row) => [
            'title' => (string) ($row['name'] ?? ''),
            'lines' => array_values(array_filter([
                match ((string) ($row['status'] ?? '')) {
                    'sent' => 'Accepted by the provider',
                    'skipped' => 'Skipped',
                    default => 'Failed',
                },
                (string) ($row['detail'] ?? ''),
            ])),
            'meta' => [],
        ], $results);
    }
}
