<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll, reported in aggregate.
 *
 * AGGREGATES ONLY, ON PURPOSE
 *
 * A salary is among the most sensitive figures a school holds. This returns the pay heads
 * the institute has defined and, per month, how many payslips were generated and their
 * totals. It never returns one named person's pay, deduction or salary structure, and the
 * tools that call it are admin-only. A question about a named person's salary is answered
 * with "this assistant reports payroll in totals only".
 *
 * WHAT THE TABLES DO NOT SUPPORT
 *
 * `total_payment` and `total_deduction` are whole-number columns the payroll run writes;
 * they are reported as stored, with no currency, tax or net-pay interpretation added.
 * `payroll_types.payroll_type` is 1 = Allowance, 2 = Deduction (the Payroll Type screen's
 * own labelling). The meaning of `amount_type` is not stated anywhere readable, so it is
 * not interpreted.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every table read.
 */
class PayrollOverviewService
{
    /**
     * The pay heads (allowances and deductions) defined for the institute.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function heads(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('payroll_types')) {
            return ['count' => 0, 'heads' => [], 'note' => 'Payroll is not recorded in this estate.'];
        }

        $query = DB::table('payroll_types')->where('sub_institute_id', $context->selectedInstituteId);

        $kind = strtolower(trim((string) ($filters['kind'] ?? 'any')));

        if ($kind === 'allowance') {
            $query->where('payroll_type', 1);
        } elseif ($kind === 'deduction') {
            $query->where('payroll_type', 2);
        }

        $total = (clone $query)->count();
        $allowances = (clone $query)->where('payroll_type', 1)->count();
        $deductions = (clone $query)->where('payroll_type', 2)->count();

        $rows = $query->orderBy('payroll_type')->orderBy('sort_order')->limit(200)
            ->get(['id', 'payroll_type', 'payroll_name', 'status', 'sort_order']);

        return [
            'count' => $total,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'heads' => $rows->map(static fn ($row) => [
                'head_id' => (int) $row->id,
                'name' => $row->payroll_name,
                'kind' => (int) $row->payroll_type === 1 ? 'Allowance' : 'Deduction',
                'active' => (int) $row->status === 1,
            ])->all(),
            'rule' => $this->rule(),
        ];
    }

    /**
     * Payslips generated per month, with totals.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function monthly(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('employee_monthly_salary_data')) {
            return ['count' => 0, 'months' => [], 'note' => 'Payroll is not recorded in this estate.'];
        }

        $query = DB::table('employee_monthly_salary_data')
            ->where('sub_institute_id', $context->selectedInstituteId);

        if (! empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }

        $months = (clone $query)
            ->selectRaw('year, month, COUNT(*) AS payslips, SUM(total_payment) AS total_payment,
                SUM(total_deduction) AS total_deduction, MAX(created_at) AS last_generated')
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('last_generated')
            ->limit(36)
            ->get()
            ->map(static fn ($row) => [
                'year' => (int) $row->year,
                'month' => $row->month,
                'payslips_generated' => (int) $row->payslips,
                'total_payment' => (int) $row->total_payment,
                'total_deduction' => (int) $row->total_deduction,
                'last_generated' => $row->last_generated,
            ])
            ->all();

        return [
            'count' => (clone $query)->count(),
            'months_listed' => count($months),
            'months' => $months,
            'rule' => $this->rule(),
        ];
    }

    private function rule(): string
    {
        return 'Payroll is reported in TOTALS ONLY. Never state, infer or estimate one named person\'s salary, '
            .'allowance or deduction, and never rank staff by pay. Amounts are shown as stored, with no '
            .'currency symbol, tax, net-pay or per-head average added. A month with no row has no payslips '
            .'recorded; it does not mean payroll was skipped.';
    }
}
