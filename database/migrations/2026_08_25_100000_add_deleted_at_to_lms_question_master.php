<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Question Bank deletes are soft deletes: the row stays in place and
     * `deleted_at` records when it was removed, so question papers and exam
     * answers that still reference the question keep their foreign keys.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_question_master')) {
            return;
        }

        if (!Schema::hasColumn('lms_question_master', 'deleted_at')) {
            Schema::table('lms_question_master', function (Blueprint $table) {
                $table->softDeletes()->after('learning_outcome');
                $table->index('deleted_at', 'lms_question_master_deleted_at_index');
            });
        }
    }

    /**
     * Drop the soft-delete column again.
     */
    public function down(): void
    {
        if (!Schema::hasTable('lms_question_master') ||
            !Schema::hasColumn('lms_question_master', 'deleted_at')) {
            return;
        }

        Schema::table('lms_question_master', function (Blueprint $table) {
            $table->dropIndex('lms_question_master_deleted_at_index');
            $table->dropSoftDeletes();
        });
    }
};
