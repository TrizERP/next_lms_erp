<?php

namespace App\Domain\AI\Agents;

use App\Domain\Governance\Verb;

/**
 * An agent's licence, loaded from `ai_agents`.
 *
 * Everything an agent is allowed to do is on this object, and the agent itself never
 * gets to construct one. AgentRunner loads the manifest, and the agent receives only
 * what the manifest permits — which is why "allowed tools" is a meaningful constraint
 * rather than a comment.
 */
final class AgentManifest
{
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly string $name,
        public readonly string $domain,
        public readonly string $runnerClass,
        public readonly string $agentType = 'domain',
        public readonly ?string $purpose = null,
        public readonly array $allowedTools = [],
        public readonly array $allowedEntities = [],
        public readonly array $allowedSignalKeys = [],
        public readonly Verb $maxVerb = Verb::Recommend,
        public readonly bool $mayExecuteActions = false,
        public readonly array $authorizedWorkflowKeys = [],
        public readonly array $inputSchema = [],
        public readonly array $outputSchema = [],
        public readonly array $requiredPermissions = [],
        public readonly array $allowedRoles = [],
        public readonly float $minConfidence = 0.5,
        public readonly int $minEvidenceCount = 1,
        public readonly int $timeoutSeconds = 120,
        public readonly int $maxRetries = 1,
        public readonly array $config = [],
        public readonly ?int $subInstituteId = null,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            key: (string) $row->agent_key,
            name: (string) $row->name,
            domain: (string) ($row->domain ?? 'k12'),
            runnerClass: (string) $row->runner_class,
            agentType: (string) ($row->agent_type ?? 'domain'),
            purpose: $row->purpose ?? null,
            allowedTools: self::decode($row->allowed_tools ?? null),
            allowedEntities: self::decode($row->allowed_entities ?? null),
            allowedSignalKeys: self::decode($row->allowed_signal_keys ?? null),
            maxVerb: Verb::fromName($row->max_verb ?? null),
            mayExecuteActions: (bool) ($row->may_execute_actions ?? false),
            authorizedWorkflowKeys: self::splitCsv($row->authorized_workflow_keys ?? null),
            inputSchema: self::decode($row->input_schema ?? null),
            outputSchema: self::decode($row->output_schema ?? null),
            requiredPermissions: self::decode($row->required_permissions ?? null),
            allowedRoles: self::decode($row->allowed_roles ?? null),
            minConfidence: (float) ($row->min_confidence ?? 0.5),
            minEvidenceCount: (int) ($row->min_evidence_count ?? 1),
            timeoutSeconds: (int) ($row->timeout_seconds ?? 120),
            maxRetries: (int) ($row->max_retries ?? 1),
            config: self::decode($row->config ?? null),
            subInstituteId: isset($row->sub_institute_id) ? (int) $row->sub_institute_id : null,
        );
    }

    public function permitsTool(string $toolName): bool
    {
        // An empty allowlist means nothing is allowed. Never "everything".
        return in_array($toolName, $this->allowedTools, true);
    }

    public function permitsEntity(string $entityKey): bool
    {
        return $this->allowedEntities === [] || in_array($entityKey, $this->allowedEntities, true);
    }

    public function permitsSignal(string $signalKey): bool
    {
        return $this->allowedSignalKeys === [] || in_array($signalKey, $this->allowedSignalKeys, true);
    }

    public function permitsVerb(Verb $verb): bool
    {
        if ($verb === Verb::Execute && ! $this->mayExecuteActions) {
            return false;
        }

        return $this->maxVerb->permits($verb);
    }

    public function permitsWorkflow(string $workflowKey): bool
    {
        return in_array($workflowKey, $this->authorizedWorkflowKeys, true);
    }

    public function permitsRole(string $role): bool
    {
        return $this->allowedRoles === [] || in_array($role, $this->allowedRoles, true);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'domain' => $this->domain,
            'agent_type' => $this->agentType,
            'purpose' => $this->purpose,
            'allowed_tools' => $this->allowedTools,
            'allowed_entities' => $this->allowedEntities,
            'max_verb' => $this->maxVerb->value,
            'may_execute_actions' => $this->mayExecuteActions,
            'authorized_workflow_keys' => $this->authorizedWorkflowKeys,
            'min_confidence' => $this->minConfidence,
            'min_evidence_count' => $this->minEvidenceCount,
            'allowed_roles' => $this->allowedRoles,
        ];
    }

    private static function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function splitCsv(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
    }
}
