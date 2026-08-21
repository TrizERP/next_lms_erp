<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from hp_erp's `s_user_skill_jobrole` table (source migration file is
 * misnamed `create_s_skill_jobrole_table.php` but creates
 * `s_user_skill_jobrole` - the name tbluserController@edit's `skillJobroleMap`
 * model actually queries). Maps a skill (by title, via `skill`) onto a
 * jobrole (by name, via `jobrole`) at a given proficiency level - this is
 * what tbluserController@edit joins against `s_users_skills` (on
 * `s_user_skill_jobrole.skill = s_users_skills.title`) to build the Employee
 * Profile's Jobrole Skill tab.
 *
 * No FK constraints to tbluser/school_setup: their `id` columns are
 * `int(11)`/`int unsigned` in this database, not the `bigint unsigned` G2G
 * uses (same reasoning already documented in
 * 2026_08_19_090000_create_s_users_skills_table.php) - not needed for the
 * joins this table is used in.
 */
class CreateSUserSkillJobroleTable extends Migration
{
    public function up()
    {
        Schema::create('s_user_skill_jobrole', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('skill')->nullable();
            $table->string('jobrole', 191)->index()->nullable();
            $table->string('proficiency_level', 191)->index()->nullable();
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('s_user_skill_jobrole');
    }
}
