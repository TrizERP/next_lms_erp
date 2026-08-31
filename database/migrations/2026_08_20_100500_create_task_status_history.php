<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management migration, stage 1 of 2 (migrations + models only).
 *
 * Adapted from hp_erp's `2026_08_10_160000_create_task_status_history.php`.
 *
 * hp_erp built this table as a PROJECTION off its `g2g_event_store` event
 * store (one row per replayed event, keyed by a unique `event_id`). This
 * target has no event store — the established pattern here (see
 * `s_performance_activity_log` in the TalentManagement precedent) is a flat
 * log table written directly by application code. So this table keeps the
 * source's field names (they carry the real business meaning: from/to
 * status, from/to approve_status, the derived terminal/active/reopen flags,
 * actor, timestamp) but drops `event_id` and the "one row per event" unique
 * constraint entirely — there is nothing to replay from, and a later agent's
 * status-change trait will INSERT a row directly on each transition.
 *
 * `is_reopen` is the point of the table: a reopen is a transition INTO an
 * active status FROM a terminal one (COMPLETED, or approve_status='approved'),
 * which can't be seen from the `task` row alone since it only holds current
 * state.
 *
 * No `syear`: the source schema doesn't carry SYEAR here either, and a
 * status transition is scoped by task_id, not academic year.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_status_history')) {
            return;
        }

        Schema::create('task_status_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('task_id')->index();

            $table->string('from_status', 64)->nullable();
            $table->string('to_status', 64)->nullable();
            $table->string('from_approve_status', 64)->nullable();
            $table->string('to_approve_status', 64)->nullable();

            // Derived at write time, from the transition itself.
            $table->boolean('from_terminal')->default(false);
            $table->boolean('to_active')->default(false);
            $table->boolean('is_reopen')->default(false)->index();

            $table->unsignedBigInteger('actor_id')->nullable()->index(); // NULL = SYSTEM
            $table->dateTime('occurred_at', 3);

            $table->index(['task_id', 'occurred_at'], 'idx_tsh_task_time');
            $table->index(['sub_institute_id', 'occurred_at'], 'idx_tsh_tenant_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_history');
    }
};
