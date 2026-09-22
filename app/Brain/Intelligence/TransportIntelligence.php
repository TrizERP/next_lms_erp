<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Transport Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `transport_map_student` is ONE STUDENT'S TRANSPORT ARRANGEMENT for
 * one academic year: a morning vehicle and stop (`from_bus_id`, `from_stop`), an
 * afternoon vehicle and stop (`to_bus_id`, `to_stop`), the shift they travel in,
 * the chargeable distance in kilometres and the amount billed for it.
 *
 * ── TWO THINGS THE PROFILER FOUND, AND WHY THEY CHANGED THIS FILE ───────────
 *
 * 1. `from_stop` IS A FOREIGN KEY, NOT A NAME. The previous version rendered
 *    `(string) $row->from_stop` as the stop label, so the breakdown listed bus
 *    stops as "2412" and "2406" instead of "Vesu" and "Althan". They join to
 *    `transport_stop.id` cleanly — 0 of 4,596 rows at the largest institute
 *    fail to resolve — so there was never a reason to show the key.
 *
 * 2. `transport_vehicle` HOLDS RECORDS THAT ARE NOT VEHICLES. Alongside real
 *    vans it holds travel-mode markers — rows titled "Unaccompanied (Purple)",
 *    "Accompanied (Green)", "self" — carrying a nominal seating capacity that
 *    means nothing. Reading those as buses produced "383 students against a
 *    seating capacity of 15 — 2553% utilization" and twenty-one more like it,
 *    presented as critical child-safety alerts. They are not overcrowding; they
 *    are records whose capacity figure cannot be believed.
 *
 *    THE TEST IS PHYSICAL PLAUSIBILITY, NOT A NAME LIST. A record carrying more
 *    than {@see self::IMPLAUSIBLE_LOAD_MULTIPLE}× its stated capacity is not
 *    reporting a crowded bus; something about the record is wrong. Across the
 *    whole database exactly six records fail that test and every one of them is
 *    a mode marker, so the bound separates them cleanly without this file
 *    knowing a single school's vocabulary. They are excluded from every
 *    capacity figure and reported as a data-quality check instead — the
 *    honest place for "this number cannot be trusted".
 *
 * ── WHAT IS AGGREGATED IN SQL AND WHY ───────────────────────────────────────
 *
 * Every figure below is a GROUP BY against an indexed `(sub_institute_id,
 * syear)` filter. Nothing walks the roll student by student.
 */
final class TransportIntelligence
{
    private const MAP_TABLE = 'transport_map_student';

    private const VEHICLE_TABLE = 'transport_vehicle';

    private const STOP_TABLE = 'transport_stop';

    private const ROUTE_TABLE = 'transport_route';

    private const ROUTE_BUS_TABLE = 'transport_route_bus';

    private const SHIFT_TABLE = 'transport_school_shift';

    private const ENROLLMENT_TABLE = 'tblstudent_enrollment';

    /**
     * Above this multiple of stated capacity, the record is not describing a
     * crowded vehicle — it is describing something that is not a vehicle, or a
     * capacity that was never filled in. A genuinely overcrowded school van runs
     * at 110–150% of its seats; 300% is not a transport problem.
     */
    public const IMPLAUSIBLE_LOAD_MULTIPLE = 3.0;

    /** Above this load a plausible vehicle is carrying more children than it seats. */
    public const OVERCAPACITY_PERCENT = 100.0;

    /**
     * Where "over its seats" becomes worth acting on.
     *
     * A 14-seat van carrying 15 children is over capacity and is an ordinary
     * consequence of counting seats in whole children — at the largest institute
     * 53 of 253 vehicles sit in that 101–120% band. Twenty per cent over is the
     * line where the load stops being roundable and starts being a decision
     * somebody made, and 29 vehicles are on the far side of it. Reporting both
     * bands separately is what lets the screen say which is which instead of
     * calling 82 vehicles a safety incident.
     */
    public const MATERIAL_OVERLOAD_PERCENT = 120.0;

    /**
     * Below this load a vehicle is running mostly empty. Transport is a fixed
     * cost per trip, so an under-filled vehicle is money, not a safety issue.
     */
    public const UNDERUSE_PERCENT = 50.0;

    /**
     * A vehicle carrying fewer than this many students is not evidence of
     * anything: a 3-seat shuttle at 33% and a 40-seat bus at 33% are different
     * facts, and only the second is worth a principal's morning.
     */
    public const MIN_VEHICLE_COHORT = 8;

    private readonly string $tenantId;

    private readonly ?string $syear;

    /** @var array<string,mixed> Memoised per request; every getter is called by both the analytics and the rules. */
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
        $empty = [
            'sources' => [],
            'counts' => [],
        ];

        if ($this->syear === null) {
            return $empty + [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute.',
            ];
        }

        if (! SchemaCache::hasTable(self::MAP_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::MAP_TABLE."' does not exist in this deployment.",
            ];
        }

        $arrangements = (int) $this->scopedMap()->count();

        if ($arrangements === 0) {
            return $empty + [
                'available' => false,
                'reason' => "No student transport arrangements are recorded for academic year {$this->syear}. "
                    .'Students travelling by their own arrangement are not mapped here, so an institute that '
                    .'runs no fleet will be empty by design rather than by omission.',
            ];
        }

        $riders = (int) $this->scopedMap()->distinct()->count('student_id');
        $vehiclesUsed = (int) $this->scopedMap()->where('from_bus_id', '>', 0)->distinct()->count('from_bus_id');
        $stopsUsed = (int) $this->scopedMap()->where('from_stop', '>', 0)->distinct()->count('from_stop');

        $fleet = SchemaCache::hasTable(self::VEHICLE_TABLE)
            ? (int) DB::table(self::VEHICLE_TABLE)->where('sub_institute_id', $this->tenantId)->count()
            : 0;

        $routes = SchemaCache::hasTable(self::ROUTE_BUS_TABLE)
            ? (int) DB::table(self::ROUTE_BUS_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->distinct()
                ->count('route_id')
            : 0;

        $priced = (int) $this->scopedMap()->where('amount', '>', 0)->count();

        return [
            'available' => true,
            'reason' => null,
            'sources' => [
                'arrangements' => $arrangements > 0,
                'vehicles' => $fleet > 0,
                'stops' => $stopsUsed > 0,
                // Route mapping is year-scoped and is frequently left on the
                // year-0 master rather than carried forward, so a module that
                // shows a route breakdown must say whether THIS year has any.
                'routesThisYear' => $routes > 0,
                'billing' => $priced > 0,
            ],
            'counts' => [
                'arrangements' => $arrangements,
                'riders' => $riders,
                'vehiclesInService' => $vehiclesUsed,
                'fleetOnFile' => $fleet,
                'stopsInService' => $stopsUsed,
                'routesThisYear' => $routes,
                'pricedArrangements' => $priced,
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
        $vehicles = $this->byVehicle();

        $credible = array_values(array_filter($vehicles, static fn ($v) => $v['capacityCredible']));
        $seats = array_sum(array_column($credible, 'capacity'));
        $seated = array_sum(array_column($credible, 'riders'));

        $loads = array_values(array_filter(
            array_column($credible, 'utilization'),
            static fn ($u) => $u !== null,
        ));
        sort($loads);

        $enrolled = $this->enrolledStudents();
        $travel = $this->travelProfile();

        return [
            'riders' => $counts['riders'],
            'enrolled' => $enrolled,
            // NULL, NOT ZERO: with no roll for this year, the share of the roll
            // that travels is undefined rather than nought per cent.
            'riderShareOfRoll' => $enrolled > 0 ? round($counts['riders'] / $enrolled * 100, 1) : null,
            'vehiclesInService' => $counts['vehiclesInService'],
            'fleetOnFile' => $counts['fleetOnFile'],
            'stopsInService' => $counts['stopsInService'],
            'credibleVehicles' => count($credible),
            'seatsOffered' => $seats > 0 ? $seats : null,
            'seatUtilization' => $seats > 0 ? round($seated / $seats * 100, 1) : null,
            'medianVehicleLoad' => $loads === [] ? null : round($loads[(int) floor(count($loads) / 2)], 1),
            'overcapacityVehicles' => count(array_filter(
                $credible,
                static fn ($v) => $v['utilization'] !== null && $v['utilization'] > self::OVERCAPACITY_PERCENT,
            )),
            'materiallyOverVehicles' => count(array_filter(
                $credible,
                static fn ($v) => $v['utilization'] !== null && $v['utilization'] >= self::MATERIAL_OVERLOAD_PERCENT,
            )),
            'underusedVehicles' => count(array_filter(
                $credible,
                static fn ($v) => $v['utilization'] !== null
                    && $v['utilization'] < self::UNDERUSE_PERCENT
                    && $v['riders'] >= self::MIN_VEHICLE_COHORT,
            )),
            'medianDistanceKm' => $travel['medianDistance'],
            'billedArrangements' => $counts['pricedArrangements'],
            'unbilledArrangements' => $counts['arrangements'] - $counts['pricedArrangements'],
        ];
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * One row per vehicle actually carrying children this year.
     *
     * `capacityCredible` is the whole point of this method: it is false when the
     * row's load makes its stated capacity impossible, and every capacity-based
     * figure downstream filters on it.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byVehicle(): array
    {
        return $this->memo['byVehicle'] ??= $this->computeByVehicle();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByVehicle(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $rows = DB::table(self::MAP_TABLE.' as m')
            ->leftJoin(self::VEHICLE_TABLE.' as v', 'v.id', '=', 'm.from_bus_id')
            ->where('m.sub_institute_id', $this->tenantId)
            ->where('m.syear', $this->syear)
            ->where('m.from_bus_id', '>', 0)
            ->groupBy('m.from_bus_id', 'v.vehicle_number', 'v.title', 'v.vehicle_type', 'v.sitting_capacity')
            ->select(
                'm.from_bus_id',
                DB::raw('COALESCE(NULLIF(v.vehicle_number, ""), NULLIF(v.title, ""), CONCAT("Vehicle #", m.from_bus_id)) as vehicle_label'),
                DB::raw('COALESCE(NULLIF(v.vehicle_type, ""), "Not recorded") as vehicle_type'),
                'v.sitting_capacity',
                DB::raw('COUNT(DISTINCT m.student_id) as riders'),
                DB::raw('ROUND(AVG(NULLIF(m.distance, 0)), 1) as avg_distance')
            )
            ->orderByDesc('riders')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            // A capacity that was never entered is unknown, not zero seats.
            $capacity = $row->sitting_capacity === null ? null : (int) $row->sitting_capacity;
            if ($capacity !== null && $capacity <= 0) {
                $capacity = null;
            }

            $riders = (int) $row->riders;
            $utilization = $capacity !== null ? round($riders / $capacity * 100, 1) : null;
            $credible = $capacity !== null && $riders <= $capacity * self::IMPLAUSIBLE_LOAD_MULTIPLE;

            $out[] = [
                'key' => (string) $row->from_bus_id,
                'label' => (string) $row->vehicle_label,
                'vehicleType' => (string) $row->vehicle_type,
                'riders' => $riders,
                'capacity' => $capacity,
                'utilization' => $utilization,
                'capacityCredible' => $credible,
                'avgDistanceKm' => $row->avg_distance === null ? null : (float) $row->avg_distance,
            ];
        }

        return $out;
    }

    /**
     * One row per boarding stop, NAMED.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byStop(): array
    {
        return $this->memo['byStop'] ??= $this->computeByStop();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByStop(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $rows = DB::table(self::MAP_TABLE.' as m')
            ->leftJoin(self::STOP_TABLE.' as s', 's.id', '=', 'm.from_stop')
            ->where('m.sub_institute_id', $this->tenantId)
            ->where('m.syear', $this->syear)
            ->where('m.from_stop', '>', 0)
            ->groupBy('m.from_stop', 's.stop_name')
            ->select(
                'm.from_stop',
                's.stop_name',
                DB::raw('COUNT(DISTINCT m.student_id) as riders'),
                DB::raw('COUNT(DISTINCT m.from_bus_id) as vehicles'),
                DB::raw('ROUND(AVG(NULLIF(m.distance, 0)), 1) as avg_distance')
            )
            ->orderByDesc('riders')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'key' => (string) $row->from_stop,
                // An unresolved key says so rather than being shown as a name.
                'label' => $row->stop_name !== null && $row->stop_name !== ''
                    ? (string) $row->stop_name
                    : "Stop #{$row->from_stop} (not in the stop master)",
                'named' => $row->stop_name !== null && $row->stop_name !== '',
                'riders' => (int) $row->riders,
                'vehicles' => (int) $row->vehicles,
                'avgDistanceKm' => $row->avg_distance === null ? null : (float) $row->avg_distance,
            ];
        }

        return $out;
    }

    /**
     * Riders by travel shift, which is how the fleet is actually rostered.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byShift(): array
    {
        return $this->memo['byShift'] ??= $this->computeByShift();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByShift(): array
    {
        if (! $this->coverage()['available'] || ! SchemaCache::hasTable(self::SHIFT_TABLE)) {
            return [];
        }

        $rows = DB::table(self::MAP_TABLE.' as m')
            ->leftJoin(self::SHIFT_TABLE.' as sh', 'sh.id', '=', 'm.from_shift_id')
            ->where('m.sub_institute_id', $this->tenantId)
            ->where('m.syear', $this->syear)
            ->groupBy('m.from_shift_id', 'sh.shift_title')
            ->select(
                'm.from_shift_id',
                DB::raw('COALESCE(NULLIF(sh.shift_title, ""), CONCAT("Shift #", m.from_shift_id)) as shift_label'),
                DB::raw('COUNT(DISTINCT m.student_id) as riders'),
                DB::raw('COUNT(DISTINCT m.from_bus_id) as vehicles'),
                DB::raw('ROUND(AVG(NULLIF(m.distance, 0)), 1) as avg_distance')
            )
            ->orderByDesc('riders')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'key' => (string) $row->from_shift_id,
                'label' => (string) $row->shift_label,
                'riders' => (int) $row->riders,
                'vehicles' => (int) $row->vehicles,
                'avgDistanceKm' => $row->avg_distance === null ? null : (float) $row->avg_distance,
            ];
        }

        return $out;
    }

    /**
     * Riders by chargeable distance, with what each band is actually billed.
     *
     * WHY THIS IS A BREAKDOWN AND NOT A FINDING. Distance is the input to the
     * transport fee, so the shape of this table is the shape of the transport
     * income — and a band whose average charge does not rise with its distance
     * is visible here without the module having to assert anything about it.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byDistanceBand(): array
    {
        return $this->memo['byDistanceBand'] ??= $this->computeByDistanceBand();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByDistanceBand(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }

        $bands = [
            ['key' => 'none', 'label' => 'No distance recorded', 'min' => null, 'max' => 0],
            ['key' => 'upto3', 'label' => '1 to 3 km', 'min' => 1, 'max' => 3],
            ['key' => 'upto6', 'label' => '4 to 6 km', 'min' => 4, 'max' => 6],
            ['key' => 'upto10', 'label' => '7 to 10 km', 'min' => 7, 'max' => 10],
            ['key' => 'upto15', 'label' => '11 to 15 km', 'min' => 11, 'max' => 15],
            ['key' => 'over15', 'label' => 'Over 15 km', 'min' => 16, 'max' => null],
        ];

        $rows = $this->scopedMap()
            ->selectRaw(
                'CASE
                    WHEN distance IS NULL OR distance <= 0 THEN "none"
                    WHEN distance <= 3 THEN "upto3"
                    WHEN distance <= 6 THEN "upto6"
                    WHEN distance <= 10 THEN "upto10"
                    WHEN distance <= 15 THEN "upto15"
                    ELSE "over15"
                 END as band,
                 COUNT(*) as arrangements,
                 ROUND(AVG(NULLIF(amount, 0))) as avg_amount,
                 SUM(CASE WHEN amount IS NULL OR amount <= 0 THEN 1 ELSE 0 END) as unbilled'
            )
            ->groupBy('band')
            ->get()
            ->keyBy('band');

        $out = [];
        foreach ($bands as $band) {
            $row = $rows->get($band['key']);
            if ($row === null) {
                continue;
            }
            $out[] = [
                'key' => $band['key'],
                'label' => $band['label'],
                'arrangements' => (int) $row->arrangements,
                // An average over nothing billed is undefined, not ₹0.
                'avgAmount' => $row->avg_amount === null ? null : (int) $row->avg_amount,
                'unbilled' => (int) $row->unbilled,
            ];
        }

        return $out;
    }

    /**
     * Riders and vehicles per named route, where this year has route mapping.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byRoute(): array
    {
        return $this->memo['byRoute'] ??= $this->computeByRoute();
    }

    /** @return array<int,array<string,mixed>> */
    private function computeByRoute(): array
    {
        if (! $this->coverage()['available']) {
            return [];
        }
        if (! SchemaCache::hasTable(self::ROUTE_BUS_TABLE) || ! SchemaCache::hasTable(self::ROUTE_TABLE)) {
            return [];
        }

        $rows = DB::table(self::ROUTE_BUS_TABLE.' as rb')
            ->join(self::MAP_TABLE.' as m', function ($join) {
                $join->on('m.from_bus_id', '=', 'rb.bus_id')
                    ->where('m.sub_institute_id', '=', $this->tenantId)
                    ->where('m.syear', '=', $this->syear);
            })
            ->leftJoin(self::ROUTE_TABLE.' as r', 'r.id', '=', 'rb.route_id')
            ->where('rb.sub_institute_id', $this->tenantId)
            ->where('rb.syear', $this->syear)
            ->groupBy('rb.route_id', 'r.route_name')
            ->select(
                'rb.route_id',
                DB::raw('COALESCE(NULLIF(r.route_name, ""), CONCAT("Route #", rb.route_id)) as route_label'),
                DB::raw('COUNT(DISTINCT m.student_id) as riders'),
                DB::raw('COUNT(DISTINCT rb.bus_id) as vehicles')
            )
            ->orderByDesc('riders')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'key' => (string) $row->route_id,
                'label' => (string) $row->route_label,
                'riders' => (int) $row->riders,
                'vehicles' => (int) $row->vehicles,
            ];
        }

        return $out;
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

        $total = (int) $coverage['counts']['arrangements'];

        $noVehicle = (int) $this->scopedMap()
            ->where(fn ($q) => $q->whereNull('from_bus_id')->orWhere('from_bus_id', '<=', 0))
            ->count();

        $noStop = (int) $this->scopedMap()
            ->where(fn ($q) => $q->whereNull('from_stop')->orWhere('from_stop', '<=', 0))
            ->count();

        $unnamedStops = count(array_filter($this->byStop(), static fn ($s) => ! $s['named']));

        $incredible = array_values(array_filter($this->byVehicle(), static fn ($v) => ! $v['capacityCredible']));
        $incredibleRiders = array_sum(array_column($incredible, 'riders'));

        $offRoll = $this->ridersNotOnRoll();
        $unbilled = $total - (int) $coverage['counts']['pricedArrangements'];

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'riders_off_roll',
                    'label' => 'Riders not on this year’s roll',
                    'value' => $offRoll,
                    'format' => 'count',
                    'sharePercent' => $total > 0 ? round($offRoll / $total * 100, 2) : null,
                    'shareLabel' => 'of arrangements',
                    'state' => $offRoll > 0 ? 'attention' : 'ok',
                    'note' => $offRoll > 0
                        ? 'These students hold a transport arrangement for this year but no enrolment row for it. '
                            .'Until that is resolved the rider count cannot be reconciled against the roll, and '
                            .'seats may be held for children who have left.'
                        : 'Every rider this year also holds an enrolment row for it.',
                ],
                [
                    'key' => 'capacity_not_credible',
                    'label' => 'Records whose capacity cannot be true',
                    'value' => count($incredible),
                    'format' => 'count',
                    'sharePercent' => $total > 0 ? round($incredibleRiders / $total * 100, 2) : null,
                    'shareLabel' => 'of arrangements sit on them',
                    'state' => $incredible !== [] ? 'attention' : 'ok',
                    'note' => $incredible !== []
                        ? 'Carrying more than '.(int) self::IMPLAUSIBLE_LOAD_MULTIPLE.'× the seats they declare — '
                            .implode(', ', array_map(
                                static fn ($v) => "{$v['label']} ({$v['riders']} on {$v['capacity']} seats)",
                                array_slice($incredible, 0, 3),
                            ))
                            .'. These are travel-mode markers or unset capacities rather than crowded vehicles, so they '
                            .'are excluded from every seat figure on this screen.'
                        : 'Every vehicle carrying children this year has a capacity its load can fit.',
                ],
                [
                    'key' => 'unassigned_vehicle',
                    'label' => 'Arrangements with no vehicle',
                    'value' => $noVehicle,
                    'format' => 'count',
                    'sharePercent' => $total > 0 ? round($noVehicle / $total * 100, 2) : null,
                    'shareLabel' => 'of arrangements',
                    'state' => $noVehicle > 0 ? 'attention' : 'ok',
                    'note' => $noVehicle > 0
                        ? 'No morning vehicle is recorded, so these students are counted as riders but appear on no '
                            .'vehicle’s load.'
                        : 'Every arrangement names a morning vehicle.',
                ],
                [
                    'key' => 'unassigned_stop',
                    'label' => 'Arrangements with no boarding stop',
                    'value' => $noStop,
                    'format' => 'count',
                    'sharePercent' => $total > 0 ? round($noStop / $total * 100, 2) : null,
                    'shareLabel' => 'of arrangements',
                    'state' => $noStop > 0 ? 'attention' : 'ok',
                    'note' => $noStop > 0
                        ? 'No pickup point is recorded, so the driver has no boarding list for these children.'
                        : 'Every arrangement names a boarding stop.',
                ],
                [
                    'key' => 'unnamed_stops',
                    'label' => 'Stops missing from the stop master',
                    'value' => $unnamedStops,
                    'format' => 'count',
                    'sharePercent' => null,
                    'state' => $unnamedStops > 0 ? 'attention' : 'ok',
                    'note' => $unnamedStops > 0
                        ? 'These stop keys are in use but have no row in the stop master, so they can only be shown '
                            .'by number.'
                        : 'Every stop in use resolves to a named row in the stop master.',
                ],
                [
                    'key' => 'unbilled_arrangements',
                    'label' => 'Arrangements with no amount',
                    'value' => $unbilled,
                    'format' => 'count',
                    'sharePercent' => $total > 0 ? round($unbilled / $total * 100, 2) : null,
                    'shareLabel' => 'of arrangements',
                    'state' => $unbilled > 0 ? 'attention' : 'ok',
                    'note' => $unbilled > 0
                        ? 'No transport amount is recorded against these arrangements. Some will be genuine waivers '
                            .'and some will be unbilled seats; this check cannot tell them apart, only that they are '
                            .'not priced.'
                        : 'Every arrangement carries a transport amount.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /** How many students this year's transport arrangements cannot be matched to the roll. */
    public function ridersNotOnRoll(): int
    {
        return $this->memo['offRoll'] ??= (function (): int {
            if (! SchemaCache::hasTable(self::ENROLLMENT_TABLE)) {
                return 0;
            }

            return (int) DB::table(self::MAP_TABLE.' as m')
                ->leftJoin(self::ENROLLMENT_TABLE.' as e', function ($join) {
                    $join->on('e.student_id', '=', 'm.student_id')
                        ->on('e.sub_institute_id', '=', 'm.sub_institute_id')
                        ->on('e.syear', '=', 'm.syear');
                })
                ->where('m.sub_institute_id', $this->tenantId)
                ->where('m.syear', $this->syear)
                ->whereNull('e.student_id')
                ->count();
        })();
    }

    /** This year's roll, for the share-of-roll figure. */
    public function enrolledStudents(): int
    {
        return $this->memo['enrolled'] ??= SchemaCache::hasTable(self::ENROLLMENT_TABLE)
            ? (int) DB::table(self::ENROLLMENT_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->distinct()
                ->count('student_id')
            : 0;
    }

    /** @return array{medianDistance:?float} */
    private function travelProfile(): array
    {
        return $this->memo['travel'] ??= (function (): array {
            $distances = $this->scopedMap()
                ->where('distance', '>', 0)
                ->orderBy('distance')
                ->pluck('distance')
                ->all();

            if ($distances === []) {
                return ['medianDistance' => null];
            }

            return ['medianDistance' => (float) $distances[(int) floor(count($distances) / 2)]];
        })();
    }

    /** Every query in this class starts here, so no figure can escape the tenant-year filter. */
    private function scopedMap(): \Illuminate\Database\Query\Builder
    {
        return DB::table(self::MAP_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', $this->syear);
    }
}
