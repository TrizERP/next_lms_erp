<?php

namespace App\Services\Platform;

use App\Brain\Support\AcademicYear;
use App\Services\Mcp\FeesArrearsService;
use App\Services\Mcp\McpRequestContext;
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
 *    anywhere in the graph, so that edge was never synced. Family grouping is
 *    computed from `tblstudent.mobile` (the guardian contact number siblings
 *    share at admission) instead, which is real, populated data today.
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

    public function __construct(
        private readonly FeesArrearsService $arrears,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function tenantSummary(int $tenantId, int $userId): array
    {
        return Cache::remember(
            "platform.fees_intelligence.{$tenantId}",
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->computeTenantSummary($tenantId, $userId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function computeTenantSummary(int $tenantId, int $userId): array
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
            'siblings' => $this->safely(fn () => $this->siblingSummary($tenantId)),
            'defaulter_risk' => $this->safely(fn () => $this->defaulterSummary($context)),
            'notification_channel_routing' => $this->safely(fn () => $this->channelRoutingSummary($tenantId)),
            'tc_no_dues' => $this->safely(fn () => $this->tcNoDuesSummary($tenantId, $context)),
            'concession_queue' => $this->safely(fn () => $this->concessionQueueSummary($tenantId)),
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

    // ---------------------------------------------------------------- siblings

    /**
     * Family groups found by shared guardian mobile number, and how many of
     * those students already carry a recorded discount in `fees_collect`.
     *
     * @return array<string, mixed>
     */
    private function siblingSummary(int $tenantId): array
    {
        $groups = DB::table('tblstudent')
            ->where('sub_institute_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1);
            })
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->select('mobile', DB::raw('COUNT(*) AS student_count'), DB::raw('GROUP_CONCAT(id) AS student_ids'))
            ->groupBy('mobile')
            ->having('student_count', '>', 1)
            ->get();

        if ($groups->isEmpty()) {
            return [
                'available' => true,
                'family_groups' => 0,
                'students_in_groups' => 0,
                'already_applied' => 0,
                'pending' => 0,
            ];
        }

        $siblingIds = [];
        foreach ($groups as $group) {
            foreach (explode(',', (string) $group->student_ids) as $id) {
                $siblingIds[] = (int) $id;
            }
        }
        $siblingIds = array_values(array_unique($siblingIds));

        $alreadyApplied = DB::table('fees_collect')
            ->whereIn('student_id', $siblingIds)
            ->where('sub_institute_id', $tenantId)
            ->where('is_deleted', 'N')
            ->where('fees_discount', '>', 0)
            ->distinct()
            ->count('student_id');

        return [
            'available' => true,
            'family_groups' => $groups->count(),
            'students_in_groups' => count($siblingIds),
            'already_applied' => $alreadyApplied,
            'pending' => max(0, count($siblingIds) - $alreadyApplied),
        ];
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

        return [
            'available' => true,
            'defaulter_count' => (int) ($data['defaulter_count'] ?? 0),
            'total_outstanding' => (float) ($data['total_outstanding'] ?? 0),
            'cohort_size' => (int) ($data['cohort_size'] ?? 0),
            'students_checked' => (int) ($data['students_checked'] ?? 0),
            'truncated' => (bool) ($data['truncated'] ?? false),
            'sample_bias' => 'largest_balance_first',
            'top_defaulters' => array_map(static fn (array $row) => [
                'student_id' => $row['student_id'],
                'student_name' => $row['student_name'],
                'outstanding' => $row['outstanding'],
            ], $top),
            'basis' => $data['basis'] ?? null,
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
        ];
    }

    // ---------------------------------------------------------------- TC no-dues

    /**
     * Real zero-dues check for this year's pending TC applications — not a
     * tenant-wide sweep (that is the per-student fee calculation, too slow to
     * loop across a whole school; see FeesArrearsService's docblock), but TC
     * applicants are naturally few, so checking all of them live is safe.
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
            return ['available' => true, 'pending_applications' => 0, 'clear_for_auto_approval' => 0, 'has_dues' => 0];
        }

        $result = $this->arrears->arrears($context, ['student_ids' => $applicantIds, 'limit' => count($applicantIds)]);

        if (! ($result['success'] ?? false)) {
            return ['available' => false, 'reason' => (string) ($result['message'] ?? 'Could not check pending dues.')];
        }

        $withDues = $result['data']['students_with_arrears'] ?? [];
        $withDuesIds = array_map(static fn (array $row) => (int) $row['student_id'], $withDues);
        $clearIds = array_values(array_diff($applicantIds, $withDuesIds));

        return [
            'available' => true,
            'pending_applications' => count($applicantIds),
            'clear_for_auto_approval' => count($clearIds),
            'has_dues' => count($withDuesIds),
            'students_with_dues' => array_map(static fn (array $row) => [
                'student_id' => $row['student_id'],
                'student_name' => $row['student_name'],
                'outstanding' => $row['outstanding'],
            ], array_slice($withDues, 0, self::SAMPLE_SIZE)),
        ];
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

    // ---------------------------------------------------------------- concession / refund / fine-waiver / scholarship queue

    /**
     * Real pending requests across the four amount-driven Fees approval
     * flows (config/platform_services.php: fees.concession, fees.refund,
     * fees.fine_waiver, fees.scholarship), classified against the actual
     * escalation rule. There is currently no live submission data for any of
     * these flows — every row the engine has seen is a seeded sample on a
     * different flow (`students.transfer.flow`) — so an empty result here is
     * the honest answer, not a bug.
     *
     * @return array<string, mixed>
     */
    private function concessionQueueSummary(int $tenantId): array
    {
        $rows = DB::table('platform_workflow_runs')
            ->where('sub_institute_id', $tenantId)
            ->where('is_sample', 0)
            ->where('status', 'pending')
            ->whereIn('flow_key', [
                'fees.concession.flow',
                'fees.refund.flow',
                'fees.fine_waiver.flow',
                'fees.scholarship.flow',
            ])
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            return ['available' => true, 'pending_count' => 0, 'escalated_to_trustee' => 0, 'items' => []];
        }

        $items = [];
        $escalated = 0;

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true) ?: [];
            $amount = null;
            foreach (['amount', 'concession_amount', 'requested_amount', 'waiver_amount'] as $key) {
                if (isset($payload[$key]) && is_numeric($payload[$key])) {
                    $amount = (float) $payload[$key];
                    break;
                }
            }

            $escalation = $amount !== null ? $this->determineConcessionEscalation($amount) : null;
            if ($escalation !== null && $escalation['is_escalated_to_trustee']) {
                $escalated++;
            }

            $items[] = [
                'entity_id' => $row->entity_id,
                'title' => $row->title,
                'flow_key' => $row->flow_key,
                'amount' => $amount,
                'required_approver_role' => $escalation['required_approver_role'] ?? null,
                'created_at' => $row->created_at,
            ];
        }

        return [
            'available' => true,
            'pending_count' => $rows->count(),
            'escalated_to_trustee' => $escalated,
            'items' => array_slice($items, 0, self::SAMPLE_SIZE),
        ];
    }

    /**
     * Value-based escalation rule:
     * - Amount <= ₹5,000: Accounts/Clerk can approve directly.
     * - ₹5,001 to ₹25,000: Standard Principal approval.
     * - Amount > ₹25,000: Escalates directly to Trustee / Management.
     *
     * This is the approval-chain POLICY, correctly applied to whatever real
     * amount is passed in. It is NOT currently enforced by the workflow run
     * engine itself — the configured `fees.concession` chain runs its fixed
     * step order regardless of amount — so today this classifies requests
     * for display; it does not yet skip steps automatically.
     *
     * @return array{amount: float, required_approver_role: string, target_step_name: string, is_escalated_to_trustee: bool, reason: string}
     */
    public function determineConcessionEscalation(float $amount): array
    {
        if ($amount > 25000) {
            return [
                'amount' => $amount,
                'required_approver_role' => 'Trustee',
                'target_step_name' => 'Trustee / Management Special Sign-off',
                'is_escalated_to_trustee' => true,
                'reason' => "Concession amount (₹" . number_format($amount) . ") exceeds threshold (₹25,000). Direct Trustee approval required.",
            ];
        }

        if ($amount <= 5000) {
            return [
                'amount' => $amount,
                'required_approver_role' => 'Accounts',
                'target_step_name' => 'Clerk / Accounts Level Approval',
                'is_escalated_to_trustee' => false,
                'reason' => "Concession amount (₹" . number_format($amount) . ") is within standard threshold (<= ₹5,000). Accounts clearance is sufficient.",
            ];
        }

        return [
            'amount' => $amount,
            'required_approver_role' => 'Principal',
            'target_step_name' => 'Principal Approval',
            'is_escalated_to_trustee' => false,
            'reason' => 'Standard mid-tier concession approval flow applies.',
        ];
    }
}
