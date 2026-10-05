<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Teach/Learn Intelligence — one institute, one academic year.
 *
 * ── WHAT TEACH/LEARN ACTUALLY IS ────────────────────────────────────────────
 *
 * Not PAL, not homework, not exams. `tblmenumaster` row 269 — level 2 under
 * "LMS + PAL" (230) — carries exactly two live level-3 screens:
 *
 *     275  LMS Global Mapping   lmsmapping.index   → /lms/global-mapping
 *     270  Course Catalog       course-master/     → /course-master
 *
 * (488 H5P content and 462 Content Library are both `status = 0`.)
 *
 * So Teach/Learn is THE CURRICULUM CONTENT CATALOGUE: which subjects are taught
 * to which classes, and what teaching material has been published against each.
 * That is a different question from every other module in the registry, which
 * is why it gets its own contract rather than reusing Academic's or Homework's.
 *
 * ── THE TWO TABLES, AND WHY THEY SCOPE DIFFERENTLY ──────────────────────────
 *
 * `sub_std_map` is the COURSE: one row is one subject offered to one class.
 * 6,697 rows across the database, spread evenly over real tenants (788 at 254,
 * 650 at 49, 419 at 195). It is tenant-scoped and **has no `syear` column at
 * all**, so a course count is NOT year-scoped and this module never pretends it
 * is — the year applies to the content, not to the catalogue.
 *
 * `content_master` is the MATERIAL: one row is one file or link published
 * against a (class, subject). 31,197 rows, and `syear` is populated on every
 * one of them, so the content half IS year-scoped natively.
 *
 * ── WHY THE INSTITUTE-1 LIBRARY IS NEVER MIXED IN ───────────────────────────
 *
 * 16,192 of those 31,197 rows sit at `sub_institute_id = 1`, and
 * `courseController` widens a tenant's reads to include them when
 * `school_setup.is_Lms = 'Y'`. NOTHING HERE DOES THAT, and the reason is
 * measured rather than cautious: `standard` is itself tenant-scoped (1,006 rows
 * across 76 institutes), so institute 1's content points at institute 1's own
 * class ids. Joined to another tenant's courses it resolves for ONE course at
 * institute 195 and ZERO at 254 — and only two institutes in the database carry
 * the flag at all (61 and 328), both of which hold no content of their own.
 * Counting that library as a tenant's coverage would credit a school with
 * material its classes cannot reach.
 *
 * Every figure below is `sub_institute_id = $tenantId`, on both sides of every
 * join.
 *
 * ── WHAT THIS MODULE CANNOT SAY, AND WHY IT SAYS SO INSTEAD ─────────────────
 *
 * CHAPTERS DO NOT RESOLVE. `content_master.chapter_id` is populated on 31,192
 * of 31,197 rows, but `chapter_master` holds **446 rows in the entire
 * database** — 414 of them at institute 1. 28,392 of 31,192 content rows name a
 * chapter no `chapter_master` row answers to; at institute 195 in 2025 it is
 * 1,918 of 1,918. There is therefore no chapter-level analysis anywhere in this
 * module. The gap is reported as a finding and a record check, which is the
 * honest thing to do with it, rather than silently grouping by an id that
 * resolves to nothing.
 *
 * LEARNING OUTCOMES ARE NOT TAGGED. `lo_master_ids` is non-empty on ONE row out
 * of 31,197. No outcome coverage is computed.
 *
 * EFFORT IS NOT RECORDED. `topic_master.estimated_minutes` is 0 on every row
 * and `sub_std_map.content_quantity` is empty at every tenant, so nothing here
 * reports teaching time or expected volume.
 *
 * ── WHAT IS DELIBERATELY NOT READ ───────────────────────────────────────────
 *
 * `title`, `description`, `filename`, `url` and `meta_tags` are free text
 * naming real teaching files. Nothing groups by, quotes or returns any of them;
 * `file_type` — a short format token such as `pdf` or `mp4` — is the only
 * content column whose VALUE reaches the screen.
 */
final class TeachLearnIntelligence
{
    private const COURSE_TABLE = 'sub_std_map';

    private const CONTENT_TABLE = 'content_master';

    private const CHAPTER_TABLE = 'chapter_master';

    private const STANDARD_TABLE = 'standard';

    /**
     * Below this many published items in the year, a share describes the
     * handful of files somebody happened to upload rather than the curriculum.
     *
     * Public so tests assert against the threshold rather than hard-coding a row
     * count out of this database.
     */
    public const MIN_ITEMS = 20;

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

    /**
     * Whether this institute-year holds enough of its OWN content to describe.
     *
     * The honest-unavailable states are distinguished deliberately, because
     * they have different answers: a school that has never published anything
     * is not running the module, a school that published in other years has
     * simply not started this one, and a school with nine files has a module in
     * use and nothing to draw a share from. "No data" would erase all three.
     *
     * @return array<string,mixed>
     */
    private function computeCoverage(): array
    {
        $empty = [
            'sourceTable' => self::CONTENT_TABLE,
            'sources' => [],
            'counts' => [],
            'totalRows' => 0,
            'usableRows' => 0,
        ];

        if (! SchemaCache::hasTable(self::CONTENT_TABLE) || ! SchemaCache::hasTable(self::COURSE_TABLE)) {
            return $empty + [
                'available' => false,
                'reason' => "Tables '".self::COURSE_TABLE."' and '".self::CONTENT_TABLE
                    ."' are not both present in this deployment.",
            ];
        }

        if ($this->syear === null) {
            return $empty + [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute, and published teaching '
                    .'content is recorded a year at a time.',
            ];
        }

        $shape = $this->shape();

        if ($shape['items'] < self::MIN_ITEMS) {
            return array_merge($empty, [
                'totalRows' => $shape['items'],
                'usableRows' => $shape['items'],
                'counts' => [
                    'courses' => $shape['courses'],
                    'items' => $shape['items'],
                    'itemsAllYears' => $shape['itemsAllYears'],
                ],
                'available' => false,
                'reason' => match (true) {
                    $shape['courses'] === 0
                        => 'This institute has no course catalogue: no subject is mapped to any class in '
                            .self::COURSE_TABLE.'. Teach/Learn has nothing to publish content against yet.',
                    $shape['items'] === 0 && $shape['itemsAllYears'] === 0
                        => 'This institute has '.$shape['courses'].' '
                            .($shape['courses'] === 1 ? 'course' : 'courses')
                            .' in its catalogue and has never published teaching content against any of them. '
                            .'Nothing here is missing — it does not use the Teach/Learn content library.',
                    $shape['items'] === 0
                        => 'No teaching content was published for '.$this->syear.', though this institute holds '
                            .$shape['itemsAllYears'].' items in other years. The library is in use; nothing was '
                            .'added to it this year.',
                    default => 'Only '.$shape['items'].' '.($shape['items'] === 1 ? 'item was' : 'items were')
                        .' published for '.$this->syear.', against '.$shape['courses'].' courses. Any coverage '
                        .'share drawn from that describes '
                        .($shape['items'] === 1 ? 'that single file' : 'those '.$shape['items'].' files')
                        .' rather than the curriculum, so the counts below are reported and no rate is.',
                },
            ]);
        }

        return [
            'available' => true,
            'reason' => null,
            'sourceTable' => self::CONTENT_TABLE,
            'sources' => [
                'courses' => $shape['courses'] > 0,
                'content' => $shape['items'] > 0,
                // False everywhere in this database except institute 1 — the
                // chapter spine is the module's largest structural gap, and it
                // is a finding rather than a missing feature.
                'chapters' => $shape['withChapter'] > 0 && $shape['orphanChapter'] < $shape['withChapter'],
                'formats' => $shape['formats'] > 0,
                'classes' => $shape['standardsCovered'] > 0,
            ],
            'counts' => [
                'courses' => $shape['courses'],
                'coursesWithContent' => $shape['coursesWithContent'],
                'items' => $shape['items'],
                'formats' => $shape['formats'],
                'classesWithContent' => $shape['standardsCovered'],
            ],
            'totalRows' => $shape['items'],
            'usableRows' => $shape['items'],
            'period' => "Academic Year {$this->syear}",
        ];
    }

    /* -------------------------------------------------------- the raw shape */

    /**
     * Every figure this module counts. One pass over the year's content, plus
     * the catalogue count and the chapter-resolution check.
     *
     * @return array{courses:int,courseRows:int,items:int,coursesWithContent:int,
     *               coursesWithoutContent:int,hidden:int,shown:int,formats:int,
     *               topFormat:?string,topFormatItems:int,noFormat:int,noTopic:int,
     *               withChapter:int,orphanChapter:int,standardsCovered:int,
     *               itemsAllYears:int,priorYearItems:int}
     */
    public function shape(): array
    {
        return $this->memo['shape'] ??= (function (): array {
            $none = [
                'courses' => 0, 'courseRows' => 0, 'items' => 0, 'coursesWithContent' => 0,
                'coursesWithoutContent' => 0, 'hidden' => 0, 'shown' => 0, 'formats' => 0,
                'topFormat' => null, 'topFormatItems' => 0, 'noFormat' => 0, 'noTopic' => 0,
                'withChapter' => 0, 'orphanChapter' => 0, 'standardsCovered' => 0,
                'itemsAllYears' => 0, 'priorYearItems' => 0,
            ];

            if (! SchemaCache::hasTable(self::CONTENT_TABLE) || ! SchemaCache::hasTable(self::COURSE_TABLE)) {
                return $none;
            }

            // The catalogue. NOT year-scoped: sub_std_map carries no syear.
            $catalogue = $this->courses()
                ->selectRaw(
                    'COUNT(*) as course_rows,
                     COUNT(DISTINCT CONCAT(standard_id, "-", subject_id)) as courses'
                )
                ->first();

            if ($this->syear === null) {
                return array_merge($none, [
                    'courses' => (int) ($catalogue->courses ?? 0),
                    'courseRows' => (int) ($catalogue->course_rows ?? 0),
                ]);
            }

            $content = $this->content()
                ->selectRaw(
                    'COUNT(*) as items,
                     COUNT(DISTINCT CONCAT(standard_id, "-", subject_id)) as courses_with_content,
                     COUNT(DISTINCT standard_id) as standards_covered,
                     SUM(CASE WHEN show_hide = "0" THEN 1 ELSE 0 END) as hidden,
                     SUM(CASE WHEN show_hide = "1" THEN 1 ELSE 0 END) as shown,
                     SUM(CASE WHEN COALESCE(TRIM(file_type), "") = "" THEN 1 ELSE 0 END) as no_format,
                     COUNT(DISTINCT NULLIF(TRIM(file_type), "")) as formats,
                     SUM(CASE WHEN COALESCE(topic_id, 0) = 0 THEN 1 ELSE 0 END) as no_topic,
                     SUM(CASE WHEN COALESCE(chapter_id, 0) <> 0 THEN 1 ELSE 0 END) as with_chapter'
                )
                ->first();

            $top = $this->content()
                ->whereRaw('COALESCE(TRIM(file_type), "") <> ""')
                ->groupBy('file_type')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->limit(1)
                ->get([DB::raw('TRIM(file_type) as ft'), DB::raw('COUNT(*) as n')])
                ->first();

            $itemsAllYears = (int) DB::table(self::CONTENT_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->count();

            $priorYear = (int) DB::table(self::CONTENT_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', (string) (((int) $this->syear) - 1))
                ->count();

            // Courses in the catalogue that carry nothing this year. Computed
            // from the catalogue rather than from the content, so a content row
            // pointing at a course the catalogue no longer offers cannot make
            // coverage look better than it is.
            $withContent = $this->coursesWithContentCount();

            $courses = (int) ($catalogue->courses ?? 0);

            return [
                'courses' => $courses,
                'courseRows' => (int) ($catalogue->course_rows ?? 0),
                'items' => (int) ($content->items ?? 0),
                'coursesWithContent' => $withContent,
                'coursesWithoutContent' => max($courses - $withContent, 0),
                'hidden' => (int) ($content->hidden ?? 0),
                'shown' => (int) ($content->shown ?? 0),
                'formats' => (int) ($content->formats ?? 0),
                'topFormat' => $top->ft ?? null,
                'topFormatItems' => (int) ($top->n ?? 0),
                'noFormat' => (int) ($content->no_format ?? 0),
                'noTopic' => (int) ($content->no_topic ?? 0),
                'withChapter' => (int) ($content->with_chapter ?? 0),
                'orphanChapter' => $this->orphanChapterCount(),
                'standardsCovered' => (int) ($content->standards_covered ?? 0),
                'itemsAllYears' => $itemsAllYears,
                'priorYearItems' => $priorYear,
            ];
        })();
    }

    /**
     * Catalogue courses holding at least one content item this year.
     *
     * Tenant-scoped on both sides of the join, so institute 1's shared library
     * cannot contribute to a tenant's coverage.
     */
    private function coursesWithContentCount(): int
    {
        return (int) DB::table(self::COURSE_TABLE.' as s')
            ->where('s.sub_institute_id', $this->tenantId)
            ->where('s.status', 1)
            ->join(self::CONTENT_TABLE.' as c', function ($join) {
                $join->on('c.standard_id', '=', 's.standard_id')
                    ->on('c.subject_id', '=', 's.subject_id')
                    ->on('c.sub_institute_id', '=', 's.sub_institute_id')
                    ->where('c.syear', '=', $this->syear);
            })
            ->distinct()
            ->count(DB::raw('CONCAT(s.standard_id, "-", s.subject_id)'));
    }

    /**
     * Content naming a chapter this institute's own chapter master does not
     * hold.
     *
     * Tenant-scoped on BOTH sides deliberately: a chapter id that resolves only
     * against institute 1's master is unresolved HERE, and saying so is the
     * point — that is precisely the state 28,392 rows in this database are in.
     */
    private function orphanChapterCount(): int
    {
        if (! SchemaCache::hasTable(self::CHAPTER_TABLE)) {
            return 0;
        }

        return (int) $this->content('c')
            ->leftJoin(self::CHAPTER_TABLE.' as m', function ($join) {
                $join->on('m.id', '=', 'c.chapter_id')
                    ->on('m.sub_institute_id', '=', 'c.sub_institute_id');
            })
            ->whereNull('m.id')
            ->whereRaw('COALESCE(c.chapter_id, 0) <> 0')
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
                    // The catalogue is real even when the content is not, and
                    // it is the one figure worth showing in that state. Every
                    // rate stays NULL rather than 0 — a coverage rate over no
                    // published content is undefined, not zero per cent.
                    'courses' => $shape['courses'] > 0 ? $shape['courses'] : null,
                    'coursesWithContent' => null,
                    'contentCoverage' => null,
                    'items' => $shape['items'] > 0 ? $shape['items'] : null,
                    'formats' => null,
                    'hidden' => null,
                ],
                'summary' => $coverage['reason'] ?? 'No teaching content is available for this year.',
            ];
        }

        return [
            'metrics' => [
                'courses' => $shape['courses'],
                'coursesWithContent' => $shape['coursesWithContent'],
                'contentCoverage' => $shape['courses'] > 0
                    ? round($shape['coursesWithContent'] / $shape['courses'] * 100, 1)
                    : null,
                'items' => $shape['items'],
                'formats' => $shape['formats'],
                'hidden' => $shape['hidden'],
            ],
            'summary' => $this->summarySentence($shape),
        ];
    }

    /** @param array<string,mixed> $shape */
    private function summarySentence(array $shape): string
    {
        $parts = [];

        $coverage = $shape['courses'] > 0
            ? round($shape['coursesWithContent'] / $shape['courses'] * 100, 1)
            : null;

        $parts[] = "This institute's catalogue offers {$shape['courses']} "
            .($shape['courses'] === 1 ? 'course' : 'courses')
            .' — one subject taught to one class — and the catalogue is not year-scoped, because '
            .self::COURSE_TABLE.' carries no academic year.';

        $parts[] = "{$shape['items']} teaching "
            .($shape['items'] === 1 ? 'item was' : 'items were')
            ." published for {$this->syear}, against {$shape['coursesWithContent']} of those courses"
            .($coverage !== null ? " ({$coverage}%)." : '.');

        if ($shape['topFormat'] !== null && $shape['items'] > 0) {
            $share = round($shape['topFormatItems'] / $shape['items'] * 100, 1);
            $parts[] = "{$share}% of it is {$shape['topFormat']}, across {$shape['formats']} "
                .($shape['formats'] === 1 ? 'format' : 'distinct formats').'.';
        }

        if ($shape['orphanChapter'] > 0) {
            $parts[] = "{$shape['orphanChapter']} of {$shape['withChapter']} items name a chapter this institute's "
                .'own chapter master does not hold, so they cannot be placed in the curriculum structure.';
        }

        if ($shape['hidden'] > 0) {
            $parts[] = "{$shape['hidden']} "
                .($shape['hidden'] === 1 ? 'item is' : 'items are')
                .' published but hidden from learners.';
        }

        return implode(' ', $parts);
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Courses and content by class, resolved against this institute's own
     * `standard` master.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byClass(): array
    {
        return $this->memo['byClass'] ??= (function (): array {
            if (! $this->coverage()['available'] || ! SchemaCache::hasTable(self::STANDARD_TABLE)) {
                return [];
            }

            $rows = DB::table(self::COURSE_TABLE.' as s')
                ->where('s.sub_institute_id', $this->tenantId)
                ->where('s.status', 1)
                ->join(self::STANDARD_TABLE.' as st', function ($join) {
                    $join->on('st.id', '=', 's.standard_id')
                        ->on('st.sub_institute_id', '=', 's.sub_institute_id');
                })
                ->leftJoin(self::CONTENT_TABLE.' as c', function ($join) {
                    $join->on('c.standard_id', '=', 's.standard_id')
                        ->on('c.subject_id', '=', 's.subject_id')
                        ->on('c.sub_institute_id', '=', 's.sub_institute_id')
                        ->where('c.syear', '=', $this->syear);
                })
                ->groupBy('st.id', 'st.name')
                ->orderByDesc(DB::raw('COUNT(DISTINCT CONCAT(s.standard_id, "-", s.subject_id))'))
                ->limit(15)
                ->get([
                    'st.id',
                    DB::raw('NULLIF(TRIM(st.name), "") as class_name'),
                    DB::raw('COUNT(DISTINCT CONCAT(s.standard_id, "-", s.subject_id)) as courses'),
                    DB::raw('COUNT(DISTINCT CASE WHEN c.id IS NOT NULL
                             THEN CONCAT(s.standard_id, "-", s.subject_id) END) as with_content'),
                    DB::raw('COUNT(c.id) as items'),
                ]);

            return array_map(static function ($row) {
                $courses = (int) $row->courses;
                $withContent = (int) $row->with_content;

                return [
                    'key' => (string) $row->id,
                    'label' => $row->class_name ?? "Class #{$row->id}",
                    'courses' => $courses,
                    'withContent' => $withContent,
                    'items' => (int) $row->items,
                    'coverage' => $courses > 0 ? round($withContent / $courses * 100, 1) : null,
                ];
            }, $rows->all());
        })();
    }

    /**
     * Published content by format.
     *
     * `file_type` is the ONLY content column whose value reaches the screen —
     * a short token such as `pdf`, `mp4` or `link`. Titles, filenames and URLs
     * name real teaching files and are never read.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byFormat(): array
    {
        return $this->memo['byFormat'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $rows = $this->content()
                ->groupBy(DB::raw('COALESCE(NULLIF(TRIM(file_type), ""), "(not recorded)")'))
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->limit(12)
                ->get([
                    DB::raw('COALESCE(NULLIF(TRIM(file_type), ""), "(not recorded)") as ft'),
                    DB::raw('COUNT(*) as items'),
                    DB::raw('SUM(CASE WHEN show_hide = "0" THEN 1 ELSE 0 END) as hidden'),
                ]);

            $total = $this->shape()['items'];

            return array_map(static fn ($row) => [
                'key' => (string) $row->ft,
                'label' => (string) $row->ft,
                'items' => (int) $row->items,
                'hidden' => (int) $row->hidden,
                'share' => $total > 0 ? round((int) $row->items / $total * 100, 1) : null,
            ], $rows->all());
        })();
    }

    /**
     * How much content this institute published in each year it has used the
     * library.
     *
     * Deliberately spans every year rather than the selected one: "was anything
     * added this year" is only answerable against what the institute normally
     * adds.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byYear(): array
    {
        return $this->memo['byYear'] ??= (function (): array {
            if (! SchemaCache::hasTable(self::CONTENT_TABLE)) {
                return [];
            }

            $rows = DB::table(self::CONTENT_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->whereRaw('COALESCE(syear, 0) <> 0')
                ->groupBy('syear')
                ->orderBy('syear')
                ->get([
                    DB::raw('syear as y'),
                    DB::raw('COUNT(*) as items'),
                    DB::raw('COUNT(DISTINCT CONCAT(standard_id, "-", subject_id)) as courses'),
                ]);

            return array_map(fn ($row) => [
                'key' => (string) $row->y,
                'label' => (string) $row->y,
                'items' => (int) $row->items,
                'courses' => (int) $row->courses,
                'current' => (string) $row->y === $this->syear,
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

        if ($shape['items'] === 0) {
            return [
                'available' => false,
                'reason' => $shape['courses'] === 0
                    ? 'This institute has no course catalogue and no published content, so there is nothing to check.'
                    : 'This institute published no teaching content in this academic year, so there is nothing to '
                        .'check against it. Its '.$shape['courses'].' catalogue courses are reported above.',
                'checks' => [],
            ];
        }

        $items = $shape['items'];
        $share = static fn (int $n, int $of): ?float => $of > 0 ? round($n / $of * 100, 2) : null;

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'courses_without_content',
                    'label' => 'Catalogue courses carrying no content this year',
                    'value' => $shape['coursesWithoutContent'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['coursesWithoutContent'], $shape['courses']),
                    'shareLabel' => 'of the course catalogue',
                    'state' => $shape['coursesWithoutContent'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['coursesWithoutContent'] > 0
                        ? 'These subject-and-class pairs are offered in the catalogue and have nothing published '
                            .'against them for this year. A learner opening one finds an empty course.'
                        : 'Every course in the catalogue carries content for this year.',
                ],
                [
                    'key' => 'unresolved_chapter',
                    'label' => 'Items naming a chapter that is not on file',
                    'value' => $shape['orphanChapter'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['orphanChapter'], $items),
                    'shareLabel' => 'of published items',
                    'state' => $shape['orphanChapter'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['orphanChapter'] > 0
                        ? 'These items carry a chapter id with no row in this institute’s own chapter master, so '
                            .'nothing can place them in the curriculum. This module therefore reports no '
                            .'chapter-level figures at all rather than grouping by an id that resolves to nothing.'
                        : 'Every item is filed against a chapter this institute holds.',
                ],
                [
                    'key' => 'no_topic_recorded',
                    'label' => 'Items filed against no topic',
                    'value' => $shape['noTopic'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['noTopic'], $items),
                    'shareLabel' => 'of published items',
                    'state' => $shape['noTopic'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['noTopic'] > 0
                        ? 'These items sit against a class and subject but no topic, so they can be listed for a '
                            .'course and not placed within it.'
                        : 'Every item records the topic it belongs to.',
                ],
                [
                    'key' => 'hidden_content',
                    'label' => 'Items published but hidden from learners',
                    'value' => $shape['hidden'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['hidden'], $items),
                    'shareLabel' => 'of published items',
                    'state' => $shape['hidden'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['hidden'] > 0
                        ? 'These items exist against a course with their visibility switched off. The work of '
                            .'preparing them is done and no learner can reach them.'
                        : 'Every published item is visible to learners.',
                ],
                [
                    'key' => 'no_format_recorded',
                    'label' => 'Items recording no file format',
                    'value' => $shape['noFormat'],
                    'format' => 'count',
                    'sharePercent' => $share($shape['noFormat'], $items),
                    'shareLabel' => 'of published items',
                    'state' => $shape['noFormat'] > 0 ? 'attention' : 'ok',
                    'note' => $shape['noFormat'] > 0
                        ? 'These items do not say what kind of file they are, so a player or viewer cannot be chosen '
                            .'for them ahead of opening.'
                        : 'Every item records its file format.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /** This institute's course catalogue. NOT year-scoped — there is no syear. */
    private function courses(?string $alias = null): \Illuminate\Database\Query\Builder
    {
        $table = $alias === null ? self::COURSE_TABLE : self::COURSE_TABLE.' as '.$alias;
        $prefix = $alias === null ? '' : $alias.'.';

        return DB::table($table)
            ->where($prefix.'sub_institute_id', $this->tenantId)
            ->where($prefix.'status', 1);
    }

    /** This institute's own published content for this academic year. */
    private function content(?string $alias = null): \Illuminate\Database\Query\Builder
    {
        $table = $alias === null ? self::CONTENT_TABLE : self::CONTENT_TABLE.' as '.$alias;
        $prefix = $alias === null ? '' : $alias.'.';

        return DB::table($table)
            ->where($prefix.'sub_institute_id', $this->tenantId)
            ->where($prefix.'syear', $this->syear);
    }
}
