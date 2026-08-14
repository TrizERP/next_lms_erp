<?php

namespace App\Services\PAL\ContentModel;

use Illuminate\Support\Facades\DB;

/**
 * The one place `semantic_intelligence` is read.
 *
 * Everything the Content Model shows comes through here. Nothing else in the
 * module touches the table, so the two schema quirks the extractor shipped with
 * — the misspelt `full_intelegance_json` / `qulity_flag` columns, and the fact
 * that the per-concept slices are ALSO embedded in the blob — are absorbed once
 * instead of at every call site.
 *
 * Two load paths, deliberately:
 *   - listChapters() / chapterSummary() read the cheap scalar columns only.
 *     They never touch the blob, which is ~430 KB per row.
 *   - conceptsFor() decodes the blob once per chapter and hands back a
 *     per-concept structure with every slice already attached.
 */
class SemanticSourceRepository
{
    /** Per-request memo, keyed by semantic_intelligence.id. */
    protected array $conceptCache = [];

    /** Column name resolution is per-connection, so it is worth memoising. */
    protected static ?array $columnCache = null;

    // ── Column resolution ────────────────────────────────────────────────────

    protected function columns(): array
    {
        if (self::$columnCache !== null) {
            return self::$columnCache;
        }

        try {
            $cols = DB::getSchemaBuilder()->getColumnListing($this->table());
        } catch (\Throwable) {
            $cols = [];
        }

        return self::$columnCache = array_flip($cols);
    }

    protected function table(): string
    {
        return config('pal_content_model.source.table', 'semantic_intelligence');
    }

    /** First column from $candidates that exists on the table, or null. */
    protected function pick(array $candidates): ?string
    {
        $existing = $this->columns();
        foreach ($candidates as $candidate) {
            if (isset($existing[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    protected function blobColumn(): ?string
    {
        return $this->pick(config('pal_content_model.source.blob_columns', []));
    }

    protected function sliceColumns(): array
    {
        $existing = $this->columns();

        return array_values(array_filter(
            config('pal_content_model.source.slice_columns', []),
            fn ($c) => isset($existing[$c])
        ));
    }

    /** Test seam — drops the memoised column listing. */
    public static function flushColumnCache(): void
    {
        self::$columnCache = null;
    }

    // ── Chapter listing ──────────────────────────────────────────────────────

    /**
     * Every extracted chapter this caller may see, cheapest columns only.
     *
     * @param  int|null  $tenant  null = unrestricted (super admin only)
     * @return array<int,array<string,mixed>>
     */
    public function listChapters(?int $tenant, array $filters = []): array
    {
        $existing = $this->columns();
        $qualityCol = $this->pick(config('pal_content_model.source.quality_columns', []));
        $countCol = $this->pick(config('pal_content_model.source.concept_count_columns', []));
        $chapterJoin = config('pal_content_model.source.chapter_join');

        $select = [
            's.id', 's.extraction_id', 's.chapter_id', 's.subject_id', 's.standard_id',
            's.subject_name', 's.standard', 's.chapter_number', 's.llm_model',
            's.created_at', 's.updated_at',
        ];
        if (isset($existing['sub_institute_id'])) {
            $select[] = 's.sub_institute_id';
        }
        if ($countCol !== null) {
            $select[] = "s.{$countCol} as total_concepts";
        }
        if ($qualityCol !== null) {
            $select[] = "s.{$qualityCol} as quality_flag";
        }

        $query = DB::table($this->table() . ' as s')->select($select);

        // chapter_master carries the human-readable chapter name; the join is
        // left so a chapter deleted from the master still lists its extraction.
        $hasChapterMaster = false;
        try {
            $hasChapterMaster = DB::getSchemaBuilder()->hasTable($chapterJoin['table']);
        } catch (\Throwable) {
            // Fall through — the projection works without the name.
        }
        if ($hasChapterMaster) {
            $query->leftJoin($chapterJoin['table'] . ' as cm', 's.chapter_id', '=', 'cm.' . $chapterJoin['key'])
                ->addSelect('cm.' . $chapterJoin['name_column'] . ' as chapter_name');
        }

        if ($tenant !== null && isset($existing['sub_institute_id'])) {
            $query->where('s.sub_institute_id', $tenant);
        }

        if (! empty($filters['id'])) {
            $query->where('s.id', (int) $filters['id']);
        }
        if (! empty($filters['subject'])) {
            $query->where('s.subject_name', $filters['subject']);
        }
        if (! empty($filters['standard'])) {
            $query->where('s.standard', (int) $filters['standard']);
        }
        if (! empty($filters['chapter_id'])) {
            $query->where('s.chapter_id', (int) $filters['chapter_id']);
        }
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term, $hasChapterMaster, $chapterJoin) {
                $q->where('s.subject_name', 'like', $term);
                if ($hasChapterMaster) {
                    $q->orWhere('cm.' . $chapterJoin['name_column'], 'like', $term);
                }
            });
        }

        return $query->orderByDesc('s.id')->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * One chapter's scalar header, or null.
     *
     * Queried directly rather than filtered out of listChapters(): this is on
     * the hot path — every node read re-projects its chapter — and scanning the
     * whole estate to find one row would make that cost grow with the estate.
     */
    public function chapterHeader(int $semanticId, ?int $tenant): ?array
    {
        $rows = $this->listChapters($tenant, ['id' => $semanticId]);

        return $rows[0] ?? null;
    }

    /**
     * Filter options for the workspace.
     *
     * Sourced from the INSTITUTE'S OWN MASTERS, not from what happens to have
     * been extracted: a school that runs Grades 1-12 sees all twelve, so the
     * grades with no extracted content yet are visible as gaps rather than
     * silently missing from the filter. Extraction coverage is a property of
     * the data, not of the school, and the two should not be conflated.
     *
     * Subjects are keyed by standard (from the curriculum estate in
     * chapter_master) so the subject row can narrow to the selected grade —
     * the institute-wide subject table holds 80+ rows, most of them library
     * and skill categories that were never taught as a chaptered subject.
     *
     * Anything present in semantic_intelligence but absent from the masters is
     * unioned in, so a filter can never hide a chapter that actually exists.
     */
    public function facets(?int $tenant): array
    {
        $extracted = $this->extractedFacets($tenant);

        $standards = $this->instituteStandards($tenant);
        [$subjectsByStandard, $subjects] = $this->curriculumSubjects($tenant);

        // Union in whatever the extraction holds, so nothing becomes unfilterable.
        foreach ($extracted['pairs'] as $pair) {
            $standard = (int) ($pair['standard'] ?? 0);
            $subject = trim((string) ($pair['subject_name'] ?? ''));

            if ($standard > 0 && ! in_array($standard, $standards, true)) {
                $standards[] = $standard;
            }
            if ($subject === '') {
                continue;
            }
            if (! in_array($subject, $subjects, true)) {
                $subjects[] = $subject;
            }
            if ($standard > 0) {
                $subjectsByStandard[$standard] = $subjectsByStandard[$standard] ?? [];
                if (! in_array($subject, $subjectsByStandard[$standard], true)) {
                    $subjectsByStandard[$standard][] = $subject;
                }
            }
        }

        sort($standards);
        sort($subjects);
        ksort($subjectsByStandard);
        foreach ($subjectsByStandard as $key => $list) {
            sort($list);
            $subjectsByStandard[$key] = $list;
        }

        return [
            'standards' => array_values($standards),
            'subjects' => array_values($subjects),
            'subjects_by_standard' => $subjectsByStandard,
            // Which grades / subjects actually have extracted chapters behind
            // them. The UI can offer every grade while still being honest that
            // some have nothing to show.
            'extracted_standards' => $extracted['standards'],
            'extracted_subjects' => $extracted['subjects'],
            'pairs' => $extracted['pairs'],
        ];
    }

    /**
     * Every grade the institute runs.
     *
     * Non-numeric standards (Nursery, LKG …) are excluded: semantic_intelligence
     * stores `standard` as an integer, so a non-numeric grade could never match
     * a chapter and offering it would be a filter that always returns nothing.
     *
     * @return array<int,int>
     */
    protected function instituteStandards(?int $tenant): array
    {
        $standards = [];

        try {
            $rows = DB::table('standard')
                ->when($tenant !== null, fn ($q) => $q->where('sub_institute_id', $tenant))
                ->orderBy('sort_order')
                ->get(['name']);

            foreach ($rows as $row) {
                $name = trim((string) $row->name);
                if ($name === '' || ! ctype_digit($name)) {
                    continue;
                }
                $value = (int) $name;
                if ($value > 0 && ! in_array($value, $standards, true)) {
                    $standards[] = $value;
                }
            }
        } catch (Throwable) {
            // No standard master on this estate — the extracted values, unioned
            // in by the caller, are then the only option list.
        }

        return $standards;
    }

    /**
     * Subjects that actually carry chapters, grouped by standard.
     *
     * @return array{0: array<int,array<int,string>>, 1: array<int,string>}
     */
    protected function curriculumSubjects(?int $tenant): array
    {
        $byStandard = [];
        $all = [];

        try {
            $rows = DB::table('chapter_master as c')
                ->join('subject as s', 'c.subject_id', '=', 's.id')
                ->leftJoin('standard as st', 'c.standard_id', '=', 'st.id')
                ->when($tenant !== null, fn ($q) => $q->where('c.sub_institute_id', $tenant))
                ->select('s.subject_name', 'st.name as standard_name')
                ->distinct()
                ->get();

            foreach ($rows as $row) {
                $subject = trim((string) $row->subject_name);
                if ($subject === '') {
                    continue;
                }
                if (! in_array($subject, $all, true)) {
                    $all[] = $subject;
                }

                $standardName = trim((string) $row->standard_name);
                if ($standardName === '' || ! ctype_digit($standardName)) {
                    continue;
                }
                $standard = (int) $standardName;
                $byStandard[$standard] = $byStandard[$standard] ?? [];
                if (! in_array($subject, $byStandard[$standard], true)) {
                    $byStandard[$standard][] = $subject;
                }
            }
        } catch (Throwable) {
            // No curriculum estate — fall back to the extracted subjects only.
        }

        return [$byStandard, $all];
    }

    /** Distinct subject / standard pairs actually present in the extraction. */
    protected function extractedFacets(?int $tenant): array
    {
        $existing = $this->columns();

        $query = DB::table($this->table())
            ->select('subject_name', 'standard', DB::raw('COUNT(*) as chapters'))
            ->groupBy('subject_name', 'standard')
            ->orderBy('subject_name');

        if ($tenant !== null && isset($existing['sub_institute_id'])) {
            $query->where('sub_institute_id', $tenant);
        }

        $rows = $query->get();

        $subjects = [];
        $standards = [];
        foreach ($rows as $row) {
            $subject = trim((string) $row->subject_name);
            if ($subject !== '' && ! in_array($subject, $subjects, true)) {
                $subjects[] = $subject;
            }
            $standard = (int) $row->standard;
            if ($standard > 0 && ! in_array($standard, $standards, true)) {
                $standards[] = $standard;
            }
        }
        sort($standards);

        return [
            'subjects' => $subjects,
            'standards' => $standards,
            'pairs' => $rows->map(fn ($r) => (array) $r)->all(),
        ];
    }

    // ── Concept loading ──────────────────────────────────────────────────────

    /**
     * The full per-concept payload for one chapter.
     *
     * Returns a list of concepts, each shaped:
     *   [
     *     'slug', 'index', 'concept' => [...],           // extractor's concept object
     *     'knowledge_items', 'abilities', 'skills', 'competencies',
     *     'learning_objectives', 'learning_outcomes', 'blooms', 'dok',
     *     'prerequisites', 'misconceptions', 'real_world_applications',
     *     'pedagogy_recommendations', 'assessment_blueprint',
     *     'assessment_rubrics', 'concept_relationships', 'evidence',
     *   ]
     *
     * Slices are taken from the blob's own per-concept entries where present and
     * back-filled from the flat slice columns (which carry `concept_name`) so a
     * row extracted by either pipeline version projects identically.
     *
     * @return array{header: array<string,mixed>|null, chapter: array<string,mixed>, concepts: array<int,array<string,mixed>>}
     */
    public function conceptsFor(int $semanticId, ?int $tenant): array
    {
        $cacheKey = $semanticId . ':' . ($tenant ?? '*');
        if (isset($this->conceptCache[$cacheKey])) {
            return $this->conceptCache[$cacheKey];
        }

        $header = $this->chapterHeader($semanticId, $tenant);
        if ($header === null) {
            return $this->conceptCache[$cacheKey] = ['header' => null, 'chapter' => [], 'concepts' => []];
        }

        $blobCol = $this->blobColumn();
        $sliceCols = $this->sliceColumns();

        $columns = array_values(array_filter(array_merge($sliceCols, [$blobCol, 'learning_objective'])));
        $row = DB::table($this->table())->where('id', $semanticId)->first($columns ?: ['*']);

        $blob = $blobCol !== null ? $this->decode($row->{$blobCol} ?? null) : [];

        $chapter = [
            'chapter_name' => $header['chapter_name'] ?? ($blob['chapter_name'] ?? null),
            'chapter_summary' => $blob['chapter_summary'] ?? null,
            'chapter_objectives' => $this->splitLines($row->learning_objective ?? null),
            'input_tokens' => $blob['total_input_tokens'] ?? null,
            'output_tokens' => $blob['total_output_tokens'] ?? null,
        ];

        // Flat slice columns, grouped by the concept_name they carry.
        $slicesByConcept = [];
        foreach ($sliceCols as $col) {
            $decoded = $this->decode($row->{$col} ?? null);
            if (! is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $name = (string) ($entry['concept_name'] ?? $entry['_parent_concept'] ?? '');
                if ($name === '') {
                    continue;
                }
                $slicesByConcept[$this->normalise($name)][$col][] = $entry;
            }
        }

        $entries = is_array($blob['concepts'] ?? null) ? $blob['concepts'] : [];

        // A row whose blob has no concepts[] but whose slice columns do still
        // projects — the concept list is then reconstructed from the slices.
        if ($entries === [] && $slicesByConcept !== []) {
            $entries = [];
            foreach ($slicesByConcept as $key => $bag) {
                $firstName = '';
                foreach ($bag as $rows) {
                    $firstName = (string) ($rows[0]['concept_name'] ?? $rows[0]['_parent_concept'] ?? '');
                    if ($firstName !== '') {
                        break;
                    }
                }
                $entries[] = ['concept' => ['concept_name' => $firstName ?: $key]];
            }
        }

        $concepts = [];
        foreach (array_values($entries) as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $conceptObject = is_array($entry['concept'] ?? null) ? $entry['concept'] : [];
            $name = (string) ($conceptObject['concept_name'] ?? $conceptObject['name'] ?? $entry['concept_name'] ?? '');
            if ($name === '') {
                $name = 'Concept ' . ($index + 1);
            }
            $bag = $slicesByConcept[$this->normalise($name)] ?? [];

            $concepts[] = [
                'index' => $index,
                'slug' => $this->slug($name),
                'name' => $name,
                'concept' => $conceptObject,
                'knowledge_items' => $this->section($entry, 'knowledge_items', $bag, 'knowledge'),
                'abilities' => $this->section($entry, 'abilities', $bag, 'ability'),
                'skills' => $this->section($entry, 'skills', $bag, 'skill'),
                'competencies' => $this->section($entry, 'competencies', $bag, 'competency'),
                'learning_objectives' => $this->section($entry, 'learning_objectives', $bag, 'learning_objectives'),
                'learning_outcomes' => $this->section($entry, 'learning_outcomes', $bag, 'learning_outcomes'),
                'blooms' => $this->section($entry, 'blooms', $bag, 'blooms_level'),
                'dok' => $this->section($entry, 'dok', $bag, 'dok'),
                'prerequisites' => $this->section($entry, 'prerequisites', $bag, 'prerequisites'),
                'misconceptions' => $this->section($entry, 'misconceptions', $bag, 'misconceptions'),
                'real_world_applications' => $this->section($entry, 'real_world_applications', $bag, 'real_world_applications'),
                'pedagogy_recommendations' => $this->section($entry, 'pedagogy_recommendations', $bag, 'pedagogy'),
                'assessment_blueprint' => $this->section($entry, 'assessment_blueprint', $bag, 'assessment_blueprint'),
                'assessment_rubrics' => $this->rubricItems($entry, $bag),
                'concept_relationships' => $this->listOf($entry['concept_relationships'] ?? null),
                'evidence' => $this->listOf($entry['evidence'] ?? null),
            ];
        }

        return $this->conceptCache[$cacheKey] = [
            'header' => $header,
            'chapter' => $chapter,
            'concepts' => $concepts,
        ];
    }

    /** One concept by slug, or null. */
    public function concept(int $semanticId, string $conceptSlug, ?int $tenant): ?array
    {
        $loaded = $this->conceptsFor($semanticId, $tenant);
        foreach ($loaded['concepts'] as $concept) {
            if ($concept['slug'] === $conceptSlug) {
                return $concept + ['_chapter' => $loaded['chapter'], '_header' => $loaded['header']];
            }
        }

        return null;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * The blob's own section, or the flat slice column as a fallback.
     * Both are lists of associative rows.
     */
    protected function section(array $entry, string $blobKey, array $bag, string $sliceColumn): array
    {
        $fromBlob = $this->listOf($entry[$blobKey] ?? null);
        if ($fromBlob !== []) {
            return $fromBlob;
        }

        return $this->listOf($bag[$sliceColumn] ?? null);
    }

    /**
     * assessment_rubrics nests one level deeper than the other sections:
     * [{concept_name, items: [...], teaching_notes: {...}}]. Flattened to the
     * items with the teaching notes attached to the first, so callers see one
     * consistent list shape.
     */
    protected function rubricItems(array $entry, array $bag): array
    {
        $groups = $this->listOf($entry['assessment_rubrics'] ?? null);
        if ($groups === []) {
            $groups = $this->listOf($bag['assessment_rubrics'] ?? null);
        }

        $items = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            // Already a flat item rather than a group.
            if (isset($group['question']) && ! isset($group['items'])) {
                $items[] = $group;
                continue;
            }
            $notes = is_array($group['teaching_notes'] ?? null) ? $group['teaching_notes'] : null;
            foreach ($this->listOf($group['items'] ?? null) as $item) {
                if (is_array($item)) {
                    if ($notes !== null) {
                        $item['_teaching_notes'] = $notes;
                        $notes = null;
                    }
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    protected function decode($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Coerce a value to a list of rows, dropping scalars and null. */
    protected function listOf($value): array
    {
        if (! is_array($value)) {
            return [];
        }
        // An associative single row is a list of one.
        if ($value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
            return [$value];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    protected function splitLines($value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value) ?: [])));
    }

    public function normalise(string $value): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }

    /**
     * Stable, URL-safe concept identifier. It is the second half of every node
     * key, so it must not change when unrelated concepts are added or reordered
     * — which is why it is derived from the name and never from the array index.
     */
    public function slug(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(trim($name))) ?? '';
        $slug = trim($slug, '-');

        $max = (int) config('pal_content_model.concept_slug_max_length', 48);
        if ($slug === '') {
            $slug = 'concept';
        }
        if (strlen($slug) > $max) {
            // Keep it stable AND unique: truncate, then pin with a short hash of
            // the full name so two long names that share a prefix stay distinct.
            $slug = substr($slug, 0, $max - 7) . '-' . substr(md5($name), 0, 6);
        }

        return $slug;
    }
}
