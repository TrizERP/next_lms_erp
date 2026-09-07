<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;

/**
 * Turns a question into a saved report, filed under the assistant's template category.
 *
 * Deliberately not a second template system. `template_master` already holds the
 * school's documents keyed by `module_name`, `AiTemplateService` already claims the
 * "AI" category within it, and Settings -> Templates already lists and edits whatever is
 * in there. All this adds is a **write** path: the library could be read by the
 * assistant but never written to, so a generated report had nowhere to live.
 *
 * Three properties it is built around:
 *
 *   1. **The rows are real, and fetched by the same services the tools use.** Fees
 *      arrears come from `FeesArrearsService`, enquiries from `AdmissionMcpService`,
 *      attendance from `AttendanceInsightService` — the same scoping, joins and field
 *      names the rest of the assistant works with. A report that queried independently
 *      would be a second opinion about the school, and the two would drift.
 *   2. **The HTML is composed from those rows, never generated.** A model writing a
 *      table of student names is a model that can invent a student. Prose around the
 *      table can be generated afterwards by the editor; the figures cannot.
 *   3. **Nothing is evaluated, and every value is escaped.** This content is editable by
 *      an administrator and rendered back into a page, which is the shape of an XSS bug
 *      if a student's name is ever allowed to be markup.
 */
class AiReportGenerator
{
    /** The modules a report can be generated for, each with a service that feeds it. */
    public const SUPPORTED = ['fees', 'admissions', 'attendance'];

    /** Eight columns is what prints across a page without wrapping into unreadability. */
    private const MAX_COLUMNS = 8;

    /**
     * The attribute that marks the generated figures inside a saved report.
     *
     * `template_master` has no metadata column — id, tenant, module, title, html,
     * status, author, date, and nothing else — so a saved report has nowhere to
     * record what produced it except the document itself. That turns out to be the
     * right place rather than a workaround: the provenance line is already written
     * into the HTML for the person reading a printout, and this is the same fact in
     * a form the refresh path can read back.
     *
     * The container is also what makes editing and regenerating coexist. Refreshing
     * replaces the contents of this element and nothing else, so the prose an
     * administrator writes around the table survives a refresh, and the figures
     * inside it are never the thing being preserved.
     */
    private const MARKER = 'data-ai-report';

    /** A ceiling on the query echoed into the document, so one attribute cannot carry a payload. */
    private const MAX_ARGS_BYTES = 2000;

    public function __construct(
        private readonly FeesArrearsService $fees,
        private readonly AdmissionMcpService $admissions,
        private readonly AttendanceInsightService $attendance,
    ) {
    }

    /**
     * Build a report for one module and save it to the template library.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function generate(McpRequestContext $context, array $arguments): array
    {
        $module = mb_strtolower(trim((string) ($arguments['module'] ?? '')));
        $question = trim((string) ($arguments['question'] ?? ''));

        if (! in_array($module, self::SUPPORTED, true)) {
            return ToolResult::failure(
                'ai.templates.generate',
                sprintf(
                    'Reports can be generated for %s. "%s" is not one of them.',
                    implode(', ', self::SUPPORTED),
                    $module === '' ? '(nothing named)' : $module
                ),
                'unsupported_module'
            );
        }

        $data = $this->rowsFor($module, $context, $arguments);

        // An empty result is an answer, not a failure — but it is not a report either.
        // Saving a document that says "no rows" clutters the library with something
        // nobody opens twice.
        if ($data['rows'] === []) {
            return ToolResult::success(
                'ai.templates.generate',
                sprintf('No %s records matched, so no report was created.', $module),
                ['module' => $module, 'row_count' => 0, 'template_id' => null]
            );
        }

        $title = $this->titleFor($module, $question, count($data['rows']));

        $id = (int) DB::table('template_master')->insertGetId([
            'sub_institute_id' => $context->selectedInstituteId,
            'module_name' => AiTemplateService::AI_MODULE,
            'title' => mb_substr($title, 0, 250),
            'html_content' => $this->composeHtml($title, $question, $module, $data, $arguments),
            'status' => 1,
            'created_by' => $context->userId,
            'created_on' => now(),
        ]);

        return ToolResult::success(
            'ai.templates.generate',
            sprintf(
                'Report saved as "%s" with %d row%s.',
                $title,
                count($data['rows']),
                count($data['rows']) === 1 ? '' : 's'
            ),
            [
                'module' => $module,
                'template_id' => $id,
                'title' => $title,
                'row_count' => count($data['rows']),
                'columns' => $data['columns'],
                'source_tool' => $data['source'],
                // The record, not the page. The frontend owns its own routes, the same
                // way it does for the module hand-off.
                'template_link' => '/ai-reports/' . $id,
            ]
        );
    }

    /**
     * Re-read the live rows for a saved report and replace only its figures.
     *
     * This is what the report page's "Refresh figures" does, and the reason it is a
     * splice rather than a re-generate: by the time somebody refreshes, the document
     * has usually been edited — a covering paragraph, a note for the trustees, a
     * signature block. Rebuilding the report would silently discard that, so the only
     * region touched is the marked container the generator wrote, and everything
     * outside it is returned exactly as it was found.
     *
     * The query is re-run from the arguments recorded in the marker, so a refresh
     * asks the same question of the database that the original did rather than the
     * module's default view.
     *
     * @return array<string, mixed>
     */
    public function refresh(McpRequestContext $context, int $reportId): array
    {
        $report = DB::table('template_master')
            ->where('id', $reportId)
            ->where('module_name', AiTemplateService::AI_MODULE)
            // Tenant scoping is the whole authorisation check here. A report belonging
            // to another school is not "forbidden" — from this caller's position it
            // does not exist, and saying so is what stops the id being an oracle.
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->first();

        if (! $report) {
            return ToolResult::failure(
                'ai.templates.regenerate',
                'That report could not be found.',
                'report_not_found'
            );
        }

        $html = (string) $report->html_content;
        $marker = $this->readMarker($html);

        if (! $marker) {
            return ToolResult::failure(
                'ai.templates.regenerate',
                'This document no longer contains a generated table, so there is nothing to refresh. '
                    . 'Generate a new report instead.',
                'figures_not_found',
                ['template_id' => $reportId]
            );
        }

        $data = $this->rowsFor($marker['module'], $context, $marker['arguments']);

        // An empty result must not blank the table. A report whose figures silently
        // vanished reads as an answer — "nobody owes fees" — when what happened is
        // that the query returned nothing, which is usually a filter, not a fact.
        if ($data['rows'] === []) {
            return ToolResult::failure(
                'ai.templates.regenerate',
                sprintf(
                    'No %s records match this report\'s query now, so its figures were left unchanged.',
                    $marker['module']
                ),
                'no_rows',
                ['template_id' => $reportId, 'module' => $marker['module'], 'row_count' => 0]
            );
        }

        $refreshed = substr_replace(
            $html,
            $this->figuresBlock($marker['module'], $data, $marker['arguments']),
            $marker['start'],
            $marker['length']
        );

        DB::table('template_master')
            ->where('id', $reportId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->update(['html_content' => $refreshed]);

        return ToolResult::success(
            'ai.templates.regenerate',
            sprintf(
                'Figures refreshed from live %s records — %d row%s.',
                $marker['module'],
                count($data['rows']),
                count($data['rows']) === 1 ? '' : 's'
            ),
            [
                'template_id' => $reportId,
                'module' => $marker['module'],
                'row_count' => count($data['rows']),
                'columns' => $data['columns'],
                'source_tool' => $data['source'],
                'html' => $refreshed,
            ]
        );
    }

    /**
     * The live rows a saved report is currently about.
     *
     * Send and refresh both need this and neither can get it from the document: the
     * HTML holds escaped text, not records, and the identities needed to reach a
     * student are not printed on the page. Both therefore re-run the query recorded in
     * the marker, which is also what keeps them consistent — a notice is sent about the
     * same figures a refresh would show.
     *
     * @return array{module: string, source: string, rows: array<int, array<string, mixed>>, columns: array<int, string>, arguments: array<string, mixed>}|null
     */
    public function rowsForReport(McpRequestContext $context, string $html): ?array
    {
        $marker = $this->readMarker($html);

        if (! $marker) {
            return null;
        }

        $data = $this->rowsFor($marker['module'], $context, $marker['arguments']);

        return [
            'module' => $marker['module'],
            'source' => $data['source'],
            'rows' => $data['rows'],
            'columns' => $data['columns'],
            'arguments' => $marker['arguments'],
        ];
    }

    /**
     * What the generated figures in a saved document say about themselves.
     *
     * The report page needs this to tell a reader when the figures were last read and
     * to know whether "Refresh figures" has anything to act on. It is exposed here
     * rather than parsed by the controller so that the marker's shape stays private to
     * the class that writes it.
     *
     * @return array{module: string, source: string, generated_at: string}|null
     */
    public function describeFigures(string $html): ?array
    {
        $marker = $this->readMarker($html);

        if (! $marker) {
            return null;
        }

        $openingTag = substr($html, $marker['start'], $marker['length']);
        $read = function (string $suffix) use ($openingTag): string {
            $pattern = '/\b' . preg_quote(self::MARKER . $suffix, '/') . '="([^"]*)"/i';

            return preg_match($pattern, $openingTag, $found)
                ? html_entity_decode($found[1], ENT_QUOTES, 'UTF-8')
                : '';
        };

        return [
            'module' => $marker['module'],
            'source' => $read('-source'),
            'generated_at' => $read('-generated'),
        ];
    }

    /**
     * The rows a module's report is built from, read through the service that owns them.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{rows: array<int, array<string, mixed>>, columns: array<int, string>, source: string}
     */
    private function rowsFor(string $module, McpRequestContext $context, array $arguments): array
    {
        $result = match ($module) {
            'fees' => $this->fees->arrears($context, $arguments),
            'admissions' => $this->admissions->listEnquiries($context, $arguments),
            'attendance' => $this->attendance->overview($context, $arguments),
        };

        $rows = $this->firstList($result);

        return [
            'rows' => $rows,
            'columns' => $this->columnsOf($rows),
            'source' => match ($module) {
                'fees' => 'fees.arrears',
                'admissions' => 'admissions.listEnquiries',
                'attendance' => 'attendance.overview',
            },
        ];
    }

    /**
     * The list a tool payload is actually about.
     *
     * A payload often carries several arrays — a list of students beside a list of
     * unresolved filters. The longest list of rows is the answer; the others describe
     * the query, and picking one of those is how a report ends up tabulating its own
     * search terms.
     *
     * @param  array<string, mixed>  $result
     * @return array<int, array<string, mixed>>
     */
    private function firstList(array $result): array
    {
        $data = is_array($result['data'] ?? null) ? $result['data'] : $result;
        $best = [];

        foreach ($data as $value) {
            if (! is_array($value) || $value === [] || ! array_is_list($value)) {
                continue;
            }

            if (! is_array($value[0] ?? null)) {
                continue;
            }

            if (count($value) > count($best)) {
                $best = $value;
            }
        }

        return $best;
    }

    /**
     * The columns worth printing, in the order the rows present them.
     *
     * Read across the first several rows rather than only the first, because a nullable
     * column missing from row one is still a column.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function columnsOf(array $rows): array
    {
        $columns = [];

        foreach (array_slice($rows, 0, 20) as $row) {
            foreach ($row as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $columns[$key] = true;
                }
            }
        }

        return array_slice(array_keys($columns), 0, self::MAX_COLUMNS);
    }

    private function titleFor(string $module, string $question, int $count): string
    {
        $label = ucfirst($module);

        return $question === ''
            ? sprintf('%s report — %d rows, %s', $label, $count, now()->format('j M Y'))
            : sprintf(
                '%s report — %s (%d rows, %s)',
                $label,
                mb_substr($question, 0, 90),
                $count,
                now()->format('j M Y')
            );
    }

    /**
     * The report: a heading, then the generated figures.
     *
     * The heading sits outside the marked container so it belongs to whoever edits the
     * document; everything a refresh will overwrite sits inside it.
     *
     * @param  array{rows: array<int, array<string, mixed>>, columns: array<int, string>, source: string}  $data
     * @param  array<string, mixed>  $arguments
     */
    private function composeHtml(string $title, string $question, string $module, array $data, array $arguments = []): string
    {
        $head = '<h2>' . e($title) . '</h2>';

        if ($question !== '') {
            $head .= '<p><em>' . e($question) . '</em></p>';
        }

        return $head . $this->figuresBlock($module, $data, $arguments);
    }

    /**
     * The figures: provenance and the rows, inside the marked container.
     *
     * Provenance is inside the container rather than above it because a refresh must
     * move the two together. A table refreshed under a stale "Generated 4 Sep" line is
     * worse than no line at all — it is a document that misdates its own contents.
     *
     * @param  array{rows: array<int, array<string, mixed>>, columns: array<int, string>, source: string}  $data
     * @param  array<string, mixed>  $arguments
     */
    private function figuresBlock(string $module, array $data, array $arguments = []): string
    {
        $count = count($data['rows']);

        $open = sprintf(
            '<div %s="%s" %s-source="%s" %s-generated="%s" %s-args="%s">',
            self::MARKER,
            e($module),
            self::MARKER,
            e($data['source']),
            self::MARKER,
            e(now()->toIso8601String()),
            self::MARKER,
            e($this->encodeArguments($arguments))
        );

        // Provenance on the document itself, not only in the chat that produced it. A
        // report gets printed, emailed, and read months later by somebody who never saw
        // the conversation, and "where did this come from" has to survive that journey.
        $provenance = sprintf(
            '<p><small>Generated %s from live %s records via <code>%s</code>. %d row%s.</small></p>',
            now()->format('j M Y, H:i'),
            e($module),
            e($data['source']),
            $count,
            $count === 1 ? '' : 's'
        );

        return $open . $provenance . $this->table($data) . '</div>';
    }

    /**
     * The rows as a table.
     *
     * @param  array{rows: array<int, array<string, mixed>>, columns: array<int, string>, source: string}  $data
     */
    private function table(array $data): string
    {
        $header = '';

        foreach ($data['columns'] as $column) {
            $header .= '<th style="text-align:left;border-bottom:1px solid #ccc;padding:6px 8px;">'
                . e(ucfirst(str_replace('_', ' ', $column)))
                . '</th>';
        }

        $body = '';

        foreach ($data['rows'] as $row) {
            $cells = '';

            foreach ($data['columns'] as $column) {
                $value = $row[$column] ?? '';
                $cells .= '<td style="padding:6px 8px;border-bottom:1px solid #eee;">'
                    . e(is_scalar($value) ? (string) $value : '')
                    . '</td>';
            }

            $body .= '<tr>' . $cells . '</tr>';
        }

        return '<table style="width:100%;border-collapse:collapse;font-size:13px;">'
            . '<thead><tr>' . $header . '</tr></thead>'
            . '<tbody>' . $body . '</tbody>'
            . '</table>';
    }

    /**
     * Locate the marked container in a saved document.
     *
     * Deliberately a bounded scan rather than a parse-and-reserialise. Loading an
     * administrator's document into DOMDocument and writing it back reformats
     * everything it touched — quotes, entities, void tags — so a refresh would show up
     * as a diff across prose it never meant to change. Finding the container's two
     * offsets and splicing between them leaves every other byte alone.
     *
     * @return array{module: string, start: int, length: int, arguments: array<string, mixed>}|null
     */
    private function readMarker(string $html): ?array
    {
        $pattern = '/<div\b[^>]*\b' . preg_quote(self::MARKER, '/') . '="([a-z]+)"[^>]*>/i';

        if (! preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $module = mb_strtolower($match[1][0]);

        if (! in_array($module, self::SUPPORTED, true)) {
            return null;
        }

        $start = (int) $match[0][1];
        $end = $this->closingDiv($html, $start + strlen($match[0][0]));

        if ($end === null) {
            return null;
        }

        return [
            'module' => $module,
            'start' => $start,
            'length' => $end - $start,
            'arguments' => $this->decodeArguments($match[0][0]),
        ];
    }

    /**
     * The offset just past the `</div>` that closes a container opened before `$from`.
     *
     * Depth-counted, because an administrator may well have wrapped part of the table
     * in a div of their own, and stopping at the first `</div>` would then splice the
     * new table into the middle of the old one.
     */
    private function closingDiv(string $html, int $from): ?int
    {
        if (! preg_match_all('/<div\b|<\/div\s*>/i', $html, $tags, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $depth = 1;

        foreach ($tags[0] as [$tag, $offset]) {
            if ($offset < $from) {
                continue;
            }

            $depth += str_starts_with(mb_strtolower($tag), '</') ? -1 : 1;

            if ($depth === 0) {
                return $offset + strlen($tag);
            }
        }

        return null;
    }

    /**
     * The query, small enough to live in an attribute.
     *
     * Only scalars are kept: what a report needs in order to reproduce itself is a
     * standard, a date range, a status — and refusing everything else means the
     * attribute cannot become a channel for arbitrary structure that the refresh path
     * would then hand to a service.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function encodeArguments(array $arguments): string
    {
        $scalars = [];

        foreach ($arguments as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $scalars[$key] = $value;
            }
        }

        // The module and the question describe the report, not its query, and both are
        // already recorded elsewhere in the document.
        unset($scalars['module'], $scalars['question']);

        $json = json_encode($scalars);

        return ($json === false || strlen($json) > self::MAX_ARGS_BYTES) ? '{}' : $json;
    }

    /**
     * The query recorded in a container's opening tag.
     *
     * Anything unreadable degrades to "no filters" rather than failing the refresh: a
     * document whose attribute an editor mangled should still be refreshable against
     * the module's default view, which is visible to the person looking at it.
     *
     * @return array<string, mixed>
     */
    private function decodeArguments(string $openingTag): array
    {
        $pattern = '/\b' . preg_quote(self::MARKER, '/') . '-args="([^"]*)"/i';

        if (! preg_match($pattern, $openingTag, $match)) {
            return [];
        }

        $decoded = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_filter(
            $decoded,
            fn ($value, $key) => is_string($key) && (is_scalar($value) || $value === null),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
