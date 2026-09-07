<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_04_000200_signal_rule_key.php
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
 * Record which rule raised a signal, in a column rather than inside JSON.
 *
 * The rule key was already being written, but only into the metadata blob. That
 * makes it unusable for the one question the detector has to ask on every run —
 * "have I already raised this?" — for two reasons:
 *
 *   1. It cannot be indexed usefully, and this lookup happens once per rule per
 *      tenant per hour.
 *   2. Reading it needs engine-specific SQL. JSON_UNQUOTE(JSON_EXTRACT(...))
 *      works on MySQL and MariaDB and does not exist on SQLite, so the query
 *      threw on the test database, was swallowed by the per-rule catch, and
 *      every rule silently counted as skipped.
 *
 * The metadata copy is left in place; it is what the UI already reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hpbrain_signals') || Schema::hasColumn('hpbrain_signals', 'rule_key')) {
            return;
        }

        Schema::table('hpbrain_signals', function ($table) {
            // Nullable because a signal need not come from a rule — the loop
            // raises them from reasoning too, and those have no rule key. Null
            // here means "not rule-derived", which is why dedupe must never
            // match on it being absent.
            $table->string('rule_key', 100)->nullable()->after('classification');
        });

        // Leading with tenant_id and status matches how the detector filters:
        // one tenant, unresolved only, then narrowed to a rule.
        Schema::table('hpbrain_signals', function ($table) {
            $table->index(['tenant_id', 'status', 'rule_key'], 'idx_signals_tenant_status_rule');
        });

        $this->backfill();
    }

    /**
     * Copy the key out of metadata for signals raised before this column
     * existed, so the first scheduled run does not duplicate every one of them.
     *
     * MySQL only — the test database builds its schema directly and has no rows
     * to carry forward.
     */
    private function backfill(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            $updated = DB::update(
                "UPDATE hpbrain_signals
                    SET rule_key = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.rule'))
                  WHERE rule_key IS NULL
                    AND JSON_VALID(metadata)
                    AND JSON_EXTRACT(metadata, '$.rule') IS NOT NULL"
            );

            logger()->info('signal_rule_key_backfilled', ['rows' => $updated]);
        } catch (\Throwable $e) {
            logger()->warning('signal_rule_key_backfill_failed', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hpbrain_signals') || ! Schema::hasColumn('hpbrain_signals', 'rule_key')) {
            return;
        }

        Schema::table('hpbrain_signals', function ($table) {
            $table->dropIndex('idx_signals_tenant_status_rule');
            $table->dropColumn('rule_key');
        });
    }
};
