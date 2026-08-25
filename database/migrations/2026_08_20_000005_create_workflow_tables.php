<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow engine — configuration-driven, not hardcoded.
 *
 * A definition owns identity and permissions; a *version* owns the step graph, so a
 * running instance keeps executing the version it started on even after the
 * definition is edited. `workflow_runs.version_id` is what makes that safe.
 *
 * Steps are data: `step_type` selects a handler, `config` parameterises it, and
 * `next_step_key` / `branches` describe the graph. An approval gate is just a step
 * with `step_type = 'approval'` — the run parks there until a `workflow_approvals`
 * row resolves it. Nothing downstream of an approval step can run without one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_key', 120)->index();
            $table->string('name', 200);
            $table->string('domain', 40)->default('k12')->index();
            $table->string('module', 80)->nullable()->index();     // academic_intervention | admissions | fees
            $table->text('description')->nullable();

            $table->string('trigger_type', 40)->default('manual')->index();
            // manual | recommendation_approved | signal | schedule | event | conversation
            $table->json('trigger_config')->nullable();
            $table->json('conditions')->nullable();                // evaluated before a run starts

            $table->string('subject_entity_key', 100)->nullable();
            $table->unsignedBigInteger('active_version_id')->nullable()->index();

            $table->json('required_permissions')->nullable();
            $table->json('allowed_roles')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_consequential')->default(true);

            $table->unsignedInteger('timeout_minutes')->nullable();
            $table->unsignedSmallInteger('max_retries')->default(0);
            $table->boolean('status')->default(true)->index();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['workflow_key', 'sub_institute_id'], 'workflow_def_key_tenant_unique');
        });

        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('definition_id')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 24)->default('draft')->index(); // draft | published | archived
            $table->json('steps');                                   // the authoritative step graph
            $table->json('outcome_metrics')->nullable();             // what to measure afterwards
            $table->string('entry_step_key', 100)->nullable();
            $table->text('change_note')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['definition_id', 'version']);
        });

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_reference', 60)->unique();
            $table->unsignedBigInteger('definition_id')->index();
            $table->unsignedBigInteger('version_id')->index();
            $table->string('workflow_key', 120)->index();

            $table->string('trigger_type', 40)->default('manual');
            $table->unsignedBigInteger('recommendation_id')->nullable()->index();
            $table->unsignedBigInteger('decision_id')->nullable()->index();
            $table->unsignedBigInteger('case_id')->nullable()->index();
            $table->unsignedBigInteger('agent_run_id')->nullable()->index();

            $table->string('subject_entity_key', 100)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();

            $table->json('input')->nullable();
            $table->json('state')->nullable();                       // accumulated step outputs
            $table->string('current_step_key', 100)->nullable()->index();

            $table->string('status', 32)->default('pending')->index();
            // pending | running | awaiting_approval | approved | rejected |
            // completed | failed | cancelled | timed_out
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('timeout_at')->nullable()->index();

            $table->unsignedBigInteger('initiated_by')->nullable()->index();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedInteger('academic_year')->nullable();
            $table->unsignedInteger('term_id')->nullable();
            $table->timestamps();

            $table->index(['sub_institute_id', 'status'], 'workflow_runs_tenant_status_idx');
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->string('step_key', 100)->index();
            $table->string('step_type', 40)->index();
            // action | approval | generate | notify | assign | condition | measure | agent | wait
            $table->string('label', 200)->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);

            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('config')->nullable();

            $table->string('status', 24)->default('pending')->index();
            // pending | running | completed | failed | skipped | awaiting_approval | rejected
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->unsignedSmallInteger('max_retries')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'sequence'], 'workflow_steps_run_seq_idx');
        });

        Schema::create('workflow_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('step_id')->nullable()->index();
            $table->string('step_key', 100)->nullable();

            $table->string('approver_role', 60)->nullable()->index();  // teacher | principal | manager
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->string('assigned_to_name', 150)->nullable();

            $table->string('status', 24)->default('pending')->index(); // pending|approved|rejected|expired
            $table->text('comment')->nullable();
            $table->json('modifications')->nullable();

            $table->unsignedBigInteger('decided_by')->nullable()->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->timestamps();
        });

        Schema::create('workflow_outcomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('outcome_id')->nullable()->index();  // -> ai_outcomes
            $table->string('metric_key', 100)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->json('detail')->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_outcomes');
        Schema::dropIfExists('workflow_approvals');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflow_definitions');
    }
};
