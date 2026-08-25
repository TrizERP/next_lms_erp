<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template engine + Generative AI service.
 *
 * Prompts stop being private PHP methods and become versioned rows, so the same
 * template can be reused across K-12 and G2G and changed without a deploy.
 *
 * Every generation is recorded twice: the request (what was asked, with which
 * template version, under whose scope) and the output (what came back, whether it
 * validated against the declared output schema, and whether safety checks passed).
 * `generation_outputs.is_generated` is always true — that flag is what
 * GeneratedContentBadge reads on the frontend and what GroundedClaims checks before
 * letting generated text be cited as evidence.
 *
 * Key rotation and daily limits already exist in `ai_api_keys` / `ai_daily_used_api`
 * (used by OpenAIService::generateContent). This layer reuses those tables rather
 * than introducing a second pool.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 120)->index();
            $table->string('name', 200);
            $table->string('domain', 40)->default('shared')->index();
            $table->string('category', 60)->nullable()->index();
            // explanation | recommendation | lesson | assessment | intervention |
            // training_plan | report | feedback
            $table->text('description')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->string('status', 24)->default('draft')->index();  // draft|published|archived

            $table->longText('system_prompt')->nullable();
            $table->longText('user_prompt');
            $table->json('variables')->nullable();                    // [{key,label,required,type}]
            $table->json('output_schema')->nullable();                // JSON Schema for validation
            $table->string('output_format', 24)->default('text');     // text | json | markdown

            $table->string('provider', 40)->nullable();               // openrouter | openai | gemini
            $table->string('model', 120)->nullable();
            $table->decimal('temperature', 4, 2)->nullable();
            $table->unsignedInteger('max_tokens')->nullable();

            $table->json('safety_rules')->nullable();
            $table->boolean('allow_as_evidence')->default(false);
            $table->boolean('requires_review')->default(false);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['template_key', 'version', 'sub_institute_id'], 'ai_templates_key_ver_tenant_unique');
        });

        Schema::create('ai_generation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_reference', 60)->unique();
            $table->string('template_key', 120)->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->string('purpose', 120)->index();
            $table->string('domain', 40)->default('k12')->index();

            $table->json('variables')->nullable();
            $table->json('context')->nullable();
            $table->longText('resolved_prompt')->nullable();
            $table->string('prompt_hash', 64)->nullable()->index();

            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();

            $table->string('subject_entity_key', 100)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->unsignedBigInteger('case_id')->nullable()->index();
            $table->unsignedBigInteger('agent_run_id')->nullable()->index();
            $table->unsignedBigInteger('workflow_run_id')->nullable()->index();

            $table->string('status', 24)->default('pending')->index();
            // pending | running | completed | failed | blocked_by_safety | invalid_output
            $table->text('error_message')->nullable();

            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->string('requested_by_role', 40)->nullable();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ai_generation_outputs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->index();
            $table->longText('content')->nullable();
            $table->json('structured_output')->nullable();

            // Always true. Generated content must stay distinguishable from fact.
            $table->boolean('is_generated')->default(true);
            $table->boolean('schema_valid')->default(false)->index();
            $table->json('schema_errors')->nullable();
            $table->boolean('safety_passed')->default(false)->index();
            $table->json('safety_report')->nullable();

            $table->boolean('reviewed')->default(false);
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_status', 24)->nullable();       // accepted | edited | rejected

            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('cost_estimate', 12, 6)->nullable();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_outputs');
        Schema::dropIfExists('ai_generation_requests');
        Schema::dropIfExists('ai_templates');
    }
};
