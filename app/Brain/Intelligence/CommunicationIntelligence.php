<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Communication Intelligence — one institute, one academic year.
 *
 * READS REAL COMMUNICATION DATA FROM vivek_erp.
 *
 * Authoritative sources:
 * - `parent_communication`: Two-way parent inquiries and replies
 * - `sms_sent_parents`: Outbound SMS notification dispatches
 * - `whatsapp_sent_messages`: Outbound WhatsApp messaging dispatches
 */
final class CommunicationIntelligence
{
    private const PARENT_COMM_TABLE = 'parent_communication';
    private const SMS_TABLE = 'sms_sent_parents';
    private const WHATSAPP_TABLE = 'whatsapp_sent_messages';

    private readonly string $tenantId;
    private readonly ?string $syear;

    /** Memoized calculations per request. */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    private function computeCoverage(): array
    {
        if ($this->syear === null) {
            return [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute.',
                'sourceTable' => self::PARENT_COMM_TABLE,
                'totalRows' => 0,
                'usableRows' => 0,
            ];
        }

        $commCount = 0;
        if (SchemaCache::hasTable(self::PARENT_COMM_TABLE)) {
            $commCount = DB::table(self::PARENT_COMM_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->count();
        }

        $smsCount = 0;
        if (SchemaCache::hasTable(self::SMS_TABLE)) {
            $smsCount = DB::table(self::SMS_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('SYEAR', $this->syear)
                ->count();
        }

        $waCount = 0;
        if (SchemaCache::hasTable(self::WHATSAPP_TABLE)) {
            $waCount = DB::table(self::WHATSAPP_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->count();
        }

        $totalRows = $commCount + $smsCount + $waCount;

        if ($totalRows === 0) {
            return [
                'available' => false,
                'reason' => "No parent communication, SMS, or messaging records found for academic year {$this->syear}.",
                'sourceTable' => self::PARENT_COMM_TABLE,
                'totalRows' => 0,
                'usableRows' => 0,
            ];
        }

        return [
            'available' => true,
            'sourceTable' => self::PARENT_COMM_TABLE . ' + ' . self::SMS_TABLE,
            'totalRows' => $totalRows,
            'usableRows' => $totalRows,
            'period' => "Academic Year {$this->syear}",
        ];
    }

    public function position(): array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    private function computePosition(): array
    {
        $cov = $this->coverage();
        if (! $cov['available']) {
            return [
                'metrics' => [
                    'totalCommunications' => null,
                    'totalInquiries' => null,
                    'repliedInquiries' => null,
                    'unrepliedInquiries' => null,
                    'responseRate' => null,
                    'medianReplyHours' => null,
                    'slowestDecileHours' => null,
                    'totalSmsSent' => null,
                    'totalWhatsAppSent' => null,
                ],
                'summary' => $cov['reason'] ?? 'No communication data available.',
            ];
        }

        // COUNTED IN SQL, NOT IN PHP. The previous version pulled every inquiry
        // row into memory to increment two counters — 4,873 rows at the largest
        // institute, for two numbers a GROUP BY already knows.
        $inquiries = null;
        $replied = null;
        $unreplied = null;
        $replyHours = [];

        if (SchemaCache::hasTable(self::PARENT_COMM_TABLE)) {
            $row = DB::table(self::PARENT_COMM_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->selectRaw(
                    'COUNT(*) as total,
                     SUM(CASE WHEN TRIM(COALESCE(reply, "")) <> "" THEN 1 ELSE 0 END) as replied'
                )
                ->first();

            $inquiries = (int) ($row->total ?? 0);
            $replied = (int) ($row->replied ?? 0);
            $unreplied = $inquiries - $replied;

            // Turnaround is only measurable where BOTH timestamps exist, so the
            // median below is over that subset and the screen says so.
            $replyHours = DB::table(self::PARENT_COMM_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->whereNotNull('reply_on')
                ->whereNotNull('created_at')
                ->whereRaw('reply_on >= created_at')
                ->orderByRaw('TIMESTAMPDIFF(HOUR, created_at, reply_on)')
                ->pluck(DB::raw('TIMESTAMPDIFF(HOUR, created_at, reply_on) as hours'))
                ->map(static fn ($h) => (int) $h)
                ->all();
        }

        $totalSms = SchemaCache::hasTable(self::SMS_TABLE)
            ? (int) DB::table(self::SMS_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('SYEAR', $this->syear)
                ->count()
            : null;

        $totalWhatsApp = SchemaCache::hasTable(self::WHATSAPP_TABLE)
            ? (int) DB::table(self::WHATSAPP_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->count()
            : null;

        $totalComms = ($inquiries ?? 0) + ($totalSms ?? 0) + ($totalWhatsApp ?? 0);

        return [
            'metrics' => [
                'totalCommunications' => $totalComms,
                'totalInquiries' => $inquiries,
                'repliedInquiries' => $replied,
                'unrepliedInquiries' => $unreplied,
                // A response rate over no inquiries is undefined, not nought.
                'responseRate' => ($inquiries ?? 0) > 0 ? round($replied / $inquiries * 100, 1) : null,
                'repliesTimed' => count($replyHours),
                'medianReplyHours' => $replyHours === []
                    ? null
                    : $replyHours[(int) floor(count($replyHours) / 2)],
                // The slowest tenth is what a parent waiting actually experiences;
                // a median hides it completely.
                'slowestDecileHours' => $replyHours === []
                    ? null
                    : $replyHours[(int) floor(count($replyHours) * 0.9)] ?? $replyHours[count($replyHours) - 1],
                'totalSmsSent' => $totalSms,
                'totalWhatsAppSent' => $totalWhatsApp,
            ],
            'summary' => null,
        ];
    }

    public function breakdowns(): array
    {
        return $this->memo['breakdowns'] ??= $this->computeBreakdowns();
    }

    private function computeBreakdowns(): array
    {
        $cov = $this->coverage();
        if (!$cov['available']) {
            return [];
        }

        $breakdowns = [];

        // 1. By Channel
        $pos = $this->position()['metrics'];
        $channelItems = [];
        if ($pos['totalInquiries'] > 0) {
            $channelItems[] = [
                'name' => 'Parent Portal Inquiries',
                'value' => $pos['totalInquiries'],
                'percentage' => round(($pos['totalInquiries'] / $pos['totalCommunications']) * 100, 1),
                'status' => 'active',
            ];
        }
        if ($pos['totalSmsSent'] > 0) {
            $channelItems[] = [
                'name' => 'SMS Outbound Dispatches',
                'value' => $pos['totalSmsSent'],
                'percentage' => round(($pos['totalSmsSent'] / $pos['totalCommunications']) * 100, 1),
                'status' => 'active',
            ];
        }
        if ($pos['totalWhatsAppSent'] > 0) {
            $channelItems[] = [
                'name' => 'WhatsApp Messages',
                'value' => $pos['totalWhatsAppSent'],
                'percentage' => round(($pos['totalWhatsAppSent'] / $pos['totalCommunications']) * 100, 1),
                'status' => 'active',
            ];
        }
        if (!empty($channelItems)) {
            $breakdowns[] = [
                'key' => 'byChannel',
                'title' => 'Communications by Delivery Channel',
                'description' => 'Multi-channel message distribution across parent portal, SMS, and WhatsApp.',
                'items' => $channelItems,
            ];
        }

        // 2. By class
        //
        // NOT BY `title`. That column is a free-text subject line a PARENT
        // writes: 2,495 distinct values across 4,873 rows at the largest
        // institute, up to 209 characters, and the values include a named
        // child's illness. Grouping it put correspondence about an identified
        // pupil onto a screen any office-holder can open. The class the child
        // is in answers the same operational question — where are messages not
        // being answered — without naming anybody.
        if (SchemaCache::hasTable(self::PARENT_COMM_TABLE) && ($pos['totalInquiries'] ?? 0) > 0) {
            $classes = DB::table(self::PARENT_COMM_TABLE.' as pc')
                ->join('tblstudent_enrollment as e', function ($join) {
                    $join->on('e.student_id', '=', 'pc.student_id')
                        ->on('e.sub_institute_id', '=', 'pc.sub_institute_id')
                        ->on('e.syear', '=', 'pc.syear');
                })
                ->leftJoin('standard as st', 'st.id', '=', 'e.standard_id')
                ->where('pc.sub_institute_id', $this->tenantId)
                ->where('pc.syear', $this->syear)
                ->groupBy('e.standard_id', 'st.name')
                ->select(
                    DB::raw('COALESCE(NULLIF(st.name, ""), CONCAT("Standard #", e.standard_id)) as class_name'),
                    DB::raw('COUNT(*) as total_count'),
                    DB::raw('SUM(CASE WHEN TRIM(COALESCE(pc.reply, "")) <> "" THEN 1 ELSE 0 END) as replied_count')
                )
                ->orderByDesc('total_count')
                ->get();

            $classItems = [];
            foreach ($classes as $c) {
                $tot = (int) $c->total_count;
                $rep = (int) $c->replied_count;
                $rate = $tot > 0 ? round($rep / $tot * 100, 1) : null;
                $classItems[] = [
                    'name' => (string) $c->class_name,
                    'value' => $tot,
                    'percentage' => round($tot / $pos['totalInquiries'] * 100, 1),
                    'status' => $rate !== null && $rate < 80 ? 'attention' : 'normal',
                    'detail' => $rate === null
                        ? 'No reply rate — no inquiries'
                        : "{$rep} of {$tot} answered ({$rate}%)",
                ];
            }

            if ($classItems !== []) {
                $breakdowns[] = [
                    'key' => 'byClass',
                    'title' => 'Parent inquiries by class',
                    'description' => 'Where the messages come from and how often they are answered. Parent messages '
                        .'are answered by the class teacher, so this is the slice that separates a routing problem '
                        .'from a volume one.',
                    'items' => $classItems,
                ];
            }

            // 2b. By month — inquiries carry a date, so unlike most modules here
            // this one can show direction as well as position.
            $months = DB::table(self::PARENT_COMM_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->whereNotNull('date_')
                ->selectRaw(
                    'DATE_FORMAT(date_, "%Y-%m") as month_key,
                     COUNT(*) as total_count,
                     SUM(CASE WHEN TRIM(COALESCE(reply, "")) <> "" THEN 1 ELSE 0 END) as replied_count'
                )
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get();

            $monthItems = [];
            foreach ($months as $m) {
                $tot = (int) $m->total_count;
                $rep = (int) $m->replied_count;
                $rate = $tot > 0 ? round($rep / $tot * 100, 1) : null;
                $monthItems[] = [
                    'name' => (string) $m->month_key,
                    'value' => $tot,
                    'percentage' => round($tot / $pos['totalInquiries'] * 100, 1),
                    'status' => $rate !== null && $rate < 80 ? 'attention' : 'normal',
                    'detail' => $rate === null ? null : "{$rep} of {$tot} answered ({$rate}%)",
                ];
            }

            if (count($monthItems) > 1) {
                $breakdowns[] = [
                    'key' => 'byMonth',
                    'title' => 'Parent inquiries by month',
                    'description' => 'Volume and reply rate through the year. The most recent month is always partly '
                        .'unanswered because it is recent; read the shape rather than the last bar.',
                    'items' => $monthItems,
                ];
            }
        }

        // 3. By SMS Module Dispatcher
        if (SchemaCache::hasTable(self::SMS_TABLE) && $pos['totalSmsSent'] > 0) {
            $smsModules = DB::table(self::SMS_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('SYEAR', $this->syear)
                ->select(
                    DB::raw("CASE WHEN MODULE_NAME IS NULL OR TRIM(MODULE_NAME) = '' THEN 'Direct / General' ELSE TRIM(MODULE_NAME) END as mod_name"),
                    DB::raw('COUNT(*) as total_count')
                )
                ->groupBy('mod_name')
                ->orderByDesc('total_count')
                ->limit(6)
                ->get();

            $smsItems = [];
            foreach ($smsModules as $sm) {
                $cnt = (int) $sm->total_count;
                $smsItems[] = [
                    'name' => (string) $sm->mod_name,
                    'value' => $cnt,
                    'percentage' => round(($cnt / $pos['totalSmsSent']) * 100, 1),
                    'status' => 'normal',
                ];
            }
            if (!empty($smsItems)) {
                $breakdowns[] = [
                    'key' => 'bySmsModule',
                    'title' => 'SMS Notification Dispatch by Module',
                    'description' => 'System notifications triggered across ERP functional domains.',
                    'items' => $smsItems,
                ];
            }
        }

        return $breakdowns;
    }

    public function findings(): array
    {
        return CommunicationSignalRules::run($this);
    }

    public function priorities(): array
    {
        $cov = $this->coverage();
        if (!$cov['available']) {
            return [];
        }

        $priorities = [];
        $pos = $this->position()['metrics'];

        if ($pos['unrepliedInquiries'] > 0) {
            $priorities[] = [
                'id' => 'unreplied-inquiries-queue',
                'title' => 'Address Unanswered Parent Inquiries',
                'description' => "{$pos['unrepliedInquiries']} parent inquiries remain unanswered. Prioritize oldest open threads to maintain school-parent relationship trust.",
                'urgency' => $pos['unrepliedInquiries'] > 50 ? 'immediate' : 'short-term',
                'actionType' => 'inquiry_resolution',
                'affectedCount' => $pos['unrepliedInquiries'],
            ];
        }

        if ($pos['totalCommunications'] > 0 && $pos['responseRate'] < 80.0 && $pos['totalInquiries'] > 10) {
            $priorities[] = [
                'id' => 'response-sla-workflow',
                'title' => 'Establish 24-Hour Parent SLA',
                'description' => "Response rate is currently {$pos['responseRate']}%. Establish institutional SLA guidelines for class teachers and administrative coordinators.",
                'urgency' => 'medium-term',
                'actionType' => 'policy_review',
                'affectedCount' => $pos['totalInquiries'],
            ];
        }

        return $priorities;
    }

    public function dataQuality(): array
    {
        $cov = $this->coverage();
        if (! $cov['available']) {
            return ['available' => false, 'reason' => $cov['reason'], 'checks' => []];
        }

        $metrics = $this->position()['metrics'];
        $checks = [];

        if (SchemaCache::hasTable(self::PARENT_COMM_TABLE)) {
            $inquiries = (int) ($metrics['totalInquiries'] ?? 0);

            $unlinked = (int) DB::table(self::PARENT_COMM_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->where(fn ($q) => $q->whereNull('student_id')->orWhere('student_id', '<=', 0))
                ->count();

            $untimed = $inquiries > 0
                ? (int) DB::table(self::PARENT_COMM_TABLE)
                    ->where('sub_institute_id', $this->tenantId)
                    ->where('syear', $this->syear)
                    ->whereRaw('TRIM(COALESCE(reply, "")) <> ""')
                    ->whereNull('reply_on')
                    ->count()
                : 0;

            $checks[] = [
                'key' => 'inquiry_student_link',
                'label' => 'Inquiries not linked to a student',
                'value' => $unlinked,
                'format' => 'count',
                'sharePercent' => $inquiries > 0 ? round($unlinked / $inquiries * 100, 2) : null,
                'shareLabel' => 'of inquiries',
                'state' => $unlinked > 0 ? 'attention' : 'ok',
                'note' => $unlinked > 0
                    ? 'These messages cannot be traced to a child, so they appear in the totals and in no class, '
                        .'cohort or family record.'
                    : 'Every inquiry is linked to a student record.',
            ];

            $checks[] = [
                'key' => 'replies_without_timestamp',
                'label' => 'Replies with no reply time',
                'value' => $untimed,
                'format' => 'count',
                'sharePercent' => $inquiries > 0 ? round($untimed / $inquiries * 100, 2) : null,
                'shareLabel' => 'of inquiries',
                'state' => $untimed > 0 ? 'attention' : 'ok',
                'note' => $untimed > 0
                    ? 'A reply was written but not timestamped, so it is counted as answered and is excluded from '
                        .'every turnaround figure on this screen.'
                    : 'Every reply carries the time it was sent.',
            ];
        }

        if (SchemaCache::hasTable(self::SMS_TABLE)) {
            $sms = (int) ($metrics['totalSmsSent'] ?? 0);

            $badNumber = (int) DB::table(self::SMS_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('SYEAR', $this->syear)
                ->where(fn ($q) => $q->whereNull('SMS_NO')
                    ->orWhere('SMS_NO', '')
                    ->orWhereRaw('LENGTH(TRIM(SMS_NO)) < 10'))
                ->count();

            $checks[] = [
                'key' => 'sms_recipient_number',
                'label' => 'SMS with no usable number',
                'value' => $badNumber,
                'format' => 'count',
                'sharePercent' => $sms > 0 ? round($badNumber / $sms * 100, 2) : null,
                'shareLabel' => 'of messages sent',
                'state' => $badNumber > 0 ? 'attention' : 'ok',
                'note' => $badNumber > 0
                    ? 'The recipient number is missing or too short to dial. These messages are recorded as sent and '
                        .'cannot have arrived.'
                    : 'Every SMS carries a recipient number of usable length.',
            ];
        }

        return ['available' => $checks !== [], 'reason' => null, 'checks' => $checks];
    }

    public function toPayload(): array
    {
        return [
            'coverage' => $this->coverage(),
            'position' => $this->position(),
            'breakdowns' => $this->breakdowns(),
            'findings' => $this->findings(),
            'priorities' => $this->priorities(),
            'dataQuality' => $this->dataQuality(),
            'recommendations' => [],
            'decisions' => [],
            'learning' => [],
        ];
    }
}

