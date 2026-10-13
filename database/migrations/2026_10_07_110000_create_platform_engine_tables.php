<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables the platform engines write to: workflow runs, the notification delivery
 * log and file attachments. Each is guarded so it can be run again safely, and
 * down() deliberately drops nothing — this database is shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_workflow_runs')) {
            Schema::create('platform_workflow_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');
                $table->unsignedBigInteger('workflow_id');
                $table->string('flow_key', 191);
                $table->string('entity_type', 128);
                $table->string('entity_id', 128);
                $table->string('title', 255)->nullable();
                $table->json('payload')->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->string('requested_by_name', 191)->nullable();
                $table->string('status', 16)->default('pending')->comment('pending | approved | rejected | returned');
                $table->unsignedSmallInteger('current_step')->default(1);
                $table->timestamp('completed_at')->nullable();
                $table->boolean('is_sample')->default(false);
                $table->timestamps();
                $table->index(['sub_institute_id', 'status'], 'pwr_tenant_status_idx');
                $table->index(['sub_institute_id', 'flow_key'], 'pwr_tenant_flow_idx');
                $table->index(['entity_type', 'entity_id'], 'pwr_entity_idx');
            });
        }

        if (! Schema::hasTable('platform_workflow_run_steps')) {
            Schema::create('platform_workflow_run_steps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('run_id');
                $table->unsignedSmallInteger('step_order');
                $table->string('step_name', 191);
                $table->string('approver_type', 32);
                $table->string('approver', 120)->default('');
                $table->string('status', 16)->default('waiting')->comment('waiting | pending | approved | rejected | skipped | escalated');
                $table->unsignedBigInteger('assignee_user_id')->nullable();
                $table->unsignedBigInteger('acted_by')->nullable();
                $table->string('acted_by_name', 191)->nullable();
                $table->timestamp('acted_at')->nullable();
                $table->text('comment')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->string('on_breach', 32)->default('none');
                $table->boolean('allow_delegate')->default(false);
                $table->boolean('require_comment')->default(false);
                $table->timestamp('escalated_at')->nullable();
                $table->timestamps();
                $table->index(['run_id', 'step_order'], 'pwrs_run_order_idx');
                $table->index(['status', 'due_at'], 'pwrs_status_due_idx');
            });
        }

        if (! Schema::hasTable('platform_notification_log')) {
            Schema::create('platform_notification_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');
                $table->string('event_key', 191);
                $table->string('channel', 32);
                $table->string('recipient', 191);
                $table->string('subject', 255)->nullable();
                $table->text('body')->nullable();
                $table->string('status', 16)->default('queued')->comment('queued | sent | failed | skipped');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->string('provider_ref', 191)->nullable();
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->boolean('is_sample')->default(false);
                $table->timestamps();
                $table->index(['sub_institute_id', 'status'], 'pnl_tenant_status_idx');
                $table->index(['sub_institute_id', 'event_key'], 'pnl_tenant_event_idx');
                $table->index(['status', 'next_attempt_at'], 'pnl_retry_idx');
            });
        }

        if (! Schema::hasTable('platform_file_attachments')) {
            Schema::create('platform_file_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sub_institute_id');
                $table->string('entity_type', 128);
                $table->string('entity_id', 128);
                $table->string('original_name', 255);
                $table->string('mime', 128)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('storage_path', 500);
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('replaced_by')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->string('uploaded_by_name', 191)->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->boolean('is_sample')->default(false);
                $table->timestamps();
                $table->index(['sub_institute_id', 'entity_type', 'entity_id'], 'pfa_entity_idx');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: shared database, never drop tables from a rollback.
    }
};
