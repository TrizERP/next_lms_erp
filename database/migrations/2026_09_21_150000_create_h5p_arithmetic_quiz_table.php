<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H5P Arithmetic Quiz (H5P.ArithmeticQuiz).
 *
 * ONE TABLE, NO CHILDREN -- WHICH IS THE POINT OF THE TYPE.
 *
 * Every other H5P type here stores the questions an author wrote. This one
 * stores the RULE that makes them: operations, difficulty, how many. The
 * questions are generated at attempt time and are different every attempt,
 * which is exactly what a fluency drill needs and exactly why there is nothing
 * per-question to persist.
 *
 * WHERE THE GENERATOR LIVES. In the frontend, as a pure, seeded, unit-tested
 * module (lib/h5p/arithmetic-quiz.ts). Two consequences worth stating:
 *
 *  - The quiz runs offline and with no round trip per question, which a drill
 *    at twenty questions a minute needs.
 *  - The answers are therefore in the client. That is the correct trade for
 *    THIS type and not for others: the answer to 7 x 8 is not a secret, and
 *    the thing being measured is speed, which server round trips would
 *    destroy. A summative assessment would not be built this way.
 *
 * The seed is recorded with the attempt, so a teacher looking at a result can
 * regenerate the exact paper the learner saw.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_arithmetic_quiz', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            // H5P `intro`: shown on the start screen above the begin button.
            $table->text('intro_text')->nullable();
            $table->boolean('show_intro')->default(true);

            /*
             * Which operations are drawn from, as a JSON list of
             * addition | subtraction | multiplication | division.
             *
             * H5P models this as a single `arithmeticType` plus a separate
             * "mixed" value. A list says the same thing without the special
             * case, and says more: "multiplication and division only" is a
             * real week of a real syllabus that H5P cannot express.
             */
            $table->json('operations');

            /*
             * 1 easy | 2 medium | 3 hard.
             *
             * The level chooses the operand ranges, and the ranges live in the
             * generator beside the code that uses them rather than as six more
             * columns here. An author who needs a range this does not cover is
             * asking for a different question type.
             */
            $table->unsignedTinyInteger('difficulty_level')->default(1);

            // H5P `maxQuestions`. Capped in validation, not here, so the cap
            // can move without a migration.
            $table->unsignedSmallInteger('max_questions')->default(20);

            /*
             * Timing.
             *
             * `enable_timer` shows the clock and records elapsed time.
             * `time_limit_seconds` ends the attempt, 0 meaning it does not.
             * Separate for the same reason as the memory game: the number is
             * wanted far more often than the pressure.
             */
            $table->boolean('enable_timer')->default(true);
            $table->unsignedInteger('time_limit_seconds')->default(0);

            $table->unsignedSmallInteger('points_per_question')->default(1);
            $table->unsignedTinyInteger('pass_percentage')->default(60);

            $table->boolean('enable_retry')->default(true);
            /*
             * 0 = unlimited retries. A positive number is enforced by the
             * player against the attempt count the analytics pipeline already
             * keeps, so it survives a page reload -- which a client-side
             * counter would not.
             */
            $table->unsignedSmallInteger('max_attempts')->default(0);

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

            $table->index(['sub_institute_id', 'chapter_id'], 'h5p_arithmetic_quiz_tenant_chapter_idx');
            $table->index(['standard_id', 'subject_id'], 'h5p_arithmetic_quiz_standard_subject_idx');
            $table->index('status', 'h5p_arithmetic_quiz_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5p_arithmetic_quiz');
    }
};
