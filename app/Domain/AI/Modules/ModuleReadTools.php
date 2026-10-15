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
     * Arguments a tool can take straight from the question's own words.
     *
     * Only what the tool's schema declares and the sentence states outright: "courses for Grade 8"
     * gives a tool with a `standard_name` argument the standard "8". Nothing is guessed — a question
     * naming no class yields no arguments, which means "no filter".
     *
     * @return array<string, mixed>
     */
    public function argumentsFromQuestion(string $tool, string $question): array
    {
        $schema = $this->registry()?->tool($tool)?->definition()['input_schema'] ?? [];
        $properties = (array) ($schema['properties'] ?? []);
        $arguments = [];

        if (array_key_exists('standard_name', $properties)
            && preg_match('/\b(?:grade|class|standard|std)\s*[-:]?\s*(\d{1,2})\b/i', $question, $m) === 1) {
            $arguments['standard_name'] = $m[1];
        }

        // A tool that reads the user's own words (to find a subject named in them) is handed them.
        if (array_key_exists('question', $properties) && trim($question) !== '') {
            $arguments['question'] = $question;
        }

        return $arguments;
    }

    /**
     * The bound tool the question's own words point at, when the module declares hints for it.
     *
     * `ai.lifecycle.modules.<module>.read_hints` maps a tool to the words that call for it: a question
     * about "chapters" or a "lesson plan" is answered by the course-summary tool, not by whichever tool
     * the module happens to list first. Only a readable bound tool is ever returned.
     *
     * @param  array<int, string>  $readable
     */
    public function hintedTool(string $moduleKey, array $readable, string $question): ?string
    {
        $hints = (array) config(sprintf('ai.lifecycle.modules.%s.read_hints', $moduleKey), []);

        foreach ($hints as $tool => $words) {
            if (! in_array($tool, $readable, true)) {
                continue;
            }

            foreach ((array) $words as $word) {
                if (is_string($word) && $word !== '' && stripos($question, $word) !== false) {
                    return (string) $tool;
                }
            }
        }

        return null;
    }

    /**
     * The first readable bound tool that declares a `period` argument, or null.
     *
     * Used when a question names a day or a range and nothing smarter could route it: the tool says,
     * in its own schema, that it can be asked about a period, so it is the right one to read.
     *
     * @param  array<int, string>  $bound
     */
    public function acceptingPeriod(array $bound): ?string
    {
        $registry = $this->registry();

        if ($registry === null) {
            return null;
        }

        foreach ($this->select($bound, 50) as $name) {
            $schema = $registry->tool($name)?->definition()['input_schema'] ?? [];
            $properties = (array) ($schema['properties'] ?? []);

            if (array_key_exists('period', $properties)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Whether a tool's own annotations declare that it only reads.
     *
     * `read_only` alone is not enough. `ai.templates.generate` is bound to nearly every module so the
     * page's report action can reach it, and it says `read_only: true` while declaring `risk: write` —
     * the module-isolation test requires every bound tool to be `read_only`, so that flag cannot be the
     * thing that tells a write apart. The declared `risk` can. Every path that runs without the user
     * naming a tool — the model planner's catalogue, the page-level fallback, a record lookup — asks
     * this, so "show me today's attendance" can never be planned as "generate a report".
     *
     * @param  array<string, mixed>  $annotations
     */
    public static function declaresRead(array $annotations): bool
    {
        return ($annotations['risk'] ?? 'read') === 'read';
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

            if (($definition['annotations']['read_only'] ?? false) !== true || ! self::declaresRead((array) $definition['annotations'])) {
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
