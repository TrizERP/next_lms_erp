<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * LMS Activity Intelligence — one institute, one academic year.
 *
 * ── WHAT THIS IS, AND WHAT IT IS NOT ─────────────────────────────────────────
 *
 * {@see TeachLearnIntelligence} answers "what is published" — the curriculum
 * content catalogue. It deliberately never touches what a learner DID with any
 * of it. This module is the other half: what children were actually asked to
 * do, and what came back. It is not PAL — PAL's meaning-bearing columns are
 * NULL at scale in this deployment and PAL is excluded from Intelligence on
 * that measured basis, the same basis this file's own probes reconfirmed for
 * `homework.reviewed_by`/`feedback_published`/`teacher_remarks` (0 of 1,543
 * rows at the institute this file was profiled against). It is not exams or
 * results, each of which is owned elsewhere in the registry.
 *
 * ── COMPOSITION, NOT DUPLICATION ─────────────────────────────────────────────
 *
 * The catalogue half is read by holding a real {@see TeachLearnIntelligence}
 * and reading its `coverage()`/`position()`/`dataQuality()` back verbatim. The
 * activity half is read the same way from a real {@see HomeworkIntelligence} —
 * `homework` is the ONLY table in this deployment where a per-child engagement
 * signal is genuinely populated; `lms_assignment`, the separate assignment
 * workflow, holds 86 rows database-wide and is reported as a bare count, never
 * a rate, for exactly the reason `HomeworkIntelligence` gives for keeping it
 * out of its own figures: it carries a two-sided status the single-sided
 * `homework` table does not, so a merged denominator would mean two things.
 *
 * Nothing here recomputes a figure either composed class already provides.
 * What this class adds is the ONE thing neither one can say alone: whether the
 * material that was published is the material children were actually set to
 * work from — read by joining the two on the same (standard, subject) pair
 * both tables carry, tenant- and year-scoped on both sides exactly as
 * `TeachLearnIntelligence` insists on for its own join to `content_master`.
 *
 * ── WHAT WAS MEASURED BEFORE THIS FILE TRUSTED A COLUMN ──────────────────────
 *
 * There is no `due_date` on `homework` — only `date` (when it was set) and
 * `submission_date` (when it came back), so "on-time vs late" cannot be said
 * without inventing a deadline. What IS real is the gap between the two dates
 * for rows that are not already flagged as contradictory by
 * `HomeworkIntelligence::anomalies()`, so this module reports a median
 * SUBMISSION LAG rather than a fabricated on-time rate.
 *
 * `lms_assignment.json_annotation` (an AI evaluation artefact) is non-empty on
 * 9 of 86 rows database-wide — real, but far too sparse at any one tenant to
 * report as a signal, so it is not read here.
 */
final class LmsActivityIntelligence
{
    private const HOMEWORK_TABLE = 'homework';

    private const CONTENT_TABLE = 'content_master';

    private const COURSE_TABLE = 'sub_std_map';

    private const STANDARD_TABLE = 'standard';

    /** Below this many child-assignments, a class's submission rate is about individuals. */
    public const MIN_CLASS_COHORT = 10;

    /**
     * Below this many catalogue courses carrying content, a content/activity
     * alignment share describes a handful of courses rather than the
     * institute. Smaller than {@see TeachLearnIntelligence::MIN_ITEMS} on
     * purpose: this counts courses, not items, and a course count is always
     * the smaller of the two at any institute in this database.
     */
    public const MIN_CROSS_COURSES = 5;

    private readonly string $tenantId;

    private readonly ?string $syear;

    private readonly TeachLearnIntelligence $catalogue;

    private readonly HomeworkIntelligence $activity;

    /** @var array<string,mixed> */
    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
        $this->catalogue = new TeachLearnIntelligence($this->tenantId, $this->syear);
        $this->activity = new HomeworkIntelligence($this->tenantId, $this->syear);
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /** The composed catalogue half, for a controller that wants its own figures directly. */
    public function catalogue(): TeachLearnIntelligence
    {
        return $this->catalogue;
    }

    /** The composed activity half, for a controller that wants its own figures directly. */
    public function activity(): HomeworkIntelligence
    {
        return $this->activity;
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
        $catalogueCoverage = $this->catalogue->coverage();
        $activityCoverage = $this->activity->coverage();
        $cross = $this->crossShape();

        $catalogueAvailable = (bool) $catalogueCoverage['available'];
        $activityAvailable = (bool) $activityCoverage['available'];
        // Either half is enough to show something real. A module that refused
        // to render until BOTH the catalogue and homework were populated would
        // go blank at every institute that runs one but not the other, which
        // is most of them — `content_master` and `homework` overlap at only
        // one tenant in this database at the volumes either table needs alone.
        $available = $catalogueAvailable || $activityAvailable;

        $sources = [
            'catalogueContent' => $catalogueAvailable,
            'homeworkActivity' => $activityAvailable,
            'assignmentWorkflow' => (int) ($activityCoverage['counts']['assignments'] ?? 0) > 0,
            'contentActivityLink' => $cross['coursesWithContent'] > 0,
        ];

        $counts = [
            'courses' => (int) ($catalogueCoverage['counts']['courses'] ?? 0),
            'coursesWithContent' => (int) ($catalogueCoverage['counts']['coursesWithContent'] ?? 0),
            'contentItems' => (int) ($catalogueCoverage['counts']['items'] ?? 0),
            'childAssignments' => (int) ($activityCoverage['counts']['childAssignments'] ?? 0),
            'studentsActive' => (int) ($activityCoverage['counts']['students'] ?? 0),
            'classesActive' => (int) ($activityCoverage['counts']['classes'] ?? 0),
            'submitted' => (int) ($activityCoverage['counts']['submitted'] ?? 0),
            'assignments' => (int) ($activityCoverage['counts']['assignments'] ?? 0),
            'coursesWithContentAndActivity' => $cross['coursesWithContentAndActivity'],
            'coursesWithContentNoActivity' => $cross['coursesWithContentNoActivity'],
            'activityOutsideCatalogue' => $cross['activityOutsideCatalogue'],
        ];

        if (! $available) {
            $reasons = array_values(array_filter([
                $catalogueCoverage['reason'] ?? null,
                $activityCoverage['reason'] ?? null,
            ]));

            return [
                'available' => false,
                'reason' => $reasons !== []
                    ? implode(' ', $reasons)
                    : 'Neither the content catalogue nor homework activity is available for this academic year.',
                'sourceTable' => self::HOMEWORK_TABLE,
                'sources' => $sources,
                'counts' => $counts,
                'totalRows' => $counts['childAssignments'] + $counts['contentItems'],
                'usableRows' => 0,
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'sourceTable' => self::HOMEWORK_TABLE,
            'sources' => $sources,
            'counts' => $counts,
            'totalRows' => $counts['childAssignments'] + $counts['contentItems'],
            'usableRows' => $counts['childAssignments'] + $counts['contentItems'],
            'period' => "Academic Year {$this->syear}",
        ];
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

        if (! $coverage['available']) {
            return [
                'metrics' => [
                    'courses' => $coverage['counts']['courses'] > 0 ? $coverage['counts']['courses'] : null,
                    'coursesWithContent' => null,
                    'contentCoverage' => null,
                    'contentItems' => $coverage['counts']['contentItems'] > 0 ? $coverage['counts']['contentItems'] : null,
                    'childAssignments' => null,
                    'students' => null,
                    'classes' => null,
                    'submissionRate' => null,
                    'classReach' => null,
                    'assignments' => $coverage['counts']['assignments'] > 0 ? $coverage['counts']['assignments'] : null,
                    'coursesWithActivity' => null,
                    'contentWithoutActivity' => null,
                    'contentActivityAlignment' => null,
                    'activityOutsideCatalogue' => null,
                    'medianSubmissionLagDays' => null,
                ],
                'summary' => $coverage['reason'] ?? 'Neither the content catalogue nor homework activity is available '
                    .'for this academic year.',
            ];
        }

        $catalogueMetrics = $this->catalogue->position()['metrics'];
        $activityMetrics = $this->activity->position()['metrics'];
        $cross = $this->crossShape();
        $lag = $this->submissionLagStats();

        $alignment = $cross['coursesWithContent'] >= self::MIN_CROSS_COURSES
            ? round($cross['coursesWithContentAndActivity'] / $cross['coursesWithContent'] * 100, 1)
            : null;

        $metrics = array_merge($catalogueMetrics, $activityMetrics, [
            'coursesWithActivity' => $cross['coursesWithActivity'],
            'contentWithoutActivity' => $cross['coursesWithContentNoActivity'],
            // The blend figure: of the courses carrying published content this
            // year, the share that also carries homework activity. NULL, not
            // zero, below the cohort — a share over two courses is not the
            // institute's alignment.
            'contentActivityAlignment' => $alignment,
            'activityOutsideCatalogue' => $cross['activityOutsideCatalogue'],
            'medianSubmissionLagDays' => $lag['median'],
        ]);

        return [
            'metrics' => $metrics,
            'summary' => $this->summarySentence($cross, $lag),
        ];
    }

    /**
     * @param  array<string,mixed>  $cross
     * @param  array{median:?float,sampled:int}  $lag
     */
    private function summarySentence(array $cross, array $lag): string
    {
        $parts = [];

        $parts[] = $this->catalogue->position()['summary'];
        $parts[] = $this->activity->position()['summary'];

        if ($cross['coursesWithContent'] >= self::MIN_CROSS_COURSES) {
            $alignment = round($cross['coursesWithContentAndActivity'] / $cross['coursesWithContent'] * 100, 1);
            $parts[] = "{$cross['coursesWithContentAndActivity']} of {$cross['coursesWithContent']} courses carrying "
                ."published content this year also carry homework activity ({$alignment}%).";
        }

        if ($lag['median'] !== null) {
            $unit = $lag['median'] == 1.0 ? 'day' : 'days';
            $parts[] = "Among submissions with both a set date and a submission date on file, the median turnaround "
                ."is {$lag['median']} {$unit}.";
        }

        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }

    /* ---------------------------------------------------- L2: distribution */

    /**
     * Catalogue courses, published content and homework activity, blended by
     * class. A class can appear with courses but no homework, homework but no
     * catalogue course at all (activity outside the mapped curriculum), or
     * both — each state is real and none is hidden by the merge.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byClass(): array
    {
        return $this->memo['byClass'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $cross = $this->crossShape();
            $hwByStandard = $this->homeworkByStandard();

            $rows = [];
            $seen = [];

            foreach ($cross['byStandard'] as $entry) {
                $key = $entry['key'];
                $seen[$key] = true;
                $hw = $hwByStandard[$key] ?? null;

                $rows[] = [
                    'key' => $key,
                    'label' => $entry['label'],
                    'courses' => $entry['courses'],
                    'withContent' => $entry['withContent'],
                    'contentCoverage' => $entry['courses'] > 0
                        ? round($entry['withContent'] / $entry['courses'] * 100, 1)
                        : null,
                    'withContentNoActivity' => $entry['withContentNoActivity'],
                    'homeworkSet' => $hw['homeworkSet'] ?? 0,
                    'homeworkStudents' => $hw['students'] ?? 0,
                    'submissionRate' => $hw['submissionRate'] ?? null,
                ];
            }

            foreach ($hwByStandard as $key => $hw) {
                if (isset($seen[$key])) {
                    continue;
                }

                // Homework set for a class the catalogue offers no course for
                // at all — counted in the totals above and shown here rather
                // than silently dropped from the breakdown.
                $rows[] = [
                    'key' => $key,
                    'label' => $hw['label'],
                    'courses' => 0,
                    'withContent' => 0,
                    'contentCoverage' => null,
                    'withContentNoActivity' => 0,
                    'homeworkSet' => $hw['homeworkSet'],
                    'homeworkStudents' => $hw['students'],
                    'submissionRate' => $hw['submissionRate'],
                ];
            }

            usort($rows, static fn ($a, $b) => $b['homeworkSet'] <=> $a['homeworkSet']);

            return $rows;
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
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $cross = $this->crossShape();
        $checks = [];

        if ($cross['coursesWithContent'] > 0) {
            $noActivity = $cross['coursesWithContentNoActivity'];
            $share = round($noActivity / $cross['coursesWithContent'] * 100, 2);

            $checks[] = [
                'key' => 'content_without_homework_activity',
                'label' => 'Courses with published content and no homework this year',
                'value' => $noActivity,
                'format' => 'count',
                'sharePercent' => $share,
                'shareLabel' => 'of courses carrying content',
                'state' => $noActivity > 0 ? 'attention' : 'ok',
                'note' => $noActivity > 0
                    ? 'These courses carry published teaching material for this year and no homework was set '
                        .'against either the class or the subject. Content and activity are read from separate '
                        .'tables — '.self::CONTENT_TABLE.' and '.self::HOMEWORK_TABLE.' — matched on the same '
                        .'(class, subject) pair, tenant- and year-scoped on both sides.'
                    : 'Every course carrying content this year also carries homework activity.',
            ];
        }

        $checks[] = [
            'key' => 'activity_outside_catalogue',
            'label' => 'Homework set for a class/subject pair not in the course catalogue',
            'value' => $cross['activityOutsideCatalogue'],
            'format' => 'count',
            'sharePercent' => null,
            'shareLabel' => null,
            'state' => $cross['activityOutsideCatalogue'] > 0 ? 'attention' : 'ok',
            'note' => $cross['activityOutsideCatalogue'] > 0
                ? 'These homework rows name a (standard, subject) combination that '.self::COURSE_TABLE.' does not '
                    .'currently offer for this institute — the catalogue and the teaching activity have drifted '
                    .'apart, or the course has since been withdrawn.'
                : 'Every (standard, subject) pair carrying homework this year is also offered in the course '
                    .'catalogue.',
        ];

        // Carried through from the composed modules and relabelled, so a
        // reader knows which half of the blend each check describes — neither
        // recomputed nor allowed to drift from the number its own module shows.
        foreach ((array) ($this->catalogue->dataQuality()['checks'] ?? []) as $check) {
            $check = (array) $check;
            $check['key'] = 'catalogue_'.$check['key'];
            $check['label'] = '[Catalogue] '.$check['label'];
            $checks[] = $check;
        }

        foreach ((array) ($this->activity->dataQuality()['checks'] ?? []) as $check) {
            $check = (array) $check;
            $check['key'] = 'activity_'.$check['key'];
            $check['label'] = '[Activity] '.$check['label'];
            $checks[] = $check;
        }

        return [
            'available' => true,
            'reason' => null,
            'checks' => $checks,
        ];
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * This institute's own median submission turnaround, in days.
     *
     * Computed only over rows the submission status marks returned AND whose
     * submission date does not precede the date the work was set — the same
     * rows {@see HomeworkIntelligence::anomalies()} would NOT flag as a
     * status/date contradiction. There is no due date anywhere in `homework`,
     * so this is deliberately a turnaround figure and never framed as
     * "on-time" against a deadline this table cannot supply.
     *
     * @return array{median:?float,sampled:int}
     */
    private function submissionLagStats(): array
    {
        return $this->memo['lag'] ??= (function (): array {
            if ($this->syear === null || ! SchemaCache::hasTable(self::HOMEWORK_TABLE)) {
                return ['median' => null, 'sampled' => 0];
            }

            $lags = DB::table(self::HOMEWORK_TABLE)
                ->where('sub_institute_id', $this->tenantId)
                ->where('syear', $this->syear)
                ->whereRaw("UPPER(TRIM(COALESCE(completion_status, ''))) = 'Y'")
                ->whereNotNull('date')
                ->whereNotNull('submission_date')
                ->whereColumn('submission_date', '>=', 'date')
                ->selectRaw('DATEDIFF(submission_date, date) as lag')
                ->pluck('lag')
                ->map(static fn ($v) => (int) $v)
                ->sort()
                ->values();

            $count = $lags->count();

            if ($count < self::MIN_CLASS_COHORT) {
                // Too few submissions carry both dates to describe this
                // institute-year's turnaround rather than a handful of pieces
                // of work.
                return ['median' => null, 'sampled' => $count];
            }

            return ['median' => (float) $lags[(int) floor($count / 2)], 'sampled' => $count];
        })();
    }

    /**
     * Homework volume and submission rate, summed to one row per class
     * (standard) regardless of section — matching the grain
     * {@see TeachLearnIntelligence::byClass()} reports the catalogue at, so
     * the two halves of the breakdown line up on the same key.
     *
     * @return array<string,array{label:string,homeworkSet:int,students:int,submissionRate:?float}>
     */
    private function homeworkByStandard(): array
    {
        return $this->memo['hwByStandard'] ??= (function (): array {
            if ($this->syear === null || ! SchemaCache::hasTable(self::HOMEWORK_TABLE)) {
                return [];
            }

            $query = DB::table(self::HOMEWORK_TABLE.' as h')
                ->where('h.sub_institute_id', $this->tenantId)
                ->where('h.syear', $this->syear)
                ->groupBy('h.standard_id');

            if (SchemaCache::hasTable(self::STANDARD_TABLE)) {
                $query->leftJoin(self::STANDARD_TABLE.' as st', function ($join) {
                    $join->on('st.id', '=', 'h.standard_id')
                        ->on('st.sub_institute_id', '=', 'h.sub_institute_id');
                })
                    ->addSelect(DB::raw('NULLIF(TRIM(st.name), "") as standard_name'))
                    ->groupBy('st.name');
            } else {
                $query->addSelect(DB::raw('NULL as standard_name'));
            }

            $rows = $query->addSelect(
                'h.standard_id',
                DB::raw('COUNT(*) as set_count'),
                DB::raw('COUNT(DISTINCT h.student_id) as students'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(h.completion_status, ""))) = "Y" THEN 1 ELSE 0 END) as submitted')
            )->get();

            $out = [];
            foreach ($rows as $row) {
                $standardId = $row->standard_id === null || (int) $row->standard_id <= 0
                    ? '0'
                    : (string) $row->standard_id;
                $set = (int) $row->set_count;
                $submitted = (int) $row->submitted;

                $out[$standardId] = [
                    'label' => $row->standard_name !== null
                        ? (string) $row->standard_name
                        : ($standardId === '0' ? 'No class recorded' : "Standard #{$standardId}"),
                    'homeworkSet' => $set,
                    'students' => (int) $row->students,
                    'submissionRate' => $set >= self::MIN_CLASS_COHORT ? round($submitted / $set * 100, 1) : null,
                ];
            }

            return $out;
        })();
    }

    /**
     * This institute's course catalogue, one row per (class, subject) pair —
     * the SAME rows {@see TeachLearnIntelligence} counts as courses. Not
     * year-scoped, because `sub_std_map` carries no `syear`.
     *
     * @return array<int,array{standardId:string,subjectId:string,standardName:string}>
     */
    private function catalogueCoursePairs(): array
    {
        return $this->memo['coursePairs'] ??= (function (): array {
            if (! SchemaCache::hasTable(self::COURSE_TABLE)) {
                return [];
            }

            $query = DB::table(self::COURSE_TABLE.' as s')
                ->where('s.sub_institute_id', $this->tenantId)
                ->where('s.status', 1);

            if (SchemaCache::hasTable(self::STANDARD_TABLE)) {
                $query->leftJoin(self::STANDARD_TABLE.' as st', function ($join) {
                    $join->on('st.id', '=', 's.standard_id')
                        ->on('st.sub_institute_id', '=', 's.sub_institute_id');
                })->select(DB::raw('COALESCE(NULLIF(TRIM(st.name), ""), CONCAT("Class ", s.standard_id)) as standard_name'));
            } else {
                $query->select(DB::raw('CONCAT("Class ", s.standard_id) as standard_name'));
            }

            return $query->addSelect('s.standard_id', 's.subject_id')
                ->get()
                ->map(static fn ($row) => [
                    'standardId' => (string) $row->standard_id,
                    'subjectId' => (string) $row->subject_id,
                    'standardName' => (string) $row->standard_name,
                ])
                ->all();
        })();
    }

    /** Distinct (standard, subject) pairs carrying published content this year. @return array<string,bool> */
    private function contentPairSet(): array
    {
        return $this->memo['contentPairs'] ??= $this->pairSet(self::CONTENT_TABLE);
    }

    /** Distinct (standard, subject) pairs carrying homework this year. @return array<string,bool> */
    private function homeworkPairSet(): array
    {
        return $this->memo['homeworkPairs'] ??= $this->pairSet(self::HOMEWORK_TABLE);
    }

    /** @return array<string,bool> */
    private function pairSet(string $table): array
    {
        if ($this->syear === null || ! SchemaCache::hasTable($table)) {
            return [];
        }

        $rows = DB::table($table)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', $this->syear)
            ->select(DB::raw('DISTINCT standard_id, subject_id'))
            ->get();

        $set = [];
        foreach ($rows as $row) {
            $set[$row->standard_id.'-'.$row->subject_id] = true;
        }

        return $set;
    }

    /**
     * The join between the catalogue, published content and homework
     * activity, one pass over the institute's own course list.
     *
     * @return array{catalogueCourses:int,coursesWithContent:int,coursesWithContentAndActivity:int,
     *               coursesWithContentNoActivity:int,coursesWithActivity:int,activityOutsideCatalogue:int,
     *               byStandard:array<int,array<string,mixed>>}
     */
    private function crossShape(): array
    {
        return $this->memo['cross'] ??= $this->computeCrossShape();
    }

    /** @return array{catalogueCourses:int,coursesWithContent:int,coursesWithContentAndActivity:int,coursesWithContentNoActivity:int,coursesWithActivity:int,activityOutsideCatalogue:int,byStandard:array<int,array<string,mixed>>} */
    private function computeCrossShape(): array
    {
        $none = [
            'catalogueCourses' => 0,
            'coursesWithContent' => 0,
            'coursesWithContentAndActivity' => 0,
            'coursesWithContentNoActivity' => 0,
            'coursesWithActivity' => 0,
            'activityOutsideCatalogue' => 0,
            'byStandard' => [],
        ];

        $pairs = $this->catalogueCoursePairs();
        if ($pairs === [] || $this->syear === null) {
            return $none;
        }

        $contentSet = $this->contentPairSet();
        $homeworkSet = $this->homeworkPairSet();

        $byStandard = [];
        $withContent = 0;
        $withContentAndActivity = 0;
        $withActivity = 0;
        $cataloguePairKeys = [];

        foreach ($pairs as $pair) {
            $key = $pair['standardId'].'-'.$pair['subjectId'];
            $cataloguePairKeys[$key] = true;
            $hasContent = isset($contentSet[$key]);
            $hasActivity = isset($homeworkSet[$key]);

            $byStandard[$pair['standardId']] ??= [
                'key' => $pair['standardId'],
                'label' => $pair['standardName'],
                'courses' => 0,
                'withContent' => 0,
                'withContentAndActivity' => 0,
                'withContentNoActivity' => 0,
            ];

            $byStandard[$pair['standardId']]['courses']++;

            if ($hasContent) {
                $byStandard[$pair['standardId']]['withContent']++;
                $withContent++;

                if ($hasActivity) {
                    $byStandard[$pair['standardId']]['withContentAndActivity']++;
                    $withContentAndActivity++;
                } else {
                    $byStandard[$pair['standardId']]['withContentNoActivity']++;
                }
            }

            if ($hasActivity) {
                $withActivity++;
            }
        }

        // Homework pairs the catalogue itself does not offer — activity
        // outside the mapped curriculum, counted once here rather than by
        // class, since there is no catalogue course to attribute it to.
        $outsideCatalogue = 0;
        foreach (array_keys($homeworkSet) as $key) {
            if (! isset($cataloguePairKeys[$key])) {
                $outsideCatalogue++;
            }
        }

        return [
            'catalogueCourses' => count($pairs),
            'coursesWithContent' => $withContent,
            'coursesWithContentAndActivity' => $withContentAndActivity,
            'coursesWithContentNoActivity' => $withContent - $withContentAndActivity,
            'coursesWithActivity' => $withActivity,
            'activityOutsideCatalogue' => $outsideCatalogue,
            'byStandard' => array_values($byStandard),
        ];
    }
}
