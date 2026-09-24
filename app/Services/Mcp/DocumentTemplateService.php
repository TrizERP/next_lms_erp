<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Document templates, as `document_templates` and `document_template_versions` record them.
 *
 * ONE ROW IS ONE TEMPLATE A DOCUMENT CAN BE GENERATED FROM
 *
 * `document_templates` carries the name, category, description, content, version and a
 * draft/published status, scoped by institute and academic year. Each saved revision lands
 * in `document_template_versions`.
 *
 * THE TABLES ARE EMPTY ACROSS THIS ENTIRE ESTATE
 *
 * Zero rows in both, for every institute. That is a real answer and this module gives it
 * plainly: no school has created a template yet. What it must never do is fill the gap —
 * an empty template list is an empty template list, not a reason to describe templates
 * that a school might want.
 *
 * It also means every figure here will read as zero for a while, and the payload says so
 * rather than leaving a reader to conclude the tool is broken.
 *
 * THE CONTENT IS NOT RETURNED, ONLY MEASURED
 *
 * A template's `content` is the document body, and it is the largest column on the table.
 * Returning it would put a whole letter into every list read, and a list is not where a
 * document belongs. So the reads report whether content exists and how long it is, plus
 * the merge fields found in it, and the body itself stays on the template screen.
 *
 * MERGE FIELDS ARE PARSED, NOT INVENTED
 *
 * `merge_fields` is extracted from the stored content with a regular expression over the
 * `{{ ... }}` placeholders actually present. A field that is not in the content is not in
 * the list, which is the point: "which variables does this template use" has to be
 * answered from the template.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read, and `syear` where the caller
 * carries an academic year. Versions are reached only through a parent template already
 * scoped to the caller's institute.
 */
class DocumentTemplateService
{
    /** How many characters of a template body are measured but never returned. */
    private const MAX_MERGE_FIELDS = 40;

    /**
     * Templates, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('document_templates')) {
            return ['count' => 0, 'templates' => [], 'note' => 'Document templates are not available in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        $status = trim((string) ($filters['status'] ?? ''));

        if ($status !== '') {
            $query->where('t.status', $status);
        }

        $category = trim((string) ($filters['category'] ?? ''));

        if ($category !== '') {
            $query->where('t.category', 'like', '%'.$category.'%');
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('t.name', 'like', $needle)->orWhere('t.description', 'like', $needle);
            });
        }

        // Counted over the whole filtered set, like every other figure here.
        $total = (clone $query)->count();

        $withoutContent = (clone $query)
            ->where(static function ($inner): void {
                $inner->whereNull('t.content')->orWhere('t.content', '');
            })
            ->count();

        $byStatus = (clone $query)
            ->selectRaw("CASE WHEN t.status IS NULL OR TRIM(t.status) = '' THEN '(no status recorded)' "
                .'ELSE t.status END AS status, COUNT(*) AS templates')
            ->groupByRaw("CASE WHEN t.status IS NULL OR TRIM(t.status) = '' THEN '(no status recorded)' "
                .'ELSE t.status END')
            ->orderByDesc('templates')
            ->limit(20)
            ->get()
            ->map(static fn ($row) => ['status' => $row->status, 'templates' => (int) $row->templates])
            ->all();

        $rows = $query
            ->selectRaw("t.id, t.name, t.category, t.description, t.status, t.version, t.syear,
                t.created_at, t.updated_at, t.content,
                CONCAT_WS(' ', c.first_name, c.middle_name, c.last_name) AS created_by_name,
                CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS updated_by_name")
            ->orderByDesc('t.updated_at')
            ->orderByDesc('t.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'without_content' => $withoutContent,
            'by_status' => $byStatus,
            'figures_cover' => 'every template matching these filters, not only the rows listed',
            'templates' => $rows->map(fn ($row) => $this->map($row))->all(),
            'rule' => $this->rule($total),
        ];
    }

    /**
     * The saved revisions of one template.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function versions(McpRequestContext $context, array $arguments): array
    {
        if (! Schema::hasTable('document_template_versions')) {
            return ['found' => false, 'note' => 'Template versions are not available in this estate.'];
        }

        $templateId = (int) ($arguments['template_id'] ?? 0);

        if ($templateId < 1) {
            return ['found' => false, 'note' => 'A template id is required.'];
        }

        // Scoped through the parent, so a version read cannot reach a template this
        // caller's institute does not own.
        $template = $this->query($context)->where('t.id', $templateId)->first(['t.id', 't.name', 't.status', 't.version']);

        if ($template === null) {
            return [
                'found' => false,
                'template_id' => $templateId,
                // The same answer for "belongs to another school" as for "does not
                // exist", so an id probe discloses nothing.
                'note' => 'No document template with that id in this institute.',
            ];
        }

        $limit = min(max((int) ($arguments['limit'] ?? 20), 1), 100);

        $rows = DB::table('document_template_versions as v')
            ->leftJoin('tbluser as u', function ($join) use ($context) {
                $join->on('u.id', '=', 'v.created_by')->where('u.sub_institute_id', '=', $context->selectedInstituteId);
            })
            ->where('v.document_template_id', $templateId)
            ->where('v.sub_institute_id', $context->selectedInstituteId)
            ->orderByDesc('v.version')
            ->limit($limit)
            ->get([
                'v.id', 'v.version', 'v.name', 'v.created_at', 'v.content',
                DB::raw("CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS saved_by_name"),
            ]);

        return [
            'found' => true,
            'template_id' => $templateId,
            'template_name' => $template->name,
            'current_version' => $template->version === null ? null : (int) $template->version,
            'status' => $template->status,
            'version_count' => $rows->count(),
            'versions' => $rows->map(function ($row) {
                $content = (string) ($row->content ?? '');

                return [
                    'version' => $row->version === null ? null : (int) $row->version,
                    'name' => $row->name,
                    'saved_on' => $row->created_at,
                    'saved_by' => trim((string) ($row->saved_by_name ?? '')) ?: null,
                    'content_present' => trim($content) !== '',
                    'content_length' => mb_strlen($content),
                    'merge_fields' => $this->mergeFields($content),
                ];
            })->all(),
            'rule' => 'One row is one saved revision. The document body itself is measured and its merge '
                .'fields listed, but is never returned here — a template is read on the template screen. '
                .'A version is a save, not an approval: this table records no reviewer and no sign-off.',
        ];
    }

    /** The rule every template answer carries, including when there is nothing to answer about. */
    private function rule(int $total): string
    {
        $rule = 'One row is one document template. The document body is never returned — only whether it '
            .'exists, how long it is, and which `{{merge_field}}` placeholders it actually contains, '
            .'parsed from the stored content rather than assumed. A template\'s status is what the row '
            .'holds; this table records no reviewer, no approval and no publish history, so never say a '
            .'template was approved, by whom, or when it went live. Never invent a template, a category '
            .'or a merge field, and never describe templates a school might want.';

        if ($total === 0) {
            $rule .= ' NO TEMPLATES ARE RECORDED for this institute and these filters. Say exactly that. '
                .'It is a complete and correct answer, and it is not a prompt to suggest what a school '
                .'could create.';
        }

        return $rule;
    }

    /** The template join, scoped at every hop that carries an institute. */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('document_templates as t')
            ->leftJoin('tbluser as c', function ($join) use ($institute) {
                $join->on('c.id', '=', 't.created_by')->where('c.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as e', function ($join) use ($institute) {
                $join->on('e.id', '=', 't.updated_by')->where('e.sub_institute_id', '=', $institute);
            })
            ->where('t.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            // A template row may predate the year column being filled, so a null year is
            // kept rather than filtered away with the other years.
            $query->where(function ($inner) use ($context): void {
                $inner->where('t.syear', $context->academicYear)->orWhereNull('t.syear');
            });
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        $content = (string) ($row->content ?? '');

        return [
            'template_id' => (int) $row->id,
            'name' => $row->name,
            'category' => $row->category,
            'description' => $row->description,
            'status' => $row->status,
            'version' => $row->version === null ? null : (int) $row->version,
            // Measured, never returned. See the class note.
            'content_present' => trim($content) !== '',
            'content_length' => mb_strlen($content),
            'merge_fields' => $this->mergeFields($content),
            'academic_year' => $row->syear === null ? null : (int) $row->syear,
            // Null when the author is not of this institute — a record to correct, not a
            // name to borrow from elsewhere.
            'created_by' => trim((string) ($row->created_by_name ?? '')) ?: null,
            'last_changed_by' => trim((string) ($row->updated_by_name ?? '')) ?: null,
            'created_on' => $row->created_at,
            'last_changed_on' => $row->updated_at,
        ];
    }

    /**
     * The `{{merge_field}}` placeholders actually present in a template body.
     *
     * Parsed, never guessed: a field absent from the content is absent from the list, which
     * is what makes "which variables does this template use" answerable at all.
     *
     * @return array<int, string>
     */
    private function mergeFields(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        preg_match_all('/\{\{\s*([A-Za-z0-9_.\-]+)\s*\}\}/', $content, $matches);

        $fields = array_values(array_unique($matches[1] ?? []));
        sort($fields);

        // Bounded, because a long document can repeat a great many and a list is not where
        // a document belongs.
        return array_slice($fields, 0, self::MAX_MERGE_FIELDS);
    }
}
