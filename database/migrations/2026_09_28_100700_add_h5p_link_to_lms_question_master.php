<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A soft pointer from a Question Bank row to the H5P content it was
     * authored as, e.g. h5p_content_type = 'h5p_true_false',
     * h5p_content_id = 41 -> that row in the h5p_true_false table.
     *
     * No FK constraint: the target table varies by h5p_content_type, and this
     * codebase already treats question_type_catalog.code / question_format_code
     * the same way -- a soft reference resolved by the reader, not an enforced
     * relation. Both nullable: most rows are never authored through an H5P
     * form and carry neither.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_question_master')) {
            return;
        }

        if (!Schema::hasColumn('lms_question_master', 'h5p_content_type')) {
            Schema::table('lms_question_master', function (Blueprint $table) {
                $table->string('h5p_content_type', 48)
                    ->nullable()
                    ->after('question_format_code')
                    ->comment('h5p_<type> route segment this row was authored as, e.g. h5p_true_false');
            });
        }

        if (!Schema::hasColumn('lms_question_master', 'h5p_content_id')) {
            Schema::table('lms_question_master', function (Blueprint $table) {
                $table->unsignedInteger('h5p_content_id')
                    ->nullable()
                    ->after('h5p_content_type')
                    ->comment('id of the row in that h5p_content_type\'s own table');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('lms_question_master')) {
            return;
        }

        Schema::table('lms_question_master', function (Blueprint $table) {
            if (Schema::hasColumn('lms_question_master', 'h5p_content_id')) {
                $table->dropColumn('h5p_content_id');
            }
            if (Schema::hasColumn('lms_question_master', 'h5p_content_type')) {
                $table->dropColumn('h5p_content_type');
            }
        });
    }
};
