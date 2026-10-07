<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a notification through the channels a school has left switched on.
 *
 * WHAT IS REAL. `web` writes the in-app record (the delivery log is the inbox),
 * and `email` sends through Laravel's configured mailer. `sms`, `whatsapp` and
 * `push` need a provider driver; until an ACTIVE integration for that channel
 * exists in platform_integration_configs they are recorded as `skipped` with the
 * reason, never as `sent`. A log that claimed delivery nobody made would be worse
 * than no log.
 *
 * Failed sends retry with doubling backoff (5, 10, 20 … minutes) up to MAX_ATTEMPTS,
 * driven by retryDue() from the scheduler.
 */
class NotificationSender
{
    public const MAX_ATTEMPTS = 4;

    /**
     * @param  array<int,array{channel?:string,recipient:string}>|array<int,string>  $recipients  email addresses, or ['channel'=>..., 'recipient'=>...] to target one channel
     * @return array<int,array<string,mixed>> the log rows written
     */
    public function dispatch(int $tenantId, string $eventKey, array $recipients, string $subject, string $body, bool $isSample = false): array
    {
        $channels = $this->channelsFor($tenantId, $eventKey);
        $written = [];

        foreach ($recipients as $recipient) {
            $target = is_array($recipient) ? ($recipient['recipient'] ?? '') : (string) $recipient;
            $only = is_array($recipient) ? ($recipient['channel'] ?? null) : null;
            if ($target === '') {
                continue;
            }

            foreach ($channels as $channel => $on) {
                if ($only !== null && $only !== $channel) {
                    continue;
                }
                $written[] = $this->deliver($tenantId, $eventKey, $channel, $target, $subject, $body, $on, $isSample);
            }
        }

        return $written;
    }

    /** @return int rows retried */
    public function retryDue(): int
    {
        $rows = DB::table('platform_notification_log')
            ->whereIn('status', ['queued', 'failed'])
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit(100)->get();

        foreach ($rows as $row) {
            [$status, $error, $ref] = $this->send($row->sub_institute_id, $row->channel, $row->recipient, (string) $row->subject, (string) $row->body);
            $this->settle((int) $row->id, (int) $row->attempts + 1, $status, $error, $ref);
        }

        return $rows->count();
    }

    /** @return array<string,string> channel => 'on'|'off' */
    private function channelsFor(int $tenantId, string $eventKey): array
    {
        $channelConfig = (array) config('platform_services.channels', []);
        $names = array_keys($channelConfig) ?: ['web', 'email', 'sms', 'whatsapp', 'push'];

        $switches = DB::table('platform_notification_channels')->where('sub_institute_id', $tenantId)->pluck('enabled', 'channel');
        $pref = DB::table('platform_notification_preferences')->where('sub_institute_id', $tenantId)->where('event_key', $eventKey)->first();

        $result = [];
        foreach ($names as $channel) {
            $channelOn = ! isset($switches[$channel]) || (bool) $switches[$channel];
            $eventOn = $pref ? ((bool) $pref->enabled && in_array($channel, (array) json_decode((string) $pref->channels, true), true)) : true;
            $result[$channel] = ($channelOn && $eventOn) ? 'on' : 'off';
        }

        return $result;
    }

    /** @return array<string,mixed> */
    private function deliver(int $tenantId, string $eventKey, string $channel, string $recipient, string $subject, string $body, string $on, bool $isSample): array
    {
        $now = now();
        $id = DB::table('platform_notification_log')->insertGetId([
            'sub_institute_id' => $tenantId,
            'event_key' => $eventKey,
            'channel' => $channel,
            'recipient' => mb_substr($recipient, 0, 191),
            'subject' => mb_substr($subject, 0, 255),
            'body' => $body,
            'status' => 'queued',
            'attempts' => 0,
            'is_sample' => $isSample ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($on === 'off') {
            $this->settle($id, 0, 'skipped', 'Switched off for this school (channel or event setting).', null);
        } else {
            [$status, $error, $ref] = $this->send($tenantId, $channel, $recipient, $subject, $body);
            $this->settle($id, 1, $status, $error, $ref);
        }

        return (array) DB::table('platform_notification_log')->where('id', $id)->first();
    }

    /** @return array{0:string,1:?string,2:?string} status, error, provider ref */
    private function send(int $tenantId, string $channel, string $recipient, string $subject, string $body): array
    {
        try {
            if ($channel === 'web') {
                return ['sent', null, 'inbox'];
            }

            if ($channel === 'email') {
                if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    return ['skipped', 'Recipient is not an email address.', null];
                }
                Mail::raw($body, function ($message) use ($recipient, $subject) {
                    $message->to($recipient)->subject($subject);
                });

                $mailer = (string) config('mail.default');
                if (in_array($mailer, ['log', 'array'], true)) {
                    return ['skipped', "The mailer is set to '{$mailer}', which writes the message to a file instead of delivering it.", null];
                }

                return ['sent', null, $mailer];
            }

            $configured = DB::table('platform_integration_configs')
                ->where('sub_institute_id', $tenantId)->where('category', $channel)->where('status', 'active')->exists();

            return $configured
                ? ['skipped', "A {$channel} integration is active but no sending driver is installed for it yet.", null]
                : ['skipped', "No active {$channel} integration is configured for this school.", null];
        } catch (Throwable $e) {
            return ['failed', mb_substr($e->getMessage(), 0, 500), null];
        }
    }

    private function settle(int $id, int $attempts, string $status, ?string $error, ?string $ref): void
    {
        DB::table('platform_notification_log')->where('id', $id)->update([
            'status' => $status,
            'attempts' => $attempts,
            'last_error' => $error,
            'provider_ref' => $ref,
            'sent_at' => $status === 'sent' ? now() : null,
            'next_attempt_at' => $status === 'failed' && $attempts < self::MAX_ATTEMPTS
                ? now()->addMinutes(5 * (2 ** max(0, $attempts - 1))) : null,
            'updated_at' => now(),
        ]);
    }
}
