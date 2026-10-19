<?php

namespace App\Services\Mcp\Concerns;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * What the SMS gateway itself says happened to a message after it accepted it.
 *
 * Gateway acceptance ("HTTP 200 … SUBMITTED") is only the start: the gateway still has to hand the message to an operator,
 * and an operator can refuse it (an unregistered DLT template, a sender id that is not approved) or the account can be out
 * of credit. None of that shows in the reply to the send request. For the gateway this estate's institutes use
 * (login.smsforyou.biz) there IS a delivery-report call — `/V2/http-dlr.php?apikey=…&msgid=…` — and this reads it.
 *
 * The result is never stronger than the report:
 *   - delivered       the gateway reports it delivered;
 *   - failed          the gateway reports a failure (its own words are shown);
 *   - pending         still "Submitted", and recent;
 *   - not_confirmed   still "Submitted" after {@see self::STUCK_AFTER_MINUTES} minutes — accepted, and never reported delivered;
 *   - unknown         the gateway offers no report for this message.
 *
 * The api key is read from the institute's saved gateway row on the server and never leaves it.
 */
trait ReadsSmsDelivery
{
    /** A message still "Submitted" this long after it was sent is reported as not confirmed. */
    public const STUCK_AFTER_MINUTES = 10;

    /** How many delivery reports one status read may fetch, so a long history cannot make a hundred gateway calls. */
    protected int $deliveryReportBudget = 10;

    /**
     * @return array{0:string, 1:string}  [state, plain-words note]
     */
    protected function smsDeliveryOf(?McpRequestContext $context, mixed $providerId, ?string $sentAt): array
    {
        $unknown = ['unknown', 'The SMS gateway accepted it but its delivery report is not available for this message, so whether it reached the phone is unknown.'];

        if ($context === null || ! is_string($providerId) || $providerId === '' || $this->deliveryReportBudget <= 0) {
            return $unknown;
        }

        $gateway = $this->smsGateway($context);

        if ($gateway === null) {
            return $unknown;
        }

        $this->deliveryReportBudget--;

        return $this->classifySmsReport($this->fetchSmsDeliveryReport($gateway, $providerId), $sentAt);
    }

    /**
     * The gateway's delivery report for one message id, or null when this gateway has no such report or it could not be read.
     *
     * @param  array<string, mixed>  $gateway  A `sms_api_details` row.
     * @return array{status:?string, delivered_at:?string}|null
     */
    protected function fetchSmsDeliveryReport(array $gateway, string $messageId): ?array
    {
        $host = (string) parse_url((string) ($gateway['url'] ?? ''), PHP_URL_HOST);

        // The one gateway whose delivery-report call is known and verified. Another provider is reported as "unknown"
        // rather than guessed at.
        if (! str_contains($host, 'smsforyou')) {
            return null;
        }

        parse_str(ltrim((string) ($gateway['pram'] ?? ''), '?'), $parameters);
        $key = (string) ($parameters['apikey'] ?? '');

        if ($key === '') {
            return null;
        }

        try {
            $response = Http::timeout(12)->get('https://'.$host.'/V2/http-dlr.php', ['apikey' => $key, 'msgid' => $messageId]);
        } catch (Throwable) {
            return null;
        }

        $row = $response->successful() ? ($response->json('data.0') ?? null) : null;

        if (! is_array($row)) {
            return null;
        }

        $delivered = trim((string) ($row['delivered_date'] ?? ''));

        return [
            'status' => trim((string) ($row['status_desc'] ?? $row['status-desc'] ?? '')) ?: null,
            // The gateway answers a 1970 date for "not delivered yet"; that is not a delivery time.
            'delivered_at' => $delivered === '' || str_starts_with($delivered, '1970') ? null : $delivered,
        ];
    }

    /**
     * @param  array{status:?string, delivered_at:?string}|null  $report
     * @return array{0:string, 1:string}
     */
    protected function classifySmsReport(?array $report, ?string $sentAt): array
    {
        if ($report === null || $report['status'] === null) {
            return ['unknown', 'The SMS gateway accepted it but its delivery report is not available for this message, so whether it reached the phone is unknown.'];
        }

        $status = mb_strtolower($report['status']);

        if (preg_match('/\b(undeliver|not deliver|fail|reject|expire|block|invalid|dnd|ndnc|barred|error)/', $status)) {
            return ['failed', sprintf('The SMS gateway reports it as "%s", so it was not delivered. Ask the provider why: the usual causes are an unregistered DLT template, an unapproved sender id, a number on the do-not-disturb list, or no credit left.', $report['status'])];
        }

        if (str_contains($status, 'deliver')) {
            return ['delivered', 'The SMS gateway reports it delivered'.($report['delivered_at'] !== null ? ' at '.$report['delivered_at'] : '').'.'];
        }

        if (preg_match('/submit|queue|accept|pending|process|sent/', $status)) {
            $minutes = $sentAt === null ? null : (int) Carbon::parse($sentAt)->diffInMinutes(now());

            if ($minutes !== null && $minutes >= self::STUCK_AFTER_MINUTES) {
                return ['not_confirmed', sprintf(
                    'Still "%s" at the SMS gateway %s after it was sent: it took the message but has not reported it delivered to the phone. '
                    .'That usually means the operator or the gateway is holding it (an unregistered DLT template, an unapproved sender id, or no credit left) — check the account with the SMS provider.',
                    $report['status'],
                    $this->humanMinutes($minutes)
                )];
            }

            return ['pending', sprintf('The SMS gateway reports "%s" and has not reported delivery yet.', $report['status'])];
        }

        return ['unknown', sprintf('The SMS gateway reports "%s", which is not a delivery result, so whether it reached the phone is unknown.', $report['status'])];
    }

    private function humanMinutes(int $minutes): string
    {
        return $minutes >= 120 ? sprintf('%d hours', intdiv($minutes, 60)) : ($minutes >= 60 ? '1 hour' : sprintf('%d minutes', $minutes));
    }
}
