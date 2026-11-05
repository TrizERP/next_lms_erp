<?php

namespace App\Domain\KnowledgeGraph;

use App\Services\Graph\GraphSchema;
use App\Services\Graph\GraphVocabulary;
use App\Services\Mcp\McpRequestContext;
use App\Services\Neo4jService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Browses the live Neo4j graph directly for the centralized Knowledge Graph's
 * Modules -> Submodules -> Data flow navigation.
 *
 * Deliberately separate from GraphQueryService: that class is the SQL-aware
 * hybrid layer the decentralized per-module tabs read through (ontology
 * entities, SQL fallback, bounded path traversal). This class never falls
 * back to SQL and never touches ontology_entities/ontology_relationships —
 * every answer here is a real Cypher query against Neo4j, scoped by the same
 * GraphSchema/GraphVocabulary whitelists the live sync pipeline itself uses,
 * so a label can never be interpolated unless GraphSchema actually knows it.
 *
 * "Module" = one of GraphVocabulary's 10 label families. "Submodule" = one
 * GraphSchema label within that family. "Data flow" = a sample of that
 * label's real live nodes plus every relationship they actually carry, in
 * whichever direction and to whatever label Neo4j itself has them connected
 * to — nothing filtered by relationship type, nothing invented.
 */
class GraphModuleBrowser
{
    /** Hard ceilings for the new endpoints below — never relaxed by a client-supplied value, only tightened. */
    private const MAX_SEARCH_LIMIT = 100;
    private const MAX_PATH_DEPTH = 6;
    private const MAX_PATH_NODES = 60;
    private const MAX_RELATIONSHIP_LIMIT = 100;
    private const REFERENCE_PROPERTIES = [
        'standard_id' => ['label' => 'Standard', 'attribute' => 'standard'],
        'subject_id' => ['label' => 'Subject', 'attribute' => 'subject'],
        'chapter_id' => ['label' => 'Chapter', 'attribute' => 'chapter'],
        'topic_id' => ['label' => 'Topic', 'attribute' => 'topic'],
        'concept_id' => ['label' => 'Concept', 'attribute' => 'concept'],
        'curriculum_id' => ['label' => 'Curriculum', 'attribute' => 'curriculum'],
        'unit_id' => ['label' => 'Unit', 'attribute' => 'unit'],
    ];

    public function __construct(
        private readonly Neo4jService $neo4j,
        private readonly ModuleGraphMapper $mapper,
        private readonly MenuRightsResolver $rights,
    ) {
    }

    /**
     * The real ERP modules — tblmenumaster's level-1 rows, exactly as the
     * rest of the app's own navigation shows them (Institute ERP, LMS + PAL,
     * People & Competency...). This is the module list the Knowledge Graph's
     * "All Modules" screen shows; GraphVocabulary's 10 technical families are
     * a different, engineering-facing taxonomy and are not used for this
     * navigation.
     *
     * Filtered exactly the way the real sidebar is: tenant provisioning
     * (FIND_IN_SET against tblmenumaster.sub_institute_id/.client_id) AND
     * per-user/role rights (MenuRightsResolver). A caller with nothing
     * resolved gets an empty list, not every module.
     */
    public function erpModules(McpRequestContext $scope): array
    {
        $allowed = $this->rights->allowedMenuIds($scope);

        if ($allowed === null || $allowed === []) {
            return [];
        }

        return $this->tenantScopedMenus($scope, 1, null, $allowed)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'label' => $this->menuLabel($row),
                // The real count behind this module's own card — the stacked-card UI
                // draws one layer per submodule (capped visually), from this exact
                // number, not a guess. Same tenant+rights+menu_type filter and the
                // same label-dedup as erpSubmodules() itself, so the count on the
                // module card always matches what clicking into it actually shows.
                'submodule_count' => count($this->submodulesFor($scope, (int) $row->id, $allowed)),
            ])
            ->values()
            ->all();
    }

    /**
     * The real submodules of one ERP module — tblmenumaster's level-2 rows
     * under it — each flagged with whether ModuleGraphMapper found any real
     * GraphSchema labels for it, so the frontend can show "not mapped to the
     * graph yet" without a failed round trip. Same tenant + rights filter as
     * erpModules(); a module id outside this scope's allowed set simply
     * returns no submodules rather than leaking its children.
     */
    public function erpSubmodules(McpRequestContext $scope, int $moduleId): array
    {
        $allowed = $this->rights->allowedMenuIds($scope);

        if ($allowed === null || $allowed === [] || ! $this->rights->allows($allowed, $moduleId)) {
            return [];
        }

        return $this->submodulesFor($scope, $moduleId, $allowed);
    }

    /** @param int[] $allowed */
    private function submodulesFor(McpRequestContext $scope, int $moduleId, array $allowed): array
    {
        $rows = $this->tenantScopedMenus($scope, 2, $moduleId, $allowed)->get();

        $seen = [];
        $submodules = [];

        foreach ($rows as $row) {
            $label = $this->menuLabel($row);

            // tblmenumaster repeats the same label under one module (several
            // rows literally named "LMS") — one tile per distinct label, not
            // one per row, since the user picks by what they can read.
            if (isset($seen[$label])) {
                continue;
            }
            $seen[$label] = true;

            $labels = $this->mapper->labelsFor($label, $this->screenNames((int) $row->id));

            $submodules[] = [
                'id' => (int) $row->id,
                'label' => $label,
                'mapped' => $labels !== [],
                // The resolved GraphSchema labels themselves — so the frontend
                // can ask search() for "a few real starting points" scoped to
                // exactly this submodule, instead of defaulting to
                // graphForSubmodule()'s flat sample of everything at once.
                'labels' => $labels,
            ];
        }

        return $submodules;
    }

    /**
     * The real connected subgraph for an entire ERP module (all its submodules combined).
     */
    public function graphForModule(McpRequestContext $scope, int $moduleId, int $sampleSizePerLabel = 8, int $limit = 300): array
    {
        $allowed = $this->rights->allowedMenuIds($scope);

        if ($allowed === null || $allowed === [] || ! $this->rights->allows($allowed, $moduleId)) {
            return ['module_id' => $moduleId, 'labels' => [], 'nodes' => [], 'edges' => []];
        }

        $submodules = $this->submodulesFor($scope, $moduleId, $allowed);
        $allLabels = [];
        foreach ($submodules as $sub) {
            if (! empty($sub['labels'])) {
                $allLabels = array_merge($allLabels, $sub['labels']);
            }
        }
        $labels = array_values(array_unique($allLabels));

        if ($labels === []) {
            return ['module_id' => $moduleId, 'labels' => [], 'nodes' => [], 'edges' => []];
        }

        $result = $this->graphForLabels($labels, $scope->selectedInstituteId, $sampleSizePerLabel, $limit);

        return array_merge(['module_id' => $moduleId], $result);
    }

    /**
     * The real connected subgraph for one ERP submodule: its GraphSchema
     * labels (via ModuleGraphMapper), each sampled, plus every relationship
     * those nodes actually carry to each other or to anything else — the
     * same honesty rule as graph(): nothing invented, nothing filtered by
     * relationship type.
     *
     * Checked against tenant + rights before anything else — this is called
     * directly by its own route, so a submodule id the URL names but this
     * scope was never granted must be refused here too, not only hidden from
     * the nav that happened to generate the id.
     */
    public function graphForSubmodule(McpRequestContext $scope, int $submoduleId, int $sampleSizePerLabel = 12, int $limit = 300): array
    {
        $allowed = $this->rights->allowedMenuIds($scope);

        if ($allowed === null || ! $this->rights->allows($allowed, $submoduleId)) {
            return ['submodule' => null, 'labels' => [], 'nodes' => [], 'edges' => []];
        }

        $row = $this->tenantScopedMenus($scope, 2, null, $allowed)
            ->where('id', $submoduleId)
            ->first(['id', 'menu_title', 'name']);

        if (! $row) {
            return ['submodule' => null, 'labels' => [], 'nodes' => [], 'edges' => []];
        }

        $label = $this->menuLabel($row);
        $labels = $this->mapper->labelsFor($label, $this->screenNames($submoduleId));

        if ($labels === []) {
            return ['submodule' => $label, 'labels' => [], 'nodes' => [], 'edges' => []];
        }

        $result = $this->graphForLabels($labels, $scope->selectedInstituteId, $sampleSizePerLabel, $limit);

        return array_merge(['submodule' => $label], $result);
    }

    /**
     * tblmenumaster rows at one level, scoped to this tenant (FIND_IN_SET on
     * sub_institute_id, or client_id for a multi-tenant admin — same split
     * MenuRightsResolver uses), to the already-resolved rights-granted ids,
     * and to `menu_type != 'MASTER'` (or null) — the real sidebar
     * (MenuMiddleware::buildMenus()'s default branch, every level) excludes
     * `menu_type = 'MASTER'` rows from the ordinary module/menu list
     * entirely; those are a separate "Master Configuration" UI area, not
     * part of the main module nav. Without this, every "(Master)"-suffixed
     * level-1 row (Fees (Master), Hostel (Master), ...) showed up here even
     * though the real sidebar never shows them — verified against a live
     * user: removing this took the list from 16 modules down to exactly the
     * 6 their real sidebar shows.
     */
    private function tenantScopedMenus(McpRequestContext $scope, int $level, ?int $parentId, array $allowedIds)
    {
        $query = DB::table('tblmenumaster')
            ->where('level', $level)
            ->where('status', 1)
            ->whereIn('id', $allowedIds)
            ->whereRaw("(menu_type != 'MASTER' OR menu_type IS NULL)")
            ->orderBy('menu_sortorder');

        if ($parentId !== null) {
            $query->where('parent_menu_id', $parentId);
        }

        if ((int) $scope->selectedInstituteId === 0 && $scope->isAdmin) {
            $query->whereRaw('FIND_IN_SET(?, client_id)', [$scope->clientId]);
        } else {
            $query->whereRaw('FIND_IN_SET(?, sub_institute_id)', [$scope->selectedInstituteId]);
        }

        return $query->select(['id', 'menu_title', 'name']);
    }

    /**
     * A connected sample across several labels at once: a batch of real
     * nodes from EACH label, then every relationship those nodes actually
     * carry — the general form graph() specialises to one label.
     *
     * Expansion only follows edges that stay WITHIN this label set — not a
     * filter on relationship type (nothing is invented or hidden by name),
     * but on where the other end lands. Verified live this matters: a
     * :Standard seed has real :ENROLLED_IN edges to thousands of students
     * and real :HAS_SUBJECT edges to a handful of subjects, and with no
     * ordering Neo4j has no reason to prefer the latter — "LMS" came back as
     * 120+ Student nodes against a handful of real curriculum ones, from a
     * request that never asked for Student at all. Restricting the far end
     * to the submodule's own mapped labels is what keeps a module's view
     * about that module's own real structure (Standard->Subject->Chapter-
     * >Topic->Concept, exactly the shape graph() itself already shows one
     * label at a time) instead of wandering into an unrelated domain that
     * happens to share a hub node. graph() (single label, used by the old
     * family browser) deliberately does NOT apply this — there a node's
     * connections to a DIFFERENT label are the point.
     */
    public function graphForLabels(array $labels, ?int $tenantId, int $sampleSizePerLabel = 12, int $limit = 300, int $perNodeLimit = 15): array
    {
        $parts = [];

        foreach ($labels as $label) {
            GraphSchema::assertLabel($label);
            $parts[] = sprintf(
                'MATCH (n:%s) '
                . 'WHERE $tenantId IS NULL OR n.sub_institute_id IS NULL OR n.sub_institute_id = $tenantId '
                . 'WITH n LIMIT $sampleSizePerLabel RETURN n',
                $label
            );
        }

        $cypher = 'CALL { ' . implode(' UNION ', $parts) . ' } '
            . 'WITH n '
            . 'CALL { '
            . '  WITH n '
            . '  OPTIONAL MATCH (n)-[r]-(m) WHERE any(l IN labels(m) WHERE l IN $labels) '
            . '  RETURN r, m '
            . '  LIMIT $perNodeLimit '
            . '} '
            . 'RETURN id(n) AS nId, labels(n) AS nLabels, n AS nProps, '
            . '       id(r) AS rId, type(r) AS rType, startNode(r) = n AS rOutgoing, '
            . '       id(m) AS mId, labels(m) AS mLabels, m AS mProps '
            . 'LIMIT $limit';

        try {
            $result = $this->neo4j->run($cypher, [
                'tenantId' => $tenantId,
                'sampleSizePerLabel' => $sampleSizePerLabel,
                'perNodeLimit' => $perNodeLimit,
                'limit' => $limit,
                'labels' => $labels,
            ]);
        } catch (Throwable $exception) {
            return ['labels' => $labels, 'nodes' => [], 'edges' => [], 'error' => $exception->getMessage()];
        }

        if ($result === null) {
            return ['labels' => $labels, 'nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];

        foreach ($result as $row) {
            $this->collectNode($nodes, $row->get('nId'), $row->get('nLabels'), $row->get('nProps'));

            $rId = $row->get('rId');

            if ($rId === null) {
                continue;
            }

            $this->collectNode($nodes, $row->get('mId'), $row->get('mLabels'), $row->get('mProps'));

            $outgoing = (bool) $row->get('rOutgoing');
            $nId = (string) $row->get('nId');
            $mId = (string) $row->get('mId');

            $edges[(string) $rId] = [
                'id' => (string) $rId,
                'source' => $outgoing ? $nId : $mId,
                'target' => $outgoing ? $mId : $nId,
                'relation' => (string) $row->get('rType'),
            ];
        }

        return [
            'labels' => $labels,
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
        ];
    }

    /**
     * `name` holds the real, specific label on every row checked live
     * ("Teach/Learn", "PAL", "Add Content", "Chapter Master"); `menu_title`
     * is either null or a stale, generic placeholder repeated across several
     * otherwise-distinct rows (four different level-2 rows under LMS + PAL
     * all carry menu_title='LMS', with their real identity only in `name`).
     * Preferring menu_title (the original order here) collapsed all four
     * into one and showed the wrong one of them at that. `name` is empty on
     * level-1 rows in exactly the cases menu_title already held the right
     * value, so this order is correct for both levels.
     */
    private function menuLabel(object $row): string
    {
        return (string) ($row->name ?: $row->menu_title ?: ('#' . $row->id));
    }

    /** @return string[] */
    private function screenNames(int $level2MenuId): array
    {
        return DB::table('tblmenumaster')
            ->where('level', 3)
            ->where('parent_menu_id', $level2MenuId)
            ->where('status', 1)
            ->get(['menu_title', 'name'])
            ->map(fn ($row) => $this->menuLabel($row))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The ten GraphVocabulary families, each with how many labels it covers.
     *
     * Gated the same way every erp* method already is — a caller with
     * nothing resolved via MenuRightsResolver gets nothing here either,
     * closing the one gap this class had versus the erp* half of itself
     * (there is no per-family menu id to filter by, so this is an
     * authenticated-and-provisioned gate, not a per-family one).
     */
    public function modules(McpRequestContext $scope): array
    {
        $allowed = $this->rights->allowedMenuIds($scope);

        if ($allowed === null || $allowed === []) {
            return [];
        }

        $byFamily = [];

        foreach (GraphVocabulary::LABEL_FAMILY as $label => $family) {
            $byFamily[$family][] = $label;
        }

        $modules = [];

        foreach (GraphVocabulary::families() as $family) {
            $modules[] = [
                'key' => $family,
                'label' => $this->humanize($family),
                'label_count' => count($byFamily[$family] ?? []),
            ];
        }

        usort($modules, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $modules;
    }

    /** The labels in one family, each with a live Neo4j node count — same rights gate as modules(). */
    public function submodules(McpRequestContext $scope, string $family): array
    {
        $allowed = $this->rights->allowedMenuIds($scope);

        if ($allowed === null || $allowed === []) {
            return [];
        }

        $labels = array_keys(array_filter(
            GraphVocabulary::LABEL_FAMILY,
            fn ($f) => $f === $family
        ));

        if ($labels === []) {
            return [];
        }

        $counts = $this->liveCounts($labels, $scope->selectedInstituteId);

        $submodules = [];

        foreach ($labels as $label) {
            $submodules[] = [
                'key' => $label,
                'label' => $this->humanize($label),
                'node_count' => $counts[$label] ?? 0,
            ];
        }

        usort(
            $submodules,
            fn ($a, $b) => $b['node_count'] <=> $a['node_count'] ?: strcmp($a['label'], $b['label'])
        );

        return $submodules;
    }

    /**
     * The real connected subgraph for one label: a sample of its live nodes,
     * plus every relationship those nodes actually carry (any type, either
     * direction) to whatever node Neo4j has them connected to. Expansion is
     * capped per seed node ($perNodeLimit) — see graphForLabels()'s docblock
     * for why: a single hub node (e.g. a popular :Standard) can otherwise
     * fill the whole sample with one relationship type.
     */
    public function graph(string $label, ?int $tenantId, int $sampleSize = 20, int $limit = 250, int $perNodeLimit = 15): array
    {
        GraphSchema::assertLabel($label);

        $cypher = sprintf(
            'MATCH (n:%s) '
            . 'WHERE $tenantId IS NULL OR n.sub_institute_id IS NULL OR n.sub_institute_id = $tenantId '
            . 'WITH n LIMIT $sampleSize '
            . 'CALL { '
            . '  WITH n '
            . '  OPTIONAL MATCH (n)-[r]-(m) '
            . '  RETURN r, m '
            . '  LIMIT $perNodeLimit '
            . '} '
            . 'RETURN id(n) AS nId, labels(n) AS nLabels, n AS nProps, '
            . '       id(r) AS rId, type(r) AS rType, startNode(r) = n AS rOutgoing, '
            . '       id(m) AS mId, labels(m) AS mLabels, m AS mProps '
            . 'LIMIT $limit',
            $label
        );

        try {
            $result = $this->neo4j->run($cypher, [
                'tenantId' => $tenantId,
                'sampleSize' => $sampleSize,
                'perNodeLimit' => $perNodeLimit,
                'limit' => $limit,
            ]);
        } catch (Throwable $exception) {
            return ['label' => $label, 'nodes' => [], 'edges' => [], 'error' => $exception->getMessage()];
        }

        if ($result === null) {
            return ['label' => $label, 'nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];

        foreach ($result as $row) {
            $this->collectNode($nodes, $row->get('nId'), $row->get('nLabels'), $row->get('nProps'));

            $rId = $row->get('rId');

            if ($rId === null) {
                continue;
            }

            $this->collectNode($nodes, $row->get('mId'), $row->get('mLabels'), $row->get('mProps'));

            $outgoing = (bool) $row->get('rOutgoing');
            $nId = (string) $row->get('nId');
            $mId = (string) $row->get('mId');

            $edges[(string) $rId] = [
                'id' => (string) $rId,
                'source' => $outgoing ? $nId : $mId,
                'target' => $outgoing ? $mId : $nId,
                'relation' => (string) $row->get('rType'),
            ];
        }

        return [
            'label' => $label,
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
        ];
    }

    /**
     * @return array<string,int> label => live node count, via one batched Cypher call.
     *
     * Tenant-filtered the same permissive way graph()/graphForLabels() already
     * are (null-tenant legacy nodes still counted) — this is an existing
     * endpoint's helper, not one of the new strictly-scoped ones, so it keeps
     * the pre-existing visibility rule rather than silently tightening it.
     */
    private function liveCounts(array $labels, ?int $tenantId = null): array
    {
        $parts = [];

        foreach ($labels as $label) {
            GraphSchema::assertLabel($label);
            $parts[] = sprintf(
                "MATCH (n:%s) WHERE \$tenantId IS NULL OR n.sub_institute_id IS NULL OR n.sub_institute_id = \$tenantId RETURN '%s' AS label, count(n) AS c",
                $label,
                addslashes($label)
            );
        }

        $cypher = 'CALL { ' . implode(' UNION ALL ', $parts) . ' } RETURN label, c';

        try {
            $result = $this->neo4j->run($cypher, ['tenantId' => $tenantId]);
        } catch (Throwable) {
            return [];
        }

        if ($result === null) {
            return [];
        }

        $counts = [];

        foreach ($result as $row) {
            $counts[(string) $row->get('label')] = (int) $row->get('c');
        }

        return $counts;
    }

    private function collectNode(array &$nodes, mixed $id, mixed $labels, mixed $properties): void
    {
        if ($id === null) {
            return;
        }

        $key = (string) $id;

        if (isset($nodes[$key])) {
            return;
        }

        $props = $this->propsToArray($properties);
        $labelList = $this->labelsToArray($labels);
        $primaryLabel = $labelList[0] ?? 'Node';

        $nodes[$key] = [
            'id' => $key,
            'entityKey' => $primaryLabel,
            'recordId' => $id,
            'label' => $this->displayLabel($props, $primaryLabel, $id),
            'source' => 'graph',
            'attributes' => $props,
        ];
    }

    private function displayLabel(array $properties, string $primaryLabel, mixed $id): string
    {
        foreach (['displayLabel', 'name', 'subject_name', 'chapter_name', 'topic_name', 'title', 'display_name'] as $property) {
            $value = $properties[$property] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $primaryLabel . ' #' . $id;
    }

    private function propsToArray(mixed $properties): array
    {
        if (is_object($properties) && method_exists($properties, 'getProperties')) {
            $inner = $properties->getProperties();

            return method_exists($inner, 'toArray') ? $inner->toArray() : (array) $inner;
        }

        // A bare `properties(r)`/`properties(n)` projection (as opposed to the
        // node/relationship entity itself) comes back as a map value with no
        // getProperties() of its own — the new findPath()/nodeDetail() methods
        // project relationship properties this way.
        if (is_object($properties) && method_exists($properties, 'toArray')) {
            return $properties->toArray();
        }

        return is_array($properties) ? $properties : [];
    }

    private function labelsToArray(mixed $labels): array
    {
        if ($labels === null) {
            return [];
        }

        $out = [];

        foreach ($labels as $label) {
            $out[] = (string) $label;
        }

        return $out;
    }

    private function humanize(string $value): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $value));
    }

    // ------------------------------------------------------------------
    // Rebuild additions: cross-label search, path-finder, full node
    // drill-down, and Organization Management's own staff/department/role
    // graph. All four are strictly tenant-scoped (no `OR sub_institute_id
    // IS NULL` fallback) — new surfaces, not inheriting the pre-existing
    // endpoints' legacy-visibility compromise above.
    // ------------------------------------------------------------------

    /**
     * Cross-label text search — matched nodes only, nothing to connect
     * them with yet (the caller drills in via nodeDetail()). Every
     * GraphSchema label is tried when `$labels` is empty; an unmatched term
     * across ~100 labels is a real cost, flagged here rather than hidden —
     * an index-backed `db.index.fulltext.queryNodes` call would be the next
     * step if this ever needs to be faster, not a reason to narrow which
     * labels are searchable today.
     *
     * An empty `$term` skips the text filter entirely — this is what turns
     * the same method into "show me a few real starting points for these
     * labels," the picker a submodule shows instead of
     * graphForSubmodule()'s old flat sample of everything at once. When
     * `$labels` is given (the picker case), the per-label cap is spread
     * across them (`$limit` / label count) so the result is a spread across
     * types rather than whichever label's Cypher UNION branch happened to
     * return first.
     *
     * @param  string[]  $labels  Restrict to these GraphSchema labels; empty means every label.
     */
    public function search(string $term, ?int $tenantId, int $limit = 30, array $labels = []): array
    {
        $limit = max(1, min($limit, self::MAX_SEARCH_LIMIT));
        $needle = mb_strtolower(trim($term));
        $hasTerm = $needle !== '';

        $searchLabels = $labels !== [] ? $labels : GraphSchema::labels();
        $perLabelLimit = $labels !== [] ? max(1, intdiv($limit, count($searchLabels))) : $limit;

        $parts = [];
        foreach ($searchLabels as $label) {
            GraphSchema::assertLabel($label);
            $filter = $hasTerm
                ? 'AND (toLower(toString(coalesce(n.displayLabel, ""))) CONTAINS $term '
                . 'OR toLower(toString(coalesce(n.name, ""))) CONTAINS $term) '
                : '';
            $parts[] = sprintf(
                'MATCH (n:%s) WHERE n.sub_institute_id = $tenantId %s'
                . 'WITH n LIMIT %d RETURN n',
                $label,
                $filter,
                $perLabelLimit
            );
        }

        $cypher = 'CALL { ' . implode(' UNION ', $parts) . ' } '
            . 'RETURN id(n) AS nId, labels(n) AS nLabels, n AS nProps '
            . 'LIMIT $limit';

        try {
            $result = $this->neo4j->run($cypher, ['tenantId' => $tenantId, 'term' => $needle, 'limit' => $limit]);
        } catch (Throwable $exception) {
            return ['term' => $term, 'nodes' => [], 'error' => $exception->getMessage()];
        }

        if ($result === null) {
            return ['term' => $term, 'nodes' => []];
        }

        $nodes = [];
        foreach ($result as $row) {
            $this->collectNode($nodes, $row->get('nId'), $row->get('nLabels'), $row->get('nProps'));
        }

        return ['term' => $term, 'nodes' => array_values($nodes)];
    }

    /**
     * Shortest path between two real nodes (identified by Neo4j's own
     * internal id, the same `recordId` every other method here already
     * hands the frontend — not a domain key, which several labels don't
     * even have one of; see GraphSchema's own "uid-only parents" note).
     *
     * Tenant authorization covers every node on the path, not just the two
     * endpoints: `shortestPath()` finds the globally shortest route first,
     * then `ALL(...)` filters it — a path that would have to cross into
     * another tenant's data (or through a null-tenant legacy node, now
     * excluded by the strict equality below) simply fails that filter and
     * reports not-found, even if a longer same-tenant path might exist.
     * That's the deliberate, approved trade-off: never silently expose a
     * cross-tenant route by finding a "better" alternative for it.
     */
    public function findPath(
        string $fromLabel,
        string $fromId,
        string $toLabel,
        string $toId,
        ?int $tenantId,
        int $maxDepth = 6,
        array $relationshipTypes = []
    ): array {
        GraphSchema::assertLabel($fromLabel);
        GraphSchema::assertLabel($toLabel);

        foreach ($relationshipTypes as $type) {
            GraphSchema::assertRelationship($type);
        }

        $params = ['fromId' => (int) $fromId, 'toId' => (int) $toId, 'tenantId' => $tenantId];

        if ($fromLabel === $toLabel && (string) $fromId === (string) $toId) {
            return $this->findPathSameNode($fromLabel, $params);
        }

        $depth = max(1, min($maxDepth, self::MAX_PATH_DEPTH));
        $typeFilter = $relationshipTypes !== [] ? ':' . implode('|', $relationshipTypes) : '';

        $cypher = sprintf(
            'MATCH (a:%s) WHERE id(a) = $fromId AND a.sub_institute_id = $tenantId '
            . 'MATCH (b:%s) WHERE id(b) = $toId AND b.sub_institute_id = $tenantId '
            . 'MATCH p = shortestPath((a)-[%s*..%d]-(b)) '
            . 'WHERE ALL(x IN nodes(p) WHERE x.sub_institute_id = $tenantId) '
            . 'WITH p LIMIT 1 '
            . 'UNWIND range(0, size(relationships(p)) - 1) AS i '
            . 'WITH relationships(p)[i] AS r, nodes(p)[i] AS fromNode, nodes(p)[i + 1] AS toNode '
            . 'RETURN id(fromNode) AS nId, labels(fromNode) AS nLabels, fromNode AS nProps, '
            . '       id(r) AS rId, type(r) AS rType, properties(r) AS rProps, startNode(r) = fromNode AS rOutgoing, '
            . '       id(toNode) AS mId, labels(toNode) AS mLabels, toNode AS mProps '
            . 'LIMIT %d',
            $fromLabel,
            $toLabel,
            $typeFilter,
            $depth,
            self::MAX_PATH_NODES
        );

        try {
            $result = $this->neo4j->run($cypher, $params);
        } catch (Throwable $exception) {
            return ['found' => false, 'nodes' => [], 'edges' => [], 'error' => $exception->getMessage()];
        }

        if ($result === null) {
            return ['found' => false, 'nodes' => [], 'edges' => []];
        }

        $nodes = [];
        $edges = [];

        foreach ($result as $row) {
            $this->collectNode($nodes, $row->get('nId'), $row->get('nLabels'), $row->get('nProps'));
            $this->collectNode($nodes, $row->get('mId'), $row->get('mLabels'), $row->get('mProps'));

            $outgoing = (bool) $row->get('rOutgoing');
            $nId = (string) $row->get('nId');
            $mId = (string) $row->get('mId');
            $rId = (string) $row->get('rId');

            $edges[$rId] = [
                'id' => $rId,
                'source' => $outgoing ? $nId : $mId,
                'target' => $outgoing ? $mId : $nId,
                'relation' => (string) $row->get('rType'),
                'properties' => $this->propsToArray($row->get('rProps')),
            ];
        }

        return ['found' => $nodes !== [], 'nodes' => array_values($nodes), 'edges' => array_values($edges)];
    }

    /** @param array{fromId:int,toId:int,tenantId:?int} $params */
    private function findPathSameNode(string $label, array $params): array
    {
        try {
            $result = $this->neo4j->run(
                sprintf(
                    'MATCH (n:%s) WHERE id(n) = $fromId AND n.sub_institute_id = $tenantId '
                    . 'RETURN id(n) AS nId, labels(n) AS nLabels, n AS nProps',
                    $label
                ),
                $params
            );
        } catch (Throwable $exception) {
            return ['found' => false, 'nodes' => [], 'edges' => [], 'error' => $exception->getMessage()];
        }

        $nodes = [];
        foreach ($result ?? [] as $row) {
            $this->collectNode($nodes, $row->get('nId'), $row->get('nLabels'), $row->get('nProps'));
        }

        return ['found' => $nodes !== [], 'nodes' => array_values($nodes), 'edges' => []];
    }

    /**
     * One node's full properties plus a paginated page of its own
     * incoming/outgoing relationships — never the unlimited dump
     * graph()/graphForLabels() give a whole label, always bounded by
     * `$relationshipLimit`/`$skip` with a hard server ceiling regardless
     * of what's requested. Every relationship's *other* endpoint is tenant-
     * checked too (`m.sub_institute_id = $tenantId`, strict) — a
     * relationship to a node outside this tenant is never returned, not
     * merely hidden client-side.
     *
     * `$relation`/`$direction` (added for the progressive per-group
     * explorer): when given, both the relationship fetch AND the count
     * query are filtered to just that relationship type (and direction,
     * `'incoming'|'outgoing'`) — so "expand this one group" gets its own
     * accurate total/pagination rather than a slice of the node's combined
     * relationship list. Omitted, behaviour is exactly as before (every
     * relationship, mixed) — this is what the panel's initial "what groups
     * exist" discovery still uses.
     */
    public function nodeDetail(
        string $label,
        string $recordId,
        ?int $tenantId,
        int $relationshipLimit = 50,
        int $skip = 0,
        ?string $relation = null,
        ?string $direction = null
    ): array {
        GraphSchema::assertLabel($label);
        if ($relation !== null) {
            GraphSchema::assertRelationship($relation);
        }
        $direction = in_array($direction, ['incoming', 'outgoing'], true) ? $direction : null;
        $relationshipLimit = max(1, min($relationshipLimit, self::MAX_RELATIONSHIP_LIMIT));
        $skip = max(0, $skip);

        $nodeParams = ['recordId' => (int) $recordId, 'tenantId' => $tenantId];
        $empty = ['node' => null, 'nodes' => [], 'edges' => [], 'total_incoming' => 0, 'total_outgoing' => 0, 'has_more' => false];

        try {
            $nodeResult = $this->neo4j->run(
                sprintf(
                    'MATCH (n:%s) WHERE id(n) = $recordId AND n.sub_institute_id = $tenantId '
                    . 'RETURN id(n) AS nId, labels(n) AS nLabels, n AS nProps',
                    $label
                ),
                $nodeParams
            );
        } catch (Throwable $exception) {
            return $empty + ['error' => $exception->getMessage()];
        }

        $nodeRow = null;
        foreach ($nodeResult ?? [] as $row) {
            $nodeRow = $row;
            break;
        }

        if ($nodeRow === null) {
            return $empty;
        }

        $nodes = [];
        $this->collectNode($nodes, $nodeRow->get('nId'), $nodeRow->get('nLabels'), $nodeRow->get('nProps'));
        $centerId = (string) $nodeRow->get('nId');
        $centerNode = $nodes[$centerId];
        $centerNode['attributes'] = $this->resolveReferenceProperties($centerNode['attributes'], $tenantId);
        $nodes[$centerId] = $centerNode;

        $typeFilterOut = $relation !== null ? 'AND type(ro) = $relation ' : '';
        $typeFilterIn = $relation !== null ? 'AND type(ri) = $relation ' : '';

        $inCount = 0;
        $outCount = 0;
        try {
            foreach ($this->neo4j->run(
                'MATCH (n) WHERE id(n) = $recordId '
                . 'OPTIONAL MATCH (n)-[ro]->(mo) WHERE mo.sub_institute_id = $tenantId ' . $typeFilterOut
                . 'OPTIONAL MATCH (n)<-[ri]-(mi) WHERE mi.sub_institute_id = $tenantId ' . $typeFilterIn
                . 'RETURN count(DISTINCT ro) AS outCount, count(DISTINCT ri) AS inCount',
                $nodeParams + ['relation' => $relation]
            ) ?? [] as $row) {
                $outCount = (int) $row->get('outCount');
                $inCount = (int) $row->get('inCount');
            }
        } catch (Throwable) {
            // Relationship counts are a convenience for "load more" — the node itself is still useful without them.
        }

        if ($direction === 'outgoing') {
            $inCount = 0;
        } elseif ($direction === 'incoming') {
            $outCount = 0;
        }

        $typeFilter = $relation !== null ? 'AND type(r) = $relation ' : '';
        $directionFilter = $direction === 'outgoing'
            ? 'AND startNode(r) = n '
            : ($direction === 'incoming' ? 'AND endNode(r) = n ' : '');

        try {
            $relResult = $this->neo4j->run(
                'MATCH (n) WHERE id(n) = $recordId '
                . 'MATCH (n)-[r]-(m) WHERE m.sub_institute_id = $tenantId ' . $typeFilter . $directionFilter
                . 'WITH r, m, startNode(r) = n AS outgoing '
                . 'ORDER BY id(r) '
                . 'SKIP $skip LIMIT $limit '
                . 'RETURN id(r) AS rId, type(r) AS rType, properties(r) AS rProps, outgoing, '
                . '       id(m) AS mId, labels(m) AS mLabels, m AS mProps',
                ['recordId' => (int) $recordId, 'tenantId' => $tenantId, 'skip' => $skip, 'limit' => $relationshipLimit, 'relation' => $relation]
            );
        } catch (Throwable $exception) {
            return [
                'node' => $centerNode,
                'nodes' => array_values($nodes),
                'edges' => [],
                'total_incoming' => $inCount,
                'total_outgoing' => $outCount,
                'has_more' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $edges = [];
        foreach ($relResult ?? [] as $row) {
            $this->collectNode($nodes, $row->get('mId'), $row->get('mLabels'), $row->get('mProps'));

            $outgoing = (bool) $row->get('outgoing');
            $mId = (string) $row->get('mId');
            $rId = (string) $row->get('rId');

            $edges[$rId] = [
                'id' => $rId,
                'source' => $outgoing ? $centerId : $mId,
                'target' => $outgoing ? $mId : $centerId,
                'relation' => (string) $row->get('rType'),
                'properties' => $this->propsToArray($row->get('rProps')),
            ];
        }

        $total = $inCount + $outCount;
        $hasMore = ($skip + count($edges)) < $total;

        return [
            'node' => $centerNode,
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
            'total_incoming' => $inCount,
            'total_outgoing' => $outCount,
            'has_more' => $hasMore,
        ];
    }

    private function resolveReferenceProperties(array $properties, ?int $tenantId): array
    {
        foreach (self::REFERENCE_PROPERTIES as $property => $reference) {
            $referenceId = $properties[$property] ?? null;

            if (! is_numeric($referenceId)) {
                continue;
            }

            $label = $reference['label'];
            $key = GraphSchema::key($label);

            try {
                $result = $this->neo4j->run(
                    sprintf(
                        'MATCH (n:%s) WHERE n.sub_institute_id = $tenantId AND toString(n.%s) = $referenceId '
                        . 'RETURN labels(n) AS labels, n AS properties LIMIT 1',
                        $label,
                        $key
                    ),
                    ['tenantId' => $tenantId, 'referenceId' => (string) $referenceId]
                );
            } catch (Throwable) {
                continue;
            }

            foreach ($result ?? [] as $row) {
                $referenceProperties = $this->propsToArray($row->get('properties'));
                $referenceLabels = $this->labelsToArray($row->get('labels'));
                $properties[$reference['attribute']] = $this->displayLabel(
                    $referenceProperties,
                    $referenceLabels[0] ?? $label,
                    $referenceId
                );
                unset($properties[$property]);
                break;
            }
        }

        return $properties;
    }

    /**
     * Organization Management's own Institute/Department/Role/Staff graph —
     * PR #988's `KnowledgeGraphController::build()` Cypher, moved here
     * verbatim (same queries, same tenant scoping) so it becomes the one
     * real implementation; that controller now delegates to this method
     * and reshapes the result back into its own exact legacy response
     * shape. Surfaced as Organization Management's own submodule entry in
     * the rebuilt explorer's tree, alongside its generic `organization`-
     * family labels (Department/Role) — not instead of them.
     */
    public function organizationStaffGraph(?int $tenantId, int $perRole = 25, bool $withSubjects = false): array
    {
        try {
            $params = ['t' => $tenantId];
            $nodes = [];
            $edges = [];

            $instituteName = null;
            foreach ($this->neo4j->run('MATCH (i:Institute {sub_institute_id: $t}) RETURN i.SchoolName AS name LIMIT 1', $params) as $row) {
                $instituteName = (string) $row->get('name');
            }
            $nodes['institute'] = $this->staffNode('institute', 'Institute', null, $instituteName ?: 'School', ['count' => 0]);

            $departmentParent = [];
            foreach ($this->neo4j->run(
                'MATCH (d:Department {sub_institute_id: $t}) WHERE coalesce(d.deleted_at, "") = "" '
                . 'OPTIONAL MATCH (s:Staff)-[:IN_DEPARTMENT]->(d) '
                . 'RETURN d.id AS id, d.department AS name, d.parent_id AS parent, count(s) AS staff',
                $params
            ) as $row) {
                $id = 'dept-' . $row->get('id');
                $departmentParent[$id] = (int) $row->get('parent');
                $nodes[$id] = $this->staffNode($id, 'Department', $row->get('id'), (string) $row->get('name'), ['count' => (int) $row->get('staff')]);
            }
            foreach ($departmentParent as $id => $parent) {
                $parentId = $parent > 0 ? 'dept-' . $parent : null;
                $fromId = $parentId && isset($nodes[$parentId]) ? $parentId : 'institute';
                $edges[$fromId . '>' . $id] = $this->staffEdge($fromId, $id, 'HAS_DEPARTMENT');
            }

            $totalStaff = 0;
            foreach ($this->neo4j->run(
                'MATCH (r:Role)<-[:HAS_ROLE]-(s:Staff {sub_institute_id: $t}) '
                . 'RETURN r.id AS id, r.name AS name, count(DISTINCT s) AS staff ORDER BY staff DESC',
                $params
            ) as $row) {
                $roleId = 'role-' . $row->get('id');
                $total = (int) $row->get('staff');
                $totalStaff += $total;
                $nodes[$roleId] = $this->staffNode($roleId, 'Role', $row->get('id'), (string) $row->get('name'), ['count' => $total]);
                $edges['institute>' . $roleId] = $this->staffEdge('institute', $roleId, 'HAS_ROLE');

                foreach ($this->neo4j->run(
                    'MATCH (r:Role {id: $rid})<-[:HAS_ROLE]-(s:Staff {sub_institute_id: $t}) '
                    . 'OPTIONAL MATCH (s)-[:IN_DEPARTMENT]->(d:Department) '
                    . 'RETURN s.staffId AS id, s.first_name AS first, s.last_name AS last, collect(DISTINCT d.id) AS depts '
                    . 'ORDER BY first LIMIT $n',
                    ['t' => $tenantId, 'rid' => $row->get('id'), 'n' => $perRole]
                ) as $staff) {
                    $staffId = 'staff-' . $staff->get('id');
                    $staffLabel = trim($staff->get('first') . ' ' . $staff->get('last')) ?: 'Staff';
                    $nodes[$staffId] = $this->staffNode($staffId, 'Staff', $staff->get('id'), $staffLabel, ['role' => $roleId]);
                    $edges[$roleId . '>' . $staffId] = $this->staffEdge($roleId, $staffId, 'HAS_ROLE');

                    foreach ($staff->get('depts') as $deptId) {
                        $deptNodeId = 'dept-' . $deptId;
                        if (isset($nodes[$deptNodeId])) {
                            $edges[$deptNodeId . '>' . $staffId] = $this->staffEdge($deptNodeId, $staffId, 'IN_DEPARTMENT');
                        }
                    }
                }
            }
            $nodes['institute']['attributes']['count'] = $totalStaff;

            if ($withSubjects) {
                $staffRecordIds = array_values(array_map(
                    fn ($n) => $n['recordId'],
                    array_filter($nodes, fn ($n) => $n['entityKey'] === 'Staff')
                ));

                if ($staffRecordIds !== []) {
                    foreach ($this->neo4j->run(
                        'MATCH (s:Staff {sub_institute_id: $t})-[:TEACHES_SUBJECT]->(x:Subject) WHERE toString(s.staffId) IN $ids '
                        . 'RETURN toString(s.staffId) AS sid, x.id AS id, x.name AS name',
                        ['t' => $tenantId, 'ids' => array_map('strval', $staffRecordIds)]
                    ) as $row) {
                        $subjectId = 'subject-' . $row->get('id');
                        if (! isset($nodes[$subjectId])) {
                            $nodes[$subjectId] = $this->staffNode($subjectId, 'Subject', $row->get('id'), (string) $row->get('name'), []);
                        }
                        $edges['staff-' . $row->get('sid') . '>' . $subjectId] =
                            $this->staffEdge('staff-' . $row->get('sid'), $subjectId, 'TEACHES_SUBJECT');
                    }
                }
            }

            $byType = array_count_values(array_map(fn ($n) => $n['entityKey'], $nodes));

            return [
                'nodes' => array_values($nodes),
                'edges' => array_values($edges),
                'stats' => [
                    'staffTotal' => $totalStaff,
                    'staffShown' => $byType['Staff'] ?? 0,
                    'roles' => $byType['Role'] ?? 0,
                    'departments' => $byType['Department'] ?? 0,
                    'subjects' => $byType['Subject'] ?? 0,
                    'edges' => count($edges),
                ],
            ];
        } catch (Throwable $exception) {
            return ['nodes' => [], 'edges' => [], 'stats' => [], 'error' => $exception->getMessage()];
        }
    }

    private function staffNode(string $id, string $entityKey, mixed $recordId, string $label, array $attributes): array
    {
        return ['id' => $id, 'entityKey' => $entityKey, 'recordId' => $recordId, 'label' => $label, 'source' => 'graph', 'attributes' => $attributes];
    }

    private function staffEdge(string $from, string $to, string $relation): array
    {
        return ['id' => "{$from}->{$relation}->{$to}", 'source' => $from, 'target' => $to, 'relation' => $relation];
    }
}
