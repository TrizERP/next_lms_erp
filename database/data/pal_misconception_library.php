<?php

/*
|--------------------------------------------------------------------------
| PAL V4 — seed misconception library
|--------------------------------------------------------------------------
|
| Source: PAL_V4_Content_Intelligence_Layer.md §4.2 (sample entries).
| Loaded by `php artisan pal:seed-misconceptions`.
|
| These seed as scope='global' / sub_institute_id=0 — shared curriculum
| vocabulary, the single documented exception to per-tenant scoping (CONTENT
| LAW C3). A tenant that wants its own variant of an entry authors a
| tenant-scoped row with the same tag; the router prefers the tenant's own.
|
| CONTENT LAW C6: every entry here ships with at least one corrective. An entry
| with no corrective is rejected by the seeder rather than written, because
| detecting an error and then showing nothing is worse than not detecting it.
|
| `tag` is permanent. It is a foreign key in learner history — rename it and you
| orphan learner records. Deprecate instead.
|
| SMEs extend this file. `typical_wrong_answers` is what drives exact-match
| detection, so it is the highest-value field to get right: every additional
| real wrong answer observed in the field should be added here.
|
| STATUS: seeded as 'draft'. Nothing here reaches a learner until a human
| approves it (C4/C5) — these are spec examples, not tenant-validated content.
|
*/

return [

    // ══════════════════════════════════════════════════════════════════
    // MATHEMATICS — Fractions (spec §4.2)
    // ══════════════════════════════════════════════════════════════════

    [
        'tag' => 'denominator_add_error',
        'concept_code' => 'FRAC_ADD_UNLIKE',
        'subject' => 'Mathematics',
        'grade_band' => '4-7',
        'description' => 'Student adds both numerators and denominators when adding unlike fractions.',
        'error_pattern' => 'Adds numerators AND denominators: 1/2 + 1/3 = 2/5',
        'corrective_action' => 'Show the pizza area model — NOT a formula re-explanation. Follow with L1 practice on what a denominator means.',
        'typical_wrong_answers' => ['2/5', '3/7', '4/9', '2/7', '5/9'],
        'error_regex' => 'add(ed|ing)?\s+(both|the)?\s*(top|bottom|numerator|denominator)',
        'prevalence_rate' => 0.43,
        'corrective_format' => 'visual',
        'priority_level' => 1,
        'correctives' => [
            [
                'title' => 'Why the bottom number is the SIZE of the piece',
                'body' => 'A pizza cut into 2 pieces gives bigger pieces than one cut into 3. '
                    . 'The bottom number does not count how many you have — it tells you how big each piece is. '
                    . 'When the pieces are different sizes you cannot add them until you cut both pizzas the same way. '
                    . 'Drag the cut-lines until both pizzas have the same size pieces, then count.',
                'format' => 'text_diagram',
                'h5p_type' => 'image_hotspot',
                'estimated_duration_minutes' => 3,
                'priority_level' => 1,
            ],
            [
                'title' => 'Half a hundred rupees plus half of ten rupees',
                'body' => 'Half of Rs.100 is Rs.50. Half of Rs.10 is Rs.5. Together that is Rs.55 — not "one whole". '
                    . 'Adding the halves only works when both halves come from the same amount. '
                    . 'Same rule with fractions: same whole first, then add.',
                'format' => 'story_audio',
                'estimated_duration_minutes' => 2,
                'priority_level' => 2,
            ],
        ],
    ],

    [
        'tag' => 'fraction_as_ratio',
        'concept_code' => 'FRAC_MEANING',
        'subject' => 'Mathematics',
        'grade_band' => '4-7',
        'description' => 'Student confuses the fraction 3/4 with the ratio statement "3 out of 4 people".',
        'error_pattern' => 'Applies a part-of-group ratio reading where a part-of-whole quantity is meant.',
        'corrective_action' => 'Show the number-line model and tie it to distance: 3/4 of 1 km = 750 m.',
        'typical_wrong_answers' => ['3 out of 4', '3:4', '3 of 4'],
        'prevalence_rate' => 0.27,
        'corrective_format' => 'visual',
        'priority_level' => 2,
        'correctives' => [
            [
                'title' => 'Three-quarters of a kilometre on the road to school',
                'body' => 'Put 0 km at your house and 1 km at school. Mark the road into 4 equal parts. '
                    . 'Three of those parts is 750 m — a DISTANCE, one single amount, not "3 people out of 4". '
                    . 'Drag the marker to 3/4 and read the metres.',
                'format' => 'text_diagram',
                'h5p_type' => 'drag_and_drop',
                'estimated_duration_minutes' => 3,
                'priority_level' => 1,
            ],
        ],
    ],

    [
        'tag' => 'larger_denom_larger_fraction',
        'concept_code' => 'FRAC_COMPARE',
        'subject' => 'Mathematics',
        'grade_band' => '3-6',
        'description' => 'Student says 1/8 > 1/4 because 8 > 4 — whole-number ordering applied to fraction size.',
        'error_pattern' => 'Reverses fraction magnitude when the numerators are equal.',
        'corrective_action' => 'Equal-length bars cut into different numbers of pieces; let the student physically compare one piece against one piece.',
        'typical_wrong_answers' => ['1/8', '1/10', '1/12', '1/8 is bigger', '1/8 > 1/4'],
        'prevalence_rate' => 0.38,
        'corrective_format' => 'visual',
        'priority_level' => 1,
        'correctives' => [
            [
                'title' => 'One roti shared between 4 people or 8 people',
                'body' => 'Same roti. Share it between 4 friends — each gets a decent piece. '
                    . 'Share the same roti between 8 friends — each piece is smaller. '
                    . 'More people sharing means a SMALLER share each. That is why 1/8 is less than 1/4. '
                    . 'Slide the number of friends and watch your piece shrink.',
                'format' => 'simulation',
                'h5p_type' => 'image_hotspot',
                'estimated_duration_minutes' => 4,
                'priority_level' => 1,
            ],
        ],
    ],

    [
        'tag' => 'mixed_number_error',
        'concept_code' => 'FRAC_MIXED',
        'subject' => 'Mathematics',
        'grade_band' => '5-8',
        'description' => 'Student converts a mixed number by a memorised algorithm they cannot reconstruct, so the method breaks down on subtraction and on borrowing.',
        'error_pattern' => 'Recalled procedure without meaning: works for 2¾ → 11/4 but fails on 3¼ − 1¾.',
        'corrective_action' => 'Step-by-step visual decomposition — 2 whole rotis plus 3 quarters, all re-cut into quarters — with the step names in the local language.',
        'typical_wrong_answers' => ['9/4', '5/4', '2 3/4 = 234'],
        'prevalence_rate' => 0.31,
        'corrective_format' => 'visual',
        'priority_level' => 2,
        'correctives' => [
            [
                'title' => 'Two whole rotis and three quarters, all cut the same way',
                'body' => 'Do not start with the formula. Start with the food. '
                    . 'Two whole rotis, each cut into 4 quarters, gives 8 quarters. Add the 3 quarters you already had: 11 quarters. '
                    . 'That is 11/4 — and now you can SEE why, so it still works when you have to take some away.',
                'format' => 'text_diagram',
                'h5p_type' => 'course_presentation',
                'estimated_duration_minutes' => 5,
                'priority_level' => 1,
            ],
        ],
    ],

    [
        'tag' => 'fraction_addition_different_wholes',
        'concept_code' => 'FRAC_ADD_UNLIKE',
        'subject' => 'Mathematics',
        'grade_band' => '4-7',
        'description' => 'Student adds fractions of different wholes as though they were the same whole — half a small pizza plus half a large pizza treated as one pizza.',
        'error_pattern' => 'Ignores that fractions may only be added when they refer to the same whole.',
        'corrective_action' => 'Money analogy: half of Rs.100 plus half of Rs.10 is Rs.55, not "one whole".',
        'typical_wrong_answers' => ['1', '1 whole', '2/2'],
        'prevalence_rate' => 0.22,
        'corrective_format' => 'story',
        'priority_level' => 3,
        'correctives' => [
            [
                'title' => 'Whose half is bigger?',
                'body' => 'Meena takes half of a small pizza. Raju takes half of a large pizza. '
                    . 'Both say "I ate a half". They did not eat the same amount. '
                    . 'Work through the story and decide at each step whether the two halves can be added.',
                'format' => 'story_audio',
                'h5p_type' => 'branching_scenario',
                'estimated_duration_minutes' => 4,
                'priority_level' => 1,
            ],
        ],
    ],

    // ══════════════════════════════════════════════════════════════════
    // MATHEMATICS — Algebra, grades 6-8 (spec §4.2)
    // ══════════════════════════════════════════════════════════════════

    [
        'tag' => 'variable_as_label',
        'concept_code' => 'ALG_VARIABLE',
        'subject' => 'Mathematics',
        'grade_band' => '6-8',
        'description' => "Student treats 'x' as an abbreviation for a word rather than as an unknown number.",
        'error_pattern' => 'Reads 3a as "3 apples" instead of "3 times some number a".',
        'corrective_action' => 'Concrete substitution activities; the "x is a mystery box" analogy.',
        'typical_wrong_answers' => ['3 apples', '3a means 3 apples', 'x = the word'],
        'prevalence_rate' => 0.35,
        'corrective_format' => 'simulation',
        'priority_level' => 1,
        'correctives' => [
            [
                'title' => 'The mystery box',
                'body' => 'x is a closed box with a number inside — never a word. '
                    . 'Put 5 in the box: 3x becomes 15. Put 2 in: 3x becomes 6. '
                    . 'The box changes what it holds; the rule 3x stays the same. Open the box yourself and watch the answer change.',
                'format' => 'simulation',
                'h5p_type' => 'drag_and_drop',
                'estimated_duration_minutes' => 4,
                'priority_level' => 1,
            ],
        ],
    ],

    [
        'tag' => 'distributive_law_error',
        'concept_code' => 'ALG_DISTRIBUTIVE',
        'subject' => 'Mathematics',
        'grade_band' => '6-8',
        'description' => 'Student multiplies only the first term inside a bracket: 3(x+2) = 3x+2.',
        'error_pattern' => 'Partial distribution across a sum.',
        'corrective_action' => 'Area model — a rectangle of width 3 and length (x+2), split into two sub-rectangles.',
        'typical_wrong_answers' => ['3x+2', '3x2', '3x + 2'],
        'prevalence_rate' => 0.41,
        'corrective_format' => 'visual',
        'priority_level' => 1,
        'correctives' => [
            [
                'title' => 'The field with two strips',
                'body' => 'A field 3 m wide and (x+2) m long. Split it where the x part ends. '
                    . 'One strip is 3 by x. The other is 3 by 2. The whole field is 3x + 6. '
                    . 'The 3 has to reach BOTH strips — the width does not stop halfway.',
                'format' => 'text_diagram',
                'h5p_type' => 'image_hotspot',
                'estimated_duration_minutes' => 3,
                'priority_level' => 1,
            ],
        ],
    ],

    [
        'tag' => 'equation_balance_error',
        'concept_code' => 'ALG_EQUATION',
        'subject' => 'Mathematics',
        'grade_band' => '6-8',
        'description' => 'Student operates on one side of an equation only: x+3=7 becomes x=7+3.',
        'error_pattern' => 'Breaks the equality by not applying the operation to both sides.',
        'corrective_action' => 'Balance-scale visual; an interactive balance the student can tip.',
        'typical_wrong_answers' => ['10', 'x=10', 'x = 7+3'],
        'prevalence_rate' => 0.39,
        'corrective_format' => 'simulation',
        'priority_level' => 1,
        'correctives' => [
            [
                'title' => 'The scale that must stay level',
                'body' => 'An equation is a balance that is already level. '
                    . 'Take 3 off the left pan and the scale tips — unless you take 3 off the right pan too. '
                    . 'Try removing from one side only and watch it tip. Whatever you do to one side, do to the other.',
                'format' => 'simulation',
                'h5p_type' => 'drag_and_drop',
                'estimated_duration_minutes' => 4,
                'priority_level' => 1,
            ],
        ],
    ],

    [
        'tag' => 'sign_change_error',
        'concept_code' => 'ALG_EQUATION',
        'subject' => 'Mathematics',
        'grade_band' => '6-8',
        'description' => 'Student moves a term across the equals sign without changing its sign: x+5=9 becomes x=9+5.',
        'error_pattern' => 'Recalled "shift it over" rule with the inverse operation dropped.',
        'corrective_action' => 'Stop teaching "shift it over". Teach "do the opposite to both sides" and let the inverse operation do the work.',
        'typical_wrong_answers' => ['14', 'x=14', 'x = 9+5'],
        'prevalence_rate' => 0.33,
        'corrective_format' => 'visual',
        'priority_level' => 2,
        'correctives' => [
            [
                'title' => 'There is no "shifting over"',
                'body' => 'Nothing ever jumps across the equals sign. '
                    . 'x + 5 = 9. To get x alone, SUBTRACT 5 — from both sides. Left: x. Right: 4. '
                    . 'The sign looks like it flipped only because you did the opposite operation. Do the operation, do not move the term.',
                'format' => 'text_diagram',
                'h5p_type' => 'course_presentation',
                'estimated_duration_minutes' => 3,
                'priority_level' => 1,
            ],
        ],
    ],

    // ══════════════════════════════════════════════════════════════════
    // READING COMPREHENSION — grades 3-5 (spec §4.2)
    // ══════════════════════════════════════════════════════════════════

    [
        'tag' => 'literal_only_reading',
        'concept_code' => 'READ_INFERENCE',
        'subject' => 'English',
        'grade_band' => '3-5',
        'description' => 'Student answers factual recall questions correctly but cannot infer meaning that is not stated outright.',
        'error_pattern' => 'Every answer is copied verbatim from the text; inference questions are left blank or answered with an unrelated quote.',
        'corrective_action' => 'Text-marking activity; "what did the character THINK or FEEL" questions with evidence underlining.',
        'typical_wrong_answers' => ['it does not say', 'not in the story', "it doesn't say"],
        'error_regex' => "(not|doesn'?t|does not)\\s+(say|mention|tell)",
        'prevalence_rate' => 0.29,
        'corrective_format' => 'visual',
        'priority_level' => 1,
        'correctives' => [
            [
                'title' => 'Reading the clues the writer left out',
                'body' => 'Writers leave clues instead of saying everything. '
                    . '"Ravi pushed his plate away and stared at the floor." The story never says he was upset — but you know he was. '
                    . 'Underline the clue words, then say what they tell you. Two clues make an answer.',
                'format' => 'text_diagram',
                'h5p_type' => 'mark_the_words',
                'estimated_duration_minutes' => 5,
                'priority_level' => 1,
            ],
        ],
    ],

    [
        'tag' => 'main_idea_missing',
        'concept_code' => 'READ_MAIN_IDEA',
        'subject' => 'English',
        'grade_band' => '3-5',
        'description' => 'Student summarises by listing details rather than identifying the central idea.',
        'error_pattern' => 'The "summary" is a retelling of every event in order, the same length as the original.',
        'corrective_action' => 'Pyramid summary scaffold: many details at the base, one main idea at the top.',
        'typical_wrong_answers' => ['retells the whole story', 'lists all events'],
        'prevalence_rate' => 0.34,
        'corrective_format' => 'visual',
        'priority_level' => 2,
        'correctives' => [
            [
                'title' => 'The pyramid: many details, one big idea',
                'body' => 'Write every detail along the bottom. Group the ones that belong together. '
                    . 'Each group gets one sentence. Now say all those sentences in ONE sentence — that is the main idea. '
                    . 'If your summary is as long as the story, you have listed, not summarised.',
                'format' => 'text_diagram',
                'h5p_type' => 'drag_and_drop',
                'estimated_duration_minutes' => 5,
                'priority_level' => 1,
            ],
        ],
    ],

    [
        'tag' => 'inference_from_memory',
        'concept_code' => 'READ_EVIDENCE',
        'subject' => 'English',
        'grade_band' => '3-5',
        'description' => 'Student answers inference questions from background knowledge rather than from evidence in the text.',
        'error_pattern' => 'A plausible answer that the passage does not support — often correct about the world, wrong about the story.',
        'corrective_action' => '"Point to where in the text it says that" practice on every answer, right or wrong.',
        'typical_wrong_answers' => ['because I know', 'everyone knows that'],
        'prevalence_rate' => 0.26,
        'corrective_format' => 'story',
        'priority_level' => 2,
        'correctives' => [
            [
                'title' => 'Show me the line',
                'body' => 'Your answer may be true in real life and still be wrong for this story. '
                    . 'Before you answer, put your finger on the line that proves it. '
                    . 'No line, no answer. Mark the proof line for each question here.',
                'format' => 'text_diagram',
                'h5p_type' => 'mark_the_words',
                'estimated_duration_minutes' => 4,
                'priority_level' => 1,
            ],
        ],
    ],

    // ══════════════════════════════════════════════════════════════════
    // SCIENCE — NGSS-aligned (spec §4.2)
    // ══════════════════════════════════════════════════════════════════

    [
        'tag' => 'sun_moves_misconception',
        'concept_code' => 'SCI_EARTH_ROTATION',
        'subject' => 'Science',
        'grade_band' => '3-6',
        'description' => 'Student believes the Sun moves across the sky rather than that the Earth rotates.',
        'error_pattern' => 'Explains day and night by the Sun travelling around a stationary Earth.',
        'corrective_action' => 'Torch-and-globe hands-on simulation (activity based), then an animation from outside the Earth.',
        'typical_wrong_answers' => ['the sun moves', 'sun goes around the earth', 'the sun rises and sets'],
        'error_regex' => 'sun\s+(moves|goes|travels|rises|sets)',
        'prevalence_rate' => 0.46,
        'corrective_format' => 'simulation',
        'priority_level' => 1,
        'correctives' => [
            [
                'title' => 'Torch and globe: who is really moving?',
                'body' => 'Hold a torch still. Spin the globe slowly. '
                    . 'Stand a small figure on your city and watch: the light arrives, crosses, and leaves — and the torch never moved. '
                    . 'From the figure\'s eyes the light seems to travel. From outside, only the globe turned. That is sunrise.',
                'format' => 'simulation',
                'h5p_type' => 'interactive_video',
                'estimated_duration_minutes' => 5,
                'priority_level' => 1,
            ],
        ],
    ],

    [
        'tag' => 'plants_eat_soil',
        'concept_code' => 'SCI_PHOTOSYNTHESIS',
        'subject' => 'Science',
        'grade_band' => '4-7',
        'description' => 'Student believes plants get their food from the soil rather than making it by photosynthesis.',
        'error_pattern' => 'Names soil, water or fertiliser as the plant\'s food.',
        'corrective_action' => 'Photosynthesis animation, then the inquiry task "what would happen without light?".',
        'typical_wrong_answers' => ['soil', 'from the soil', 'mud', 'fertiliser', 'manure'],
        'error_regex' => '(soil|mud|fertili[sz]er|manure)',
        'prevalence_rate' => 0.44,
        'corrective_format' => 'visual',
        'priority_level' => 1,
        'correctives' => [
            [
                'title' => 'The pot that never got lighter',
                'body' => 'Weigh a pot of soil. Grow a plant in it for a year. Weigh the soil again — almost nothing is gone. '
                    . 'So where did the plant come from? Air and light. '
                    . 'The leaf takes carbon dioxide from the air and uses sunlight to build sugar. Soil gives minerals, not food.',
                'format' => 'video',
                'h5p_type' => 'interactive_video',
                'estimated_duration_minutes' => 4,
                'priority_level' => 1,
            ],
        ],
    ],

    [
        'tag' => 'force_requires_motion',
        'concept_code' => 'SCI_FORCE',
        'subject' => 'Science',
        'grade_band' => '6-8',
        'description' => 'Student believes a stationary object can have no forces acting on it.',
        'error_pattern' => 'Says "no force" for a book resting on a table, or for a wall being pushed.',
        'corrective_action' => 'Push-a-wall activity; balanced-forces interactive with visible arrows.',
        'typical_wrong_answers' => ['no force', 'zero force', 'no forces acting', 'nothing is acting'],
        'error_regex' => '(no|zero|0)\s+forces?',
        'prevalence_rate' => 0.37,
        'corrective_format' => 'simulation',
        'priority_level' => 1,
        'correctives' => [
            [
                'title' => 'Push the wall as hard as you can',
                'body' => 'Push a wall. Nothing moves. Are you applying a force? Obviously yes — you can feel it. '
                    . 'The wall pushes back exactly as hard. Two forces, balanced, so nothing moves. '
                    . 'No motion does not mean no force. It means the forces cancel. Turn the arrows on and watch them balance.',
                'format' => 'simulation',
                'h5p_type' => 'drag_and_drop',
                'estimated_duration_minutes' => 4,
                'priority_level' => 1,
            ],
        ],
    ],
];
