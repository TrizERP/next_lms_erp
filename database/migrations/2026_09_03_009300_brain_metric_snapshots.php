<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_03_000400_metric_snapshots.php
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
 * Daily metric snapshots — the Brain's memory of its own numbers.
 *
 * Every figure the system produced was instantaneous. It could say "42 open
 * signals" and never "42, down from 61 last week", so no screen could show
 * MOVEMENT and the Intelligence Score could not be told from a coin-flip that
 * happened to land on 62 twice.
 *
 * value is DECIMAL(18,4) and confidence DECIMAL(6,4). An unqualified NUMERIC
 * becomes DECIMAL(10,0) on MySQL and would round every confidence to an integer.
 *
 * value IS NULLABLE AND THAT IS THE POINT. A metric with no denominator — a
 * reuse rate on a tenant with no learnings, a deficit with no assessments — is
 * stored as NULL, not 0. A reuse rate of null is not a reuse rate of zero, and a
 * time series that silently converts one to the other draws a flat line at the
 * bottom of the chart and calls it a measurement.
 *
 * sample_n is what lets a reader tell a rate of 1.0 over two cases from the same
 * rate over two hundred.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE TABLE IF NOT EXISTS hpbrain_metric_snapshots (
  id            VARCHAR(36) PRIMARY KEY,
  tenant_id     VARCHAR(36) NOT NULL,
  snapshot_date DATE NOT NULL,
  metric_key    VARCHAR(191) NOT NULL,
  dimension_key VARCHAR(191) NULL,
  value         DECIMAL(18,4) NULL,
  confidence    DECIMAL(6,4) NULL,
  sample_n      INT NULL,
  created_date  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT metric_snapshots_unique UNIQUE (tenant_id, snapshot_date, metric_key, dimension_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        DB::unprepared('CREATE INDEX idx_metric_snapshots_series ON hpbrain_metric_snapshots (tenant_id, metric_key, snapshot_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hpbrain_metric_snapshots');
    }
};
