<?php

namespace App\Domain\Ontology;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime access to the ontology.
 *
 * Definitions live in `ontology_entities` / `ontology_relationships`, so they can be
 * extended per tenant without a deploy. Rows with a NULL sub_institute_id are the
 * platform baseline; a tenant row with the same key overrides it.
 *
 * Every read is cached per tenant because the ontology is consulted on almost every
 * intelligence call and changes rarely. `flush()` is called by the seeder and by the
 * admin API after any write.
 */
class OntologyRegistry
{
    private const CACHE_TTL = 3600;

    /** @var array<string, array<string, EntityDefinition>> */
    private array $entityMemo = [];

    /** @var array<string, array<int, RelationshipDefinition>> */
    private array $relationshipMemo = [];

    /**
     * @return array<string, EntityDefinition> keyed by entity_key
     */
    public function entities(?int $subInstituteId = null): array
    {
        $memoKey = (string) ($subInstituteId ?? 'global');

        if (isset($this->entityMemo[$memoKey])) {
            return $this->entityMemo[$memoKey];
        }

        if (! Schema::hasTable('ontology_entities')) {
            return $this->entityMemo[$memoKey] = [];
        }

        $rows = Cache::remember(
            $this->cacheKey('entities', $subInstituteId),
            self::CACHE_TTL,
            fn () => DB::table('ontology_entities')
                ->where('status', 1)
                ->where(function ($query) use ($subInstituteId) {
                    $query->whereNull('sub_institute_id');
                    if ($subInstituteId !== null) {
                        $query->orWhere('sub_institute_id', $subInstituteId);
                    }
                })
                // Tenant rows sort last so they overwrite the global baseline below.
                ->orderByRaw('sub_institute_id IS NULL DESC')
                ->orderBy('sort_order')
                ->get()
                ->all()
        );

        $entities = [];
        foreach ($rows as $row) {
            $definition = EntityDefinition::fromRow($row);
            $entities[$definition->key] = $definition;
        }

        return $this->entityMemo[$memoKey] = $entities;
    }

    public function entity(string $key, ?int $subInstituteId = null): ?EntityDefinition
    {
        return $this->entities($subInstituteId)[$key] ?? null;
    }

    public function hasEntity(string $key, ?int $subInstituteId = null): bool
    {
        return isset($this->entities($subInstituteId)[$key]);
    }

    /**
     * @return array<int, RelationshipDefinition>
     */
    public function relationships(?int $subInstituteId = null): array
    {
        $memoKey = (string) ($subInstituteId ?? 'global');

        if (isset($this->relationshipMemo[$memoKey])) {
            return $this->relationshipMemo[$memoKey];
        }

        if (! Schema::hasTable('ontology_relationships')) {
            return $this->relationshipMemo[$memoKey] = [];
        }

        $rows = Cache::remember(
            $this->cacheKey('relationships', $subInstituteId),
            self::CACHE_TTL,
            fn () => DB::table('ontology_relationships')
                ->where('status', 1)
                ->where(function ($query) use ($subInstituteId) {
                    $query->whereNull('sub_institute_id');
                    if ($subInstituteId !== null) {
                        $query->orWhere('sub_institute_id', $subInstituteId);
                    }
                })
                ->orderByRaw('sub_institute_id IS NULL DESC')
                ->orderBy('traversal_cost')
                ->get()
                ->all()
        );

        $byKey = [];
        foreach ($rows as $row) {
            $definition = RelationshipDefinition::fromRow($row);
            $byKey[$definition->key] = $definition;
        }

        return $this->relationshipMemo[$memoKey] = array_values($byKey);
    }

    /**
     * Edges leaving an entity. Used by the traversal planner to expand a frontier.
     *
     * @return array<int, RelationshipDefinition>
     */
    public function relationshipsFrom(string $entityKey, ?int $subInstituteId = null): array
    {
        return array_values(array_filter(
            $this->relationships($subInstituteId),
            fn (RelationshipDefinition $rel) => $rel->fromEntityKey === $entityKey
        ));
    }

    /**
     * @return array<int, RelationshipDefinition>
     */
    public function relationshipsTo(string $entityKey, ?int $subInstituteId = null): array
    {
        return array_values(array_filter(
            $this->relationships($subInstituteId),
            fn (RelationshipDefinition $rel) => $rel->toEntityKey === $entityKey
        ));
    }

    public function relationship(string $key, ?int $subInstituteId = null): ?RelationshipDefinition
    {
        foreach ($this->relationships($subInstituteId) as $relationship) {
            if ($relationship->key === $key) {
                return $relationship;
            }
        }

        return null;
    }

    /**
     * Find the edge joining two entities, optionally constrained to a named relation.
     * Returns the cheapest match so the planner prefers direct edges over pivots.
     */
    public function findEdge(
        string $fromEntityKey,
        string $toEntityKey,
        ?string $relation = null,
        ?int $subInstituteId = null
    ): ?RelationshipDefinition {
        $matches = array_filter(
            $this->relationships($subInstituteId),
            function (RelationshipDefinition $rel) use ($fromEntityKey, $toEntityKey, $relation) {
                if ($rel->fromEntityKey !== $fromEntityKey || $rel->toEntityKey !== $toEntityKey) {
                    return false;
                }

                return $relation === null || $rel->relation === $relation;
            }
        );

        if ($matches === []) {
            return null;
        }

        usort(
            $matches,
            fn (RelationshipDefinition $a, RelationshipDefinition $b) => $a->traversalCost <=> $b->traversalCost
        );

        return $matches[0];
    }

    /**
     * @return array<int, EntityDefinition>
     */
    public function entitiesInDomain(string $domain, ?int $subInstituteId = null): array
    {
        return array_values(array_filter(
            $this->entities($subInstituteId),
            fn (EntityDefinition $entity) => $entity->domain === $domain || $entity->domain === 'shared'
        ));
    }

    public function flush(?int $subInstituteId = null): void
    {
        Cache::forget($this->cacheKey('entities', $subInstituteId));
        Cache::forget($this->cacheKey('relationships', $subInstituteId));

        $memoKey = (string) ($subInstituteId ?? 'global');
        unset($this->entityMemo[$memoKey], $this->relationshipMemo[$memoKey]);
    }

    public function flushAll(): void
    {
        $this->entityMemo = [];
        $this->relationshipMemo = [];

        if (! Schema::hasTable('ontology_entities')) {
            Cache::forget($this->cacheKey('entities', null));
            Cache::forget($this->cacheKey('relationships', null));

            return;
        }

        $tenantIds = DB::table('ontology_entities')
            ->distinct()
            ->pluck('sub_institute_id')
            ->all();

        $tenantIds[] = null;

        foreach (array_unique($tenantIds, SORT_REGULAR) as $tenantId) {
            $this->flush($tenantId === null ? null : (int) $tenantId);
        }
    }

    private function cacheKey(string $bucket, ?int $subInstituteId): string
    {
        return sprintf('ontology:%s:%s', $bucket, $subInstituteId ?? 'global');
    }
}
