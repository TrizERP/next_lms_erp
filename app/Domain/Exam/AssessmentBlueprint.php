<?php

namespace App\Domain\Exam;

/**
 * The shape of an assessment blueprint's `definition`.
 *
 * A blueprint answers four questions about a paper before anyone writes it:
 *
 *   1. SECTIONS — what the paper is made of. Rows of "n questions of this type,
 *      m marks each", grouped into the sections a board prints them in. This is
 *      the part every published blueprint has, and it is the part that varies
 *      most: Delhi's Class III-V Maths paper counts sub-parts (1 question with
 *      4 sub-parts at 2 marks each), Delhi's Class VI-VIII Science paper splits
 *      one 4-mark case study as 1+1+2. Both have to fit without inventing a new
 *      column, which is why a row carries BOTH its printed `total_marks` and
 *      the pieces it was built from.
 *   2. CONTENT WEIGHTAGE — which chapters or content areas the marks go to.
 *   3. COMPETENCY DISTRIBUTION — what share of marks tests which cognitive
 *      demand. Left as a free list of {code, label, weight} so a school can load
 *      CBSE's own bands, Bloom's levels, or a foreign standard set, without any
 *      of them being hardcoded here.
 *   4. DIFFICULTY — the easy / average / difficult split.
 *
 * Everything normalises: a blueprint saved by an older build, or half-filled by
 * a coordinator who is still working on it, still loads. Nothing in here
 * refuses a partial design, because a blueprint IS partial until it is
 * finished, and a validator that rejects a draft is a validator nobody uses.
 * `validate()` reports what does not add up; it never blocks a save.
 */
class AssessmentBlueprint
{
    public const VERSION = 1;

    /** Statuses a blueprint moves through. */
    public const STATUSES = ['Draft', 'Active', 'Archived'];

    /**
     * The question forms the editor offers.
     *
     * Deliberately the vocabulary the published blueprints themselves print,
     * not this platform's internal `question_type_master` rows: a blueprint is
     * a design document that has to be able to say "Case Based / Source Based"
     * whether or not a school has ever created a question of that kind.
     */
    public const QUESTION_TYPES = [
        'mcq' => 'Objective Type (MCQ)',
        'objective_other' => 'Objective Type (fill in the blanks, match, true/false)',
        'assertion_reason' => 'Assertion-Reason',
        'vsa' => 'Very Short Answer',
        'sa1' => 'Short Answer Type I',
        'sa2' => 'Short Answer Type II',
        'sa' => 'Short Answer',
        'la' => 'Long Answer',
        'case_based' => 'Case Based / Source Based',
        'competency' => 'Competency Based',
        'practical' => 'Practical / Activity',
        'internal' => 'Internal Assessment',
    ];

    public const ASSESSMENT_TYPES = [
        'Periodic Test',
        'Unit Test',
        'Half Yearly',
        'Term',
        'Annual',
        'Board',
        'Formative',
        'Summative',
    ];

    /** Common in the Indian schools this serves; the field stays free text. */
    public const BOARDS = ['CBSE', 'Delhi DoE', 'GSEB', 'ICSE', 'State Board', 'IB', 'Cambridge', 'Other'];

    public static function defaults(): array
    {
        return [
            'version' => self::VERSION,
            'sections' => [self::defaultSection('Section A')],
            'content_weightage' => [],
            'competency_distribution' => [],
            'difficulty_distribution' => ['easy' => 0, 'average' => 0, 'difficult' => 0],
            'internal_choice_pct' => 0,
            'notes' => '',
        ];
    }

    public static function defaultSection(string $name = ''): array
    {
        return [
            'id' => 'section-' . substr(md5(uniqid('', true)), 0, 8),
            'name' => $name,
            'note' => '',
            'rows' => [self::defaultRow()],
        ];
    }

    public static function defaultRow(): array
    {
        return [
            'id' => 'row-' . substr(md5(uniqid('', true)), 0, 8),
            'question_type' => 'mcq',
            'label' => '',
            'question_numbers' => '',
            'count' => 1,
            // 0 means "this question is not split into parts". Kept separate
            // from `count` because "1 question with 4 sub-parts" and "4
            // questions" are the same marks and a different paper.
            'subparts' => 0,
            'marks_each' => 1,
            'total_marks' => 1,
            'note' => '',
        ];
    }

    /**
     * Brings any stored or posted definition up to the current shape.
     *
     * @param  mixed  $definition  Decoded JSON, a JSON string, or null.
     */
    public static function normalize(mixed $definition): array
    {
        if (is_string($definition)) {
            $definition = json_decode($definition, true);
        }

        if (! is_array($definition)) {
            return self::defaults();
        }

        $sections = [];

        foreach (self::arrayOf($definition, 'sections') as $index => $section) {
            if (! is_array($section)) {
                continue;
            }

            $rows = [];

            foreach (self::arrayOf($section, 'rows') as $row) {
                if (is_array($row)) {
                    $rows[] = self::normalizeRow($row);
                }
            }

            $sections[] = [
                'id' => self::id($section, 'section-' . ($index + 1)),
                'name' => self::text($section['name'] ?? '', 120),
                'note' => self::text($section['note'] ?? '', 300),
                'rows' => $rows !== [] ? $rows : [self::defaultRow()],
            ];
        }

        $content = [];

        foreach (self::arrayOf($definition, 'content_weightage') as $index => $area) {
            if (! is_array($area)) {
                continue;
            }

            $content[] = [
                'id' => self::id($area, 'area-' . ($index + 1)),
                'name' => self::text($area['name'] ?? '', 191),
                // Bound to a real chapter only where a school picked one; a
                // reference blueprint names its content areas in words.
                'chapter_id' => isset($area['chapter_id']) && (int) $area['chapter_id'] > 0
                    ? (int) $area['chapter_id']
                    : null,
                'marks' => self::decimal($area['marks'] ?? 0),
                'weight_pct' => self::decimal($area['weight_pct'] ?? 0, 100),
                'note' => self::text($area['note'] ?? '', 300),
            ];
        }

        $competency = [];

        foreach (self::arrayOf($definition, 'competency_distribution') as $index => $band) {
            if (! is_array($band)) {
                continue;
            }

            $competency[] = [
                'id' => self::id($band, 'competency-' . ($index + 1)),
                'code' => self::text($band['code'] ?? '', 60),
                'label' => self::text($band['label'] ?? '', 191),
                'weight_pct' => self::decimal($band['weight_pct'] ?? 0, 100),
            ];
        }

        $difficulty = is_array($definition['difficulty_distribution'] ?? null)
            ? $definition['difficulty_distribution']
            : [];

        return [
            'version' => self::VERSION,
            'sections' => $sections !== [] ? $sections : [self::defaultSection('Section A')],
            'content_weightage' => $content,
            'competency_distribution' => $competency,
            'difficulty_distribution' => [
                'easy' => self::decimal($difficulty['easy'] ?? 0, 100),
                'average' => self::decimal($difficulty['average'] ?? 0, 100),
                'difficult' => self::decimal($difficulty['difficult'] ?? 0, 100),
            ],
            'internal_choice_pct' => self::decimal($definition['internal_choice_pct'] ?? 0, 100),
            'notes' => self::text($definition['notes'] ?? '', 2000),
        ];
    }

    /** Marks the sections actually add up to, from their printed row totals. */
    public static function marksFromSections(array $definition): float
    {
        $total = 0.0;

        foreach ($definition['sections'] ?? [] as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                $total += (float) ($row['total_marks'] ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * Marks carried by the written paper — everything except rows marked as
     * internal assessment, which are earned outside it.
     */
    public static function writtenMarks(array $definition): float
    {
        $total = 0.0;

        foreach ($definition['sections'] ?? [] as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                if (($row['question_type'] ?? '') === 'internal') {
                    continue;
                }

                $total += (float) ($row['total_marks'] ?? 0);
            }
        }

        return round($total, 2);
    }

    /** What a row's own pieces multiply out to, for the editor to check against. */
    public static function computedRowMarks(array $row): float
    {
        $units = max(1, (int) ($row['subparts'] ?? 0));

        return round((float) ($row['count'] ?? 0) * $units * (float) ($row['marks_each'] ?? 0), 2);
    }

    /**
     * Everything about this blueprint that does not add up, in the words a
     * coordinator would use.
     *
     * Advisory, never blocking. A published board blueprint can legitimately
     * trip some of these — a paper carrying separate internal-assessment marks
     * will not have its sections sum to the headline total — so these are
     * things to look at, not errors to fix before saving.
     *
     * @return array<int,string>
     */
    public static function validate(array $definition, float $totalMarks): array
    {
        $warnings = [];
        $sectionMarks = self::marksFromSections($definition);

        if ($totalMarks > 0 && abs($sectionMarks - $totalMarks) > 0.01) {
            $warnings[] = sprintf(
                'The sections add up to %s marks but the paper is set to %s.',
                self::trim($sectionMarks),
                self::trim($totalMarks)
            );
        }

        foreach ($definition['sections'] ?? [] as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                $computed = self::computedRowMarks($row);

                if (abs($computed - (float) ($row['total_marks'] ?? 0)) > 0.01) {
                    $warnings[] = sprintf(
                        '%s: %s works out to %s marks, but is entered as %s.',
                        $section['name'] !== '' ? $section['name'] : 'Section',
                        self::QUESTION_TYPES[$row['question_type']] ?? 'This row',
                        self::trim($computed),
                        self::trim((float) ($row['total_marks'] ?? 0))
                    );
                }
            }
        }

        $contentMarks = array_sum(array_map(
            static fn ($area) => (float) ($area['marks'] ?? 0),
            $definition['content_weightage'] ?? []
        ));

        // Chapter weightage is a property of the WRITTEN paper. Internal
        // assessment marks are earned across a term, not against a chapter on a
        // question paper, so they are excluded from what the weightage has to
        // add up to -- otherwise every CBSE-shaped blueprint carries a warning
        // for being correct.
        $writtenMarks = self::writtenMarks($definition);
        $expected = $writtenMarks > 0 ? $writtenMarks : $totalMarks;

        if ($contentMarks > 0 && $expected > 0 && abs($contentMarks - $expected) > 0.01) {
            $warnings[] = sprintf(
                'Chapter weightage adds up to %s marks against a written paper of %s.',
                self::trim($contentMarks),
                self::trim($expected)
            );
        }

        $competency = array_sum(array_map(
            static fn ($band) => (float) ($band['weight_pct'] ?? 0),
            $definition['competency_distribution'] ?? []
        ));

        if ($competency > 0 && abs($competency - 100) > 0.01) {
            $warnings[] = sprintf('Competency weightings add up to %s%%, not 100%%.', self::trim($competency));
        }

        $difficulty = array_sum(array_values($definition['difficulty_distribution'] ?? []));

        if ($difficulty > 0 && abs($difficulty - 100) > 0.01) {
            $warnings[] = sprintf('Difficulty split adds up to %s%%, not 100%%.', self::trim($difficulty));
        }

        return $warnings;
    }

    // -- helpers -------------------------------------------------------------

    private static function normalizeRow(array $row): array
    {
        $type = (string) ($row['question_type'] ?? 'mcq');

        return [
            'id' => self::id($row, 'row-' . substr(md5(uniqid('', true)), 0, 8)),
            'question_type' => isset(self::QUESTION_TYPES[$type]) ? $type : 'mcq',
            'label' => self::text($row['label'] ?? '', 191),
            'question_numbers' => self::text($row['question_numbers'] ?? '', 60),
            'count' => max(0, (int) ($row['count'] ?? 0)),
            'subparts' => max(0, (int) ($row['subparts'] ?? 0)),
            'marks_each' => self::decimal($row['marks_each'] ?? 0),
            'total_marks' => self::decimal($row['total_marks'] ?? 0),
            'note' => self::text($row['note'] ?? '', 300),
        ];
    }

    private static function arrayOf(array $source, string $key): array
    {
        return is_array($source[$key] ?? null) ? $source[$key] : [];
    }

    private static function id(array $source, string $fallback): string
    {
        $id = trim((string) ($source['id'] ?? ''));

        return $id !== '' ? mb_substr($id, 0, 40) : $fallback;
    }

    private static function text(mixed $value, int $length): string
    {
        return mb_substr(trim((string) $value), 0, $length);
    }

    private static function decimal(mixed $value, ?float $max = null): float
    {
        $number = is_numeric($value) ? (float) $value : 0.0;
        $number = max(0.0, $number);

        return round($max === null ? $number : min($max, $number), 2);
    }

    /** 8.00 -> "8", 2.50 -> "2.5". Marks read badly with trailing zeros. */
    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
