<?php

namespace App\Brain\Intelligence;

/**
 * What the boarding records mean, and what is worth someone's morning.
 *
 * ── WHAT A RULE HERE MAY NOT DO ─────────────────────────────────────────────
 *
 * IT MAY NOT INVENT A CAPACITY. The previous version raised "High dormitory room
 * occupancy (Room #12, 5 boarders) — exceeding standard dorm density
 * recommendations". There is no capacity, bed-count or room-type column on a
 * room anywhere in this schema, and no such recommendation anywhere in this
 * system: five children in a ten-bed dormitory is not a finding, and the records
 * cannot tell a ten-bed dormitory from a double. That rule is gone.
 *
 * IT MAY NOT REQUIRE A COHORT TO REPORT A BROKEN JOIN. A placement naming a room
 * nobody registered is true of the records whether there are six placements or
 * six hundred, so the structural rules below run on the structure and not on the
 * allocation floor — which matters, because the one institute with 96 registered
 * rooms has no allocations at all and would otherwise be silent.
 */
final class HostelSignalRules
{
    public function __construct(
        private readonly HostelIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        $structure = $this->analytics->structure();
        $integrity = $this->analytics->integrity();

        // Nothing at all on file: not even a broken chain to report.
        if ($structure['hostels'] === 0 && $structure['rooms'] === 0 && $integrity['children'] === 0) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'placements_name_unknown_rooms' => [
                'Placements naming a room that is not registered',
                fn () => $this->placementsNameUnknownRooms(),
            ],
            'rooms_hanging_off_nothing' => [
                'Registered rooms that belong to no floor on file',
                fn () => $this->roomsHangingOffNothing(),
            ],
            'hostel_without_warden' => [
                'A boarding house with nobody named against it',
                fn () => $this->hostelWithoutWarden(),
            ],
            'unassigned_bed_numbers' => [
                'Children placed without a bed number',
                fn () => $this->unassignedBedNumbers(),
            ],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => true, 'raised' => $raised !== []];

            // Tagged HERE rather than in each rule, so a rule added later cannot
            // forget to. ModuleSignalBridge dedupes the ledger on (tenant, rule,
            // year); a finding with no rule key cannot be deduped and is skipped.
            foreach ($raised as $finding) {
                $findings[] = $finding + ['rule' => $key];
            }
        }

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, function ($a, $b) use ($rank) {
            $bySeverity = ($rank[$a['severity']] ?? 5) <=> ($rank[$b['severity']] ?? 5);

            return $bySeverity !== 0
                ? $bySeverity
                : ($b['affected']['count'] ?? 0) <=> ($a['affected']['count'] ?? 0);
        });

        return ['findings' => $findings, 'ruleStatus' => $ruleStatus];
    }

    /* ------------------------------------------------------- the structure */

    /**
     * Children placed in rooms nobody has registered.
     *
     * This is the most consequential thing this module can say. A placement that
     * names a room with no row in the room master cannot be resolved to a floor
     * or a building, which means the one document a boarding house must be able
     * to produce at three in the morning — who is where — cannot be produced.
     *
     * @return array<int,array<string,mixed>>
     */
    private function placementsNameUnknownRooms(): array
    {
        $integrity = $this->analytics->integrity();
        $named = $integrity['roomsNamed'];
        $missing = $named - $integrity['roomsResolved'];

        if ($named === 0 || $missing === 0) {
            return [];
        }

        $children = $integrity['children'];
        $share = round($missing / $named * 100, 1);
        $all = $missing === $named;

        return [[
            'id' => "hostel-unknown-rooms-{$this->syear}",
            'severity' => $all ? 'high' : 'medium',
            'severityLabel' => $all ? 'High' : 'Medium',
            'title' => $all
                ? "All {$children} children in boarding are placed in rooms that are not registered"
                : "{$missing} of {$named} rooms used for boarding are not registered",
            'whatHappened' => $this->sentence([
                "{$children} ".($children === 1 ? 'child is' : 'children are')." placed in boarding this year, "
                    ."across {$named} distinct ".($named === 1 ? 'room' : 'rooms').'.',
                "{$missing} of those ".($missing === 1 ? 'rooms has' : 'rooms have')
                    ." no row in the room master ({$share}%), so the placement names a room the system does not "
                    .'hold.',
                $integrity['hostelsResolved'] > 0
                    ? 'The hostel itself resolves — it is the room below it that does not.'
                    : 'Neither the hostel nor the room resolves.',
            ]),
            'whyItMatters' => 'A room that is not registered cannot be resolved to a floor or a building. That means '
                .'no roll can be printed by room, no warden can be shown who is on their floor, and in a fire the '
                .'list of who should be where does not exist. It also means every structural figure on this screen '
                .'is describing rooms that are not the ones these children sleep in.',
            'evidence' => [
                ['label' => 'Rooms named in placements', 'value' => (string) $named],
                ['label' => 'Of those, registered', 'value' => (string) $integrity['roomsResolved']],
                ['label' => 'Not registered', 'value' => (string) $missing],
                ['label' => 'Children affected', 'value' => (string) $children],
                ['label' => 'Registered rooms at this institute', 'value' => (string) $this->analytics->structure()['rooms']],
            ],
            'likelyCause' => 'Rooms allocated from a list held outside the system, or a room master that was emptied '
                .'or never filled after the placements were made. The allocation holds an id and the master holds '
                .'nothing at it; which came first is not recorded.',
            'causeConfirmed' => false,
            'recommendation' => 'Register the rooms these children are actually in before anything else in this '
                .'module is used. Until the room master holds them, a boarding roll cannot be produced from this '
                .'system at all.',
            'owner' => 'Hostel warden',
            'priority' => $all ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $children, 'total' => $children, 'unit' => 'children'],
            'impact' => ['value' => $children, 'display' => (string) $children, 'label' => 'children in unregistered rooms'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * Registered rooms that belong to no floor on file.
     *
     * The other end of the same broken chain, and at a different institute.
     *
     * @return array<int,array<string,mixed>>
     */
    private function roomsHangingOffNothing(): array
    {
        $structure = $this->analytics->structure();
        $orphans = $this->analytics->integrity()['orphanRooms'];

        if ($orphans === 0 || $structure['rooms'] === 0) {
            return [];
        }

        $share = round($orphans / $structure['rooms'] * 100, 1);
        $all = $orphans === $structure['rooms'];

        return [[
            'id' => "hostel-orphan-rooms-{$this->syear}",
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => $all
                ? "All {$structure['rooms']} registered rooms belong to a floor that is not on file"
                : "{$orphans} of {$structure['rooms']} registered rooms belong to a floor that is not on file",
            'whatHappened' => $this->sentence([
                "This institute has {$structure['rooms']} rooms in the room master. {$orphans} of them ({$share}%) "
                    .'name a floor with no row in the floor master.',
                "It has {$structure['hostels']} ".($structure['hostels'] === 1 ? 'hostel' : 'hostels')
                    .", {$structure['buildings']} ".($structure['buildings'] === 1 ? 'building' : 'buildings')
                    ." and {$structure['floors']} ".($structure['floors'] === 1 ? 'floor' : 'floors').' on file.',
            ]),
            'whyItMatters' => 'The boarding records are a chain — hostel, building, floor, room — and a room whose '
                .'floor is missing hangs off nothing. It cannot be located in a building, shown on a warden’s round, '
                .'or reported against a hostel, however completely the room itself is described.',
            'evidence' => [
                ['label' => 'Rooms with no floor on file', 'value' => (string) $orphans],
                ['label' => 'Registered rooms', 'value' => (string) $structure['rooms']],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Floors on file', 'value' => (string) $structure['floors']],
                ['label' => 'Buildings on file', 'value' => (string) $structure['buildings']],
                ['label' => 'Hostels on file', 'value' => (string) $structure['hostels']],
            ],
            'likelyCause' => 'Rooms imported or entered before the floors above them, which is the usual order when '
                .'a room list already exists on paper. The room master records a floor id and nothing guarantees '
                .'that floor was ever created.',
            'causeConfirmed' => false,
            'recommendation' => 'Create the floors these rooms name, or repoint the rooms at floors that exist. '
                .'Either fixes it; leaving the rooms in place means the boarding structure cannot be walked from '
                .'the top at all.',
            'owner' => 'Hostel warden',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $orphans, 'total' => $structure['rooms'], 'unit' => 'rooms'],
            'impact' => ['value' => $orphans, 'display' => (string) $orphans, 'label' => 'rooms that cannot be located'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function hostelWithoutWarden(): array
    {
        $structure = $this->analytics->structure();
        $unnamed = $structure['hostels'] - $structure['wardensNamed'];

        if ($structure['hostels'] === 0 || $unnamed === 0) {
            return [];
        }

        $unnamedHostels = array_values(array_filter(
            $this->analytics->byHostel(),
            static fn ($h) => ! $h['wardenNamed'],
        ));

        return [[
            'id' => "hostel-no-warden-{$this->syear}",
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => $unnamed === 1
                ? 'One boarding house has no warden named against it'
                : "{$unnamed} boarding houses have no warden named against them",
            'whatHappened' => "{$unnamed} of the {$structure['hostels']} "
                .($structure['hostels'] === 1 ? 'hostel' : 'hostels').' registered at this institute '
                .($unnamed === 1 ? 'has' : 'have').' no warden recorded.',
            'whyItMatters' => 'A boarding house with nobody named against it has no recorded person responsible for '
                .'the children in it overnight. That is a question an inspection asks directly, and the answer is '
                .'currently not in the system.',
            'evidence' => array_merge(
                [
                    ['label' => 'Hostels with no warden', 'value' => (string) $unnamed],
                    ['label' => 'Hostels registered', 'value' => (string) $structure['hostels']],
                ],
                array_map(static fn ($h) => [
                    'label' => $h['label'],
                    'value' => 'no warden named',
                    'note' => "{$h['children']} children placed",
                ], array_slice($unnamedHostels, 0, 4)),
            ),
            'likelyCause' => 'A hostel record created for the structure before anybody was assigned to it. The field '
                .'exists and was left empty.',
            'causeConfirmed' => false,
            'recommendation' => 'Name the warden against each hostel. It is one field and it is the first thing '
                .'anybody asks for.',
            'owner' => 'Hostel warden',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $unnamed, 'total' => $structure['hostels'], 'unit' => 'hostels'],
            'impact' => ['value' => $unnamed, 'display' => (string) $unnamed, 'label' => 'hostels with nobody named'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ the beds */

    /** @return array<int,array<string,mixed>> */
    private function unassignedBedNumbers(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return [];
        }

        $metrics = $this->analytics->position()['metrics'];
        $boarders = (int) $metrics['boarders'];
        $withBed = (int) $metrics['bedNumbersIssued'];
        $without = $boarders - $withBed;

        if ($without <= 0) {
            return [];
        }

        $share = round($without / $boarders * 100, 1);

        return [[
            'id' => "hostel-unassigned-beds-{$this->syear}",
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => "{$without} of {$boarders} children in boarding have no bed number recorded",
            'whatHappened' => $this->sentence([
                "{$without} of the {$boarders} placements this year ({$share}%) carry a room but no bed number.",
                (int) $metrics['lockersIssued'] < $boarders
                    ? ($boarders - (int) $metrics['lockersIssued']).' also have no locker number.'
                    : null,
            ]),
            'whyItMatters' => 'The room narrows it to a group; the bed number is what identifies whose belongings '
                .'are whose and who was where. Without it a warden settling a dispute or checking a room at night '
                .'has the room and nothing finer.',
            'evidence' => array_values(array_filter([
                ['label' => 'Placements with no bed number', 'value' => (string) $without],
                ['label' => 'Children placed', 'value' => (string) $boarders],
                ['label' => 'Share', 'value' => "{$share}%"],
                (int) $metrics['lockersIssued'] < $boarders
                    ? ['label' => 'No locker number', 'value' => (string) ($boarders - (int) $metrics['lockersIssued'])]
                    : null,
            ])),
            'likelyCause' => 'Beds allocated informally and never written back, which is the usual pattern where the '
                .'boarding house is small enough to be managed on paper.',
            'causeConfirmed' => false,
            'recommendation' => 'Record the bed numbers once, at the start of term. It is the only field that makes '
                .'the placement specific to a child rather than to a room.',
            'owner' => 'Hostel warden',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $without, 'total' => $boarders, 'unit' => 'children'],
            'impact' => ['value' => $without, 'display' => (string) $without, 'label' => 'placements without a bed'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ helpers */

    /** @param array<int,?string> $parts */
    private function sentence(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }
}
