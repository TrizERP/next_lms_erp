<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_01_02_000000_capability_state.php
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
 * Architecture Invariant 6 — every capability carries an explicit state.
 *
 * Adds state + provenance to hpbrain_capability_proficiency, which previously
 * stored only numeric 0-5 levels. Existing rows are BACKFILLED, never dropped:
 * a legacy level with no evidence reference maps to 'Asserted', because that is
 * all such a row can honestly support (see CapabilityState::fromLegacyLevel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hpbrain_capability_proficiency', function ($table) {
            $table->string('capability_state', 20)->default('Unknown')->after('attitude_level');
            $table->string('evidence_ref', 36)->nullable()->after('capability_state');
            $table->string('state_source', 100)->nullable()->after('evidence_ref');
            $table->timestamp('state_changed_date')->nullable()->after('state_source');
            $table->text('state_change_reason')->nullable()->after('state_changed_date');
            $table->index(['tenant_id', 'capability_state'], 'idx_cap_prof_state');
        });

        // Backfill. Conservative by design — never invent a state the row
        // cannot evidence.
        DB::table('hpbrain_capability_proficiency')
            ->whereNotNull('evidence_confidence')
            ->update(['capability_state' => 'Assessed', 'state_source' => 'backfill:has_evidence_confidence']);

        DB::table('hpbrain_capability_proficiency')
            ->whereNull('evidence_confidence')
            ->where(function ($q) {
                foreach (['knowledge', 'ability', 'skill', 'behaviour', 'attitude'] as $d) {
                    $q->orWhere($d.'_level', '>', 0);
                }
            })
            ->update(['capability_state' => 'Asserted', 'state_source' => 'backfill:level_without_provenance']);
    }

    public function down(): void
    {
        Schema::table('hpbrain_capability_proficiency', function ($table) {
            $table->dropIndex('idx_cap_prof_state');
            $table->dropColumn([
                'capability_state', 'evidence_ref', 'state_source',
                'state_changed_date', 'state_change_reason',
            ]);
        });
    }
};
