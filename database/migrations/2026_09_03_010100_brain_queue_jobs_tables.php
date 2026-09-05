<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_10_000200_queue_jobs_tables.php
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
 * The framework queue tables.
 *
 * WHY THEY WERE MISSING. .env sets QUEUE_CONNECTION=database, but this project
 * has no config/queue.php and never ran `queue:table`, so the connection was
 * configured and unprovisioned: dispatching anything would have failed with
 * "Base table or view not found: hpbrain… jobs". Nothing dispatched, so nobody
 * noticed. Ingestion is the first caller that genuinely needs a worker — a
 * 162,810-row commit is minutes of work and cannot sit inside a request.
 *
 * STANDARD LARAVEL SCHEMA, unmodified. These are framework-owned tables read by
 * the queue worker itself; deviating from the expected column set breaks
 * `queue:work`. They deliberately do NOT carry a tenant_id: a queue row is
 * infrastructure, and the tenant lives inside the serialised job payload where
 * the job's own constructor put it. Tenant isolation is enforced where it has
 * always been enforced — in the service the job calls, against the tenant id
 * the job was created with.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        // Without this, a job that exhausts its retries vanishes. For an
        // ingestion run that is the difference between "the import failed and
        // here is the exception" and silence.
        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
    }
};
