<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per COLUMN mutation, across all three content tables.
 *
 * Per-column granularity (rather than per-row) is deliberate: it makes
 * rollback a single generic statement with no per-table special casing,
 *
 *   UPDATE {entity} SET {column_name} = :old_value
 *   WHERE id = :entity_id AND {column_name} <=> :new_value
 *
 * and it lets remap:verify prove completeness by comparing the audit
 * count against a snapshot diff, column by column.
 *
 * None of lms_question_master / content_master / lms_teacher_resource
 * has an updated_at, so this table plus the bak_ snapshot IS the entire
 * forensic trail for the run.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('lms_remap_audit')) {
            return;
        }

        Schema::create('lms_remap_audit', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('run_id', 36);

            $table->string('entity', 32);          // lms_question_master | content_master | lms_teacher_resource
            $table->unsignedBigInteger('entity_id');
            $table->string('column_name', 32);     // chapter_id | subject_id | concept_id | concept | deleted_at

            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();

            // remap_chapter | remap_subject | assign_concept | soft_delete
            $table->string('action', 24);

            $table->unsignedBigInteger('crosswalk_id')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('method', 32)->nullable(); // lexical_exact | llm_batch | crosswalk
            $table->longText('evidence_json')->nullable();

            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->string('revert_note', 191)->nullable();

            // Makes double-application impossible even under concurrent runs.
            $table->unique(['run_id', 'entity', 'entity_id', 'column_name'], 'uq_audit_run_cell');
            $table->index(['entity', 'entity_id']);
            $table->index('run_id');
            $table->index('reverted_at');
            $table->index('crosswalk_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('lms_remap_audit');
    }
};
