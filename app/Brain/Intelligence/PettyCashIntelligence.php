<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Petty cash spending for one institute, read from vivek_erp.
 *
 * ── THE GRAIN, AND WHAT IT IS NOT ───────────────────────────────────────────
 *
 * ONE ROW IS ONE PETTY-CASH CLAIM. `petty_cash` carries an amount, a spender
 * (`user_id`), a heading (`title_id` → `petty_cash_master.title`) and a date
 * (`created_on`). That is the whole ledger: there is no approval column, no
 * payment status and no reimbursement record on this table.
 *
 * SO THIS CLASS REPORTS SPENDING, NOT APPROVAL. A screen offering "pending
 * approvals" would be inventing a workflow the schema does not record, which is
 * why none appears here however natural it would look on a petty-cash page.
 *
 * ── NO ACADEMIC YEAR ────────────────────────────────────────────────────────
 *
 * `petty_cash` has no `syear`. Spending is not an academic-year fact in this
 * schema, so every figure is an ALL-TIME total for the institute and the screen
 * says so rather than implying the selected year filtered it.
 */
final class PettyCashIntelligence
{
    private const CLAIMS = 'petty_cash';

    private const HEADS = 'petty_cash_master';

    /** A claim at or above this multiple of the institute's own mean is called out. */
    private const OUTLIER_MULTIPLE = 3.0;

    private array $memo = [];

    public function __construct(private readonly string $tenantId)
    {
    }

    /**
     * Every read starts here, so no query can forget the tenant.
     *
     * The column is QUALIFIED because `byHead()` joins `petty_cash_master`,
     * which carries a `sub_institute_id` of its own — an unqualified predicate
     * is ambiguous the moment a join appears and MySQL rejects the statement.
     */
    private function claims()
    {
        return DB::table(self::CLAIMS)->where(self::CLAIMS.'.sub_institute_id', $this->tenantId);
    }

    private function memo(string $key, callable $fn)
    {
        return $this->memo[$key] ??= $fn();
    }

    public function coverage(): array
    {
        return $this->memo('coverage', function () {
            if (! SchemaCache::hasTable(self::CLAIMS)) {
                return $this->unavailable('This installation has no '.self::CLAIMS.' table.');
            }

            $t = $this->claims()->selectRaw(
                'COUNT(*) AS n,
                 COUNT(DISTINCT user_id) AS spenders,
                 COUNT(DISTINCT title_id) AS heads,
                 SUM(CASE WHEN amount IS NULL OR amount = 0 THEN 1 ELSE 0 END) AS zero_amount'
            )->first();

            $rows = (int) ($t->n ?? 0);

            if ($rows === 0) {
                return $this->unavailable('No petty-cash claims have been recorded for this institute.');
            }

            return [
                'available' => true,
                'reason' => null,
                'syear' => null,
                'sources' => [
                    'claims' => true,
                    'headsConfigured' => $this->headCount() > 0,
                    'spenderNamed' => (int) ($t->spenders ?? 0) > 0,
                ],
                'counts' => [
                    'claims' => $rows,
                    'spenders' => (int) ($t->spenders ?? 0),
                    'headsUsed' => (int) ($t->heads ?? 0),
                    'headsConfigured' => $this->headCount(),
                    'zeroAmount' => (int) ($t->zero_amount ?? 0),
                ],
            ];
        });
    }

    private function unavailable(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'syear' => null, 'sources' => [], 'counts' => []];
    }

    private function headCount(): int
    {
        return $this->memo('headCount', function () {
            if (! SchemaCache::hasTable(self::HEADS)) {
                return 0;
            }

            return (int) DB::table(self::HEADS)->where('sub_institute_id', $this->tenantId)->count();
        });
    }

    public function position(): ?array
    {
        return $this->memo('position', function () {
            if (! $this->coverage()['available']) {
                return null;
            }

            $t = $this->claims()->selectRaw(
                'COUNT(*) AS claims,
                 SUM(amount) AS total,
                 AVG(amount) AS mean,
                 MAX(amount) AS largest,
                 COUNT(DISTINCT user_id) AS spenders,
                 COUNT(DISTINCT title_id) AS heads,
                 MIN(created_on) AS first_on,
                 MAX(created_on) AS last_on'
            )->first();

            $total = (float) ($t->total ?? 0);
            $claims = (int) ($t->claims ?? 0);

            return [
                'claims' => $claims,
                'totalAmount' => $total,
                // Undefined rather than zero when nothing was claimed.
                'meanAmount' => $claims > 0 ? round($total / $claims, 2) : null,
                'largestAmount' => $t->largest !== null ? (float) $t->largest : null,
                'spenders' => (int) ($t->spenders ?? 0),
                'headsUsed' => (int) ($t->heads ?? 0),
                'headsConfigured' => $this->headCount(),
                'firstClaimOn' => $t->first_on ?? null,
                'lastClaimOn' => $t->last_on ?? null,
                'withBill' => (int) $this->claims()->whereNotNull('bill_image')->where('bill_image', '!=', '')->count(),
            ];
        });
    }

    /** Spending by heading, resolved to the institute's own head names. */
    public function byHead(): array
    {
        return $this->memo('byHead', function () {
            if (! $this->coverage()['available']) {
                return [];
            }

            // One LEFT JOIN, not a lookup per row: the head table is small but a
            // per-claim lookup is still an N+1 waiting to be copied elsewhere.
            $rows = $this->claims()
                ->leftJoin(self::HEADS, function ($join) {
                    $join->on(self::HEADS.'.id', '=', self::CLAIMS.'.title_id')
                        ->where(self::HEADS.'.sub_institute_id', '=', $this->tenantId);
                })
                ->selectRaw(self::HEADS.'.title AS head, '.self::CLAIMS.'.title_id AS head_id,
                            COUNT(*) AS n, SUM('.self::CLAIMS.'.amount) AS amt')
                ->groupBy(self::CLAIMS.'.title_id', self::HEADS.'.title')
                ->orderByRaw('amt DESC')
                ->get();

            return $rows->map(fn ($r) => [
                'key' => (string) ($r->head_id ?? 'none'),
                'label' => $r->head !== null && $r->head !== '' ? (string) $r->head : 'Not categorised',
                'claims' => (int) $r->n,
                'amount' => (float) $r->amt,
            ])->all();
        });
    }

    /** Spending by the person who claimed it. */
    public function bySpender(): array
    {
        return $this->memo('bySpender', function () {
            if (! $this->coverage()['available']) {
                return [];
            }

            $rows = $this->claims()
                ->selectRaw('user_id, COUNT(*) AS n, SUM(amount) AS amt')
                ->groupBy('user_id')
                ->orderByRaw('amt DESC')
                ->limit(25)
                ->get();

            $ids = $rows->pluck('user_id')->filter()->all();
            $names = [];
            if ($ids !== [] && SchemaCache::hasTable('tbluser')) {
                // One IN() for every name, so the loop below touches no database.
                $names = DB::table('tbluser')->whereIn('id', $ids)
                    ->pluck(DB::raw("TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')))"), 'id')
                    ->all();
            }

            return $rows->map(fn ($r) => [
                'key' => (string) $r->user_id,
                'label' => trim((string) ($names[$r->user_id] ?? '')) !== ''
                    ? (string) $names[$r->user_id]
                    : 'User '.$r->user_id,
                'claims' => (int) $r->n,
                'amount' => (float) $r->amt,
            ])->all();
        });
    }

    /**
     * Checks on the ledger itself. These are data-entry problems, not spending
     * problems, and are kept apart from the findings for that reason.
     */
    public function dataQuality(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $total = (int) $coverage['counts']['claims'];
        $zero = (int) $coverage['counts']['zeroAmount'];
        $noHead = (int) $this->claims()->where(function ($q) {
            $q->whereNull('title_id')->orWhere('title_id', 0);
        })->count();
        $noBill = (int) $this->claims()->where(function ($q) {
            $q->whereNull('bill_image')->orWhere('bill_image', '');
        })->count();
        $negative = (int) $this->claims()->where('amount', '<', 0)->count();

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'zero_amount', 'label' => 'Claims with no amount', 'value' => $zero, 'format' => 'count',
                    'sharePercent' => $total > 0 ? round($zero / $total * 100, 2) : null,
                    'state' => $zero > 0 ? 'attention' : 'ok',
                    'note' => $zero > 0
                        ? 'A claim with no amount contributes nothing to any total on this screen.'
                        : 'Every claim records an amount.',
                ],
                [
                    'key' => 'no_head', 'label' => 'Claims with no heading', 'value' => $noHead, 'format' => 'count',
                    'sharePercent' => $total > 0 ? round($noHead / $total * 100, 2) : null,
                    'state' => $noHead > 0 ? 'attention' : 'ok',
                    'note' => $noHead > 0
                        ? 'These claims cannot be attributed to a spending head and appear as "Not categorised".'
                        : 'Every claim is filed under a heading.',
                ],
                [
                    'key' => 'no_bill', 'label' => 'Claims with no bill attached', 'value' => $noBill, 'format' => 'count',
                    'sharePercent' => $total > 0 ? round($noBill / $total * 100, 2) : null,
                    'state' => $noBill > 0 ? 'attention' : 'ok',
                    'note' => $noBill > 0
                        ? 'The table stores a bill image per claim; these rows have none, so the spend is unevidenced.'
                        : 'Every claim carries a bill image.',
                ],
                [
                    'key' => 'negative', 'label' => 'Negative amounts', 'value' => $negative, 'format' => 'count',
                    'sharePercent' => null,
                    'state' => $negative > 0 ? 'attention' : 'ok',
                    'note' => $negative > 0 ? 'Claims recording a negative amount.' : 'No negative amounts recorded.',
                ],
            ],
        ];
    }

    /**
     * What the figures mean. Each finding names its threshold and carries the
     * rows behind it; nothing is raised without them.
     *
     * @return array{findings: array, ruleStatus: array}
     */
    public function findings(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $position = $this->position();
        $findings = [];
        $status = [];

        // 1. Unusually large single claims, measured against this institute's own mean.
        $mean = $position['meanAmount'] ?? null;
        $raised = false;
        if ($mean !== null && $mean > 0) {
            $threshold = $mean * self::OUTLIER_MULTIPLE;
            $outliers = $this->claims()->where('amount', '>=', $threshold)
                ->orderByRaw('amount DESC')->limit(5)
                ->get(['id', 'amount', 'description', 'created_on']);

            if ($outliers->count() > 0) {
                $raised = true;
                $findings[] = [
                    'id' => 'petty-cash-outliers',
                    'severity' => 'medium',
                    'severityLabel' => 'Medium',
                    'title' => $outliers->count().' claim'.($outliers->count() === 1 ? '' : 's')
                        .' at more than '.self::OUTLIER_MULTIPLE.'× the average',
                    'whatHappened' => 'The average petty-cash claim at this institute is '
                        .number_format((float) $mean, 2).'. '.$outliers->count().' claim'
                        .($outliers->count() === 1 ? ' is' : 's are').' at or above '
                        .number_format($threshold, 2).'.',
                    'whyItMatters' => 'Petty cash is meant for small incidental spend. A claim several times the '
                        .'institute’s own average is either miscoded or belongs in a different budget line.',
                    'evidence' => array_merge(
                        [
                            ['label' => 'Average claim', 'value' => number_format((float) $mean, 2)],
                            ['label' => 'Threshold', 'value' => number_format($threshold, 2)],
                        ],
                        $outliers->take(3)->map(fn ($o) => [
                            'label' => 'Claim #'.$o->id,
                            'value' => number_format((float) $o->amount, 2),
                            'note' => $o->description !== null && $o->description !== ''
                                ? mb_substr((string) $o->description, 0, 60) : null,
                        ])->all(),
                    ),
                    'likelyCause' => 'A large purchase routed through petty cash rather than through a requisition.',
                    'causeConfirmed' => false,
                    'recommendation' => 'Check whether these belong under an inventory requisition instead.',
                    'owner' => 'Accounts',
                    'priority' => 'medium',
                    'confidence' => ['band' => 'Medium', 'value' => 0.7],
                    'affected' => ['count' => $outliers->count(), 'total' => $position['claims'], 'unit' => 'claims'],
                    'impact' => null,
                    'status' => 'open',
                ];
            }
        }
        $status[] = ['key' => 'large_claims', 'label' => 'Unusually large claims', 'checked' => $mean !== null, 'raised' => $raised];

        // 2. Spend concentrated on one person.
        $spenders = $this->bySpender();
        $raised = false;
        if (count($spenders) > 1 && ($position['totalAmount'] ?? 0) > 0) {
            $top = $spenders[0];
            $share = $top['amount'] / $position['totalAmount'] * 100;
            if ($share >= 60.0) {
                $raised = true;
                $findings[] = [
                    'id' => 'petty-cash-concentration',
                    'severity' => 'low',
                    'severityLabel' => 'Low',
                    'title' => $top['label'].' accounts for '.round($share, 1).'% of petty-cash spend',
                    'whatHappened' => $top['label'].' filed '.$top['claims'].' of '.$position['claims']
                        .' claims, totalling '.number_format($top['amount'], 2).' of '
                        .number_format((float) $position['totalAmount'], 2).'.',
                    'whyItMatters' => 'Concentration is not wrongdoing — many institutes route petty cash through one '
                        .'office — but it does mean a single person’s records are the only evidence for most of the spend.',
                    'evidence' => [
                        ['label' => 'Share of spend', 'value' => round($share, 1).'%'],
                        ['label' => 'Claims', 'value' => (string) $top['claims']],
                        ['label' => 'Distinct claimants', 'value' => (string) count($spenders)],
                    ],
                    'likelyCause' => 'A single custodian operating the float on behalf of others.',
                    'causeConfirmed' => false,
                    'recommendation' => 'Confirm the bills for these claims are filed and countersigned.',
                    'owner' => 'Accounts',
                    'priority' => 'low',
                    'confidence' => ['band' => 'High', 'value' => 0.85],
                    'affected' => ['count' => 1, 'total' => count($spenders), 'unit' => 'claimants'],
                    'impact' => null,
                    'status' => 'open',
                ];
            }
        }
        $status[] = ['key' => 'concentration', 'label' => 'Spend concentration', 'checked' => true, 'raised' => $raised];

        // 3. Unevidenced spend.
        $noBill = (int) $this->claims()->where(function ($q) {
            $q->whereNull('bill_image')->orWhere('bill_image', '');
        })->count();
        $raised = false;
        if ($noBill > 0 && ($position['claims'] ?? 0) > 0) {
            $share = $noBill / $position['claims'] * 100;
            if ($share >= 25.0) {
                $raised = true;
                $findings[] = [
                    'id' => 'petty-cash-unevidenced',
                    'severity' => $share >= 60 ? 'high' : 'medium',
                    'severityLabel' => $share >= 60 ? 'High' : 'Medium',
                    'title' => $noBill.' of '.$position['claims'].' claims have no bill attached',
                    'whatHappened' => round($share, 1).'% of petty-cash claims at this institute carry no bill image.',
                    'whyItMatters' => 'The table has a field for the bill, so an empty one is a gap in the audit trail '
                        .'rather than a limitation of the system.',
                    'evidence' => [
                        ['label' => 'Claims without a bill', 'value' => (string) $noBill],
                        ['label' => 'Share', 'value' => round($share, 1).'%'],
                        ['label' => 'Total claims', 'value' => (string) $position['claims']],
                    ],
                    'likelyCause' => 'Bills collected on paper and never attached to the record.',
                    'causeConfirmed' => false,
                    'recommendation' => 'Attach the held bills before the next reconciliation.',
                    'owner' => 'Accounts',
                    'priority' => 'medium',
                    'confidence' => ['band' => 'High', 'value' => 0.9],
                    'affected' => ['count' => $noBill, 'total' => $position['claims'], 'unit' => 'claims'],
                    'impact' => null,
                    'status' => 'open',
                ];
            }
        }
        $status[] = ['key' => 'unevidenced', 'label' => 'Claims without a bill', 'checked' => true, 'raised' => $raised];

        return ['findings' => $findings, 'ruleStatus' => $status];
    }
}
