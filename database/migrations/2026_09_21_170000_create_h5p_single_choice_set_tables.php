<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H5P Single Choice Set (H5P.SingleChoiceSet).
 *
 * THREE TABLES, BECAUSE THE TYPE IS A SEQUENCE OF QUESTIONS.
 *
 * H5P.SingleChoiceSet is a run of questions, each with exactly one right
 * answer, played one after another with instant feedback between them. That is
 * a parent, its questions, and each question's options -- and the options are
 * their own table rather than a JSON column on the question because per-option
 * feedback is authored, exported and reported per option. A JSON blob would
 * make "which distractor did Class 6B pick most" a scan of parsed text instead
 * of a group-by.
 *
 * EXACTLY ONE OPTION IS CORRECT. That is the type. It is enforced in
 * validation, re-checked before publish, and is why there is no scoring-mode
 * column: a question is right or it is not.
 *
 * WHY `set_id` IS ALSO ON THE OPTIONS TABLE. It is denormalised on purpose.
 * The publish check, the max-score sum and the child cascade all want "every
 * option in this set" and none of them cares which question it sits under;
 * without the column each is a join or a query per question. This is the same
 * reasoning the Course Presentation elements table records.
 *
 * WHERE THIS SCHEMA IS WIDER THAN H5P. Three things the official library has
 * no field for, and which are therefore carried in the export's `eduerpSet`
 * extension and read back by the importer:
 *
 *   - per-question and per-option feedback (H5P has one `overallFeedback` for
 *     the whole set);
 *   - randomised question and answer order;
 *   - points per question (H5P scores one point per question, always).
 *
 * A package exported from here runs in Moodle or Lumi as a narrower set rather
 * than a broken one, and re-imports here complete. The export warning says so
 * at the moment the author downloads the file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_single_choice_set', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            // Shown above the first question. H5P has no field for it, so it
            // travels in the extension -- but it is what a teacher writes
            // first, so it is a column and not an afterthought.
            $table->text('task_description')->nullable();

            /*
             * Behaviour, mapped onto H5P.SingleChoiceSet `behaviour` where the
             * library has the field and onto the extension where it does not.
             *
             * `auto_continue` is the library's `autoContinue`: after the
             * feedback pause the next question arrives by itself. Off, the
             * learner presses to continue, which is right for a class reading
             * the feedback rather than racing it.
             */
            $table->boolean('auto_continue')->default(true);
            // The library's `timeoutCorrect` / `timeoutWrong`, in ms. Wrong is
            // longer than correct by default because there is more to read.
            $table->unsignedSmallInteger('timeout_correct_ms')->default(2000);
            $table->unsignedSmallInteger('timeout_wrong_ms')->default(3000);
            $table->boolean('sound_effects')->default(false);

            $table->boolean('enable_retry')->default(true);
            $table->boolean('enable_show_solution')->default(true);

            // Extension keys: see the class header.
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('randomize_answers')->default(true);
            $table->unsignedSmallInteger('points_per_question')->default(1);

            $table->unsignedTinyInteger('pass_percentage')->default(60);
            // The progress counter between questions ("3 of 8"). Off for a
            // low-stakes warm-up where the count is pressure, not information.
            $table->boolean('show_progress')->default(true);

            $table->json('feedback_bands')->nullable();

            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->longText('content_json')->nullable();
            $table->string('library', 64)->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('syear', 16)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'chapter_id'], 'h5p_scs_tenant_chapter_idx');
            $table->index(['standard_id', 'subject_id'], 'h5p_scs_standard_subject_idx');
            $table->index('status', 'h5p_scs_status_idx');
        });

        Schema::create('h5p_single_choice_questions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('set_id');

            // HTML, because H5P stores it as HTML and a question routinely
            // carries an emphasis or a formula fragment.
            $table->text('question_text');

            /*
             * Per-question feedback.
             *
             * Nullable, and null is not the empty string: null means "say
             * nothing beyond right or wrong", which is the sensible default
             * for a rapid set, while an empty string is an author who cleared
             * the box and meant it. Both render the same; only the export
             * tells them apart.
             */
            $table->text('feedback_correct')->nullable();
            $table->text('feedback_incorrect')->nullable();

            // Shown with the solution, not with the feedback -- it explains
            // the answer rather than reacting to the attempt.
            $table->text('explanation')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['set_id', 'sort_order'], 'h5p_scs_question_order_idx');
        });

        Schema::create('h5p_single_choice_options', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('question_id');
            // Denormalised. See the class header.
            $table->unsignedBigInteger('set_id');

            $table->text('option_text');

            /*
             * Exactly one per question is true.
             *
             * Not a `correct_option_id` on the question, because that is a
             * circular foreign key -- the option cannot exist before the
             * question it belongs to, and the question is not complete before
             * the option. A flag plus a validated invariant is the shape the
             * rest of this ERP's question tables use.
             */
            $table->boolean('is_correct')->default(false);

            // Per-option feedback: what to say to a learner who chose THIS
            // distractor. The most useful field on a wrong answer, and the one
            // H5P.SingleChoiceSet does not have.
            $table->text('feedback')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['question_id', 'sort_order'], 'h5p_scs_option_order_idx');
            $table->index('set_id', 'h5p_scs_option_set_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5p_single_choice_options');
        Schema::dropIfExists('h5p_single_choice_questions');
        Schema::dropIfExists('h5p_single_choice_set');
    }
};
