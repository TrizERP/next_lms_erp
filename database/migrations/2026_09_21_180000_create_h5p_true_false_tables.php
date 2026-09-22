<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H5P True/False (H5P.TrueFalse).
 *
 * TWO TABLES, BECAUSE ONE QUESTION IS NOT A LESSON.
 *
 * H5P.TrueFalse is, in the official library, exactly ONE statement with one
 * boolean answer. That is a faithful modelling of the widget and a useless
 * modelling of the classroom: nobody sets a single true/false question. Every
 * real use -- a ten-question recap, a misconception sweep, a bell-ringer -- is
 * a POOL, and the platform's own brief asks for one ("Question Pool Support").
 *
 * So an item here is a pool, and the export handles the mismatch the way
 * H5PArithmeticQuizBuilder handles its own:
 *
 *   - the FIRST question becomes the `question` / `correct` pair a stock H5P
 *     host will actually run;
 *   - the whole pool travels in the `eduerpPool` extension, which this
 *     importer reads back exactly.
 *
 * A ten-question pool therefore exports as a one-question activity in Moodle
 * rather than as a broken one, re-imports here complete, and the export
 * warning says which of the two just happened. The alternative -- writing ten
 * questions into a field the library will ignore -- would look like it worked
 * and would not.
 *
 * QUESTIONS CARRY THEIR OWN MEDIA. H5P.TrueFalse takes an optional `media`
 * above the statement, and a true/false about a diagram is one of the few
 * places a picture is the question rather than decoration. One image per
 * question, as a URL, on the same media endpoint every other type uses.
 *
 * `questions_to_ask` IS HOW A POOL BECOMES A PAPER. 0 means every question in
 * author order. A positive number draws that many at random per attempt, which
 * is what makes a pool worth authoring: twenty questions, ten asked, a
 * different ten each time. The draw is seeded and the seed is recorded with
 * the attempt, so a teacher looking at a result can see the exact paper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_true_false', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('task_description')->nullable();

            /*
             * Behaviour. The first five are H5P.TrueFalse `behaviour` keys
             * verbatim; the rest are this schema's pool handling.
             *
             * `auto_check` is the library's `autoCheck`: the answer is marked
             * the moment it is chosen, with no Check button. That is "instant
             * feedback" in the brief, and it is a genuine pedagogical choice
             * rather than a nicety -- a learner who cannot change their mind
             * commits to an answer, which is the point of a recap and the
             * wrong shape for a considered assessment.
             */
            $table->boolean('enable_retry')->default(true);
            $table->boolean('enable_show_solution')->default(true);
            $table->boolean('enable_check_button')->default(true);
            $table->boolean('auto_check')->default(false);
            $table->boolean('confirm_check_dialog')->default(false);
            $table->boolean('confirm_retry_dialog')->default(false);

            $table->boolean('randomize_questions')->default(false);
            // 0 = ask the whole pool, in author order. See the class header.
            $table->unsignedSmallInteger('questions_to_ask')->default(0);

            $table->unsignedSmallInteger('points_per_question')->default(1);
            $table->unsignedTinyInteger('pass_percentage')->default(60);
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

            $table->index(['sub_institute_id', 'chapter_id'], 'h5p_tf_tenant_chapter_idx');
            $table->index(['standard_id', 'subject_id'], 'h5p_tf_standard_subject_idx');
            $table->index('status', 'h5p_tf_status_idx');
        });

        Schema::create('h5p_true_false_questions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('true_false_id');

            // HTML, as H5P stores it.
            $table->text('question_text');

            /*
             * The answer.
             *
             * A boolean, not the string "true"/"false" H5P params use. The
             * builder converts at the boundary, because a string that is
             * sometimes "true" and sometimes "1" is the classic way this type
             * marks a whole class wrong.
             */
            $table->boolean('correct_answer')->default(true);

            // H5P `feedbackOnCorrect` / `feedbackOnWrong`, per question --
            // which is where they belong in a pool, and where the library puts
            // them for its single question.
            $table->text('feedback_correct')->nullable();
            $table->text('feedback_incorrect')->nullable();

            // Shown with the solution. Explains the statement rather than
            // reacting to the attempt, so it reads the same either way.
            $table->text('explanation')->nullable();

            // Optional image above the statement. A URL, uploaded through the
            // shared media endpoint; the package service rewrites it on export
            // and back on import.
            $table->string('media_image', 2048)->nullable();
            $table->string('media_alt')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['true_false_id', 'sort_order'], 'h5p_tf_question_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5p_true_false_questions');
        Schema::dropIfExists('h5p_true_false');
    }
};
