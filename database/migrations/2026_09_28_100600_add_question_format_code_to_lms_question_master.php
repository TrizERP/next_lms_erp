<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The question_type_catalog.code a teacher explicitly picked for a question
     * through the manual Question Bank editor's "Question Format" dropdown.
     *
     * Neither existing catalog-code column is safe to write here: g_qtype_code is
     * algorithmically derived from the stem, and lms_question_extraction is the AI
     * extraction pipeline's sidecar, whose NOT NULL provenance columns
     * (extraction_id, reproduction, validation_status) have no meaning for a
     * hand-typed question. This column is the manual-entry counterpart, read
     * ahead of g_qtype_code but behind an actual extraction result wherever the
     * bank resolves a question's catalog code.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_question_master')) {
            return;
        }

        if (!Schema::hasColumn('lms_question_master', 'question_format_code')) {
            Schema::table('lms_question_master', function (Blueprint $table) {
                $table->string('question_format_code', 48)
                    ->nullable()
                    ->after('g_qtype_code')
                    ->comment('question_type_catalog.code chosen manually, e.g. via the Question Bank editor');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('lms_question_master') ||
            !Schema::hasColumn('lms_question_master', 'question_format_code')) {
            return;
        }

        Schema::table('lms_question_master', function (Blueprint $table) {
            $table->dropColumn('question_format_code');
        });
    }
};
