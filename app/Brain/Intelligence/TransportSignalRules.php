<?php

namespace App\Brain\Intelligence;

/**
 * What the transport figures mean, and what is worth someone's morning.
 *
 * ── WHAT THIS REPLACED, AND WHY ─────────────────────────────────────────────
 *
 * The previous version raised ONE FINDING PER VEHICLE. At the largest institute
 * that was eighty-nine findings, twenty-two of them "critical", and the four
 * loudest were travel-mode markers being read as 15-seat vans carrying 383
 * children. A screen in that state is not intelligence; it is a list, and a
 * reader who scrolls eighty-nine cards learns less than one who reads none.
 *
 * Three things changed:
 *
 *  1. ONE FINDING PER PATTERN, NOT PER ROW. "Eleven vehicles are over their
 *     seating" is one fact about the fleet. The vehicles are named in the
 *     evidence, worst first, which is where a list belongs.
 *
 *  2. A COHORT FLOOR ON EVERY COMPARISON. A 3-seat shuttle at 133% and a
 *     40-seat bus at 133% are not the same fact. Nothing is raised below
 *     {@see TransportIntelligence::MIN_VEHICLE_COHORT} riders, and findings are
 *     ordered by how many children are affected rather than by the percentage,
 *     so a small cohort with a dramatic ratio can never outrank a large one.
 *
 *  3. RECORDS WHOSE CAPACITY CANNOT BE TRUE ARE A DATA PROBLEM, NOT A SAFETY
 *     ONE. They are excluded here and reported in the data-quality ledger,
 *     because "this bus is dangerously full" and "this number is wrong" call
 *     for different people and different actions.
 */
final class TransportSignalRules
{
    /** Named in a finding's evidence; beyond this the evidence stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    /** A stop this busy is a boarding-time problem rather than a statistic. */
    private const CROWDED_STOP_RIDERS = 60;

    /**
     * Below this share of the fleet, "some vehicles are over their seats" is an
     * exception to handle, not a pattern to report to a principal.
     */
    private const FLEET_PATTERN_SHARE = 5.0;

    public function __construct(
        private readonly TransportIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'fleet_overcapacity' => [
                'Vehicles carrying more children than they seat',
                fn () => $this->fleetOvercapacity(),
            ],
            'fleet_underuse' => [
                'Vehicles running under half full',
                fn () => $this->fleetUnderuse(),
            ],
            'crowded_stop' => [
                'Boarding points carrying an outsized share',
                fn () => $this->crowdedStops(),
            ],
            'riders_off_roll' => [
                'Riders with no enrolment for this year',
                fn () => $this->ridersOffRoll(),
            ],
            'unbilled_seats' => [
                'Arrangements carrying no transport amount',
                fn () => $this->unbilledSeats(),
            ],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = [
                'key' => $key,
                'label' => $label,
                'checked' => true,
                'raised' => $raised !== [],
            ];
            foreach ($raised as $finding) {
                $findings[] = $finding;
            }
        }

        // Ordered by severity, then by how many children each one touches —
        // never by the size of a percentage.
        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, function ($a, $b) use ($rank) {
            $bySeverity = ($rank[$a['severity']] ?? 5) <=> ($rank[$b['severity']] ?? 5);

            return $bySeverity !== 0
                ? $bySeverity
                : ($b['affected']['count'] ?? 0) <=> ($a['affected']['count'] ?? 0);
        });

        return ['findings' => $findings, 'ruleStatus' => $ruleStatus];
    }

    /* ------------------------------------------------------------ capacity */

    /**
     * MATERIALLY over its seats, not merely over.
     *
     * Splitting the two bands is what makes this finding readable. Reporting
     * every vehicle above 100% put 82 of 253 vehicles in one alert at an
     * institute whose median vehicle runs at 93% — a fleet that is, on the
     * whole, correctly sized, with a long tail of vans carrying one or two more
     * children than they seat. The finding is about the 29 that are 20% or more
     * over; the rest are counted in the same sentence as what they are.
     *
     * @return array<int,array<string,mixed>>
     */
    private function fleetOvercapacity(): array
    {
        $anyOver = $this->credibleVehicles(
            fn ($v) => $v['utilization'] !== null
                && $v['utilization'] > TransportIntelligence::OVERCAPACITY_PERCENT
                && $v['riders'] >= TransportIntelligence::MIN_VEHICLE_COHORT,
        );

        $material = array_values(array_filter(
            $anyOver,
            static fn ($v) => $v['utilization'] >= TransportIntelligence::MATERIAL_OVERLOAD_PERCENT,
        ));

        if ($material === []) {
            return [];
        }

        usort($material, static fn ($a, $b) => ($b['riders'] - $b['capacity']) <=> ($a['riders'] - $a['capacity']));

        $fleet = count($this->credibleVehicles(static fn () => true));
        $share = $fleet > 0 ? round(count($material) / $fleet * 100, 1) : null;
        $excess = array_sum(array_map(static fn ($v) => $v['riders'] - $v['capacity'], $material));
        $affected = array_sum(array_column($material, 'riders'));
        $marginal = count($anyOver) - count($material);
        $worst = $material[0];

        // A single crowded vehicle is an exception; a tenth of the fleet is a
        // planning problem, and the wording has to be able to tell them apart.
        $isPattern = $share !== null && $share >= self::FLEET_PATTERN_SHARE;

        return [[
            'id' => "transport-overcapacity-{$this->syear}",
            'rule' => 'fleet_overcapacity',
            'severity' => $isPattern ? 'high' : 'medium',
            'severityLabel' => $isPattern ? 'High' : 'Medium',
            'title' => count($material) === 1
                ? "{$worst['label']} is carrying a fifth more children than it seats"
                : count($material).' vehicles are carrying at least a fifth more children than they seat',
            'whatHappened' => $this->sentence([
                count($material) === 1
                    ? "{$worst['label']} carries {$worst['riders']} students on {$worst['capacity']} seats, "
                        ."{$worst['utilization']}% of its capacity."
                    : count($material)." of {$fleet} vehicles with a usable seating figure are allocated at "
                        .(int) TransportIntelligence::MATERIAL_OVERLOAD_PERCENT.'% of capacity or above, '
                        ."{$excess} seats' worth in total.",
                count($material) > 1
                    ? "The heaviest is {$worst['label']}: {$worst['riders']} students on {$worst['capacity']} seats, "
                        ."{$worst['utilization']}%."
                    : null,
                $share !== null ? "That is {$share}% of the fleet." : null,
                // The marginal band is stated rather than folded in, so nobody
                // reads this as a claim about a third of the fleet.
                $marginal > 0
                    ? "A further {$marginal} vehicles are between 100% and "
                        .(int) TransportIntelligence::MATERIAL_OVERLOAD_PERCENT.'%, which on a van of this size is '
                        .'one or two children over and is not counted here.'
                    : null,
            ]),
            'whyItMatters' => 'Seating capacity is what the vehicle is registered and insured to carry. An allocation '
                .'a fifth above it is carried every school day by the same children, and it is the school rather '
                .'than the operator that holds the duty of care.',
            'evidence' => $this->vehicleEvidence($material),
            'likelyCause' => 'Either more arrangements were accepted on these vehicles than seats exist, or the '
                .'registered capacity on file is lower than the vehicle actually running the route. This data cannot '
                .'distinguish between them — the first is an allocation decision, the second is a record to correct.',
            'causeConfirmed' => false,
            'recommendation' => 'Check the registered capacity of the named vehicles against the vehicles actually on '
                .'the route, then rebalance the arrangements that remain genuinely over.',
            'owner' => 'Transport in-charge',
            'priority' => $isPattern ? 'high' : 'medium',
            'confidence' => $this->confidence(count($material), $affected),
            'affected' => ['count' => $affected, 'total' => $this->analytics->position()['riders'] ?? null, 'unit' => 'students'],
            'impact' => ['value' => $excess, 'display' => (string) $excess, 'label' => 'seats over capacity'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function fleetUnderuse(): array
    {
        $under = $this->credibleVehicles(
            fn ($v) => $v['utilization'] !== null
                && $v['utilization'] < TransportIntelligence::UNDERUSE_PERCENT
                && $v['riders'] >= TransportIntelligence::MIN_VEHICLE_COHORT,
        );

        $fleet = count($this->credibleVehicles(static fn () => true));
        if ($under === [] || $fleet === 0) {
            return [];
        }

        $share = round(count($under) / $fleet * 100, 1);
        if ($share < self::FLEET_PATTERN_SHARE) {
            // One quiet vehicle is not a pattern and does not justify a finding.
            return [];
        }

        usort($under, static fn ($a, $b) => ($b['capacity'] - $b['riders']) <=> ($a['capacity'] - $a['riders']));

        $emptySeats = array_sum(array_map(static fn ($v) => $v['capacity'] - $v['riders'], $under));
        $affected = array_sum(array_column($under, 'riders'));

        return [[
            'id' => "transport-underuse-{$this->syear}",
            'rule' => 'fleet_underuse',
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => count($under).' vehicles are running under half full',
            'whatHappened' => $this->sentence([
                count($under)." of {$fleet} vehicles with a usable seating figure are carrying fewer than half the "
                    .'students they seat.',
                "{$emptySeats} seats go out empty on those vehicles every trip.",
                "That is {$share}% of the fleet.",
            ]),
            'whyItMatters' => 'A trip costs the same whether the vehicle is full or half full, so empty seats on a '
                .'route are a standing cost rather than spare capacity — and they are the first place to look when '
                .'another route is over its seating.',
            'evidence' => $this->vehicleEvidence($under),
            'likelyCause' => 'Routes may be shaped around where children live rather than around vehicle size, or a '
                .'vehicle may have been kept on a route whose demand has since fallen. Neither is established by this '
                .'data.',
            'causeConfirmed' => false,
            'recommendation' => 'Read this beside the overcapacity finding: the two lists together show whether a '
                .'reallocation between existing vehicles would resolve the crowding without adding a trip.',
            'owner' => 'Transport in-charge',
            'priority' => 'low',
            'confidence' => $this->confidence(count($under), $affected),
            'affected' => ['count' => $affected, 'total' => $this->analytics->position()['riders'] ?? null, 'unit' => 'students'],
            'impact' => ['value' => $emptySeats, 'display' => (string) $emptySeats, 'label' => 'empty seats per trip'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------------- stops */

    /** @return array<int,array<string,mixed>> */
    private function crowdedStops(): array
    {
        $stops = $this->analytics->byStop();
        $totalRiders = array_sum(array_column($stops, 'riders'));
        if ($totalRiders === 0) {
            return [];
        }

        $crowded = array_values(array_filter(
            $stops,
            static fn ($s) => $s['riders'] >= self::CROWDED_STOP_RIDERS,
        ));

        if ($crowded === []) {
            return [];
        }

        $busiest = $crowded[0];
        $busiestShare = round($busiest['riders'] / $totalRiders * 100, 1);
        $affected = array_sum(array_column($crowded, 'riders'));

        return [[
            'id' => "transport-stop-concentration-{$this->syear}",
            'rule' => 'crowded_stop',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$busiest['label']} boards {$busiest['riders']} students, the heaviest of "
                .count($crowded).' busy stops',
            'whatHappened' => $this->sentence([
                count($crowded).' boarding points carry '.self::CROWDED_STOP_RIDERS.' students or more.',
                "{$busiest['label']} is the heaviest at {$busiest['riders']} students across "
                    ."{$busiest['vehicles']} vehicles — {$busiestShare}% of everyone who travels.",
            ]),
            'whyItMatters' => 'Boarding time at a stop scales with the number of children waiting there, and a delay '
                .'at a heavy stop is carried by every stop after it on the same run. It is also where the most '
                .'children are standing at the roadside at once.',
            'evidence' => array_map(static fn ($s) => [
                'label' => $s['label'],
                'value' => "{$s['riders']} students",
                'note' => "{$s['vehicles']} vehicle".($s['vehicles'] === 1 ? '' : 's')
                    .($s['avgDistanceKm'] !== null ? " · {$s['avgDistanceKm']} km average" : ''),
            ], array_slice($crowded, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Check the pickup times at the named stops against the run that follows them, and '
                .'consider staggering vehicles or splitting the heaviest boarding point.',
            'owner' => 'Transport in-charge',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $affected, 'total' => $totalRiders, 'unit' => 'students'],
            'impact' => [
                'value' => $busiest['riders'],
                'display' => (string) $busiest['riders'],
                'label' => 'students at the busiest stop',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ integrity */

    /** @return array<int,array<string,mixed>> */
    private function ridersOffRoll(): array
    {
        $offRoll = $this->analytics->ridersNotOnRoll();
        if ($offRoll === 0) {
            return [];
        }

        $position = $this->analytics->position();
        $riders = $position['riders'] ?? 0;
        $enrolled = $position['enrolled'] ?? 0;
        $share = $riders > 0 ? round($offRoll / $riders * 100, 1) : null;

        // Below a twentieth this is ordinary churn — mid-year leavers whose
        // arrangement was not closed — and saying so is more useful than
        // flagging it.
        $material = $share !== null && $share >= 5.0;
        if (! $material) {
            return [];
        }

        return [[
            'id' => "transport-off-roll-{$this->syear}",
            'rule' => 'riders_off_roll',
            'severity' => 'high',
            'severityLabel' => 'High',
            'title' => "{$offRoll} transport arrangements belong to students with no enrolment this year",
            'whatHappened' => $this->sentence([
                "{$offRoll} of {$riders} transport arrangements for {$this->syear} are held by students who have no "
                    ."enrolment row for {$this->syear}.",
                $enrolled > 0 ? "The roll for this year holds {$enrolled} students." : null,
                $share !== null ? "That is {$share}% of everyone recorded as travelling." : null,
            ]),
            'whyItMatters' => 'Every figure on this screen counts these arrangements, so the rider total, the vehicle '
                .'loads and the transport billing are all computed over more students than the roll contains. Until '
                .'this is resolved none of them reconciles against the student module.',
            'evidence' => [
                ['label' => 'Arrangements with no enrolment', 'value' => (string) $offRoll],
                ['label' => 'Arrangements this year', 'value' => (string) $riders],
                ['label' => 'Students on the roll', 'value' => $enrolled > 0 ? (string) $enrolled : '—'],
                ['label' => 'Share of arrangements', 'value' => $share !== null ? "{$share}%" : '—'],
            ],
            'likelyCause' => 'Arrangements carried forward from a previous year without being closed when the student '
                .'left, or enrolments recorded under a different student record. This data shows the mismatch, not '
                .'which of the two produced it.',
            'causeConfirmed' => false,
            'recommendation' => 'Reconcile the transport list against this year’s roll before using any rider or '
                .'billing figure from this screen, and close the arrangements of students who have left.',
            'owner' => 'Transport in-charge with the admissions office',
            'priority' => 'high',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $offRoll, 'total' => $riders, 'unit' => 'arrangements'],
            'impact' => ['value' => $offRoll, 'display' => (string) $offRoll, 'label' => 'arrangements to reconcile'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function unbilledSeats(): array
    {
        $position = $this->analytics->position();
        $unbilled = $position['unbilledArrangements'] ?? 0;
        $total = ($position['billedArrangements'] ?? 0) + $unbilled;

        if ($unbilled === 0 || $total === 0) {
            return [];
        }

        $share = round($unbilled / $total * 100, 1);
        if ($share < 5.0) {
            return [];
        }

        // Which distance bands they sit in is the whole question: an unbilled
        // arrangement at 0 km is almost certainly a child who makes their own
        // way, and one at 12 km is almost certainly a seat nobody charged for.
        $bands = array_values(array_filter(
            $this->analytics->byDistanceBand(),
            static fn ($b) => $b['unbilled'] > 0,
        ));
        usort($bands, static fn ($a, $b) => $b['unbilled'] <=> $a['unbilled']);

        $overZero = array_sum(array_map(
            static fn ($b) => $b['key'] === 'none' ? 0 : $b['unbilled'],
            $bands,
        ));

        return [[
            'id' => "transport-unbilled-{$this->syear}",
            'rule' => 'unbilled_seats',
            'severity' => $overZero > 0 ? 'medium' : 'low',
            'severityLabel' => $overZero > 0 ? 'Medium' : 'Low',
            'title' => "{$unbilled} transport arrangements carry no amount",
            'whatHappened' => $this->sentence([
                "{$unbilled} of {$total} arrangements ({$share}%) have no transport amount recorded.",
                $overZero > 0
                    ? "{$overZero} of them record a travelled distance above zero, so they are seats on a route "
                        .'rather than students making their own way.'
                    : 'All of them record no distance travelled, which is consistent with students making their own '
                        .'way rather than with unbilled seats.',
            ]),
            'whyItMatters' => $overZero > 0
                ? 'An arrangement with a distance but no amount occupies a seat that the transport income does not '
                    .'account for. Whether each one is a waiver or an omission is a decision someone has made or has '
                    .'yet to make, and the two should not be indistinguishable in the ledger.'
                : 'These are recorded as transport arrangements but travel no distance and carry no charge, so they '
                    .'inflate the rider count without occupying a seat.',
            'evidence' => array_map(static fn ($b) => [
                'label' => $b['label'],
                'value' => "{$b['unbilled']} unbilled",
                'note' => "of {$b['arrangements']} arrangements"
                    .($b['avgAmount'] !== null ? " · ₹{$b['avgAmount']} average where billed" : ''),
            ], array_slice($bands, 0, self::MAX_NAMED_IN_EVIDENCE)),
            'likelyCause' => 'Fee waivers, staff children, or arrangements created before the distance-based rate was '
                .'applied. This data records the absence of an amount, not the reason for it.',
            'causeConfirmed' => false,
            'recommendation' => $overZero > 0
                ? 'Review the unbilled arrangements that record a distance, and record a waiver where one was granted '
                    .'so the ledger distinguishes a decision from an omission.'
                : 'Confirm whether these should be held as transport arrangements at all, since they travel no '
                    .'distance and occupy no seat.',
            'owner' => 'Transport in-charge with the fees office',
            'priority' => $overZero > 0 ? 'medium' : 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $unbilled, 'total' => $total, 'unit' => 'arrangements'],
            'impact' => ['value' => $unbilled, 'display' => (string) $unbilled, 'label' => 'arrangements unpriced'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * Vehicles whose capacity figure can be believed, filtered further.
     *
     * @param  callable(array<string,mixed>):bool  $predicate
     * @return array<int,array<string,mixed>>
     */
    private function credibleVehicles(callable $predicate): array
    {
        return array_values(array_filter(
            $this->analytics->byVehicle(),
            static fn ($v) => $v['capacityCredible'] && $predicate($v),
        ));
    }

    /**
     * @param  array<int,array<string,mixed>>  $vehicles
     * @return array<int,array<string,mixed>>
     */
    private function vehicleEvidence(array $vehicles): array
    {
        $points = array_map(static fn ($v) => [
            'label' => $v['label'],
            'value' => "{$v['riders']} on {$v['capacity']} seats",
            'note' => "{$v['utilization']}%"
                .($v['avgDistanceKm'] !== null ? " · {$v['avgDistanceKm']} km average run" : ''),
        ], array_slice($vehicles, 0, self::MAX_NAMED_IN_EVIDENCE));

        $remaining = count($vehicles) - count($points);
        if ($remaining > 0) {
            $points[] = [
                'label' => 'Not named above',
                'value' => "{$remaining} more",
                'note' => 'Listed in the by-vehicle breakdown.',
            ];
        }

        return $points;
    }

    /**
     * Confidence in the PATTERN, not in the arithmetic.
     *
     * The arithmetic is exact. What is uncertain is whether a handful of
     * vehicles is a pattern at all, so confidence rises with how many vehicles
     * and how many children stand behind the finding, and is capped where the
     * cohort is thin. A small cohort can never present as strongly as a large
     * one, whatever its percentage.
     *
     * @return array{band:string,value:float}
     */
    private function confidence(int $vehicles, int $students): array
    {
        if ($vehicles >= 5 && $students >= 100) {
            return ['band' => 'High', 'value' => 0.92];
        }
        if ($vehicles >= 3 || $students >= 50) {
            return ['band' => 'Medium', 'value' => 0.7];
        }

        return ['band' => 'Low', 'value' => 0.45];
    }

    /** @param array<int,?string> $parts */
    private function sentence(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }
}
