<?php

namespace App\Services\PAL\Content;

use App\Services\Eso\EsoConceptVideoResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every learning resource a student may see for one concept, grouped by kind.
 *
 * -----------------------------------------------------------------------------
 * WHY THIS EXISTS, GIVEN HOW MUCH CONTENT MACHINERY ALREADY DOES
 * -----------------------------------------------------------------------------
 * The Learn stage used to serve exactly ONE item, because every resolver behind
 * it is single-result by construction: EsoLearningContentResolver::forTeach()
 * returns `?array`, and so does EsoConceptVideoResolver::forConcept(). Neither
 * is a bug - the ESO engine wants one thing to teach - but it meant 104 approved
 * videos on chapter 8677 reached learners as 37 (one per concept), and the
 * chapter's presentations and notes were not reachable from Learn at all.
 *
 * Three existing services were considered and rejected as the aggregator:
 *
 *   - VariantRouterService DOES return `content` + `alternatives`, but it reads
 *     pal_content_metadata, which holds 124 rows covering 4 concepts on chapters
 *     8594/8595/8596 - none of the PAL chapters - and has content_type NULL on
 *     all 124, which its own query filters on. It returns nothing here.
 *   - PedagogySuggestedContentService is chapter-keyed and buckets by PEDAGOGY
 *     (remediation/practice/enrichment), not by resource kind, and it filters on
 *     content_master.syear, which is the UPLOAD year - chapter 1014's rows are
 *     all syear 2021 against a chapter syear of 2026, so that filter drops them.
 *   - EsoEnrichmentResolver converts the concept to a chapter, discards the
 *     concept, and caps at 3 items.
 *
 * So this composes rather than re-implements: videos come from
 * EsoConceptVideoResolver::allForConcept() (which already existed and already
 * returns every approved video), and everything else is one grouped read of
 * content_master.
 *
 * -----------------------------------------------------------------------------
 * THE SCOPE PROBLEM, WHICH THE PAYLOAD MUST NOT HIDE
 * -----------------------------------------------------------------------------
 * content_master.concept_id is populated on 1 row out of 31,331. topic_id is
 * populated on 17,579 rows, but for the PAL chapters the concept topic ids and
 * the content topic ids do not intersect AT ALL (measured: 0 overlap on 8677,
 * 8592 and 1014). So content_master can only be reached by chapter.
 *
 * That means the same handful of chapter rows is offered under every concept in
 * the chapter, and saying otherwise would be a lie. Every item therefore carries
 * `scope`: 'concept' when it is genuinely tagged to this concept, 'chapter' when
 * it belongs to the chapter as a whole. The UI is expected to say which.
 *
 * Read-only. Stamps nothing, logs nothing, creates no learner rows.
 */
class ConceptLearningResourceService
{
    public function __construct(
        protected EsoConceptVideoResolver $videos = new EsoConceptVideoResolver(),
    ) {
    }

    /**
     * @return array{
     *     sections: array<int, array{key:string,label:string,count:int,scope:string,items:array}>,
     *     totals: array{items:int, concept_scoped:int, chapter_scoped:int},
     *     excluded: array{teacher_material:int, unresolvable:int},
     *     notes: array<int, string>
     * }
     */
    public function forConcept(int $conceptId, ?int $chapterId, ?int $subInstituteId): array
    {
        $buckets = [];
        foreach (array_keys($this->sectionConfig()) as $key) {
            $buckets[$key] = [];
        }

        // ── Concept-accurate: approved videos ────────────────────────────────
        // allForConcept() already applies servable() + tenant scoping and
        // returns EVERY approved video, newest curation first.
        foreach ($this->videos->allForConcept($conceptId, $subInstituteId) as $video) {
            $buckets['video'][] = $this->shapeVideo($video, $conceptId);
        }

        // ── Chapter-level: content_master ────────────────────────────────────
        $excludedTeacher = 0;
        $unresolvable = 0;
        $unclassified = 0;

        foreach ($this->chapterRows($chapterId, $subInstituteId) as $row) {
            $category = $this->normaliseCategory($row->content_category);

            if ($this->isTeacherOnly($category)) {
                $excludedTeacher++;
                continue;
            }

            $section = $this->sectionFor($category);

            if ($section === null) {
                // ─────────────────────────────────────────────────────────────
                // UNCLASSIFIED — not shown to a student.
                // ─────────────────────────────────────────────────────────────
                // content_category is the ONLY audience signal this table has,
                // so a row without a recognised one has nothing vouching that a
                // learner should see it. Filing those under "More material"
                // leaked real teacher documents: the 781 NULL-category rows are
                // titled "lesson plan", "Innovative Pedagogy", "focus point",
                // "Activity KIT", "reading material" — and on chapter 8592 two
                // of them are literally titled "Teacher Training".
                //
                // Counted and reported, never silently dropped, so a genuinely
                // new student category shows up as a number to investigate
                // rather than vanishing.
                $unclassified++;
                continue;
            }

            $url = $this->resolveUrl($row);

            if ($url === null) {
                // 23 rows estate-wide have neither an absolute filename nor a
                // reconstructable path. A dead link is worse than an absent one.
                $unresolvable++;
                continue;
            }

            $buckets[$section][] = $this->shapeContent($row, $url, $category, $section);
        }

        // ── H5P ──────────────────────────────────────────────────────────────
        $h5p = $this->h5pForConcept($conceptId, $subInstituteId);
        if ($h5p !== []) {
            $buckets['h5p'] = $h5p;
        }

        return $this->assemble($buckets, $excludedTeacher, $unresolvable, $unclassified);
    }

    /**
     * H5P tagged to this concept.
     *
     * pal_h5p_node_metadata.concept_ref_id is the bridge the schema was designed
     * around, and it is the ONLY concept link H5P has: h5p_scenarios,
     * h5p_interactive_video and h5p_flashcard have no concept or topic column at
     * all, so a concept link is not even representable there.
     *
     * The table has 0 rows today, so this returns [] and the section does not
     * render. That is deliberate and it is the whole design:
     *
     *   - There is no H5P runtime in this repo. No h5p_libraries, no
     *     h5p_contents, no composer package, no player, no .h5p handling.
     *   - Of 21 registered types in pal_vocabulary, 4 are 'native' and 17 are
     *     'planned' with no source_table. interactive_video and flash_cards have
     *     0 rows; branching_scenario has no table.
     *   - So switching on h5p_type strings - which config/pal_content_model.php
     *     does supply - would build branches that can never have data. Both
     *     config/pal_content.php:487 ("ASPIRATIONAL") and
     *     VariantRouterService.php:23 ("gating on H5P would serve nothing")
     *     already warn about exactly this.
     *
     * Keyed on data, not on a type whitelist, so the section appears by itself
     * the moment content is genuinely tagged - and stays honest until then.
     */
    protected function h5pForConcept(int $conceptId, ?int $subInstituteId): array
    {
        if (! Schema::hasTable('pal_h5p_node_metadata')) {
            return [];
        }

        try {
            $rows = DB::table('pal_h5p_node_metadata')
                ->where('concept_ref_id', $conceptId)
                ->where('quality_status', 'approved')
                ->when($subInstituteId !== null, fn ($q) => $q->whereIn('sub_institute_id', [$subInstituteId, 0]))
                ->orderBy('id')
                ->get();
        } catch (\Throwable) {
            // Never fail a lesson over an optional format.
            return [];
        }

        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'id' => 'h5p:' . $row->id,
                'source_table' => 'pal_h5p_node_metadata',
                'section' => 'h5p',
                'scope' => 'concept',
                'title' => $this->trimOrNull($row->title ?? null) ?? 'Interactive activity',
                'description' => $this->trimOrNull($row->ai_rationale ?? null),
                'url' => null,
                'file_type' => 'h5p',
                'category' => 'H5P interactive',
                'h5p_type' => $this->trimOrNull($row->h5p_type ?? null),
                'provider' => null,
                'attribution' => null,
                'thumbnail_url' => null,
                'duration_seconds' => isset($row->estimated_duration_minutes) && $row->estimated_duration_minutes !== null
                    ? ((int) $row->estimated_duration_minutes) * 60
                    : null,
                'tags' => array_filter([
                    'difficulty' => $this->trimOrNull(isset($row->difficulty_1_to_5) ? (string) $row->difficulty_1_to_5 : null),
                    'bloom_level' => $this->trimOrNull($row->bloom_level ?? null),
                    'pedagogy_tag' => $this->trimOrNull($row->pedagogy_tag ?? null),
                    'concept_id' => $conceptId,
                ], fn ($v) => $v !== null),
            ];
        }

        return $items;
    }

    /**
     * The chapter's visible content rows.
     *
     * Deliberately NOT filtered on:
     *   - syear: it is the UPLOAD year, not the academic year. Chapter 1014 is
     *     syear 2026 and every one of its 19 content rows is syear 2021, so this
     *     filter would empty the screen. PedagogySuggestedContentService applies
     *     it and loses those rows.
     *   - topic_id: concept topic ids and content topic ids have zero overlap on
     *     every PAL chapter, so narrowing by topic returns nothing rather than
     *     something more precise.
     *   - concept_id: populated on 1 row out of 31,331.
     */
    protected function chapterRows(?int $chapterId, ?int $subInstituteId)
    {
        if ($chapterId === null || $chapterId <= 0 || ! Schema::hasTable('content_master')) {
            return collect();
        }

        try {
            return DB::table('content_master')
                ->where('chapter_id', $chapterId)
                ->where('show_hide', 1)
                // No sub_institute_id = 0 rows exist on this table, so a
                // shared-pool clause would be dead code; tenant rows only.
                ->when($subInstituteId !== null && $subInstituteId > 0,
                    fn ($q) => $q->where('sub_institute_id', $subInstituteId))
                ->orderByRaw('COALESCE(sort_order, 999999) ASC')
                ->orderBy('id')
                ->get([
                    'id', 'title', 'description', 'file_folder', 'filename', 'file_type',
                    'url', 'content_category', 'meta_tags', 'basic_advance', 'topic_id',
                    'chapter_id', 'subject_id', 'standard_id', 'sort_order', 'file_size',
                ]);
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * The estate's own URL rule, from lms_apiController.php:444.
     *
     *   file_type = 'link'  -> `filename` already holds an absolute URL
     *   otherwise           -> {public_base}public{file_folder}/{filename}
     *
     * Verified live on chapter 8677: the two absolute cases and one
     * reconstruction all return HTTP 200.
     *
     * `url` is tried only as a last resort because it is unreliable - populated
     * on 450 rows of 31,331, and on some of those it holds nothing but the
     * bucket root, which would render as a broken link.
     */
    protected function resolveUrl(object $row): ?string
    {
        $filename = trim((string) ($row->filename ?? ''));

        if ($this->isAbsolute($filename)) {
            return $filename;
        }

        $url = trim((string) ($row->url ?? ''));

        if ($filename !== '') {
            $base = rtrim((string) config('pal_content.public_base', ''), '/');
            $folder = trim((string) ($row->file_folder ?? ''));

            if ($base !== '') {
                $folder = $folder === '' ? '' : '/' . trim($folder, '/');

                return $base . '/public' . $folder . '/' . ltrim($filename, '/');
            }
        }

        // Only an absolute url that actually points at a file, never the bucket
        // root on its own.
        if ($this->isAbsolute($url) && pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) !== '') {
            return $url;
        }

        return null;
    }

    protected function isAbsolute(string $value): bool
    {
        return $value !== ''
            && preg_match('#^https?://#i', $value) === 1
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function shapeVideo(array $video, int $conceptId): array
    {
        return [
            'id' => 'video:' . ($video['video_id'] ?? 0),
            'source_table' => 'pal_concept_video',
            'section' => 'video',
            // The one source in this payload that is genuinely per-concept.
            'scope' => 'concept',
            'title' => $video['title'] ?? null,
            'description' => $video['body'] ?? null,
            'url' => $video['media_url'] ?? null,
            'file_type' => 'video',
            'category' => $video['format_label'] ?? 'Video',
            'h5p_type' => null,
            'provider' => $video['provider'] ?? null,
            'attribution' => $video['attribution'] ?? null,
            'thumbnail_url' => $video['thumbnail_url'] ?? null,
            // duration_seconds is NULL on all 127 rows today; carried so the UI
            // shows a runtime the moment the column is populated.
            'duration_seconds' => null,
            'tags' => array_filter([
                'concept_id' => $conceptId,
                'match_score' => $video['match_score'] ?? null,
                'source' => $video['source'] ?? null,
            ], fn ($v) => $v !== null),
        ];
    }

    protected function shapeContent(object $row, string $url, string $category, string $section): array
    {
        return [
            'id' => 'cm:' . $row->id,
            'source_table' => 'content_master',
            'section' => $section,
            // Chapter-wide, and labelled so. content_master cannot be reached by
            // concept on this estate - see the class docblock.
            'scope' => 'chapter',
            'title' => $this->trimOrNull($row->title) ?? $this->titleFromCategory($row->content_category),
            'description' => $this->trimOrNull($row->description),
            'url' => $url,
            'file_type' => $this->trimOrNull($row->file_type),
            'category' => $this->trimOrNull($row->content_category) ?? 'Content',
            'h5p_type' => null,
            'provider' => null,
            'attribution' => null,
            'thumbnail_url' => null,
            'duration_seconds' => null,
            'tags' => array_filter([
                'chapter_id' => (int) $row->chapter_id,
                // Carried because the curriculum position is part of the tagging
                // the payload is expected to expose. Not rendered as chips - a
                // raw id tells a student nothing, and the subject is already
                // implied by how they navigated here.
                'subject_id' => ((int) ($row->subject_id ?? 0)) ?: null,
                'standard_id' => ((int) ($row->standard_id ?? 0)) ?: null,
                // Populated on 17,579 rows estate-wide but 0 for every PAL
                // chapter, which is why it cannot be used to narrow scope.
                'topic_id' => ((int) ($row->topic_id ?? 0)) ?: null,
                // The only difficulty signal content_master carries, and it is
                // binary. Five rows hold a filename in this column instead of a
                // flag, so anything that is not 0/1 is dropped rather than shown.
                'difficulty' => $this->basicAdvance($row->basic_advance ?? null),
                'meta_tags' => $this->metaTags($row->meta_tags ?? null),
                'file_size' => $this->trimOrNull($row->file_size ?? null),
            ], fn ($v) => $v !== null && $v !== []),
        ];
    }

    /**
     * `basic_advance` is 1 = advance, 0 = basic on 9,460 rows. Five rows contain
     * a PDF filename, which is corruption, not a third level - those return null.
     */
    protected function basicAdvance($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ((string) $value) {
            '1' => 'advance',
            '0' => 'basic',
            default => null,
        };
    }

    /** @return array<int, string> */
    protected function metaTags($value): array
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return [];
        }

        // Stored as a comma-separated string on this estate.
        $tags = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($t) => $t !== ''));

        return array_slice(array_values(array_unique($tags)), 0, 8);
    }

    protected function normaliseCategory(?string $category): string
    {
        return mb_strtolower(trim((string) $category));
    }

    protected function isTeacherOnly(string $normalisedCategory): bool
    {
        $teacher = array_map(
            fn ($c) => mb_strtolower((string) $c),
            (array) config('pal_content.learn_teacher_only_categories', [])
        );

        return in_array($normalisedCategory, $teacher, true);
    }

    /**
     * Which section a category belongs in, or null when it is not on the list.
     *
     * Null means "not cleared for a student", not "put it at the end". The
     * config block is a CLOSED set for exactly the reason config/lms_content.php
     * states for its own vocabularies: an unregistered value is a failure, not a
     * new category. Defaulting to a visible bucket is how teacher material got
     * in front of learners.
     */
    protected function sectionFor(string $normalisedCategory): ?string
    {
        if ($normalisedCategory === '') {
            return null;
        }

        foreach ($this->sectionConfig() as $key => $section) {
            if (in_array($normalisedCategory, array_map('mb_strtolower', (array) ($section['categories'] ?? [])), true)) {
                return $key;
            }
        }

        return null;
    }

    /** @return array<string, array{label:string, categories:array}> */
    protected function sectionConfig(): array
    {
        $configured = (array) config('pal_content.learn_sections', []);

        // H5P has no content_category - it is its own source - so it is appended
        // here rather than configured as a category bucket. Kept last but ahead
        // of `other`, matching the requested Video -> ... -> H5P -> Other order.
        $other = $configured['other'] ?? ['label' => 'More material', 'categories' => []];
        unset($configured['other']);

        $configured['h5p'] = ['label' => 'Interactive activity', 'categories' => []];
        $configured['other'] = $other;

        return $configured;
    }

    protected function titleFromCategory(?string $category): string
    {
        $category = $this->trimOrNull($category);

        return $category ?? 'Learning material';
    }

    protected function trimOrNull($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, array> $buckets */
    protected function assemble(array $buckets, int $excludedTeacher, int $unresolvable, int $unclassified): array
    {
        $sections = [];
        $total = 0;
        $conceptScoped = 0;
        $chapterScoped = 0;

        foreach ($this->sectionConfig() as $key => $meta) {
            $items = $buckets[$key] ?? [];

            if ($items === []) {
                // An empty section is not rendered. The screen shows what exists
                // and says nothing about what does not.
                continue;
            }

            $scopes = array_unique(array_column($items, 'scope'));

            $sections[] = [
                'key' => $key,
                'label' => $meta['label'],
                'count' => count($items),
                // 'concept' | 'chapter' | 'mixed' — so a header can be honest in
                // one word about what the learner is looking at.
                'scope' => count($scopes) === 1 ? reset($scopes) : 'mixed',
                'items' => $items,
            ];

            $total += count($items);

            foreach ($items as $item) {
                $item['scope'] === 'concept' ? $conceptScoped++ : $chapterScoped++;
            }
        }

        $notes = [];

        if ($conceptScoped === 0 && $chapterScoped > 0) {
            $notes[] = 'chapter_level_only';
        }

        if ($total === 0) {
            $notes[] = 'nothing_authored';
        }

        return [
            'sections' => $sections,
            'totals' => [
                'items' => $total,
                'concept_scoped' => $conceptScoped,
                'chapter_scoped' => $chapterScoped,
            ],
            // Reported rather than silently dropped, so a content gap is
            // diagnosable from the payload instead of looking like a bug.
            'excluded' => [
                'teacher_material' => $excludedTeacher,
                'unresolvable' => $unresolvable,
                // Rows with no recognised content_category. Overwhelmingly
                // teacher planning documents on this estate; a non-zero count
                // here is worth a look rather than a shrug.
                'unclassified' => $unclassified,
            ],
            'notes' => $notes,
        ];
    }
}
