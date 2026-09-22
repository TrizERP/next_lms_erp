<?php

namespace Tests\Feature\AI\Workspace;

use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Domain\AI\Modules\ModuleReadTools;
use App\Domain\AI\Workspace\AiContext;
use App\Domain\AI\Workspace\ModuleToolData;
use App\Mcp\AbstractMcpTool;
use App\Mcp\ToolRegistry;
use App\Services\Mcp\McpConfirmationService;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\ToolResult;
use Tests\TestCase;

/**
 * A module's Create and Analyse actions are grounded in that module's own data.
 *
 * The failure this guards is the one that made the workspace's Create tab useless: every
 * template that summarises something declares grounding variables, `GroundingCheck`
 * refuses when all of them are empty, and nothing was ever filling them — so "Summarise
 * pending fees" refused on a school with 3,424 students because the page had not
 * volunteered its rows.
 *
 * The tests use stub tools rather than the real fees ones on purpose. The contract being
 * guarded is that a module's *bound read tools* are what fills a template, whatever those
 * tools are — a fees-specific test would pass on a fees-specific implementation, which is
 * exactly what this must not become.
 */
class ModuleToolDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.lifecycle.modules.stub_module' => [
                'mcp_tools' => [
                    // Deliberately first: a write tool must be passed over, not called,
                    // by a path that runs without the user naming a tool.
                    'stub.write',
                    // Second: a read tool whose required argument a page-level question
                    // cannot supply. Calling it could only fail.
                    'stub.needs_id',
                    'stub.rows',
                    'stub.figures',
                ],
            ],
        ]);

        $this->app->instance(ToolRegistry::class, new ToolRegistry(
            [new StubRowsTool(), new StubFiguresTool(), new StubWriteTool(), new StubNeedsIdTool()],
            $this->app->make(McpConfirmationService::class),
        ));

        $this->app->forgetInstance(ModuleRegistry::class);
    }

    public function test_a_module_is_grounded_from_the_read_tools_it_is_bound_to(): void
    {
        $data = $this->resolve();

        $this->assertTrue($data['resolved']);
        $this->assertSame('stub.rows, stub.figures', $data['source']);
    }

    public function test_a_write_tool_and_an_unsatisfiable_one_are_never_called(): void
    {
        $data = $this->resolve();

        // Both appear before the usable tools in the binding, so if either were called
        // the source would name it and the two read tools would not both be reached.
        $this->assertStringNotContainsString('stub.write', (string) $data['source']);
        $this->assertStringNotContainsString('stub.needs_id', (string) $data['source']);
    }

    public function test_rows_arrive_with_a_label_and_their_fields_intact(): void
    {
        $records = $this->resolve()['records'];

        $this->assertCount(2, $records);
        $this->assertSame('Aarav Patel', $records[0]['label']);
        $this->assertSame(11, $records[0]['id']);

        // The field that supplied the label is still an attribute. A row whose only
        // useful field was the name would otherwise reach the template with nothing
        // beside it.
        $this->assertSame('Aarav Patel', $records[0]['attributes']['student_name']);
        $this->assertSame('1200', $records[0]['attributes']['outstanding']);
    }

    public function test_the_real_total_wins_over_the_window_that_was_read(): void
    {
        // Two rows were listed; the tool says the cohort is 3,424. Reporting "2" would
        // let a template state a school-wide finding from a sample of two.
        $this->assertSame(3424, $this->resolve()['record_count']);
    }

    public function test_figures_from_every_tool_reach_the_template(): void
    {
        $metrics = collect($this->resolve()['metrics'])->pluck('value', 'key')->all();

        $this->assertSame('2', $metrics['defaulter_count']);
        $this->assertSame('yes', $metrics['truncated'], 'A boolean has to survive as something a prompt can read.');
        $this->assertSame('48250', $metrics['collected_total'], 'The second tool contributes figures too.');

        $this->assertArrayNotHasKey(
            'basis',
            $metrics,
            'A paragraph explaining where the numbers came from is an audit note, not a figure.'
        );
    }

    public function test_a_module_bound_to_nothing_readable_resolves_nothing(): void
    {
        config(['ai.lifecycle.modules.stub_module.mcp_tools' => ['stub.write', 'stub.needs_id']]);
        $this->app->forgetInstance(ModuleRegistry::class);

        $data = $this->resolve();

        $this->assertFalse($data['resolved']);
        $this->assertSame([], $data['records']);
        $this->assertSame([], $data['metrics']);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array<string, mixed>
     */
    private function resolve(): array
    {
        $resolver = new ModuleToolData(
            $this->app->make(ModuleRegistry::class),
            new ModuleReadTools(),
        );

        return $resolver->resolve(new AiContext(
            scope: $this->scope(),
            route: '/stub',
            moduleKey: 'stub_module',
            moduleLabel: 'Stub',
        ));
    }

    private function scope(): McpRequestContext
    {
        return new McpRequestContext(
            userId: 7,
            role: 'admin',
            selectedInstituteId: 1,
            allowedInstituteIds: [1],
            userProfileId: null,
            clientId: null,
            academicYear: 2026,
            termId: null,
            isAdmin: true,
            isStudent: false,
        );
    }
}

/** Returns rows in the `ToolResult::success()` envelope, beside its own figures. */
class StubRowsTool extends AbstractMcpTool
{
    public function name(): string
    {
        return 'stub.rows';
    }

    public function description(): string
    {
        return 'Rows and figures, in the wrapped envelope.';
    }

    protected function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        return ToolResult::success('stub.rows', 'Two rows.', [
            'students_with_arrears' => [
                ['student_id' => 11, 'student_name' => 'Aarav Patel', 'outstanding' => 1200],
                ['student_id' => 12, 'student_name' => 'Meera Shah', 'outstanding' => 800],
            ],
            'defaulter_count' => 2,
            'cohort_size' => 3424,
            'truncated' => true,
            'basis' => 'A long paragraph explaining exactly which controller computed these '
                . 'figures and why nothing here recomputes what is owed.',
        ]);
    }
}

/** Returns its payload bare, the way several existing tools do. */
class StubFiguresTool extends AbstractMcpTool
{
    public function name(): string
    {
        return 'stub.figures';
    }

    public function description(): string
    {
        return 'Figures only, unwrapped.';
    }

    protected function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        return ['collected_total' => 48250, 'receipts' => []];
    }
}

class StubWriteTool extends AbstractMcpTool
{
    public function name(): string
    {
        return 'stub.write';
    }

    public function description(): string
    {
        return 'Changes a record.';
    }

    protected function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'additionalProperties' => false];
    }

    protected function isReadOnly(): bool
    {
        return false;
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        throw new \RuntimeException('A write tool must never be reached from this path.');
    }
}

class StubNeedsIdTool extends AbstractMcpTool
{
    public function name(): string
    {
        return 'stub.needs_id';
    }

    public function description(): string
    {
        return 'Needs a student nobody named.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['student_id' => ['type' => 'integer']],
            'required' => ['student_id'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        throw new \RuntimeException('A tool with an unsatisfiable required argument must never be reached.');
    }
}
