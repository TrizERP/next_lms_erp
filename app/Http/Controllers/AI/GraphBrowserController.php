<?php

namespace App\Http\Controllers\AI;

use App\Domain\K12\TeacherClassScopeService;
use App\Domain\KnowledgeGraph\GraphModuleBrowser;
use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use App\Services\Graph\GraphSchema;
use App\Services\Mcp\AdministratorCheck;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;
use Throwable;

/**
 * The centralized Knowledge Graph's own navigation: Modules (GraphVocabulary
 * families) -> Submodules (GraphSchema labels) -> the real connected
 * subgraph for one label, read straight from Neo4j via GraphModuleBrowser.
 *
 * Deliberately separate from OntologyController, which serves the SQL-aware
 * hybrid GraphQueryService the decentralized per-module tabs use. Nothing on
 * this controller ever touches ontology_entities/ontology_relationships or
 * falls back to SQL.
 */
class GraphBrowserController extends AiController
{
    /** The `ai_modules.module_key` the Organization Management staff graph is filed under — see the seeding migrations that use the same slug. */
    private const ORGANIZATION_MANAGEMENT_MODULE = 'organization-management';

    public function __construct(
        private readonly GraphModuleBrowser $browser,
        private readonly TeacherClassScopeService $classScope,
        private readonly AdministratorCheck $administrators,
        private readonly KnowledgeGraphSettingsResolver $knowledgeGraphSettings,
    ) {
    }

    /**
     * Student-reachability restriction for this caller: `null` (today's
     * unrestricted behaviour) for a real administrator, otherwise exactly
     * the students this teacher's `class_teacher`/`timetable` rows reach —
     * `[]`, not unrestricted, when they have none. Uses AdministratorCheck
     * rather than the raw `$scope->isAdmin` flag because that flag mirrors
     * `tbluser.is_admin`, which AdministratorCheck's own docblock documents
     * as empty for every real admin in this estate — the naive flag would
     * misclassify genuine admins as teachers and wrongly narrow their view.
     */
    private function restrictedStudentIds(McpRequestContext $scope): ?array
    {
        if ($this->administrators->isAdministrator($scope)) {
            return null;
        }

        return $this->classScope->allowedStudentIds($scope->userId, $scope->selectedInstituteId);
    }

    /** One of the Explorer's five ERP module labels for a tblmenumaster id, or null if this scope cannot see it. */
    private function erpModuleLabel(McpRequestContext $scope, int $moduleId): ?string
    {
        foreach ($this->browser->erpModules($scope) as $module) {
            if ($module['id'] === $moduleId) {
                return $module['label'];
            }
        }

        return null;
    }

    /**
     * Drop any node whose node type is switched off, and any edge left
     * pointing at a node that is no longer there — the actual enforcement
     * behind a node-type toggle, not just the settings screen's display.
     *
     * With `$submoduleLabel` given, a node type is kept only if that one
     * submodule's own row for it resolves to enabled. Without one (a whole
     * module's merged graph, which can combine several submodules' labels),
     * a node type is kept if ANY submodule under the module still allows it —
     * the same "most permissive union" `erpModuleGraph()` already uses for
     * which labels to query at all.
     */
    private function withoutDisabledNodeTypes(array $result, McpRequestContext $scope, string $moduleLabel, ?string $submoduleLabel): array
    {
        $nodes = $result['nodes'] ?? [];

        if ($nodes === []) {
            return $result;
        }

        $allowed = $submoduleLabel !== null
            ? $this->allowedNodeTypes($scope, $moduleLabel, $submoduleLabel)
            : $this->allowedNodeTypesAcrossModule($scope, $moduleLabel);

        $kept = [];
        $filteredNodes = [];

        foreach ($nodes as $node) {
            $entityKey = $node['entityKey'] ?? null;

            if ($entityKey !== null && ! in_array($entityKey, $allowed, true)) {
                continue;
            }

            $kept[(string) $node['id']] = true;
            $filteredNodes[] = $node;
        }

        $result['nodes'] = $filteredNodes;
        $result['edges'] = array_values(array_filter(
            $result['edges'] ?? [],
            fn (array $edge) => isset($kept[(string) $edge['source']]) && isset($kept[(string) $edge['target']])
        ));

        return $result;
    }

    /** @return string[] node types still enabled for one specific submodule. */
    private function allowedNodeTypes(McpRequestContext $scope, string $moduleLabel, string $submoduleLabel): array
    {
        foreach ($this->browser->erpModules($scope) as $module) {
            if ($module['label'] !== $moduleLabel) {
                continue;
            }

            foreach ($this->browser->erpSubmodules($scope, $module['id']) as $submodule) {
                if ($submodule['label'] !== $submoduleLabel) {
                    continue;
                }

                return array_values(array_filter(
                    $submodule['labels'],
                    fn (string $label) => $this->knowledgeGraphSettings->isNodeTypeTreeEnabledFor($moduleLabel, $submoduleLabel, $label, $scope->selectedInstituteId)
                ));
            }
        }

        return [];
    }

    /** @return string[] node types still enabled for at least one submodule under this module. */
    private function allowedNodeTypesAcrossModule(McpRequestContext $scope, string $moduleLabel): array
    {
        $allowed = [];

        foreach ($this->browser->erpModules($scope) as $module) {
            if ($module['label'] !== $moduleLabel) {
                continue;
            }

            foreach ($this->browser->erpSubmodules($scope, $module['id']) as $submodule) {
                foreach ($submodule['labels'] as $label) {
                    if ($this->knowledgeGraphSettings->isNodeTypeTreeEnabledFor($moduleLabel, $submodule['label'], $label, $scope->selectedInstituteId)) {
                        $allowed[$label] = true;
                    }
                }
            }
        }

        return array_keys($allowed);
    }

    public function modules(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if ($request->query('view') === 'erp' || $request->query('type') === 'erp' || $request->query('mode') === 'erp' || $request->boolean('erp')) {
                return $this->success('Modules loaded.', ['modules' => $this->browser->erpModules($scope)]);
            }

            return $this->success('Modules loaded.', ['modules' => $this->browser->modules($scope)]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function submodules(Request $request, string $family)
    {
        try {
            $scope = $this->scope($request);

            if (is_numeric($family)) {
                return $this->success('Submodules loaded.', [
                    'module_id' => (int) $family,
                    'family' => $family,
                    'submodules' => $this->browser->erpSubmodules($scope, (int) $family),
                ]);
            }

            return $this->success('Submodules loaded.', [
                'family' => $family,
                'submodules' => $this->browser->submodules($scope, $family),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function graph(Request $request, string $label)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'sample_size' => 'nullable|integer|min:1|max:100',
                'limit' => 'nullable|integer|min:1|max:1000',
            ]);

            if (is_numeric($label)) {
                return $this->success('Graph loaded.', $this->browser->graphForSubmodule(
                    $scope,
                    (int) $label,
                    $validated['sample_size'] ?? 12,
                    $validated['limit'] ?? 300,
                    $this->restrictedStudentIds($scope),
                ));
            }

            return $this->success('Graph loaded.', $this->browser->graph(
                $label,
                $scope->selectedInstituteId,
                $validated['sample_size'] ?? 20,
                $validated['limit'] ?? 250,
                15,
                $this->restrictedStudentIds($scope),
            ));
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The real ERP modules — tblmenumaster level-1 — for the "All Modules"
     * screen. See GraphModuleBrowser::erpModules().
     */
    public function erpModules(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $modules = array_values(array_filter(
                $this->browser->erpModules($scope),
                fn (array $module) => $this->knowledgeGraphSettings->isModuleTreeEnabledFor($module['label'], $scope->selectedInstituteId)
            ));

            return $this->success('Modules loaded.', ['modules' => $modules]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** One module's real submodules — tblmenumaster level-2. */
    public function erpSubmodules(Request $request, int $moduleId)
    {
        try {
            $scope = $this->scope($request);

            $moduleLabel = $this->erpModuleLabel($scope, $moduleId);

            // A module this scope cannot see falls through unchanged (the
            // browser itself already returns no children for it); a module
            // this scope CAN see but has been switched off returns no
            // children either, rather than leaking what is under it.
            if ($moduleLabel !== null && ! $this->knowledgeGraphSettings->isModuleTreeEnabledFor($moduleLabel, $scope->selectedInstituteId)) {
                return $this->success('Submodules loaded.', ['module_id' => $moduleId, 'submodules' => []]);
            }

            $submodules = $moduleLabel === null
                ? $this->browser->erpSubmodules($scope, $moduleId)
                : array_values(array_filter(
                    $this->browser->erpSubmodules($scope, $moduleId),
                    fn (array $submodule) => $this->knowledgeGraphSettings->isSubmoduleTreeEnabledFor($moduleLabel, $submodule['label'], $scope->selectedInstituteId)
                ));

            return $this->success('Submodules loaded.', [
                'module_id' => $moduleId,
                'submodules' => $submodules,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** One ERP module's real connected data flow across all its submodules. */
    public function erpModuleGraph(Request $request, int $moduleId)
    {
        try {
            $scope = $this->scope($request);

            $moduleLabel = $this->erpModuleLabel($scope, $moduleId);

            if ($moduleLabel !== null && ! $this->knowledgeGraphSettings->isModuleTreeEnabledFor($moduleLabel, $scope->selectedInstituteId)) {
                return $this->failure('Knowledge Graph is turned off for this module.', 403);
            }

            $validated = $request->validate([
                'sample_size' => 'nullable|integer|min:1|max:100',
                'limit' => 'nullable|integer|min:1|max:1000',
            ]);

            $result = $this->browser->graphForModule(
                $scope,
                $moduleId,
                $validated['sample_size'] ?? 8,
                $validated['limit'] ?? 300,
                $this->restrictedStudentIds($scope),
            );

            if ($moduleLabel !== null) {
                $result = $this->withoutDisabledNodeTypes($result, $scope, $moduleLabel, null);
            }

            return $this->success('Graph loaded.', $result);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** One submodule's real connected data flow. */
    public function erpSubmoduleGraph(Request $request, int $submoduleId)
    {
        try {
            $scope = $this->scope($request);

            $labels = $this->browser->moduleAndSubmoduleLabels($scope, $submoduleId);

            if ($labels !== null && ! $this->knowledgeGraphSettings->isSubmoduleTreeEnabledFor($labels['module'], $labels['submodule'], $scope->selectedInstituteId)) {
                return $this->failure('Knowledge Graph is turned off for this submodule.', 403);
            }

            $validated = $request->validate([
                'sample_size' => 'nullable|integer|min:1|max:100',
                'limit' => 'nullable|integer|min:1|max:1000',
            ]);

            $result = $this->browser->graphForSubmodule(
                $scope,
                $submoduleId,
                $validated['sample_size'] ?? 12,
                $validated['limit'] ?? 300,
                $this->restrictedStudentIds($scope),
            );

            if ($labels !== null) {
                $result = $this->withoutDisabledNodeTypes($result, $scope, $labels['module'], $labels['submodule']);
            }

            return $this->success('Graph loaded.', $result);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Cross-label text search — the rebuilt explorer's global search box. */
    /**
     * Cross-label search — a non-empty `q` searches every (or `labels`-
     * scoped) matching node by name; an empty/absent `q` with `labels`
     * given instead returns a few real sample nodes of those labels, no
     * text filter at all — the "pick a starting point for this submodule"
     * case the contextual-drill-down explorer uses instead of a flat
     * cross-label sample.
     */
    public function search(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'q' => 'nullable|string|max:200',
                'limit' => 'nullable|integer|min:1|max:100',
                'labels' => 'nullable|array',
                'labels.*' => 'string',
            ]);

            $labels = array_values(array_filter(
                $validated['labels'] ?? [],
                fn ($label) => GraphSchema::knowsLabel($label)
            ));

            return $this->success('Search complete.', $this->browser->search(
                $validated['q'] ?? '',
                $scope->selectedInstituteId,
                $validated['limit'] ?? 30,
                $labels,
                $this->restrictedStudentIds($scope),
            ));
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Shortest path between two nodes. Tenant id always comes from the
     * JWT-derived scope, never from the request body — a caller names only
     * the two nodes and, optionally, a depth/relationship-type filter.
     */
    public function path(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'from_label' => 'required|string',
                'from_id' => 'required|numeric',
                'to_label' => 'required|string',
                'to_id' => 'required|numeric',
                'max_depth' => 'nullable|integer|min:1|max:6',
                'relationship_types' => 'nullable|array',
                'relationship_types.*' => 'string',
            ]);

            return $this->success('Path search complete.', $this->browser->findPath(
                $validated['from_label'],
                (string) $validated['from_id'],
                $validated['to_label'],
                (string) $validated['to_id'],
                $scope->selectedInstituteId,
                $validated['max_depth'] ?? 6,
                $validated['relationship_types'] ?? [],
                $this->restrictedStudentIds($scope),
            ));
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * One node's full properties plus a paginated page of its own
     * relationships. `relation`/`direction`, when given, fetch just that
     * one relationship-group — the progressive explorer's "expand this one
     * branch" action, instead of the node's whole combined relationship list.
     */
    public function nodeDetail(Request $request, string $label, string $id)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'relationship_limit' => 'nullable|integer|min:1|max:100',
                'skip' => 'nullable|integer|min:0',
                // Not re-validated against the whitelist here — nodeDetail()
                // itself calls GraphSchema::assertRelationship(), the same
                // guard every other method already relies on; an unknown
                // value is rejected there and surfaces as a normal error.
                'relation' => 'nullable|string|max:100',
                'direction' => 'nullable|in:incoming,outgoing',
            ]);

            return $this->success('Node detail loaded.', $this->browser->nodeDetail(
                $label,
                $id,
                $scope->selectedInstituteId,
                $validated['relationship_limit'] ?? 50,
                $validated['skip'] ?? 0,
                $validated['relation'] ?? null,
                $validated['direction'] ?? null,
                $this->restrictedStudentIds($scope),
            ));
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Organization Management's own Institute/Department/Role/Staff graph — PR #988's data, GraphModuleBrowser-mediated. */
    public function organizationStaff(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->knowledgeGraphSettings->isEnabledFor(self::ORGANIZATION_MANAGEMENT_MODULE, $scope->selectedInstituteId)) {
                return $this->failure('Knowledge Graph is turned off for Organization Management.', 403);
            }

            $validated = $request->validate([
                'per_role' => 'nullable|integer|min:5|max:100',
                'subjects' => 'nullable|boolean',
            ]);

            return $this->success('Organization staff graph loaded.', $this->browser->organizationStaffGraph(
                $scope->selectedInstituteId,
                $validated['per_role'] ?? 25,
                $request->boolean('subjects'),
            ));
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
