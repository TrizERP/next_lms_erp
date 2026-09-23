<?php

namespace App\Brain\Intelligence;

/**
 * What the curriculum content catalogue means, and what is worth somebody's
 * morning.
 *
 * ── WHAT A RULE HERE MAY NOT DO ─────────────────────────────────────────────
 *
 * IT MAY NOT READ WHAT A FILE IS. `title`, `description`, `filename`, `url` and
 * `meta_tags` name real teaching material prepared by named staff. No rule below
 * groups by, quotes or counts their values. `file_type` — `pdf`, `mp4`, `link` —
 * is the only content column whose VALUE any rule touches.
 *
 * IT MAY NOT REPORT A CHAPTER FIGURE. `chapter_master` holds 446 rows in the
 * whole database against 31,192 content rows carrying a chapter id, so a
 * chapter-level count would be a count over a join that does not resolve. The
 * only rule that mentions chapters is the one reporting that they do not
 * resolve.
 *
 * IT MAY NOT CALL AN UNUSED MODULE A FAILING ONE. Every rule is gated on
 * `coverage()['available']`, which is false at an institute that has never
 * published content. A school that does not run the content library must not be
 * told its curriculum is 0% covered — that is the fabricated zero this whole
 * module is written to avoid, and it is the same mistake HR Intelligence had to
 * correct when it reported "0 leave days" at schools with no leave register.
 *
 * IT MAY NOT COMPARE A YEAR TO A YEAR THAT DOES NOT EXIST. The publishing-trend
 * rule fires only where the institute has a prior year holding content of its
 * own, so a school's first year on the platform is not reported as a collapse.
 */
final class TeachLearnSignalRules
{
    /**
     * Above this share of the catalogue carrying nothing, the gap describes the
     * curriculum rather than a few courses nobody has got to yet.
     */
    private const UNCOVERED_THRESHOLD = 25.0;

    /** Above this share in one format, the library depends on that format. */
    private const FORMAT_CONCENTRATION_THRESHOLD = 70.0;

    /** Below this share of the prior year's volume, publishing has stopped. */
    private const PUBLISHING_DROP_THRESHOLD = 0.4;

    /** Above this share hidden, prepared material is being withheld at scale. */
    private const HIDDEN_THRESHOLD = 5.0;

    public function __construct(
        private readonly TeachLearnIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        $shape = $this->analytics->shape();

        // Nothing published and no catalogue: the module is not in use, and an
        // unused module raises nothing rather than raising everything.
        if ($shape['items'] === 0 && $shape['courses'] === 0) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'curriculum_without_content' => [
                'Courses offered with nothing published against them',
                fn () => $this->curriculumWithoutContent(),
            ],
            'unresolved_chapter' => [
                'Content that cannot be placed in the curriculum structure',
                fn () => $this->unresolvedChapter(),
            ],
            'format_concentration' => [
                'A library resting on a single file format',
                fn () => $this->formatConcentration(),
            ],
            'publishing_stalled' => [
                'Publishing well below this institute’s own prior year',
                fn () => $this->publishingStalled(),
            ],
            'hidden_content' => [
                'Prepared material hidden from learners',
                fn () => $this->hiddenContent(),
            ],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => true, 'raised' => $raised !== []];

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

    /* ------------------------------------------------- the empty course */

    /**
     * Courses a learner can open and find nothing in.
     *
     * This is the most consequential thing this module can say, and it is the
     * one figure the Course Catalog screen itself cannot show: that screen
     * lists what exists, so a course with no content looks like every other
     * course until somebody opens it.
     *
     * @return array<int,array<string,mixed>>
     */
    private function curriculumWithoutContent(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return [];
        }

        $shape = $this->analytics->shape();
        $courses = $shape['courses'];
        $without = $shape['coursesWithoutContent'];

        if ($courses === 0 || $without === 0) {
            return [];
        }

        $share = round($without / $courses * 100, 1);

        if ($share < self::UNCOVERED_THRESHOLD) {
            return [];
        }

        return [[
            'id' => "teach-learn-uncovered-courses-{$this->syear}",
            'severity' => $share >= 75.0 ? 'high' : 'medium',
            'severityLabel' => $share >= 75.0 ? 'High' : 'Medium',
            'title' => "{$without} of {$courses} courses carry no teaching content for {$this->syear}",
            'whatHappened' => $this->sentence([
                "This institute's catalogue offers {$courses} courses — one subject taught to one class.",
                "{$shape['coursesWithContent']} of them hold published content for {$this->syear}; "
                    ."{$without} ({$share}%) hold none.",
                "{$shape['items']} items were published in total this year, so the material that exists is "
                    .'concentrated on part of the curriculum rather than spread across it.',
            ]),
            'whyItMatters' => 'A course in the catalogue is a promise to a class that material is there. Where '
                .'nothing is published against it, a learner opening it finds an empty shelf, and nobody teaching it '
                .'has anything to point at. The catalogue screen cannot show this — it lists the courses that exist, '
                .'and an empty course looks exactly like a full one until it is opened.',
            'evidence' => array_values(array_filter([
                ['label' => 'Courses in the catalogue', 'value' => (string) $courses,
                    'note' => 'sub_std_map carries no academic year, so this is the standing catalogue'],
                ['label' => 'Courses with content this year', 'value' => (string) $shape['coursesWithContent']],
                ['label' => 'Courses with none', 'value' => (string) $without],
                ['label' => 'Share of catalogue uncovered', 'value' => "{$share}%"],
                ['label' => 'Items published this year', 'value' => (string) $shape['items']],
                ['label' => 'Academic year', 'value' => (string) $this->syear],
            ])),
            'likelyCause' => 'Content is published by whoever teaches a subject, so coverage follows the staff who '
                .'have adopted the library rather than the shape of the curriculum. Nothing in the catalogue asks for '
                .'material before a course is offered.',
            'causeConfirmed' => false,
            'recommendation' => 'Take the uncovered courses by class from the breakdown below and decide which are '
                .'meant to carry material this year. Courses that are not meant to be taught from the library are '
                .'worth marking so the gap stops being counted against them.',
            'owner' => 'Academic head',
            'priority' => $share >= 75.0 ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $without, 'total' => $courses, 'unit' => 'courses'],
            'impact' => ['value' => $without, 'display' => (string) $without, 'label' => 'courses with nothing to open'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------- the broken chapter spine */

    /**
     * Content that cannot be placed in the curriculum structure.
     *
     * Reported as its own finding rather than folded into the coverage one,
     * because the fix is different: this one is repaired in the chapter master,
     * not by publishing more material.
     *
     * @return array<int,array<string,mixed>>
     */
    private function unresolvedChapter(): array
    {
        $shape = $this->analytics->shape();
        $orphan = $shape['orphanChapter'];
        $withChapter = $shape['withChapter'];

        if ($shape['items'] === 0 || $orphan === 0) {
            return [];
        }

        $share = $withChapter > 0 ? round($orphan / $withChapter * 100, 1) : null;
        $total = $share !== null && $share >= 90.0;

        return [[
            'id' => "teach-learn-unresolved-chapter-{$this->syear}",
            'severity' => $total ? 'high' : 'medium',
            'severityLabel' => $total ? 'High' : 'Medium',
            'title' => $total
                ? "Every one of {$orphan} items names a chapter this institute does not hold"
                : "{$orphan} of {$withChapter} items name a chapter this institute does not hold",
            'whatHappened' => $this->sentence([
                "{$withChapter} of this year's {$shape['items']} published items carry a chapter id.",
                $share !== null
                    ? "{$orphan} of them ({$share}%) name a chapter with no row in this institute's own chapter "
                        .'master.'
                    : "{$orphan} of them name a chapter with no row in this institute's own chapter master.",
                'The reference is checked tenant-scoped on both sides: a chapter id that resolves only against '
                    .'another institute’s master is unresolved here.',
            ]),
            'whyItMatters' => 'The chapter is what places a file inside a syllabus — it is how a teacher finds the '
                .'material for the week they are on, and how content is sequenced rather than listed. Where the '
                .'reference resolves to nothing, the item still opens but nothing can say where in the course it '
                .'belongs. This is also why this screen reports no chapter-level figures at all: grouping by an id '
                .'that answers to nothing would produce a chart of invented structure.',
            'evidence' => array_values(array_filter([
                ['label' => 'Items carrying a chapter id', 'value' => (string) $withChapter],
                ['label' => 'Chapter ids that do not resolve', 'value' => (string) $orphan],
                $share !== null
                    ? ['label' => 'Share unresolved', 'value' => "{$share}%"]
                    : null,
                ['label' => 'Items published this year', 'value' => (string) $shape['items']],
                ['label' => 'Checked against', 'value' => 'chapter_master, same sub_institute_id'],
                ['label' => 'Academic year', 'value' => (string) $this->syear],
            ])),
            'likelyCause' => 'Chapter rows appear to have been created in a shared or central catalogue and the '
                .'content filed against their ids, while this institute’s own chapter master was never populated. '
                .'That is a reference this deployment cannot resolve rather than a mistake made at the desk.',
            'causeConfirmed' => false,
            'recommendation' => 'Establish whether the chapters these items cite are meant to live in this '
                .'institute’s own chapter master. Until they do, treat the course as a flat list of material and do '
                .'not rely on any chapter ordering in reports built on this data.',
            'owner' => 'Academic head',
            'priority' => $total ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $orphan, 'total' => $withChapter, 'unit' => 'items'],
            'impact' => ['value' => $orphan, 'display' => (string) $orphan, 'label' => 'items with no place in the syllabus'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------ one format only */

    /** @return array<int,array<string,mixed>> */
    private function formatConcentration(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return [];
        }

        $shape = $this->analytics->shape();

        if ($shape['items'] === 0 || $shape['topFormat'] === null) {
            return [];
        }

        $share = round($shape['topFormatItems'] / $shape['items'] * 100, 1);

        if ($share < self::FORMAT_CONCENTRATION_THRESHOLD) {
            return [];
        }

        $format = $shape['topFormat'];

        return [[
            'id' => "teach-learn-format-concentration-{$this->syear}",
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => "{$share}% of this year's teaching content is {$format}",
            'whatHappened' => $this->sentence([
                "{$shape['topFormatItems']} of {$shape['items']} items published for {$this->syear} are {$format}.",
                "{$shape['formats']} distinct formats are in use across the year in total.",
            ]),
            'whyItMatters' => 'A library that is almost entirely one format is as accessible as that format is. '
                .($format === 'pdf'
                    ? 'A document is read the same way by every learner, which makes it the easiest thing to '
                        .'publish and the hardest to learn from where reading is the barrier.'
                    : 'Where one format dominates, any learner who cannot use it is shut out of most of the '
                        .'material at once.')
                .' This is a description of the library’s shape, not a defect — it is here so the balance is a '
                .'decision rather than an accident.',
            'evidence' => [
                ['label' => "Items in {$format}", 'value' => (string) $shape['topFormatItems']],
                ['label' => 'Items published this year', 'value' => (string) $shape['items']],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'Distinct formats in use', 'value' => (string) $shape['formats']],
                ['label' => 'Academic year', 'value' => (string) $this->syear],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Decide whether the balance is intended. Where it is not, the courses with the most '
                .'material are the cheapest place to add a second format, because their structure already exists.',
            'owner' => 'Academic head',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $shape['topFormatItems'], 'total' => $shape['items'], 'unit' => 'items'],
            'impact' => ['value' => null, 'display' => "{$share}%", 'label' => 'of the library in one format'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------- publishing fell away */

    /**
     * This year's publishing against the institute's OWN prior year.
     *
     * Never against another institute and never against an absolute figure: a
     * small school publishing forty items a year is not behind a large one
     * publishing four hundred.
     *
     * @return array<int,array<string,mixed>>
     */
    private function publishingStalled(): array
    {
        $shape = $this->analytics->shape();
        $prior = $shape['priorYearItems'];
        $current = $shape['items'];

        // No prior year to compare against — a first year on the platform is
        // not a collapse, and saying so would be inventing a trend.
        if ($prior < TeachLearnIntelligence::MIN_ITEMS) {
            return [];
        }

        if ($current >= $prior * self::PUBLISHING_DROP_THRESHOLD) {
            return [];
        }

        $priorYear = (string) (((int) $this->syear) - 1);
        $drop = round(($prior - $current) / $prior * 100, 1);
        $stopped = $current === 0;

        return [[
            'id' => "teach-learn-publishing-stalled-{$this->syear}",
            'severity' => $stopped ? 'high' : 'medium',
            'severityLabel' => $stopped ? 'High' : 'Medium',
            'title' => $stopped
                ? "Nothing was published for {$this->syear}, against {$prior} items in {$priorYear}"
                : "{$current} items published for {$this->syear}, down {$drop}% from {$priorYear}",
            'whatHappened' => $this->sentence([
                "This institute published {$prior} items for {$priorYear}.",
                $stopped
                    ? "It has published none at all for {$this->syear}."
                    : "It has published {$current} for {$this->syear} — a fall of {$drop}%.",
                'Both figures are this institute’s own rows, counted the same way, so this is a change in what is '
                    .'being added rather than in what is being measured.',
            ]),
            'whyItMatters' => $stopped
                ? 'A library that stopped being added to still opens, which is what makes this easy to miss: every '
                    .'course a learner opens shows last year’s material with nothing marking it as last year’s.'
                : 'The material a class works from this year is the material published for this year. A fall this '
                    .'size means most courses are being taught from content prepared for a different cohort.',
            'evidence' => [
                ['label' => "Items published in {$priorYear}", 'value' => (string) $prior],
                ['label' => "Items published in {$this->syear}", 'value' => (string) $current],
                ['label' => 'Change', 'value' => "−{$drop}%"],
                ['label' => 'Items on file across all years', 'value' => (string) $shape['itemsAllYears']],
                ['label' => 'Compared against', 'value' => 'this institute’s own prior year, not another school'],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Establish whether this year’s teaching is meant to run on last year’s material. '
                .'Where it is, rolling the content forward into this year makes that explicit; where it is not, the '
                .'courses carrying the most of last year’s material are where the gap will be felt first.',
            'owner' => 'Academic head',
            'priority' => $stopped ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $prior - $current, 'total' => $prior, 'unit' => 'items'],
            'impact' => ['value' => $prior - $current, 'display' => (string) ($prior - $current), 'label' => 'fewer items than last year'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ---------------------------------------------------------- hidden work */

    /** @return array<int,array<string,mixed>> */
    private function hiddenContent(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return [];
        }

        $shape = $this->analytics->shape();
        $hidden = $shape['hidden'];

        if ($shape['items'] === 0 || $hidden === 0) {
            return [];
        }

        $share = round($hidden / $shape['items'] * 100, 1);

        if ($share < self::HIDDEN_THRESHOLD) {
            return [];
        }

        return [[
            'id' => "teach-learn-hidden-content-{$this->syear}",
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => "{$hidden} of {$shape['items']} published items are hidden from learners",
            'whatHappened' => $this->sentence([
                "{$hidden} items published for {$this->syear} ({$share}%) carry their visibility switched off.",
                "{$shape['shown']} are visible.",
            ]),
            'whyItMatters' => 'Hiding an item is a normal way to stage material before a class reaches it. At this '
                .'share it is worth checking which of them are staged and which were hidden once and never turned '
                .'back on — the preparation is already paid for, and nobody can reach the result.',
            'evidence' => [
                ['label' => 'Items hidden', 'value' => (string) $hidden],
                ['label' => 'Items visible', 'value' => (string) $shape['shown']],
                ['label' => 'Items published this year', 'value' => (string) $shape['items']],
                ['label' => 'Share hidden', 'value' => "{$share}%"],
                ['label' => 'Academic year', 'value' => (string) $this->syear],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Review the hidden items against the courses they belong to. Anything hidden from a '
                .'course the class has already passed is finished work nobody received.',
            'owner' => 'Academic head',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $hidden, 'total' => $shape['items'], 'unit' => 'items'],
            'impact' => ['value' => $hidden, 'display' => (string) $hidden, 'label' => 'prepared items nobody can open'],
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
