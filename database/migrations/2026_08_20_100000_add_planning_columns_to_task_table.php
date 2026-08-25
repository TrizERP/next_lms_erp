<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management migration, stage 1 of 2 (migrations + models only).
 *
 * Ported from hp_erp's `2026_08_04_130000_add_planning_columns_to_task_table.php`.
 * Adds the planning/effort/status-label columns the Task Management API layer
 * (MyTasksController and friends) reads and writes, plus `status_label` from
 * hp_erp's `2026_08_05_100000_create_task_option_sets.php` (folded in here
 * rather than as a separate migration, since both alter the same `task`
 * table and this repo's `task` table already has its own distinct history).
 *
 * The target's `task` table (2023_03_05_115658_create_task_table.php) has a
 * different column set from hp_erp's (mostly uppercase legacy columns, no
 * `taskcompletation_remarks`), so this is purely additive and does not use
 * `->after()` positioning tied to columns that don't exist here.
 *
 * Every column is nullable: existing rows stay valid, and the legacy /task
 * screens that never touch these columns are unaffected.
 */
return new class extends Migration
{
    /** Columns this migration owns, so up()/down() stay in one place. */
    private const COLUMNS = [
        'planned_start_date',
        'estimated_hours',
        'actual_hours',
        'remaining_hours',
        'acceptance_criteria',
        'delay_category',
        'delay_reason',
        'status_label',
    ];

    public function up(): void
    {
        Schema::table('task', function (Blueprint $table) {
            // Planning: when work should start, and the effort envelope.
            if (! Schema::hasColumn('task', 'planned_start_date')) {
                $table->date('planned_start_date')->nullable();
            }
            if (! Schema::hasColumn('task', 'estimated_hours')) {
                $table->decimal('estimated_hours', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('task', 'actual_hours')) {
                $table->decimal('actual_hours', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('task', 'remaining_hours')) {
                $table->decimal('remaining_hours', 8, 2)->nullable();
            }

            // Definition of done, stored as a JSON array of criterion strings.
            if (! Schema::hasColumn('task', 'acceptance_criteria')) {
                $table->longText('acceptance_criteria')->nullable();
            }

            // Why a task went ON HOLD - feeds the delay reports.
            if (! Schema::hasColumn('task', 'delay_category')) {
                $table->string('delay_category', 50)->nullable()->index();
            }
            if (! Schema::hasColumn('task', 'delay_reason')) {
                $table->text('delay_reason')->nullable();
            }

            // Tenant-defined display label for a custom status mapped onto
            // one of the system status categories (see task_management_statuses).
            if (! Schema::hasColumn('task', 'status_label')) {
                $table->string('status_label', 100)->nullable();
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
