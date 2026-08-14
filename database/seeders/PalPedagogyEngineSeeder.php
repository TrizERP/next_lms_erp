<?php

namespace Database\Seeders;

use App\Models\PAL\PedagogyEngineModule;
use App\Models\PAL\PedagogyEngineRule;
use App\Models\PAL\PedagogyEngineSection;
use Illuminate\Database\Seeder;

/**
 * Installs the PAL V4 AI Pedagogy Engine rule set (PAL_V4_AI_Pedagogy_Engine,
 * v4.0 / March 2026) into the database.
 *
 * Every value here comes from the specification document -- Section 2 (the five
 * IF/THEN rule tiers), Section 6.2 (engagement score composition) and the
 * pedagogy x trigger map. Nothing is generated at request time and nothing is
 * duplicated in the frontend; the PAL UI reads it back through
 * GET /api/pal/pedagogy-engine.
 *
 * Idempotent: re-running refreshes the rows in place via updateOrCreate.
 */
class PalPedagogyEngineSeeder extends Seeder
{
    public function run(): void
    {
        PedagogyEngineModule::updateOrCreate(
            ['slug' => 'pedagogy-engine'],
            [
                'module_name' => 'Pedagogy Engine',
                'title' => 'Pedagogy Engine',
                'description' => 'AI-powered learning adaptation rules: the tiered IF/THEN decision set that chooses the pedagogy, content type and format for every learning moment.',
                'version' => 'PAL V4',
                'source_label' => 'pal_pedagogy_engine_sections + pal_pedagogy_engine_rules',
                'is_active' => true,
            ]
        );

        foreach ($this->sections() as $index => $section) {
            $rules = $section['rules'];
            unset($section['rules']);

            PedagogyEngineSection::updateOrCreate(
                ['section_key' => $section['section_key']],
                $section + ['sort_order' => $index + 1, 'is_active' => true]
            );

            foreach (array_values($rules) as $ruleIndex => $rule) {
                PedagogyEngineRule::updateOrCreate(
                    ['section_key' => $section['section_key'], 'rule_key' => $rule['rule_key']],
                    $rule + [
                        'section_key' => $section['section_key'],
                        'sort_order' => $ruleIndex + 1,
                        'is_active' => true,
                    ]
                );
            }

            // Drop rows that were removed from the specification.
            PedagogyEngineRule::where('section_key', $section['section_key'])
                ->whereNotIn('rule_key', array_column($rules, 'rule_key'))
                ->delete();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sections(): array
    {
        return [
            $this->tier1(),
            $this->tier2(),
            $this->tier3(),
            $this->tier4(),
            $this->tier5(),
            $this->engagementScore(),
            $this->triggerMap(),
            $this->practiceLadder(),
        ];
    }

    /**
     * The 5-level Bloom ladder the Tier 1 bands select practice from.
     *
     * Stored as data (not a PHP match statement) because it is the lookup that
     * turns a mastery band into a filter over the concept's real
     * assessment_blueprint rows in semantic_intelligence. Hidden from the UI --
     * it is engine configuration, surfaced only through the resolved rules.
     */
    private function practiceLadder(): array
    {
        $level = fn (string $key, int $n, string $name, array $blooms, array $dok, array $difficulty) => [
            'rule_key' => $key,
            'group_label' => 'practice_level',
            'condition' => "Practice L{$n}",
            'action' => $name,
            'pedagogy' => null,
            'h5p_type' => null,
            'scaffolding' => null,
            'trigger_action' => null,
            'reason' => null,
            'implementation_status' => null,
            'rule_meta' => [
                'level' => $n,
                'bloom_levels' => $blooms,
                'dok_levels' => $dok,
                'difficulty' => $difficulty,
            ],
        ];

        return [
            'section_key' => 'practice-ladder',
            'name' => '5-Level Bloom Ladder',
            'subtitle' => 'Maps Practice L1-L5 onto Bloom, DOK and difficulty bands.',
            'summary' => 'Used to select which of a concept\'s extracted assessment_blueprint rows each Tier 1 mastery band should serve.',
            'section_type' => 'rules',
            'badges' => ['engine config', 'bloom ladder'],
            'implementation_status' => null,
            'current_state' => null,
            'gap' => null,
            'ui_visible' => false,
            'rules' => [
                $level('practice-l1', 1, 'Recall', ['Remember'], ['1'], ['Easy']),
                $level('practice-l2', 2, 'Understand', ['Understand'], ['1', '2'], ['Easy', 'Medium']),
                $level('practice-l3', 3, 'Apply', ['Apply'], ['2'], ['Medium']),
                $level('practice-l4', 4, 'Analyze', ['Analyze'], ['2', '3'], ['Medium', 'Hard']),
                $level('practice-l5', 5, 'Evaluate / Create', ['Evaluate', 'Create'], ['3', '4'], ['Hard']),
            ],
        ];
    }

    private function tier1(): array
    {
        return [
            'section_key' => 'tier-1',
            'name' => 'Tier 1: Mastery & Knowledge State Rules',
            'subtitle' => 'What content type is served, based on where the learner sits in the mastery progression.',
            'summary' => 'Tier 1 runs first. It maps the BKT mastery band to a content type, a pedagogy, an H5P type and a scaffolding level before any other tier can override the decision.',
            'section_type' => 'rules',
            'badges' => ['bkt_mastery', 'runs first', 'content selection'],
            'implementation_status' => 'Not Implemented',
            'current_state' => 'UI shows pedagogy recommendations',
            'gap' => 'No Tier 1 rule execution',
            'ui_visible' => true,
            'rules' => [
                [
                    'rule_key' => 'mastery-below-40',
                    'group_label' => 'No foundation',
                    'condition' => 'bkt_mastery < 0.40',
                    'action' => 'Serve Concept Learning V1 (text + diagram, in the learner\'s current language)',
                    'pedagogy' => 'concept_based',
                    'h5p_type' => 'Course Presentation or Interactive Video (short, <= 5 min)',
                    'scaffolding' => 'Full - all hints available, worked example visible',
                    'trigger_action' => null,
                    'reason' => 'Student needs to build the schema first',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'mastery_range' => ['min' => 0.0, 'max' => 0.399],
                        'state' => 'no foundation',
                        'content_type' => 'concept',
                        'do_not' => ['Serve Practice L3+', 'Serve Assessment'],
                        'bloom_ceiling' => 'understand',
                        'difficulty' => 1,
                    ],
                ],
                [
                    'rule_key' => 'mastery-40-69',
                    'group_label' => 'Building',
                    'condition' => 'bkt_mastery BETWEEN 0.40 AND 0.69',
                    'action' => 'Serve Practice L1 -> L2 -> L3 in sequence',
                    'pedagogy' => 'activity_based or flashcard',
                    'h5p_type' => 'Fill in the Blanks, Drag & Drop, Dialog Cards',
                    'scaffolding' => 'Hints available on request (not shown automatically)',
                    'trigger_action' => 'IF 2 consecutive wrong -> trigger misconception check. IF misconception confirmed -> serve Misconception Library, then return here.',
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'mastery_range' => ['min' => 0.40, 'max' => 0.69],
                        'state' => 'building',
                        'bloom_ceiling' => 'apply',
                        'content_type' => 'practice',
                        'practice_levels' => [1, 2, 3],
                    ],
                ],
                [
                    'rule_key' => 'mastery-70-84',
                    'group_label' => 'Consolidating',
                    'condition' => 'bkt_mastery BETWEEN 0.70 AND 0.84',
                    'action' => 'Serve Practice L3 + L4; spaced review of earlier concepts',
                    'pedagogy' => 'inquiry_based or scenario_based',
                    'h5p_type' => 'Essay (short), Branching Scenario (basic)',
                    'scaffolding' => 'None - student should work independently',
                    'trigger_action' => 'Schedule spaced review for this concept; suggest a cross-curricular link when one is available.',
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'mastery_range' => ['min' => 0.70, 'max' => 0.84],
                        'state' => 'consolidating',
                        'bloom_ceiling' => 'analyze',
                        'content_type' => 'practice',
                        'practice_levels' => [3, 4],
                        'spaced_review' => true,
                    ],
                ],
                [
                    'rule_key' => 'mastery-85-92',
                    'group_label' => 'Approaching mastery',
                    'condition' => 'bkt_mastery BETWEEN 0.85 AND 0.92',
                    'action' => 'Serve Practice L4 + L5; open a peer teaching opportunity',
                    'pedagogy' => 'project_based or competency_based',
                    'h5p_type' => 'Documentation Tool, Branching Scenario (complex)',
                    'scaffolding' => 'None',
                    'trigger_action' => 'Suggest a peer tutoring assignment to the teacher; flag Expanded Tasks.',
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'mastery_range' => ['min' => 0.85, 'max' => 0.92],
                        'state' => 'approaching mastery',
                        'bloom_ceiling' => 'create',
                        'content_type' => 'practice',
                        'practice_levels' => [4, 5],
                    ],
                ],
                [
                    'rule_key' => 'mastery-93-plus',
                    'group_label' => 'Mastered',
                    'condition' => 'bkt_mastery >= 0.93',
                    'action' => 'Serve Expanded Tasks; cross-curricular application',
                    'pedagogy' => 'project_based or concept_sports',
                    'h5p_type' => 'Documentation Tool, complex Branching Scenario',
                    'scaffolding' => 'None',
                    'trigger_action' => 'Unlock the next concept in the learning path; schedule a 30-day retention probe.',
                    'reason' => 'Further practice on this concept has diminishing returns',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'mastery_range' => ['min' => 0.93, 'max' => 1.0],
                        'state' => 'mastered',
                        'content_type' => 'expanded_task',
                        'practice_levels' => [5],
                        'do_not' => ['Serve more practice on this concept'],
                    ],
                ],
            ],
        ];
    }

    private function tier2(): array
    {
        return [
            'section_key' => 'tier-2',
            'name' => 'Tier 2: Engagement State Rules',
            'subtitle' => 'Motivational overrides that outrank the Tier 1 content decision.',
            'summary' => 'Tier 2 runs second. When engagement signals indicate a motivational problem it temporarily overrides Tier 1 - injecting a game node, reducing session load, or protecting a flow state.',
            'section_type' => 'rules',
            'badges' => ['engagement_score', 'override', 'motivation'],
            'implementation_status' => 'Not Implemented',
            'current_state' => 'No engagement score calculation',
            'gap' => 'No engagement tracking, no intervention triggers',
            'ui_visible' => true,
            'rules' => [
                [
                    'rule_key' => 'engagement-below-50',
                    'group_label' => 'Low engagement this session',
                    'condition' => 'engagement_score < 50',
                    'action' => 'Check difficulty first. If difficulty is appropriate but engagement is low, inject a single game-based content node, then return to the original pedagogy.',
                    'pedagogy' => 'game_based (temporary, 1 content node only)',
                    'h5p_type' => 'Memory Game, Crossword',
                    'scaffolding' => null,
                    'trigger_action' => 'Motivational injection; the original decision resumes immediately after the game.',
                    'reason' => 'Motivational reset, not a permanent switch',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['duration' => 'short_5min', 'scope' => 'single node'],
                ],
                [
                    'rule_key' => 'engagement-7d-decline',
                    'group_label' => 'Declining over a week',
                    'condition' => 'engagement_7d_trend < -15%',
                    'action' => 'Serve the highest-engagement content type in the learner\'s history and re-run modality inference to check whether the learning style has changed.',
                    'pedagogy' => 'Learner\'s historically best-performing pedagogy',
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => 'Alert the teacher through the Intervention Queue; consider reducing session length when the average session exceeds 25 minutes.',
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['teacher_alert' => true, 'window_days' => 7],
                ],
                [
                    'rule_key' => 'session-over-30-min',
                    'group_label' => 'Cognitive fatigue',
                    'condition' => 'session_duration > 30 minutes with no break',
                    'action' => 'Serve one flashcard (spaced review) node as a quick win, then suggest a break.',
                    'pedagogy' => 'flashcard',
                    'h5p_type' => 'Dialog Cards',
                    'scaffolding' => null,
                    'trigger_action' => 'Prompt: "You\'ve been learning for 30 minutes! Take a 5-minute break?"',
                    'reason' => 'Cognitive fatigue - a short win before the break improves retention',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['count' => 3, 'after_this' => 'suggest_break'],
                ],
                [
                    'rule_key' => 'return-streak-zero',
                    'group_label' => 'Returning after absence',
                    'condition' => 'return_streak = 0 (student has not returned in 3+ days)',
                    'action' => 'Always start the first session back with spaced review on familiar, lower-difficulty content.',
                    'pedagogy' => 'flashcard (familiar, low-stakes re-entry)',
                    'h5p_type' => 'Dialog Cards, Flash Cards',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => 'Re-establish confidence before challenging the learner',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['do_not' => ['Serve new hard content on the return session', 'Mention the absence']],
                ],
                [
                    'rule_key' => 'flow-state',
                    'group_label' => 'Flow state',
                    'condition' => 'engagement_score > 85 AND bkt_mastery > 0.85',
                    'action' => 'Do not interrupt. Let the session continue, increase difficulty slightly and serve L5 when available.',
                    'pedagogy' => 'Keep the current pedagogy',
                    'h5p_type' => 'Most intellectually demanding content available',
                    'scaffolding' => 'None',
                    'trigger_action' => null,
                    'reason' => 'Interrupting a flow state costs more than the intervention gains',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['do_not' => ['Inject game-based content - it breaks flow']],
                ],
            ],
        ];
    }

    private function tier3(): array
    {
        return [
            'section_key' => 'tier-3',
            'name' => 'Tier 3: Error & Misconception Rules',
            'subtitle' => 'Responses driven by the pattern of errors, not just the fact of an error.',
            'summary' => 'Tier 3 runs third. It distinguishes a confirmed misconception from a random slip, a slow-but-correct answer from automaticity, and an individual gap from a class-wide one.',
            'section_type' => 'rules',
            'badges' => ['misconception', 'error pattern', 'corrective'],
            'implementation_status' => 'Partially Implemented',
            'current_state' => 'UI shows misconception modal and content generation',
            'gap' => 'No pattern matching, no corrective routing',
            'ui_visible' => true,
            'rules' => [
                [
                    'rule_key' => 'misconception-confirmed',
                    'group_label' => 'Confirmed misconception',
                    'condition' => 'wrong_answer AND misconception_tag CONFIRMED',
                    'action' => 'Serve Misconception Library corrective content, switching to the corrective_format from the misconception node (visual if text was served, text if visual was served). Return to Practice L1 afterwards.',
                    'pedagogy' => 'activity_based or art_integrated (hands-on fix)',
                    'h5p_type' => 'The h5p_type carried by the corrective node',
                    'scaffolding' => null,
                    'trigger_action' => 'On the 3rd occurrence of the same misconception, notify the teacher.',
                    'reason' => 'Repeating the same explanation in the same format is what already failed',
                    'implementation_status' => 'Partially Implemented',
                    'rule_meta' => ['do_not' => ['Show the same explanation again in the same format'], 'after_this' => 'Practice L1'],
                ],
                [
                    'rule_key' => 'no-misconception-match',
                    'group_label' => 'Unmatched error',
                    'condition' => 'wrong_answer AND NO misconception_tag matched',
                    'action' => 'Serve the next concept variant (V2 if V1 was shown, V3 if V2 was shown) and add hint 1.',
                    'pedagogy' => 'Unchanged - insufficient data to justify a switch',
                    'h5p_type' => null,
                    'scaffolding' => 'Add hint 1 only (not hint 2 yet)',
                    'trigger_action' => null,
                    'reason' => 'May be a random error rather than a deep misconception',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['difficulty_delta' => -0.5],
                ],
                [
                    'rule_key' => 'correct-high-latency',
                    'group_label' => 'Knows but slow',
                    'condition' => 'correct_answer BUT high_latency (> 3x average time)',
                    'action' => 'Serve one fluency-building flashcard on this sub-concept.',
                    'pedagogy' => 'flashcard',
                    'h5p_type' => 'Flash Cards, Dialog Cards',
                    'scaffolding' => null,
                    'trigger_action' => 'Flag confidence_dimension as "knows but slow".',
                    'reason' => 'Student may be guessing slowly or computing manually',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['do_not' => ['Advance to the next concept yet']],
                ],
                [
                    'rule_key' => 'fast-and-right',
                    'group_label' => 'Automaticity',
                    'condition' => 'high_error_fluency AND high_correct_fluency (fast and right)',
                    'action' => 'Serve Practice L4 / L5 immediately.',
                    'pedagogy' => 'inquiry_based or project_based',
                    'h5p_type' => 'Branching Scenario, Documentation Tool',
                    'scaffolding' => 'None',
                    'trigger_action' => null,
                    'reason' => 'Student is performing at automaticity - challenge them',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [],
                ],
                [
                    'rule_key' => 'fast-and-wrong',
                    'group_label' => 'Guessing',
                    'condition' => 'high_error_fluency AND low_correct_fluency (fast and wrong)',
                    'action' => 'Serve Concept V1 again and reduce difficulty by one level.',
                    'pedagogy' => 'concept_based (back to basics)',
                    'h5p_type' => 'Course Presentation, Interactive Video',
                    'scaffolding' => 'Full',
                    'trigger_action' => 'Flag misconception suspicion as high.',
                    'reason' => 'The response pattern indicates guessing rather than reasoning',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['difficulty_delta' => -1],
                ],
                [
                    'rule_key' => 'class-wide-misconception',
                    'group_label' => 'Majority problem',
                    'condition' => 'misconception_majority (> 40% of the class shows the same error)',
                    'action' => 'Raise a teacher alert, e.g. "43% of your class has denominator_add_error. Consider a whole-class re-teach using the pizza area model."',
                    'pedagogy' => 'Teacher decides',
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => 'Teacher alert only.',
                    'reason' => 'This is a data-informed signal, not a data-driven action',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['threshold' => 0.40, 'do_not' => ['Auto-fix for the whole class - the teacher must decide']],
                ],
            ],
        ];
    }

    private function tier4(): array
    {
        return [
            'section_key' => 'tier-4',
            'name' => 'Tier 4: Learning Style Rules',
            'subtitle' => 'Format selection tuned to historical preference, language, bandwidth and device.',
            'summary' => 'Tier 4 runs fourth. It does not change what is taught - it changes the format it arrives in, using the learner\'s modality profile and delivery context.',
            'section_type' => 'rules',
            'badges' => ['learning style', 'context', 'format'],
            'implementation_status' => 'Not Implemented',
            'current_state' => 'No learning style inference',
            'gap' => 'No VARK profiling, no modality switching',
            'ui_visible' => true,
            'rules' => [
                [
                    'rule_key' => 'style-visual',
                    'group_label' => 'Learning style',
                    'condition' => "learning_style = 'visual'",
                    'action' => 'Prefer diagram-based content, Image Hotspot and Interactive Video.',
                    'pedagogy' => 'art_integrated / concept_based (visual delivery)',
                    'h5p_type' => 'Image Hotspot > Interactive Video > Fill in the Blanks',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'avoid' => ['Text-heavy explanations as the primary format'],
                        'h5p_priority' => ['image_hotspot', 'interactive_video', 'course_presentation'],
                    ],
                ],
                [
                    'rule_key' => 'style-auditory',
                    'group_label' => 'Learning style',
                    'condition' => "learning_style = 'auditory'",
                    'action' => 'Prefer Interactive Video with narration and Audio Recorder tasks.',
                    'pedagogy' => 'experiential',
                    'h5p_type' => 'Interactive Video > Course Presentation with audio',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'avoid' => ['Silent diagram-only content'],
                        'h5p_priority' => ['interactive_video', 'audio_recorder', 'course_presentation'],
                    ],
                ],
                [
                    'rule_key' => 'style-kinesthetic',
                    'group_label' => 'Learning style',
                    'condition' => "learning_style = 'kinesthetic'",
                    'action' => 'Prefer Drag & Drop, activity-based tasks and Documentation Tool.',
                    'pedagogy' => 'activity_based',
                    'h5p_type' => 'Drag & Drop > Mark the Words > Documentation Tool',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'avoid' => ['Passive reading as the first approach'],
                        'h5p_priority' => ['drag_and_drop', 'mark_the_words', 'documentation_tool'],
                    ],
                ],
                [
                    'rule_key' => 'style-read-write',
                    'group_label' => 'Learning style',
                    'condition' => "learning_style = 'read_write'",
                    'action' => 'Prefer text-based explanations, Fill in the Blanks and Essay.',
                    'pedagogy' => 'concept_based',
                    'h5p_type' => 'Fill in the Blanks > Essay > Course Presentation',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'avoid' => ['Video-only delivery'],
                        'h5p_priority' => ['fill_in_the_blanks', 'essay', 'course_presentation'],
                    ],
                ],
                [
                    'rule_key' => 'context-language',
                    'group_label' => 'Language / vernacular',
                    'condition' => "language != 'en' AND vernacular_variant_available = true",
                    'action' => 'Always serve Concept content in the mother tongue. Serve Practice content bilingually - body text in the mother tongue, technical terms in English with a translation.',
                    'pedagogy' => 'Unchanged',
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['avoid' => ['Serving Concept content in English when a vernacular variant exists']],
                ],
                [
                    'rule_key' => 'context-bandwidth',
                    'group_label' => 'Bandwidth profile',
                    'condition' => "bandwidth_profile = '2G_3G'",
                    'action' => 'Serve text + diagram variants first and enable offline sync for the next 5 content nodes.',
                    'pedagogy' => 'Unchanged',
                    'h5p_type' => 'Fill in the Blanks > Drag & Drop > Flash Cards',
                    'scaffolding' => null,
                    'trigger_action' => 'Enable offline sync for the next 5 content nodes.',
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'avoid' => ['Video content over 2MB'],
                        'h5p_priority' => ['fill_in_the_blanks', 'drag_and_drop', 'flash_cards'],
                    ],
                ],
                [
                    'rule_key' => 'context-smartphone',
                    'group_label' => 'Device context',
                    'condition' => "device_type = 'smartphone'",
                    'action' => 'Prefer vertical-scrolling content with large tap targets; target 15-minute sessions instead of 20.',
                    'pedagogy' => 'Unchanged',
                    'h5p_type' => 'Tap-to-place instead of drag-and-drop on very small screens',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [
                        'avoid' => ['Drag-and-drop on very small screens'],
                        'session_target_minutes' => 15,
                    ],
                ],
            ],
        ];
    }

    /**
     * Tier 5 is part of the documented engine but is not one of the six sections
     * the PAL UI currently exposes, so it ships with ui_visible = false: the API
     * still returns it under ?include_hidden=1 without changing the UI.
     */
    private function tier5(): array
    {
        return [
            'section_key' => 'tier-5',
            'name' => 'Tier 5: Special State Rules',
            'subtitle' => 'Spaced review, first session, confidence crisis and teacher override.',
            'summary' => 'Tier 5 runs last and handles states that sit outside the normal mastery / engagement / error flow.',
            'section_type' => 'rules',
            'badges' => ['special states', 'runs last'],
            'implementation_status' => 'Not Implemented',
            'current_state' => 'Not surfaced in the PAL UI',
            'gap' => 'No special-state handling',
            'ui_visible' => false,
            'rules' => [
                [
                    'rule_key' => 'spaced-review-due',
                    'group_label' => 'Spaced review',
                    'condition' => 'spaced_review_due = true (next_review_date <= today)',
                    'action' => 'Always start the session with spaced review - 3 to 5 items on mastered concepts - before new content.',
                    'pedagogy' => 'flashcard',
                    'h5p_type' => 'Dialog Cards or Flash Cards',
                    'scaffolding' => null,
                    'trigger_action' => 'Proceed to new or pending content after the review.',
                    'reason' => 'The forgetting-curve check takes priority; retention outranks new learning',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['count' => ['min' => 3, 'max' => 5]],
                ],
                [
                    'rule_key' => 'first-session-ever',
                    'group_label' => 'First session',
                    'condition' => 'first_session_ever (Class A learner)',
                    'action' => 'Serve a diagnostic assessment of 5 items across a wide difficulty range.',
                    'pedagogy' => 'concept_based (most universal entry point)',
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => 'There is not yet enough data to infer a learning style',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['do_not' => ['Infer learning style yet', 'Serve advanced content regardless of early correct answers']],
                ],
                [
                    'rule_key' => 'low-self-confidence',
                    'group_label' => 'Confidence crisis',
                    'condition' => 'student_self_rated_confidence < 2',
                    'action' => 'Reduce difficulty by one level temporarily and serve high-frequency, quick-win content with fast feedback.',
                    'pedagogy' => 'activity_based',
                    'h5p_type' => 'Short practice with immediate feedback',
                    'scaffolding' => 'On request',
                    'trigger_action' => null,
                    'reason' => 'Rebuild confidence through visible progress before difficulty returns',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['difficulty_delta' => -1, 'avoid' => ['Peer comparison', 'Class rank or comparison scores']],
                ],
                [
                    'rule_key' => 'teacher-override',
                    'group_label' => 'Teacher override',
                    'condition' => 'teacher_override_set = true',
                    'action' => 'Respect the teacher\'s explicit instruction; it overrides every algorithm rule.',
                    'pedagogy' => 'As set by the teacher',
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => 'Log teacher_id, reason and timestamp for model improvement.',
                    'reason' => 'The teacher has context the algorithm cannot see',
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => [],
                ],
            ],
        ];
    }

    private function engagementScore(): array
    {
        return [
            'section_key' => 'engagement-score',
            'name' => 'Engagement Score Composition',
            'subtitle' => 'The four weighted signals behind the 0-100 engagement score, and how each band is read.',
            'summary' => 'engagement_score = (time_on_task_ratio x 0.30) + (interaction_rate x 0.25) + (session_return_rate x 0.25) + (voluntary_extension_rate x 0.20). Weights are reviewed quarterly.',
            'section_type' => 'signals',
            'badges' => ['0-100', 'quarterly weights', 'drives Tier 2'],
            'implementation_status' => 'Not Implemented',
            'current_state' => 'No engagement score tracking',
            'gap' => 'No real-time engagement calculation',
            'ui_visible' => true,
            'rules' => [
                [
                    'rule_key' => 'signal-time-on-task',
                    'group_label' => 'signal',
                    'condition' => 'Time on Task Ratio',
                    'action' => 'Actual session duration measured against the expected duration for the served content.',
                    'pedagogy' => null,
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['weight' => 0.30, 'weight_percent' => 30, 'key' => 'time_on_task_ratio'],
                ],
                [
                    'rule_key' => 'signal-interaction-rate',
                    'group_label' => 'signal',
                    'condition' => 'Interaction Rate',
                    'action' => 'Clicks, answers and drags per minute during the session.',
                    'pedagogy' => null,
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['weight' => 0.25, 'weight_percent' => 25, 'key' => 'interaction_rate'],
                ],
                [
                    'rule_key' => 'signal-session-return',
                    'group_label' => 'signal',
                    'condition' => 'Session Return Rate',
                    'action' => 'Whether the learner came back the following day.',
                    'pedagogy' => null,
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['weight' => 0.25, 'weight_percent' => 25, 'key' => 'session_return_rate'],
                ],
                [
                    'rule_key' => 'signal-voluntary-extension',
                    'group_label' => 'signal',
                    'condition' => 'Voluntary Extension Rate',
                    'action' => 'Whether the learner continued past the required content.',
                    'pedagogy' => null,
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['weight' => 0.20, 'weight_percent' => 20, 'key' => 'voluntary_extension_rate'],
                ],
                [
                    'rule_key' => 'band-critical',
                    'group_label' => 'band',
                    'condition' => 'engagement_score < 40',
                    'action' => 'Critical',
                    'pedagogy' => 'game_based',
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => 'Serve a game-based injection and alert the teacher.',
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['range' => ['min' => 0, 'max' => 39], 'label' => 'Critical', 'tone' => 'critical'],
                ],
                [
                    'rule_key' => 'band-low',
                    'group_label' => 'band',
                    'condition' => 'engagement_score 40-60',
                    'action' => 'Low',
                    'pedagogy' => null,
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => 'Switch pedagogy and check content quality.',
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['range' => ['min' => 40, 'max' => 60], 'label' => 'Low', 'tone' => 'warning'],
                ],
                [
                    'rule_key' => 'band-normal',
                    'group_label' => 'band',
                    'condition' => 'engagement_score 60-75',
                    'action' => 'Normal',
                    'pedagogy' => null,
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => 'No action needed.',
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['range' => ['min' => 60, 'max' => 75], 'label' => 'Normal', 'tone' => 'neutral'],
                ],
                [
                    'rule_key' => 'band-good',
                    'group_label' => 'band',
                    'condition' => 'engagement_score 75-85',
                    'action' => 'Good',
                    'pedagogy' => null,
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => 'Maintain the current approach.',
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['range' => ['min' => 75, 'max' => 85], 'label' => 'Good', 'tone' => 'good'],
                ],
                [
                    'rule_key' => 'band-flow',
                    'group_label' => 'band',
                    'condition' => 'engagement_score > 85',
                    'action' => 'Flow State',
                    'pedagogy' => null,
                    'h5p_type' => null,
                    'scaffolding' => null,
                    'trigger_action' => 'Increase difficulty; do not interrupt.',
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['range' => ['min' => 86, 'max' => 100], 'label' => 'Flow State', 'tone' => 'flow'],
                ],
            ],
        ];
    }

    private function triggerMap(): array
    {
        return [
            'section_key' => 'trigger-map',
            'name' => 'Pedagogy x Trigger Map',
            'subtitle' => 'Which pedagogy and H5P type each engine trigger resolves to.',
            'summary' => 'The lookup the engine consults once a trigger fires: trigger -> pedagogy -> H5P content type -> expected session length.',
            'section_type' => 'triggers',
            'badges' => ['trigger routing', 'h5p mapping'],
            'implementation_status' => 'Not Implemented',
            'current_state' => 'UI displays pedagogy tags',
            'gap' => 'No automatic pedagogy selection',
            'ui_visible' => true,
            'rules' => [
                [
                    'rule_key' => 'trigger-misconception-confirmed',
                    'group_label' => 'trigger',
                    'condition' => 'Misconception confirmed',
                    'action' => 'Route to hands-on corrective content.',
                    'pedagogy' => 'activity_based',
                    'h5p_type' => 'Image Hotspot',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['duration' => 'Short', 'source_tier' => 'tier-3'],
                ],
                [
                    'rule_key' => 'trigger-engagement-below-40',
                    'group_label' => 'trigger',
                    'condition' => 'Engagement < 40',
                    'action' => 'Inject a motivational game node.',
                    'pedagogy' => 'game_based',
                    'h5p_type' => 'Memory Game',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['duration' => 'Short', 'source_tier' => 'tier-2'],
                ],
                [
                    'rule_key' => 'trigger-mastery-above-85',
                    'group_label' => 'trigger',
                    'condition' => 'Mastery > 0.85',
                    'action' => 'Move to portfolio-grade application work.',
                    'pedagogy' => 'project_based',
                    'h5p_type' => 'Documentation Tool',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['duration' => 'Long', 'source_tier' => 'tier-1'],
                ],
                [
                    'rule_key' => 'trigger-spaced-review-due',
                    'group_label' => 'trigger',
                    'condition' => 'Spaced review due',
                    'action' => 'Open the session with retrieval practice.',
                    'pedagogy' => 'flashcard',
                    'h5p_type' => 'Dialog Cards',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['duration' => 'Short', 'source_tier' => 'tier-5'],
                ],
                [
                    'rule_key' => 'trigger-riasec-career-signal',
                    'group_label' => 'trigger',
                    'condition' => 'RIASEC career signal',
                    'action' => 'Anchor the concept in a career decision context.',
                    'pedagogy' => 'scenario_based',
                    'h5p_type' => 'Branching Scenario',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['duration' => null, 'source_tier' => null],
                ],
                [
                    'rule_key' => 'trigger-art-integration',
                    'group_label' => 'trigger',
                    'condition' => 'Art integration',
                    'action' => 'Express the concept through a creative artefact.',
                    'pedagogy' => 'art_integrated',
                    'h5p_type' => 'Audio Recorder',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['duration' => null, 'source_tier' => null],
                ],
                [
                    'rule_key' => 'trigger-cross-curricular-link',
                    'group_label' => 'trigger',
                    'condition' => 'Cross-curricular link',
                    'action' => 'Transfer the concept into another subject context.',
                    'pedagogy' => 'concept_sports',
                    'h5p_type' => 'Interactive Video',
                    'scaffolding' => null,
                    'trigger_action' => null,
                    'reason' => null,
                    'implementation_status' => 'Not Implemented',
                    'rule_meta' => ['duration' => null, 'source_tier' => null],
                ],
            ],
        ];
    }
}
