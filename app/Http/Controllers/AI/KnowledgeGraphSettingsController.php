<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Templates\TemplateModuleCatalog;
use App\Domain\KnowledgeGraph\GraphModuleBrowser;
use App\Domain\KnowledgeGraph\KnowledgeGraphSettingsResolver;
use App\Services\Mcp\AdministratorCheck;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\Request;
use Throwable;

/**
 * Centralized management for the Knowledge Graph: one screen that turns the
 * feature on or off per module, per school, or for the whole platform.
 *
 * `index`/`update`/`destroy` are the admin screen, gated by `AdministratorCheck`
 * rather than the route-level `admin.only` middleware. `admin.only` trusts
 * `McpRequestContext::$isAdmin`, which mirrors `tbluser.is_admin` — empty for
 * every real administrator in this estate (see `AdministratorCheck`'s own
 * docblock). That gate is deliberately left untouched so fixing it here cannot
 * widen any other tool's access; this controller opts in to the correct check
 * instead, the same way `GraphBrowserController` already does. `resolved` is the
 * decentralized read every product/module calls instead of keeping a Knowledge
 * Graph setting of its own, the same role `ConversationalSettingsController::getSettings()`
 * plays for the voice toggle and `WorkspaceController::context()`'s `channel_settings` field.
 *
 * WHAT THIS SCREEN CAN AND CANNOT WRITE
 *
 * `McpRequestContext::$selectedInstituteId` is never null — every caller through
 * this API is scoped to one real school — so, exactly like
 * `AiModuleModelController`/`ModuleModelBindings::save()`, every write here targets
 * the caller's own institute: a module-specific override, or (when `module_key` is
 * left out) an institute-wide override covering every module this school has. The
 * platform-wide default and the platform's own per-module rows — the two lowest
 * rungs `KnowledgeGraphSettingsResolver::isEnabledFor()` still falls back through —
 * are the estate's shared baseline and are set directly in `kg_module_settings`
 * (seeded, the same way `ai_api_keys`'s own platform rows arrive), not through this
 * per-school screen.
 *
 * ONE CONCEPT, TWO TABLES — KEPT IN SYNC AT WRITE TIME
 *
 * "Fees" is both a product module (`ai_modules.module_key = 'fees'`, governs the
 * decentralized tab) and an Explorer submodule (`kg_explorer_tree_settings`,
 * governs the Centralized Explorer's own tree). An administrator switching
 * either one off means it off everywhere, so `update()`/`destroy()` also mirror
 * onto any Explorer submodule whose label canonically matches the product
 * module key, and `updateTree()`/`destroyTree()` mirror back the other way —
 * see `matchingProductModuleKey()`/`matchingTreeSubmodules()`. Every READ stays
 * exactly as simple as it was: it is the two tables that are kept consistent,
 * not the resolution logic.
 */
class KnowledgeGraphSettingsController extends AiController
{
    public function __construct(
        private readonly KnowledgeGraphSettingsResolver $settings,
        private readonly TemplateModuleCatalog $modules,
        private readonly AdministratorCheck $administrators,
        private readonly GraphModuleBrowser $browser,
    ) {
    }

    /** Every module this school has, resolved, plus the two wildcard rows. */
    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->administrators->isAdministrator($scope)) {
                return $this->failure('You are not authorized to access this resource.', 403);
            }

            $overview = $this->settings->overview($scope->selectedInstituteId);

            // Cross-checked against any matching Explorer submodule, the same
            // way tree() checks back against this table — so a module this
            // screen has never saved itself still shows as disabled when a
            // matching submodule was switched off in the Explorer tree. The
            // tree is walked ONCE here (5 modules, not 60+) rather than once
            // per row — see buildCanonicalSubmoduleIndex()'s own docblock for
            // the N+1 this replaced.
            $submoduleIndex = $this->buildCanonicalSubmoduleIndex($scope);

            $overview['modules'] = array_map(function (array $row) use ($scope, $submoduleIndex) {
                if (! $row['enabled']) {
                    return $row;
                }

                $matches = $submoduleIndex[$this->canonicalKey($row['module'])] ?? [];

                foreach ($matches as [$moduleLabel, $submoduleLabel]) {
                    if (! $this->settings->isSubmoduleTreeEnabledFor($moduleLabel, $submoduleLabel, $scope->selectedInstituteId)) {
                        $row['enabled'] = false;
                        break;
                    }
                }

                return $row;
            }, $overview['modules']);

            return $this->success('Knowledge Graph settings resolved.', $overview);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Save this scope's choice for one module, or the wildcard when `module_key` is omitted. */
    public function update(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->administrators->isAdministrator($scope)) {
                return $this->failure('You are not authorized to access this resource.', 403);
            }

            $data = $request->validate([
                'module_key' => ['nullable', 'string', 'max:100'],
                'enabled' => ['required', 'boolean'],
            ]);

            $moduleKey = $data['module_key'] ?? null;

            if ($moduleKey !== null && ! $this->modules->exists($moduleKey, $scope->selectedInstituteId)) {
                return $this->failure('That is not a module this school has.', 422);
            }

            $saved = $this->settings->save($moduleKey, $scope->selectedInstituteId, (bool) $data['enabled'], $scope->userId);

            if ($saved === null) {
                return $this->failure('Knowledge Graph settings are not available on this estate.', 503);
            }

            if ($moduleKey !== null) {
                $this->mirrorToTree($scope, $moduleKey, (bool) $data['enabled']);
            }

            return $this->success(
                $moduleKey === null
                    ? 'This default now applies to every module without its own override.'
                    : 'This module will use this setting.',
                [
                    'module_key' => $saved->module_key,
                    'enabled' => (bool) $saved->enabled,
                    'scope' => $saved->sub_institute_id === null ? 'platform' : 'institute',
                    'updated_at' => $saved->updated_at,
                ]
            );
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Return this scope's choice for one module (or the wildcard) to whatever the rest of the chain decides. */
    public function destroy(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->administrators->isAdministrator($scope)) {
                return $this->failure('You are not authorized to access this resource.', 403);
            }

            $moduleKey = $request->query('module_key', $request->input('module_key'));
            $moduleKey = is_string($moduleKey) && trim($moduleKey) !== '' ? trim($moduleKey) : null;

            $cleared = $this->settings->clear($moduleKey, $scope->selectedInstituteId);

            if ($moduleKey !== null && $cleared) {
                $this->clearTreeMirror($scope, $moduleKey);
            }

            return $this->success(
                $cleared
                    ? 'That override was removed.'
                    : 'There was no override of this scope\'s own to remove.',
                [
                    'module_key' => $moduleKey,
                    'cleared' => $cleared,
                    'enabled' => $this->settings->isEnabledFor($moduleKey, $scope->selectedInstituteId),
                ]
            );
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The decentralized read: every module this school has, resolved to a plain
     * `module_key => enabled` map. No admin gate — this is what a module's own
     * screen (or the frontend's tab list) calls to decide whether to render its
     * Knowledge Graph without keeping a setting of its own.
     */
    public function resolved(Request $request)
    {
        try {
            $scope = $this->scope($request);

            return $this->success('Knowledge Graph settings resolved.', [
                'modules' => $this->settings->resolvedMap($scope->selectedInstituteId),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    // ------------------------------------------------------------------
    // The Explorer's own browse tree — module -> submodule -> node types.
    //
    // A different concern from index/update/destroy/resolved above: those
    // decide whether a PRODUCT module's tab renders at all; these decide
    // whether a branch of the Centralized Explorer's own tree
    // (GraphModuleBrowser::erpModules()/erpSubmodules()) is browsable, and
    // feed the same gate GraphBrowserController's erp* endpoints enforce.
    // ------------------------------------------------------------------

    /**
     * The Explorer's five ERP modules, each with its real submodules and the
     * GraphSchema node types ModuleGraphMapper resolved for them, each
     * resolved against this school's Knowledge Graph tree settings.
     */
    public function tree(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->administrators->isAdministrator($scope)) {
                return $this->failure('You are not authorized to access this resource.', 403);
            }

            $modules = [];

            foreach ($this->browser->erpModules($scope) as $module) {
                $moduleLabel = $module['label'];
                $moduleEnabled = $this->settings->isModuleTreeEnabledFor($moduleLabel, $scope->selectedInstituteId);

                $submodules = [];

                foreach ($this->browser->erpSubmodules($scope, $module['id']) as $submodule) {
                    $submoduleLabel = $submodule['label'];
                    $matchingKey = $this->matchingProductModuleKey($scope, $submoduleLabel);
                    $submoduleEnabled = $moduleEnabled
                        && $this->settings->isSubmoduleTreeEnabledFor($moduleLabel, $submoduleLabel, $scope->selectedInstituteId)
                        && ($matchingKey === null || $this->settings->isEnabledFor($matchingKey, $scope->selectedInstituteId));

                    $nodeTypes = [];
                    foreach ($submodule['labels'] as $nodeType) {
                        $nodeTypes[] = [
                            'label' => $nodeType,
                            'enabled' => $submoduleEnabled
                                && $this->settings->isNodeTypeTreeEnabledFor($moduleLabel, $submoduleLabel, $nodeType, $scope->selectedInstituteId),
                        ];
                    }

                    $submodules[] = [
                        'label' => $submoduleLabel,
                        'mapped' => $submodule['mapped'],
                        // The real GraphSchema node types ModuleGraphMapper
                        // resolved for this submodule, each individually
                        // switchable and parent-gated by this submodule's own
                        // state.
                        'node_types' => $nodeTypes,
                        // The matching product module's tab (if any), so a
                        // disabled one explains this row even before the next
                        // save mirrors the states back together.
                        'linked_module_key' => $matchingKey,
                        'enabled' => $submoduleEnabled,
                    ];
                }

                $modules[] = [
                    'label' => $moduleLabel,
                    'enabled' => $moduleEnabled,
                    'submodules' => $submodules,
                ];
            }

            return $this->success('Knowledge Graph explorer tree resolved.', ['modules' => $modules]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Save a module's own switch, one of its submodules' (when `submodule_label`
     * is given), or one node type within that submodule (when `node_type` is
     * also given).
     */
    public function updateTree(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->administrators->isAdministrator($scope)) {
                return $this->failure('You are not authorized to access this resource.', 403);
            }

            $data = $request->validate([
                'module_label' => ['required', 'string', 'max:150'],
                'submodule_label' => ['nullable', 'string', 'max:150'],
                'node_type' => ['nullable', 'string', 'max:150'],
                'enabled' => ['required', 'boolean'],
            ]);

            $moduleLabel = $data['module_label'];
            $submoduleLabel = $data['submodule_label'] ?? null;
            $nodeType = $submoduleLabel !== null ? ($data['node_type'] ?? null) : null;

            if (! $this->moduleLabelExists($scope, $moduleLabel)) {
                return $this->failure('That is not one of the Explorer\'s modules.', 422);
            }

            if ($submoduleLabel !== null && ! $this->submoduleLabelExists($scope, $moduleLabel, $submoduleLabel)) {
                return $this->failure('That is not one of this module\'s submodules.', 422);
            }

            if ($nodeType !== null && ! $this->nodeTypeExists($scope, $moduleLabel, $submoduleLabel, $nodeType)) {
                return $this->failure('That is not one of this submodule\'s node types.', 422);
            }

            $saved = match (true) {
                $nodeType !== null => $this->settings->saveNodeTypeTree($moduleLabel, $submoduleLabel, $nodeType, $scope->selectedInstituteId, (bool) $data['enabled'], $scope->userId),
                $submoduleLabel !== null => $this->settings->saveSubmoduleTree($moduleLabel, $submoduleLabel, $scope->selectedInstituteId, (bool) $data['enabled'], $scope->userId),
                default => $this->settings->saveModuleTree($moduleLabel, $scope->selectedInstituteId, (bool) $data['enabled'], $scope->userId),
            };

            if ($saved === null) {
                return $this->failure('Knowledge Graph explorer tree settings are not available on this estate.', 503);
            }

            // The product-module bridge applies to submodules, not individual
            // node types — a node type is finer-grained than anything
            // `ai_modules` describes, so there is nothing on the other side to mirror onto.
            if ($submoduleLabel !== null && $nodeType === null) {
                $matchingKey = $this->matchingProductModuleKey($scope, $submoduleLabel);

                if ($matchingKey !== null) {
                    $this->settings->save($matchingKey, $scope->selectedInstituteId, (bool) $data['enabled'], $scope->userId);
                }
            }

            return $this->success(
                match (true) {
                    $nodeType !== null => 'This node type will use this setting.',
                    $submoduleLabel !== null => 'This submodule will use this setting.',
                    default => 'This module will use this setting.',
                },
                [
                    'module_label' => $saved->module_label,
                    'submodule_label' => $saved->submodule_label,
                    'node_type' => $saved->node_type,
                    'enabled' => (bool) $saved->enabled,
                    'scope' => $saved->sub_institute_id === null ? 'platform' : 'institute',
                    'updated_at' => $saved->updated_at,
                ]
            );
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Return a module's, one submodule's, or one node type's switch to whatever the rest of the chain decides. */
    public function destroyTree(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->administrators->isAdministrator($scope)) {
                return $this->failure('You are not authorized to access this resource.', 403);
            }

            $moduleLabel = trim((string) $request->query('module_label', $request->input('module_label', '')));

            if ($moduleLabel === '') {
                return $this->failure('Name the module to clear.', 422);
            }

            $submoduleLabel = $request->query('submodule_label', $request->input('submodule_label'));
            $submoduleLabel = is_string($submoduleLabel) && trim($submoduleLabel) !== '' ? trim($submoduleLabel) : null;

            $nodeType = $request->query('node_type', $request->input('node_type'));
            $nodeType = $submoduleLabel !== null && is_string($nodeType) && trim($nodeType) !== '' ? trim($nodeType) : null;

            $cleared = match (true) {
                $nodeType !== null => $this->settings->clearNodeTypeTree($moduleLabel, $submoduleLabel, $nodeType, $scope->selectedInstituteId),
                $submoduleLabel !== null => $this->settings->clearSubmoduleTree($moduleLabel, $submoduleLabel, $scope->selectedInstituteId),
                default => $this->settings->clearModuleTree($moduleLabel, $scope->selectedInstituteId),
            };

            if ($submoduleLabel !== null && $nodeType === null && $cleared) {
                $matchingKey = $this->matchingProductModuleKey($scope, $submoduleLabel);

                if ($matchingKey !== null) {
                    $this->settings->clear($matchingKey, $scope->selectedInstituteId);
                }
            }

            $enabled = match (true) {
                $nodeType !== null => $this->settings->isNodeTypeTreeEnabledFor($moduleLabel, $submoduleLabel, $nodeType, $scope->selectedInstituteId),
                $submoduleLabel !== null => $this->settings->isSubmoduleTreeEnabledFor($moduleLabel, $submoduleLabel, $scope->selectedInstituteId),
                default => $this->settings->isModuleTreeEnabledFor($moduleLabel, $scope->selectedInstituteId),
            };

            return $this->success(
                $cleared ? 'That override was removed.' : 'There was no override of this scope\'s own to remove.',
                [
                    'module_label' => $moduleLabel,
                    'submodule_label' => $submoduleLabel,
                    'node_type' => $nodeType,
                    'cleared' => $cleared,
                    'enabled' => $enabled,
                ]
            );
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Every node label Neo4j actually holds right now, each with its live
     * count and whether GraphSchema recognises it — "what we actually know",
     * as distinct from `tree()`'s curated per-submodule subset. An unmapped
     * or unexpected label shows up here precisely because this does not
     * filter through ModuleGraphMapper at all.
     */
    public function liveInventory(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $this->administrators->isAdministrator($scope)) {
                return $this->failure('You are not authorized to access this resource.', 403);
            }

            return $this->success('Live Neo4j inventory resolved.', [
                'labels' => $this->browser->liveLabelInventory($scope->selectedInstituteId),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    private function moduleLabelExists(McpRequestContext $scope, string $moduleLabel): bool
    {
        foreach ($this->browser->erpModules($scope) as $module) {
            if ($module['label'] === $moduleLabel) {
                return true;
            }
        }

        return false;
    }

    private function submoduleLabelExists(McpRequestContext $scope, string $moduleLabel, string $submoduleLabel): bool
    {
        foreach ($this->browser->erpModules($scope) as $module) {
            if ($module['label'] !== $moduleLabel) {
                continue;
            }

            foreach ($this->browser->erpSubmodules($scope, $module['id']) as $submodule) {
                if ($submodule['label'] === $submoduleLabel) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Whether ModuleGraphMapper actually resolved this node type for this submodule. */
    private function nodeTypeExists(McpRequestContext $scope, string $moduleLabel, string $submoduleLabel, string $nodeType): bool
    {
        foreach ($this->browser->erpModules($scope) as $module) {
            if ($module['label'] !== $moduleLabel) {
                continue;
            }

            foreach ($this->browser->erpSubmodules($scope, $module['id']) as $submodule) {
                if ($submodule['label'] === $submoduleLabel) {
                    return in_array($nodeType, $submodule['labels'], true);
                }
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // The bridge: one concept, matched by name across the two vocabularies
    // (`ai_modules.module_key` slugs and Explorer submodule labels), kept in
    // sync by mirroring every save/clear onto whichever rows match.
    // ------------------------------------------------------------------

    /**
     * A comparable form for a label or a slug — lower-cased, spaces and
     * underscores folded to hyphens, anything else stripped — so "Fees" and
     * "fees" (or "Document Templates" and "document-templates") compare equal
     * without either side needing to spell the other's convention.
     */
    private function canonicalKey(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[\s_]+/', '-', $slug) ?? $slug;
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug) ?? $slug;

        return trim($slug, '-');
    }

    /** The `ai_modules.module_key` whose canonical form matches this Explorer submodule's label, or null. */
    private function matchingProductModuleKey(McpRequestContext $scope, string $submoduleLabel): ?string
    {
        $target = $this->canonicalKey($submoduleLabel);

        foreach ($this->modules->keys($scope->selectedInstituteId) as $key) {
            if ($this->canonicalKey($key) === $target) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Every Explorer (module_label, submodule_label) pair, keyed by the
     * submodule's canonical form — built from one walk of the tree.
     *
     * `index()` used to call `matchingTreeSubmodules()` once per product
     * module (60+ times), and each call walked the whole tree from scratch —
     * `erpModules()` plus an `erpSubmodules()` per module, none of it cached
     * across calls. That turned one settings-page load into hundreds of
     * `tblmenumaster` queries and was the reason the screen hung on
     * "Loading…". Building this index once and looking keys up in it is the
     * fix; `matchingTreeSubmodules()` below is now just one lookup into it.
     *
     * Walks the browser's own, unfiltered erpModules()/erpSubmodules() — not
     * GraphBrowserController's gated versions — so a submodule already
     * switched off is still found here and not silently skipped.
     *
     * @return array<string, array<int, array{0: string, 1: string}>>
     */
    private function buildCanonicalSubmoduleIndex(McpRequestContext $scope): array
    {
        $index = [];

        foreach ($this->browser->erpModules($scope) as $module) {
            foreach ($this->browser->erpSubmodules($scope, $module['id']) as $submodule) {
                $index[$this->canonicalKey($submodule['label'])][] = [$module['label'], $submodule['label']];
            }
        }

        return $index;
    }

    /**
     * Every Explorer (module_label, submodule_label) pair whose submodule
     * canonically matches this product module key.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function matchingTreeSubmodules(McpRequestContext $scope, string $moduleKey): array
    {
        return $this->buildCanonicalSubmoduleIndex($scope)[$this->canonicalKey($moduleKey)] ?? [];
    }

    /** Mirror a product module's save onto every Explorer submodule that names the same concept. */
    private function mirrorToTree(McpRequestContext $scope, string $moduleKey, bool $enabled): void
    {
        foreach ($this->matchingTreeSubmodules($scope, $moduleKey) as [$moduleLabel, $submoduleLabel]) {
            $this->settings->saveSubmoduleTree($moduleLabel, $submoduleLabel, $scope->selectedInstituteId, $enabled, $scope->userId);
        }
    }

    /** Mirror a product module's cleared override onto every Explorer submodule that names the same concept. */
    private function clearTreeMirror(McpRequestContext $scope, string $moduleKey): void
    {
        foreach ($this->matchingTreeSubmodules($scope, $moduleKey) as [$moduleLabel, $submoduleLabel]) {
            $this->settings->clearSubmoduleTree($moduleLabel, $submoduleLabel, $scope->selectedInstituteId);
        }
    }
}
