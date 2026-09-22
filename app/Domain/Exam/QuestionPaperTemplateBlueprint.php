<?php

namespace App\Domain\Exam;

/**
 * The shape of a question paper template.
 *
 * A blueprint is pure layout: page setup, header, instruction block, an ordered
 * list of sections and a footer. It carries no school, exam or question data of
 * its own -- every school-specific string is a `{{placeholder}}` that the Exam
 * module resolves against the signed-in school and the selected question paper
 * at render time. That is what lets one template serve every school.
 *
 * Blueprints are stored as JSON in `template_master.html_content` under
 * `module_name = 'Question Paper'`, so no new table is needed and each row is
 * already scoped to a school by `sub_institute_id`.
 */
class QuestionPaperTemplateBlueprint
{
    public const MODULE_NAME = 'Question Paper';

    public const VERSION = 1;

    /**
     * Placeholders a template author may use, and where each one is resolved
     * from. Surfaced by the API so the template editor can list them without
     * the frontend keeping its own copy.
     */
    public const PLACEHOLDERS = [
        'school_name' => "The signed-in school's name",
        'exam_name' => 'Name of the selected question paper',
        'paper_desc' => 'Description saved on the question paper',
        'exam_type' => 'Online / offline, from the paper',
        'subject' => 'Subject of the selected paper',
        'standard' => 'Standard / class of the selected paper',
        'grade' => 'Section of the selected paper',
        'academic_year' => 'Academic year of the paper',
        'date' => "Today's date",
        'open_date' => 'Exam open date',
        'close_date' => 'Exam close date',
        'duration' => 'Time allowed, formatted (e.g. 2 Hours)',
        'time_allowed' => 'Time allowed, in minutes',
        'total_marks' => 'Total marks, summed from the questions the template placed',
        'total_questions' => 'Number of questions the template placed',
        'section_title' => 'Title of the section being rendered',
        'section_marks' => 'Marks placed in the section being rendered',
    ];

    /** How a section draws its questions out of the selected paper. */
    public const SOURCE_MODES = ['types', 'points', 'chapters', 'rest', 'all', 'manual'];

    /** Fallback used when a stored blueprint is unreadable or a field is absent. */
    public static function defaults(): array
    {
        return [
            'version' => self::VERSION,
            'page' => [
                'size' => 'A4',
                'orientation' => 'portrait',
                'margin' => '16mm',
                'fontFamily' => 'serif',
                'fontSize' => 11,
            ],
            'header' => [
                'showLogo' => true,
                'showSchoolName' => true,
                'title' => '{{exam_name}}',
                'subtitle' => '{{subject}}',
                'metaLeft' => [['label' => 'Time', 'value' => '{{duration}}']],
                'metaRight' => [['label' => 'Marks', 'value' => '{{total_marks}}']],
                'showStudentFields' => false,
                'studentFields' => [],
                'rule' => 'single',
                'align' => 'center',
            ],
            'instructions' => [
                'title' => 'Instructions',
                'numbering' => 'decimal',
                'items' => [],
            ],
            'sections' => [self::defaultSection()],
            'footer' => [
                'text' => '',
                'showPageNumbers' => false,
            ],
        ];
    }

    public static function defaultSection(): array
    {
        return [
            'id' => 'section-1',
            'title' => '',
            'subtitle' => '',
            'note' => '',
            'instructions' => [],
            'marksLabel' => '',
            'source' => [
                'mode' => 'all',
                'questionTypes' => [],
                'points' => [],
                'chapterIds' => [],
                'questionIds' => [],
                'limit' => 0,
            ],
            'numbering' => [
                'prefix' => 'Q.',
                'style' => 'decimal',
                'start' => 1,
                'restart' => true,
                // When true the whole section is one question number and each
                // question in it prints as a lettered part -- the "Q.1 (A) ...
                // (B) ..." shape most board papers use.
                'groupAsParts' => false,
                'subStyle' => 'upper-alpha',
            ],
            'layout' => 'list',
            'showMarks' => true,
            'showQuestionType' => false,
            'optional' => [
                'enabled' => false,
                'attempt' => 0,
                'outOf' => 0,
                'label' => 'Any {{attempt}} out of {{outOf}}',
            ],
            'answerSpace' => [
                'mode' => 'none',
                'lines' => 3,
            ],
        ];
    }

    /**
     * Coerce whatever was stored (or posted) into a blueprint the renderer can
     * rely on. Unknown keys are dropped rather than trusted and every field
     * falls back to its default, so an older or hand-edited row still renders.
     */
    public static function normalize(mixed $raw): array
    {
        $input = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($input)) {
            $input = [];
        }

        $defaults = self::defaults();

        $sections = [];
        $rawSections = isset($input['sections']) && is_array($input['sections']) ? $input['sections'] : [];

        foreach (array_values($rawSections) as $index => $section) {
            $sections[] = self::normalizeSection(is_array($section) ? $section : [], $index);
        }

        if ($sections === []) {
            $sections = [self::defaultSection()];
        }

        return [
            'version' => self::VERSION,
            'page' => self::mergeShallow($defaults['page'], $input['page'] ?? []),
            'header' => self::normalizeHeader($input['header'] ?? [], $defaults['header']),
            'instructions' => [
                'title' => self::str($input['instructions']['title'] ?? $defaults['instructions']['title']),
                'numbering' => self::enum(
                    $input['instructions']['numbering'] ?? null,
                    ['decimal', 'paren', 'lower-alpha', 'roman', 'bullet', 'none'],
                    $defaults['instructions']['numbering']
                ),
                'items' => self::stringList($input['instructions']['items'] ?? []),
            ],
            'sections' => $sections,
            'footer' => [
                'text' => self::str($input['footer']['text'] ?? ''),
                'showPageNumbers' => self::bool($input['footer']['showPageNumbers'] ?? false),
            ],
        ];
    }

    private static function normalizeHeader(mixed $header, array $defaults): array
    {
        $header = is_array($header) ? $header : [];

        return [
            'showLogo' => self::bool($header['showLogo'] ?? $defaults['showLogo']),
            'showSchoolName' => self::bool($header['showSchoolName'] ?? $defaults['showSchoolName']),
            'title' => self::str($header['title'] ?? $defaults['title']),
            'subtitle' => self::str($header['subtitle'] ?? ''),
            'metaLeft' => self::metaList($header['metaLeft'] ?? []),
            'metaRight' => self::metaList($header['metaRight'] ?? []),
            'showStudentFields' => self::bool($header['showStudentFields'] ?? false),
            'studentFields' => self::stringList($header['studentFields'] ?? []),
            'rule' => self::enum($header['rule'] ?? null, ['none', 'single', 'double', 'dashed'], 'single'),
            'align' => self::enum($header['align'] ?? null, ['left', 'center'], 'center'),
        ];
    }

    private static function normalizeSection(array $section, int $index): array
    {
        $defaults = self::defaultSection();
        $source = is_array($section['source'] ?? null) ? $section['source'] : [];
        $numbering = is_array($section['numbering'] ?? null) ? $section['numbering'] : [];
        $optional = is_array($section['optional'] ?? null) ? $section['optional'] : [];
        $answerSpace = is_array($section['answerSpace'] ?? null) ? $section['answerSpace'] : [];

        return [
            'id' => self::str($section['id'] ?? '') ?: 'section-'.($index + 1),
            'title' => self::str($section['title'] ?? ''),
            'subtitle' => self::str($section['subtitle'] ?? ''),
            'note' => self::str($section['note'] ?? ''),
            'instructions' => self::stringList($section['instructions'] ?? []),
            'marksLabel' => self::str($section['marksLabel'] ?? ''),
            'source' => [
                'mode' => self::enum($source['mode'] ?? null, self::SOURCE_MODES, $defaults['source']['mode']),
                'questionTypes' => self::stringList($source['questionTypes'] ?? []),
                'points' => self::intList($source['points'] ?? []),
                'chapterIds' => self::intList($source['chapterIds'] ?? []),
                'questionIds' => self::intList($source['questionIds'] ?? []),
                'limit' => max(0, (int) ($source['limit'] ?? 0)),
            ],
            'numbering' => [
                'prefix' => self::str($numbering['prefix'] ?? $defaults['numbering']['prefix']),
                'style' => self::enum(
                    $numbering['style'] ?? null,
                    ['decimal', 'upper-alpha', 'lower-alpha', 'roman'],
                    $defaults['numbering']['style']
                ),
                'start' => max(1, (int) ($numbering['start'] ?? 1)),
                'restart' => self::bool($numbering['restart'] ?? true),
                'groupAsParts' => self::bool($numbering['groupAsParts'] ?? false),
                'subStyle' => self::enum(
                    $numbering['subStyle'] ?? null,
                    ['upper-alpha', 'lower-alpha', 'decimal', 'roman', 'none'],
                    $defaults['numbering']['subStyle']
                ),
            ],
            'layout' => self::normalizeLayout($section['layout'] ?? null),
            'showMarks' => self::bool($section['showMarks'] ?? true),
            'showQuestionType' => self::bool($section['showQuestionType'] ?? false),
            'optional' => [
                'enabled' => self::bool($optional['enabled'] ?? false),
                'attempt' => max(0, (int) ($optional['attempt'] ?? 0)),
                'outOf' => max(0, (int) ($optional['outOf'] ?? 0)),
                'label' => self::str($optional['label'] ?? $defaults['optional']['label']),
            ],
            'answerSpace' => [
                'mode' => self::enum($answerSpace['mode'] ?? null, ['none', 'lines', 'box', 'grid'], 'none'),
                'lines' => max(0, (int) ($answerSpace['lines'] ?? 0)),
            ],
        ];
    }

    /**
     * Questions always run down the page, one below the next.
     *
     * An earlier build offered a two-column section layout, which read badly on
     * a printed paper: the numbering ran down one column and back up the other,
     * and long questions collided. Blueprints already saved with it are mapped
     * onto the single-column list rather than rejected, so no school loses a
     * template it had built. Two-column density now applies only to a
     * question's own options, where it belongs.
     */
    private static function normalizeLayout(mixed $value): string
    {
        $layout = self::enum($value, ['list', 'grid-2', 'table', 'compact'], 'list');

        return $layout === 'grid-2' ? 'list' : $layout;
    }

    private static function mergeShallow(array $defaults, mixed $input): array
    {
        if (! is_array($input)) {
            return $defaults;
        }

        return array_merge($defaults, array_intersect_key($input, $defaults));
    }

    private static function metaList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = self::str($row['label'] ?? '');
            $text = self::str($row['value'] ?? '');

            if ($label === '' && $text === '') {
                continue;
            }

            $rows[] = ['label' => $label, 'value' => $text];
        }

        return $rows;
    }

    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $items[] = trim((string) $item);
            }
        }

        return array_values($items);
    }

    private static function intList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_numeric($item)) {
                $items[] = (int) $item;
            }
        }

        return array_values(array_unique($items));
    }

    private static function enum(mixed $value, array $allowed, string $fallback): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
