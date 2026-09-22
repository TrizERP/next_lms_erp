<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Inventory & Asset Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `item_scan_details` is ONE BARCODE SCAN during a stock-take: the
 * code that was scanned, and whether the item was found. Several rows can carry
 * the same code — a stock-take that passes the same shelf twice produces two.
 *
 * ── TWO THINGS THIS FILE WAS REWRITTEN TO STOP DOING ────────────────────────
 *
 * 1. IT INVENTED CATEGORY NAMES. The previous version bucketed item codes by
 *    their first letter and labelled the buckets "Category A (Institutional
 *    Fixtures / Infrastructure)", "Category D (Departmental Hardware /
 *    Devices)" and "Category X (General / Miscellaneous Supplies)". Nothing in
 *    this schema says what an "A" prefix means. Those names were written into
 *    the code, not read from the data, and a reader had no way to tell. The
 *    prefixes are real and are still shown — as prefixes, with the note that
 *    their meaning is not recorded anywhere this module can read.
 *
 * 2. IT REPORTED 0% VERIFICATION WHERE NOTHING WAS VERIFIED OR REJECTED. At one
 *    institute every one of 1,105 scans carries an EMPTY `scan_status`. The old
 *    code counted anything that was not "yes" as unverified, so the screen said
 *    "Verification rate: 0%" and "1,105 assets missing or unverified" about a
 *    stock-take whose outcome column was simply never filled in. Where no scan
 *    carries a status, the verification figures are NULL and coverage says why.
 *
 * ── WHAT IS EXCLUDED, AND WHY ───────────────────────────────────────────────
 *
 * `inventory_item_master` holds ONE ROW across the two institutes that run
 * stock-takes, so a scan cannot be resolved to an item, a category or a
 * reorder level anywhere in this database. Everything that would need it —
 * stock against minimum, value, category — is therefore absent from this
 * module rather than approximated, and the coverage block says the master is
 * not populated.
 */
final class InventoryIntelligence
{
    private const SCAN_TABLE = 'item_scan_details';

    private const REQUISITION_TABLE = 'inventory_requisition_details';

    private const ITEM_MASTER_TABLE = 'inventory_item_master';

    /** Scans of the same code above this are worth reporting as re-scans. */
    public const RESCAN_ALERT_SHARE = 20.0;

    /** A stock-take this incomplete is worth naming. */
    public const VERIFICATION_ALERT = 95.0;

    private readonly string $tenantId;

    private readonly ?string $syear;

    /** @var array<string,mixed> */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /* -------------------------------------------------------- L0: coverage */

    /** @return array<string,mixed> */
    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    /** @return array<string,mixed> */
    private function computeCoverage(): array
    {
        $empty = ['sources' => [], 'counts' => []];

        if ($this->syear === null) {
            return $empty + [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute.',
            ];
        }

        if (! SchemaCache::hasTable(self::SCAN_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::SCAN_TABLE."' does not exist in this deployment.",
            ];
        }

        $shape = $this->scopedScans()
            ->selectRaw(
                'COUNT(*) as scans,
                 COUNT(DISTINCT NULLIF(TRIM(item_code), "")) as codes,
                 SUM(CASE WHEN TRIM(COALESCE(scan_status, "")) <> "" THEN 1 ELSE 0 END) as with_status,
                 SUM(CASE WHEN LOWER(TRIM(COALESCE(scan_status, ""))) = "yes" THEN 1 ELSE 0 END) as found,
                 SUM(CASE WHEN TRIM(COALESCE(item_code, "")) = "" THEN 1 ELSE 0 END) as no_code'
            )
            ->first();

        $scans = (int) ($shape->scans ?? 0);
        $requisitions = $this->requisitionCount();

        if ($scans === 0 && $requisitions === 0) {
            return $empty + [
                'available' => false,
                'reason' => "No stock-take scans or requisitions were recorded for academic year {$this->syear}.",
            ];
        }

        $itemMaster = SchemaCache::hasTable(self::ITEM_MASTER_TABLE)
            ? (int) DB::table(self::ITEM_MASTER_TABLE)->where('sub_institute_id', $this->tenantId)->count()
            : 0;

        return [
            'available' => true,
            'reason' => null,
            'sources' => [
                'scans' => $scans > 0,
                // The single most important flag on this screen: without it,
                // every verification figure is undefined rather than nought.
                'scanOutcomes' => (int) $shape->with_status > 0,
                'requisitions' => $requisitions > 0,
                'itemMaster' => $itemMaster > 0,
            ],
            'counts' => [
                'scans' => $scans,
                'itemCodes' => (int) $shape->codes,
                'scansWithOutcome' => (int) $shape->with_status,
                'itemsFound' => (int) $shape->found,
                'scansWithoutCode' => (int) $shape->no_code,
                'requisitions' => $requisitions,
                'itemsOnMaster' => $itemMaster,
            ],
        ];
    }

    /* -------------------------------------------------------- L1: position */

    /** @return array<string,mixed>|null */
    public function position(): ?array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    /** @return array<string,mixed>|null */
    private function computePosition(): ?array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return null;
        }

        $counts = $coverage['counts'];
        $scans = (int) $counts['scans'];
        $withOutcome = (int) $counts['scansWithOutcome'];
        $codes = (int) $counts['itemCodes'];

        return [
            'scans' => $scans,
            'itemCodes' => $codes,
            'repeatScans' => max(0, $scans - $codes),
            'repeatShare' => $scans > 0 ? round(max(0, $scans - $codes) / $scans * 100, 1) : null,
            'scansWithOutcome' => $withOutcome,
            // NULL, NOT ZERO. A stock-take whose outcome column was never filled
            // in has an UNKNOWN result; reporting it as 0% found describes a
            // catastrophe that did not happen.
            'itemsFound' => $withOutcome > 0 ? (int) $counts['itemsFound'] : null,
            'itemsNotFound' => $withOutcome > 0 ? $withOutcome - (int) $counts['itemsFound'] : null,
            'verificationRate' => $withOutcome > 0
                ? round((int) $counts['itemsFound'] / $withOutcome * 100, 1)
                : null,
            'outcomeCoverage' => $scans > 0 ? round($withOutcome / $scans * 100, 1) : null,
            'requisitions' => (int) $counts['requisitions'] > 0 ? (int) $counts['requisitions'] : null,
            'itemsOnMaster' => (int) $counts['itemsOnMaster'] > 0 ? (int) $counts['itemsOnMaster'] : null,
        ];
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Scans by the outcome recorded against them.
     *
     * Omitted entirely where no scan carries one — an empty "found / not found"
     * table reads as a result, and there is no result.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byOutcome(): array
    {
        return $this->memo['byOutcome'] ??= (function (): array {
            $coverage = $this->coverage();
            if (! $coverage['available'] || ! ($coverage['sources']['scanOutcomes'] ?? false)) {
                return [];
            }

            $rows = $this->scopedScans()
                ->selectRaw(
                    'CASE
                        WHEN TRIM(COALESCE(scan_status, "")) = "" THEN "Not recorded"
                        ELSE TRIM(scan_status)
                     END as outcome,
                     COUNT(*) as scans,
                     COUNT(DISTINCT NULLIF(TRIM(item_code), "")) as codes'
                )
                ->groupBy('outcome')
                ->orderByDesc('scans')
                ->get();

            $total = array_sum(array_map(static fn ($r) => (int) $r->scans, $rows->all()));

            return array_map(static fn ($row) => [
                'key' => substr(md5((string) $row->outcome), 0, 12),
                // The institute's own word for the outcome, verbatim. This
                // module does not decide that "Yes" means verified and "No"
                // means missing; it reports what was recorded.
                'label' => (string) $row->outcome,
                'scans' => (int) $row->scans,
                'codes' => (int) $row->codes,
                'share' => $total > 0 ? round((int) $row->scans / $total * 100, 1) : null,
            ], $rows->all());
        })();
    }

    /**
     * Scans by the prefix of the code, WITHOUT inventing a meaning for it.
     *
     * The prefixes are real and they concentrate: one institute's stock-take is
     * 8,648 codes beginning "A0" and 626 beginning "D0". What those stand for
     * is not recorded in any table this module can read, so the label is the
     * prefix and the description says so.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byCodePrefix(): array
    {
        return $this->memo['byPrefix'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $rows = $this->scopedScans()
                ->whereRaw('TRIM(COALESCE(item_code, "")) <> ""')
                ->selectRaw(
                    'UPPER(LEFT(TRIM(item_code), 2)) as prefix,
                     COUNT(*) as scans,
                     COUNT(DISTINCT TRIM(item_code)) as codes,
                     SUM(CASE WHEN LOWER(TRIM(COALESCE(scan_status, ""))) = "yes" THEN 1 ELSE 0 END) as found,
                     SUM(CASE WHEN TRIM(COALESCE(scan_status, "")) <> "" THEN 1 ELSE 0 END) as with_outcome'
                )
                ->groupBy('prefix')
                ->orderByDesc('scans')
                ->get();

            $total = array_sum(array_map(static fn ($r) => (int) $r->scans, $rows->all()));

            return array_map(static function ($row) use ($total) {
                $withOutcome = (int) $row->with_outcome;

                return [
                    'key' => (string) $row->prefix,
                    'label' => "Codes beginning “{$row->prefix}”",
                    'scans' => (int) $row->scans,
                    'codes' => (int) $row->codes,
                    'share' => $total > 0 ? round((int) $row->scans / $total * 100, 1) : null,
                    // Undefined where no scan in this prefix carries an outcome.
                    'verificationRate' => $withOutcome > 0
                        ? round((int) $row->found / $withOutcome * 100, 1)
                        : null,
                ];
            }, $rows->all());
        })();
    }

    /**
     * Requisitions by the status they are sitting in.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byRequisitionStatus(): array
    {
        return $this->memo['byRequisition'] ??= (function (): array {
            if (! $this->coverage()['available'] || $this->requisitionCount() === 0) {
                return [];
            }

            $rows = DB::table(self::REQUISITION_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->selectRaw(
                    'CASE
                        WHEN TRIM(COALESCE(requisition_status, "")) = "" THEN "No status recorded"
                        ELSE TRIM(requisition_status)
                     END as status,
                     COUNT(*) as line_count,
                     SUM(COALESCE(item_qty, 0)) as requested,
                     SUM(COALESCE(approved_qty, 0)) as approved'
                )
                ->groupBy('status')
                ->orderByDesc('line_count')
                ->get();

            $total = array_sum(array_map(static fn ($r) => (int) $r->line_count, $rows->all()));

            return array_map(static fn ($row) => [
                'key' => substr(md5((string) $row->status), 0, 12),
                'label' => (string) $row->status,
                'lines' => (int) $row->line_count,
                'requested' => (int) $row->requested,
                'approved' => (int) $row->approved,
                'share' => $total > 0 ? round((int) $row->line_count / $total * 100, 1) : null,
            ], $rows->all());
        })();
    }

    /* ------------------------------------------------------- the DQ ledger */

    /** @return array<string,mixed> */
    public function dataQuality(): array
    {
        return $this->memo['dataQuality'] ??= $this->computeDataQuality();
    }

    /** @return array<string,mixed> */
    private function computeDataQuality(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $counts = $coverage['counts'];
        $scans = (int) $counts['scans'];
        $noOutcome = $scans - (int) $counts['scansWithOutcome'];
        $repeats = max(0, $scans - (int) $counts['itemCodes']);

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'scans_without_outcome',
                    'label' => 'Scans with no outcome recorded',
                    'value' => $noOutcome,
                    'format' => 'count',
                    'sharePercent' => $scans > 0 ? round($noOutcome / $scans * 100, 2) : null,
                    'shareLabel' => 'of scans',
                    'state' => $noOutcome > 0 ? 'attention' : 'ok',
                    'note' => $noOutcome > 0
                        ? 'The item was scanned but nobody recorded whether it was found. These scans are excluded '
                            .'from the verification rate rather than counted against it — a blank outcome is unknown, '
                            .'not a missing asset.'
                        : 'Every scan records whether the item was found.',
                ],
                [
                    'key' => 'scans_without_code',
                    'label' => 'Scans with no item code',
                    'value' => (int) $counts['scansWithoutCode'],
                    'format' => 'count',
                    'sharePercent' => $scans > 0 ? round((int) $counts['scansWithoutCode'] / $scans * 100, 2) : null,
                    'shareLabel' => 'of scans',
                    'state' => (int) $counts['scansWithoutCode'] > 0 ? 'attention' : 'ok',
                    'note' => (int) $counts['scansWithoutCode'] > 0
                        ? 'A scan with no code cannot be matched to anything, so it counts towards the stock-take and '
                            .'identifies nothing.'
                        : 'Every scan carries an item code.',
                ],
                [
                    'key' => 'repeat_scans',
                    'label' => 'Codes scanned more than once',
                    'value' => $repeats,
                    'format' => 'count',
                    'sharePercent' => $scans > 0 ? round($repeats / $scans * 100, 2) : null,
                    'shareLabel' => 'of scans',
                    'state' => $repeats > 0 ? 'attention' : 'ok',
                    'note' => $repeats > 0
                        ? "{$scans} scans cover {$counts['itemCodes']} distinct codes. Repeats are normal in a "
                            .'stock-take that passes a location twice; they matter because the scan count is not the '
                            .'item count, and only the second is stock.'
                        : 'Every scan covers a distinct item code.',
                ],
                [
                    'key' => 'item_master_empty',
                    'label' => 'Items on the item master',
                    'value' => (int) $counts['itemsOnMaster'],
                    'format' => 'count',
                    'sharePercent' => null,
                    'state' => (int) $counts['itemsOnMaster'] === 0 ? 'attention' : 'ok',
                    'note' => (int) $counts['itemsOnMaster'] === 0
                        ? 'The item master holds no rows for this institute, so a scanned code cannot be resolved to '
                            .'an item name, a category or a reorder level. Everything that would need one is absent '
                            .'from this screen rather than approximated.'
                        : 'Scanned codes can be resolved against the item master.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    private function requisitionCount(): int
    {
        return $this->memo['requisitions'] ??= SchemaCache::hasTable(self::REQUISITION_TABLE)
            ? (int) DB::table(self::REQUISITION_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->count()
            : 0;
    }

    /** Every scan query starts here — tenant-scoped, year-scoped, and excluding deleted rows. */
    private function scopedScans(): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::SCAN_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', $this->syear)
            ->whereNull('deleted_at');
    }
}
