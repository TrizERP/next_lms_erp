<?php

namespace App\Domain\KnowledgeGraph;

use App\Domain\AI\Support\SchemaCache;
use App\Domain\AI\Templates\TemplateModuleCatalog;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Whether the Knowledge Graph is switched on for a module — read and written in
 * `kg_module_settings` — resolved the same way `ModuleModelBindings` resolves a
 * module's model choice: this school's own row for this module, then this school's
 * wildcard row, then the platform's row for this module, then the platform's own
 * wildcard, then a hard default.
 *
 * WHAT THIS IS FOR
 *
 * Centralized management, decentralized execution: an administrator flips one
 * switch here, and every surface that renders a module's Knowledge Graph —
 * `WorkspaceController`'s decentralized module views, the tab-visibility flag a
 * product reads before rendering, the Organization Management staff graph — asks
 * THIS class rather than keeping an enabled flag of its own. A surface that built
 * its own check would be a surface the settings screen could not govern.
 *
 * NON-BREAKING BY DEFAULT
 *
 * No row anywhere resolves to `true`. Turning this feature on for an estate that
 * has never saved a row changes nothing for any module — the table only ever
 * takes something away, never grants something that was not already there.
 */
class KnowledgeGraphSettingsResolver
{
    public const TABLE = 'kg_module_settings';

    /**
     * Rows per institute scope, keyed by the institute (or '' for platform-only reads).
     *
     * @var array<string, array<int, object>|null>
     */
    private array $cache = [];

    public function __construct(
        private readonly SchemaCache $schema,
        private readonly TemplateModuleCatalog $modules,
    ) {
    }

    /** Whether this estate has the table at all. An older deployment simply has no overrides. */
    public function available(): bool
    {
        return $this->schema->hasTable(self::TABLE);
    }

    /**
     * Whether the Knowledge Graph is on for one module, in one school.
     *
     * `null` is a real value for `$moduleKey`: a caller asking about the feature in
     * general (no module resolved yet) only ever matches the wildcard rows.
     */
    public function isEnabledFor(?string $moduleKey, int|string|null $subInstituteId): bool
    {
        $rows = $this->rows($subInstituteId);

        if ($rows === null || $rows === []) {
            return true;
        }

        $institute = $this->normaliseInstitute($subInstituteId);
        $moduleKey = $moduleKey !== null && $moduleKey !== '' ? $moduleKey : null;

        $attempts = [];

        if ($institute !== null && $moduleKey !== null) {
            $attempts[] = static fn (object $r) => (string) ($r->sub_institute_id ?? '') === $institute
                && (string) ($r->module_key ?? '') === $moduleKey;
        }

        if ($institute !== null) {
            $attempts[] = static fn (object $r) => (string) ($r->sub_institute_id ?? '') === $institute
                && ($r->module_key ?? null) === null;
        }

        if ($moduleKey !== null) {
            $attempts[] = static fn (object $r) => ($r->sub_institute_id ?? null) === null
                && (string) ($r->module_key ?? '') === $moduleKey;
        }

        $attempts[] = static fn (object $r) => ($r->sub_institute_id ?? null) === null
            && ($r->module_key ?? null) === null;

        foreach ($attempts as $matches) {
            foreach ($rows as $row) {
                if ($matches($row)) {
                    return (bool) $row->enabled;
                }
            }
        }

        return true;
    }

    /**
     * `isEnabledFor()` for every module this school has, in one pass — what the
     * decentralized bulk read (and `WorkspaceController::context()`) call rather
     * than resolving one module at a time.
     *
     * @return array<string, bool>
     */
    public function resolvedMap(int|string|null $subInstituteId): array
    {
        $map = [];

        foreach ($this->modules->keys($subInstituteId) as $key) {
            $map[$key] = $this->isEnabledFor($key, $subInstituteId);
        }

        return $map;
    }

    /**
     * Every module this school has, resolved, with which level of the chain
     * decided it — the admin screen's list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function overview(int|string|null $subInstituteId): array
    {
        $out = [];

        foreach ($this->modules->all($subInstituteId) as $module) {
            if ($module['key'] === TemplateModuleCatalog::SHARED) {
                continue;
            }

            [$enabled, $source] = $this->resolveWithSource($module['key'], $subInstituteId);

            $out[] = [
                'module' => $module['key'],
                'module_label' => $module['label'],
                'enabled' => $enabled,
                'source' => $source,
            ];
        }

        return [
            'modules' => $out,
            // The two wildcard rows themselves, so the screen can show and edit
            // "every module in this school" / "every module on the platform"
            // directly rather than inferring them from the per-module list.
            'institute_default' => $this->wildcardRow($subInstituteId),
            'platform_default' => $this->wildcardRow(null),
        ];
    }

    /** @return array{0: bool, 1: string} */
    private function resolveWithSource(string $moduleKey, int|string|null $subInstituteId): array
    {
        $rows = $this->rows($subInstituteId);

        if ($rows === null || $rows === []) {
            return [true, 'default'];
        }

        $institute = $this->normaliseInstitute($subInstituteId);

        $levels = [
            'institute_module' => static fn (object $r) => $institute !== null
                && (string) ($r->sub_institute_id ?? '') === $institute
                && (string) ($r->module_key ?? '') === $moduleKey,
            'institute_default' => static fn (object $r) => $institute !== null
                && (string) ($r->sub_institute_id ?? '') === $institute
                && ($r->module_key ?? null) === null,
            'platform_module' => static fn (object $r) => ($r->sub_institute_id ?? null) === null
                && (string) ($r->module_key ?? '') === $moduleKey,
            'platform_default' => static fn (object $r) => ($r->sub_institute_id ?? null) === null
                && ($r->module_key ?? null) === null,
        ];

        foreach ($levels as $source => $matches) {
            foreach ($rows as $row) {
                if ($matches($row)) {
                    return [(bool) $row->enabled, $source];
                }
            }
        }

        return [true, 'default'];
    }

    /** The wildcard (module_key = null) row for one scope, as the screen shows it, or null when unset. */
    private function wildcardRow(int|string|null $subInstituteId): ?array
    {
        $rows = $this->rows($subInstituteId) ?? [];
        $institute = $this->normaliseInstitute($subInstituteId);

        foreach ($rows as $row) {
            $rowInstitute = ($row->sub_institute_id ?? null) === null ? null : (string) $row->sub_institute_id;

            if ($rowInstitute === $institute && ($row->module_key ?? null) === null) {
                return [
                    'enabled' => (bool) $row->enabled,
                    'updated_at' => $row->updated_at ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Save one scope's choice — a module's, a school's wildcard, the platform's
     * module default, or the platform's own wildcard — as an upsert.
     *
     * A `$subInstituteId` of `null` is the platform default and is the central
     * console's to set; a module's own screen only ever saves with its own
     * institute. Enforcing that distinction is the caller's responsibility (the
     * same split `AiModuleModelController` and `ModuleModelBindings::save()` draw),
     * not this method's — it saves exactly the scope it is given.
     */
    public function save(
        ?string $moduleKey,
        int|string|null $subInstituteId,
        bool $enabled,
        int|string|null $actorId = null
    ): ?object {
        if (! $this->available()) {
            return null;
        }

        $institute = $this->normaliseInstitute($subInstituteId);
        $moduleKey = $moduleKey !== null && $moduleKey !== '' ? $moduleKey : null;

        $existing = DB::table(self::TABLE)
            ->where(fn ($q) => $moduleKey === null ? $q->whereNull('module_key') : $q->where('module_key', $moduleKey))
            ->where(fn ($q) => $institute === null ? $q->whereNull('sub_institute_id') : $q->where('sub_institute_id', $institute))
            ->first();

        $payload = [
            'enabled' => $enabled ? 1 : 0,
            'updated_by' => $actorId === null ? null : (int) $actorId,
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            DB::table(self::TABLE)->where('id', $existing->id)->update($payload);
        } else {
            DB::table(self::TABLE)->insert($payload + [
                'module_key' => $moduleKey,
                'sub_institute_id' => $institute === null ? null : (int) $institute,
                'created_by' => $actorId === null ? null : (int) $actorId,
                'created_at' => now(),
            ]);
        }

        $this->flush();

        return DB::table(self::TABLE)
            ->where(fn ($q) => $moduleKey === null ? $q->whereNull('module_key') : $q->where('module_key', $moduleKey))
            ->where(fn ($q) => $institute === null ? $q->whereNull('sub_institute_id') : $q->where('sub_institute_id', $institute))
            ->first();
    }

    /** Remove one scope's override, returning that scope to whatever the rest of the chain decides. */
    public function clear(?string $moduleKey, int|string|null $subInstituteId): bool
    {
        if (! $this->available()) {
            return false;
        }

        $institute = $this->normaliseInstitute($subInstituteId);
        $moduleKey = $moduleKey !== null && $moduleKey !== '' ? $moduleKey : null;

        $deleted = DB::table(self::TABLE)
            ->where(fn ($q) => $moduleKey === null ? $q->whereNull('module_key') : $q->where('module_key', $moduleKey))
            ->where(fn ($q) => $institute === null ? $q->whereNull('sub_institute_id') : $q->where('sub_institute_id', $institute))
            ->delete();

        $this->flush();

        return $deleted > 0;
    }

    /** Drop the per-request cache after a write, so the next read sees the new row. */
    public function flush(): void
    {
        $this->cache = [];
    }

    // ---------------------------------------------------------------------
    // The Explorer's own browse tree — a different concern, a different table.
    //
    // `kg_module_settings` decides whether a PRODUCT module's Knowledge Graph
    // tab renders at all. These methods decide whether a branch of the
    // Centralized Explorer's own tree (GraphModuleBrowser::erpModules()/
    // erpSubmodules() — tblmenumaster labels, not ai_modules keys) is
    // browsable at all. See the migration for why these are separate tables.
    // ---------------------------------------------------------------------

    public const TREE_TABLE = 'kg_explorer_tree_settings';

    /** @var array<string, array<int, object>|null> */
    private array $treeCache = [];

    public function treeAvailable(): bool
    {
        return $this->schema->hasTable(self::TREE_TABLE);
    }

    /**
     * Whether this estate (its own rows, or the platform's) has ever saved an
     * Explorer-tree override at all — the cheap check a caller that would
     * otherwise have to walk the whole module/submodule/node-type tree (to
     * find every label with no enabled placement left anywhere) can run
     * first to skip that walk entirely. Matches this class's own
     * non-breaking-by-default rule: no rows anywhere means nothing is hidden,
     * so there is nothing to compute.
     */
    public function hasTreeOverrides(int|string|null $subInstituteId): bool
    {
        $rows = $this->treeRows($subInstituteId);

        return $rows !== null && $rows !== [];
    }

    /** Whether one of the Explorer's five ERP modules is browsable at all, in one school. */
    public function isModuleTreeEnabledFor(string $moduleLabel, int|string|null $subInstituteId): bool
    {
        $rows = $this->treeRows($subInstituteId);

        if ($rows === null || $rows === []) {
            return true;
        }

        $institute = $this->normaliseInstitute($subInstituteId);

        foreach ([
            static fn (object $r) => $institute !== null && (string) ($r->sub_institute_id ?? '') === $institute
                && $r->submodule_label === null && (string) $r->module_label === $moduleLabel,
            static fn (object $r) => ($r->sub_institute_id ?? null) === null
                && $r->submodule_label === null && (string) $r->module_label === $moduleLabel,
        ] as $matches) {
            foreach ($rows as $row) {
                if ($matches($row)) {
                    return (bool) $row->enabled;
                }
            }
        }

        return true;
    }

    /**
     * Whether one submodule within one of the Explorer's modules is browsable.
     *
     * The module's own switch is the parent gate: a module switched off takes
     * every submodule under it with it, whatever that submodule's own row says.
     */
    public function isSubmoduleTreeEnabledFor(string $moduleLabel, string $submoduleLabel, int|string|null $subInstituteId): bool
    {
        if (! $this->isModuleTreeEnabledFor($moduleLabel, $subInstituteId)) {
            return false;
        }

        $rows = $this->treeRows($subInstituteId);

        if ($rows === null || $rows === []) {
            return true;
        }

        $institute = $this->normaliseInstitute($subInstituteId);

        foreach ([
            static fn (object $r) => $institute !== null && (string) ($r->sub_institute_id ?? '') === $institute
                && (string) ($r->submodule_label ?? '') === $submoduleLabel && ($r->node_type ?? null) === null
                && (string) $r->module_label === $moduleLabel,
            static fn (object $r) => ($r->sub_institute_id ?? null) === null
                && (string) ($r->submodule_label ?? '') === $submoduleLabel && ($r->node_type ?? null) === null
                && (string) $r->module_label === $moduleLabel,
        ] as $matches) {
            foreach ($rows as $row) {
                if ($matches($row)) {
                    return (bool) $row->enabled;
                }
            }
        }

        return true;
    }

    /**
     * Whether one node type (a GraphSchema label) within one submodule is
     * shown. The submodule's own switch is the parent gate, which already
     * chains up through the module's — a module or submodule switched off
     * takes every node type under it with it, whatever its own row says.
     */
    public function isNodeTypeTreeEnabledFor(string $moduleLabel, string $submoduleLabel, string $nodeType, int|string|null $subInstituteId): bool
    {
        if (! $this->isSubmoduleTreeEnabledFor($moduleLabel, $submoduleLabel, $subInstituteId)) {
            return false;
        }

        $rows = $this->treeRows($subInstituteId);

        if ($rows === null || $rows === []) {
            return true;
        }

        $institute = $this->normaliseInstitute($subInstituteId);

        foreach ([
            static fn (object $r) => $institute !== null && (string) ($r->sub_institute_id ?? '') === $institute
                && (string) ($r->node_type ?? '') === $nodeType && (string) ($r->submodule_label ?? '') === $submoduleLabel
                && (string) $r->module_label === $moduleLabel,
            static fn (object $r) => ($r->sub_institute_id ?? null) === null
                && (string) ($r->node_type ?? '') === $nodeType && (string) ($r->submodule_label ?? '') === $submoduleLabel
                && (string) $r->module_label === $moduleLabel,
        ] as $matches) {
            foreach ($rows as $row) {
                if ($matches($row)) {
                    return (bool) $row->enabled;
                }
            }
        }

        return true;
    }

    /** Save a module's own switch, one submodule's, or (with both given) one node type's, as an upsert. */
    private function saveTreeRow(
        string $moduleLabel,
        ?string $submoduleLabel,
        ?string $nodeType,
        int|string|null $subInstituteId,
        bool $enabled,
        int|string|null $actorId
    ): ?object {
        if (! $this->treeAvailable()) {
            return null;
        }

        $institute = $this->normaliseInstitute($subInstituteId);
        $submoduleLabel = $submoduleLabel !== null && $submoduleLabel !== '' ? $submoduleLabel : null;
        $nodeType = $nodeType !== null && $nodeType !== '' ? $nodeType : null;

        $scopeQuery = fn ($query) => $query
            ->where('module_label', $moduleLabel)
            ->where(fn ($q) => $submoduleLabel === null ? $q->whereNull('submodule_label') : $q->where('submodule_label', $submoduleLabel))
            ->where(fn ($q) => $nodeType === null ? $q->whereNull('node_type') : $q->where('node_type', $nodeType))
            ->where(fn ($q) => $institute === null ? $q->whereNull('sub_institute_id') : $q->where('sub_institute_id', $institute));

        $existing = $scopeQuery(DB::table(self::TREE_TABLE))->first();

        $payload = [
            'enabled' => $enabled ? 1 : 0,
            'updated_by' => $actorId === null ? null : (int) $actorId,
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            DB::table(self::TREE_TABLE)->where('id', $existing->id)->update($payload);
        } else {
            DB::table(self::TREE_TABLE)->insert($payload + [
                'module_label' => $moduleLabel,
                'submodule_label' => $submoduleLabel,
                'node_type' => $nodeType,
                'sub_institute_id' => $institute === null ? null : (int) $institute,
                'created_by' => $actorId === null ? null : (int) $actorId,
                'created_at' => now(),
            ]);
        }

        $this->flushTree();

        return $scopeQuery(DB::table(self::TREE_TABLE))->first();
    }

    public function saveModuleTree(string $moduleLabel, int|string|null $subInstituteId, bool $enabled, int|string|null $actorId = null): ?object
    {
        return $this->saveTreeRow($moduleLabel, null, null, $subInstituteId, $enabled, $actorId);
    }

    public function saveSubmoduleTree(string $moduleLabel, string $submoduleLabel, int|string|null $subInstituteId, bool $enabled, int|string|null $actorId = null): ?object
    {
        return $this->saveTreeRow($moduleLabel, $submoduleLabel, null, $subInstituteId, $enabled, $actorId);
    }

    public function saveNodeTypeTree(string $moduleLabel, string $submoduleLabel, string $nodeType, int|string|null $subInstituteId, bool $enabled, int|string|null $actorId = null): ?object
    {
        return $this->saveTreeRow($moduleLabel, $submoduleLabel, $nodeType, $subInstituteId, $enabled, $actorId);
    }

    /** Remove one scope's override for a module, one submodule, or one node type, returning that scope to whatever the chain decides. */
    private function clearTreeRow(string $moduleLabel, ?string $submoduleLabel, ?string $nodeType, int|string|null $subInstituteId): bool
    {
        if (! $this->treeAvailable()) {
            return false;
        }

        $institute = $this->normaliseInstitute($subInstituteId);
        $submoduleLabel = $submoduleLabel !== null && $submoduleLabel !== '' ? $submoduleLabel : null;
        $nodeType = $nodeType !== null && $nodeType !== '' ? $nodeType : null;

        $deleted = DB::table(self::TREE_TABLE)
            ->where('module_label', $moduleLabel)
            ->where(fn ($q) => $submoduleLabel === null ? $q->whereNull('submodule_label') : $q->where('submodule_label', $submoduleLabel))
            ->where(fn ($q) => $nodeType === null ? $q->whereNull('node_type') : $q->where('node_type', $nodeType))
            ->where(fn ($q) => $institute === null ? $q->whereNull('sub_institute_id') : $q->where('sub_institute_id', $institute))
            ->delete();

        $this->flushTree();

        return $deleted > 0;
    }

    public function clearModuleTree(string $moduleLabel, int|string|null $subInstituteId): bool
    {
        return $this->clearTreeRow($moduleLabel, null, null, $subInstituteId);
    }

    public function clearSubmoduleTree(string $moduleLabel, string $submoduleLabel, int|string|null $subInstituteId): bool
    {
        return $this->clearTreeRow($moduleLabel, $submoduleLabel, null, $subInstituteId);
    }

    public function clearNodeTypeTree(string $moduleLabel, string $submoduleLabel, string $nodeType, int|string|null $subInstituteId): bool
    {
        return $this->clearTreeRow($moduleLabel, $submoduleLabel, $nodeType, $subInstituteId);
    }

    public function flushTree(): void
    {
        $this->treeCache = [];
    }

    /** @return array<int, object>|null */
    private function treeRows(int|string|null $subInstituteId): ?array
    {
        if (! $this->treeAvailable()) {
            return [];
        }

        $institute = $this->normaliseInstitute($subInstituteId);
        $cacheKey = $institute ?? '';

        if (array_key_exists($cacheKey, $this->treeCache)) {
            return $this->treeCache[$cacheKey];
        }

        try {
            $rows = DB::table(self::TREE_TABLE)
                ->where(function ($inner) use ($institute) {
                    $inner->whereNull('sub_institute_id');

                    if ($institute !== null) {
                        $inner->orWhere('sub_institute_id', $institute);
                    }
                })
                ->orderByDesc('id')
                ->get()
                ->all();
        } catch (Throwable) {
            return $this->treeCache[$cacheKey] = null;
        }

        return $this->treeCache[$cacheKey] = $rows;
    }

    /**
     * Every active row this institute can see — its own plus the platform's.
     *
     * Null, distinct from an empty list, when the table could not be read.
     *
     * @return array<int, object>|null
     */
    private function rows(int|string|null $subInstituteId): ?array
    {
        if (! $this->available()) {
            return [];
        }

        $institute = $this->normaliseInstitute($subInstituteId);
        $cacheKey = $institute ?? '';

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        try {
            $rows = DB::table(self::TABLE)
                ->where(function ($inner) use ($institute) {
                    $inner->whereNull('sub_institute_id');

                    if ($institute !== null) {
                        $inner->orWhere('sub_institute_id', $institute);
                    }
                })
                ->orderByDesc('id')
                ->get()
                ->all();
        } catch (Throwable) {
            return $this->cache[$cacheKey] = null;
        }

        return $this->cache[$cacheKey] = $rows;
    }

    private function normaliseInstitute(int|string|null $subInstituteId): ?string
    {
        $institute = $subInstituteId === null ? null : trim((string) $subInstituteId);

        return $institute === '' ? null : $institute;
    }
}
