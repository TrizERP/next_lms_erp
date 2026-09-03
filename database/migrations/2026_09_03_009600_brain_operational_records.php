<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_04_000300_operational_records.php
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
 * ONE new table. No ALTER on anything that already exists.
 *
 * WHY A TABLE AT ALL
 * ------------------
 * The Brain reads Organization / Department / Person live from the ERP and owns
 * everything it reasons WITH (docs/ERP-TO-BRAIN-MAPPING.md). FiberValley's
 * workbooks are neither: a complaint ticket is not a Person, and forcing 65,268
 * of them into tbluser would corrupt the master-data semantics every other
 * organization depends on. They are operational facts the Brain reasons over,
 * so by the existing rule they belong in an hpbrain_* table.
 *
 * WHY GENERIC RATHER THAN hpbrain_fibervalley_complaints
 * ------------------------------------------------------
 * A per-organization table is a new subsystem by another name — it needs its
 * own repository, its own rules, its own migration every time a workbook shape
 * changes. This table is keyed by (tenant_id, dataset) and carries the columns
 * that operational records of ANY kind share: when it happened, when it closed,
 * who owned it, who supervised, where, what state, one metric. Anything
 * dataset-specific lives in `payload`. FiberValley's five datasets fit it, and
 * so does the next organization's, without further DDL.
 *
 * WHY THESE COLUMNS ARE PROMOTED OUT OF payload
 * ---------------------------------------------
 * Exactly the ones the signal rules filter and group by. JSON extraction cannot
 * use an index in MySQL 8 without a generated column, and the complaint rules
 * group 65k rows by zone and by month on every evaluation. Everything the rules
 * only ever display — subscriber id, remarks, POP, junction box — stays in
 * payload where it costs nothing.
 *
 * IDEMPOTENCY
 * -----------
 * UNIQUE (tenant_id, dataset, natural_key) is the whole re-import story. The
 * same workbook imported twice updates in place; next month's larger export
 * inserts only what is new. row_hash lets the loader skip a row whose content
 * has not changed at all, which turns a re-run of an unchanged 65k-row sheet
 * into 65k cheap comparisons instead of 65k writes.
 *
 * KEY LENGTH: 36 + 64 + 191 = 291 chars. At utf8mb4's 4 bytes that is 1,164
 * bytes, inside InnoDB's 3,072-byte limit for DYNAMIC row format. natural_key
 * is 191 rather than 255 for exactly this reason — the longest real key in the
 * FiberValley data is a 10-character ticket number, so nothing is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_operational_records (
  id               VARCHAR(36) PRIMARY KEY,
  tenant_id        VARCHAR(36) NOT NULL,
  org_id           VARCHAR(36) NULL,
  dataset          VARCHAR(64) NOT NULL,
  natural_key      VARCHAR(191) NOT NULL,
  source_file      VARCHAR(255) NULL,
  source_row       INT NULL,
  occurred_at      DATETIME NULL,
  closed_at        DATETIME NULL,
  status           VARCHAR(64) NULL,
  category         VARCHAR(191) NULL,
  sub_category     VARCHAR(191) NULL,
  owner_name       VARCHAR(191) NULL,
  supervisor_name  VARCHAR(191) NULL,
  zone             VARCHAR(128) NULL,
  area             VARCHAR(128) NULL,
  subject_ref      VARCHAR(191) NULL,
  metric_value     DECIMAL(14,4) NULL,
  metric_unit      VARCHAR(20) NULL,
  quantity         INT NULL,
  payload          JSON NULL,
  row_hash         CHAR(64) NOT NULL,
  import_job_id    VARCHAR(36) NULL,
  created_date     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_date     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT operational_records_natural_key_unique UNIQUE (tenant_id, dataset, natural_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        DB::unprepared('CREATE INDEX idx_oprec_tenant_dataset_occurred ON hpbrain_operational_records (tenant_id, dataset, occurred_at)');
        DB::unprepared('CREATE INDEX idx_oprec_tenant_dataset_status ON hpbrain_operational_records (tenant_id, dataset, status)');
        DB::unprepared('CREATE INDEX idx_oprec_tenant_dataset_zone ON hpbrain_operational_records (tenant_id, dataset, zone)');
        DB::unprepared('CREATE INDEX idx_oprec_tenant_dataset_owner ON hpbrain_operational_records (tenant_id, dataset, owner_name)');
        DB::unprepared('CREATE INDEX idx_oprec_tenant_dataset_subject ON hpbrain_operational_records (tenant_id, dataset, subject_ref)');
        DB::unprepared('CREATE INDEX idx_oprec_import_job ON hpbrain_operational_records (import_job_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_operational_records');
    }
};
