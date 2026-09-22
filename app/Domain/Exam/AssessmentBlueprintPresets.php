<?php

namespace App\Domain\Exam;

/**
 * Published blueprints a school can start from.
 *
 * These are transcriptions of real documents, not illustrations. Every number
 * below came off a published paper design, and `source_url` on each one points
 * at where it came from so a coordinator can check it rather than take our word
 * for it. That is the whole point of shipping references: a school that has to
 * invent its own weightings will invent them badly, and a school handed
 * invented weightings under an official-looking name is worse off still.
 *
 * Confidence is NOT the same across these, and the descriptions say so:
 *
 *  - The two Delhi DoE designs were read directly out of the Directorate's own
 *    2025-26 PDFs. Section, type, count, sub-parts and marks are exactly as
 *    printed.
 *  - The CBSE Class X Science design follows CBSE's published 2025-26 paper
 *    pattern. Its SECTION structure is well established and stable; its
 *    COMPETENCY percentages are the widely reported 2025-26 figures and should
 *    be confirmed against the official sample paper before a school relies on
 *    them, which is what the blueprint's own note says.
 *
 * A preset is never edited in place. Using one clones it into the school, where
 * every part of it can be changed — see AssessmentBlueprintApiController.
 */
class AssessmentBlueprintPresets
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        return [
            self::delhiMathematics3to5(),
            self::delhiScience6to8(),
            self::cbseScienceClass10(),
        ];
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $preset) {
            if ($preset['preset_key'] === $key) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Directorate of Education, Delhi — Question Paper Design for Classes III-V,
     * Mathematics, Session 2025-26. 10 questions, 50 marks.
     *
     * The sub-part counting matters here and is why `subparts` exists as its own
     * field: Section A is THREE questions carrying 20 marks between them, not
     * twelve questions, and a generator told to produce twelve would produce the
     * wrong paper.
     */
    private static function delhiMathematics3to5(): array
    {
        return [
            'preset_key' => 'delhi-doe-3to5-mathematics-2025-26',
            'name' => 'Mathematics — Classes III-V',
            'description' => 'Delhi Directorate of Education paper design for 2025-26, transcribed from the published PDF. 10 questions, 50 marks.',
            'board' => 'Delhi DoE',
            'class_band' => 'III-V',
            'subject_label' => 'Mathematics',
            'assessment_type' => 'Term',
            'academic_year' => '2025-26',
            'total_marks' => 50,
            'duration_minutes' => null,
            'source' => 'Delhi DoE 2025-26',
            'source_url' => 'https://edustud.nic.in/edu/BLUEPRINT_2025/3to5/Mathematics.pdf',
            'definition' => [
                'version' => AssessmentBlueprint::VERSION,
                'sections' => [
                    [
                        'id' => 'section-a',
                        'name' => 'Section A',
                        'note' => 'Case Based / Source Based Type',
                        'rows' => [
                            self::row('a1', 'mcq', 'MCQs', '', 1, 4, 2, 8),
                            self::row('a2', 'mcq', 'MCQs', '', 1, 4, 2, 8),
                            self::row('a3', 'vsa', 'Very Short Answer Type', '', 1, 4, 1, 4),
                        ],
                    ],
                    [
                        'id' => 'section-b',
                        'name' => 'Section B',
                        'note' => '',
                        'rows' => [
                            self::row(
                                'b1',
                                'objective_other',
                                'Objective Type (fill in the blanks, match the following, true-false etc.)',
                                '',
                                2,
                                5,
                                1,
                                10
                            ),
                        ],
                    ],
                    [
                        'id' => 'section-c',
                        'name' => 'Section C',
                        'note' => '',
                        'rows' => [
                            self::row('c1', 'sa1', 'Short Answer Type I', '', 2, 0, 3, 6),
                            self::row('c2', 'sa2', 'Short Answer Type II', '', 1, 0, 4, 4),
                            self::row('c3', 'la', 'Long Answer Type', '', 2, 0, 5, 10),
                        ],
                    ],
                ],
                'content_weightage' => [],
                'competency_distribution' => [],
                'difficulty_distribution' => ['easy' => 0, 'average' => 0, 'difficult' => 0],
                'internal_choice_pct' => 33,
                'notes' => "Total number of questions: 10. Total marks: 50.\nInternal choice will be approximately 33% of the total weightage.\n\nChapter weightage is not prescribed in the Directorate's design — add your own against the chapters you have actually taught.",
            ],
        ];
    }

    /**
     * Directorate of Education, Delhi — Question Paper Design for Classes
     * VI-VIII, Science, Session 2025-26. 15 questions, 60 marks.
     */
    private static function delhiScience6to8(): array
    {
        return [
            'preset_key' => 'delhi-doe-6to8-science-2025-26',
            'name' => 'Science — Classes VI-VIII',
            'description' => 'Delhi Directorate of Education paper design for 2025-26, transcribed from the published PDF. 15 questions, 60 marks.',
            'board' => 'Delhi DoE',
            'class_band' => 'VI-VIII',
            'subject_label' => 'Science',
            'assessment_type' => 'Term',
            'academic_year' => '2025-26',
            'total_marks' => 60,
            'duration_minutes' => null,
            'source' => 'Delhi DoE 2025-26',
            'source_url' => 'https://edustud.nic.in/edu/BLUEPRINT_2025/6to8/Science.pdf',
            'definition' => [
                'version' => AssessmentBlueprint::VERSION,
                'sections' => [
                    [
                        'id' => 'section-a',
                        'name' => 'Section A',
                        'note' => '',
                        'rows' => [
                            self::row('a1', 'mcq', 'Objective Type (MCQs)', '1', 1, 12, 1, 12, 'One question with sub-parts I-XII.'),
                        ],
                    ],
                    [
                        'id' => 'section-b',
                        'name' => 'Section B',
                        'note' => '',
                        'rows' => [self::row('b1', 'vsa', 'Very Short Answer Type', '2-5', 4, 0, 2, 8)],
                    ],
                    [
                        'id' => 'section-c',
                        'name' => 'Section C',
                        'note' => '',
                        'rows' => [self::row('c1', 'sa', 'Short Answer Type', '6-8', 3, 0, 3, 9)],
                    ],
                    [
                        'id' => 'section-d',
                        'name' => 'Section D',
                        'note' => '',
                        'rows' => [self::row('d1', 'la', 'Long Answer Type', '9-11', 3, 0, 5, 15)],
                    ],
                    [
                        'id' => 'section-e',
                        'name' => 'Section E',
                        'note' => '',
                        'rows' => [
                            self::row(
                                'e1',
                                'case_based',
                                'Case Based / Source Based Type',
                                '12-15',
                                4,
                                0,
                                4,
                                16,
                                'Each 4-mark question is split 1+1+2 across sub-parts I-III.'
                            ),
                        ],
                    ],
                ],
                'content_weightage' => [],
                'competency_distribution' => [],
                'difficulty_distribution' => ['easy' => 0, 'average' => 0, 'difficult' => 0],
                'internal_choice_pct' => 33,
                'notes' => "Total number of questions: 15. Total marks: 60.\nInternal choice will be approximately 33% of the total weightage.\n\nChapter weightage is not prescribed in the Directorate's design — add your own against the chapters you have actually taught.",
            ],
        ];
    }

    /**
     * CBSE Class X Science, 2025-26 pattern. 39 questions, 80 marks theory,
     * plus 20 marks internal assessment carried separately.
     *
     * The 20 internal marks are recorded as their own section row rather than
     * left out, so the blueprint's headline total is the 100 a report card
     * shows — and the sections still add up, which is what the validator checks.
     */
    private static function cbseScienceClass10(): array
    {
        return [
            'preset_key' => 'cbse-class10-science-2025-26',
            'name' => 'Science — Class X',
            'description' => 'CBSE Class X Science 2025-26 pattern: 39 questions across five sections, 80 marks theory plus 20 internal. Confirm the competency percentages against the official sample paper before relying on them.',
            'board' => 'CBSE',
            'class_band' => 'IX-X',
            'subject_label' => 'Science',
            'assessment_type' => 'Board',
            'academic_year' => '2025-26',
            'total_marks' => 100,
            'duration_minutes' => 180,
            'source' => 'CBSE 2025-26 pattern',
            'source_url' => 'https://cbseacademic.nic.in/SQP_CLASSX_2025-26.html',
            'definition' => [
                'version' => AssessmentBlueprint::VERSION,
                'sections' => [
                    [
                        'id' => 'section-a',
                        'name' => 'Section A',
                        'note' => 'Objective type, including assertion-reason',
                        'rows' => [self::row('a1', 'mcq', 'Objective Type', '1-20', 20, 0, 1, 20)],
                    ],
                    [
                        'id' => 'section-b',
                        'name' => 'Section B',
                        'note' => '',
                        'rows' => [self::row('b1', 'vsa', 'Very Short Answer', '21-26', 6, 0, 2, 12)],
                    ],
                    [
                        'id' => 'section-c',
                        'name' => 'Section C',
                        'note' => '',
                        'rows' => [self::row('c1', 'sa', 'Short Answer', '27-33', 7, 0, 3, 21)],
                    ],
                    [
                        'id' => 'section-d',
                        'name' => 'Section D',
                        'note' => '',
                        'rows' => [self::row('d1', 'la', 'Long Answer', '34-36', 3, 0, 5, 15)],
                    ],
                    [
                        'id' => 'section-e',
                        'name' => 'Section E',
                        'note' => 'Source / case / passage based, with sub-parts',
                        'rows' => [self::row('e1', 'case_based', 'Case Based', '37-39', 3, 0, 4, 12)],
                    ],
                    [
                        'id' => 'section-internal',
                        'name' => 'Internal Assessment',
                        'note' => 'Carried outside the written paper',
                        'rows' => [self::row('i1', 'internal', 'Internal Assessment', '', 1, 0, 20, 20)],
                    ],
                ],
                'content_weightage' => [
                    ['id' => 'area-bio', 'name' => 'Biology', 'chapter_id' => null, 'marks' => 30, 'weight_pct' => 37.5, 'note' => 'Of the 80-mark theory paper.'],
                    ['id' => 'area-chem', 'name' => 'Chemistry', 'chapter_id' => null, 'marks' => 25, 'weight_pct' => 31.25, 'note' => 'Of the 80-mark theory paper.'],
                    ['id' => 'area-phy', 'name' => 'Physics', 'chapter_id' => null, 'marks' => 25, 'weight_pct' => 31.25, 'note' => 'Of the 80-mark theory paper.'],
                ],
                'competency_distribution' => [
                    ['id' => 'comp-ku', 'code' => 'knowledge_understanding', 'label' => 'Demonstrate Knowledge and Understanding', 'weight_pct' => 50],
                    ['id' => 'comp-app', 'code' => 'application', 'label' => 'Application of Knowledge / Concepts', 'weight_pct' => 30],
                    ['id' => 'comp-hots', 'code' => 'analyse_evaluate_create', 'label' => 'Analyse, Evaluate and Create', 'weight_pct' => 20],
                ],
                'difficulty_distribution' => ['easy' => 0, 'average' => 0, 'difficult' => 0],
                'internal_choice_pct' => 33,
                'notes' => "39 questions, 80 marks theory + 20 marks internal assessment.\nInternal choice of roughly 33% is provided.\n\nThe subject-area split (Biology 30 / Chemistry 25 / Physics 25) is against the 80-mark theory paper, which is why it does not sum to the 100 shown above.\n\nThe competency percentages are CBSE's reported 2025-26 figures. Confirm them against the official sample paper for your session before building a paper on them.",
            ],
        ];
    }

    /**
     * One row of a section's table.
     *
     * `$total` is passed rather than computed because the published documents
     * print it, and where a board's arithmetic and ours ever disagree the
     * board's number is the one a school is accountable to. The editor shows
     * both and flags the difference.
     */
    private static function row(
        string $id,
        string $type,
        string $label,
        string $numbers,
        int $count,
        int $subparts,
        float $marksEach,
        float $total,
        string $note = ''
    ): array {
        return [
            'id' => $id,
            'question_type' => $type,
            'label' => $label,
            'question_numbers' => $numbers,
            'count' => $count,
            'subparts' => $subparts,
            'marks_each' => $marksEach,
            'total_marks' => $total,
            'note' => $note,
        ];
    }
}
