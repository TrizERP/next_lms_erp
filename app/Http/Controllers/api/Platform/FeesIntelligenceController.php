<?php

namespace App\Http\Controllers\api\Platform;

use App\Services\Platform\AuditTrail;
use App\Services\Platform\Fees\ConcessionGrantNotApplicable;
use App\Services\Platform\Fees\FeesConcessionRequestService;
use App\Services\Platform\FeesIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The Fees "Knowledge Graph Intelligence" banner: family/sibling groups, the
 * largest arrears, the concession/refund queue, channel engagement, TC
 * no-dues clearance and the escalation rule — plus the drill-down and
 * request-submission endpoints those six cards open into.
 *
 * Split out of WorkflowController, which only ever hosted feesIntelligence()
 * because the banner was built as one endpoint. It has grown real
 * sub-resources since (paginated family/arrears lists, a live request queue)
 * that belong with their own controller, not bolted onto approval-chain
 * configuration.
 */
class FeesIntelligenceController extends PlatformController
{
    public function __construct(
        \App\Services\Platform\PlatformRegistry $registry,
        private readonly FeesIntelligenceService $intelligence,
        private readonly FeesConcessionRequestService $requests,
    ) {
        parent::__construct($registry);
    }

    /** GET /api/platform/workflow/fees-intelligence */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $userId = (int) ($this->auth($request)['user_id'] ?? 0);
        $data = $this->intelligence->tenantSummary($tenantId, $userId);

        return $this->ok($data, ['message' => 'Fees Knowledge Graph Intelligence retrieved successfully.']);
    }

    /** GET /api/platform/workflow/fees-intelligence/families?page=&per_page=&applied=&flag= */
    public function families(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));

        $result = $this->intelligence->families(
            $tenantId,
            $page,
            $perPage,
            (string) $request->query('applied', 'all'),
            (string) $request->query('flag', '')
        );

        return $this->ok($result['rows'], ['meta' => $result['meta']]);
    }

    /** GET /api/platform/workflow/fees-intelligence/families/{anchorStudentId} */
    public function family(Request $request, int $anchorStudentId): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $userId = (int) ($this->auth($request)['user_id'] ?? 0);
        $family = $this->intelligence->family($tenantId, $userId, $anchorStudentId);

        return $family !== null
            ? $this->ok($family)
            : $this->fail('That family could not be found.', 404);
    }

    /** GET /api/platform/workflow/fees-intelligence/arrears?page=&per_page=&standard_id= */
    public function arrears(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $userId = (int) ($this->auth($request)['user_id'] ?? 0);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(10, (int) $request->query('per_page', 10)));
        $standardId = $request->query('standard_id') !== null ? (int) $request->query('standard_id') : null;

        $result = $this->intelligence->arrearsPage($tenantId, $userId, $page, $perPage, $standardId);

        return $this->ok($result);
    }

    /** GET /api/platform/workflow/fees-intelligence/graph-signals */
    public function graphSignals(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        return $this->ok($this->intelligence->graphSignals($tenantId));
    }

    // ---------------------------------------------------------------- concession / refund requests

    /** GET /api/fees/concession-requests?kind=&state=&student_id= */
    public function requestsIndex(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $rows = $this->requests->list($tenantId, [
            'kind' => $request->query('kind'),
            'state' => $request->query('state'),
            'student_id' => $request->query('student_id') !== null ? (int) $request->query('student_id') : null,
        ]);

        return $this->ok($rows);
    }

    /** POST /api/fees/concession-requests  body: {kind, student_id, amount, reason, refund_heads?} */
    public function submitRequest(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $userId = (int) ($this->auth($request)['user_id'] ?? 0);
        if ($tenantId === null || $userId <= 0) {
            return $this->unauthenticated($request);
        }

        $data = $request->validate([
            'kind' => 'required|in:concession,refund',
            'student_id' => 'required|integer|min:1',
            'amount' => 'required_if:kind,concession|nullable|integer|min:1',
            'reason' => 'required|string|max:1000',
            'refund_heads' => 'required_if:kind,refund|nullable|array',
        ]);

        $userName = $this->actorLabel($request);

        try {
            $run = $data['kind'] === 'refund'
                ? $this->requests->submitRefund($tenantId, (int) $data['student_id'], (array) ($data['refund_heads'] ?? []), $data['reason'], $userId, $userName)
                : $this->requests->submitConcession($tenantId, (int) $data['student_id'], (int) $data['amount'], $data['reason'], $userId, $userName);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        AuditTrail::record($tenantId, 'fees', $data['kind'], 'request_submitted', [
            'entity_type' => 'student', 'entity_id' => $data['student_id'],
            'after' => ['run_id' => $run['id'] ?? null, 'amount' => $data['amount'] ?? null],
        ], $request);

        return $this->ok($run, [], 201);
    }

    /** POST /api/fees/concession-requests/{runId}/{action}  action: approve|reject|delegate */
    public function actOnRequest(Request $request, int $runId, string $action): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $userId = (int) ($this->auth($request)['user_id'] ?? 0);
        if ($tenantId === null || $userId <= 0) {
            return $this->unauthenticated($request);
        }

        $data = $request->validate(['comment' => 'nullable|string|max:2000', 'delegate_to' => 'nullable|integer']);

        try {
            $result = $this->requests->act($tenantId, $runId, $action, $userId, $this->actorLabel($request), $data['comment'] ?? null, $data['delegate_to'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        AuditTrail::record($tenantId, 'fees', 'approval', 'request_'.$action, [
            'entity_type' => 'workflow_run', 'entity_id' => $runId,
            'after' => ['status' => $result['run']['status'] ?? null],
        ], $request);

        return $this->ok($result);
    }

    /** GET /api/fees/concession-grants/{grantId} */
    public function grant(Request $request, int $grantId): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $row = DB::table('fees_concession_grants')->where('sub_institute_id', $tenantId)->find($grantId);
        if (! $row) {
            return $this->fail('That approved request was not found.', 404);
        }

        return $this->ok([
            'id' => (int) $row->id,
            'run_id' => (int) $row->run_id,
            'kind' => $row->kind,
            'student_id' => (int) $row->student_id,
            'amount' => (float) $row->amount,
            'applied_amount' => (float) $row->applied_amount,
            'remaining' => round((float) $row->amount - (float) $row->applied_amount, 2),
            'refund_heads' => $row->refund_heads !== null ? json_decode((string) $row->refund_heads, true) : null,
            'status' => $row->status,
            'approved_at' => $row->approved_at,
        ]);
    }
}
