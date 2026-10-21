<?php

namespace App\Services\StudyDeck;

/**
 * How a teaching method changes a study deck.
 *
 * OPTIONAL and OFF by default: a run names no method and every prompt, rule and check of the pipeline is exactly what
 * it was. When a method is named (`lms:generate-study-deck --teaching-method=...`) it changes three things, all in
 * terms the existing pipeline already has (its slide types, its interaction kinds):
 *
 *   1. what the planner is told (a directive and the phase sequence), and what the slide text and the interactions
 *      are asked to look like;
 *   2. what the plan must contain, checked mechanically (`planErrors`): slide types the method needs, and the order of
 *      two slide types within one concept (for example the observation before the explanation);
 *   3. the same check on the final deck, so a deck cannot pass validation while ignoring the method it was made for.
 *
 * The profiles are deliberately modest: the pipeline caps recall/practice/exit_ticket slides at three, and a deck has
 * 30-35 slides for the whole chapter, so a profile asks for few, achievable things. It never asks for a slide type
 * the planner cannot produce.
 */
class TeachingMethodProfile
{
    /**
     * key => [label, directive, phases, content, interaction, min (type => count), min_any ([types[], count]), order ([before, after])]
     *
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        return [
            'explicit_instruction' => [
                'label' => 'Explicit instruction / direct teaching',
                'phases' => ['Explain', 'Model (worked example)', 'Guided practice', 'Check'],
                'directive' => 'The teacher explains first, then models. For each topic: a concept_intro slide that states the idea plainly, then a worked_example slide that shows it step by step, then practice or a check on it. Lead with explanation, never with an open investigation.',
                'content' => 'Write for a teacher who is explaining: state the idea directly and in order, and make every worked example a numbered, step-by-step model a student could copy before trying one alone. The "check" is a question the teacher asks to confirm understanding right after explaining.',
                'interaction' => 'Prefer "steps" (a worked procedure) and "reveal" (the explanation opened part by part), then "order" for a real sequence. Use "scenario" only where the chapter has a real decision.',
                'min' => ['concept_intro' => 6, 'worked_example' => 3],
                'min_any' => [[['practice', 'recall', 'exit_ticket'], 2]],
                'order' => [['concept_intro', 'worked_example'], ['worked_example', 'practice']],
            ],
            'inquiry_based' => [
                'label' => 'Inquiry-based learning',
                'phases' => ['Observe', 'Investigable question', 'Evidence', 'Explanation'],
                'directive' => 'Students meet the phenomenon before the explanation. For each topic: a concept_visual or scenario slide that shows something to observe and asks a question worth investigating, then the evidence, and only then the concept_intro that explains it. Use misconception slides to test what students think.',
                'content' => 'Open each slide with what students can observe or a question to investigate, and put the explanation AFTER the evidence. Phrase the "check" as an investigable question ("what would happen if...", "how could we find out...").',
                'interaction' => 'Prefer "hotspots" and "compare" (evidence to examine) and "scenario" (a situation to reason about) over "reveal". Each interaction should make the student look at evidence before reading the conclusion.',
                'min' => ['scenario' => 2, 'concept_visual' => 3],
                'min_any' => [],
                'order' => [['concept_visual', 'concept_intro'], ['scenario', 'concept_intro']],
            ],
            'problem_based' => [
                'label' => 'Problem-based learning',
                'phases' => ['Authentic problem', 'Investigation', 'Solution', 'Reflection'],
                'directive' => 'Each topic starts from a real problem. A scenario slide poses the problem, the concept_intro and worked_example slides are the investigation that equips students to solve it, an application slide is the solution, and a closing slide reflects on it. Never explain before the problem is posed.',
                'content' => 'Frame each slide as part of solving a problem: say what the problem is, what we need to find out, and how the idea solves it. The "check" asks students to apply the idea to the problem or reflect on how they solved it.',
                'interaction' => 'Prefer "scenario" (a problem with decisions and consequences), then "steps" for a solution method and "compare" for weighing options.',
                'min' => ['scenario' => 3, 'application' => 2, 'worked_example' => 2],
                'min_any' => [],
                'order' => [['scenario', 'concept_intro'], ['concept_intro', 'worked_example'], ['concept_intro', 'application']],
            ],
            'project_based' => [
                'label' => 'Project-based learning',
                'phases' => ['Driving question', 'Plan', 'Build', 'Present', 'Reflect'],
                'directive' => 'The deck serves a project. Open with a challenge slide that sets a driving question, teach only what the project needs, and use application and challenge slides as the build and present stages. The deck must read as a plan for student-made work, not a lecture.',
                'content' => 'Tie every slide to the project question and say what students will make or decide with the idea. The "check" is a planning or presenting prompt.',
                'interaction' => 'Prefer "steps" (a project plan) and "reveal" (success criteria), with "scenario" for design choices.',
                'min' => ['challenge' => 2, 'application' => 3],
                'min_any' => [],
                'order' => [['concept_intro', 'application'], ['challenge', 'application']],
            ],
            'experiential' => [
                'label' => 'Experiential / learning by doing',
                'phases' => ['Do', 'Observe', 'Explain', 'Apply'],
                'directive' => 'Students act first. For each topic: a slide that has students do or observe something (an activity, an experiment, a phenomenon to see), then the concept_intro that explains what they saw, then an application slide. Use the school\'s practical_activities wherever one matches.',
                'content' => 'Write each slide as an instruction to do or observe something, then explain what it shows. The "check" asks what students noticed.',
                'interaction' => 'Prefer "hotspots" (examine the set-up), "steps" (the procedure to carry out) and "scenario" (predict, then see the outcome).',
                'min' => ['concept_visual' => 3, 'scenario' => 2, 'application' => 2],
                'min_any' => [],
                'order' => [['concept_visual', 'concept_intro'], ['concept_intro', 'application']],
            ],
            'collaborative' => [
                'label' => 'Collaborative / peer learning',
                'phases' => ['Group task', 'Shared evidence', 'Discussion', 'Synthesis'],
                'directive' => 'Students work in groups. Use challenge slides as group tasks, make the "check" prompts group discussions, and close each topic with a synthesis. Plan slides students can split between group members (each takes a part and reports back).',
                'content' => 'Address groups: give each slide a task that can be divided between roles (for example reader, recorder, reporter), and word the "check" as something the group must agree on and explain to the class.',
                'interaction' => 'Prefer "reveal" and "compare" where different group members take different cards and report, and "match" for a group sort. Avoid interactions that suit a single reader.',
                'min' => ['challenge' => 2, 'application' => 2, 'summary' => 1],
                'min_any' => [],
                'order' => [['concept_intro', 'challenge']],
            ],
            'socratic' => [
                'label' => 'Socratic questioning / guided discovery',
                'phases' => ['Opening question', 'Probe', 'Challenge', 'Refine'],
                'directive' => 'The teacher questions rather than tells. Open each topic with a question, use misconception slides to probe and challenge what students believe, and relationship slides to make them connect ideas, finishing by refining the idea in the students\' own words. Keep slide text short; the questions carry the lesson.',
                'content' => 'Every slide leads with a question, the explanation is held back until the "check" has been asked, and the "check" prompts are chains of follow-up questions (why, what makes you think so, what if it were different).',
                'interaction' => 'Prefer "compare" (a wrong idea against a right one) and "reveal" (the answer opened only after discussion). Never present an interaction as a quiz.',
                'min' => ['misconception' => 3, 'relationship' => 2],
                'min_any' => [],
                'order' => [['concept_intro', 'misconception']],
            ],
            'flipped_classroom' => [
                'label' => 'Flipped classroom',
                'phases' => ['Pre-class input', 'In-class clarification', 'Application', 'Debrief'],
                'directive' => 'The explanation is done before class, so the deck is built for class time. Open with a prior_knowledge or recall slide that checks the pre-class input, keep concept_intro slides brief, and spend most slides on application and challenge work.',
                'content' => 'Assume students have met the idea already: confirm it in a line, then move quickly to using it. Make the "check" an application or clarification question.',
                'interaction' => 'Prefer "scenario" and "steps" (applying the idea) and "compare" (clearing up confusions) over "reveal".',
                'min' => ['prior_knowledge' => 1, 'challenge' => 2, 'application' => 3],
                'min_any' => [],
                'order' => [['concept_intro', 'application']],
            ],
            'differentiated_mastery' => [
                'label' => 'Differentiated / mastery-based instruction',
                'phases' => ['Diagnose', 'Scaffold gaps', 'Practise with feedback', 'Mastery check'],
                'directive' => 'Teach to mastery. Start from prior_knowledge to diagnose, scaffold with worked_example slides, address misconceptions explicitly, and end each stage with a check before moving on. Give each worked example a simpler step first and an extension.',
                'content' => 'Make every explanation scaffolded: the simplest statement first, then the full idea. Give each worked example in graded steps, and word the "check" as a mastery question with a model answer for teachers.',
                'interaction' => 'Prefer "steps" (graded), "compare" (misconceptions against corrections) and "match" (vocabulary to secure).',
                'min' => ['prior_knowledge' => 1, 'misconception' => 3, 'worked_example' => 3],
                'min_any' => [[['practice', 'recall', 'exit_ticket'], 2]],
                'order' => [['concept_intro', 'worked_example']],
            ],
            'gamified' => [
                'label' => 'Gamified learning',
                'phases' => ['Learning challenge', 'Progressive tasks', 'Feedback', 'Mastery check'],
                'directive' => 'Frame the deck as a sequence of challenges that get harder. Use challenge slides as levels, put the explanation each level needs just before it, give feedback after each, and end with a mastery check (exit_ticket). Students should feel progress.',
                'content' => 'Present each task as a level or mission with a clear goal and visible progress ("Level 2 of 5"). Keep the tone encouraging, never childish, and word the "check" as the challenge to clear.',
                'interaction' => 'Prefer "match", "order" and "scenario" (challenges with immediate feedback), with "reveal" as a reward after a task.',
                'min' => ['challenge' => 4, 'exit_ticket' => 1],
                'min_any' => [],
                'order' => [['concept_intro', 'challenge']],
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    public static function get(?string $key): ?array
    {
        return $key === null ? null : (self::all()[$key] ?? null);
    }

    /** @return array<int,string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * What the plan must contain for the method. Mechanical, and worded so it can be sent back to the planner.
     *
     * @param array<int,array<string,mixed>> $slides plan slides (slide_type, concept_ids)
     * @return array<int,string>
     */
    public static function planErrors(?string $key, array $slides): array
    {
        $p = self::get($key);
        if ($p === null) {
            return [];
        }
        $errors = [];
        $types = array_map(fn ($s) => (string) ($s['slide_type'] ?? ''), $slides);
        $counts = array_count_values($types);

        foreach ((array) $p['min'] as $type => $n) {
            $n = self::scaled($n, count($slides));
            if (($counts[$type] ?? 0) < $n) {
                $errors[] = "Teaching method \"{$p['label']}\" needs at least {$n} \"{$type}\" slide(s); the plan has " . ($counts[$type] ?? 0) . '.';
            }
        }
        foreach ((array) $p['min_any'] as [$group, $n]) {
            $n = self::scaled($n, count($slides));
            $have = array_sum(array_map(fn ($t) => $counts[$t] ?? 0, $group));
            if ($have < $n) {
                $errors[] = "Teaching method \"{$p['label']}\" needs at least {$n} slides of type " . implode(' / ', $group) . "; the plan has {$have}.";
            }
        }

        // Order within one concept: where a concept has a slide of each of two types, the first must come first.
        foreach ((array) $p['order'] as [$before, $after]) {
            $firstOf = [];
            foreach ($slides as $i => $s) {
                foreach ((array) ($s['concept_ids'] ?? []) as $cid) {
                    $firstOf[$s['slide_type'] ?? ''][(int) $cid] ??= $i;
                }
            }
            foreach ((array) ($firstOf[$before] ?? []) as $cid => $iBefore) {
                $iAfter = $firstOf[$after][$cid] ?? null;
                if ($iAfter !== null && $iAfter < $iBefore) {
                    $errors[] = "Teaching method \"{$p['label']}\": for concept {$cid} a \"{$after}\" slide (slide " . ($iAfter + 1) . ") comes before its \"{$before}\" slide (slide " . ($iBefore + 1) . '); the order must be ' . "{$before} then {$after}.";
                    break;
                }
            }
        }

        return array_slice($errors, 0, 12);
    }

    /** A profile's minimum is for a full chapter deck; a shorter deck is asked for proportionally fewer (one per six slides, at least one). */
    private static function scaled(int $n, int $slideCount): int
    {
        return min($n, max(1, intdiv($slideCount, 6)));
    }

    /** The planner's paragraph for the method, or '' for no method (so a default prompt is unchanged). */
    public static function plannerRule(?string $key): string
    {
        $p = self::get($key);
        if ($p === null) {
            return '';
        }
        $min = [];
        foreach ((array) $p['min'] as $type => $n) {
            $min[] = "at least {$n} \"{$type}\"";
        }
        foreach ((array) $p['min_any'] as [$group, $n]) {
            $min[] = "at least {$n} of " . implode('/', $group);
        }
        $order = array_map(fn ($o) => "{$o[0]} before {$o[1]}", (array) $p['order']);

        return "\n- TEACHING METHOD: {$p['label']}. Phases: " . implode(' -> ', $p['phases']) . ". {$p['directive']} For a full-length deck it must contain " . implode(', ', $min)
            . ' slides; for a concept that has both, put ' . implode('; ', $order) . '. Set "teaching_strategy" to say how the deck follows this method. The chapter text is still the only source of facts.';
    }

    /** One paragraph for the slide-text prompt. */
    public static function contentRule(?string $key): string
    {
        $p = self::get($key);

        return $p === null ? '' : "\n- TEACHING METHOD: {$p['label']}. {$p['content']}";
    }

    /** One paragraph for the interaction prompt. */
    public static function interactionRule(?string $key): string
    {
        $p = self::get($key);

        return $p === null ? '' : "\n- TEACHING METHOD: {$p['label']}. {$p['interaction']}";
    }
}
