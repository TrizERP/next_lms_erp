<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task Management migration, stage 1 of 2 (migrations + models only).
 *
 * Ported from hp_erp's `2026_07_29_000000_create_task_management_dependency_tables.php`.
 *
 * The source migration also created `task_management_task_comments`, but the
 * live controllers (WorkspaceController, ActivityController) only ever read
 * and write `task_management_comments` — the richer, sub_institute_id +
 * author_id shaped table created by the support-tables migration. That table
 * appears superseded/unused, so it is deliberately not ported; comments are
 * created once, in `..._create_task_management_support_tables.php`.
 *
 * predecessor_task_id / successor_task_id both loosely reference `task.id`,
 * same as the legacy `task` table's own convention of no foreign key
 * constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_management_dependencies')) {
            Schema::create('task_management_dependencies', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('predecessor_task_id')->index();
                $table->unsignedBigInteger('successor_task_id')->index();
                $table->string('dependency_type', 10)->default('FS')->index(); // FS | SS | FF | SF
                $table->integer('lag_days')->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedInteger('syear')->index();
                $table->unsignedBigInteger('created_by')->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();

                $table->unique(
                    ['sub_institute_id', 'syear', 'predecessor_task_id', 'successor_task_id'],
                    'tm_dependency_pair_unique'
                );
            });
        }

        if (! Schema::hasTable('task_management_milestones')) {
            Schema::create('task_management_milestones', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('workstream_id')->nullable()->index();
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->date('target_date');
                $table->string('status', 30)->default('UPCOMING')->index(); // UPCOMING | AT_RISK | MISSED | ACHIEVED
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->unsignedInteger('syear')->index();
                $table->unsignedBigInteger('created_by')->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_management_milestones');
        Schema::dropIfExists('task_management_dependencies');
    }
};
