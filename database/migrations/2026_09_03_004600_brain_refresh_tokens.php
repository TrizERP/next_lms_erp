<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_07_31_000100_refresh_tokens.php
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

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hpbrain_refresh_tokens')) {
            Schema::create('hpbrain_refresh_tokens', function ($table) {
                $table->string('jti')->primary();
                $table->string('tenant_id');
                $table->string('user_id');
                $table->timestamp('expires_at');
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['tenant_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_refresh_tokens');
    }
};
