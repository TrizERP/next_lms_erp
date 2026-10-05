<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Inward & Outward Correspondence Intelligence — one institute, one academic
 * year.
 *
 * ── WHAT ONE ROW MEANS ──────────────────────────────────────────────────────
 *
 * One row of `inward` is ONE PIECE OF POST OR ONE DOCUMENT ARRIVING at the
 * institute: the day it came, the number the office gave it, who it came from
 * (`place_id`, resolved against `place_master`), where the physical paper was
 * filed (`file_location_id`, resolved against `physical_file_location`), and a
 * scan attached to it. `outward` is the same record for something sent OUT.
 *
 * Both tables carry a real `syear`, so this module is year-scoped natively and
 * needs no term-date window.
 *
 * ── THE ASYMMETRY IS THE POINT ──────────────────────────────────────────────
 *
 * Measured across this database, the two registers are not used the same way at
 * all: one institute holds 4,741 inward entries and FOUR outward ones, and the
 * other holds 989 inward against 32 outward. A correspondence register that
 * records everything arriving and almost nothing leaving cannot answer the
 * question it exists for — whether a letter was ever replied to — and that is
 * the first thing this module says.
 *
 * ── WHAT THE PROFILER CORRECTED ─────────────────────────────────────────────
 *
 * An earlier reading of this data counted 4,332 distinct `inward_number` values
 * across 4,741 rows and called the difference 409 duplicate numbers. IT WAS
 * WRONG: the numbers restart each academic year, which is how a correspondence
 * register is supposed to work, and counted WITHIN a year the duplicates are 5
 * at that institute's busiest year rather than 409. Duplicates are therefore
 * counted per (syear, number), which is the grain the office actually issues
 * them at.
 *
 * ── WHAT IS DELIBERATELY NOT READ ───────────────────────────────────────────
 *
 * `title` and `description` — free text describing what a letter was about,
 * 4,476 distinct values across one institute's rows. A correspondence register
 * holds legal notices, staff disciplinary matters and letters about individual
 * children. Nothing in this module reads, groups by, or quotes either column;
 * every figure is a count, and the evidence cites numbers and dates only.
 *
 * `attachment` is read ONLY as present-or-absent — never the filename, which is
 * frequently the subject of the letter written out.
 */
final class CorrespondenceIntelligence
{
    private const INWARD_TABLE = 'inward';

    private const OUTWARD_TABLE = 'outward';

    private const PLACE_TABLE = 'place_master';

    private const LOCATION_TABLE = 'physical_file_location';

    /**
     * Below this many entries in the year, a share describes the handful of
     * letters somebody happened to log rather than the correspondence office.
     *
     * Public so tests assert against the threshold rather than hard-coding a row
     * count out of this database.
     */
    public const MIN_ENTRIES = 20;

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
        $empty = [
            'sourceTable' => self::INWARD_TABLE,
            'sources' => [],
            'counts' => [],
            'totalRows' => 0,
            'usableRows' => 0,
        ];

        if (! SchemaCache::hasTable(self::INWARD_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Table '".self::INWARD_TABLE."' does not exist in this deployment.",
            ];
        }

        if ($this->syear === null) {
            return $empty + [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute, and correspondence is '
                    .'numbered a year at a time.',
            ];
        }

        $shape = $this->shape();
        $entries = $shape['inward'] + $shape['outward'];

        if ($entries < self::MIN_ENTRIES) {
            $everRecorded = (int) DB::table(self::INWARD_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->count();

            return array_merge($empty, [
                'totalRows' => $entries,
                'usableRows' => $entries,
                'counts' => [
                    'inward' => $shape['inward'],
                    'outward' => $shape['outward'],
                    'inwardAllYears' => $everRecorded,
                ],
                'available' => false,
                'reason' => match (true) {
                    $entries === 0 && $everRecorded === 0
                        => 'This institute has never logged a piece of correspondence. Nothing here is missing — it '
                            .'does not use the inward/outward register.',
                    $entries === 0
                        => 'No correspondence was logged for '.$this->syear.', though this institute has '
                            .$everRecorded.' inward entries on file in other years. The register exists; it was not '
                            .'used this year.',
                    default => 'Only '.$entries.' correspondence '.($entries === 1 ? 'entry was' : 'entries were')
                        .' logged for '.$this->syear.'. Any share drawn from that describes '
                        .($entries === 1 ? 'that single letter' : 'those '.$entries.' letters')
                        .' rather than the office, so the counts below are reported and no rate is.',
                },
            ]);
        }

        return [
            'available' => true,
            'reason' => null,
            'sourceTable' => self::INWARD_TABLE,
            'sources' => [
                'inward' => $shape['inward'] > 0,
                // False at the institutes that log nothing going out — which is
                // most of them, and is the module's first finding rather than a
                // gap in it.
                'outward' => $shape['outward'] > 0,
                'places' => $shape['placesUsed'] > 0,
                'fileLocations' => $shape['locationsUsed'] > 0,
                'attachments' => $shape['withAttachment'] > 0,
            ],
            'counts' => [
                'inward' => $shape['inward'],
                'outward' => $shape['outward'],
                'placesUsed' => $shape['placesUsed'],
                'locationsUsed' => $shape['locationsUsed'],
                'daysWithEntries' => $shape['days'],
            ],
            'totalRows' => $entries,
            'usableRows' => $entries,
            'period' => "Academic Year {$this->syear}",
        ];
    }

    /* -------------------------------------------------------- the raw shape */

    /**
     * Every figure this module counts, over the year's rows.
     *
     * @return array{inward:int,outward:int,withAttachment:int,withoutAttachment:int,
     *               placesUsed:int,placesUnresolved:int,locationsUsed:int,locationsUnresolved:int,
     *               locationsMissing:int,duplicateNumbers:int,duplicatedRows:int,missingNumbers:int,days:int}
     */
    public function shape(): array
    {
        return $this->memo['shape'] ??= (function (): array {
            $none = [
                'inward' => 0, 'outward' => 0, 'withAttachment' => 0, 'withoutAttachment' => 0,
                'placesUsed' => 0, 'placesUnresolved' => 0, 'locationsUsed' => 0,
                'locationsUnresolved' => 0, 'locationsMissing' => 0, 'duplicateNumbers' => 0,
                'duplicatedRows' => 0, 'missingNumbers' => 0, 'days' => 0,
            ];

            if (! SchemaCache::hasTable(self::INWARD_TABLE) || $this->syear === null) {
                return $none;
            }

            $row = $this->inward()
                ->selectRaw(
                    'COUNT(*) as entries,
                     SUM(CASE WHEN COALESCE(TRIM(attachment), "") <> "" THEN 1 ELSE 0 END) as with_attachment,
                     SUM(CASE WHEN COALESCE(TRIM(attachment), "") = "" THEN 1 ELSE 0 END) as without_attachment,
                     SUM(CASE WHEN COALESCE(TRIM(inward_number), "") = "" THEN 1 ELSE 0 END) as missing_numbers,
                     COUNT(DISTINCT NULLIF(place_id, 0)) as places_used,
                     COUNT(DISTINCT NULLIF(file_location_id, 0)) as locations_used,
                     SUM(CASE WHEN COALESCE(file_location_id, 0) = 0 THEN 1 ELSE 0 END) as locations_missing,
                     COUNT(DISTINCT inward_date) as days'
                )
                ->first();

            $outward = SchemaCache::hasTable(self::OUTWARD_TABLE)
                ? (int) DB::table(self::OUTWARD_TABLE)
                    ->where('sub_institute_id', $this->tenantId)
                    ->where('syear', $this->syear)
                    ->count()
                : 0;

            // Numbers issued more than once WITHIN the year. Across years they
            // restart by design, which is what an earlier reading got wrong.
            $dupes = $this->inward()
                ->selectRaw('inward_number, COUNT(*) as n')
                ->whereRaw('COALESCE(TRIM(inward_number), "") <> ""')
                ->groupBy('inward_number')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            return [
                'inward' => (int) ($row->entries ?? 0),
                'outward' => $outward,
                'withAttachment' => (int) ($row->with_attachment ?? 0),
                'withoutAttachment' => (int) ($row->without_attachment ?? 0),
                'placesUsed' => (int) ($row->places_used ?? 0),
                'placesUnresolved' => $this->unresolvedCount(self::PLACE_TABLE, 'place_id'),
                'locationsUsed' => (int) ($row->locations_used ?? 0),
                'locationsUnresolved' => $this->unresolvedCount(self::LOCATION_TABLE, 'file_location_id'),
                // A location id of 0 is NOT an unresolved id — it is no location
                // at all. At one institute 923 of 1,361 entries in a single year
                // are in this state, which is a bigger hole than a broken join
                // and would have been invisible if the two were counted together.
                'locationsMissing' => (int) ($row->locations_missing ?? 0),
                'duplicateNumbers' => $dupes->count(),
                'duplicatedRows' => (int) $dupes->sum('n'),
                'missingNumbers' => (int) ($row->missing_numbers ?? 0),
                'days' => (int) ($row->days ?? 0),
            ];
        })();
    }

    /**
     * Inward rows naming a master record this institute does not hold.
     *
     * Tenant-scoped on BOTH sides: a reference id that resolves only against
     * another institute's master is unresolved here, and saying so is the point.
     */
    private function unresolvedCount(string $table, string $column): int
    {
        if (! SchemaCache::hasTable($table)) {
            return 0;
        }

        return (int) $this->inward('i')
            ->leftJoin($table.' as m', function ($join) use ($column) {
                $join->on('m.id', '=', 'i.'.$column)
                    ->on('m.sub_institute_id', '=', 'i.sub_institute_id');
            })
            ->whereNull('m.id')
            ->whereRaw('COALESCE(i.'.$column.', 0) <> 0')
            ->count();
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
                'metrics' => [
                    'inward' => $shape['inward'] > 0 ? $shape['inward'] : null,
                    'outward' => null,
                    'outwardShare' => null,
                    'attachmentShare' => null,
                    'placesUsed' => null,
                    'fileLocationsUsed' => null,
                    'duplicateNumbers' => null,
                ],
                'summary' => $coverage['reason'] ?? 'No correspondence records are available for this year.',
            ];
        }

        $total = $shape['inward'] + $shape['outward'];

        return [
            'metrics' => [
                'inward' => $shape['inward'],
                'outward' => $shape['outward'],
                'outwardShare' => $total > 0 ? round($shape['outward'] / $total * 100, 1) : null,
                'attachmentShare' => $shape['inward'] > 0
                    ? round($shape['withAttachment'] / $shape['inward'] * 100, 1)
                    : null,
                'placesUsed' => $shape['placesUsed'],
                'fileLocationsUsed' => $shape['locationsUsed'],
                'duplicateNumbers' => $shape['duplicateNumbers'],
            ],
            'summary' => $this->summarySentence($shape),
        ];
    }

    /** @param array<string,int> $shape */
    private function summarySentence(array $shape): string
    {
        $parts = [
            "{$shape['inward']} pieces of correspondence were logged inward for {$this->syear}, across "
                ."{$shape['days']} ".($shape['days'] === 1 ? 'day' : 'days').'.',
        ];

        $parts[] = $shape['outward'] === 0
            ? 'Nothing at all was logged outward in the same year.'
            : "{$shape['outward']} ".($shape['outward'] === 1 ? 'entry was' : 'entries were').' logged outward.';

        if ($shape['inward'] > 0) {
            $attachShare = round($shape['withAttachment'] / $shape['inward'] * 100, 1);
            $parts[] = "{$attachShare}% of inward entries carry a scanned document.";
        }

        $lost = $shape['locationsUnresolved'] + $shape['locationsMissing'];
        if ($lost > 0) {
            $parts[] = "{$lost} of them do not say where the physical paper went — "
                ."{$shape['locationsMissing']} record no file location at all and "
                ."{$shape['locationsUnresolved']} name one this institute's file-location master does not hold.";
        }

        return implode(' ', $parts);
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Inward correspondence by where it came from, resolved to the institute's
     * own place master.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byPlace(): array
    {
        return $this->memo['byPlace'] ??= (function (): array {
            if (! $this->coverage()['available'] || ! SchemaCache::hasTable(self::PLACE_TABLE)) {
                return [];
            }

            $rows = $this->inward('i')
                ->join(self::PLACE_TABLE.' as p', function ($join) {
                    $join->on('p.id', '=', 'i.place_id')
                        ->on('p.sub_institute_id', '=', 'i.sub_institute_id');
                })
                ->groupBy('p.id', 'p.title')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->limit(12)
                ->get([
                    'p.id',
                    DB::raw('NULLIF(TRIM(p.title), "") as place_title'),
                    DB::raw('COUNT(*) as entries'),
                    DB::raw('SUM(CASE WHEN COALESCE(TRIM(i.attachment), "") = "" THEN 1 ELSE 0 END) as no_attachment'),
                ]);

            $total = $this->shape()['inward'];

            return array_map(static fn ($row) => [
                'key' => (string) $row->id,
                'label' => $row->place_title ?? "Place #{$row->id}",
                'entries' => (int) $row->entries,
                'noAttachment' => (int) $row->no_attachment,
                'share' => $total > 0 ? round((int) $row->entries / $total * 100, 1) : null,
            ], $rows->all());
        })();
    }

    /**
     * Inward correspondence month by month.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byMonth(): array
    {
        return $this->memo['byMonth'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $rows = $this->inward()
                ->whereRaw('COALESCE(CAST(inward_date AS CHAR), "0000-00-00") <> "0000-00-00"')
                ->groupBy(DB::raw('DATE_FORMAT(inward_date, "%Y-%m")'))
                ->orderBy(DB::raw('DATE_FORMAT(inward_date, "%Y-%m")'))
                ->get([
                    DB::raw('DATE_FORMAT(inward_date, "%Y-%m") as month_key'),
                    DB::raw('COUNT(*) as entries'),
                ]);

            return array_map(static fn ($row) => [
                'key' => (string) $row->month_key,
                'label' => date('M Y', strtotime($row->month_key.'-01')),
                'entries' => (int) $row->entries,
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

        if ($shape['inward'] === 0 && $shape['outward'] === 0) {
            return [
                'available' => false,
                'reason' => 'This institute logged no correspondence in this academic year, so there is nothing to '
                    .'check.',
                'checks' => [],
            ];
        }

        $inward = $shape['inward'];
        $share = static fn (int $n, int $of): ?float => $of > 0 ? round($n / $of * 100, 2) : null;

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'outward_register_unused',
                    'label' => 'Entries logged outward',
                    'value' => $shape['outward'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['outward'], max($inward + $shape['outward'], 1)),
                    'shareLabel' => 'of all correspondence logged',
                    'state' => $shape['outward'] === 0 || $shape['outward'] < $inward / 20 ? 'attention' : 'ok',
                    'note' => $shape['outward'] === 0
                        ? 'Nothing was logged outward this year at all, against '.$inward.' logged inward. The '
                            .'register cannot show whether anything that arrived was ever answered.'
                        : 'Correspondence is logged in both directions.',
                ],
                [
                    'key' => 'unresolved_file_location',
                    'label' => 'Entries filed to a location that is not on file',
                    'value' => $shape['locationsUnresolved'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['locationsUnresolved'], $inward),
                    'shareLabel' => 'of inward entries',
                    'state' => $shape['locationsUnresolved'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['locationsUnresolved'] > 0
                        ? 'These entries name a physical file location with no row in this institute’s '
                            .'file-location master, so the record cannot say where the paper actually is.'
                        : 'Every entry is filed to a location this institute holds.',
                ],
                [
                    'key' => 'no_file_location_recorded',
                    'label' => 'Entries recording no file location at all',
                    'value' => $shape['locationsMissing'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['locationsMissing'], $inward),
                    'shareLabel' => 'of inward entries',
                    'state' => $shape['locationsMissing'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['locationsMissing'] > 0
                        ? 'These entries carry no file location at all — not a broken reference, an empty one. '
                            .'Nothing on the record says where the physical paper went.'
                        : 'Every entry records a file location.',
                ],
                [
                    'key' => 'unresolved_place',
                    'label' => 'Entries naming a sender that is not on file',
                    'value' => $shape['placesUnresolved'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['placesUnresolved'], $inward),
                    'shareLabel' => 'of inward entries',
                    'state' => $shape['placesUnresolved'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['placesUnresolved'] > 0
                        ? 'These entries name a place id with no row in this institute’s place master, so where the '
                            .'correspondence came from cannot be named.'
                        : 'Every entry names a place this institute holds.',
                ],
                [
                    'key' => 'duplicate_inward_numbers',
                    'label' => 'Inward numbers issued more than once this year',
                    // Counted WITHIN the year. Across years the numbering
                    // restarts by design, and counting across them reported 409
                    // duplicates where the real figure is single digits.
                    'value' => $shape['duplicateNumbers'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['duplicatedRows'], $inward),
                    'shareLabel' => 'of inward entries affected',
                    'state' => $shape['duplicateNumbers'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['duplicateNumbers'] > 0
                        ? 'The inward number is how a letter is found again. Where one number names more than one '
                            .'entry, a reference to it is ambiguous.'
                        : 'Every inward number this year names exactly one entry.',
                ],
                [
                    'key' => 'missing_inward_number',
                    'label' => 'Inward entries with no number at all',
                    'value' => $shape['missingNumbers'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['missingNumbers'], $inward),
                    'shareLabel' => 'of inward entries',
                    'state' => $shape['missingNumbers'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['missingNumbers'] > 0
                        ? 'These entries were logged without a reference number, so nothing else can cite them.'
                        : 'Every inward entry carries a reference number.',
                ],
                [
                    'key' => 'missing_attachment',
                    'label' => 'Inward entries with no scanned document',
                    'value' => $shape['withoutAttachment'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['withoutAttachment'], $inward),
                    'shareLabel' => 'of inward entries',
                    'state' => $shape['withoutAttachment'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['withoutAttachment'] > 0
                        ? 'These entries record that something arrived without holding a copy of it, so the register '
                            .'points at paper that has to be found physically.'
                        : 'Every inward entry carries a scanned document.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /** This institute's inward entries for this academic year. */
    private function inward(?string $alias = null): \Illuminate\Database\Query\Builder
    {
        $table = $alias === null ? self::INWARD_TABLE : self::INWARD_TABLE.' as '.$alias;
        $prefix = $alias === null ? '' : $alias.'.';

        return DB::table($table)
            ->where($prefix.'sub_institute_id', $this->tenantId)
            ->where($prefix.'syear', $this->syear);
    }
}
