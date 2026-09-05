<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_07_29_000200_learning_domain.php
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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a learning the wedge it belongs to.
 *
 * hpbrain_learnings records what was learned but not what it was learned
 * ABOUT, so grounding — "which prior learnings bear on this question?" — had
 * nothing to filter on but confidence. MemoryGrounding::retrieveFor() already
 * filters on a `domain` column that has never existed; this is the column it
 * has been querying.
 *
 * NULLABLE ON PURPOSE. A learning with no domain is cross-domain: it must
 * ground every question, not none. Making the column NOT NULL with a default
 * ('general', 'unknown') would file every general lesson under one wedge and
 * hide it from all the others — the opposite of what memory is for. NULL here
 * means "applies everywhere", and the grounding query reads it that way.
 *
 * The index carries `reusable` because that is never not in the query: a failed
 * outcome is recorded so the organization learns from it and is never offered
 * back as a pattern to repeat (ADR-005), so grounding always reads
 * (tenant, domain, reusable) together.
 */
return new class extends Migration
{
    private const TABLE = 'hpbrain_learnings';

    public function up(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'domain')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                // VARCHAR(64), not TEXT: MySQL cannot index a TEXT column
                // without a prefix length (error 1170), and this column exists
                // to be indexed.
                $table->string('domain', 64)->nullable()->after('description');
            });
        }

        if (! $this->brainHasIndex(self::TABLE, 'idx_learnings_grounding')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['tenant_id', 'domain', 'reusable'], 'idx_learnings_grounding');
            });
        }
    }

    public function down(): void
    {
        if ($this->brainHasIndex(self::TABLE, 'idx_learnings_grounding')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('idx_learnings_grounding');
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn('domain');
        });
    }

    /**
     * Whether an index exists. Schema::hasIndex() only exists from Laravel 11;
     * this project runs Laravel 9, so the catalogue is queried directly.
     */
    private function brainHasIndex(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
              LIMIT 1',
            [$table, $index]
        );

        return $rows !== [];
    }
};
