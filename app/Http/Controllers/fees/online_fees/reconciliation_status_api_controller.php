<?php

namespace App\Http\Controllers\fees\online_fees;

use App\Http\Controllers\Controller;
use App\Services\Fees\FeePaymentGatewayResolver;
use App\Services\Fees\FeeStatusMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only reconciliation STATUS report. Shows which fees_payment rows
 * look pending/stuck and whether a matching fees_collect ledger row exists.
 *
 * This is a new, parallel, SELECT-only view. It does not call, wrap, or in
 * any way modify confirmOnlineFeesController — the existing (HDFC-only)
 * manual reconciliation confirm action stays exactly as-is. This endpoint
 * performs no writes anywhere. Sprint 2 "Reconciliation Status Report".
 *
 * Known limitation: fees_collect.cheque_no is an integer column, but
 * Razorpay/HDFC-Razorpay/PayPhi order ids are non-numeric strings (e.g.
 * "order_abc123"). The ledger_finalized cross-reference below is therefore
 * only reliable for HDFC/ICICI/Axis/AggrePay rows, whose order ids are
 * numeric — this is a pre-existing schema shape, not something this
 * read-only endpoint can correct.
 */
class reconciliation_status_api_controller extends Controller
{
    public function index(Request $request)
    {
        try {
            $olderThanMinutes = max(1, (int) $request->input('older_than_minutes', 30));

            $query = DB::table('fees_payment AS fp')
                ->select(
                    'fp.id', 'fp.student_id', 'fp.syear', 'fp.sub_institute_id', 'fp.amount',
                    'fp.hdfc_order_id', 'fp.hdfc_payment_status',
                    'fp.icici_order_id', 'fp.icici_payment_status',
                    'fp.axis_order_id', 'fp.axis_payment_status',
                    'fp.aggre_pay_order_id', 'fp.aggre_pay_payment_status',
                    'fp.razorpay_order_id', 'fp.razorpay_payment_status',
                    'fp.payphi_order_id', 'fp.payphi_payment_status',
                    'fp.created_at', 'fp.updated_at'
                )
                ->where('fp.created_at', '<=', now()->subMinutes($olderThanMinutes));

            if ($request->filled('sub_institute_id')) {
                $query->where('fp.sub_institute_id', $request->input('sub_institute_id'));
            }
            if ($request->filled('syear')) {
                $query->where('fp.syear', $request->input('syear'));
            }

            $perPage = (int) $request->input('per_page', 25);
            $perPage = ($perPage > 0 && $perPage <= 200) ? $perPage : 25;

            $paginator = $query->orderByDesc('fp.id')->paginate($perPage);

            $rows = collect($paginator->items())->map(fn ($row) => $this->transform($row));

            $stuckOnly = $request->boolean('stuck_only');
            if ($stuckOnly) {
                $rows = $rows->filter(fn ($row) => $row['looks_stuck'])->values();
            }

            return response()->json([
                'status' => 1,
                'data' => $rows,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
                'note' => 'Read-only status view. Performs no reconciliation action — see confirmOnlineFeesController for the existing manual (HDFC-only) confirm flow, which this endpoint does not call or modify.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => 0, 'message' => 'Unable to load reconciliation status.', 'data' => []], 500);
        }
    }

    private function transform($row): array
    {
        $group = FeePaymentGatewayResolver::detect($row);
        $columns = $group ? FeePaymentGatewayResolver::GATEWAY_GROUPS[$group] : null;

        $rawStatus = $columns ? ($row->{$columns['status']} ?? null) : null;
        $orderId = $columns ? ($row->{$columns['order']} ?? null) : null;

        $canonicalStatus = $group
            ? FeeStatusMapper::normalize($group, $rawStatus)
            : FeeStatusMapper::STATUS_UNKNOWN;

        $ledgerFinalized = $orderId
            ? DB::table('fees_collect')
                ->where('cheque_no', $orderId)
                ->where('student_id', $row->student_id)
                ->where('syear', $row->syear)
                ->where('sub_institute_id', $row->sub_institute_id)
                ->exists()
            : false;

        return [
            'id' => $row->id,
            'student_id' => $row->student_id,
            'syear' => $row->syear,
            'sub_institute_id' => $row->sub_institute_id,
            'amount' => $row->amount,
            'gateway_group' => $group,
            'order_id' => $orderId,
            'status' => $canonicalStatus,
            'raw_status' => $rawStatus,
            'ledger_finalized' => $ledgerFinalized,
            'looks_stuck' => $canonicalStatus === FeeStatusMapper::STATUS_PENDING && ! $ledgerFinalized,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
