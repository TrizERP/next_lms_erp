<?php

namespace App\Domain\AI\Templates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finds the published report layout for a module.
 *
 * This is the step that turns "the chatbot was asked about fees" into "use the Pending
 * Fees Report the school configured". It is deliberately the only place that decision
 * is made, so the assistant, the refresh path and anything added later all resolve the
 * same template for the same module.
 *
 * RESOLUTION ORDER MIRRORS `TemplateRegistry`
 *
 * A school's own template beats the platform baseline, and a higher version beats a
 * lower one. That is the same order the prompt side already resolves in, and it has to
 * be: an administrator who overrides the Fees layout expects the override to be what
 * the assistant renders, exactly as they would for a prompt.
 *
 * NO TEMPLATE IS A NORMAL ANSWER
 *
 * Most modules will have no report layout on day one. `find()` returning null is not an
 * error — it means the generator falls back to the table it has always composed, so a
 * module keeps working exactly as it did before Template Management knew about reports.
 * Publishing a layout is what changes the output, and nothing has to be migrated for it
 * to take effect.
 */
class ReportTemplateResolver
{
    public const KIND = 'report';

    public function __construct(private readonly ReportDataSourceCatalog $sources)
    {
    }

    /**
     * The report layout a module should be rendered with, or null when it has none.
     *
     * @return object|null The `ai_templates` row.
     */
    public function find(string $moduleKey, int|string|null $institute): ?object
    {
        if (! $this->available()) {
            return null;
        }

        return DB::table('ai_templates')
            ->where('kind', self::KIND)
            ->where('status', 'published')
            ->where('module_key', $moduleKey)
            ->whereNotNull('html_layout')
            ->where('html_layout', '!=', '')
            ->where(function ($inner) use ($institute) {
                $inner->whereNull('sub_institute_id');

                if ($institute !== null && $institute !== '') {
                    $inner->orWhere('sub_institute_id', $institute);
                }
            })
            // `sub_institute_id IS NULL ASC` puts the school's own row first, because
            // false (0) sorts before true (1). Same ordering as TemplateRegistry.
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->orderByDesc('version')
            ->first();
    }

    /** One specific layout by id, still scoped so another school's row is unreachable. */
    public function findById(int $id, int|string|null $institute): ?object
    {
        if (! $this->available()) {
            return null;
        }

        return DB::table('ai_templates')
            ->where('id', $id)
            ->where('kind', self::KIND)
            ->where(function ($inner) use ($institute) {
                $inner->whereNull('sub_institute_id');

                if ($institute !== null && $institute !== '') {
                    $inner->orWhere('sub_institute_id', $institute);
                }
            })
            ->first();
    }

    /**
     * Every module that has a usable report layout published.
     *
     * "Usable" means the layout names a data source that is still registered and still
     * read-only. A template bound to a tool that has since been renamed is listed
     * nowhere and reported as unsupported, rather than failing at the moment somebody
     * asks for the report.
     *
     * @return array<int, string>
     */
    public function modulesWithLayouts(int|string|null $institute): array
    {
        if (! $this->available()) {
            return [];
        }

        $rows = DB::table('ai_templates')
            ->where('kind', self::KIND)
            ->where('status', 'published')
            ->whereNotNull('module_key')
            ->whereNotNull('data_source')
            ->where(function ($inner) use ($institute) {
                $inner->whereNull('sub_institute_id');

                if ($institute !== null && $institute !== '') {
                    $inner->orWhere('sub_institute_id', $institute);
                }
            })
            ->get(['module_key', 'data_source']);

        $modules = [];

        foreach ($rows as $row) {
            if ($this->sources->isBindable((string) $row->data_source)) {
                $modules[(string) $row->module_key] = true;
            }
        }

        return array_keys($modules);
    }

    /**
     * The argument map a layout hands its data source, decoded.
     *
     * @return array<string, mixed>
     */
    public function arguments(object $template): array
    {
        $raw = $template->data_arguments ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Whether the report columns of `ai_templates` exist yet.
     *
     * Guarded rather than assumed because the generator is reached from the assistant
     * on every estate, including one that has not run
     * `2026_09_14_000003_add_report_layout_to_ai_templates`. There the answer is simply
     * "no layouts", and the old composed table is still produced.
     */
    private function available(): bool
    {
        return Schema::hasTable('ai_templates')
            && Schema::hasColumn('ai_templates', 'kind')
            && Schema::hasColumn('ai_templates', 'html_layout');
    }
}
