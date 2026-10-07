<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Create the two outbox tables that every later Neo4j sync migration assumes
 * already exist (2026_08_21_100000, 2026_08_21_100100, 2026_09_04_140000 are
 * all ALTERs — there has never been a Schema::create for either table). On
 * this environment both tables already exist with live data, so this is
 * guarded to be a no-op here; on a fresh database it creates them with the
 * exact shape the live tables have today (confirmed via SHOW COLUMNS), so a
 * clean `migrate` produces tables the trigger migration can attach to.
 *
 * See LMS-AUDIT-184 / Blocker 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sync_log')) {
            Schema::create('sync_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('table_name', 155)->nullable();
                $table->string('operation_type', 155)->nullable();
                $table->unsignedInteger('record_id')->nullable();
                $table->longText('payload_json')->nullable();
                $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED'])->nullable();
                $table->integer('retry_count')->nullable()->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('processed_at')->nullable();
            });
        }

        if (! Schema::hasTable('neo4j_sync_queue')) {
            Schema::create('neo4j_sync_queue', function (Blueprint $table) {
                $table->increments('id');
                $table->enum('event_type', ['INSERT', 'UPDATE', 'DELETE'])->nullable();
                $table->string('source_table', 50)->nullable();
                $table->integer('source_id')->nullable();
                $table->string('rel_type', 50)->nullable();
                $table->string('target_table', 50)->nullable();
                $table->integer('old_target_id')->nullable();
                $table->integer('new_target_id')->nullable();
                $table->longText('edge_key')->nullable();
                $table->enum('status', ['pending', 'processing', 'done', 'failed'])->nullable()->default('pending');
                $table->integer('retry_count')->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('processed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('neo4j_sync_queue');
        Schema::dropIfExists('sync_log');
    }
};
