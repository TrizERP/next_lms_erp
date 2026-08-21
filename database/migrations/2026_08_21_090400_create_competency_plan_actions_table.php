<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `2026_07_29_100100_create_competency_plan_actions_table.php`.
 *
 * Action items / milestones inside a development plan - the plan detail
 * panel's "Actions" tab and "Next Milestone" card, with plan progress derived
 * from completed actions rather than typed in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('s_competency_plan_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('title', 191);
            $table->text('description')->nullable();
            // milestone | training | mentoring | project | reading | other
            $table->string('action_type', 50)->default('milestone')->index();
            // pending | in_progress | completed | blocked
            $table->string('status', 30)->default('pending')->index();
            // Optional competency this action closes the gap on (s_users_skills.id)
            $table->unsignedBigInteger('competency_id')->nullable()->index();
            $table->unsignedBigInteger('owner_id')->nullable();   // tbluser.id
            $table->date('due_date')->nullable()->index();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->unsignedBigInteger('created_by')->index()->nullable();
            $table->unsignedBigInteger('updated_by')->index()->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_competency_plan_actions');
    }
};
