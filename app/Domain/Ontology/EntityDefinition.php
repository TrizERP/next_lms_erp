<?php

namespace App\Domain\Ontology;

/**
 * One ontology entity: a name the platform agrees on, bound to the table that
 * already holds the real records.
 *
 * This is a mapping, never a copy. `sourceTable` points at `tblstudent`, `standard`,
 * `subject` and so on; the ontology owns the vocabulary, the ERP keeps owning the data.
 */
final class EntityDefinition
{
    /**
     * @param  array<int, array{key:string,column:string,type?:string,label?:string}>  $attributes
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $domain = 'shared',
        public readonly string $category = 'core',
        public readonly ?string $description = null,
        public readonly ?string $sourceTable = null,
        public readonly ?string $primaryKeyColumn = 'id',
        public readonly ?string $labelColumn = null,
        public readonly ?string $tenantColumn = null,
        public readonly ?string $clientColumn = null,
        public readonly ?string $academicYearColumn = null,
        public readonly array $attributes = [],
        public readonly bool $isVirtual = false,
        public readonly bool $isTenantScoped = true,
        public readonly ?int $subInstituteId = null,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            key: (string) $row->entity_key,
            label: (string) $row->label,
            domain: (string) ($row->domain ?? 'shared'),
            category: (string) ($row->category ?? 'core'),
            description: $row->description ?? null,
            sourceTable: $row->source_table ?? null,
            primaryKeyColumn: $row->primary_key_column ?? 'id',
            labelColumn: $row->label_column ?? null,
            tenantColumn: $row->tenant_column ?? null,
            clientColumn: $row->client_column ?? null,
            academicYearColumn: $row->academic_year_column ?? null,
            attributes: self::decodeJson($row->attributes ?? null),
            isVirtual: (bool) ($row->is_virtual ?? false),
            isTenantScoped: (bool) ($row->is_tenant_scoped ?? true),
            subInstituteId: isset($row->sub_institute_id) ? (int) $row->sub_institute_id : null,
        );
    }

    /**
     * True when this entity can be read from a real table. Virtual entities
     * (Signal, Case, Hypothesis, Decision, Outcome) answer false and are served
     * from the ai_* tables instead.
     */
    public function isQueryable(): bool
    {
        return ! $this->isVirtual && $this->sourceTable !== null;
    }

    /**
     * The columns a lookup should select. Falls back to the primary key alone when
     * no label column is mapped, so a half-configured entity degrades rather than throws.
     */
    public function selectColumns(): array
    {
        $columns = [$this->primaryKeyColumn ?: 'id'];

        if ($this->labelColumn) {
            // A label may be an expression such as "CONCAT(first_name,' ',last_name)".
            $columns[] = $this->labelColumn;
        }

        foreach ($this->attributes as $attribute) {
            if (! empty($attribute['column'])) {
                $columns[] = $attribute['column'];
            }
        }

        return array_values(array_unique($columns));
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'domain' => $this->domain,
            'category' => $this->category,
            'description' => $this->description,
            'source_table' => $this->sourceTable,
            'is_virtual' => $this->isVirtual,
            'is_tenant_scoped' => $this->isTenantScoped,
            'queryable' => $this->isQueryable(),
            'attributes' => $this->attributes,
        ];
    }

    private static function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
