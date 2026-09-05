<?php

namespace App\Brain\Universal;

use RuntimeException;
use Throwable;

/**
 * Raised when the vocabulary layer is asked for something a tenant has not
 * mapped.
 *
 * Ported from hp-enterprise-brain/app/Domain/Universal/UnsupportedEntityException.php.
 *
 * This exception is the whole point of the resolver. The alternative — falling
 * back to some default table when a mapping is missing — would make one
 * institute read another's rows and return them as its own. That is a
 * cross-tenant data leak, and it would present as a puzzling display bug rather
 * than as a security incident, which is exactly what makes it dangerous.
 * Throwing is the safe failure: loud, immediate, impossible to mistake for data.
 */
final class UnsupportedEntityException extends RuntimeException
{
    /** @var string */
    public $tenantId;

    /** @var string */
    public $entity;

    /** @var string|null */
    public $field;

    public function __construct(
        string $message,
        string $tenantId,
        string $entity,
        ?string $field = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->tenantId = $tenantId;
        $this->entity = $entity;
        $this->field = $field;
    }

    /** @param array<int, string> $known */
    public static function forEntity(string $tenantId, string $entity, array $known = []): self
    {
        $suffix = $known === []
            ? ' No entities are mapped for this tenant.'
            : ' Mapped entities: '.implode(', ', $known).'.';

        return new self(
            "No active entity mapping for '{$entity}' on tenant '{$tenantId}'.".$suffix
            .' Add rows to hpbrain_entity_mappings; the resolver will not fall back to another tenant\'s tables.',
            $tenantId,
            $entity
        );
    }

    /** @param array<int, string> $known */
    public static function forField(string $tenantId, string $entity, string $field, array $known = []): self
    {
        $suffix = $known === []
            ? ' No fields are mapped for this entity.'
            : ' Mapped fields: '.implode(', ', $known).'.';

        return new self(
            "Entity '{$entity}' on tenant '{$tenantId}' has no mapping for universal field '{$field}'.".$suffix
            .' Check has() before field() where the field is genuinely optional.',
            $tenantId,
            $entity,
            $field
        );
    }

    /**
     * Two source tables claim the same universal entity. The resolver cannot
     * choose between them, and guessing would bind every later query to an
     * arbitrary winner.
     *
     * @param array<int, string> $tables
     */
    public static function ambiguous(string $tenantId, string $entity, array $tables): self
    {
        sort($tables);

        return new self(
            "Entity '{$entity}' on tenant '{$tenantId}' is mapped to more than one source table: "
            .implode(', ', $tables).'. Exactly one source table per universal entity is required.',
            $tenantId,
            $entity
        );
    }

    /**
     * A mapping exists but omits a binding the resolver cannot work without —
     * the primary key or the tenant-scoping column.
     */
    public static function incomplete(string $tenantId, string $entity, string $missing): self
    {
        return new self(
            "Entity '{$entity}' on tenant '{$tenantId}' is missing the required binding '{$missing}'. "
            ."Every mapped entity needs a row for universal_field 'id' and one for 'tenantKey'; "
            .'without the latter the resolver cannot scope reads to the tenant.',
            $tenantId,
            $entity,
            $missing
        );
    }

    /**
     * The mappings table itself is absent — the Brain schema was never migrated
     * onto this database.
     *
     * Distinct from every case above, which all mean "migrated but not
     * configured". Raw, this surfaces as SQLSTATE 42S02 from whichever query
     * happened to run first, which names the table but not the reason and reads
     * like a code bug rather than a deployment step that was skipped.
     */
    public static function notInstalled(string $table, string $database, ?Throwable $previous = null): self
    {
        return new self(
            "The Brain vocabulary table '{$table}' does not exist in database '{$database}', so no entity "
            .'can be resolved and nothing that reads tenant data will work. '
            .'This database has not had the Brain schema migrated onto it: run `php artisan migrate` '
            .'followed by `php artisan db:seed --class=BrainEntityMappingSeeder`. '
            .'Check `php artisan migrate:status` first — pending migrations from other modules run too.',
            '',
            '',
            null,
            $previous
        );
    }
}
