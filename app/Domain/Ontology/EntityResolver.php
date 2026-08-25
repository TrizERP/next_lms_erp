<?php

namespace App\Domain\Ontology;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns an ontology entity key plus a user's words into real records.
 *
 * This is the bridge between "the user said Riya in class 8B" and
 * `tblstudent.id = 4471`. It is deliberately the only place that reads a mapped
 * source table by entity key, because it is also the place that applies the
 * tenant filter — resolving through anything else would let a caller skip it.
 *
 * Scope is taken from McpRequestContext, the same object the MCP tools already
 * trust, so an agent resolving an entity can never see outside the requesting
 * user's institutes.
 */
class EntityResolver
{
    public function __construct(private readonly OntologyRegistry $registry)
    {
    }

    /**
     * Look up instances of an entity, optionally filtered by a search term.
     *
     * @return array<int, array{id:int|string,label:string,entity:string,attributes:array}>
     */
    public function resolve(
        string $entityKey,
        McpRequestContext $context,
        ?string $search = null,
        int $limit = 25
    ): array {
        $entity = $this->registry->entity($entityKey, $context->selectedInstituteId);

        if (! $entity || ! $entity->isQueryable()) {
            return [];
        }

        if (! Schema::hasTable($entity->sourceTable)) {
            return [];
        }

        $query = DB::table($entity->sourceTable);

        $this->applyTenantScope($query, $entity, $context);
        $this->applyAcademicScope($query, $entity, $context);
        $this->applySearch($query, $entity, $search);

        $primaryKey = $entity->primaryKeyColumn ?: 'id';
        $rows = $query->limit(max(1, min($limit, 200)))->get();

        return $rows->map(function ($row) use ($entity, $primaryKey) {
            $data = (array) $row;

            return [
                'id' => $data[$primaryKey] ?? null,
                'label' => $this->extractLabel($data, $entity),
                'entity' => $entity->key,
                'attributes' => $this->extractAttributes($data, $entity),
            ];
        })->all();
    }

    /**
     * Resolve a single instance by primary key, still tenant-scoped.
     *
     * Returns null when the record exists but sits outside the caller's scope —
     * indistinguishable, on purpose, from "does not exist".
     */
    public function resolveOne(
        string $entityKey,
        int|string $id,
        McpRequestContext $context
    ): ?array {
        $entity = $this->registry->entity($entityKey, $context->selectedInstituteId);

        if (! $entity || ! $entity->isQueryable() || ! Schema::hasTable($entity->sourceTable)) {
            return null;
        }

        $primaryKey = $entity->primaryKeyColumn ?: 'id';

        $query = DB::table($entity->sourceTable)->where($primaryKey, $id);
        $this->applyTenantScope($query, $entity, $context);

        $row = $query->first();

        if (! $row) {
            return null;
        }

        $data = (array) $row;

        return [
            'id' => $data[$primaryKey] ?? null,
            'label' => $this->extractLabel($data, $entity),
            'entity' => $entity->key,
            'attributes' => $this->extractAttributes($data, $entity),
            'record' => $data,
        ];
    }

    /**
     * Which entities could the user plausibly mean? Used by intent resolution to
     * narrow a question like "who is at risk" to `student` rather than guessing.
     *
     * @return array<int, array{entity:string,label:string,score:int}>
     */
    public function candidateEntities(string $text, ?int $subInstituteId = null): array
    {
        $normalized = mb_strtolower(trim($text));

        if ($normalized === '') {
            return [];
        }

        $candidates = [];

        foreach ($this->registry->entities($subInstituteId) as $entity) {
            $score = 0;
            $label = mb_strtolower($entity->label);
            $key = str_replace('_', ' ', $entity->key);

            if (str_contains($normalized, $label)) {
                $score += 5;
            }

            if (str_contains($normalized, $key)) {
                $score += 4;
            }

            // Crude plural handling: "students" should still match the Student entity.
            if (str_contains($normalized, $label . 's') || str_contains($normalized, $key . 's')) {
                $score += 3;
            }

            if ($score > 0) {
                $candidates[] = [
                    'entity' => $entity->key,
                    'label' => $entity->label,
                    'score' => $score,
                ];
            }
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates;
    }

    /**
     * The tenant filter. An entity marked tenant-scoped but missing its tenant
     * column is treated as unresolvable rather than global — failing closed.
     */
    private function applyTenantScope($query, EntityDefinition $entity, McpRequestContext $context): void
    {
        if (! $entity->isTenantScoped) {
            return;
        }

        // Org-level entities scope by client instead of by school.
        if ($entity->clientColumn && Schema::hasColumn($entity->sourceTable, $entity->clientColumn)) {
            if ($context->clientId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($entity->clientColumn, $context->clientId);

            // school_setup carries both: narrow to the selected school as well.
            if ($entity->tenantColumn && Schema::hasColumn($entity->sourceTable, $entity->tenantColumn)) {
                $query->where($entity->tenantColumn, $context->selectedInstituteId);
            }

            return;
        }

        $column = $entity->tenantColumn;

        if (! $column || ! Schema::hasColumn($entity->sourceTable, $column)) {
            // Fail closed: no way to scope means no rows.
            $query->whereRaw('1 = 0');

            return;
        }

        $allowed = $context->allowedInstituteIds !== []
            ? $context->allowedInstituteIds
            : [$context->selectedInstituteId];

        // A caller may only ever see the institute they selected, and only if it is
        // one they are allowed. McpContextResolver has already enforced that pairing.
        $query->where($column, $context->selectedInstituteId)
            ->whereIn($column, $allowed);
    }

    private function applyAcademicScope($query, EntityDefinition $entity, McpRequestContext $context): void
    {
        $column = $entity->academicYearColumn;

        if (! $column || $context->academicYear === null) {
            return;
        }

        if (! Schema::hasColumn($entity->sourceTable, $column)) {
            return;
        }

        $query->where($column, $context->academicYear);
    }

    private function applySearch($query, EntityDefinition $entity, ?string $search): void
    {
        $term = trim((string) $search);

        if ($term === '') {
            return;
        }

        $searchable = [];

        foreach ($entity->attributes as $attribute) {
            if (($attribute['searchable'] ?? false) && ! empty($attribute['column'])) {
                $searchable[] = $attribute['column'];
            }
        }

        if ($searchable === [] && $entity->labelColumn && ! str_contains($entity->labelColumn, '(')) {
            $searchable[] = $entity->labelColumn;
        }

        if ($searchable === []) {
            return;
        }

        $query->where(function ($inner) use ($searchable, $term, $entity) {
            foreach ($searchable as $column) {
                if (Schema::hasColumn($entity->sourceTable, $column)) {
                    $inner->orWhere($column, 'like', '%' . $term . '%');
                }
            }
        });
    }

    private function extractLabel(array $data, EntityDefinition $entity): string
    {
        $column = $entity->labelColumn;

        if ($column && isset($data[$column])) {
            return (string) $data[$column];
        }

        // Common ERP fallbacks before giving up on a human-readable name.
        foreach (['name', 'subject_name', 'title', 'label', 'first_name'] as $candidate) {
            if (isset($data[$candidate]) && $data[$candidate] !== null) {
                return (string) $data[$candidate];
            }
        }

        $primaryKey = $entity->primaryKeyColumn ?: 'id';

        return $entity->label . ' #' . ($data[$primaryKey] ?? '?');
    }

    private function extractAttributes(array $data, EntityDefinition $entity): array
    {
        $attributes = [];

        foreach ($entity->attributes as $attribute) {
            $key = $attribute['key'] ?? null;
            $column = $attribute['column'] ?? null;

            if ($key && $column && array_key_exists($column, $data)) {
                $attributes[$key] = $data[$column];
            }
        }

        return $attributes;
    }
}
