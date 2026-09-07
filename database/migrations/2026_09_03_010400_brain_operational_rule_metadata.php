<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_13_000200_operational_rule_metadata.php
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
use Illuminate\Support\Facades\Schema;

/**
 * Human-approved root-cause metadata for code-held operational rules.
 *
 * hpbrain_signal_rules already carries these columns for row-held rules. The
 * aggregate operational rules cannot live there without fictional predicate
 * columns, so their approvals live here instead and are read through the same
 * resolver as row-held rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hpbrain_operational_rule_metadata')) {
            return;
        }

        Schema::create('hpbrain_operational_rule_metadata', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 36);
            $table->string('rule_key', 191);
            $table->string('root_cause_family', 100)->nullable();
            $table->decimal('hypothesis_confidence', 6, 4)->nullable();
            $table->text('recommended_action')->nullable();
            $table->text('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('created_by');
            $table->timestamp('created_date')->useCurrent();
            $table->timestamp('updated_date')->nullable();

            $table->unique(['tenant_id', 'rule_key'], 'operational_rule_metadata_tenant_rule_unique');
            $table->index(['tenant_id', 'root_cause_family'], 'operational_rule_metadata_family_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_operational_rule_metadata');
    }
};
