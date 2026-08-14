<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pal_contents', function (Blueprint $table) {
            if (! Schema::hasColumn('pal_contents', 'pedagogy_tag')) {
                $table->string('pedagogy_tag')->nullable()->after('bloom_level');
            }
            foreach (['casel_domain', 'ngss_practice', 'ncdg_goal', 'music_domain', 'sports_domain', 'finance_domain', 'riasec_signal', 'assessment_method'] as $column) {
                if (! Schema::hasColumn('pal_contents', $column)) {
                    $table->string($column)->nullable()->after('pedagogy_tag');
                }
            }
            foreach (['gardner_intelligence', 'cross_curricular_links', 'framework_metadata', 'evidence_requirements', 'pedagogy_profile'] as $column) {
                if (! Schema::hasColumn('pal_contents', $column)) {
                    $table->json($column)->nullable()->after('assessment_method');
                }
            }
        });

        Schema::table('pal_learning_events', function (Blueprint $table) {
            foreach (['content_id', 'concept_id', 'session_id'] as $column) {
                if (! Schema::hasColumn('pal_learning_events', $column)) {
                    $table->unsignedBigInteger($column)->nullable()->after('learner_id');
                }
            }
            foreach (['pedagogy_tag', 'h5p_type', 'source', 'language', 'platform', 'riasec_signal'] as $column) {
                if (! Schema::hasColumn('pal_learning_events', $column)) {
                    $table->string($column)->nullable()->after('event_type');
                }
            }
            foreach (['framework_tags', 'gardner_intelligence', 'misconception_data'] as $column) {
                if (! Schema::hasColumn('pal_learning_events', $column)) {
                    $table->json($column)->nullable()->after('riasec_signal');
                }
            }
            if (! Schema::hasColumn('pal_learning_events', 'score')) {
                $table->float('score')->default(0)->after('framework_tags');
            }
            if (! Schema::hasColumn('pal_learning_events', 'duration_seconds')) {
                $table->unsignedInteger('duration_seconds')->default(0)->after('score');
            }
            if (! Schema::hasColumn('pal_learning_events', 'completion')) {
                $table->boolean('completion')->default(false)->after('duration_seconds');
            }
        });

        Schema::table('pal_telemetry_events', function (Blueprint $table) {
            foreach (['content_id', 'concept_id'] as $column) {
                if (! Schema::hasColumn('pal_telemetry_events', $column)) {
                    $table->unsignedBigInteger($column)->nullable()->after('object_id');
                }
            }
            foreach (['pedagogy_tag', 'h5p_type'] as $column) {
                if (! Schema::hasColumn('pal_telemetry_events', $column)) {
                    $table->string($column)->nullable()->after('concept_id');
                }
            }
            if (! Schema::hasColumn('pal_telemetry_events', 'framework_tags')) {
                $table->json('framework_tags')->nullable()->after('h5p_type');
            }
        });

        Schema::create('pal_learning_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->unsignedBigInteger('content_id')->nullable();
            $table->unsignedBigInteger('concept_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('pedagogy_tag')->nullable();
            $table->string('h5p_type')->nullable();
            $table->string('evidence_type');
            $table->json('framework_tags')->nullable();
            $table->float('score')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('completion')->default(false);
            $table->string('evidence_source')->default('system');
            $table->json('context_data')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['learner_id', 'pedagogy_tag']);
        });

        Schema::create('pal_framework_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learner_id');
            $table->string('framework_type');
            $table->string('framework_tag');
            $table->float('progress_score')->default(0);
            $table->unsignedInteger('evidence_count')->default(0);
            $table->timestamp('last_evidenced_at')->nullable();
            $table->string('status')->default('emerging');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'framework_type', 'framework_tag'], 'pal_framework_progress_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pal_framework_progress');
        Schema::dropIfExists('pal_learning_evidence');

        Schema::table('pal_telemetry_events', function (Blueprint $table) {
            foreach (['content_id', 'concept_id', 'pedagogy_tag', 'h5p_type', 'framework_tags'] as $column) {
                if (Schema::hasColumn('pal_telemetry_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('pal_learning_events', function (Blueprint $table) {
            foreach (['content_id', 'concept_id', 'session_id', 'pedagogy_tag', 'h5p_type', 'source', 'language', 'platform', 'riasec_signal', 'framework_tags', 'gardner_intelligence', 'misconception_data', 'score', 'duration_seconds', 'completion'] as $column) {
                if (Schema::hasColumn('pal_learning_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('pal_contents', function (Blueprint $table) {
            foreach (['pedagogy_tag', 'casel_domain', 'ngss_practice', 'ncdg_goal', 'music_domain', 'sports_domain', 'finance_domain', 'riasec_signal', 'assessment_method', 'gardner_intelligence', 'cross_curricular_links', 'framework_metadata', 'evidence_requirements', 'pedagogy_profile'] as $column) {
                if (Schema::hasColumn('pal_contents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
