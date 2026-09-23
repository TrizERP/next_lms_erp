<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-question marks for a homework submission.
 *
 * Homework has had AI evaluation since the 2026_09_11 migration, but only as a
 * whole-submission verdict: `ai_score` out of `ai_total_questions`, with the
 * reasoning buried in `ai_result_json` where no teacher could edit it. The
 * teacher's only instrument was a free-text remark.
 *
 * This is the piece Exam Evaluation has and homework did not: one row per
 * question, carrying what the AI proposed AND what the teacher allowed. The
 * same rule holds here as there — `ai_marks` is a proposal, `teacher_marks` is
 * the number that counts, and only a teacher writes the second.
 *
 * `homework_id` is the whole key. An exam sheet needs a batch and an identity
 * match because the school bulk-scans a class set; a homework submission is
 * uploaded by the student themselves, so who it belongs to is never in doubt.
 *
 * Two new marks columns go on `homework` alongside the existing count-based
 * ones rather than replacing them. `ai_score` is `int unsigned` and counts
 * QUESTIONS CORRECT — it cannot hold 2.5 marks, and anything already reading it
 * expects a count. So marks live in their own decimal columns and the old
 * fields keep meaning exactly what they always did.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homework_evaluation_answer')) {
            Schema::create('homework_evaluation_answer', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('homework_id')->index();
                $table->unsignedBigInteger('sub_institute_id')->nullable()->index();

                $table->unsignedBigInteger('question_id')->nullable()->index();
                $table->unsignedInteger('question_no');
                $table->string('question_type', 60)->nullable();
                // An objective question is scored against the key in PHP and is
                // safe to total; everything else is an AI proposal a teacher is
                // expected to look at.
                $table->boolean('is_objective')->default(false);

                $table->text('question_title')->nullable();
                $table->text('detected_answer')->nullable();
                $table->string('selected_options', 100)->nullable();
                $table->text('expected_answer')->nullable();

                $table->decimal('max_marks', 6, 2)->default(0);
                $table->decimal('ai_marks', 6, 2)->nullable();
                $table->decimal('teacher_marks', 6, 2)->nullable();
                // correct | partially_correct | wrong | unattempted
                $table->string('status', 20)->default('unattempted');
                $table->decimal('ai_confidence', 5, 2)->nullable();
                $table->string('ai_remark', 500)->nullable();

                $table->unsignedInteger('page')->default(1);
                $table->string('box_2d', 60)->nullable();
                $table->timestamps();

                $table->unique(['homework_id', 'question_no'], 'hea_homework_question');
            });
        }

        if (Schema::hasTable('homework') && ! Schema::hasColumn('homework', 'ai_marks')) {
            Schema::table('homework', function (Blueprint $table) {
                $table->decimal('ai_marks', 7, 2)->nullable()->after('ai_percentage');
                $table->decimal('teacher_marks', 7, 2)->nullable()->after('ai_marks');
                $table->decimal('max_marks', 7, 2)->nullable()->after('teacher_marks');
                // answer_key — the homework carried real questions, so the marking
                //               key came from them and objective items were scored
                //               deterministically.
                // free_form   — no questions on the row, so the teacher's own
                //               attachment was read for them and everything was
                //               judged by the model.
                $table->string('evaluation_mode', 20)->nullable()->after('max_marks');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_evaluation_answer');

        if (Schema::hasTable('homework') && Schema::hasColumn('homework', 'ai_marks')) {
            Schema::table('homework', function (Blueprint $table) {
                $table->dropColumn(['ai_marks', 'teacher_marks', 'max_marks', 'evaluation_mode']);
            });
        }
    }
};
