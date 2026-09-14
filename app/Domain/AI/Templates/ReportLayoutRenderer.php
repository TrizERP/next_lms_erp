<?php

namespace App\Domain\AI\Templates;

/**
 * Puts real rows into a configured report layout.
 *
 * WHY SUBSTITUTION AND NOT GENERATION
 *
 * A model asked to write a table of students is a model that can invent a student, and
 * a fee figure that looks plausible is worse than no figure at all because nobody
 * checks it. So nothing here reaches a model: the layout is a document an administrator
 * wrote, the rows came from an MCP tool that read the database, and this class only
 * puts the second into the first. The prose an author writes around the table is theirs;
 * the figures inside it are the database's. Neither is generated.
 *
 * WHY ONE LAYOUT SERVES BOTH "ALL STUDENTS" AND "THIS STUDENT"
 *
 * The requirement that made this class necessary was not wanting a copy of the Pending
 * Fees template per student. So a layout has three kinds of placeholder and a single
 * document can use all three:
 *
 *   `<<row_count>>`              a scalar — report-level facts, always available.
 *   `<<student_name>>`           a field of the *first* row. On a one-student report
 *                                that is the student; on a list it is the first row,
 *                                which is why a list layout uses the block instead.
 *   `<<#rows>>…<</rows>>`        repeated once per row, with the row's own fields
 *                                available inside it.
 *   `<<rows_table>>`             the whole result as a table, for a layout that wants
 *                                the figures without laying out each column by hand.
 *
 * Ask for one student and the tool returns one row: the block runs once and the scalar
 * fields are that student's. Ask for all of them and the same document repeats. The
 * template did not change; the arguments to its data source did.
 *
 * EVERY VALUE IS ESCAPED, NOTHING IS EVALUATED
 *
 * The output is stored as HTML, edited by an administrator, and rendered back into a
 * page — which is the exact shape of a stored-XSS bug if a student's name is ever
 * allowed to be markup. Values are escaped on the way in, without exception, and there
 * is no expression language: a placeholder names a field or it resolves to nothing.
 */
class ReportLayoutRenderer
{
    /**
     * The delimiters, raw or HTML-encoded.
     *
     * `TemplateHtmlEditor` — the editor an author writes these layouts in — inserts a
     * placeholder with `insertHTML` of `&lt;&lt; key &gt;&gt;`, so what is stored is the
     * *encoded* form; typing `<<key>>` by hand stores the raw form. Both are the same
     * placeholder to the person who wrote it, so both have to be the same placeholder
     * here. `AiTemplateService::substitute()` already makes exactly this allowance for
     * the ERP document templates.
     */
    private const OPEN = '(?:<<|&lt;&lt;)';

    private const CLOSE = '(?:>>|&gt;&gt;)';

    /** Matches `<<#rows>> … <</rows>>`, non-greedy so two blocks in one layout stay separate. */
    private const ROW_BLOCK = '/' . self::OPEN . '\s*#rows\s*' . self::CLOSE
        . '(.*?)' . self::OPEN . '\s*\/rows\s*' . self::CLOSE . '/s';

    /** A single `<<placeholder>>`; the same spelling the ERP document templates use. */
    private const TOKEN = '/' . self::OPEN . '\s*([a-zA-Z0-9_.]+)\s*' . self::CLOSE . '/';

    /** Eight columns is what prints across a page without wrapping into unreadability. */
    private const MAX_COLUMNS = 8;

    /** A value long enough to be a payload rather than a field. */
    private const MAX_VALUE_LENGTH = 2000;

    /**
     * Render a layout against the rows a data source returned.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>                $columns
     * @param  array<string, mixed>              $facts  Report-level scalars: title, module, question…
     * @return array{html:string, unresolved:array<int, string>}
     */
    public function render(string $layout, array $rows, array $columns, array $facts = []): array
    {
        $scalars = $this->scalars($rows, $columns, $facts);

        // The repeating block first: its inner tokens are row fields, and resolving the
        // document's scalars first would consume `<<student_name>>` inside the block
        // using the first row's value for every row.
        $html = preg_replace_callback(
            self::ROW_BLOCK,
            fn (array $match) => $this->repeat($match[1], $rows),
            $layout
        ) ?? $layout;

        // Before the scalar pass, and by regex rather than str_replace, so the encoded
        // spelling the editor produces is handled as well as the typed one. A callback
        // rather than a replacement string because the table is markup built from user
        // data, and a `$1` in a student's name would otherwise be read as a
        // backreference and silently eat part of the table.
        $html = preg_replace_callback(
            '/' . self::OPEN . '\s*rows_table\s*' . self::CLOSE . '/',
            fn () => $this->table($rows, $columns),
            $html
        ) ?? $html;

        $html = $this->substitute($html, $scalars);

        return [
            'html' => $html,
            'unresolved' => $this->unresolved($html),
        ];
    }

    /**
     * The placeholders a layout can use, for the editor's "Insert template variable"
     * picker.
     *
     * Built from the columns the bound data source actually returns, so an author
     * picks from what this report will really have rather than from a generic list
     * that is right for fees and wrong for attendance.
     *
     * @param  array<int, string>  $columns
     * @return array<int, array{key:string, label:string, scope:string}>
     */
    public function placeholders(array $columns = []): array
    {
        $tags = [
            ['key' => 'report_title', 'label' => 'Report title', 'scope' => 'report'],
            ['key' => 'question', 'label' => 'The question that produced the report', 'scope' => 'report'],
            ['key' => 'module', 'label' => 'Module name', 'scope' => 'report'],
            ['key' => 'row_count', 'label' => 'Number of records', 'scope' => 'report'],
            ['key' => 'generated_at', 'label' => 'Date and time generated', 'scope' => 'report'],
            // No `institute_name`: this estate has no table that reliably holds one per
            // sub-institute — only per-module copies such as `fees_config_master`. A
            // placeholder that always renders empty is worse than none, so a letterhead
            // is typed into the layout until there is a source worth reading.
            ['key' => 'rows_table', 'label' => 'All records as a table', 'scope' => 'report'],
        ];

        foreach ($columns as $column) {
            $tags[] = [
                'key' => $column,
                'label' => $this->humanise($column) . ' (first record, or each record inside a rows block)',
                'scope' => 'row',
            ];
        }

        return $tags;
    }

    /**
     * One repetition of the block per row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function repeat(string $block, array $rows): string
    {
        $out = '';

        foreach ($rows as $index => $row) {
            $values = $this->rowValues($row);
            // Useful in a numbered list, and the one fact a row does not carry itself.
            $values['row_number'] = (string) ($index + 1);

            $out .= $this->substitute($block, $values);
        }

        return $out;
    }

    /**
     * Report-level facts plus the first row's fields.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>                $columns
     * @param  array<string, mixed>              $facts
     * @return array<string, string>
     */
    private function scalars(array $rows, array $columns, array $facts): array
    {
        $values = [];

        foreach ($facts as $key => $value) {
            $values[(string) $key] = $this->stringify($value);
        }

        $values['row_count'] = (string) count($rows);
        $values['generated_at'] = now()->format('j M Y, H:i');

        // The first row's fields, so a single-record report reads naturally as
        // `<<student_name>>` rather than forcing a rows block around one row.
        if (isset($rows[0]) && is_array($rows[0])) {
            foreach ($this->rowValues($rows[0]) as $key => $value) {
                // Report facts win: a data source with a `module` column must not
                // silently rename the report's own module in the heading.
                $values[$key] ??= $value;
            }
        }

        foreach ($columns as $column) {
            $values[$column] ??= '';
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function rowValues(array $row): array
    {
        $values = [];

        foreach ($row as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $values[(string) $key] = $this->stringify($value);
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function substitute(string $html, array $values): string
    {
        return preg_replace_callback(
            self::TOKEN,
            static function (array $match) use ($values) {
                // Left as written when nothing matches, so an author can see which
                // placeholder had nothing behind it instead of a silent blank.
                return $values[$match[1]] ?? $match[0];
            },
            $html
        ) ?? $html;
    }

    /**
     * The rows as a table, for `<<rows_table>>`.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>                $columns
     */
    private function table(array $rows, array $columns): string
    {
        $columns = array_slice($columns, 0, self::MAX_COLUMNS);

        if ($rows === [] || $columns === []) {
            return '<p><em>No records matched.</em></p>';
        }

        $header = '';

        foreach ($columns as $column) {
            $header .= '<th style="border:1px solid #cbd5e1;padding:6px;text-align:left;background:#f1f5f9;">'
                . e($this->humanise($column)) . '</th>';
        }

        $body = '';

        foreach ($rows as $row) {
            $cells = '';

            foreach ($columns as $column) {
                $value = $row[$column] ?? '';
                $cells .= '<td style="border:1px solid #cbd5e1;padding:6px;">'
                    . e($this->stringify(is_scalar($value) || $value === null ? $value : '')) . '</td>';
            }

            $body .= '<tr>' . $cells . '</tr>';
        }

        return '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr>' . $header . '</tr></thead><tbody>' . $body . '</tbody></table>';
    }

    /** @return array<int, string> */
    private function unresolved(string $html): array
    {
        preg_match_all(self::TOKEN, $html, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function stringify(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if ($value === true) {
            return 'Yes';
        }

        return e(mb_substr((string) $value, 0, self::MAX_VALUE_LENGTH));
    }

    private function humanise(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }
}
