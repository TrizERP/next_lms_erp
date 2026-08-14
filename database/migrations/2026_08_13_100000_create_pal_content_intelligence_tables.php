<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAL V4 Content Intelligence Layer — schema (plan phase P1).
 *
 * CONTENT LAW C2 (additive only): this migration creates NEW tables and does not
 * alter, rename or drop a single column on lms_question_master (62,209 rows) or
 * content_master (31,362 rows). Those are live across 56 tenants and are still
 * read by the legacy /lms/* Blade UI. The intelligence overlay lives in sidecars
 * joined on id.
 *
 * CONTENT LAW C3 (tenancy): unlike the 27 existing pal_* tables — which have no
 * sub_institute_id and resolve tenancy by joining learner_id -> tblstudent/tbluser —
 * every table here carries sub_institute_id explicitly, because content-scoped rows
 * have no learner to resolve through. Shared curriculum vocabulary is the one
 * exception: sub_institute_id = 0 together with scope = 'global'.
 *
 * CONTENT LAW C8 (graph freeze): MariaDB only. Nothing here writes to Neo4j.
 *
 * No foreign keys are declared against lms_question_master / content_master /
 * lms_concept: those are MyISAM-era legacy tables whose ids are also referenced by
 * soft-deleted rows, and a hard FK would make the sidecar reject perfectly valid
 * historical content. Referential integrity is asserted by `pal:content-coverage`
 * instead of by the engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Sidecar: assessment metadata over lms_question_master (spec §5.1) ──
        if (! Schema::hasTable('pal_question_metadata')) {
            Schema::create('pal_question_metadata', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('question_id')->comment('lms_question_master.id');
                $table->unsignedBigInteger('sub_institute_id')->default(0);
                $table->string('scope', 16)->default('tenant');

                // Concept mapping
                $table->string('content_id_ref', 128)->nullable()->comment('spec question_id, e.g. Q_MATH_G6_FRAC_ADD_L3_0421');
                $table->unsignedBigInteger('concept_ref_id')->nullable()->comment('lms_concept.id');
                $table->string('sub_concept_ref', 191)->nullable();
                $table->string('grade_band', 16)->nullable();
                $table->string('stage', 32)->nullable();
                $table->string('board', 64)->nullable();

                // Cognitive classification
                $table->string('bloom_level', 16)->nullable();
                $table->unsignedTinyInteger('practice_level')->nullable();
                $table->string('knowledge_type', 24)->nullable();
                $table->unsignedTinyInteger('difficulty_1_to_5')->nullable();

                // Psychometrics (derived from lms_online_exam_answer, not from a pilot)
                $table->double('irt_a')->nullable();
                $table->double('irt_b')->nullable();
                $table->double('irt_c')->nullable();
                $table->double('discrimination_index')->nullable();
                $table->unsignedInteger('avg_time_seconds')->nullable();
                $table->double('first_attempt_correct_rate')->nullable();
                $table->unsignedInteger('response_count')->default(0);
                $table->string('guessing_vulnerability', 16)->nullable();
                $table->timestamp('psychometrics_derived_at')->nullable();

                // Misconception
                $table->json('misconception_tags')->nullable();
                $table->json('distractor_rationale')->nullable();

                // Pedagogy & delivery
                $table->string('assessment_type', 24)->nullable();
                $table->string('format', 32)->nullable();
                $table->string('h5p_type', 48)->nullable();
                $table->unsignedBigInteger('pedagogy_mapping_id')->nullable()->comment('lms_mapping_type.id under the pedagogy parent');
                $table->string('scaffold_type', 32)->nullable();
                $table->string('response_latency_band', 32)->nullable();
                $table->boolean('visual_dependency')->default(false);
                $table->boolean('offline_compatible')->default(true);
                $table->unsignedTinyInteger('cognitive_load_intrinsic')->nullable();
                $table->unsignedTinyInteger('cognitive_load_extraneous')->nullable();
                $table->unsignedTinyInteger('cognitive_load_germane')->nullable();

                // Language & culture
                $table->string('language', 8)->nullable();
                $table->json('language_variants_available')->nullable();
                $table->string('cultural_context', 32)->nullable();
                $table->string('gender_representation', 24)->nullable();
                $table->double('reading_level_fk')->nullable();

                // Standards mapping
                $table->json('skills')->nullable();
                $table->string('casel_domain', 48)->nullable();
                $table->string('ngss_practice', 48)->nullable();
                $table->string('ncdg_goal', 16)->nullable();
                $table->string('riasec_signal', 2)->nullable();
                $table->string('gardner_intelligence', 32)->nullable();
                $table->string('aptitude_domain', 24)->nullable();
                $table->string('nep_vocational_stream', 32)->nullable();
                $table->unsignedTinyInteger('nsqf_level')->nullable();
                $table->string('p21_skill', 32)->nullable();
                $table->string('hpc_lens_primary', 16)->nullable();

                // Career guidance
                $table->string('soft_skill_signal', 32)->nullable();
                $table->string('career_cluster_signal', 32)->nullable();

                // Quality — CONTENT LAW C4/C5
                $table->string('quality_status', 24)->default('draft');
                $table->string('tagged_by', 16)->default('ai');
                $table->double('confidence')->nullable();
                $table->string('content_flag', 64)->nullable();
                $table->boolean('sensitivity_flag')->default(false);
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedInteger('usage_count')->default(0);
                $table->string('version', 16)->default('1.0');
                $table->json('ai_rationale')->nullable();
                $table->timestamps();

                // One metadata row per question per tenant.
                $table->unique(['question_id', 'sub_institute_id'], 'pqm_question_tenant_unique');
                $table->index(['sub_institute_id', 'quality_status'], 'pqm_tenant_status_idx');
                $table->index(['concept_ref_id', 'bloom_level'], 'pqm_concept_bloom_idx');
                $table->index(['concept_ref_id', 'practice_level', 'quality_status'], 'pqm_ladder_idx');
                $table->index(['discrimination_index'], 'pqm_discrimination_idx');
            });
        }

        // ── Sidecar: content metadata over content_master (spec §2.2) ──
        if (! Schema::hasTable('pal_content_metadata')) {
            Schema::create('pal_content_metadata', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('content_master_id')->comment('content_master.id');
                $table->unsignedBigInteger('sub_institute_id')->default(0);
                $table->string('scope', 16)->default('tenant');

                $table->string('content_id_ref', 128)->nullable()->comment('spec content_id, e.g. CC_FRAC_ADD_UNLIKE_001_V1');
                $table->unsignedBigInteger('concept_ref_id')->nullable()->comment('lms_concept.id');
                $table->string('sub_concept_ref', 191)->nullable();

                // The 4-type model (spec §1) + variant system (spec §2.1)
                $table->string('content_type', 24)->nullable();
                $table->unsignedTinyInteger('variant_number')->nullable();
                $table->string('format', 32)->nullable();

                $table->string('language', 8)->nullable();
                $table->json('language_variants_available')->nullable();
                $table->string('grade_band', 16)->nullable();
                $table->string('stage', 32)->nullable();
                $table->string('bloom_level_served', 16)->nullable();
                $table->unsignedTinyInteger('practice_level')->nullable();
                $table->unsignedTinyInteger('difficulty_1_to_5')->nullable();

                $table->unsignedBigInteger('pedagogy_mapping_id')->nullable();
                $table->json('pedagogy_secondary')->nullable();
                $table->string('cultural_context', 32)->nullable();
                $table->double('reading_level_fk')->nullable();
                $table->unsignedSmallInteger('estimated_duration_minutes')->nullable();
                $table->boolean('visual_dependency')->default(false);
                $table->boolean('offline_compatible')->default(true);
                $table->string('h5p_type', 48)->nullable();
                $table->string('h5p_content_id', 64)->nullable();
                $table->text('video_url')->nullable();

                // Accessibility (spec §2.2)
                $table->boolean('has_alt_text')->default(false);
                $table->boolean('has_transcript')->default(false);
                $table->string('wcag_level', 8)->nullable();
                $table->boolean('screen_reader_compatible')->default(false);

                // Standards
                $table->string('casel_domain', 48)->nullable();
                $table->string('ngss_practice', 48)->nullable();
                $table->string('ncdg_goal', 16)->nullable();
                $table->json('gardner_intelligence')->nullable();
                $table->string('riasec_signal', 2)->nullable();
                $table->string('hpc_lens_primary', 16)->nullable();

                // Quality + live monitoring (spec §7.3)
                $table->string('quality_status', 24)->default('draft');
                $table->string('tagged_by', 16)->default('ai');
                $table->double('confidence')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('last_reviewed')->nullable();
                $table->unsignedInteger('usage_count')->default(0);
                $table->double('avg_mastery_post')->nullable();
                $table->unsignedInteger('confusion_flag_count')->default(0);
                $table->double('discrimination_index')->nullable();
                $table->string('content_flag', 64)->nullable();
                $table->string('version', 16)->default('1.0');
                $table->json('ai_rationale')->nullable();
                $table->timestamps();

                $table->unique(['content_master_id', 'sub_institute_id'], 'pcm_content_tenant_unique');
                $table->index(['sub_institute_id', 'quality_status'], 'pcm_tenant_status_idx');
                // The variant router's hot path: concept + type + variant, approved only.
                $table->index(['concept_ref_id', 'content_type', 'variant_number', 'quality_status'], 'pcm_variant_idx');
                $table->index(['content_type', 'format'], 'pcm_type_format_idx');
                $table->index(['usage_count', 'avg_mastery_post'], 'pcm_monitoring_idx');
            });
        }

        // ── Sidecar: concept enrichment over lms_concept (spec §6.1 :Concept) ──
        if (! Schema::hasTable('pal_concept_metadata')) {
            Schema::create('pal_concept_metadata', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('concept_ref_id')->comment('lms_concept.id');
                $table->unsignedBigInteger('sub_institute_id')->default(0);
                $table->string('scope', 16)->default('tenant');

                $table->string('concept_code', 128)->nullable()->comment('spec concept_id, e.g. FRAC_ADD_UNLIKE');
                $table->string('bloom_ceiling', 16)->nullable();
                $table->unsignedTinyInteger('practice_ceiling')->nullable();
                $table->string('hpc_lens', 16)->nullable();
                $table->string('stage_gate', 32)->nullable();
                // 1-10; high = commonly a prerequisite for other concepts.
                $table->unsignedTinyInteger('priority_score')->nullable();
                // lms_concept already has mastery_threshold; this is the PAL V4 BKT gate
                // and is kept separate so we never overwrite the legacy column (C2).
                $table->double('mastery_gate')->default(0.70);
                $table->unsignedBigInteger('pedagogy_mapping_id')->nullable();
                $table->string('riasec_primary', 2)->nullable();
                $table->string('gardner_primary', 32)->nullable();
                $table->string('ngss_practice', 48)->nullable();
                $table->string('ncdg_goal', 16)->nullable();
                $table->string('quality_status', 24)->default('draft');
                $table->string('tagged_by', 16)->default('ai');
                $table->double('confidence')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique(['concept_ref_id', 'sub_institute_id'], 'pcpm_concept_tenant_unique');
                $table->index(['sub_institute_id', 'priority_score'], 'pcpm_priority_idx');
                $table->index(['concept_code'], 'pcpm_code_idx');
            });
        }

        // ── Concept prerequisite edges (spec §6.1 REQUIRES / §6.2 CROSS_CURRICULAR_LINK) ──
        if (! Schema::hasTable('pal_concept_relations')) {
            Schema::create('pal_concept_relations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('from_concept_id')->comment('lms_concept.id');
                $table->unsignedBigInteger('to_concept_id')->comment('lms_concept.id');
                // requires | cross_curricular
                $table->string('relation_type', 32)->default('requires');
                $table->unsignedBigInteger('sub_institute_id')->default(0);
                $table->string('scope', 16)->default('tenant');
                $table->string('link_type', 32)->nullable()->comment('reinforcement | application');
                $table->string('transfer_direction', 64)->nullable();
                $table->boolean('auto_suggest')->default(false);
                $table->double('suggestion_trigger_mastery')->nullable();
                $table->double('mastery_gate')->nullable();
                $table->string('quality_status', 24)->default('draft');
                $table->string('tagged_by', 16)->default('human');
                $table->timestamps();

                $table->unique(['from_concept_id', 'to_concept_id', 'relation_type', 'sub_institute_id'], 'pcr_edge_unique');
                $table->index(['to_concept_id', 'relation_type'], 'pcr_reverse_idx');
            });
        }

        // ── Misconception library (spec §4.3) ──
        if (! Schema::hasTable('pal_misconception_library')) {
            Schema::create('pal_misconception_library', function (Blueprint $table) {
                $table->id();
                // lower_snake_case, stable forever — it is a foreign key in learner
                // history. Renaming orphans learner records; deprecate instead.
                $table->string('tag', 96);
                $table->unsignedBigInteger('sub_institute_id')->default(0);
                $table->string('scope', 16)->default('global');

                $table->string('concept_code', 128)->nullable();
                $table->unsignedBigInteger('concept_ref_id')->nullable()->comment('lms_concept.id');
                $table->string('subject', 96)->nullable();
                $table->string('grade_band', 16)->nullable();
                $table->text('description');
                $table->text('error_pattern')->nullable();
                $table->text('corrective_action')->nullable();
                $table->string('error_regex', 191)->nullable();
                $table->json('typical_wrong_answers')->nullable();
                $table->double('prevalence_rate')->nullable();
                $table->boolean('teacher_confirmed')->default(false);
                $table->string('corrective_format', 24)->nullable();
                $table->unsignedTinyInteger('priority_level')->default(1);
                $table->string('quality_status', 24)->default('draft');
                $table->string('tagged_by', 16)->default('human');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedInteger('detection_count')->default(0);
                $table->timestamps();

                $table->unique(['tag', 'sub_institute_id'], 'pml_tag_tenant_unique');
                $table->index(['concept_ref_id', 'priority_level'], 'pml_concept_priority_idx');
                $table->index(['sub_institute_id', 'quality_status'], 'pml_tenant_status_idx');
            });
        }

        // ── CORRECTS_WITH: misconception -> corrective content (spec §4.3) ──
        // CONTENT LAW C6: a misconception with no row here may not be served.
        if (! Schema::hasTable('pal_misconception_corrective')) {
            Schema::create('pal_misconception_corrective', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('misconception_id')->comment('pal_misconception_library.id');
                $table->unsignedBigInteger('sub_institute_id')->default(0);
                $table->string('scope', 16)->default('global');

                // The corrective may be existing LMS content OR authored inline here.
                // At least one of content_master_id / body must be present — asserted
                // by MisconceptionLibraryService, not by a CHECK constraint (MariaDB
                // 10.x on this host does not enforce them consistently).
                $table->unsignedBigInteger('content_master_id')->nullable();
                $table->string('content_id_ref', 128)->nullable();
                $table->string('title', 191);
                $table->longText('body')->nullable();
                $table->text('media_url')->nullable();
                $table->string('format', 32)->default('text_diagram');
                $table->string('h5p_type', 48)->nullable();
                $table->string('language', 8)->default('en');
                $table->json('language_variants_available')->nullable();
                $table->unsignedSmallInteger('estimated_duration_minutes')->nullable();
                $table->unsignedTinyInteger('priority_level')->default(1);
                $table->string('quality_status', 24)->default('draft');
                $table->string('tagged_by', 16)->default('human');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedInteger('served_count')->default(0);
                $table->double('resolution_rate')->nullable();
                $table->timestamps();

                $table->index(['misconception_id', 'priority_level', 'quality_status'], 'pmc_lookup_idx');
                $table->index(['sub_institute_id'], 'pmc_tenant_idx');
            });
        }

        // ── Learner exposure log — the shown-set behind CONTENT LAW C7 ──
        if (! Schema::hasTable('pal_learner_content_exposure')) {
            Schema::create('pal_learner_content_exposure', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learner_id');
                $table->unsignedBigInteger('sub_institute_id')->default(0);
                $table->unsignedBigInteger('concept_ref_id')->nullable();
                // content | practice | corrective | assessment
                $table->string('content_type', 24)->nullable();
                $table->unsignedBigInteger('content_master_id')->nullable();
                $table->unsignedBigInteger('corrective_id')->nullable();
                $table->unsignedBigInteger('question_id')->nullable();
                $table->string('content_id_ref', 128)->nullable();
                $table->string('format', 32)->nullable();
                $table->unsignedTinyInteger('variant_number')->nullable();
                $table->unsignedTinyInteger('practice_level')->nullable();
                $table->string('reason', 48)->nullable()->comment('first_delivery | variant_reroute | misconception_corrective | regression');
                $table->string('misconception_tag', 96)->nullable();
                $table->unsignedBigInteger('session_id')->nullable();
                $table->boolean('resolved')->nullable();
                $table->timestamp('served_at')->nullable();
                $table->timestamps();

                $table->index(['learner_id', 'concept_ref_id', 'content_type'], 'plce_shown_set_idx');
                $table->index(['learner_id', 'served_at'], 'plce_recent_idx');
                $table->index(['misconception_tag'], 'plce_misconception_idx');
            });
        }

        // ── QA pipeline audit trail (spec §7.1) ──
        if (! Schema::hasTable('pal_content_review_log')) {
            Schema::create('pal_content_review_log', function (Blueprint $table) {
                $table->id();
                // question | content | concept | misconception | corrective
                $table->string('entity_type', 24);
                $table->unsignedBigInteger('entity_id');
                $table->unsignedBigInteger('sub_institute_id')->default(0);
                $table->string('from_status', 24)->nullable();
                $table->string('to_status', 24);
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->string('actor_type', 16)->default('human')->comment('human | ai | derived | imported');
                $table->text('note')->nullable();
                $table->json('changed_fields')->nullable();
                $table->timestamps();

                $table->index(['entity_type', 'entity_id'], 'pcrl_entity_idx');
                $table->index(['sub_institute_id', 'created_at'], 'pcrl_tenant_time_idx');
                $table->index(['reviewed_by'], 'pcrl_reviewer_idx');
            });
        }
    }

    public function down(): void
    {
        // Reverse creation order. Every table is new in this migration, so a full
        // rollback leaves the live content tables exactly as they were.
        Schema::dropIfExists('pal_content_review_log');
        Schema::dropIfExists('pal_learner_content_exposure');
        Schema::dropIfExists('pal_misconception_corrective');
        Schema::dropIfExists('pal_misconception_library');
        Schema::dropIfExists('pal_concept_relations');
        Schema::dropIfExists('pal_concept_metadata');
        Schema::dropIfExists('pal_content_metadata');
        Schema::dropIfExists('pal_question_metadata');
    }
};
