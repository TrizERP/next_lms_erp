<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use function App\Helpers\FeeMonthId;

/**
 * Stateless aggregate for the Next.js Fees dashboard.
 *
 * This deliberately reads the same fee-breakoff and receipt tables used by the
 * existing Fees reports.  It accepts the tenant/year in the request so it can
 * live on Laravel's API middleware (no browser session is required).
 */
class FeesDashboardApiController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sub_institute_id' => ['required'],
            'syear' => ['required'],
            'month_id' => ['nullable'],
            'grade_id' => ['nullable'],
            'standard_id' => ['nullable'],
            'section_id' => ['nullable'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $subInstituteId = (string) $validated['sub_institute_id'];
        $syear = (string) $validated['syear'];
        $monthId = $request->input('month_id');
        $months = FeeMonthId($syear, $subInstituteId);
        $selectedMonths = $monthId !== null && $monthId !== '' ? [(string) $monthId] : array_map('strval', array_keys($months));

        $applyBreakoffFilters = function ($query, string $alias) use ($subInstituteId, $syear, $selectedMonths, $request) {
            $query->where("{$alias}.sub_institute_id", $subInstituteId)
                ->where("{$alias}.syear", $syear)
                ->when($selectedMonths, function ($builder) use ($alias, $selectedMonths) {
                    $builder->whereIn("{$alias}.month_id", $selectedMonths);
                })
                ->when($request->filled('grade_id'), function ($builder) use ($alias, $request) {
                    $builder->where("{$alias}.grade_id", $request->input('grade_id'));
                })
                ->when($request->filled('standard_id'), function ($builder) use ($alias, $request) {
                    $builder->where("{$alias}.standard_id", $request->input('standard_id'));
                })
                ->when($request->filled('section_id'), function ($builder) use ($alias, $request) {
                    $builder->where("{$alias}.section_id", $request->input('section_id'));
                });
        };

        $regularDemand = DB::table('fees_breackoff as fb')
            ->join('fees_title as ft', function ($join) {
                $join->on('ft.id', '=', 'fb.fee_type_id')
                    ->whereColumn('ft.sub_institute_id', '=', 'fb.sub_institute_id')
                    ->whereColumn('ft.syear', '=', 'fb.syear');
            });
        $applyBreakoffFilters($regularDemand, 'fb');

        $otherDemand = DB::table('fees_breakoff_other as fbo')
            ->join('fees_title as ft', function ($join) {
                $join->on('ft.other_fee_id', '=', 'fbo.fee_type_id')
                    ->whereColumn('ft.sub_institute_id', '=', 'fbo.sub_institute_id')
                    ->whereColumn('ft.syear', '=', 'fbo.syear');
            })
            ->join('tblstudent_enrollment as se', function ($join) use ($syear) {
                $join->on('se.student_id', '=', 'fbo.student_id')
                    ->whereColumn('se.sub_institute_id', '=', 'fbo.sub_institute_id')
                    ->where('se.syear', $syear)
                    ->whereNull('se.end_date');
            })
            ->where('fbo.sub_institute_id', $subInstituteId)
            ->where('fbo.syear', $syear)
            ->when($selectedMonths, function ($query) use ($selectedMonths) { $query->whereIn('fbo.month_id', $selectedMonths); })
            ->when($request->filled('grade_id'), function ($query) use ($request) { $query->where('se.grade_id', $request->input('grade_id')); })
            ->when($request->filled('standard_id'), function ($query) use ($request) { $query->where('se.standard_id', $request->input('standard_id')); })
            ->when($request->filled('section_id'), function ($query) use ($request) { $query->where('se.section_id', $request->input('section_id')); });

        $regularRows = (clone $regularDemand)->selectRaw('fb.month_id, ft.fees_title, ft.display_name, ft.sort_order, SUM(fb.amount) as demand_amount')
            ->groupBy('fb.month_id', 'ft.fees_title', 'ft.display_name', 'ft.sort_order')->get();
        $otherRows = (clone $otherDemand)->selectRaw('fbo.month_id, ft.fees_title, ft.display_name, ft.sort_order, SUM(fbo.amount) as demand_amount')
            ->groupBy('fbo.month_id', 'ft.fees_title', 'ft.display_name', 'ft.sort_order')->get();

        $receiptScope = function ($query, string $alias, string $monthColumn) use ($subInstituteId, $syear, $selectedMonths, $request) {
            $query->where("{$alias}.sub_institute_id", $subInstituteId)
                ->where("{$alias}.syear", $syear)
                ->where("{$alias}.is_deleted", 'N')
                ->when($selectedMonths, function ($builder) use ($alias, $monthColumn, $selectedMonths) { $builder->whereIn("{$alias}.{$monthColumn}", $selectedMonths); })
                ->when($request->filled('from_date'), function ($builder) use ($alias, $request) { $builder->whereDate("{$alias}.receiptdate", '>=', $request->input('from_date')); })
                ->when($request->filled('to_date'), function ($builder) use ($alias, $request) { $builder->whereDate("{$alias}.receiptdate", '<=', $request->input('to_date')); });
        };

        $regularReceipts = DB::table('fees_collect as fc');
        $receiptScope($regularReceipts, 'fc', 'term_id');
        $otherReceipts = DB::table('fees_paid_other as fpo');
        $receiptScope($otherReceipts, 'fpo', 'month_id');

        $regularByMonth = (clone $regularReceipts)->selectRaw('fc.term_id as month_id, SUM(fc.amount) as collected_amount, SUM(fc.fees_discount) as discount_amount, SUM(fc.fine) as fine_amount')
            ->groupBy('fc.term_id')->get()->keyBy('month_id');
        $otherByMonth = (clone $otherReceipts)->selectRaw('fpo.month_id, SUM(fpo.actual_amountpaid) as collected_amount, SUM(fpo.fees_discount) as discount_amount, SUM(fpo.fine) as fine_amount')
            ->groupBy('fpo.month_id')->get()->keyBy('month_id');

        $demandByMonth = [];
        $headwise = [];
        foreach ($regularRows->merge($otherRows) as $row) {
            $month = (string) $row->month_id;
            $head = (string) $row->fees_title;
            $demandByMonth[$month] = ($demandByMonth[$month] ?? 0) + (float) $row->demand_amount;
            if (!isset($headwise[$head])) $headwise[$head] = ['fee_title' => $head, 'display_name' => $row->display_name, 'sort_order' => (int) $row->sort_order, 'target_amount' => 0.0];
            $headwise[$head]['target_amount'] += (float) $row->demand_amount;
        }

        $collectedByMonth = [];
        $fineAmount = 0.0;
        $discountAmount = 0.0;
        foreach ($selectedMonths as $month) {
            $regular = $regularByMonth->get($month);
            $other = $otherByMonth->get($month);
            $collectedByMonth[$month] = (float) ($regular->collected_amount ?? 0) + (float) ($other->collected_amount ?? 0);
            $fineAmount += (float) ($regular->fine_amount ?? 0) + (float) ($other->fine_amount ?? 0);
            $discountAmount += (float) ($regular->discount_amount ?? 0) + (float) ($other->discount_amount ?? 0);
        }

        $demandAmount = array_sum($demandByMonth);
        $collectedAmount = array_sum($collectedByMonth);
        $outstandingAmount = max($demandAmount - $collectedAmount - $discountAmount, 0);

        foreach ($headwise as &$head) {
            // Legacy receipt tables keep fee-head values in tenant-configured
            // columns.  Check the schema before selecting one: some tenants
            // have a fee title in the master which was added after older rows.
            $column = str_replace('`', '``', (string) $head['fee_title']);
            $collected = 0.0;
            if (Schema::hasColumn('fees_collect', $head['fee_title'])) {
                $collected += (float) ((clone $regularReceipts)->sum(DB::raw("`{$column}`")) ?? 0);
            }
            if (Schema::hasColumn('fees_paid_other', $head['fee_title'])) {
                $collected += (float) ((clone $otherReceipts)->sum(DB::raw("`{$column}`")) ?? 0);
            }
            $head['collected_amount'] = round($collected, 2);
            $head['pending_amount'] = round(max($head['target_amount'] - $collected, 0), 2);
        }
        unset($head);

        $collectionVsTarget = [];
        foreach ($selectedMonths as $month) {
            $collectionVsTarget[] = [
                'month_id' => (int) $month,
                'month_label' => $months[$month] ?? $month,
                'target_amount' => round($demandByMonth[$month] ?? 0, 2),
                'collected_amount' => round($collectedByMonth[$month] ?? 0, 2),
            ];
        }

        $paymentModeMix = collect();
        foreach ([(clone $regularReceipts)->selectRaw("COALESCE(NULLIF(fc.payment_mode, ''), 'Unspecified') as payment_mode, COUNT(DISTINCT fc.receipt_no) as receipts, SUM(fc.amount) as amount")->groupBy('fc.payment_mode')->get(), (clone $otherReceipts)->selectRaw("COALESCE(NULLIF(fpo.payment_mode, ''), 'Unspecified') as payment_mode, COUNT(DISTINCT fpo.reciept_id) as receipts, SUM(fpo.actual_amountpaid) as amount")->groupBy('fpo.payment_mode')->get()] as $rows) {
            foreach ($rows as $row) $paymentModeMix->push($row);
        }
        $paymentModeMix = $paymentModeMix->groupBy('payment_mode')->map(function ($rows, $mode) {
            return ['payment_mode' => $mode, 'receipts' => (int) $rows->sum('receipts'), 'amount' => round((float) $rows->sum('amount'), 2)];
        })->values();

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'context' => ['sub_institute_id' => (int) $subInstituteId, 'syear' => (int) $syear, 'month_id' => $monthId ? (int) $monthId : null, 'month_label' => $monthId ? ($months[$monthId] ?? null) : null, 'from_date' => $request->input('from_date'), 'to_date' => $request->input('to_date'), 'currency' => 'INR', 'generated_at' => now()->toIso8601String()],
            'summary' => ['collected_amount' => round($collectedAmount, 2), 'collected_display' => $this->amount($collectedAmount), 'demand_amount' => round($demandAmount, 2), 'demand_display' => $this->amount($demandAmount), 'outstanding_amount' => round($outstandingAmount, 2), 'outstanding_display' => $this->amount($outstandingAmount), 'collection_rate' => $demandAmount > 0 ? round(($collectedAmount / $demandAmount) * 100, 2) : 0, 'collection_rate_display' => $demandAmount > 0 ? round(($collectedAmount / $demandAmount) * 100, 1) . '%' : '0%', 'defaulters_count' => 0, 'students_considered' => 0, 'receipts_count' => (int) $paymentModeMix->sum('receipts'), 'fine_amount' => round($fineAmount, 2), 'discount_amount' => round($discountAmount, 2)],
            'collection_vs_target' => $collectionVsTarget,
            'headwise' => array_values($headwise),
            'payment_mode_mix' => $paymentModeMix,
        ]);
    }

    private function amount(float $amount): string
    {
        return '₹' . number_format($amount, 2, '.', ',');
    }
}
