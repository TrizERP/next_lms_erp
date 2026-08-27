<?php

namespace App\Domain\AI\Workspace;

use App\Domain\Ontology\EntityResolver;
use App\Domain\Ontology\OntologyRegistry;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns "the user is on this route" into a usable AI context.
 *
 * The whole resolution is data-driven: `ai_modules` holds the route patterns, the
 * entity each pattern is about, and which capabilities the module offers. Adding a
 * module to the workspace is a row, not a code change — which is the point, because
 * this estate has 56 route folders and no two named alike.
 *
 * The entity is then hydrated through EntityResolver, so the label shown in the panel
 * is the real record's name, read under the caller's tenant scope. A route naming a
 * record the user cannot see resolves to no entity at all, and the workspace falls
 * back to module-level suggestions rather than leaking that the record exists.
 */
class AiContextService
{
    private const CACHE_TTL = 600;

    public function __construct(
        private readonly RouteMatcher $matcher,
        private readonly EntityResolver $entities,
        private readonly OntologyRegistry $ontology,
        private readonly PageTypeResolver $pageTypes,
    ) {
    }

    /**
     * Resolve the full context for a panel open.
     *
     * @param  array  $hints  Optional page-supplied extras: selected_records, page_data,
     *                        and an explicit entity when a page knows better than its URL
     *                        (a modal, a selected row in a grid).
     */
    public function resolve(string $route, McpRequestContext $scope, array $hints = []): AiContext
    {
        $normalizedRoute = $this->matcher->normalize($route);
        $module = $this->matchModule($normalizedRoute, $scope);

        $routeParams = $module['params'] ?? [];
        $entityKey = null;
        $entityId = null;

        if ($module['row'] ?? null) {
            $entityKey = $module['row']->entity_key ?: null;
            $entityParam = $module['row']->entity_param ?: null;

            if ($entityKey && $entityParam && isset($routeParams[$entityParam])) {
                $entityId = $routeParams[$entityParam];
            }
        }

        // A page may declare its own subject — a selected grid row, a modal record —
        // which is more accurate than the URL. It is still resolved through the
        // scoped resolver, so it cannot be used to reach anything new.
        if (! empty($hints['entity_type']) && ! empty($hints['entity_id'])) {
            $hintedKey = (string) $hints['entity_type'];

            if ($this->ontology->hasEntity($hintedKey, $scope->selectedInstituteId)) {
                $entityKey = $hintedKey;
                $entityId = $hints['entity_id'];
            }
        }

        $entityLabel = null;
        $entityAttributes = [];

        if ($entityKey && $entityId !== null && is_numeric($entityId)) {
            $resolved = $this->entities->resolveOne($entityKey, $entityId, $scope);

            if ($resolved) {
                $entityLabel = $resolved['label'];
                $entityAttributes = $resolved['attributes'];
            } else {
                // Out of scope or gone. Drop the entity rather than showing actions
                // against a record the caller cannot read.
                $entityKey = null;
                $entityId = null;
            }
        }

        $page = PageSnapshot::fromArray(is_array($hints['page_data'] ?? null) ? $hints['page_data'] : []);

        // What kind of page this is drives which capabilities are useful here, so it is
        // resolved before them. The page's own declaration wins; otherwise it is
        // inferred from the route, falling back to "detail" when a record resolved.
        $pageType = $this->pageTypes->resolve(
            $normalizedRoute,
            $page->type,
            $entityKey !== null && $entityId !== null
        );

        return new AiContext(
            scope: $scope,
            route: $normalizedRoute,
            moduleKey: $module['row']->module_key ?? null,
            moduleLabel: $module['row']->label ?? null,
            entityKey: $entityKey,
            entityId: $entityId,
            entityLabel: $entityLabel,
            entityAttributes: $entityAttributes,
            routeParams: $routeParams,
            selectedRecords: $this->normalizeSelected($hints['selected_records'] ?? []),
            pageData: is_array($hints['page_data'] ?? null) ? $hints['page_data'] : [],
            capabilities: $this->capabilitiesFor($module['row'] ?? null, $scope, $pageType),
            page: $page,
            pageType: $pageType,
        );
    }

    /**
     * @return array<int, object>
     */
    public function modules(?int $subInstituteId = null): array
    {
        if (! Schema::hasTable('ai_modules')) {
            return [];
        }

        $rows = Cache::remember(
            'ai:modules:' . ($subInstituteId ?? 'global'),
            self::CACHE_TTL,
            fn () => DB::table('ai_modules')
                ->where('status', 1)
                ->where(function ($query) use ($subInstituteId) {
                    $query->whereNull('sub_institute_id');
                    if ($subInstituteId !== null) {
                        $query->orWhere('sub_institute_id', $subInstituteId);
                    }
                })
                // Tenant rows first so an override shadows the baseline of the same key.
                ->orderByRaw('sub_institute_id IS NULL ASC')
                ->orderBy('match_priority')
                ->orderBy('sort_order')
                ->get()
                ->all()
        );

        $byKey = [];

        foreach ($rows as $row) {
            if (! isset($byKey[$row->module_key])) {
                $byKey[$row->module_key] = $row;
            }
        }

        return array_values($byKey);
    }

    public function flush(?int $subInstituteId = null): void
    {
        Cache::forget('ai:modules:' . ($subInstituteId ?? 'global'));
    }

    /**
     * @return array{row:object|null, params:array<string,string>}
     */
    private function matchModule(string $route, McpRequestContext $scope): array
    {
        $best = ['row' => null, 'params' => [], 'specificity' => -1];

        foreach ($this->modules($scope->selectedInstituteId) as $row) {
            if (! $this->roleAllows($row->allowed_roles ?? null, $scope)) {
                continue;
            }

            $patterns = $this->decode($row->route_patterns);

            if ($patterns === []) {
                continue;
            }

            $result = $this->matcher->best($patterns, $route);

            if (! $result['matched']) {
                continue;
            }

            // match_priority breaks ties between equally specific patterns, so a
            // deliberately broad catch-all can be pushed behind the modules it overlaps.
            $score = ($result['specificity'] * 1000) - (int) ($row->match_priority ?? 100);

            if ($score > $best['specificity']) {
                $best = ['row' => $row, 'params' => $result['params'], 'specificity' => $score];
            }
        }

        return ['row' => $best['row'], 'params' => $best['params']];
    }

    /**
     * Which tabs this module offers, after the role check.
     *
     * A capability the module does not declare is absent, not disabled — the panel
     * should not show a tab that leads nowhere.
     */
    private function capabilitiesFor(?object $row, McpRequestContext $scope, string $pageType): array
    {
        $capabilities = [
            'conversational' => true,
            'generative' => false,
            'agent' => false,
            'workflow' => false,
            'ontology' => false,
        ];

        /*
         * Page type first, module second, and the two are *unioned* rather than the
         * module overriding.
         *
         * Union is deliberate. The module rows were written before page types existed
         * and mostly declare `false` for everything but conversation; letting those
         * win would mean a page type could never switch anything on, which is the
         * whole point of this layer. And over-enabling costs nothing: a capability
         * that resolves to no suggestions is hidden rather than shown as an empty tab,
         * so a tab only ever appears when there is something behind it.
         */
        foreach ($this->pageTypes->capabilitiesFor($pageType) as $key => $enabled) {
            if (array_key_exists($key, $capabilities) && $enabled) {
                $capabilities[$key] = true;
            }
        }

        if (! $row) {
            // Unmapped route: the page type still applies, so an unconfigured report
            // page offers analysis rather than conversation alone.
            return $this->applyRoleLimits($capabilities, $scope);
        }

        foreach ($this->decode($row->capabilities) as $key => $enabled) {
            if (array_key_exists($key, $capabilities) && $enabled) {
                $capabilities[$key] = true;
            }
        }

        return $this->applyRoleLimits($capabilities, $scope);
    }

    /**
     * Role limits, applied last so neither the page type nor the module can widen past
     * them. Students get the assistant, but not the analysis and action surfaces.
     *
     * @param  array<string, bool>  $capabilities
     * @return array<string, bool>
     */
    private function applyRoleLimits(array $capabilities, McpRequestContext $scope): array
    {
        if ($scope->isStudent) {
            $capabilities['agent'] = false;
            $capabilities['workflow'] = false;
        }

        return $capabilities;
    }

    private function roleAllows(mixed $allowedRoles, McpRequestContext $scope): bool
    {
        $roles = $this->decode($allowedRoles);

        return $roles === [] || in_array($scope->role, $roles, true);
    }

    /**
     * @return array<int, array{entity:string|null, id:mixed}>
     */
    private function normalizeSelected(mixed $selected): array
    {
        if (! is_array($selected)) {
            return [];
        }

        $records = [];

        foreach (array_slice($selected, 0, 100) as $record) {
            if (is_scalar($record)) {
                $records[] = ['entity' => null, 'id' => $record];

                continue;
            }

            if (is_array($record) && isset($record['id'])) {
                $records[] = [
                    'entity' => isset($record['entity']) ? (string) $record['entity'] : null,
                    'id' => $record['id'],
                ];
            }
        }

        return $records;
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
