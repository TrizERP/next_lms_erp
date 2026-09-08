<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\AskAuditor;
use App\Services\Mcp\McpAuditService;
use App\Services\Mcp\McpRequestContext;
use RuntimeException;
use Tests\TestCase;

/**
 * Every turn leaves a row — including the ones that call nothing.
 *
 * `mcp_audit_logs` was written from exactly one place, `McpController::audit()` on the
 * deprecated REST façade. So a lifecycle turn wrote no audit row even when it called
 * four tools, and a turn that answered without a tool at all — small talk, a general
 * answer, a refusal — wrote nothing anywhere. On the live estate the table held **zero**
 * rows, which is how completely the gap had opened.
 *
 * "What did this user ask, and what did the system tell them" has to be answerable for
 * every turn, not only the ones that happened to touch a database.
 *
 * No database: McpAuditService is faked and the rows are inspected in memory.
 */
class AskAuditTest extends TestCase
{
    public function test_a_turn_that_called_no_tool_still_writes_a_row(): void
    {
        // The case the old audit lost entirely.
        [$auditor, $rows] = $this->auditor();

        $auditor->turn('hello', $this->scope(), [], $this->turnResult(source: 'general'), null, 120);

        $this->assertCount(1, $rows->rows);
        $this->assertNull($rows->rows[0]['tool_name'], 'A tool-less turn should record no tool, not be skipped.');
        $this->assertSame('success', $rows->rows[0]['outcome']);
        $this->assertSame('hello', $rows->rows[0]['input_payload']['question']);
    }

    public function test_a_general_answer_is_a_success_not_a_refusal(): void
    {
        // The bug this guards: a general answer and a refusal both leave planning
        // blocked, so reading stage counts alone marked "what is the capital of
        // Australia" as refused when it had been answered perfectly well.
        [$auditor, $rows] = $this->auditor();

        $auditor->turn(
            'What is the capital of Australia?',
            $this->scope(),
            [],
            $this->turnResult(source: 'general', blocked: 1),
            null,
            900
        );

        $this->assertSame('success', $rows->rows[0]['outcome']);
        $this->assertSame('general', $rows->rows[0]['response_payload']['answer_source']);
    }

    public function test_a_fallback_is_recorded_as_refused(): void
    {
        // Every stage ran and none had anything to say. Distinct from an error, because
        // being unable to answer is the system working.
        [$auditor, $rows] = $this->auditor();

        $auditor->turn('something unanswerable', $this->scope(), [], $this->turnResult(source: 'fallback'), null, 400);

        $this->assertSame('refused', $rows->rows[0]['outcome']);
        $this->assertSame(200, $rows->rows[0]['status_code']);
    }

    public function test_the_legacy_pipeline_falls_back_to_stage_counts(): void
    {
        // AskService reports no answer_source. Stage counts are the best signal there.
        [$auditor, $rows] = $this->auditor();

        $result = $this->turnResult(source: null, blocked: 2);
        unset($result['answer_source']);

        $auditor->turn('anything', $this->scope(), [], $result, null, 100);

        $this->assertSame('refused', $rows->rows[0]['outcome']);
    }

    public function test_a_failed_turn_is_audited(): void
    {
        // The turn most worth having in the trail, and the one a happy-path-only audit
        // would silently drop.
        [$auditor, $rows] = $this->auditor();

        $auditor->turn('anything', $this->scope(), [], null, new RuntimeException('provider down'), 50);

        $this->assertSame('error', $rows->rows[0]['outcome']);
        $this->assertSame(500, $rows->rows[0]['status_code']);
        $this->assertSame('ask_failed', $rows->rows[0]['error_code']);
        $this->assertSame('provider down', $rows->rows[0]['error_message']);
    }

    public function test_the_tools_a_turn_reached_are_named(): void
    {
        [$auditor, $rows] = $this->auditor();

        $auditor->turn('How many students?', $this->scope(), [], $this->turnResult(
            tools: ['academics.structure', 'students.directory']
        ), null, 300);

        $this->assertSame('academics.structure,students.directory', $rows->rows[0]['tool_name']);
    }

    public function test_the_row_is_scoped_to_the_caller(): void
    {
        // Scope comes from the hydrated context, never from the question.
        [$auditor, $rows] = $this->auditor();

        $auditor->turn('anything', $this->scope(userId: 77, instituteId: 254), [], $this->turnResult(), null, 10);

        $this->assertSame(77, $rows->rows[0]['user_id']);
        $this->assertSame(254, $rows->rows[0]['sub_institute_id']);
    }

    public function test_the_response_payload_is_a_summary_not_the_whole_turn(): void
    {
        // The full trace already lives on ai_conversation_turns. Copying it here would
        // double the storage of the largest thing the platform writes.
        [$auditor, $rows] = $this->auditor();

        $auditor->turn('anything', $this->scope(), [], $this->turnResult(), null, 10);

        $payload = $rows->rows[0]['response_payload'];

        $this->assertArrayNotHasKey('trace', $payload);
        $this->assertArrayNotHasKey('lifecycle_trace', $payload);
        $this->assertArrayHasKey('headline', $payload);
        $this->assertArrayHasKey('depth_reached', $payload);
        $this->assertArrayHasKey('duration_ms', $payload);
    }

    public function test_an_audit_failure_never_reaches_the_caller(): void
    {
        // Losing a log row is bad; losing a teacher's answer because the audit table was
        // locked is worse.
        $audit = new class extends McpAuditService
        {
            public function log(array $payload): void
            {
                throw new RuntimeException('table locked');
            }
        };

        $auditor = new AskAuditor($audit);

        $auditor->turn('anything', $this->scope(), [], $this->turnResult(), null, 10);

        $this->assertTrue(true, 'An audit failure must not propagate.');
    }

    // ------------------------------------------------------------------- helpers

    /**
     * @return array{0: AskAuditor, 1: RecordingAuditService}
     */
    private function auditor(): array
    {
        $audit = new RecordingAuditService();

        return [new AskAuditor($audit), $audit];
    }

    /**
     * @param  array<int, string>  $tools
     * @return array<string, mixed>
     */
    private function turnResult(?string $source = 'stages', int $blocked = 0, array $tools = []): array
    {
        return [
            'answer' => ['headline' => 'An answer.'],
            'answer_source' => $source,
            'pipeline' => 'lifecycle_v2',
            'module' => ['key' => 'student'],
            'intent' => ['key' => 'student.count'],
            'depth_reached' => 11,
            'conversation' => ['id' => 9, 'turn_id' => 42],
            'lifecycle_stage_counts' => ['blocked' => $blocked],
            'trace' => [
                ['key' => 'conversation', 'data' => []],
                ['key' => 'mcp_tool_selection', 'data' => ['selected_tools' => $tools]],
            ],
        ];
    }

    private function scope(int $userId = 1, int $instituteId = 1): McpRequestContext
    {
        return new McpRequestContext(
            userId: $userId,
            role: 'admin',
            selectedInstituteId: $instituteId,
            allowedInstituteIds: [$instituteId],
            userProfileId: null,
            clientId: null,
            academicYear: 2026,
            termId: null,
            isAdmin: true,
            isStudent: false,
        );
    }
}

/**
 * Collects rows in memory so a test can read what would have been written.
 */
class RecordingAuditService extends McpAuditService
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function log(array $payload): void
    {
        $this->rows[] = $payload;
    }
}
