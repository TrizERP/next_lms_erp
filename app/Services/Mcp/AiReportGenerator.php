<?php

namespace App\Services\Mcp;

use App\Domain\AI\Templates\GeneratedReportStore;
use App\Domain\AI\Templates\ReportDataSourceCatalog;
use App\Domain\AI\Templates\ReportLayoutRenderer;
use App\Domain\AI\Templates\ReportTemplateResolver;
use App\Mcp\ToolRegistry;
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
    /**
     * The modules that have a service wired in directly, from before Template Management.
     *
     * No longer the whole answer to "what can be reported on" — see `supportedFor()`.
     * Any module with a published report layout is reportable too, and reaches its rows
     * through the layout's bound MCP tool rather than through a service named here.
     * These three stay because they work, they are what the estate uses today, and
     * rewriting them as layouts would be a migration with no user-visible gain.
     */
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

    /**
     * The attribute naming the layout a report was rendered from.
     *
     * Written into the marker so a refresh re-renders the same configured layout rather
     * than splicing the built-in table into a document that never had one.
     */
    private const LAYOUT_ATTRIBUTE = 'data-ai-report-layout';

    public function __construct(
        private readonly FeesArrearsService $fees,
        private readonly AdmissionMcpService $admissions,
        private readonly AttendanceInsightService $attendance,
        private readonly ReportTemplateResolver $layouts,
        private readonly ReportDataSourceCatalog $sources,
        private readonly ReportLayoutRenderer $renderer,
        private readonly GeneratedReportStore $reports,
    ) {
    }

    /**
     * Resolved per call rather than injected — see the note on
     * `ReportDataSourceCatalog::registry()`. This class is reachable from a tool inside
     * the registry, so taking the registry as a constructor argument makes building it
     * recursive.
     */
    private function tools(): ToolRegistry
    {
        return app(ToolRegistry::class);
    }

    /**
     * Every module this caller can generate a report for, right now.
     *
     * The three wired services plus every module with a published layout. Resolved per
     * call rather than held in a constant because publishing a template in Template
     * Management has to make its module reportable immediately — a constant would need
     * a deploy, which is the coupling this whole feature exists to remove.
     *
     * @return array<int, string>
     */
    public function supportedFor(McpRequestContext $context): array
    {
        return array_values(array_unique(array_merge(
            self::SUPPORTED,
            $this->layouts->modulesWithLayouts($context->selectedInstituteId)
        )));
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

        $supported = $this->supportedFor($context);

        if (! in_array($module, $supported, true)) {
            return ToolResult::failure(
                'ai.templates.generate',
                sprintf(
                    'Reports can be generated for %s. "%s" is not one of them — publish a report '
                        . 'template for it under AI & Intelligence → Template Management to make it one.',
                    implode(', ', $supported),
                    $module === '' ? '(nothing named)' : $module
                ),
                'unsupported_module'
            );
        }

        // The school's configured layout for this module, if it has one. Resolved before
        // the rows because the layout is what decides where they come from: its bound
        // MCP tool, not the service this class happens to have been given.
        $layout = $this->layouts->find($module, $context->selectedInstituteId);

        $data = $this->rowsFor($module, $context, $arguments, $layout);

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

        // A configured layout names the report; otherwise the generic title does.
        $title = $layout !== null && trim((string) $layout->name) !== ''
            ? $this->layoutTitle($layout, $data, $question)
            : $this->titleFor($module, $question, count($data['rows']));

        $html = $layout !== null
            ? $this->composeFromLayout($layout, $title, $question, $module, $data, $arguments, $context)
            : $this->composeHtml($title, $question, $module, $data, $arguments);

        // `ai_generated_reports`, not `template_master`. That table is UNIQUE on
        // (sub_institute_id, module_name), so it could hold exactly one AI report per
        // school and the second one generated threw a constraint violation out of this
        // very line. See 2026_09_14_000004.
        $id = $this->reports->create($context, [
            'module_key' => $module,
            'layout_template_id' => $layout === null ? null : (int) $layout->id,
            'title' => $title,
            'html_content' => $html,
            'question' => $question,
            'source_tool' => $data['source'],
            'arguments' => $arguments,
            'row_count' => count($data['rows']),
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
                // Which centrally configured layout produced this, so the chat answer
                // and the report page can both say so rather than leaving the reader to
                // guess whether the design came from Template Management or the default.
                'layout_template_id' => $layout === null ? null : (int) $layout->id,
                'layout_name' => $layout === null ? null : (string) $layout->name,
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
        // Tenant scoping is the whole authorisation check, and the store applies it to
        // both the current table and the legacy one — a report written before
        // `ai_generated_reports` existed still refreshes.
        $report = $this->reports->find($context, $reportId);

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

        // The layout this report was rendered from, when it was rendered from one. Read
        // by id rather than re-resolved by module, so a report keeps being refreshed
        // with the template that produced it even after a newer one is published — a
        // refresh is meant to update figures, not silently restyle the document.
        $layoutId = $this->readLayoutId($html, $marker);
        $layout = $layoutId === null ? null : $this->layouts->findById($layoutId, $context->selectedInstituteId);

        // Neither a layout to re-render nor a service to fall back on. Said plainly,
        // because the cause is recoverable — the layout was retired or its data source
        // renamed — and "no rows matched" would send somebody looking at their filters.
        if ($layout === null && ! in_array($marker['module'], self::SUPPORTED, true)) {
            return ToolResult::failure(
                'ai.templates.regenerate',
                sprintf(
                    'This report was built from a %s template that is no longer published, so its '
                        . 'figures cannot be refreshed. Re-publish the template, or generate a new report.',
                    $marker['module']
                ),
                'layout_unavailable',
                ['template_id' => $reportId, 'module' => $marker['module']]
            );
        }

        $data = $this->rowsFor($marker['module'], $context, $marker['arguments'], $layout);

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

        // Re-render the layout when there was one, splice the built-in table when there
        // was not. Both replace exactly the marked region, so whatever an administrator
        // wrote outside it survives either way.
        $replacement = $layout !== null
            ? $this->composeFromLayout(
                $layout,
                (string) $report->title,
                '',
                $marker['module'],
                $data,
                $marker['arguments'],
                $context
            )
            : $this->figuresBlock($marker['module'], $data, $marker['arguments']);

        $refreshed = substr_replace($html, $replacement, $marker['start'], $marker['length']);

        $this->reports->updateHtml($context, $reportId, $refreshed, (bool) ($report->legacy ?? false));

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

        // Same layout the document was rendered from, so Share reads the rows through
        // the same data source a refresh would — a notice must not be sent about
        // figures a refresh would disagree with.
        $layoutId = $this->readLayoutId($html, $marker);
        $layout = $layoutId === null ? null : $this->layouts->findById($layoutId, $context->selectedInstituteId);

        $data = $this->rowsFor($marker['module'], $context, $marker['arguments'], $layout);

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
    private function rowsFor(
        string $module,
        McpRequestContext $context,
        array $arguments,
        ?object $layout = null
    ): array {
        // A configured layout decides where its own rows come from. That is what makes
        // the flow generic: `fees.get_pending`, `attendance.overview` and
        // `students.directory` are all reached the same way, so adding a module means
        // publishing a template that names its tool — not editing this match.
        $result = $layout !== null && $this->sources->isBindable((string) ($layout->data_source ?? ''))
            ? $this->fromDataSource($layout, $context, $arguments)
            : $this->fromWiredService($module, $context, $arguments);

        $rows = $this->firstList($result);

        return [
            'rows' => $rows,
            'columns' => $this->columnsOf($rows),
            // Named by whatever actually ran. The old `match` here had no default arm,
            // so the first module reportable by layout alone would have thrown an
            // UnhandledMatchError after its rows had already been fetched.
            'source' => $this->sourceName($module, $layout),
            // The totals the tool worked out for itself.
            'scalars' => $this->scalarsOf($result),
        ];
    }

    /**
     * Run the MCP tool a layout is bound to.
     *
     * Through `ToolRegistry::execute()` rather than by calling the tool object, so the
     * call is governed exactly as it is when the assistant makes it: same permission
     * check, same tenant scoping, same confirmation gate. A report must not be a way
     * around the rules that apply to asking the question directly.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function fromDataSource(object $layout, McpRequestContext $context, array $arguments): array
    {
        $tool = (string) $layout->data_source;

        // The layout's own mapping is the floor; the question's arguments override it,
        // so "pending fees for Aarav" and "pending fees for everyone" reach the same
        // tool through the same template with a different student_id.
        $payload = array_merge($this->layouts->arguments($layout), $arguments);

        // Housekeeping keys the generator adds for itself. Passing them on would fail
        // validation on tools whose schema is closed.
        unset($payload['module'], $payload['question']);

        try {
            $envelope = $this->tools()->execute($tool, $payload, $context);
        } catch (\Throwable $exception) {
            report($exception);

            return ToolResult::failure(
                'ai.templates.generate',
                sprintf('The data source "%s" could not be read.', $tool),
                'data_source_failed'
            );
        }

        // `ToolRegistry::execute()` does not return the tool's own result — it wraps it
        // as `['mode' => 'execute', 'result' => <ToolResult>]`. Handing the wrapper
        // straight to `firstList()` finds no list inside it and yields zero rows, which
        // surfaces as the cheerful and completely wrong "No fees records matched".
        if (($envelope['mode'] ?? null) === 'preview') {
            // Only reachable if a confirmable tool were ever annotated read-only. It
            // has run nothing and returned a confirmation prompt, which is not data.
            return ToolResult::failure(
                'ai.templates.generate',
                sprintf('The data source "%s" requires confirmation and cannot feed a report.', $tool),
                'data_source_requires_confirmation'
            );
        }

        $result = $envelope['result'] ?? $envelope;

        return is_array($result) ? $result : [];
    }

    /**
     * The three services wired in before Template Management existed.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function fromWiredService(string $module, McpRequestContext $context, array $arguments): array
    {
        return match ($module) {
            'fees' => $this->fees->arrears($context, $arguments),
            'admissions' => $this->admissions->listEnquiries($context, $arguments),
            'attendance' => $this->attendance->overview($context, $arguments),
            // Reachable when a layout's data source has been renamed or made write-only
            // since it was published. An empty result is reported honestly by the
            // caller; throwing here would lose the report and the reason for it.
            default => ToolResult::failure(
                'ai.templates.generate',
                sprintf('No data source is configured for the %s module.', $module),
                'no_data_source'
            ),
        };
    }

    /**
     * The single values a tool returns alongside its rows, as layout placeholders.
     *
     * `fees.arrears` works out `total_outstanding`, `defaulter_count`, `cohort_size`
     * and `students_checked`; `attendance.overview` works out its own averages. These
     * are exactly the figures a report wants in its heading, and the tool has already
     * computed them correctly — recomputing them from the rows in the layout would get
     * a different answer whenever the rows are a sample of a larger set, which for
     * `fees.arrears` is the normal case.
     *
     * Without this, `<<total_outstanding>>` in a layout resolved to nothing and a
     * Pending Fees Report could not state its own total.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, string>
     */
    private function scalarsOf(array $result): array
    {
        $data = is_array($result['data'] ?? null) ? $result['data'] : $result;
        $scalars = [];

        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $scalars[(string) $key] = $value === null
                    ? ''
                    : (is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value);
            }
        }

        return $scalars;
    }

    private function sourceName(string $module, ?object $layout): string
    {
        if ($layout !== null && $this->sources->isBindable((string) ($layout->data_source ?? ''))) {
            return (string) $layout->data_source;
        }

        return match ($module) {
            'fees' => 'fees.arrears',
            'admissions' => 'admissions.listEnquiries',
            'attendance' => 'attendance.overview',
            default => $module,
        };
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
    /**
     * Render a configured layout and wrap it in the marker container.
     *
     * The whole rendered document goes inside the marker, not just a table, because
     * with a layout the document *is* the figures — headings, totals and the operator's
     * wording are all substituted from the same rows. Refreshing therefore re-renders
     * the layout rather than splicing a table into it, and the marker carries the
     * layout's id so the refresh knows which template to re-render.
     *
     * Anything an administrator adds outside the marker on the report page still
     * survives a refresh, exactly as it did before.
     *
     * @param  array{rows: array<int, array<string, mixed>>, columns: array<int, string>, source: string}  $data
     * @param  array<string, mixed>  $arguments
     */
    private function composeFromLayout(
        object $layout,
        string $title,
        string $question,
        string $module,
        array $data,
        array $arguments,
        McpRequestContext $context
    ): string {
        $rendered = $this->renderer->render(
            (string) $layout->html_layout,
            $data['rows'],
            $data['columns'],
            // Report facts on the left, because PHP's `+` keeps the LEFT operand's key
            // on a collision. A data source with its own `module` or `question` key
            // must not rename the report in its own heading.
            [
                'report_title' => $title,
                'question' => $question,
                'module' => $module,
            ] + ($data['scalars'] ?? [])
        );

        $open = sprintf(
            '<div %s="%s" %s-source="%s" %s-generated="%s" %s-args="%s" %s="%d">',
            self::MARKER,
            e($module),
            self::MARKER,
            e($data['source']),
            self::MARKER,
            e(now()->toIso8601String()),
            self::MARKER,
            e($this->encodeArguments($arguments)),
            self::LAYOUT_ATTRIBUTE,
            (int) $layout->id
        );

        return $open . $rendered['html'] . '</div>';
    }

    /**
     * The saved report's title when a layout named it.
     *
     * The layout's name plus what the report is actually of, so a library of fifty
     * reports all rendered from "Pending Fees Report" is still distinguishable — the
     * row count and date are what tell two of them apart.
     *
     * @param  array{rows: array<int, array<string, mixed>>, columns: array<int, string>, source: string}  $data
     */
    private function layoutTitle(object $layout, array $data, string $question): string
    {
        $count = count($data['rows']);

        // A single-record report is better identified by whose it is than by "1 row".
        $subject = $count === 1 ? $this->subjectOf($data['rows'][0] ?? []) : '';

        if ($subject !== '') {
            return sprintf('%s — %s, %s', $layout->name, $subject, now()->format('j M Y'));
        }

        return sprintf(
            '%s — %d row%s, %s',
            $layout->name,
            $count,
            $count === 1 ? '' : 's',
            now()->format('j M Y')
        );
    }

    /**
     * A human name for a single row, for the title.
     *
     * Looks for the field names this estate's tools actually return rather than
     * guessing one: a report titled "Pending Fees Report — 1 row" helps nobody find it
     * again, and "— Aarav Sharma" does.
     *
     * @param  array<string, mixed>  $row
     */
    private function subjectOf(array $row): string
    {
        foreach (['student_name', 'name', 'full_name', 'title', 'employee_name', 'teacher_name'] as $key) {
            $value = $row[$key] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr(trim((string) $value), 0, 80);
            }
        }

        return '';
    }

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
    /**
     * The layout id recorded on the marker, when the report was rendered from one.
     *
     * @param  array{start:int, length:int}  $marker
     */
    private function readLayoutId(string $html, array $marker): ?int
    {
        $container = substr($html, $marker['start'], $marker['length']);
        $pattern = '/\b' . preg_quote(self::LAYOUT_ATTRIBUTE, '/') . '="(\d+)"/i';

        return preg_match($pattern, $container, $found) ? (int) $found[1] : null;
    }

    private function readMarker(string $html): ?array
    {
        // `[a-z0-9_-]` rather than `[a-z]`: module keys are `ai_modules` keys, and
        // `course-master`, `front_desk` and `admin-services` are all real ones. The
        // narrower class silently failed to match them, which read to the caller as
        // "this document has no figures" for a document that plainly did.
        $pattern = '/<div\b[^>]*\b' . preg_quote(self::MARKER, '/') . '="([a-z0-9_\-]+)"[^>]*>/i';

        if (! preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $module = mb_strtolower($match[1][0]);

        // Deliberately not checked against `SUPPORTED` any more. That list is the three
        // modules with a wired service, and since a module becomes reportable by having
        // a layout published, gating here made every layout-generated report
        // unrefreshable — reported as "no generated table", which is not what was wrong.
        // Whether the module can still be read is decided by `refresh()`, which has the
        // context needed to answer it.

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
