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
        Schema::table('suggested_content', function (Blueprint $table) {

            // Title
            if (!Schema::hasColumn('suggested_content', 'title')) {
                $table->string('title')->nullable();
            }

            // Modality
            if (!Schema::hasColumn('suggested_content', 'modality')) {
                $table->string('modality')->nullable();
            }

            // Language
            if (!Schema::hasColumn('suggested_content', 'language')) {
                $table->string('language')->nullable();
            }

            // Difficulty level
            if (!Schema::hasColumn('suggested_content', 'difficulty_level')) {
                $table->integer('difficulty_level')->nullable();
            }

            // Bloom level
            if (!Schema::hasColumn('suggested_content', 'bloom_level')) {
                $table->string('bloom_level')->nullable();
            }

            // Pedagogy tag
            if (!Schema::hasColumn('suggested_content', 'pedagogy_tag')) {
                $table->string('pedagogy_tag')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suggested_content', function (Blueprint $table) {

            if (Schema::hasColumn('suggested_content', 'title')) {
                $table->dropColumn('title');
            }

            if (Schema::hasColumn('suggested_content', 'modality')) {
                $table->dropColumn('modality');
            }

            if (Schema::hasColumn('suggested_content', 'language')) {
                $table->dropColumn('language');
            }

            if (Schema::hasColumn('suggested_content', 'difficulty_level')) {
                $table->dropColumn('difficulty_level');
            }

            if (Schema::hasColumn('suggested_content', 'bloom_level')) {
                $table->dropColumn('bloom_level');
            }

            if (Schema::hasColumn('suggested_content', 'pedagogy_tag')) {
                $table->dropColumn('pedagogy_tag');
            }
        });
    }
};