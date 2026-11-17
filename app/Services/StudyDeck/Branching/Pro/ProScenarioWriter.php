<?php

namespace App\Services\StudyDeck\Branching\Pro;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\ExtractsJson;
use App\Services\StudyDeck\Story\StoryActs;
use App\Services\StudyDeck\Story\StoryFigures;
use App\Services\StudyDeck\Story\StorySkeleton;
use App\Services\StudyDeck\Story\StoryTemplate;

/**
 * Writes the words of ONE act of a pro Branching Scenario: a scene to explore, a decision with an answer for each approach, what
 * each answer leads to, and a short closing sequence for the sound path.
 *
 * The model writes TEXT ONLY. The structure (how many slides, which answer opens which slide, where each button goes) is built by
 * code in ProScenarioBuilder, so the model cannot produce a branch that leads nowhere. What it writes is checked here against the
 * chapter (no invented figures, sizes, one sound answer, no quiz wording) and, when a check fails, asked again once with the exact
 * findings.
 */
class ProScenarioWriter
{
    use ExtractsJson;

    /** Word limits per field: [min, max]. The slides are read on a screen, not on paper. */
    public const LIMITS = [
        'situation' => [15, 60],
        'part' => [8, 38],
        'question' => [8, 40],
        'option' => [4, 22],
        'what' => [10, 50],
        'why' => [10, 60],
        'wrapup' => [6, 34],
        'step' => [2, 10],
    ];

    /** @var array<int,string> raw replies, kept so a refusal can be examined */
    public array $replies = [];

    public function __construct(private readonly Completer $completer)
    {
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<string,mixed> $act StoryActs::plan() entry
     * @return array{written:array<string,mixed>,notes:array<int,string>}
     *
     * @throws \RuntimeException when the model's answer cannot be made to pass the checks
     */
    public function write(array $context, array $map, array $act, ?string $method = null): array
    {
        $prompt = $this->prompt($context, $map, $act, $method);
        $errors = [];
        $notes = [];
        $last = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $ask = $attempt === 0 ? $prompt : $prompt . "\n\nYOUR PREVIOUS ANSWER WAS REFUSED for these reasons. Write the whole JSON again and fix every one of them:\n- " . implode("\n- ", $errors);
            $reply = $this->completer->complete(self::system(), $ask, 12000);
            $this->replies[] = $reply;
            try {
                $last = self::normalise($this->extractJson($reply));
            } catch (\RuntimeException $e) {
                $errors = [$e->getMessage()];
                $notes[] = 'Act "' . $act['title'] . '": the reply was not readable (' . mb_substr($e->getMessage(), 0, 80) . ').';
                continue;
            }
            $errors = array_merge(self::check($last), self::grounding($last, $context, $map));
            if ($errors === []) {
                return ['written' => $last, 'notes' => $notes];
            }
            $notes[] = 'Act "' . $act['title'] . '": asked again (' . count($errors) . ' finding' . (count($errors) === 1 ? '' : 's') . ').';
        }

        throw new \RuntimeException('Act "' . $act['title'] . '" could not be written to the rules: ' . implode(' ', array_slice($errors, 0, 4)));
    }

    private static function system(): string
    {
        return 'You write interactive branching-scenario lessons for school students. You reply with one JSON object and nothing else. Every fact comes from the chapter text you are given; you never invent science, measurements or numbers.';
    }

    /**
     * The model's JSON in the shape the rest of the code reads: strings trimmed, the lists as lists, a flag for the sound answer.
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    public static function normalise(array $raw): array
    {
        $s = fn ($v): string => trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : ''));
        $scene = (array) ($raw['scene'] ?? []);
        $decision = (array) ($raw['decision'] ?? []);
        $flow = (array) ($raw['flow'] ?? []);

        return [
            'scene' => [
                'title' => $s($scene['title'] ?? ''),
                'situation' => $s($scene['situation'] ?? ''),
                'intro' => $s($scene['intro'] ?? ''),
                'parts' => array_values(array_map(
                    fn ($p) => ['label' => $s($p['label'] ?? ''), 'text' => $s($p['text'] ?? '')],
                    array_filter((array) ($scene['parts'] ?? []), 'is_array')
                )),
            ],
            'decision' => [
                'title' => $s($decision['title'] ?? ''),
                'kicker' => $s($decision['kicker'] ?? ''),
                'question' => $s($decision['question'] ?? ''),
                'options' => array_values(array_map(
                    fn ($o) => [
                        'text' => $s($o['text'] ?? ''), 'sound' => filter_var($o['sound'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'title' => $s($o['title'] ?? ''), 'what' => $s($o['what'] ?? ''), 'why' => $s($o['why'] ?? ''),
                    ],
                    array_filter((array) ($decision['options'] ?? []), 'is_array')
                )),
            ],
            'flow' => [
                'title' => $s($flow['title'] ?? ''),
                'intro' => $s($flow['intro'] ?? ''),
                'steps' => array_values(array_filter(array_map(fn ($x) => $s(is_array($x) ? ($x['label'] ?? '') : $x), (array) ($flow['steps'] ?? [])), fn ($x) => $x !== '')),
                'wrapup' => $s($flow['wrapup'] ?? ''),
            ],
            'conclusion' => $s($raw['conclusion'] ?? ($flow['wrapup'] ?? '')),
        ];
    }

    /**
     * The rules that need no chapter text: sizes, the one sound answer, wording.
     *
     * @param array<string,mixed> $w ProScenarioWriter::normalise()
     * @return array<int,string>
     */
    public static function check(array $w): array
    {
        $e = [];
        $words = fn (string $t): int => count(preg_split('/\s+/u', trim($t), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $size = function (string $where, string $field, string $text) use (&$e, $words): void {
            [$lo, $hi] = self::LIMITS[$field];
            $n = $words($text);
            if ($n < $lo || $n > $hi) {
                $e[] = "$where must be $lo to $hi words (it is $n).";
            }
        };

        $scene = $w['scene'];
        if ($scene['title'] === '' || $words($scene['title']) > 9) {
            $e[] = 'scene.title is required and at most 9 words.';
        }
        $size('scene.situation', 'situation', $scene['situation']);
        if ($scene['intro'] === '' || $words($scene['intro']) > 24) {
            $e[] = 'scene.intro is required (one short instruction, at most 24 words).';
        }
        $parts = $scene['parts'];
        if (count($parts) < ProScenarioFormat::MIN_SPOTS || count($parts) > 4) {
            $e[] = 'scene.parts must list 2 to 4 parts to explore, 3 is best (it lists ' . count($parts) . ').';
        }
        $labels = [];
        foreach ($parts as $k => $p) {
            $at = 'scene.parts[' . ($k + 1) . ']';
            if ($p['label'] === '' || $words($p['label']) > 4 || mb_strlen($p['label']) > 34) {
                $e[] = "$at.label must be a name of at most 4 words.";
            }
            if (isset($labels[mb_strtolower($p['label'])])) {
                $e[] = "$at.label repeats another part.";
            }
            $labels[mb_strtolower($p['label'])] = true;
            $size("$at.text", 'part', $p['text']);
        }

        $d = $w['decision'];
        if ($d['title'] === '' || $words($d['title']) > 10) {
            $e[] = 'decision.title is required and at most 10 words (for example "A decision for the farm team").';
        }
        if ($d['kicker'] === '' || $words($d['kicker']) > 8) {
            $e[] = 'decision.kicker is required and at most 8 words (for example "Scenario · School farm").';
        }
        $size('decision.question', 'question', $d['question']);
        $options = $d['options'];
        if (count($options) !== 3) {
            $e[] = 'decision.options must hold exactly 3 answers (it holds ' . count($options) . ').';
        }
        $sound = count(array_filter($options, fn ($o) => $o['sound']));
        if (count($options) === 3 && $sound !== 1) {
            $e[] = 'Exactly one of the 3 options must have "sound": true (it is ' . $sound . ').';
        }
        $texts = [];
        foreach ($options as $k => $o) {
            $at = 'decision.options[' . ($k + 1) . ']';
            $size("$at.text", 'option', $o['text']);
            if ($o['title'] === '' || $words($o['title']) > 12) {
                $e[] = "$at.title is required and at most 12 words (a heading for what that answer leads to).";
            }
            $size("$at.what", 'what', $o['what']);
            $size("$at.why", 'why', $o['why']);
            if (preg_match('/^\(?[A-Da-d][).:]\s/', $o['text'])) {
                $e[] = "$at.text must not start with a letter such as A) or B.";
            }
            if (isset($texts[mb_strtolower($o['text'])])) {
                $e[] = "$at.text repeats another answer.";
            }
            $texts[mb_strtolower($o['text'])] = true;
        }
        // No answer may give itself away by a word like never, always, guess; and the answers should look alike in length.
        $tell = array_values(array_filter($options, fn ($o) => StorySkeleton::givesAway($o['text'])));
        if ($tell !== [] && count($tell) < count($options)) {
            $e[] = 'The answer "' . mb_substr($tell[0]['text'], 0, 50) . '" gives itself away with a word like never, always, guess or blindly; write every answer as an approach a sensible student could take, in the same tone.';
        }
        $lengths = array_map(fn ($o) => $words($o['text']), $options);
        if ($lengths !== [] && max($lengths) > 2 * max(1, min($lengths)) + 4) {
            $e[] = 'The answers differ too much in length (the longest is much longer than the shortest); the longest answer must not be the sound one by habit.';
        }
        $whys = array_map(fn ($o) => mb_strtolower($o['why']), $options);
        if (count(array_unique($whys)) !== count($whys)) {
            $e[] = 'Every answer needs its own "why".';
        }

        $f = $w['flow'];
        if ($f['title'] === '' || $words($f['title']) > 10) {
            $e[] = 'flow.title is required and at most 10 words.';
        }
        if ($f['intro'] === '' || $words($f['intro']) > 24) {
            $e[] = 'flow.intro is required (at most 24 words).';
        }
        if (count($f['steps']) < 3 || count($f['steps']) > 5) {
            $e[] = 'flow.steps must hold 3 to 5 steps (it holds ' . count($f['steps']) . ').';
        }
        foreach ($f['steps'] as $k => $st) {
            $size('flow.steps[' . ($k + 1) . ']', 'step', $st);
        }
        $size('flow.wrapup', 'wrapup', $f['wrapup']);

        $all = json_encode($w, JSON_UNESCAPED_UNICODE) ?: '';
        if (preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $all)) {
            $e[] = 'The text contains emoji.';
        }
        if (preg_match('/\b(?:which of the following|true or false|choose the correct|select the correct)\b/i', $all)) {
            $e[] = 'The decision reads like a quiz question; a decision is something the student does in the situation.';
        }

        return $e;
    }

    /**
     * No figure the chapter does not contain, and every part label drawn from the chapter's own words.
     *
     * @param array<string,mixed> $w
     * @return array<int,string>
     */
    public static function grounding(array $w, array $context, array $map): array
    {
        $allowed = StoryFigures::allowed($context, $map);
        $e = [];
        $texts = [
            'The scene' => $w['scene']['title'] . ' ' . $w['scene']['situation'] . ' ' . $w['scene']['intro'] . ' ' . implode(' ', array_map(fn ($p) => $p['label'] . ' ' . $p['text'], $w['scene']['parts'])),
            'The decision' => $w['decision']['title'] . ' ' . $w['decision']['question'],
            'The closing sequence' => $w['flow']['title'] . ' ' . $w['flow']['intro'] . ' ' . implode(' ', $w['flow']['steps']) . ' ' . $w['flow']['wrapup'],
        ];
        foreach ($w['decision']['options'] as $k => $o) {
            $texts['Answer ' . ($k + 1)] = $o['text'] . ' ' . $o['title'] . ' ' . $o['what'] . ' ' . $o['why'];
        }
        foreach ($texts as $where => $text) {
            array_push($e, ...StoryFigures::errors($where, $text, $allowed));
        }

        return $e;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<string,mixed> $act
     */
    private function prompt(array $context, array $map, array $act, ?string $method): string
    {
        $mission = $act['kind'] === StoryActs::MISSION;
        $ids = $mission ? $act['required_ids'] : $act['concept_ids'];
        $concepts = [];
        foreach ($ids as $id) {
            $c = $map['concepts'][$id] ?? null;
            if ($c) {
                $concepts[] = ['name' => $c['name'], 'definition' => $c['definition'] ?? '', 'common_wrong_ideas' => array_values(array_filter(array_column((array) ($c['misconceptions'] ?? []), 'wrong_idea')))];
            }
        }
        $input = json_encode(['topic' => $act['title'], 'about' => $act['description'] ?? '', 'concepts_to_teach' => $concepts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $methodLine = $method !== null && ($m = StoryTemplate::methodRule($method)) !== null ? "\n- TEACHING METHOD: $m" : '';
        $scope = $mission
            ? 'This is the FINAL MISSION: one realistic situation that needs several of the ideas below working together. Apply the chapter; do not introduce new ideas.'
            : 'This lesson part is about the topic below. Every idea listed in concepts_to_teach must be needed to understand the situation, the answers or the explanations.';
        [$sit0, $sit1] = self::LIMITS['situation'];
        [$p0, $p1] = self::LIMITS['part'];
        [$o0, $o1] = self::LIMITS['option'];
        [$w0, $w1] = self::LIMITS['what'];
        [$y0, $y1] = self::LIMITS['why'];

        return <<<PROMPT
Write ONE part of an interactive branching scenario for a school student (about 13 to 14 years old). The student explores a real situation, makes a decision, and finds out what that choice leads to. The structure is fixed; you write only the words.

{$scope}

What the student does, in order:
1. EXPLORE: a picture of the situation with 3 or 4 parts they can open ("scene"). Each part explains one thing that matters for the decision.
2. DECIDE: a question about what to do, with exactly 3 answers ("decision"). Exactly ONE answer is the sound one; the other two are things a sensible student might really do but that are weaker, with a real reason why.
3. SEE THE CONSEQUENCE: each answer opens its own screen saying what happens and why. Nobody is told "wrong"; a weaker answer shows what it misses.
4. WATCH: a short sequence of 3 to 5 steps that tells how the sound path works ("flow").

Rules
- The situation is concrete and told to "you" (the student or a person in the story), in the present tense: who, where, what has to be decided. Not a list of facts. {$sit0} to {$sit1} words.
- "scene.title" is a short heading for the situation (not "Scene 1"). "scene.intro" is one short instruction such as "Select a marker to see what matters." Each part: "label" (a name of at most 4 words, taken from the chapter's own vocabulary) and "text" ({$p0} to {$p1} words, what it is and why it matters here).
- "decision.title" is like "A decision for the school farm team". "decision.kicker" is like "Scenario · School farm". "decision.question": what should be done, as something a person does in the situation. It is NOT a quiz question (never "which of the following"). 
- Each answer: "text" ({$o0} to {$o1} words, what the person does; no letter in front); "sound": true for exactly one; "title": a short heading for its consequence screen (at most 12 words, different for each answer); "what": what happens as a result ({$w0} to {$w1} words); "why": the science behind it ({$y0} to {$y1} words, different for each answer, taken from the chapter). All three answers have a similar length and tone; no giveaway words such as never, always, just, guess, blindly. Put the sound answer in ANY position.
- "flow": "title", "intro" (what the student is about to watch), "steps" (3 to 5 short labels, in order, of how the sound path works), "wrapup" (one sentence: what the student now understands). 
- "conclusion": one sentence naming what the student now knows after this part.
- LANGUAGE: write every word the student reads in the language of the CHAPTER TEXT below (a Hindi chapter is written in Hindi, an English chapter in English), in the same script. Only the JSON keys stay in English.
- Every fact, term and number comes from the CHAPTER TEXT or the concept definitions. Invent no science, measurements or results. Write no number the chapter text does not contain (say "many", "a few" instead). No emoji.{$methodLine}

Reply with this JSON and nothing else:
{
  "scene": {"title": "...", "situation": "...", "intro": "...", "parts": [{"label": "...", "text": "..."}]},
  "decision": {"title": "...", "kicker": "...", "question": "...", "options": [{"text": "...", "sound": false, "title": "...", "what": "...", "why": "..."}]},
  "flow": {"title": "...", "intro": "...", "steps": ["..."], "wrapup": "..."},
  "conclusion": "..."
}

PART DATA
{$input}

CHAPTER TEXT (the only source of facts)
{$context['ground_truth']}
PROMPT;
    }
}
