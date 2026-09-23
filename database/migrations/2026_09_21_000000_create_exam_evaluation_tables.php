<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exam Evaluation — scanned answer sheets graded against a question paper.
 *
 * Three tables, one per level of the thing a teacher actually works with:
 *
 *  - `exam_evaluation_batch`  one scanning run for one question paper, e.g.
 *                             "Class 7 Science Term 1 — Section A".
 *  - `exam_evaluation_sheet`  one student's uploaded answer sheet inside that
 *                             batch, carrying who the AI thinks it belongs to
 *                             and which teacher signed the marks off.
 *  - `exam_evaluation_answer` one question on one sheet: what the student put,
 *                             what the key expected, what the AI proposed and
 *                             what the teacher finally allowed.
 *
 * The AI never writes a final mark. `ai_marks` is a proposal; `teacher_marks`
 * is the number that counts, and a sheet only reaches `Approved` through an
 * explicit teacher action that stamps `approved_by`. Publishing to the
 * gradebook reads `teacher_marks`, never `ai_marks`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_evaluation_batch')) {
            Schema::create('exam_evaluation_batch', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->integer('syear')->nullable();
                $table->unsignedBigInteger('question_paper_id')->index();
                $table->string('name', 191);
                // Draft -> Processing -> Review -> Published. A batch sits in
                // Review for as long as any sheet still needs a teacher.
                $table->string('status', 30)->default('Draft');
                $table->unsignedInteger('total_sheets')->default(0);
                $table->unsignedInteger('evaluated_sheets')->default(0);
                $table->unsignedInteger('approved_sheets')->default(0);
                $table->decimal('total_marks', 7, 2)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['sub_institute_id', 'syear', 'question_paper_id'], 'eeb_tenant_year_paper');
            });
        }

        if (! Schema::hasTable('exam_evaluation_sheet')) {
            Schema::create('exam_evaluation_sheet', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('batch_id')->index();
                $table->unsignedBigInteger('sub_institute_id')->index();
                $table->integer('syear')->nullable();

                $table->string('original_name', 191)->nullable();
                $table->string('file_name', 191);
                $table->string('file_type', 20)->nullable();
                $table->string('mime_type', 100)->nullable();

                // What the reader saw in the identity block, kept separately
                // from `student_id` so a teacher can see WHY a sheet was
                // matched -- or why it was not.
                $table->string('detected_roll_no', 50)->nullable();
                $table->string('detected_enrollment_no', 50)->nullable();
                $table->string('detected_student_name', 150)->nullable();
                $table->unsignedBigInteger('student_id')->nullable()->index();
                $table->decimal('identity_confidence', 5, 2)->nullable();
                // roll_no | enrollment_no | name | manual | unmatched
                $table->string('identity_source', 30)->nullable();

                // Pending | Processing | Evaluated | Needs review | Approved | Failed
                $table->string('status', 30)->default('Pending');
                $table->decimal('ai_total', 7, 2)->nullable();
                $table->decimal('teacher_total', 7, 2)->nullable();
                $table->decimal('max_marks', 7, 2)->nullable();
                $table->decimal('percentage', 5, 2)->nullable();

                $table->string('annotated_file_name', 191)->nullable();
                $table->longText('ai_result_json')->nullable();
                $table->string('failure_reason', 250)->nullable();
                $table->timestamp('evaluated_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['batch_id', 'status'], 'ees_batch_status');
            });
        }

        if (! Schema::hasTable('exam_evaluation_answer')) {
            Schema::create('exam_evaluation_answer', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sheet_id')->index();
                $table->unsignedBigInteger('batch_id')->index();
                $table->unsignedBigInteger('question_id')->nullable()->index();
                $table->unsignedInteger('question_no');
                $table->string('question_type', 60)->nullable();
                // An objective question is scored in PHP against the key and is
                // safe to auto-total; a subjective one is an AI proposal that a
                // teacher is expected to look at.
                $table->boolean('is_objective')->default(false);

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

                $table->unique(['sheet_id', 'question_no'], 'eea_sheet_question');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_evaluation_answer');
        Schema::dropIfExists('exam_evaluation_sheet');
        Schema::dropIfExists('exam_evaluation_batch');
    }
};
