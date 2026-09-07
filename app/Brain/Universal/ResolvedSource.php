<?php

namespace App\Brain\Universal;

/**
 * Where one universal entity actually lives for one tenant.
 *
 * Ported from hp-enterprise-brain/app/Domain/Universal/ResolvedSource.php.
 * Promoted readonly constructor properties are PHP 8.1; this project targets
 * 8.0, so the same immutability is expressed as public properties assigned once
 * in the constructor and never written again.
 *
 * Immutable and query-free by design: it describes a source, it does not read
 * from one. Callers build their own Query Builder statements from it, which
 * keeps data access in the repositories.
 *
 * TWO RESERVED UNIVERSAL FIELDS. 'id' and 'tenantKey' are bindings rather than
 * data: without them the resolver cannot identify a row or confine a read to one
 * tenant.
 *
 * AN ABSENT FIELD IS NOT AN EMPTY ONE. has() returning false means the source
 * system has no column for that concept — hrms_job_titles has no vacancy flag,
 * so Position.isVacant is unmapped for a school tenant. That is a fact about the
 * ERP, and the honest rendering of it is "never measured", not false and not
 * zero. field() throws rather than returning null so the distinction cannot be
 * lost by accident downstream.
 */
final class ResolvedSource
{
    /** @var string */
    public $tenantId;

    /** @var string */
    public $entity;

    /** @var string */
    public $table;

    /** @var string */
    public $sourceSystem;

    /** @var string */
    public $tenantKey;

    /** @var string */
    public $primaryKey;

    /**
     * @var array<string, array{column: string, type: string, expression: mixed, lookupTable: string|null}>
     *      keyed by universal field name
     */
    private $fields;

    /**
     * @param array<string, array{column: string, type: string, expression: mixed, lookupTable: string|null}> $fields
     */
    public function __construct(
        string $tenantId,
        string $entity,
        string $table,
        string $sourceSystem,
        string $tenantKey,
        string $primaryKey,
        array $fields
    ) {
        $this->tenantId = $tenantId;
        $this->entity = $entity;
        $this->table = $table;
        $this->sourceSystem = $sourceSystem;
        $this->tenantKey = $tenantKey;
        $this->primaryKey = $primaryKey;
        $this->fields = $fields;
    }

    /** The source column backing a universal field. */
    public function field(string $universalField): string
    {
        if (! isset($this->fields[$universalField])) {
            throw UnsupportedEntityException::forField(
                $this->tenantId,
                $this->entity,
                $universalField,
                $this->universalFields()
            );
        }

        return $this->fields[$universalField]['column'];
    }

    public function has(string $universalField): bool
    {
        return isset($this->fields[$universalField]);
    }

    /** `table.column` — for joins and selects that need disambiguation. */
    public function qualified(string $universalField): string
    {
        return $this->table.'.'.$this->field($universalField);
    }

    /**
     * Full mapping detail, for callers that must honour mapping_type rather than
     * just read a column.
     *
     * @return array{column: string, type: string, expression: mixed, lookupTable: string|null}
     */
    public function mapping(string $universalField): array
    {
        // Routed through field() so the unmapped case raises the same named
        // exception here as everywhere else.
        $this->field($universalField);

        return $this->fields[$universalField];
    }

    public function isDirect(string $universalField): bool
    {
        return $this->mapping($universalField)['type'] === 'direct';
    }

    /** @return array<int, string> sorted, so error messages are stable */
    public function universalFields(): array
    {
        $names = array_keys($this->fields);
        sort($names);

        return $names;
    }

    /**
     * Source columns for a set of universal fields, skipping the unmapped ones.
     *
     * The common shape at a call site is "select whichever of these the tenant
     * actually has". Doing it here keeps has()/field() pairs out of every
     * controller.
     *
     * @param  array<int, string>  $universalFields
     * @return array<string, string> universal field => source column
     */
    public function columns(array $universalFields): array
    {
        $out = [];

        foreach ($universalFields as $name) {
            if ($this->has($name)) {
                $out[$name] = $this->fields[$name]['column'];
            }
        }

        return $out;
    }
}
