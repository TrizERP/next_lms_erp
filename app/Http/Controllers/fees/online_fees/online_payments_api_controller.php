<?php

namespace App\Http\Controllers\fees\online_fees;

use App\Http\Controllers\Controller;
use App\Services\Fees\FeePaymentGatewayResolver;
use App\Services\Fees\FeeStatusMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only listing/detail API over the existing fees_payment table.
 * SELECT-only — never writes to fees_payment, fees_collect, or any other
 * table, and never calls, wraps, or alters any gateway request/response
 * handler. Sprint 2 "Unified Online Payments API".
 */
class online_payments_api_controller extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('fees_payment');

            if ($request->filled('sub_institute_id')) {
                $query->where('sub_institute_id', $request->input('sub_institute_id'));
            }
            if ($request->filled('syear')) {
                $query->where('syear', $request->input('syear'));
            }
            if ($request->filled('student_id')) {
                $query->where('student_id', $request->input('student_id'));
            }
            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', $request->input('from_date'));
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', $request->input('to_date'));
            }

            $perPage = (int) $request->input('per_page', 25);
            $perPage = ($perPage > 0 && $perPage <= 200) ? $perPage : 25;

            $paginator = $query->orderByDesc('id')->paginate($perPage);

            $rows = collect($paginator->items())->map(fn ($row) => $this->transform($row));

            $gatewayFilter = $request->input('gateway');
            $statusFilter = $request->input('status');
            if ($gatewayFilter || $statusFilter) {
                $rows = $rows->filter(function ($row) use ($gatewayFilter, $statusFilter) {
                    if ($gatewayFilter && $row['gateway_group'] !== $gatewayFilter) {
                        return false;
                    }
                    if ($statusFilter && $row['status'] !== $statusFilter) {
                        return false;
                    }

                    return true;
                })->values();
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
            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => 0, 'message' => 'Unable to load online payments.', 'data' => []], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $row = DB::table('fees_payment')->where('id', $id)->first();

            if (! $row) {
                return response()->json(['status' => 0, 'message' => 'Not found', 'data' => null], 404);
            }

            return response()->json([
                'status' => 1,
                'data' => $this->transform($row, true),
            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => 0, 'message' => 'Unable to load this online payment.', 'data' => null], 500);
        }
    }

    private function transform($row, bool $detail = false): array
    {
        $group = FeePaymentGatewayResolver::detect($row);
        $columns = $group ? FeePaymentGatewayResolver::GATEWAY_GROUPS[$group] : null;

        $rawStatus = $columns ? ($row->{$columns['status']} ?? null) : null;
        $orderId = $columns ? ($row->{$columns['order']} ?? null) : null;
        $date = $columns ? ($row->{$columns['date']} ?? null) : null;

        $canonicalStatus = $group
            ? FeeStatusMapper::normalize($group, $rawStatus)
            : FeeStatusMapper::STATUS_UNKNOWN;

        $data = [
            'id' => $row->id,
            'student_id' => $row->student_id,
            'syear' => $row->syear,
            'sub_institute_id' => $row->sub_institute_id,
            'amount' => $row->amount,
            'fine' => $row->fine ?? null,
            'discount' => $row->discount ?? null,
            'gateway_group' => $group,
            'gateway_group_label' => $columns['label'] ?? 'Unrecognized — no order id set on any known gateway column',
            'order_id' => $orderId,
            'status' => $canonicalStatus,
            'raw_status' => $rawStatus,
            'payment_date' => $date,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];

        if ($detail) {
            // Which gateway-columns hold any order id at all — never exposes
            // raw encrypted/plain request payloads or full bank response
            // bodies (those stay internal; this is a status viewer, not a
            // payload viewer).
            $data['gateway_groups_with_data'] = collect(FeePaymentGatewayResolver::GATEWAY_GROUPS)
                ->filter(fn ($cols) => ! empty($row->{$cols['order']} ?? null))
                ->keys()
                ->values();
        }

        return $data;
    }
}
