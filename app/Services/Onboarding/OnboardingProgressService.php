<?php

namespace App\Services\Onboarding;

use App\Models\onboarding\OnboardingModuleModel;
use App\Models\onboarding\OnboardingProgressModel;
use App\Models\onboarding\OnboardingStepModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The derived-completion engine.
 *
 * A step is complete because the data that proves it exists — not because
 * somebody ticked a box. This keeps the good property of the legacy engine in
 * tourController@Onboarding while fixing its three material weaknesses:
 *
 *   1. Legacy proof was "does this menu's table have >= 1 row for the tenant?".
 *      Here each step carries its own proof with a row threshold, optional
 *      column predicates, and academic-year scoping.
 *   2. Legacy returned `false` both for "not done" and for "the table does not
 *      exist / has no sub_institute_id column", so a misconfigured step looked
 *      identical to an unstarted one and could never complete. Here that case
 *      is surfaced explicitly as `misconfigured`.
 *   3. Steps with no table to point at could not be represented at all. Here
 *      `proof_type = manual` falls back to a recorded, attributed sign-off.
 *
 * Every derived query is tenant-scoped by hand, because this codebase has no
 * global tenant scope and nothing will add the predicate for us.
 */
class OnboardingProgressService
{
    /** Schema lookups are hot in a loop over ~200 steps; memoise per request. */
    private array $tableCache = [];
    private array $columnCache = [];

    /** The sidebar walk is asked for by the list, the detail and the write path. */
    private array $sidebarCache = [];

    /**
     * Report steps are excluded from every module's journey — the onboarding
     * flow walks a school through setting up each module's own data, and a
     * report screen has nothing to configure, so it never belongs in that
     * flow. Matches any step whose title contains "report" (case-insensitive)
     * — the generic "Reports" catch-all as well as specific ones like "Fees
     * Collection Report" or "Attendance Report".
     */
    private const HIDDEN_STEP_TITLE_KEYWORD = 'report';

    /**
     * Full journey for one module, with every step resolved to a status.
     *
     * @return array{module: array, steps: array, summary: array}
     */
    public function moduleJourney(OnboardingModuleModel $module, int $subInstituteId, int $syear): array
    {
        $steps = $this->excludeHiddenSteps(
            OnboardingStepModel::where('module_id', $module->id)
                ->where('status', 1)
        )
            ->orderBy('sort_order')
            ->get();

        $progress = $this->progressIndex($subInstituteId, $syear, $module->id);
        $menuLinks = $this->menuHelpLinks($steps->pluck('action_menu_id'));

        $resolved = [];
        foreach ($steps as $step) {
            $resolved[] = $this->resolveStep($step, $subInstituteId, $syear, $progress[$step->id] ?? null, $menuLinks);
        }

        return [
            'module' => $this->presentModule($module, $subInstituteId),
            'steps' => $resolved,
            'summary' => $this->summarise($resolved),
        ];
    }

    /**
     * Lightweight roll-up for every module — used by the onboarding index.
     * Runs the same derivation, so the index and the detail screen can never
     * disagree.
     */
    public function tenantOverview(int $subInstituteId, int $syear): array
    {
        $modules = $this->modulesForTenant($subInstituteId);

        if ($modules->isEmpty()) {
            return ['modules' => [], 'summary' => $this->summarise([])];
        }

        $moduleIds = $modules->pluck('id')->all();

        $stepsByModule = $this->excludeHiddenSteps(
            OnboardingStepModel::whereIn('module_id', $moduleIds)
                ->where('status', 1)
        )
            ->orderBy('sort_order')
            ->get()
            ->groupBy('module_id');

        $progress = $this->progressIndex($subInstituteId, $syear, $moduleIds);
        $menuLinks = $this->menuHelpLinks($stepsByModule->flatten()->pluck('action_menu_id'));

        $out = [];
        $all = [];
        foreach ($modules as $module) {
            $resolved = [];
            foreach ($stepsByModule->get($module->id, collect()) as $step) {
                $resolved[] = $this->resolveStep($step, $subInstituteId, $syear, $progress[$step->id] ?? null, $menuLinks);
            }
            $all = array_merge($all, $resolved);

            $summary = $this->summarise($resolved);
            $out[] = $this->presentModule($module, $subInstituteId) + [
                'summary' => $summary,
                'next_step' => $this->firstIncomplete($resolved),
            ];
        }

        return ['modules' => $out, 'summary' => $this->summarise($all)];
    }

    /**
     * Record the part of a step's state that cannot be derived. Never used to
     * fake completion of a `table_rows` step — `applyManualOverride()` refuses
     * a manual `completed` there, so proof stays authoritative.
     */
    public function saveStepState(
        OnboardingStepModel $step,
        int $subInstituteId,
        int $syear,
        array $attributes
    ): OnboardingProgressModel {
        $scopedYear = $step->isYearScoped() ? $syear : 0;

        $record = OnboardingProgressModel::firstOrNew([
            'sub_institute_id' => $subInstituteId,
            'syear' => $scopedYear,
            'step_id' => $step->id,
        ]);

        $record->module_id = $step->module_id;

        foreach (['status', 'notes', 'assigned_to_id', 'assigned_to_name', 'updated_by_id', 'updated_by_name'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $record->{$key} = $attributes[$key];
            }
        }

        if (($attributes['status'] ?? null) === 'completed') {
            $record->completed_at = $record->completed_at ?: now();
        } elseif (array_key_exists('status', $attributes)) {
            $record->completed_at = null;
        }

        $record->save();

        return $record;
    }

    // ------------------------------------------------------------------
    // Resolution
    // ------------------------------------------------------------------

    private function resolveStep(
        OnboardingStepModel $step,
        int $subInstituteId,
        int $syear,
        ?OnboardingProgressModel $record,
        array $menuLinks = []
    ): array {
        $derived = $this->deriveProof($step, $subInstituteId, $syear);
        $status = $this->applyManualOverride($step, $derived, $record);
        $menu = $step->action_menu_id ? ($menuLinks[(int) $step->action_menu_id] ?? null) : null;

        return [
            'id' => (int) $step->id,
            'step_key' => $step->step_key,
            'title' => $step->title,
            'description' => $step->description,
            'sort_order' => (int) $step->sort_order,
            'is_required' => (bool) $step->is_required,
            'owner' => $step->owner,
            'triz_role' => $step->triz_role,
            'school_role' => $step->school_role,
            'status' => $status,
            'is_complete' => in_array($status, ['completed', 'skipped'], true),
            'proof' => [
                'type' => $step->proof_type,
                'table' => $step->proof_table,
                'scope' => $step->proof_scope,
                'min_rows' => (int) $step->proof_min_rows,
                'row_count' => $derived['row_count'],
                'at_least' => $derived['at_least'],
                'satisfied' => $derived['satisfied'],
                'state' => $derived['state'],
                'reason' => $derived['reason'],
            ],
            'action' => [
                'route' => $step->action_route,
                'menu_id' => $step->action_menu_id ? (int) $step->action_menu_id : null,
                // Live from tblmenumaster for the step's linked menu when one is
                // set, so the drawer always reflects the menu's current help
                // content rather than a copy frozen on the onboarding step.
                // Falls back to the step's own columns for steps with no linked menu.
                //
                // Uses blankToNull() rather than `??` because these columns are
                // free-text inputs that sometimes get saved as '' instead of
                // NULL when left blank — plain `??` only falls back on a real
                // NULL, so a blank string on the menu row would silently win
                // over a populated value on the step and make the link vanish
                // even though "the value is in the database" on the other row.
                'youtube_link' => $this->blankToNull($menu->youtube_link ?? null) ?? $this->blankToNull($step->help_youtube_link),
                'pdf_link' => $this->resolveHelpGuideUrl($this->blankToNull($menu->pdf_link ?? null) ?? $this->blankToNull($step->help_pdf_link)),
                'quick_menu' => $this->blankToNull($menu->quick_menu ?? null),
            ],
            'state' => [
                'manual_status' => $record->status ?? null,
                'notes' => $record->notes ?? null,
                'assigned_to_id' => $record && $record->assigned_to_id ? (int) $record->assigned_to_id : null,
                'assigned_to_name' => $record->assigned_to_name ?? null,
                'updated_by_name' => $record->updated_by_name ?? null,
                'completed_at' => $record && $record->completed_at
                    ? $record->completed_at->toDateTimeString()
                    : null,
                'updated_at' => $record && $record->updated_at
                    ? $record->updated_at->toDateTimeString()
                    : null,
            ],
        ];
    }

    /**
     * Excludes every report step from a step query — anything whose title
     * contains "report", case-insensitively, is left out entirely: it never
     * enters the resolved list, so it counts toward neither the journey nor
     * the progress summary.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function excludeHiddenSteps($query)
    {
        return $query->whereRaw(
            'LOWER(title) NOT LIKE ?',
            ['%' . self::HIDDEN_STEP_TITLE_KEYWORD . '%']
        );
    }

    /**
     * Live help content — youtube link, pdf link, quick menu — for the menus a
     * batch of steps point at, read straight from tblmenumaster keyed by menu
     * id. Batched once per journey/overview call rather than per step, so a
     * module with dozens of steps costs one query instead of dozens.
     *
     * @param  \Illuminate\Support\Collection  $menuIds
     * @return array<int, object{youtube_link: ?string, pdf_link: ?string, quick_menu: ?string}>
     */
    private function menuHelpLinks($menuIds): array
    {
        $ids = collect($menuIds)->filter()->map(static fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('tblmenumaster')
            ->select('id', 'youtube_link', 'pdf_link', 'quick_menu')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(static fn ($row) => (int) $row->id)
            ->all();
    }

    /**
     * `tblmenumaster.pdf_link` (and the legacy `onboarding_steps.help_pdf_link`
     * copy of it) stores a bare filename, not a URL — the existing help-guide
     * viewer (footerJs.blade.php) resolves it the same way, against
     * `storage/help_guide/`. Turned into an absolute URL here so the API never
     * hands the client a bare filename for it to mis-resolve against its own
     * origin.
     */
    private function resolveHelpGuideUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $value)) {
            return $value;
        }

        return rtrim(config('app.url'), '/') . '/storage/help_guide/' . ltrim($value, '/');
    }

    /**
     * A handful of tblmenumaster help-link rows have a saved '' (or stray
     * whitespace/CRLF from how the value was originally entered) instead of a
     * real NULL. Trimmed and normalised to a true null so `??` fallbacks
     * downstream behave correctly and no button renders with a blank/CRLF
     * href.
     */
    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Ask the database whether the work behind this step actually happened.
     *
     * @return array{satisfied: bool, row_count: int|null, state: string, reason: string|null}
     */
    private function deriveProof(OnboardingStepModel $step, int $subInstituteId, int $syear): array
    {
        if ($step->proof_type !== 'table_rows') {
            return [
                'satisfied' => false,
                'row_count' => null,
                'at_least' => false,
                'state' => $step->proof_type === 'none' ? 'not_applicable' : 'manual',
                'reason' => null,
            ];
        }

        $table = (string) $step->proof_table;

        if ($table === '' || ! $this->hasTable($table)) {
            return $this->misconfigured("Proof table `{$table}` does not exist.");
        }

        $query = DB::table($table);

        if ($step->proof_scope !== 'global') {
            $tenantColumn = $this->tenantColumn($table);
            if (! $tenantColumn) {
                return $this->misconfigured(
                    "Table `{$table}` has no sub_institute_id column, so it cannot prove a tenant-scoped step."
                );
            }
            $query->where($tenantColumn, $subInstituteId);
        }

        if ($step->proof_scope === 'tenant_year' && $syear > 0) {
            $yearColumn = $this->yearColumn($table);
            if ($yearColumn) {
                $query->where($yearColumn, $syear);
            }
            // A missing year column is not fatal: the step degrades to
            // tenant-scoped proof rather than becoming permanently unachievable.
        }

        foreach ((array) $step->proof_conditions as $column => $value) {
            if (! is_string($column) || ! $this->hasColumn($table, $column)) {
                continue;
            }
            is_array($value) ? $query->whereIn($column, $value) : $query->where($column, $value);
        }

        $required = max(1, (int) $step->proof_min_rows);

        // Probe, don't tally. A full COUNT(*) over tables the size of
        // `tblstudent` or `fees_collect` costs far more than the question needs:
        // all we must know is whether at least `$required` rows exist. Fetching
        // at most that many rows turns each proof into an index-range probe and
        // took the 20-module overview from ~2.6s to well under a second.
        $found = $query->select(DB::raw('1 as probe'))->limit($required)->get()->count();
        $satisfied = $found >= $required;

        return [
            'satisfied' => $satisfied,
            'row_count' => $found,
            // True when the probe hit its cap, i.e. the real total is >= row_count
            // but was deliberately not measured. The UI must not print row_count
            // as an exact figure in that case.
            'at_least' => $satisfied,
            'state' => $satisfied ? 'satisfied' : 'unsatisfied',
            'reason' => $satisfied
                ? null
                : ($found === 0
                    ? "No records found in `{$table}` for this institute" . ($step->proof_scope === 'tenant_year' ? ' and academic year.' : '.')
                    : "Found {$found} of the {$required} record(s) required in `{$table}`."),
        ];
    }

    /**
     * Combine derived proof with the recorded state.
     *
     * Precedence:
     *   - `skipped` / `blocked` are deliberate human decisions and always win.
     *   - For a proof-backed step, satisfied proof completes it regardless of
     *     what anyone recorded; an unsatisfied proof can never be talked up to
     *     `completed`, only down to `in_progress`.
     *   - For a manual step there is no proof, so the record is the truth.
     */
    private function applyManualOverride(
        OnboardingStepModel $step,
        array $derived,
        ?OnboardingProgressModel $record
    ): string {
        $manual = $record->status ?? null;

        if ($manual === 'skipped' || $manual === 'blocked') {
            return $manual;
        }

        if ($step->proof_type === 'table_rows') {
            if ($derived['state'] === 'misconfigured') {
                return 'misconfigured';
            }
            if ($derived['satisfied']) {
                return 'completed';
            }

            return $manual === 'in_progress' ? 'in_progress' : 'pending';
        }

        return $manual ?: 'pending';
    }

    private function summarise(array $steps): array
    {
        $total = count($steps);
        $required = 0;
        $completed = 0;
        $requiredCompleted = 0;
        $byStatus = ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'skipped' => 0, 'blocked' => 0, 'misconfigured' => 0];

        foreach ($steps as $step) {
            $byStatus[$step['status']] = ($byStatus[$step['status']] ?? 0) + 1;
            if ($step['is_complete']) {
                $completed++;
            }
            if ($step['is_required']) {
                $required++;
                if ($step['is_complete']) {
                    $requiredCompleted++;
                }
            }
        }

        $denominator = $required ?: $total;
        $numerator = $required ? $requiredCompleted : $completed;

        return [
            'total_steps' => $total,
            'required_steps' => $required,
            'completed_steps' => $completed,
            'required_completed' => $requiredCompleted,
            'percent_complete' => $denominator > 0 ? (int) round(($numerator / $denominator) * 100) : 0,
            'by_status' => $byStatus,
            'is_complete' => $denominator > 0 && $numerator >= $denominator,
        ];
    }

    private function firstIncomplete(array $steps): ?array
    {
        foreach ($steps as $step) {
            if (! $step['is_complete']) {
                return [
                    'id' => $step['id'],
                    'step_key' => $step['step_key'],
                    'title' => $step['title'],
                    'owner' => $step['owner'],
                    'status' => $step['status'],
                ];
            }
        }

        return null;
    }

    /**
     * The modules this school actually sees, in this school's own menu order.
     *
     * The journey definitions in `onboarding_module` are a catalogue, not the
     * display list. Which of them appear — and in what sequence — is driven
     * entirely by `tblmenumaster` for this tenant:
     *
     *   - `menu_title` present in the school's menu  → shown, positioned exactly
     *     where the sidebar puts that group.
     *   - `menu_title` set but absent from the menu  → hidden. The school does
     *     not have that module, so there is nothing to onboard.
     *   - `menu_title` null                          → shown last. The journey is
     *     deliberately not tied to the menu tree, so it is kept rather than
     *     silently dropped.
     *
     * Two schools with different menus therefore get different onboarding lists,
     * each in the order its staff already know from the sidebar.
     */
    public function modulesForTenant(int $subInstituteId)
    {
        $titles = $this->sidebarIndex($subInstituteId)['titles'];

        $visible = OnboardingModuleModel::forTenant($subInstituteId)
            ->filter(fn ($module) => empty($module->menu_title) || isset($titles[$module->menu_title]));

        return $this->orderBySchoolMenu($visible, $titles);
    }

    /** Resolve one module, applying the same visibility rule as the list. */
    public function findModuleForTenant(string $moduleKey, int $subInstituteId): ?OnboardingModuleModel
    {
        return $this->modulesForTenant($subInstituteId)->firstWhere('module_key', $moduleKey);
    }

    /**
     * Sidebar position of every menu row this school can see, keyed by menu id.
     *
     * Exposed so the sub-module list on the module detail screen can be sorted
     * by the same walk that orders the modules themselves — the two must agree,
     * and re-deriving the order in the controller would let them drift.
     *
     * @return array<int, int> menu id => 1-based position in the sidebar
     */
    public function menuPositions(int $subInstituteId): array
    {
        return $this->sidebarIndex($subInstituteId)['menus'];
    }

    private function orderBySchoolMenu($modules, array $titles)
    {
        if ($modules->isEmpty()) {
            return $modules->values();
        }

        // A padded string rather than a tuple: PHP compares arrays by LENGTH
        // before content, so any array-valued key silently mis-sorts here.
        $key = static fn ($module) => sprintf(
            // 0 = the school has this module in its menu, 1 = it does not and
            // the module falls to the end on its template order.
            '%d.%08d.%08d.%s',
            isset($titles[$module->menu_title]) ? 0 : 1,
            $titles[$module->menu_title] ?? 0,
            max(0, (int) $module->sort_order),
            (string) $module->module_name
        );

        return $modules->sortBy($key)->values();
    }

    /**
     * Number every menu row in the order the sidebar reads them.
     *
     * A plain `ORDER BY sort_order` cannot express sidebar order: `sort_order`
     * is scoped to a parent, so every submenu restarts at 1 and a level-3 row's
     * own value says nothing about where it sits overall.
     *
     * So this walks the tenant's menu tree the way MenuMiddleware builds it —
     * roots by `sort_order`, then each row's children by `sort_order`, parent
     * before children — and stamps an incrementing position on each row as it
     * is visited. Pre-order numbering means one integer per row now encodes the
     * full hierarchy: comparing two positions gives the sidebar's answer, with
     * no ancestor paths to compare and no array-length trap to fall into.
     *
     * A `menu_title` group spans several menu rows, sometimes under different
     * parents (Result lives under both "Exam (Master)" and "Reports"). Its
     * position is that of its first row in the walk, i.e. where a member of
     * staff scanning the sidebar top-down first meets that module.
     *
     * @return array{
     *     menus: array<int,int>,             menu id      => position
     *     titles: array<string,int>,         menu_title   => position of its first row
     *     roots: array<int,array>,           root menu id => {id, name, sort_order}
     *     sections: array<string,int|null>   menu_title   => the root it sits under
     * }
     */
    private function sidebarIndex(int $subInstituteId): array
    {
        if (isset($this->sidebarCache[$subInstituteId])) {
            return $this->sidebarCache[$subInstituteId];
        }

        // Ordered here so every child bucket below inherits sidebar order for
        // free. `id` breaks the ties that `sort_order` alone leaves — duplicate
        // sort_order values are common — so the sequence is deterministic
        // rather than left to whatever the server returns.
        $rows = DB::table('tblmenumaster')
            ->select('id', 'parent_menu_id', 'level', 'sort_order', 'name', 'menu_title')
            ->where('status', 1)
            ->whereRaw('find_in_set(?, sub_institute_id)', [$subInstituteId])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $visible = [];
        foreach ($rows as $row) {
            $visible[(int) $row->id] = $row;
        }

        $roots = [];
        $children = [];
        $detached = [];

        foreach ($rows as $row) {
            $parentId = (int) $row->parent_menu_id;

            if ($parentId === 0) {
                $roots[] = $row;
            } elseif ($parentId === (int) $row->id || ! isset($visible[$parentId])) {
                // Either self-parented or hanging off a menu this school cannot
                // see. Neither renders in the sidebar.
                $detached[] = $row;
            } else {
                $children[$parentId][] = $row;
            }
        }

        $menus = [];
        $titles = [];
        $sections = [];
        $seq = 0;

        // $rootId is the top-level menu this row hangs under — carried down so a
        // module can name its sidebar section without a second traversal. Null
        // for a detached row, which belongs to no section the school can see.
        $walk = function ($row, ?int $rootId) use (&$walk, &$children, &$menus, &$titles, &$sections, &$seq) {
            $id = (int) $row->id;
            if (isset($menus[$id])) {
                return; // Already numbered — also the guard against a parent cycle.
            }

            $menus[$id] = ++$seq;

            $title = (string) $row->menu_title;
            if ($title !== '' && ! isset($titles[$title])) {
                $titles[$title] = $menus[$id];
                $sections[$title] = $rootId;
            }

            foreach ($children[$id] ?? [] as $child) {
                $walk($child, $rootId);
            }
        };

        $rootIndex = [];
        foreach ($roots as $root) {
            $rootIndex[(int) $root->id] = [
                'id' => (int) $root->id,
                'name' => $root->name,
                'sort_order' => (int) $root->sort_order,
            ];

            $walk($root, (int) $root->id);
        }

        // Numbered after everything that does render, so a module reachable only
        // through an invisible parent can never jump ahead of one the staff can
        // actually navigate to.
        foreach ($detached as $row) {
            $walk($row, null);
        }

        return $this->sidebarCache[$subInstituteId] = [
            'menus' => $menus,
            'titles' => $titles,
            'roots' => $rootIndex,
            'sections' => $sections,
        ];
    }

    private function presentModule(OnboardingModuleModel $module, int $subInstituteId): array
    {
        $index = $this->sidebarIndex($subInstituteId);
        $title = (string) $module->menu_title;
        $rootId = $title !== '' ? ($index['sections'][$title] ?? null) : null;

        return [
            'id' => (int) $module->id,
            'module_key' => $module->module_key,
            'module_name' => $module->module_name,
            'menu_title' => $module->menu_title,
            'description' => $module->description,
            'icon' => $module->icon,
            'sort_order' => (int) $module->sort_order,
            'is_tenant_override' => (int) $module->sub_institute_id !== 0,
            // Where the sidebar puts this module, and which top-level menu it
            // sits under — enough for the client to group the journey list into
            // the same sections, in the same order, as the sidebar itself.
            'menu_position' => $title !== '' ? ($index['titles'][$title] ?? null) : null,
            'menu_parent' => $rootId !== null ? ($index['roots'][$rootId] ?? null) : null,
        ];
    }

    /**
     * @param int|array $moduleId
     * @return array<int, OnboardingProgressModel> keyed by step_id
     */
    private function progressIndex(int $subInstituteId, int $syear, $moduleId): array
    {
        $rows = OnboardingProgressModel::where('sub_institute_id', $subInstituteId)
            ->whereIn('module_id', (array) $moduleId)
            // 0 = the not-year-scoped bucket; both are relevant in one read.
            ->whereIn('syear', [0, $syear])
            ->orderBy('syear')
            ->get();

        $index = [];
        foreach ($rows as $row) {
            // A year-specific row outranks the year-agnostic one for the same step.
            $index[$row->step_id] = $row;
        }

        return $index;
    }

    private function misconfigured(string $reason): array
    {
        return [
            'satisfied' => false,
            'row_count' => null,
            'at_least' => false,
            'state' => 'misconfigured',
            'reason' => $reason,
        ];
    }

    private function hasTable(string $table): bool
    {
        return $this->tableCache[$table] ??= Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->columnCache["{$table}.{$column}"] ??= Schema::hasColumn($table, $column);
    }

    /** Column naming is inconsistent across the 456 tables; probe the known variants. */
    private function tenantColumn(string $table): ?string
    {
        foreach (['sub_institute_id', 'SUB_INSTITUTE_ID', 'sub_institute_Id'] as $candidate) {
            if ($this->hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function yearColumn(string $table): ?string
    {
        foreach (['syear', 'academic_year', 'academic_yr'] as $candidate) {
            if ($this->hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
