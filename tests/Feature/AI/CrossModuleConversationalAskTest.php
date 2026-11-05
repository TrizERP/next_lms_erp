<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Lifecycle\LifecycleAskService;
use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Domain\AI\Lifecycle\Modules\ModuleResolver;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Stages\McpToolSelectionStage;
use App\Domain\Workflow\WorkflowEngine;
use App\Http\Controllers\AI\ReportController;
use App\Mcp\ToolRegistry;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrossModuleConversationalAskTest extends TestCase
{
    private function scope(int $instituteId = 1): McpRequestContext
    {
        return new McpRequestContext(
            userId: 1,
            role: 'Admin',
            selectedInstituteId: $instituteId,
            allowedInstituteIds: [$instituteId],
            userProfileId: 1,
            clientId: 1,
            academicYear: 2026,
            termId: 1,
            isAdmin: true,
            isStudent: false,
        );
    }

    /**
     * Item 3 & 4: Multi-module natural-language query resolution and federation.
     */
    public function test_cross_module_question_resolves_primary_and_federated_modules(): void
    {
        $resolver = app(ModuleResolver::class);
        $question = 'Which students with attendance below 75 percent also have pending fees?';
        $resolution = $resolver->resolve($question, [], 1);

        $this->assertNotSame(
            'general',
            $resolution['module']->key,
            'Cross-module question must not drop into the empty general module.'
        );

        $this->assertContains($resolution['module']->key, ['fees', 'attendance']);
        $this->assertNotEmpty($resolution['federated'], 'Should identify at least one federated module.');

        $federatedKeys = array_map(fn ($m) => $m->key, $resolution['federated']);
        $allParticipating = array_merge([$resolution['module']->key], $federatedKeys);

        $this->assertContains('fees', $allParticipating);
        $this->assertContains('attendance', $allParticipating);
    }

    /**
     * Item 5: Unmapped module route bindings.
     */
    public function test_all_previously_unmapped_module_routes_resolve_to_active_modules(): void
    {
        $resolver = app(ModuleResolver::class);

        $routesToExpected = [
            '/modules/books/ai-stack' => 'library',
            '/modules/hrms-report/ai-stack' => 'hr',
            '/modules/hrit-management/ai-stack' => 'hr',
            '/modules/talent-management/ai-stack' => 'hr',
            '/modules/organization-management/ai-stack' => 'institute',
            '/modules/career-counseling/ai-stack' => 'career-counselling',
            '/modules/career-awareness/ai-stack' => 'career-counselling',
            '/modules/career-explorer/ai-stack' => 'career-counselling',
            '/modules/stock-verification/ai-stack' => 'inventory',
            '/modules/other-reports/ai-stack' => 'reports',
            '/modules/capability-intelligence/ai-stack' => 'reports',
            '/modules/platform-services/ai-stack' => 'migration-modules',
            '/modules/skill-assessment/ai-stack' => 'teach_learn',
            '/modules/skill-management/ai-stack' => 'teach_learn',
        ];

        foreach ($routesToExpected as $route => $expectedModule) {
            $resolution = $resolver->resolve('Summarise this page.', ['route' => $route], 1);
            $this->assertSame(
                $expectedModule,
                $resolution['module']->key,
                "Route {$route} should resolve to {$expectedModule}, got {$resolution['module']->key}"
            );
            $this->assertNotEmpty(
                $resolution['module']->mcpTools,
                "Resolved module {$expectedModule} must have active data tools."
            );
        }
    }

    /**
     * Item 3 & 4: McpToolSelectionStage commits candidate tools from primary AND federated modules.
     */
    public function test_mcp_tool_selection_stage_allows_federated_tools(): void
    {
        $registry = app(ModuleRegistry::class);
        $modules = $registry->all(1);

        $primaryModule = $modules['fees'];
        $federatedModule = $modules['attendance'];

        $context = new StageContext(
            question: 'Check student fees and attendance',
            scope: $this->scope(),
            module: $primaryModule
        );
        $context->federatedModules = [$federatedModule];

        // Mock a plan that proposed tools across both modules
        $plan = new \App\Domain\AI\Lifecycle\Plan\Plan(
            goal: 'Read fees and attendance records',
            steps: [
                new \App\Domain\AI\Lifecycle\Plan\PlanStep(id: 's1', purpose: 'Read fees', tool: 'fees.outstanding_accounts'),
                new \App\Domain\AI\Lifecycle\Plan\PlanStep(id: 's2', purpose: 'Read attendance', tool: 'attendance.overview'),
            ],
            source: 'test',
            route: 'mcp_tools',
            candidateTools: ['fees.outstanding_accounts', 'attendance.overview']
        );
        $context->plan = $plan;

        $stage = new McpToolSelectionStage(app(ToolRegistry::class));
        $outcome = $stage->run($context);

        $this->assertSame('ran', $outcome->status->value);
        $this->assertContains('fees.outstanding_accounts', $context->selectedTools);
        $this->assertContains('attendance.overview', $context->selectedTools);
    }

    /**
     * Item 12: Workflow approval state management and idempotency.
     */
    public function test_workflow_approval_idempotency_returns_success_when_already_resolved(): void
    {
        if (! Schema::hasTable('workflow_approvals')) {
            $this->markTestSkipped('workflow_approvals table not present.');
        }

        $scope = $this->scope(1);

        // Insert a temporary test approval that is already approved
        $approvalId = DB::table('workflow_approvals')->insertGetId([
            'run_id' => 1,
            'sub_institute_id' => 1,
            'step_key' => 'test_step',
            'approver_role' => 'Admin',
            'status' => 'approved',
            'decided_by' => 1,
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $engine = app(WorkflowEngine::class);

            // Calling resolveApproval with same decision ('approved') must succeed idempotently
            $result = $engine->resolveApproval($approvalId, 'approved', $scope);

            $this->assertTrue($result['idempotent'] ?? false);
            $this->assertSame('approved', $result['status']);
        } finally {
            DB::table('workflow_approvals')->where('id', $approvalId)->delete();
        }
    }

    /**
     * Item 10: Report export endpoint.
     */
    public function test_report_export_endpoint_formats_html_and_csv(): void
    {
        $controller = app(ReportController::class);

        // We can test the private extractCsvFromHtml via reflection
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('extractCsvFromHtml');
        $method->setAccessible(true);

        $sampleHtml = '<table><tr><th>Student</th><th>Pending</th></tr><tr><td>John Doe</td><td>5000</td></tr></table>';
        $csv = $method->invoke($controller, $sampleHtml);

        $this->assertStringContainsString('"Student","Pending"', $csv);
        $this->assertStringContainsString('"John Doe","5000"', $csv);
    }
}

