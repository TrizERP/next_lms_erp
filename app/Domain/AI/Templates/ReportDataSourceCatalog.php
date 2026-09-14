<?php

namespace App\Domain\AI\Templates;

use App\Mcp\ToolRegistry;

/**
 * The MCP tools a report template may draw its rows from.
 *
 * WHY THE LIST IS THE TOOL REGISTRY AND NOT A LIST OF ITS OWN
 *
 * Every module that can already answer a question in the assistant does so through a
 * tool in `ToolRegistry` — `fees.get_pending`, `attendance.overview`,
 * `students.directory`. Those tools carry the tenant scoping, the joins, the field
 * names and the governance annotations that took the rest of the assistant to get
 * right. A report that queried the database on its own would be a second opinion about
 * the school, and the two would drift the first time a fee head was renamed.
 *
 * So the catalogue is a view over the registry rather than a table an administrator
 * fills in. Register a tool and it becomes available to report templates; that is the
 * whole of "a new module needs no new code here".
 *
 * READ-ONLY TOOLS ONLY
 *
 * A report is a document about what is true. Binding one to a tool annotated `write`
 * would mean that opening a report — or refreshing its figures, which re-runs the same
 * call — changes the school's records. `admissions.confirm` is a real tool in this
 * registry and it confirms admissions. It must never be reachable from a layout, so
 * the filter here is on the tool's own `read_only` annotation rather than on a list of
 * names somebody has to remember to update.
 */
class ReportDataSourceCatalog
{
    /**
     * The registry is resolved per call, NOT constructor-injected. Do not "tidy" this
     * into the constructor — it deadlocks the container.
     *
     * `ToolRegistry` is built by instantiating every tool, and one of those tools is
     * `AiTemplatesGenerateTool`, which depends on `AiReportGenerator`, which depends on
     * this catalogue. Taking the registry as a constructor argument closes that loop:
     *
     *   ToolRegistry → AiTemplatesGenerateTool → AiReportGenerator
     *                → ReportDataSourceCatalog → ToolRegistry → …
     *
     * which recurses until the process runs out of memory — not a readable error, just
     * a 500MB fatal on the first request that touches a report. Resolving on demand
     * breaks the loop because by the time anything calls a method here, the registry
     * singleton is already built and cached.
     */
    private function registry(): ToolRegistry
    {
        return app(ToolRegistry::class);
    }

    /**
     * Every tool a report template may bind to, grouped by the module it serves.
     *
     * The module is taken from the tool's own name — `fees.get_pending` serves `fees` —
     * because that prefix is already the convention every tool in the registry follows,
     * and deriving it means a new tool is grouped correctly without being registered
     * anywhere a second time.
     *
     * @return array<int, array{name:string, module:string, label:string, description:string, arguments:array<int, array{key:string, type:string, description:string, required:bool}>}>
     */
    public function all(): array
    {
        $sources = [];

        foreach ($this->registry()->tools() as $tool) {
            $definition = $tool->definition();

            if (($definition['annotations']['read_only'] ?? false) !== true) {
                continue;
            }

            $name = (string) $definition['name'];

            $sources[] = [
                'name' => $name,
                'module' => $this->moduleOf($name),
                'label' => $this->labelOf($name),
                'description' => (string) ($definition['description'] ?? ''),
                'arguments' => $this->argumentsOf($definition['input_schema'] ?? []),
            ];
        }

        usort($sources, static fn (array $a, array $b) => [$a['module'], $a['name']] <=> [$b['module'], $b['name']]);

        return $sources;
    }

    /** @return array{name:string, module:string, label:string, description:string, arguments:array<int, array<string, mixed>>}|null */
    public function find(string $name): ?array
    {
        foreach ($this->all() as $source) {
            if ($source['name'] === $name) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Whether a template may bind to this tool.
     *
     * False for a tool that does not exist *and* for one that exists but writes — the
     * caller gets one answer to "can a layout use this", not two it has to combine.
     */
    public function isBindable(string $name): bool
    {
        return $this->find($name) !== null;
    }

    /**
     * The tools that serve one module, for the picker once a module is chosen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forModule(?string $moduleKey): array
    {
        if ($moduleKey === null || $moduleKey === '' || $moduleKey === TemplateModuleCatalog::SHARED) {
            return $this->all();
        }

        $exact = array_values(array_filter(
            $this->all(),
            fn (array $source) => $this->matchesModule($source['module'], $moduleKey)
        ));

        // Falling back to everything rather than to nothing: the module prefixes are a
        // convention, not a guarantee, and a module whose tool is named differently
        // would otherwise show an empty picker with no way to recover.
        return $exact === [] ? $this->all() : $exact;
    }

    /**
     * Tool prefixes and `ai_modules` keys are two vocabularies for the same thing.
     *
     * `admissions.list_enquiries` serves the module keyed `admissions`; `lms.courses`
     * serves `course-master`. Rather than a mapping table that has to be maintained
     * beside both, this compares them loosely — singular/plural and separator
     * differences — and the picker falls back to the full list when nothing matches.
     */
    private function matchesModule(string $toolModule, string $moduleKey): bool
    {
        $normalise = static fn (string $value) => rtrim(
            str_replace(['-', '_'], '', mb_strtolower($value)),
            's'
        );

        return $normalise($toolModule) === $normalise($moduleKey);
    }

    private function moduleOf(string $toolName): string
    {
        $segments = explode('.', $toolName);

        return $segments[0] ?? $toolName;
    }

    private function labelOf(string $toolName): string
    {
        return ucwords(str_replace(['.', '_'], [' — ', ' '], $toolName));
    }

    /**
     * The arguments a tool accepts, flattened for the form that maps values into them.
     *
     * @param  array<string, mixed>  $schema
     * @return array<int, array{key:string, type:string, description:string, required:bool}>
     */
    private function argumentsOf(array $schema): array
    {
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        $arguments = [];

        foreach ($properties as $key => $property) {
            $arguments[] = [
                'key' => (string) $key,
                'type' => (string) (is_array($property) ? ($property['type'] ?? 'string') : 'string'),
                'description' => (string) (is_array($property) ? ($property['description'] ?? '') : ''),
                'required' => in_array($key, $required, true),
            ];
        }

        return $arguments;
    }
}
