<?php

namespace App\Domain\KnowledgeGraph;

use InvalidArgumentException;

/**
 * A bounded request to walk the graph.
 *
 * Bounded is the point: `maxDepth` and `limitPerHop` exist so a relationship query
 * cannot turn into a table scan across the whole estate. The defaults are small on
 * purpose — a caller that needs more has to say so.
 */
final class TraversalSpec
{
    public const MAX_ALLOWED_DEPTH = 6;

    public const MAX_ALLOWED_LIMIT = 200;

    /**
     * @param  array<int, string>  $path      Ordered entity keys, e.g. ['student','assessment']
     * @param  array<int, string>  $relations Optional relation names constraining each hop
     */
    public function __construct(
        public readonly string $startEntityKey,
        public readonly int|string $startId,
        public readonly array $path = [],
        public readonly array $relations = [],
        public readonly int $maxDepth = 3,
        public readonly int $limitPerHop = 25,
        public readonly bool $preferGraph = true,
    ) {
        if ($this->startEntityKey === '') {
            throw new InvalidArgumentException('A traversal needs a starting entity.');
        }

        if ($this->maxDepth < 1 || $this->maxDepth > self::MAX_ALLOWED_DEPTH) {
            throw new InvalidArgumentException(
                'Traversal depth must be between 1 and ' . self::MAX_ALLOWED_DEPTH . '.'
            );
        }

        if ($this->limitPerHop < 1 || $this->limitPerHop > self::MAX_ALLOWED_LIMIT) {
            throw new InvalidArgumentException(
                'Traversal limit per hop must be between 1 and ' . self::MAX_ALLOWED_LIMIT . '.'
            );
        }

        if ($this->relations !== [] && count($this->relations) !== count($this->path)) {
            throw new InvalidArgumentException(
                'When relations are given there must be exactly one per path hop.'
            );
        }
    }

    public static function fromArray(array $input): self
    {
        $path = array_values(array_filter(
            array_map('strval', (array) ($input['path'] ?? [])),
            fn (string $segment) => $segment !== ''
        ));

        return new self(
            startEntityKey: (string) ($input['entity'] ?? $input['start_entity'] ?? ''),
            startId: $input['id'] ?? $input['start_id'] ?? 0,
            path: $path,
            relations: array_values(array_map('strval', (array) ($input['relations'] ?? []))),
            maxDepth: (int) ($input['max_depth'] ?? max(1, count($path) ?: 3)),
            limitPerHop: (int) ($input['limit'] ?? 25),
            preferGraph: (bool) ($input['prefer_graph'] ?? true),
        );
    }

    /** The relation constraint for a given hop, if one was supplied. */
    public function relationAt(int $index): ?string
    {
        $relation = $this->relations[$index] ?? null;

        return ($relation === null || $relation === '') ? null : $relation;
    }

    public function toArray(): array
    {
        return [
            'entity' => $this->startEntityKey,
            'id' => $this->startId,
            'path' => $this->path,
            'relations' => $this->relations,
            'max_depth' => $this->maxDepth,
            'limit' => $this->limitPerHop,
            'prefer_graph' => $this->preferGraph,
        ];
    }
}
