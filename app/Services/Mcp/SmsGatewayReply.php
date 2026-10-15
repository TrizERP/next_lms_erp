<?php

namespace App\Services\Mcp;

use Illuminate\Support\Str;

/**
 * What an SMS gateway's reply means, as far as the reply itself says.
 *
 * The Communication module's `sendSMSDetailed()` returns the provider's HTTP status and body. This
 * reads them the same way `FeesReminderService::dispatchSms()` does — a redirect, an empty body, a
 * non-2xx status or a body that says "error/invalid/insufficient…" is a refusal, never a send — so
 * the chatbot reports one answer for the same reply whichever feature sent it. It is a separate class
 * rather than a call into the Fees service because that method is protected and tied to reminders;
 * the two should be merged once Fees can be changed safely.
 */
class SmsGatewayReply
{
    /**
     * @param  array<string, mixed>  $reply  `sendSMSDetailed()`'s return value.
     * @return array{status:string, detail:string, outage:bool, provider_reply:?string}
     */
    public static function classify(array $reply, ?string $gatewayUrl): array
    {
        $from = parse_url((string) $gatewayUrl, PHP_URL_HOST) ?: 'the saved gateway address';

        if (! empty($reply['error'])) {
            $reason = Str::limit(trim((string) ($reply['message'] ?? '')), 140, '…');

            return [
                'status' => 'failed',
                'detail' => 'The SMS sender reported an error: '.($reason === '' ? 'no reason given' : $reason),
                // curl 6 (host), 7 (connect) and 28 (timeout): the gateway is not answering at all.
                'outage' => (bool) preg_match('/-(6|7|28)$/', $reason),
                'provider_reply' => null,
            ];
        }

        if (! empty($reply['redirect_host']) || (isset($reply['http']) && $reply['http'] >= 300 && $reply['http'] < 400)) {
            return [
                'status' => 'failed',
                'detail' => sprintf(
                    'The SMS gateway (%s) redirected the request instead of accepting it, which usually means the provider has retired that address or its key. '
                    .'The gateway address and key in the school\'s SMS settings need to be checked with the provider.',
                    $from
                ),
                'outage' => false,
                'provider_reply' => null,
            ];
        }

        if (! isset($reply['http'])) {
            return [
                'status' => 'failed',
                'detail' => 'The SMS gateway\'s reply was not read, so there is no evidence it accepted the message.',
                'outage' => false,
                'provider_reply' => null,
            ];
        }

        $http = (int) $reply['http'];
        $excerpt = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($reply['body'] ?? ''))) ?? ''), 120, '…');

        if ($http < 200 || $http >= 300) {
            return ['status' => 'failed', 'detail' => sprintf('The SMS gateway answered HTTP %d%s.', $http, $excerpt === '' ? '' : ': '.$excerpt), 'outage' => $http >= 500, 'provider_reply' => $excerpt ?: null];
        }

        if ($excerpt === '') {
            return ['status' => 'failed', 'detail' => 'The SMS gateway answered but said nothing, so there is no evidence it accepted the message.', 'outage' => false, 'provider_reply' => null];
        }

        if (preg_match('/\b(error|invalid|fail(?:ed|ure)?|denied|unauthori[sz]ed|insufficient|not approved|reject(?:ed)?|wrong|blocked|blacklist(?:ed)?|expired|unknown|not (?:found|allowed|registered))\b/i', $excerpt)) {
            return ['status' => 'failed', 'detail' => 'The SMS gateway replied with an error: '.$excerpt, 'outage' => false, 'provider_reply' => $excerpt];
        }

        return [
            'status' => 'sent',
            'detail' => sprintf('Accepted by the SMS gateway (HTTP %d): %s. Delivery to the phone is not reported back.', $http, $excerpt),
            'outage' => false,
            'provider_reply' => $excerpt,
        ];
    }
}
