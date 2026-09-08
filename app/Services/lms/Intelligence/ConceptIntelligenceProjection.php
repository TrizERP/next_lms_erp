<?php

namespace App\Services\lms\Intelligence;

use Illuminate\Support\Facades\DB;

/**
 * Projects the Concept Intelligence blobs into queryable rows.
 *
 * Follow-on to tracker row 6 / Decision #38. See the migration
 * 2026_09_08_090000_create_lms_concept_intelligence_index_table for why this exists and
 * what it deliberately is not.
 *
 * `semantic_intelligence` is READ ONLY here. This class never writes it, and the
 * projection is fully rebuildable from it.
 */
class ConceptIntelligenceProjection
{
    /**
     * How each blob column maps into projected rows.
     *
     * `label` is the list of candidate keys holding the item's name, tried in order -
     * the extractor is not perfectly consistent and a missing key must degrade to
     * "skip this entry", never to a mislabelled row.
     *
     * `confidence` names the key holding a 0-1 score where the dimension has one.
     * Absent means the extractor expressed none, which is NOT the same as zero.
     *
     * PREREQUISITES ARE INVERTED, and getting this wrong would mislabel 4,600 rows:
     * on every other dimension `concept_name` is the concept the item belongs to, but
     * on a prerequisite entry `concept_name` IS the prerequisite and `_parent_concept`
     * is the concept that requires it. Hence `owner` / `label` are swapped there.
     */
    private const DIMENSIONS = [
        'knowledge' => [
            'column' => 'knowledge',
            'label' => ['knowledge', 'statement'],
            'confidence' => 'confidence',
        ],
        'ability' => [
            'column' => 'ability',
            'label' => ['ability', 'description'],
        ],
        'skill' => [
            'column' => 'skill',
            'label' => ['skill'],
        ],
        'competency' => [
            'column' => 'competency',
            'label' => ['competency', 'statement'],
        ],
        'bloom' => [
            'column' => 'blooms_level',
            'label' => ['level'],
            'confidence' => 'coverage_score',
        ],
        'dok' => [
            'column' => 'dok',
            'label' => ['level'],
            'confidence' => 'coverage_score',
        ],
        'prerequisite' => [
            'column' => 'prerequisites',
            'label' => ['concept_name'],
            'owner' => '_parent_concept',   // <- the inversion
        ],
        'misconception' => [
            'column' => 'misconceptions',
            'label' => ['misconception', 'statement'],
        ],
        'real_world' => [
            'column' => 'real_world_applications',
            'label' => ['example', 'application_type'],
        ],
        'pedagogy' => [
            'column' => 'pedagogy',
            'label' => ['strategy'],
        ],
    ];

    /** @return list<string> */
    public static function dimensions(): array
    {
        return array_keys(self::DIMENSIONS);
    }

    /** @return list<string> */
    public static function sourceColumns(): array
    {
        return array_values(array_column(self::DIMENSIONS, 'column'));
    }

    /**
     * Turn one `semantic_intelligence` row into projected rows.
     *
     * Pure: no database access, so it is unit-testable without touching the live
     * shared database that phpunit.xml points at.
     *
     * @param  array<string,mixed>  $row  a semantic_intelligence row as an assoc array
     * @return list<array<string,mixed>>
     */
    public function project(array $row, ?string $projectedAt = null): array
    {
        $semanticId = (int) ($row['id'] ?? 0);
        $chapterId = isset($row['chapter_id']) ? (int) $row['chapter_id'] : null;
        $tenantId = (int) ($row['sub_institute_id'] ?? 0);

        $out = [];

        foreach (self::DIMENSIONS as $dimension => $spec) {
            $entries = $this->decodeList($row[$spec['column']] ?? null);

            foreach ($entries as $ordinal => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $label = $this->firstNonEmpty($entry, $spec['label']);

                // An entry with no readable label is skipped, not defaulted. A row
                // labelled "Untitled" would be indistinguishable from real content in
                // a query, which is worse than an absent row.
                if ($label === null) {
                    continue;
                }

                $owner = isset($spec['owner'])
                    ? $this->stringOrNull($entry[$spec['owner']] ?? null)
                    : $this->stringOrNull($entry['concept_name'] ?? null);

                $itemKey = $this->slug($label);
                if ($itemKey === '') {
                    continue;
                }

                $confidence = null;
                if (isset($spec['confidence']) && isset($entry[$spec['confidence']]) && is_numeric($entry[$spec['confidence']])) {
                    $confidence = (float) $entry[$spec['confidence']];
                }

                // Everything not already promoted to a column, kept verbatim so the
                // projection is lossless.
                $attributes = $entry;
                unset($attributes['concept_name']);

                $out[] = [
                    'semantic_id' => $semanticId,
                    'chapter_id' => $chapterId,
                    'sub_institute_id' => $tenantId,
                    'dimension' => $dimension,
                    'concept_name' => $owner === null ? null : mb_substr($owner, 0, 191),
                    'item_key' => $itemKey,
                    'item_label' => $label,
                    'ordinal' => $ordinal,
                    'confidence' => $confidence,
                    'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'row_hash' => hash('sha256', $dimension . '|' . ($owner ?? '') . '|' . $itemKey),
                    'projected_at' => $projectedAt,
                ];
            }
        }

        // One blob can legitimately repeat the same (dimension, concept, item) - the
        // same skill listed against two abilities, say. The unique key would reject the
        // duplicate anyway; collapsing here keeps the batch upsert deterministic rather
        // than order-dependent.
        $unique = [];
        foreach ($out as $projected) {
            $unique[$projected['row_hash']] = $projected;
        }

        return array_values($unique);
    }

    /**
     * Read the source rows to project.
     *
     * @return list<array<string,mixed>>
     */
    public function sourceRows(?int $tenantId = null, ?int $chapterId = null, int $limit = 0): array
    {
        $query = DB::table('semantic_intelligence')
            ->select(array_merge(['id', 'chapter_id', 'sub_institute_id'], self::sourceColumns()))
            ->orderBy('id');

        if ($tenantId !== null) {
            $query->where('sub_institute_id', $tenantId);
        }

        if ($chapterId !== null) {
            $query->where('chapter_id', $chapterId);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return array_map(static fn ($r) => (array) $r, $query->get()->all());
    }

    /**
     * Decode a blob column into a list of entries.
     *
     * Tolerant on purpose: the column is longtext holding JSON written by an LLM
     * pipeline. A malformed or non-list value yields no rows rather than an exception,
     * because one bad chapter must not stop a projection run over 89 of them.
     *
     * @return list<mixed>
     */
    private function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_is_list($value) ? $value : [$value];
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return [];
        }

        return array_is_list($decoded) ? $decoded : [$decoded];
    }

    /**
     * @param  array<string,mixed>  $entry
     * @param  list<string>  $keys
     */
    private function firstNonEmpty(array $entry, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->stringOrNull($entry[$key] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalise a label into a queryable key.
     *
     * Lowercased, punctuation collapsed to single underscores, truncated to the column
     * width. Two labels differing only in case or punctuation deliberately collapse to
     * one key - that is what makes "find this competency across chapters" work.
     */
    private function slug(string $label): string
    {
        $slug = mb_strtolower($label, 'UTF-8');
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '_', $slug) ?? '';

        return mb_substr(trim($slug, '_'), 0, 191);
    }
}
