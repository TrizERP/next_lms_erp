<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes "which module does this template belong to" a column instead of a convention.
 *
 * WHY
 *
 * Until now a template's module could only be inferred two ways, both indirect:
 *
 *   1. By reading `template_key` and hoping — `k12.fees.pending_summary` looks like
 *      Fees, but `k12.course_catalog_summary` belongs to `course-master` and says so
 *      nowhere, and `k12.analyse.list` belongs to every module at once.
 *   2. By joining `ai_suggestions.action_ref` back to `template_key`, which only finds
 *      templates somebody has already surfaced in a module panel.
 *
 * Neither can answer the question Template Management is built around — "show me the
 * templates for Fees" — without guessing. A nullable column answers it directly, and
 * NULL keeps its own meaning: a shared template that serves every module, which is
 * what the `k12.analyse.*` and `k12.assist.*` rows genuinely are.
 *
 * BACKFILL
 *
 * Existing rows are filled from `ai_suggestions` first, because a binding somebody
 * made deliberately beats a string parsed out of a key. Only then does it fall back to
 * the second segment of `template_key`, and only when that segment is a real
 * `ai_modules` row — so `analyse` and `assist` are left NULL rather than inventing two
 * modules that do not exist.
 *
 * Run it on its own — `php artisan migrate` must never be run bare on this project:
 *
 *   php artisan migrate --path=database/migrations/2026_09_14_000001_add_module_key_to_ai_templates.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        if (! Schema::hasColumn('ai_templates', 'module_key')) {
            Schema::table('ai_templates', function (Blueprint $table) {
                // Nullable and NULL by default: every existing row is shared until the
                // backfill below can show otherwise. A default of 'general' would have
                // claimed a module for thirteen templates on the strength of nothing.
                $table->string('module_key', 60)->nullable()->after('domain')->index();
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_templates') || ! Schema::hasColumn('ai_templates', 'module_key')) {
            return;
        }

        Schema::table('ai_templates', function (Blueprint $table) {
            $table->dropIndex(['module_key']);
            $table->dropColumn('module_key');
        });
    }

    /**
     * Fill the new column for templates that already exist.
     *
     * Deliberately only touches rows where `module_key` is still NULL, so a re-run
     * never overwrites a module an administrator has since chosen by hand.
     */
    private function backfill(): void
    {
        $moduleKeys = Schema::hasTable('ai_modules')
            ? DB::table('ai_modules')->pluck('module_key')->map(fn ($key) => (string) $key)->all()
            : [];

        // template_key => module_key, from bindings somebody made on purpose.
        $bound = Schema::hasTable('ai_suggestions')
            ? DB::table('ai_suggestions')
                ->where('action_type', 'generate')
                ->whereNotNull('action_ref')
                ->pluck('module_key', 'action_ref')
                ->all()
            : [];

        $rows = DB::table('ai_templates')
            ->whereNull('module_key')
            ->get(['id', 'template_key']);

        foreach ($rows as $row) {
            $key = (string) $row->template_key;

            $module = $bound[$key] ?? $this->fromKey($key, $moduleKeys);

            if ($module === null) {
                continue;
            }

            DB::table('ai_templates')->where('id', $row->id)->update(['module_key' => $module]);
        }
    }

    /**
     * The second segment of `k12.fees.pending_summary`, but only if a module by that
     * name actually exists. Returns null for anything else — a wrong module is worse
     * than none, because none is visibly shared and wrong is invisibly misfiled.
     *
     * @param array<int, string> $moduleKeys
     */
    private function fromKey(string $templateKey, array $moduleKeys): ?string
    {
        $segments = explode('.', $templateKey);

        if (count($segments) < 3) {
            return null;
        }

        $candidate = $segments[1];

        return in_array($candidate, $moduleKeys, true) ? $candidate : null;
    }
};
