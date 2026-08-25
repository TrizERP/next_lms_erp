<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The governed intelligence chain:
 *
 *   Signal -> Evidence -> Case -> Hypothesis -> Explanation -> Recommendation
 *          -> Decision (human) -> Workflow -> Action -> Outcome -> Learning
 *
 * Two rules are enforced in the schema rather than left to convention:
 *
 *  1. `ai_evidence.verified` defaults to FALSE. Generated content can be stored as
 *     evidence but cannot be *cited* by a grounded claim until a human or a
 *     deterministic check flips it. See App\Domain\Governance\GroundedClaims.
 *  2. `ai_recommendations.status` starts at 'draft'. Nothing downstream of a
 *     recommendation may run until an `ai_decisions` row exists for it.
 *
 * The legacy `recommendations` table (2025-01-02, an untyped JSON blob keyed on
 * user_id) is deliberately left untouched — it is still read by existing code.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Signal definitions: the detectors, and their thresholds ----------
        Schema::create('ai_signal_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('signal_key', 100)->index();
            $table->string('label', 150);
            $table->string('domain', 40)->default('k12')->index();
            $table->string('subject_entity_key', 100);        // ontology entity the signal is about
            $table->text('description')->nullable();
            $table->string('detector_class', 255)->nullable();
            $table->string('severity_scale', 24)->default('risk_score');
            // Thresholds are seeded FROM the existing PredictiveInterventionEngine,
            // not invented. See App\Domain\AI\Signals\ThresholdRegistry.
            $table->json('thresholds')->nullable();
            $table->json('inputs')->nullable();
            $table->boolean('requires_evidence')->default(true);
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['signal_key', 'sub_institute_id'], 'ai_signal_def_key_tenant_unique');
        });

        // ---- Signals: detected, and persisted so trends exist -----------------
        Schema::create('ai_signals', function (Blueprint $table) {
            $table->id();
            $table->string('signal_key', 100)->index();
            $table->string('domain', 40)->default('k12')->index();
            $table->string('subject_entity_key', 100)->index();   // 'student'
            $table->unsignedBigInteger('subject_id')->index();    // tblstudent.id
            $table->string('subject_label', 200)->nullable();

            $table->decimal('score', 8, 4)->nullable();
            $table->string('severity', 24)->default('low')->index();  // low|moderate|high|critical
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('components')->nullable();               // per-signal contributor breakdown
            $table->json('context')->nullable();

            $table->string('status', 24)->default('open')->index(); // open|cased|resolved|dismissed|expired
            $table->timestamp('detected_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('detected_by_run_id')->nullable()->index();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedInteger('academic_year')->nullable()->index();
            $table->unsignedInteger('term_id')->nullable();
            $table->timestamps();

            $table->index(['subject_entity_key', 'subject_id', 'signal_key'], 'ai_signals_subject_key_idx');
            $table->index(['sub_institute_id', 'status', 'severity'], 'ai_signals_tenant_status_idx');
        });

        // ---- Evidence: addressable, citable, with provenance ------------------
        Schema::create('ai_evidence', function (Blueprint $table) {
            $table->id();
            $table->string('evidence_key', 120)->nullable()->index();
            $table->string('kind', 60)->index();                  // assessment_score | attendance_rate | ...
            $table->string('subject_entity_key', 100)->index();
            $table->unsignedBigInteger('subject_id')->index();

            // Provenance — where this came from in the real database.
            $table->string('source_table', 100)->nullable();
            $table->string('source_column', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_service', 255)->nullable();
            $table->timestamp('observed_at')->nullable()->index();

            $table->string('summary', 500)->nullable();
            $table->json('value')->nullable();
            $table->decimal('numeric_value', 14, 4)->nullable();
            $table->string('unit', 32)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();

            // The generated-vs-fact boundary.
            $table->boolean('is_generated')->default(false);
            $table->boolean('verified')->default(false)->index();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedInteger('academic_year')->nullable()->index();
            $table->timestamps();

            $table->index(['subject_entity_key', 'subject_id', 'kind'], 'ai_evidence_subject_kind_idx');
        });

        // ---- Cases: the reviewable unit --------------------------------------
        Schema::create('ai_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_reference', 60)->unique();       // CASE-2026-000123
            $table->string('case_type', 80)->index();             // academic_risk | teacher_support | ...
            $table->string('domain', 40)->default('k12')->index();
            $table->string('title', 300);
            $table->text('summary')->nullable();

            $table->string('subject_entity_key', 100)->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->string('subject_label', 200)->nullable();

            $table->string('severity', 24)->default('low')->index();
            $table->decimal('priority_score', 8, 4)->nullable()->index();
            $table->string('status', 32)->default('open')->index();
            // open | analysing | awaiting_decision | approved | rejected | in_progress | closed | dismissed

            $table->unsignedBigInteger('opened_by_run_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('context')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedInteger('academic_year')->nullable()->index();
            $table->unsignedInteger('term_id')->nullable();
            $table->timestamps();

            $table->index(['sub_institute_id', 'status', 'severity'], 'ai_cases_tenant_status_idx');
        });

        Schema::create('ai_case_signals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->index();
            $table->unsignedBigInteger('signal_id')->index();
            $table->decimal('weight', 6, 4)->default(1);
            $table->timestamps();
            $table->unique(['case_id', 'signal_id']);
        });

        Schema::create('ai_case_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->index();
            $table->unsignedBigInteger('evidence_id')->index();
            $table->string('role', 40)->default('supporting');   // supporting | contradicting | context
            $table->decimal('weight', 6, 4)->default(1);
            $table->timestamps();
            $table->unique(['case_id', 'evidence_id']);
        });

        Schema::create('ai_hypotheses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->index();
            $table->string('statement', 500);
            $table->text('rationale')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('status', 24)->default('proposed');    // proposed|supported|refuted|inconclusive
            $table->json('supporting_evidence_ids')->nullable();
            $table->json('contradicting_evidence_ids')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->timestamps();
        });

        // ---- Explanations: every claim cites evidence -------------------------
        Schema::create('ai_explanations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->index();
            $table->string('audience', 40)->default('teacher');   // teacher|admin|parent|student
            $table->text('narrative');
            // [{claim, evidence_ids:[], confidence}] — validated by GroundedClaims.
            $table->json('claims');
            $table->boolean('is_generated')->default(false);
            $table->boolean('governance_passed')->default(false)->index();
            $table->json('governance_report')->nullable();
            $table->string('generated_by_model', 120)->nullable();
            $table->unsignedBigInteger('generation_output_id')->nullable();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->timestamps();
        });

        // ---- Recommendations: drafted, never auto-executed --------------------
        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('recommendation_reference', 60)->unique();
            $table->unsignedBigInteger('case_id')->nullable()->index();
            $table->unsignedBigInteger('explanation_id')->nullable()->index();
            $table->string('domain', 40)->default('k12')->index();
            $table->string('action_type', 80)->index();           // create_intervention | assign_activity | ...
            $table->string('title', 300);
            $table->text('body')->nullable();
            $table->text('rationale')->nullable();

            $table->string('subject_entity_key', 100)->index();
            $table->unsignedBigInteger('subject_id')->index();

            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('risk_level', 16)->default('low');     // low|medium|high
            $table->boolean('is_consequential')->default(true);
            $table->boolean('requires_approval')->default(true);

            // Governance: which verb produced it, and did it bind correctly.
            $table->string('verb', 40)->default('recommend');
            $table->json('evidence_ids')->nullable();
            $table->json('eso_binding')->nullable();              // {objective, strategy, outcome}
            $table->boolean('governance_passed')->default(false)->index();
            $table->json('governance_report')->nullable();

            // What it will run once approved — a workflow definition key, never raw code.
            $table->string('workflow_key', 120)->nullable();
            $table->json('workflow_payload')->nullable();

            $table->string('status', 32)->default('draft')->index();
            // draft | pending_approval | approved | rejected | superseded | expired | executed
            $table->timestamp('expires_at')->nullable();

            $table->unsignedBigInteger('created_by_run_id')->nullable()->index();
            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedInteger('academic_year')->nullable()->index();
            $table->timestamps();

            $table->index(['sub_institute_id', 'status'], 'ai_recs_tenant_status_idx');
        });

        // ---- Decisions: the durable human approval record ---------------------
        Schema::create('ai_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recommendation_id')->index();
            $table->unsignedBigInteger('case_id')->nullable()->index();
            $table->string('decision', 24)->index();               // approved | rejected | deferred
            $table->text('reason')->nullable();
            $table->json('modifications')->nullable();             // what the human changed before approving

            $table->unsignedBigInteger('decided_by')->index();
            $table->string('decided_by_role', 40)->nullable();
            $table->string('decided_by_name', 150)->nullable();
            $table->timestamp('decided_at')->index();

            // Ties the decision to the MCP confirmation token that carried it, so the
            // existing confirmation flow stays the transport and this stays the record.
            $table->string('confirmation_token', 128)->nullable()->index();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();
        });

        // ---- Outcomes: what actually happened, so the loop can close ----------
        Schema::create('ai_outcomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->nullable()->index();
            $table->unsignedBigInteger('recommendation_id')->nullable()->index();
            $table->unsignedBigInteger('workflow_run_id')->nullable()->index();

            $table->string('subject_entity_key', 100)->index();
            $table->unsignedBigInteger('subject_id')->index();

            $table->string('metric_key', 100)->index();            // e.g. maths_assessment_average
            $table->string('metric_label', 200)->nullable();
            $table->decimal('baseline_value', 14, 4)->nullable();
            $table->timestamp('baseline_at')->nullable();
            $table->decimal('target_value', 14, 4)->nullable();
            $table->decimal('observed_value', 14, 4)->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->decimal('delta', 14, 4)->nullable();

            $table->string('status', 24)->default('pending')->index();
            // pending | measuring | improved | unchanged | worsened | inconclusive
            $table->timestamp('measure_after')->nullable()->index();
            $table->json('detail')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->index();
            $table->unsignedInteger('academic_year')->nullable();
            $table->timestamps();
        });

        // ---- Audit: mirrors mcp_audit_logs so both can be read together -------
        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 64)->nullable()->index();
            $table->string('event_type', 80)->index();
            // agent.run | signal.detected | case.opened | explanation.generated |
            // recommendation.drafted | decision.recorded | workflow.transition |
            // generation.requested | governance.rejected | tool.execution
            $table->string('actor_type', 24)->default('system');   // user | agent | system
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_label', 150)->nullable();

            $table->string('subject_entity_key', 100)->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('related_type', 80)->nullable();
            $table->unsignedBigInteger('related_id')->nullable()->index();

            $table->string('outcome', 24)->nullable()->index();     // success | failure | rejected
            $table->text('message')->nullable();
            $table->longText('payload')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();

            $table->index(['event_type', 'created_at'], 'ai_audit_event_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
        Schema::dropIfExists('ai_outcomes');
        Schema::dropIfExists('ai_decisions');
        Schema::dropIfExists('ai_recommendations');
        Schema::dropIfExists('ai_explanations');
        Schema::dropIfExists('ai_hypotheses');
        Schema::dropIfExists('ai_case_evidence');
        Schema::dropIfExists('ai_case_signals');
        Schema::dropIfExists('ai_cases');
        Schema::dropIfExists('ai_evidence');
        Schema::dropIfExists('ai_signals');
        Schema::dropIfExists('ai_signal_definitions');
    }
};
