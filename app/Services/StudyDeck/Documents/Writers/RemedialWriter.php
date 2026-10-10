<?php

namespace App\Services\StudyDeck\Documents\Writers;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\InteractionPlanner;

/**
 * Writes the wording of a remedial class: one unit for every concept in scope, for a learner who has not yet
 * mastered it.
 *
 * A remedial class does not repeat the lesson. For each concept it rebuilds the idea in small steps, in plainer words
 * than the textbook, from what the learner must already know, through a worked example and the mistakes learners
 * actually make, to practice that gets harder and a closing win. The model writes only the WORDING of those parts.
 * Which bank questions make up the practice, and in what order, was decided before it is asked (QuestionPlacement),
 * and what to revisit afterwards is worked out from the prerequisite graph; neither is the model's to invent.
 */
class RemedialWriter extends DocumentWriter
{
    public const EXPLANATION_WORDS = [10, 80];

    public const STEPS = [3, 5];

    public const HINT_WORDS = 40;

    public const WIN_WORDS = [5, 30];

    /**
     * The COMPACT profile: a unit for a class that must fit a few printed pages. The idea in a sentence or two, three
     * one-line steps, at most one mistake, one "you can now" line; no worked example, no real-life picture, no
     * refresher for each prerequisite (the unit says what it needs first by name) and no hints (the bank's own questions
     * are printed after the units, without them). The validator applies the same limits to the finished document.
     */
    public const COMPACT_EXPLANATION_WORDS = [6, 18];

    public const COMPACT_STEP_LABEL_WORDS = 3;

    public const COMPACT_STEP_TEXT_WORDS = 8;

    public const COMPACT_MISTAKE_WORDS = 8;

    public const COMPACT_WIN_WORDS = [5, 10];

    /**
     * The PURPOSE profile: a LESSON for a concept learners may find hard, in a class that reteaches only those concepts
     * (GapAnalysis chooses them). It teaches the idea again from its foundations, so unlike a card it must carry a worked
     * example that reasons through a situation from the chapter, step by step, and a real-life picture; it has hints for
     * only its two guided questions, and no "mistakes" (the misconceptions have a section of their own, the clinic).
     */
    public const LESSON_EXPLANATION_WORDS = [20, 60];

    public const LESSON_STEPS = [3, 4];

    public const LESSON_STEP_TEXT_WORDS = 24;

    public const LESSON_REAL_LIFE_WORDS = 38;

    public const LESSON_EXAMPLE_STEPS = [3, 4];

    public const LESSON_EXAMPLE_WORDS = ['problem' => 32, 'step' => 20, 'why' => 18, 'answer' => 24];

    public const LESSON_WIN_WORDS = [5, 20];

    private const SYSTEM = 'You are a patient remedial teacher. You help learners who found a textbook chapter hard to follow, '
        . 'by rebuilding each idea in small, plain steps. You reply with a single JSON object and nothing else. '
        . 'You never add facts that are not in the chapter text.';

    public function __construct(Completer $completer, int $chunkSize = 3, private readonly ?InteractionPlanner $checker = null, private readonly bool $compact = false, private readonly bool $purpose = false)
    {
        parent::__construct($completer, $chunkSize);
    }

    public function kind(): DocumentKind
    {
        return DocumentKind::Remedial;
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<int,int> $scope the concepts to write, in teaching order (prerequisites first)
     * @param array<int,array<int,array<string,mixed>>> $placement concept_id => the bank questions chosen for it, easiest first
     * @param callable(int,int):void|null $progress
     * @return array{units:array<int,array<string,mixed>>}
     */
    public function write(array $context, array $map, array $scope, array $placement = [], ?callable $progress = null): array
    {
        $topicName = array_column($map['topics'], 'name', 'id');
        $work = [];
        foreach ($scope as $id) {
            $c = $map['concepts'][$id];
            $work[$id] = [
                'concept_id' => $id,
                'name' => $c['name'],
                'topic' => $topicName[$c['topic_id']] ?? '',
                'definition' => $c['definition'],
                'difficulty' => $c['difficulty'],
                'objectives' => array_slice($c['objectives'], 0, 3),
                'knowledge' => array_slice($c['knowledge'], 0, 5),
                'real_world' => array_slice($c['real_world'], 0, 2),
                // A lesson has no "mistakes": the clinic takes the misconceptions, so the model is not shown them here.
                'misconceptions' => $this->purpose ? [] : array_map(fn ($m) => ['wrong_idea' => $m['wrong_idea'], 'what_learners_think' => $m['statement'], 'correction' => $m['correction']], $c['misconceptions']),
                'prerequisites' => array_values(array_map(
                    fn ($r) => ['concept_id' => $r, 'name' => $map['concepts'][$r]['name'], 'definition' => $map['concepts'][$r]['definition']],
                    array_filter($c['requires'], fn ($r) => isset($map['concepts'][$r]))
                )),
                'practice' => $this->compact ? [] : array_map(fn ($q, $i) => self::practiceItem($q, $i + 1), $placement[$id] ?? [], array_keys($placement[$id] ?? [])),
            ];
            if ($this->compact) {
                // A compact unit has no real-life picture, so the model is not shown what it cannot use.
                unset($work[$id]['real_world']);
            }
        }

        $compact = $this->compact;
        $purpose = $this->purpose;
        $units = $this->inChunks(
            $work,
            self::SYSTEM,
            $compact ? 8000 : 14000,
            $purpose ? 'remedial lessons' : 'remedial units',
            fn (array $chunk) => match (true) {
                $purpose => $this->lessonPrompt($context, $chunk),
                $compact => $this->compactPrompt($context, $chunk),
                default => $this->prompt($context, $chunk),
            },
            fn (array $json, array $chunk) => $this->parse($json, $chunk),
            fn (array $unit, array $item) => self::problems($unit, $item, $this->checker, $compact, $purpose),
            $progress
        );

        return ['units' => $this->finalise($units, $work)];
    }

    /** The bank question as the model needs to see it to write a hint for it (and never to change it). @return array<string,mixed> */
    private static function practiceItem(array $q, int $level): array
    {
        return [
            'question_id' => $q['id'],
            'level' => $level,
            'form' => $q['form'],
            'question' => mb_substr(trim(strip_tags($q['stem'])), 0, 400),
            'options' => $q['options'],
            'correct_option' => $q['correct_label'],
            'model_answer' => $q['answer_text'] !== '' ? mb_substr($q['answer_text'], 0, 300) : null,
            'stored_explanation' => mb_substr($q['explanation'], 0, 300),
        ];
    }

    /**
     * What is wrong with one unit, as instructions to the model. The validator applies the same rules.
     *
     * @param array<string,mixed> $unit a cleaned unit
     * @param array<string,mixed> $work the work item it was written for
     * @param bool $compact the compact profile (a class that must fit a few pages): tighter limits, see COMPACT_*
     * @param bool $purpose the purpose profile (a lesson in a class that reteaches only some concepts): see LESSON_*
     * @return array<int,string>
     */
    public static function problems(array $unit, array $work, ?InteractionPlanner $checker = null, bool $compact = false, bool $purpose = false): array
    {
        $out = [];

        $explanation = $compact ? self::COMPACT_EXPLANATION_WORDS : ($purpose ? self::LESSON_EXPLANATION_WORDS : self::EXPLANATION_WORDS);
        $w = self::words($unit['simple_explanation']);
        if ($w < $explanation[0] || $w > $explanation[1]) {
            $out[] = 'The simple explanation has ' . $w . ' words; it must be ' . $explanation[0] . ' to ' . $explanation[1] . ', in plain words.';
        }
        if ($compact) {
            if (count($unit['steps']) !== 3) {
                $out[] = 'There must be exactly 3 steps; there are ' . count($unit['steps']) . '.';
            }
            foreach ($unit['steps'] as $step) {
                if (self::words($step['label']) > self::COMPACT_STEP_LABEL_WORDS || self::words($step['text']) > self::COMPACT_STEP_TEXT_WORDS) {
                    $out[] = 'A step is too long: the label is at most ' . self::COMPACT_STEP_LABEL_WORDS . ' words and the text at most ' . self::COMPACT_STEP_TEXT_WORDS . ' ("' . mb_substr($step['label'], 0, 30) . '").';
                    break;
                }
            }
            if (count($unit['mistakes']) > 1) {
                $out[] = 'Give at most one mistake.';
            }
            foreach ($unit['mistakes'] as $m) {
                if (self::words($m['wrong_idea']) > self::COMPACT_MISTAKE_WORDS || self::words($m['why_wrong']) > self::COMPACT_MISTAKE_WORDS + 6 || self::words($m['correct_idea']) > self::COMPACT_MISTAKE_WORDS) {
                    $out[] = 'The mistake and what is true instead are at most ' . self::COMPACT_MISTAKE_WORDS . ' words each (why it does not hold, at most ' . (self::COMPACT_MISTAKE_WORDS + 6) . ').';
                    break;
                }
            }
        }

        if ($purpose) {
            [$stepMin, $stepMax] = self::LESSON_STEPS;
            if (count($unit['steps']) < $stepMin || count($unit['steps']) > $stepMax) {
                $out[] = 'There must be ' . $stepMin . ' to ' . $stepMax . ' steps; there are ' . count($unit['steps']) . '.';
            }
            foreach ($unit['steps'] as $step) {
                if (self::words($step['text']) > self::LESSON_STEP_TEXT_WORDS) {
                    $out[] = 'A step is over ' . self::LESSON_STEP_TEXT_WORDS . ' words ("' . mb_substr($step['label'], 0, 30) . '"); one small thing to understand per step.';
                    break;
                }
            }
            if ($unit['real_life'] !== null && self::words($unit['real_life']) > self::LESSON_REAL_LIFE_WORDS) {
                $out[] = 'The real-life picture is over ' . self::LESSON_REAL_LIFE_WORDS . ' words.';
            }
            if ($unit['worked_example'] === null) {
                $out[] = 'The worked example is required: reason step by step through one situation from the chapter text for this concept.';
            }
        }

        if ($checker !== null) {
            [, $bad] = $checker->check(
                [0 => ['interaction' => ['kind' => 'steps', 'intro' => 'Open each step in turn.', 'items' => $unit['steps'], 'wrapup' => ''], 'reason' => 'the idea built up in small steps']],
                [0 => ['_anchors' => [], 'scenario_allowed' => false, 'slide_type' => 'unit']]
            );
            foreach ($bad[0] ?? [] as $issue) {
                $out[] = 'Steps: ' . $issue;
            }
        } elseif (count($unit['steps']) < self::STEPS[0] || count($unit['steps']) > self::STEPS[1]) {
            $out[] = 'There must be ' . self::STEPS[0] . ' to ' . self::STEPS[1] . ' steps.';
        }

        if ($work['misconceptions'] !== [] && $unit['mistakes'] === []) {
            $out[] = 'This concept has listed misconceptions; write one or two as "mistakes".';
        }
        foreach ($unit['mistakes'] as $m) {
            if (!self::traces($m['wrong_idea'], array_merge(array_column($work['misconceptions'], 'wrong_idea'), array_column($work['misconceptions'], 'what_learners_think')))) {
                $out[] = 'The mistake "' . mb_substr($m['wrong_idea'], 0, 40) . '..." is not one of the listed misconceptions; use a listed one (same idea) or leave it out.';
            }
            if (self::words($m['why_wrong']) < 4 || self::words($m['correct_idea']) < 4) {
                $out[] = 'A mistake needs a "why_wrong" and a "correct_idea" of at least 4 words each.';
            }
        }

        $winWords = $compact ? self::COMPACT_WIN_WORDS : ($purpose ? self::LESSON_WIN_WORDS : self::WIN_WORDS);
        $win = self::words($unit['win']);
        if ($win < $winWords[0] || $win > $winWords[1]) {
            $out[] = 'The win has ' . $win . ' words; it must be ' . $winWords[0] . ' to ' . $winWords[1] . ' and say what the learner can now do.';
        }

        $byId = [];
        foreach ($unit['practice'] as $p) {
            $byId[$p['question_id']] = $p;
        }
        // A compact class prints the bank's questions without hints, so there is no hint to ask for or to check.
        foreach ($compact ? [] : $work['practice'] as $q) {
            $p = $byId[$q['question_id']] ?? null;
            if ($p === null || self::words($p['hint']) < 3) {
                $out[] = 'Write a hint (3 to ' . self::HINT_WORDS . ' words) for practice question ' . $q['question_id'] . '.';
                continue;
            }
            if (self::words($p['hint']) > self::HINT_WORDS) {
                $out[] = 'The hint for question ' . $q['question_id'] . ' is over ' . self::HINT_WORDS . ' words.';
            }
            $answer = trim((string) ($q['model_answer'] ?? ''));
            if ($answer !== '' && mb_strlen($answer) >= 4 && mb_stripos($p['hint'], $answer) !== false) {
                $out[] = 'The hint for question ' . $q['question_id'] . ' gives the answer away; point the way without stating it.';
            }
        }

        if ($unit['worked_example'] !== null) {
            $we = $unit['worked_example'];
            if ($purpose) {
                [$min, $max] = self::LESSON_EXAMPLE_STEPS;
                $limit = self::LESSON_EXAMPLE_WORDS;
                if (count($we['steps']) < $min || count($we['steps']) > $max || self::words($we['problem']) < 3 || self::words($we['answer']) < 3) {
                    $out[] = 'The worked example needs a problem, ' . $min . ' to ' . $max . ' steps and an answer of at least 3 words.';
                }
                if (self::words($we['problem']) > $limit['problem'] || self::words($we['answer']) > $limit['answer']) {
                    $out[] = 'In the worked example the problem is at most ' . $limit['problem'] . ' words and the answer at most ' . $limit['answer'] . '.';
                }
                foreach ($we['steps'] as $st) {
                    if (self::words($st['why']) < 3) {
                        $out[] = 'Every step of the worked example needs a reason ("why") of at least 3 words.';
                        break;
                    }
                    if (self::words($st['text']) > $limit['step'] || self::words($st['why']) > $limit['why']) {
                        $out[] = 'A step of the worked example is too long: the step is at most ' . $limit['step'] . ' words and its reason at most ' . $limit['why'] . '.';
                        break;
                    }
                }
            } elseif (count($we['steps']) < 2 || count($we['steps']) > 6 || self::words($we['problem']) < 3 || self::words($we['answer']) < 1) {
                $out[] = 'The worked example needs a problem, 2 to 6 steps and an answer, or must be null.';
            }
        }

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $chunk work items by concept id
     */
    private function prompt(array $context, array $chunk): string
    {
        $input = json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        [$expMin, $expMax] = self::EXPLANATION_WORDS;
        [$stepMin, $stepMax] = self::STEPS;
        [$winMin, $winMax] = self::WIN_WORDS;
        $hint = self::HINT_WORDS;
        $ground = self::GROUND_RULES;

        return <<<PROMPT
Write a remedial class unit for EVERY concept listed under CONCEPTS TO WRITE. The learners are those who found this chapter hard to follow: slow learners, learners with gaps, learners who need it again after the regular lesson. A remedial unit does NOT repeat the lesson. It rebuilds the one idea in smaller steps and plainer words than the textbook, then gives practice that builds confidence.

Rules
{$ground}
- Reading level: simpler than the chapter text. Short sentences (about 12 words on average), everyday words, one idea at a time. Keep the technical term and explain it the first time it appears.
- "prerequisites": for each listed prerequisite, one sentence (at most 25 words) that refreshes it using the chapter text, as {"concept_id": id, "refresher": "..."}. [] when there are none.
- "simple_explanation": the idea in plain words, {$expMin} to {$expMax} words. Say what it is and why it matters. No new facts.
- "steps": {$stepMin} to {$stepMax} small steps that build the idea up in order, each {"label": "1 to 8 words", "text": "5 to 35 words"}. One thing to understand per step. Labels differ from each other.
- "real_life": a familiar situation that makes the idea concrete, at most 50 words, taken from the chapter's own examples or the listed real-world uses. You may use an everyday comparison ("Think of it like ...") to help, but it must not add a fact, number or name the chapter does not contain. null if nothing fits.
- "worked_example": ONLY when the chapter text has an example or numbers for this concept: {"problem": "...", "steps": [{"text": "what to do", "why": "why we do it"}], "answer": "..."} with 2 to 6 steps. null otherwise. Never invent numbers.
- "mistakes": one or two common mistakes, ONLY from the concept's listed misconceptions: {"wrong_idea": "the mistaken idea (same idea as listed)", "why_wrong": "why it does not hold, at least 4 words", "correct_idea": "what is true instead, at least 4 words"}. [] when none are listed.
- "practice": one entry for EVERY question listed under that concept's "practice", with the same "question_id": {"question_id": id, "hint": "...", "not_options": {"A": "..."}}. The hint is 3 to {$hint} words and helps the learner think the question through WITHOUT stating the answer. Level 1 questions get the most help (a first step to take); level 3 questions get the lightest nudge. For a multiple-choice question, "not_options" gives, for EACH option that is not the correct one, a reason of at most 25 words why it does not fit, using the chapter text; leave it {} for any other kind of question. Never change the question or its options.
- "win": one sentence of {$winMin} to {$winMax} words saying what the learner can now do, starting "You can now". Specific, warm and calm.
- "diagram" and "diagram_notes": ONLY where a picture of the parts, steps or kinds the chapter lists would make the idea clearer; most units have none (null and []). A diagram is {"layout": "flow" | "compare" | "hub", "title": "...", ...}: flow has "nodes": 2 to 6 short labels in order; hub has "center" and "nodes": 2 to 6 labels; compare has "left" and "right", each {"heading": "...", "items": [1 to 5 labels]}. Every label uses words from the chapter text and is at most 60 characters. "diagram_notes" has one entry for EVERY label drawn: [{"label": "the label exactly", "text": "5 to 35 words saying what it is"}]. At most one unit in three has a diagram.
- "bloom" is one of remember, understand, apply, analyze, evaluate, create. "dok" is 1 to 4. "minutes" is whole minutes the unit takes.

Reply with:
{"units": [{"concept_id": 0, "prerequisites": [{"concept_id": 0, "refresher": ""}], "simple_explanation": "", "steps": [{"label": "", "text": ""}], "real_life": null, "worked_example": null, "mistakes": [{"wrong_idea": "", "why_wrong": "", "correct_idea": ""}], "practice": [{"question_id": 0, "hint": "", "not_options": {}}], "win": "You can now ...", "diagram": null, "diagram_notes": [], "bloom": "understand", "dok": 2, "minutes": 8}]}

CONCEPTS TO WRITE
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    /**
     * @param array<string,mixed> $json
     * @param array<int,array<string,mixed>> $chunk
     * @return array<int,array<string,mixed>>
     */
    private function parse(array $json, array $chunk): array
    {
        $out = [];
        foreach ((array) ($json['units'] ?? []) as $u) {
            $id = (int) ($u['concept_id'] ?? 0);
            if (!is_array($u) || !isset($chunk[$id]) || $this->str($u['simple_explanation'] ?? '') === '') {
                continue;
            }
            $unit = $this->clean($u, $chunk[$id]);
            $out[$id] = $this->compact ? $this->compactOnly($unit) : ($this->purpose ? $this->lessonOnly($unit) : $unit);
        }

        return $out;
    }

    /**
     * A compact unit has no worked example, real-life picture, refresher or practice hints, three steps and at most one
     * mistake. Whatever the model sends beyond that is dropped here, so the rest of the pipeline never sees it.
     *
     * @param array<string,mixed> $unit a cleaned unit
     * @return array<string,mixed>
     */
    private function compactOnly(array $unit): array
    {
        $unit['prerequisites'] = [];
        $unit['real_life'] = null;
        $unit['worked_example'] = null;
        $unit['practice'] = [];
        $unit['steps'] = array_slice($unit['steps'], 0, 3);
        $unit['mistakes'] = array_slice($unit['mistakes'], 0, 1);

        return $unit;
    }

    /**
     * The compact profile's prompt: the same structured fields, tighter, and fewer of them. The class is printed on a few
     * pages, so each unit is a card a struggling learner can take in at a glance.
     *
     * @param array<int,array<string,mixed>> $chunk work items by concept id
     */
    private function compactPrompt(array $context, array $chunk): string
    {
        $input = json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        [$expMin, $expMax] = self::COMPACT_EXPLANATION_WORDS;
        [$winMin, $winMax] = self::COMPACT_WIN_WORDS;
        $label = self::COMPACT_STEP_LABEL_WORDS;
        $text = self::COMPACT_STEP_TEXT_WORDS;
        $mistake = self::COMPACT_MISTAKE_WORDS;
        $total = count($chunk);
        $ground = self::GROUND_RULES;

        return <<<PROMPT
Write a COMPACT remedial class unit for EVERY concept listed under CONCEPTS TO WRITE. The learners found this chapter hard to follow. The whole class is printed on five pages, so each unit is a small card a learner can take in at a glance: it does NOT repeat the lesson, it rebuilds the one idea in plain words and three small steps.

Rules
{$ground}
- Reading level: simpler than the chapter text. Short sentences (about 10 words on average), everyday words, one idea at a time. Keep the technical term and explain it the first time it appears.
- "simple_explanation": the idea in plain words, {$expMin} to {$expMax} words. Say what it is and why it matters. No new facts.
- "steps": EXACTLY 3 small steps that build the idea up in order, each {"label": "at most {$label} words", "text": "5 to {$text} words"}. Very short: the card prints only the text, one line per step. One thing to understand per step. Labels differ from each other.
- "mistakes": at most ONE common mistake, ONLY from the concept's listed misconceptions: {"wrong_idea": "the mistaken idea (same idea as listed)", "why_wrong": "why it does not hold", "correct_idea": "what is true instead"}; "wrong_idea" and "correct_idea" at most {$mistake} words each, "why_wrong" at most 15, all at least 4. [] when none are listed.
- "win": one sentence of {$winMin} to {$winMax} words saying what the learner can now do, starting "You can now". Specific, warm and calm.
- "diagram" and "diagram_notes": at most ONE diagram in this whole reply, and only where a picture of the parts, steps or kinds the chapter lists would make the idea clearer; most units have none (null and []). A diagram is {"layout": "flow" | "compare" | "hub", "title": "...", ...}: flow has "nodes": 3 to 6 short labels in order; hub has "center" and "nodes": 3 to 6 labels; compare has "left" and "right", each {"heading": "...", "items": [2 to 5 labels]}. Every label uses words from the chapter text and is at most 40 characters. "diagram_notes" has one entry for EVERY label drawn: [{"label": "the label exactly", "text": "5 to 25 words saying what it is"}].
- "bloom" is one of remember, understand, apply, analyze, evaluate, create. "dok" is 1 to 4. "minutes" is whole minutes the unit takes.

Reply with:
{"units": [{"concept_id": 0, "simple_explanation": "", "steps": [{"label": "", "text": ""}], "mistakes": [{"wrong_idea": "", "why_wrong": "", "correct_idea": ""}], "win": "You can now ...", "diagram": null, "diagram_notes": [], "bloom": "understand", "dok": 2, "minutes": 4}]}

THIS REPLY HAS {$total} UNITS. CONCEPTS TO WRITE
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    /**
     * A lesson has no "mistakes" (the clinic holds the misconceptions) and no more than its steps. Whatever the model sends
     * beyond that is dropped here, so the rest of the pipeline never sees it.
     *
     * @param array<string,mixed> $unit a cleaned unit
     * @return array<string,mixed>
     */
    private function lessonOnly(array $unit): array
    {
        $unit['mistakes'] = [];
        $unit['steps'] = array_slice($unit['steps'], 0, self::LESSON_STEPS[1]);
        if ($unit['worked_example'] !== null) {
            $unit['worked_example']['steps'] = array_slice($unit['worked_example']['steps'], 0, self::LESSON_EXAMPLE_STEPS[1]);
        }

        return $unit;
    }

    /**
     * The purpose profile's prompt: a lesson that TEACHES the idea again, not a card that restates it.
     *
     * @param array<int,array<string,mixed>> $chunk work items by concept id
     */
    private function lessonPrompt(array $context, array $chunk): string
    {
        $input = json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        [$expMin, $expMax] = self::LESSON_EXPLANATION_WORDS;
        [$stepMin, $stepMax] = self::LESSON_STEPS;
        [$exMin, $exMax] = self::LESSON_EXAMPLE_STEPS;
        [$winMin, $winMax] = self::LESSON_WIN_WORDS;
        $stepWords = self::LESSON_STEP_TEXT_WORDS;
        $real = self::LESSON_REAL_LIFE_WORDS;
        $problem = self::LESSON_EXAMPLE_WORDS['problem'];
        $exStep = self::LESSON_EXAMPLE_WORDS['step'];
        $exWhy = self::LESSON_EXAMPLE_WORDS['why'];
        $exAnswer = self::LESSON_EXAMPLE_WORDS['answer'];
        $hint = self::HINT_WORDS;
        $ground = self::GROUND_RULES;

        return <<<PROMPT
Write a remedial LESSON for EVERY concept listed under CONCEPTS TO WRITE. The learners have already met this chapter and some of them did not understand these ideas. A lesson TEACHES the idea again from its foundations, in plainer words and smaller steps than the textbook. It does not summarise the chapter, and it is not a list of facts to memorise: the learner should finish it able to explain the idea and to reason about a new case.

Rules
{$ground}
- Reading level: simpler than the chapter text. Short sentences (about 12 words on average), everyday words, one idea at a time. Keep the technical term and explain it the first time it appears. A supportive, calm tone ("Let us look at...", "Notice that..."); never call anything easy, obvious or simple.
- "prerequisites": for each listed prerequisite, one sentence (at most 25 words) that reminds the learner of it using the chapter text, as {"concept_id": id, "refresher": "..."}. [] when there are none.
- "simple_explanation": the idea rebuilt from the beginning, {$expMin} to {$expMax} words: what it is, in everyday words, and why it matters. No new facts.
- "steps": {$stepMin} to {$stepMax} small steps that build the idea up in order, each {"label": "1 to 6 words", "text": "5 to {$stepWords} words"}. One thing to understand per step, and each step uses the one before it. Labels differ from each other.
- "real_life": a familiar situation or comparison that makes the idea concrete, at most {$real} words, taken from the chapter's own examples or the listed real-world uses. "Think of it like ..." is allowed, but it must not add a fact, number or name the chapter does not contain. null if nothing fits.
- "worked_example": REQUIRED. Reason through ONE situation from the chapter text for this concept, step by step, so the learner sees HOW to think about it: {"problem": "the situation or question, at most {$problem} words", "steps": [{$exMin} to {$exMax} of {"text": "what to notice or do, at most {$exStep} words", "why": "the reason for it, at most {$exWhy} words"}], "answer": "what we can conclude, at most {$exAnswer} words"}. Use only situations, examples and numbers that are in the chapter text. Never invent numbers.
- "practice": one entry for EVERY question listed under the concept's "practice", with the same "question_id": {"question_id": id, "hint": "...", "not_options": {"A": "..."}}. The hint is 3 to {$hint} words and helps the learner think the question through WITHOUT stating the answer. Level 1 gets the most help (a first step to take); level 2 gets a lighter nudge. For a multiple-choice question, "not_options" gives, for EACH option that is not the correct one, a reason of at most 25 words why it does not fit, using the chapter text; leave it {} for any other kind of question. Never change the question or its options.
- "win": one sentence of {$winMin} to {$winMax} words saying what the learner can now do, starting "You can now". Specific, warm and calm.
- "diagram" and "diagram_notes": ONLY where a picture of a PROCESS or a sequence of steps that the chapter describes would make the idea clearer, as {"layout": "flow", "title": "...", "nodes": [3 to 6 short labels in order]}. At most one in this whole reply; most lessons have none (null and []). Every label uses words from the chapter text and is at most 40 characters. "diagram_notes" has one entry for EVERY label drawn: [{"label": "the label exactly", "text": "5 to 25 words saying what it is"}].
- "bloom" is one of remember, understand, apply, analyze, evaluate, create. "dok" is 1 to 4. "minutes" is whole minutes the lesson takes.

Reply with:
{"units": [{"concept_id": 0, "prerequisites": [{"concept_id": 0, "refresher": ""}], "simple_explanation": "", "steps": [{"label": "", "text": ""}], "real_life": null, "worked_example": {"problem": "", "steps": [{"text": "", "why": ""}], "answer": ""}, "practice": [{"question_id": 0, "hint": "", "not_options": {}}], "win": "You can now ...", "diagram": null, "diagram_notes": [], "bloom": "understand", "dok": 2, "minutes": 10}]}

CONCEPTS TO WRITE
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    /**
     * @param array<string,mixed> $u
     * @param array<string,mixed> $work
     * @return array<string,mixed>
     */
    private function clean(array $u, array $work): array
    {
        $required = array_column($work['prerequisites'], 'concept_id');
        $prerequisites = [];
        foreach ((array) ($u['prerequisites'] ?? []) as $p) {
            $id = (int) ($p['concept_id'] ?? 0);
            if (is_array($p) && in_array($id, $required, true) && $this->str($p['refresher'] ?? '') !== '') {
                $prerequisites[$id] = ['concept_id' => $id, 'refresher' => $this->str($p['refresher'])];
            }
        }

        $steps = [];
        foreach ((array) ($u['steps'] ?? []) as $s) {
            if (is_array($s) && $this->str($s['label'] ?? '') !== '' && $this->str($s['text'] ?? '') !== '') {
                $steps[] = ['id' => 'i' . (count($steps) + 1), 'label' => $this->str($s['label']), 'text' => $this->str($s['text'])];
            }
        }

        $example = null;
        $we = $u['worked_example'] ?? null;
        if (is_array($we) && $this->str($we['problem'] ?? '') !== '') {
            $weSteps = [];
            foreach ((array) ($we['steps'] ?? []) as $s) {
                $text = $this->str(is_array($s) ? ($s['text'] ?? '') : $s);
                if ($text !== '') {
                    $weSteps[] = ['text' => $text, 'why' => is_array($s) ? $this->str($s['why'] ?? '') : ''];
                }
            }
            $example = ['problem' => $this->str($we['problem']), 'steps' => $weSteps, 'answer' => $this->str($we['answer'] ?? '')];
        }

        $mistakes = [];
        foreach ((array) ($u['mistakes'] ?? []) as $m) {
            if (is_array($m) && $this->str($m['wrong_idea'] ?? '') !== '') {
                $mistakes[] = ['wrong_idea' => $this->str($m['wrong_idea']), 'why_wrong' => $this->str($m['why_wrong'] ?? ''), 'correct_idea' => $this->str($m['correct_idea'] ?? '')];
            }
        }

        $wanted = array_column($work['practice'], null, 'question_id');
        $practice = [];
        foreach ((array) ($u['practice'] ?? []) as $p) {
            $qid = (int) ($p['question_id'] ?? 0);
            if (!is_array($p) || !isset($wanted[$qid])) {
                continue;
            }
            // Only the options that really are wrong may carry a reason.
            $wrong = array_column(array_filter($wanted[$qid]['options'] ?? [], fn ($o) => (string) $o['label'] !== (string) ($wanted[$qid]['correct_option'] ?? '')), 'label');
            $not = [];
            foreach ((array) ($p['not_options'] ?? []) as $label => $why) {
                $label = (string) $label;
                if (in_array($label, array_map('strval', $wrong), true) && $this->str($why) !== '') {
                    $not[$label] = $this->str($why);
                }
            }
            $practice[$qid] = ['question_id' => $qid, 'hint' => $this->str($p['hint'] ?? ''), 'not_options' => $not];
        }

        [$diagram, $notes] = $this->diagram($u['diagram'] ?? null, $u['diagram_notes'] ?? []);

        return [
            'prerequisites' => array_values($prerequisites),
            'simple_explanation' => $this->str($u['simple_explanation']),
            'steps' => array_slice($steps, 0, 6),
            'real_life' => $this->nullable($u['real_life'] ?? null),
            'worked_example' => $example,
            'mistakes' => array_slice($mistakes, 0, 2),
            'practice' => array_values($practice),
            'win' => $this->str($u['win'] ?? ''),
            'diagram' => $diagram,
            'diagram_notes' => $notes,
            'bloom' => $this->bloom($u['bloom'] ?? ''),
            'dok' => $this->dok($u['dok'] ?? 2),
            'minutes' => $this->minutes($u['minutes'] ?? 8, 8, 40),
        ];
    }

    /**
     * Once the rules have had their say, drop what could not be fixed and is not worth failing a class for: a mistake
     * that is not a listed misconception, and a worked example that is not a real one.
     *
     * @param array<int,array<string,mixed>> $units
     * @param array<int,array<string,mixed>> $work
     * @return array<int,array<string,mixed>>
     */
    private function finalise(array $units, array $work): array
    {
        foreach ($units as $id => $unit) {
            $listed = array_merge(array_column($work[$id]['misconceptions'], 'wrong_idea'), array_column($work[$id]['misconceptions'], 'what_learners_think'));
            $units[$id]['mistakes'] = array_values(array_filter(
                $unit['mistakes'],
                fn ($m) => self::traces($m['wrong_idea'], $listed) && self::words($m['why_wrong']) >= 4 && self::words($m['correct_idea']) >= 4
            ));
            $we = $unit['worked_example'];
            if ($we !== null && (count($we['steps']) < 2 || self::words($we['problem']) < 3 || $we['answer'] === '')) {
                $units[$id]['worked_example'] = null;
            }
            $units[$id]['steps'] = array_slice($unit['steps'], 0, self::STEPS[1]);
        }

        return $units;
    }
}
