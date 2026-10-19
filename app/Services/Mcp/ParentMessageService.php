<?php

namespace App\Services\Mcp;

use App\Domain\AI\Support\AiAuditLogger;
use App\Http\Controllers\easy_com\send_sms_parents\send_sms_parents_controller;
use App\Services\Mcp\Concerns\DeliversWhatsapp;
use App\Services\Mcp\Concerns\ReadsSmsDelivery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

use function App\Helpers\SearchStudent;

/**
 * A message to students' parents, by SMS or WhatsApp, sent from the assistant after explicit approval.
 *
 * NOTHING HERE IS A NEW GATEWAY. Both channels go through the senders the Communication module already
 * uses, with the institute's own saved settings:
 *
 *   - SMS    -> `send_sms_parents_controller::sendSMSDetailed()` (the Parent module's sender; the gateway
 *               row, URL assembly and key stay on the server) and the legacy `sms_sent_parents` log.
 *   - WhatsApp -> `WhatsappController::sendWhatsappCloudApi()` (Meta Cloud API, approved templates) and the
 *               `whatsapp_sent_messages` log that the delivery webhook later updates with delivered/read.
 *
 * Two calls by design: `preview()` lists who would be messaged, the exact wording and the fingerprint of
 * that list, and sends nothing; `send()` sends only if the list the database gives now is the one approved.
 *
 * "SENT" MEANS THE PROVIDER ACCEPTED IT. For SMS that is the gateway's own reply; for WhatsApp it is a
 * message id from the Cloud API. Neither is proof of delivery, and the result says so. A WhatsApp message's
 * later delivered/read state is read back from `whatsapp_sent_messages` by `communication.messages`.
 */
class ParentMessageService
{
    use DeliversWhatsapp;
    use ReadsSmsDelivery;

    public const CHANNELS = ['sms', 'whatsapp'];

    public const MAX_RECIPIENTS = 50;

    public const SMS_MAX_LENGTH = 480;

    public const WHATSAPP_MAX_LENGTH = 1000;

    public const DUPLICATE_WINDOW_MINUTES = 10;

    public const EVENT = 'module.communication.parent_message';

    /**
     * Variables a message may use, each replaced with the student's own value from their record.
     * Shown to the person writing the message, so nothing is a guess.
     */
    public const VARIABLES = [
        '{name}' => 'the student\'s first name',
        '{student_name}' => 'the student\'s full name',
        '{class}' => 'the class and division, such as "10 A"',
        '{enrollment_no}' => 'the GR / enrollment number',
    ];

    /** After this many gateway outages in a row the rest are recorded as failed rather than waited on. */
    private const OUTAGES_BEFORE_STOP = 2;

    public function __construct(private readonly AiAuditLogger $audit)
    {
    }

    // ------------------------------------------------------------------ preview

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function preview(McpRequestContext $context, array $arguments): array
    {
        $resolved = $this->resolve($context, $arguments);

        if (! $resolved['success']) {
            return $resolved['failure'];
        }

        $reachable = $resolved['reachable'];
        $unreachable = $resolved['unreachable'];
        $channel = $resolved['channel'];
        $label = $this->label($channel);

        $message = $reachable === []
            ? sprintf('None of these students has a usable mobile number on record, so nobody can be messaged by %s.', $label)
            : sprintf(
                '%d message%s ready to send by %s; %d student%s without a usable number will not be contacted.',
                count($reachable),
                count($reachable) === 1 ? '' : 's',
                $label,
                count($unreachable),
                count($unreachable) === 1 ? '' : 's'
            );

        return ToolResult::success('communication.send_parent_message', $message, [
            'channel' => $channel,
            'channel_label' => $label,
            'recipient_count' => count($reachable),
            'unreachable_count' => count($unreachable),
            'recipients' => array_map(fn (array $r) => [
                'student_id' => $r['student_id'],
                'name' => $r['name'],
                'class' => $r['class'],
                'enrollment_no' => $r['enrollment_no'],
                'contact_masked' => $this->mask($r['mobile']),
            ], $reachable),
            'unreachable' => array_map(static fn (array $r) => [
                'student_id' => $r['student_id'],
                'name' => $r['name'],
                'class' => $r['class'],
                'reason' => $r['problem'],
            ], $unreachable),
            // The first recipient's actual message: what is approved is the wording that will be sent.
            'sample_message' => $reachable === [] ? null : $this->personalise($resolved['message'], $reachable[0]),
            'message_length' => mb_strlen($resolved['message']),
            'provider' => $resolved['provider'],
            // What the Parent module's Send SMS screen applies without showing: the institute's saved
            // gateway template or signature. Shown here so the person reviews the message as it will go.
            'template' => $resolved['template'],
            'final_message' => $reachable === [] ? null : $this->finalText($channel, $this->personalise($resolved['message'], $reachable[0]), $resolved['template']),
            'variables' => self::VARIABLES,
            'warnings' => $this->warnings($channel, $resolved['message'], $resolved['template']),
            'fingerprint' => $reachable === [] ? null : $this->fingerprint($reachable, $channel, $resolved['message'], $resolved['template']),
            'basis' => 'Each student\'s parent is messaged on the mobile number on that student\'s own record. '
                .'"Sent" will mean the provider accepted the message, not that it was delivered.',
        ]);
    }

    // ----------------------------------------------------------------- describe

    /**
     * Who was selected and what the message will be sent through, before any wording exists.
     *
     * This is the step the Parent module's screen shows once a student is ticked: the student, the
     * registered mobile number, the institute's saved SMS template or signature, and the variables the
     * message may use. Reads only; nothing is previewed for approval and nothing is sent.
     *
     * @param  array<string, mixed>  $arguments  `channel` and the recipients; no message is needed.
     * @return array<string, mixed>
     */
    public function describe(McpRequestContext $context, array $arguments): array
    {
        $resolved = $this->resolve($context, ['message' => '{name}'] + $arguments);

        if (! $resolved['success']) {
            return $resolved['failure'];
        }

        $card = fn (array $r, ?string $problem) => [
            'student_id' => $r['student_id'],
            'name' => $r['name'],
            'class' => $r['class'],
            'enrollment_no' => $r['enrollment_no'],
            'contact_masked' => $problem === null ? $this->mask($r['mobile']) : null,
            'problem' => $problem,
        ];

        return ToolResult::success('communication.send_parent_message', 'Recipient and template loaded.', [
            'channel' => $resolved['channel'],
            'channel_label' => $this->label($resolved['channel']),
            'students' => array_merge(
                array_map(fn (array $r) => $card($r, null), $resolved['reachable']),
                array_map(fn (array $r) => $card($r, (string) $r['problem']), $resolved['unreachable'])
            ),
            'recipient_count' => count($resolved['reachable']),
            'template' => $resolved['template'],
            'variables' => self::VARIABLES,
            'provider' => $resolved['provider'],
            'limit' => $resolved['channel'] === 'sms' ? self::SMS_MAX_LENGTH : self::WHATSAPP_MAX_LENGTH,
        ]);
    }

    // --------------------------------------------------------------------- send

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function send(McpRequestContext $context, array $arguments, ?string $expectedFingerprint): array
    {
        $resolved = $this->resolve($context, $arguments);

        if (! $resolved['success']) {
            return $resolved['failure'];
        }

        $reachable = $resolved['reachable'];
        $channel = $resolved['channel'];

        if ($expectedFingerprint === null || $expectedFingerprint === '') {
            return ToolResult::failure(
                'communication.send_parent_message',
                'Nothing was sent: there is no approved list to send. Preview the message and approve it first.',
                'no_approved_list'
            );
        }

        if ($reachable === []) {
            return ToolResult::failure('communication.send_parent_message', 'None of these students has a usable mobile number on record, so nothing was sent.', 'no_recipients');
        }

        if (! hash_equals($expectedFingerprint, $this->fingerprint($reachable, $channel, $resolved['message'], $resolved['template']))) {
            return ToolResult::failure(
                'communication.send_parent_message',
                'The list changed since you reviewed it (different students, numbers, channel or wording). '
                .'Nothing was sent. Review the new list and approve it again.',
                'recipients_changed'
            );
        }

        $reference = 'PM-'.now()->format('Ymd').'-'.Str::lower(Str::random(5));
        $results = [];
        $outages = 0;

        foreach ($reachable as $index => $recipient) {
            if ($outages >= self::OUTAGES_BEFORE_STOP) {
                $results[] = $this->record($context, $recipient, $channel, $reference, $resolved['message'], 'failed', 'Not attempted: the provider could not be reached for the previous messages.');

                continue;
            }

            $text = $this->personalise($resolved['message'], $recipient);
            $earlier = $this->recentlySentAt($context, (int) $recipient['student_id'], $channel, $text);

            if ($earlier !== null) {
                $results[] = $this->record($context, $recipient, $channel, $reference, $text, 'skipped', 'The same message was already sent at '.$earlier.'; not sent again.');

                continue;
            }

            if ($channel === 'whatsapp' && $index > 0) {
                // The pacing the Communication screen itself uses between Cloud API calls.
                usleep(300000);
            }

            $outcome = $channel === 'sms'
                ? $this->deliverSms($context, $recipient, $this->finalText('sms', $text, $resolved['template']), $resolved['sms_gateway'])
                : $this->deliverWhatsapp($context, $recipient, $this->finalText('whatsapp', $text, $resolved['template']), $resolved['whatsapp']);

            $outages = $outcome['outage'] ? $outages + 1 : 0;
            $results[] = $this->record($context, $recipient, $channel, $reference, $text, $outcome['status'], $outcome['detail'], $outcome['provider_id'] ?? null);
        }

        $sent = count(array_filter($results, static fn (array $r) => $r['status'] === 'sent'));
        $failed = count(array_filter($results, static fn (array $r) => $r['status'] === 'failed'));
        $skipped = count(array_filter($results, static fn (array $r) => $r['status'] === 'skipped'));

        $summary = sprintf(
            '%d message%s accepted by %s, %d failed, %d skipped. %s',
            $sent,
            $sent === 1 ? '' : 's',
            $channel === 'sms' ? 'the SMS gateway' : 'WhatsApp',
            $failed,
            $skipped,
            $channel === 'sms'
                ? '"Accepted" is the gateway\'s own reply; delivery to the phone is not reported back.'
                : '"Accepted" means WhatsApp returned a message id; whether it was delivered or read is updated later and can be read with the Communication messages report.'
        );

        $data = [
            'reference' => $reference,
            'channel' => $channel,
            'channel_label' => $this->label($channel),
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'results' => array_map(static fn (array $r) => [
                'student_id' => $r['student_id'],
                'name' => $r['name'],
                'status' => $r['status'],
                'detail' => $r['detail'],
                'provider_id' => $r['provider_id'],
            ], $results),
        ];

        return $sent === 0
            ? ToolResult::failure('communication.send_parent_message', 'No message could be sent. '.$summary, 'send_failed', $data)
            : ToolResult::success('communication.send_parent_message', $summary, $data);
    }

    // ---------------------------------------------------------------- resolution

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function resolve(McpRequestContext $context, array $arguments): array
    {
        $channel = mb_strtolower(trim((string) ($arguments['channel'] ?? '')));

        if (! in_array($channel, self::CHANNELS, true)) {
            return $this->refuse('Choose the channel: sms or whatsapp.', 'invalid_channel');
        }

        $message = $this->cleanMessage($arguments['message'] ?? null);
        $max = $channel === 'sms' ? self::SMS_MAX_LENGTH : self::WHATSAPP_MAX_LENGTH;

        if ($message === null) {
            return $this->refuse('The message is empty. Say what the parents should be told.', 'empty_message');
        }

        if (mb_strlen($message) > $max) {
            return $this->refuse(sprintf('The message is %d characters; a %s message here is limited to %d.', mb_strlen($message), $this->label($channel), $max), 'message_too_long');
        }

        $configuration = $channel === 'sms' ? $this->smsGateway($context) : $this->whatsappConfiguration($context);

        if ($configuration === null) {
            return $this->refuse(
                $channel === 'sms'
                    ? 'No SMS gateway is saved for this institute, so nothing can be sent. Add the SMS API details under Communication → SMS API Master.'
                    : 'WhatsApp is not configured for this institute, so nothing can be sent. It needs the WhatsApp Cloud API access token and phone number id '
                        .'(Meta WhatsApp Business) saved under Communication → WhatsApp API settings, and an approved message template.',
                'channel_not_configured'
            );
        }

        $students = $this->students($context, $arguments);

        if ($students === null) {
            return $this->refuse('Name the students (student_ids) or a class (standard_id, optionally division_id) to message.', 'no_recipients_named');
        }

        if ($students === []) {
            return $this->refuse('No students matched that selection in this institute.', 'no_students');
        }

        if (count($students) > self::MAX_RECIPIENTS) {
            return $this->refuse(sprintf('That is %d students; one message is limited to %d recipients. Narrow the selection.', count($students), self::MAX_RECIPIENTS), 'too_many_recipients');
        }

        $reachable = [];
        $unreachable = [];

        foreach ($students as $student) {
            $raw = trim((string) $student['mobile']);
            $mobile = $this->validMobile($raw);

            if ($mobile === null) {
                $student['problem'] = $raw === '' ? 'No mobile number on the student record.' : 'The mobile number on record is not a valid 10-digit Indian mobile.';
                $unreachable[] = $student;

                continue;
            }

            $student['mobile'] = $mobile;
            $reachable[] = $student;
        }

        return [
            'success' => true,
            'channel' => $channel,
            'message' => $message,
            'reachable' => $reachable,
            'unreachable' => $unreachable,
            'sms_gateway' => $channel === 'sms' ? $configuration : null,
            'whatsapp' => $channel === 'whatsapp' ? $configuration : null,
            'template' => $this->templateInfo($channel, $configuration, $message),
            // The host only: the full address carries the account's key.
            'provider' => $channel === 'sms'
                ? ['name' => 'SMS gateway', 'host' => parse_url((string) ($configuration['url'] ?? ''), PHP_URL_HOST) ?: null]
                : ['name' => 'WhatsApp Cloud API', 'host' => 'graph.facebook.com'],
        ];
    }

    /**
     * The students to message, from this institute's own records, or null when none were named.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, array{student_id:int, name:string, first_name:string, class:string, mobile:string, standard_id:?int, division_id:?int}>|null
     */
    protected function students(McpRequestContext $context, array $arguments): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($arguments['student_ids'] ?? [])), static fn (int $id) => $id > 0)));
        $standardId = (int) ($arguments['standard_id'] ?? 0);
        $divisionId = (int) ($arguments['division_id'] ?? 0);

        if ($ids !== []) {
            $classes = $this->classLabels($context, $ids);

            return DB::table('tblstudent')
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'mobile', 'enrollment_no'])
                ->map(fn ($row) => $this->student(
                    (int) $row->id, $row->first_name, $row->middle_name, $row->last_name, $row->mobile,
                    $classes[(int) $row->id]['label'] ?? '', $classes[(int) $row->id]['standard_id'] ?? null, $classes[(int) $row->id]['division_id'] ?? null,
                    (string) ($row->enrollment_no ?? '')
                ))
                ->all();
        }

        if ($standardId <= 0) {
            return null;
        }

        // The same lookup Communication → Send SMS to Parents lists a class with.
        $rows = SearchStudent('', $standardId, $divisionId > 0 ? $divisionId : '', $context->selectedInstituteId, $context->academicYear ?? '');

        return array_map(fn ($row) => $this->student(
            (int) ($row['student_id'] ?? 0),
            $row['first_name'] ?? '',
            $row['middle_name'] ?? '',
            $row['last_name'] ?? '',
            $row['mobile'] ?? '',
            trim(($row['standard_name'] ?? '').' '.($row['division_name'] ?? '')),
            $standardId,
            $divisionId > 0 ? $divisionId : null,
            (string) ($row['enrollment_no'] ?? '')
        ), array_values(array_filter((array) $rows, static fn ($row) => (int) ($row['student_id'] ?? 0) > 0)));
    }

    /**
     * Each student's class and division this year, from the enrolment record.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{label:string, standard_id:?int, division_id:?int}>
     */
    protected function classLabels(McpRequestContext $context, array $ids): array
    {
        try {
            $rows = DB::table('tblstudent_enrollment as e')
                ->leftJoin('standard as s', fn ($j) => $j->on('s.id', '=', 'e.standard_id')->on('s.sub_institute_id', '=', 'e.sub_institute_id'))
                ->leftJoin('division as d', fn ($j) => $j->on('d.id', '=', 'e.section_id')->on('d.sub_institute_id', '=', 'e.sub_institute_id'))
                ->where('e.sub_institute_id', $context->selectedInstituteId)
                ->when($context->academicYear !== null, fn ($q) => $q->where('e.syear', $context->academicYear))
                ->whereIn('e.student_id', $ids)
                ->orderBy('e.id')
                ->get(['e.student_id', 'e.standard_id', 'e.section_id', 's.name as standard_name', 'd.name as division_name']);
        } catch (Throwable) {
            return [];
        }

        $labels = [];

        foreach ($rows as $row) {
            $labels[(int) $row->student_id] = [
                'label' => trim((string) $row->standard_name.' '.(string) $row->division_name),
                'standard_id' => $row->standard_id === null ? null : (int) $row->standard_id,
                'division_id' => $row->section_id === null ? null : (int) $row->section_id,
            ];
        }

        return $labels;
    }

    /** @return array{student_id:int, name:string, first_name:string, class:string, mobile:string, standard_id:?int, division_id:?int, enrollment_no:string} */
    private function student(int $id, mixed $first, mixed $middle, mixed $last, mixed $mobile, string $class, ?int $standardId, ?int $divisionId, string $enrollmentNo = ''): array
    {
        return [
            'student_id' => $id,
            'enrollment_no' => $enrollmentNo,
            'name' => trim(preg_replace('/\s+/', ' ', implode(' ', [(string) $first, (string) $middle, (string) $last])) ?? ''),
            'first_name' => trim((string) $first),
            'class' => $class,
            'mobile' => (string) $mobile,
            'standard_id' => $standardId,
            'division_id' => $divisionId,
        ];
    }

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


    // ------------------------------------------------------------------ delivery

    /**
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>  $gateway
     * @return array{status:string, detail:string, outage:bool, provider_id?:?string}
     */
    private function deliverSms(McpRequestContext $context, array $recipient, string $text, array $gateway): array
    {
        try {
            $reply = $this->sendSmsRequest((int) $context->selectedInstituteId, $recipient['mobile'], $text);
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'failed', 'detail' => 'The SMS request failed before it could be made ('.class_basename($exception).').', 'outage' => true];
        }

        $verdict = SmsGatewayReply::classify($reply, (string) ($gateway['url'] ?? ''));

        if ($verdict['status'] === 'sent') {
            $this->logLegacySms((int) $recipient['student_id'], $text, $recipient['mobile'], $context);
        }

        return ['status' => $verdict['status'], 'detail' => $verdict['detail'], 'outage' => $verdict['outage'], 'provider_id' => null];
    }


    /**
     * The Parent module's own sender. Overridable so tests need no network.
     *
     * @return array<string, mixed>
     */
    protected function sendSmsRequest(int $instituteId, string $mobile, string $text): array
    {
        return app(send_sms_parents_controller::class)->sendSMSDetailed($mobile, $text, $instituteId);
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
            // The provider accepted the message; a missing report line must not turn that into a failure.
            report($exception);
        }
    }


    // ------------------------------------------------------------------ history

    /**
     * Parent messages already sent or attempted from the assistant, newest first, read back from the saved record.
     *
     * Each row says what is actually known about delivery and no more:
     *   - SMS accepted by the gateway: "unknown" — the gateway returns no delivery receipt;
     *   - WhatsApp accepted: the status WhatsApp has reported since, read live from `whatsapp_sent_messages`
     *     (sent -> delivered -> read, or failed), and "pending" while no receipt has arrived;
     *   - refused by the provider: "not_delivered"; skipped: "not_sent".
     *
     * @param  array<string, mixed>  $arguments  `student_id`, `channel`, `status` (sent|failed|skipped), `batch`, `limit`.
     * @return array<string, mixed>
     */
    public function history(McpRequestContext $context, array $arguments): array
    {
        if (! Schema::hasTable('ai_audit_logs')) {
            return ToolResult::success('communication.parent_message_history', 'No parent message history is recorded in this estate.', ['count' => 0, 'messages' => []]);
        }

        $limit = min(max((int) ($arguments['limit'] ?? 50), 1), 200);
        $batch = ! empty($arguments['batch']) ? mb_strtolower(trim((string) $arguments['batch'])) : null;

        $query = DB::table('ai_audit_logs')
            ->where('event_type', self::EVENT)
            ->where('sub_institute_id', $context->selectedInstituteId);

        if (! empty($arguments['student_id'])) {
            $query->where('subject_id', (int) $arguments['student_id']);
        }

        $rows = $query->orderByDesc('id')->limit(500)->get(['id', 'subject_id', 'outcome', 'payload', 'created_at', 'actor_id']);

        // At most this many live delivery-report calls to the SMS gateway per status read.
        $this->deliveryReportBudget = 10;

        $messages = [];
        $totals = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $message = $this->messageFromRow($row, $context);

            if (($batch !== null && mb_strtolower((string) $message['batch']) !== $batch)
                || (! empty($arguments['channel']) && $arguments['channel'] !== $message['channel'])
                || (! empty($arguments['status']) && $arguments['status'] !== $message['status'])) {
                continue;
            }

            $totals[$message['status']] = ($totals[$message['status']] ?? 0) + 1;
            $messages[] = $message;

            if (count($messages) >= $limit) {
                break;
            }
        }

        $delivery = array_count_values(array_column($messages, 'delivery_status'));

        return ToolResult::success(
            'communication.parent_message_history',
            $messages === []
                ? 'No parent message has been sent from here yet.'
                : sprintf(
                    '%d parent message record%s: %d accepted by the provider, %d failed, %d skipped.%s',
                    count($messages),
                    count($messages) === 1 ? '' : 's',
                    $totals['sent'],
                    $totals['failed'],
                    $totals['skipped'],
                    ($delivery['delivered'] ?? 0) + ($delivery['read'] ?? 0) > 0
                        ? sprintf(' WhatsApp has confirmed %d as delivered or read.', ($delivery['delivered'] ?? 0) + ($delivery['read'] ?? 0))
                        : ''
                ),
            [
                'count' => count($messages),
                'accepted' => $totals['sent'],
                'failed' => $totals['failed'],
                'skipped' => $totals['skipped'],
                'delivery' => $delivery,
                'messages' => $messages,
                'rule' => '"Accepted" means the SMS gateway or WhatsApp took the message. An SMS gateway sends no delivery receipt, so delivery of an SMS is unknown. '
                    .'WhatsApp reports delivered and read later; those states are read from its own record, and "pending" means no receipt has arrived yet.',
            ]
        );
    }

    /**
     * One saved row, in this feature's words, with the live WhatsApp receipt where there is one.
     *
     * @return array<string, mixed>
     */
    protected function messageFromRow(object $row, ?McpRequestContext $context = null): array
    {
        $payload = json_decode((string) $row->payload, true);
        $payload = is_array($payload) ? $payload : [];
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        $stored = (string) ($payload['status'] ?? ($row->outcome === 'success' ? 'completed' : 'failed'));
        $status = $stored === 'completed' ? 'sent' : $stored;
        $channel = (string) ($result['channel'] ?? 'sms');
        $providerId = $result['provider_id'] ?? null;

        [$deliveryStatus, $deliveryNote] = match (true) {
            $status === 'failed' => ['not_delivered', 'The provider did not accept it, so it was not delivered.'],
            $status === 'skipped' => ['not_sent', 'It was never sent.'],
            // The gateway's own delivery report, read live: delivered, failed, still submitted, or unknown.
            $channel === 'sms' => $this->smsDeliveryOf($context, $providerId, (string) $row->created_at),
            default => $this->whatsappReceipt(is_string($providerId) ? $providerId : null, (string) $row->created_at),
        };

        return [
            'record_id' => (int) $row->id,
            'batch' => $payload['reference'] ?? null,
            'student_id' => $row->subject_id === null ? null : (int) $row->subject_id,
            'student_name' => $payload['subject_label'] ?? null,
            'channel' => $channel,
            'message' => $result['message'] ?? null,
            'status' => $status,
            'detail' => $result['detail'] ?? null,
            'sent_to' => $result['sent_to'] ?? null,
            'provider_id' => $providerId,
            'delivery_status' => $deliveryStatus,
            'delivery_note' => $deliveryNote,
            'recorded_at' => (string) $row->created_at,
            'recorded_by' => $row->actor_id === null ? null : (int) $row->actor_id,
        ];
    }



    // ------------------------------------------------------------------- ledger

    /**
     * @param  array<string, mixed>  $recipient
     * @return array<string, mixed>
     */
    private function record(McpRequestContext $context, array $recipient, string $channel, string $reference, string $text, string $status, string $detail, ?string $providerId = null): array
    {
        $this->audit->record(self::EVENT, $context, [
            'subject_entity_key' => 'student',
            'subject_id' => $recipient['student_id'],
            'outcome' => $status === 'sent' ? 'success' : ($status === 'skipped' ? 'rejected' : 'failure'),
            'message' => sprintf('Parent message to %s by %s: %s.', $recipient['name'], $channel, $status),
            'payload' => [
                'module' => 'easy_com',
                'operation' => 'parent_message',
                'operation_label' => 'Parent message',
                'capability' => 'conversational',
                'status' => $status === 'sent' ? 'completed' : $status,
                'subject_label' => $recipient['name'],
                'reference' => $reference,
                'used' => ['tool' => 'communication.send_parent_message'],
                'result' => [
                    'channel' => $channel,
                    'message_hash' => hash('sha256', $text),
                    // The wording that was sent, so a status read shows what the parent was told.
                    'message' => Str::limit($text, 500, '…'),
                    'detail' => $detail,
                    'provider_id' => $providerId,
                    'sent_to' => $this->mask((string) $recipient['mobile']),
                ],
            ],
        ]);

        return [
            'student_id' => $recipient['student_id'],
            'name' => $recipient['name'],
            'status' => $status,
            'detail' => $detail,
            'provider_id' => $providerId,
        ];
    }

    /** When this student was last sent this same message on this channel within the duplicate window, or null. */
    protected function recentlySentAt(McpRequestContext $context, int $studentId, string $channel, string $text): ?string
    {
        try {
            if (! Schema::hasTable('ai_audit_logs')) {
                return null;
            }

            $hash = hash('sha256', $text);

            $rows = DB::table('ai_audit_logs')
                ->where('event_type', self::EVENT)
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->where('subject_id', $studentId)
                ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
                ->orderByDesc('id')
                ->limit(10)
                ->get(['payload', 'outcome', 'created_at']);

            foreach ($rows as $row) {
                $payload = json_decode((string) $row->payload, true);
                $result = is_array($payload) ? ($payload['result'] ?? []) : [];

                if ($row->outcome === 'success' && ($result['channel'] ?? null) === $channel && ($result['message_hash'] ?? null) === $hash) {
                    return Carbon::parse($row->created_at)->format('H:i');
                }
            }
        } catch (Throwable $exception) {
            // A guard that cannot look must not stop a message the person approved; the single-use
            // approval still protects against the ordinary repeat.
            report($exception);
        }

        return null;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param  array<int, array<string, mixed>>  $reachable
     */
    private function fingerprint(array $reachable, string $channel, string $message, array $template = []): string
    {
        $pairs = array_map(static fn (array $r) => $r['student_id'].':'.$r['mobile'], $reachable);
        sort($pairs);

        // The template is part of what was approved: a gateway template changed in between is a different message.
        return hash('sha256', json_encode([$channel, $message, $pairs, $template]));
    }

    /** @param  array<string, mixed>  $recipient */
    private function personalise(string $message, array $recipient): string
    {
        $first = $recipient['first_name'] !== '' ? $recipient['first_name'] : $recipient['name'];

        return strtr($message, [
            '{name}' => $first,
            '{student}' => $first,
            '{student_name}' => $recipient['name'],
            '{class}' => (string) ($recipient['class'] ?? ''),
            '{enrollment_no}' => (string) ($recipient['enrollment_no'] ?? ''),
            '{gr_no}' => (string) ($recipient['enrollment_no'] ?? ''),
        ]);
    }

    /**
     * The message as the provider receives it. For SMS the institute's saved suffix is appended exactly
     * as the Parent module's sender appends it (`last_var`, after the message); a gateway parameter such
     * as `&templateid=…` is not text and is not added here. WhatsApp text is one line, because the Cloud
     * API template takes it as a single parameter and rejects line breaks in one.
     *
     * @param  array<string, mixed>  $template
     */
    private function finalText(string $channel, string $text, array $template): string
    {
        if ($channel === 'whatsapp') {
            return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        }

        return SmsGatewayTemplate::finalText($text, $template);
    }

    /**
     * What the institute has saved as the SMS "template", or the WhatsApp template the Cloud API will use.
     *
     * The Parent module keeps no list of SMS templates: the template is the gateway row's `last_var`,
     * which is either text appended to every message (a signature) or a parameter carrying the template
     * id the gateway has registered. Credentials in the row are masked and never shown.
     *
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function templateInfo(string $channel, array $configuration, string $message): array
    {
        if ($channel === 'whatsapp') {
            // The same rule the Communication screen uses to pick the template from the wording.
            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $message, $found);
            $variables = count(array_unique($found[1] ?? []));
            $name = $variables === 2 ? 'p2' : ($variables >= 3 ? 'p3' : 'parents_');

            return [
                'kind' => 'cloud_template',
                'name' => $name,
                'language' => 'en_US (if WhatsApp does not find it, en and then en_GB are tried)',
                'text' => null,
                'note' => sprintf('Sent as the approved WhatsApp template "%s". It must be approved for this WhatsApp Business account, or WhatsApp will reject the message.', $name),
            ];
        }

        return SmsGatewayTemplate::describe($configuration);
    }

    private function cleanMessage(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', strip_tags($value)) ?? '');

        return $clean === '' ? null : $clean;
    }

    /** @return array<int, string> */
    private function warnings(string $channel, string $message, array $template = []): array
    {
        $warnings = [];

        if ($channel === 'sms') {
            $message = $this->finalText('sms', $message, $template);
            $unicode = (bool) preg_match('/[^\x20-\x7E\n]/', $message);
            $length = mb_strlen($message);
            $parts = (int) ceil($length / ($unicode ? 70 : 160));

            if ($parts > 1) {
                $warnings[] = sprintf('This SMS is %d characters, so it goes as %d parts%s.', $length, $parts, $unicode ? ' (it contains non-English characters)' : '');
            }

            $warnings[] = ($template['kind'] ?? '') === 'gateway_parameter'
                ? 'This gateway only delivers messages whose wording matches its registered template.'
                : 'The SMS gateway may only deliver messages whose wording matches a template registered with it.';
        } else {
            $warnings[] = 'WhatsApp business messages go out as the institute\'s approved template; the wording is fitted into it. A recipient who has not opted in, or a template that is not approved, will be rejected by WhatsApp.';
        }

        return $warnings;
    }

    private function label(string $channel): string
    {
        return $channel === 'sms' ? 'SMS' : 'WhatsApp';
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

    private function mask(string $mobile): string
    {
        return '******'.substr($mobile, -4);
    }

    /** @return array{success:false, failure:array<string, mixed>} */
    private function refuse(string $message, string $code): array
    {
        return ['success' => false, 'failure' => ToolResult::failure('communication.send_parent_message', $message, $code)];
    }
}
