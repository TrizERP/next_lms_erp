<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * What the communication figures mean, and what is worth someone's morning.
 *
 * ── TWO THINGS THIS FILE WAS REWRITTEN TO STOP DOING ────────────────────────
 *
 * 1. IT PUT PARENTS' MESSAGES IN THE PAYLOAD. The unanswered-inquiry rule
 *    attached ten sample rows as its evidence, each carrying a student id, the
 *    inquiry title and the first eighty characters of what the parent had
 *    written. That is correspondence about a named child, travelling to every
 *    screen that can open this module. Evidence here is now AGGREGATE ONLY —
 *    counts, rates and turnaround — and no message text leaves the database.
 *
 * 2. IT RAISED "ACTIVE SMS NOTIFICATION DISPATCH (5687 MESSAGES SENT)". That
 *    was the only finding the module produced at the largest institute, and its
 *    entire content was that the SMS feature is switched on. A finding must
 *    detect a risk, an anomaly, a gap, a trend or an opportunity; "this module
 *    is in use" is none of those. It is gone, and what replaced it asks the
 *    question the same rows can actually answer — what those messages were FOR.
 */
final class CommunicationSignalRules
{
    private const PARENT_COMM_TABLE = 'parent_communication';

    private const SMS_TABLE = 'sms_sent_parents';

    /** Rows named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    /** Below this many inquiries, a class's reply rate is about the individuals. */
    private const MIN_CLASS_COHORT = 40;

    /** A reply rate below this is worth naming. */
    private const RESPONSE_ALERT = 90.0;

    /** Hours a parent waits before the turnaround is worth naming. */
    private const SLOW_REPLY_HOURS = 48;

    /** Share of outbound messages from one module before the mix is the story. */
    private const CHANNEL_DOMINANCE = 70.0;

    public function __construct(
        private readonly CommunicationIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'unanswered_inquiries' => ['Parent inquiries with no reply', fn () => $this->unansweredInquiries()],
            'slow_replies' => ['How long a parent waits for a reply', fn () => $this->slowReplies()],
            'class_reply_gap' => ['Classes whose families are answered less often', fn () => $this->classReplyGap()],
            'reply_rate_drift' => ['Reply rate moving through the year', fn () => $this->replyRateDrift()],
            'outbound_mix' => ['What the outbound messages are for', fn () => $this->outboundMix()],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => true, 'raised' => $raised !== []];
            foreach ($raised as $finding) {
                $findings[] = $finding;
            }
        }

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, function ($a, $b) use ($rank) {
            $bySeverity = ($rank[$a['severity']] ?? 5) <=> ($rank[$b['severity']] ?? 5);

            return $bySeverity !== 0
                ? $bySeverity
                : ($b['affected']['count'] ?? 0) <=> ($a['affected']['count'] ?? 0);
        });

        return ['findings' => $findings, 'ruleStatus' => $ruleStatus];
    }

    /* ------------------------------------------------------------- replies */

    /** @return array<int,array<string,mixed>> */
    private function unansweredInquiries(): array
    {
        $metrics = $this->analytics->position()['metrics'];
        $total = (int) ($metrics['totalInquiries'] ?? 0);
        $unreplied = (int) ($metrics['unrepliedInquiries'] ?? 0);

        if ($total === 0 || $unreplied === 0) {
            return [];
        }

        $rate = $metrics['responseRate'];
        if ($rate !== null && $rate >= self::RESPONSE_ALERT) {
            return [];
        }

        $share = round($unreplied / $total * 100, 1);

        return [[
            'id' => "communication-unanswered-{$this->syear}",
            'rule' => 'unanswered_inquiries',
            'severity' => $share >= 25.0 ? 'high' : 'medium',
            'severityLabel' => $share >= 25.0 ? 'High' : 'Medium',
            'title' => "{$unreplied} parent inquiries have had no reply",
            'whatHappened' => $this->sentence([
                "{$unreplied} of {$total} parent inquiries this year ({$share}%) carry no reply, against a response "
                    ."rate of {$rate}%.",
                $metrics['medianReplyHours'] !== null
                    ? "The inquiries that were answered took a median of {$metrics['medianReplyHours']} hours."
                    : null,
            ]),
            'whyItMatters' => 'A parent who writes through the school’s own portal and receives nothing learns that '
                .'the portal is not the way to reach the school. The next message comes by telephone to whoever '
                .'answers, and the record of it stops existing.',
            // AGGREGATE ONLY. No student id, no title, no message text.
            'evidence' => $this->classEvidence(),
            'likelyCause' => 'Inquiries arriving to a queue nobody owns, or replies given by telephone and never '
                .'written back. The records show the absence of a written reply, not the absence of an answer.',
            'causeConfirmed' => false,
            'recommendation' => 'Check whether the unanswered inquiries concentrate in particular classes — a class '
                .'whose teacher does not use the portal is a different problem from a general backlog, and the '
                .'class table below separates them.',
            'owner' => 'Front office',
            'priority' => $share >= 25.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $unreplied, 'total' => $total, 'unit' => 'inquiries'],
            'impact' => ['value' => $unreplied, 'display' => (string) $unreplied, 'label' => 'families awaiting a reply'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function slowReplies(): array
    {
        $metrics = $this->analytics->position()['metrics'];
        $median = $metrics['medianReplyHours'] ?? null;
        $slowest = $metrics['slowestDecileHours'] ?? null;

        if ($median === null || $median < self::SLOW_REPLY_HOURS) {
            return [];
        }

        $timed = (int) ($metrics['repliesTimed'] ?? 0);
        $replied = (int) ($metrics['repliedInquiries'] ?? 0);

        return [[
            'id' => "communication-slow-replies-{$this->syear}",
            'rule' => 'slow_replies',
            'severity' => $median >= 96 ? 'medium' : 'low',
            'severityLabel' => $median >= 96 ? 'Medium' : 'Low',
            'title' => "Half of all replies take more than {$median} hours",
            'whatHappened' => $this->sentence([
                "Across {$timed} replies with both timestamps, the median parent waited {$median} hours.",
                $slowest !== null
                    ? "The slowest tenth waited {$slowest} hours or more."
                    : null,
                $timed < $replied
                    ? ($replied - $timed).' further replies carry no reply time and are not counted here.'
                    : null,
            ]),
            'whyItMatters' => 'A median measured in days means the reply arrives after the thing it was about. Most '
                .'parent inquiries are time-bound — an absence, a transport change, a fee date — and an answer that '
                .'is correct but late is the same as no answer.',
            'evidence' => array_values(array_filter([
                ['label' => 'Median wait', 'value' => "{$median} hours"],
                $slowest !== null ? ['label' => 'Slowest tenth', 'value' => "{$slowest} hours or more"] : null,
                ['label' => 'Replies timed', 'value' => (string) $timed],
                ['label' => 'Replies in total', 'value' => (string) $replied],
                ['label' => 'Inquiries', 'value' => (string) ($metrics['totalInquiries'] ?? 0)],
            ])),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Read the slowest tenth rather than the median — the median is what most families '
                .'get, and the tail is who complains.',
            'owner' => 'Front office',
            'priority' => $median >= 96 ? 'medium' : 'low',
            'confidence' => $timed >= 100
                ? ['band' => 'High', 'value' => 0.9]
                : ['band' => 'Medium', 'value' => 0.65],
            'affected' => ['count' => $timed, 'total' => $replied, 'unit' => 'replies'],
            'impact' => ['value' => $median, 'display' => "{$median} hours", 'label' => 'median wait'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function classReplyGap(): array
    {
        $classes = $this->classStats();
        $metrics = $this->analytics->position()['metrics'];
        $baseline = $metrics['responseRate'] ?? null;

        if ($classes === [] || $baseline === null) {
            return [];
        }

        $weak = array_values(array_filter(
            $classes,
            static fn ($c) => $c['inquiries'] >= self::MIN_CLASS_COHORT
                && $c['replyRate'] !== null
                && $baseline - $c['replyRate'] >= 10.0,
        ));

        if ($weak === []) {
            return [];
        }

        // Ordered by how many families are waiting, never by the size of the gap.
        usort($weak, static fn ($a, $b) => ($b['inquiries'] - $b['replied']) <=> ($a['inquiries'] - $a['replied']));
        $worst = $weak[0];
        $affected = array_sum(array_map(static fn ($c) => $c['inquiries'] - $c['replied'], $weak));

        return [[
            'id' => "communication-class-gap-{$this->syear}",
            'rule' => 'class_reply_gap',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => count($weak) === 1
                ? "Families in {$worst['label']} are answered {$worst['replyRate']}% of the time against {$baseline}% overall"
                : count($weak).' classes answer their families materially less often than the institute',
            'whatHappened' => $this->sentence([
                'Across all inquiries this year the reply rate is '.$baseline.'%.',
                count($weak).' class'.(count($weak) === 1 ? '' : 'es').' with at least '.self::MIN_CLASS_COHORT
                    .' inquiries sit at least 10 points below it.',
                "The largest backlog is {$worst['label']}: ".($worst['inquiries'] - $worst['replied'])
                    ." of {$worst['inquiries']} inquiries unanswered.",
            ]),
            'whyItMatters' => 'Parent messages are answered by the class teacher, so a reply rate that varies by '
                .'class is about who is reading the portal rather than about how much mail arrives. The families '
                .'affected are the ones whose teacher does not.',
            'evidence' => array_map(static fn ($c) => [
                'label' => $c['label'],
                'value' => "{$c['replyRate']}% answered",
                'note' => "{$c['replied']} of {$c['inquiries']} inquiries",
            ], array_slice($weak, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => 'A class teacher who answers parents by telephone or in the diary rather than through '
                .'the portal would produce exactly this pattern. So would one who is not reading it. The records '
                .'cannot tell those apart.',
            'causeConfirmed' => false,
            'recommendation' => 'Ask the named classes how they answer parents before treating this as a backlog — '
                .'a reply given in the diary is an answer the portal cannot see.',
            'owner' => 'Academic coordinator',
            'priority' => 'medium',
            'confidence' => $affected >= 50
                ? ['band' => 'High', 'value' => 0.85]
                : ['band' => 'Medium', 'value' => 0.6],
            'affected' => ['count' => $affected, 'total' => $metrics['totalInquiries'] ?? null, 'unit' => 'inquiries'],
            'impact' => ['value' => $affected, 'display' => (string) $affected, 'label' => 'families waiting in these classes'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * The reply rate moving across the year.
     *
     * Inquiries carry a DATE, so unlike most modules here this one can say
     * something about direction rather than only about position.
     *
     * @return array<int,array<string,mixed>>
     */
    private function replyRateDrift(): array
    {
        $months = $this->monthStats();
        if (count($months) < 4) {
            return [];
        }

        // Compared as halves rather than first-versus-last month, so one quiet
        // month cannot manufacture a trend.
        $half = (int) floor(count($months) / 2);
        $early = array_slice($months, 0, $half);
        $late = array_slice($months, count($months) - $half);

        $rate = static function (array $window): ?float {
            $inquiries = array_sum(array_column($window, 'inquiries'));

            return $inquiries > 0
                ? round(array_sum(array_column($window, 'replied')) / $inquiries * 100, 1)
                : null;
        };

        $earlyRate = $rate($early);
        $lateRate = $rate($late);

        if ($earlyRate === null || $lateRate === null) {
            return [];
        }

        $change = round($lateRate - $earlyRate, 1);
        if (abs($change) < 8.0) {
            return [];
        }

        $falling = $change < 0;
        $unanswered = array_sum(array_map(
            static fn ($m) => $m['inquiries'] - $m['replied'],
            $late,
        ));

        return [[
            'id' => "communication-reply-drift-{$this->syear}",
            'rule' => 'reply_rate_drift',
            'severity' => $falling ? 'medium' : 'info',
            'severityLabel' => $falling ? 'Medium' : 'Notable',
            'title' => $falling
                ? 'The reply rate has fallen '.abs($change).' points across the year'
                : "The reply rate has risen {$change} points across the year",
            'whatHappened' => $this->sentence([
                "The first half of the year answered {$earlyRate}% of inquiries; the second half answered "
                    ."{$lateRate}%.",
                $falling
                    ? "{$unanswered} inquiries in the later months are still unanswered."
                    : null,
            ]),
            'whyItMatters' => $falling
                ? 'A reply rate that falls through the year is not about volume — the same office handled more '
                    .'earlier. It is what a habit looks like when it stops being kept, and it will not recover on '
                    .'its own in the next one.'
                : 'A reply rate that rises through the year is worth understanding for the same reason a fall would '
                    .'be: whatever changed, changed for everyone.',
            'evidence' => array_map(static fn ($m) => [
                'label' => $m['label'],
                'value' => $m['replyRate'] === null ? '—' : "{$m['replyRate']}% answered",
                'note' => "{$m['replied']} of {$m['inquiries']} inquiries",
            ], array_slice($months, -self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => 'The most recent month is always partly unanswered simply because it is recent, which '
                .'pulls the later half down. This finding compares halves rather than months to limit that, but it '
                .'does not eliminate it.',
            'causeConfirmed' => false,
            'recommendation' => $falling
                ? 'Discount the current month before acting on this — the rest of the drop is real.'
                : null,
            'owner' => 'Front office',
            'priority' => $falling ? 'medium' : 'low',
            // Held down deliberately: the recency effect above is a real
            // alternative explanation the data cannot separate out.
            'confidence' => ['band' => 'Medium', 'value' => 0.65],
            'affected' => [
                'count' => $unanswered,
                'total' => array_sum(array_column($months, 'inquiries')),
                'unit' => 'inquiries',
            ],
            'impact' => [
                'value' => abs($change),
                'display' => abs($change).' points',
                'label' => $falling ? 'fall in the reply rate' : 'rise in the reply rate',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ outbound */

    /**
     * WHAT the outbound messages are for, which is the question "5,687 messages
     * were sent" does not answer.
     *
     * @return array<int,array<string,mixed>>
     */
    private function outboundMix(): array
    {
        $modules = $this->smsModuleStats();
        $metrics = $this->analytics->position()['metrics'];
        $sms = (int) ($metrics['totalSmsSent'] ?? 0);

        if ($sms === 0 || count($modules) < 2) {
            return [];
        }

        $top = $modules[0];
        if ($top['share'] === null || $top['share'] < self::CHANNEL_DOMINANCE) {
            return [];
        }

        $rest = $sms - $top['messages'];
        $inquiries = (int) ($metrics['totalInquiries'] ?? 0);

        return [[
            'id' => "communication-outbound-mix-{$this->syear}",
            'rule' => 'outbound_mix',
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => "{$top['share']}% of outbound SMS comes from a single module: {$top['label']}",
            'whatHappened' => $this->sentence([
                "{$top['messages']} of {$sms} messages sent this year were raised by “{$top['label']}”, leaving "
                    ."{$rest} from everything else combined across ".(count($modules) - 1).' other modules.',
                $inquiries > 0
                    ? "Over the same year parents sent {$inquiries} inquiries inward."
                    : null,
            ]),
            'whyItMatters' => 'What a school sends is what parents learn to expect from it. Where almost all outbound '
                .'messaging is raised by one operational process, the channel becomes associated with that process, '
                .'and anything else sent through it is read against that expectation.',
            'evidence' => array_map(static fn ($m) => [
                'label' => $m['label'],
                'value' => "{$m['messages']} messages",
                'note' => $m['share'] === null ? null : "{$m['share']}% of outbound SMS",
            ], array_slice($modules, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => null,
            'owner' => 'Front office',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $top['messages'], 'total' => $sms, 'unit' => 'messages'],
            'impact' => null,
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * Reply rate per CLASS, not per subject line.
     *
     * `parent_communication.title` is free text a parent writes — 2,495
     * distinct values across 4,873 rows at one institute, up to 209
     * characters, and they include a named child's illness. It is not a
     * topic, it cannot be grouped, and nothing here reads it.
     *
     * @return array<int,array<string,mixed>>
     */
    private function classStats(): array
    {
        if (! SchemaCache::hasTable(self::PARENT_COMM_TABLE)) {
            return [];
        }

        $rows = DB::table(self::PARENT_COMM_TABLE.' as pc')
            ->join('tblstudent_enrollment as e', function ($join) {
                $join->on('e.student_id', '=', 'pc.student_id')
                    ->on('e.sub_institute_id', '=', 'pc.sub_institute_id')
                    ->on('e.syear', '=', 'pc.syear');
            })
            ->leftJoin('standard as st', 'st.id', '=', 'e.standard_id')
            ->where('pc.sub_institute_id', $this->analytics->tenantId())
            ->where('pc.syear', $this->syear)
            ->groupBy('e.standard_id', 'st.name')
            ->select(
                'e.standard_id',
                DB::raw('COALESCE(NULLIF(st.name, ""), CONCAT("Standard #", e.standard_id)) as class_name'),
                DB::raw('COUNT(*) as inquiries'),
                DB::raw('SUM(CASE WHEN TRIM(COALESCE(pc.reply, "")) <> "" THEN 1 ELSE 0 END) as replied')
            )
            ->orderByDesc('inquiries')
            ->get();

        return array_map(static function ($row) {
            $inquiries = (int) $row->inquiries;
            $replied = (int) $row->replied;

            return [
                'label' => (string) $row->class_name,
                'inquiries' => $inquiries,
                'replied' => $replied,
                'replyRate' => $inquiries > 0 ? round($replied / $inquiries * 100, 1) : null,
            ];
        }, $rows->all());
    }

    /** @return array<int,array<string,mixed>> */
    private function monthStats(): array
    {
        if (! SchemaCache::hasTable(self::PARENT_COMM_TABLE)) {
            return [];
        }

        $rows = DB::table(self::PARENT_COMM_TABLE)
            ->where('sub_institute_id', $this->analytics->tenantId())
            ->where('syear', $this->syear)
            ->whereNotNull('date_')
            ->selectRaw(
                'DATE_FORMAT(date_, "%Y-%m") as month_key,
                 COUNT(*) as inquiries,
                 SUM(CASE WHEN TRIM(COALESCE(reply, "")) <> "" THEN 1 ELSE 0 END) as replied'
            )
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get();

        return array_map(static function ($row) {
            $inquiries = (int) $row->inquiries;
            $replied = (int) $row->replied;

            return [
                'label' => (string) $row->month_key,
                'inquiries' => $inquiries,
                'replied' => $replied,
                'replyRate' => $inquiries > 0 ? round($replied / $inquiries * 100, 1) : null,
            ];
        }, $rows->all());
    }

    /** @return array<int,array<string,mixed>> */
    private function smsModuleStats(): array
    {
        if (! SchemaCache::hasTable(self::SMS_TABLE)) {
            return [];
        }

        $rows = DB::table(self::SMS_TABLE)
            ->where('sub_institute_id', $this->analytics->tenantId())
            ->where('SYEAR', $this->syear)
            ->selectRaw(
                'CASE
                    WHEN TRIM(COALESCE(MODULE_NAME, "")) = "" THEN "Not attributed"
                    ELSE TRIM(MODULE_NAME)
                 END as module_name,
                 COUNT(*) as messages'
            )
            ->groupBy('module_name')
            ->orderByDesc('messages')
            ->get();

        $total = array_sum(array_map(static fn ($r) => (int) $r->messages, $rows->all()));

        return array_map(static fn ($row) => [
            'label' => (string) $row->module_name,
            'messages' => (int) $row->messages,
            'share' => $total > 0 ? round((int) $row->messages / $total * 100, 1) : null,
        ], $rows->all());
    }

    /**
     * Class-level aggregates, used as the unanswered rule's evidence.
     *
     * @return array<int,array<string,mixed>>
     */
    private function classEvidence(): array
    {
        $classes = $this->classStats();
        usort($classes, static fn ($a, $b) => ($b['inquiries'] - $b['replied']) <=> ($a['inquiries'] - $a['replied']));

        return array_map(static fn ($c) => [
            'label' => $c['label'],
            'value' => ($c['inquiries'] - $c['replied']).' unanswered',
            'note' => "of {$c['inquiries']} inquiries"
                .($c['replyRate'] === null ? '' : " · {$c['replyRate']}% answered"),
        ], array_slice($classes, 0, self::MAX_NAMED_IN_EVIDENCE));
    }

    /** @param array<int,?string> $parts */
    private function sentence(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }
}
