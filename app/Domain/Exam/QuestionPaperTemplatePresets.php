<?php

namespace App\Domain\Exam;

/**
 * Worked examples of what the question paper template can express.
 *
 * These exist to show the range of the blueprint -- a board-style paper with
 * optional questions, a short class test, a sectioned semester paper -- not to
 * constrain a school to three layouts. They live in code rather than in the
 * database so every school sees them without anything being seeded, and so a
 * school that picks one gets its own editable copy (a `template_master` row)
 * instead of sharing a row with anyone else.
 *
 * Nothing here names a school, subject, standard, mark or question: each is a
 * `{{placeholder}}` resolved from the signed-in school and the selected paper.
 */
class QuestionPaperTemplatePresets
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return [
            self::boardExam(),
            self::unitTest(),
            self::semesterExam(),
        ];
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $preset) {
            if ($preset['key'] === $key) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Board / annual examination: a formal letterhead, a numbered note block,
     * parts that draw different kinds of question, and "attempt any two of
     * three" style optional groups.
     */
    private static function boardExam(): array
    {
        return [
            'key' => 'board-exam',
            'name' => 'School exam question paper',
            'description' => 'Formal board-style paper: numbered note block, lettered parts, per-part optional questions and a centred footer rule.',
            'blueprint' => [
                'version' => QuestionPaperTemplateBlueprint::VERSION,
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
                    'title' => 'Format of question paper for {{standard}}',
                    'subtitle' => '{{subject}}',
                    'metaLeft' => [['label' => 'Time', 'value' => '{{duration}}']],
                    'metaRight' => [['label' => 'Marks', 'value' => '{{total_marks}}']],
                    'showStudentFields' => false,
                    'studentFields' => [],
                    'rule' => 'double',
                    'align' => 'center',
                ],
                'instructions' => [
                    'title' => 'Note:',
                    'numbering' => 'paren',
                    'items' => [
                        'All questions are compulsory.',
                        'Figures to the right indicate full marks.',
                        'Draw neat, labelled diagrams wherever necessary.',
                        'Answers to every part of a question must be written together.',
                    ],
                ],
                'sections' => [
                    [
                        'id' => 'part-a',
                        'title' => 'PART A',
                        'subtitle' => 'Objective questions',
                        'note' => '',
                        'instructions' => ['Choose the correct alternative and rewrite the statement.'],
                        'marksLabel' => '',
                        'source' => [
                            'mode' => 'types',
                            'questionTypes' => ['multiple'],
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
                            // The whole part prints as "Q.1" with lettered
                            // sub-questions under it, as board papers do.
                            'groupAsParts' => true,
                            'subStyle' => 'upper-alpha',
                        ],
                        'layout' => 'list',
                        'showMarks' => true,
                        'showQuestionType' => false,
                        'optional' => ['enabled' => false, 'attempt' => 0, 'outOf' => 0, 'label' => 'Any {{attempt}} out of {{outOf}}'],
                        'answerSpace' => ['mode' => 'none', 'lines' => 0],
                    ],
                    [
                        'id' => 'part-b',
                        'title' => 'PART B',
                        'subtitle' => 'Short answers',
                        'note' => '',
                        'instructions' => ['Write short notes on the following.'],
                        'marksLabel' => '',
                        'source' => [
                            'mode' => 'points',
                            'questionTypes' => [],
                            'points' => [2, 3],
                            'chapterIds' => [],
                            'questionIds' => [],
                            'limit' => 0,
                        ],
                        'numbering' => [
                            'prefix' => 'Q.',
                            'style' => 'decimal',
                            'start' => 1,
                            'restart' => false,
                            'subStyle' => 'upper-alpha',
                        ],
                        'layout' => 'list',
                        'showMarks' => true,
                        'showQuestionType' => false,
                        'optional' => ['enabled' => true, 'attempt' => 2, 'outOf' => 3, 'label' => 'Any {{attempt}} out of {{outOf}}'],
                        'answerSpace' => ['mode' => 'lines', 'lines' => 3],
                    ],
                    [
                        'id' => 'part-c',
                        'title' => 'PART C',
                        'subtitle' => 'Answer in detail',
                        'note' => '',
                        'instructions' => [],
                        'marksLabel' => '',
                        'source' => [
                            'mode' => 'rest',
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
                            'restart' => false,
                            'subStyle' => 'upper-alpha',
                        ],
                        'layout' => 'list',
                        'showMarks' => true,
                        'showQuestionType' => false,
                        'optional' => ['enabled' => true, 'attempt' => 2, 'outOf' => 3, 'label' => 'Any {{attempt}} out of {{outOf}}'],
                        'answerSpace' => ['mode' => 'lines', 'lines' => 5],
                    ],
                ],
                'footer' => [
                    'text' => '*******',
                    'showPageNumbers' => false,
                ],
            ],
        ];
    }

    /** Unit test: one page, one list of questions, room to answer in place. */
    private static function unitTest(): array
    {
        return [
            'key' => 'unit-test',
            'name' => 'Unit test / class test',
            'description' => 'Compact single-section test with a name and roll-number strip, questions answered on the paper itself.',
            'blueprint' => [
                'version' => QuestionPaperTemplateBlueprint::VERSION,
                'page' => [
                    'size' => 'A4',
                    'orientation' => 'portrait',
                    'margin' => '14mm',
                    'fontFamily' => 'sans',
                    'fontSize' => 11,
                ],
                'header' => [
                    'showLogo' => true,
                    'showSchoolName' => true,
                    'title' => '{{exam_name}}',
                    'subtitle' => '{{subject}} | {{standard}}',
                    'metaLeft' => [
                        ['label' => 'Time', 'value' => '{{duration}}'],
                        ['label' => 'Date', 'value' => '{{date}}'],
                    ],
                    'metaRight' => [['label' => 'Max. marks', 'value' => '{{total_marks}}']],
                    'showStudentFields' => true,
                    'studentFields' => ['Name', 'Roll No.', 'Division'],
                    'rule' => 'single',
                    'align' => 'center',
                ],
                'instructions' => [
                    'title' => 'Instructions',
                    'numbering' => 'decimal',
                    'items' => [
                        'All questions are compulsory.',
                        'Marks for each question are shown in brackets.',
                        'Write answers in the space provided.',
                    ],
                ],
                'sections' => [
                    [
                        'id' => 'questions',
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
                            'prefix' => '',
                            'style' => 'decimal',
                            'start' => 1,
                            'restart' => true,
                            'subStyle' => 'lower-alpha',
                        ],
                        'layout' => 'compact',
                        'showMarks' => true,
                        'showQuestionType' => false,
                        'optional' => ['enabled' => false, 'attempt' => 0, 'outOf' => 0, 'label' => 'Any {{attempt}} out of {{outOf}}'],
                        'answerSpace' => ['mode' => 'lines', 'lines' => 2],
                    ],
                ],
                'footer' => [
                    'text' => 'End of test',
                    'showPageNumbers' => true,
                ],
            ],
        ];
    }

    /** Semester / final: lettered sections by question weight, page numbered. */
    private static function semesterExam(): array
    {
        return [
            'key' => 'semester-exam',
            'name' => 'Semester / final examination',
            'description' => 'Multi-section paper graded by question weight — objective, short, long — with per-section marks, answer space and page numbers.',
            'blueprint' => [
                'version' => QuestionPaperTemplateBlueprint::VERSION,
                'page' => [
                    'size' => 'A4',
                    'orientation' => 'portrait',
                    'margin' => '18mm',
                    'fontFamily' => 'serif',
                    'fontSize' => 12,
                ],
                'header' => [
                    'showLogo' => true,
                    'showSchoolName' => true,
                    'title' => '{{exam_name}}',
                    'subtitle' => '{{subject}} — {{standard}} — {{academic_year}}',
                    'metaLeft' => [
                        ['label' => 'Time', 'value' => '{{duration}}'],
                        ['label' => 'Date', 'value' => '{{date}}'],
                    ],
                    'metaRight' => [
                        ['label' => 'Total marks', 'value' => '{{total_marks}}'],
                        ['label' => 'Questions', 'value' => '{{total_questions}}'],
                    ],
                    'showStudentFields' => true,
                    'studentFields' => ['Student name', 'Roll No.', 'Seat No.', 'Invigilator sign'],
                    'rule' => 'double',
                    'align' => 'center',
                ],
                'instructions' => [
                    'title' => 'General instructions',
                    'numbering' => 'roman',
                    'items' => [
                        'This question paper is divided into sections. All sections are compulsory.',
                        'Read each question carefully before answering.',
                        'Figures to the right indicate full marks for the question.',
                        'Use of a calculator is not permitted unless stated otherwise.',
                    ],
                ],
                'sections' => [
                    [
                        'id' => 'section-a',
                        'title' => 'SECTION A',
                        'subtitle' => 'Multiple choice questions',
                        'note' => 'Shade or write the letter of the correct option.',
                        'instructions' => [],
                        'marksLabel' => '{{section_marks}} marks',
                        'source' => [
                            'mode' => 'types',
                            'questionTypes' => ['multiple'],
                            'points' => [],
                            'chapterIds' => [],
                            'questionIds' => [],
                            'limit' => 0,
                        ],
                        'numbering' => [
                            'prefix' => 'Q.',
                            'style' => 'decimal',
                            'start' => 1,
                            'restart' => false,
                            'subStyle' => 'upper-alpha',
                        ],
                        'layout' => 'list',
                        'showMarks' => true,
                        'showQuestionType' => false,
                        'optional' => ['enabled' => false, 'attempt' => 0, 'outOf' => 0, 'label' => 'Any {{attempt}} out of {{outOf}}'],
                        'answerSpace' => ['mode' => 'none', 'lines' => 0],
                    ],
                    [
                        'id' => 'section-b',
                        'title' => 'SECTION B',
                        'subtitle' => 'Short answer questions',
                        'note' => '',
                        'instructions' => [],
                        'marksLabel' => '{{section_marks}} marks',
                        'source' => [
                            'mode' => 'points',
                            'questionTypes' => [],
                            'points' => [2, 3],
                            'chapterIds' => [],
                            'questionIds' => [],
                            'limit' => 0,
                        ],
                        'numbering' => [
                            'prefix' => 'Q.',
                            'style' => 'decimal',
                            'start' => 1,
                            'restart' => false,
                            'subStyle' => 'upper-alpha',
                        ],
                        'layout' => 'list',
                        'showMarks' => true,
                        'showQuestionType' => false,
                        'optional' => ['enabled' => false, 'attempt' => 0, 'outOf' => 0, 'label' => 'Any {{attempt}} out of {{outOf}}'],
                        'answerSpace' => ['mode' => 'lines', 'lines' => 4],
                    ],
                    [
                        'id' => 'section-c',
                        'title' => 'SECTION C',
                        'subtitle' => 'Long answer questions',
                        'note' => '',
                        'instructions' => [],
                        'marksLabel' => '{{section_marks}} marks',
                        'source' => [
                            'mode' => 'rest',
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
                            'restart' => false,
                            'subStyle' => 'upper-alpha',
                        ],
                        'layout' => 'list',
                        'showMarks' => true,
                        'showQuestionType' => false,
                        'optional' => ['enabled' => true, 'attempt' => 3, 'outOf' => 4, 'label' => 'Attempt any {{attempt}} of the following {{outOf}}'],
                        'answerSpace' => ['mode' => 'lines', 'lines' => 6],
                    ],
                ],
                'footer' => [
                    'text' => '— End of question paper —',
                    'showPageNumbers' => true,
                ],
            ],
        ];
    }
}
