<?php

namespace App\Domain\KnowledgeGraph;

/**
 * One resolved node on a traversal: which ontology entity it is, which record,
 * and how it was reached.
 */
final class GraphNode
{
    public function __construct(
        public readonly string $entityKey,
        public readonly int|string|null $id,
        public readonly string $label,
        public readonly array $attributes = [],
        public readonly ?string $viaRelation = null,
        public readonly int $depth = 0,
        public readonly string $source = 'sql',   // sql | graph
    ) {
    }

    public function fingerprint(): string
    {
        return $this->entityKey . ':' . ($this->id ?? 'null');
    }

    public function toArray(): array
    {
        return [
            'entity' => $this->entityKey,
            'id' => $this->id,
            'label' => $this->label,
            'attributes' => $this->attributes,
            'via' => $this->viaRelation,
            'depth' => $this->depth,
            'source' => $this->source,
        ];
    }
}
