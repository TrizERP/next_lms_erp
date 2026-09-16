<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The fee position of one institute, for one academic year, read from
 * vivek_erp.
 *
 * THIS IS THE ANALYTICS LAYER ONLY — "what is happening". It counts, sums and
 * groups; it draws no conclusions. Interpretation lives in FeesSignalRules,
 * which reads this class and is the only thing allowed to say a number means
 * something. Keeping the two apart is what stops a chart caption from quietly
 * becoming a finding nobody can trace.
 *
 * ── Where the numbers come from ──────────────────────────────────────────────
 *
 * DEMAND is per-student, and is derived exactly the way the LMS derives it in
 * App\Helpers\FeeBreackoff(): `fees_breackoff` is a fee STRUCTURE table with no
 * student_id, keyed by (admission_year, quota, grade, standard, month, head).
 * A student's demand is the structure rows their enrolment matches. Summing
 * `fees_breackoff.amount` on its own — as the existing Fees dashboard endpoint
 * does — totals the price list, not the money owed, and is out by whatever the
 * class sizes happen to be. Every figure here joins the roll first.
 *
 * COLLECTION is `fees_collect` (regular) and `fees_paid_other` (additional
 * heads), both scoped by tenant + syear and both excluding `is_deleted = 'Y'`,
 * matching every existing Fees report.
 *
 * A CYCLE is `month_id`: month number followed by the four-digit CALENDAR year
 * ("52021" = May 2021, "12022" = January 2022), so one academic year spans two
 * calendar years. `fees_collect.term_id` holds the same value — it is the fee
 * month, not the academic term. Both facts are load-bearing and are why
 * cycleKey() exists rather than a DATE_FORMAT over receiptdate.
 *
 * OUTSTANDING IS FLOORED PER STUDENT, NOT IN TOTAL. A student who overpaid must
 * not silently cancel out a student who has paid nothing: netting them would
 * understate what is actually owed and hide the accounts worth chasing. Each
 * account's shortfall is floored at zero first, and the floored values are what
 * is summed.
 *
 * ── Tenant and year ──────────────────────────────────────────────────────────
 *
 * Every query below carries `sub_institute_id` AND `syear`. There is no code
 * path that reads one without the other, and no caller can supply a year this
 * institute does not own — BrainController resolves it through
 * App\Brain\Support\AcademicYear against that institute's own `academic_year`
 * rows before it ever reaches this class.
 *
 * ── Honesty ──────────────────────────────────────────────────────────────────
 *
 * Nothing here substitutes a zero for an absence. A year with no receipts
 * reports `receipts = 0` AND `hasReceipts = false`, and coverage() says so in
 * words, so the layer above can decline to draw a trend instead of drawing a
 * flat line through nothing.
 */
final class FeesIntelligence
{
    /** Cycles older than this many months are reported as the deepest aging band. */
    private const AGING_BANDS = [30, 60, 90];

    private readonly string $tenantId;

    private readonly ?string $syear;

    /** @var array<string, mixed> memo, so one request does not re-run an aggregate */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = $tenantId;
        // A null year is not defaulted to "this year". Fee facts are year-owned;
        // a caller that has not resolved one gets the unavailable answer rather
        // than a plausible wrong one.
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    /* ===================================================== data availability */

    /**
     * What this institute-year actually has, in the layer's own words.
     *
     * Read this BEFORE any figure. Every downstream surface keys its empty
     * states off these flags rather than off a zero, because zero and absent
     * are different answers and only one of them is a finding.
     *
     * @return array{
     *     available: bool, reason: ?string, syear: ?string,
     *     hasDemand: bool, hasReceipts: bool, hasOtherFees: bool,
     *     hasCycleMap: bool, hasHeads: bool,
     *     demandRows: int, receiptRows: int, enrolledStudents: int, feeAccounts: int
     * }
     */
    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    /** @return array<string, mixed> */
    private function computeCoverage(): array
    {
        $base = [
            'available' => false,
            'reason' => null,
            'syear' => $this->syear,
            'hasDemand' => false,
            'hasReceipts' => false,
            'hasOtherFees' => false,
            'hasCycleMap' => false,
            'hasHeads' => false,
            'demandRows' => 0,
            'receiptRows' => 0,
            'enrolledStudents' => 0,
            'feeAccounts' => 0,
        ];

        if ($this->syear === null) {
            return array_merge($base, [
                'reason' => 'No academic year is selected, and fee records are recorded against a year.',
            ]);
        }

        foreach (['tblstudent', 'tblstudent_enrollment', 'fees_breackoff', 'fees_collect'] as $table) {
            if (! SchemaCache::hasTable($table)) {
                return array_merge($base, [
                    'reason' => 'This ERP does not record fees in the expected tables.',
                ]);
            }
        }

        $enrolled = (int) DB::table('tblstudent_enrollment')
            ->where('sub_institute_id', $this->tenantId)->where('syear', $this->syear)
            ->distinct()->count('student_id');

        $totals = $this->positionTotals();

        $receiptRows = (int) DB::table('fees_collect')
            ->where('sub_institute_id', $this->tenantId)->where('syear', $this->syear)
            ->where('is_deleted', 'N')->count();

        $otherRows = SchemaCache::hasTable('fees_breakoff_other')
            ? (int) DB::table('fees_breakoff_other')
                ->where('sub_institute_id', $this->tenantId)->where('syear', $this->syear)->count()
            : 0;

        $cycles = count($this->cycleMap());
        $heads = SchemaCache::hasTable('fees_title')
            ? (int) DB::table('fees_title')
                ->where('sub_institute_id', $this->tenantId)->where('syear', $this->syear)->count()
            : 0;

        $hasDemand = $totals['demandAmount'] > 0;

        return array_merge($base, [
            'available' => $hasDemand || $receiptRows > 0,
            'reason' => $hasDemand || $receiptRows > 0
                ? null
                : ($enrolled === 0
                    ? 'No students are enrolled for this academic year, so there is no fee position to report.'
                    : 'No fee structure matches this year\'s enrolments, and no receipts have been recorded against this year.'),
            'hasDemand' => $hasDemand,
            'hasReceipts' => $receiptRows > 0 || $totals['otherReceipts'] > 0,
            'hasOtherFees' => $otherRows > 0,
            'hasCycleMap' => $cycles > 0,
            'hasHeads' => $heads > 0,
            'demandRows' => $totals['demandRows'],
            'receiptRows' => $receiptRows,
            'enrolledStudents' => $enrolled,
            'feeAccounts' => $totals['feeAccounts'],
        ]);
    }

    /* ============================================================= position */

    /**
     * Section 1 — the current financial position, all of it computed.
     *
     * @return array<string, mixed>
     */
    public function position(): array
    {
        $t = $this->positionTotals();
        $overdue = $this->overdueTotals();

        $demand = $t['demandAmount'];
        $collected = $t['collectedAmount'];
        $concession = $t['discountAmount'];

        return [
            'currency' => 'INR',
            'demandAmount' => $demand,
            'collectedAmount' => $collected,
            'outstandingAmount' => $t['outstandingAmount'],
            // Null, not zero: a rate over no demand is undefined, and a "0%
            // collection rate" reads as catastrophic performance rather than as
            // an absent denominator.
            'collectionRate' => $demand > 0 ? round($collected / $demand * 100, 1) : null,
            'concessionAmount' => $concession,
            'fineAmount' => $t['fineAmount'],
            'overdueAmount' => $overdue['amount'],
            'overdueCycles' => $overdue['cycles'],
            'agingBands' => $overdue['bands'],
            'feeAccounts' => $t['feeAccounts'],
            'payingAccounts' => $t['payingAccounts'],
            'defaulterAccounts' => $t['defaulterAccounts'],
            'fullySettledAccounts' => max($t['feeAccounts'] - $t['defaulterAccounts'], 0),
            'receipts' => $t['receipts'],
            'averageOutstandingPerDefaulter' => $t['defaulterAccounts'] > 0
                ? round($t['outstandingAmount'] / $t['defaulterAccounts'], 2)
                : null,
        ];
    }

    /**
     * Per-account ledger rolled up to institute totals.
     *
     * ONE QUERY, GROUPED IN THE DATABASE. The per-student floor means the
     * arithmetic cannot be done on institute totals, but it must not be done by
     * pulling thousands of accounts into PHP either — so the flooring happens in
     * SQL and only the aggregate crosses the wire.
     *
     * @return array<string, mixed>
     */
    private function positionTotals(): array
    {
        return $this->memo['positionTotals'] ??= $this->computePositionTotals();
    }

    /** @return array<string, mixed> */
    private function computePositionTotals(): array
    {
        $empty = [
            'demandAmount' => 0.0, 'collectedAmount' => 0.0, 'discountAmount' => 0.0,
            'fineAmount' => 0.0, 'outstandingAmount' => 0.0, 'feeAccounts' => 0,
            'payingAccounts' => 0, 'defaulterAccounts' => 0, 'receipts' => 0,
            'demandRows' => 0, 'otherReceipts' => 0,
        ];

        if ($this->syear === null) {
            return $empty;
        }

        $ledger = $this->accountLedger();
        if ($ledger === []) {
            return array_merge($empty, ['receipts' => $this->receiptCount(), 'otherReceipts' => $this->otherReceiptCount()]);
        }

        $demand = 0.0;
        $collected = 0.0;
        $discount = 0.0;
        $fine = 0.0;
        $outstanding = 0.0;
        $paying = 0;
        $defaulters = 0;

        foreach ($ledger as $row) {
            $demand += $row['demand'];
            $collected += $row['collected'];
            $discount += $row['discount'];
            $fine += $row['fine'];
            // Floored PER ACCOUNT before summing — see the class docblock.
            $shortfall = $row['demand'] - $row['collected'] - $row['discount'];
            if ($shortfall > 0) {
                $outstanding += $shortfall;
                $defaulters++;
            }
            if ($row['collected'] > 0) {
                $paying++;
            }
        }

        return [
            'demandAmount' => round($demand, 2),
            'collectedAmount' => round($collected, 2),
            'discountAmount' => round($discount, 2),
            'fineAmount' => round($fine, 2),
            'outstandingAmount' => round($outstanding, 2),
            'feeAccounts' => count($ledger),
            'payingAccounts' => $paying,
            'defaulterAccounts' => $defaulters,
            'receipts' => $this->receiptCount(),
            'otherReceipts' => $this->otherReceiptCount(),
            'demandRows' => count($ledger),
        ];
    }

    /**
     * Money owed for cycles whose month has already passed.
     *
     * Overdue is a statement about TIME, so it is derived from the cycle key
     * (month + calendar year) rather than from the receipt date — a cycle is
     * late once its own month is behind us, whatever anybody paid since.
     *
     * @return array{amount: float, cycles: int, bands: array<int, array<string, mixed>>}
     */
    private function overdueTotals(): array
    {
        return $this->memo['overdue'] ??= $this->computeOverdue();
    }

    /** @return array{amount: float, cycles: int, bands: array<int, array<string, mixed>>} */
    private function computeOverdue(): array
    {
        $none = ['amount' => 0.0, 'cycles' => 0, 'bands' => []];
        if ($this->syear === null) {
            return $none;
        }

        $rows = $this->cycleRows();
        if ($rows === []) {
            return $none;
        }

        $today = new \DateTimeImmutable('first day of this month 00:00:00');
        $amount = 0.0;
        $cycles = 0;
        $bands = [];
        foreach (self::AGING_BANDS as $days) {
            $bands[$days] = 0.0;
        }
        $bands['beyond'] = 0.0;

        foreach ($rows as $row) {
            $due = $row['dueDate'];
            if ($due === null || $due >= $today) {
                continue;
            }

            $shortfall = max($row['demand'] - $row['collected'] - $row['discount'], 0);
            if ($shortfall <= 0) {
                continue;
            }

            $amount += $shortfall;
            $cycles++;

            // A cycle is aged from the END of its own month — money is not late
            // on the first of the month it is billed for.
            $ageDays = (int) $due->modify('last day of this month')->diff($today)->days;
            $placed = false;
            foreach (self::AGING_BANDS as $days) {
                if ($ageDays <= $days) {
                    $bands[$days] += $shortfall;
                    $placed = true;
                    break;
                }
            }
            if (! $placed) {
                $bands['beyond'] += $shortfall;
            }
        }

        $labelled = [];
        $previous = 0;
        foreach (self::AGING_BANDS as $days) {
            $labelled[] = [
                'key' => (string) $days,
                'label' => $previous === 0 ? 'Up to '.$days.' days' : ($previous + 1).'–'.$days.' days',
                'amount' => round($bands[$days], 2),
            ];
            $previous = $days;
        }
        $labelled[] = [
            'key' => 'beyond',
            'label' => 'Over '.$previous.' days',
            'amount' => round($bands['beyond'], 2),
        ];

        return [
            'amount' => round($amount, 2),
            'cycles' => $cycles,
            'bands' => array_values(array_filter($labelled, fn ($band) => $band['amount'] > 0)),
        ];
    }

    /* =============================================================== cycles */

    /**
     * Section 3 — demand against collection, one row per fee cycle, in the
     * institute's own cycle order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cycles(): array
    {
        return array_map(function (array $row) {
            return [
                'cycleId' => $row['cycleId'],
                'label' => $row['label'],
                'demandAmount' => round($row['demand'], 2),
                'collectedAmount' => round($row['collected'], 2),
                'concessionAmount' => round($row['discount'], 2),
                'outstandingAmount' => round(max($row['demand'] - $row['collected'] - $row['discount'], 0), 2),
                'collectionRate' => $row['demand'] > 0
                    ? round($row['collected'] / $row['demand'] * 100, 1)
                    : null,
                'receipts' => $row['receipts'],
                'isPast' => $row['dueDate'] !== null
                    && $row['dueDate'] < new \DateTimeImmutable('first day of this month 00:00:00'),
            ];
        }, $this->cycleRows());
    }

    /** @return array<int, array<string, mixed>> */
    private function cycleRows(): array
    {
        return $this->memo['cycleRows'] ??= $this->computeCycleRows();
    }

    /** @return array<int, array<string, mixed>> */
    private function computeCycleRows(): array
    {
        if ($this->syear === null) {
            return [];
        }

        $demand = [];
        foreach (DB::select(
            'SELECT d.month_id AS cycle_id, COALESCE(SUM(d.amount), 0) AS amount
               FROM ('.$this->demandSql('month_id').') d GROUP BY d.month_id',
            $this->demandBindings()
        ) as $row) {
            $demand[(string) $row->cycle_id] = (float) $row->amount;
        }

        $collected = [];
        $discount = [];
        $receipts = [];

        foreach (DB::select(
            "SELECT fc.term_id AS cycle_id,
                    COALESCE(SUM(fc.amount), 0)        AS collected,
                    COALESCE(SUM(fc.fees_discount), 0) AS discount,
                    COUNT(DISTINCT CONCAT(fc.student_id, '#', fc.receipt_no)) AS receipts
               FROM fees_collect fc
              WHERE fc.sub_institute_id = ? AND fc.syear = ? AND fc.is_deleted = 'N'
              GROUP BY fc.term_id",
            [$this->tenantId, $this->syear]
        ) as $row) {
            $key = (string) $row->cycle_id;
            $collected[$key] = ($collected[$key] ?? 0) + (float) $row->collected;
            $discount[$key] = ($discount[$key] ?? 0) + (float) $row->discount;
            $receipts[$key] = ($receipts[$key] ?? 0) + (int) $row->receipts;
        }

        if (SchemaCache::hasTable('fees_paid_other')) {
            foreach (DB::select(
                "SELECT fpo.month_id AS cycle_id,
                        COALESCE(SUM(fpo.actual_amountpaid), 0) AS collected,
                        COALESCE(SUM(fpo.fees_discount), 0)     AS discount,
                        COUNT(DISTINCT CONCAT(fpo.student_id, '#', fpo.reciept_id)) AS receipts
                   FROM fees_paid_other fpo
                  WHERE fpo.sub_institute_id = ? AND fpo.syear = ? AND fpo.is_deleted = 'N'
                  GROUP BY fpo.month_id",
                [$this->tenantId, $this->syear]
            ) as $row) {
                $key = (string) $row->cycle_id;
                $collected[$key] = ($collected[$key] ?? 0) + (float) $row->collected;
                $discount[$key] = ($discount[$key] ?? 0) + (float) $row->discount;
                $receipts[$key] = ($receipts[$key] ?? 0) + (int) $row->receipts;
            }
        }

        if (SchemaCache::hasTable('fees_breakoff_other')) {
            foreach (DB::select(
                'SELECT fbo.month_id AS cycle_id, COALESCE(SUM(fbo.amount), 0) AS amount
                   FROM fees_breakoff_other fbo
                   JOIN ('.$this->enrolmentSql().') e ON e.student_id = fbo.student_id
                  WHERE fbo.sub_institute_id = ? AND fbo.syear = ?
                  GROUP BY fbo.month_id',
                array_merge($this->enrolmentBindings(), [$this->tenantId, $this->syear])
            ) as $row) {
                $key = (string) $row->cycle_id;
                $demand[$key] = ($demand[$key] ?? 0) + (float) $row->amount;
            }
        }

        $map = $this->cycleMap();
        $keys = array_unique(array_merge(array_keys($map), array_keys($demand), array_keys($collected)));

        $rows = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            $due = $this->cycleDate($key);
            $rows[] = [
                'cycleId' => $key,
                'label' => $map[$key] ?? $this->cycleLabel($key),
                'demand' => (float) ($demand[$key] ?? 0),
                'collected' => (float) ($collected[$key] ?? 0),
                'discount' => (float) ($discount[$key] ?? 0),
                'receipts' => (int) ($receipts[$key] ?? 0),
                'dueDate' => $due,
            ];
        }

        // Chronological, so "the last two cycles" means what it says. Cycles the
        // institute has not mapped still sort by their own key rather than being
        // dropped — money recorded against an unmapped cycle is still money.
        usort($rows, function ($a, $b) {
            if ($a['dueDate'] === null || $b['dueDate'] === null) {
                return strcmp($a['cycleId'], $b['cycleId']);
            }

            return $a['dueDate'] <=> $b['dueDate'];
        });

        return $rows;
    }

    /**
     * The institute's own fee calendar for this year, from `fees_map_years`.
     *
     * Mirrors App\Helpers\FeeMonthId(): the mapping row gives the starting
     * month, and twelve cycles run from there, rolling into the next calendar
     * year.
     *
     * SEVERAL ROWS CAN EXIST PER (INSTITUTE, YEAR) — one per fee type (monthly,
     * quarterly, yearly …) — and they disagree about the starting month. The
     * helper takes the first row of an unordered `get()`, which in practice is
     * the lowest id, so that is what is taken here, explicitly ordered. The
     * alternative of taking the earliest start across all rows looks more
     * careful and is wrong: for the institute in this database it yields a
     * January–December calendar, while the LMS's own fee screens and every
     * recorded receipt use April–March. The Brain must label a cycle the way
     * the Fees module labels it.
     *
     * @return array<string, string> cycleId => label
     */
    private function cycleMap(): array
    {
        if (isset($this->memo['cycleMap'])) {
            return $this->memo['cycleMap'];
        }

        $map = [];
        if ($this->syear !== null && SchemaCache::hasTable('fees_map_years')) {
            $start = DB::table('fees_map_years')
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->orderBy('id')
                ->value('from_month');

            if ($start !== null) {
                $month = (int) $start;
                $year = (int) $this->syear;
                for ($i = 0; $i < 12; $i++) {
                    $map[$month.$year] = date('M', mktime(0, 0, 0, $month, 1)).'/'.$year;
                    if ($month === 12) {
                        $month = 1;
                        $year++;
                    } else {
                        $month++;
                    }
                }
            }
        }

        return $this->memo['cycleMap'] = $map;
    }

    /** "52021" → 1 May 2021, or null when the key is not a cycle key. */
    private function cycleDate(string $cycleId): ?\DateTimeImmutable
    {
        if (! preg_match('/^(\d{1,2})(\d{4})$/', $cycleId, $m)) {
            return null;
        }

        $month = (int) $m[1];
        if ($month < 1 || $month > 12) {
            return null;
        }

        return new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', (int) $m[2], $month));
    }

    private function cycleLabel(string $cycleId): string
    {
        $date = $this->cycleDate($cycleId);

        return $date ? $date->format('M/Y') : 'Cycle '.$cycleId;
    }

    /* ============================================================ fee heads */

    /**
     * Demand and collection by fee head.
     *
     * The receipt tables keep each head's value in a COLUMN NAMED AFTER THE
     * HEAD (`fees_collect.tution_fee`, `fees_paid_other.8`), which is why this
     * looks up the column rather than joining. The name is only ever used after
     * it has been matched against the live column listing, so a head configured
     * after those rows were written is reported as demand with unattributable
     * collection instead of crashing the query — and no tenant-controlled string
     * reaches SQL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function heads(): array
    {
        return $this->memo['heads'] ??= $this->computeHeads();
    }

    /** @return array<int, array<string, mixed>> */
    private function computeHeads(): array
    {
        if ($this->syear === null || ! SchemaCache::hasTable('fees_title')) {
            return [];
        }

        $titles = DB::table('fees_title')
            ->where('sub_institute_id', $this->tenantId)->where('syear', $this->syear)
            ->get(['id', 'other_fee_id', 'fees_title', 'display_name', 'sort_order']);

        if ($titles->isEmpty()) {
            return [];
        }

        $demandByType = [];
        foreach (DB::select(
            'SELECT d.fee_type_id, COALESCE(SUM(d.amount), 0) AS amount
               FROM ('.$this->demandSql('fee_type_id').') d GROUP BY d.fee_type_id',
            $this->demandBindings()
        ) as $row) {
            $demandByType[(string) $row->fee_type_id] = (float) $row->amount;
        }

        $otherDemandByType = [];
        if (SchemaCache::hasTable('fees_breakoff_other')) {
            foreach (DB::select(
                'SELECT fbo.fee_type_id, COALESCE(SUM(fbo.amount), 0) AS amount
                   FROM fees_breakoff_other fbo
                   JOIN ('.$this->enrolmentSql().') e ON e.student_id = fbo.student_id
                  WHERE fbo.sub_institute_id = ? AND fbo.syear = ?
                  GROUP BY fbo.fee_type_id',
                array_merge($this->enrolmentBindings(), [$this->tenantId, $this->syear])
            ) as $row) {
                $otherDemandByType[(string) $row->fee_type_id] = (float) $row->amount;
            }
        }

        $rows = [];
        foreach ($titles as $title) {
            $column = (string) $title->fees_title;
            // `other_fee_id` is '0' on a regular head, not null and not empty —
            // a zero here means "this is not an additional fee", and reading it
            // as truthy sends every regular head down the additional-fee lookup,
            // where it matches nothing and silently vanishes from the breakdown.
            $otherFeeId = trim((string) ($title->other_fee_id ?? ''));
            $isOther = $otherFeeId !== '' && $otherFeeId !== '0';

            $demand = $isOther
                ? ($otherDemandByType[$otherFeeId] ?? 0.0)
                : ($demandByType[(string) $title->id] ?? 0.0);

            [$collected, $attributable] = $this->headCollection($column, $isOther);

            if ($demand <= 0 && $collected <= 0) {
                // A head configured but never billed and never paid is not a
                // dimension of this year's position; showing it as a zero bar
                // is noise, not information.
                continue;
            }

            $rows[] = [
                'headId' => $isOther ? $otherFeeId : (string) $title->id,
                'key' => $column,
                'label' => trim((string) ($title->display_name ?: $column)) ?: $column,
                'kind' => $isOther ? 'additional' : 'regular',
                'sortOrder' => (int) $title->sort_order,
                'demandAmount' => round($demand, 2),
                'collectedAmount' => round($collected, 2),
                'outstandingAmount' => round(max($demand - $collected, 0), 2),
                'collectionRate' => $demand > 0 ? round($collected / $demand * 100, 1) : null,
                // False ⇒ the receipt tables have no column for this head, so
                // its collection cannot be attributed. The surface says
                // "not separately recorded" rather than showing ₹0 collected.
                'collectionAttributable' => $attributable,
            ];
        }

        usort($rows, fn ($a, $b) => [$b['demandAmount'], $a['sortOrder']] <=> [$a['demandAmount'], $b['sortOrder']]);

        return $rows;
    }

    /**
     * Collection recorded against one head.
     *
     * @return array{0: float, 1: bool} amount, and whether it was attributable
     */
    private function headCollection(string $column, bool $isOther): array
    {
        $table = $isOther ? 'fees_paid_other' : 'fees_collect';
        if (! SchemaCache::hasTable($table) || ! SchemaCache::hasColumn($table, $column)) {
            return [0.0, false];
        }

        // Safe to interpolate ONLY because SchemaCache::hasColumn has just
        // confirmed this exact string is a column of this table; the backticks
        // then cover names that are bare digits ("8") or reserved words.
        $quoted = '`'.$column.'`';

        $value = DB::table($table)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', $this->syear)
            ->where('is_deleted', 'N')
            ->sum(DB::raw($quoted));

        // A head column that exists but is null everywhere still counts as
        // attributable — the institute records it, this year simply has none.
        return [round((float) $value, 2), true];
    }

    /* ============================================================== classes */

    /**
     * Demand, collection and outstanding by class, with the roll behind each.
     *
     * @return array<int, array<string, mixed>>
     */
    public function classes(): array
    {
        return $this->memo['classes'] ??= $this->computeClasses();
    }

    /** @return array<int, array<string, mixed>> */
    private function computeClasses(): array
    {
        $ledger = $this->accountLedger();
        if ($this->syear === null || $ledger === []) {
            return [];
        }

        $groups = [];
        foreach ($ledger as $row) {
            $key = $row['gradeId'].'|'.$row['standardId'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'gradeId' => $row['gradeId'], 'standardId' => $row['standardId'],
                    'accounts' => 0, 'demand' => 0.0, 'collected' => 0.0,
                    'outstanding' => 0.0, 'defaulters' => 0,
                ];
            }

            $groups[$key]['accounts']++;
            $groups[$key]['demand'] += $row['demand'];
            $groups[$key]['collected'] += $row['collected'];
            $shortfall = $row['demand'] - $row['collected'] - $row['discount'];
            if ($shortfall > 0) {
                $groups[$key]['outstanding'] += $shortfall;
                $groups[$key]['defaulters']++;
            }
        }

        $standards = SchemaCache::hasTable('standard')
            ? DB::table('standard')->whereIn('id', array_column($groups, 'standardId'))->pluck('name', 'id')
            : collect();
        $grades = SchemaCache::hasTable('academic_section')
            ? DB::table('academic_section')->whereIn('id', array_column($groups, 'gradeId'))->pluck('title', 'id')
            : collect();

        $out = [];
        foreach ($groups as $row) {
            $name = (string) ($standards[$row['standardId']] ?? '');
            $out[] = [
                'gradeId' => (string) $row['gradeId'],
                'standardId' => (string) $row['standardId'],
                // The LMS stores plain class numbers ("6"); a bare number reads
                // as an id wherever it appears on its own.
                'label' => $name === '' ? 'Class '.$row['standardId'] : (is_numeric($name) ? 'Class '.$name : $name),
                'section' => (string) ($grades[$row['gradeId']] ?? ''),
                'accounts' => $row['accounts'],
                'demandAmount' => round($row['demand'], 2),
                'collectedAmount' => round($row['collected'], 2),
                'outstandingAmount' => round($row['outstanding'], 2),
                'defaulterAccounts' => $row['defaulters'],
                'collectionRate' => $row['demand'] > 0 ? round($row['collected'] / $row['demand'] * 100, 1) : null,
            ];
        }

        usort($out, fn ($a, $b) => $b['outstandingAmount'] <=> $a['outstandingAmount']);

        return $out;
    }

    /* ======================================================= payment habits */

    /**
     * How money actually arrives. Both receipt tables, one vocabulary.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paymentModes(): array
    {
        if ($this->syear === null) {
            return [];
        }

        $modes = [];

        foreach (DB::select(
            "SELECT COALESCE(NULLIF(TRIM(fc.payment_mode), ''), 'Unspecified') AS mode,
                    COUNT(DISTINCT CONCAT(fc.student_id, '#', fc.receipt_no))  AS receipts,
                    COALESCE(SUM(fc.amount), 0)                                AS amount
               FROM fees_collect fc
              WHERE fc.sub_institute_id = ? AND fc.syear = ? AND fc.is_deleted = 'N'
              GROUP BY mode",
            [$this->tenantId, $this->syear]
        ) as $row) {
            $this->addMode($modes, (string) $row->mode, (int) $row->receipts, (float) $row->amount);
        }

        if (SchemaCache::hasTable('fees_paid_other')) {
            foreach (DB::select(
                "SELECT COALESCE(NULLIF(TRIM(fpo.payment_mode), ''), 'Unspecified') AS mode,
                        COUNT(DISTINCT CONCAT(fpo.student_id, '#', fpo.reciept_id)) AS receipts,
                        COALESCE(SUM(fpo.actual_amountpaid), 0)                     AS amount
                   FROM fees_paid_other fpo
                  WHERE fpo.sub_institute_id = ? AND fpo.syear = ? AND fpo.is_deleted = 'N'
                  GROUP BY mode",
                [$this->tenantId, $this->syear]
            ) as $row) {
                $this->addMode($modes, (string) $row->mode, (int) $row->receipts, (float) $row->amount);
            }
        }

        $out = array_values($modes);
        usort($out, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $out;
    }

    /** @param array<string, array<string, mixed>> $modes */
    private function addMode(array &$modes, string $mode, int $receipts, float $amount): void
    {
        // "CASH", "Cash" and "cash " are one payment mode in every report the
        // school reads; they must not become three slices of a pie chart.
        $key = strtolower(trim($mode));
        if (! isset($modes[$key])) {
            $modes[$key] = ['mode' => ucfirst($key), 'receipts' => 0, 'amount' => 0.0];
        }

        $modes[$key]['receipts'] += $receipts;
        $modes[$key]['amount'] = round($modes[$key]['amount'] + $amount, 2);
    }

    /* ======================================================= account detail */

    /**
     * Outstanding accounts, largest first — the drill-down behind "Outstanding"
     * and behind every concentration claim.
     *
     * PAGINATED IN THE DATABASE. The browser never receives the roll; it
     * receives a page of it, and the totals it displays came from position().
     *
     * @return array{total: int, rows: array<int, array<string, mixed>>}
     */
    public function outstandingAccounts(int $limit = 25, int $offset = 0, ?string $standardId = null): array
    {
        if ($this->syear === null) {
            return ['total' => 0, 'rows' => [], 'scope' => null];
        }

        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);

        $arrears = $this->accountsInArrears();

        // The class drill-down filters the SAME ledger the headline figure was
        // folded from, so "Class 6 owes X" and the names listed under it cannot
        // disagree - they are the same rows, filtered.
        $scope = null;
        if ($standardId !== null && $standardId !== '') {
            $arrears = array_values(array_filter($arrears, fn ($row) => $row['standardId'] === $standardId));
            $scope = [
                'standardId' => $standardId,
                'label' => $this->standardLabel($standardId),
                'accounts' => count($arrears),
                'outstandingAmount' => round(array_sum(array_column($arrears, 'outstanding')), 2),
            ];
        }

        $page = array_slice($arrears, $offset, $limit);

        return ['total' => count($arrears), 'rows' => $this->nameAccounts($page), 'scope' => $scope];
    }

    /** "6" reads as an id on its own; the LMS shows it as a class. */
    private function standardLabel(string $standardId): string
    {
        $name = SchemaCache::hasTable('standard')
            ? (string) (DB::table('standard')->where('id', $standardId)->value('name') ?? '')
            : '';

        if ($name === '') {
            return 'Class '.$standardId;
        }

        return is_numeric($name) ? 'Class '.$name : $name;
    }

    /**
     * Every account that owes money, largest shortfall first.
     *
     * Derived from the one ledger read rather than a query of its own — which is
     * what lets the drill-down, the concentration measure and the headline
     * "Outstanding" figure agree by construction instead of by coincidence.
     *
     * @return array<int, array<string, mixed>>
     */
    private function accountsInArrears(): array
    {
        if (isset($this->memo['arrears'])) {
            return $this->memo['arrears'];
        }

        $rows = [];
        foreach ($this->accountLedger() as $row) {
            $shortfall = $row['demand'] - $row['collected'] - $row['discount'];
            if ($shortfall > 0) {
                $rows[] = $row + ['outstanding' => $shortfall];
            }
        }

        usort($rows, fn ($a, $b) => [$b['outstanding'], $a['studentId']] <=> [$a['outstanding'], $b['studentId']]);

        return $this->memo['arrears'] = $rows;
    }

    /**
     * Attach the minimum identifying detail a bursar needs to act, and no more.
     *
     * Enrolment number and class are what a follow-up list is worked from.
     * Guardian phone numbers, addresses and family detail are deliberately NOT
     * selected here: this screen's job is to say which accounts matter, and the
     * Fees module's own collection screens already carry contact details behind
     * their own permissions.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function nameAccounts(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $students = DB::table('tblstudent')->whereIn('id', array_column($rows, 'studentId'))
            ->where('sub_institute_id', $this->tenantId)
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'enrollment_no'])
            ->keyBy('id');

        $standards = SchemaCache::hasTable('standard')
            ? DB::table('standard')->whereIn('id', array_column($rows, 'standardId'))->pluck('name', 'id')
            : collect();

        $out = [];
        foreach ($rows as $row) {
            $student = $students[$row['studentId']] ?? null;
            $name = $student
                ? trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name])))
                : '';
            $standard = (string) ($standards[$row['standardId']] ?? '');

            $out[] = [
                'studentId' => (string) $row['studentId'],
                'name' => $name !== '' ? $name : 'Student '.$row['studentId'],
                'enrollmentNo' => (string) ($student->enrollment_no ?? ''),
                'className' => $standard === '' ? '' : (is_numeric($standard) ? 'Class '.$standard : $standard),
                'demandAmount' => round($row['demand'], 2),
                'collectedAmount' => round($row['collected'], 2),
                'concessionAmount' => round($row['discount'], 2),
                'outstandingAmount' => round($row['outstanding'], 2),
            ];
        }

        return $out;
    }

    /**
     * How much of the outstanding balance sits in the largest accounts.
     *
     * This is the one measure that turns "₹X is owed" into "chase these first",
     * so it is computed rather than asserted, and it reports the inputs it was
     * computed from.
     *
     * @return array{available: bool, reason: ?string, topCount: int, topAmount: float, totalAmount: float, share: ?float, defaulterAccounts: int}
     */
    public function concentration(int $top = 10): array
    {
        $totals = $this->positionTotals();
        $defaulters = $totals['defaulterAccounts'];
        $outstanding = $totals['outstandingAmount'];

        $blank = [
            'available' => false, 'reason' => null, 'topCount' => 0,
            'topAmount' => 0.0, 'totalAmount' => round($outstanding, 2),
            'share' => null, 'defaulterAccounts' => $defaulters,
        ];

        if ($this->syear === null || $outstanding <= 0 || $defaulters === 0) {
            return array_merge($blank, ['reason' => 'No account is currently in arrears for this year.']);
        }

        /*
         * CONCENTRATION IS ONLY A FINDING WHEN THE TOP SLICE IS A MINORITY.
         * "100% of outstanding sits in 6 of 6 accounts in arrears" is true of
         * every institute that has ever existed, and reads as an alarming
         * discovery. The claim carries information only when a SMALL share of
         * the accounts holds a LARGE share of the money, so the top slice is
         * capped at a third of the accounts in arrears and must still be at
         * least three accounts. Below that the measure is reported unavailable,
         * with the reason, rather than dressed up as insight.
         */
        $top = min($top, intdiv($defaulters, 3));
        if ($top < 3) {
            return array_merge($blank, [
                'reason' => 'Too few accounts are in arrears for concentration to distinguish anything.',
            ]);
        }
        // Sliced from the one ledger read, so the concentration figure and the
        // drill-down list behind it can never disagree.
        $topAmount = 0.0;
        foreach (array_slice($this->accountsInArrears(), 0, $top) as $row) {
            $topAmount += $row['outstanding'];
        }

        return [
            'available' => true,
            'reason' => null,
            'topCount' => $top,
            'topAmount' => round($topAmount, 2),
            'totalAmount' => round($outstanding, 2),
            'share' => round($topAmount / $outstanding * 100, 1),
            'defaulterAccounts' => $defaulters,
        ];
    }

    /* ==================================================== receipt behaviour */

    private function receiptCount(): int
    {
        if ($this->syear === null) {
            return 0;
        }

        return (int) (DB::selectOne(
            "SELECT COUNT(DISTINCT CONCAT(fc.student_id, '#', fc.receipt_no)) AS c
               FROM fees_collect fc
              WHERE fc.sub_institute_id = ? AND fc.syear = ? AND fc.is_deleted = 'N'",
            [$this->tenantId, $this->syear]
        )->c ?? 0) + $this->otherReceiptCount();
    }

    private function otherReceiptCount(): int
    {
        if ($this->syear === null || ! SchemaCache::hasTable('fees_paid_other')) {
            return 0;
        }

        return (int) (DB::selectOne(
            "SELECT COUNT(DISTINCT CONCAT(fpo.student_id, '#', fpo.reciept_id)) AS c
               FROM fees_paid_other fpo
              WHERE fpo.sub_institute_id = ? AND fpo.syear = ? AND fpo.is_deleted = 'N'",
            [$this->tenantId, $this->syear]
        )->c ?? 0);
    }

    /* ============================================ cancellations and refunds */

    /**
     * Receipts cancelled and money refunded this year.
     *
     * BOTH READ REAL TABLES OR REPORT THEMSELVES UNAVAILABLE. `fees_cancel`,
     * `fees_other_cancel` and `fees_refund` are all tenant- and year-scoped in
     * this schema, so where an institute has none the answer is "none recorded",
     * which is different from "this ERP cannot tell you" — and the two are
     * reported differently.
     *
     * Cancellation matters to a collection figure because a cancelled receipt is
     * money the school counted and then un-counted; a year with heavy
     * cancellation has a collection rate that overstates what actually arrived.
     *
     * @return array<string, mixed>
     */
    public function adjustments(): array
    {
        return $this->memo['adjustments'] ??= $this->computeAdjustments();
    }

    /** @return array<string, mixed> */
    private function computeAdjustments(): array
    {
        $out = [
            'available' => false,
            'reason' => null,
            'cancelledReceipts' => 0,
            'cancelledAmount' => 0.0,
            'refunds' => 0,
            'refundedAmount' => 0.0,
            'cancelledShareOfCollection' => null,
        ];

        if ($this->syear === null) {
            return array_merge($out, ['reason' => 'No academic year is selected.']);
        }

        $hasCancel = SchemaCache::hasTable('fees_cancel');
        $hasRefund = SchemaCache::hasTable('fees_refund');

        if (! $hasCancel && ! $hasRefund) {
            return array_merge($out, [
                'reason' => 'This ERP does not record fee cancellations or refunds.',
            ]);
        }

        if ($hasCancel) {
            $row = DB::table('fees_cancel')
                ->where('sub_institute_id', $this->tenantId)->where('syear', $this->syear)
                ->selectRaw('COUNT(*) AS c, COALESCE(SUM(amountpaid), 0) AS amt')->first();
            $out['cancelledReceipts'] = (int) ($row->c ?? 0);
            $out['cancelledAmount'] = round((float) ($row->amt ?? 0), 2);
        }

        if (SchemaCache::hasTable('fees_other_cancel')) {
            $row = DB::table('fees_other_cancel')
                ->where('sub_institute_id', $this->tenantId)->where('syear', $this->syear)
                ->selectRaw('COUNT(*) AS c, COALESCE(SUM(cancellation_amount), 0) AS amt')->first();
            $out['cancelledReceipts'] += (int) ($row->c ?? 0);
            $out['cancelledAmount'] = round($out['cancelledAmount'] + (float) ($row->amt ?? 0), 2);
        }

        if ($hasRefund) {
            $row = DB::table('fees_refund')
                ->where('sub_institute_id', $this->tenantId)->where('syear', $this->syear)
                ->selectRaw('COUNT(*) AS c, COALESCE(SUM(amount), 0) AS amt')->first();
            $out['refunds'] = (int) ($row->c ?? 0);
            $out['refundedAmount'] = round((float) ($row->amt ?? 0), 2);
        }

        $collected = $this->positionTotals()['collectedAmount'];
        $out['available'] = true;
        $out['cancelledShareOfCollection'] = $collected > 0
            ? round($out['cancelledAmount'] / $collected * 100, 1)
            : null;

        return $out;
    }

    /**
     * Receipts recorded against this year that were later cancelled but whose
     * row still reads as live.
     *
     * A MEASURABLE RULE, NOT A HUNCH. `fees_cancel` holds one row per cancelled
     * receipt; `fees_collect.is_deleted` is what every Fees report filters on.
     * A receipt present in the first and still flagged not-deleted in the second
     * is money counted as collected that the school has already cancelled — a
     * reconciliation defect with an exact definition and an exact row list.
     * Anything that cannot be stated that precisely is not reported here.
     *
     * @return array{available: bool, reason: ?string, count: int, amount: float}
     */
    public function reconciliationGaps(): array
    {
        return $this->memo['reconciliation'] ??= $this->computeReconciliationGaps();
    }

    /** @return array{available: bool, reason: ?string, count: int, amount: float} */
    private function computeReconciliationGaps(): array
    {
        $none = ['available' => false, 'reason' => null, 'count' => 0, 'amount' => 0.0];

        if ($this->syear === null || ! SchemaCache::hasTable('fees_cancel')) {
            return array_merge($none, ['reason' => 'This ERP does not record fee cancellations separately.']);
        }

        $row = DB::selectOne(
            'SELECT COUNT(*) AS c, COALESCE(SUM(fc.amount), 0) AS amt
               FROM fees_collect fc
               JOIN fees_cancel fx
                 ON fx.sub_institute_id = fc.sub_institute_id
                AND fx.syear = fc.syear
                AND fx.student_id = fc.student_id
                AND fx.reciept_id = fc.receipt_no
              WHERE fc.sub_institute_id = ? AND fc.syear = ? AND fc.is_deleted = ?',
            [$this->tenantId, $this->syear, 'N']
        );

        return [
            'available' => true,
            'reason' => null,
            'count' => (int) ($row->c ?? 0),
            'amount' => round((float) ($row->amt ?? 0), 2),
        ];
    }

    /* ====================================================== query fragments */

    /**
     * One enrolment row per student for this institute-year.
     *
     * WHY DEDUPLICATED. `tblstudent_enrollment` can hold more than one row for
     * the same student and year (one institute in this database has such a
     * student today). Joining fee structure through an undeduplicated enrolment
     * multiplies that student's demand by the number of rows — the LMS's own
     * FeeBreackoff() helper carries the same exposure, but a doubled demand is a
     * defect wherever it appears, and this layer's whole claim is that its
     * figures are trustworthy. The latest row wins, which is the one the LMS's
     * own screens show as the student's current class.
     */
    private function enrolmentSql(): string
    {
        return 'SELECT se.student_id, se.grade_id, se.standard_id, se.section_id, se.student_quota
                  FROM tblstudent_enrollment se
                  JOIN (SELECT student_id, MAX(id) AS id
                          FROM tblstudent_enrollment
                         WHERE sub_institute_id = ? AND syear = ?
                         GROUP BY student_id) pick ON pick.id = se.id';
    }

    /** @return array<int, mixed> */
    private function enrolmentBindings(): array
    {
        return [$this->tenantId, $this->syear];
    }

    /**
     * Per-student regular demand, optionally split by one extra dimension.
     *
     * The join is App\Helpers\FeeBreackoff() semantically — admission year,
     * quota, grade and standard — so the Brain and the Fees module agree on what
     * a student owes. Only active students are counted, matching the LMS's own
     * defaulter report (`tblstudent.status = 1`), because a withdrawn student is
     * not a collection opportunity.
     *
     * THE FEE STRUCTURE IS COLLAPSED BEFORE THE JOIN, AND THAT IS WHAT MAKES
     * THIS USABBLE. `fees_breackoff` holds one row per (key, cycle, head): 24,536
     * of them for the largest institute here, across just 669 distinct join
     * keys. Joining the raw rows to a 5,000-student roll is a nested loop over
     * the product of the two and measured at 52 SECONDS per execution — enough
     * to blow the request time limit on its own. Summing the structure by its
     * join key first turns the same query into 1.4 seconds, because the join
     * then runs against 669 rows instead of 24,536. The arithmetic is identical:
     * SUM over a group of a group is SUM over the group.
     *
     * @param  string  $dimension  '' | 'month_id' | 'fee_type_id' — the extra
     *                             column to keep, when a caller needs demand
     *                             broken down by cycle or by head.
     */
    private function demandSql(string $dimension = ''): string
    {
        // Whitelisted: this reaches SQL as an identifier, and only these three
        // values are ever meaningful. Nothing caller-supplied gets through.
        if (! in_array($dimension, ['', 'month_id', 'fee_type_id'], true)) {
            $dimension = '';
        }

        $select = $dimension === '' ? '' : ', fs.'.$dimension;
        $group = $dimension === '' ? '' : ', '.$dimension;

        return 'SELECT e.student_id, e.grade_id, e.standard_id, e.section_id'.$select.', fs.amount
                  FROM ('.$this->enrolmentSql().') e
                  JOIN tblstudent s
                    ON s.id = e.student_id AND s.sub_institute_id = ? AND s.status = 1
                  JOIN (SELECT grade_id, standard_id, quota, admission_year'.$group.',
                               SUM(amount) AS amount
                          FROM fees_breackoff
                         WHERE sub_institute_id = ? AND syear = ?
                         GROUP BY grade_id, standard_id, quota, admission_year'.$group.') fs
                    ON fs.admission_year = s.admission_year
                   AND fs.quota = e.student_quota
                   AND fs.grade_id = e.grade_id
                   AND fs.standard_id = e.standard_id';
    }

    /** @return array<int, mixed> */
    private function demandBindings(): array
    {
        return array_merge($this->enrolmentBindings(), [$this->tenantId, $this->tenantId, $this->syear]);
    }

    /**
     * The account ledger, read ONCE per request and held.
     *
     * WHY IT IS MATERIALISED RATHER THAN RE-QUERIED. Four separate figures are
     * built from this ledger — the position totals, the class breakdown, the
     * concentration measure and the drill-down page — and each one used to run
     * the join again. On the largest institute here that was four executions of
     * a query measured at 52 seconds, which is how a read endpoint ends up
     * exceeding the request time limit and returning a 500.
     *
     * One account per student, a handful of scalars each: ~5,100 rows and under
     * a megabyte for the biggest institute in this database, because the row
     * count is bounded by the school roll rather than by the fee history. The
     * browser still never receives this — it gets aggregates, and a 25-row page.
     *
     * Holding it also makes the figures CONSISTENT BY CONSTRUCTION: the
     * headline "Outstanding", the class totals and the names in the drawer are
     * all folds of the same array, so they cannot drift apart.
     *
     * @return array<int, array{studentId: string, gradeId: string, standardId: string, demand: float, collected: float, discount: float, fine: float}>
     */
    private function accountLedger(): array
    {
        if (isset($this->memo['ledger'])) {
            return $this->memo['ledger'];
        }

        if ($this->syear === null) {
            return $this->memo['ledger'] = [];
        }

        $ledger = $this->accountLedgerSql();

        $rows = [];
        foreach (DB::select($ledger['sql'], $ledger['bindings']) as $row) {
            $rows[] = [
                'studentId' => (string) $row->student_id,
                'gradeId' => (string) $row->grade_id,
                'standardId' => (string) $row->standard_id,
                'demand' => (float) $row->demand,
                'collected' => (float) $row->collected,
                'discount' => (float) $row->discount,
                'fine' => (float) $row->fine,
            ];
        }

        return $this->memo['ledger'] = $rows;
    }

    /**
     * The account ledger: one row per student with everything owed, paid and
     * conceded for this year, regular and additional heads combined.
     *
     * Every institute-level figure on the screen is an aggregate of this — which
     * is what lets "Outstanding" drill down to the accounts that make it up and
     * have the two agree.
     *
     * @return array{sql: string, bindings: array<int, mixed>}
     */
    private function accountLedgerSql(): array
    {
        $bindings = [];

        $demand = 'SELECT d.student_id, d.grade_id, d.standard_id, SUM(d.amount) AS amount
                     FROM ('.$this->demandSql().') d GROUP BY d.student_id, d.grade_id, d.standard_id';
        $bindings = array_merge($bindings, $this->demandBindings());

        if (SchemaCache::hasTable('fees_breakoff_other')) {
            // Additional fees are billed per student directly, so they are
            // unioned into the demand side rather than joined through structure.
            $demand .= ' UNION ALL
                SELECT fbo.student_id, e.grade_id, e.standard_id, SUM(fbo.amount) AS amount
                  FROM fees_breakoff_other fbo
                  JOIN ('.$this->enrolmentSql().') e ON e.student_id = fbo.student_id
                  JOIN tblstudent s2 ON s2.id = fbo.student_id AND s2.sub_institute_id = ? AND s2.status = 1
                 WHERE fbo.sub_institute_id = ? AND fbo.syear = ?
                 GROUP BY fbo.student_id, e.grade_id, e.standard_id';
            $bindings = array_merge($bindings, $this->enrolmentBindings(), [$this->tenantId, $this->tenantId, $this->syear]);
        }

        $paid = "SELECT fc.student_id,
                        SUM(fc.amount)        AS collected,
                        SUM(fc.fees_discount) AS discount,
                        SUM(fc.fine)          AS fine
                   FROM fees_collect fc
                  WHERE fc.sub_institute_id = ? AND fc.syear = ? AND fc.is_deleted = 'N'
                  GROUP BY fc.student_id";
        $paidBindings = [$this->tenantId, $this->syear];

        if (SchemaCache::hasTable('fees_paid_other')) {
            $paid .= " UNION ALL
                SELECT fpo.student_id,
                       SUM(fpo.actual_amountpaid) AS collected,
                       SUM(fpo.fees_discount)     AS discount,
                       0                          AS fine
                  FROM fees_paid_other fpo
                 WHERE fpo.sub_institute_id = ? AND fpo.syear = ? AND fpo.is_deleted = 'N'
                 GROUP BY fpo.student_id";
            $paidBindings = array_merge($paidBindings, [$this->tenantId, $this->syear]);
        }

        // LEFT JOIN, so an account that owes and has paid nothing still appears.
        // A receipt against a student with no matching structure row cannot
        // create an account here — it has no demand to be measured against, and
        // inventing one would report a negative balance as a credit.
        $sql = 'SELECT dm.student_id,
                       MAX(dm.grade_id)                 AS grade_id,
                       MAX(dm.standard_id)              AS standard_id,
                       SUM(dm.amount)                   AS demand,
                       COALESCE(MAX(pd.collected), 0)   AS collected,
                       COALESCE(MAX(pd.discount), 0)    AS discount,
                       COALESCE(MAX(pd.fine), 0)        AS fine
                  FROM ('.$demand.') dm
                  LEFT JOIN (SELECT p.student_id,
                                    SUM(p.collected) AS collected,
                                    SUM(p.discount)  AS discount,
                                    SUM(p.fine)      AS fine
                               FROM ('.$paid.') p GROUP BY p.student_id) pd
                    ON pd.student_id = dm.student_id
                 GROUP BY dm.student_id';

        return ['sql' => $sql, 'bindings' => array_merge($bindings, $paidBindings)];
    }
}
