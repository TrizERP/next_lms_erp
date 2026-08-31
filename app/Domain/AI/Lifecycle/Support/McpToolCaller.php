<?php

namespace App\Domain\AI\Lifecycle\Support;

use App\Domain\AI\Lifecycle\StageContext;
use App\Mcp\ToolRegistry;
use Throwable;

/**
 * The one way a stage calls a Laravel MCP tool.
 *
 * Every call is logged on the context whether it succeeded, was refused by the tool's
 * own role gate, or asked for confirmation. Stage 6 reports from that log rather than
 * making the calls itself, and that indirection is deliberate: a tool call legitimately
 * happens in several stages — hydrating the student a decision is recorded against is an
 * approval-stage call — and a stage 6 that only knew about its own calls would have to
 * report "no call was needed" while the turn had plainly made several.
 *
 * A refusal never propagates as an exception. A lookup gate must not become an answer
 * gate: when a tool turns a caller down, the caller falls back to the scoped domain
 * service and the trace says Laravel MCP was reached and refused, which is the honest
 * thing to show rather than a shrug.
 */
class McpToolCaller
{
    public function __construct(private readonly ToolRegistry $tools)
    {
    }

    /**
     * Call a tool and record the attempt.
     *
     * @param  array<string, mixed>  $arguments
     * @param  string  $why  One sentence explaining why this turn needed this call.
     * @return array<string, mixed>|null  The tool's result payload, or null if it did not run.
     */
    public function call(
        StageContext $context,
        string $tool,
        array $arguments,
        string $why,
        ?string $confirmationToken = null
    ): ?array {
        // A tool the module is not bound to is not refused at the transport — it is
        // never reached. Recording it as a blocked call would blame the role gate for a
        // configuration decision made here.
        if (! in_array($tool, $context->module->mcpTools, true)) {
            $context->recordToolCall([
                'tool' => $tool,
                'status' => 'unavailable',
                'note' => $why,
                'arguments' => $arguments,
                'count' => 0,
                'error' => sprintf(
                    'The %s module is not bound to %s, so the turn could not select it.',
                    $context->module->label,
                    $tool
                ),
            ]);

            return null;
        }

        $startedAt = microtime(true);

        try {
            $result = $this->tools->execute($tool, $arguments, $context->scope, $confirmationToken);
        } catch (Throwable $exception) {
            $context->recordToolCall([
                'tool' => $tool,
                'status' => 'blocked',
                'duration_ms' => $this->elapsed($startedAt),
                'note' => $why,
                'arguments' => $arguments,
                'count' => 0,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        $mode = (string) ($result['mode'] ?? 'unknown');
        $payload = is_array($result['result'] ?? null) ? $result['result'] : [];

        // A confirmable tool asked for a token instead of acting. That is a successful
        // round trip that deliberately changed nothing, and it must not be reported as
        // either a completed action or a refusal.
        if ($mode === 'preview') {
            $context->recordToolCall([
                'tool' => $tool,
                'status' => 'awaiting_confirmation',
                'duration_ms' => $this->elapsed($startedAt),
                'note' => $why,
                'arguments' => $arguments,
                'count' => 0,
                'preview' => $result['preview'] ?? null,
            ]);

            return null;
        }

        $context->recordToolCall([
            'tool' => $tool,
            'status' => $mode === 'execute' ? 'completed' : $mode,
            'duration_ms' => $this->elapsed($startedAt),
            'note' => $why,
            'arguments' => $arguments,
            'count' => $this->countOf($payload),
        ]);

        return $payload;
    }

    /**
     * Ask a confirmable tool what it *would* do, and get the token to authorise it.
     *
     * Deliberately a separate method from `call()`. A confirmable tool reached without a
     * token changes nothing and hands back a token instead — which is a successful round
     * trip that a caller must not mistake for the action having happened. Giving that
     * its own verb means a reader of the calling code can see which of the two things is
     * being asked for.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{token:string|null, preview:array<string, mixed>, error:string|null}
     */
    public function confirmable(StageContext $context, string $tool, array $arguments, string $why): array
    {
        if (! in_array($tool, $context->module->mcpTools, true)) {
            return [
                'token' => null,
                'preview' => [],
                'error' => sprintf(
                    'The %s module is not bound to %s.',
                    $context->module->label,
                    $tool
                ),
            ];
        }

        $startedAt = microtime(true);

        try {
            $result = $this->tools->execute($tool, $arguments, $context->scope);
        } catch (Throwable $exception) {
            $context->recordToolCall([
                'tool' => $tool,
                'status' => 'blocked',
                'duration_ms' => $this->elapsed($startedAt),
                'note' => $why,
                'arguments' => $arguments,
                'count' => 0,
                'error' => $exception->getMessage(),
            ]);

            return ['token' => null, 'preview' => [], 'error' => $exception->getMessage()];
        }

        $token = $result['confirmation']['token'] ?? null;
        $preview = is_array($result['preview'] ?? null) ? $result['preview'] : [];

        $context->recordToolCall([
            'tool' => $tool,
            'status' => $token === null ? 'no_confirmation_offered' : 'awaiting_confirmation',
            'duration_ms' => $this->elapsed($startedAt),
            'note' => $why,
            'arguments' => $arguments,
            'count' => 0,
            'preview' => $preview,
        ]);

        return [
            'token' => is_string($token) ? $token : null,
            'preview' => $preview,
            // A confirmable tool that declines to offer a token is refusing, and its
            // own message says why far better than a generic sentence would.
            'error' => $token === null
                ? (string) ($result['result']['message'] ?? $result['message'] ?? 'This action was refused.')
                : null,
        ];
    }

    /**
     * Call `students.search` and return the first match, or null.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    public function firstStudent(StageContext $context, array $arguments, string $why): ?array
    {
        $payload = $this->call($context, 'students.search', $arguments, $why);
        $students = is_array($payload['students'] ?? null) ? $payload['students'] : [];

        foreach ($students as $student) {
            if (is_array($student)) {
                return $student;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    public function searchStudents(StageContext $context, array $arguments, string $why): array
    {
        $payload = $this->call($context, 'students.search', $arguments, $why);
        $students = is_array($payload['students'] ?? null) ? $payload['students'] : [];

        return array_values(array_filter($students, 'is_array'));
    }

    /**
     * A best-effort row count, for the trace. Tools return differently shaped payloads,
     * so this looks for the first list rather than assuming a key.
     *
     * @param  array<string, mixed>  $payload
     */
    private function countOf(array $payload): int
    {
        foreach ($payload as $value) {
            if (is_array($value) && array_is_list($value)) {
                return count($value);
            }
        }

        return $payload === [] ? 0 : 1;
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
