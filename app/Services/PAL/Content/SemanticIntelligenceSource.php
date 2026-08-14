<?php

namespace App\Services\PAL\Content;

use Illuminate\Support\Facades\DB;

/**
 * Reads extracted chapter intelligence out of the `semantic_intelligence` table
 * and normalises one concept entry into the arrays the Pedagogy Engine reasons
 * over (objectives, blooms, blueprint, misconceptions, pedagogy recommendations,
 * real-world applications, prerequisites, relationships).
 *
 * This is the single place that knows the storage shape of
 * `full_intelegance_json`, so the engine services never parse raw JSON.
 */
class SemanticIntelligenceSource
{
    /**
     * Chapters that have been through semantic extraction, for the UI selector.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listChapters(?int $subInstituteId = null): array
    {
        $query = DB::table('semantic_intelligence as s')
            ->leftJoin('document_extractions as d', 's.extraction_id', '=', 'd.id')
            ->select([
                's.id',
                's.chapter_id',
                's.extraction_id',
                's.subject_name',
                's.standard',
                's.chapter_number',
                's.total_concepts',
                'd.document_tittle',
            ])
            ->orderByDesc('s.id');

        if ($subInstituteId !== null) {
            $query->where('s.sub_institute_id', $subInstituteId);
        }

        return $query->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'chapter_id' => $row->chapter_id !== null ? (int) $row->chapter_id : null,
                'extraction_id' => $row->extraction_id !== null ? (int) $row->extraction_id : null,
                'title' => $row->document_tittle ?: ('Chapter ' . ($row->chapter_number ?? $row->chapter_id)),
                'subject_name' => $row->subject_name,
                'standard' => $row->standard,
                'chapter_number' => $row->chapter_number,
                'total_concepts' => (int) ($row->total_concepts ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Resolve a chapter + concept into the payload the engine evaluates.
     *
     * `$chapterId` matches chapter_id, extraction_id or the semantic row id, in
     * that order. When it is empty the most recent extracted chapter is used --
     * the same fallback the PAL Framework / ULU screens already rely on.
     * `$concept` is either a numeric index or a concept_name.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(?string $chapterId, ?string $concept): ?array
    {
        $row = $this->findRow($chapterId);
        if (! $row) {
            return null;
        }

        $blob = $this->decode($row->full_intelegance_json);
        $entries = array_values(array_filter(
            (array) ($blob['concepts'] ?? []),
            fn ($item) => is_array($item) && $item !== []
        ));

        [$entry, $index] = $this->pickConcept($entries, (string) $concept);

        return [
            'context' => $this->buildContext($row, $blob, $entry, $index, count($entries)),
            'concepts' => array_map(
                fn ($item, $position) => [
                    'index' => $position,
                    'name' => (string) ($item['concept']['concept_name'] ?? "Concept {$position}"),
                    'difficulty' => $item['concept']['difficulty'] ?? null,
                    'importance' => $item['concept']['importance'] ?? null,
                ],
                $entries,
                array_keys($entries)
            ),
            'concept' => $entry === null ? null : $this->normalizeConcept($entry, $row),
        ];
    }

    private function findRow(?string $chapterId): ?object
    {
        $query = DB::table('semantic_intelligence')->whereNotNull('full_intelegance_json');

        $id = trim((string) $chapterId);
        if ($id !== '' && ctype_digit($id)) {
            return $query
                ->where(function ($q) use ($id) {
                    $q->where('chapter_id', (int) $id)
                        ->orWhere('extraction_id', (int) $id)
                        ->orWhere('id', (int) $id);
                })
                ->orderByDesc('id')
                ->first();
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array{0: array<string, mixed>|null, 1: int}
     */
    private function pickConcept(array $entries, string $concept): array
    {
        if ($entries === []) {
            return [null, 0];
        }

        $needle = trim($concept);

        if ($needle !== '' && ctype_digit($needle)) {
            $bounded = max(0, min((int) $needle, count($entries) - 1));

            return [$entries[$bounded], $bounded];
        }

        if ($needle !== '') {
            foreach ($entries as $position => $entry) {
                if (strcasecmp((string) ($entry['concept']['concept_name'] ?? ''), $needle) === 0) {
                    return [$entry, $position];
                }
            }
        }

        return [$entries[0], 0];
    }

    /**
     * @param  array<string, mixed>|null  $entry
     * @return array<string, mixed>
     */
    private function buildContext(object $row, array $blob, ?array $entry, int $index, int $total): array
    {
        return [
            'semantic_id' => (int) $row->id,
            'chapter_id' => $row->chapter_id !== null ? (int) $row->chapter_id : null,
            'extraction_id' => $row->extraction_id !== null ? (int) $row->extraction_id : null,
            'chapter_title' => (string) ($blob['chapter_name'] ?? ('Chapter ' . ($row->chapter_number ?? $row->chapter_id))),
            'chapter_summary' => $blob['chapter_summary'] ?? null,
            'subject_name' => $row->subject_name,
            'standard' => $row->standard,
            'chapter_number' => $row->chapter_number,
            'concept_index' => $index,
            'concept_total' => $total,
            'concept_name' => $entry['concept']['concept_name'] ?? null,
            'concept_definition' => $entry['concept']['definition'] ?? null,
            'concept_difficulty' => $entry['concept']['difficulty'] ?? null,
            'concept_importance' => $entry['concept']['importance'] ?? null,
            'extracted_by' => $row->llm_model ?? null,
            'extracted_at' => $row->updated_at ?? $row->created_at ?? null,
            'source_table' => 'semantic_intelligence',
        ];
    }

    /**
     * Flatten one concept entry, falling back to the chapter-level columns when
     * a per-concept array is absent (older extractions stored them separately).
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function normalizeConcept(array $entry, object $row): array
    {
        return [
            'identity' => (array) ($entry['concept'] ?? []),
            'knowledge_items' => $this->listOf($entry, 'knowledge_items', $row, 'knowledge'),
            'abilities' => $this->listOf($entry, 'abilities', $row, 'ability'),
            'skills' => $this->listOf($entry, 'skills', $row, 'skill'),
            'competencies' => $this->listOf($entry, 'competencies', $row, 'competency'),
            'learning_objectives' => $this->listOf($entry, 'learning_objectives', $row, 'learning_objectives'),
            'learning_outcomes' => $this->listOf($entry, 'learning_outcomes', $row, 'learning_outcomes'),
            'blooms' => $this->listOf($entry, 'blooms', $row, 'blooms_level'),
            'dok' => $this->listOf($entry, 'dok', $row, 'dok'),
            'prerequisites' => $this->listOf($entry, 'prerequisites', $row, 'prerequisites'),
            'misconceptions' => $this->listOf($entry, 'misconceptions', $row, 'misconceptions'),
            'real_world_applications' => $this->listOf($entry, 'real_world_applications', $row, 'real_world_applications'),
            'pedagogy_recommendations' => $this->listOf($entry, 'pedagogy_recommendations', $row, 'pedagogy'),
            'assessment_blueprint' => $this->listOf($entry, 'assessment_blueprint', $row, 'assessment_blueprint'),
            'concept_relationships' => $this->listOf($entry, 'concept_relationships', $row, null),
            'evidence' => $this->listOf($entry, 'evidence', $row, null),
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<int, array<string, mixed>>
     */
    private function listOf(array $entry, string $key, object $row, ?string $columnFallback): array
    {
        $value = $this->asRecordList($entry[$key] ?? null);
        if ($value !== [] || $columnFallback === null) {
            return $value;
        }

        return $this->asRecordList($this->decode($row->{$columnFallback} ?? null));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function asRecordList(mixed $value): array
    {
        if (is_string($value)) {
            $value = $this->decode($value);
        }

        if (! is_array($value)) {
            return [];
        }

        $records = [];
        foreach ($value as $item) {
            if (is_array($item) && $item !== []) {
                $records[] = $item;
            } elseif (is_string($item) && trim($item) !== '') {
                // Some extractions store plain strings (e.g. prerequisites).
                $records[] = ['value' => trim($item)];
            }
        }

        return $records;
    }

    private function decode(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return is_array($value) ? $value : [];
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }
}
