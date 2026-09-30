<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\ToolAnswerComposer;
use App\Services\Mcp\McpRequestContext;
use PHPUnit\Framework\TestCase;

/**
 * A tool declares which of its fields are rupee amounts; the composer formats those and
 * only those. Formatting by field name would put a currency sign on a count of students.
 */
class ToolAnswerMoneyTest extends TestCase
{
    public function test_declared_money_fields_are_shown_in_indian_grouping_and_counts_are_not(): void
    {
        $context = $this->contextReturning([
            'headline' => '₹2,92,299 is pending across 66 students.',
            'money_fields' => ['outstanding', 'total_outstanding'],
            'total_outstanding' => 292299,
            'students_owing' => 66,
            'accounts' => [
                ['student_id' => 5, 'student_name' => 'A Student', 'outstanding' => 146000, 'failed_payments' => 3],
            ],
        ]);

        (new ToolAnswerComposer(new AnswerComposer()))->compose($context);

        $this->assertSame('₹2,92,299 is pending across 66 students.', $context->headline());

        $records = $this->section($context, 'records');
        $this->assertSame('₹1,46,000', $records['items'][0]['meta']['Outstanding']);
        $this->assertSame('3', $records['items'][0]['meta']['Failed payments'], 'a count must not become money');

        $figures = array_column($this->section($context, 'key_values')['items'], 'value', 'label');
        $this->assertSame('₹2,92,299', $figures['Total outstanding']);
        $this->assertSame('66', $figures['Students owing']);
        $this->assertArrayNotHasKey('Money fields', $figures);
        $this->assertArrayNotHasKey('Headline', $figures);
    }

    public function test_a_payload_that_declares_nothing_is_left_exactly_as_it_was(): void
    {
        $context = $this->contextReturning([
            'count' => 2,
            'outstanding' => 12500,
            'accounts' => [['student_name' => 'B Student', 'outstanding' => 12500]],
        ]);

        (new ToolAnswerComposer(new AnswerComposer()))->compose($context);

        $this->assertSame('2 accounts.', $context->headline());
        $this->assertSame('12500', $this->section($context, 'records')['items'][0]['meta']['Outstanding']);
    }

    public function test_indian_grouping_at_the_boundaries(): void
    {
        $context = $this->contextReturning([
            'money_fields' => ['amount'],
            'accounts' => [
                ['student_name' => 'Small', 'amount' => 999],
                ['student_name' => 'Thousand', 'amount' => 1000],
                ['student_name' => 'Lakh', 'amount' => 100000],
                ['student_name' => 'Crore', 'amount' => 12345678],
            ],
        ]);

        (new ToolAnswerComposer(new AnswerComposer()))->compose($context);

        $amounts = array_map(
            static fn (array $item) => $item['meta']['Amount'],
            $this->section($context, 'records')['items']
        );

        $this->assertSame(['₹999', '₹1,000', '₹1,00,000', '₹1,23,45,678'], $amounts);
    }

    private function contextReturning(array $data): StageContext
    {
        $context = new StageContext(
            question: 'q',
            scope: new McpRequestContext(
                userId: 1, role: 'admin', selectedInstituteId: 1, allowedInstituteIds: [1],
                userProfileId: null, clientId: null, academicYear: 2022, termId: null,
                isAdmin: true, isStudent: false,
            ),
            module: new ModuleCapability(key: 'fees', label: 'Fees', capabilities: ['conversational' => true], mcpTools: ['fees.position']),
        );
        $context->thread = ['id' => null, 'memory' => []];
        $context->set('mcp_step_results', ['step' => ['success' => true, 'data' => $data]]);
        $context->recordToolCall(['tool' => 'fees.position', 'status' => 'completed', 'count' => 1]);

        return $context;
    }

    /** @return array<string, mixed> */
    private function section(StageContext $context, string $type): array
    {
        foreach ($context->sections() as $section) {
            if (($section['type'] ?? null) === $type) {
                return $section;
            }
        }

        $this->fail("no $type section was composed");
    }
}
