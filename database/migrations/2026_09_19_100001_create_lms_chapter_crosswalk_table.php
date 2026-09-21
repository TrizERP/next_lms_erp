<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per legacy (subject, chapter) group decision.
 *
 * This is the authoritative record of WHY each of the ~137 groups was
 * moved where it was. The apply path reads only this table, never an
 * LLM, which is what makes "never write a guess" structural.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('lms_chapter_crosswalk')) {
            return;
        }

        Schema::create('lms_chapter_crosswalk', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('run_id', 36);

            $table->unsignedBigInteger('sub_institute_id');
            $table->unsignedBigInteger('standard_id');
            $table->unsignedBigInteger('legacy_subject_id');
            $table->unsignedBigInteger('legacy_chapter_id');

            $table->unsignedBigInteger('new_subject_id')->nullable();
            $table->unsignedBigInteger('new_chapter_id')->nullable();

            // map | nearest_surviving | out_of_syllabus | needs_review
            $table->string('decision', 24);
            // exact | nearest_surviving | cross_subject
            $table->string('mapping_kind', 24)->nullable();
            // approved_auto | low_confidence | needs_review | failed
            $table->string('review_status', 16);

            $table->decimal('confidence', 4, 3)->nullable();
            $table->decimal('lex_score', 9, 4)->nullable();
            $table->decimal('lex_norm', 5, 4)->nullable();
            $table->decimal('lex_margin', 5, 4)->nullable();
            $table->decimal('syllabus_coverage', 5, 4)->nullable();

            $table->string('adjudicator_model', 64)->nullable();
            $table->decimal('adjudicator_conf', 4, 3)->nullable();
            $table->string('verifier_model', 64)->nullable();
            $table->tinyInteger('verifier_agree')->nullable();
            $table->boolean('tiebreak_used')->default(false);
            $table->boolean('duplicate_evidence')->default(false);
            $table->string('shortlist_mode', 8)->default('narrow'); // narrow|wide

            $table->longText('shortlist_json')->nullable();
            $table->longText('evidence_json')->nullable();
            $table->char('prompt_hash', 64)->nullable();
            $table->longText('response_json')->nullable();
            $table->string('row_counts_json', 191)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->unique(
                ['run_id', 'sub_institute_id', 'legacy_subject_id', 'legacy_chapter_id'],
                'uq_crosswalk_run_group'
            );
            $table->index('legacy_chapter_id');
            $table->index('review_status');
            $table->index('new_chapter_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('lms_chapter_crosswalk');
    }
};
