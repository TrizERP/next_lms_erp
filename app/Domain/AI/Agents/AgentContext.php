<?php

namespace App\Domain\AI\Agents;

use App\Domain\AI\Cases\CaseBuilder;
use App\Domain\AI\Evidence\EvidenceStore;
use App\Domain\AI\Explanations\ExplanationBuilder;
use App\Domain\AI\Recommendations\RecommendationDrafter;
use App\Domain\AI\Signals\SignalStore;
use App\Domain\Governance\GovernanceReport;
use App\Domain\Governance\Verb;
use App\Domain\KnowledgeGraph\GraphQueryService;
use App\Domain\Ontology\EntityResolver;
use App\Services\Mcp\McpRequestContext;
use RuntimeException;

/**
 * Everything an agent is handed — and the reason agents cannot overreach.
 *
 * An agent never receives a database connection, a query builder, or the ability to
 * construct its own request context. It gets this object: a set of already-scoped
 * services plus its own manifest. Each write path here re-checks the manifest before
 * doing anything, so an agent that tries to recommend when it is only licensed to
 * explain is refused by its own context rather than by a reviewer noticing later.
 */
class AgentContext
{
    private array $toolCalls = [];

    private int $signalsDetected = 0;

    private int $evidenceCollected = 0;

    private int $casesOpened = 0;

    private int $recommendationsDrafted = 0;

    public function __construct(
        public readonly AgentManifest $manifest,
        public readonly McpRequestContext $scope,
        public readonly int $runId,
        public readonly array $input,
        private readonly EntityResolver $entities,
        private readonly GraphQueryService $graph,
        private readonly SignalStore $signals,
        private readonly EvidenceStore $evidence,
        private readonly CaseBuilder $cases,
        private readonly ExplanationBuilder $explanations,
        private readonly RecommendationDrafter $recommendations,
    ) {
    }

    // ---- Reading -----------------------------------------------------------

    /**
     * Resolve records of an ontology entity. Refused for entities outside the
     * manifest, so an academic agent cannot browse payroll.
     */
    public function resolveEntity(string $entityKey, ?string $search = null, int $limit = 25): array
    {
        $this->assertEntity($entityKey);

        return $this->entities->resolve($entityKey, $this->scope, $search, $limit);
    }

    public function resolveOne(string $entityKey, int|string $id): ?array
    {
        $this->assertEntity($entityKey);

        return $this->entities->resolveOne($entityKey, $id, $this->scope);
    }

    /**
     * Walk a relationship. The graph layer applies the same tenant filter on every
     * hop, so traversal cannot be used to reach records a direct read would refuse.
     */
    public function neighbours(string $entityKey, int|string $id, string $relation, int $limit = 25): array
    {
        $this->assertEntity($entityKey);

        return array_map(
            fn ($node) => $node->toArray(),
            $this->graph->neighbours($entityKey, $id, $relation, $this->scope, $limit)
        );
    }

    public function evidenceFor(string $entityKey, int|string $id, ?string $kind = null, int $limit = 50): array
    {
        $this->assertEntity($entityKey);

        return $this->evidence->forSubject($entityKey, $id, $this->scope, $kind, $limit);
    }

    public function signalHistoryFor(string $entityKey, int|string $id, int $limit = 50): array
    {
        $this->assertEntity($entityKey);

        return $this->signals->historyFor($entityKey, $id, $this->scope, $limit);
    }

    // ---- Writing (each gated by the manifest) ------------------------------

    /**
     * @param  array<int, \App\Domain\AI\Signals\DetectedSignal>  $detected
     * @return array{signal_ids:array<int,int>, evidence_ids:array<int,int>}
     */
    public function recordSignals(array $detected): array
    {
        $this->assertVerb(Verb::Detect);

        $signalIds = [];
        $evidenceIds = [];

        foreach ($detected as $signal) {
            if (! $this->manifest->permitsSignal($signal->signalKey)) {
                throw new RuntimeException(sprintf(
                    'Agent "%s" is not licensed to raise the signal "%s".',
                    $this->manifest->key,
                    $signal->signalKey
                ));
            }

            $stored = $this->signals->store($signal, $this->scope, $this->runId);

            if ($stored['signal_id'] !== null) {
                $signalIds[] = $stored['signal_id'];
                $this->signalsDetected++;
            }

            $evidenceIds = array_merge($evidenceIds, $stored['evidence_ids']);
        }

        $evidenceIds = array_values(array_unique($evidenceIds));
        $this->evidenceCollected += count($evidenceIds);

        return ['signal_ids' => $signalIds, 'evidence_ids' => $evidenceIds];
    }

    /**
     * @param  array<int, \App\Domain\AI\Signals\DetectedSignal>  $signals
     */
    public function openCase(
        string $caseType,
        array $signals,
        array $signalIds,
        array $evidenceIds
    ): ?int {
        $this->assertVerb(Verb::Analyse);

        $caseId = $this->cases->buildFromSignals(
            $caseType,
            $signals,
            $signalIds,
            $evidenceIds,
            $this->scope,
            $this->runId
        );

        if ($caseId !== null) {
            $this->casesOpened++;
        }

        return $caseId;
    }

    public function addHypothesis(
        int $caseId,
        string $statement,
        ?string $rationale = null,
        array $supporting = [],
        array $contradicting = [],
        ?float $confidence = null
    ): ?int {
        $this->assertVerb(Verb::Analyse);

        return $this->cases->addHypothesis(
            $caseId,
            $statement,
            $this->scope,
            $rationale,
            $supporting,
            $contradicting,
            $confidence
        );
    }

    /**
     * @return array{id:int|null, governance:GovernanceReport, narrative:string}
     */
    public function explain(
        int $caseId,
        array $claims,
        string $audience = 'teacher',
        ?string $subjectEntityKey = null,
        int|string|null $subjectId = null,
        ?string $subjectLabel = null
    ): array {
        $this->assertVerb(Verb::Explain);

        return $this->explanations->build(
            $caseId,
            $claims,
            $this->scope,
            $audience,
            $subjectEntityKey,
            $subjectId,
            $subjectLabel
        );
    }

    /**
     * Draft a recommendation. Two gates apply before RecommendVerb even sees it: the
     * agent must be licensed to recommend, and any workflow it binds to must be one
     * this agent is authorised for.
     *
     * @return array{id:int|null, governance:GovernanceReport, status:string}
     */
    public function recommend(array $draft): array
    {
        $this->assertVerb(Verb::Recommend);

        $workflowKey = $draft['workflow_key'] ?? null;

        if ($workflowKey !== null && ! $this->manifest->permitsWorkflow($workflowKey)) {
            throw new RuntimeException(sprintf(
                'Agent "%s" is not authorised to bind recommendations to workflow "%s".',
                $this->manifest->key,
                $workflowKey
            ));
        }

        $result = $this->recommendations->draft(
            $draft,
            $this->scope,
            $this->manifest->authorizedWorkflowKeys,
            $this->runId
        );

        if ($result['id'] !== null) {
            $this->recommendationsDrafted++;
        }

        return $result;
    }

    // ---- Bookkeeping -------------------------------------------------------

    public function noteToolCall(string $tool, string $status, ?int $durationMs = null, ?string $note = null): void
    {
        if (! $this->manifest->permitsTool($tool)) {
            throw new RuntimeException(sprintf(
                'Agent "%s" is not licensed to use the tool "%s".',
                $this->manifest->key,
                $tool
            ));
        }

        $this->toolCalls[] = array_filter([
            'tool' => $tool,
            'status' => $status,
            'duration_ms' => $durationMs,
            'note' => $note,
            'at' => now()->toIso8601String(),
        ], fn ($value) => $value !== null);
    }

    public function counters(): array
    {
        return [
            'signals_detected' => $this->signalsDetected,
            'evidence_collected' => $this->evidenceCollected,
            'cases_opened' => $this->casesOpened,
            'recommendations_drafted' => $this->recommendationsDrafted,
        ];
    }

    public function toolCalls(): array
    {
        return $this->toolCalls;
    }

    // ---- Guards ------------------------------------------------------------

    private function assertVerb(Verb $verb): void
    {
        if (! $this->manifest->permitsVerb($verb)) {
            throw new RuntimeException(sprintf(
                'Agent "%s" is licensed up to "%s" and may not "%s".',
                $this->manifest->key,
                $this->manifest->maxVerb->value,
                $verb->value
            ));
        }
    }

    private function assertEntity(string $entityKey): void
    {
        if (! $this->manifest->permitsEntity($entityKey)) {
            throw new RuntimeException(sprintf(
                'Agent "%s" may not read the entity "%s".',
                $this->manifest->key,
                $entityKey
            ));
        }
    }
}
