<?php

namespace App\Services\Mcp;

use App\Services\Mcp\Concerns\DeliversWhatsapp;
use App\Services\Mcp\Concerns\ReadsSmsDelivery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Is each messaging channel actually able to reach a parent? Asked of the providers themselves, not inferred from "HTTP 200".
 *
 * A send that the provider "accepts" proves very little: the SMS gateway will take a message it never hands to an operator, and
 * WhatsApp will take one it later fails to deliver. This looks at what can be checked from here and says plainly what it found:
 *
 *   SMS       the saved gateway row; whether the gateway offers delivery reports; and, for the last few messages it accepted,
 *             what the gateway's own report says happened to them. Several accepted messages and none delivered is a provider-side
 *             problem (an unregistered DLT template, an unapproved sender id, no credit), and is reported as that.
 *   WHATSAPP  the saved Cloud API credentials; Meta's answer on whether the token is valid and what it may do; the sending number's
 *             status, quality and display-name state; whether this system receives Meta's delivery receipts at all; and how the
 *             last few messages stand.
 *
 * Nothing is sent and nothing is written. Credentials are used server-side for the checks and are never returned.
 */
class ChannelHealthService
{
    use DeliversWhatsapp;
    use ReadsSmsDelivery;

    /** The approved template every reminder goes out as, and the languages the Communication sender tries, in order. */
    public const WHATSAPP_TEMPLATE = 'parents_';

    public const WHATSAPP_LANGUAGES = ['en_US', 'en', 'en_GB'];

    /** How many of the most recent accepted messages each channel is judged by. */
    private const RECENT = 5;

    /**
     * @return array<string, mixed>
     */
    public function check(McpRequestContext $context, ?string $channel = null): array
    {
        $channel = in_array($channel, ['sms', 'whatsapp'], true) ? $channel : null;

        $report = [
            'sms' => $channel === 'whatsapp' ? null : $this->sms($context),
            'whatsapp' => $channel === 'sms' ? null : $this->whatsapp($context),
        ];

        $problems = array_merge([], ...array_values(array_map(static fn ($block) => $block['problems'] ?? [], array_filter($report))));

        // "Healthy" is claimed only when no problem was found AND every recent message a provider reported on was delivered or read.
        $unconfirmed = 0;

        foreach (array_filter($report) as $block) {
            foreach ((array) ($block['recent'] ?? []) as $message) {
                $unconfirmed += in_array($message['delivery_status'] ?? '', ['delivered', 'read'], true) ? 0 : 1;
            }
        }

        return ToolResult::success(
            'communication.channel_health',
            match (true) {
                $problems !== [] => sprintf('%d problem%s found that can stop messages reaching parents.', count($problems), count($problems) === 1 ? '' : 's'),
                $unconfirmed > 0 => sprintf('No configuration problem was found, but %d recent message%s not confirmed delivered, so delivery is unproven.', $unconfirmed, $unconfirmed === 1 ? ' is' : 's are'),
                default => 'No configuration problem was found, and the recent messages were reported delivered.',
            },
            $report + ['problems' => $problems, 'checked_at' => now()->toDateTimeString()]
        );
    }

    // ---------------------------------------------------------------------- SMS

    /** @return array<string, mixed> */
    private function sms(McpRequestContext $context): array
    {
        $gateway = $this->smsGateway($context);

        if ($gateway === null) {
            return [
                'configured' => false,
                'problems' => ['No SMS gateway is saved for this institute (Communication → Manage SMS API), so SMS cannot be sent.'],
            ];
        }

        parse_str(ltrim((string) ($gateway['pram'] ?? ''), '?'), $parameters);
        $host = (string) parse_url((string) ($gateway['url'] ?? ''), PHP_URL_HOST);
        $supportsReports = str_contains($host, 'smsforyou');
        $template = SmsGatewayTemplate::describe($gateway);

        $this->deliveryReportBudget = self::RECENT;
        $recent = [];

        foreach ($this->recentAcceptedFor($context, 'sms') as $message) {
            [$state, $note] = $this->smsDeliveryOf($context, $message['provider_id'], $message['sent_at']);
            $recent[] = $message + ['delivery_status' => $state, 'delivery_note' => $note];
        }

        $problems = [];
        $counts = array_count_values(array_column($recent, 'delivery_status'));
        $judged = count($recent);

        if (($counts['failed'] ?? 0) > 0) {
            $problems[] = sprintf('The SMS gateway reports %d of the last %d accepted messages as failed. Its status text is shown against each; ask the provider about it.', $counts['failed'], $judged);
        }

        // Two or more messages the gateway reports as still "Submitted" and none delivered: the gateway is not getting them to phones.
        // (A message with no gateway report at all is "unknown" and neither helps nor hurts this.)
        if (($counts['not_confirmed'] ?? 0) >= 2 && ($counts['delivered'] ?? 0) === 0) {
            $problems[] = sprintf(
                'None of the last %d messages the SMS gateway accepted has been reported delivered, and %d of them are still "Submitted". The gateway is taking the messages but not '
                .'getting them to phones. Check with the SMS provider: that the sender id (%s) is approved, that the message wording matches a registered DLT template, '
                .'and that the account has credit.',
                $judged,
                (int) $counts['not_confirmed'],
                (string) ($parameters['senderid'] ?? $parameters['SenderId'] ?? 'saved sender id')
            );
        }

        if (! $supportsReports) {
            $problems[] = sprintf('This SMS gateway (%s) has no delivery-report call this system knows how to read, so SMS delivery can only ever show as "unknown" here.', $host);
        }

        return [
            'configured' => true,
            'gateway_host' => $host,
            'sender_id' => (string) ($parameters['senderid'] ?? $parameters['SenderId'] ?? '') ?: null,
            'template' => $template,
            'delivery_reports_available' => $supportsReports,
            'recent' => $recent,
            'delivery' => $counts,
            'problems' => $problems,
        ];
    }

    // ----------------------------------------------------------------- WhatsApp

    /** @return array<string, mixed> */
    private function whatsapp(McpRequestContext $context): array
    {
        $configuration = $this->whatsappConfiguration($context);

        if ($configuration === null) {
            return [
                'configured' => false,
                'problems' => ['WhatsApp is not configured for this institute: it needs the Cloud API access token and phone number id under Communication → WhatsApp API settings.'],
            ];
        }

        $token = (string) $configuration['cloud_api_access_token'];
        $phoneId = (string) $configuration['cloud_api_phone_number_id'];
        $problems = [];

        [$tokenInfo, $tokenProblem] = $this->inspectToken($token);
        [$number, $numberProblem] = $this->inspectNumber($token, $phoneId);

        foreach ([$tokenProblem, $numberProblem] as $problem) {
            if ($problem !== null) {
                $problems[] = $problem;
            }
        }

        if (($number['name_status'] ?? null) === 'DECLINED') {
            $problems[] = sprintf('Meta has DECLINED the WhatsApp display name "%s". Messages can still send, but the name parents see may be the bare number, and some are filtered. Get the display name approved in Meta Business Manager.', $number['verified_name'] ?? '');
        }

        if (isset($number['quality_rating']) && in_array($number['quality_rating'], ['RED', 'YELLOW'], true)) {
            $problems[] = sprintf('The WhatsApp number\'s quality rating is %s: too many parents are blocking or reporting it, and Meta may throttle or restrict sending.', $number['quality_rating']);
        }

        $arriving = $this->whatsappReceiptsArriving();
        $webhook = [
            'receipts_arriving' => $arriving,
            'callback_url' => $this->whatsappWebhookUrl(),
            'verify_token_configured' => (string) config('services.whatsapp.verify_token') !== '',
            'app_secret_configured' => (string) config('services.whatsapp.app_secret') !== '',
        ];

        if (! $arriving) {
            $problems[] = 'No WhatsApp delivery receipt has been recorded for any message in the last 30 days, so this system cannot tell delivered from failed. '
                .'Set WHATSAPP_WEBHOOK_VERIFY_TOKEN and WHATSAPP_APP_SECRET, then in Meta (WhatsApp → Configuration) add the callback URL '.$webhook['callback_url']
                .' with the same verify token and subscribe to "messages".';
        }

        $recent = [];

        foreach ($this->recentAcceptedFor($context, 'whatsapp') as $message) {
            [$state, $note] = $this->whatsappReceipt($message['provider_id'], $message['sent_at']);
            $recent[] = $message + ['delivery_status' => $state, 'delivery_note' => $note];
        }

        $counts = array_count_values(array_column($recent, 'delivery_status'));

        if (($counts['failed'] ?? 0) > 0) {
            $problems[] = sprintf('WhatsApp reports %d of the last %d accepted messages as failed; its reason is shown against each.', $counts['failed'], count($recent));
        }

        return [
            'configured' => true,
            'token' => $tokenInfo,
            'number' => $number,
            // The template cannot be listed with this token (it names no WhatsApp Business Account), so what is known is what a send proves:
            // WhatsApp refuses a send naming a template that does not exist or is not approved (error 132001), and has accepted these.
            'template' => [
                'name' => self::WHATSAPP_TEMPLATE,
                'languages_tried' => self::WHATSAPP_LANGUAGES,
                'parameters' => 1,
                'status' => $recent === [] ? 'unverified' : 'accepted_by_whatsapp_on_send',
                'note' => $recent === []
                    ? 'No message has been sent through this template yet, so its approval is unverified. The first send will be refused with error 132001 if it is missing or unapproved.'
                    : 'WhatsApp accepted messages sent through this template, which it only does for an approved template in a language that exists. The approval list itself cannot be read with the saved token.',
            ],
            'webhook' => $webhook,
            'recent' => $recent,
            'delivery' => $counts,
            'problems' => $problems,
        ];
    }

    /**
     * What the token is and may do, according to Meta.
     *
     * @return array{0:array<string, mixed>, 1:?string}
     */
    private function inspectToken(string $token): array
    {
        try {
            $response = Http::timeout(15)->get('https://graph.facebook.com/v25.0/debug_token', ['input_token' => $token, 'access_token' => $token]);
        } catch (Throwable) {
            return [['checked' => false], 'Meta could not be reached to check the WhatsApp token, so its validity is unknown.'];
        }

        $data = (array) ($response->json('data') ?? []);

        if (! $response->successful() || $data === []) {
            return [['checked' => false], 'Meta did not accept a token check: '.($response->json('error.message') ?? 'no reason given').'.'];
        }

        $scopes = (array) ($data['scopes'] ?? []);
        $info = [
            'checked' => true,
            'valid' => (bool) ($data['is_valid'] ?? false),
            'type' => $data['type'] ?? null,
            'expires' => ((int) ($data['expires_at'] ?? 0)) === 0 ? 'never' : Carbon::createFromTimestamp((int) $data['expires_at'])->toDateTimeString(),
            'can_message' => in_array('whatsapp_business_messaging', $scopes, true),
        ];

        if (! $info['valid']) {
            return [$info, 'Meta reports the WhatsApp access token as INVALID or expired. Generate a new system-user token and save it under Communication → WhatsApp API settings.'];
        }

        if (! $info['can_message']) {
            return [$info, 'The WhatsApp token lacks the whatsapp_business_messaging permission, so it cannot send messages.'];
        }

        return [$info, null];
    }

    /**
     * The sending number as Meta sees it.
     *
     * @return array{0:array<string, mixed>, 1:?string}
     */
    private function inspectNumber(string $token, string $phoneId): array
    {
        try {
            $response = Http::withToken($token)->timeout(15)->get('https://graph.facebook.com/v25.0/'.rawurlencode($phoneId), [
                'fields' => 'display_phone_number,verified_name,quality_rating,name_status,status,account_mode,platform_type',
            ]);
        } catch (Throwable) {
            return [['checked' => false], 'Meta could not be reached to check the WhatsApp number, so its status is unknown.'];
        }

        if (! $response->successful()) {
            return [['checked' => false], 'Meta did not return the WhatsApp number: '.($response->json('error.message') ?? 'no reason given').'. Check the saved phone number id.'];
        }

        $number = ['checked' => true] + array_intersect_key((array) $response->json(), array_flip(['display_phone_number', 'verified_name', 'quality_rating', 'name_status', 'status', 'account_mode', 'platform_type']));

        if (($number['status'] ?? 'CONNECTED') !== 'CONNECTED') {
            return [$number, sprintf('The WhatsApp number\'s status at Meta is %s, not CONNECTED, so messages will not be delivered.', $number['status'])];
        }

        return [$number, null];
    }

    // ------------------------------------------------------------------ shared

    /**
     * The last few messages the provider accepted on a channel, from both ledgers that record sends.
     *
     * @return array<int, array{student_name:?string, provider_id:?string, sent_at:string, reference:?string}>
     */
    protected function recentAcceptedFor(McpRequestContext $context, string $channel): array
    {
        if (! Schema::hasTable('ai_audit_logs')) {
            return [];
        }

        $rows = DB::table('ai_audit_logs')
            ->whereIn('event_type', [FeesReminderService::EVENT, ParentMessageService::EVENT])
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('outcome', 'success')
            ->orderByDesc('id')
            ->limit(60)
            ->get(['payload', 'created_at']);

        $messages = [];

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true);
            $result = is_array($payload) ? ($payload['result'] ?? []) : [];

            if (($result['channel'] ?? null) !== $channel) {
                continue;
            }

            $messages[] = [
                'student_name' => $payload['subject_label'] ?? null,
                'provider_id' => is_string($result['provider_id'] ?? null) ? $result['provider_id'] : null,
                'sent_at' => (string) $row->created_at,
                'reference' => $payload['reference'] ?? null,
            ];

            if (count($messages) >= self::RECENT) {
                break;
            }
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function smsGateway(McpRequestContext $context): ?array
    {
        if (! Schema::hasTable('sms_api_details')) {
            return null;
        }

        $query = DB::table('sms_api_details')->where('sub_institute_id', $context->selectedInstituteId);

        if (Schema::hasColumn('sms_api_details', 'is_active')) {
            $query->where('is_active', 1);
        }

        $row = $query->orderByDesc('id')->first();

        return $row !== null && trim((string) ($row->url ?? '')) !== '' ? (array) $row : null;
    }
}
