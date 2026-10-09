<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * First Prayogshala labs: Standard 9 > Science > chapters 1 and 2.
 *
 *   Chapter 1  Exploration: Entering the World of Secondary Science
 *   Chapter 2  Cell: The Building Block of Life
 *
 * This is DATA, not behaviour. Each activity is a plain row whose `lab_config` describes the
 * eight-step lab (mission, predict, do, observe, explain, concept, apply, reflect) and names a
 * generic simulation engine plus its parameters. The frontend renders any lab_config with the
 * same engines; nothing in the code knows these chapters exist.
 *
 * GROUNDING. Every activity is built from the chapter's own source text (document_extractions)
 * and filed against its real topic_master / lms_concept rows, looked up by NAME inside the
 * chapter - never by hard-coded id:
 *   - estimation lab       : Example 1.3 (breaths per minute x litres per breath x 1,440 min,
 *                            cross-checked with a balloon route).
 *   - models lab           : the cricket-ball example (mass, speed, direction matter for a six;
 *                            the bat's brand and the grass length do not).
 *   - cell-size lab        : Activity 2.1 (field diameter in micrometres / cells along it).
 *   - osmosis lab          : Activity 2.2 (potato in plain water swells; in 20% salt or sugar
 *                            solution it shrinks) and the isotonic/hypotonic/hypertonic concept.
 * Where a simulation needs a number the textbook does not give (the solute level inside the
 * cell, how fast water moves) it is a labelled model setting, not a stated fact.
 *
 * REVIEW. These were authored by an AI assistant from the textbook, so they are seeded with
 * status 'review': teachers and admins see them (marked as pending review) and publish them;
 * learners see nothing until then.
 *
 * Idempotent: an activity is keyed on (institute, chapter, slug); a re-run updates it in place.
 * Refuses to run, rather than half-seed, if a chapter, topic or concept is not found.
 *
 *   php artisan db:seed --class=PrayogshalaStandard9ScienceSeeder
 */
class PrayogshalaStandard9ScienceSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $made = 0;
        $updated = 0;

        DB::transaction(function () use ($now, &$made, &$updated) {
            foreach ($this->activities() as $a) {
                $chapter = DB::table('chapter_master as ch')
                    ->join('standard as st', 'st.id', '=', 'ch.standard_id')
                    ->join('subject as su', 'su.id', '=', 'ch.subject_id')
                    ->where('st.name', '9')
                    ->where('su.subject_name', 'Science')
                    ->where('ch.chapter_name', $a['chapter'])
                    ->select('ch.*')
                    ->first();
                if (! $chapter) {
                    throw new \RuntimeException("Standard 9 Science chapter '{$a['chapter']}' not found; nothing seeded.");
                }

                $topicId = DB::table('topic_master')->where('chapter_id', $chapter->id)->where('name', $a['topic'])->value('id');
                $conceptId = $topicId
                    ? DB::table('lms_concept')->where('chapter_id', $chapter->id)->where('topic_id', $topicId)->where('name', $a['concept'])->value('id')
                    : null;
                if (! $topicId || ! $conceptId) {
                    throw new \RuntimeException("topic '{$a['topic']}' / concept '{$a['concept']}' not found in '{$a['chapter']}'; nothing seeded.");
                }

                $row = [
                    'syear'                => $chapter->syear,
                    'grade_id'             => $chapter->grade_id,
                    'standard_id'          => $chapter->standard_id,
                    'subject_id'           => $chapter->subject_id,
                    'topic_id'             => $topicId,
                    'concept_id'           => $conceptId,
                    'title'                => $a['title'],
                    'activity_type'        => $a['type'],
                    'description'          => $a['description'],
                    'objective'            => $a['objective'],
                    'materials_required'   => json_encode($a['materials'], JSON_UNESCAPED_UNICODE),
                    'procedure_steps'      => json_encode($a['procedure'], JSON_UNESCAPED_UNICODE),
                    'observation'          => $a['observation'],
                    'result'               => $a['result'],
                    'safety_instructions'  => $a['safety'],
                    'teacher_instructions' => $a['teacher'],
                    'student_instructions' => $a['student'],
                    'estimated_minutes'    => $a['minutes'],
                    'lab_config'           => json_encode($a['lab'], JSON_UNESCAPED_UNICODE),
                    'status'               => 'review',
                    'show_hide'            => 1,
                    'sort_order'           => $a['order'],
                    'updated_at'           => $now,
                ];

                $existing = DB::table('lms_prayogshala_activity')
                    ->where('sub_institute_id', $chapter->sub_institute_id)
                    ->where('chapter_id', $chapter->id)
                    ->where('slug', $a['slug'])
                    ->first();

                if ($existing) {
                    // Keep a status a teacher has already moved on from 'review'.
                    $row['status'] = $existing->status;
                    DB::table('lms_prayogshala_activity')->where('id', $existing->id)->update($row);
                    $updated++;
                } else {
                    DB::table('lms_prayogshala_activity')->insert($row + [
                        'sub_institute_id' => $chapter->sub_institute_id,
                        'chapter_id'       => $chapter->id,
                        'slug'             => $a['slug'],
                        'created_at'       => $now,
                    ]);
                    $made++;
                }
            }
        });

        $this->command?->info("Prayogshala: {$made} created, {$updated} updated.");
    }

    /** @return list<array<string,mixed>> */
    private function activities(): array
    {
        $ch1 = 'Exploration: Entering the World of Secondary Science';
        $ch2 = 'Cell: The Building Block of Life';

        return [
            // ------------------------------------------------------------ chapter 1
            [
                'chapter' => $ch1, 'order' => 1, 'slug' => 'same-ball-different-questions',
                'topic' => 'Scientific Models and Simplification', 'concept' => 'Question decides relevant details',
                'type' => 'activity', 'minutes' => 25,
                'title' => 'Same Ball, Different Questions: What Does Your Model Keep?',
                'description' => 'A cricket ball hit for a six can be described in many ways. Which details a scientific model keeps depends on the question being asked. Students choose the details themselves and see what the question needed.',
                'objective' => 'Show that a model keeps only the details that matter for the question, and that the same object needs a different model when the question changes.',
                'materials' => ['Virtual lab: six details about a cricket ball hit for a six', 'Notebook'],
                'procedure' => [
                    'Read the question and predict which detail can be left out of the model.',
                    'Choose a question and tick the details your model keeps.',
                    'Compare your model with what the question needs.',
                    'Switch to the other question and rebuild the model.',
                ],
                'observation' => 'Note which details you kept for each question, and which you had to add or remove when the question changed.',
                'result' => 'For "how far will the ball travel for a six?" the mass, speed and direction of the ball matter; the brand of the bat and the length of the grass do not. For "which ball is easiest to tell apart?" the colour matters instead.',
                'safety' => 'No equipment is handled in the virtual lab. If you try a physical version with a real ball, do it in an open area away from other people.',
                'teacher' => 'Let students disagree before they check. Ask them to defend one detail they ignored. Link back to the textbook: ignoring a detail is a deliberate choice, not an error.',
                'student' => 'Write down why you keep or ignore each detail, not just whether you do.',
                'lab' => [
                    'version' => 1,
                    'simulation' => ['type' => 'relevance', 'params' => [
                        'subject' => 'A cricket ball hit for a six',
                        'details' => [
                            ['id' => 'ball_mass', 'label' => 'Mass of the ball'],
                            ['id' => 'ball_speed', 'label' => 'Speed of the ball'],
                            ['id' => 'hit_direction', 'label' => 'Direction of the hit'],
                            ['id' => 'bat_brand', 'label' => 'Brand of the bat'],
                            ['id' => 'grass_length', 'label' => 'Length of the grass'],
                            ['id' => 'ball_colour', 'label' => 'Colour of the ball'],
                        ],
                        'questions' => [
                            ['id' => 'distance', 'text' => 'How far will the ball travel for a six?', 'relevant' => ['ball_mass', 'ball_speed', 'hit_direction']],
                            ['id' => 'spot', 'text' => 'Which ball is easiest to tell apart from another ball in the match?', 'relevant' => ['ball_colour']],
                        ],
                        'default_question' => 'distance',
                        'observation_template' => 'Question: {question}. Your model keeps {kept} of 6 details. It is missing {missed} detail(s) the question needs and keeps {extra} it does not.',
                    ]],
                    'steps' => [
                        'mission' => [
                            'scenario' => 'A commentator says a batter has hit a six. How far did the ball travel? To answer, you have to decide which facts about the situation to keep and which to ignore. That is what a scientific model does.',
                            'task' => 'Build a model that is simple enough to work with but keeps what the question needs.',
                            'tags' => ['Models and simplification', 'Question decides the details'],
                        ],
                        'predict' => [
                            'question' => 'For the question "How far will the ball travel for a six?", which one of these can safely be left out of your model?',
                            'scenario' => ['question' => 'distance', 'kept' => []],
                            'options' => [
                                ['id' => 'a', 'label' => 'The speed of the ball', 'when' => 'rel_ball_speed==0'],
                                ['id' => 'b', 'label' => 'The brand of the bat', 'when' => 'rel_bat_brand==0'],
                                ['id' => 'c', 'label' => 'The direction of the hit', 'when' => 'rel_hit_direction==0'],
                                ['id' => 'd', 'label' => 'The mass of the ball', 'when' => 'rel_ball_mass==0'],
                            ],
                        ],
                        'do' => [
                            'instructions' => ['Pick a question.', 'Tick the details your model keeps. Untick the ones you would ignore.', 'Then switch to the other question and rebuild the model.'],
                            'materials' => ['Virtual lab: six details about a cricket ball hit for a six'],
                            'safety' => 'Nothing physical is handled. A physical version with a real ball should be done in an open area away from other people.',
                        ],
                        'observe' => ['prompt' => 'Look at the model sheet and the counts: what is missing, and what is extra?'],
                        'explain' => [
                            'text' => 'A model keeps only what the question needs.',
                            'cases' => [
                                ['when' => 'complete==1', 'text' => 'Your model keeps every detail the question needs and nothing else. That is a good model for this question: it is as simple as it can be and still answer it.'],
                                ['when' => 'missed>0 && extra>0', 'text' => 'Your model is missing something the question needs and also carries details it does not. Check each detail against the question.'],
                                ['when' => 'missed>0', 'text' => 'Your model leaves out a detail the question needs, so it cannot answer the question reliably.'],
                                ['when' => 'extra>0', 'text' => 'Your model answers the question but carries details that do not change the answer. They make it more complicated for no gain.'],
                            ],
                        ],
                        'concept' => [
                            'text' => 'A scientific model is a deliberately simplified representation of a real system.',
                            'points' => [
                                'Ignoring a detail is a deliberate choice, not an error.',
                                'Which details belong in the model is decided by the question being asked. Change the question and the relevant details change.',
                                'A simple model can be made more accurate by adding detail back, but accuracy is bought with complexity.',
                            ],
                        ],
                        'apply' => [
                            'question' => 'You now ask: "Which ball is easiest to tell apart from another ball in the match?" Which detail becomes important that was not before?',
                            'options' => [
                                ['id' => 'a', 'label' => 'The colour of the ball', 'correct' => true, 'feedback' => 'Yes. The new question is about telling balls apart, so colour now matters.'],
                                ['id' => 'b', 'label' => 'The brand of the bat', 'correct' => false, 'feedback' => 'The bat does not help you tell two balls apart.'],
                                ['id' => 'c', 'label' => 'The length of the grass', 'correct' => false, 'feedback' => 'The grass is the same for both balls, so it cannot separate them.'],
                                ['id' => 'd', 'label' => 'None - the same details matter for every question', 'correct' => false, 'feedback' => 'The details that matter depend on the question, so they change when the question changes.'],
                            ],
                        ],
                        'reflect' => ['prompts' => ['Which detail did you ignore, and why was it safe to ignore?', 'How did your model change when the question changed?']],
                    ],
                    'outcomes' => [
                        'Explains that a model is a simplified representation that keeps only the details the question needs.',
                        'Chooses relevant details for a given question and justifies leaving others out.',
                    ],
                    'teacher_script' => [
                        'Before the Do step, ask for a show of hands: which detail would you drop first?',
                        'In Observe, ask groups to read out what their model was missing or carrying extra.',
                        'In Concept, stress that dropping the bat brand is a choice made because of the question, not a mistake.',
                    ],
                    'particle_view' => false,
                ],
            ],
            [
                'chapter' => $ch1, 'order' => 2, 'slug' => 'air-you-breathe-in-a-day',
                'topic' => 'Estimation and Approximate Reasoning', 'concept' => 'Estimate using rates and assumed values',
                'type' => 'experiment', 'minutes' => 40,
                'title' => 'How Much Air Do You Breathe in a Day? Estimate, Then Cross-Check',
                'description' => 'Students estimate the litres of air they breathe in a day from a breathing rate and an assumed volume per breath, as in the textbook\'s Example 1.3, then check the figure by a second, independent route using balloons.',
                'objective' => 'Build a rough estimate from measured and assumed values, and strengthen it with an independent cross-check, so you can judge whether an answer is reasonable without needing an exact value.',
                'materials' => ['Virtual lab: sliders for breathing rate, volume per breath and the balloon route', 'Stopwatch (to count your own breaths)', 'Calculator'],
                'procedure' => [
                    'Count your breaths for one minute while sitting quietly. Resting adults take about 12-15 breaths a minute.',
                    'Assume a volume for one breath (about 0.5 litre) and say that it is an assumption.',
                    'Multiply breaths per minute x litres per breath x 1,440 minutes in a day.',
                    'Cross-check with the balloon route: balloons filled per minute x litres per balloon x 1,440.',
                    'Compare the two estimates.',
                ],
                'observation' => 'Record your breaths per minute, the assumed volume per breath, both estimates in litres per day, and how close they are.',
                'result' => 'With 12-15 breaths a minute and about 0.5 litre per breath the estimate is roughly 10,000 litres a day. The balloon cross-check (about 3 balloons a minute x 2 litres x 1,440 minutes = 8,640 litres) is reasonably close.',
                'safety' => 'Counting breaths is safe. If you try the physical balloon version, breathe out gently and normally; never blow nonstop or repeat deep breaths, which can cause dizziness. Anyone with a breathing condition should only count.',
                'teacher' => 'Collect the class estimates and look at the spread before discussing any one. Ask which assumption each student trusts least. Point out that blowing balloons nonstop is a thought experiment: it would tire a person quickly.',
                'student' => 'Label every number as measured or assumed. Work it out before using the calculator.',
                'lab' => [
                    'version' => 1,
                    'simulation' => ['type' => 'calculator', 'params' => [
                        'variables' => [
                            ['id' => 'breaths_per_min', 'label' => 'Breaths per minute', 'unit' => 'breaths/min', 'kind' => 'measured', 'min' => 6, 'max' => 30, 'step' => 1, 'default' => 12],
                            ['id' => 'volume_per_breath_l', 'label' => 'Volume of one breath', 'unit' => 'litre', 'kind' => 'assumed', 'min' => 0.1, 'max' => 1.5, 'step' => 0.05, 'default' => 0.5],
                            ['id' => 'balloons_per_min', 'label' => 'Balloons filled per minute (cross-check)', 'unit' => 'balloons/min', 'kind' => 'assumed', 'min' => 1, 'max' => 6, 'step' => 1, 'default' => 3],
                            ['id' => 'balloon_litres', 'label' => 'Volume of one balloon (cross-check)', 'unit' => 'litre', 'kind' => 'assumed', 'min' => 0.5, 'max' => 4, 'step' => 0.5, 'default' => 2],
                        ],
                        'outputs' => [
                            ['id' => 'breaths_day', 'label' => 'Breaths in a day', 'unit' => 'breaths', 'formula' => 'breaths_per_min*60*24', 'decimals' => 0],
                            ['id' => 'litres_day', 'label' => 'Route 1: air breathed in a day', 'unit' => 'litres', 'formula' => 'breaths_day*volume_per_breath_l', 'decimals' => 0, 'primary' => true],
                            ['id' => 'litres_check', 'label' => 'Route 2: balloon cross-check', 'unit' => 'litres', 'formula' => 'balloons_per_min*balloon_litres*1440', 'decimals' => 0],
                            ['id' => 'agreement', 'label' => 'Route 1 / Route 2', 'unit' => 'x', 'formula' => 'litres_day/litres_check', 'decimals' => 2],
                        ],
                        'warnings' => [
                            ['when' => 'agreement>2', 'text' => 'Route 1 is more than twice Route 2. One of your assumptions is probably too large.'],
                            ['when' => 'agreement<0.5', 'text' => 'Route 1 is less than half of Route 2. One of your assumptions is probably too small.'],
                        ],
                        'observation_template' => 'Route 1: {breaths_per_min} breaths/min x {volume_per_breath_l} litre x 1,440 min = about {litres_day} litres a day. Route 2: {balloons_per_min} balloons/min x {balloon_litres} litres x 1,440 min = about {litres_check} litres. Route 1 is {agreement} times Route 2.',
                    ]],
                    'steps' => [
                        'mission' => [
                            'scenario' => 'You cannot weigh the air you breathe, and you cannot count every breath in a day. But you can still say roughly how much it is.',
                            'task' => 'Estimate the litres of air you breathe in one day, then check your estimate by a second, independent route.',
                            'tags' => ['Estimation', 'Rates and assumed values', 'Cross-checking'],
                        ],
                        'predict' => [
                            'question' => 'Before you calculate: roughly how many litres of air does a person breathe in a day?',
                            'scenario' => [],
                            'options' => [
                                ['id' => 'a', 'label' => 'About 100 litres', 'when' => 'litres_day>=50 && litres_day<500'],
                                ['id' => 'b', 'label' => 'About 1,000 litres', 'when' => 'litres_day>=500 && litres_day<5000'],
                                ['id' => 'c', 'label' => 'About 10,000 litres', 'when' => 'litres_day>=5000 && litres_day<50000'],
                                ['id' => 'd', 'label' => 'About 1,000,000 litres', 'when' => 'litres_day>=500000'],
                            ],
                        ],
                        'do' => [
                            'instructions' => ['Set your breaths per minute (a resting adult takes about 12-15).', 'Set the volume of one breath. This is an assumption: say why you chose it.', 'Set the balloon route: how many balloons a minute, and how big.', 'Watch both estimates change.'],
                            'materials' => ['Virtual lab sliders', 'Stopwatch to count your own breaths'],
                            'safety' => 'Counting breaths is safe. A physical balloon version should use gentle, normal breaths only.',
                        ],
                        'observe' => ['prompt' => 'Read both routes. How close are they?'],
                        'explain' => [
                            'text' => 'An estimate is stronger when a second, independent line of reasoning reaches a similar figure.',
                            'cases' => [
                                ['when' => 'agreement>=0.5 && agreement<=2', 'text' => 'The two routes land within a factor of two of each other. Because they use different reasoning, it is unlikely that both hide a large error. That is what makes the estimate reasonable.'],
                                ['when' => 'agreement>2 || agreement<0.5', 'text' => 'The two routes disagree by more than a factor of two. When independent routes disagree, one assumption is wrong. Find which one before trusting either figure.'],
                            ],
                        ],
                        'concept' => [
                            'text' => 'Estimation begins with understanding, not with arithmetic.',
                            'points' => [
                                'Grasp the situation, identify the quantities that matter, then produce a rough figure.',
                                'A rough estimate is usually enough to tell whether a result is reasonable, for example to catch an answer wrong by a factor of a thousand.',
                                'A rough estimate can be built from simple rates and assumed values and scaled up.',
                                'An estimate is stronger when a second, independent route reaches a similar figure.',
                            ],
                        ],
                        'apply' => [
                            'question' => 'A classmate reports that a person breathes 10 million litres of air a day. What should you do first?',
                            'options' => [
                                ['id' => 'a', 'label' => 'Check for a unit or factor error, because the figure is about a thousand times your estimate', 'correct' => true, 'feedback' => 'Yes. A rough estimate exists exactly to catch an answer that is wrong by a factor like a thousand.'],
                                ['id' => 'b', 'label' => 'Accept it because it came from a calculator', 'correct' => false, 'feedback' => 'A calculator repeats whatever it is given. Compare it with your rough estimate.'],
                                ['id' => 'c', 'label' => 'Round it to 10,000 litres', 'correct' => false, 'feedback' => 'Rounding does not fix a result that is wrong by a factor of a thousand.'],
                                ['id' => 'd', 'label' => 'Conclude that your own estimate is wrong', 'correct' => false, 'feedback' => 'Your estimate has a cross-check. The new figure needs checking first.'],
                            ],
                        ],
                        'reflect' => ['prompts' => ['Which of your numbers was an assumption rather than a measurement?', 'How did the second route change how much you trust your estimate?']],
                    ],
                    'outcomes' => [
                        'Builds a rough estimate from rates and assumed values.',
                        'Judges whether an answer is reasonable and cross-checks it by an independent route.',
                    ],
                    'teacher_script' => [
                        'Collect the class predictions before anyone opens the sliders.',
                        'In Do, ask students to defend their volume-per-breath value.',
                        'In Apply, ask what they would check first if their own answer were a thousand times off.',
                    ],
                    'particle_view' => false,
                ],
            ],

            // ------------------------------------------------------------ chapter 2
            [
                'chapter' => $ch2, 'order' => 1, 'slug' => 'estimate-size-of-an-onion-cell',
                'topic' => 'How to Study Cells', 'concept' => 'Estimating cell size',
                'type' => 'experiment', 'minutes' => 35,
                'title' => 'Estimate the Size of a Cell Under the Microscope',
                'description' => 'Following Activity 2.1, students estimate the real size of an onion peel cell from the diameter of the microscope\'s field of view and the number of cells along it, then work out the total magnification.',
                'objective' => 'Estimate the size of a cell indirectly, and connect it to the total magnification of a compound microscope.',
                'materials' => ['Compound microscope', 'Transparent ruler with millimetre markings', 'Onion peel slide', 'Virtual lab: field-of-view and magnification sliders'],
                'procedure' => [
                    'Place the ruler on the stage, focus, and measure the diameter of the circular field of view in millimetres.',
                    'Convert the diameter to micrometres (1 mm = 1000 micrometres).',
                    'Replace the ruler with the onion peel slide and focus.',
                    'Count the cells along the diameter of the field in one straight line.',
                    'Size of one cell = diameter of the field in micrometres / number of cells along the diameter.',
                    'Total magnification = eyepiece magnification x objective magnification.',
                ],
                'observation' => 'Record the field diameter, the number of cells along it, the estimated cell size and the total magnification.',
                'result' => 'For a field 5 mm across (5,000 micrometres) with 25 cells along the diameter, one onion cell is about 5,000 / 25 = 200 micrometres. A 10x eyepiece with a 10x objective gives 100x total magnification.',
                'safety' => 'Handle glass slides and cover slips carefully. Carry the microscope with both hands and do not point the mirror at the sun. Use blunt tools; an adult prepares any onion peel with a knife.',
                'teacher' => 'Stress that this is an indirect measurement: the result depends on counting carefully. Ask what happens to the estimate if they miscount by five cells.',
                'student' => 'Count along one straight line through the middle of the field, and recount before you divide.',
                'lab' => [
                    'version' => 1,
                    'simulation' => ['type' => 'calculator', 'params' => [
                        'variables' => [
                            ['id' => 'field_diameter_mm', 'label' => 'Diameter of the field of view', 'unit' => 'mm', 'kind' => 'measured', 'min' => 1, 'max' => 8, 'step' => 0.5, 'default' => 5],
                            ['id' => 'cells_along_diameter', 'label' => 'Cells along the diameter', 'unit' => 'cells', 'kind' => 'measured', 'min' => 4, 'max' => 80, 'step' => 1, 'default' => 25],
                            ['id' => 'eyepiece_x', 'label' => 'Eyepiece magnification', 'unit' => 'x', 'kind' => 'measured', 'min' => 5, 'max' => 20, 'step' => 5, 'default' => 10],
                            ['id' => 'objective_x', 'label' => 'Objective magnification', 'unit' => 'x', 'kind' => 'measured', 'min' => 4, 'max' => 40, 'step' => 2, 'default' => 10],
                        ],
                        'outputs' => [
                            ['id' => 'field_um', 'label' => 'Field diameter', 'unit' => 'micrometres', 'formula' => 'field_diameter_mm*1000', 'decimals' => 0],
                            ['id' => 'cell_um', 'label' => 'Estimated size of one cell', 'unit' => 'micrometres', 'formula' => 'field_um/cells_along_diameter', 'decimals' => 0, 'primary' => true],
                            ['id' => 'total_mag', 'label' => 'Total magnification', 'unit' => 'x', 'formula' => 'eyepiece_x*objective_x', 'decimals' => 0],
                            ['id' => 'image_mm', 'label' => 'Size of one cell in the image', 'unit' => 'mm', 'formula' => 'cell_um*total_mag/1000', 'decimals' => 1],
                        ],
                        'warnings' => [
                            ['when' => 'cells_along_diameter<8', 'text' => 'Only a few cells fit across the field, so a miscount of one or two changes the estimate a lot. Count carefully.'],
                        ],
                        'observation_template' => 'Field diameter {field_diameter_mm} mm = {field_um} micrometres. With {cells_along_diameter} cells along it, one cell is about {field_um} / {cells_along_diameter} = {cell_um} micrometres. Total magnification {eyepiece_x}x x {objective_x}x = {total_mag}x, so one cell appears about {image_mm} mm wide.',
                    ]],
                    'steps' => [
                        'mission' => [
                            'scenario' => 'Most cells are far smaller than 0.1 mm, below what the unaided eye can resolve, so you cannot measure one with a ruler. But the microscope gives you two things you can measure: how wide the field of view is, and how many cells fit across it.',
                            'task' => 'Estimate the real size of one onion peel cell, and find out how much the microscope magnifies it.',
                            'tags' => ['How to study cells', 'Estimating cell size', 'Magnification'],
                        ],
                        'predict' => [
                            'question' => 'The field of view is 5 mm across and 25 cells fit along its diameter. About how big is one cell?',
                            'scenario' => [],
                            'options' => [
                                ['id' => 'a', 'label' => 'About 5 micrometres', 'when' => 'cell_um<20'],
                                ['id' => 'b', 'label' => 'About 20 micrometres', 'when' => 'cell_um>=20 && cell_um<100'],
                                ['id' => 'c', 'label' => 'About 200 micrometres', 'when' => 'cell_um>=100 && cell_um<1000'],
                                ['id' => 'd', 'label' => 'About 2 millimetres', 'when' => 'cell_um>=1000'],
                            ],
                        ],
                        'do' => [
                            'instructions' => ['Set the diameter of the field of view (measured with the ruler on the stage).', 'Set how many cells you count along the diameter.', 'Choose the eyepiece and objective lens.', 'Watch the cell-size estimate and the magnification update.'],
                            'materials' => ['Compound microscope', 'Ruler with millimetre markings', 'Onion peel slide', 'Virtual lab sliders'],
                            'safety' => 'Handle glass slides and cover slips carefully; carry the microscope with both hands; never point the mirror at the sun.',
                        ],
                        'observe' => ['prompt' => 'Read the working. Which number did you divide by which?'],
                        'explain' => [
                            'text' => 'The cell size is the field diameter shared out among the cells that fit across it.',
                            'cases' => [
                                ['when' => 'cells_along_diameter<8', 'text' => 'The estimate is the field diameter divided by the number of cells. With so few cells across, one miscount changes the answer a lot, so this is a coarse estimate.'],
                                ['when' => 'cell_um>0', 'text' => 'The estimate is the field diameter in micrometres divided by the number of cells along it. It is indirect: you never measured one cell, so the result is only as good as your count of cells.'],
                            ],
                        ],
                        'concept' => [
                            'text' => 'Cells are too small to see with the unaided eye, so they are studied with microscopes.',
                            'points' => [
                                'The eye cannot separate two points closer than about 0.1 mm; most cells are smaller than that.',
                                'Cell size is estimated by dividing the diameter of the field of view by the number of cells along it.',
                                'Total magnification of a compound microscope = eyepiece magnification x objective magnification.',
                            ],
                        ],
                        'apply' => [
                            'question' => 'On another slide with the same 5 mm field you count 50 cells along the diameter. How do these cells compare with the onion cells (25 along the diameter)?',
                            'options' => [
                                ['id' => 'a', 'label' => 'About half as wide (about 100 micrometres)', 'correct' => true, 'feedback' => 'Yes. Twice as many cells fit in the same width, so each is about half as wide: 5,000 / 50 = 100 micrometres.'],
                                ['id' => 'b', 'label' => 'About twice as wide', 'correct' => false, 'feedback' => 'More cells across the same width means each cell is narrower, not wider.'],
                                ['id' => 'c', 'label' => 'The same size, because the field is the same', 'correct' => false, 'feedback' => 'The field is the same, but more cells fit inside it, so each cell is smaller.'],
                                ['id' => 'd', 'label' => 'Cannot say without changing the magnification', 'correct' => false, 'feedback' => 'The estimate only needs the field diameter and the cell count.'],
                            ],
                        ],
                        'reflect' => ['prompts' => ['Why is this called an indirect measurement?', 'What would make your estimate less reliable?']],
                    ],
                    'outcomes' => [
                        'Estimates cell size from the field of view and the number of cells across it.',
                        'Calculates the total magnification of a compound microscope.',
                    ],
                    'teacher_script' => [
                        'Do Activity 2.1 on the real microscope first if one is available; use the lab to compare answers and test what-ifs.',
                        'Ask what a miscount of five cells does to the estimate, and compare with a field of 25 vs 8 cells.',
                    ],
                    'particle_view' => false,
                ],
            ],
            [
                'chapter' => $ch2, 'order' => 2, 'slug' => 'potato-in-water-and-salt-solution',
                'topic' => 'Cell Membrane and Cell Wall', 'concept' => 'Isotonic, hypotonic and hypertonic solutions',
                'type' => 'experiment', 'minutes' => 40,
                'title' => 'Potato in Water and in Salt Solution: Osmosis at Work',
                'description' => 'Following Activity 2.2, students place a potato piece, a plant cell or an animal cell in plain water, a 20% salt solution, or a solution matching the cell, and see which way water moves across the membrane.',
                'objective' => 'Predict and explain which way water moves across a selectively permeable membrane when a cell is placed in hypotonic, isotonic and hypertonic solutions, and why a plant cell keeps its shape.',
                'materials' => ['Potato, kitchen knife (adult use), weighing balance', 'Two beakers: plain water and 20% salt or sugar solution', 'Virtual lab: specimen and solution selectors with a time slider'],
                'procedure' => [
                    'Cut a potato into two roughly equal pieces and weigh each.',
                    'Put one piece in Beaker A (plain water) and the other in Beaker B (20% salt or sugar solution).',
                    'Leave them for about an hour, or until the size visibly changes.',
                    'Weigh each piece again and work out the difference from its starting weight.',
                ],
                'observation' => 'Record the starting and final weights of each piece, and describe how each piece looks and feels.',
                'result' => 'The piece in plain water swells; the piece in 20% salt or sugar solution shrinks.',
                'safety' => 'An adult uses the knife. Do not taste the solutions or the potato pieces. Wipe up spills so the floor is not slippery, and wash your hands afterwards.',
                'teacher' => 'Use the lab to extend what the beakers show: ask what would happen in a solution matching the cell, and why a plant cell, unlike an animal cell, keeps its outline in a concentrated solution. Note that the numbers in the simulation are illustrative model settings.',
                'student' => 'Say what you expect before you run each case, and compare afterwards.',
                'lab' => [
                    'version' => 1,
                    'simulation' => ['type' => 'osmosis', 'params' => [
                        'specimens' => [
                            ['id' => 'potato', 'label' => 'Potato piece (Activity 2.2)', 'has_wall' => true, 'measure' => 'mass'],
                            ['id' => 'plant_cell', 'label' => 'Plant cell', 'has_wall' => true, 'measure' => 'volume'],
                            ['id' => 'animal_cell', 'label' => 'Animal cell', 'has_wall' => false, 'measure' => 'volume'],
                        ],
                        'solutions' => [
                            ['id' => 'water', 'label' => 'Plain water', 'solute_pct' => 0],
                            ['id' => 'salt20', 'label' => '20% salt solution', 'solute_pct' => 20],
                            ['id' => 'matched', 'label' => 'Solution matching the cell (isotonic)', 'solute_pct' => 5],
                        ],
                        // Illustrative model settings, NOT textbook facts: the solute level inside the
                        // cell, and how large a change the model shows at full effect.
                        'cell_solute_pct' => 5,
                        'max_minutes' => 60,
                        'full_change_pct' => 12,
                        'default_specimen' => 'potato',
                        'default_solution' => 'water',
                        'model_note' => 'The solute level inside the cell (5%) and the size of the change are illustrative model settings, not measured values.',
                        'observation_template' => '{specimen} in {solution} for {minutes} min: {outcome_text}',
                    ]],
                    'steps' => [
                        'mission' => [
                            'scenario' => 'A potato is made of cells. If you put a piece in plain water and another in a strong salt solution, do they stay the same? Water cannot be seen moving, but a weighing balance can show where it went.',
                            'task' => 'Find out which way water moves when cells are placed in different solutions, and why.',
                            'tags' => ['Osmosis', 'Selectively permeable membrane', 'Isotonic, hypotonic, hypertonic'],
                        ],
                        'predict' => [
                            'question' => 'A potato piece is left for an hour in a 20% salt solution. What happens to it?',
                            'scenario' => ['specimen' => 'potato', 'solution' => 'salt20', 'minutes' => 60],
                            'options' => [
                                ['id' => 'a', 'label' => 'It swells', 'when' => 'swell==1'],
                                ['id' => 'b', 'label' => 'It shrinks', 'when' => 'shrink==1'],
                                ['id' => 'c', 'label' => 'It stays the same', 'when' => 'nochange==1'],
                            ],
                        ],
                        'do' => [
                            'instructions' => ['Choose a specimen: a potato piece, a plant cell or an animal cell.', 'Choose a solution: plain water, 20% salt solution, or one matching the cell.', 'Move the time slider and watch the specimen.', 'Switch on Particle view to see water moving across the membrane.'],
                            'materials' => ['Virtual lab selectors', 'Potato, knife (adult use), balance, two beakers for the real activity'],
                            'safety' => 'In the real activity an adult uses the knife; do not taste the solutions; wipe up spills.',
                        ],
                        'observe' => ['prompt' => 'Describe what happened to the specimen, and which way the water moved.'],
                        'explain' => [
                            'text' => 'Water moves across the selectively permeable membrane from the more dilute solution to the more concentrated one, until the concentrations are equal.',
                            'cases' => [
                                ['when' => 'swell==1', 'text' => 'The solution outside is more dilute than the cell contents (hypotonic), so water moves into the cell by osmosis and it swells.'],
                                ['when' => 'shrink==1', 'text' => 'The solution outside is more concentrated than the cell contents (hypertonic), so water moves out of the cell by osmosis and it shrinks.'],
                                ['when' => 'nochange==1', 'text' => 'The solution outside matches the cell contents (isotonic), so there is no net movement of water and the cell stays the same size.'],
                            ],
                        ],
                        'concept' => [
                            'text' => 'Osmosis is the diffusion of water across a selectively permeable membrane.',
                            'points' => [
                                'A solution is isotonic when its solute concentration equals that inside the cell (no net water movement), hypotonic when it is lower (water enters, the cell swells), and hypertonic when it is higher (water leaves, the cell shrinks).',
                                'The cell membrane is selectively permeable: it lets some substances through and holds others back.',
                                'A plant cell has a rigid cell wall outside the membrane, so it keeps its outline even when it loses water. An animal cell has no wall and changes shape readily.',
                            ],
                        ],
                        'apply' => [
                            'question' => 'Why does a plant cell in a concentrated sugar solution keep its outline, while an animal cell in the same solution shrivels?',
                            'options' => [
                                ['id' => 'a', 'label' => 'The plant cell has a rigid cell wall that holds its outline; the animal cell has none', 'correct' => true, 'feedback' => 'Yes. The contents of the plant cell pull away from the wall, but the wall holds the original outline.'],
                                ['id' => 'b', 'label' => 'Water does not leave the plant cell', 'correct' => false, 'feedback' => 'Water does leave the plant cell. The difference is the wall, which holds the shape.'],
                                ['id' => 'c', 'label' => 'Sugar enters the plant cell but not the animal cell', 'correct' => false, 'feedback' => 'The difference comes from the rigid wall, not from sugar entering.'],
                                ['id' => 'd', 'label' => 'Osmosis only happens in animal cells', 'correct' => false, 'feedback' => 'Osmosis happens in plant cells too: it is how root cells take in water from the soil.'],
                            ],
                        ],
                        'reflect' => ['prompts' => ['Which solution made the cell swell, and which made it shrink? Say why.', 'What did you predict, and what happened?']],
                    ],
                    'outcomes' => [
                        'Predicts the direction of water movement in hypotonic, isotonic and hypertonic solutions.',
                        'Explains osmosis as diffusion of water across a selectively permeable membrane.',
                        'Explains why a plant cell keeps its shape but an animal cell does not.',
                    ],
                    'teacher_script' => [
                        'Run Activity 2.2 with real potato pieces first if time allows, and use the lab to explore what the beakers cannot show.',
                        'Turn on Particle view only after students have predicted and observed, then ask them to point to the water movement.',
                        'Stress that the 5% inside the cell and the size of the change are model settings, not measurements.',
                    ],
                    'particle_view' => true,
                ],
            ],
        ];
    }
}
