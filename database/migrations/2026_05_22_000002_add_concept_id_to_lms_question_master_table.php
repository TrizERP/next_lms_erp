<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lms_question_master', function (Blueprint $table) {
            if (!Schema::hasColumn('lms_question_master', 'concept_id')) {
                $table->unsignedBigInteger('concept_id')->nullable()->after('chapter_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_question_master', function (Blueprint $table) {
            if (Schema::hasColumn('lms_question_master', 'concept_id')) {
                $table->dropColumn('concept_id');
            }
        });
    }
};
