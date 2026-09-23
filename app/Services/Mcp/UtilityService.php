<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Utility module — which in this ERP is bulk data operations, not utilities.
 *
 * READ THIS FIRST, BECAUSE THE NAME IS MISLEADING
 *
 * "Utility" here does not mean electricity, water, gas, meter readings, consumption or
 * utility bills. This estate holds NO table for any of those — searched for as `utility%`,
 * `meter%`, `electric%`, `water%` and `consumption%`, and the only match is
 * `transport_kilometer_rate`, which belongs to Transport.
 *
 * What `/Utility` actually is, from `app/Utility/page.tsx`:
 *
 *   · Student transfer — move students to another institute of the same client
 *   · Rollover — copy master data and enrolments into the next academic year
 *   · Breakoff rollover — the fee-breakoff half of the same
 *   · Update all data — bulk field updates
 *   · Custom module — define a new table and its columns from the UI
 *
 * These are operations a person performs, not records a person keeps, which shapes the
 * whole module: there is very little to READ here, and what there is describes what the
 * tools operate ON rather than what they have DONE.
 *
 * NOTHING RECORDS THAT AN OPERATION RAN
 *
 * No rollover log, no transfer log, no audit row, no timestamp of a bulk update anywhere.
 * `s_mobility_transfers` exists but holds zero rows estate-wide and is an HR staff-movement
 * table rather than the student transfer these screens perform.
 *
 * So nothing here may say a rollover has or has not been run, how many students were moved,
 * when a bulk update happened, or whether the next academic year is ready. Those are the
 * questions somebody will ask, and this module's whole job is to answer them with "that is
 * not recorded" rather than with a number.
 *
 * WHAT IS READABLE
 *
 * The custom modules somebody has defined, with their columns — real rows in
 * `custom_module_tables` and `custom_module_table_columns`. And the academic years this
 * institute actually has data for, which is what a rollover would be FROM and TO.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token. `custom_module_table_columns` has no
 * institute column of its own — it hangs off `table_id`, so it is only ever reached
 * through a parent row already scoped to the caller's institute.
 */
class UtilityService
{
    /**
     * Custom modules defined from the Utility screens, with their columns.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function customModules(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('custom_module_tables')) {
            return ['count' => 0, 'custom_modules' => [], 'note' => 'Custom modules are not available in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('custom_module_tables')
            ->where('sub_institute_id', $context->selectedInstituteId);

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('module_name', 'like', $needle)->orWhere('table_name', 'like', $needle);
            });
        }

        $moduleType = trim((string) ($filters['module_type'] ?? ''));

        if ($moduleType !== '') {
            $query->where('module_type', $moduleType);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy('module_name')
            ->limit($limit)
            ->get(['id', 'table_name', 'module_name', 'module_type', 'display_under', 'relational_table',
                'access_link', 'level_2', 'syear_wise', 'created_at', 'updated_at']);

        $columns = $this->columnsByTable($rows->pluck('id')->map(static fn ($id) => (int) $id)->all());

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'figures_cover' => 'every custom module matching these filters, not only the rows listed',
            'custom_modules' => $rows->map(static function ($row) use ($columns) {
                $own = $columns[(int) $row->id] ?? [];

                return [
                    'custom_module_id' => (int) $row->id,
                    'module_name' => $row->module_name,
                    'table_name' => $row->table_name,
                    'module_type' => $row->module_type ?: null,
                    'display_under' => $row->display_under ?: null,
                    'relational_table' => $row->relational_table ?: null,
                    'year_wise' => ! empty($row->syear_wise),
                    'column_count' => count($own),
                    'columns' => $own,
                    'defined_on' => $row->created_at,
                    'last_changed_on' => $row->updated_at,
                ];
            })->all(),
            'rule' => 'One row is a table somebody defined from the custom-module screen. These are '
                .'DEFINITIONS, not data: nothing here says how many records the resulting table holds, '
                .'whether anybody uses it, or whether it was ever deployed. This module records no history '
                .'of any operation it performs.',
        ];
    }

    /**
     * What a rollover or a transfer would operate on: the years and the sibling institutes.
     *
     * Deliberately not called `rollover_history`. There is no history — see the class note.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function rolloverScope(McpRequestContext $context, array $filters): array
    {
        $institute = $context->selectedInstituteId;

        $years = [];

        if (Schema::hasTable('tblstudent_enrollment')) {
            $years = DB::table('tblstudent_enrollment')
                ->where('sub_institute_id', $institute)
                ->whereNotNull('syear')
                ->selectRaw('syear, COUNT(*) AS enrolments')
                ->groupBy('syear')
                ->orderByDesc('syear')
                ->limit(20)
                ->get()
                ->map(static fn ($row) => [
                    'academic_year' => (int) $row->syear,
                    'enrolments_recorded' => (int) $row->enrolments,
                ])
                ->all();
        }

        // The institutes a student transfer could target: the caller's own client, which
        // is the rule `studentTransferController` applies —
        //
        //     $from_client_id = $from_institute_details[0]['client_id'];
        //     school_setupModel::where(['client_id' => $from_client_id])->get()
        //
        // resolved from the caller's own institute row rather than named here. The table
        // is `school_setup`, whose primary key `Id` IS the sub-institute id and whose name
        // column is `SchoolName` — it does not carry a `sub_institute_id` column of its
        // own, which is worth knowing before writing a query against it.
        $siblings = [];

        if (Schema::hasTable('school_setup')) {
            $clientId = DB::table('school_setup')->where('Id', $institute)->value('client_id');

            if ($clientId !== null && $clientId !== '') {
                $siblings = DB::table('school_setup')
                    ->where('client_id', $clientId)
                    ->where('Id', '<>', $institute)
                    ->orderBy('SchoolName')
                    ->limit(100)
                    ->get(['Id', 'SchoolName'])
                    ->map(static fn ($row) => [
                        'sub_institute_id' => (int) $row->Id,
                        'name' => $row->SchoolName,
                    ])
                    ->all();
            }
        }

        return [
            'current_institute' => $institute,
            'current_academic_year' => $context->academicYear,
            'academic_years_with_enrolments' => $years,
            'transfer_targets_in_the_same_client' => $siblings,
            'transfer_target_count' => count($siblings),
            'rule' => 'This describes what a rollover or a student transfer WOULD operate on, and nothing '
                .'about what has been done. THIS MODULE RECORDS NO OPERATION HISTORY: no rollover log, no '
                .'transfer log, no bulk-update audit row exists anywhere in this estate. Never state that '
                .'a rollover has been run or not run, how many students were moved, when anything last '
                .'happened, or whether the next year is ready — none of it is recorded. A year appearing '
                .'here means enrolments exist against it, which is not the same as the year having been '
                .'rolled over. Transfers are limited to institutes of the same client, which is what this '
                .'list is.',
        ];
    }

    /**
     * The columns defined against each custom module.
     *
     * Reached only through parent ids already scoped to the caller's institute —
     * `custom_module_table_columns` carries no institute column of its own.
     *
     * @param  array<int, int>  $tableIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function columnsByTable(array $tableIds): array
    {
        if ($tableIds === [] || ! Schema::hasTable('custom_module_table_columns')) {
            return [];
        }

        $byTable = [];

        foreach (DB::table('custom_module_table_columns')
            ->whereIn('table_id', $tableIds)
            ->orderBy('id')
            ->limit(500)
            ->get(['table_id', 'column_name', 'type', 'length', 'not_null', 'field_type']) as $row) {
            $byTable[(int) $row->table_id][] = [
                'column_name' => $row->column_name,
                'type' => $row->type,
                'length' => $row->length === null || $row->length === '' ? null : (int) $row->length,
                'required' => ! empty($row->not_null),
                'field_type' => $row->field_type ?: null,
            ];
        }

        return $byTable;
    }
}
