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

    /**
     * Full journey for one module, with every step resolved to a status.
     *
     * @return array{module: array, steps: array, summary: array}
     */
    public function moduleJourney(OnboardingModuleModel $module, int $subInstituteId, int $syear): array
    {
        $steps = OnboardingStepModel::where('module_id', $module->id)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->get();

        $progress = $this->progressIndex($subInstituteId, $syear, $module->id);

        $resolved = [];
        foreach ($steps as $step) {
            $resolved[] = $this->resolveStep($step, $subInstituteId, $syear, $progress[$step->id] ?? null);
        }

        return [
            'module' => $this->presentModule($module),
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
        $modules = OnboardingModuleModel::forTenant($subInstituteId);
        if ($modules->isEmpty()) {
            return ['modules' => [], 'summary' => $this->summarise([])];
        }

        $moduleIds = $modules->pluck('id')->all();

        $stepsByModule = OnboardingStepModel::whereIn('module_id', $moduleIds)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('module_id');

        $progress = $this->progressIndex($subInstituteId, $syear, $moduleIds);

        $out = [];
        $all = [];
        foreach ($modules as $module) {
            $resolved = [];
            foreach ($stepsByModule->get($module->id, collect()) as $step) {
                $resolved[] = $this->resolveStep($step, $subInstituteId, $syear, $progress[$step->id] ?? null);
            }
            $all = array_merge($all, $resolved);

            $summary = $this->summarise($resolved);
            $out[] = $this->presentModule($module) + [
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
        ?OnboardingProgressModel $record
    ): array {
        $derived = $this->deriveProof($step, $subInstituteId, $syear);
        $status = $this->applyManualOverride($step, $derived, $record);

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
                'youtube_link' => $step->help_youtube_link,
                'pdf_link' => $step->help_pdf_link,
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

    private function presentModule(OnboardingModuleModel $module): array
    {
        return [
            'id' => (int) $module->id,
            'module_key' => $module->module_key,
            'module_name' => $module->module_name,
            'menu_title' => $module->menu_title,
            'description' => $module->description,
            'icon' => $module->icon,
            'sort_order' => (int) $module->sort_order,
            'is_tenant_override' => (int) $module->sub_institute_id !== 0,
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
