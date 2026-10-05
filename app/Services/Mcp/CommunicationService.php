<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the school has sent, across every channel it sends on.
 *
 * FOUR TABLES, ONE REGISTER
 *
 * `sms_sent_parents`, `sms_sent_staff`, `whatsapp_sent_messages` and `app_notification`
 * are four separate logs with four different column spellings, and a person asking "what
 * did we send last week" means all of them. So this service normalises them into one
 * shape and always reports which channel each row came from.
 *
 * WHAT NORMALISING MUST NOT HIDE
 *
 * The four tables do not record the same things, and the differences are load-bearing:
 *
 *   · Only `whatsapp_sent_messages` records a delivery outcome (`message_status`,
 *     `message_error`). The other three record that a row was written, which is not the
 *     same as a message arriving. So `delivery_status` is null for them, and that null is
 *     reported rather than filled in with "sent".
 *   · Only the parent SMS and app notification logs carry a student. Staff SMS carries a
 *     staff id, and neither is forced into the other's column.
 *   · Only the parent SMS log carries `MODULE_NAME`, which is the module that triggered
 *     the send — a fee reminder, an attendance alert. It is reported as `triggered_by`
 *     where present and null elsewhere.
 *
 * A single "messages sent" number that quietly averaged those four would be the most
 * misleading figure this module could produce, so every count is reported per channel as
 * well as in total.
 *
 * NO MESSAGE IS SENT, RESENT OR CANCELLED HERE. Every method reads.
 *
 * SCOPING
 *
 * `sub_institute_id` on all four tables from the caller's token, and `syear` on the three
 * that carry it. `whatsapp_sent_messages` carries both.
 */
class CommunicationService
{
    /**
     * The channels, and how each one's log is shaped.
     *
     * Declared once so `messages()` and `channels()` cannot disagree about which table a
     * channel means or which column holds its date.
     *
     * @var array<string, array<string, string|null>>
     */
    private const CHANNELS = [
        'sms_parent' => [
            'table' => 'sms_sent_parents',
            'label' => 'SMS to parents',
            'id' => 'ID',
            'body' => 'SMS_TEXT',
            'to' => 'SMS_NO',
            'sent_at' => 'CREATED_ON',
            'student' => 'STUDENT_ID',
            'staff' => null,
            'triggered_by' => 'MODULE_NAME',
            'status' => null,
            'error' => null,
            'year' => 'SYEAR',
            'institute' => 'sub_institute_id',
        ],
        'sms_staff' => [
            'table' => 'sms_sent_staff',
            'label' => 'SMS to staff',
            'id' => 'id',
            'body' => 'sms_text',
            'to' => 'sms_no',
            'sent_at' => 'created_on',
            'student' => null,
            'staff' => 'staff_id',
            'triggered_by' => 'module_name',
            'status' => null,
            'error' => null,
            'year' => 'syear',
            'institute' => 'sub_institute_id',
        ],
        'whatsapp' => [
            'table' => 'whatsapp_sent_messages',
            'label' => 'WhatsApp to parents',
            'id' => 'id',
            'body' => 'message',
            'to' => 'whatsapp_number',
            'sent_at' => 'sent_date',
            'student' => 'student_id',
            'staff' => null,
            'triggered_by' => null,
            // The only channel that records what happened after the send.
            'status' => 'message_status',
            'error' => 'message_error',
            'year' => 'syear',
            'institute' => 'sub_institute_id',
        ],
        'app_notification' => [
            'table' => 'app_notification',
            'label' => 'App notification',
            'id' => 'ID',
            'body' => 'NOTIFICATION_DESCRIPTION',
            'to' => null,
            'sent_at' => 'NOTIFICATION_DATE',
            'student' => 'STUDENT_ID',
            'staff' => null,
            'triggered_by' => 'NOTIFICATION_TYPE',
            'status' => 'STATUS',
            'error' => null,
            'year' => 'SYEAR',
            'institute' => 'SUB_INSTITUTE_ID',
        ],
    ];

    /**
     * Messages sent, newest first, across the channels asked for.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function messages(McpRequestContext $context, array $filters): array
    {
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $wanted = $this->wantedChannels($filters['channel'] ?? null);

        $messages = [];
        $perChannel = [];
        $total = 0;
        $unavailable = [];

        foreach ($wanted as $key) {
            $spec = self::CHANNELS[$key];

            if (! Schema::hasTable($spec['table'])) {
                $unavailable[] = $key;

                continue;
            }

            $query = $this->channelQuery($context, $spec, $filters);

            // Counted per channel before the limit. The per-channel figure is the honest
            // one; the total below is a sum of figures that mean slightly different
            // things, and is labelled as such.
            $count = (clone $query)->count();
            $perChannel[$key] = ['label' => $spec['label'], 'count' => $count];
            $total += $count;

            // Each channel contributes at most the full limit, and the merged list is cut
            // back to it after sorting — so a busy channel cannot crowd out a quiet one
            // before the sort has happened.
            foreach ($query->orderByDesc($spec['sent_at'])->limit($limit)->get() as $row) {
                $messages[] = $this->map($key, $spec, $row);
            }
        }

        usort($messages, static fn (array $a, array $b) => ($b['sent_at'] ?? '') <=> ($a['sent_at'] ?? ''));
        $messages = array_slice($messages, 0, $limit);

        return [
            'count' => $total,
            'row_count' => count($messages),
            'academic_year' => $context->academicYear,
            'by_channel' => $perChannel,
            'channels_unavailable' => $unavailable,
            'messages' => $messages,
            'rule' => 'Only WhatsApp records a delivery outcome. For every other channel `delivery_status` '
                .'is null, which means the send was logged — not that the message arrived. `count` is the '
                .'sum of per-channel totals and is reported beside them, never instead of them.',
        ];
    }

    /**
     * Every channel, whether its log exists, and how much is in it.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function channels(McpRequestContext $context, array $filters): array
    {
        $channels = [];

        foreach (self::CHANNELS as $key => $spec) {
            if (! Schema::hasTable($spec['table'])) {
                $channels[] = [
                    'channel' => $key,
                    'label' => $spec['label'],
                    'available' => false,
                    'reason' => 'This estate has no '.$spec['table'].' table.',
                ];

                continue;
            }

            $query = $this->channelQuery($context, $spec, $filters);

            $channels[] = [
                'channel' => $key,
                'label' => $spec['label'],
                'available' => true,
                'messages' => (clone $query)->count(),
                'first_sent' => (clone $query)->min($spec['sent_at']),
                'last_sent' => (clone $query)->max($spec['sent_at']),
                'records_delivery_outcome' => $spec['status'] !== null,
                'addressed_to' => $spec['student'] !== null ? 'student' : ($spec['staff'] !== null ? 'staff' : 'unspecified'),
            ];
        }

        return [
            'count' => count($channels),
            'academic_year' => $context->academicYear,
            'channels' => $channels,
            'rule' => 'A channel with `records_delivery_outcome` false logs that a message was submitted '
                .'and nothing about whether it was received.',
        ];
    }

    /**
     * The channels a request asked for, or all of them.
     *
     * @return array<int, string>
     */
    private function wantedChannels(mixed $value): array
    {
        $given = strtolower(trim((string) ($value ?? '')));

        return isset(self::CHANNELS[$given]) ? [$given] : array_keys(self::CHANNELS);
    }

    /**
     * One channel's tenant-scoped query with the shared filters applied.
     *
     * @param  array<string, string|null>  $spec
     * @param  array<string, mixed>  $filters
     */
    private function channelQuery(McpRequestContext $context, array $spec, array $filters): \Illuminate\Database\Query\Builder
    {
        $query = DB::table($spec['table'])->where($spec['institute'], $context->selectedInstituteId);

        if ($context->academicYear !== null && $spec['year'] !== null) {
            $query->where($spec['year'], $context->academicYear);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate($spec['sent_at'], '>=', (string) $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate($spec['sent_at'], '<=', (string) $filters['to_date']);
        }

        // Applied only where the channel has the column. A student filter on the staff SMS
        // log would silently return nothing rather than saying it does not apply.
        if (! empty($filters['student_id']) && $spec['student'] !== null) {
            $query->where($spec['student'], (int) $filters['student_id']);
        }

        if (! empty($filters['search_text'])) {
            $query->where($spec['body'], 'like', '%'.$filters['search_text'].'%');
        }

        return $query;
    }

    /**
     * One row of any channel, in the shared shape.
     *
     * @param  array<string, string|null>  $spec
     * @return array<string, mixed>
     */
    private function map(string $channel, array $spec, object $row): array
    {
        $value = static fn (?string $column) => $column === null ? null : ($row->{$column} ?? null);

        $body = (string) ($value($spec['body']) ?? '');

        return [
            'channel' => $channel,
            'channel_label' => $spec['label'],
            'message_id' => (int) ($value($spec['id']) ?? 0),
            // Trimmed to a preview. A register of fifty messages does not need fifty full
            // bodies, and a model summarising one does not need them either.
            'message' => mb_substr($body, 0, 240),
            'message_truncated' => mb_strlen($body) > 240,
            'sent_to' => $value($spec['to']),
            'sent_at' => $value($spec['sent_at']),
            'student_id' => $spec['student'] === null ? null : (int) ($value($spec['student']) ?? 0),
            'staff_id' => $spec['staff'] === null ? null : (int) ($value($spec['staff']) ?? 0),
            'triggered_by' => $value($spec['triggered_by']),
            // Null where the channel records no outcome — see the note at the top.
            'delivery_status' => $value($spec['status']),
            'delivery_error' => $value($spec['error']),
            'records_delivery_outcome' => $spec['status'] !== null,
        ];
    }
}
