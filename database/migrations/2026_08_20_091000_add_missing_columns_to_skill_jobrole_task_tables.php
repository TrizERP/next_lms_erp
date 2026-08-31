<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 2026_08_20_0901xx migrations that first created these 3 tables were
 * built from hp_erp's original CREATE-TABLE migration files; a live
 * `SHOW CREATE TABLE` against hp_erp's current database (2026-08-20) found
 * these tables have since grown extra columns there that never got their own
 * migration file (added directly to the live schema). Adding them here so
 * the ported tables match hp_erp's actual current structure exactly, ahead
 * of the verbatim data copy.
 */
class AddMissingColumnsToSkillJobroleTaskTables extends Migration
{
    public function up()
    {
        Schema::table('s_user_skill_jobrole', function (Blueprint $table) {
            $table->unsignedBigInteger('jobrole_id')->nullable()->after('jobrole')->index();
            $table->unsignedBigInteger('skill_id')->nullable()->after('skill')->index();
            $table->string('type', 191)->nullable()->after('skill_id');
            $table->text('proficiency_description')->nullable()->after('proficiency_level');
            $table->string('skill_code', 50)->nullable()->after('proficiency_description');

            $table->foreign('jobrole_id')->references('id')->on('s_user_jobrole')->nullOnDelete();
            $table->foreign('skill_id')->references('id')->on('s_users_skills')->nullOnDelete();
        });

        Schema::table('s_skill_knowledge_ability', function (Blueprint $table) {
            $table->text('proficiency_description')->nullable()->after('proficiency_level');
            $table->string('classification_category', 191)->nullable()->after('classification');
            $table->string('classification_sub_category', 191)->nullable()->after('classification_category');
        });

        Schema::table('s_user_jobrole_task', function (Blueprint $table) {
            $table->unsignedBigInteger('catalogue_task_id')->nullable()->after('id')->index();
            $table->unsignedBigInteger('jobrole_id')->nullable()->after('catalogue_task_id')->index();
            $table->string('task_type', 25)->nullable()->after('task');

            $table->foreign('jobrole_id')->references('id')->on('s_user_jobrole')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('s_user_skill_jobrole', function (Blueprint $table) {
            $table->dropForeign(['jobrole_id']);
            $table->dropForeign(['skill_id']);
            $table->dropColumn(['jobrole_id', 'skill_id', 'type', 'proficiency_description', 'skill_code']);
        });

        Schema::table('s_skill_knowledge_ability', function (Blueprint $table) {
            $table->dropColumn(['proficiency_description', 'classification_category', 'classification_sub_category']);
        });

        Schema::table('s_user_jobrole_task', function (Blueprint $table) {
            $table->dropForeign(['jobrole_id']);
            $table->dropColumn(['catalogue_task_id', 'jobrole_id', 'task_type']);
        });
    }
}
