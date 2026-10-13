<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Donations received, as `donation_collection` records them, with the donor from
 * `Z_donarDetails`.
 *
 * THE DONATION MODULE, NOT ALUMNI DONATIONS
 *
 * Alumni giving lives in `alumni_donations` and `alumni_donation_campaigns` and belongs to
 * the Alumni module. Nothing here reads those.
 *
 * DONOR PRIVACY
 *
 * A donor's PAN, address, mobile number, cheque number and bank are NOT returned, and the
 * stored receipt HTML is never read. The donor appears by name, as on the receipt.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on the donation and on the donor. Soft-deleted
 * donations (the screen's delete) are excluded.
 */
class DonationService
{
    /**
     * Donations, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function collections(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('donation_collection')) {
            return ['count' => 0, 'donations' => [], 'note' => 'Donations are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();
        $amount = (int) (clone $query)->sum('d.donation_amount');

        $rows = $query
            ->selectRaw('d.id AS donation_id, d.paid_date, d.donation_head, d.donation_amount,
                d.payment_mode, d.reciept_no AS receipt_no, d.remarks, n.full_name AS donor_name')
            ->orderByDesc('d.paid_date')
            ->orderByDesc('d.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'total_amount' => $amount,
            'figures_cover' => 'every donation matching these filters, not only the rows listed',
            'donations' => $rows->map(static fn ($row) => [
                'donation_id' => (int) $row->donation_id,
                'paid_date' => $row->paid_date,
                'donor_name' => $row->donor_name,
                'donation_head' => $row->donation_head,
                'amount' => (int) $row->donation_amount,
                'payment_mode' => $row->payment_mode,
                'receipt_no' => $row->receipt_no,
                'purpose' => $row->remarks,
            ])->all(),
            'rule' => $this->rule(),
        ];
    }

    /**
     * Donations counted and totalled by head and by payment mode.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('donation_collection')) {
            return ['count' => 0, 'by_head' => [], 'note' => 'Donations are not recorded in this estate.'];
        }

        $query = $this->query($context);
        $this->applyFilters($query, $filters);

        $group = static fn (Builder $q, string $column, string $alias) => $q
            ->selectRaw("COALESCE(NULLIF(TRIM($column), ''), '(not recorded)') AS $alias,
                COUNT(*) AS donations, SUM(d.donation_amount) AS amount")
            ->groupByRaw("COALESCE(NULLIF(TRIM($column), ''), '(not recorded)')")
            ->orderByDesc('amount')
            ->limit(20)
            ->get()
            ->map(static fn ($row) => [
                $alias => $row->$alias,
                'donations' => (int) $row->donations,
                'amount' => (int) $row->amount,
            ])
            ->all();

        return [
            'count' => (clone $query)->count(),
            'total_amount' => (int) (clone $query)->sum('d.donation_amount'),
            'distinct_donors' => (int) (clone $query)->distinct()->count('d.donar_id'),
            'by_head' => $group(clone $query, 'd.donation_head', 'donation_head'),
            'by_payment_mode' => $group(clone $query, 'd.payment_mode', 'payment_mode'),
            'rule' => $this->rule(),
        ];
    }

    private function rule(): string
    {
        return 'One row is one donation received by the school (alumni giving is a different module). Amounts '
            .'are shown as stored, with no currency symbol or tax treatment added. A donor\'s PAN, address, '
            .'phone, bank and cheque details are not provided and must not be asked for or guessed. Do not '
            .'state that a donation is 80G-eligible or tax-deductible; the table records no such status.';
    }

    /** @param  array<string, mixed>  $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['donation_head'])) {
            $query->where('d.donation_head', (string) $filters['donation_head']);
        }

        if (! empty($filters['payment_mode'])) {
            $query->where('d.payment_mode', (string) $filters['payment_mode']);
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('d.paid_date', $operator, $date);
            }
        }
    }

    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        return DB::table('donation_collection as d')
            ->leftJoin('Z_donarDetails as n', function ($join) use ($institute) {
                $join->on('n.id', '=', 'd.donar_id')->where('n.sub_institute_id', '=', $institute);
            })
            ->where('d.sub_institute_id', $institute)
            ->whereNull('d.deleted_at');
    }
}
