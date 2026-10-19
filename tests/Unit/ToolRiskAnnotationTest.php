<?php

namespace Tests\Unit;

use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Domain\AI\Lifecycle\Plan\LlmPlanner;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Modules\ModuleReadTools;
use App\Mcp\ToolRegistry;
use App\Services\Mcp\McpRequestContext;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A tool that changes something is never offered to a path the user did not aim at it.
 *
 * `ai.templates.generate` is bound to nearly every module (the page's report action reaches it) and
 * declares `read_only: true` — the module-isolation test requires that of every bound tool — while
 * declaring `risk: write`. The model planner trusted `read_only` alone, so "show me today's
 * attendance" was planned as that tool and saved a report. The declared risk is what tells them apart.
 */
class ToolRiskAnnotationTest extends TestCase
{
    public function test_a_tool_declaring_a_non_read_risk_is_not_a_read(): void
    {
        $offered = [];

        foreach (app(ToolRegistry::class)->definitions() as $definition) {
            $annotations = (array) ($definition['annotations'] ?? []);

            if (ModuleReadTools::declaresRead($annotations) !== (($annotations['risk'] ?? 'read') === 'read')) {
                $offered[] = $definition['name'];
            }
        }

        $this->assertSame([], $offered);
        $this->assertFalse(ModuleReadTools::declaresRead(['risk' => 'write']));
        $this->assertFalse(ModuleReadTools::declaresRead(['risk' => 'approval']));
        $this->assertTrue(ModuleReadTools::declaresRead(['risk' => 'read']));
        $this->assertTrue(ModuleReadTools::declaresRead([]), 'a tool that declares no risk is a read, as it always was');
    }

    public function test_the_page_level_fallback_never_reads_report_generation(): void
    {
        $usable = (new ModuleReadTools())->select(['ai.templates.generate', 'ai.templates.list'], 5);

        $this->assertNotContains('ai.templates.generate', $usable);
    }

    public function test_the_model_planners_catalogue_never_offers_a_write_to_any_module_that_binds_one(): void
    {
        $modules = app(ModuleRegistry::class);
        $planner = app(LlmPlanner::class);
        $catalogue = new ReflectionMethod($planner, 'availableTools');
        $writes = ['ai.templates.generate', 'admissions.updateEnquiry', 'admissions.confirm', 'fees.send_reminders'];
        $scope = new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);
        $checked = 0;

        foreach (['attendance', 'fees', 'timetable', 'hostel'] as $key) {
            $module = $modules->find($key);

            $this->assertNotNull($module, $key);

            $offered = array_keys($catalogue->invoke($planner, new StageContext('anything', $scope, $module)));

            $this->assertSame([], array_values(array_intersect($offered, $writes)), "{$key} offers a write to the model planner");
            $this->assertNotSame([], $offered, "{$key} offers the model planner nothing at all");
            $checked++;
        }

        $this->assertSame(4, $checked);
    }
}
