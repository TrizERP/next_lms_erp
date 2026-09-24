<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Student consent records for one institute and academic year.
 *
 * ── THIS IS CONSENT WITH MONEY ATTACHED, NOT A PERMISSION FLAG ──────────────
 *
 * `consent_master` carries `amount`, `imprest_head_id` and `accountable_status`
 * alongside the student and the title. So a row is a consent request that
 * usually collects money — a trip, a kit, an activity fee — and "outstanding
 * consent" therefore has a rupee value, not just a count.
 *
 * NOTHING HERE INTERPRETS `status` BEYOND GROUPING IT. The column is a small
 * integer whose meaning is set by the institute's own screens, so this class
 * reports the distribution and lets the reader map it, rather than asserting
 * that 1 means approved. Inventing that mapping is how a screen ends up
 * confidently reporting the opposite of the truth.
 *
 * ── SCOPE ───────────────────────────────────────────────────────────────────
 *
 * Every query carries `sub_institute_id` AND `syear`; consent is a per-year
 * record and a caller with no year resolved gets the unavailable answer rather
 * than another year's figures.
 */
final class ConsentIntelligence
{
    private const CONSENT = 'consent_master';

    private array $memo = [];

    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $syear,
    ) {
    }

    private function consent()
    {
        return DB::table(self::CONSENT)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', $this->syear);
    }

    private function memo(string $k, callable $fn)
    {
        return $this->memo[$k] ??= $fn();
    }

    public function coverage(): array
    {
        return $this->memo('coverage', function () {
            if (! SchemaCache::hasTable(self::CONSENT)) {
                return $this->unavailable('This installation has no '.self::CONSENT.' table.');
            }

            if ($this->syear === null || $this->syear === '') {
                return $this->unavailable('No academic year is selected, and consent is recorded per year.');
            }

            $t = $this->consent()->selectRaw(
                'COUNT(*) AS n, COUNT(DISTINCT student_id) AS students,
                 COUNT(DISTINCT standard_id) AS classes, COUNT(DISTINCT title) AS titles'
            )->first();

            $rows = (int) ($t->n ?? 0);

            if ($rows === 0) {
                return $this->unavailable(
                    "No consent records were raised for academic year {$this->syear}."
                );
            }

            return [
                'available' => true,
                'reason' => null,
                'syear' => $this->syear,
                'sources' => [
                    'consent' => true,
                    'studentLinked' => (int) ($t->students ?? 0) > 0,
                    'amountRecorded' => $this->amountTotal() > 0,
                ],
                'counts' => [
                    'records' => $rows,
                    'students' => (int) ($t->students ?? 0),
                    'classes' => (int) ($t->classes ?? 0),
                    'titles' => (int) ($t->titles ?? 0),
                ],
            ];
        });
    }

    private function unavailable(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'syear' => $this->syear, 'sources' => [], 'counts' => []];
    }

    private function amountTotal(): float
    {
        return $this->memo('amountTotal', fn () => (float) ($this->consent()->sum('amount') ?? 0));
    }

    public function position(): ?array
    {
        return $this->memo('position', function () {
            if (! $this->coverage()['available']) {
                return null;
            }

            $t = $this->consent()->selectRaw(
                'COUNT(*) AS n,
                 COUNT(DISTINCT student_id) AS students,
                 COUNT(DISTINCT standard_id) AS classes,
                 COUNT(DISTINCT title) AS titles,
                 SUM(amount) AS amt,
                 MIN(date) AS first_d,
                 MAX(date) AS last_d'
            )->first();

            $records = (int) ($t->n ?? 0);
            $accountable = (int) $this->consent()
                ->whereIn('accountable_status', [1, '1', 'Y', 'y'])->count();

            return [
                'records' => $records,
                'students' => (int) ($t->students ?? 0),
                'classes' => (int) ($t->classes ?? 0),
                'titles' => (int) ($t->titles ?? 0),
                'totalAmount' => (float) ($t->amt ?? 0),
                'meanAmount' => $records > 0 && $t->amt !== null ? round((float) $t->amt / $records, 2) : null,
                'accountable' => $accountable,
                'firstRaisedOn' => $t->first_d ?? null,
                'lastRaisedOn' => $t->last_d ?? null,
            ];
        });
    }

    /** The status distribution, grouped but not interpreted. */
    public function byStatus(): array
    {
        return $this->memo('byStatus', function () {
            if (! $this->coverage()['available']) {
                return [];
            }

            return $this->consent()
                ->selectRaw('status, COUNT(*) AS n, SUM(amount) AS amt')
                ->groupBy('status')
                ->orderByRaw('n DESC')
                ->get()
                ->map(fn ($r) => [
                    'key' => (string) ($r->status ?? 'none'),
                    'label' => $r->status === null || $r->status === '' ? 'No status recorded' : 'Status '.$r->status,
                    'records' => (int) $r->n,
                    'amount' => (float) ($r->amt ?? 0),
                ])->all();
        });
    }

    /** Consent requests grouped by what they are for. */
    public function byTitle(): array
    {
        return $this->memo('byTitle', function () {
            if (! $this->coverage()['available']) {
                return [];
            }

            return $this->consent()
                ->selectRaw('title, COUNT(*) AS n, COUNT(DISTINCT student_id) AS students, SUM(amount) AS amt')
                ->groupBy('title')
                ->orderByRaw('n DESC')
                ->limit(25)
                ->get()
                ->map(fn ($r) => [
                    'key' => (string) ($r->title ?? 'none'),
                    'label' => $r->title !== null && $r->title !== '' ? (string) $r->title : 'Untitled request',
                    'records' => (int) $r->n,
                    'students' => (int) $r->students,
                    'amount' => (float) ($r->amt ?? 0),
                ])->all();
        });
    }

    /** Consent per class. */
    public function byClass(): array
    {
        return $this->memo('byClass', function () {
            if (! $this->coverage()['available']) {
                return [];
            }

            return $this->consent()
                ->selectRaw('standard_id, COUNT(*) AS n, COUNT(DISTINCT student_id) AS students, SUM(amount) AS amt')
                ->groupBy('standard_id')
                ->orderByRaw('n DESC')
                ->get()
                ->map(fn ($r) => [
                    'key' => (string) ($r->standard_id ?? 'none'),
                    'label' => $r->standard_id ? 'Class '.$r->standard_id : 'No class recorded',
                    'records' => (int) $r->n,
                    'students' => (int) $r->students,
                    'amount' => (float) ($r->amt ?? 0),
                ])->all();
        });
    }

    public function dataQuality(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $total = (int) $coverage['counts']['records'];
        $noStudent = (int) $this->consent()->where(function ($q) {
            $q->whereNull('student_id')->orWhere('student_id', 0);
        })->count();
        $noDate = (int) $this->consent()->whereNull('date')->count();
        $zeroAmount = (int) $this->consent()->where(function ($q) {
            $q->whereNull('amount')->orWhere('amount', 0);
        })->count();
        $noTitle = (int) $this->consent()->where(function ($q) {
            $q->whereNull('title')->orWhere('title', '');
        })->count();

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'no_student', 'label' => 'Records with no student', 'value' => $noStudent, 'format' => 'count',
                    'sharePercent' => $total > 0 ? round($noStudent / $total * 100, 2) : null,
                    'state' => $noStudent > 0 ? 'attention' : 'ok',
                    'note' => $noStudent > 0
                        ? 'These rows cannot be linked to a child, so no parent can be asked about them.'
                        : 'Every consent record names a student.',
                ],
                [
                    'key' => 'no_date', 'label' => 'Records with no date', 'value' => $noDate, 'format' => 'count',
                    'sharePercent' => $total > 0 ? round($noDate / $total * 100, 2) : null,
                    'state' => $noDate > 0 ? 'attention' : 'ok',
                    'note' => $noDate > 0 ? 'Undated consent cannot be chased against a deadline.' : 'Every record is dated.',
                ],
                [
                    'key' => 'zero_amount', 'label' => 'Records with no amount', 'value' => $zeroAmount, 'format' => 'count',
                    'sharePercent' => $total > 0 ? round($zeroAmount / $total * 100, 2) : null,
                    'state' => 'ok',
                    'note' => 'Consent without money attached is legitimate — a permission slip carries no amount. '
                        .'Reported so the amount totals above are read against the right denominator.',
                ],
                [
                    'key' => 'no_title', 'label' => 'Records with no title', 'value' => $noTitle, 'format' => 'count',
                    'sharePercent' => $total > 0 ? round($noTitle / $total * 100, 2) : null,
                    'state' => $noTitle > 0 ? 'attention' : 'ok',
                    'note' => $noTitle > 0 ? 'A parent cannot tell what they are consenting to.' : 'Every record has a title.',
                ],
            ],
        ];
    }

    /** @return array{findings: array, ruleStatus: array} */
    public function findings(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $p = $this->position();
        $findings = [];
        $status = [];

        // 1. Consent concentrated in very few classes — the rest of the school never asked.
        $raised = false;
        if ($p['classes'] > 0 && $p['classes'] <= 2 && $p['records'] >= 3) {
            $raised = true;
            $findings[] = $this->finding(
                'consent-narrow-reach',
                'low',
                'Consent was raised in only '.$p['classes'].' class'.($p['classes'] === 1 ? '' : 'es'),
                $p['records'].' consent records for '.$this->syear.' cover '.$p['students'].' students across '
                    .$p['classes'].' class'.($p['classes'] === 1 ? '' : 'es').'.',
                'Narrow reach is normal for a single trip and unusual for a school-wide requirement. Which one this is '
                    .'decides whether the other classes have been missed.',
                [
                    ['label' => 'Classes covered', 'value' => (string) $p['classes']],
                    ['label' => 'Students covered', 'value' => (string) $p['students']],
                    ['label' => 'Requests', 'value' => (string) $p['titles']],
                ],
                'A single activity rather than a school-wide consent round.',
                'Confirm whether this consent was meant for the whole school.',
                'Class teacher',
                ['band' => 'Medium', 'value' => 0.68],
                ['count' => $p['classes'], 'total' => null, 'unit' => 'classes'],
            );
        }
        $status[] = ['key' => 'reach', 'label' => 'Class reach', 'checked' => true, 'raised' => $raised];

        // 2. Money attached but not marked accountable.
        $raised = false;
        if ($p['totalAmount'] > 0 && $p['accountable'] < $p['records']) {
            $unaccounted = $p['records'] - $p['accountable'];
            $raised = true;
            $findings[] = $this->finding(
                'consent-unaccounted',
                'medium',
                $unaccounted.' consent records carry money but are not marked accountable',
                'Consent for '.$this->syear.' totals '.number_format($p['totalAmount'], 2).' across '.$p['records']
                    .' records, of which '.$p['accountable'].' are marked accountable.',
                'The accountable flag is what ties collected money to an imprest head. Without it the amount is '
                    .'consented to but not tracked anywhere.',
                [
                    ['label' => 'Total amount', 'value' => number_format($p['totalAmount'], 2)],
                    ['label' => 'Marked accountable', 'value' => $p['accountable'].' of '.$p['records']],
                    ['label' => 'Not marked', 'value' => (string) $unaccounted],
                ],
                'The accountable step being completed later, or skipped for non-cash consent.',
                'Reconcile these against the imprest heads before the term closes.',
                'Accounts',
                ['band' => 'Medium', 'value' => 0.72],
                ['count' => $unaccounted, 'total' => $p['records'], 'unit' => 'records'],
            );
        }
        $status[] = ['key' => 'accountable', 'label' => 'Money marked accountable', 'checked' => true, 'raised' => $raised];

        return ['findings' => $findings, 'ruleStatus' => $status];
    }

    private function finding(
        string $id, string $severity, string $title, string $what, string $why,
        array $evidence, string $cause, string $recommendation, string $owner,
        array $confidence, array $affected,
    ): array {
        return [
            'id' => $id, 'severity' => $severity, 'severityLabel' => ucfirst($severity),
            'title' => $title, 'whatHappened' => $what, 'whyItMatters' => $why,
            'evidence' => $evidence, 'likelyCause' => $cause, 'causeConfirmed' => false,
            'recommendation' => $recommendation, 'owner' => $owner, 'priority' => $severity,
            'confidence' => $confidence, 'affected' => $affected, 'impact' => null, 'status' => 'open',
        ];
    }
}
