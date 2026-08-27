<?php

namespace App\Domain\AI\Support;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Durable audit for the intelligence layer.
 *
 * Deliberately shaped like `mcp_audit_logs` so the two can be read together: an
 * investigation into "what did the system do about this student" should not have to
 * join across two different vocabularies.
 *
 * Writes never throw. An audit failure must not roll back the thing being audited —
 * losing a log line is bad, losing a teacher's approval because the log table was
 * locked is worse. Failures fall back to the application log.
 */
class AiAuditLogger
{
    public const AGENT_RUN_STARTED = 'agent.run.started';

    public const AGENT_RUN_COMPLETED = 'agent.run.completed';

    public const AGENT_RUN_FAILED = 'agent.run.failed';

    public const SIGNAL_DETECTED = 'signal.detected';

    public const EVIDENCE_COLLECTED = 'evidence.collected';

    public const CASE_OPENED = 'case.opened';

    public const CASE_CLOSED = 'case.closed';

    public const EXPLANATION_BUILT = 'explanation.built';

    public const RECOMMENDATION_DRAFTED = 'recommendation.drafted';

    public const DECISION_RECORDED = 'decision.recorded';

    public const WORKFLOW_TRANSITION = 'workflow.transition';

    public const GENERATION_REQUESTED = 'generation.requested';

    public const GOVERNANCE_REJECTED = 'governance.rejected';

    public const TOOL_EXECUTION = 'tool.execution';

    /**
     * Record an event. Returns the row id, or null if the write could not happen.
     */
    public function record(
        string $eventType,
        ?McpRequestContext $context = null,
        array $options = []
    ): ?int {
        $payload = [
            'request_id' => $options['request_id'] ?? request()?->header('X-Request-Id'),
            'event_type' => $eventType,
            'actor_type' => $options['actor_type'] ?? ($context ? 'user' : 'system'),
            'actor_id' => $options['actor_id'] ?? $context?->userId,
            'actor_label' => isset($options['actor_label'])
                ? mb_substr((string) $options['actor_label'], 0, 150)
                : null,
            'subject_entity_key' => $options['subject_entity_key'] ?? null,
            'subject_id' => isset($options['subject_id']) && is_numeric($options['subject_id'])
                ? (int) $options['subject_id']
                : null,
            'related_type' => $options['related_type'] ?? null,
            'related_id' => isset($options['related_id']) && is_numeric($options['related_id'])
                ? (int) $options['related_id']
                : null,
            'outcome' => $options['outcome'] ?? 'success',
            'message' => isset($options['message']) ? (string) $options['message'] : null,
            'payload' => isset($options['payload'])
                ? json_encode($this->redact($options['payload']), JSON_UNESCAPED_SLASHES)
                : null,
            'sub_institute_id' => $context?->selectedInstituteId ?? ($options['sub_institute_id'] ?? null),
            'client_id' => $context?->clientId ?? ($options['client_id'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            if (! Schema::hasTable('ai_audit_logs')) {
                $this->fallback($eventType, $payload);

                return null;
            }

            return (int) DB::table('ai_audit_logs')->insertGetId($payload);
        } catch (Throwable $exception) {
            $this->fallback($eventType, $payload + ['audit_error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Convenience for the case every governance refusal takes. Refusals are logged
     * as `outcome = rejected` rather than as failures, because being refused is the
     * system working, not breaking.
     */
    public function recordRejection(
        string $reason,
        ?McpRequestContext $context = null,
        array $options = []
    ): ?int {
        return $this->record(self::GOVERNANCE_REJECTED, $context, $options + [
            'outcome' => 'rejected',
            'message' => $reason,
        ]);
    }

    /**
     * Strip anything that should not sit in an audit row in the clear. Audit exists
     * to answer "what happened", not to become a second copy of sensitive data.
     */
    private function redact(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        $sensitive = [
            'password', 'user_password', 'token', 'api_key', 'authorization',
            'remember_token', 'otp', 'adharnumber', 'aadhar', 'bank_account_number',
            'ifsc_code', 'micr_code', 'confirmation_token',
        ];

        $redacted = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function fallback(string $eventType, array $payload): void
    {
        Log::channel(config('logging.default'))->info('[ai.audit] ' . $eventType, $payload);
    }
}
