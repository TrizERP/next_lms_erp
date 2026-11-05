<?php

namespace App\Http\Controllers\AI;

use App\Domain\KnowledgeGraph\GraphModuleBrowser;
use App\Services\Graph\GraphSchema;
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
    public function __construct(private readonly GraphModuleBrowser $browser)
    {
    }

    public function modules(Request $request)
    {
        try {
            $scope = $this->scope($request);

            return $this->success('Modules loaded.', ['modules' => $this->browser->modules($scope)]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function submodules(Request $request, string $family)
    {
        try {
            $scope = $this->scope($request);

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

            return $this->success('Graph loaded.', $this->browser->graph(
                $label,
                $scope->selectedInstituteId,
                $validated['sample_size'] ?? 20,
                $validated['limit'] ?? 250,
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

            return $this->success('Modules loaded.', ['modules' => $this->browser->erpModules($scope)]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** One module's real submodules — tblmenumaster level-2. */
    public function erpSubmodules(Request $request, int $moduleId)
    {
        try {
            $scope = $this->scope($request);

            return $this->success('Submodules loaded.', [
                'module_id' => $moduleId,
                'submodules' => $this->browser->erpSubmodules($scope, $moduleId),
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

            $validated = $request->validate([
                'sample_size' => 'nullable|integer|min:1|max:100',
                'limit' => 'nullable|integer|min:1|max:1000',
            ]);

            return $this->success('Graph loaded.', $this->browser->graphForModule(
                $scope,
                $moduleId,
                $validated['sample_size'] ?? 8,
                $validated['limit'] ?? 300,
            ));
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** One submodule's real connected data flow. */
    public function erpSubmoduleGraph(Request $request, int $submoduleId)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'sample_size' => 'nullable|integer|min:1|max:100',
                'limit' => 'nullable|integer|min:1|max:1000',
            ]);

            return $this->success('Graph loaded.', $this->browser->graphForSubmodule(
                $scope,
                $submoduleId,
                $validated['sample_size'] ?? 12,
                $validated['limit'] ?? 300,
            ));
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
