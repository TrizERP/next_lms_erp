<?php

namespace App\Domain\Exam;

/**
 * Published Holistic Progress Card designs a school can start from.
 *
 * Both are transcriptions of the official documents, and each carries its
 * `source_url` so a coordinator can check rather than trust us — the same rule
 * the marks-based references follow.
 *
 * WHAT THESE DELIBERATELY DO NOT CONTAIN. Neither preset ships a filled-in list
 * of curricular goals and competencies, because neither document publishes one:
 * the Middle Stage card literally prints "Curricular Goals (Choose one or
 * more)" as a blank for the teacher, and the goals themselves live in the NCF
 * for that stage, not in the card. Inventing a plausible-looking NCF chain and
 * labelling it official would be worse than shipping the frame empty. So the
 * areas arrive named and correct, and the validator then tells a school exactly
 * what it still has to fill in from the NCF — which is the true state of the
 * work, not a defect.
 *
 * The one exception is the single Language and Literacy curricular goal quoted
 * verbatim in the CBSE Foundational guide, with the two competencies it gives
 * as worked examples. That is a real example from the document, and it is
 * marked as such, so a school can see the shape before filling the rest.
 */
class HpcBlueprintPresets
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        return [
            self::foundationalStage(),
            self::middleStage(),
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
     * CBSE, Holistic Progress Card for the Foundational Stage — an
     * implementation guide for teachers, adapted from the PARAKH HPC document.
     *
     * Note the scale: Beginner / Progressive / Proficient, with the guide's own
     * descriptors. This is NOT the Middle Stage scale, and the difference is
     * the reason the scale is stored per blueprint instead of assumed.
     */
    private static function foundationalStage(): array
    {
        return [
            'preset_key' => 'hpc-foundational-cbse',
            'name' => 'HPC — Foundational Stage',
            'description' => 'CBSE implementation guide for the Foundational Stage (ages 3-8), adapted from PARAKH. Five development domains plus Positive Learning Habits, judged as Beginner / Progressive / Proficient.',
            'board' => 'CBSE',
            'class_band' => 'I-II',
            'stage' => 'Foundational',
            'subject_label' => 'All domains',
            'assessment_type' => 'Formative',
            'academic_year' => '2025-26',
            'source' => 'CBSE / PARAKH HPC — Foundational Stage',
            'source_url' => 'https://cbseacademic.nic.in/web_material/Manuals/HPC_TeacherGuide.pdf',
            'definition' => [
                'version' => HpcBlueprint::VERSION,
                'stage' => 'Foundational',
                'proficiency_scale' => [
                    [
                        'code' => 'beginner',
                        'label' => 'Beginner',
                        'descriptor' => 'Tries to achieve the Competency and associated Learning Outcomes with a lot of support from teachers.',
                    ],
                    [
                        'code' => 'progressive',
                        'label' => 'Progressive',
                        'descriptor' => 'Achieves the Competency and associated Learning Outcomes with occasional/some support from teachers.',
                    ],
                    [
                        'code' => 'proficient',
                        'label' => 'Proficient',
                        'descriptor' => 'Achieves the Competency and associated Learning Outcomes on his/her own.',
                    ],
                ],
                'assessors' => ['teacher', 'parent', 'self', 'peer'],
                'abilities' => [],
                'areas' => [
                    self::area('Physical Development', 'physical'),
                    self::area('Socio-emotional and Ethical Development', 'socio_emotional'),
                    self::area('Cognitive Development', 'cognitive'),
                    [
                        'id' => 'area-language',
                        'name' => 'Language and Literacy Development',
                        'code' => 'language',
                        'note' => 'The curricular goal below is the worked example printed in the guide — the rest come from NCF-FS 2022.',
                        'curricular_goals' => [
                            [
                                'id' => 'cg-language-example',
                                'code' => '',
                                'name' => 'Children develop effective communication skills for day-to-day interactions in two languages',
                                'competencies' => [
                                    [
                                        'id' => 'c-language-1',
                                        'code' => '',
                                        'name' => 'Converses fluently and can hold a meaningful conversation',
                                        'learning_outcomes' => [],
                                        'assessors' => ['teacher'],
                                        'evidence_modes' => ['observation', 'conversation'],
                                    ],
                                    [
                                        'id' => 'c-language-2',
                                        'code' => '',
                                        'name' => 'Understands oral instructions for a complex task and gives clear oral instructions for the same',
                                        'learning_outcomes' => [],
                                        'assessors' => ['teacher'],
                                        'evidence_modes' => ['observation', 'conversation'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    self::area('Aesthetic and Cultural Development', 'aesthetic'),
                    [
                        'id' => 'area-habits',
                        'name' => 'Positive Learning Habits',
                        'code' => 'habits',
                        'note' => 'Developed alongside the five domains: intentional action, mental flexibility, self-control and self-regulation, observation and exploration.',
                        'curricular_goals' => [],
                    ],
                ],
                'activity_approaches' => ['experiential', 'toy_based', 'art_integrated'],
                'evidence_modes' => ['observation', 'portfolio', 'activity', 'conversation'],
                // Goal setting and the ambition card start from the Middle
                // Stage card, so they are off here rather than shown empty.
                'part_a' => [
                    'attendance' => true,
                    'interest' => true,
                    'all_about_me' => true,
                    'goal_setting' => false,
                    'ambition_card' => false,
                ],
                'strengths' => [],
                'barriers' => [],
                'notes' => "The two methods of assessment the guide names as appropriate at this stage are observation of the child, and analysing the evidence gathered as part of their learning experience. There are no marks and no ranking.\n\nThe level of attainment may be shown with any neutral icon — a flower, a tree, a smiley — rather than a grade.\n\nCurricular goals and competencies are not printed in the card: take them from NCF-FS 2022 for your stage and add them under each domain. The Language and Literacy goal already here is the worked example from the guide, kept to show the shape.",
            ],
        ];
    }

    /**
     * NCERT / PARAKH, Holistic Progress Card — Middle Stage, August 2025
     * (ISBN 978-93-5292-586-5). Grades 6 to 8.
     *
     * This is the 360-degree card: the same activity is judged by the student,
     * a peer and the teacher, against three abilities, on a three-level scale.
     */
    private static function middleStage(): array
    {
        return [
            'preset_key' => 'hpc-middle-ncert-2025',
            'name' => 'HPC — Middle Stage (Grades 6-8)',
            'description' => 'NCERT / PARAKH Holistic Progress Card for the Middle Stage, August 2025. Nine curricular areas, judged by student, peer and teacher against Awareness, Sensitivity and Creativity on a Beginner / Proficient / Advanced scale.',
            'board' => 'CBSE',
            'class_band' => 'VI-VIII',
            'stage' => 'Middle',
            'subject_label' => 'All curricular areas',
            'assessment_type' => 'Formative',
            'academic_year' => '2025-26',
            'source' => 'NCERT / PARAKH HPC — Middle Stage (Aug 2025)',
            'source_url' => 'https://parakh.ncert.gov.in/themes/parakh/hpc-files/cards-pdf/Holistic-Progress-Card-(Middle-Stage).pdf',
            'definition' => [
                'version' => HpcBlueprint::VERSION,
                'stage' => 'Middle',
                'proficiency_scale' => [
                    [
                        'code' => 'beginner',
                        'label' => 'Beginner',
                        'descriptor' => 'Scoring key on the progress grid: 0, 1 or 2 statements circled.',
                    ],
                    [
                        'code' => 'proficient',
                        'label' => 'Proficient',
                        'descriptor' => 'Scoring key on the progress grid: 3 or 4 statements circled.',
                    ],
                    [
                        'code' => 'advanced',
                        'label' => 'Advanced',
                        'descriptor' => 'Scoring key on the progress grid: 5 or 6 statements circled.',
                    ],
                ],
                'assessors' => ['self', 'peer', 'teacher', 'parent'],
                // Named per area on the card itself -- Language Education's row
                // reads "Literary Awareness", and each subject prefixes them
                // the same way.
                'abilities' => [
                    ['id' => 'ability-awareness', 'code' => 'awareness', 'label' => 'Awareness'],
                    ['id' => 'ability-sensitivity', 'code' => 'sensitivity', 'label' => 'Sensitivity'],
                    ['id' => 'ability-creativity', 'code' => 'creativity', 'label' => 'Creativity'],
                ],
                'areas' => [
                    self::area('Language Education (R1)', 'language_r1'),
                    self::area('Language Education (R2)', 'language_r2'),
                    self::area('Language Education (R3)', 'language_r3'),
                    self::area('Mathematics Education', 'mathematics'),
                    self::area('Science Education', 'science'),
                    self::area('Social Science Education', 'social_science'),
                    self::area('Art Education', 'art'),
                    self::area('Physical Education and Well-Being', 'physical_education'),
                    self::area('Skill Education', 'skill'),
                ],
                'activity_approaches' => array_keys(HpcBlueprint::ACTIVITY_APPROACHES),
                'evidence_modes' => ['activity', 'self_reflection', 'peer_feedback', 'observation', 'portfolio'],
                'part_a' => [
                    'attendance' => true,
                    'interest' => true,
                    'all_about_me' => true,
                    'goal_setting' => true,
                    'ambition_card' => true,
                ],
                'strengths' => HpcBlueprint::STRENGTHS,
                'barriers' => HpcBlueprint::BARRIERS,
                'notes' => "Each activity is judged three times over: the student circles statements on their own Progress Grid, a peer fills the Peer Feedback sheet, and the teacher records a level per ability on the Student Progress Wheel. There are no marks.\n\nScoring key for the progress grid: Beginner 0-2 statements, Proficient 3-4, Advanced 5-6.\n\nThe abilities are named per subject on the card — Language Education reads \"Literary Awareness\", \"Literary Sensitivity\", \"Literary Creativity\".\n\nCurricular goals and competencies are chosen per activity from the NCF for this stage; the card prints them as blanks, so add the ones you teach against under each area.",
            ],
        ];
    }

    /** An area with its name and code, and its NCF chain left for the school. */
    private static function area(string $name, string $code): array
    {
        return [
            'id' => 'area-' . $code,
            'name' => $name,
            'code' => $code,
            'note' => '',
            'curricular_goals' => [],
        ];
    }
}
