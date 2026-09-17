<?php

namespace App\Domain\AI\Modules;

use App\Mcp\ToolRegistry;

/**
 * Which of a module's bound tools may be read on a page-level question.
 *
 * Two callers need this answer and they must not disagree. `ModuleReadPlanner` asks it
 * when a question reaches the conversational fallback — "no route matched, read the
 * module the user is standing on". `ModuleToolData` asks it when a Create or Analyse
 * action needs the data its template is about. If the two applied different rules, the
 * chat could read a tool the report refused to, and a school would be shown two
 * different accounts of the same screen depending on which tab produced them.
 *
 * TWO RULES, AND THEY ARE THE SAME TWO THE PLANNER ALWAYS APPLIED
 *
 *   1. **Read-only.** These paths run without the user naming a tool, so they must never
 *      reach one that changes a record. `admissions.confirm` and `ai.templates.generate`
 *      are both bound to modules and both excluded.
 *   2. **No unsatisfiable required argument.** `fees.getPending` requires a `student_id`;
 *      a page-level question has no student, so calling it can only fail. A tool whose
 *      required arguments cannot be filled from page context is skipped rather than
 *      called and reported as an error.
 *
 * It knows nothing about fees, attendance or admissions. Everything it needs is declared
 * per module in `config('ai.lifecycle.modules')` and per tool in the tool's own schema,
 * so a module added later is served without a line changing here.
 */
class ModuleReadTools
{
    /**
     * The registry is resolved per call rather than injected.
     *
     * Building `ToolRegistry` instantiates every registered tool, which needs the MCP
     * bindings. Taking it as a constructor argument makes every holder of this class
     * unconstructable in a plain unit test — `LifecyclePipelineTest` builds a
     * `HybridPlanner` by hand to assert which planner a sentence reaches, and it should
     * not have to stand up the whole tool layer to do that.
     */
    public function registry(): ?ToolRegistry
    {
        try {
            return app(ToolRegistry::class);
        } catch (\Throwable) {
            // No container, or no tool layer bound in it. Callers' contract is "propose a
            // read or propose nothing", so this returns nothing rather than taking the
            // turn down with it.
            return null;
        }
    }

    /**
     * The bound tools that may be read, in the order the module declares them.
     *
     * @param  array<int, string>  $bound
     * @return array<int, string>
     */
    public function select(array $bound, int $max): array
    {
        $registry = $this->registry();

        if ($registry === null || $max < 1) {
            return [];
        }

        $usable = [];

        foreach ($bound as $name) {
            $tool = $registry->tool($name);

            if ($tool === null) {
                continue;
            }

            $definition = $tool->definition();

            if (($definition['annotations']['read_only'] ?? false) !== true) {
                continue;
            }

            if ($this->needsArgumentsWeDoNotHave($definition['input_schema'] ?? [])) {
                continue;
            }

            $usable[] = $name;

            if (count($usable) >= $max) {
                break;
            }
        }

        return $usable;
    }

    /**
     * Whether a tool demands something a page-level question cannot supply.
     *
     * Only the tool's own `required` list is consulted. An optional filter is fine —
     * omitting it means "no filter", which is exactly what a general question wants.
     *
     * @param  array<string, mixed>  $schema
     */
    private function needsArgumentsWeDoNotHave(array $schema): bool
    {
        $required = $schema['required'] ?? [];

        return is_array($required) && $required !== [];
    }
}
