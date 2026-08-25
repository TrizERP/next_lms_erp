<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management port, stage 2 gap-fill.
 *
 * Ported from hp_erp's dependency-table `project_id`/`workstream_id` columns
 * (read/written by `DependencyController::payload()`/`resource()`/`schedule()`).
 * Before this, a dependency's project could only be inferred by joining
 * `task_management_project_tasks` on the successor task — this stores it
 * directly so it can be filtered on and so it survives a task's project
 * changing later.
 *
 * Loose unsignedBigInteger, no FK — matches this table's existing convention
 * (predecessor_task_id/successor_task_id already loosely reference `task.id`
 * with no constraint).
 *
 * Must run after `2026_08_20_100200_create_task_management_dependency_tables.php`
 * (the table being altered) and after
 * `2026_08_20_100900_create_task_management_project_departments_table.php`
 * (this migration's date slot is later), whose backfill needs
 * `task_management_project_tasks` — already created by
 * `2026_08_20_100100_create_task_management_project_tables.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_management_dependencies', function (Blueprint $table) {
            if (! Schema::hasColumn('task_management_dependencies', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->index()->after('successor_task_id');
            }
            if (! Schema::hasColumn('task_management_dependencies', 'workstream_id')) {
                $table->unsignedBigInteger('workstream_id')->nullable()->index()->after('project_id');
            }
        });

        // Backfill: derive each dependency's project from its successor task's
        // link in task_management_project_tasks, so existing rows read the
        // same project a fresh save would now compute via shareProject()/
        // projectMatchesTasks(). A task linked to more than one project picks
        // the lowest project_id (MIN), matching taskOptions()'s own
        // "MIN(pt.project_id)" tie-break elsewhere in this module.
        $rows = DB::table('task_management_dependencies as d')
            ->whereNull('d.project_id')
            ->select('d.id', 'd.successor_task_id')
            ->get();

        foreach ($rows as $row) {
            $link = DB::table('task_management_project_tasks')
                ->where('task_id', $row->successor_task_id)
                ->orderBy('project_id')
                ->first(['project_id', 'workstream_id']);

            if ($link) {
                DB::table('task_management_dependencies')
                    ->where('id', $row->id)
                    ->update([
                        'project_id' => $link->project_id,
                        'workstream_id' => $link->workstream_id,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('task_management_dependencies', function (Blueprint $table) {
            if (Schema::hasColumn('task_management_dependencies', 'workstream_id')) {
                $table->dropColumn('workstream_id');
            }
            if (Schema::hasColumn('task_management_dependencies', 'project_id')) {
                $table->dropColumn('project_id');
            }
        });
    }
};
