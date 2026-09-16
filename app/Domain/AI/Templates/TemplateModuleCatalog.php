<?php

namespace App\Domain\AI\Templates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The modules a template can belong to — the list behind the module selector.
 *
 * WHY IT READS `ai_modules` RATHER THAN A CONSTANT
 *
 * `AiModuleRegistry`, the other registry in this codebase with a similar name, lists
 * the *AI* modules an administrator points at a provider: Conversational AI,
 * Generative AI, Agent Reasoning. That is a different question. This one lists the
 * *product* modules a template is written for — Fees, Attendance, Admissions, Course
 * Catalog — and those already exist as rows in `ai_modules`, where the workspace
 * resolves them from a route.
 *
 * Reading the table is what makes the eighth requirement true: a module added to
 * `ai_modules` later appears in this selector, with its own label and icon, and
 * Template Management does not change. A hard-coded list here would have to be edited
 * every time, and would drift from the list the workspace actually routes against —
 * so an administrator could write templates for a module the runtime cannot resolve.
 *
 * THE SHARED ENTRY
 *
 * `null` is a real choice, not a missing one. `k12.analyse.list` and `k12.assist.form`
 * are templates every module uses, and filing them under a module would either hide
 * them from the other thirty-seven or need thirty-eight copies. The catalogue surfaces
 * that as an explicit "Shared — every module" row so the selector can offer it.
 */
class TemplateModuleCatalog
{
    /** The value the API and the UI use for "not one module — all of them". */
    public const SHARED = '__shared__';

    /**
     * Every module a template can be filed under, shared first.
     *
     * @return array<int, array{key:string, label:string, description:?string, icon:?string, shared:bool}>
     */
    public function all(int|string|null $subInstituteId = null): array
    {
        $modules = [[
            'key' => self::SHARED,
            'label' => 'Shared — every module',
            'description' => 'Templates that apply across the product, such as page and record analysis.',
            'icon' => 'layers',
            'shared' => true,
        ]];

        if (! Schema::hasTable('ai_modules')) {
            return $modules;
        }

        $rows = DB::table('ai_modules')
            ->where('status', 1)
            ->where(function ($inner) use ($subInstituteId) {
                $inner->whereNull('sub_institute_id');

                if ($subInstituteId !== null && $subInstituteId !== '') {
                    $inner->orWhere('sub_institute_id', $subInstituteId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['module_key', 'label', 'description', 'icon', 'sub_institute_id']);

        // A tenant row and a platform row can name the same module. The tenant's label
        // wins, because it is the one that school chose; without this the selector
        // would show the same module twice under two names.
        $seen = [];

        foreach ($rows as $row) {
            $key = (string) $row->module_key;

            if (isset($seen[$key]) && $row->sub_institute_id === null) {
                continue;
            }

            $seen[$key] = [
                'key' => $key,
                'label' => (string) $row->label,
                'description' => $row->description === null ? null : (string) $row->description,
                'icon' => $row->icon === null ? null : (string) $row->icon,
                'shared' => false,
            ];
        }

        return array_merge($modules, array_values($seen));
    }

    /** @return array<int, string> Real module keys, excluding the shared sentinel. */
    public function keys(int|string|null $subInstituteId = null): array
    {
        return array_values(array_filter(
            array_column($this->all($subInstituteId), 'key'),
            fn (string $key) => $key !== self::SHARED
        ));
    }

    public function exists(string $key, int|string|null $subInstituteId = null): bool
    {
        return $key === self::SHARED || in_array($key, $this->keys($subInstituteId), true);
    }

    public function label(?string $key, int|string|null $subInstituteId = null): string
    {
        if ($key === null || $key === self::SHARED) {
            return 'Shared — every module';
        }

        foreach ($this->all($subInstituteId) as $module) {
            if ($module['key'] === $key) {
                return $module['label'];
            }
        }

        // A template filed under a module that has since been retired. Showing the raw
        // key is better than showing nothing: it is still findable and still editable.
        return $key;
    }

    /**
     * The sentinel maps to NULL in the column, and an empty string does too.
     *
     * One place does this conversion so the controller never has to remember which of
     * the three spellings of "no module" it is holding.
     */
    public function toColumn(?string $key): ?string
    {
        if ($key === null || $key === '' || $key === self::SHARED) {
            return null;
        }

        return $key;
    }

    /** The inverse: a NULL column reads back to the selector as the shared entry. */
    public function fromColumn(?string $column): string
    {
        return $column === null || $column === '' ? self::SHARED : $column;
    }
}
