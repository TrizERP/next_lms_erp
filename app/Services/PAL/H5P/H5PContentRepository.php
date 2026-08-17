<?php

namespace App\Services\PAL\H5P;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unified read over the H5P content this ERP actually stores.
 *
 * The H5P estate is four unrelated tables with four different column names:
 * `h5p_scenarios` (+points), `h5p_interactive_video` (+interactions),
 * `h5p_flashcard`, and the `question_type_id = 1` slice of the shared
 * `lms_question_master` question bank. This class turns all four into one
 * shape — an H5P **node** — so the model, the engagement layer and the UI
 * never have to know which table a node came from.
 *
 * Nothing here hard-codes a table. Every table name, column name, child
 * relation and filter is read from the `implementation` block of the H5P type
 * in `pal_vocabulary`, so registering a fifth native type is a registry edit.
 * A type whose table does not exist on this estate is skipped rather than
 * throwing, which is what makes the module survive a partially migrated DB.
 *
 * No new content table is introduced and nothing is copied: a node is a live
 * projection of the row that already exists.
 */
class H5PContentRepository
{
    /** Nodes returned per type when the caller does not ask for a limit. */
    public const DEFAULT_LIMIT = 50;

    public function __construct(protected H5PModelRegistry $registry)
    {
    }

    /**
     * Every H5P node in one chapter, across every natively implemented type.
     *
     * @param  array  $context  chapter_id / subject_id / standard_id / sub_institute_id
     * @return array<int,array> node list, richest-first within each type
     */
    public function nodesForContext(array $context, ?string $onlyType = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $tenant = $this->tenant($context);
        $types = $this->registry->nativeTypes($tenant);

        if ($onlyType !== null) {
            $code = $this->registry->normalize('h5p_types', $onlyType, $tenant);
            $types = $code !== null && isset($types[$code]) ? [$code => $types[$code]] : [];
        }

        $nodes = [];
        foreach ($types as $code => $type) {
            foreach ($this->nodesOfType($code, $type, $context, $limit) as $node) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * How many nodes exist per type for a context, plus how many child parts
     * (hotspots / interactions / options) hang off them. This is what makes the
     * H5P Content hub show real counts instead of four identical cards.
     *
     * @return array<string,array{nodes:int,children:int,child_label:?string,available:bool,reason:?string}>
     */
    public function inventory(array $context): array
    {
        $tenant = $this->tenant($context);
        $out = [];

        foreach ($this->registry->nativeTypes($tenant) as $code => $type) {
            $implementation = $type['metadata']['implementation'] ?? [];
            $table = $implementation['source_table'] ?? null;

            if (! $table || ! $this->tableExists($table)) {
                $out[$code] = [
                    'nodes' => 0,
                    'children' => 0,
                    'child_label' => $implementation['child_label'] ?? null,
                    'available' => false,
                    'reason' => $table
                        ? "The `{$table}` table is not present on this database."
                        : 'No source table is registered for this type.',
                ];
                continue;
            }

            $query = $this->baseQuery($table, $implementation, $context);
            $nodeCount = (clone $query)->count();

            $children = 0;
            $childTable = $implementation['child_table'] ?? null;
            if ($childTable && $this->tableExists($childTable) && $nodeCount > 0) {
                $idColumn = $implementation['columns']['id'] ?? 'id';
                $children = DB::table($childTable)
                    ->whereIn(
                        $implementation['child_foreign_key'],
                        (clone $query)->select("{$table}.{$idColumn}")
                    )
                    ->when($this->hasColumn($childTable, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                    ->count();
            }

            $out[$code] = [
                'nodes' => $nodeCount,
                'children' => $children,
                'child_label' => $implementation['child_label'] ?? null,
                'available' => true,
                'reason' => null,
            ];
        }

        return $out;
    }

    /** A single node by type + id, or null when it does not exist / is out of tenant. */
    public function node(string $h5pType, int $id, array $context): ?array
    {
        $tenant = $this->tenant($context);
        $code = $this->registry->normalize('h5p_types', $h5pType, $tenant);
        $type = $code ? $this->registry->type($code, $tenant) : null;

        if ($type === null || ($type['metadata']['implementation']['status'] ?? null) !== 'native') {
            return null;
        }

        $implementation = $type['metadata']['implementation'];
        $table = $implementation['source_table'];
        if (! $this->tableExists($table)) {
            return null;
        }

        $idColumn = $implementation['columns']['id'] ?? 'id';
        $row = $this->baseQuery($table, $implementation, $context)
            ->where("{$table}.{$idColumn}", $id)
            ->first();

        return $row ? $this->toNode($code, $implementation, $row, $context, true) : null;
    }

    /**
     * Distinct chapters that hold at least one H5P node, for the model
     * workspace's chapter picker when it is opened without a chapter.
     */
    public function chaptersWithContent(array $context, int $limit = 200): array
    {
        $tenant = $this->tenant($context);
        $chapterIds = [];

        foreach ($this->registry->nativeTypes($tenant) as $type) {
            $implementation = $type['metadata']['implementation'] ?? [];
            $table = $implementation['source_table'] ?? null;
            $chapterColumn = $implementation['columns']['chapter'] ?? null;

            if (! $table || ! $chapterColumn || ! $this->tableExists($table)) {
                continue;
            }

            // Chapter is the only join key that is reliably populated across
            // this estate, so the workspace is chapter-scoped throughout.
            $ids = $this->baseQuery($table, $implementation, array_diff_key($context, ['chapter_id' => null]))
                ->whereNotNull("{$table}.{$chapterColumn}")
                ->where("{$table}.{$chapterColumn}", '>', 0)
                ->distinct()
                ->limit($limit)
                ->pluck("{$table}.{$chapterColumn}")
                ->all();

            foreach ($ids as $id) {
                $chapterIds[(int) $id] = true;
            }
        }

        if ($chapterIds === []) {
            return [];
        }

        $ids = array_slice(array_keys($chapterIds), 0, $limit);

        // Resolve names where `chapter_master` has the row. It frequently does
        // not — this estate holds H5P content against chapter ids that were
        // never written to chapter_master — so an unresolved chapter is
        // labelled rather than dropped. Dropping it would hide content that
        // demonstrably exists.
        $names = [];
        if ($this->tableExists('chapter_master')) {
            foreach (DB::table('chapter_master')->whereIn('id', $ids)->get(['id', 'chapter_name', 'subject_id', 'standard_id', 'grade_id']) as $row) {
                $names[(int) $row->id] = $row;
            }
        }

        $out = [];
        foreach ($ids as $id) {
            $row = $names[$id] ?? null;
            $name = $row !== null ? trim((string) $row->chapter_name) : '';

            $out[] = [
                'chapter_id' => $id,
                'chapter_name' => $name !== '' ? $name : "Chapter #{$id}",
                'resolved' => $row !== null && $name !== '',
                'subject_id' => $row?->subject_id !== null ? (int) $row->subject_id : null,
                'standard_id' => $row?->standard_id !== null ? (int) $row->standard_id : null,
                'grade_id' => $row?->grade_id !== null ? (int) $row->grade_id : null,
            ];
        }

        return $out;
    }

    /** Chapter / subject / standard display names for the workspace header. */
    public function resolveContextNames(array $context): array
    {
        $names = ['chapter_name' => null, 'subject_name' => null, 'standard_name' => null];

        $lookups = [
            'chapter_name' => ['chapter_master', (int) ($context['chapter_id'] ?? 0), 'chapter_name'],
            'subject_name' => ['subject', (int) ($context['subject_id'] ?? 0), 'subject_name'],
            'standard_name' => ['standard', (int) ($context['standard_id'] ?? 0), 'name'],
        ];

        foreach ($lookups as $key => [$table, $id, $column]) {
            if ($id > 0 && $this->tableExists($table) && $this->hasColumn($table, $column)) {
                $value = DB::table($table)->where('id', $id)->value($column);
                $names[$key] = $value !== null ? (string) $value : null;
            }
        }

        return $names;
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * Nodes of one type. Ordered by child count descending where a child table
     * exists — a scenario with 12 hotspots is more useful to look at first than
     * an empty one — then newest first.
     */
    protected function nodesOfType(string $code, array $type, array $context, int $limit): array
    {
        $implementation = $type['metadata']['implementation'] ?? [];
        $table = $implementation['source_table'] ?? null;

        if (! $table || ! $this->tableExists($table)) {
            return [];
        }

        $columns = $implementation['columns'] ?? [];
        $idColumn = $columns['id'] ?? 'id';
        $query = $this->baseQuery($table, $implementation, $context);

        $select = ["{$table}.*"];
        $childTable = $implementation['child_table'] ?? null;
        $hasChildren = $childTable && $this->tableExists($childTable);

        if ($hasChildren) {
            $childKey = $implementation['child_foreign_key'];
            $childDeleted = $this->hasColumn($childTable, 'deleted_at')
                ? " and {$childTable}.deleted_at is null"
                : '';
            $select[] = DB::raw(
                "(select count(*) from {$childTable} where {$childTable}.{$childKey} = {$table}.{$idColumn}{$childDeleted}) as pal_child_count"
            );
        }

        $query->select($select);

        if ($hasChildren) {
            $query->orderByDesc('pal_child_count');
        }
        if (isset($columns['created_at']) && $this->hasColumn($table, $columns['created_at'])) {
            $query->orderByDesc("{$table}.{$columns['created_at']}");
        }
        $query->orderByDesc("{$table}.{$idColumn}");

        return $query->limit(max(1, $limit))
            ->get()
            ->map(fn ($row) => $this->toNode($code, $implementation, $row, $context, false))
            ->all();
    }

    /**
     * Tenant + curriculum scoping shared by every read. Only filters on a
     * column the table actually declares, so a table without (say) standard_id
     * is not silently emptied.
     */
    protected function baseQuery(string $table, array $implementation, array $context)
    {
        $columns = $implementation['columns'] ?? [];
        $query = DB::table($table);

        $filters = [
            'chapter' => $context['chapter_id'] ?? null,
            'subject' => $context['subject_id'] ?? null,
            'standard' => $context['standard_id'] ?? null,
            'tenant' => $context['sub_institute_id'] ?? null,
        ];

        foreach ($filters as $key => $value) {
            $column = $columns[$key] ?? null;
            if ($column && $value !== null && $value !== '' && (int) $value > 0 && $this->hasColumn($table, $column)) {
                $query->where("{$table}.{$column}", (int) $value);
            }
        }

        foreach ((array) ($implementation['where'] ?? []) as $column => $value) {
            if ($this->hasColumn($table, $column)) {
                $query->where("{$table}.{$column}", $value);
            }
        }

        $softDelete = $columns['soft_delete'] ?? null;
        if ($softDelete && $this->hasColumn($table, $softDelete)) {
            $query->whereNull("{$table}.{$softDelete}");
        }

        return $query;
    }

    /**
     * One row → one H5P node.
     *
     * `signals` is the raw text the tagging layer reasons over; it is assembled
     * here so the model layer never re-reads the source table.
     */
    protected function toNode(string $code, array $implementation, $row, array $context, bool $withChildren): array
    {
        $columns = $implementation['columns'] ?? [];
        $data = (array) $row;
        $read = fn (?string $key) => $key && array_key_exists($key, $data) ? $data[$key] : null;

        $id = (int) ($read($columns['id'] ?? 'id') ?? 0);
        $title = $this->plainText($read($columns['title'] ?? null));
        $body = $this->plainText($read($columns['body'] ?? null));

        $node = [
            'node_key' => "{$code}:{$id}",
            'h5p_type' => $code,
            'id' => $id,
            'title' => $title !== '' ? $title : "Untitled #{$id}",
            'summary' => $body !== '' ? mb_substr($body, 0, 400) : null,
            'media_url' => $read($columns['media'] ?? null),
            'chapter_id' => $this->intOrNull($read($columns['chapter'] ?? null)),
            'subject_id' => $this->intOrNull($read($columns['subject'] ?? null)),
            'standard_id' => $this->intOrNull($read($columns['standard'] ?? null)),
            'sub_institute_id' => $this->intOrNull($read($columns['tenant'] ?? null)),
            'created_by' => $this->intOrNull($read($columns['created_by'] ?? null)),
            'created_at' => $read($columns['created_at'] ?? null),
            'child_count' => array_key_exists('pal_child_count', $data) ? (int) $data['pal_child_count'] : null,
            'child_label' => $implementation['child_label'] ?? null,
            'source_table' => $implementation['source_table'],
            'signals' => array_values(array_filter([
                $title,
                $body,
                $this->plainText($read($columns['concept'] ?? null)),
                $this->plainText($read($columns['learning_outcome'] ?? null)),
                $this->plainText($read($columns['answer'] ?? null)),
                $this->plainText($read($columns['hint'] ?? null)),
            ], fn ($value) => is_string($value) && $value !== '')),
        ];

        if ($withChildren) {
            $node['children'] = $this->children($implementation, $id);
        }

        return $node;
    }

    /** Child parts of one node (hotspots / interactions / answer options). */
    protected function children(array $implementation, int $id): array
    {
        $childTable = $implementation['child_table'] ?? null;
        if (! $childTable || ! $this->tableExists($childTable)) {
            return [];
        }

        return DB::table($childTable)
            ->where($implementation['child_foreign_key'], $id)
            ->when($this->hasColumn($childTable, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('id')
            ->get()
            ->map(fn ($child) => (array) $child)
            ->all();
    }

    protected function tenant(array $context): ?int
    {
        $tenant = (int) ($context['sub_institute_id'] ?? 0);

        return $tenant > 0 ? $tenant : null;
    }

    protected function intOrNull($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /** Strip the CKEditor HTML these tables store so signals stay readable. */
    protected function plainText($value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $value) ?? $value;
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** Schema probes are memoised — the workspace asks about the same handful. */
    protected function tableExists(string $table): bool
    {
        static $cache = [];

        return $cache[$table] ??= Schema::hasTable($table);
    }

    protected function hasColumn(string $table, string $column): bool
    {
        static $cache = [];

        return $cache["{$table}.{$column}"] ??= $this->tableExists($table) && Schema::hasColumn($table, $column);
    }
}
