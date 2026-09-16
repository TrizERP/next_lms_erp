<?php

namespace App\Domain\AI\Templates;

use App\Services\Mcp\AiTemplateService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where generated reports are stored and read back.
 *
 * One place, because three callers need the same answer: the generator writes, the
 * report page reads and saves edits, and the refresh path does both. They used to
 * reach `template_master` directly and each had its own copy of the tenant scoping.
 *
 * TWO TABLES, ON PURPOSE AND TEMPORARILY
 *
 * New reports go to `ai_generated_reports`, which allows many rows per school.
 * Reports written before that table existed are still in `template_master` under the
 * assistant's `AI` category, where the unique key allowed exactly one per school — see
 * `2026_09_14_000004_create_ai_generated_reports_table` for why that was a ceiling
 * rather than a design.
 *
 * `find()` therefore looks in the new table first and falls back to the old one, so a
 * `/ai-reports/{id}` link that predates this change still opens. Ids from the two
 * tables can collide, which is why the new table is checked first and the legacy row is
 * only consulted when nothing matched — a new report always wins its own id.
 *
 * TENANT SCOPE IS THE AUTHORISATION CHECK
 *
 * Every read and write is filtered by the institute on the caller's context. A report
 * belonging to another school is not "forbidden" — from this caller's position it does
 * not exist, which is what stops the id being an oracle.
 */
class GeneratedReportStore
{
    public const TABLE = 'ai_generated_reports';

    /**
     * Save a freshly generated report.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(McpRequestContext $context, array $attributes): int
    {
        return (int) DB::table(self::TABLE)->insertGetId([
            'sub_institute_id' => $context->selectedInstituteId,
            'client_id' => $context->clientId,
            'module_key' => mb_substr((string) ($attributes['module_key'] ?? ''), 0, 60),
            'layout_template_id' => $attributes['layout_template_id'] ?? null,
            'title' => mb_substr((string) ($attributes['title'] ?? 'Report'), 0, 250),
            'html_content' => (string) ($attributes['html_content'] ?? ''),
            'question' => $this->nullable($attributes['question'] ?? null),
            'source_tool' => $this->nullable($attributes['source_tool'] ?? null),
            'arguments' => isset($attributes['arguments']) && $attributes['arguments'] !== []
                ? json_encode($attributes['arguments'], JSON_UNESCAPED_SLASHES)
                : null,
            'row_count' => (int) ($attributes['row_count'] ?? 0),
            'status' => 1,
            'created_by' => $context->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * One report, from whichever table holds it.
     *
     * The returned object is normalised to the shape both tables' callers already
     * expect — `id`, `title`, `html_content`, `created_on` — plus `legacy`, so an
     * update knows which table to write back to.
     */
    public function find(McpRequestContext $context, int $id): ?object
    {
        if (Schema::hasTable(self::TABLE)) {
            $row = DB::table(self::TABLE)
                ->where('id', $id)
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->first();

            if ($row !== null) {
                return (object) [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'html_content' => (string) $row->html_content,
                    'created_on' => $row->created_at,
                    'module_key' => (string) $row->module_key,
                    'layout_template_id' => $row->layout_template_id === null ? null : (int) $row->layout_template_id,
                    'legacy' => false,
                ];
            }
        }

        return $this->findLegacy($context, $id);
    }

    /** Replace a report's HTML — the report page's Save, and the refresh path. */
    public function updateHtml(McpRequestContext $context, int $id, string $html, bool $legacy = false): void
    {
        if ($legacy) {
            DB::table('template_master')
                ->where('id', $id)
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->update(['html_content' => $html]);

            return;
        }

        DB::table(self::TABLE)
            ->where('id', $id)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->update(['html_content' => $html, 'updated_at' => now()]);
    }

    /** Title and body together — the report page's Save. */
    public function save(
        McpRequestContext $context,
        int $id,
        string $title,
        string $html,
        bool $legacy = false
    ): void {
        $columns = ['title' => mb_substr($title, 0, 250), 'html_content' => $html];

        if ($legacy) {
            DB::table('template_master')
                ->where('id', $id)
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->update($columns);

            return;
        }

        DB::table(self::TABLE)
            ->where('id', $id)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->update($columns + ['updated_at' => now()]);
    }

    /**
     * A school's reports, newest first — the backing list for a reports index.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(McpRequestContext $context, int $limit = 50): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('status', 1)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'title', 'module_key', 'row_count', 'source_tool', 'created_at'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'module_key' => (string) $row->module_key,
                'row_count' => (int) $row->row_count,
                'source_tool' => $row->source_tool,
                'created_at' => $row->created_at,
                'link' => '/ai-reports/' . $row->id,
            ])
            ->all();
    }

    /** A report written before `ai_generated_reports` existed. */
    private function findLegacy(McpRequestContext $context, int $id): ?object
    {
        $row = DB::table('template_master')
            ->where('id', $id)
            ->where('module_name', AiTemplateService::AI_MODULE)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->first();

        if ($row === null) {
            return null;
        }

        return (object) [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'html_content' => (string) $row->html_content,
            'created_on' => $row->created_on,
            'module_key' => '',
            'layout_template_id' => null,
            'legacy' => true,
        ];
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
