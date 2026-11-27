<?php

namespace App\Services\Platform;

use App\Brain\Support\AcademicYear;
use App\Services\Mcp\FeesArrearsService;
use App\Services\Mcp\McpRequestContext;
use App\Services\Platform\Fees\FeesConcessionGrantService;
use App\Services\Platform\Fees\FeesEscalationReportService;
use App\Services\Platform\Fees\FeesFamilyGroupService;
use App\Services\Platform\Fees\FeesGraphSignalsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Real signals for the Fees "Knowledge Graph Intelligence" banner.
 *
 * This banner sits above the whole Fees module with no selected student, so
 * every figure here is a tenant-wide aggregate rather than one learner's
 * numbers. Two rules keep it honest:
 *
 * 1. Nothing here recomputes "what a family owes". That number comes from
 *    FeesPendingService / FeesArrearsService, the same path the Fees screen
 *    itself uses (fees_collect_controller::studentFeesDetailAPI → getBk()).
 *    See FeesArrearsService's own docblock for why a second definition of
 *    arrears is worse than no answer.
 * 2. Sibling detection is SQL, not Neo4j. The graph was tried first
 *    (MATCH (s:Student)-[:CHILD_OF]->(p:Parent)) and came back empty on every
 *    tenant — there are zero `Parent` nodes and no `CHILD_OF` relationship
 *    anywhere in the graph, so that edge was never synced. Family grouping
 *    (FeesFamilyGroupService) is computed from `tblstudent.mobile` instead,
 *    which is real, populated data today.
 *
 * This class orchestrates five focused services (App\Services\Platform\Fees\*)
 * rather than doing everything itself — each card grew real family-wise/
 * drill-down logic that deserved its own file once this stopped being one
 * endpoint's worth of code.
 */
class FeesIntelligenceService
{
    /** A banner card is a teaser, not a report — cap any per-row list this short. */
    private const SAMPLE_SIZE = 5;

    /**
     * The defaulter and TC sections each pay for several real per-student fee
     * lookups (the same ~1.5-2s/student cost FeeArrearsDetector's own docblock
     * documents) — a cold call for one tenant runs 30-45s. That is fine for a
     * banner a staff member opens occasionally, not for every page load, so
     * the computed result is cached and reused across requests.
     */
    private const CACHE_MINUTES = 15;

    /**
     * Bumped from the unversioned key when the payload shape changed
     * (2026-11-28) — without this, a request landing inside the 15-minute
     * window right after deploy would serve the old shape from cache.
     */
    private const CACHE_VERSION = 'v2';

    public function __construct(
        private readonly FeesArrearsService $arrears,
        private readonly FeesFamilyGroupService $families,
        private readonly FeesEscalationReportService $escalation,
        private readonly FeesGraphSignalsService $graphSignals,
        private readonly FeesConcessionGrantService $grants,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function tenantSummary(int $tenantId, int $userId): array
    {
        $cached = Cache::remember(
            'platform.fees_intelligence.'.self::CACHE_VERSION.".{$tenantId}",
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->computeCachedSections($tenantId, $userId)
        );

        // The queue and the escalation report must never be 15 minutes stale
        // — a request just submitted or acted on has to show up immediately,
        // so these two are computed live on every call rather than cached.
        return array_merge($cached, [
            'concession_queue' => $this->safely(fn () => $this->concessionQueueSummary($tenantId)),
            'escalation' => $this->safely(fn () => $this->escalation->report($tenantId)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function computeCachedSections(int $tenantId, int $userId): array
    {
        $academicYearRaw = AcademicYear::currentFor((string) $tenantId);
        $academicYear = $academicYearRaw !== null ? (int) $academicYearRaw : null;

        $context = new McpRequestContext(
            $userId,
            'staff',
            $tenantId,
            [$tenantId],
            null,
            null,
            $academicYear,
            null,
            true,
            false,
        );

        return [
            'sub_institute_id' => $tenantId,
            'academic_year' => $academicYear,
            'computed_at' => now()->toIso8601String(),
            'siblings' => $this->safely(fn () => $this->families->summary($tenantId, $academicYear)),
            'defaulter_risk' => $this->safely(fn () => $this->defaulterSummary($context)),
            'notification_channel_routing' => $this->safely(fn () => $this->channelRoutingSummary($tenantId)),
            'tc_no_dues' => $this->safely(fn () => $this->tcNoDuesSummary($tenantId, $context)),
            'graph_signals' => $this->safely(fn () => $this->graphSignals->signals($tenantId, $academicYear !== null ? (string) $academicYear : null)),
        ];
    }

    /** One section failing (a bad connection, a locked table) must not blank the whole banner. */
    private function safely(callable $section): array
    {
        try {
            return $section();
        } catch (Throwable $e) {
            return ['available' => false, 'reason' => 'This signal could not be read right now.'];
        }
    }

    // ---------------------------------------------------------------- family drill-down

    /**
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function families(int $tenantId, int $page, int $perPage, string $appliedFilter, string $flag): array
    {
        $academicYearRaw = AcademicYear::currentFor((string) $tenantId);

        return $this->families->families($tenantId, $page, $perPage, $appliedFilter, $flag, $academicYearRaw !== null ? (int) $academicYearRaw : null);
    }

    /**
     * Family detail with real per-member outstanding and any open
     * concession/refund request attached — the two things the family
     * service itself deliberately does not compute (it has no
     * FeesArrearsService/McpRequestContext dependency).
     *
     * @return array<string, mixed>|null
     */
    public function family(int $tenantId, int $userId, int $anchorStudentId): ?array
    {
        $family = $this->families->family($tenantId, $anchorStudentId);
        if ($family === null) {
            return null;
        }

        $academicYearRaw = AcademicYear::currentFor((string) $tenantId);
        $academicYear = $academicYearRaw !== null ? (int) $academicYearRaw : null;
        $ids = $family['member_ids'];

        $context = new McpRequestContext($userId, 'staff', $tenantId, [$tenantId], null, null, $academicYear, null, true, false);
        $arrearsResult = $this->arrears->arrears($context, ['student_ids' => $ids, 'limit' => count($ids)]);

        $outstandingById = [];
        $checkedIds = [];
        if ($arrearsResult['success'] ?? false) {
            $checkedIds = $arrearsResult['data']['checked_student_ids'] ?? [];
            foreach ($arrearsResult['data']['students_with_arrears'] ?? [] as $row) {
                $outstandingById[(int) $row['student_id']] = (float) $row['outstanding'];
            }
        }

        $openRequests = DB::table('platform_workflow_runs')
            ->where('sub_institute_id', $tenantId)
            ->whereIn('flow_key', array_keys(FeesConcessionGrantService::FLOWS))
            ->where('entity_type', 'student')
            ->whereIn('entity_id', array_map('strval', $ids))
            ->where('status', 'pending')
            ->where('is_sample', 0)
            ->get(['entity_id', 'id', 'flow_key'])
            ->keyBy(fn ($r) => (int) $r->entity_id);

        $family['members'] = array_map(function (array $member) use ($outstandingById, $checkedIds, $openRequests) {
            $id = $member['student_id'];
            $member['outstanding'] = in_array($id, $checkedIds, true) ? ($outstandingById[$id] ?? 0.0) : null;
            $request = $openRequests->get($id);
            $member['open_request'] = $request !== null
                ? ['run_id' => (int) $request->id, 'flow_key' => $request->flow_key]
                : null;

            return $member;
        }, $family['members']);

        return $family;
    }

    // ---------------------------------------------------------------- arrears ranked list

    /**
     * @return array<string, mixed>
     */
    public function arrearsPage(int $tenantId, int $userId, int $page, int $perPage, ?int $standardId): array
    {
        $academicYearRaw = AcademicYear::currentFor((string) $tenantId);
        if ($academicYearRaw === null) {
            return ['available' => false, 'reason' => 'No academic year resolved for this institute.'];
        }
        $academicYear = (int) $academicYearRaw;

        return Cache::remember(
            "platform.fees_intelligence.".self::CACHE_VERSION.".{$tenantId}.arrears.{$page}.{$perPage}.".($standardId ?? 'all'),
            now()->addMinutes(self::CACHE_MINUTES),
            function () use ($tenantId, $userId, $page, $perPage, $standardId, $academicYear) {
                $ranked = (new \App\Brain\Intelligence\FeesIntelligence((string) $tenantId, (string) $academicYear))
                    ->outstandingAccounts($perPage, ($page - 1) * $perPage, $standardId !== null ? (string) $standardId : null);

                $pageIds = array_map(static fn (array $row) => (int) $row['studentId'], $ranked['rows'] ?? []);
                if ($pageIds === []) {
                    return ['available' => true, 'page' => $page, 'per_page' => $perPage, 'ranking_total' => (int) ($ranked['total'] ?? 0), 'rows' => [], 'not_checked' => []];
                }

                $context = new McpRequestContext($userId, 'staff', $tenantId, [$tenantId], null, null, $academicYear, null, true, false);
                $result = $this->arrears->arrears($context, ['student_ids' => $pageIds, 'limit' => count($pageIds)]);

                if (! ($result['success'] ?? false)) {
                    return ['available' => false, 'reason' => (string) ($result['message'] ?? 'Arrears lookup failed.')];
                }

                $data = $result['data'] ?? [];
                $byId = [];
                foreach ($data['students_with_arrears'] ?? [] as $row) {
                    $byId[(int) $row['student_id']] = $row;
                }
                $checked = $data['checked_student_ids'] ?? [];
                $notChecked = array_values(array_diff($pageIds, $checked));
                $familyKeys = $this->families->familyKeyFor($tenantId, $pageIds);

                $rows = [];
                foreach ($pageIds as $rank0 => $id) {
                    $row = $byId[$id] ?? null;
                    $items = $row['pending_items'] ?? 0;
                    $rows[] = [
                        'rank' => ($page - 1) * $perPage + $rank0 + 1,
                        'student_id' => $id,
                        'student_name' => $row['student_name'] ?? null,
                        'enrollment_no' => $row['enrollment_no'] ?? null,
                        'standard_name' => $row['standard_name'] ?? null,
                        'outstanding' => in_array($id, $checked, true) ? (float) ($row['outstanding'] ?? 0) : null,
                        'pending_items' => $items,
                        'family' => $familyKeys[$id] ?? null,
                        'checked' => in_array($id, $checked, true),
                    ];
                }

                return [
                    'available' => true,
                    'page' => $page,
                    'per_page' => $perPage,
                    'ranking_total' => (int) ($ranked['total'] ?? count($pageIds)),
                    'ranking_basis' => 'Ordered by the ledger\'s own outstanding-balance ranking; figures come from the same per-student arrears calculation the fees screen uses.',
                    'rows' => $rows,
                    'not_checked' => $notChecked,
                ];
            }
        );
    }

    // ---------------------------------------------------------------- graph signals

    /**
     * @return array<string, mixed>
     */
    public function graphSignals(int $tenantId): array
    {
        $academicYearRaw = AcademicYear::currentFor((string) $tenantId);

        return $this->graphSignals->signals($tenantId, $academicYearRaw !== null ? (string) $academicYearRaw : null);
    }

    // ---------------------------------------------------------------- defaulter risk

    /**
     * The largest fee arrears right now — never a population "risk score".
     *
     * FeesArrearsService deliberately checks the largest balances first once
     * a cohort exceeds its limit (see its `largestBalancesFirst()`), so a
     * defaulter-count-over-checked ratio from this sample would read as
     * "100% high risk" on almost every school: the sample is the worst
     * accounts by construction, not a random cross-section. So this reports
     * the real amount outstanding and who owes it, and leaves "what
     * fraction of the school is defaulting" unanswered rather than faking a
     * ratio the sample cannot support.
     *
     * @return array<string, mixed>
     */
    private function defaulterSummary(McpRequestContext $context): array
    {
        $result = $this->arrears->arrears($context, ['limit' => 12]);

        if (! ($result['success'] ?? false)) {
            return ['available' => false, 'reason' => (string) ($result['message'] ?? 'Arrears sweep failed.')];
        }

        $data = $result['data'] ?? [];
        $top = array_slice($data['students_with_arrears'] ?? [], 0, self::SAMPLE_SIZE);
        $topIds = array_map(static fn (array $row) => (int) $row['student_id'], $top);
        $familyKeys = $this->families->familyKeyFor($context->selectedInstituteId, $topIds);

        return [
            'available' => true,
            'defaulter_count' => (int) ($data['defaulter_count'] ?? 0),
            'total_outstanding' => (float) ($data['total_outstanding'] ?? 0),
            'cohort_size' => (int) ($data['cohort_size'] ?? 0),
            'students_checked' => (int) ($data['students_checked'] ?? 0),
            'truncated' => (bool) ($data['truncated'] ?? false),
            'sample_bias' => 'largest_balance_first',
            'top_defaulters' => array_map(function (array $row) use ($familyKeys) {
                $overdue = $this->monthsOverdue($row['pending_items_detail'] ?? []);

                return [
                    'student_id' => $row['student_id'],
                    'student_name' => $row['student_name'],
                    'outstanding' => $row['outstanding'],
                    'family' => $familyKeys[(int) $row['student_id']] ?? null,
                    'oldest_pending_month' => $overdue['oldest_pending_month'],
                    'months_overdue' => $overdue['months_overdue'],
                ];
            }, $top),
            'basis' => ($data['basis'] ?? null).' Months overdue is derived from the oldest unpaid fee month (fees_breackoff.month_id) vs today where that field is present on the pending item — there is no stored due-date column.',
        ];
    }

    /**
     * month_id is stored as month-then-year with no zero padding (e.g. '42026'
     * = April 2026, '122025' = December 2025) — the last 4 digits are always
     * the year. Defensive on purpose: this field is not guaranteed present on
     * every pending-item row from the legacy fee-ledger read, so a missing or
     * unparseable value reports "unknown" rather than a guessed figure.
     *
     * @param  array<int, mixed>  $items
     * @return array{oldest_pending_month: ?int, months_overdue: ?int}
     */
    private function monthsOverdue(array $items): array
    {
        $parsed = [];
        foreach ($items as $item) {
            $row = is_object($item) ? (array) $item : (array) $item;
            $raw = $row['month_id'] ?? null;
            if ($raw === null || ! ctype_digit((string) $raw) || strlen((string) $raw) < 5) {
                continue;
            }
            $str = (string) $raw;
            $year = (int) substr($str, -4);
            $month = (int) substr($str, 0, -4);
            if ($month < 1 || $month > 12) {
                continue;
            }
            $parsed[] = $year * 12 + $month;
        }

        if ($parsed === []) {
            return ['oldest_pending_month' => null, 'months_overdue' => null];
        }

        sort($parsed);
        $oldest = $parsed[0];
        $nowKey = (int) now()->format('Y') * 12 + (int) now()->format('n');
        $diff = $nowKey - $oldest;

        $oldestYear = intdiv($oldest, 12);
        $oldestMonth = $oldest % 12 ?: 12;

        return [
            'oldest_pending_month' => (int) sprintf('%d%04d', $oldestMonth, $oldestYear),
            'months_overdue' => max(0, $diff),
        ];
    }

    // ---------------------------------------------------------------- channel routing

    /**
     * Real channel engagement over the last 90 days: WhatsApp delivery/read
     * status from `whatsapp_sent_messages`, and reply rate on direct
     * messages from `parent_communication`. There is no SMS or Email log
     * table in this estate, so those channels are left out rather than
     * invented.
     *
     * @return array<string, mixed>
     */
    private function channelRoutingSummary(int $tenantId): array
    {
        $since = now()->subDays(90);

        $wa = DB::table('whatsapp_sent_messages')
            ->where('sub_institute_id', $tenantId)
            ->where('sent_date', '>=', $since)
            ->selectRaw(
                "COUNT(*) AS total,
                 SUM(message_status LIKE 'read%') AS read_count,
                 SUM(message_status LIKE 'delivered%') AS delivered_count"
            )
            ->first();

        $pc = DB::table('parent_communication')
            ->where('sub_institute_id', $tenantId)
            ->where('date_', '>=', $since)
            ->selectRaw(
                "COUNT(*) AS total,
                 SUM(reply IS NOT NULL AND reply <> '') AS replied_count"
            )
            ->first();

        $waTotal = (int) ($wa->total ?? 0);
        $pcTotal = (int) ($pc->total ?? 0);

        if ($waTotal === 0 && $pcTotal === 0) {
            return ['available' => false, 'reason' => 'No channel history in the last 90 days.'];
        }

        $channels = [];
        if ($waTotal > 0) {
            $channels['whatsapp'] = [
                'sent' => $waTotal,
                'read_rate' => round(((int) $wa->read_count) / $waTotal * 100),
                'delivered_rate' => round(((int) $wa->delivered_count) / $waTotal * 100),
            ];
        }
        if ($pcTotal > 0) {
            $channels['direct_message'] = [
                'sent' => $pcTotal,
                'reply_rate' => round(((int) $pc->replied_count) / $pcTotal * 100),
            ];
        }

        $recommended = null;
        $confidence = 0;
        foreach ($channels as $key => $stats) {
            $rate = $stats['read_rate'] ?? $stats['reply_rate'] ?? 0;
            if ($rate >= $confidence) {
                $recommended = $key;
                $confidence = $rate;
            }
        }

        return [
            'available' => true,
            'recommended_channel' => $recommended,
            'confidence' => $confidence,
            'channels' => $channels,
            'window_days' => 90,
            'family_reach' => $this->families->familyReachSummary($tenantId),
            'reminder_followup' => $this->reminderFollowup($tenantId),
        ];
    }

    /**
     * Correlates real fee-reminder sends (`ai_audit_logs`, event
     * `module.fees.fee_reminder` — written by FeesReminderService) with a
     * subsequent payment by the same student within 14 days. Only reminders
     * old enough that the window has fully closed are counted, so this
     * never reports on a follow-up period still in progress. Correlation
     * only — there is no comparison group, "sent" means the gateway
     * accepted it rather than confirmed delivery, and this never claims the
     * reminder caused the payment.
     *
     * @return array<string, mixed>
     */
    private function reminderFollowup(int $tenantId): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ai_audit_logs')) {
            return ['available' => false, 'reason' => 'No fee-reminder log exists.'];
        }

        $windowDays = 14;
        $lookbackDays = 90;
        $cutoff = now()->subDays($windowDays);
        $since = now()->subDays($lookbackDays);

        $reminders = DB::table('ai_audit_logs')
            ->where('sub_institute_id', $tenantId)
            ->where('event_type', 'module.fees.fee_reminder')
            ->where('outcome', 'success')
            ->whereBetween('created_at', [$since, $cutoff])
            ->get(['subject_id', 'payload', 'created_at']);

        if ($reminders->isEmpty()) {
            return ['available' => false, 'reason' => "No fee reminders in the last {$lookbackDays} days have had their {$windowDays}-day follow-up window close yet."];
        }

        $byChannel = [];
        foreach ($reminders as $row) {
            $payload = json_decode((string) $row->payload, true) ?: [];
            $channel = $payload['result']['channel'] ?? 'unknown';
            $byChannel[$channel][] = ['student_id' => (int) $row->subject_id, 'sent_at' => $row->created_at];
        }

        $result = [];
        foreach ($byChannel as $channel => $rows) {
            $studentIds = array_unique(array_column($rows, 'student_id'));
            $payments = DB::table('fees_collect')
                ->where('sub_institute_id', $tenantId)
                ->where('is_deleted', 'N')
                ->whereIn('student_id', $studentIds)
                ->get(['student_id', 'receiptdate'])
                ->groupBy('student_id');

            $followed = 0;
            foreach ($rows as $reminder) {
                $sentAt = \Illuminate\Support\Carbon::parse($reminder['sent_at']);
                $deadline = $sentAt->copy()->addDays($windowDays);
                $paidAfter = ($payments[$reminder['student_id']] ?? collect())->first(
                    fn ($p) => \Illuminate\Support\Carbon::parse($p->receiptdate)->between($sentAt, $deadline)
                );
                if ($paidAfter !== null) {
                    $followed++;
                }
            }

            $result[$channel] = [
                'reminders' => count($rows),
                'followed_by_payment' => $followed,
                'rate' => count($rows) > 0 ? round($followed / count($rows) * 100) : 0,
            ];
        }

        return [
            'available' => true,
            'window_days' => $windowDays,
            'lookback_days' => $lookbackDays,
            'by_channel' => $result,
            'basis' => 'Correlation only, no comparison group. "Sent" means the reminder gateway accepted it, not that it was delivered. This does not claim the reminder caused the payment.',
        ];
    }

    // ---------------------------------------------------------------- TC no-dues

    /**
     * Real zero-dues check for this year's pending TC applications — not a
     * tenant-wide sweep (that is the per-student fee calculation, too slow to
     * loop across a whole school; see FeesArrearsService's docblock), but TC
     * applicants are naturally few, so checking all of them live is safe.
     *
     * Classifies every applicant as `zero_due` / `has_dues` / `not_checked`
     * using FeesArrearsService's own `checked_student_ids` — a student whose
     * lookup failed or who was dropped by the cohort filter is never read as
     * "clear", which the original version of this method did.
     *
     * @return array<string, mixed>
     */
    private function tcNoDuesSummary(int $tenantId, McpRequestContext $context): array
    {
        $applicantIds = $this->pendingTcApplicantIds($tenantId, $context->academicYear);

        // The resolved "current" year disagreeing with where the TC backlog
        // actually sits is the same problem FeesArrearsService::cohort()
        // guards against: scoping strictly and finding nothing would report
        // a confident "no pending applications" when the backlog is simply
        // sitting under an adjacent year.
        if ($applicantIds === [] && $context->academicYear !== null) {
            $applicantIds = $this->pendingTcApplicantIds($tenantId, null);
        }

        if ($applicantIds === []) {
            return ['available' => true, 'pending_applications' => 0, 'zero_due' => 0, 'has_dues' => 0, 'not_checked' => 0, 'applicants' => []];
        }

        $result = $this->arrears->arrears($context, ['student_ids' => $applicantIds, 'limit' => count($applicantIds)]);

        if (! ($result['success'] ?? false)) {
            return ['available' => false, 'reason' => (string) ($result['message'] ?? 'Could not check pending dues.')];
        }

        $data = $result['data'] ?? [];
        $checkedIds = $data['checked_student_ids'] ?? [];
        $withDues = $data['students_with_arrears'] ?? [];
        $withDuesIds = array_map(static fn (array $row) => (int) $row['student_id'], $withDues);
        $withDuesById = [];
        foreach ($withDues as $row) {
            $withDuesById[(int) $row['student_id']] = $row;
        }
        $notCheckedIds = array_values(array_diff($applicantIds, $checkedIds));
        $selfReported = $this->tcSelfReportedDues($tenantId, $applicantIds);

        $applicants = [];
        foreach ($applicantIds as $id) {
            $status = in_array($id, $notCheckedIds, true) ? 'not_checked' : (in_array($id, $withDuesIds, true) ? 'has_dues' : 'zero_due');
            $applicants[] = [
                'student_id' => $id,
                'student_name' => $withDuesById[$id]['student_name'] ?? $selfReported[$id]['name'] ?? null,
                'status' => $status,
                'outstanding' => isset($withDuesById[$id]) ? (float) $withDuesById[$id]['outstanding'] : ($status === 'zero_due' ? 0.0 : null),
                'date_of_application' => $selfReported[$id]['date_of_application'] ?? null,
                'self_reported_dues_paid_upto' => $selfReported[$id]['month_up_paid_school_dues'] ?? null,
                'self_reported_any_concession' => $selfReported[$id]['any_fees_concession'] ?? null,
            ];
        }

        return [
            'available' => true,
            'pending_applications' => count($applicantIds),
            'zero_due' => count(array_filter($applicants, fn ($a) => $a['status'] === 'zero_due')),
            'has_dues' => count($withDuesIds),
            'not_checked' => count($notCheckedIds),
            'applicants' => array_slice($applicants, 0, 15),
            'basis' => 'Zero due today, not an auto-clear decision — no automatic TC approval exists. A not_checked applicant is never counted as clear.',
        ];
    }

    /**
     * @param  list<int>  $applicantIds
     * @return array<int, array<string, mixed>>
     */
    private function tcSelfReportedDues(int $tenantId, array $applicantIds): array
    {
        return DB::table('tblstudent_tc_details as tc')
            ->join('tblstudent as s', 's.id', '=', 'tc.student_id')
            ->where('tc.sub_institute_id', $tenantId)
            ->whereIn('tc.student_id', $applicantIds)
            ->selectRaw("tc.student_id, TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) as name, tc.date_of_application_for_certificate as date_of_application, tc.month_up_paid_school_dues, tc.any_fees_concession")
            ->get()
            ->keyBy('student_id')
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function pendingTcApplicantIds(int $tenantId, ?int $syear): array
    {
        $query = DB::table('tblstudent_tc_details')
            ->where('sub_institute_id', $tenantId)
            ->whereNull('date_of_issue_of_certificate');

        if ($syear !== null) {
            $query->where('syear', $syear);
        }

        return $query->orderByDesc('date_of_application_for_certificate')
            ->limit(15)
            ->pluck('student_id')
            ->map(static fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------- concession / refund queue

    /**
     * Real-time (never cached) pending/approved/applied state across the
     * concession and refund flows, plus whether a tenant even has an active
     * chain for each — so an empty queue reads as "nobody has submitted
     * one yet" or "no chain is active" rather than an unexplained zero.
     *
     * Fine-waiver/scholarship flows are intentionally left out of this
     * card's counts: the request-submission feature (and the escalation
     * rule, whose own text names concessions specifically) covers
     * concession/refund only.
     *
     * @return array<string, mixed>
     */
    private function concessionQueueSummary(int $tenantId): array
    {
        $this->grants->reconcile($tenantId);

        $pendingByFlow = DB::table('platform_workflow_runs')
            ->where('sub_institute_id', $tenantId)->where('is_sample', 0)->where('status', 'pending')
            ->whereIn('flow_key', array_keys(FeesConcessionGrantService::FLOWS))
            ->selectRaw('flow_key, COUNT(*) as c')
            ->groupBy('flow_key')
            ->get();

        $pendingCount = 0;
        $pendingByFlowOut = [];
        foreach ($pendingByFlow as $row) {
            $pendingCount += (int) $row->c;
            $pendingByFlowOut[FeesConcessionGrantService::FLOWS[$row->flow_key] ?? $row->flow_key] = (int) $row->c;
        }

        $escalated = DB::table('platform_workflow_runs')
            ->where('sub_institute_id', $tenantId)->where('is_sample', 0)->where('status', 'pending')
            ->where('flow_key', 'fees.concession.flow')
            ->get(['payload'])
            ->filter(function ($row) {
                $payload = json_decode((string) $row->payload, true) ?: [];

                return is_numeric($payload['amount'] ?? null) && (float) $payload['amount'] > 25000;
            })
            ->count();

        $approvedAwaiting = DB::table('fees_concession_grants')
            ->where('sub_institute_id', $tenantId)->where('status', 'approved_pending_application')
            ->selectRaw('COUNT(*) as c, SUM(amount - applied_amount) as amt')->first();

        $appliedThisYear = DB::table('fees_concession_grants')
            ->where('sub_institute_id', $tenantId)->where('status', 'applied')
            ->where('applied_at', '>=', now()->startOfYear())
            ->selectRaw('COUNT(*) as c, SUM(applied_amount) as amt')->first();

        $closed30d = DB::table('platform_workflow_runs')
            ->where('sub_institute_id', $tenantId)->where('is_sample', 0)
            ->whereIn('flow_key', array_keys(FeesConcessionGrantService::FLOWS))
            ->whereIn('status', ['rejected', 'returned'])
            ->where('completed_at', '>=', now()->subDays(30))
            ->count();

        $activeChains = DB::table('platform_workflows')
            ->where('sub_institute_id', $tenantId)->where('status', 'active')
            ->whereIn('flow_key', array_keys(FeesConcessionGrantService::FLOWS))
            ->pluck('flow_key')->all();

        $items = DB::table('platform_workflow_runs as r')
            ->leftJoin('tblstudent as s', 's.id', '=', DB::raw('CAST(r.entity_id AS UNSIGNED)'))
            ->where('r.sub_institute_id', $tenantId)->where('r.is_sample', 0)->where('r.status', 'pending')
            ->whereIn('r.flow_key', array_keys(FeesConcessionGrantService::FLOWS))
            ->orderBy('r.created_at')
            ->limit(self::SAMPLE_SIZE)
            ->selectRaw("r.id, r.flow_key, r.payload, r.current_step, r.created_at, s.id as student_id, TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) as student_name")
            ->get();

        return [
            'available' => true,
            'pending_count' => $pendingCount,
            'pending_by_flow' => $pendingByFlowOut,
            'escalated_to_trustee' => $escalated,
            'approved_awaiting' => ['count' => (int) ($approvedAwaiting->c ?? 0), 'amount' => round((float) ($approvedAwaiting->amt ?? 0), 2)],
            'applied_this_year' => ['count' => (int) ($appliedThisYear->c ?? 0), 'amount' => round((float) ($appliedThisYear->amt ?? 0), 2)],
            'closed_30d' => $closed30d,
            'chain_active' => [
                'concession' => in_array('fees.concession.flow', $activeChains, true),
                'refund' => in_array('fees.refund.flow', $activeChains, true),
            ],
            'items' => $items->map(function ($row) {
                $payload = json_decode((string) $row->payload, true) ?: [];

                return [
                    'run_id' => (int) $row->id,
                    'flow_key' => $row->flow_key,
                    'student_id' => $row->student_id !== null ? (int) $row->student_id : null,
                    'student_name' => $row->student_name,
                    'amount' => is_numeric($payload['amount'] ?? null) ? (float) $payload['amount'] : null,
                    'current_step' => (int) $row->current_step,
                    'created_at' => $row->created_at,
                ];
            })->all(),
        ];
    }
}
