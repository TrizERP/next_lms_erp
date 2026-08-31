<?php

namespace App\Domain\AI\Conversation;

/**
 * The fifteen stages of the architecture, in order, for a single question.
 *
 * Every stage exists on every trace from the moment the trace is created. Stages that
 * did not run stay in the trace as `not_reached` with a reason, because "the Workflow
 * Engine did not run, because nothing has been approved yet" is exactly the fact a
 * person trying to understand this system needs to see. Silence would leave them
 * guessing whether the stage is missing or merely idle.
 *
 * Nothing in here decides anything. It is a recorder: AskService runs the real
 * services and reports what happened into this object.
 */
final class FlowTrace implements \App\Domain\AI\Lifecycle\RecordableTrace
{
    /**
     * Canonical stage definitions. `component` names the class that genuinely does the
     * work, so a reader can open it; `surface` names where the result shows up.
     *
     * @var array<int, array{key:string, layer:string, component:string, surface:string}>
     */
    private const STAGES = [
        [
            'key' => 'conversation',
            'layer' => 'Conversational AI',
            'component' => 'App\\Domain\\AI\\Conversation\\ConversationStore',
            'surface' => 'AI Journey console — the message you typed',
        ],
        [
            'key' => 'gen_ai',
            'layer' => 'Gen AI (understanding)',
            'component' => 'App\\Domain\\AI\\Conversation\\IntentClassifier',
            'surface' => 'AI Journey console — the "Understood as" chip',
        ],
        [
            'key' => 'agent',
            'layer' => 'Agent',
            'component' => 'App\\Domain\\AI\\Agents\\AgentRunner -> AcademicRiskAgent',
            'surface' => 'AI Administration → Agent runs',
        ],
        [
            'key' => 'ontology',
            'layer' => 'Ontology / Knowledge Graph',
            'component' => 'App\\Domain\\Ontology\\EntityResolver + KnowledgeGraph\\GraphQueryService',
            'surface' => 'Student profile → Relationships',
        ],
        [
            'key' => 'data',
            'layer' => 'Real data',
            'component' => 'AttendanceRiskDetector, AssessmentDeclineDetector, MissedAssignmentDetector',
            'surface' => 'The underlying attendance / assessment / assignment screens',
        ],
        [
            'key' => 'evidence',
            'layer' => 'Evidence',
            'component' => 'App\\Domain\\AI\\Evidence\\EvidenceStore',
            'surface' => 'Case → Evidence tab',
        ],
        [
            'key' => 'case',
            'layer' => 'Case',
            'component' => 'App\\Domain\\AI\\Cases\\CaseBuilder',
            'surface' => 'Risk management → Cases',
        ],
        [
            'key' => 'explanation',
            'layer' => 'Explain',
            'component' => 'App\\Domain\\AI\\Explanations\\ExplanationBuilder + GovernanceValidator',
            'surface' => 'Case → Why this was raised',
        ],
        [
            'key' => 'template',
            'layer' => 'Template Engine',
            'component' => 'App\\Domain\\Templates\\TemplateRegistry -> GenerativeAI\\GenerationService',
            'surface' => 'Intervention draft shown before approval',
        ],
        [
            'key' => 'recommendation',
            'layer' => 'Recommendation',
            'component' => 'App\\Domain\\AI\\Recommendations\\RecommendationDrafter',
            'surface' => 'Approvals inbox — the drafted action',
        ],
        [
            'key' => 'approval',
            'layer' => 'Human Approval',
            'component' => 'App\\Domain\\AI\\Decisions\\DecisionGate',
            'surface' => 'Approve / Reject buttons in the console and approvals inbox',
        ],
        [
            'key' => 'workflow',
            'layer' => 'Workflow + Workflow Engine',
            'component' => 'App\\Domain\\Workflow\\WorkflowEngine (k12_academic_intervention)',
            'surface' => 'Case → Process steps',
        ],
        [
            'key' => 'action',
            'layer' => 'Action',
            'component' => 'App\\Domain\\K12\\AcademicRisk\\CreateAcademicInterventionAction',
            'surface' => 'Student profile → Interventions',
        ],
        [
            'key' => 'outcome',
            'layer' => 'Outcome',
            'component' => 'App\\Domain\\AI\\Outcomes\\OutcomeTracker',
            'surface' => 'Case → Outcome (baseline vs measured)',
        ],
        [
            'key' => 'learning',
            'layer' => 'Learning',
            'component' => 'OutcomeTracker::effectivenessByActionType',
            'surface' => 'AI Administration → Effectiveness',
        ],
    ];

    /** @var array<string, TraceStage> */
    private array $stages = [];

    private float $startedAt;

    public function __construct()
    {
        $this->startedAt = microtime(true);

        foreach (self::STAGES as $index => $definition) {
            $this->stages[$definition['key']] = new TraceStage(
                key: $definition['key'],
                order: $index + 1,
                layer: $definition['layer'],
                component: $definition['component'],
                surface: $definition['surface'],
            );
        }
    }

    /**
     * Record that a stage ran.
     *
     * @param  array{table?:string, ids?:array}  $records
     * @param  array{api?:string, sql?:string}  $verify
     */
    public function ran(
        string $key,
        string $summary,
        array $data = [],
        array $records = [],
        array $verify = [],
        ?int $durationMs = null
    ): self {
        return $this->set($key, TraceStage::RAN, $summary, $data, $records, $verify, $durationMs);
    }

    /**
     * The stage was reached but had nothing to do — and that is a normal outcome.
     */
    public function skipped(string $key, string $why, array $data = []): self
    {
        return $this->set($key, TraceStage::SKIPPED, $why, $data);
    }

    /**
     * The stage refused. Governance, role, or a missing decision.
     */
    public function blocked(string $key, string $why, array $data = []): self
    {
        return $this->set($key, TraceStage::BLOCKED, $why, $data);
    }

    /**
     * The stage is legitimately waiting on something — nearly always a human.
     */
    public function pending(string $key, string $why, array $data = [], array $records = [], array $verify = []): self
    {
        return $this->set($key, TraceStage::PENDING, $why, $data, $records, $verify);
    }

    /**
     * The question never got this far. Says why, so the gap is legible.
     */
    public function notReached(string $key, string $why): self
    {
        $stage = $this->stages[$key] ?? null;

        if ($stage) {
            $stage->note = $why;
        }

        return $this;
    }

    public function get(string $key): ?TraceStage
    {
        return $this->stages[$key] ?? null;
    }

    public function statusOf(string $key): string
    {
        return $this->stages[$key]->status ?? TraceStage::NOT_REACHED;
    }

    public function dataOf(string $key): array
    {
        return $this->stages[$key]->data ?? [];
    }

    /**
     * Stages in architecture order, ready to render.
     */
    public function toArray(): array
    {
        $stages = array_values(array_map(fn (TraceStage $stage) => $stage->toArray(), $this->stages));

        usort($stages, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $stages;
    }

    /**
     * The one-line-per-stage form, for the ladder in the console and for anyone reading
     * the JSON without a UI in front of them.
     */
    public function toLadder(): array
    {
        $marks = [
            TraceStage::RAN => 'OK',
            TraceStage::SKIPPED => '--',
            TraceStage::BLOCKED => 'XX',
            TraceStage::PENDING => '..',
            TraceStage::NOT_REACHED => '  ',
        ];

        return array_map(
            fn (array $stage) => sprintf(
                '[%s] %-28s %s',
                $marks[$stage['status']] ?? '  ',
                $stage['layer'],
                $stage['summary'] !== '' ? $stage['summary'] : ($stage['note'] ?? 'not reached in this turn')
            ),
            $this->toArray()
        );
    }

    public function elapsedMs(): int
    {
        return (int) round((microtime(true) - $this->startedAt) * 1000);
    }

    public function summaryCounts(): array
    {
        $counts = [];

        foreach ($this->stages as $stage) {
            $counts[$stage->status] = ($counts[$stage->status] ?? 0) + 1;
        }

        return $counts;
    }

    private function set(
        string $key,
        string $status,
        string $summary,
        array $data = [],
        array $records = [],
        array $verify = [],
        ?int $durationMs = null
    ): self {
        $stage = $this->stages[$key] ?? null;

        if (! $stage) {
            return $this;
        }

        // A stage's own payload is replaced wholesale — a stage that reports twice means
        // the second reading, not a merge of both. The `lifecycle_*` keys are the one
        // exception, because they are not this stage's payload at all: they are the
        // product lifecycle's bookkeeping, appended to `gen_ai` by whichever code path
        // built the plan or called an MCP tool. Overwriting them meant that an ambiguous
        // student name — which reports `blocked` on gen_ai with no data — silently
        // deleted the record of a plan and of MCP calls that had genuinely happened, and
        // the replayed trace then showed Planning and Laravel MCP as never reached.
        foreach ($stage->data as $key => $value) {
            if (str_starts_with((string) $key, 'lifecycle_') && ! array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        $stage->status = $status;
        $stage->summary = $summary;
        $stage->data = $data;
        $stage->records = $records;
        $stage->verify = $verify;
        $stage->durationMs = $durationMs;

        return $this;
    }
}
