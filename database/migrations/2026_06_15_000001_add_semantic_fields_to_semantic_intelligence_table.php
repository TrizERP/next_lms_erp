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
        Schema::table('semantic_intelligence', function (Blueprint $table) {
            $table->json('knowledge')->nullable()->after('full_intelegance_json');
            $table->json('ability')->nullable()->after('knowledge');
            $table->json('skill')->nullable()->after('ability');
            $table->json('competency')->nullable()->after('skill');
            $table->json('blooms_level')->nullable()->after('competency');
            $table->json('dok')->nullable()->after('blooms_level');
            $table->json('prerequisites')->nullable()->after('dok');
            $table->json('misconceptions')->nullable()->after('prerequisites');
            $table->json('real_world_applications')->nullable()->after('misconceptions');
            $table->json('pedagogy')->nullable()->after('real_world_applications');
            $table->json('learning_objectives')->nullable()->after('pedagogy');
            $table->json('learning_outcomes')->nullable()->after('learning_objectives');
            $table->json('assessment_blueprint')->nullable()->after('learning_outcomes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semantic_intelligence', function (Blueprint $table) {
            $table->dropColumn([
                'knowledge',
                'ability',
                'skill',
                'competency',
                'blooms_level',
                'dok',
                'prerequisites',
                'misconceptions',
                'real_world_applications',
                'pedagogy',
                'learning_objectives',
                'learning_outcomes',
                'assessment_blueprint',
            ]);
        });
    }
};
