<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_01_000032_import_logs.php
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
 * `row_number` IS BACKTICKED BECAUSE IT IS A RESERVED WORD.
 *
 * MariaDB 10.2 and MySQL 8.0 both introduced the ROW_NUMBER() window function
 * and reserved the identifier. Unquoted, this CREATE TABLE fails with
 *
 *     SQLSTATE[42000]: 1064 ... near 'row_number INT, action VARCHAR(50) ...'
 *
 * and because migrations stop at the first failure, it took the FIFTEEN
 * migrations after it down with it — including every table added in Phases 3
 * to 5. The suite never caught it: it builds its schema by hand on SQLite,
 * where row_number is an ordinary word.
 *
 * Found by running the migrations against a real MariaDB 10.11 instance, which
 * is the only place this bug exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_import_logs (
  id              VARCHAR(36) PRIMARY KEY,
  tenant_id       VARCHAR(36) NOT NULL,
  import_job_id   VARCHAR(36) NOT NULL,
  `row_number`    INT,
  action          VARCHAR(50) NOT NULL,
  entity_type     VARCHAR(100),
  entity_id       VARCHAR(36),
  data            JSON,
  error_message   TEXT,
  created_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE INDEX idx_import_logs_tenant_id ON hpbrain_import_logs (tenant_id)');
        DB::unprepared('CREATE INDEX idx_import_logs_import_job_id ON hpbrain_import_logs (import_job_id)');
        DB::unprepared('CREATE INDEX idx_import_logs_action ON hpbrain_import_logs (action)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_import_logs');
    }
};
