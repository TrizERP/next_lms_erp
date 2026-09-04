<?php

namespace Tests\Feature;

use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Mcp\ToolRegistry;
use Tests\TestCase;

/**
 * The module tool bindings and the tool registry have to agree.
 *
 * This test exists because they silently did not. `config/ai.php` bound the fees module
 * to `fees.get_pending`, and the registered tool is called `fees.getPending` — so the
 * binding matched nothing, the tool was unreachable from every fees question, and
 * nothing anywhere reported a problem. A name that does not exist is not an error at
 * runtime; it is a tool the caller quietly never has.
 *
 * These are cheap assertions over configuration and the container. They do not touch the
 * database, which is deliberate: this must keep passing on a machine that cannot reach
 * the estate.
 */
class McpToolBindingsTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function registeredToolNames(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $definition) => $definition['name'] ?? null,
            app(ToolRegistry::class)->definitions()
        ), 'is_string'));
    }

    public function test_every_module_binding_names_a_registered_tool(): void
    {
        $registered = $this->registeredToolNames();
        $problems = [];

        foreach ((array) config('ai.lifecycle.modules', []) as $module => $binding) {
            foreach ((array) ($binding['mcp_tools'] ?? []) as $tool) {
                if (! in_array($tool, $registered, true)) {
                    $problems[] = sprintf('%s binds "%s", which is not registered', $module, $tool);
                }
            }
        }

        $this->assertSame(
            [],
            $problems,
            "Module tool bindings reference tools that do not exist:\n  " . implode("\n  ", $problems)
                . "\n\nRegistered tools are:\n  " . implode("\n  ", $registered)
        );
    }

    public function test_tool_names_are_unique(): void
    {
        // The registry keys by name, so a duplicate silently replaces its predecessor
        // and one tool disappears without a word.
        $names = $this->registeredToolNames();

        $this->assertSame(
            array_values(array_unique($names)),
            $names,
            'Two MCP tools share a name; the registry keeps only the last one registered.'
        );
    }

    public function test_every_tool_declares_a_usable_definition(): void
    {
        foreach (app(ToolRegistry::class)->definitions() as $definition) {
            $name = $definition['name'] ?? '(unnamed)';

            $this->assertNotEmpty($definition['name'] ?? null, 'A tool has no name.');
            $this->assertNotEmpty(
                $definition['description'] ?? null,
                "{$name} has no description — the planner selects tools by reading these."
            );
            $this->assertIsArray(
                $definition['input_schema'] ?? null,
                "{$name} has no input schema, so a planner cannot fill its arguments."
            );
            $this->assertArrayHasKey(
                'allowed_roles',
                $definition['annotations'] ?? [],
                "{$name} declares no role gate."
            );
        }
    }

    public function test_a_module_without_an_agent_still_explains_its_depth(): void
    {
        // Stages 10-12 report not-reached for these modules, and a reader is owed a
        // reason rather than a blank row.
        $modules = app(ModuleRegistry::class)->all();

        foreach ($modules as $key => $module) {
            if ($module->hasAgent()) {
                continue;
            }

            $this->assertNotSame(
                '',
                trim($module->whyNoDepth()),
                "The {$key} module cannot reach stage 10 and gives no reason why."
            );
        }
    }

    public function test_consequential_tools_are_marked_so_the_planner_cannot_see_them(): void
    {
        // The LLM planner filters its catalogue on these annotations. A write tool that
        // forgets to declare itself would become model-selectable.
        $confirmable = [];

        foreach (app(ToolRegistry::class)->definitions() as $definition) {
            $annotations = $definition['annotations'] ?? [];

            if (($annotations['requires_confirmation'] ?? false) === true) {
                $confirmable[] = $definition['name'];
            }
        }

        $this->assertContains(
            'admissions.confirm',
            $confirmable,
            'admissions.confirm writes a record and must require confirmation.'
        );
    }
}
