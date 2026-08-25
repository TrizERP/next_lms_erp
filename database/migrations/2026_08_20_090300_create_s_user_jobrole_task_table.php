<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from hp_erp's `s_user_jobrole_task` table (+ its later
 * `task_category` column, see 2025_09_20_075515_add_task_category_to_
 * s_user_jobrole_task_table.php in hp_erp). tbluserController@edit does not
 * actually query this table directly for the profile response (its
 * `jobroleTasks` key stays an empty array there, matching the ported
 * frontend's `profileData?.jobroleTasks` fallback), but the Jobrole Tasks
 * tab component expects this shape and the source route
 * `/hrms/jobrole-tasks` (departmentController) reads from it - ported here
 * so LMS-K12 can wire the Jobrole Tasks tab up properly (see
 * EmployeeDirectoryController@show).
 *
 * No FK to tbluser/school_setup, same reasoning as `s_user_skill_jobrole`.
 */
class CreateSUserJobroleTaskTable extends Migration
{
    public function up()
    {
        Schema::create('s_user_jobrole_task', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sector', 50)->index()->nullable();
            $table->string('track', 50)->index()->nullable();
            $table->string('jobrole', 191)->index()->nullable();
            $table->string('critical_work_function', 191)->index()->nullable();
            $table->string('task', 191)->index()->nullable();
            $table->string('task_category', 255)->nullable();
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('s_user_jobrole_task');
    }
}
