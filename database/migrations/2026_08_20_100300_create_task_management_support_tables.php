<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management migration, stage 1 of 2 (migrations + models only).
 *
 * Ported from hp_erp's `2026_08_04_140000_create_task_management_support_tables.php`.
 * Data stores for comments, audit logs, notifications, subtasks, time
 * tracking, templates, recurrence, attachment versions, idempotency keys and
 * deadline extensions.
 *
 * Deliberately NOT ported: the source's event-sourcing tables (g2g_event_store,
 * g2g_audit_log) — they don't exist in this target, and the established
 * pattern here (see `s_performance_activity_log` in the TalentManagement
 * precedent) is a flat log table written directly by application code, not
 * an event-sourced projection. `task_management_audit_logs` below is
 * designed as a plain directly-written table: no event_id/replay columns,
 * since there is no event store to replay from.
 *
 * JSON-ish payloads (`audit_logs.before`, `templates.payload`) are stored as
 * longText rather than native JSON, matching the TalentManagement precedent.
 *
 * No `syear` on these tables: the source schema does not carry SYEAR/syear
 * here either — these are task-scoped, not year-scoped, and derive their
 * academic year via the parent `task` row.
 */
return new class extends Migration
{
    /** Skips tables a previously interrupted run already created. */
    private function createIfMissing(string $table, \Closure $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }

    public function up(): void
    {
        $this->createIfMissing('task_management_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('author_id')->index();
            $table->text('content');
            $table->timestamps();

            $table->index(['task_id', 'sub_institute_id'], 'tm_comments_task_tenant_idx');
        });

        $this->createIfMissing('task_management_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('task_id')->index();
            $table->string('event', 50)->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            // The pre-change values that matter for "who changed what".
            $table->longText('before')->nullable();
            $table->timestamp('created_at');

            $table->index(['sub_institute_id', 'created_at'], 'tm_audit_logs_tenant_time_idx');
        });

        $this->createIfMissing('task_management_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('task_id')->nullable()->index();
            $table->string('type', 50)->index();
            $table->string('title', 191);
            $table->text('body')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');

            // Named: the auto-generated name exceeds MySQL's 64-char limit.
            $table->index(['user_id', 'sub_institute_id', 'read_at'], 'tm_notifications_user_tenant_read_idx');
        });

        $this->createIfMissing('task_management_subtasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('task_id')->index();
            $table->string('title', 191);
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['task_id', 'sub_institute_id'], 'tm_subtasks_task_tenant_idx');
        });

        $this->createIfMissing('task_management_time_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            // Denormalised on stop, so reports never re-derive durations.
            $table->unsignedInteger('minutes')->nullable();
            $table->timestamp('created_at');

            $table->index(['task_id', 'sub_institute_id'], 'tm_time_entries_task_tenant_idx');
            $table->index(['user_id', 'ended_at'], 'tm_time_entries_user_ended_idx');
        });

        $this->createIfMissing('task_management_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('name', 191);
            // The task fields the template pre-fills, as submitted.
            $table->longText('payload');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        $this->createIfMissing('task_management_recurrences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('task_id')->unique();
            $table->string('frequency', 20)->index(); // daily | weekly | monthly
            $table->unsignedSmallInteger('interval')->default(1);
            $table->date('until')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('task_management_attachment_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedInteger('version');
            $table->string('file_name', 191);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_type', 100)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            // Set when this version was created by restoring an older one.
            $table->unsignedInteger('restored_from')->nullable();
            $table->timestamp('created_at');

            $table->unique(['task_id', 'version'], 'tm_attachment_versions_task_version_unique');
        });

        $this->createIfMissing('task_management_idempotency_keys', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->string('idempotency_key', 100);
            $table->unsignedBigInteger('task_id')->index();
            $table->timestamp('created_at');

            // Same key, same tenant -> same task, never a duplicate.
            // Named: the auto-generated name exceeds MySQL's 64-char limit.
            $table->unique(['sub_institute_id', 'idempotency_key'], 'tm_idem_tenant_key_unique');
        });

        $this->ensureNotificationsIndex();

        $this->createIfMissing('task_deadline_extensions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('requested_by')->index();
            $table->date('requested_date');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->unsignedBigInteger('decided_by')->nullable()->index();
            $table->timestamp('decided_on')->nullable();
            $table->text('decision_remarks')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'status'], 'tm_deadline_ext_task_status_idx');
            $table->index(['sub_institute_id', 'status'], 'tm_deadline_ext_tenant_status_idx');
        });
    }

    /** Heals the half-created state an interrupted earlier run left behind. */
    private function ensureNotificationsIndex(): void
    {
        if (! Schema::hasTable('task_management_notifications')) {
            return;
        }

        // Schema::getIndexes() is not available on this project's installed
        // framework version (added upstream later than what's in composer.lock
        // here) — query information_schema directly instead, which works
        // identically across MySQL/MariaDB regardless of Laravel version.
        $exists = (int) DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'task_management_notifications')
            ->where('index_name', 'tm_notifications_user_tenant_read_idx')
            ->count() > 0;

        if (! $exists) {
            Schema::table('task_management_notifications', function (Blueprint $table) {
                $table->index(['user_id', 'sub_institute_id', 'read_at'], 'tm_notifications_user_tenant_read_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_deadline_extensions');
        Schema::dropIfExists('task_management_idempotency_keys');
        Schema::dropIfExists('task_management_attachment_versions');
        Schema::dropIfExists('task_management_recurrences');
        Schema::dropIfExists('task_management_templates');
        Schema::dropIfExists('task_management_time_entries');
        Schema::dropIfExists('task_management_subtasks');
        Schema::dropIfExists('task_management_notifications');
        Schema::dropIfExists('task_management_audit_logs');
        Schema::dropIfExists('task_management_comments');
    }
};
