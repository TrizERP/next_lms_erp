<?php

namespace App\Services\Fees\Eso;

use App\Mcp\ToolRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Executive Skill Object (ESO) for the three sequential Fees agents.
 *
 * Mirrors TeachLearnEsoRegistry: each agent is described by Knowledge (EK), Abilities (EA),
 * Rules (ER) and Quality (EQ), plus the legacy four-pillar `eso` block the modal reads. The
 * descriptions are static prose about the design; every figure in `live_metrics` is counted
 * from this institute's own tables at request time.
 */
class FeesEsoRegistry
{
    public const AGENT_1_KEY = 'fees.position+fees.arrears';
    public const AGENT_2_KEY = 'fees.getPending+fees.collection_report+fees.reminder_history';
    public const AGENT_3_KEY = 'fees.send_reminders:preview';

    public function __construct(private readonly ToolRegistry $registry)
    {
    }

    public function getExpertSystemOverview(int $subInstituteId, ?int $academicYear = null): array
    {
        $metrics = $this->liveMetrics($subInstituteId, $academicYear);

        $agents = [
            'agent_1' => $this->agent1($subInstituteId, $metrics),
            'agent_2' => $this->agent2($subInstituteId, $metrics),
            'agent_3' => $this->agent3($subInstituteId, $metrics),
        ];

        $capabilities = $this->capabilities();
        $total = count($capabilities);
        $full = count(array_filter($capabilities, static fn ($c) => $c['status'] === 'fully_implemented'));
        $partial = count(array_filter($capabilities, static fn ($c) => $c['status'] === 'partially_implemented'));
        $summary = [
            'total' => $total,
            'fully_implemented' => $full,
            'partially_implemented' => $partial,
            'unavailable' => $total - $full - $partial,
            'readiness_percentage' => $total === 0 ? 0 : (int) round(($full + $partial * 0.5) / $total * 100),
        ];

        return [
            'overview' => [
                'system_name' => 'Fees Sequential Agent Pipeline',
                'framework_version' => '1.0.0',
                'module' => 'fees',
                'sub_institute_id' => $subInstituteId,
                'academic_year' => $academicYear,
                'architecture' => 'Sequential Pipeline with Multi-Gate Handshakes (EK · EA · ER · EQ)',
                'generated_at' => Carbon::now()->toIso8601String(),
                'total_agents' => 3,
                'status' => 'operational',
                'capability_summary' => [
                    'total_capabilities' => $total,
                    'fully_implemented' => $full,
                    'partially_implemented' => $partial,
                    'unavailable' => $summary['unavailable'],
                    'readiness_percentage' => $summary['readiness_percentage'],
                ],
            ],
            'agents' => $agents,
            'execution_workflow' => [
                'pattern' => 'Agent 1 → Gate 1 → Agent 2 → Gate 2 → Agent 3 → Gate 3 → Unified Report',
                'execution_order' => [
                    'Step 1: Fee Position & Arrears Reader — who owes, how much, and how reliable that picture is',
                    'Step 2: Collection Evidence & Reminder History Analyst — line-item evidence, recent payments, and who was already reminded',
                    'Step 3: Reminder Drafter — a previewed, fingerprinted reminder list that stops at an administrator approval',
                ],
                'data_flow' => [
                    'input_to_step_1' => 'academic year (resolved), optional standard_id / min_amount / scan limit',
                    'step_1_to_step_2' => 'candidates[]: student_id, name, class, outstanding, pending_items',
                    'step_2_to_step_3' => 'eligible[] (cleared to remind) and held_back[] (with the reason)',
                    'step_3_to_final' => 'preview: recipients, unreachable, sample message, deadline, approval fingerprint',
                ],
                'isolation_and_safety' => [
                    'read_only_default' => true,
                    'writes_to_database' => 'ai_agent_runs only (one row per step). No fee record is created or changed.',
                    'sends_messages' => false,
                    'tenant_isolated' => true,
                    'rbac_enforced' => 'Each tool re-checks fees.collect / fees.remind; sending needs an administrator and the approval fingerprint.',
                    'idempotent_generation' => 'Re-running produces a new preview; nothing accumulates.',
                ],
            ],
            'capability_registry' => ['items' => $capabilities, 'summary' => $summary],
        ];
    }

    /**
     * Validate a handoff between two agents.
     */
    public function validateHandoff(string $from, string $to, array $payload): array
    {
        if ($from === 'agent_1' && $to === 'agent_2') {
            if (! isset($payload['candidates']) || ! is_array($payload['candidates']) || $payload['candidates'] === []) {
                return [
                    'valid' => false,
                    'error' => 'Handoff Contract Violation: Agent 1 produced no students with arrears to analyse.',
                    'from_agent' => $from,
                    'to_agent' => $to,
                ];
            }

            return [
                'valid' => true,
                'message' => 'Handoff Contract Verified: Agent 1 -> Agent 2 arrears candidates passed successfully.',
                'candidates_count' => count($payload['candidates']),
            ];
        }

        if ($from === 'agent_2' && $to === 'agent_3') {
            if (! isset($payload['eligible']) || ! is_array($payload['eligible']) || ! isset($payload['held_back']) || ! is_array($payload['held_back'])) {
                return [
                    'valid' => false,
                    'error' => 'Handoff Contract Violation: Agent 2 must emit both eligible and held_back arrays.',
                    'from_agent' => $from,
                    'to_agent' => $to,
                ];
            }

            return [
                'valid' => true,
                'message' => 'Handoff Contract Verified: Agent 2 -> Agent 3 reminder eligibility passed successfully.',
                'eligible_count' => count($payload['eligible']),
                'held_back_count' => count($payload['held_back']),
            ];
        }

        return ['valid' => false, 'error' => "Unknown handoff transition: {$from} -> {$to}"];
    }

    private function agent1(int $inst, array $metrics): array
    {
        return $this->build(1, 'Fee Position & Arrears Reader', 'agt_fees_position_reader', self::AGENT_1_KEY, [
            'role' => 'Establishes the factual baseline: the whole-school ledger position, then a bounded per-student arrears scan that names who owes.',
            'tables' => ['fees_breackoff', 'fees_collect', 'fees_paid_other', 'fees_breakoff_other', 'fees_title', 'tblstudent_enrollment', 'fees_map_years'],
            'knowledge' => [
                'Demand is derived, not stored: fees_breackoff (per grade, standard, quota, admission year and month) joined to each student\'s enrollment.',
                'Collected = fees_collect + fees_paid_other (non-deleted receipts); pending is computed per student by the same calculation the fee screen uses.',
                'Fee data belongs to an institute AND an academic year — a different year is a different ledger.',
                'fees.arrears checks a bounded cohort; the school-wide total always comes from fees.position, never from the sample.',
            ],
            'activities' => [
                'Resolve the academic year (request, session, else the latest year that has a fee structure)',
                'Call fees.position for demand, collected, outstanding, collection rate, ageing and the classes owing most',
                'Call fees.arrears (optionally per standard / minimum amount) to list students who owe',
                'Disclose scan coverage (checked of cohort) and any lookup failures',
                'Run Gate 1 and hand the candidate list to Agent 2',
            ],
            'skills' => ['Ledger reading', 'Per-student arrears calculation', 'Coverage disclosure', 'Class-level concentration analysis'],
            'safeguards' => ['Read-only', 'Tenant and academic-year scoped', 'A failed lookup is never reported as "nobody owes"', 'Sample never presented as school-wide'],
            'must_do' => ['Resolve a real academic year', 'Report checked vs cohort', 'Pass only students with a positive balance'],
            'must_not' => ['Invent or default a tenant id', 'Treat a sample as a school total', 'Modify any fee record'],
            'gate' => ['Gate 1', 'Position & Arrears Grounding', ['tenant_isolation', 'academic_year_resolved', 'ledger_grounded', 'arrears_scan_completed', 'coverage_disclosed', 'scan_consistent_with_ledger']],
            'usage' => [
                'Resolve the institute and academic year',
                'Read the school-wide ledger position',
                'Scan for students with arrears',
                'Validate against Gate 1 and pass candidates onward',
            ],
            'downstream' => 'Agent 2 receives candidates[] (student, class, outstanding, pending item count).',
            'upstream' => 'None — first step.',
            'metrics' => $metrics,
            'inst' => $inst,
        ]);
    }

    private function agent2(int $inst, array $metrics): array
    {
        return $this->build(2, 'Collection Evidence & Reminder History Analyst', 'agt_fees_evidence_analyst', self::AGENT_2_KEY, [
            'role' => 'Corroborates each candidate with line-item evidence, shows what has actually been collected recently, and holds back anyone who was already reminded.',
            'tables' => ['fees_collect', 'fees_title', 'ai_audit_logs', 'sms_sent_parents'],
            'knowledge' => [
                'fees.getPending returns the pending fee heads/months for one student, from the fee screen\'s own calculation.',
                'fees.collection_report totals receipts dated inside a period, by payment mode, across academic years.',
                'fees.reminder_history reads reminder records from ai_audit_logs (event module.fees.fee_reminder); "sent" means accepted, never delivered.',
                'A reminder cooldown stops the same family being contacted repeatedly.',
            ],
            'activities' => [
                'Pull pending line items for the highest balances (bounded, because each lookup is a per-student calculation)',
                'Read the period collection total as a context check',
                'Read recent reminder history and match it to candidates by student id',
                'Classify every candidate as eligible or held back, with the reason',
                'Run Gate 2 and hand eligible[] / held_back[] to Agent 3',
            ],
            'skills' => ['Line-item evidence gathering', 'Duplicate-contact prevention', 'Reconciling collections with balances'],
            'safeguards' => ['Read-only', 'Bounded evidence lookups', 'Every candidate accounted for', 'Held-back students keep their reason'],
            'must_do' => ['Classify every candidate', 'Cite pending items or say none were found', 'Honour the reminder cooldown'],
            'must_not' => ['Drop a student silently', 'Treat "sent" as delivered', 'Contact anyone'],
            'gate' => ['Gate 2', 'Evidence & Duplicate-Reminder Audit', ['candidates_received', 'collection_report_read', 'reminder_history_read', 'every_candidate_accounted_for', 'no_double_classification', 'amounts_positive']],
            'usage' => [
                'Receive validated candidates from Agent 1',
                'Collect line-item evidence and recent collections',
                'Apply the reminder cooldown',
                'Validate against Gate 2 and pass eligible students onward',
            ],
            'downstream' => 'Agent 3 receives eligible[] and held_back[].',
            'upstream' => 'Agent 1 candidates[] through Gate 1.',
            'metrics' => $metrics,
            'inst' => $inst,
        ]);
    }

    private function agent3(int $inst, array $metrics): array
    {
        return $this->build(3, 'Reminder Drafter', 'agt_fees_reminder_drafter', self::AGENT_3_KEY, [
            'role' => 'Turns the cleared list into a concrete reminder preview — recipients, unreachable families, the exact wording and a fingerprint — and stops there for a human.',
            'tables' => ['tblstudent', 'sms_api_details', 'ai_audit_logs'],
            'knowledge' => [
                'fees.send_reminders is two-step: a preview that sends nothing, then a send that needs an administrator and the approved fingerprint.',
                'Students with no usable email or mobile number are listed as unreachable and never contacted.',
                'The fingerprint binds the approval to this exact list, channel and wording.',
            ],
            'activities' => [
                'Call the reminder preview for exactly the eligible student ids',
                'Report reachable vs unreachable families and the sample message',
                'Verify recipients are a subset of Gate 2 eligible',
                'Run Gate 3 and compile the unified report',
            ],
            'skills' => ['Multi-channel reminder drafting (email / SMS / WhatsApp)', 'Reachability analysis', 'Approval fingerprinting'],
            'safeguards' => ['Preview only — this pipeline can never send', 'Administrator rights required for the preview', 'Fingerprint must be approved by a person'],
            'must_do' => ['Preview only eligible students', 'Surface unreachable families', 'Show the exact message'],
            'must_not' => ['Call the send step', 'Add a recipient Gate 2 did not clear', 'Use a past deadline'],
            'gate' => ['Gate 3', 'Reminder Draft Safety', ['preview_generated', 'recipients_within_eligible', 'deadline_valid', 'approval_fingerprint_present', 'nothing_sent']],
            'usage' => [
                'Receive eligible students from Agent 2',
                'Generate the reminder preview',
                'Validate against Gate 3',
                'Hand the fingerprinted preview to an administrator for approval',
            ],
            'downstream' => 'Final report. An administrator approves the fingerprint through the Fees assistant to send.',
            'upstream' => 'Agent 2 eligible[] through Gate 2.',
            'metrics' => $metrics,
            'inst' => $inst,
        ]);
    }

    private function build(int $order, string $name, string $agentId, string $tool, array $c): array
    {
        [$gateName, $gateTitle, $rules] = $c['gate'];

        return [
            'order' => $order,
            'name' => $name,
            'agent_id' => $agentId,
            'backend_key' => $tool,
            'module' => 'fees',
            'status' => 'active',
            'trigger' => $order === 1 ? 'Run pipeline' : "Gate {$order} - 1 handoff",
            'tool_key' => $tool,
            'tool_alias' => $tool,
            'role_description' => $c['role'],
            'knowledge_requirements' => ['summary' => $c['role'], 'items' => $c['knowledge']],
            'database_resources' => ['primary_tables' => $c['tables'], 'live_metrics' => $c['metrics']],
            'outputs' => $c['downstream'],
            'validation_rules' => ['gate' => $gateName, 'name' => $gateTitle, 'enforcement' => 'blocking', 'rules' => $rules],
            'ek' => [
                'summary' => $c['role'],
                'schemas_and_relationships' => $c['knowledge'],
                'academic_curriculum_knowledge' => [],
                'live_metrics' => $c['metrics'],
                'tables_and_sources' => $c['tables'],
            ],
            'ea' => [
                'summary' => 'What this agent can do, and the limits it works inside.',
                'skills' => $c['skills'],
                'capabilities' => $c['activities'],
                'constraints_and_safeguards' => $c['safeguards'],
            ],
            'er' => [
                'summary' => 'The rules this agent must follow.',
                'must_do' => $c['must_do'],
                'must_not_do' => $c['must_not'],
                'allowed_data_access' => $c['tables'],
                'dependencies' => $c['upstream'],
                'stopping_conditions' => ["{$gateName} fails", 'Required upstream handoff is invalid'],
                'error_handling' => 'A failing step stops the pipeline at its gate and reports the reason; later agents do not run on missing data.',
                'handoff_rules' => $c['downstream'],
                'state_and_history' => 'One ai_agent_runs row per executed step.',
            ],
            'eq' => [
                'summary' => 'Quality is scored by the gate that follows this agent.',
                'applicable_gates' => [$gateName],
                'quality_rules' => $rules,
                'scoring_formula' => 'passed_checks / total_checks × 100 per gate; composite = mean of gates',
                'tenant_isolation_verified' => $c['inst'] > 0,
            ],
            'eso' => [
                'knowledge' => ['summary' => $c['role'], 'items' => $c['knowledge'], 'tables_and_sources' => $c['tables']],
                'activities' => ['summary' => $c['role'], 'items' => $c['activities']],
                'abilities' => ['summary' => 'Deterministic, read-only, tenant-scoped.', 'skills' => $c['skills'], 'constraints_and_safeguards' => $c['safeguards']],
                'usage' => [
                    'summary' => $c['downstream'],
                    'step_by_step_flow' => $c['usage'],
                    'upstream_dependencies' => $c['upstream'],
                    'downstream_handoff' => $c['downstream'],
                ],
            ],
        ];
    }

    private function capabilities(): array
    {
        $rows = [
            ['agent_1', 'School-wide ledger position', 'fees.position'],
            ['agent_1', 'Per-student arrears scan', 'fees.arrears'],
            ['agent_2', 'Pending line items per student', 'fees.getPending'],
            ['agent_2', 'Collection report for a period', 'fees.collection_report'],
            ['agent_2', 'Reminder history and cooldown', 'fees.reminder_history'],
            ['agent_3', 'Reminder preview with fingerprint', 'fees.send_reminders'],
        ];

        $items = [];
        foreach ($rows as [$agent, $label, $tool]) {
            $registered = $this->toolRegistered($tool);
            $items[] = [
                'agent' => $agent,
                'capability' => $label,
                'tool' => $tool,
                'status' => $registered ? 'fully_implemented' : 'unavailable',
            ];
        }

        // Sending is deliberately not part of this pipeline: it is an approval-gated step a person takes.
        $items[] = ['agent' => 'agent_3', 'capability' => 'Send approved reminders', 'tool' => 'fees.send_reminders', 'status' => 'partially_implemented', 'note' => 'Intentionally human-approved, outside the automated run.'];

        return $items;
    }

    private function toolRegistered(string $tool): bool
    {
        try {
            return $this->registry->has($tool);
        } catch (Throwable) {
            return false;
        }
    }

    private function liveMetrics(int $inst, ?int $year): array
    {
        $count = static function (string $table, array $where) {
            try {
                if (! Schema::hasTable($table)) {
                    return null;
                }

                return DB::table($table)->where($where)->count();
            } catch (Throwable) {
                return null;
            }
        };

        $base = ['sub_institute_id' => $inst];
        $scoped = $year ? $base + ['syear' => $year] : $base;

        return [
            'academic_year' => $year,
            'fee_structure_rows' => $count('fees_breackoff', $scoped),
            'fee_heads' => $count('fees_title', $scoped),
            'active_receipts' => $count('fees_collect', $scoped + ['is_deleted' => 'N']),
        ];
    }
}
