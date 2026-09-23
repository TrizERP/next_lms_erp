<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory, as the `inventory_*` tables record it.
 *
 * THIS ESTATE KEEPS NO RUNNING STOCK BALANCE. READ THIS BEFORE CHANGING ANYTHING HERE.
 *
 * `inventory_item_master.opening_stock` looks like a current stock level and is not one.
 * Tracing every write to it:
 *
 *   · `inventory_item_masterController` sets it when an item is created or edited.
 *   · `inventory_item_direct_purchaseController` ADDS to it on a purchase, and subtracts
 *     on delete to undo that purchase.
 *   · nothing else writes it. `requisitionApprovedController` only READS it for display.
 *
 * So issuing stock never decreases it. An item whose entire quantity has been allocated
 * still reports its full figure, which means the column systematically OVERSTATES what is
 * on the shelf — and the overstatement grows with every issue.
 *
 * That makes "which items are low on stock" and "what is out of stock" unanswerable from
 * this schema, and they are the two questions everybody asks an inventory system. Answering
 * them anyway would send somebody to a shelf that is empty, or stop a reorder for an item
 * that has run out.
 *
 * So nothing here returns "stock". `recorded_stock` is returned under that name, with
 * `approved_for_issue` — the quantity requisitions have actually approved, which IS
 * recorded — beside it, and the rule on every payload says the two have never been
 * reconciled. `at_or_below_minimum` compares the recorded figure to the recorded reorder
 * level and is labelled as exactly that.
 *
 * THE VENDOR COLUMN LIST IS AN ACCESS CONTROL
 *
 * `inventory_vendor_master` carries `bank_account_no`, `bank_ifsc_code`, `bank_name`,
 * `bank_branch`, `pan_no`, `tin_no` and `registration_no`. A purchase order needs the
 * vendor's NAME. None of the rest appears in `columns()` below and none may be added —
 * the same rule, for the same reason, as the staff card in `UserIcardService`.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read, and `syear` where the caller
 * carries an academic year. Every lookup that has an institute is joined on it, so a
 * category, vendor or requester from another school can never appear on this school's
 * records. `inventory_requisition_status_master` has no institute column — it is a
 * four-row global vocabulary (the statuses themselves), not anybody's data.
 */
class InventoryService
{
    /**
     * Items on the item master, with what is recorded about their quantity.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function items(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('inventory_item_master')) {
            return ['count' => 0, 'items' => [], 'note' => 'An inventory is not kept in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->itemQuery($context);

        foreach ([
            'category_id' => 'i.category_id',
            'sub_category_id' => 'i.sub_category_id',
            'item_type_id' => 'i.item_type_id',
            'item_id' => 'i.id',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('i.title', 'like', $needle)->orWhere('i.description', 'like', $needle);
            });
        }

        // The recorded figure at or below the recorded reorder level. NOT "low stock" —
        // see the class note. Named for what it compares.
        if (! empty($filters['at_or_below_minimum_only'])) {
            $query->whereRaw('COALESCE(i.opening_stock, 0) <= COALESCE(i.minimum_stock, 0)')
                ->whereRaw('COALESCE(i.minimum_stock, 0) > 0');
        }

        // Counted before the limit, so a page is never read as the whole item master.
        $total = (clone $query)->count();
        $atOrBelow = (clone $query)
            ->whereRaw('COALESCE(i.opening_stock, 0) <= COALESCE(i.minimum_stock, 0)')
            ->whereRaw('COALESCE(i.minimum_stock, 0) > 0')
            ->count();

        // Items whose category id points at a category this institute does not have. On
        // one live institute that is ALL 284 of its items — the categories were created
        // elsewhere — so the name comes back null rather than borrowed, and the count is
        // reported so the office can see the gap is the record's and not the reader's.
        $danglingCategory = (clone $query)
            ->whereNotNull('i.category_id')
            ->where('i.category_id', '>', 0)
            ->whereNull('cat.id')
            ->count();

        $rows = $query
            ->selectRaw($this->itemColumns())
            ->orderBy('cat.title')
            ->orderBy('i.title')
            ->limit($limit)
            ->get();

        $issued = $this->approvedForIssue($context, $rows->pluck('item_id')->map(static fn ($id) => (int) $id)->all());

        $items = $rows->map(function ($row) use ($issued) {
            $mapped = $this->mapItem($row);
            $mapped['approved_for_issue'] = $issued[$mapped['item_id']] ?? 0;

            return $mapped;
        })->all();

        return [
            'count' => $total,
            'row_count' => count($items),
            'academic_year' => $context->academicYear,
            'at_or_below_minimum' => $atOrBelow,
            'items_whose_category_is_not_in_this_institute' => $danglingCategory,
            'figures_cover' => 'every item matching these filters, not only the rows listed',
            'items' => $items,
            'rule' => $this->stockRule().' A null `category` means this institute has no category record for '
                .'that id; the name is never borrowed from another school, and '
                .'`items_whose_category_is_not_in_this_institute` is how many such rows matched.',
        ];
    }

    /**
     * Requisitions raised against the item master.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function requisitions(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('inventory_requisition_details')) {
            return ['count' => 0, 'requisitions' => [], 'note' => 'Inventory requisitions are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $institute = $context->selectedInstituteId;

        $query = DB::table('inventory_requisition_details as r')
            ->leftJoin('inventory_item_master as i', function ($join) use ($institute) {
                $join->on('i.id', '=', 'r.item_id')->where('i.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'r.requisition_by')->where('u.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as a', function ($join) use ($institute) {
                $join->on('a.id', '=', 'r.requisition_approved_by')->where('a.sub_institute_id', '=', $institute);
            })
            ->where('r.sub_institute_id', $institute);

        // A four-row global vocabulary rather than anybody's data, so it carries no
        // institute column and is joined without one.
        if (Schema::hasTable('inventory_requisition_status_master')) {
            $query->leftJoin('inventory_requisition_status_master as s', 's.id', '=', 'r.requisition_status');
        }

        if ($context->academicYear !== null) {
            $query->where('r.syear', $context->academicYear);
        }

        foreach (['item_id' => 'r.item_id', 'department_id' => 'r.department_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        $status = trim((string) ($filters['status'] ?? ''));

        if ($status !== '' && Schema::hasTable('inventory_requisition_status_master')) {
            $query->where('s.title', 'like', '%'.$status.'%');
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('r.requisition_date', $operator, $date);
            }
        }

        $total = (clone $query)->count();
        $awaitingApproval = (clone $query)
            ->where(static function ($inner): void {
                $inner->whereNull('r.requisition_approved_date')->orWhere('r.requisition_approved_date', '');
            })
            ->count();

        $statusTitle = Schema::hasTable('inventory_requisition_status_master') ? 's.title' : 'NULL';

        $rows = $query
            ->selectRaw("r.id, r.requisition_no, r.requisition_date, r.item_id, r.item_qty, r.item_unit,
                r.approved_qty, r.item_qty_in_stock, r.expected_delivery_time, r.requisition_status,
                r.remarks, r.requisition_approved_remarks, r.requisition_approved_date, r.department_id,
                i.title AS item_title,
                {$statusTitle} AS status_title,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS requested_by_name,
                CONCAT_WS(' ', a.first_name, a.middle_name, a.last_name) AS approved_by_name")
            ->orderByDesc('r.requisition_date')
            ->orderByDesc('r.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'no_approval_date_recorded' => $awaitingApproval,
            'figures_cover' => 'every requisition matching these filters, not only the rows listed',
            'requisitions' => $rows->map(static fn ($row) => [
                'requisition_id' => (int) $row->id,
                'requisition_no' => $row->requisition_no,
                'requisition_date' => $row->requisition_date,
                'item_id' => $row->item_id === null ? null : (int) $row->item_id,
                // Null when the item belongs to another institute — a record to correct,
                // not a name to borrow from elsewhere.
                'item' => $row->item_title,
                'quantity_requested' => $row->item_qty === null || $row->item_qty === '' ? null : (float) $row->item_qty,
                'quantity_approved' => $row->approved_qty === null || $row->approved_qty === '' ? null : (float) $row->approved_qty,
                'unit' => $row->item_unit ?: null,
                // The figure somebody typed on the requisition screen at the time. It is
                // a note on the form, not a reading of the shelf.
                'stock_noted_on_the_form' => $row->item_qty_in_stock === null || $row->item_qty_in_stock === ''
                    ? null
                    : (float) $row->item_qty_in_stock,
                'status' => $row->status_title,
                'requested_by' => trim((string) ($row->requested_by_name ?? '')) ?: null,
                'approved_by' => trim((string) ($row->approved_by_name ?? '')) ?: null,
                'approved_on' => self::date($row->requisition_approved_date),
                'approval_remarks' => $row->requisition_approved_remarks ?: null,
                'remarks' => $row->remarks ?: null,
                'expected_delivery' => self::date($row->expected_delivery_time),
            ])->all(),
            'rule' => 'One row is one item requested on one requisition. `quantity_approved` is what an '
                .'approver allowed; nothing records that the item was then handed over, so a requisition '
                .'is not proof of an issue. `stock_noted_on_the_form` is a figure typed on the requisition '
                .'screen at the time and is not a reading of the shelf. '.$this->stockRule(),
        ];
    }

    /**
     * Purchase orders raised on vendors.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function purchaseOrders(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('inventory_generate_po_details')) {
            return ['count' => 0, 'purchase_orders' => [], 'note' => 'Purchase orders are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $institute = $context->selectedInstituteId;

        $query = DB::table('inventory_generate_po_details as p')
            ->leftJoin('inventory_item_master as i', function ($join) use ($institute) {
                $join->on('i.id', '=', 'p.item_id')->where('i.sub_institute_id', '=', $institute);
            })
            ->leftJoin('inventory_vendor_master as v', function ($join) use ($institute) {
                $join->on('v.id', '=', 'p.vendor_id')->where('v.sub_institute_id', '=', $institute);
            })
            ->where('p.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            $query->where('p.syear', $context->academicYear);
        }

        foreach (['item_id' => 'p.item_id', 'vendor_id' => 'p.vendor_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        $poNumber = trim((string) ($filters['po_number'] ?? ''));

        if ($poNumber !== '') {
            $query->where('p.po_number', 'like', '%'.$poNumber.'%');
        }

        $total = (clone $query)->count();
        $totalAmount = (float) (clone $query)->sum('p.amount_per_item');

        $rows = $query
            // The vendor's NAME only. See the class note about the columns beside it.
            ->selectRaw("p.id, p.po_number, p.item_id, p.vendor_id, p.price, p.qty, p.amount,
                p.tax_amount_value, p.amount_per_item, p.transportation_charge, p.installation_charge,
                p.payment_terms, p.delivery_time, p.remarks,
                p.po_approval_status, p.po_approved_date, p.po_approval_remark, p.created_on,
                i.title AS item_title, v.vendor_name")
            ->orderByDesc('p.created_on')
            ->orderByDesc('p.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'total_amount' => round($totalAmount, 2),
            'figures_cover' => 'every purchase order line matching these filters, not only the rows listed',
            'purchase_orders' => $rows->map(static fn ($row) => [
                'po_line_id' => (int) $row->id,
                'po_number' => $row->po_number,
                'item_id' => $row->item_id === null ? null : (int) $row->item_id,
                'item' => $row->item_title,
                'vendor_id' => $row->vendor_id === null ? null : (int) $row->vendor_id,
                // Null when the vendor belongs to another institute — a record to correct,
                // not a name to borrow from elsewhere.
                'vendor' => $row->vendor_name,
                'quantity' => $row->qty === null || $row->qty === '' ? null : (float) $row->qty,
                'unit_price' => $row->price === null || $row->price === '' ? null : round((float) $row->price, 2),
                'line_amount' => $row->amount_per_item === null || $row->amount_per_item === ''
                    ? null
                    : round((float) $row->amount_per_item, 2),
                'approval_status' => $row->po_approval_status ?: null,
                'approved_on' => self::date($row->po_approved_date),
                'approval_remarks' => $row->po_approval_remark ?: null,
                'payment_terms' => $row->payment_terms ?: null,
                'delivery_time' => self::date($row->delivery_time),
                'raised_on' => self::date($row->created_on),
            ])->all(),
            'rule' => 'One row is one item line on one purchase order. A purchase order is an order, not a '
                .'delivery: what actually arrived is recorded separately against the receivable register, '
                .'and nothing here proves anything was received or paid for. The vendor\'s bank, PAN and '
                .'registration details are held on the vendor record and are NOT readable through this '
                .'tool.',
        ];
    }

    /**
     * A date, or null where the column holds MySQL's zero date.
     *
     * `0000-00-00 00:00:00` is what this schema stores when nothing was entered, and it
     * reaches a reader as a real date in the year zero — "expected delivery: 0000-00-00"
     * is worse than no answer, and any arithmetic on it is nonsense. Several columns here
     * carry it, so the normalisation is in one place.
     */
    private static function date(mixed $value): ?string
    {
        $date = trim((string) ($value ?? ''));

        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            return null;
        }

        return $date;
    }

    /**
     * The one sentence every inventory answer has to carry.
     *
     * Written once and attached to all three payloads, because the moment it appears on
     * one and not another is the moment somebody quotes a stock figure as fact.
     */
    private function stockRule(): string
    {
        return 'THIS ESTATE KEEPS NO RUNNING STOCK BALANCE. `recorded_stock` is the `opening_stock` column, '
            .'which is set when an item is created and INCREASED by a direct purchase, but is never '
            .'decreased when stock is issued — so it overstates what is on the shelf, and by more the more '
            .'has been issued. Never state that an item is in stock, out of stock, low on stock or '
            .'sufficient. `at_or_below_minimum` compares that same unreconciled figure to the recorded '
            .'reorder level and means only that. `approved_for_issue` is the quantity requisitions have '
            .'approved; it has never been reconciled against the stock figure, and subtracting one from '
            .'the other is not a stock count.';
    }

    /**
     * Quantity approved for issue per item, from the requisition register.
     *
     * A real recorded figure and the nearest thing this schema has to "what has gone out".
     * Returned beside the stock column rather than subtracted from it — see `stockRule()`.
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, float>
     */
    private function approvedForIssue(McpRequestContext $context, array $itemIds): array
    {
        if ($itemIds === [] || ! Schema::hasTable('inventory_requisition_details')) {
            return [];
        }

        $query = DB::table('inventory_requisition_details')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->whereIn('item_id', $itemIds);

        if ($context->academicYear !== null) {
            $query->where('syear', $context->academicYear);
        }

        $totals = [];

        foreach ($query->selectRaw('item_id, SUM(COALESCE(approved_qty, 0)) AS approved')->groupBy('item_id')->get() as $row) {
            $totals[(int) $row->item_id] = round((float) $row->approved, 2);
        }

        return $totals;
    }

    /** The item-master join, scoped at every hop that carries an institute. */
    private function itemQuery(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('inventory_item_master as i')
            ->leftJoin('inventory_item_category_master as cat', function ($join) use ($institute) {
                $join->on('cat.id', '=', 'i.category_id')->where('cat.sub_institute_id', '=', $institute);
            })
            ->leftJoin('inventory_item_sub_category_master as sub', function ($join) use ($institute) {
                $join->on('sub.id', '=', 'i.sub_category_id')->where('sub.sub_institute_id', '=', $institute);
            })
            ->leftJoin('inventory_item_type as t', function ($join) use ($institute) {
                $join->on('t.id', '=', 'i.item_type_id')->where('t.sub_institute_id', '=', $institute);
            })
            ->where('i.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            $query->where('i.syear', $context->academicYear);
        }

        return $query;
    }

    private function itemColumns(): string
    {
        return 'i.id AS item_id, i.title, i.description, i.opening_stock, i.minimum_stock,
                i.direct_purchase_stock, i.item_status, i.category_id, i.sub_category_id, i.item_type_id,
                cat.title AS category_title, sub.title AS sub_category_title, t.title AS item_type_title';
    }

    /** @return array<string, mixed> */
    private function mapItem(object $row): array
    {
        $recorded = $row->opening_stock === null || $row->opening_stock === '' ? null : (float) $row->opening_stock;
        $minimum = $row->minimum_stock === null || $row->minimum_stock === '' ? null : (float) $row->minimum_stock;

        return [
            'item_id' => (int) $row->item_id,
            'title' => $row->title,
            'description' => $row->description,
            'category_id' => $row->category_id === null ? null : (int) $row->category_id,
            // Null when the category belongs to another institute — a record to correct,
            // not a name to borrow from elsewhere.
            'category' => $row->category_title,
            'sub_category' => $row->sub_category_title,
            'item_type' => $row->item_type_title,
            // Deliberately NOT called `stock`. See the class note.
            'recorded_stock' => $recorded,
            'minimum_stock' => $minimum,
            // Only meaningful where a reorder level was actually set; a zero minimum is
            // not a reorder level and would make every item look fine.
            'at_or_below_minimum' => ($minimum !== null && $minimum > 0 && $recorded !== null)
                ? $recorded <= $minimum
                : null,
            'direct_purchase_stock' => $row->direct_purchase_stock === null || $row->direct_purchase_stock === ''
                ? null
                : (float) $row->direct_purchase_stock,
            'item_status' => $row->item_status ?: null,
        ];
    }
}
