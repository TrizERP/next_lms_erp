<?php

namespace App\Domain\AI\Lifecycle\Support;

use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Mcp\ToolRegistry;

/**
 * Which bound tool loads one record in full, and what to call it with.
 *
 * Selecting a row off a list is only useful if something can then read that row properly,
 * and the answer differs per module: an enquiry is loaded by
 * `admissions.getEnquiryDetails`, a student by `students.search`. Hard-coding that
 * mapping in the planner would mean every new module needing a planner edit before its
 * lists could be selected from, which is exactly the coupling the lifecycle rewrite
 * removed.
 *
 * So two sources, in the same order the planner itself uses:
 *
 *   1. **Configured**, in `ai.lifecycle.modules.*.detail_tools`, keyed by the identifying
 *      column. A module with several plausible lookups says which one it means, once.
 *   2. **Derived**, from the tool schemas the registry already publishes: a read-only
 *      tool that takes this identifier and requires nothing else is, by construction, the
 *      lookup for that identifier. A module nobody has configured still gets selection
 *      working on its first day.
 *
 * A configured tool that is not bound to the module, or not registered, is ignored rather
 * than honoured — a binding is a permission and this must not be a way around it.
 */
class RecordDetail
{
    public function __construct(private readonly ToolRegistry $tools)
    {
    }

    /**
     * The tool that loads one record identified by `$idField`, or null.
     */
    public function toolFor(ModuleCapability $module, ?string $idField): ?string
    {
        if ($idField === null || $idField === '' || $module->mcpTools === []) {
            return null;
        }

        $configured = $this->configured($module, $idField);

        if ($configured !== null) {
            return $configured;
        }

        return $this->derived($module, $idField);
    }

    // ---------------------------------------------------------------- internals

    private function configured(ModuleCapability $module, string $idField): ?string
    {
        $map = config(sprintf('ai.lifecycle.modules.%s.detail_tools', $module->key), []);
        $tool = is_array($map) ? ($map[$idField] ?? null) : null;

        if (! is_string($tool) || $tool === '') {
            return null;
        }

        return $this->isCallable($module, $tool) ? $tool : null;
    }

    /**
     * The one read-only tool whose whole job is to take this identifier.
     *
     * Ranked rather than picked, because several tools accept a student id and only one
     * of them is a record lookup. Fewest declared arguments wins — a tool taking the id
     * and nothing else is a lookup, while one taking the id plus five filters is a query
     * that happens to be narrowable. Ties break on the name so the choice cannot vary
     * between two runs of the same estate.
     */
    private function derived(ModuleCapability $module, string $idField): ?string
    {
        $candidates = [];

        foreach ($this->tools->definitions() as $definition) {
            $name = $definition['name'] ?? null;

            if (! is_string($name) || ! in_array($name, $module->mcpTools, true)) {
                continue;
            }

            $annotations = (array) ($definition['annotations'] ?? []);

            // A write or confirmable tool is never a way to read a record. Selecting a
            // row must not be able to change it.
            if (($annotations['requires_confirmation'] ?? false) || ($annotations['read_only'] ?? true) === false) {
                continue;
            }

            $schema = (array) ($definition['inputSchema'] ?? $definition['input_schema'] ?? []);
            $properties = (array) ($schema['properties'] ?? []);
            $required = array_values(array_filter((array) ($schema['required'] ?? []), 'is_string'));

            if (! array_key_exists($idField, $properties)) {
                continue;
            }

            // Anything else the tool insists on cannot be filled from a row identifier,
            // so the call would be unmakeable.
            if (array_diff($required, [$idField]) !== []) {
                continue;
            }

            $candidates[$name] = count($properties) + ($required === [$idField] ? 0 : 100);
        }

        if ($candidates === []) {
            return null;
        }

        // Sort by rank, then by name, so the outcome is a property of the configuration
        // rather than of the registry's iteration order.
        ksort($candidates);
        asort($candidates);

        return (string) array_key_first($candidates);
    }

    private function isCallable(ModuleCapability $module, string $tool): bool
    {
        return in_array($tool, $module->mcpTools, true) && $this->tools->has($tool);
    }
}
