<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent framework — registry, tool bindings, and run history.
 *
 * An agent is a *manifest*, not code that can do whatever it likes. `allowed_tools`
 * and `allowed_entities` are the whole of its reach: App\Domain\AI\Agents\AgentRunner
 * gives an agent no database handle, only a tool broker that re-checks every call
 * against this row AND against the caller's McpRequestContext. An agent therefore
 * cannot widen the requesting user's data scope, by construction.
 *
 * `max_verb` is the governance ceiling. An agent whose ceiling is `recommend` can
 * detect, explain and draft, and physically cannot execute — the runner refuses to
 * dispatch an action verb it is not licensed for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_key', 100)->index();
            $table->string('name', 150);
            $table->string('domain', 40)->default('k12')->index();
            $table->text('purpose')->nullable();
            $table->text('description')->nullable();

            $table->string('runner_class', 255);                   // App\Domain\K12\AcademicRisk\AcademicRiskAgent
            $table->string('agent_type', 40)->default('domain');   // domain | coordinator | utility

            // Reach. Empty array = nothing allowed, never "everything".
            $table->json('allowed_tools')->nullable();
            $table->json('allowed_entities')->nullable();
            $table->json('allowed_signal_keys')->nullable();

            // Governance ceiling: detect < analyse < explain < recommend < execute
            $table->string('max_verb', 24)->default('recommend');
            $table->boolean('may_execute_actions')->default(false);
            $table->string('authorized_workflow_keys', 500)->nullable();

            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->json('required_permissions')->nullable();
            $table->json('allowed_roles')->nullable();             // admin | staff | student

            $table->decimal('min_confidence', 5, 4)->default(0.5);
            $table->unsignedSmallInteger('min_evidence_count')->default(1);
            $table->unsignedInteger('timeout_seconds')->default(120);
            $table->unsignedSmallInteger('max_retries')->default(1);

            $table->json('config')->nullable();
            $table->boolean('status')->default(true)->index();
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['agent_key', 'sub_institute_id'], 'ai_agents_key_tenant_unique');
        });

        Schema::create('ai_agent_tools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id')->index();
            $table->string('tool_name', 150)->index();
            $table->string('tool_source', 40)->default('mcp');     // mcp | internal
            $table->boolean('is_read_only')->default(true);
            $table->boolean('requires_confirmation')->default(false);
            $table->json('argument_constraints')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['agent_id', 'tool_name']);
        });

        Schema::create('ai_agent_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_reference', 60)->unique();
            $table->unsignedBigInteger('agent_id')->index();
            $table->string('agent_key', 100)->index();

            $table->string('trigger_type', 40)->default('manual')->index();
            // manual | conversation | schedule | signal | workflow | coordinator
            $table->string('trigger_reference', 120)->nullable();
            $table->unsignedBigInteger('triggered_by')->nullable()->index();

            $table->string('subject_entity_key', 100)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();

            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('tool_calls')->nullable();                // [{tool, args_hash, status, ms}]
            $table->json('governance_report')->nullable();

            $table->unsignedInteger('signals_detected')->default(0);
            $table->unsignedInteger('evidence_collected')->default(0);
            $table->unsignedInteger('cases_opened')->default(0);
            $table->unsignedInteger('recommendations_drafted')->default(0);

            $table->string('status', 24)->default('queued')->index();
            // queued | running | completed | failed | rejected | timed_out | cancelled
            $table->decimal('confidence', 5, 4)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // The tenant scope the run was pinned to. Never re-derived mid-run.
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedInteger('academic_year')->nullable();
            $table->unsignedInteger('term_id')->nullable();
            $table->string('actor_role', 40)->nullable();
            $table->timestamps();

            $table->index(['agent_key', 'status', 'created_at'], 'ai_agent_runs_key_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_runs');
        Schema::dropIfExists('ai_agent_tools');
        Schema::dropIfExists('ai_agents');
    }
};
