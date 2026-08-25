<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management port, stage 2 gap-fill.
 *
 * Ported from hp_erp's `task_management_project_departments` table (used by
 * `ProjectController::syncDepartments()`/`departments()`), so a project can
 * carry more than one department while `task_management_projects.department_id`
 * keeps holding the primary one for every existing list/report that already
 * reads that column.
 *
 * Must run after `2026_08_20_100100_create_task_management_project_tables.php`
 * (the FK target, `task_management_projects.id`) — the `10090x` date slot
 * places it well after that file's `1001xx` slot.
 *
 * Loose `department_id` (indexed, no FK) matches this module's established
 * convention of not constraining joins into `hrms_departments`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_management_project_departments')) {
            Schema::create('task_management_project_departments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('department_id')->index();
                $table->boolean('is_primary')->default(false);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->unique(['project_id', 'department_id'], 'tm_project_department_unique');

                $table->foreign('project_id', 'tm_project_departments_project_fk')
                    ->references('id')->on('task_management_projects')
                    ->cascadeOnDelete();
            });
        }

        // Backfill: every existing project that already carries a
        // department_id gets a corresponding primary row here, so
        // ProjectController::departments()/syncDepartments() see the same
        // department a project already had before this table existed.
        $projects = \Illuminate\Support\Facades\DB::table('task_management_projects')
            ->whereNotNull('department_id')
            ->select('id', 'department_id', 'created_by')
            ->get();

        foreach ($projects as $project) {
            $exists = \Illuminate\Support\Facades\DB::table('task_management_project_departments')
                ->where('project_id', $project->id)
                ->where('department_id', $project->department_id)
                ->exists();

            if (! $exists) {
                \Illuminate\Support\Facades\DB::table('task_management_project_departments')->insert([
                    'project_id' => $project->id,
                    'department_id' => $project->department_id,
                    'is_primary' => true,
                    'created_by' => $project->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_management_project_departments');
    }
};
