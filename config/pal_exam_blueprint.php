<?php

/*
|--------------------------------------------------------------------------
| Board exam blueprints
|--------------------------------------------------------------------------
|
| A blueprint is the shape of a paper: how many questions of each type, worth
| how much, adding to what total. Per the architecture decision recorded in the
| PAL tracker (#19), the Blueprint is centralised as an ENGINE and the
| board-specific patterns are CONFIGURATION on top of it — so a new board is a
| new entry here, not new code.
|
| ─────────────────────────────────────────────────────────────────────────
| THE SHIPPED PATTERNS ARE ILLUSTRATIVE AND MUST BE CONFIRMED PER SCHOOL.
|
| Board patterns change by year, subject and paper, and this file is not an
| authoritative source for any board's current specification. The entry below
| exists so the engine has something real-shaped to run against; a school must
| replace it with the pattern its board has actually published for the year
| before any output is used for a real paper.
| ─────────────────────────────────────────────────────────────────────────
|
| `blueprint_category` values must exist in pal_content.blueprint_categories —
| the engine validates this, so a typo here is a hard error rather than a
| silently unfillable section.
|
*/

return [

    'blueprints' => [

        'CBSE' => [

            // Keyed so one board can carry several patterns (a full paper, a
            // half-yearly, a sample paper) without a schema change.
            'standard_theory_80' => [
                'label' => 'Standard theory paper (80 marks) — ILLUSTRATIVE, confirm against the published pattern',
                'total_marks' => 80,
                'sections' => [
                    ['section' => 'A', 'blueprint_category' => 'mcq',               'count' => 16, 'marks_each' => 1],
                    ['section' => 'A', 'blueprint_category' => 'assertion_reason',  'count' => 4,  'marks_each' => 1],
                    ['section' => 'B', 'blueprint_category' => 'very_short_answer', 'count' => 5,  'marks_each' => 2],
                    ['section' => 'C', 'blueprint_category' => 'short_answer',      'count' => 6,  'marks_each' => 3],
                    ['section' => 'D', 'blueprint_category' => 'long_answer',       'count' => 4,  'marks_each' => 5],
                    ['section' => 'E', 'blueprint_category' => 'case_based',        'count' => 3,  'marks_each' => 4],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Difficulty spread
    |--------------------------------------------------------------------------
    |
    | The share of marks a paper should carry at each difficulty band. Reported
    | as guidance against what the available pool could actually produce, never
    | enforced: an item's difficulty comes from psychometrics that most of the
    | estate does not yet have (see #1/#2), so treating this as a hard
    | constraint would make every paper report as infeasible for a reason that
    | is about data coverage rather than about the paper.
    |
    */
    'difficulty_spread' => [
        'easy' => ['levels' => [1, 2], 'target_share' => 0.20],
        'medium' => ['levels' => [3], 'target_share' => 0.50],
        'hard' => ['levels' => [4, 5], 'target_share' => 0.30],
    ],
];
