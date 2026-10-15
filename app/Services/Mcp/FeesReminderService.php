<?php

namespace App\Services\Mcp;

use App\Domain\AI\Support\AiAuditLogger;
use App\Services\Mcp\Concerns\DeliversWhatsapp;
use App\Services\Mcp\Concerns\ReadsSmsDelivery;
use App\Http\Controllers\easy_com\send_sms_parents\send_sms_parents_controller;
use App\Mail\FeeReminderNotice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fee payment reminders: who is told what, by which channel, and a record of what happened.
 *
 * WHERE EVERYTHING COMES FROM
 *
 * Who owes, and how much, is `FeesPositionService::accounts()` — the school's own fee ledger
 * for this institute and academic year, ranked largest first. Nothing here re-computes a
 * balance or holds a student. The address or number a reminder goes to is read from the
 * student's own `tblstudent` row (`email`, `mobile`), scoped to the caller's institute.
 *
 * TWO CHANNELS, AND WHAT EACH CAN AND CANNOT SAY
 *
 *   email   sent inline through the configured mailer, one message at a time.
 *   sms     sent through the institute's own gateway in `sms_api_details` (the one the
 *           Communication module uses), one message at a time.
 *
 * Each recipient ends in exactly one of three states:
 *
 *   sent     the mail server / SMS gateway ACCEPTED the message. That is NOT delivery. The
 *            gateway's own reply is kept and shown, because that reply is the only evidence
 *            there is; no delivery receipt is available, and nothing here says otherwise.
 *   failed   it was refused or could not be reached; the reason is recorded.
 *   skipped  no valid address / number on the record, so nothing was attempted.
 *
 * THE SMS SENDER IS THE COMMUNICATION MODULE'S OWN
 *
 * SMS goes through `send_sms_parents_controller::sendSMS()` — Communication → Send SMS to
 * Parents — and is logged in `sms_sent_parents` like that screen's messages. That sender
 * reports an error only when the request cannot be made; it never reads the provider's reply.
 * So "sent" here means "handed to the gateway", and the result says that in words: a gateway
 * answering "Invalid template" or "Insufficient balance" cannot be seen from here.
 *
 * NOTHING LEAVES WITHOUT A PREVIEW THE SENDER MATCHED
 *
 * `preview()` returns the recipients, amounts, deadline, channel and the literal message.
 * `send()` re-reads the ledger and refuses if the students, any amount, the deadline, the
 * channel or the wording differ from the fingerprint the person approved.
 *
 * THE RECORD
 *
 * One row per recipient in `ai_audit_logs`, event type `module.fees.fee_reminder`, written
 * through the existing logger — the ledger the Fees AI Stack Activity tab reads. No new table.
 * Addresses and numbers are stored masked, and the gateway URL (which carries credentials) is
 * never logged.
 */
class FeesReminderService
{
    use DeliversWhatsapp;
    use ReadsSmsDelivery;

    public const EVENT = 'module.fees.fee_reminder';

    /** An accident limit, not a performance limit: the batch is sent inline. */
    /** A reminder already sent to someone is not sent again within this many minutes. */
    public const DUPLICATE_WINDOW_MINUTES = 10;

    public const MAX_RECIPIENTS = 25;

    public const DEFAULT_TOP = 10;

    public const CHANNELS = ['email', 'sms', 'whatsapp'];

    private const MAX_DEADLINE_DAYS = 366;

    /** Longest custom message accepted: three SMS parts, or a short email. */
    private const MAX_MESSAGE_CHARS = 480;

    /** After this many gateway outages in a row, the rest of an SMS batch is not attempted. */
    private const GATEWAY_OUTAGES_BEFORE_STOP = 2;

    public function __construct(
        private readonly FeesPositionService $position,
        private readonly AiAuditLogger $audit,
        private readonly ?AdministratorCheck $admins = null,
    ) {
    }

    // ------------------------------------------------------------------- who may

    /** Whether this person may send reminders: a school administrator, as the ERP defines one. */
    public function mayRemind(McpRequestContext $context): bool
    {
        return ($this->admins ?? app(AdministratorCheck::class))->isAdministrator($context);
    }

    // ------------------------------------------------------------------ preview

    /**
     * Who would be reminded, of what, by which channel, by when. Sends nothing.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function preview(McpRequestContext $context, array $arguments): array
    {
        $resolved = $this->resolve($context, $arguments);

        if (! $resolved['success']) {
            return $resolved['failure'];
        }

        $channel = $resolved['channel'];
        $template = $resolved['template'];
        $deadline = $resolved['deadline'];
        $recipients = $resolved['recipients'];
        $reachable = array_values(array_filter($recipients, static fn (array $r) => $r['contact'] !== null));
        $unreachable = array_values(array_filter($recipients, static fn (array $r) => $r['contact'] === null));
        $label = $this->channelWord($channel);
        $contactNoun = $this->isMobile($channel) ? 'mobile number' : 'email address';

        $message = $reachable === []
            ? sprintf('None of these students has a usable %s on record, so nobody can be reminded by %s.', $contactNoun, $label)
            : sprintf(
                '%d reminder%s ready to send by %s; %d student%s without a usable %s will not be contacted.',
                count($reachable),
                count($reachable) === 1 ? '' : 's',
                $label,
                count($unreachable),
                count($unreachable) === 1 ? '' : 's',
                $contactNoun
            );

        $sample = $reachable === [] ? null : $this->render($channel, $reachable[0]['name'], $reachable[0]['outstanding'], $deadline, $template);

        $data = [
            'channel' => $channel,
            'channel_label' => $this->channelLabel($channel),
            'deadline' => $deadline->toDateString(),
            'deadline_label' => $this->dateLabel($deadline),
            'recipient_count' => count($reachable),
            'unreachable_count' => count($unreachable),
            'total_outstanding' => round(array_sum(array_column($reachable, 'outstanding')), 2),
            'recipients' => array_map(fn (array $r) => [
                'student_id' => $r['student_id'],
                'name' => $r['name'],
                'class' => $r['class'],
                'outstanding' => $r['outstanding'],
                'outstanding_label' => $this->rupees($r['outstanding']),
                'contact_masked' => $this->mask((string) $r['contact'], $channel),
            ], $reachable),
            'unreachable' => array_map(static fn (array $r) => [
                'student_id' => $r['student_id'],
                'name' => $r['name'],
                'class' => $r['class'],
                'outstanding' => $r['outstanding'],
                'reason' => $r['contact_problem'],
            ], $unreachable),
            // The first recipient's actual message, so what is approved is the wording that
            // will be sent and not a description of it.
            'sample_subject' => $channel === 'email' && $reachable !== [] ? $this->subject() : null,
            'sample_message' => $sample['text'] ?? null,
            'message_is_custom' => $template !== null,
            'warnings' => $reachable === [] ? [] : $this->warnings($channel, $sample['text'] ?? '', $template, $deadline),
            'fingerprint' => $this->fingerprint($reachable, $deadline, $channel, $template, $resolved['gateway'] ?? null),
            'basis' => 'Amounts are from the fee ledger for this institute and academic year. Each student is '
                .($channel === 'whatsapp' ? 'messaged on WhatsApp at the mobile number' : ($channel === 'sms' ? 'texted on the mobile number' : 'emailed at the address'))
                .' on their own record, and only their own figure is in their message.',
        ];

        if ($channel === 'sms') {
            // What the Send SMS Parents module sends: the message, then the institute's saved template/signature.
            // The length and the number of parts are counted on THAT text, because that is what goes out.
            $saved = SmsGatewayTemplate::describe((array) ($resolved['gateway'] ?? []));
            $final = $sample === null ? null : SmsGatewayTemplate::finalText($sample['text'], $saved);

            $data['sms'] = ($final === null ? [] : $this->smsFacts($final)) + ['gateway_host' => $resolved['gateway_host']];
            $data['template'] = $saved;
            $data['final_message'] = $final;
        }

        if ($channel === 'whatsapp') {
            // WhatsApp business messages go out as the institute's approved Cloud API template (the Communication
            // module's rule); the wording is fitted into it as one line, which is what the preview shows.
            $template = $this->whatsappTemplate($sample['text'] ?? '');
            $final = $sample === null ? null : $this->oneLine($sample['text']);

            $data['template'] = $template;
            $data['final_message'] = $final;
            $data['whatsapp'] = ['characters' => $final === null ? 0 : mb_strlen($final), 'host' => 'graph.facebook.com'];
        }

        return ToolResult::success('fees.send_reminders', $message, $data);
    }

    // --------------------------------------------------------------------- send

    /**
     * Send the previewed reminders and record what happened to each.
     *
     * @param  array<string, mixed>  $arguments
     * @param  string|null  $expectedFingerprint  The fingerprint of the list the person approved.
     * @return array<string, mixed>
     */
    public function send(McpRequestContext $context, array $arguments, ?string $expectedFingerprint): array
    {
        $resolved = $this->resolve($context, $arguments);

        if (! $resolved['success']) {
            return $resolved['failure'];
        }

        $channel = $resolved['channel'];
        $template = $resolved['template'];
        $deadline = $resolved['deadline'];
        $recipients = $resolved['recipients'];
        $reachable = array_values(array_filter($recipients, static fn (array $r) => $r['contact'] !== null));
        $contactNoun = $this->isMobile($channel) ? 'mobile number' : 'email address';

        if ($expectedFingerprint === null || $expectedFingerprint === '') {
            return ToolResult::failure(
                'fees.send_reminders',
                'Nothing was sent: there is no approved list to send. Preview the reminders and approve them first.',
                'no_approved_list'
            );
        }

        if ($reachable === []) {
            return ToolResult::failure(
                'fees.send_reminders',
                sprintf('None of these students has a usable %s on record, so nothing was sent.', $contactNoun),
                'no_recipients'
            );
        }

        // The ledger moved between preview and send, or the channel, date or wording changed:
        // an approved list is not this list any more.
        if (! hash_equals($expectedFingerprint, $this->fingerprint($reachable, $deadline, $channel, $template, $resolved['gateway'] ?? null))) {
            return ToolResult::failure(
                'fees.send_reminders',
                'The list changed since you reviewed it (a payment or fee change, or a different selection, '
                .'channel, date or wording). Nothing was sent. Review the new list and approve it again.',
                'recipients_changed'
            );
        }

        $reference = 'FR-'.now()->format('Ymd').'-'.Str::lower(Str::random(5));
        $results = [];
        $outages = 0;

        foreach ($recipients as $recipient) {
            if ($this->isMobile($channel) && $outages >= self::GATEWAY_OUTAGES_BEFORE_STOP && $recipient['contact'] !== null) {
                // The gateway has not answered twice running; more attempts would only be more
                // waiting. They are recorded as failed — not skipped, and not "sent".
                $results[] = $this->record($context, $recipient, $channel, $deadline, $reference, 'failed', 'Not attempted: the '.($channel === 'whatsapp' ? 'WhatsApp service' : 'SMS gateway').' could not be reached for the previous messages.');

                continue;
            }

            // The same reminder to the same person, already accepted a moment ago (a double-clicked
            // Approve, a retried request): said, recorded as skipped, and not sent again.
            $earlier = $recipient['contact'] === null ? null : $this->recentlySentAt($context, (int) $recipient['student_id'], $channel, $deadline);

            if ($earlier !== null) {
                $results[] = $this->record($context, $recipient, $channel, $deadline, $reference, 'skipped', 'Already sent at '.$earlier.'; not sent again.');

                continue;
            }

            $result = $this->deliver($context, $recipient, $channel, $deadline, $reference, $template, $resolved['gateway']);
            $outages = $result['outage'] ? $outages + 1 : 0;
            $results[] = $result;
        }

        $sent = count(array_filter($results, static fn (array $r) => $r['status'] === 'sent'));
        $failed = count(array_filter($results, static fn (array $r) => $r['status'] === 'failed'));
        $skipped = count(array_filter($results, static fn (array $r) => $r['status'] === 'skipped'));

        $summary = $channel === 'whatsapp'
            ? sprintf(
                '%d reminder%s accepted by WhatsApp, %d failed, %d skipped (no usable %s on record). '
                .'"Accepted" means WhatsApp returned a message id; whether it was delivered or read is reported later and shown in the reminder status.',
                $sent,
                $sent === 1 ? '' : 's',
                $failed,
                $skipped,
                $contactNoun
            )
            : ($channel === 'sms'
            ? sprintf(
                '%d reminder%s accepted by the SMS gateway, %d failed, %d skipped (no usable %s on record). '
                .'"Accepted" is the gateway\'s own reply to the request; delivery to the phone is not reported back.',
                $sent,
                $sent === 1 ? '' : 's',
                $failed,
                $skipped,
                $contactNoun
            )
            : sprintf(
                '%d reminder%s accepted by the mail server, %d failed, %d skipped (no usable %s on record). '
                .'"Accepted" means the mail server took the message; it is not proof it reached the inbox.',
                $sent,
                $sent === 1 ? '' : 's',
                $failed,
                $skipped,
                $contactNoun
            ));

        $data = [
            'reference' => $reference,
            'channel' => $channel,
            'channel_label' => $this->channelLabel($channel),
            'deadline' => $deadline->toDateString(),
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'results' => array_map(static fn (array $r) => [
                'student_id' => $r['student_id'],
                'name' => $r['name'],
                'outstanding' => $r['outstanding'],
                'status' => $r['status'],
                'detail' => $r['detail'],
            ], $results),
        ];

        // Nothing was accepted at all is a failure of the action, not a success with zeros.
        return $sent === 0
            ? ToolResult::failure('fees.send_reminders', 'No reminder could be sent. '.$summary, 'send_failed', $data)
            : ToolResult::success('fees.send_reminders', $summary, $data);
    }

    // ------------------------------------------------------------------ history

    /**
     * Reminders already sent or attempted, newest first, read back from the ledger.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function history(McpRequestContext $context, array $arguments): array
    {
        if (! Schema::hasTable('ai_audit_logs')) {
            return ToolResult::success('fees.reminder_history', 'No reminder history is recorded in this estate.', [
                'count' => 0,
                'reminders' => [],
            ]);
        }

        $limit = min(max((int) ($arguments['limit'] ?? 50), 1), 200);

        // At most this many live delivery-report calls to the SMS gateway per status read; older records show "unknown".
        $this->deliveryReportBudget = 10;

        $query = DB::table('ai_audit_logs')
            ->where('event_type', self::EVENT)
            ->where('sub_institute_id', $context->selectedInstituteId);

        if (! empty($arguments['student_id'])) {
            $query->where('subject_id', (int) $arguments['student_id']);
        }

        if (! empty($arguments['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $arguments['date'])) {
            $query->whereDate('created_at', (string) $arguments['date']);
        }

        $rows = $query->orderByDesc('id')->limit($limit)->get(['id', 'subject_id', 'outcome', 'payload', 'created_at', 'actor_id']);

        $reminders = [];
        $totals = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        // One send, by its reference ("FR-20261015-abcde"), compared case-insensitively.
        $batch = ! empty($arguments['batch']) ? mb_strtolower(trim((string) $arguments['batch'])) : null;

        foreach ($rows as $row) {
            $reminder = $this->reminderFromRow($row, $context);

            if ($batch !== null && mb_strtolower((string) $reminder['batch']) !== $batch) {
                continue;
            }

            if (! empty($arguments['status']) && $arguments['status'] !== $reminder['status']) {
                continue;
            }

            if (! empty($arguments['channel']) && $arguments['channel'] !== $reminder['channel']) {
                continue;
            }

            $totals[$reminder['status']] = ($totals[$reminder['status']] ?? 0) + 1;
            $reminders[] = $reminder;
        }

        $message = $reminders === []
            ? 'No fee reminder has been sent from here yet.'
            : sprintf(
                '%d reminder record%s: %d accepted by the mail server, SMS gateway or WhatsApp, %d failed, %d skipped.%s',
                count($reminders),
                count($reminders) === 1 ? '' : 's',
                $totals['sent'],
                $totals['failed'],
                $totals['skipped'],
                $this->deliverySentence($reminders)
            );

        return ToolResult::success('fees.reminder_history', $message, [
            'count' => count($reminders),
            'accepted' => $totals['sent'],
            'accepted_by_mail_server' => $totals['sent'],
            'failed' => $totals['failed'],
            'skipped' => $totals['skipped'],
            // Delivery, as far as it is known: none is ever confirmed (no receipt comes back), so every accepted
            // reminder is "unknown" and every refused one "not delivered".
            'delivery' => $this->deliveryCounts($reminders),
            'reminders' => $reminders,
            'rule' => '"Sent" means the mail server or SMS gateway accepted the message. It is not proof of delivery to '
                .'an inbox or a phone, and nothing here records whether it was opened or read.',
        ]);
    }

    /**
     * One ledger row, read back in this feature's own words.
     *
     * The ledger stores the Activity tab's vocabulary — `completed` for a message the mail
     * server or gateway accepted — and this reports it as `sent`, with the same caveat
     * everywhere: sent means accepted, not delivered.
     *
     * @return array<string, mixed>
     */
    protected function reminderFromRow(object $row, ?McpRequestContext $context = null): array
    {
        $payload = json_decode((string) $row->payload, true);
        $payload = is_array($payload) ? $payload : [];
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        $stored = (string) ($payload['status'] ?? ($row->outcome === 'success' ? 'completed' : 'failed'));

        return [
            'reminder_id' => (int) $row->id,
            'batch' => $payload['reference'] ?? null,
            'student_id' => $row->subject_id === null ? null : (int) $row->subject_id,
            'student_name' => $payload['subject_label'] ?? null,
            'channel' => $result['channel'] ?? 'email',
            'class' => $result['class'] ?? null,
            'message' => $result['message'] ?? null,
            'outstanding' => $result['outstanding'] ?? null,
            'deadline' => $result['deadline'] ?? null,
            'status' => $stored === 'completed' ? 'sent' : $stored,
            'detail' => $result['detail'] ?? null,
            'sent_to' => $result['sent_to'] ?? null,
            'provider_id' => $result['provider_id'] ?? null,
            // What is actually known about delivery, never more than that: an accepted message is "unknown"
            // (neither a mail server nor this SMS gateway reports delivery back), a refused one was not
            // delivered, a skipped one was never sent.
            'delivery_status' => ($delivery = $this->deliveryOf($stored === 'completed' ? 'sent' : $stored, (string) ($result['channel'] ?? 'email'), $result['provider_id'] ?? null, $context, (string) $row->created_at))[0],
            'delivery_note' => $delivery[1],
            'recorded_at' => (string) $row->created_at,
            'recorded_by' => $row->actor_id === null ? null : (int) $row->actor_id,
        ];
    }

    // ----------------------------------------------------------------- internals

    /**
     * The students, their amounts and contacts, the channel, wording and deadline — or why not.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function resolve(McpRequestContext $context, array $arguments): array
    {
        $deadline = $this->deadlineFrom($arguments['deadline'] ?? null);

        if ($deadline === null) {
            return $this->refuse(
                'A payment deadline is needed, as a date that is today or later (for example 2026-10-31), '
                .'and no more than a year ahead.',
                'invalid_deadline'
            );
        }

        $channel = strtolower(trim((string) ($arguments['channel'] ?? 'email')));

        if (! in_array($channel, self::CHANNELS, true)) {
            return $this->refuse('The reminder can be sent by email, SMS or WhatsApp.', 'invalid_channel');
        }

        $template = $this->cleanMessage($arguments['message'] ?? null);

        if ($template === false) {
            return $this->refuse(
                sprintf('That message is longer than %d characters, which is more than a reminder should be.', self::MAX_MESSAGE_CHARS),
                'message_too_long'
            );
        }

        $gateway = null;
        $gatewayHost = null;

        if ($channel === 'sms') {
            $gateway = $this->smsGateway($context);

            if ($gateway === null) {
                return $this->refuse(
                    'SMS is not set up for this institute: there is no active SMS gateway in Communication → Manage SMS API, '
                    .'so nothing can be sent by SMS. Email is available.',
                    'sms_not_configured'
                );
            }

            // The row was read for this institute; the Communication sender is told which one.
            $gateway['sub_institute_id'] = $context->selectedInstituteId;
            $gatewayHost = parse_url((string) ($gateway['url'] ?? ''), PHP_URL_HOST) ?: null;
        }

        $whatsapp = null;

        if ($channel === 'whatsapp') {
            $whatsapp = $this->whatsappConfiguration($context);

            if ($whatsapp === null) {
                return $this->refuse(
                    'WhatsApp is not set up for this institute: it needs the WhatsApp Cloud API access token and phone number id '
                    .'saved under Communication → WhatsApp API settings, and an approved message template, so nothing can be sent by '
                    .'WhatsApp. Email and SMS are available.',
                    'whatsapp_not_configured'
                );
            }

            $gatewayHost = 'graph.facebook.com';
        }

        $top = min(max((int) ($arguments['top'] ?? self::DEFAULT_TOP), 1), self::MAX_RECIPIENTS);
        $minimum = max((float) ($arguments['min_outstanding'] ?? 0), 0);

        $query = ['limit' => 50];
        $named = false;

        if (! empty($arguments['standard_id'])) {
            $query['standard_id'] = (int) $arguments['standard_id'];
        }

        if (! empty($arguments['student_ids']) && is_array($arguments['student_ids'])) {
            $query['student_ids'] = array_slice(array_values(array_filter(
                array_map('intval', $arguments['student_ids']),
                static fn (int $id) => $id > 0
            )), 0, self::MAX_RECIPIENTS);
            $top = self::MAX_RECIPIENTS;
            $named = true;
        }

        $read = $this->position->accounts($context, $query);

        if (! ($read['success'] ?? false)) {
            return $this->refuse(
                'The fee ledger could not be read, so no reminder list was made. '.(string) ($read['message'] ?? ''),
                'ledger_unavailable'
            );
        }

        $accounts = is_array($read['data']['accounts'] ?? null) ? $read['data']['accounts'] : [];

        $accounts = array_values(array_filter(
            $accounts,
            static fn ($a) => is_array($a) && (float) ($a['outstanding'] ?? 0) > 0 && (float) $a['outstanding'] >= $minimum
        ));

        if ($accounts === []) {
            return $this->refuse(
                $named
                    ? 'The student you chose has no outstanding fees on the ledger, so there is nothing to remind them about.'
                    : ($minimum > 0
                        ? 'No student owes at least '.$this->rupees($minimum).', so there is nobody to remind.'
                        : 'No student has outstanding fees, so there is nobody to remind.'),
                'nobody_owes'
            );
        }

        $accounts = array_slice($accounts, 0, $top);
        $contacts = $this->contactsFor($context, array_map(static fn (array $a) => (int) $a['student_id'], $accounts));

        $recipients = [];

        foreach ($accounts as $account) {
            $id = (int) $account['student_id'];
            $contact = $this->contactFor($channel, $contacts[$id] ?? []);

            $recipients[] = [
                'student_id' => $id,
                'name' => trim((string) ($account['student_name'] ?? '')) ?: ('Student #'.$id),
                'class' => $account['standard_name'] ?? null,
                'outstanding' => round((float) $account['outstanding'], 2),
                'contact' => $contact['value'],
                'contact_problem' => $contact['problem'],
            ];
        }

        return [
            'success' => true,
            'channel' => $channel,
            'template' => $template,
            'deadline' => $deadline,
            'recipients' => $recipients,
            'gateway' => $gateway,
            'whatsapp' => $whatsapp,
            'gateway_host' => $gatewayHost,
        ];
    }

    /**
     * Send one reminder by the chosen channel and record the outcome, whatever it was.
     *
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>|null  $gateway
     * @return array<string, mixed>
     */
    private function deliver(McpRequestContext $context, array $recipient, string $channel, Carbon $deadline, string $reference, ?string $template, ?array $gateway): array
    {
        if ($recipient['contact'] === null) {
            return $this->record($context, $recipient, $channel, $deadline, $reference, 'skipped', (string) $recipient['contact_problem']);
        }

        $message = $this->render($channel, $recipient['name'], $recipient['outstanding'], $deadline, $template);
        $recipient['message'] = $message['text'];

        if ($channel === 'email') {
            try {
                Mail::to($recipient['contact'])->send(new FeeReminderNotice($this->subject(), $message['html']));

                return $this->record($context, $recipient, $channel, $deadline, $reference, 'sent', 'Accepted by the mail server.');
            } catch (Throwable $exception) {
                report($exception);

                return $this->record(
                    $context, $recipient, $channel, $deadline, $reference, 'failed',
                    'The mail server did not accept it: '.Str::limit(trim($exception->getMessage()), 140, '…')
                );
            }
        }

        if ($channel === 'whatsapp') {
            $configuration = $this->whatsappConfiguration($context);

            if ($configuration === null) {
                return $this->record($context, $recipient, $channel, $deadline, $reference, 'failed', 'WhatsApp is not configured for this institute, so nothing was sent.');
            }

            $reply = $this->deliverWhatsapp(
                $context,
                ['student_id' => (int) $recipient['student_id'], 'mobile' => (string) $recipient['contact'], 'standard_id' => null, 'division_id' => null],
                $this->oneLine($message['text']),
                $configuration
            );

            return $this->record($context, $recipient, $channel, $deadline, $reference, $reply['status'], $reply['detail'], $reply['outage'], $reply['provider_id'] ?? null);
        }

        $reply = $this->dispatchSms($gateway ?? [], (string) $recipient['contact'], $message['text']);

        if ($reply['status'] === 'sent') {
            $this->logLegacySms((int) $recipient['student_id'], $message['text'], (string) $recipient['contact'], $context);
        }

        return $this->record($context, $recipient, $channel, $deadline, $reference, $reply['status'], $reply['detail'], $reply['outage'], $reply['provider_id'] ?? null);
    }

    /**
     * Write the recipient's ledger row and return the result the caller reports.
     *
     * @param  array<string, mixed>  $recipient
     * @return array<string, mixed>
     */
    private function record(McpRequestContext $context, array $recipient, string $channel, Carbon $deadline, string $reference, string $status, string $detail, bool $outage = false, ?string $providerId = null): array
    {
        $this->audit->record(self::EVENT, $context, [
            'subject_entity_key' => 'student',
            'subject_id' => $recipient['student_id'],
            'outcome' => $status === 'sent' ? 'success' : ($status === 'skipped' ? 'rejected' : 'failure'),
            'message' => sprintf('Fee reminder to %s by %s: %s.', $recipient['name'], $channel, $status),
            'payload' => [
                'module' => 'fees',
                'operation' => 'fee_reminder',
                'operation_label' => 'Fee reminder',
                'capability' => 'conversational',
                // The Activity tab's own vocabulary: it counts and colours completed / failed /
                // skipped. "completed" means the server or gateway accepted the message, which is
                // all this can know; the result below says so in words.
                'status' => $status === 'sent' ? 'completed' : $status,
                'subject_label' => $recipient['name'],
                'reference' => $reference,
                'used' => ['tool' => 'fees.send_reminders'],
                'result' => [
                    'channel' => $channel,
                    'class' => $recipient['class'] ?? null,
                    'message' => $recipient['message'] ?? null,
                    'outstanding' => $recipient['outstanding'],
                    'deadline' => $deadline->toDateString(),
                    'detail' => $detail,
                    'provider_id' => $providerId,
                    'sent_to' => $recipient['contact'] === null ? null : $this->mask((string) $recipient['contact'], $channel),
                ],
            ],
        ]);

        return [
            'student_id' => $recipient['student_id'],
            'name' => $recipient['name'],
            'outstanding' => $recipient['outstanding'],
            'status' => $status,
            'detail' => $detail,
            'outage' => $outage,
        ];
    }

    // ------------------------------------------------------------------- SMS

    /**
     * The institute's active SMS gateway row, or null. Read-only.
     *
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

    /**
     * Send one SMS exactly as Communication → Send SMS to Parents does: through that screen's own
     * `send_sms_parents_controller::sendSMS()`, so the gateway row, the way its URL is assembled and the
     * request itself are the ones already working there. Nothing about the gateway is re-implemented here.
     *
     * What that sender can report is narrow, and this says so: it returns an error only when the request
     * could not be made (no connection, a refused URL, no gateway row). It does not read the provider's
     * reply, so a message it hands over is recorded as sent, but the provider's acceptance is not known.
     *
     * @param  array<string, mixed>  $gateway
     * @return array{status:string, detail:string, outage:bool}
     */
    protected function dispatchSms(array $gateway, string $mobile, string $text): array
    {
        try {
            $reply = $this->legacySend((int) ($gateway['sub_institute_id'] ?? 0), $mobile, $text);
            $from = parse_url((string) ($gateway['url'] ?? ''), PHP_URL_HOST) ?: 'the saved gateway address';
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'failed', 'detail' => 'The SMS request failed before it could be made ('.class_basename($exception).').', 'outage' => true];
        }

        if (! empty($reply['error'])) {
            $reason = Str::limit(trim((string) ($reply['message'] ?? '')), 140, '…');

            return [
                'status' => 'failed',
                'detail' => 'The SMS sender reported an error: '.($reason === '' ? 'no reason given' : $reason),
                // curl 6 (host), 7 (connect) and 28 (timeout): the gateway is not answering at all.
                'outage' => (bool) preg_match('/-(6|7|28)$/', $reason),
            ];
        }

        // A redirect is how a provider retires an address; the page it points at answers for another service.
        if (! empty($reply['redirect_host']) || (isset($reply['http']) && $reply['http'] >= 300 && $reply['http'] < 400)) {
            return [
                'status' => 'failed',
                'detail' => sprintf(
                    'The SMS gateway (%s) redirected the request%s instead of accepting it, which usually means the provider has retired that address or its key. '
                    .'The gateway address and key in the school\'s SMS settings need to be checked with the provider.',
                    $from,
                    ! empty($reply['redirect_host']) ? ' to '.$reply['redirect_host'] : ''
                ),
                'outage' => false,
            ];
        }

        // The provider's own answer, when the sender read one.
        if (isset($reply['http'])) {
            $http = (int) $reply['http'];
            $excerpt = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($reply['body'] ?? ''))) ?? ''), 120, '…');

            if ($http < 200 || $http >= 300) {
                return ['status' => 'failed', 'detail' => sprintf('The SMS gateway answered HTTP %d%s.', $http, $excerpt === '' ? '' : ': '.$excerpt), 'outage' => $http >= 500];
            }

            if ($excerpt === '') {
                return ['status' => 'failed', 'detail' => 'The SMS gateway answered but said nothing, so there is no evidence it accepted the message.', 'outage' => false];
            }

            if (preg_match('/\b(error|invalid|fail(?:ed|ure)?|denied|unauthori[sz]ed|insufficient|not approved|reject(?:ed)?|wrong|blocked|blacklist(?:ed)?|expired|unknown|not (?:found|allowed|registered))\b/i', $excerpt)) {
                return ['status' => 'failed', 'detail' => 'The SMS gateway replied with an error: '.$excerpt, 'outage' => false];
            }

            return [
                'status' => 'sent',
                'detail' => sprintf('Accepted by the SMS gateway (HTTP %d): %s. Delivery to the phone is not reported back.', $http, $excerpt),
                'outage' => false,
                // The gateway's own id for this message, when its reply carries one, so it can be traced with the provider.
                'provider_id' => $this->providerMessageId((string) ($reply['body'] ?? '')),
            ];
        }

        // A sender that read no reply: handed over, nothing more is known.
        return [
            'status' => 'sent',
            'detail' => 'Handed to the SMS gateway through the Communication module\'s sender. That sender does not read the provider\'s reply, so acceptance and delivery are not confirmed.',
            'outage' => false,
        ];
    }

    /** The gateway's message id from a JSON reply (`msgid`, or the first `data[].id`), or null. */
    protected function providerMessageId(string $body): ?string
    {
        $json = json_decode($body, true);

        if (! is_array($json)) {
            return null;
        }

        $id = $json['msgid'] ?? ($json['data'][0]['id'] ?? null);

        return is_scalar($id) && trim((string) $id) !== '' ? Str::limit(trim((string) $id), 60, '') : null;
    }

    /**
     * The Communication module's own sender. Overridable so tests need no network.
     *
     * @return array{error:int, message?:mixed, http?:int, body?:string, redirect_host?:?string}
     */
    protected function legacySend(int $instituteId, string $mobile, string $text): array
    {
        // The Communication sender's own variant that also reads the provider's answer; see its docblock.
        return app(send_sms_parents_controller::class)->sendSMSDetailed($mobile, $text, $instituteId);
    }

    /**
     * When this student was last sent this same reminder (same channel and deadline) within the
     * duplicate window, or null.
     *
     * The approval token is single-use, so a second approval cannot send. This is the second line:
     * two requests that both carry a valid approval, or a retry after a timeout, still cannot send
     * one person two identical messages.
     */
    protected function recentlySentAt(McpRequestContext $context, int $studentId, string $channel, Carbon $deadline): ?string
    {
        try {
            if (! Schema::hasTable('ai_audit_logs')) {
                return null;
            }

            $rows = DB::table('ai_audit_logs')
                ->where('event_type', self::EVENT)
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->where('subject_id', $studentId)
                ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'subject_id', 'outcome', 'payload', 'created_at', 'actor_id']);

            foreach ($rows as $row) {
                $earlier = $this->reminderFromRow($row);

                if ($earlier['status'] === 'sent' && $earlier['channel'] === $channel && $earlier['deadline'] === $deadline->toDateString()) {
                    return $earlier['recorded_at'];
                }
            }
        } catch (Throwable $exception) {
            // A guard that cannot look must not stop a reminder the person approved; the approval
            // token still protects against the ordinary repeat.
            report($exception);
        }

        return null;
    }

    /** The legacy Communication-module SMS log, written only for accepted messages. */
    protected function logLegacySms(int $studentId, string $text, string $mobile, McpRequestContext $context): void
    {
        try {
            if (! Schema::hasTable('sms_sent_parents')) {
                return;
            }

            DB::table('sms_sent_parents')->insert([
                'SYEAR' => $context->academicYear,
                'STUDENT_ID' => $studentId,
                'SMS_TEXT' => $text,
                'SMS_NO' => $mobile,
                'MODULE_NAME' => 'SENT SMS PARENT',
                'sub_institute_id' => $context->selectedInstituteId,
            ]);
        } catch (Throwable $exception) {
            // The reminder was accepted by the gateway; failing to add a line to the old report
            // must not turn that into a failure, and is reported for whoever maintains it.
            report($exception);
        }
    }

    /**
     * Characters and parts of an SMS. A character outside the basic set makes it Unicode,
     * where a part holds 70 characters, not 160.
     *
     * @return array{characters:int, segments:int, unicode:bool}
     */
    private function smsFacts(string $text): array
    {
        $length = mb_strlen($text);
        $unicode = (bool) preg_match('/[^\x20-\x7E\n]/', $text);
        $single = $unicode ? 70 : 160;
        $multi = $unicode ? 67 : 153;

        return [
            'characters' => $length,
            'segments' => $length <= $single ? 1 : (int) ceil($length / $multi),
            'unicode' => $unicode,
        ];
    }

    // ------------------------------------------------------------ the message

    /**
     * What this student is sent, in every form it takes.
     *
     * @return array{text:string, html:string}
     */
    public function render(string $channel, string $name, float $amount, Carbon $deadline, ?string $template = null): array
    {
        // An SMS amount is written "Rs." rather than "₹": the rupee sign is outside the basic SMS
        // character set, and one such character turns a message into Unicode and halves its length.
        $money = $this->isMobile($channel) ? str_replace('₹', 'Rs.', $this->rupees($amount)) : $this->rupees($amount);

        if ($template !== null) {
            $text = strtr($template, [
                '{name}' => $name,
                '{amount}' => $money,
                '{deadline}' => $this->dateLabel($deadline),
            ]);

            return ['text' => $text, 'html' => '<p>'.nl2br(e($text)).'</p>'];
        }

        if ($this->isMobile($channel)) {
            return [
                'text' => sprintf('Dear %s, your outstanding fee amount is %s. Please submit your pending fees by %s.', $name, $money, $this->dateLabel($deadline)),
                'html' => '',
            ];
        }

        return ['text' => $this->plainText($name, $amount, $deadline), 'html' => $this->html($name, $amount, $deadline)];
    }

    public function plainText(string $name, float $amount, Carbon $deadline): string
    {
        return sprintf(
            "Dear %s,\n\nYour outstanding fee amount is %s. Please submit your pending fees by %s.\n\n"
            ."If you have already made this payment, please ignore this reminder.",
            $name,
            $this->rupees($amount),
            $this->dateLabel($deadline)
        );
    }

    private function html(string $name, float $amount, Carbon $deadline): string
    {
        return '<p>Dear '.e($name).',</p>'
            .'<p>Your outstanding fee amount is <strong>'.e($this->rupees($amount)).'</strong>. '
            .'Please submit your pending fees by <strong>'.e($this->dateLabel($deadline)).'</strong>.</p>'
            .'<p>If you have already made this payment, please ignore this reminder.</p>';
    }

    /**
     * A custom message as the person typed it, made safe: no markup, one tidy line of whitespace,
     * and not so long it stops being a reminder. Null for none; false for too long.
     */
    private function cleanMessage(mixed $value): string|false|null
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace("/[ \t]+/", ' ', strip_tags($value)) ?? '');

        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > self::MAX_MESSAGE_CHARS ? false : $text;
    }

    /**
     * Things worth knowing before approving, none of which stop the send.
     *
     * @return array<int, string>
     */
    private function warnings(string $channel, string $sampleText, ?string $template, Carbon $deadline): array
    {
        $warnings = [];

        if ($template !== null) {
            if (! str_contains($sampleText, $this->dateLabel($deadline))) {
                $warnings[] = 'Your message does not mention the payment deadline. Use {deadline} to include it.';
            }

            if (! preg_match('/\d/', $sampleText)) {
                $warnings[] = 'Your message does not mention the amount owed. Use {amount} to include each student\'s own figure.';
            }
        }

        if ($channel === 'sms') {
            $facts = $this->smsFacts($sampleText);

            if ($facts['segments'] > 1) {
                $warnings[] = sprintf('This SMS is %d parts long (%d characters); most gateways charge for each part.', $facts['segments'], $facts['characters']);
            }

            $warnings[] = 'Many SMS providers only deliver messages that match a pre-approved (DLT) template. If this wording '
                .'is not one of yours, the gateway may refuse it — its reply is shown after sending, and nothing is called sent that it did not accept.';
        }

        if ($channel === 'whatsapp') {
            $warnings[] = 'WhatsApp business messages go out as the institute\'s approved template, with this wording fitted into it. A parent who has not opted in, '
                .'or a template that is not approved for this WhatsApp Business account, will be refused by WhatsApp — its reply is shown after sending, '
                .'and nothing is called sent that it did not accept.';
        }

        return $warnings;
    }

    // ----------------------------------------------------------------- channels

    /** SMS and WhatsApp both go to the mobile number on the student's record. */
    private function isMobile(string $channel): bool
    {
        return $channel === 'sms' || $channel === 'whatsapp';
    }

    /** The channel in a sentence: "by SMS", "by WhatsApp", "by email". */
    private function channelWord(string $channel): string
    {
        return match ($channel) {
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
            default => 'email',
        };
    }

    private function channelLabel(string $channel): string
    {
        return match ($channel) {
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
            default => 'Email',
        };
    }

    /**
     * Delivery counted from each record's own state: WhatsApp confirms delivered and read, an SMS gateway and a mail
     * server never do. Nothing is assumed from the channel or from the stored status.
     *
     * @param  array<int, array<string, mixed>>  $reminders
     * @return array<string, int>
     */
    private function deliveryCounts(array $reminders): array
    {
        $by = array_count_values(array_column($reminders, 'delivery_status'));

        return [
            'confirmed_delivered' => ($by['delivered'] ?? 0) + ($by['read'] ?? 0),
            'read' => $by['read'] ?? 0,
            'pending' => $by['pending'] ?? 0,
            'not_confirmed' => $by['not_confirmed'] ?? 0,
            'unknown' => $by['unknown'] ?? 0,
            'not_delivered' => ($by['not_delivered'] ?? 0) + ($by['failed'] ?? 0),
            'not_sent' => $by['not_sent'] ?? 0,
        ];
    }

    /** One plain sentence on what is and is not known about delivery. @param array<int, array<string, mixed>> $reminders */
    private function deliverySentence(array $reminders): string
    {
        $d = $this->deliveryCounts($reminders);
        $parts = [];

        if ($d['confirmed_delivered'] > 0) {
            $parts[] = sprintf('WhatsApp has confirmed %d as delivered or read', $d['confirmed_delivered']);
        }

        if ($d['pending'] > 0) {
            $parts[] = sprintf('%d WhatsApp message%s accepted with no delivery receipt yet', $d['pending'], $d['pending'] === 1 ? '' : 's');
        }

        if ($d['not_confirmed'] > 0) {
            $parts[] = sprintf('%d accepted but never reported delivered by the provider, so they have not been confirmed', $d['not_confirmed']);
        }

        if ($d['unknown'] > 0) {
            $parts[] = sprintf('delivery of %d is unknown (no delivery report exists for it)', $d['unknown']);
        }

        if ($d['not_delivered'] > 0) {
            $parts[] = sprintf('%d not delivered', $d['not_delivered']);
        }

        return $parts === [] ? '' : ' '.ucfirst(implode('; ', $parts)).'.';
    }

    /** WhatsApp takes the text as one template parameter and refuses line breaks in it. */
    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * The approved Cloud API template a reminder goes out as, chosen by the Communication module's own rule: the number
     * of {{n}} placeholders in the wording (none for a reminder, so the plain one-parameter template).
     *
     * @return array{kind:string, name:string, text:?string, note:string}
     */
    private function whatsappTemplate(string $message): array
    {
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $message, $found);
        $count = count(array_unique($found[1] ?? []));
        $name = $count === 2 ? 'p2' : ($count >= 3 ? 'p3' : 'parents_');

        return [
            'kind' => 'cloud_template',
            'name' => $name,
            'language' => 'en_US (if WhatsApp does not find it, en and then en_GB are tried)',
            'text' => null,
            'note' => sprintf('Sent as the approved WhatsApp template "%s". It must be approved for this WhatsApp Business account, or WhatsApp will refuse the message.', $name),
        ];
    }

    /**
     * What is known about delivery, never more than that.
     *
     * @return array{0:string, 1:string}
     */
    private function deliveryOf(string $status, string $channel, mixed $providerId, ?McpRequestContext $context = null, ?string $sentAt = null): array
    {
        return match (true) {
            $status === 'failed' => ['not_delivered', 'The provider did not accept it, so it was not delivered.'],
            $status !== 'sent' => ['not_sent', 'It was never sent.'],
            $channel === 'whatsapp' => $this->whatsappReceipt(is_string($providerId) ? $providerId : null, $sentAt),
            // The gateway's own delivery report, read live: delivered, failed, still submitted, or unknown.
            $channel === 'sms' => $this->smsDeliveryOf($context, $providerId, $sentAt),
            default => ['unknown', 'The mail server accepted it but does not report delivery, so whether it reached the inbox is unknown.'],
        };
    }

    // ----------------------------------------------------------------- contacts

    /**
     * How a student can be reached, masked, and which reminder channels that makes available.
     *
     * Shown with a student's fee details so "can I text them?" is answered before a reminder is
     * prepared. Only the masked forms leave this method.
     *
     * @return array{email_masked: ?string, mobile_masked: ?string, channels: array<int, string>, email_problem: ?string, mobile_problem: ?string}
     */
    public function contactSummary(McpRequestContext $context, int $studentId): array
    {
        $contact = $this->contactsFor($context, [$studentId])[$studentId] ?? [];
        $email = $this->contactFor('email', $contact);
        $mobile = $this->contactFor('sms', $contact);

        return [
            'email_masked' => $email['value'] === null ? null : $this->mask($email['value'], 'email'),
            'mobile_masked' => $mobile['value'] === null ? null : $this->mask($mobile['value'], 'sms'),
            'channels' => array_values(array_filter([$email['value'] !== null ? 'email' : null, $mobile['value'] !== null ? 'sms' : null, $mobile['value'] !== null ? 'whatsapp' : null])),
            'email_problem' => $email['problem'],
            'mobile_problem' => $mobile['problem'],
            // The numbers an SMS is NOT sent to, so a person can see why one differs from what they expect.
            'other_mobiles' => array_filter([
                'Mother' => $this->otherMobile($contact['mother_mobile'] ?? null, $mobile['value']),
                'Student' => $this->otherMobile($contact['student_mobile'] ?? null, $mobile['value']),
            ]),
        ];
    }

    /**
     * Contacts from the student rows themselves, scoped to this institute.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, array{email: ?string, mobile: ?string}>
     */
    protected function contactsFor(McpRequestContext $context, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $contacts = [];

        foreach (DB::table('tblstudent')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->whereIn('id', $studentIds)
            ->get(['id', 'email', 'mobile', 'mother_mobile', 'student_mobile']) as $row) {
            $contacts[(int) $row->id] = [
                'email' => $row->email === null ? null : (string) $row->email,
                'mobile' => $row->mobile === null ? null : (string) $row->mobile,
                'mother_mobile' => $row->mother_mobile === null ? null : (string) $row->mother_mobile,
                'student_mobile' => $row->student_mobile === null ? null : (string) $row->student_mobile,
            ];
        }

        return $contacts;
    }

    /**
     * The usable contact for a channel, or the reason there is not one.
     *
     * @param  array<string, mixed>  $contact
     * @return array{value: ?string, problem: ?string}
     */
    private function contactFor(string $channel, array $contact): array
    {
        if ($this->isMobile($channel)) {
            $raw = trim((string) ($contact['mobile'] ?? ''));
            $mobile = $this->validMobile($raw);

            return [
                'value' => $mobile,
                'problem' => $mobile !== null ? null : ($raw === '' ? 'No mobile number on the student record.' : 'The mobile number on record is not a valid 10-digit Indian mobile.'),
            ];
        }

        $raw = trim((string) ($contact['email'] ?? ''));
        $email = $raw !== '' && filter_var($raw, FILTER_VALIDATE_EMAIL) ? $raw : null;

        return [
            'value' => $email,
            'problem' => $email !== null ? null : ($raw === '' ? 'No email address on the student record.' : 'The email address on record is not valid.'),
        ];
    }

    private function otherMobile(?string $raw, ?string $used): ?string
    {
        $valid = $raw === null ? null : $this->validMobile($raw);

        return $valid === null || $valid === $used ? null : $this->mask($valid, 'sms');
    }

    /** A 10-digit Indian mobile, from the ways it is commonly written; null if it is not one. */
    private function validMobile(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }

    private function mask(string $value, string $channel): string
    {
        if ($this->isMobile($channel)) {
            return '******'.substr($value, -4);
        }

        [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');

        return $domain === '' ? '***' : Str::substr($local, 0, 1).'***@'.$domain;
    }

    // ----------------------------------------------------------------- the rest

    /** @return array<string, mixed> */
    private function refuse(string $message, string $code): array
    {
        return ['success' => false, 'failure' => ToolResult::failure('fees.send_reminders', $message, $code)];
    }

    /**
     * A date from a caller, accepted only if it is today or later and within a year.
     */
    private function deadlineFrom(mixed $value): ?Carbon
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (Throwable) {
            return null;
        }

        if ($date->toDateString() !== $raw) {
            return null;
        }

        $today = Carbon::today();

        return $date->lt($today) || $date->gt($today->copy()->addDays(self::MAX_DEADLINE_DAYS)) ? null : $date;
    }

    /**
     * The same students, amounts, deadline, channel and wording give the same fingerprint, and
     * any change gives a different one — so an approval covers all of them.
     *
     * @param  array<int, array<string, mixed>>  $reachable
     */
    private function fingerprint(array $reachable, Carbon $deadline, string $channel, ?string $template, ?array $gateway = null): string
    {
        $pairs = [];

        foreach ($reachable as $recipient) {
            $pairs[(int) $recipient['student_id']] = number_format((float) $recipient['outstanding'], 2, '.', '');
        }

        ksort($pairs);

        // For SMS the institute's saved template is part of the message that was approved: a signature or template
        // id changed between the preview and the send is a different SMS, so it needs a new approval.
        $saved = $channel === 'sms' && $gateway !== null ? SmsGatewayTemplate::describe($gateway) : ($channel === 'whatsapp' ? $this->whatsappTemplate($template ?? '') : null);

        return hash('sha256', json_encode($saved === null ? [$pairs, $deadline->toDateString(), $channel, $template ?? ''] : [$pairs, $deadline->toDateString(), $channel, $template ?? '', $saved]));
    }

    private function subject(): string
    {
        return 'Fee payment reminder';
    }

    private function dateLabel(Carbon $date): string
    {
        return $date->format('j F Y');
    }

    /** ₹ with Indian digit grouping (12,34,567), and paise only when there are some. */
    public function rupees(float $amount): string
    {
        return self::formatRupees($amount);
    }

    public static function formatRupees(float $amount): string
    {
        $negative = $amount < 0;
        $amount = abs($amount);
        $whole = (int) floor($amount);
        $paise = (int) round(($amount - $whole) * 100);

        if ($paise === 100) {
            $whole++;
            $paise = 0;
        }

        $digits = (string) $whole;
        $last3 = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $grouped = $rest === '' ? $last3 : preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;

        return ($negative ? '-' : '').'₹'.$grouped.($paise > 0 ? sprintf('.%02d', $paise) : '');
    }
}
