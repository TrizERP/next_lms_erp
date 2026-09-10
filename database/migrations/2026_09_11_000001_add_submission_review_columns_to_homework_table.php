<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('homework', function (Blueprint $table) {
            // Workflow state for the new multi-file submit + teacher-review
            // flow. Distinct from the legacy `completion_status` (char(4)
            // Y/N) column, which stays untouched and is only kept in sync as
            // a courtesy.
            $table->string('status', 30)->nullable();

            // JSON-encoded array of {path, original_name, mime_type,
            // file_size} objects -- replaces the old homework_submission_files
            // table now that everything lives on this one row.
            $table->longText('submission_files')->nullable();

            // Teacher-authored feedback. Distinct from `submission_remarks`,
            // which is the student's own remark (reused as-is for the new
            // submit path since it already carries that exact meaning).
            $table->text('teacher_remarks')->nullable();

            $table->integer('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('feedback_published')->default(false);

            $table->string('ai_status', 30)->nullable();
            $table->string('ai_failure_reason')->nullable();
            $table->unsignedInteger('ai_score')->nullable();
            $table->unsignedInteger('ai_total_questions')->nullable();
            $table->decimal('ai_percentage', 5, 2)->nullable();
            $table->longText('ai_result_json')->nullable();
            $table->string('reviewed_pdf_path')->nullable();
            $table->timestamp('evaluated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('homework', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'submission_files',
                'teacher_remarks',
                'reviewed_by',
                'reviewed_at',
                'feedback_published',
                'ai_status',
                'ai_failure_reason',
                'ai_score',
                'ai_total_questions',
                'ai_percentage',
                'ai_result_json',
                'reviewed_pdf_path',
                'evaluated_at',
            ]);
        });
    }
};
