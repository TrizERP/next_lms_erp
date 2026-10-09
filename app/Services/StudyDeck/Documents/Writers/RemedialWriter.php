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

    private const SYSTEM = 'You are a patient remedial teacher. You help learners who found a textbook chapter hard to follow, '
        . 'by rebuilding each idea in small, plain steps. You reply with a single JSON object and nothing else. '
        . 'You never add facts that are not in the chapter text.';

    public function __construct(Completer $completer, int $chunkSize = 3, private readonly ?InteractionPlanner $checker = null)
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
                'misconceptions' => array_map(fn ($m) => ['wrong_idea' => $m['wrong_idea'], 'what_learners_think' => $m['statement'], 'correction' => $m['correction']], $c['misconceptions']),
                'prerequisites' => array_values(array_map(
                    fn ($r) => ['concept_id' => $r, 'name' => $map['concepts'][$r]['name'], 'definition' => $map['concepts'][$r]['definition']],
                    array_filter($c['requires'], fn ($r) => isset($map['concepts'][$r]))
                )),
                'practice' => array_map(fn ($q, $i) => self::practiceItem($q, $i + 1), $placement[$id] ?? [], array_keys($placement[$id] ?? [])),
            ];
        }

        $units = $this->inChunks(
            $work,
            self::SYSTEM,
            14000,
            'remedial units',
            fn (array $chunk) => $this->prompt($context, $chunk),
            fn (array $json, array $chunk) => $this->parse($json, $chunk),
            fn (array $unit, array $item) => self::problems($unit, $item, $this->checker),
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
     * @return array<int,string>
     */
    public static function problems(array $unit, array $work, ?InteractionPlanner $checker = null): array
    {
        $out = [];

        $w = self::words($unit['simple_explanation']);
        if ($w < self::EXPLANATION_WORDS[0] || $w > self::EXPLANATION_WORDS[1]) {
            $out[] = 'The simple explanation has ' . $w . ' words; it must be ' . self::EXPLANATION_WORDS[0] . ' to ' . self::EXPLANATION_WORDS[1] . ', in plain words.';
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

        $win = self::words($unit['win']);
        if ($win < self::WIN_WORDS[0] || $win > self::WIN_WORDS[1]) {
            $out[] = 'The win has ' . $win . ' words; it must be ' . self::WIN_WORDS[0] . ' to ' . self::WIN_WORDS[1] . ' and say what the learner can now do.';
        }

        $byId = [];
        foreach ($unit['practice'] as $p) {
            $byId[$p['question_id']] = $p;
        }
        foreach ($work['practice'] as $q) {
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
            if (count($we['steps']) < 2 || count($we['steps']) > 6 || self::words($we['problem']) < 3 || self::words($we['answer']) < 1) {
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
            $out[$id] = $this->clean($u, $chunk[$id]);
        }

        return $out;
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
