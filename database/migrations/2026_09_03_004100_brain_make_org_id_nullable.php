<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_07_28_043303_make_org_id_nullable.php
 * (read-only reference; that repository is not modified).
 *
 * Every table here is hpbrain_-prefixed. The Brain was designed to share a
 * database with the institute ERP, so nothing below collides with, alters or
 * duplicates an LMS table — Organization, Departments, People, Positions and
 * Students continue to live in the LMS's own tables and are reached through
 * hpbrain_entity_mappings.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'hpbrain_signals', 'hpbrain_capabilities', 'hpbrain_departments',
            'hpbrain_people', 'hpbrain_process_definitions', 'hpbrain_context_entities',
            'hpbrain_reasoning_patterns', 'hpbrain_eso_definitions', 'hpbrain_telemetry_events',
        ];
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'org_id')) {
                DB::unprepared("ALTER TABLE `{$table}` MODIFY COLUMN `org_id` VARCHAR(36) NULL");
            }
        }
    }
    public function down(): void {}
};