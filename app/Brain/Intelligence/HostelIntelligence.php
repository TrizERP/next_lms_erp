<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Hostel & Residential Boarding Intelligence — one institute, one academic year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `hostel_room_allocation` is ONE CHILD PLACED IN ONE ROOM for a year,
 * with the bed, locker, table and bedsheet numbers issued to them. The structure
 * around it is a chain: hostel → building → floor → room.
 *
 * ── WHAT THE PROFILER FOUND, AND WHY THIS MODULE REPORTS SO LITTLE ──────────
 *
 * Across this entire database there are 4 hostels, 3 buildings, 6 floors, 97
 * rooms and 9 allocations — and THE CHAIN DOES NOT JOIN UP AT EITHER END:
 *
 * 1. EVERY ALLOCATION POINTS AT A ROOM THAT IS NOT ON FILE. All six allocations
 *    at the only institute with a live boarding house name a `room_id` with no
 *    row in `hostel_room_master`. The children resolve to the roll and the
 *    hostel resolves; the room does not.
 *
 * 2. EVERY ROOM ON FILE BELONGS TO A FLOOR THAT IS NOT ON FILE. All 96 rooms at
 *    the one institute that has rooms name a `floor_id` with no row in
 *    `hostel_floor_master` — and that institute has no hostel, building or
 *    allocation at all.
 *
 * 3. THERE IS NO CAPACITY COLUMN ANYWHERE. `hostel_room_master` holds an id, a
 *    floor and a room name. Not a bed count, not a room type, not an occupancy
 *    limit. So OCCUPANCY CANNOT BE COMPUTED — not approximately, not with a
 *    caveat. Any percentage this module showed would have been invented, and the
 *    previous version's "average density of 3.0 students per room" was boarders
 *    divided by the number of distinct room ids IN THE ALLOCATIONS, which is to
 *    say divided by rooms that do not exist.
 *
 * So this module is deliberately L0–L2: it reports the structure that is on
 * file, the children who are placed, and the joins that fail. Saying that
 * clearly is the whole of what this data supports, and it is worth saying —
 * a boarding house whose allocations name rooms nobody has registered is a fire
 * roll that cannot be printed.
 *
 * ── WHAT IS DELIBERATELY NOT READ ───────────────────────────────────────────
 *
 * `hostel_master.warden_contact` is a personal telephone number, populated on
 * every hostel in this database. The warden's NAME is shown, because a hostel
 * with no named warden is a finding; the number never is.
 */
final class HostelIntelligence
{
    private const ALLOCATION_TABLE = 'hostel_room_allocation';

    private const ROOM_TABLE = 'hostel_room_master';

    private const FLOOR_TABLE = 'hostel_floor_master';

    private const BUILDING_TABLE = 'hostel_building_master';

    private const HOSTEL_TABLE = 'hostel_master';

    private const TYPE_TABLE = 'hostel_type_master';

    private const VISITOR_TABLE = 'hostel_visitor_master';

    private const STUDENT_TABLE = 'tblstudent';

    /**
     * Below this many allocations, any rate describes the handful of rows that
     * were entered rather than the boarding house.
     *
     * Public so the tests can assert against the threshold itself rather than
     * hard-coding a row count out of this database — a test that pins a business
     * figure fails the day somebody allocates a room, and teaches whoever sees
     * it to edit the number instead of reading the assertion.
     */
    public const MIN_ALLOCATIONS = 5;

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
        $empty = ['sourceTable' => self::ALLOCATION_TABLE, 'sources' => [], 'counts' => [], 'totalRows' => 0, 'usableRows' => 0];

        if ($this->syear === null) {
            return $empty + [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute, and a room is allocated '
                    .'for a year.',
            ];
        }

        if (! SchemaCache::hasTable(self::ALLOCATION_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::ALLOCATION_TABLE."' does not exist in this deployment.",
            ];
        }

        $structure = $this->structure();
        $allocations = (int) $this->scoped()->count();

        // NOTE THE ORDER. An institute with a registered boarding house and no
        // allocations is a different statement from one with neither, and the
        // first is worth making.
        if ($allocations < self::MIN_ALLOCATIONS) {
            return array_merge($empty, [
                'available' => false,
                'counts' => $structure,
                'totalRows' => $allocations,
                'usableRows' => $allocations,
                'reason' => match (true) {
                    // NOTE THE ROOM CLAUSE. One institute holds 96 rooms and no
                    // hostel at all; telling it "nothing here is missing" while
                    // the screen below lists 96 rooms would contradict itself in
                    // consecutive sentences.
                    $allocations === 0 && $structure['hostels'] === 0 && $structure['rooms'] === 0
                        => 'This institute has no boarding house on file and no room allocations. Nothing here is '
                            .'missing — it does not board children.',
                    $allocations === 0 && $structure['hostels'] === 0 => 'This institute has '.$structure['rooms']
                        .' rooms in the room master and no hostel, building or floor above them, and no room '
                        .'allocations. The rooms exist; nothing places them in a building or anybody in them.',
                    $allocations === 0 => 'This institute has '.$structure['hostels'].' hostel'
                        .($structure['hostels'] === 1 ? '' : 's').' registered and no room allocations for '
                        .$this->syear.'. The boarding house exists on file; nobody has been placed in it through '
                        .'the system.',
                    default => 'Only '.$allocations.' room allocation'.($allocations === 1 ? '' : 's').' '
                        .($allocations === 1 ? 'is' : 'are').' recorded for '.$this->syear.'. Any figure drawn from '
                        .'that describes those '.$allocations.' children rather than the boarding house, so the '
                        .'structure below is reported and no rate is.',
                },
            ]);
        }

        return [
            'available' => true,
            'reason' => null,
            'sourceTable' => self::ALLOCATION_TABLE,
            'sources' => [
                'allocations' => true,
                'hostels' => $structure['hostels'] > 0,
                'buildings' => $structure['buildings'] > 0,
                'floors' => $structure['floors'] > 0,
                // Absent at the only institute that allocates rooms. Saying so
                // is why the room breakdown can be honestly omitted.
                'rooms' => $structure['rooms'] > 0,
                // There is no capacity column in this schema at all, so this is
                // false everywhere, by construction rather than by accident.
                'capacity' => false,
                'visitors' => $structure['visitors'] > 0,
            ],
            'counts' => $structure + ['allocations' => $allocations],
            'totalRows' => $allocations,
            'usableRows' => $allocations,
            'period' => "Academic Year {$this->syear}",
        ];
    }

    /**
     * What the institute has on file, at every level of the chain.
     *
     * @return array{hostels:int,buildings:int,floors:int,rooms:int,types:int,visitors:int,wardensNamed:int}
     */
    public function structure(): array
    {
        return $this->memo['structure'] ??= (function (): array {
            $count = function (string $table): int {
                if (! SchemaCache::hasTable($table)) {
                    return 0;
                }

                return (int) DB::table($table)->where('sub_institute_id', $this->tenantId)->count();
            };

            $wardens = 0;
            if (SchemaCache::hasTable(self::HOSTEL_TABLE)) {
                $wardens = (int) DB::table(self::HOSTEL_TABLE)
                    ->where('sub_institute_id', $this->tenantId)
                    ->whereRaw('COALESCE(TRIM(warden), "") <> ""')
                    ->count();
            }

            return [
                'hostels' => $count(self::HOSTEL_TABLE),
                'buildings' => $count(self::BUILDING_TABLE),
                'floors' => $count(self::FLOOR_TABLE),
                'rooms' => $count(self::ROOM_TABLE),
                'types' => $count(self::TYPE_TABLE),
                'visitors' => $count(self::VISITOR_TABLE),
                'wardensNamed' => $wardens,
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
        $structure = $this->structure();
        $integrity = $this->integrity();

        if (! $coverage['available']) {
            return [
                // The STRUCTURE is real whether or not there are enough
                // allocations to analyse, so it is reported either way. The
                // per-child figures are NULL, never zero.
                'metrics' => [
                    'boarders' => null,
                    'hostels' => $structure['hostels'],
                    'buildings' => $structure['buildings'],
                    'floors' => $structure['floors'],
                    'rooms' => $structure['rooms'],
                    'roomsOnFile' => null,
                    'bedNumbersIssued' => null,
                    'lockersIssued' => null,
                    // Never computed anywhere: there is no capacity column in
                    // this schema. See the class note.
                    'occupancy' => null,
                    'wardensNamed' => $structure['wardensNamed'],
                ],
                'summary' => $coverage['reason'] ?? 'No residential boarding data is available for this year.',
            ];
        }

        $shape = $this->scoped()
            ->selectRaw(
                'COUNT(*) as boarders,
                 COUNT(DISTINCT user_id) as children,
                 COUNT(DISTINCT NULLIF(room_id, 0)) as rooms_named,
                 SUM(CASE WHEN COALESCE(TRIM(bed_no), "") <> "" THEN 1 ELSE 0 END) as beds,
                 SUM(CASE WHEN COALESCE(TRIM(locker_no), "") <> "" THEN 1 ELSE 0 END) as lockers'
            )
            ->first();

        $boarders = (int) $shape->boarders;

        return [
            'metrics' => [
                'boarders' => $boarders,
                'hostels' => $structure['hostels'],
                'buildings' => $structure['buildings'],
                'floors' => $structure['floors'],
                'rooms' => $structure['rooms'],
                // How many of the rooms named in allocations actually exist.
                'roomsOnFile' => $integrity['roomsResolved'],
                'bedNumbersIssued' => (int) $shape->beds,
                'lockersIssued' => (int) $shape->lockers,
                'occupancy' => null,
                'wardensNamed' => $structure['wardensNamed'],
            ],
            'summary' => $this->summarySentence($boarders, (int) $shape->rooms_named, $structure, $integrity),
        ];
    }

    /** @param array<string,int> $structure @param array<string,int> $integrity */
    private function summarySentence(int $boarders, int $roomsNamed, array $structure, array $integrity): string
    {
        $parts = [
            "{$boarders} children are placed in boarding for {$this->syear}, across {$roomsNamed} "
                .($roomsNamed === 1 ? 'room' : 'rooms').' and '.$structure['hostels'].' registered '
                .($structure['hostels'] === 1 ? 'hostel' : 'hostels').'.',
        ];

        $parts[] = $integrity['roomsResolved'] === $roomsNamed
            ? 'Every room named in an allocation is registered in the room master.'
            : ($roomsNamed - $integrity['roomsResolved']).' of those '.$roomsNamed.' '
                .($roomsNamed - $integrity['roomsResolved'] === 1 ? 'rooms is' : 'rooms are')
                .' not registered in the room master at all, so the placements name a room nobody has recorded.';

        // The absence of a capacity column is stated once, plainly, rather than
        // producing a percentage nobody can defend.
        $parts[] = 'This schema records no bed count, room type or occupancy limit against a room, so how full the '
            .'boarding house is cannot be computed from it — and is therefore not shown rather than estimated.';

        return implode(' ', $parts);
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Where the chain breaks, counted at each link.
     *
     * @return array{roomsNamed:int,roomsResolved:int,hostelsResolved:int,childrenOnRoll:int,children:int,orphanRooms:int}
     */
    public function integrity(): array
    {
        return $this->memo['integrity'] ??= (function (): array {
            $none = [
                'roomsNamed' => 0, 'roomsResolved' => 0, 'hostelsResolved' => 0,
                'childrenOnRoll' => 0, 'children' => 0, 'orphanRooms' => 0,
            ];

            if ($this->syear === null || ! SchemaCache::hasTable(self::ALLOCATION_TABLE)) {
                return $none;
            }

            $row = $this->scoped('a')
                ->leftJoin(self::ROOM_TABLE.' as r', function ($join) {
                    $join->on('r.id', '=', 'a.room_id')->on('r.sub_institute_id', '=', 'a.sub_institute_id');
                })
                ->leftJoin(self::HOSTEL_TABLE.' as h', function ($join) {
                    $join->on('h.id', '=', 'a.hostel_id')->on('h.sub_institute_id', '=', 'a.sub_institute_id');
                })
                ->leftJoin(self::STUDENT_TABLE.' as s', function ($join) {
                    $join->on('s.id', '=', 'a.user_id')->on('s.sub_institute_id', '=', 'a.sub_institute_id');
                })
                ->selectRaw(
                    'COUNT(DISTINCT NULLIF(a.room_id, 0)) as rooms_named,
                     COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN a.room_id END) as rooms_resolved,
                     COUNT(DISTINCT CASE WHEN h.id IS NOT NULL THEN a.hostel_id END) as hostels_resolved,
                     COUNT(DISTINCT a.user_id) as children,
                     COUNT(DISTINCT CASE WHEN s.id IS NOT NULL THEN a.user_id END) as children_on_roll'
                )
                ->first();

            // Rooms on file whose floor does not exist — the other end of the
            // same broken chain, and it is a different institute's problem from
            // the one above.
            $orphanRooms = 0;
            if (SchemaCache::hasTable(self::ROOM_TABLE) && SchemaCache::hasTable(self::FLOOR_TABLE)) {
                $orphanRooms = (int) DB::table(self::ROOM_TABLE.' as r')
                    ->leftJoin(self::FLOOR_TABLE.' as f', function ($join) {
                        $join->on('f.id', '=', 'r.floor_id')->on('f.sub_institute_id', '=', 'r.sub_institute_id');
                    })
                    ->where('r.sub_institute_id', $this->tenantId)
                    ->whereNull('f.id')
                    ->count();
            }

            return [
                'roomsNamed' => (int) ($row->rooms_named ?? 0),
                'roomsResolved' => (int) ($row->rooms_resolved ?? 0),
                'hostelsResolved' => (int) ($row->hostels_resolved ?? 0),
                'children' => (int) ($row->children ?? 0),
                'childrenOnRoll' => (int) ($row->children_on_roll ?? 0),
                'orphanRooms' => $orphanRooms,
            ];
        })();
    }

    /**
     * The boarding house as it is recorded, hostel by hostel.
     *
     * NO OCCUPANCY COLUMN: see the class note. This says how many children are
     * placed in each hostel and what structure sits under it, and stops there.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byHostel(): array
    {
        return $this->memo['byHostel'] ??= (function (): array {
            if (! SchemaCache::hasTable(self::HOSTEL_TABLE) || $this->syear === null) {
                return [];
            }

            $rows = DB::table(self::HOSTEL_TABLE.' as h')
                ->leftJoin(self::BUILDING_TABLE.' as b', function ($join) {
                    $join->on('b.hostel_id', '=', 'h.id')->on('b.sub_institute_id', '=', 'h.sub_institute_id');
                })
                ->leftJoin(self::FLOOR_TABLE.' as f', function ($join) {
                    $join->on('f.building_id', '=', 'b.id')->on('f.sub_institute_id', '=', 'h.sub_institute_id');
                })
                ->leftJoin(self::ALLOCATION_TABLE.' as a', function ($join) {
                    $join->on('a.hostel_id', '=', 'h.id')
                        ->on('a.sub_institute_id', '=', 'h.sub_institute_id')
                        ->where('a.syear', '=', $this->syear);
                })
                ->where('h.sub_institute_id', $this->tenantId)
                ->groupBy('h.id', 'h.name', 'h.code', 'h.warden')
                ->get([
                    'h.id',
                    DB::raw('NULLIF(TRIM(h.name), "") as hostel_name'),
                    DB::raw('NULLIF(TRIM(h.code), "") as hostel_code'),
                    // The warden's NAME only. The contact number is never read.
                    DB::raw('CASE WHEN COALESCE(TRIM(h.warden), "") = "" THEN NULL ELSE 1 END as has_warden'),
                    DB::raw('COUNT(DISTINCT b.id) as buildings'),
                    DB::raw('COUNT(DISTINCT f.id) as floors'),
                    DB::raw('COUNT(DISTINCT a.user_id) as children'),
                ]);

            return array_map(static fn ($row) => [
                'key' => (string) $row->id,
                'label' => $row->hostel_name ?? ($row->hostel_code ?? "Hostel #{$row->id}"),
                'buildings' => (int) $row->buildings,
                'floors' => (int) $row->floors,
                'children' => (int) $row->children,
                'wardenNamed' => $row->has_warden !== null,
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
        $structure = $this->structure();
        $integrity = $this->integrity();

        // Reported whenever the institute has ANY hostel record at all, even
        // below the allocation floor — a broken chain is a fact about the
        // records and does not need a cohort to be true.
        if ($structure['hostels'] === 0 && $structure['rooms'] === 0 && $integrity['children'] === 0) {
            return [
                'available' => false,
                'reason' => 'This institute has no residential boarding records of any kind, so there is nothing to '
                    .'check.',
                'checks' => [],
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'allocations_naming_unknown_rooms',
                    'label' => 'Placements naming a room that is not registered',
                    'value' => $integrity['roomsNamed'] - $integrity['roomsResolved'],
                    'format' => 'count',
                    'sharePercent' => $integrity['roomsNamed'] > 0
                        ? round(($integrity['roomsNamed'] - $integrity['roomsResolved']) / $integrity['roomsNamed'] * 100, 2)
                        : null,
                    'shareLabel' => 'of rooms named in placements',
                    'state' => $integrity['roomsResolved'] < $integrity['roomsNamed'] ? 'attention' : 'ok',
                    'note' => $integrity['roomsResolved'] < $integrity['roomsNamed']
                        ? 'The children are placed in rooms that have no row in the room master. Nothing can be said '
                            .'about which building or floor they are on, and a fire roll printed by room would be '
                            .'blank for them.'
                        : 'Every room named in a placement is registered in the room master.',
                ],
                [
                    'key' => 'rooms_on_an_unknown_floor',
                    'label' => 'Registered rooms whose floor is not on file',
                    'value' => $integrity['orphanRooms'],
                    'format' => 'count',
                    'sharePercent' => $structure['rooms'] > 0
                        ? round($integrity['orphanRooms'] / $structure['rooms'] * 100, 2)
                        : null,
                    'shareLabel' => 'of registered rooms',
                    'state' => $integrity['orphanRooms'] > 0 ? 'attention' : 'ok',
                    'note' => $integrity['orphanRooms'] > 0
                        ? 'These rooms exist in the room master and hang off nothing. The hostel → building → floor → '
                            .'room chain is broken above them, so they cannot be located in any building.'
                        : 'Every registered room sits on a floor that is on file.',
                ],
                [
                    'key' => 'no_room_capacity',
                    'label' => 'Rooms with a recorded capacity',
                    // NOT a count of zero rows: the COLUMN does not exist. The
                    // value is null and the note says why, so nobody reads this
                    // as "capacity is recorded as 0 everywhere".
                    'value' => null,
                    'format' => 'count',
                    'sharePercent' => null,
                    'state' => 'attention',
                    'note' => 'This schema has no capacity, bed-count or room-type column on a room. How full the '
                        .'boarding house is cannot be computed from these tables at all, so no occupancy figure '
                        .'appears anywhere on this screen. It would have to be invented.',
                ],
                [
                    'key' => 'boarders_not_on_roll',
                    'label' => 'Children in boarding who are not on the roll',
                    'value' => $integrity['children'] - $integrity['childrenOnRoll'],
                    'format' => 'count',
                    'sharePercent' => $integrity['children'] > 0
                        ? round(($integrity['children'] - $integrity['childrenOnRoll']) / $integrity['children'] * 100, 2)
                        : null,
                    'shareLabel' => 'of children in boarding',
                    'state' => $integrity['childrenOnRoll'] < $integrity['children'] ? 'attention' : 'ok',
                    'note' => $integrity['childrenOnRoll'] < $integrity['children']
                        ? 'These placements name a child with no record in the student master, so they cannot be '
                            .'reached through a parent contact or a class teacher.'
                        : 'Every child in boarding is on the student roll.',
                ],
                [
                    'key' => 'hostels_without_a_named_warden',
                    'label' => 'Hostels with no warden named',
                    'value' => $structure['hostels'] - $structure['wardensNamed'],
                    'format' => 'count',
                    'sharePercent' => $structure['hostels'] > 0
                        ? round(($structure['hostels'] - $structure['wardensNamed']) / $structure['hostels'] * 100, 2)
                        : null,
                    'shareLabel' => 'of hostels',
                    'state' => $structure['wardensNamed'] < $structure['hostels'] ? 'attention' : 'ok',
                    'note' => $structure['wardensNamed'] < $structure['hostels']
                        ? 'A boarding house with nobody named against it has no recorded person responsible for the '
                            .'children in it overnight.'
                        : 'Every hostel has a warden named against it.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /** Allocations for this institute and this year. */
    private function scoped(?string $alias = null): \Illuminate\Database\Query\Builder
    {
        $table = $alias === null ? self::ALLOCATION_TABLE : self::ALLOCATION_TABLE.' as '.$alias;
        $prefix = $alias === null ? '' : $alias.'.';

        return DB::table($table)
            ->where($prefix.'sub_institute_id', $this->tenantId)
            ->where($prefix.'syear', $this->syear);
    }
}
