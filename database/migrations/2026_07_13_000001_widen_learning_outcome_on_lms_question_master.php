<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen learning_outcome for prompt-pack JSON arrays.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lms_question_master') ||
            !Schema::hasColumn('lms_question_master', 'learning_outcome')) {
            return;
        }

        Schema::table('lms_question_master', function (Blueprint $table) {
            $table->longText('learning_outcome')->nullable()->change();
        });
    }

    /**
     * Reverse the column width change.
     */
    public function down(): void
    {
        if (!Schema::hasTable('lms_question_master') ||
            !Schema::hasColumn('lms_question_master', 'learning_outcome')) {
            return;
        }

        Schema::table('lms_question_master', function (Blueprint $table) {
            $table->string('learning_outcome')->nullable()->change();
        });
    }
};
