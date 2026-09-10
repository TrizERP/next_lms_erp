<?php

namespace App\Services\Curriculum;

/**
 * Recovers the marks structure of a curriculum from the extracted syllabus text.
 *
 * `lms_curriculum` stores the marks as two scalars — total_marks and
 * internal_marks — and nothing else. How those 20 internal marks are made up,
 * and how the theory paper is weighted across competencies, exists only as
 * prose and HTML tables inside `document_extractions.md_content`. There is no
 * structured field to read, so this parses it back out.
 *
 * It is text mining over documents nobody controls, and the boards do not agree
 * on a layout. Measured across the ten curricula on this estate the internal
 * assessment block arrives in four distinct shapes:
 *
 *   Science (ext 28, 52)   prose:  "Internal Assessment (20 Marks) Periodic
 *                                   Assessment 05 marks + 05 marks ..."
 *   Maths (ext 47)         2-col table, marker in its own first row
 *   English (ext 105)      2-col table, marker in a single colspan cell
 *   Social Science (ext 66) 3-col table, marker in a heading *before* the table
 *
 * and Hindi (ext 86) states none of it at all. So every result here is reported
 * with whether its components actually reconcile to the internal_marks already
 * stored: a breakdown that does not add up is a parse that went wrong, and the
 * caller is told rather than shown plausible wrong numbers.
 */
class CurriculumAssessmentParser
{
    /** How far past the "internal assessment" marker a table may start and still be its table. */
    private const TABLE_SEARCH_WINDOW = 2000;

    /** How much text after the marker the prose fallback will consider. */
    private const PROSE_WINDOW = 600;

    /** Mentions of "internal assessment" to try before giving up. */
    private const MAX_ANCHORS = 10;

    /**
     * @return array{
     *   theory_marks: int|null,
     *   theory_marks_source: string|null,
     *   internal_breakdown: list<array{component: string|null, marks: int}>,
     *   internal_breakdown_total: int|null,
     *   internal_reconciles: bool|null,
     *   competencies: list<array{competency: string, percentage: float}>,
     *   competency_total_percent: float|null
     * }
     */
    public function parse(?string $markdown, ?int $totalMarks, ?int $internalMarks): array
    {
        $empty = [
            'theory_marks'             => null,
            'theory_marks_source'      => null,
            'internal_breakdown'       => [],
            'internal_breakdown_total' => null,
            'internal_reconciles'      => null,
            'competencies'             => [],
            'competency_total_percent' => null,
        ];

        if ($markdown === null || trim($markdown) === '') {
            return $empty;
        }

        $tables = $this->tables($markdown);

        $breakdown = $this->internalBreakdown($markdown, $tables, $internalMarks);
        $breakdownTotal = $breakdown === [] ? null : array_sum(array_column($breakdown, 'marks'));

        $competencies = $this->competencies($tables);
        $competencyTotal = $competencies === []
            ? null
            : round(array_sum(array_column($competencies, 'percentage')), 2);

        [$theory, $theorySource] = $this->theoryMarks($markdown, $totalMarks, $internalMarks);

        return [
            'theory_marks'             => $theory,
            'theory_marks_source'      => $theorySource,
            'internal_breakdown'       => $breakdown,
            'internal_breakdown_total' => $breakdownTotal,
            // null when nothing was found, so "no breakdown recorded" stays
            // distinguishable from "a breakdown that does not add up".
            'internal_reconciles'      => $breakdownTotal === null || $internalMarks === null
                ? null
                : $breakdownTotal === $internalMarks,
            'competencies'             => $competencies,
            'competency_total_percent' => $competencyTotal,
        ];
    }

    /**
     * Every HTML table in the markdown, flattened to rows of plain-text cells
     * and tagged with where it sits in the document.
     *
     * The offset is what lets a marker in a *heading* be tied to the table that
     * follows it, which is the only way the Social Science layout is readable.
     *
     * @return list<array{offset: int, end: int, rows: list<list<string>>}>
     */
    private function tables(string $markdown): array
    {
        if (!preg_match_all('/<table\b.*?<\/table>/is', $markdown, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $tables = [];

        foreach ($matches[0] as [$html, $offset]) {
            preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $rowMatches);

            $rows = [];
            foreach ($rowMatches[1] as $rowHtml) {
                preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', $rowHtml, $cellMatches);
                $rows[] = array_map(fn ($cell) => $this->text($cell), $cellMatches[1]);
            }

            $tables[] = ['offset' => $offset, 'end' => $offset + strlen($html), 'rows' => $rows];
        }

        return $tables;
    }

    /**
     * The components that make up the internal marks.
     *
     * "Internal assessment" is written several times in one syllabus and most
     * of those mentions are not the breakdown: Science names it as a line of
     * the unit-marks table, English as a row of the section-weightage table.
     * Both parse perfectly and are both wrong - they sum to 30 and to 100
     * against an internal_marks of 20.
     *
     * So every mention is tried, as a table and as prose, and the arithmetic
     * decides: the candidate whose components add up to the internal_marks
     * already stored is the breakdown. Nothing else can distinguish them.
     *
     * @param  list<array{offset: int, end: int, rows: list<list<string>>}>  $tables
     * @return list<array{component: string, marks: int}>
     */
    private function internalBreakdown(string $markdown, array $tables, ?int $internalMarks): array
    {
        if (!preg_match_all('/internal\s*assessment/i', $markdown, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $candidates = [];

        foreach (array_slice($matches[0], 0, self::MAX_ANCHORS) as [, $anchor]) {
            // The table holding the breakdown either contains the marker itself
            // (Maths, English) or is the next one after it (Social Science).
            foreach ($tables as $table) {
                if ($table['end'] < $anchor) {
                    continue;
                }

                if ($table['offset'] > $anchor + self::TABLE_SEARCH_WINDOW) {
                    break;
                }

                $rows = $this->componentRows($table['rows']);

                if ($rows !== []) {
                    $candidates[] = $rows;
                }

                break;
            }

            // Tags stripped first so the pattern cannot match markup fragments
            // when the marker turns out to sit inside a table after all.
            $prose = $this->proseBreakdown($this->text(substr($markdown, $anchor, self::PROSE_WINDOW)));

            if ($prose !== []) {
                $candidates[] = $prose;
            }
        }

        if ($internalMarks !== null) {
            foreach ($candidates as $candidate) {
                if (array_sum(array_column($candidate, 'marks')) === $internalMarks) {
                    return $candidate;
                }
            }
        }

        // Nothing reconciled. Return the first reading anyway - parse() reports
        // that it does not add up, which is more use than showing nothing.
        return $candidates[0] ?? [];
    }

    /**
     * Table rows read as "component ... marks".
     *
     * The label is the first cell and the marks the last, which holds for the
     * two-column layouts and for Social Science's three-column one where a
     * description sits between them. Anything whose last cell is not a number -
     * the header row, the marker row - simply drops out.
     *
     * @param  list<list<string>>  $rows
     * @return list<array{component: string, marks: int}>
     */
    private function componentRows(array $rows): array
    {
        $components = [];

        foreach ($rows as $row) {
            if (count($row) < 2) {
                continue;
            }

            $label = $this->cleanLabel($row[0]);
            $marks = $this->marks(end($row));

            // "INTERNAL ASSESSMENT | 20 MARKS" is the marker, not a component,
            // and a "Total" row would double the sum it is meant to check.
            if ($label === '' || $marks === null || preg_match('/^(internal\s*assessment|total|grand\s*total)$/i', $label)) {
                continue;
            }

            $components[] = ['component' => $label, 'marks' => $marks];
        }

        return $components;
    }

    /**
     * The prose layout: "Periodic Assessment 05 marks + 05 marks Subject
     * Enrichment (Practical Work) 05 marks Portfolio 05 marks".
     *
     * The label class excludes digits so a component name can never swallow the
     * figure belonging to the one before it.
     *
     * A trailing "+ NN marks" is emitted as its own component with a null name,
     * NOT added to the label in front of it. In the CBSE Science syllabus that
     * tail is Multiple Assessment: the printed table lists four bullets of five
     * marks each, and the PDF-to-text step drops that one bullet's label while
     * keeping its figure. "Multiple Assessment" appears nowhere in the whole
     * extraction, so its name cannot be recovered - but folding its five marks
     * into Periodic Assessment states something the syllabus does not, and the
     * total reconciles either way (5+5+5+5 and 10+5+5 are both 20), so nothing
     * downstream would catch it. A component the extract failed to name is
     * shown as exactly that.
     *
     * @return list<array{component: string|null, marks: int}>
     */
    private function proseBreakdown(string $tail): array
    {
        // Drop the marker itself, with its "(20 Marks)" when it has one - the
        // total is not a component, and where the marker runs straight into the
        // first component ("Internal assessment Periodic Assessment 05 marks")
        // leaving it attached renames that component.
        $tail = preg_replace(
            '/^.*?internal\s*assessment\b\s*[:\-]?\s*(?:\(?\s*\d{1,3}\s*marks?\s*\)?)?/is',
            '',
            $tail
        ) ?? $tail;

        $pattern = '/([A-Za-z][A-Za-z\s\/&\(\),\.\-]{2,80}?)\s*(\d{1,3})\s*marks?(?:\s*\+\s*(\d{1,3})\s*marks?)?/i';

        if (!preg_match_all($pattern, $tail, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $components = [];

        foreach ($matches as $match) {
            $label = $this->cleanLabel($match[1]);
            $marks = (int) $match[2];

            if ($label === '' || $marks === 0) {
                continue;
            }

            $components[] = ['component' => $label, 'marks' => $marks];

            $trailing = isset($match[3]) ? (int) $match[3] : 0;

            if ($trailing > 0) {
                $components[] = ['component' => null, 'marks' => $trailing];
            }
        }

        return $components;
    }

    /**
     * The theory paper's competency weighting.
     *
     * Boards label the same column differently - "Competencies" against
     * "Typology of Questions" - and put the percentage in a different position,
     * so both the label and the percentage column are located by their headers
     * rather than assumed.
     *
     * @param  list<array{offset: int, end: int, rows: list<list<string>>}>  $tables
     * @return list<array{competency: string, percentage: float}>
     */
    private function competencies(array $tables): array
    {
        foreach ($tables as $table) {
            $header = $table['rows'][0] ?? [];

            $labelIndex = $this->indexMatching($header, '/competenc|typology/i');
            if ($labelIndex === null) {
                continue;
            }

            $percentIndex = $this->indexMatching($header, '/%|percent|weightage/i')
                ?? $this->indexMatching($header, '/^total$/i');

            if ($percentIndex === null || $percentIndex === $labelIndex) {
                continue;
            }

            $rows = [];

            foreach (array_slice($table['rows'], 1) as $row) {
                if (count($row) <= max($labelIndex, $percentIndex)) {
                    continue;
                }

                $label = $this->cleanLabel($row[$labelIndex]);
                $percentage = $this->percentage($row[$percentIndex]);

                // A wrapped continuation row carries no percentage, and the
                // closing "Total ... 100" is the sum rather than a competency.
                // Matched anywhere in the label, not just at the start: OCR
                // spills the previous row's text into that cell, so the total
                // arrives as "...proposing alternative solutions Total".
                if ($label === '' || $percentage === null || preg_match('/\btotals?\b/i', $label)) {
                    continue;
                }

                $rows[] = ['competency' => $label, 'percentage' => $percentage];
            }

            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * Marks carried by the written paper.
     *
     * Stated outright by some boards and only implied by others, so the derived
     * value is labelled as derived - the difference matters to anyone checking
     * the figure against the syllabus PDF.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function theoryMarks(string $markdown, ?int $totalMarks, ?int $internalMarks): array
    {
        // "Theory (80 marks)" and "Theory: 20 Marks, Practical: 80 Marks" are
        // both in use, and the second is the only place Physical Education
        // states that its written paper is the smaller half.
        if (preg_match('/theory\s*[:\(]\s*(\d{1,3})\s*marks?/i', $markdown, $match)) {
            return [(int) $match[1], 'document'];
        }

        // A bare "Max. Marks" is the grand total as often as it is the paper,
        // so it only counts inside a question-paper-design heading.
        if (preg_match('/paper\s*design[\s\S]{0,200}?max\.?\s*marks\s*:?\s*(\d{1,3})/i', $markdown, $match)) {
            return [(int) $match[1], 'document'];
        }

        if ($totalMarks !== null && $internalMarks !== null && $totalMarks > $internalMarks) {
            return [$totalMarks - $internalMarks, 'derived'];
        }

        return [null, null];
    }

    /** The first header cell matching $pattern. */
    private function indexMatching(array $header, string $pattern): ?int
    {
        foreach ($header as $index => $cell) {
            if (preg_match($pattern, $cell)) {
                return $index;
            }
        }

        return null;
    }

    /** "05 Marks" and "5" both mean 5; "Marks" and "" mean nothing was stated. */
    private function marks(string $cell): ?int
    {
        return preg_match('/(\d{1,3})/', $cell, $match) ? (int) $match[1] : null;
    }

    /** "50 %", "54" -> 50.0, 54.0. */
    private function percentage(string $cell): ?float
    {
        return preg_match('/(\d{1,3}(?:\.\d+)?)\s*%?/', $cell, $match) && $match[1] !== ''
            ? (float) $match[1]
            : null;
    }

    /**
     * A label trimmed to the part worth showing.
     *
     * The typology tables spell a competency out as "Remembering: Exhibit
     * memory of previously learned material by recalling facts, terms..." -
     * everything after the colon is the definition, not the name, and would not
     * fit a tooltip row.
     */
    private function cleanLabel(string $value): string
    {
        $label = trim(explode(':', $this->text($value), 2)[0]);
        $label = trim($label, " \t\n\r\0\x0B.-|");

        return strlen($label) > 90 ? rtrim(substr($label, 0, 90)) . '...' : $label;
    }

    /** Markup and entities out, single spaces in. */
    private function text(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value))) ?? '');
    }
}
