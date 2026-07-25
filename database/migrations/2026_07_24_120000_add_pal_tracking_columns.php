<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the tracking/context columns that the PAL pedagogy-effectiveness and
 * metacognition-reflection write paths already insert but that were never
 * migrated. Without them, `POST /api/pal/pedagogy/track` and
 * `POST /api/pal/metacognition/reflect` fail with "Unknown column ...".
 *
 * All columns are nullable and additive, so this is safe and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pal_pedagogy_effectiveness', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_pedagogy_effectiveness', 'session_id')) {
                $table->unsignedBigInteger('session_id')->nullable()->after('learner_id');
            }
            if (! Schema::hasColumn('pal_pedagogy_effectiveness', 'concept_id')) {
                $table->unsignedBigInteger('concept_id')->nullable()->after('session_id');
            }
            if (! Schema::hasColumn('pal_pedagogy_effectiveness', 'content_id')) {
                $table->unsignedBigInteger('content_id')->nullable()->after('concept_id');
            }
            if (! Schema::hasColumn('pal_pedagogy_effectiveness', 'context_data')) {
                $table->json('context_data')->nullable()->after('effectiveness_score');
            }
        });

        Schema::table('pal_reflections', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_reflections', 'session_id')) {
                $table->unsignedBigInteger('session_id')->nullable()->after('learner_id');
            }
            if (! Schema::hasColumn('pal_reflections', 'concept_id')) {
                $table->unsignedBigInteger('concept_id')->nullable()->after('session_id');
            }
            if (! Schema::hasColumn('pal_reflections', 'content_id')) {
                $table->unsignedBigInteger('content_id')->nullable()->after('concept_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pal_pedagogy_effectiveness', function (Blueprint $table) {
            foreach (['session_id', 'concept_id', 'content_id', 'context_data'] as $column) {
                if (Schema::hasColumn('pal_pedagogy_effectiveness', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('pal_reflections', function (Blueprint $table) {
            foreach (['session_id', 'concept_id', 'content_id'] as $column) {
                if (Schema::hasColumn('pal_reflections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
