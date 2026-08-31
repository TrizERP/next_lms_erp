<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management port, stage 2 — additive fix, NOT a stage-1 file.
 *
 * Stage 1 (`2026_08_20_100000_add_planning_columns_to_task_table.php`) ported
 * only hp_erp's *planning* columns onto the target's pre-existing, differently
 * shaped `task` table (legacy uppercase columns: ID, TASK_TITLE,
 * TASK_DESCRIPTION, TASK_ATTACHMENT, FILE_SIZE, FILE_TYPE, TASK_DATE, STATUS,
 * TASK_ALLOCATED, TASK_ALLOCATED_TO, CREATED_ON, CREATED_BY, SYEAR,
 * sub_institute_id, approved_by, approved_on — no task_type, reply,
 * approve_status, kra/kpa, soft-delete or updated_* columns at all).
 *
 * hp_erp's OWN `task` table (`2025_06_21_050744_create_task_table.php`)
 * separately carries task_type (priority), reply (completion remarks),
 * kra/kpa/required_skills/observation_point, approve_status/approve_remarks
 * (the approval flow), created_by/updated_by/deleted_by, timestamps() and
 * softDeletes(). Every controller ported in this stage 2 pass
 * (WorkspaceController, MyTasksController, LegacyTaskController,
 * ProjectController's task linking, DependencyController, ReportController,
 * TaskScheduleController, TaskTimeTrackingController, ...) reads/writes these
 * columns as part of the source's core task-management behaviour — without
 * them the port cannot be behaviourally equivalent, not just missing an edge
 * case. This is exactly the "genuine bug blocking your work" carve-out in the
 * stage-2 brief: fixed here, minimally, as a brand new additive migration
 * rather than editing the stage-1 file.
 *
 * Every column is nullable and guarded with hasColumn(), so this is safe to
 * run after stage 1 and safe to re-run.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'task_type', 'reply', 'approve_status', 'approve_remarks',
        'kra', 'kpa', 'required_skills', 'observation_point',
        'updated_by', 'updated_at', 'deleted_by', 'deleted_at',
    ];

    public function up(): void
    {
        Schema::table('task', function (Blueprint $table) {
            // Priority label (system High/Medium/Low or a tenant custom name).
            if (! Schema::hasColumn('task', 'task_type')) {
                $table->string('task_type', 100)->nullable()->index();
            }
            // Completion / status-change remarks (My Tasks "update status" flow).
            if (! Schema::hasColumn('task', 'reply')) {
                $table->text('reply')->nullable();
            }
            // Approve / reject review flow (Workspace approve()).
            if (! Schema::hasColumn('task', 'approve_status')) {
                $table->string('approve_status', 30)->nullable()->index();
            }
            if (! Schema::hasColumn('task', 'approve_remarks')) {
                $table->text('approve_remarks')->nullable();
            }
            // Legacy task-create form fields (LegacyTaskController).
            if (! Schema::hasColumn('task', 'kra')) {
                $table->string('kra', 1000)->nullable();
            }
            if (! Schema::hasColumn('task', 'kpa')) {
                $table->string('kpa', 1000)->nullable();
            }
            if (! Schema::hasColumn('task', 'required_skills')) {
                $table->string('required_skills', 2000)->nullable();
            }
            if (! Schema::hasColumn('task', 'observation_point')) {
                $table->text('observation_point')->nullable();
            }
            // Audit + soft delete, used throughout (archive/destroy flows).
            if (! Schema::hasColumn('task', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->index();
            }
            if (! Schema::hasColumn('task', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
            if (! Schema::hasColumn('task', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->index();
            }
            if (! Schema::hasColumn('task', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->index();
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
