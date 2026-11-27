<?php

namespace App\Services\Platform\Fees;

use App\Services\Platform\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Submits and acts on Fees concession/refund requests through the existing
 * WorkflowEngine — this class wraps it, it does not change it.
 * `WorkflowEngine::start()`/`act()` are shared by every other module's
 * approval points (HR leave, lesson-plan review, fee-structure change); a
 * run's final status reaching `approved` is handed to
 * FeesConcessionGrantService here, since that recording is Fees-specific
 * and must not live inside the shared engine.
 */
class FeesConcessionRequestService
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly FeesConcessionGrantService $grants,
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function submitConcession(int $tenantId, int $studentId, int $amount, string $reason, ?int $userId, ?string $userName): array
    {
        $student = $this->activeStudent($tenantId, $studentId);
        $this->refuseDuplicatePending($tenantId, 'fees.concession.flow', $studentId);

        $run = $this->engine->start(
            $tenantId,
            'fees.concession.flow',
            'student',
            (string) $studentId,
            $userId,
            $userName,
            sprintf('Concession ₹%s: %s (%s)', number_format($amount), $student->name, $student->enrollment_no ?: $studentId),
            [
                'kind' => 'concession',
                'student_id' => $studentId,
                'amount' => $amount,
                'reason' => $reason,
                'syear' => $student->syear,
                'requested_via' => 'fees_intelligence',
            ]
        );

        return $run;
    }

    /**
     * @param  array<int|string, mixed>  $refundHeads  particular id => amount
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function submitRefund(int $tenantId, int $studentId, array $refundHeads, string $reason, ?int $userId, ?string $userName): array
    {
        $student = $this->activeStudent($tenantId, $studentId);
        $this->refuseDuplicatePending($tenantId, 'fees.refund.flow', $studentId);

        $amounts = array_map('floatval', $refundHeads);
        $total = (int) round(array_sum($amounts));
        if ($total <= 0) {
            throw new InvalidArgumentException('A refund request needs at least one head with an amount.');
        }

        $run = $this->engine->start(
            $tenantId,
            'fees.refund.flow',
            'student',
            (string) $studentId,
            $userId,
            $userName,
            sprintf('Refund ₹%s: %s (%s)', number_format($total), $student->name, $student->enrollment_no ?: $studentId),
            [
                'kind' => 'refund',
                'student_id' => $studentId,
                'amount' => $total,
                'refund_heads' => $amounts,
                'reason' => $reason,
                'syear' => $student->syear,
                'requested_via' => 'fees_intelligence',
            ]
        );

        return $run;
    }

    /**
     * @param  'approve'|'reject'|'delegate'  $action
     * @return array{run: array<string, mixed>, grant: array<string, mixed>|null}
     * @throws InvalidArgumentException
     */
    public function act(int $tenantId, int $runId, string $action, int $userId, ?string $userName, ?string $comment, ?int $delegateTo): array
    {
        $run = DB::table('platform_workflow_runs')->where('sub_institute_id', $tenantId)->where('id', $runId)->first();
        if ($run === null || ! isset(FeesConcessionGrantService::FLOWS[$run->flow_key])) {
            throw new InvalidArgumentException('That is not a Fees concession/refund request.');
        }

        $result = $this->engine->act($tenantId, $runId, $action, $userId, $userName, $comment, $delegateTo);

        $grant = ($result['status'] ?? null) === 'approved'
            ? $this->grants->recordFromRun($tenantId, $runId)
            : null;

        return ['run' => $result, 'grant' => $grant];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(int $tenantId, array $filters): array
    {
        $this->grants->reconcile($tenantId);

        $query = DB::table('platform_workflow_runs as r')
            ->leftJoin('fees_concession_grants as g', 'g.run_id', '=', 'r.id')
            ->leftJoin('tblstudent as s', 's.id', '=', DB::raw('CAST(r.entity_id AS UNSIGNED)'))
            ->where('r.sub_institute_id', $tenantId)
            ->where('r.is_sample', 0)
            ->whereIn('r.flow_key', array_keys(FeesConcessionGrantService::FLOWS));

        if (! empty($filters['kind'])) {
            $query->where('r.flow_key', array_search($filters['kind'], FeesConcessionGrantService::FLOWS, true) ?: '__none__');
        }
        if (! empty($filters['student_id'])) {
            $query->where('r.entity_id', (string) $filters['student_id']);
        }
        if (! empty($filters['state'])) {
            match ($filters['state']) {
                'pending' => $query->where('r.status', 'pending'),
                'approved_pending_application' => $query->where('g.status', 'approved_pending_application'),
                'applied' => $query->where('g.status', 'applied'),
                'closed' => $query->whereIn('r.status', ['rejected', 'returned']),
                default => null,
            };
        }

        return $query->orderByDesc('r.id')->limit(100)->select(
            'r.id as run_id', 'r.flow_key', 'r.status as run_status', 'r.title', 'r.current_step', 'r.created_at',
            's.id as student_id', DB::raw("TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) as student_name"),
            'g.id as grant_id', 'g.status as grant_status', 'g.amount', 'g.applied_amount'
        )->get()->map(fn ($row) => [
            'run_id' => (int) $row->run_id,
            'flow_key' => $row->flow_key,
            'kind' => FeesConcessionGrantService::FLOWS[$row->flow_key] ?? null,
            'status' => $row->grant_id !== null ? $row->grant_status : $row->run_status,
            'title' => $row->title,
            'current_step' => (int) $row->current_step,
            'created_at' => $row->created_at,
            'student_id' => $row->student_id !== null ? (int) $row->student_id : null,
            'student_name' => $row->student_name,
            'grant_id' => $row->grant_id !== null ? (int) $row->grant_id : null,
            'amount' => $row->amount !== null ? (float) $row->amount : null,
            'applied_amount' => $row->applied_amount !== null ? (float) $row->applied_amount : null,
        ])->all();
    }

    private function activeStudent(int $tenantId, int $studentId): object
    {
        $student = DB::table('tblstudent')
            ->where('sub_institute_id', $tenantId)
            ->where('id', $studentId)
            ->where(function ($q) {
                $q->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1);
            })
            ->selectRaw("id, TRIM(CONCAT_WS(' ', first_name, last_name)) as name, enrollment_no")
            ->first();

        if ($student === null) {
            throw new InvalidArgumentException('That student was not found in this institute.');
        }

        $student->syear = (int) (\App\Brain\Support\AcademicYear::currentFor((string) $tenantId) ?? 0) ?: null;

        return $student;
    }

    private function refuseDuplicatePending(int $tenantId, string $flowKey, int $studentId): void
    {
        $exists = DB::table('platform_workflow_runs')
            ->where('sub_institute_id', $tenantId)
            ->where('flow_key', $flowKey)
            ->where('entity_type', 'student')
            ->where('entity_id', (string) $studentId)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('A request for this student is already pending on this flow.');
        }
    }
}
