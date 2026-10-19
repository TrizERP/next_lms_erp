<?php

namespace Tests\Unit;

use App\Domain\AI\Cases\CaseBuilder;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\Stages\RealDataStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageStatus;
use App\Services\Mcp\McpRequestContext;
use PHPUnit\Framework\TestCase;

/**
 * The "Read the database" step of the trace, for a turn that only previewed an action.
 *
 * A confirmable tool's preview reads the school's records to work out who would be affected, changes nothing and
 * waits for a person. It was reported as "Refused — every tool call was refused or unavailable", which described a
 * different turn than the one that happened.
 */
class RealDataPreviewTest extends TestCase
{
    private function context(array $calls): StageContext
    {
        $context = new StageContext('x', new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false), ModuleCapability::general());

        foreach ($calls as $call) {
            $context->recordToolCall($call);
        }

        return $context;
    }

    private function stage(): RealDataStage
    {
        return new RealDataStage($this->createMock(CaseBuilder::class));
    }

    public function test_a_preview_awaiting_approval_is_a_read_not_a_refusal(): void
    {
        $outcome = $this->stage()->run($this->context([
            ['tool' => 'fees.send_reminders', 'status' => 'awaiting_confirmation', 'note' => 'Show who would be reminded', 'count' => 0,
                'preview' => ['data' => ['recipient_count' => 3]]],
        ]));

        $this->assertSame(StageStatus::Ran, $outcome->status);
        $this->assertStringContainsString('3 records would be affected', $outcome->summary);
        $this->assertStringContainsString('nothing has been changed', $outcome->summary);
    }

    public function test_a_turn_where_every_call_was_really_refused_is_still_a_refusal(): void
    {
        $outcome = $this->stage()->run($this->context([
            ['tool' => 'fees.get_pending', 'status' => 'blocked', 'count' => 0, 'error' => 'Outside the tool\'s allowed roles.'],
            ['tool' => 'x.y', 'status' => 'unavailable', 'count' => 0],
        ]));

        $this->assertSame(StageStatus::Blocked, $outcome->status);
    }

    public function test_completed_reads_are_reported_with_their_rows_as_before(): void
    {
        $outcome = $this->stage()->run($this->context([
            ['tool' => 'fees.reminder_history', 'status' => 'completed', 'count' => 9, 'note' => 'Read the saved record'],
            ['tool' => 'fees.send_reminders', 'status' => 'awaiting_confirmation', 'count' => 0, 'preview' => []],
        ]));

        $this->assertSame(StageStatus::Ran, $outcome->status);
        $this->assertStringContainsString('Read 9 rows from live records through 1 tool call', $outcome->summary);
    }
}
