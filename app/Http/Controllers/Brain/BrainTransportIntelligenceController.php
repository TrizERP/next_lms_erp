<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\TransportIntelligence;
use App\Brain\Intelligence\TransportSignalRules;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainTransportIntelligenceController extends Controller
{
    private string $tenantId = '';
    private ?string $syear = null;
    private string $actorId = '';
    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        // The L5 half of the payload, read back from the signal ledger this
        // module's findings were written to by the pipeline.
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'transport', 'arrangements');

        $analytics = new TransportIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new TransportSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · transport_map_student, transport_vehicle, transport_stop, transport_school_shift, transport_route_bus',
            'academicYear' => ['syear' => $this->syear],
            'coverage' => $coverage,
            'freshness' => [
                'positionLabel' => 'Read live at page load',
                // The ledger's own timestamp when the loop has run for this module,
                // falling back to now for the live per-request computation.
                'findingsRefreshedAt' => $loop->signalsRefreshedAt()
                    ?? ($coverage['available'] ? now()->toIso8601String() : null),
                'findingsLabel' => 'Computed on this request',
            ],
            'execution' => [
                'automated' => false,
                'note' => 'Every figure is read from this year’s transport arrangements at the moment the page loaded.',
            ],
            'summary' => $this->summary($pos, $coverage, $raised['findings']),
            'position' => $this->position($pos, $coverage),
            'breakdowns' => $this->breakdowns($analytics, $coverage),
            'findings' => $raised['findings'],
            'priorities' => $this->priorities($raised['findings']),
            'recommendations' => $loop->recommendations(),
            'decisionTrail' => $loop->decisionTrail(),
            'learning' => $loop->learning(),
            'dataQuality' => $analytics->dataQuality(),
            'ruleStatus' => $raised['ruleStatus'],
        ]));
    }

    /**
     * "What is happening", composed from the same figures the cards below show.
     *
     * DETERMINISTIC, NEVER MODEL OUTPUT. Every sentence is assembled from a
     * value in `position()`; nothing here is generated, and no figure appears
     * that the reader cannot find again lower down the page.
     */
    private function summary(?array $pos, array $coverage, array $findings): array
    {
        if ($pos === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'No transport arrangements in this academic year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        // A share above 100% is not a rounding artefact — it means arrangements
        // are held by students the roll does not contain. Saying so here is the
        // difference between a figure that looks wrong and one that explains
        // itself; the finding below gives the count.
        $sentences[] = match (true) {
            $pos['riderShareOfRoll'] === null => "{$pos['riders']} students travel on the institute's own transport.",
            $pos['riderShareOfRoll'] > 100.0 => "{$pos['riders']} transport arrangements are on file against a roll of "
                ."{$pos['enrolled']} students, so more arrangements exist than there are students enrolled this year.",
            default => "{$pos['riders']} students travel on the institute's own transport, "
                ."{$pos['riderShareOfRoll']}% of the {$pos['enrolled']} on this year's roll.",
        };

        $sentences[] = "They are carried by {$pos['vehiclesInService']} of the {$pos['fleetOnFile']} vehicles on file, "
            ."across {$pos['stopsInService']} boarding points.";

        if ($pos['seatUtilization'] !== null) {
            $sentences[] = "Across the {$pos['credibleVehicles']} vehicles whose seating figure can be believed, "
                ."{$pos['seatUtilization']}% of seats are taken, with the median vehicle at "
                ."{$pos['medianVehicleLoad']}%.";
        } else {
            // Saying WHY the seat figure is absent is the difference between a
            // gap in the records and a fleet with no seats.
            $sentences[] = 'No vehicle this year carries a seating capacity that its own load makes possible, so seat '
                .'utilisation cannot be computed. The record checks below name the records involved.';
        }

        if ($pos['overcapacityVehicles'] > 0 || $pos['underusedVehicles'] > 0) {
            $marginal = $pos['overcapacityVehicles'] - $pos['materiallyOverVehicles'];
            $sentences[] = "{$pos['materiallyOverVehicles']} vehicle"
                .($pos['materiallyOverVehicles'] === 1 ? ' is' : 's are').' at least a fifth over capacity'
                .($marginal > 0 ? ", {$marginal} more are between one and twenty per cent over," : '')
                ." and {$pos['underusedVehicles']} ".($pos['underusedVehicles'] === 1 ? 'is' : 'are')
                .' running under half full.';
        }

        if ($pos['medianDistanceKm'] !== null) {
            $total = $pos['billedArrangements'] + $pos['unbilledArrangements'];
            $sentences[] = "The median journey on file is {$pos['medianDistanceKm']} km, and "
                ."{$pos['unbilledArrangements']} of {$total} arrangements carry no transport amount.";
        }

        $count = count($findings);
        $sentences[] = match ($count) {
            0 => 'No check found enough evidence to raise a finding for this year.',
            1 => 'One finding below, with the figures it rests on.',
            default => "{$count} findings below, each with the figures it rests on.",
        };

        return [
            'available' => true,
            'reason' => null,
            'headline' => $pos['seatUtilization'] !== null
                ? "{$pos['riders']} riders, {$pos['vehiclesInService']} vehicles, {$pos['seatUtilization']}% of seats taken"
                : "{$pos['riders']} riders across {$pos['vehiclesInService']} vehicles",
            'sentences' => $sentences,
        ];
    }

    private function position(?array $pos, array $coverage): ?array
    {
        if ($pos === null) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'riders',
                    'label' => 'Students travelling',
                    'value' => $pos['riders'],
                    'format' => 'count',
                    'tone' => $pos['riderShareOfRoll'] !== null && $pos['riderShareOfRoll'] > 100.0
                        ? 'attention'
                        : null,
                    'hint' => match (true) {
                        $pos['riderShareOfRoll'] === null => 'No roll on file for this year to compare against',
                        $pos['riderShareOfRoll'] > 100.0 => "More than this year's roll of {$pos['enrolled']} — see the record checks",
                        default => "{$pos['riderShareOfRoll']}% of this year's roll",
                    },
                ],
                [
                    'key' => 'seatUtilization',
                    'label' => 'Seats taken',
                    // Null where no vehicle has a believable capacity. An em
                    // dash, never a zero: a rate over no denominator is
                    // undefined, not nought per cent.
                    'value' => $pos['seatUtilization'],
                    'format' => 'percent',
                    'hint' => $pos['seatsOffered'] !== null
                        ? "Across {$pos['credibleVehicles']} vehicles offering {$pos['seatsOffered']} seats"
                        : 'No vehicle carries a capacity its own load makes possible',
                ],
                [
                    'key' => 'vehiclesInService',
                    'label' => 'Vehicles in service',
                    'value' => $pos['vehiclesInService'],
                    'format' => 'count',
                    'hint' => "{$pos['fleetOnFile']} on file",
                ],
                [
                    'key' => 'stopsInService',
                    'label' => 'Boarding points',
                    'value' => $pos['stopsInService'],
                    'format' => 'count',
                ],
                [
                    'key' => 'medianVehicleLoad',
                    'label' => 'Median vehicle load',
                    'value' => $pos['medianVehicleLoad'],
                    'format' => 'percent',
                    'hint' => 'Half the fleet runs above this',
                ],
                [
                    'key' => 'materiallyOverVehicles',
                    'label' => 'A fifth over capacity',
                    'value' => $pos['materiallyOverVehicles'],
                    'format' => 'count',
                    'tone' => $pos['materiallyOverVehicles'] > 0 ? 'warning' : 'positive',
                    'hint' => $pos['overcapacityVehicles'] > $pos['materiallyOverVehicles']
                        ? ($pos['overcapacityVehicles'] - $pos['materiallyOverVehicles'])
                            .' more are over by one to twenty per cent'
                        : 'Carrying at least 20% more students than they seat',
                ],
                [
                    'key' => 'underusedVehicles',
                    'label' => 'Under half full',
                    'value' => $pos['underusedVehicles'],
                    'format' => 'count',
                    'hint' => 'Where spare seats already exist',
                ],
                [
                    'key' => 'medianDistanceKm',
                    'label' => 'Median journey',
                    'value' => $pos['medianDistanceKm'],
                    'format' => 'decimal',
                    'hint' => 'Chargeable kilometres',
                ],
                [
                    'key' => 'unbilledArrangements',
                    'label' => 'Unpriced arrangements',
                    'value' => $pos['unbilledArrangements'],
                    'format' => 'count',
                    'tone' => $pos['unbilledArrangements'] > 0 ? 'attention' : 'positive',
                    'hint' => 'No transport amount recorded',
                ],
            ],
        ];
    }

    private function breakdowns(TransportIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byVehicle = $analytics->byVehicle();
        $byStop = $analytics->byStop();
        $byShift = $analytics->byShift();
        $byDistance = $analytics->byDistanceBand();
        $byRoute = $analytics->byRoute();

        $credible = array_values(array_filter($byVehicle, fn ($v) => $v['capacityCredible']));

        return [
            [
                'key' => 'vehicles',
                'label' => 'By vehicle',
                'description' => 'Students carried against the seats each vehicle is registered for, heaviest first. '
                    .'Records whose stated capacity their own load makes impossible are excluded here and reported in '
                    .'the record checks instead.',
                'available' => $credible !== [],
                'reason' => $credible === []
                    ? 'No vehicle this year carries a seating capacity that its own load makes possible.'
                    : null,
                'primaryColumn' => 'riders',
                'columns' => [
                    ['key' => 'riders', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'capacity', 'label' => 'Seats', 'format' => 'count'],
                    ['key' => 'utilization', 'label' => 'Load', 'format' => 'percent'],
                    ['key' => 'avgDistanceKm', 'label' => 'Avg run (km)', 'format' => 'decimal'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['vehicleType'],
                    'tone' => $row['utilization'] !== null
                        && $row['utilization'] > TransportIntelligence::OVERCAPACITY_PERCENT
                            ? 'warning'
                            : null,
                    'values' => [
                        'riders' => $row['riders'],
                        'capacity' => $row['capacity'],
                        'utilization' => $row['utilization'],
                        'avgDistanceKm' => $row['avgDistanceKm'],
                    ],
                ], $credible),
            ],
            [
                'key' => 'stops',
                'label' => 'By boarding point',
                'description' => 'Where students actually board, busiest first. Names are read from the stop master; '
                    .'a stop missing from it is shown by its key rather than given a name.',
                'available' => $byStop !== [],
                'reason' => $byStop === [] ? 'No arrangement this year records a boarding stop.' : null,
                'primaryColumn' => 'riders',
                'columns' => [
                    ['key' => 'riders', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'vehicles', 'label' => 'Vehicles', 'format' => 'count'],
                    ['key' => 'avgDistanceKm', 'label' => 'Avg run (km)', 'format' => 'decimal'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'riders' => $row['riders'],
                        'vehicles' => $row['vehicles'],
                        'avgDistanceKm' => $row['avgDistanceKm'],
                    ],
                ], $byStop),
            ],
            [
                'key' => 'distance',
                'label' => 'By chargeable distance',
                'description' => 'How far students travel, and what each band is actually charged. The transport '
                    .'amount is distance-based, so this is the shape of the transport income as well as of the runs.',
                'available' => $byDistance !== [],
                'reason' => $byDistance === [] ? 'No arrangement this year records a travelled distance.' : null,
                'primaryColumn' => 'arrangements',
                'columns' => [
                    ['key' => 'arrangements', 'label' => 'Arrangements', 'format' => 'count'],
                    ['key' => 'avgAmount', 'label' => 'Avg amount', 'format' => 'currency'],
                    ['key' => 'unbilled', 'label' => 'Unpriced', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'arrangements' => $row['arrangements'],
                        'avgAmount' => $row['avgAmount'],
                        'unbilled' => $row['unbilled'],
                    ],
                ], $byDistance),
            ],
            [
                'key' => 'shifts',
                'label' => 'By travel shift',
                'description' => 'How the load divides across the shifts the fleet is rostered in.',
                'available' => count($byShift) > 1,
                'reason' => count($byShift) <= 1
                    ? 'This institute runs a single travel shift, so there is nothing to compare across.'
                    : null,
                'primaryColumn' => 'riders',
                'columns' => [
                    ['key' => 'riders', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'vehicles', 'label' => 'Vehicles', 'format' => 'count'],
                    ['key' => 'avgDistanceKm', 'label' => 'Avg run (km)', 'format' => 'decimal'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'riders' => $row['riders'],
                        'vehicles' => $row['vehicles'],
                        'avgDistanceKm' => $row['avgDistanceKm'],
                    ],
                ], $byShift),
            ],
            [
                'key' => 'routes',
                'label' => 'By route',
                'description' => 'Students carried per named route, through the vehicles mapped to it this year.',
                'available' => $byRoute !== [],
                // Route-to-vehicle mapping is year-scoped and is often left on
                // the year-0 master, so an empty table here means the mapping
                // was not carried forward, NOT that the school runs no routes.
                'reason' => $byRoute === []
                    ? 'No vehicle is mapped to a route for this academic year, so riders cannot be attributed to one. '
                        .'Route mapping is held per year in transport_route_bus and appears not to have been carried '
                        .'forward into this one.'
                    : null,
                'primaryColumn' => 'riders',
                'columns' => [
                    ['key' => 'riders', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'vehicles', 'label' => 'Vehicles', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['riders' => $row['riders'], 'vehicles' => $row['vehicles']],
                ], $byRoute),
            ],
        ];
    }

    private function priorities(array $findings): array
    {
        $severe = array_values(array_filter(
            $findings,
            fn ($f) => in_array($f['severity'], ['critical', 'high'], true)
        ));

        return array_map(fn ($f) => [
            'id' => $f['id'],
            'severity' => $f['severity'],
            'severityLabel' => $f['severityLabel'],
            'title' => $f['title'],
            'whatHappened' => $f['whatHappened'],
            'whyItMatters' => $f['whyItMatters'],
            'evidence' => $f['evidence'],
            'impact' => $f['impact'],
            'nextStep' => $f['recommendation'],
            'owner' => $f['owner'],
            'confidence' => $f['confidence'],
        ], array_slice($severe, 0, 5));
    }


    /**
     * Write this module's findings to the signal ledger, so they enter the
     * recommendation → decision → execution → outcome → learning loop.
     *
     * IDEMPOTENT. `SignalWriter` dedupes on (tenant, rule, year), so pressing
     * this twice refreshes the same signals with fresher figures rather than
     * duplicating them. It runs the WHOLE pipeline rather than this module
     * alone, because reasoning over a signal that has not been raised produces
     * nothing and the stages are not independent.
     */
    public function run(Request $request): JsonResponse
    {
        $this->scope($request);

        $result = (new IntelligencePipeline($this->tenantId, $this->syear))->run();

        return response()->json([
            'tenantId' => $this->tenantId,
            'syear' => $this->syear,
            'signalsCreated' => $result['rules']['signalsCreated'] ?? 0,
            'signalsRefreshed' => $result['rules']['signalsRefreshed'] ?? 0,
            'recommendations' => $result['reasoning']['recommendations'] ?? 0,
            // Signals whose rule has no approved cause reach evidence and stop
            // there. Reported rather than hidden: it is the honest count of
            // findings the engine will not explain.
            'undetermined' => $result['reasoning']['undetermined'] ?? 0,
            'elapsedMs' => $result['elapsedMs'] ?? null,
        ]);
    }

    private function scope(Request $request): void
    {
        $this->tenantId = (string) $request->attributes->get(
            'tenantId',
            $request->attributes->get('auth.tenantId')
        );

        $this->syear = AcademicYear::resolve($this->tenantId, $request->query('syear'));
        $this->actorId = (string) $request->attributes->get('auth.userId', '');
        $payload = (array) $request->attributes->get('brain.payload', []);
        $this->actorIsStudent = (bool) ($payload['is_student'] ?? false);
    }
}

