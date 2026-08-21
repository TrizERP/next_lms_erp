<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from hp_erp's `s_skill_knowledge_ability` table. Per-skill,
 * per-proficiency-level classification rows (classification =
 * knowledge/ability/behaviour/attitude, classification_item = the actual
 * text) - tbluserController@edit groups these by `classification` to build
 * the `knowledge`/`ability`/`behaviour`/`attitude` arrays on each Jobrole
 * Skill tab entry.
 *
 * `skill_id` keeps its FK to `s_users_skills.id` - both are `bigint(20)
 * unsigned` in this database, so the constraint (unlike tbluser/school_setup
 * elsewhere) is type-compatible. No FK to tbluser/school_setup, same
 * reasoning as `s_user_skill_jobrole`.
 */
class CreateSSkillKnowledgeAbilityTable extends Migration
{
    public function up()
    {
        Schema::create('s_skill_knowledge_ability', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('skill_id')->nullable()->index();
            $table->foreign('skill_id')->references('id')->on('s_users_skills')
                ->nullOnDelete()
                ->onUpdate('NO ACTION');
            $table->string('proficiency_level', 191)->index()->nullable();
            $table->string('classification', 191)->index()->nullable();
            $table->tinyText('classification_item')->nullable();
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
        Schema::dropIfExists('s_skill_knowledge_ability');
    }
}
