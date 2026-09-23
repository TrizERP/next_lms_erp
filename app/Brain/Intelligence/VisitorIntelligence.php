<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\AcademicYear;
use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Visitor Management Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `visitor_master` is ONE PERSON ARRIVING AT THE GATE ON ONE DAY:
 * the date they came, the time they were signed in, the time they were signed
 * out if anybody signed them out, what kind of visitor they are, and whether
 * they were expected.
 *
 * ── YEAR SCOPING ────────────────────────────────────────────────────────────
 *
 * This table carries NO `syear`. It is scoped through the institute's own
 * `academic_year` term dates against `meet_date` — the day of the visit —
 * exactly as staff leave and admissions are. `created_at` is when the row was
 * typed, which is not the same day and is not what a year means here.
 *
 * ── WHY THERE IS NO VISIT DURATION ON THIS SCREEN ───────────────────────────
 *
 * `in_time` and `out_time` are TIME columns, and they are not on a consistent
 * clock. At the richest institute 31 of 258 closed visits (12.0%) record an exit
 * EARLIER than the entry — `13:00:00` in and `01:53:10` out — because some rows
 * are written on a 12-hour clock and some on a 24-hour one, with nothing
 * recording which. A duration computed across that would be negative on one row
 * in eight and silently an hour out on others.
 *
 * So no average visit length, no "longest visit", no dwell time appears
 * anywhere. The rows that contradict themselves are counted in the record
 * checks instead, which is the honest thing this data supports. What CAN be
 * said is whether a visit was ever closed at all, and that is a security
 * question worth asking.
 *
 * ── WHAT IS DELIBERATELY NOT READ ───────────────────────────────────────────
 *
 * `name`, `contact`, `email`, `photo` and `visitor_idcard` — who came, how to
 * reach them, and a photograph of them. No question this module answers needs
 * a visitor named, and a gate register is the single most re-identifying table
 * in this database.
 *
 * `to_meet` — who they came to see. Measured, it is a MIXED column: a staff id
 * on 453 of 463 rows at one institute and a typed human name ("Anupriya
 * Pandey", "Principal") on the rest. It can be neither joined nor shown — the
 * join misses a tenth of the rows and the display names members of staff. The
 * COUNT of rows carrying a typed name is reported as a record check, because a
 * field that holds an id on most rows and a name on others cannot be reported
 * on at all; the values never are.
 *
 * `coming_from` — a free-text town typed at the desk. "Nadiad" and "Nadaid",
 * "Ahmedabad" and "Ahemadabad" are separate values in the same column, so a
 * breakdown of it would be a breakdown of spellings.
 */
final class VisitorIntelligence
{
    private const VISITOR_TABLE = 'visitor_master';

    private const TYPE_TABLE = 'visitor_type';

    /**
     * Below this many visits in the year, a percentage describes the handful of
     * people who happened to be signed in rather than the gate.
     *
     * Public so tests assert against the threshold rather than hard-coding a row
     * count out of this database — a test pinning a business figure fails the
     * day somebody signs a visitor in.
     */
    public const MIN_VISITS = 20;

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

    /** @return array{start:string,end:string}|null */
    public function window(): ?array
    {
        return $this->memo['window'] ??= AcademicYear::window($this->tenantId, $this->syear);
    }

    /** @return array<string,mixed> */
    private function computeCoverage(): array
    {
        $empty = [
            'sourceTable' => self::VISITOR_TABLE,
            'sources' => [],
            'counts' => [],
            'totalRows' => 0,
            'usableRows' => 0,
        ];

        if (! SchemaCache::hasTable(self::VISITOR_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::VISITOR_TABLE."' does not exist in this deployment.",
            ];
        }

        if ($this->syear === null) {
            return $empty + [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute, and the gate register is '
                    .'reported a year at a time.',
            ];
        }

        $window = $this->window();
        if ($window === null) {
            // The module's rows carry a date and nothing else. Without the
            // institute's own term dates there is no defensible way to decide
            // which of them belong to this year, and inventing a calendar-year
            // convention would be a second year mechanism.
            return $empty + [
                'available' => false,
                'reason' => 'This institute has no academic_year term dates for '.$this->syear.'. The visitor '
                    .'register records a visit date and no year, so without the term dates there is no way to say '
                    .'which visits belong to this year.',
            ];
        }

        $shape = $this->shape();
        $visits = $shape['visits'];

        // An institute that keeps no gate register at all is a different
        // statement from one whose register is too thin to describe, and both
        // are different from one that has no term dates. Each says which.
        if ($visits < self::MIN_VISITS) {
            $everRecorded = (int) DB::table(self::VISITOR_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->count();

            return array_merge($empty, [
                'totalRows' => $visits,
                'usableRows' => $visits,
                'counts' => ['visits' => $visits, 'visitsAllYears' => $everRecorded],
                'available' => false,
                'reason' => match (true) {
                    $visits === 0 && $everRecorded === 0
                        => 'This institute has never recorded a visitor through the system. Nothing here is missing — '
                            .'it does not use the gate register.',
                    $visits === 0
                        => 'No visitor was recorded between '.$window['start'].' and '.$window['end'].', though this '
                            .'institute has '.$everRecorded.' visits on file in other years. The register exists; it '
                            .'was not used this year.',
                    default => 'Only '.$visits.' visit'.($visits === 1 ? '' : 's').' '.($visits === 1 ? 'was' : 'were')
                        .' recorded between '.$window['start'].' and '.$window['end'].'. Any share drawn from that '
                        .'describes '.($visits === 1 ? 'that single arrival' : 'those '.$visits.' arrivals')
                        .' rather than the gate, so the counts below are reported and no rate is.',
                },
            ]);
        }

        return [
            'available' => true,
            'reason' => null,
            'sourceTable' => self::VISITOR_TABLE,
            'sources' => [
                'visits' => true,
                // False at the institutes whose register points at a type master
                // that does not hold the ids it names. Saying so is what lets the
                // type breakdown be honestly omitted rather than drawn empty.
                'visitorTypes' => $shape['typesResolved'] > 0,
                'appointmentType' => $shape['appointmentRecorded'] > 0,
                'exitTimes' => $shape['closed'] > 0,
                // There is no reliable clock on this table at all, so a duration
                // is false everywhere by construction rather than by accident.
                'visitDuration' => false,
                'yearWindow' => true,
            ],
            'counts' => [
                'visits' => $visits,
                'closed' => $shape['closed'],
                'open' => $shape['open'],
                'typesUsed' => $shape['typesUsed'],
                'typesResolved' => $shape['typesResolved'],
                'daysWithVisits' => $shape['days'],
            ],
            'totalRows' => $visits,
            'usableRows' => $visits,
            'period' => "Academic Year {$this->syear} ({$window['start']} to {$window['end']})",
        ];
    }

    /* -------------------------------------------------------- the raw shape */

    /**
     * Every figure this module counts, in one pass over the year's rows.
     *
     * @return array{visits:int,closed:int,open:int,exitBeforeEntry:int,prior:int,direct:int,
     *               appointmentRecorded:int,days:int,typesUsed:int,typesResolved:int,
     *               unresolvedRows:int,hostAsText:int,badDates:int}
     */
    public function shape(): array
    {
        return $this->memo['shape'] ??= (function (): array {
            $none = [
                'visits' => 0, 'closed' => 0, 'open' => 0, 'exitBeforeEntry' => 0,
                'prior' => 0, 'direct' => 0, 'appointmentRecorded' => 0, 'days' => 0,
                'typesUsed' => 0, 'typesResolved' => 0, 'unresolvedRows' => 0,
                'hostAsText' => 0, 'badDates' => 0,
            ];

            if (! SchemaCache::hasTable(self::VISITOR_TABLE) || $this->window() === null) {
                return $none;
            }

            $row = $this->scoped()
                ->selectRaw(
                    'COUNT(*) as visits,
                     SUM(CASE WHEN COALESCE(TRIM(out_time), "") <> "" THEN 1 ELSE 0 END) as closed,
                     SUM(CASE WHEN COALESCE(TRIM(out_time), "") = "" THEN 1 ELSE 0 END) as open,
                     SUM(CASE WHEN COALESCE(TRIM(out_time), "") <> ""
                               AND COALESCE(TRIM(in_time), "") <> ""
                               AND out_time < in_time THEN 1 ELSE 0 END) as exit_before_entry,
                     SUM(CASE WHEN LOWER(TRIM(COALESCE(appointment_type, ""))) = "prior" THEN 1 ELSE 0 END) as prior_n,
                     SUM(CASE WHEN LOWER(TRIM(COALESCE(appointment_type, ""))) = "direct" THEN 1 ELSE 0 END) as direct_n,
                     SUM(CASE WHEN COALESCE(TRIM(appointment_type), "") <> "" THEN 1 ELSE 0 END) as appointment_recorded,
                     COUNT(DISTINCT meet_date) as days,
                     COUNT(DISTINCT NULLIF(visitor_type, 0)) as types_used,
                     SUM(CASE WHEN COALESCE(TRIM(to_meet), "") <> ""
                               AND to_meet NOT REGEXP "^[0-9]+$" THEN 1 ELSE 0 END) as host_as_text'
                )
                ->first();

            // How many of the types this year's rows name actually exist in THIS
            // institute's own type master. Scoped by tenant on both sides on
            // purpose: one institute's register names type id 10, which belongs
            // to a different institute, and resolving it globally would read
            // another school's reference data onto this screen.
            $resolved = $this->scoped('v')
                ->join(self::TYPE_TABLE.' as t', function ($join) {
                    $join->on('t.id', '=', 'v.visitor_type')
                        ->on('t.sub_institute_id', '=', 'v.sub_institute_id');
                })
                ->selectRaw('COUNT(DISTINCT v.visitor_type) as types_resolved, COUNT(*) as rows_resolved')
                ->first();

            $visits = (int) ($row->visits ?? 0);

            // Dates the register itself cannot place. Counted OUTSIDE the year
            // window, because a row with a zero date is by definition not inside
            // any window and would otherwise be invisible.
            $badDates = (int) DB::table(self::VISITOR_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->whereRaw('(meet_date IS NULL OR CAST(meet_date AS CHAR) = "0000-00-00")')
                ->count();

            return [
                'visits' => $visits,
                'closed' => (int) ($row->closed ?? 0),
                'open' => (int) ($row->open ?? 0),
                'exitBeforeEntry' => (int) ($row->exit_before_entry ?? 0),
                'prior' => (int) ($row->prior_n ?? 0),
                'direct' => (int) ($row->direct_n ?? 0),
                'appointmentRecorded' => (int) ($row->appointment_recorded ?? 0),
                'days' => (int) ($row->days ?? 0),
                'typesUsed' => (int) ($row->types_used ?? 0),
                'typesResolved' => (int) ($resolved->types_resolved ?? 0),
                'unresolvedRows' => $visits - (int) ($resolved->rows_resolved ?? 0),
                'hostAsText' => (int) ($row->host_as_text ?? 0),
                'badDates' => $badDates,
            ];
        })();
    }

    /* -------------------------------------------------------- L1: position */

    /** @return array<string,mixed> */
    public function position(): array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    /** @return array<string,mixed> */
    private function computePosition(): array
    {
        $coverage = $this->coverage();
        $shape = $this->shape();

        if (! $coverage['available']) {
            return [
                // NULL, never 0. "0% of visitors were signed out" is a statement
                // about a gate; "this institute keeps no register" is a statement
                // about the institute, and only the second is true here.
                'metrics' => [
                    'visits' => $shape['visits'] > 0 ? $shape['visits'] : null,
                    'openVisits' => null,
                    'openShare' => null,
                    'priorShare' => null,
                    'visitorTypes' => null,
                    'daysWithVisits' => null,
                    'averageVisitLength' => null,
                ],
                'summary' => $coverage['reason'] ?? 'No visitor records are available for this year.',
            ];
        }

        $visits = $shape['visits'];

        return [
            'metrics' => [
                'visits' => $visits,
                'openVisits' => $shape['open'],
                'openShare' => round($shape['open'] / $visits * 100, 1),
                // Undefined rather than 0 where nobody recorded whether the
                // visitor was expected: an unused field is not "0% expected".
                'priorShare' => $shape['appointmentRecorded'] > 0
                    ? round($shape['prior'] / $shape['appointmentRecorded'] * 100, 1)
                    : null,
                'visitorTypes' => $shape['typesResolved'] > 0 ? $shape['typesResolved'] : null,
                'daysWithVisits' => $shape['days'],
                // NEVER COMPUTED, AT ANY INSTITUTE. See the class note: the two
                // time columns are not on a consistent clock.
                'averageVisitLength' => null,
            ],
            'summary' => $this->summarySentence($shape),
        ];
    }

    /** @param array<string,int> $shape */
    private function summarySentence(array $shape): string
    {
        $visits = $shape['visits'];
        $openShare = round($shape['open'] / $visits * 100, 1);
        $window = $this->window();

        $parts = [
            "{$visits} visitors were signed in between {$window['start']} and {$window['end']}, across "
                ."{$shape['days']} ".($shape['days'] === 1 ? 'day' : 'days').'.',
        ];

        $parts[] = $shape['open'] === 0
            ? 'Every one of them was signed out again.'
            : "{$shape['open']} of them ({$openShare}%) have no exit time recorded, so the register does not show "
                .'them leaving.';

        if ($shape['appointmentRecorded'] > 0) {
            $priorShare = round($shape['prior'] / $shape['appointmentRecorded'] * 100, 1);
            $parts[] = "{$priorShare}% arrived on a prior appointment; the rest came to the gate unannounced.";
        } else {
            $parts[] = 'Whether a visitor was expected was not recorded on any row this year.';
        }

        $parts[] = 'How long a visit lasted is not reported: the entry and exit times on this table are not on a '
            .'consistent clock, so any duration drawn from them would be wrong rather than approximate.';

        return implode(' ', $parts);
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Visits by the kind of visitor, resolved to the institute's own type names.
     *
     * Returns [] where the register names types this institute's master does not
     * hold — which is every row at one institute in this database. The caller
     * reports that as unavailable WITH the reason rather than drawing a chart of
     * numeric ids or, worse, resolving them against another tenant's master.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byType(): array
    {
        return $this->memo['byType'] ??= (function (): array {
            if (! $this->coverage()['available'] || $this->shape()['typesResolved'] === 0) {
                return [];
            }

            $rows = $this->scoped('v')
                ->join(self::TYPE_TABLE.' as t', function ($join) {
                    $join->on('t.id', '=', 'v.visitor_type')
                        ->on('t.sub_institute_id', '=', 'v.sub_institute_id');
                })
                ->groupBy('t.id', 't.title')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->get([
                    't.id',
                    DB::raw('NULLIF(TRIM(t.title), "") as type_title'),
                    DB::raw('COUNT(*) as visits'),
                    DB::raw('SUM(CASE WHEN COALESCE(TRIM(v.out_time), "") = "" THEN 1 ELSE 0 END) as open_visits'),
                ]);

            $total = $this->shape()['visits'];

            return array_map(static fn ($row) => [
                'key' => (string) $row->id,
                'label' => $row->type_title ?? "Type #{$row->id}",
                'visits' => (int) $row->visits,
                'openVisits' => (int) $row->open_visits,
                'share' => $total > 0 ? round((int) $row->visits / $total * 100, 1) : null,
                'openShare' => (int) $row->visits > 0
                    ? round((int) $row->open_visits / (int) $row->visits * 100, 1)
                    : null,
            ], $rows->all());
        })();
    }

    /**
     * Visits by month of the academic year, in the order the year runs.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byMonth(): array
    {
        return $this->memo['byMonth'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $rows = $this->scoped()
                ->groupBy(DB::raw('DATE_FORMAT(meet_date, "%Y-%m")'))
                ->orderBy(DB::raw('DATE_FORMAT(meet_date, "%Y-%m")'))
                ->get([
                    DB::raw('DATE_FORMAT(meet_date, "%Y-%m") as month_key'),
                    DB::raw('COUNT(*) as visits'),
                    DB::raw('SUM(CASE WHEN COALESCE(TRIM(out_time), "") = "" THEN 1 ELSE 0 END) as open_visits'),
                ]);

            return array_map(static fn ($row) => [
                'key' => (string) $row->month_key,
                'label' => date('M Y', strtotime($row->month_key.'-01')),
                'visits' => (int) $row->visits,
                'openVisits' => (int) $row->open_visits,
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
        $shape = $this->shape();

        if ($shape['visits'] === 0 && $shape['badDates'] === 0) {
            return [
                'available' => false,
                'reason' => 'This institute recorded no visitors in this academic year, so there is nothing to check.',
                'checks' => [],
            ];
        }

        $visits = $shape['visits'];
        $share = static fn (int $n, int $of): ?float => $of > 0 ? round($n / $of * 100, 2) : null;

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'visits_never_closed',
                    'label' => 'Visits with no exit time recorded',
                    'value' => $shape['open'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['open'], $visits),
                    'shareLabel' => 'of this year’s visits',
                    'state' => $shape['open'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['open'] > 0
                        ? 'The register shows these people arriving and never shows them leaving. On the register’s '
                            .'own evidence they are still on site.'
                        : 'Every visitor signed in this year was signed out again.',
                ],
                [
                    'key' => 'exit_before_entry',
                    'label' => 'Closed visits whose exit is earlier than the entry',
                    'value' => $shape['exitBeforeEntry'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['exitBeforeEntry'], max($shape['closed'], 1)),
                    'shareLabel' => 'of closed visits',
                    'state' => $shape['exitBeforeEntry'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['exitBeforeEntry'] > 0
                        ? 'These rows record somebody leaving before they arrived, which happens when one time is '
                            .'written on a 12-hour clock and the other on a 24-hour one. It is why no visit duration '
                            .'appears anywhere on this screen.'
                        : 'Every closed visit has its exit after its entry.',
                ],
                [
                    'key' => 'visit_duration_not_computable',
                    'label' => 'Visits with a reliable duration',
                    // NOT a count of zero. The COLUMNS exist and carry times;
                    // what is missing is any record of which clock they are on.
                    'value' => null,
                    'format' => 'count',
                    'sharePercent' => null,
                    'state' => 'attention',
                    'note' => 'This table stores entry and exit as bare times with no date and no record of whether '
                        .'they are on a 12- or 24-hour clock. How long a visit lasted therefore cannot be computed '
                        .'from it, so no dwell time, average visit length or longest-visit figure appears on this '
                        .'screen. It would have to be invented.',
                ],
                [
                    'key' => 'visitor_type_not_in_master',
                    'label' => 'Visits filed under a type this institute does not hold',
                    'value' => $shape['unresolvedRows'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['unresolvedRows'], $visits),
                    'shareLabel' => 'of this year’s visits',
                    'state' => $shape['unresolvedRows'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['unresolvedRows'] > 0
                        ? 'These rows name a visitor-type id with no row in this institute’s own visitor_type master, '
                            .'so the kind of visitor cannot be named. They are not resolved against any other '
                            .'institute’s master, which is where those ids actually exist.'
                        : 'Every visit is filed under a visitor type this institute holds.',
                ],
                [
                    'key' => 'host_recorded_as_text',
                    'label' => 'Visits recording the host as a typed name rather than a staff id',
                    'value' => $shape['hostAsText'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['hostAsText'], $visits),
                    'shareLabel' => 'of this year’s visits',
                    'state' => $shape['hostAsText'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['hostAsText'] > 0
                        ? 'The "to meet" field holds a staff id on most rows and a typed human name on these, so '
                            .'visits cannot be totalled by host at all. The values themselves are never read or '
                            .'shown by this module, because on these rows they are a person’s name.'
                        : 'Every visit records the host as a staff id.',
                ],
                [
                    'key' => 'unusable_visit_date',
                    'label' => 'Visitor rows with no usable visit date',
                    // Counted across the whole register, not the year: a row with
                    // a zero date is inside no year window and would otherwise
                    // never be reported anywhere.
                    'value' => $shape['badDates'],
                    'format' => 'count',
                    'sharePercent' => null,
                    'state' => $shape['badDates'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['badDates'] > 0
                        ? 'These rows carry a zero or empty visit date. They belong to no academic year, so they are '
                            .'absent from this screen and from every year-scoped visitor report.'
                        : 'Every visitor row carries a usable visit date.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * This institute's visits inside this academic year's own term dates.
     *
     * The tenant is pinned on every read and is never a parameter.
     */
    private function scoped(?string $alias = null): \Illuminate\Database\Query\Builder
    {
        $window = $this->window();
        $table = $alias === null ? self::VISITOR_TABLE : self::VISITOR_TABLE.' as '.$alias;
        $prefix = $alias === null ? '' : $alias.'.';

        $query = DB::table($table)->where($prefix.'sub_institute_id', $this->tenantId);

        if ($window !== null) {
            $query->whereBetween($prefix.'meet_date', [$window['start'], $window['end']]);
        }

        return $query;
    }
}
