<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported verbatim from database/migrations/004_person.sql.
 *
 * The SQL is kept as raw statements rather than rewritten into Schema
 * builder calls. That is deliberate: this DDL has been executed against a
 * live MySQL 8 server and carries three hard-won corrections that a
 * Schema-builder rewrite would silently undo —
 *   1. every table is hpbrain_-prefixed (the Brain shares a database with
 *      the institute ERP and must not collide with it),
 *   2. every id/foreign-key column is VARCHAR(36), because MySQL rejects
 *      TEXT in a key (error 1170),
 *   3. every ratio column states explicit precision, because a bare
 *      NUMERIC becomes DECIMAL(10,0) in MySQL and rounds confidence to an
 *      integer.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_people (
  id                  VARCHAR(36) PRIMARY KEY,
  tenant_id           VARCHAR(36) NOT NULL,
  employee_id         VARCHAR(36) NOT NULL,
  first_name          TEXT NOT NULL,
  last_name           TEXT NOT NULL,
  display_name        TEXT,
  email               VARCHAR(255) NOT NULL,
  phone               TEXT,
  profile_photo       TEXT,
  gender              TEXT,
  date_of_birth       DATE,
  employment_type     TEXT NOT NULL DEFAULT \'full_time\',
  employment_status   TEXT NOT NULL DEFAULT \'active\',
  joining_date        DATE,
  department_id       VARCHAR(36),
  manager_id          VARCHAR(36),
  designation         TEXT,
  location            TEXT,
  reporting_manager_id VARCHAR(36),
  org_id              VARCHAR(36) NOT NULL,
  status              VARCHAR(255) NOT NULL DEFAULT \'active\',
  created_by          TEXT NOT NULL,
  created_date        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        DB::unprepared('CREATE UNIQUE INDEX idx_people_tenant_employee_id ON hpbrain_people (tenant_id, employee_id)');
        DB::unprepared('CREATE UNIQUE INDEX idx_people_tenant_email ON hpbrain_people (tenant_id, email)');
        DB::unprepared('CREATE INDEX idx_people_tenant_id ON hpbrain_people (tenant_id)');
        DB::unprepared('CREATE INDEX idx_people_org_id ON hpbrain_people (org_id)');
        DB::unprepared('CREATE INDEX idx_people_department_id ON hpbrain_people (department_id)');
        DB::unprepared('CREATE INDEX idx_people_manager_id ON hpbrain_people (manager_id)');
        DB::unprepared('CREATE INDEX idx_people_status ON hpbrain_people (status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_people');
    }
};
