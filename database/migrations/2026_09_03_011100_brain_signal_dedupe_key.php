<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_19_000100_signal_dedupe_key.php
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
 * Make the database, not a convention, the thing that refuses a duplicate
 * ingested signal.
 *
 * WHAT WAS THERE BEFORE. hpbrain_operational_records has carried
 * UNIQUE (tenant_id, dataset, natural_key) since it was created, and that is why
 * the 398,831 imported Lions rows contain no duplicate receipts. hpbrain_signals
 * had no equivalent: its only protection was that IngestionService derived the
 * primary key deterministically. That derivation included the file's row NUMBER,
 * so two exports containing the same record produced two ids and two rows, and
 * nothing at the storage layer objected. On tenant 1000010 the fee source holds
 * 10,430 signals against 10,376 distinct external references.
 *
 * WHAT THIS ADDS.
 *   dedupe_key CHAR(64) NULL — the business identity of an ingested row:
 *   sha256(sourceKey | external_ref | title | owner | state), each part
 *   trimmed, whitespace-collapsed and case-folded. Written by
 *   IngestionService::dedupeKey(); see businessIdentity() there for why the
 *   external reference ALONE is not the identity (tenant 1000010's fee export
 *   carries receipt 4707 for two different children).
 *
 *   UNIQUE (tenant_id, dedupe_key) — tenant first, because that is the column
 *   every read already filters on and a duplicate is only ever a duplicate
 *   WITHIN one organization. Two schools may both hold a student 10821 and they
 *   remain two rows, permanently.
 *
 * WHY NULLABLE, AND WHY THAT IS NOT A LOOPHOLE. MySQL permits repeated NULLs in
 * a unique index. Signals raised by rules or entered by hand are not "the same
 * fact observed again" and have no business key to compute; they stay NULL and
 * are unaffected. Only ingested rows claim identity, and among those the
 * constraint is absolute.
 *
 * THE INDEX IS ADDED ONLY WHEN THE DATA CAN CARRY IT. Existing duplicates would
 * make CREATE UNIQUE INDEX fail, and the correct response to that is to
 * consolidate them — deliberately, with their evidence and case links
 * repointed — not to drop the constraint so the migration passes. So this
 * migration backfills what it safely can, adds the index if the result is
 * clean, and otherwise leaves the column in place and says exactly which
 * command to run:
 *
 *     php artisan brain:dedupe-signals --tenant=<id>          (dry run)
 *     php artisan brain:dedupe-signals --tenant=<id> --apply
 *
 * Re-running this migration after that cleanup adds the index. It is written to
 * be re-runnable for that reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hpbrain_signals')) {
            return;
        }

        if (! Schema::hasColumn('hpbrain_signals', 'dedupe_key')) {
            Schema::table('hpbrain_signals', function ($table) {
                $table->char('dedupe_key', 64)->nullable()->after('tenant_id');
            });
        }

        $this->backfill();

        if ($this->hasIndex('signals_dedupe_unique')) {
            return;
        }

        $collisions = $this->collisions();

        if ($collisions > 0) {
            // Not silent, and not fatal. A migration that refused to finish
            // would block every other pending change over data that needs a
            // human decision about which copy is canonical.
            echo "  hpbrain_signals: {$collisions} duplicate dedupe_key group(s) remain; UNIQUE index NOT created.\n";
            echo "  Run `php artisan brain:dedupe-signals --apply`, then re-run this migration.\n";

            return;
        }

        Schema::table('hpbrain_signals', function ($table) {
            $table->unique(['tenant_id', 'dedupe_key'], 'signals_dedupe_unique');
        });
    }

    /**
     * Give existing ingested rows the identity they would have been written
     * with today, so a file imported before this change and re-imported after
     * it is recognised as the same data rather than added again.
     *
     * IT IS DERIVABLE, AND THAT IS WHY THE IDENTITY IS THE MAPPED FIELDS. Every
     * component was already recorded: metadata.provenance.sourceKey,
     * metadata.externalRef, metadata.title, metadata.owner, and the state in
     * `classification`. Had the identity been a hash of the raw source row, no
     * backfill would have been possible at all — the row is not in the database
     * — and every pre-existing import would have been invisible to the
     * constraint.
     *
     * THE SQL MIRRORS canonicalToken() EXACTLY, and must keep doing so: LOWER
     * of TRIM of REGEXP_REPLACE(…, '\\s+', ' '). A backfilled key that differs
     * from the one the service computes would be worse than no key — the
     * re-import would not match, and the row would be written twice with two
     * different keys.
     *
     * JSON null READS BACK AS THE STRING 'null'. JSON_UNQUOTE of a JSON null is
     * the four characters n-u-l-l, not SQL NULL, so `IS NOT NULL` does not
     * filter it — on tenant 7 that turned 351 distinct observations into one
     * identity. It is normalised to the empty string here, exactly as the
     * service canonicalises an absent mapped field to ''.
     */
    private function backfill(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // 0x1f — the unit separator businessIdentity() joins its parts with. A
        // character no source value contains, so no combination of field values
        // can spell another combination's identity.
        DB::statement(<<<'SQL'
            UPDATE hpbrain_signals
               SET dedupe_key = SHA2(
                     CONCAT(
                       JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.provenance.sourceKey')), '|',
                       CONCAT_WS(CHAR(31),
                         LOWER(TRIM(REGEXP_REPLACE(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.externalRef')), 'null'), ''), '[[:space:]]+', ' '))),
                         LOWER(TRIM(REGEXP_REPLACE(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.title')),       'null'), ''), '[[:space:]]+', ' '))),
                         LOWER(TRIM(REGEXP_REPLACE(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.owner')),       'null'), ''), '[[:space:]]+', ' '))),
                         LOWER(TRIM(REGEXP_REPLACE(COALESCE(NULLIF(classification, 'UNDETERMINED'), ''),                       '[[:space:]]+', ' ')))
                       )
                     ), 256)
             WHERE dedupe_key IS NULL
               AND created_by = 'ingestion'
               AND JSON_VALID(metadata)
               AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.provenance.sourceKey')) IS NOT NULL
        SQL);
    }

    private function collisions(): int
    {
        $rows = DB::table('hpbrain_signals')
            ->selectRaw('tenant_id, dedupe_key')
            ->whereNotNull('dedupe_key')
            ->groupBy('tenant_id', 'dedupe_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $rows->count();
    }

    private function hasIndex(string $name): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        return DB::select('SHOW INDEX FROM hpbrain_signals WHERE Key_name = ?', [$name]) !== [];
    }

    public function down(): void
    {
        if (! Schema::hasTable('hpbrain_signals')) {
            return;
        }

        if ($this->hasIndex('signals_dedupe_unique')) {
            Schema::table('hpbrain_signals', function ($table) {
                $table->dropUnique('signals_dedupe_unique');
            });
        }

        if (Schema::hasColumn('hpbrain_signals', 'dedupe_key')) {
            Schema::table('hpbrain_signals', function ($table) {
                $table->dropColumn('dedupe_key');
            });
        }
    }
};
