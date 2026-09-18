<?php

namespace App\Domain\AI\Workspace;

use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Domain\AI\Modules\ModuleReadTools;

/**
 * A module's own data, read through the tools that module is already bound to.
 *
 * WHY THIS EXISTS
 *
 * The Create and Analyse tabs generate from a template, and every template that
 * summarises something declares which of its variables carry the data it is summarising
 * — `GroundingCheck` refuses the generation when all of them are empty, precisely so a
 * model cannot write "this school has no fee defaulters" from an empty prompt. That
 * guard is right, and it was firing constantly: the variables were filled from what the
 * *page* reported, and a page that reports nothing is indistinguishable from a page with
 * nothing on it. Pressing "Summarise pending fees" on the Fees screen refused every
 * time, because nothing had ever put the fees into the prompt.
 *
 * `PageDataResolver` had already answered this for one module by hand-writing a query
 * for the course catalogue. Hand-writing one per module does not scale past the second
 * module, and it puts a second copy of every module's read logic beside the first.
 *
 * So this reads a module's data the way the rest of the platform already reads it:
 * through the MCP tools the module is bound to in `config('ai.lifecycle.modules')`. Fees
 * is bound to `fees.arrears` and `fees.collection_report`; attendance to
 * `attendance.overview`; admissions to `admissions.today`. Each of those is a tool
 * somebody already wrote, tested and scoped. Nothing here knows what a fee is.
 *
 * WHAT THAT BUYS, BEYOND FEES
 *
 * A module gains grounded Create and Analyse actions by being bound to a read tool —
 * which most of them already are. No arm is added here per module, no schema changes,
 * and no second definition of what a module's data is: the chat's own fallback reads the
 * same tools through the same rule (`ModuleReadTools`), so the summary a teacher
 * generates and the answer the chat gives them come from one source.
 *
 * SCOPE IS NOT NEGOTIABLE HERE
 *
 * Tools are executed with the caller's own `McpRequestContext`, which came from the JWT.
 * Each tool authorises against it and filters by institute exactly as it does when the
 * chat calls it. This path can therefore surface nothing the user could not already read
 * on the page they are standing on — it widens nothing, it stops the prompt being empty.
 */
class ModuleToolData
{
    /**
     * How many of a module's tools one resolution may call.
     *
     * Matches `ModuleReadPlanner::MAX_TOOLS`, and for the same reason: the useful figures
     * are sometimes in the second tool — a fees page has arrears and it has collection —
     * and reading both costs two scoped queries. Not more, because this runs on every
     * generate, and a template is a prompt, not a report engine.
     */
    private const MAX_TOOLS = 2;

    /** Matches PageSnapshot::MAX_RECORDS — these land in a prompt, not a page. */
    private const MAX_RECORDS = 25;

    /** Keys that name a row rather than describe it, most specific first. */
    private const LABEL_KEYS = [
        'student_name', 'full_name', 'display_name', 'name', 'title', 'label',
        'subject_name', 'teacher_name', 'course_name', 'exam_name', 'standard_name',
    ];

    /** Keys that identify a row. */
    private const ID_KEYS = ['id', 'student_id', 'enquiry_id', 'record_id', 'enrollment_no'];

    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ModuleReadTools $tools,
    ) {
    }

    /**
     * @return array{
     *   resolved: bool, source: string|null, records: array, record_count: int,
     *   metrics: array, filters: array
     * }
     */
    public function resolve(AiContext $context): array
    {
        $empty = [
            'resolved' => false,
            'source' => null,
            'records' => [],
            'record_count' => 0,
            'metrics' => [],
            'filters' => [],
        ];

        if ($context->moduleKey === null || $context->moduleKey === 'general') {
            return $empty;
        }

        $module = $this->modules->find($context->moduleKey, $context->scope->selectedInstituteId);

        if ($module === null || $module->mcpTools === []) {
            return $empty;
        }

        $registry = $this->tools->registry();

        if ($registry === null) {
            return $empty;
        }

        $selected = $this->tools->select($module->mcpTools, self::MAX_TOOLS);
        $records = [];
        $metrics = [];
        $recordCount = 0;
        $sources = [];

        foreach ($selected as $name) {
            $tool = $registry->tool($name);

            if ($tool === null) {
                continue;
            }

            try {
                // No arguments. A page-level question has no filters of its own, and
                // every one of these tools treats "no filter" as its default view —
                // which is the view the page itself is showing.
                $result = $tool->execute([], $context->scope);
            } catch (\Throwable) {
                // One tool failing must not cost the others, and must not take the
                // generate request down with it. A tool that throws contributes nothing,
                // and the grounding guard decides what that means.
                continue;
            }

            $data = $this->unwrap($result);

            if ($data === []) {
                continue;
            }

            $sources[] = $name;
            $rows = $this->rows($data);

            if ($rows !== [] && $records === []) {
                // The first tool that returns rows owns the record list. Concatenating
                // two tools' rows would produce a list whose entries mean different
                // things, and a template would summarise it as though they did not.
                $records = array_slice($rows, 0, self::MAX_RECORDS);
                $recordCount = $this->totalFor($data, count($rows));
            } elseif ($rows === [] && $recordCount === 0) {
                // No rows returned (e.g. no defaulters), but the tool may still report
                // a cohort total (cohort_size, total, count). Capture it so the template
                // knows the real scope rather than seeing "0 of 0".
                $recordCount = $this->totalFor($data, 0);
            }

            $metrics = array_merge($metrics, $this->metrics($data));
        }

        if ($sources === []) {
            return $empty;
        }

        // Normalised through the same class the page's own snapshot goes through, so a
        // record read here and a record reported by the page arrive at the template in
        // exactly one shape.
        $snapshot = PageSnapshot::fromArray([
            'records' => $records,
            'metrics' => $metrics,
            'record_count' => max($recordCount, count($records)),
        ]);

        return [
            // True even when the module genuinely holds nothing. Reported as
            // resolved-but-empty so the grounding guard still refuses rather than the
            // model inventing rows — but the emptiness is now a fact about the data
            // rather than about the request.
            'resolved' => true,
            'source' => implode(', ', $sources),
            'records' => $snapshot->records,
            'record_count' => $snapshot->recordCount,
            'metrics' => $snapshot->metrics,
            'filters' => [],
        ];
    }

    // ---------------------------------------------------------------- internals

    /**
     * The data half of a tool result.
     *
     * Tools do not share one envelope. Some return `ToolResult::success()`, which wraps
     * the payload under `data` beside `success` and `message`; others return their
     * payload directly. Both are read here rather than normalising thirty tools, because
     * changing what an existing tool returns would change what the chat receives from it
     * too — a far larger blast radius than reading two shapes.
     *
     * @param  mixed  $result
     * @return array<string, mixed>
     */
    private function unwrap($result): array
    {
        if (! is_array($result)) {
            return [];
        }

        if (array_key_exists('success', $result)) {
            if (($result['success'] ?? false) !== true) {
                // A failed tool has nothing to ground a template with, and its error
                // message is not data about the school.
                return [];
            }

            return is_array($result['data'] ?? null) ? $result['data'] : [];
        }

        return $result;
    }

    /**
     * The rows in a tool's payload.
     *
     * The first list of associative arrays wins — `students_with_arrears`, `receipts`,
     * `students`, `enquiries`. Which key it sits under differs per tool and does not need
     * to be known here: a list of records is recognisable by its shape.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{id: mixed, label: string|null, attributes: array<string, mixed>}>
     */
    private function rows(array $data): array
    {
        foreach ($data as $value) {
            if (! is_array($value) || $value === [] || ! array_is_list($value)) {
                continue;
            }

            if (! is_array($value[0]) || array_is_list($value[0])) {
                continue;
            }

            return array_values(array_map(
                fn (array $row) => $this->row($row),
                array_filter($value, static fn ($row) => is_array($row) && ! array_is_list($row))
            ));
        }

        return [];
    }

    /**
     * One row, flattened the way `PageSnapshot` reads a row.
     *
     * It walks a record's own top-level keys and takes `id` and `label` from among them,
     * so a row handed over pre-nested as `['id' =>, 'label' =>, 'attributes' => [...]]`
     * arrives with every attribute dropped — `attributes` is an array, and it keeps only
     * scalars. The fields therefore stay at the top level, with `id` and `label` resolved
     * beside them: `student_name` is both the label and an attribute, which is what a
     * template wants.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row): array
    {
        $label = null;

        foreach (self::LABEL_KEYS as $key) {
            if (isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== '') {
                $label = (string) $row[$key];

                break;
            }
        }

        $id = null;

        foreach (self::ID_KEYS as $key) {
            if (isset($row[$key]) && is_scalar($row[$key])) {
                $id = $row[$key];

                break;
            }
        }

        return array_merge($row, array_filter(
            ['id' => $id, 'label' => $label],
            static fn ($value) => $value !== null
        ));
    }

    /**
     * The scalar figures in a tool's payload, which are what a summary quotes.
     *
     * `defaulter_count`, `total_outstanding`, `cohort_size`, `students_checked` — each is
     * a number the tool computed and stands behind. Long strings are excluded: `basis` is
     * a paragraph explaining where the numbers came from, and it belongs in the audit
     * row, not in a list of figures.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, scalar>
     */
    private function metrics(array $data): array
    {
        $metrics = [];

        foreach ($data as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            if (is_string($value) && mb_strlen($value) > 60) {
                continue;
            }

            $metrics[$key] = is_bool($value) ? ($value ? 'yes' : 'no') : $value;
        }

        return $metrics;
    }

    /**
     * The real total behind a windowed list.
     *
     * "These 25" is wrong when the cohort holds 3,424, and the tools already say so —
     * `cohort_size`, `count`, `total_receipts`. Preferring the tool's own figure over
     * `count($rows)` is what lets a template state honestly that it is looking at a
     * window.
     *
     * @param  array<string, mixed>  $data
     */
    private function totalFor(array $data, int $listed): int
    {
        foreach (['cohort_size', 'total', 'total_count', 'count', 'record_count'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key]) && (int) $data[$key] >= $listed) {
                return (int) $data[$key];
            }
        }

        return $listed;
    }
}
