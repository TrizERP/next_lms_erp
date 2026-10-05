<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\HostelIntelligence;
use App\Brain\Intelligence\HostelSignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainHostelIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'hostel', 'allocations');

        $analytics = new HostelIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        // NOT gated on coverage. A placement naming a room nobody registered, or
        // a room hanging off a floor that does not exist, is true of the records
        // whether or not there are enough allocations to analyse — and the one
        // institute holding 96 orphaned rooms has no allocations at all.
        $rules = new HostelSignalRules($analytics, $this->syear);
        $raised = $rules->run();

        // Always present: the structural half is real even where the per-child
        // half is not, and every per-child figure inside it is null rather than
        // zero when there is nothing to count.
        $pos = $analytics->position()['metrics'];

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · hostel_room_allocation, hostel_master, hostel_building_master, '
                .'hostel_floor_master, hostel_room_master',
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
                'note' => 'Hostel findings are evaluated per request from live residential room allocations.',
            ],
            'summary' => $this->summary($analytics, $coverage, $raised['findings']),
            'position' => $this->position($analytics, $pos, $coverage),
            'breakdowns' => $this->breakdowns($analytics),
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
     * DETERMINISTIC, NEVER MODEL OUTPUT. It never reports an occupancy or a
     * density: this schema holds no capacity against a room, so both would have
     * to be invented. See HostelIntelligence's class note.
     */
    private function summary(HostelIntelligence $analytics, array $coverage, array $findings): array
    {
        $structure = $analytics->structure();
        $count = count($findings);

        if (! $coverage['available']) {
            // The STRUCTURE is still worth stating where it exists. An institute
            // with two registered hostels and nobody placed in them is a
            // different sentence from one that does not board children at all.
            $sentences = [$coverage['reason'] ?? 'No residential boarding records for this academic year.'];

            if ($structure['rooms'] > 0 || $structure['hostels'] > 0) {
                $sentences[] = "On file: {$structure['hostels']} "
                    .($structure['hostels'] === 1 ? 'hostel' : 'hostels').", {$structure['buildings']} "
                    .($structure['buildings'] === 1 ? 'building' : 'buildings').", {$structure['floors']} "
                    .($structure['floors'] === 1 ? 'floor' : 'floors')." and {$structure['rooms']} "
                    .($structure['rooms'] === 1 ? 'room' : 'rooms').'.';
            }

            if ($count > 0) {
                $sentences[] = $count === 1
                    ? 'One finding below about the records themselves, with the figures it rests on.'
                    : "{$count} findings below about the records themselves, each with the figures it rests on.";
            }

            return [
                'available' => $structure['hostels'] > 0 || $structure['rooms'] > 0,
                'reason' => $coverage['reason'] ?? null,
                'headline' => $structure['hostels'] > 0
                    ? "{$structure['hostels']} registered · nobody placed"
                    : null,
                'sentences' => $sentences,
            ];
        }

        $metrics = $analytics->position()['metrics'];
        $sentences = [$analytics->position()['summary']];

        $sentences[] = match ($count) {
            0 => 'No check found enough evidence to raise a finding for this year.',
            1 => 'One finding below, with the figures it rests on.',
            default => "{$count} findings below, each with the figures it rests on.",
        };

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$metrics['boarders']} children placed · {$structure['hostels']} registered "
                .($structure['hostels'] === 1 ? 'hostel' : 'hostels'),
            'sentences' => $sentences,
        ];
    }

    /** @param array<string,mixed> $pos */
    private function position(HostelIntelligence $analytics, array $pos, array $coverage): array
    {
        $integrity = $analytics->integrity();

        if ($pos['hostels'] === 0 && $pos['rooms'] === 0 && $pos['boarders'] === null) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'boarders',
                    'label' => 'Children placed',
                    // NULL where nobody is placed this year — never 0, which
                    // reads the same as a boarding house standing empty when the
                    // truth may be that nobody used the module.
                    'value' => $pos['boarders'],
                    'format' => 'count',
                    'hint' => $pos['boarders'] === null
                        ? ($coverage['reason'] ?? 'No placements recorded for this year')
                        : 'Placements recorded for this academic year',
                ],
                [
                    'key' => 'hostels',
                    'label' => 'Hostels registered',
                    'value' => $pos['hostels'],
                    'format' => 'count',
                ],
                [
                    'key' => 'rooms',
                    'label' => 'Rooms registered',
                    'value' => $pos['rooms'],
                    'format' => 'count',
                    'tone' => $pos['rooms'] === 0 && ($pos['boarders'] ?? 0) > 0 ? 'warning' : null,
                    'hint' => $pos['rooms'] === 0 && ($pos['boarders'] ?? 0) > 0
                        ? 'Children are placed and no room is registered at all'
                        : null,
                ],
                [
                    'key' => 'roomsOnFile',
                    'label' => 'Placement rooms that exist',
                    'value' => $pos['roomsOnFile'],
                    'format' => 'count',
                    'tone' => $pos['roomsOnFile'] !== null && $pos['roomsOnFile'] < $integrity['roomsNamed']
                        ? 'warning'
                        : null,
                    'hint' => $integrity['roomsNamed'] > 0
                        ? "Of {$integrity['roomsNamed']} rooms named in placements"
                        : 'No placement names a room',
                ],
                [
                    'key' => 'occupancy',
                    'label' => 'Occupancy',
                    // ALWAYS NULL, AT EVERY INSTITUTE. There is no capacity,
                    // bed-count or room-type column on a room in this schema, so
                    // this cannot be computed and is not estimated.
                    'value' => null,
                    'format' => 'percent',
                    'hint' => 'Not computable: this system records no bed count or capacity against a room',
                ],
                [
                    'key' => 'bedNumbersIssued',
                    'label' => 'Bed numbers issued',
                    'value' => $pos['bedNumbersIssued'],
                    'format' => 'count',
                ],
                [
                    'key' => 'wardensNamed',
                    'label' => 'Hostels with a warden named',
                    'value' => $pos['wardensNamed'],
                    'format' => 'count',
                    'tone' => $pos['wardensNamed'] < $pos['hostels'] ? 'warning' : null,
                    'hint' => "Of {$pos['hostels']} registered",
                ],
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function breakdowns(HostelIntelligence $analytics): array
    {
        $byHostel = $analytics->byHostel();

        return [
            [
                'key' => 'by_hostel',
                'label' => 'By hostel',
                'description' => 'What is on file under each registered boarding house, and how many children are '
                    .'placed in it. There is deliberately no occupancy column: this system records no capacity '
                    .'against a room, so a figure for how full a hostel is would have to be invented.',
                'available' => $byHostel !== [],
                'reason' => $byHostel === []
                    ? 'This institute has no hostel registered, so there is nothing to divide.'
                    : null,
                'primaryColumn' => 'children',
                'columns' => [
                    ['key' => 'children', 'label' => 'Children placed', 'format' => 'count'],
                    ['key' => 'buildings', 'label' => 'Buildings', 'format' => 'count'],
                    ['key' => 'floors', 'label' => 'Floors', 'format' => 'count'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'tone' => $row['wardenNamed'] ? null : 'attention',
                    'note' => $row['wardenNamed'] ? null : 'No warden named against this hostel',
                    'values' => [
                        'children' => $row['children'],
                        'buildings' => $row['buildings'],
                        'floors' => $row['floors'],
                    ],
                ], $byHostel),
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function priorities(array $findings): array
    {
        $severe = array_values(array_filter(
            $findings,
            static fn ($f) => in_array($f['severity'], ['critical', 'high', 'medium'], true),
        ));

        return array_map(static fn ($f) => [
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

