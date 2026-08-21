<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management migration, stage 2 follow-up.
 *
 * Stage 1 (`2026_08_20_100000_add_planning_columns_to_task_table.php`) ported
 * hp_erp's planning/effort columns onto `task`, but did not add the columns
 * the Task Management API layer needs for soft-delete/archive, optimistic
 * locking and the approve/reject review flow: hp_erp's `task` table carries
 * `deleted_at`, `deleted_by`, `updated_at`, `updated_by`, `approve_status`
 * and `approve_remarks`; this target's `task` table (a much older,
 * uppercase-legacy table — see `2023_03_05_115658_create_task_table.php`)
 * never had them. `approved_by`/`approved_on` already exist on this table
 * and are reused as-is.
 *
 * Without these columns, WorkspaceController::destroy/approve,
 * VersionedLegacyTaskController's optimistic-locking check, and every write
 * path that stamps `updated_at` would have nothing to write to. This is
 * additive only (new nullable columns), does not touch any stage-1 file, and
 * is a genuine prerequisite for stage 2 rather than a stage-1 bug fix.
 *
 * `repeat_days` and `taskcompletation_remarks` are also added: both are read
 * by the bulk-import path (`front_desk\BulkTaskController`) this stage ports
 * into `TaskManagement\BulkTaskController`, and neither exists on this
 * table yet either.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'deleted_at',
        'deleted_by',
        'updated_at',
        'updated_by',
        'approve_status',
        'approve_remarks',
        'repeat_days',
        'taskcompletation_remarks',
    ];

    public function up(): void
    {
        Schema::table('task', function (Blueprint $table) {
            if (! Schema::hasColumn('task', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
            if (! Schema::hasColumn('task', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }
            if (! Schema::hasColumn('task', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->index();
            }
            if (! Schema::hasColumn('task', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable();
            }
            // Reviewer decision on a COMPLETED task; approved_by/approved_on
            // already exist on this table (2023_03_05_115658) and are reused.
            if (! Schema::hasColumn('task', 'approve_status')) {
                $table->string('approve_status', 20)->nullable()->index();
            }
            if (! Schema::hasColumn('task', 'approve_remarks')) {
                $table->text('approve_remarks')->nullable();
            }
            if (! Schema::hasColumn('task', 'repeat_days')) {
                $table->integer('repeat_days')->nullable();
            }
            if (! Schema::hasColumn('task', 'taskcompletation_remarks')) {
                $table->text('taskcompletation_remarks')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('task', function (Blueprint $table) {
            $columns = array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn('task', $column));

            if ($columns !== []) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
