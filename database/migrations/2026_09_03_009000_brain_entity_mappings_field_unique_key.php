<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_03_000100_entity_mappings_field_unique_key.php
 * (read-only reference; that repository is not modified).
 *
 * Every table here is hpbrain_-prefixed. The Brain was designed to share a
 * database with the institute ERP, so nothing below collides with, alters or
 * duplicates an LMS table — Organization, Departments, People, Positions and
 * Students continue to live in the LMS's own tables and are reached through
 * hpbrain_entity_mappings.
 */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correct the uniqueness rule on hpbrain_entity_mappings.
 *
 * THE BUG. 2026_08_01_000004_entity_mappings declares
 *
 *     UNIQUE (tenant_id, source_system, source_entity)
 *
 * while the table stores ONE ROW PER FIELD — source_field and universal_field
 * are singular columns, EntityMappingRepository::create() writes a row per
 * field, and list() orders by source_field. Those two facts cannot both hold:
 * the constraint permits exactly one row per (tenant, system, entity), so a
 * tenant could map exactly one field of Person and no more. Mapping Person's
 * eleven universal fields was structurally impossible.
 *
 * It was never hit because the table had zero consumers until EntityResolver.
 * The in-memory test schema declares no unique index at all, so the suite could
 * not have caught it either; that omission is corrected in BuildsBrainSchema
 * alongside this migration.
 *
 * THE REPLACEMENT, and why it is not merely the old key plus universal_field.
 *
 *     UNIQUE (tenant_id, universal_entity, universal_field)
 *
 * The resolver's contract is that a universal field resolves to exactly one
 * source column for a tenant. Keying on source_system would allow two systems
 * to both claim Person.email, and the resolver would have to pick one — silently
 * and arbitrarily. Forbidding that at the database is better than resolving it
 * in code, because the ambiguity is a configuration error and the only correct
 * response to it is to refuse the write.
 *
 * SAFETY. The table does not exist on the live shared database (hp_erp) — this
 * migration has never run there. Both steps are guarded by lookups, so running
 * against a database that has the table, lacks the old index, or already has
 * the new one is a metadata read and nothing else.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_entity_mappings';

    private const OLD_INDEX = 'entity_mappings_tenant_source_entity_unique';

    private const NEW_INDEX = 'entity_mappings_tenant_universal_field_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        // Drop first. Adding the new key while the old one stands would leave a
        // table that still rejects the second field of any entity.
        if ($this->indexExists(self::OLD_INDEX)) {
            DB::unprepared('ALTER TABLE '.self::TABLE.' DROP INDEX '.self::OLD_INDEX);
        }

        if (! $this->indexExists(self::NEW_INDEX)) {
            DB::unprepared(
                'ALTER TABLE '.self::TABLE.' ADD UNIQUE KEY '.self::NEW_INDEX
                .' (tenant_id, universal_entity, universal_field)'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->indexExists(self::NEW_INDEX)) {
            DB::unprepared('ALTER TABLE '.self::TABLE.' DROP INDEX '.self::NEW_INDEX);
        }

        // The original key is deliberately NOT restored. Re-adding it would
        // reject the multi-field mapping rows this migration exists to permit,
        // so a down() that restored it could not run on any database carrying
        // real mappings.
    }

    private function indexExists(string $name): bool
    {
        return DB::select(
            'SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
              LIMIT 1',
            [self::TABLE, $name],
        ) !== [];
    }
};
