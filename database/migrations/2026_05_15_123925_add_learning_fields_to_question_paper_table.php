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
        Schema::table('question_paper', function (Blueprint $table) {

            // Concept ID
            if (!Schema::hasColumn('question_paper', 'concept_id')) {
                $table->bigInteger('concept_id')->nullable();
            }

            // Question type
            if (!Schema::hasColumn('question_paper', 'question_type')) {
                $table->string('question_type')->nullable();
            }

            // Difficulty level
            if (!Schema::hasColumn('question_paper', 'difficulty_level')) {
                $table->integer('difficulty_level')->nullable();
            }

            // Bloom level
            if (!Schema::hasColumn('question_paper', 'bloom_level')) {
                $table->string('bloom_level')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_paper', function (Blueprint $table) {

            if (Schema::hasColumn('question_paper', 'concept_id')) {
                $table->dropColumn('concept_id');
            }

            if (Schema::hasColumn('question_paper', 'question_type')) {
                $table->dropColumn('question_type');
            }

            if (Schema::hasColumn('question_paper', 'difficulty_level')) {
                $table->dropColumn('difficulty_level');
            }

            if (Schema::hasColumn('question_paper', 'bloom_level')) {
                $table->dropColumn('bloom_level');
            }
        });
    }
};