<?php

namespace App\Domain\AI\Lifecycle\Support;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageOutcome;

/**
 * What the assistant says when asked whether SMS and WhatsApp are really working.
 *
 * Rendered from what `communication.channel_health` returned — the providers' own answers and the saved history — and nothing
 * else. A channel is described by what was checked; a problem is stated as a problem, with what to do about it; and a message
 * is never called delivered unless its provider said so.
 */
class ChannelHealthAnswer
{
    public function __construct(private readonly AnswerComposer $compose)
    {
    }

    /**
     * @param  array<string, mixed>  $data  `communication.channel_health`'s data.
     */
    public function compose(StageContext $context, array $data, string $message): StageOutcome
    {
        $problems = (array) ($data['problems'] ?? []);

        $context->setHeadline($message !== '' ? $message : ($problems === [] ? 'No configuration problem was found.' : count($problems).' problem(s) found.'));

        if ($problems !== []) {
            $context->addSection($this->compose->text('Problems found', '• '.implode("\n• ", $problems)));
        }

        if (is_array($data['whatsapp'] ?? null)) {
            $this->whatsapp($context, $data['whatsapp']);
        }

        if (is_array($data['sms'] ?? null)) {
            $this->sms($context, $data['sms']);
        }

        $context->addSection($this->compose->text(
            'How to read this',
            'These checks ask the providers themselves. "Accepted" only means a provider took the request; a message counts as delivered only when its provider reports it. '
            .'Nothing was sent and nothing was changed by this check.'
        ));

        return StageOutcome::ran(
            sprintf('Checked the messaging channels: %d problem%s found.', count($problems), count($problems) === 1 ? '' : 's'),
            ['problems' => count($problems)],
            [],
            ['api' => 'POST /api/mcp/tools/call']
        );
    }

    /** @param  array<string, mixed>  $whatsapp */
    private function whatsapp(StageContext $context, array $whatsapp): void
    {
        if (! ($whatsapp['configured'] ?? false)) {
            $context->addSection($this->compose->keyValues('WhatsApp', ['Configured' => 'No']));

            return;
        }

        $token = (array) ($whatsapp['token'] ?? []);
        $number = (array) ($whatsapp['number'] ?? []);
        $template = (array) ($whatsapp['template'] ?? []);
        $webhook = (array) ($whatsapp['webhook'] ?? []);

        $context->addSection($this->compose->keyValues('WhatsApp configuration', array_filter([
            'Access token' => ($token['checked'] ?? false)
                ? (($token['valid'] ?? false) ? 'Valid' : 'INVALID').' ('.($token['type'] ?? '?').', expires: '.($token['expires'] ?? '?').', can send: '.(($token['can_message'] ?? false) ? 'yes' : 'no').')'
                : 'Could not be checked',
            'Sending number' => ($number['display_phone_number'] ?? null) ? $number['display_phone_number'].' — '.($number['status'] ?? '?').', '.($number['account_mode'] ?? '?') : null,
            'Display name' => ($number['verified_name'] ?? null) ? $number['verified_name'].' — '.($number['name_status'] ?? '?') : null,
            'Quality rating' => $number['quality_rating'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '')));

        $context->addSection($this->compose->keyValues('WhatsApp template', array_filter([
            'Template name' => $template['name'] ?? null,
            'Languages tried, in order' => implode(', ', (array) ($template['languages_tried'] ?? [])),
            'Parameters' => isset($template['parameters']) ? (string) $template['parameters'].' (the whole message, on one line)' : null,
            'Approval' => ($template['status'] ?? '') === 'accepted_by_whatsapp_on_send' ? 'Accepted by WhatsApp on send' : 'Unverified',
        ], static fn ($value) => $value !== null && $value !== '')));

        if (! empty($template['note'])) {
            $context->addSection($this->compose->text('About the template', (string) $template['note']));
        }

        $context->addSection($this->compose->keyValues('Delivery receipts', array_filter([
            'Receipts reaching this system' => ($webhook['receipts_arriving'] ?? false) ? 'Yes' : 'NO — none recorded in the last 30 days',
            'Callback URL to give Meta' => $webhook['callback_url'] ?? null,
            'Verify token configured' => ($webhook['verify_token_configured'] ?? false) ? 'Yes' : 'No (WHATSAPP_WEBHOOK_VERIFY_TOKEN)',
            'App secret configured' => ($webhook['app_secret_configured'] ?? false) ? 'Yes' : 'No (WHATSAPP_APP_SECRET)',
        ], static fn ($value) => $value !== null && $value !== '')));

        $this->recent($context, 'Recent WhatsApp messages', (array) ($whatsapp['recent'] ?? []));
    }

    /** @param  array<string, mixed>  $sms */
    private function sms(StageContext $context, array $sms): void
    {
        if (! ($sms['configured'] ?? false)) {
            $context->addSection($this->compose->keyValues('SMS', ['Configured' => 'No']));

            return;
        }

        $template = (array) ($sms['template'] ?? []);

        $context->addSection($this->compose->keyValues('SMS configuration', array_filter([
            'Gateway' => $sms['gateway_host'] ?? null,
            'Sender id' => $sms['sender_id'] ?? null,
            'Saved template or signature' => match ((string) ($template['kind'] ?? '')) {
                'signature' => 'Text added to every SMS: "'.($template['text'] ?? '').'"',
                'gateway_parameter' => 'Template parameter: '.($template['name'] ?? ''),
                default => 'None saved',
            },
            'Delivery reports available' => ($sms['delivery_reports_available'] ?? false) ? 'Yes (read live from the gateway)' : 'No — delivery will show as unknown',
        ], static fn ($value) => $value !== null && $value !== '')));

        $this->recent($context, 'Recent SMS messages', (array) ($sms['recent'] ?? []));
    }

    /** @param  array<int, array<string, mixed>>  $recent */
    private function recent(StageContext $context, string $title, array $recent): void
    {
        if ($recent === []) {
            return;
        }

        $context->addSection($this->compose->records(
            $title,
            array_map(static fn (array $row) => [
                'title' => (string) ($row['student_name'] ?? 'Message'),
                'lines' => array_values(array_filter([
                    'Delivery: '.str_replace('_', ' ', (string) ($row['delivery_status'] ?? 'unknown')).(! empty($row['delivery_note']) ? ' — '.$row['delivery_note'] : ''),
                    ! empty($row['reference']) ? 'Reference: '.$row['reference'] : null,
                    ! empty($row['provider_id']) ? 'Provider message id: '.$row['provider_id'] : null,
                    'Sent: '.($row['sent_at'] ?? ''),
                ])),
                'meta' => [],
            ], $recent)
        ));
    }
}
