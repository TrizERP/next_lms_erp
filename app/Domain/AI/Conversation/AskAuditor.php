<?php

namespace App\Domain\AI\Conversation;

use App\Services\Mcp\McpAuditService;
use App\Services\Mcp\McpRequestContext;
use Throwable;

/**
 * One audit row per turn through ask — including the turns that call nothing.
 *
 * The gap this closes is specific. `mcp_audit_logs` was written from exactly one place,
 * `McpController::audit()` on the deprecated REST façade, so:
 *
 *   - a turn answered through the lifecycle wrote **no** audit row, even when it called
 *     four tools, because it reaches ToolRegistry in-process rather than over HTTP;
 *   - a turn that answered without a tool at all — small talk, a general answer, a
 *     refusal, "nothing to report" — wrote nothing anywhere.
 *
 * The second is the one that matters for an audit trail. "What did this user ask, and
 * what did the system tell them" must be answerable for every turn, not only the ones
 * that happened to touch a database. A refusal is an event; so is a general answer, and
 * so is a question that produced nothing.
 *
 * Tool calls are not lost today — they are recorded on the turn's trace in
 * `ai_conversation_turns` — but a compliance query looks in the audit table, and having
 * to know which of two places to read is how an audit trail stops being used.
 *
 * The Next.js path it replaces kept its audit in a 500-entry in-memory array plus a
 * `console.log` (`packages/conversational-ai-core/src/audit.ts`). That is lost on every
 * restart, is not per-tenant, and cannot be queried, so parity with it is a low bar —
 * durable and scoped is the actual goal.
 *
 * Never throws. Losing an audit row is bad; losing a teacher's answer because the audit
 * table was locked is worse.
 */
class AskAuditor
{
    public function __construct(private readonly McpAuditService $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>|null  $result
     */
    public function turn(
        string $question,
        McpRequestContext $scope,
        array $options,
        ?array $result,
        ?Throwable $exception,
        int $durationMs
    ): void {
        try {
            $this->audit->log([
                'request_id' => $this->requestId(),
                'endpoint' => $this->endpoint(),
                // The tools this turn actually reached, so the audit answers "which
                // tools ran for this question" without opening the trace. Null when
                // none did, which is a fact worth recording rather than a blank.
                'tool_name' => $this->toolsUsed($result),
                'user_id' => $scope->userId,
                'sub_institute_id' => $scope->selectedInstituteId,
                'status_code' => $exception === null ? 200 : 500,
                'outcome' => $this->outcome($result, $exception),
                'input_payload' => [
                    'question' => $question,
                    'conversation_id' => $result['conversation']['id'] ?? null,
                    'module' => $options['module'] ?? null,
                    'route' => $options['route'] ?? null,
                ],
                'response_payload' => $this->response($result, $durationMs),
                'error_code' => $exception === null ? null : 'ask_failed',
                'error_message' => $exception?->getMessage(),
            ]);
        } catch (Throwable) {
            // McpAuditService already swallows its own failures; this guards the
            // shaping above, which reads a result array the pipeline built.
        }
    }

    /**
     * What happened, in the vocabulary the audit table already uses.
     *
     * `refused` is deliberately distinct from `error`. A turn the governance layer or a
     * role gate turned down is the system working, and counting it as a failure would
     * make the error rate meaningless — which is the same distinction the Next.js audit
     * drew with its separate `conversation.refused_no_data` event.
     *
     * @param  array<string, mixed>|null  $result
     */
    private function outcome(?array $result, ?Throwable $exception): string
    {
        if ($exception !== null) {
            return 'error';
        }

        // The lifecycle records where the answer came from, because stage counts cannot
        // tell these apart: a general answer and a refusal both leave planning blocked,
        // and reading the counts alone marked "what is the capital of Australia" as a
        // refusal. Only `fallback` — every stage ran, nothing had anything to say — is
        // actually a refusal.
        $source = $result['answer_source'] ?? null;

        if ($source !== null) {
            return $source === 'fallback' ? 'refused' : 'success';
        }

        // The legacy pipeline does not report a source. Fall back to stage counts, which
        // is the best signal available there.
        $counts = $result['lifecycle_stage_counts'] ?? $result['stage_counts'] ?? [];

        return (int) ($counts['blocked'] ?? 0) > 0 ? 'refused' : 'success';
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function toolsUsed(?array $result): ?string
    {
        $tools = [];

        foreach (($result['trace'] ?? []) as $stage) {
            if (($stage['key'] ?? null) === 'mcp_tool_selection') {
                $tools = array_filter((array) ($stage['data']['selected_tools'] ?? []), 'is_string');
            }
        }

        return $tools === [] ? null : mb_substr(implode(',', $tools), 0, 255);
    }

    /**
     * A summary, not the turn.
     *
     * The full trace already lives on `ai_conversation_turns`; copying it here would
     * double the storage of the largest thing the platform writes and make the audit
     * table unreadable. What an auditor needs is what was said, by which module, how
     * deep it got, and whether anything was refused.
     *
     * @param  array<string, mixed>|null  $result
     * @return array<string, mixed>
     */
    private function response(?array $result, int $durationMs): array
    {
        if ($result === null) {
            return ['duration_ms' => $durationMs];
        }

        return [
            'headline' => mb_substr((string) ($result['answer']['headline'] ?? ''), 0, 500),
            'pipeline' => $result['pipeline'] ?? null,
            'module' => $result['module']['key'] ?? null,
            'intent' => $result['intent']['key'] ?? null,
            'depth_reached' => $result['depth_reached'] ?? null,
            'answer_source' => $result['answer_source'] ?? null,
            'stage_counts' => $result['lifecycle_stage_counts'] ?? $result['stage_counts'] ?? [],
            'turn_id' => $result['conversation']['turn_id'] ?? null,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * The endpoint that asked, so a streamed turn and a JSON one are distinguishable.
     */
    private function endpoint(): string
    {
        try {
            $path = request()?->path();

            // "/" is what a console context reports — `php artisan ai:journey` runs the
            // same pipeline, and labelling those rows as the root URL would make the
            // audit look like it had traffic on a route that does not answer questions.
            return is_string($path) && $path !== '' && $path !== '/' ? $path : 'ai:journey';
        } catch (Throwable) {
            // No request — the artisan command runs the same pipeline.
            return 'ai:journey';
        }
    }

    private function requestId(): ?string
    {
        try {
            return request()?->headers->get('X-Request-Id');
        } catch (Throwable) {
            return null;
        }
    }
}
