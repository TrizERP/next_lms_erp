<?php

namespace App\Services\StudyDeck;

use App\Services\StudyDeck\Contracts\Completer;

/**
 * Stage 3 of the study-deck flow: decides, concept by concept, whether a slide earns an
 * INTERACTION and writes it.
 *
 * EVERY learning slide is asked for one. The kinds, all played by the student player natively (no H5P row,
 * no H5P runtime), are chosen by what the slide IS:
 *   hotspots  explore the labelled parts of a drawn diagram; each opens a short explanation
 *   scenario  a lightweight branching scenario: a real situation, a decision, its consequence,
 *             and why - then the next decision or a conclusion
 *   reveal    click-to-reveal cards: a set of ideas, each explained when opened
 *   steps     a process whose steps are opened one by one
 *   timeline  dated events opened one by one (only where the chapter really has dates)
 *   compare   two or three things set side by side, each explained, then how they compare
 *   match     pair terms with their meanings
 *   order     put a real sequence in order
 *
 * The model chooses the kind and must say why. A slide may still come back with none, and says why: the
 * rule is that an interaction must teach, never that every slide gets a token one. The validator counts the
 * slides left without one.
 *
 * Everything it writes is checked mechanically (shape, word budgets, labels that really exist on the
 * drawn diagram, a scenario graph that is acyclic and fully reachable), given one repair round, and
 * DROPPED if still wrong. A bad interaction therefore costs a slide its extra, never the deck.
 * Wording is checked against the chapter text by DeckValidator like every other word on a slide.
 */
class InteractionPlanner
{
    use ExtractsJson;

    public const KINDS = ['hotspots', 'scenario', 'reveal', 'steps', 'timeline', 'compare', 'match', 'order'];

    /** Kinds that are a list of items opened one at a time. */
    public const ITEM_KINDS = ['reveal', 'steps', 'timeline', 'compare'];

    /** Most of a kind a deck may carry; kinds not listed are uncapped. */
    public const CAPS = ['scenario' => 3, 'match' => 2, 'order' => 2, 'timeline' => 2];

    /** A decision with consequences needs a situation. Other slide types have nothing to decide. */
    public const SCENARIO_SLIDES = ['scenario', 'application', 'worked_example', 'misconception', 'challenge'];

    public const MAX_SCENARIOS = 3;

    private const SYSTEM = 'You are an experienced classroom teacher deciding where a lesson slide should become interactive. '
        . 'You reply with a single JSON object and nothing else. Every fact, number, name and example comes from the chapter text supplied; you add none of your own. '
        . 'Plain sentence-case language addressed to the student as "you", no emoji, no exclamation marks.';

    /** @var array<int,string> raw replies, kept for inspection */
    public array $replies = [];

    public function __construct(private readonly Completer $completer, private readonly int $chunkSize = 12)
    {
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<string,mixed> $plan SlidePlanner::plan()
     * @param array<int,array<string,mixed>> $content SlideContentGenerator::generate()
     * @param array<int,array<string,mixed>> $images ImagePlanner::plan()
     * @return array<int,array{interaction:?array<string,mixed>,reason:string}> slide number => decision
     */
    public function plan(array $context, array $map, array $plan, array $content, array $images, ?callable $progress = null): array
    {
        $slides = [];
        $out = [];
        foreach ($plan['slides'] as $s) {
            $out[$s['n']] = ['interaction' => null, 'reason' => 'left to plain teaching'];
            if ($s['slide_type'] === 'cover') {
                $out[$s['n']]['reason'] = 'the title slide only opens the chapter';
                continue;
            }
            $slides[$s['n']] = $this->candidate($s, $content[$s['n']] ?? [], $images[$s['n']] ?? null, $map);
        }

        foreach (array_chunk($slides, $this->chunkSize, true) as $chunk) {
            $prompt = $this->prompt($context, $plan, $chunk);
            $got = $this->parse($this->ask($prompt), $chunk);

            [$ok, $bad] = $this->check($got, $chunk);
            foreach ($ok as $n => $d) {
                $out[$n] = $d;
            }

            if ($bad) {
                $notes = [];
                foreach ($bad as $n => $issues) {
                    $notes[] = "Slide $n: " . implode(' ', $issues);
                }
                $retry = $this->prompt($context, $plan, array_intersect_key($chunk, $bad))
                    . "\n\nThese interactions were rejected. For each slide below, return a corrected interaction, or null with a reason if it cannot be fixed within the rules:\n"
                    . implode("\n", $notes);
                [$fixed, $still] = $this->check($this->parse($this->ask($retry), array_intersect_key($chunk, $bad)), array_intersect_key($chunk, $bad));
                foreach ($fixed as $n => $d) {
                    $out[$n] = $d;
                }
                // Still wrong, or not answered at all: the slide teaches plainly, and the reason says why.
                foreach (array_diff_key($bad, $fixed) as $n => $issues) {
                    $issues = $still[$n] ?? $issues;
                    $out[$n] = ['interaction' => null, 'reason' => 'an interaction was drafted but dropped: ' . implode(' ', array_slice($issues, 0, 2))];
                }
            }

            if ($progress) {
                $progress(count(array_filter($out, fn ($d) => $d['interaction'] !== null)), count($out));
            }
        }

        // Caps first: a deck may carry only so many scenarios, matches and the like, and a slide trimmed by a cap is a
        // slide still waiting for an interaction of a kind that is not capped.
        $out = $this->capped($out, $plan);

        // Every learning slide is meant to have one. A slide that came back plain (or whose draft was dropped or trimmed)
        // gets one more chance, with the instruction made firmer; if it still cannot, it stays plain and says why.
        $missing = array_filter($slides, fn ($c, $n) => ($out[$n]['interaction'] ?? null) === null, ARRAY_FILTER_USE_BOTH);
        foreach (array_chunk($missing, $this->chunkSize, true) as $chunk) {
            $retry = $this->prompt($context, $plan, $chunk)
                . "\n\nThese slides came back with no interaction. Every learning slide needs one that teaches, so give each the closest fit now: "
                . '"reveal" (cards for the ideas on the slide), "compare" (two things set side by side), "steps" (a process), or "hotspots" (only with a diagram). '
                . 'The deck already has all the scenarios, matches, orders and timelines it may carry, so do not use those kinds here. '
                . 'Answer null only if a slide truly has nothing to explore, and then give a specific reason.';
            [$ok] = $this->check($this->parse($this->ask($retry), $chunk), $chunk);
            foreach ($ok as $n => $d) {
                if ($d['interaction'] !== null) {
                    $out[$n] = $d;
                }
            }
        }

        return $out;
    }

    /** The slide as the model sees it. @return array<string,mixed> */
    private function candidate(array $slide, array $c, ?array $image, array $map): array
    {
        $concepts = [];
        foreach ($slide['taught_concept_ids'] as $id) {
            $k = $map['concepts'][$id] ?? null;
            if ($k) {
                $concepts[] = [
                    'name' => $k['name'], 'definition' => $k['definition'], 'difficulty' => $k['difficulty'],
                    'blooms' => $k['blooms'], 'dok' => $k['dok'], 'misconceptions' => array_column($k['misconceptions'], 'wrong_idea'),
                    'real_world' => $k['real_world'],
                ];
            }
        }

        $anchors = [];
        if (($image['type'] ?? '') === 'diagram' && is_array($slide['diagram'] ?? null)) {
            $anchors = DiagramRenderer::anchors($slide['diagram']);
        }

        return [
            'n' => $slide['n'],
            'slide_type' => $slide['slide_type'],
            'title' => $c['title'] ?? $slide['title'],
            'teaches' => $slide['teaches'],
            'concepts_taught' => $concepts,
            'explanation' => trim(SlideContentGenerator::explanationText($c) . ' ' . ($c['body'] ?? '')),
            'example' => $c['example'] ?? null,
            'bullets' => $c['bullets'] ?? [],
            'relationship' => $slide['relationship'] ?? null,
            'has_worked_example_or_mistake_on_a_later_screen' => !empty($c['example']) || !empty($c['misconception']),
            'diagram' => $anchors ? ['title' => $slide['diagram']['title'], 'labels_you_may_use' => array_column($anchors, 'label')] : null,
            'scenario_allowed' => in_array($slide['slide_type'], self::SCENARIO_SLIDES, true),
            '_anchors' => $anchors,
        ];
    }

    private function ask(string $prompt): string
    {
        return $this->replies[] = $this->completer->complete(self::SYSTEM, $prompt, 12000);
    }

    /** @return array<int,array<string,mixed>> slide number => raw decision */
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
            if (isset($wanted[$n]) && is_array($s)) {
                $out[$n] = $s;
            }
        }

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $got
     * @param array<int,array<string,mixed>> $chunk
     * @return array{0:array<int,array{interaction:?array,reason:string}>,1:array<int,array<int,string>>} accepted, rejected-with-reasons
     */
    private function check(array $got, array $chunk): array
    {
        $ok = [];
        $bad = [];

        foreach ($got as $n => $s) {
            $raw = $s['interaction'] ?? null;
            $reason = $this->text($s['reason'] ?? ($raw['reason'] ?? ''));
            if (!is_array($raw) || $raw === []) {
                $ok[$n] = ['interaction' => null, 'reason' => $reason !== '' ? $reason : 'plain teaching is enough for this slide'];
                continue;
            }

            $clean = match ($raw['kind'] ?? '') {
                'hotspots' => $this->hotspots($raw, $chunk[$n], $errors),
                'scenario' => $this->scenario($raw, $chunk[$n], $errors),
                'reveal', 'steps', 'timeline', 'compare' => $this->items($raw, $errors),
                'match' => $this->match($raw, $errors),
                'order' => $this->order($raw, $errors),
                default => $this->fail($errors, 'kind must be one of ' . implode(', ', self::KINDS) . '.'),
            };

            if ($clean === null || $errors) {
                $bad[$n] = $errors ?: ['The interaction is not valid.'];
                $errors = [];
                continue;
            }
            $clean['reason'] = $reason !== '' ? $reason : $this->text($raw['reason'] ?? '');
            if ($clean['reason'] === '') {
                $bad[$n] = ['Say in "reason" why this interaction teaches the concept better than plain teaching.'];
                continue;
            }
            $ok[$n] = ['interaction' => $clean, 'reason' => $clean['reason']];
        }

        return [$ok, $bad];
    }

    private function fail(?array &$errors, string $message): null
    {
        $errors = [$message];

        return null;
    }

    /** @return array<string,mixed>|null */
    private function hotspots(array $r, array $slide, ?array &$errors): ?array
    {
        $errors = [];
        $anchors = $slide['_anchors'];
        if (!$anchors) {
            return $this->fail($errors, 'Hotspots need a drawn diagram on the slide; this slide has none.');
        }

        $byLabel = [];
        foreach ($anchors as $a) {
            $byLabel[mb_strtolower($a['label'])] = $a;
        }

        $spots = [];
        foreach ((array) ($r['spots'] ?? []) as $i => $sp) {
            $label = mb_strtolower($this->text($sp['label'] ?? ''));
            $text = $this->text($sp['text'] ?? '');
            if (!isset($byLabel[$label])) {
                $errors[] = 'Spot "' . $this->text($sp['label'] ?? '') . '" is not a label on the diagram; use exactly: ' . implode(' | ', array_column($anchors, 'label')) . '.';
                continue;
            }
            $w = self::words($text);
            if ($w < 5 || $w > 35) {
                $errors[] = 'The text for "' . $byLabel[$label]['label'] . "\" has $w words; it must be 5 to 35.";
            }
            $a = $byLabel[$label];
            $spots[$label] = ['id' => 'h' . (count($spots) + 1), 'label' => $a['label'], 'x' => $a['x'], 'y' => $a['y'], 'w' => $a['w'], 'h' => $a['h'], 'text' => $text];
        }
        $missing = array_diff(array_keys($byLabel), array_keys($spots));
        if ($missing) {
            $errors[] = 'Every label on the diagram needs a spot. Missing: ' . implode(' | ', array_map(fn ($l) => $byLabel[$l]['label'], $missing)) . '.';
        }

        $intro = $this->text($r['intro'] ?? '');
        $wrap = $this->text($r['wrapup'] ?? '');
        if ($intro === '' || self::words($intro) > 25) {
            $errors[] = '"intro" is one short instruction (at most 25 words), for example telling the student what to select.';
        }
        if ($wrap === '' || self::words($wrap) > 25) {
            $errors[] = '"wrapup" is one short line (at most 25 words) shown when every spot has been explored.';
        }

        return $errors ? null : ['kind' => 'hotspots', 'intro' => $intro, 'spots' => array_values($spots), 'wrapup' => $wrap];
    }

    /** @return array<string,mixed>|null */
    private function scenario(array $r, array $slide, ?array &$errors): ?array
    {
        $errors = [];
        if (!$slide['scenario_allowed']) {
            return $this->fail($errors, 'A scenario needs a slide of type ' . implode(', ', self::SCENARIO_SLIDES) . ' - a real situation with a decision. This slide is a ' . $slide['slide_type'] . ' slide.');
        }

        $situation = $this->text($r['situation'] ?? '');
        $sw = self::words($situation);
        if ($sw < 8 || $sw > 60) {
            $errors[] = "The situation has $sw words; it must be 8 to 60.";
        }

        $nodes = array_values((array) ($r['nodes'] ?? []));
        if (count($nodes) < 1 || count($nodes) > 3) {
            return $this->fail($errors, 'A scenario has 1 to 3 decision nodes.');
        }

        $ids = [];
        $clean = [];
        foreach ($nodes as $i => $node) {
            $id = $this->text($node['id'] ?? '');
            if ($id === '' || isset($ids[$id])) {
                $errors[] = 'Node ' . ($i + 1) . ' needs its own unique id.';
                continue;
            }
            $ids[$id] = $i;
            $prompt = $this->text($node['prompt'] ?? '');
            if ($prompt === '' || self::words($prompt) > 40) {
                $errors[] = "Node $id needs a prompt (the decision to make) of at most 40 words.";
            }
            $choices = array_values((array) ($node['choices'] ?? []));
            if (count($choices) < 2 || count($choices) > 3) {
                $errors[] = "Node $id needs 2 or 3 choices.";
                continue;
            }
            $cc = [];
            $sound = 0;
            foreach ($choices as $j => $ch) {
                $text = $this->text($ch['text'] ?? '');
                $outcome = $this->text($ch['outcome'] ?? '');
                $why = $this->text($ch['why'] ?? '');
                if (self::words($text) < 2 || self::words($text) > 25) {
                    $errors[] = "A choice in node $id must be 2 to 25 words.";
                }
                foreach (['outcome' => $outcome, 'why' => $why] as $name => $v) {
                    $w = self::words($v);
                    if ($w < 5 || $w > 40) {
                        $errors[] = "The $name of a choice in node $id has $w words; it must be 5 to 40.";
                    }
                }
                $isSound = !empty($ch['sound']);
                $sound += $isSound ? 1 : 0;
                $next = $ch['next'] ?? null;
                $cc[] = ['id' => $id . '-' . ($j + 1), 'text' => $text, 'outcome' => $outcome, 'why' => $why, 'sound' => $isSound, 'next' => $next === null || $next === '' ? null : $this->text($next)];
            }
            if ($sound < 1) {
                $errors[] = "Node $id needs at least one sound choice.";
            }
            $clean[] = ['id' => $id, 'prompt' => $prompt, 'choices' => $cc];
        }

        // The graph: only forward links (so no cycles), to nodes that exist, and every node reachable.
        $reached = [$clean[0]['id'] ?? '' => true];
        foreach ($clean as $i => $node) {
            foreach ($node['choices'] as $ch) {
                if ($ch['next'] === null) {
                    continue;
                }
                if (!isset($ids[$ch['next']])) {
                    $errors[] = "A choice in node {$node['id']} leads to \"{$ch['next']}\", which does not exist.";
                } elseif ($ids[$ch['next']] <= $i) {
                    $errors[] = "A choice in node {$node['id']} leads backwards to \"{$ch['next']}\"; links only go forward.";
                } else {
                    $reached[$ch['next']] = true;
                }
            }
        }
        foreach ($clean as $node) {
            if (!isset($reached[$node['id']])) {
                $errors[] = "Node {$node['id']} can never be reached; link to it from an earlier node or remove it.";
            }
        }

        $conclusion = $this->text($r['conclusion'] ?? '');
        $cw = self::words($conclusion);
        if ($cw < 5 || $cw > 40) {
            $errors[] = "The conclusion has $cw words; it must be 5 to 40 and state what the student should take away.";
        }

        return $errors ? null : ['kind' => 'scenario', 'situation' => $situation, 'start' => $clean[0]['id'], 'nodes' => $clean, 'conclusion' => $conclusion];
    }

    /**
     * reveal / steps / timeline / compare: items opened one at a time, each with its own explanation.
     *
     * @return array<string,mixed>|null
     */
    private function items(array $r, ?array &$errors): ?array
    {
        $errors = [];
        $kind = (string) ($r['kind'] ?? '');
        [$min, $max] = match ($kind) {
            'steps' => [3, 5],
            'timeline' => [3, 6],
            'compare' => [2, 3],
            default => [2, 5],
        };

        $items = [];
        foreach ((array) ($r['items'] ?? []) as $c) {
            $label = $this->text($c['label'] ?? '');
            $text = $this->text($c['text'] ?? '');
            $when = $this->text($c['when'] ?? '');
            if ($label === '' || self::words($label) > 8) {
                $errors[] = 'An item label is 1 to 8 words.';
            }
            $w = self::words($text);
            if ($w < 5 || $w > 35) {
                $errors[] = "The text for \"$label\" has $w words; it must be 5 to 35.";
            }
            if ($kind === 'timeline' && ($when === '' || mb_strlen($when) > 24)) {
                $errors[] = "Timeline event \"$label\" needs a short \"when\" (a year or date from the chapter text).";
            }
            $items[] = ['id' => 'i' . (count($items) + 1), 'label' => $label, 'text' => $text] + ($kind === 'timeline' ? ['when' => $when] : []);
        }
        if (count($items) < $min || count($items) > $max) {
            $errors[] = "A $kind interaction has $min to $max items; this has " . count($items) . '.';
        }
        if (count(array_unique(array_map(fn ($i) => mb_strtolower($i['label']), $items))) !== count($items)) {
            $errors[] = 'Item labels must differ from each other.';
        }

        $intro = $this->text($r['intro'] ?? '');
        $wrap = $this->text($r['wrapup'] ?? '');
        if ($intro === '' || self::words($intro) > 25) {
            $errors[] = '"intro" is one short instruction of at most 25 words.';
        }
        if (($kind === 'compare' && ($wrap === '' || self::words($wrap) > 30)) || ($wrap !== '' && self::words($wrap) > 30)) {
            $errors[] = $kind === 'compare'
                ? '"wrapup" for a compare is one line of at most 30 words saying how the items compare.'
                : '"wrapup" is at most 30 words.';
        }

        return $errors ? null : ['kind' => $kind, 'intro' => $intro, 'items' => $items, 'wrapup' => $wrap];
    }

    /** @return array<string,mixed>|null */
    private function match(array $r, ?array &$errors): ?array
    {
        $errors = [];
        $pairs = [];
        foreach ((array) ($r['pairs'] ?? []) as $p) {
            $term = $this->text($p['term'] ?? '');
            $meaning = $this->text($p['meaning'] ?? '');
            if ($term === '' || self::words($term) > 6) {
                $errors[] = 'A term is 1 to 6 words.';
            }
            $w = self::words($meaning);
            if ($w < 4 || $w > 20) {
                $errors[] = "The meaning of \"$term\" has $w words; it must be 4 to 20.";
            }
            $pairs[] = ['id' => 'p' . (count($pairs) + 1), 'term' => $term, 'meaning' => $meaning];
        }
        if (count($pairs) < 3 || count($pairs) > 5) {
            $errors[] = 'A match has 3 to 5 pairs.';
        }
        if (count(array_unique(array_map(fn ($p) => mb_strtolower($p['term']), $pairs))) !== count($pairs)) {
            $errors[] = 'Terms must differ from each other.';
        }
        $intro = $this->text($r['intro'] ?? '');
        $wrap = $this->text($r['wrapup'] ?? '');
        if ($intro === '' || self::words($intro) > 25) {
            $errors[] = '"intro" is one short instruction of at most 25 words.';
        }
        if (self::words($wrap) > 30) {
            $errors[] = '"wrapup" is at most 30 words.';
        }

        return $errors ? null : ['kind' => 'match', 'intro' => $intro, 'pairs' => $pairs, 'wrapup' => $wrap];
    }

    /** @return array<string,mixed>|null */
    private function order(array $r, ?array &$errors): ?array
    {
        $errors = [];
        $items = [];
        foreach ((array) ($r['items'] ?? []) as $i) {
            $text = $this->text(is_array($i) ? ($i['text'] ?? '') : $i);
            $w = self::words($text);
            if ($w < 2 || $w > 14) {
                $errors[] = "An item to put in order has $w words; it must be 2 to 14.";
            }
            $items[] = ['id' => 'o' . (count($items) + 1), 'text' => $text];
        }
        if (count($items) < 3 || count($items) > 5) {
            $errors[] = 'An order has 3 to 5 items, listed in the CORRECT order.';
        }
        $intro = $this->text($r['intro'] ?? '');
        $wrap = $this->text($r['wrapup'] ?? '');
        if ($intro === '' || self::words($intro) > 25) {
            $errors[] = '"intro" is one short instruction of at most 25 words.';
        }
        if (self::words($wrap) > 30) {
            $errors[] = '"wrapup" is at most 30 words.';
        }

        return $errors ? null : ['kind' => 'order', 'intro' => $intro, 'items' => $items, 'wrapup' => $wrap];
    }
    /**
     * Keep the deck from turning into an activity page: at most a few scenarios and reveals,
     * earliest slides first. A dropped one is recorded, not silently lost.
     *
     * @param array<int,array{interaction:?array,reason:string}> $out
     * @return array<int,array{interaction:?array,reason:string}>
     */
    private function capped(array $out, array $plan): array
    {
        $seen = [];

        ksort($out);
        foreach ($out as $n => $d) {
            $kind = $d['interaction']['kind'] ?? null;
            if (!isset(self::CAPS[$kind])) {
                continue;
            }
            $seen[$kind] = ($seen[$kind] ?? 0) + 1;
            if ($seen[$kind] > self::CAPS[$kind]) {
                $out[$n] = ['interaction' => null, 'reason' => 'left to plain teaching: the lesson already has ' . self::CAPS[$kind] . ' ' . $kind . ' activities'];
            }
        }
        return $out;
    }

    private function text(mixed $v): string
    {
        if (is_array($v)) {
            $v = implode(' ', array_map(fn ($x) => $this->text($x), array_values($v)));
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');
    }

    public static function words(string $s): int
    {
        return count(array_filter(preg_split('/\s+/u', trim($s)) ?: []));
    }

    /** @param array<int,array<string,mixed>> $chunk */
    private function prompt(array $context, array $plan, array $chunk): string
    {
        $work = array_map(function ($c) {
            unset($c['_anchors']);

            return $c;
        }, array_values($chunk));
        $input = json_encode($work, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $scenarios = implode(', ', self::SCENARIO_SLIDES);
        $caps = self::CAPS;

        return <<<PROMPT
This is a teacher-led classroom lesson that students also study on their own, shown as a full-screen presentation. The explanations, pictures and examples are already written. Your job: EVERY slide listed below should let the student DO something that teaches - explore, reveal, compare, connect, decide - not just read and press Continue. Write one interaction for each slide, choosing the kind that fits what the slide IS. It must feel like an interactive textbook, never like a quiz: nothing is scored and nothing has one right answer except where a real sequence or definition does.

An interaction must teach. It adds something the slide has not already said (why a detail is left out, what assumption is being made, a real case from the chapter, how a part connects to the others). Never a token click that repeats the slide. Only if a slide truly has nothing worth exploring, reply null with the reason; that should be rare. The worked example and the "common mistake" are shown on their own later screen, so do not repeat them here.

The kinds - pick by the slide's content, and vary them across the lesson:
1. "hotspots": ONLY for a slide with a "diagram". The student selects each labelled part. Every label in "labels_you_may_use" must get a spot, spelled exactly as given.
   {"kind": "hotspots", "reason": "...", "intro": "one short instruction", "spots": [{"label": "exact label", "text": "5 to 35 words"}], "wrapup": "one short line when all parts are explored"}
2. "reveal": click-to-reveal CARDS for a set of 2 to 5 ideas (parts, kinds, reasons, objectives, key points). Each card opens to its own explanation. Best for slides whose "bullets" or explanation name several ideas.
   {"kind": "reveal", "reason": "...", "intro": "one short instruction", "items": [{"label": "1 to 8 words", "text": "5 to 35 words"}], "wrapup": "optional, at most 30 words"}
3. "steps": a PROCESS or sequence of 3 to 5 steps, opened one by one, each explained.
   {"kind": "steps", "reason": "...", "intro": "...", "items": [{"label": "Step name", "text": "5 to 35 words"}], "wrapup": "optional"}
4. "compare": 2 or 3 things set side by side (a law and a theory, two models, a wrong idea and a right one). Each is explained when opened; "wrapup" is required and says how they compare.
   {"kind": "compare", "reason": "...", "intro": "...", "items": [{"label": "...", "text": "5 to 35 words"}], "wrapup": "one line, at most 30 words"}
5. "timeline": 3 to 6 dated events, ONLY where the chapter text gives real dates. Each item needs "when" (a year or date from the chapter text). At most {$caps['timeline']} in the deck.
   {"kind": "timeline", "reason": "...", "intro": "...", "items": [{"when": "1920", "label": "Event", "text": "5 to 35 words"}], "wrapup": "optional"}
6. "scenario": ONLY where "scenario_allowed" is true (types {$scenarios}), at most {$caps['scenario']} in the whole deck, and only when the concept truly turns on a decision, a cause and effect, or a real situation. NEVER invent one for a factual concept. 1 to 3 decision nodes; each choice has what happens ("outcome"), why ("why"), "sound" and the id of the next node or null. Links go forward only, every node reachable, every node has a sound choice, include a tempting less sound choice.
   {"kind": "scenario", "reason": "...", "situation": "8 to 60 words", "nodes": [{"id": "n1", "prompt": "the decision", "choices": [{"text": "2 to 25 words", "outcome": "5 to 40 words", "why": "5 to 40 words", "sound": true, "next": "n2 or null"}]}], "conclusion": "5 to 40 words"}
7. "match": 3 to 5 terms the chapter defines, paired with their meanings (at most {$caps['match']} in the deck).
   {"kind": "match", "reason": "...", "intro": "...", "pairs": [{"term": "1 to 6 words", "meaning": "4 to 20 words"}], "wrapup": "optional"}
8. "order": a real sequence of 3 to 5 steps worth arranging, written in the CORRECT order (at most {$caps['order']} in the deck).
   {"kind": "order", "reason": "...", "intro": "...", "items": [{"text": "2 to 14 words"}], "wrapup": "optional"}

How to choose: a slide with a diagram gets "hotspots". A process gets "steps". Contrasting ideas get "compare". A set of ideas, or objectives, get "reveal". A decision or real situation gets "scenario". Defined vocabulary can get "match". Do not give every slide the same kind.

For a slide with no worthwhile interaction reply {"n": 3, "interaction": null, "reason": "why"}.

Rules
- Teach, do not test. No scores. Feedback is explanation.
- Every explanation you write adds to the slide; it never just repeats it.
- Numbers, names, dates and examples must appear in the chapter text. If concept data and chapter text disagree, the chapter text wins.
- Sentence case, address the student as "you", no emoji, no exclamation marks.

Reply with {"slides": [one entry for every slide listed, {"n": 1, "interaction": null or an object above, "reason": "..."}]}

SLIDES
{$input}

CHAPTER TEXT (the only source of facts)
{$context['ground_truth']}
PROMPT;
    }
}
