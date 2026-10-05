<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Petty cash, as the `petty_cash` table records it.
 *
 * ONE ROW IS ONE SPEND
 *
 * `petty_cash` carries a head (`title_id` into `petty_cash_master`), a description, an
 * amount, the date it was entered, the user who entered it and an optional scan of the
 * bill. That is the whole record.
 *
 * THERE IS NO APPROVAL, AND NO BALANCE. BOTH ABSENCES MATTER.
 *
 * The two questions a person most wants to ask a petty cash book are "what is awaiting
 * approval" and "what is left in the tin", and this schema answers neither:
 *
 *   · No approval column, no approver, no approved-at, no rejection, and no approval table
 *     anywhere in the estate. `PettyCashController` writes a row and that is the whole
 *     lifecycle — entering a spend IS the record of it.
 *   · No opening float, no top-up, no reimbursement and no closing balance. The table
 *     records money that went out and nothing that came in, so a balance cannot be
 *     computed from it. Summing the spends gives total spending, which is not a balance
 *     and must never be presented as one.
 *
 * A tool that reported "pending approval" or "₹X remaining" would be inventing both, and
 * on a financial record an invented figure is the most damaging kind. So neither is
 * returned, and the rule on every payload says so in the words a model will read.
 *
 * WHAT IS REAL AND CHECKABLE
 *
 * Totals by head and by month, computed over the whole filtered set rather than the page.
 * Whether a bill scan is attached, which is the one genuine "needs attention" signal this
 * table carries — a spend with no bill is a spend with no evidence behind it, and that is
 * a fact about the record rather than a judgement about the person.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read. `petty_cash` has no `syear`,
 * so the academic year is not a filter here — date ranges are. `petty_cash_master` and
 * `tbluser` are joined on institute too, so a head or a spender's name from another school
 * can never appear on this school's book.
 */
class PettyCashService
{
    /**
     * Petty cash spends, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function transactions(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('petty_cash')) {
            return ['count' => 0, 'transactions' => [], 'note' => 'A petty cash book is not kept in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        $this->applyFilters($query, $filters);

        if (! empty($filters['without_bill_only'])) {
            $query->where(static function ($inner): void {
                $inner->whereNull('pc.bill_image')->orWhere('pc.bill_image', '');
            });
        }

        // Counted and summed over the WHOLE filtered set before the limit is applied. A
        // page total presented as the period's spending is the single easiest way to be
        // wrong about money here.
        $scope = clone $query;
        $total = (clone $scope)->count();
        $totalAmount = (float) (clone $scope)->sum('pc.amount');
        $withoutBill = (clone $scope)
            ->where(static function ($inner): void {
                $inner->whereNull('pc.bill_image')->orWhere('pc.bill_image', '');
            })
            ->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderByDesc('pc.created_on')
            ->orderByDesc('pc.id')
            ->limit($limit)
            ->get();

        $transactions = $rows->map(fn ($row) => $this->map($row))->all();

        return [
            'count' => $total,
            'row_count' => count($transactions),
            'total_amount' => round($totalAmount, 2),
            'without_bill' => $withoutBill,
            'figures_cover' => 'every transaction matching these filters, not only the rows listed',
            'transactions' => $transactions,
            'rule' => $this->rule(),
        ];
    }

    /**
     * What was spent, by head and by month.
     *
     * Both groupings are computed over the whole filtered set. `total_amount` is money
     * that LEFT the tin; it is not a balance, and no balance is derivable here.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('petty_cash')) {
            return ['count' => 0, 'by_head' => [], 'by_month' => [], 'note' => 'A petty cash book is not kept in this estate.'];
        }

        $query = $this->query($context);
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();
        $totalAmount = (float) (clone $query)->sum('pc.amount');

        $byHead = (clone $query)
            ->selectRaw('pc.title_id, pcm.title AS head_title, COUNT(*) AS transactions, SUM(pc.amount) AS amount')
            ->groupBy('pc.title_id', 'pcm.title')
            ->orderByDesc('amount')
            ->limit(50)
            ->get()
            ->map(static fn ($row) => [
                'head_id' => $row->title_id === null ? null : (int) $row->title_id,
                // Null when the head belongs to another institute — a record to correct,
                // not a name to borrow from elsewhere.
                'head' => $row->head_title,
                'transactions' => (int) $row->transactions,
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();

        $byMonth = (clone $query)
            ->selectRaw("DATE_FORMAT(pc.created_on, '%Y-%m') AS month, COUNT(*) AS transactions, SUM(pc.amount) AS amount")
            ->groupByRaw("DATE_FORMAT(pc.created_on, '%Y-%m')")
            ->orderByDesc('month')
            ->limit(36)
            ->get()
            ->map(static fn ($row) => [
                'month' => $row->month,
                'transactions' => (int) $row->transactions,
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();

        $withoutBill = (clone $query)
            ->where(static function ($inner): void {
                $inner->whereNull('pc.bill_image')->orWhere('pc.bill_image', '');
            })
            ->count();

        return [
            'count' => $total,
            'total_amount' => round($totalAmount, 2),
            'total_amount_means' => 'money recorded as spent. It is NOT a balance: this book records no '
                .'opening float, no top-up and no reimbursement, so nothing that came in is recorded '
                .'anywhere and no remaining figure can be derived.',
            'without_bill' => $withoutBill,
            'by_head' => $byHead,
            'by_month' => $byMonth,
            'rule' => $this->rule(),
        ];
    }

    /**
     * The two absences every petty cash answer has to carry.
     *
     * Written once and attached to both payloads, so the rule cannot drift between the
     * list and the totals.
     */
    private function rule(): string
    {
        return 'One row is one spend entered in the petty cash book. This table records NO approval of any '
            .'kind — no approver, no approved-at, no rejection, and no approval table exists in this '
            .'estate — so nothing here may be described as pending, awaiting approval, approved or '
            .'rejected. It also records NO opening float, top-up or reimbursement, so a balance or '
            .'remaining figure cannot be computed and must never be stated. `bill_attached` is whether a '
            .'scan of the bill is on file; a missing bill is a missing document, not a suspect '
            .'transaction or a person to accuse.';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['title_id'])) {
            $query->where('pc.title_id', (int) $filters['title_id']);
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('pc.created_on', $operator, $date);
            }
        }

        if (isset($filters['min_amount']) && is_numeric($filters['min_amount'])) {
            $query->where('pc.amount', '>=', (float) $filters['min_amount']);
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('pc.description', 'like', $needle)->orWhere('pcm.title', 'like', $needle);
            });
        }
    }

    /** The petty cash join, scoped at every hop that carries an institute. */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        return DB::table('petty_cash as pc')
            ->leftJoin('petty_cash_master as pcm', function ($join) use ($institute) {
                $join->on('pcm.id', '=', 'pc.title_id')->where('pcm.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'pc.user_id')->where('u.sub_institute_id', '=', $institute);
            })
            ->where('pc.sub_institute_id', $institute);
    }

    private function columns(): string
    {
        return "pc.id, pc.title_id, pc.description, pc.amount, pc.created_on, pc.user_id,
                pc.bill_image, pc.file_type,
                pcm.title AS head_title,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS entered_by_name";
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        $bill = trim((string) ($row->bill_image ?? ''));

        return [
            'transaction_id' => (int) $row->id,
            'head_id' => $row->title_id === null ? null : (int) $row->title_id,
            'head' => $row->head_title,
            'description' => $row->description,
            'amount' => round((float) $row->amount, 2),
            'recorded_on' => $row->created_on,
            'entered_by_user_id' => $row->user_id === null ? null : (int) $row->user_id,
            // Null when the user is not of this institute — a record to correct, not a
            // name to borrow from elsewhere.
            'entered_by' => trim((string) ($row->entered_by_name ?? '')) ?: null,
            // Whether a bill is on file, never the file itself.
            'bill_attached' => $bill !== '',
            'bill_type' => $bill === '' ? null : ($row->file_type ?: null),
        ];
    }
}
