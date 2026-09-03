<?php

/*
 * Enterprise Brain schema — ported into the K-12 LMS backend.
 *
 * Source of truth: hp-enterprise-brain/database/migrations/2026_08_02_000100_backfill_missing_indexes.php
 * (read-only reference; that repository is not modified).
 *
 * Every table here is hpbrain_-prefixed. The Brain was designed to share a
 * database with the institute ERP, so nothing below collides with, alters or
 * duplicates an LMS table — Organization, Departments, People, Positions and
 * Students continue to live in the LMS's own tables and are reached through
 * hpbrain_entity_mappings.
 */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill every index the earlier migrations declared but never created.
 *
 * WHY THIS IS NEEDED. Thirteen migrations called `DB::unprepaired(...)` — a
 * misspelling of `unprepared`, and not a method on the DB facade. Every one of
 * those statements threw BadMethodCallException instead of executing. Twenty of
 * them were CREATE INDEX statements, so those indexes do not exist on any
 * database that ran the migrations while the typo was present.
 *
 * Fixing the spelling in the original files does not help: those migrations are
 * already recorded in the `migrations` table and will never run again. The
 * indexes have to be re-asserted by a new migration, which is this one.
 *
 * WHY IT RE-ASSERTS ALL 227 RATHER THAN THE 20 KNOWN-MISSING. A migration that
 * aborts partway leaves everything after the failure point undone, and these
 * failures were scattered across thirteen files. Rather than reason about which
 * statements each aborted run did or did not reach — which depends on the order
 * they ran and on which tables already existed — this asserts the full declared
 * set. Every index is guarded by a lookup, so the ones that already exist cost
 * a metadata read and nothing else.
 *
 * WHY THE INDEXES MATTER. Every one of them leads with tenant_id, which is the
 * column every query in the application filters on. Without them, each read is a
 * full table scan, and the scan gets slower with every row any tenant adds.
 *
 * IDEMPOTENT AND NON-DESTRUCTIVE. It only ever adds; it never drops or alters.
 * A missing table is skipped rather than treated as an error, because the two
 * tables whose CREATE TABLE was also behind the typo may legitimately not exist
 * yet. down() is deliberately a no-op: dropping indexes that other migrations
 * also declare would leave the schema worse than it started.
 */
return new class extends Migration
{
    /**
     * table => [[index name, column list, is unique], ...]
     *
     * Extracted mechanically from every CREATE INDEX statement in
     * database/migrations, so this list and the declarations stay in step.
     *
     * @var array<string, array<int, array{0:string,1:string,2:bool}>>
     */
    private const INDEXES = [
        "hpbrain_accreditation_criteria" => [
            ["idx_accreditation_criteria_framework", "tenant_id, framework_id", false],
        ],
        "hpbrain_ai_evaluations" => [
            ["idx_ai_evaluations_tenant_id", "tenant_id", false],
        ],
        "hpbrain_ai_executions" => [
            ["idx_ai_executions_tenant", "tenant_id, created_date DESC", false],
        ],
        "hpbrain_ai_fallback_chains" => [
            ["idx_ai_fallback_chains_tenant_id", "tenant_id", false],
        ],
        "hpbrain_ai_feedback" => [
            ["idx_ai_feedback_execution_id", "execution_id", false],
            ["idx_ai_feedback_tenant_id", "tenant_id", false],
        ],
        "hpbrain_ai_prompt_templates" => [
            ["idx_ai_prompt_templates_prompt_key", "prompt_key", false],
            ["idx_ai_prompt_templates_tenant_id", "tenant_id", false],
        ],
        "hpbrain_ai_providers" => [
            ["idx_ai_providers_is_active", "is_active", false],
            ["idx_ai_providers_tenant_id", "tenant_id", false],
        ],
        "hpbrain_ai_quotas" => [
            ["idx_ai_quotas_tenant_id", "tenant_id", false],
        ],
        "hpbrain_ai_safety_rules" => [
            ["idx_ai_safety_rules_tenant_id", "tenant_id", false],
        ],
        "hpbrain_api_keys" => [
            ["idx_api_keys_tenant", "tenant_id, user_id", false],
            ["idx_api_keys_hash", "key_hash", true],
        ],
        "hpbrain_audit_logs" => [
            ["idx_audit_logs_correlation_id", "correlation_id", false],
            ["idx_audit_logs_created_at", "created_at", false],
            ["idx_audit_logs_entity", "entity_type, entity_id", false],
            ["idx_audit_logs_event_id", "event_id", false],
            ["idx_audit_logs_org_id", "org_id", false],
            ["idx_audit_logs_source", "source", false],
            ["idx_audit_logs_status", "status", false],
            ["idx_audit_logs_tenant_id", "tenant_id", false],
        ],
        "hpbrain_branding" => [
            ["idx_branding_tenant_id", "tenant_id", false],
        ],
        "hpbrain_capabilities" => [
            ["idx_capabilities_category", "category", false],
            ["idx_capabilities_org_id", "org_id", false],
            ["idx_capabilities_status", "status", false],
            ["idx_capabilities_tenant_id", "tenant_id", false],
            ["idx_capabilities_tenant_code", "tenant_id, capability_code", true],
        ],
        "hpbrain_capability_assignments" => [
            ["idx_capability_assignments_cap", "capability_id", false],
            ["idx_capability_assignments_target", "target_type, target_id", false],
            ["idx_capability_assignments_tenant", "tenant_id", false],
            ["idx_capability_assignments_uniq", "tenant_id, capability_id, target_type, target_id", true],
        ],
        "hpbrain_capability_proficiency" => [
            ["idx_capability_proficiency_assignment", "tenant_id, assignment_id", false],
        ],
        "hpbrain_capability_tasks" => [
            ["idx_capability_tasks_capability", "tenant_id, capability_id", false],
            ["idx_capability_tasks_parent", "tenant_id, parent_task_id", false],
        ],
        "hpbrain_capability_versions" => [
            ["idx_capability_versions_cap", "capability_id", false],
            ["idx_capability_versions_tenant", "tenant_id", false],
        ],
        "hpbrain_career_clusters" => [
            ["idx_career_clusters_code", "tenant_id, code", true],
        ],
        "hpbrain_case_evidence" => [
            ["idx_case_evidence_tenant", "tenant_id", false],
        ],
        "hpbrain_cases" => [
            ["idx_cases_signal", "tenant_id, signal_id", false],
            ["idx_cases_status", "tenant_id, status", false],
            ["idx_cases_tenant", "tenant_id", false],
        ],
        "hpbrain_competencies" => [
            ["idx_competencies_category", "category", false],
            ["idx_competencies_competency_key", "competency_key", false],
            ["idx_competencies_tenant_id", "tenant_id", false],
        ],
        "hpbrain_config_versions" => [
            ["idx_config_versions_activated_date", "activated_date", false],
            ["idx_config_versions_status", "status", false],
            ["idx_config_versions_tenant_id", "tenant_id", false],
        ],
        "hpbrain_consumer_state" => [
            ["idx_consumer_state_name", "consumer_name", false],
        ],
        "hpbrain_context_entities" => [
            ["idx_context_entities_org", "tenant_id, org_id", false],
            ["idx_context_entities_type", "tenant_id, entity_type", false],
        ],
        "hpbrain_conversation_messages" => [
            ["idx_conversation_messages_session", "tenant_id, session_id", false],
        ],
        "hpbrain_conversation_sessions" => [
            ["idx_conversation_sessions_pinned", "tenant_id, pinned", false],
            ["idx_conversation_sessions_tenant", "tenant_id", false],
            ["idx_conversation_sessions_user", "tenant_id, created_by", false],
        ],
        "hpbrain_criterion_evidence" => [
            ["idx_criterion_evidence_tenant", "tenant_id", false],
        ],
        "hpbrain_dashboard_layouts" => [
            ["idx_dashboard_layouts_dashboard_id", "dashboard_id", false],
            ["idx_dashboard_layouts_tenant_id", "tenant_id", false],
        ],
        "hpbrain_dashboard_widgets" => [
            ["idx_dashboard_widgets_category", "category", false],
            ["idx_dashboard_widgets_is_system", "is_system", false],
            ["idx_dashboard_widgets_tenant_id", "tenant_id", false],
        ],
        "hpbrain_dashboards" => [
            ["idx_dashboards_is_default", "is_default", false],
            ["idx_dashboards_tenant_id", "tenant_id", false],
        ],
        "hpbrain_dead_letter_queue" => [
            ["idx_dlq_consumer", "consumer_name", false],
            ["idx_dlq_created_at", "created_at", false],
            ["idx_dlq_event_id", "event_id", false],
        ],
        "hpbrain_decisions" => [
            ["idx_decisions_tenant", "tenant_id", false],
        ],
        "hpbrain_departments" => [
            ["idx_departments_org_id", "org_id", false],
            ["idx_departments_parent_id", "parent_department_id", false],
            ["idx_departments_status", "status", false],
            ["idx_departments_tenant_id", "tenant_id", false],
        ],
        "hpbrain_entity_mappings" => [
            ["idx_entity_mappings_is_active", "is_active", false],
            ["idx_entity_mappings_tenant_id", "tenant_id", false],
        ],
        "hpbrain_eso_definitions" => [
            ["idx_eso_definitions_kasba_node", "tenant_id, kasba_node_id", false],
            ["idx_eso_definitions_objective", "tenant_id, objective", false],
            ["idx_eso_definitions_org", "tenant_id, org_id", false],
            ["idx_eso_definitions_status", "tenant_id, status", false],
        ],
        "hpbrain_eso_efficacy_records" => [
            ["idx_eso_efficacy_eso", "tenant_id, eso_definition_id", false],
            ["idx_eso_efficacy_gap_type", "tenant_id, gap_type", false],
        ],
        "hpbrain_eso_execution_evidence" => [
            ["idx_eso_execution_evidence_tenant", "tenant_id", false],
        ],
        "hpbrain_eso_executions" => [
            ["idx_eso_executions_definition", "eso_definition_id", false],
            ["idx_eso_executions_eso", "tenant_id, eso_id", false],
            ["idx_eso_executions_tenant", "tenant_id", false],
        ],
        "hpbrain_event_store" => [
            ["idx_event_store_correlation", "correlation_id", false],
            ["idx_event_store_created_at", "created_at", false],
            ["idx_event_store_entity", "entity_type, entity_id", false],
            ["idx_event_store_status", "status", false],
            ["idx_event_store_tenant_id", "tenant_id", false],
            ["idx_event_store_type", "type", false],
            ["idx_event_store_idempotency", "idempotency_key", true],
        ],
        "hpbrain_evidence" => [
            ["idx_evidence_signal", "tenant_id, signal_id", false],
            ["idx_evidence_tenant", "tenant_id", false],
        ],
        "hpbrain_executors" => [
            ["idx_executors_tenant", "tenant_id", false],
            ["idx_executors_type", "tenant_id, executor_type", false],
        ],
        "hpbrain_feature_flags" => [
            ["idx_feature_flags_enabled", "enabled", false],
            ["idx_feature_flags_tenant_id", "tenant_id", false],
        ],
        "hpbrain_forms" => [
            ["idx_forms_entity_type", "entity_type", false],
            ["idx_forms_is_active", "is_active", false],
            ["idx_forms_tenant_id", "tenant_id", false],
        ],
        "hpbrain_guardians" => [
            ["idx_guardians_student", "tenant_id, student_person_id", false],
        ],
        "hpbrain_health_checks" => [
            ["idx_health_checks_checked_at", "checked_at", false],
            ["idx_health_checks_name", "check_name", false],
            ["idx_health_checks_status", "status", false],
        ],
        "hpbrain_hypotheses" => [
            ["idx_hypotheses_case", "tenant_id, case_id", false],
            ["idx_hypotheses_tenant", "tenant_id", false],
        ],
        "hpbrain_import_jobs" => [
            ["idx_import_jobs_import_type", "import_type", false],
            ["idx_import_jobs_org_id", "org_id", false],
            ["idx_import_jobs_status", "status", false],
            ["idx_import_jobs_tenant_id", "tenant_id", false],
        ],
        "hpbrain_import_logs" => [
            ["idx_import_logs_action", "action", false],
            ["idx_import_logs_import_job_id", "import_job_id", false],
            ["idx_import_logs_tenant_id", "tenant_id", false],
        ],
        "hpbrain_industries" => [
            ["idx_industries_code", "code", false],
            ["idx_industries_status", "status", false],
            ["idx_industries_tenant_id", "tenant_id", false],
        ],
        "hpbrain_industry_templates" => [
            ["idx_industry_templates_is_active", "is_active", false],
            ["idx_industry_templates_tenant_id", "tenant_id", false],
        ],
        "hpbrain_job_role_capability_requirements" => [
            ["idx_job_role_requirements_tenant", "tenant_id", false],
        ],
        "hpbrain_knowledge_assets" => [
            ["idx_knowledge_assets_department", "tenant_id, department_id", false],
            ["idx_knowledge_assets_tenant", "tenant_id, category", false],
        ],
        "hpbrain_learnings" => [
            ["idx_learnings_outcome", "tenant_id, outcome_id", false],
            ["idx_learnings_tenant", "tenant_id", false],
        ],
        "hpbrain_location_types" => [
            ["idx_location_types_tenant_id", "tenant_id", false],
            ["idx_location_types_type_key", "type_key", false],
        ],
        "hpbrain_locations" => [
            ["idx_locations_is_headquarters", "is_headquarters", false],
            ["idx_locations_org_id", "org_id", false],
            ["idx_locations_tenant_id", "tenant_id", false],
        ],
        "hpbrain_logs" => [
            ["idx_logs_correlation_id", "correlation_id", false],
            ["idx_logs_created_at", "created_at", false],
            ["idx_logs_level", "level", false],
            ["idx_logs_tenant_id", "tenant_id", false],
        ],
        "hpbrain_mental_models" => [
            ["idx_mental_models_domain", "tenant_id, domain", false],
            ["idx_mental_models_tenant", "tenant_id", false],
        ],
        "hpbrain_metrics" => [
            ["idx_metrics_name", "metric_name", false],
            ["idx_metrics_recorded_at", "recorded_at", false],
            ["idx_metrics_tenant_id", "tenant_id", false],
        ],
        "hpbrain_modules" => [
            ["idx_modules_is_core", "is_core", false],
            ["idx_modules_is_enabled", "is_enabled", false],
            ["idx_modules_tenant_id", "tenant_id", false],
        ],
        "hpbrain_navigation_items" => [
            ["idx_navigation_items_is_visible", "is_visible", false],
            ["idx_navigation_items_tenant_id", "tenant_id", false],
        ],
        "hpbrain_notifications" => [
            ["idx_notifications_unread", "tenant_id, user_id", false],
            ["idx_notifications_user", "tenant_id, user_id, created_date DESC", false],
        ],
        "hpbrain_occupation_capability_requirements" => [
            ["idx_occupation_requirements_tenant", "tenant_id", false],
        ],
        "hpbrain_occupations" => [
            ["idx_occupations_code", "tenant_id, occupation_code", true],
        ],
        "hpbrain_onboarding_sessions" => [
            ["idx_onboarding_sessions_org_id", "org_id", false],
            ["idx_onboarding_sessions_status", "status", false],
            ["idx_onboarding_sessions_tenant_id", "tenant_id", false],
        ],
        "hpbrain_organization_configs" => [
            ["idx_org_configs_is_active", "is_active", false],
            ["idx_org_configs_org_id", "org_id", false],
            ["idx_org_configs_tenant_id", "tenant_id", false],
        ],
        "hpbrain_organization_modules" => [
            ["idx_org_modules_is_enabled", "is_enabled", false],
            ["idx_org_modules_org_id", "org_id", false],
            ["idx_org_modules_tenant_id", "tenant_id", false],
        ],
        "hpbrain_organization_types" => [
            ["idx_org_types_status", "status", false],
            ["idx_org_types_tenant_id", "tenant_id", false],
            ["idx_org_types_type_key", "type_key", false],
        ],
        "hpbrain_organization_units" => [
            ["idx_org_units_org_id", "org_id", false],
            ["idx_org_units_parent_unit_id", "parent_unit_id", false],
            ["idx_org_units_status", "status", false],
            ["idx_org_units_tenant_id", "tenant_id", false],
            ["idx_org_units_unit_type", "unit_type", false],
        ],
        "hpbrain_organizations" => [
            ["idx_organizations_status", "status", false],
            ["idx_organizations_tenant_id", "tenant_id", false],
        ],
        "hpbrain_outcomes" => [
            ["idx_outcomes_decision", "tenant_id, decision_id", false],
            ["idx_outcomes_tenant", "tenant_id", false],
        ],
        "hpbrain_people" => [
            ["idx_people_department_id", "department_id", false],
            ["idx_people_manager_id", "manager_id", false],
            ["idx_people_org_id", "org_id", false],
            ["idx_people_status", "status", false],
            ["idx_people_tenant_id", "tenant_id", false],
            ["idx_people_tenant_email", "tenant_id, email", true],
            ["idx_people_tenant_employee_id", "tenant_id, employee_id", true],
        ],
        "hpbrain_person_competencies" => [
            ["idx_person_competencies_current_level", "current_level", false],
            ["idx_person_competencies_person_id", "person_id", false],
            ["idx_person_competencies_tenant_id", "tenant_id", false],
        ],
        "hpbrain_person_roles" => [
            ["idx_person_roles_person_id", "person_id", false],
            ["idx_person_roles_tenant_id", "tenant_id", false],
            ["idx_person_roles_unit_id", "unit_id", false],
        ],
        "hpbrain_person_skills" => [
            ["idx_person_skills_person_id", "person_id", false],
            ["idx_person_skills_proficiency_level", "proficiency_level", false],
            ["idx_person_skills_tenant_id", "tenant_id", false],
        ],
        "hpbrain_placement_job_roles" => [
            ["idx_placement_job_roles_company", "tenant_id, company_id", false],
        ],
        "hpbrain_policies" => [
            ["idx_policies_tenant", "tenant_id", false],
            ["idx_policies_type", "tenant_id, policy_type", false],
        ],
        "hpbrain_positions" => [
            ["idx_positions_is_vacant", "is_vacant", false],
            ["idx_positions_org_id", "org_id", false],
            ["idx_positions_tenant_id", "tenant_id", false],
            ["idx_positions_unit_id", "unit_id", false],
        ],
        "hpbrain_process_definitions" => [
            ["idx_process_definitions_org", "tenant_id, org_id", false],
            ["idx_process_definitions_status", "tenant_id, status", false],
        ],
        "hpbrain_prompt_templates" => [
            ["idx_prompt_templates_tenant", "tenant_id", false],
        ],
        "hpbrain_readiness_checks" => [
            ["idx_readiness_checks_check_type", "check_type", false],
            ["idx_readiness_checks_org_id", "org_id", false],
            ["idx_readiness_checks_status", "status", false],
            ["idx_readiness_checks_tenant_id", "tenant_id", false],
        ],
        "hpbrain_reasoning_patterns" => [
            ["idx_reasoning_patterns_org", "tenant_id, org_id", false],
            ["idx_reasoning_patterns_type", "tenant_id, pattern_type", false],
        ],
        "hpbrain_reasoning_steps" => [
            ["idx_reasoning_steps_signal", "tenant_id, signal_id", false],
            ["idx_reasoning_steps_tenant", "tenant_id", false],
        ],
        "hpbrain_recommendations" => [
            ["idx_recommendations_eso", "tenant_id, eso_id", false],
            ["idx_recommendations_tenant", "tenant_id", false],
        ],
        "hpbrain_reporting_structures" => [
            ["idx_reporting_structures_org_id", "org_id", false],
            ["idx_reporting_structures_tenant_id", "tenant_id", false],
            ["idx_reporting_structures_unit_id", "unit_id", false],
        ],
        "hpbrain_risks" => [
            ["idx_risks_decision", "tenant_id, decision_id", false],
            ["idx_risks_tenant", "tenant_id", false],
        ],
        "hpbrain_roles" => [
            ["idx_roles_category", "category", false],
            ["idx_roles_role_key", "role_key", false],
            ["idx_roles_tenant_id", "tenant_id", false],
        ],
        "hpbrain_signals" => [
            ["idx_signals_department", "tenant_id, department_id", false],
            ["idx_signals_status", "tenant_id, status", false],
            ["idx_signals_tenant", "tenant_id", false],
        ],
        "hpbrain_skills" => [
            ["idx_skills_category", "category", false],
            ["idx_skills_skill_key", "skill_key", false],
            ["idx_skills_tenant_id", "tenant_id", false],
        ],
        "hpbrain_telemetry_events" => [
            ["idx_telemetry_events_entity", "tenant_id, entity_type, entity_id", false],
            ["idx_telemetry_events_metric", "tenant_id, metric_name", false],
            ["idx_telemetry_events_org", "tenant_id, org_id", false],
            ["idx_telemetry_events_type", "tenant_id, event_type", false],
        ],
        "hpbrain_template_overrides" => [
            ["idx_template_overrides_is_active", "is_active", false],
            ["idx_template_overrides_org_id", "org_id", false],
            ["idx_template_overrides_tenant_id", "tenant_id", false],
        ],
        "hpbrain_terminology" => [
            ["idx_terminology_status", "status", false],
            ["idx_terminology_tenant_id", "tenant_id", false],
        ],
        "hpbrain_themes" => [
            ["idx_themes_is_default", "is_default", false],
            ["idx_themes_tenant_id", "tenant_id", false],
        ],    ];

    public function up(): void
    {
        // SHOW INDEX is MySQL-specific. The suite runs on SQLite against a
        // hand-built fixture that does not use migrations at all, so there is
        // nothing here for it to do and every statement below would be a syntax
        // error on that driver.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as [$name, $columns, $unique]) {
                if ($this->indexExists($table, $name)) {
                    $skipped++;

                    continue;
                }

                // Guarded individually: one malformed or already-satisfied
                // index must not abort the remaining 226, which is precisely
                // the failure mode that made this migration necessary.
                try {
                    DB::unprepared(sprintf(
                        'CREATE %sINDEX %s ON %s (%s)',
                        $unique ? 'UNIQUE ' : '',
                        $name,
                        $table,
                        $columns,
                    ));
                    $created++;
                } catch (\Throwable $e) {
                    // A UNIQUE index can legitimately fail on data that already
                    // violates it. That is a data problem to resolve
                    // deliberately, not a reason to abandon the other indexes.
                    logger()->warning('index_backfill_failed', [
                        'table' => $table,
                        'index' => $name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        logger()->info('index_backfill_complete', ['created' => $created, 'already_present' => $skipped]);
    }

    /**
     * Does an index of this NAME exist on the table?
     *
     * Matching on name rather than on columns is deliberate. The original
     * migrations create these by name, so a name match is exactly the question
     * "did that statement already succeed?". A column-shape match would also
     * skip an index that happens to cover the same columns under a different
     * name, which is usually fine but would silently diverge from what the
     * declarations say the schema contains.
     */
    private function indexExists(string $table, string $name): bool
    {
        return DB::select(
            'SELECT 1 FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
              LIMIT 1',
            [$table, $name],
        ) !== [];
    }

    public function down(): void
    {
        // No-op by design. These indexes are declared by the migrations this one
        // repairs, so dropping them here would remove indexes those migrations
        // consider part of the schema.
    }
};
