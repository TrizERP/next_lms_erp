<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds ONLY the columns genuinely missing from `lms_assignment` for the AI
 * homework-evaluation pipeline. No new table is created.
 *
 * Reused as-is (no new column needed):
 *   - exam_pdf          -> assignment PDF (teacher-uploaded questions)
 *   - submission_image  -> student-uploaded answer PDF/JPG/JPEG/PNG
 *   - teacher_remarks   -> auto-filled with the AI summary (teacher can still overwrite it)
 *   - json_annotation   -> stores the full Gemini evaluation JSON (unused by any
 *                          existing code path, confirmed by a project-wide grep)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lms_assignment', function (Blueprint $table) {
            $table->string('reviewed_pdf_path')->nullable()->after('json_annotation');
            $table->unsignedInteger('ai_score')->nullable()->after('reviewed_pdf_path');
            $table->unsignedInteger('ai_total_questions')->nullable()->after('ai_score');
            $table->decimal('ai_percentage', 5, 2)->nullable()->after('ai_total_questions');
            $table->string('ai_status', 30)->nullable()->after('ai_percentage');
            $table->string('ai_failure_reason')->nullable()->after('ai_status');
            $table->timestamp('evaluated_at')->nullable()->after('ai_failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('lms_assignment', function (Blueprint $table) {
            $table->dropColumn([
                'reviewed_pdf_path',
                'ai_score',
                'ai_total_questions',
                'ai_percentage',
                'ai_status',
                'ai_failure_reason',
                'evaluated_at',
            ]);
        });
    }
};
