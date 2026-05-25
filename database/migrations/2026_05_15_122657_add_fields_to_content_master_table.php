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
        Schema::table('content_master', function (Blueprint $table) {

            // Difficulty level
            if (!Schema::hasColumn('content_master', 'difficulty_level')) {
                $table->integer('difficulty_level')->nullable()->after('user_profile_name');
            }

            // Bloom level
            if (!Schema::hasColumn('content_master', 'bloom_level')) {
                $table->string('bloom_level')->nullable()->after('difficulty_level');
            }

            // Pedagogy tag
            if (!Schema::hasColumn('content_master', 'pedagogy_tag')) {
                $table->string('pedagogy_tag')->nullable()->after('bloom_level');
            }

            // Mastery threshold
            if (!Schema::hasColumn('content_master', 'mastery_threshold')) {
                $table->float('mastery_threshold')->nullable()->after('pedagogy_tag');
            }

            // Estimated mastery minutes
            if (!Schema::hasColumn('content_master', 'estimated_mastery_minutes')) {
                $table->integer('estimated_mastery_minutes')->nullable()->after('mastery_threshold');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_master', function (Blueprint $table) {

            if (Schema::hasColumn('content_master', 'difficulty_level')) {
                $table->dropColumn('difficulty_level');
            }

            if (Schema::hasColumn('content_master', 'bloom_level')) {
                $table->dropColumn('bloom_level');
            }

            if (Schema::hasColumn('content_master', 'pedagogy_tag')) {
                $table->dropColumn('pedagogy_tag');
            }

            if (Schema::hasColumn('content_master', 'mastery_threshold')) {
                $table->dropColumn('mastery_threshold');
            }

            if (Schema::hasColumn('content_master', 'estimated_mastery_minutes')) {
                $table->dropColumn('estimated_mastery_minutes');
            }
        });
    }
};