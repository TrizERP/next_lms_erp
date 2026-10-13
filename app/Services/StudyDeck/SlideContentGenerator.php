<?php

namespace App\Services\StudyDeck;

use App\Services\StudyDeck\Contracts\Completer;

/**
 * Stage 2 of the two-call flow: Claude writes the wording of every slide in the
 * approved plan.
 *
 * It is asked for STRUCTURED fields, not HTML. SlideHtmlRenderer owns the markup,
 * so the design-system classes, data attributes, escaping and word budget are
 * applied by code and cannot drift with the model's mood.
 *
 * Every concept a slide TEACHES gets its own explanation entry, so no concept is
 * merely mentioned. Bank questions are not written here at all: the plan names
 * question ids and the renderer / student player take the stored stem, options and
 * explanation verbatim. The model writes a quick check only for a slide the plan
 * gave no bank question. It writes NO alt text or caption: those are made from the
 * picture that was actually chosen, after the choice (ImagePlanner).
 *
 * Slides are generated in chunks so one malformed reply costs a few slides, not
 * the deck, and a single repair round fixes slides that break the word budget,
 * lack an explanation or check, or carry an example that only repeats the text.
 */
class SlideContentGenerator
{
    use ExtractsJson;

    private const SYSTEM = 'You write slide text for a classroom teacher. You reply with a single JSON object and nothing else. '
        . 'Every fact, definition, number, name and example comes from the chapter text supplied; you add none of your own. '
        . 'Plain sentence-case language, no emoji, no exclamation marks.';

    /** Word budget for everything on a slide outside the callouts, the validator's 60 less furniture. */
    public const BODY_BUDGET = 54;

    public const EXPLANATION_WORDS = 24;

    public const MIN_EXPLANATION_WORDS = 6;

    public function __construct(private readonly Completer $completer, private readonly int $chunkSize = 8)
    {
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<string,mixed> $plan SlidePlanner::plan()
     * @return array<int,array<string,mixed>> slide number => content
     */
    public function generate(array $context, array $map, array $plan, ?callable $progress = null): array
    {
        $content = [];

        foreach (array_chunk($plan['slides'], $this->chunkSize) as $chunk) {
            $numbers = array_column($chunk, 'n');
            $prompt = $this->prompt($context, $map, $plan, $chunk);
            $got = $this->parse($this->completer->complete(self::SYSTEM, $prompt, 12000), $numbers);

            $missing = array_diff($numbers, array_keys($got));
            if ($missing) {
                $retry = $prompt . "\n\nYour reply was missing slide numbers " . implode(', ', $missing)
                    . ' or had them malformed. Return all of these slides again, complete.';
                $got = $this->parse($this->completer->complete(self::SYSTEM, $retry, 12000), $numbers) + $got;
            }

            $stillMissing = array_diff($numbers, array_keys($got));
            if ($stillMissing) {
                throw new \RuntimeException('No content generated for slide(s) ' . implode(', ', $stillMissing) . '.');
            }

            $content += $got;
            if ($progress) {
                $progress(count($content), count($plan['slides']));
            }
        }

        // One targeted repair round, cheaper and kinder than regenerating the deck,
        // and it keeps every slide that was already fine.
        $bySlide = [];
        foreach ($plan['slides'] as $s) {
            $bySlide[$s['n']] = $s;
        }
        $problems = $this->problems($bySlide, $content);
        if ($problems) {
            foreach (array_chunk($problems, $this->chunkSize, true) as $batch) {
                $slides = array_map(fn ($n) => $bySlide[$n], array_keys($batch));
                $notes = [];
                foreach ($batch as $n => $issues) {
                    $notes[] = "Slide $n: " . implode(' ', $issues) . ' Your current text: ' . json_encode($content[$n], JSON_UNESCAPED_UNICODE);
                }
                $prompt = $this->prompt($context, $map, $plan, $slides)
                    . "\n\nThese slides need fixing. Rewrite each one in full, fixing the problem and keeping what was good:\n" . implode("\n", $notes);
                foreach ($this->parse($this->completer->complete(self::SYSTEM, $prompt, 12000), array_keys($batch)) as $n => $slide) {
                    $content[$n] = $slide;
                }
            }
            if ($progress) {
                $progress(count($content), count($plan['slides']));
            }
        }

        ksort($content);

        return $content;
    }

    /**
     * Slides that would fail DeckValidator: over the word budget, a taught concept
     * without a real explanation, no check, or an example that only repeats the text.
     *
     * @param array<int,array<string,mixed>> $planSlides by slide number
     * @param array<int,array<string,mixed>> $content
     * @return array<int,array<int,string>> slide number => issues
     */
    public function problems(array $planSlides, array $content): array
    {
        $out = [];
        foreach ($content as $n => $c) {
            $plan = $planSlides[$n] ?? null;
            if (!$plan || $plan['slide_type'] === 'cover') {
                continue;
            }

            $words = 2 + str_word_count($c['title'] . ' ' . $c['body'] . ' ' . self::explanationText($c) . ' ' . implode(' ', $c['bullets']))
                + ($plan['visual'] ? 8 : 0);
            if ($words > self::BODY_BUDGET) {
                $out[$n][] = "It has about $words words of body text; the limit is 60 including the title, so shorten the explanations and cut the bullets (aim for 35).";
            }

            $explained = array_column($c['explanations'], 'concept_id');
            foreach ((array) ($plan['taught_concept_ids'] ?? []) as $cid) {
                $entry = array_values(array_filter($c['explanations'], fn ($e) => $e['concept_id'] === (int) $cid))[0] ?? null;
                if (!$entry) {
                    $out[$n][] = "It must explain concept $cid in its own entry of \"explanations\" (a real explanation, not a name).";
                } elseif (str_word_count($entry['text']) < self::MIN_EXPLANATION_WORDS) {
                    $out[$n][] = "The explanation of concept $cid is too thin; say what it is and why it matters.";
                }
            }
            foreach ($explained as $cid) {
                if (!in_array($cid, (array) ($plan['taught_concept_ids'] ?? []), true)) {
                    $out[$n][] = "It explains concept $cid, which this slide does not teach; remove that entry.";
                }
            }

            if (empty($plan['question_ids']) && $c['check'] === null) {
                $out[$n][] = 'It has no discussion prompt. Write one open question for the class to talk about, with a short possible answer.';
            }

            if ($c['example'] !== null && self::overlap($c['example'], self::explanationText($c) . ' ' . $c['body'] . ' ' . implode(' ', $c['bullets'])) >= 0.7) {
                $out[$n][] = 'The example only repeats the explanation. Give a concrete case or a step the text has not already said, or set example to null.';
            }
        }

        return $out;
    }

    /** Share of the example's content words that already appear in the other text, 0 to 1. */
    public static function overlap(string $example, string $other): float
    {
        $words = fn (string $s) => array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)) ?: [],
            fn ($w) => mb_strlen($w) >= 4 && !in_array($w, ['this', 'that', 'with', 'from', 'have', 'been', 'such', 'when', 'then', 'than', 'they', 'them', 'also', 'into', 'each'], true)
        )));
        $e = $words($example);
        if (count($e) < 3) {
            return 0.0;
        }
        $o = $words($other);

        return count(array_intersect($e, $o)) / count($e);
    }

    /** @param array<string,mixed> $c */
    public static function explanationText(array $c): string
    {
        return implode(' ', array_column($c['explanations'], 'text'));
    }

    /** @return array<int,array<string,mixed>> */
    private function parse(string $reply, array $wanted): array
    {
        try {
            $json = $this->extractJson($reply);
        } catch (\RuntimeException) {
            return [];
        }

        $out = [];
        foreach ((array) ($json['slides'] ?? []) as $s) {
            $n = (int) ($s['n'] ?? 0);
            if (in_array($n, $wanted, true) && $this->str($s['title'] ?? '') !== '') {
                $out[$n] = $this->clean($s);
            }
        }

        return $out;
    }

    /** A model sometimes returns a list or object where text was asked for; flatten it rather than crash. */
    private function str(mixed $v): string
    {
        if (is_array($v)) {
            $v = implode(' ', array_map(fn ($x) => $this->str($x), array_values($v)));
        }

        return trim((string) $v);
    }

    /** @return array<string,mixed> */
    private function clean(array $s): array
    {
        $check = $s['check'] ?? null;
        if (is_array($check) && $this->str($check['question'] ?? '') !== '' && $this->str($check['answer'] ?? '') !== '') {
            $check = ['question' => $this->str($check['question']), 'answer' => $this->str($check['answer'])];
        } else {
            $check = null;
        }

        $mis = $s['misconception'] ?? null;
        $mis = is_array($mis) && !empty($mis['wrong_idea']) && !empty($mis['correction'])
            ? ['wrong_idea' => $this->str($mis['wrong_idea']), 'correction' => $this->str($mis['correction'])]
            : null;

        $explanations = [];
        foreach ((array) ($s['explanations'] ?? []) as $e) {
            if (is_array($e) && $this->str($e['text'] ?? '') !== '' && (int) ($e['concept_id'] ?? 0) > 0) {
                $explanations[] = ['concept_id' => (int) $e['concept_id'], 'text' => $this->str($e['text'])];
            }
        }

        $bloom = strtolower($this->str($s['bloom'] ?? 'understand'));

        return [
            'n' => (int) $s['n'],
            'title' => $this->str($s['title']),
            'body' => $this->str($s['body'] ?? ''),
            'explanations' => $explanations,
            'bullets' => array_slice(array_values(array_filter(array_map(fn ($b) => $this->str($b), (array) ($s['bullets'] ?? [])))), 0, 3),
            'example' => ($e = $this->str($s['example'] ?? '')) !== '' ? $e : null,
            'misconception' => $mis,
            'relationship_note' => ($r = $this->str($s['relationship_note'] ?? '')) !== '' ? $r : null,
            'key_idea' => ($k = $this->str($s['key_idea'] ?? '')) !== '' ? $k : null,
            'check' => $check,
            'bloom' => in_array($bloom, ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'], true) ? $bloom : 'understand',
            'dok' => in_array((int) ($s['dok'] ?? 2), [1, 2, 3, 4], true) ? (int) $s['dok'] : 2,
            'minutes' => max(1, (int) ($s['minutes'] ?? 2)),
        ];
    }

    /** @param array<int,array<string,mixed>> $chunk */
    private function prompt(array $context, array $map, array $plan, array $chunk): string
    {
        $outline = array_map(fn ($s) => $s['n'] . '. ' . $s['title'] . ' [' . $s['slide_type'] . ']', $plan['slides']);

        $work = [];
        foreach ($chunk as $s) {
            $taught = [];
            $related = [];
            foreach ($s['concept_ids'] as $cid) {
                $c = $map['concepts'][$cid] ?? null;
                if (!$c) {
                    continue;
                }
                $row = [
                    'id' => $cid, 'name' => $c['name'], 'definition' => $c['definition'],
                    'knowledge' => $c['knowledge'], 'misconceptions' => $c['misconceptions'],
                    'real_world' => $c['real_world'], 'blooms' => $c['blooms'], 'dok' => $c['dok'],
                ];
                if (in_array($cid, $s['taught_concept_ids'] ?? [], true)) {
                    $taught[] = $row;
                } else {
                    $related[] = ['id' => $cid, 'name' => $c['name']];
                }
            }
            $work[] = [
                'n' => $s['n'], 'section' => $s['section'] ?? null, 'slide_type' => $s['slide_type'], 'title_hint' => $s['title'],
                'teaches' => $s['teaches'], 'relationship' => $s['relationship'] ?? null,
                'has_picture' => $s['visual'] !== null,
                'bank_question_attached' => !empty($s['question_ids']),
                'concepts_to_explain' => $taught,
                'related_concepts_not_explained_here' => $related,
            ];
        }

        $input = json_encode($work, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $outlineText = implode("\n", $outline);
        $words = self::EXPLANATION_WORDS;
        $min = self::MIN_EXPLANATION_WORDS;

        return <<<PROMPT
Write the slide text for the slides listed under SLIDES TO WRITE. The whole deck is outlined first so you keep the flow; write only the listed slides.

Rules
- Each slide holds ONE idea. Everything outside the example, misconception, relationship note and check must fit in about 40 words in total, title included. Title at most 8 words, sentence case.
- "explanations": one entry for EACH item in concepts_to_explain, {"concept_id": id, "text": "..."}. The text really explains the concept: what it is and why it matters, in one or two short sentences (at least {$min} words, at most {$words}), using the chapter text's own wording for definitions. A name, a label or a list of terms is not an explanation. Slides with nothing in concepts_to_explain have "explanations": [] and use "body" instead: one short framing line a teacher would say.
- "bullets": at most 3, a few words each, and ONLY if they add something the explanations do not say (a list of parts, a contrast). Otherwise [].
- "example": a concrete case, with a result or a step, taken from the chapter text. It must contain information the explanations and bullets do NOT already state; never rephrase them. If the chapter offers no such case for this slide, set it to null.
- Numbers, names and examples must appear in the chapter text. If the concept data and the chapter text disagree, the chapter text wins.
- A "misconception" is allowed only from the concept's listed misconceptions (same idea, tidied wording): {"wrong_idea": "...", "correction": "..."}. Otherwise null.
- If "relationship" is set, put one sentence in "relationship_note" saying how the two ideas connect.
- "key_idea": the one sentence a student should remember from this slide, in plain words (at most 20 words), taken from the chapter text. Not a repeat of the title. Null on the cover.
- "check" is the DISCUSSION prompt the teacher puts to the class after this slide, with a short possible answer for the teacher: {"question": "...", "answer": "..."}. It is open and makes students think (why, how, what would happen if, which would you choose, how do these connect), and can be answered from what the slide just taught or from the chapter text. It is not a quiz item with a one-word answer, never about "the passage" or "the text", and never about something taught on a later slide. If bank_question_attached is true, set check to null (the stored practice question stands in for it).
- Do not write alt text or captions; pictures are described after they are chosen.
- "bloom" is one of remember, understand, apply, analyze, evaluate, create. "dok" is 1 to 4. "minutes" is whole minutes.
- The cover slide has no question: set its check to null.
- Cover and hook slides: "body" is the one-line promise to the learner. Objectives slides: bullets are the objectives in "you will" form, and they must be things this deck teaches.

Reply with:
{"slides": [{"n": 1, "title": "", "body": "", "explanations": [{"concept_id": 0, "text": ""}], "bullets": [], "example": null, "misconception": null, "relationship_note": null, "key_idea": null, "check": null, "bloom": "understand", "dok": 2, "minutes": 2}]}

DECK OUTLINE
{$outlineText}

SLIDES TO WRITE
{$input}

CHAPTER TEXT (the only source of facts)
{$context['ground_truth']}
PROMPT;
    }
}
