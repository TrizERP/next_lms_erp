<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Seed Difficulty Levels (parent_id = 1)
        $difficultyLevels = [
            ['id' => 101, 'name' => 'Easy', 'parent_id' => 1, 'globally' => 1, 'status' => 1],
            ['id' => 102, 'name' => 'Medium', 'parent_id' => 1, 'globally' => 1, 'status' => 1],
            ['id' => 103, 'name' => 'Hard', 'parent_id' => 1, 'globally' => 1, 'status' => 1],
        ];

        foreach ($difficultyLevels as $level) {
            DB::table('lms_mapping_type')->updateOrInsert(
                ['id' => $level['id']],
                $level
            );
        }

        // Seed Bloom's Taxonomy Levels (parent_id = 2)
        $bloomTaxonomy = [
            ['id' => 201, 'name' => 'Remember', 'parent_id' => 2, 'globally' => 1, 'status' => 1],
            ['id' => 202, 'name' => 'Understand', 'parent_id' => 2, 'globally' => 1, 'status' => 1],
            ['id' => 203, 'name' => 'Apply', 'parent_id' => 2, 'globally' => 1, 'status' => 1],
            ['id' => 204, 'name' => 'Analyze', 'parent_id' => 2, 'globally' => 1, 'status' => 1],
            ['id' => 205, 'name' => 'Evaluate', 'parent_id' => 2, 'globally' => 1, 'status' => 1],
            ['id' => 206, 'name' => 'Create', 'parent_id' => 2, 'globally' => 1, 'status' => 1],
        ];

        foreach ($bloomTaxonomy as $taxonomy) {
            DB::table('lms_mapping_type')->updateOrInsert(
                ['id' => $taxonomy['id']],
                $taxonomy
            );
        }

        // Add parent entries for Difficulty and Bloom's Taxonomy types
        $mappingTypes = [
            ['id' => 1, 'name' => 'Difficulty Level', 'parent_id' => 0, 'globally' => 1, 'status' => 1],
            ['id' => 2, 'name' => 'Bloom\'s Taxonomy', 'parent_id' => 0, 'globally' => 1, 'status' => 1],
        ];

        foreach ($mappingTypes as $type) {
            DB::table('lms_mapping_type')->updateOrInsert(
                ['id' => $type['id']],
                $type
            );
        }

        // Add new columns to question_paper table for AI generation
        Schema::table('question_paper', function (Blueprint $table) {
            if (!Schema::hasColumn('question_paper', 'ai_generated')) {
                $table->tinyInteger('ai_generated')->default(0)->nullable();
            }
            if (!Schema::hasColumn('question_paper', 'difficulty_distribution')) {
                $table->text('difficulty_distribution')->nullable();
            }
            if (!Schema::hasColumn('question_paper', 'taxonomy_distribution')) {
                $table->text('taxonomy_distribution')->nullable();
            }
            if (!Schema::hasColumn('question_paper', 'sections_config')) {
                $table->text('sections_config')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove seeded data
        DB::table('lms_mapping_type')->whereIn('id', [101, 102, 103, 201, 202, 203, 204, 205, 206])->delete();
        DB::table('lms_mapping_type')->whereIn('id', [1, 2])->delete();

        // Remove columns from question_paper
        Schema::table('question_paper', function (Blueprint $table) {
            if (Schema::hasColumn('question_paper', 'ai_generated')) {
                $table->dropColumn('ai_generated');
            }
            if (Schema::hasColumn('question_paper', 'difficulty_distribution')) {
                $table->dropColumn('difficulty_distribution');
            }
            if (Schema::hasColumn('question_paper', 'taxonomy_distribution')) {
                $table->dropColumn('taxonomy_distribution');
            }
            if (Schema::hasColumn('question_paper', 'sections_config')) {
                $table->dropColumn('sections_config');
            }
        });
    }
};
