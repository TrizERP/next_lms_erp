<?php

namespace App\Services\StudyDeck\Branching\Pro;

use App\Services\StudyDeck\Story\StoryTemplate;

/**
 * Shows a Branching Scenario stored in the older story format in the pro format (explore / decide / outcome slides), without changing
 * what is stored.
 *
 * A story lesson keeps one slide per act, holding a graph of scenes, decisions and endings. This reads that graph and writes the slides the
 * pro player draws, from the same words:
 *
 *   act slide        -> an introduction slide (the mission), then one slide per node:
 *   scene            -> a slide with its picture and text (its explanation kept); a scene whose picture carries marked parts becomes a
 *                       hotspots slide on that picture
 *   decision         -> a decision slide (the question over the picture of the scene before it), and for EACH choice an outcome slide:
 *                       what happened, why, sound or caution; a weaker choice offers "Choose again" and "See the intelligent path"
 *   ending           -> a slide, which goes on to whatever follows the act
 *   merge lead-in    -> the short "what you carry in from that path" text of a scene where branches meet becomes a slide of its own,
 *                       reached only from that path, so each learner still reads the lead-in for the way they came
 *
 * Every way on that the story had is kept (a choice's `next` becomes the outcome's Continue), so the paths a learner can take are the same.
 * Slides outside the story acts (cover, objectives, ...) are kept as they are, renumbered.
 *
 * It is a pure function and it fails safe: a deck it cannot convert into a valid, fully linked graph (ProScenarioValidator, without the
 * picture rule) is returned as it came, never half converted.
 */
final class ProScenarioConverter
{
    /** The reason the last refused conversion was refused, for a log line; null after a good one. */
    public static ?string $lastRefusal = null;

    /**
     * True for a deck that should be converted: stored in the story format (not already in the pro format) and belonging to a chapter the
     * configuration names, with conversion switched on.
     *
     * @param array<string,mixed> $deck
     * @param array<string,mixed>|null $config
     */
    public static function applies(array $deck, ?array $config = null): bool
    {
        if (!self::hasStoryActs($deck) || ProScenarioFormat::isPro($deck)) {
            return false;
        }
        $ch = (array) ($deck['chapter'] ?? []);

        return ProScenarioGate::convertsExisting([
            'standard_name' => $ch['standard_name'] ?? '', 'subject_name' => $ch['subject_name'] ?? '', 'sort_order' => $ch['number'] ?? 0,
        ], $config);
    }

    /**
     * The deck as the player should get it: converted when it applies and can be converted, otherwise exactly as it came.
     *
     * @param array<string,mixed> $deck
     * @return array<string,mixed>
     */
    public static function forRead(array $deck, ?array $config = null): array
    {
        self::$lastRefusal = null;
        if (!self::applies($deck, $config)) {
            return $deck;
        }
        try {
            return self::convert($deck);
        } catch (\Throwable $e) {
            self::$lastRefusal = $e->getMessage();

            return $deck;
        }
    }

    /** The most slides a converted lesson may have (config study_deck_pro_branching.max_slides); null for no limit. */
    public static function configuredMax(): ?int
    {
        try {
            $max = function_exists('config') ? (int) config('study_deck_pro_branching.max_slides', 0) : 0;
        } catch (\Throwable) {
            $max = 0;   // no application booted (a plain unit test): no cap unless one is passed
        }

        return $max > 0 ? $max : null;
    }

    /**
     * The lesson as pro slides, every scene and decision kept; when that is more slides than the cap allows, condensed (see condense()).
     *
     * @param array<string,mixed> $deck a stored story-format deck
     * @param int|null $maxSlides the cap (default -1: the configured one); null: none
     * @return array<string,mixed> the same lesson as pro slides
     *
     * @throws \RuntimeException when the result would not be a valid graph, or cannot be brought under the cap
     */
    public static function convert(array $deck, ?int $maxSlides = -1): array
    {
        $max = $maxSlides === -1 ? self::configuredMax() : $maxSlides;
        $full = self::assemble($deck);
        if ($max === null || count($full['slides']) <= $max) {
            return $full;
        }

        $short = self::condense($deck);
        // Still over (many parts, few slides each): the parts with the fewest explanations lose their introduction slide, one at a time.
        $candidates = [];
        foreach (array_values((array) $short['slides']) as $i => $slide) {
            if (self::isAct($slide)) {
                $candidates[$i] = count((array) ($slide['content']['explanations'] ?? []));
            }
        }
        asort($candidates);
        $drop = [];
        for (;;) {
            $out = self::assemble($short, $drop);
            if (count($out['slides']) <= $max) {
                return $out;
            }
            if ($candidates === []) {
                throw new \RuntimeException('not converted: even condensed, the lesson has ' . count($out['slides']) . ' slides (the limit is ' . $max . ').');
            }
            $drop[array_key_first($candidates)] = true;
            unset($candidates[array_key_first($candidates)]);
        }
    }

    /**
     * @param array<string,mixed> $deck
     * @param array<int,bool> $dropIntro old slide index => leave out that part's introduction slide
     * @return array<string,mixed>
     */
    private static function assemble(array $deck, array $dropIntro = []): array
    {
        $old = array_values((array) $deck['slides']);

        // ---- pass 1: every new slide's number --------------------------------------------------------------------------------------
        $n = 0;
        $plan = [];   // old index => ['kind' => 'keep', 'n' => int] | ['kind' => 'act', 'units' => [key => n], 'order' => [key...]]
        foreach ($old as $i => $slide) {
            if (!self::isAct($slide)) {
                $plan[$i] = ['kind' => 'keep', 'n' => ++$n];
                continue;
            }
            $it = $slide['interaction'];
            $nodes = self::indexNodes($it);
            $order = isset($dropIntro[$i]) ? [] : ['intro'];
            foreach ($it['nodes'] as $node) {
                foreach (self::leadIns($node) as $after => $_) {
                    $order[] = 'lead:' . $after . '>' . $node['id'];
                }
                $order[] = 'node:' . $node['id'];
                if (($node['kind'] ?? '') === 'decision') {
                    foreach (array_keys((array) $node['choices']) as $k) {
                        $order[] = 'out:' . $node['id'] . ':' . $k;
                    }
                }
            }
            // the last slide of the lesson is an act: its endings need a closing slide to go on to, or Continue would wander to the next slide in order
            if (!isset($old[$i + 1])) {
                $order[] = 'outro';
            }
            $units = [];
            foreach ($order as $key) {
                $units[$key] = ++$n;
            }
            $plan[$i] = ['kind' => 'act', 'units' => $units, 'nodes' => $nodes];
        }

        // ---- pass 2: the slides --------------------------------------------------------------------------------------------------
        $out = [];
        $titles = [];
        foreach ($old as $i => $slide) {
            $p = $plan[$i];
            if ($p['kind'] === 'keep') {
                $slide['n'] = $p['n'];
                if (isset($slide['next']) && (int) $slide['next'] > 0) {
                    $slide['next'] = self::entry($plan, $old, (int) $slide['next']) ?? $slide['next'];
                }
                $out[] = $slide;
                continue;
            }
            $following = isset($old[$i + 1]) ? self::firstNumber($plan[$i + 1]) : null;
            foreach (self::actSlides($slide, $p, $following, $titles) as $s) {
                $out[] = $s;
            }
        }

        $errors = ProScenarioValidator::check($out, false, false);
        if ($errors !== []) {
            throw new \RuntimeException('not converted: ' . implode(' ', array_slice($errors, 0, 3)));
        }

        $deck['slides'] = $out;
        $deck['slide_count'] = count($out);
        $deck['theme'] = ProScenarioFormat::THEME;
        [$deck['concept_slides'], $deck['taught_by']] = self::conceptIndex($out);

        return $deck;
    }

    // ---- one act ----------------------------------------------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $slide the act's stored slide
     * @param array<string,mixed> $p its plan
     * @param array<string,int> $titles titles already used, so none repeats
     * @return array<int,array<string,mixed>>
     */
    private static function actSlides(array $slide, array $p, ?int $following, array &$titles): array
    {
        $it = $slide['interaction'];
        $units = $p['units'];
        $nodes = $p['nodes'];
        $section = (string) ($slide['section'] ?? $slide['title'] ?? '');
        $actConcepts = array_map('intval', (array) ($slide['concept_ids'] ?? []));
        $out = [];

        $make = function (int $n, string $title, array $content, ?array $interaction, ?array $image, array $concepts, array $taught, ?int $next, bool $withNext) use (&$out, &$titles, $slide, $section): void {
            $key = mb_strtolower(trim($title));
            if (isset($titles[$key])) {
                $title = rtrim($title, '. ') . ' (' . $section . ($titles[$key] > 1 ? ', ' . $titles[$key] : '') . ')';
            }
            $titles[$key] = ($titles[$key] ?? 0) + 1;
            $titles[mb_strtolower(trim($title))] ??= 1;
            $names = [];
            foreach ($concepts as $cid) {
                foreach ((array) ($slide['concept_ids'] ?? []) as $k => $id) {
                    if ((int) $id === $cid && isset($slide['concepts'][$k])) {
                        $names[] = $slide['concepts'][$k];
                    }
                }
            }
            $s = [
                'n' => $n, 'section' => $section, 'slide_type' => 'teach', 'title' => $title, 'concept_ids' => $concepts, 'taught_concept_ids' => $taught,
                'concepts' => $names, 'relationship' => null,
                'content' => $content + ['body' => '', 'explanations' => [], 'bullets' => [], 'example' => null, 'misconception' => null, 'relationship_note' => null, 'key_idea' => null, 'discussion' => null, 'bloom' => 'understand', 'dok' => 2, 'minutes' => 2],
                'question_ids' => [], 'activities' => [], 'h5p_pattern' => null, 'pattern_dropped' => null, 'visual' => null,
                'interaction' => $interaction, 'interaction_reason' => (string) ($interaction['reason'] ?? ''), 'image' => $image, 'image_missing' => null,
                'sources' => ['concept_intelligence' => $concepts !== [], 'existing_ai_content' => false, 'question_bank' => false, 'h5p_pattern_reference' => false],
            ];
            if ($withNext) {
                $s['next'] = $next;
            }
            $out[] = $s;
        };

        // the target a way on from $from to node $to lands on: the lead-in for that path when the scene has one, else the node's first slide
        $target = function (string $from, string $to) use ($units): int {
            return $units['lead:' . $from . '>' . $to] ?? $units['node:' . $to] ?? throw new \RuntimeException('a way on leads to "' . $to . '", which is not a node');
        };
        $afterAct = fn (): ?int => $following ?? ($units['outro'] ?? null);

        // ---- the introduction: the mission, and the concepts the act explains
        $startNode = (string) ($it['start'] ?? '');
        $mission = (array) ($it['mission'] ?? []);
        $body = trim((string) ($slide['content']['body'] ?? '')) !== '' ? (string) $slide['content']['body'] : (string) ($mission['body'] ?? $it['situation'] ?? '');
        if (isset($units['intro'])) {
            $make($units['intro'], (string) ($slide['title'] ?? $mission['title'] ?? $section),
                ['body' => $body, 'explanations' => (array) ($slide['content']['explanations'] ?? []), 'minutes' => (int) ($slide['content']['minutes'] ?? 3)],
                null, self::cleanImage($slide['image'] ?? null), $actConcepts, array_map('intval', (array) ($slide['taught_concept_ids'] ?? $actConcepts)),
                $units['node:' . $startNode] ?? null, true);
        }

        // the picture each decision is asked over: the nearest scene before it
        $before = self::pictureBefore($it, $nodes);

        foreach ($it['nodes'] as $node) {
            $id = (string) $node['id'];
            $kind = (string) ($node['kind'] ?? 'scene');
            $cids = array_map('intval', (array) ($node['concept_ids'] ?? []));
            $image = self::cleanImage($node['image'] ?? null);

            // lead-ins first: reached only from the path they describe
            foreach (self::leadIns($node) as $after => $text) {
                $make($units['lead:' . $after . '>' . $id], rtrim((string) $node['title'], '. ') . ': what you bring from your last step', ['body' => $text], null, $image, $cids, [], $units['node:' . $id], true);
            }

            if ($kind === 'decision') {
                $choices = array_values((array) $node['choices']);
                $letters = [];
                $options = [];
                foreach ($choices as $k => $c) {
                    $letters[$k] = chr(97 + $k);
                    $options[] = ['id' => $letters[$k], 'text' => (string) $c['text'], 'goto' => $units['out:' . $id . ':' . $k]];
                }
                $make($units['node:' . $id], (string) $node['title'], ['body' => (string) ($node['body'] ?? '')],
                    ['kind' => 'decision', 'reason' => 'A choice that changes what happens next.', 'kicker' => 'Scenario · ' . $section, 'question' => (string) ($node['prompt'] ?? $node['title']), 'options' => $options],
                    $before[$id] ?? $image, $cids, [], ProScenarioFormat::WAIT, true);

                $sounds = array_keys(array_filter($choices, fn ($c) => !empty($c['sound'])));
                foreach ($choices as $k => $c) {
                    $sound = !empty($c['sound']);
                    // a choice that goes nowhere in a condensed part goes on to whatever follows the part
                    $way = ($c['next'] ?? '') === '' ? $afterAct() : $target($id, (string) $c['next']);
                    $actions = $sound
                        ? [['label' => 'Continue the story', 'goto' => $way, 'primary' => true], ['label' => 'Choose again', 'goto' => $units['node:' . $id]]]
                        : [['label' => 'Choose again', 'goto' => $units['node:' . $id], 'primary' => true]];
                    if (!$sound && $sounds !== []) {
                        $actions[] = ['label' => 'See the intelligent path', 'goto' => $units['out:' . $id . ':' . $sounds[0]]];
                    }
                    $make($units['out:' . $id . ':' . $k], (string) $c['text'], [],
                        ['kind' => 'outcome', 'reason' => 'What the choice leads to.', 'tone' => $sound ? ProScenarioFormat::TONE_SOUND : ProScenarioFormat::TONE_CAUTION,
                            'choice' => ['id' => $letters[$k], 'text' => (string) $c['text']], 'what' => (string) $c['outcome'], 'why' => (string) $c['why'], 'actions' => $actions],
                        $before[$id] ?? $image, [], [], $way, true);
                }
                continue;
            }

            // a scene or an ending
            $explain = (array) ($node['explain'] ?? []);
            $explanations = [];
            if (trim((string) ($explain['body'] ?? '')) !== '' && ($cids !== [] || $actConcepts !== [])) {
                $explanations[] = ['concept_id' => ($cids[0] ?? $actConcepts[0]), 'text' => trim((string) ($explain['title'] ?? '') !== '' ? rtrim((string) $explain['title'], '. ') . '. ' . $explain['body'] : (string) $explain['body'])];
            }
            $content = ['body' => (string) ($node['body'] ?? ''), 'explanations' => $explanations];
            $interaction = null;
            $spots = self::spots($node['image'] ?? null);
            if ($spots !== null && $image !== null) {
                $image['annotated'] = true;
                $interaction = ['kind' => 'hotspots', 'reason' => 'Select a part of the picture.', 'intro' => 'Select a marker to see what it shows.', 'spots' => $spots, 'wrapup' => ''];
            }
            if ($kind === 'ending') {
                $make($units['node:' . $id], (string) $node['title'], $content, $interaction, $image, $cids, $cids, $afterAct(), $afterAct() !== null);
                continue;
            }
            $choice = $node['choices'][0] ?? null;
            $make($units['node:' . $id], (string) $node['title'], $content, $interaction, $image, $cids, $cids,
                $choice !== null ? $target($id, (string) $choice['next']) : null, $choice !== null);
        }

        if (isset($units['outro'])) {
            $conclusion = trim((string) ($it['conclusion'] ?? ''));
            $make($units['outro'], 'Summing up: ' . $section, ['body' => $conclusion !== '' ? $conclusion : 'You have followed this lesson to its end.'], null, null, [], [], null, false);
            $out[array_key_last($out)]['slide_type'] = 'summary';
        }

        usort($out, fn ($a, $b) => $a['n'] <=> $b['n']);

        return $out;
    }

    /**
     * The lesson cut down to the slides a cap allows, keeping what teaches: for every part, its introduction (the mission, with the explanation
     * of EVERY concept the part's scenes explained, merged and not repeated), the part's central decision (the one that touches the most
     * concepts, the first of them on a tie) over the picture of the scene before it, and an outcome for each answer. The scenes between the
     * decisions and the part's other decisions are left out of this view only; the stored lesson is not changed.
     *
     * @param array<string,mixed> $deck
     * @return array<string,mixed> a story-format deck of the same parts, each with one decision
     */
    public static function condense(array $deck): array
    {
        foreach ($deck['slides'] as $i => $slide) {
            if (!self::isAct($slide)) {
                continue;
            }
            $it = $slide['interaction'];
            $nodes = self::indexNodes($it);
            $before = self::pictureBefore($it, $nodes);
            $decisions = array_values(array_filter($it['nodes'], fn ($n) => ($n['kind'] ?? '') === 'decision' && count((array) $n['choices']) >= 2));
            if ($decisions === []) {
                continue;   // nothing to decide in this part: it stays as it is
            }
            // usort is stable in PHP 8, so on a tie the story's own order decides
            usort($decisions, fn ($a, $b) => count((array) ($b['concept_ids'] ?? [])) <=> count((array) ($a['concept_ids'] ?? [])));
            $d = $decisions[0];
            foreach ($d['choices'] as $k => $c) {
                $d['choices'][$k]['next'] = null;
            }
            $d['image'] = $before[$d['id']] ?? ($d['image'] ?? null);

            // every concept explanation the scenes gave, once each, added to the part's own
            $have = [];
            $explanations = array_values((array) ($slide['content']['explanations'] ?? []));
            foreach ($explanations as $e) {
                $have[mb_strtolower(trim((string) $e['text']))] = true;
            }
            $firstImage = null;
            foreach ($it['nodes'] as $n) {
                $firstImage ??= self::cleanImage($n['image'] ?? null);
                $text = trim((string) ($n['explain']['body'] ?? ''));
                $cid = $n['concept_ids'][0] ?? ($slide['concept_ids'][0] ?? null);
                if ($text !== '' && $cid !== null && !isset($have[mb_strtolower($text)])) {
                    $have[mb_strtolower($text)] = true;
                    $title = trim((string) ($n['explain']['title'] ?? ''));
                    $explanations[] = ['concept_id' => (int) $cid, 'text' => $title !== '' ? rtrim($title, '. ') . '. ' . $text : $text];
                }
            }
            $deck['slides'][$i]['content']['explanations'] = $explanations;
            $deck['slides'][$i]['image'] = $slide['image'] ?? $firstImage;
            $deck['slides'][$i]['interaction']['nodes'] = [$d];
            $deck['slides'][$i]['interaction']['start'] = $d['id'];
        }

        return $deck;
    }

    // ---- helpers ----------------------------------------------------------------------------------------------------------------

    private static function isAct(array $slide): bool
    {
        return ($slide['interaction']['kind'] ?? null) === 'scenario' && ($slide['interaction']['template'] ?? null) === StoryTemplate::KEY;
    }

    private static function hasStoryActs(array $deck): bool
    {
        foreach ((array) ($deck['slides'] ?? []) as $s) {
            if (self::isAct($s)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,array<string,mixed>> */
    private static function indexNodes(array $interaction): array
    {
        $by = [];
        foreach ((array) $interaction['nodes'] as $node) {
            $by[(string) $node['id']] = $node;
        }

        return $by;
    }

    /** @return array<string,string> the lead-in text for each path into a scene where branches meet: node id of the path => text */
    private static function leadIns(array $node): array
    {
        $out = [];
        foreach ((array) ($node['variants'] ?? []) as $v) {
            if (is_array($v) && isset($v['after']) && trim((string) ($v['body'] ?? '')) !== '') {
                $out[(string) $v['after']] = trim((string) $v['body']);
            }
        }

        return $out;
    }

    /**
     * The picture each decision is shown over: the nearest scene that leads to it and has a picture (walking back along the story).
     *
     * @param array<string,array<string,mixed>> $nodes
     * @return array<string,array<string,mixed>|null>
     */
    private static function pictureBefore(array $interaction, array $nodes): array
    {
        $from = [];
        foreach ($nodes as $id => $node) {
            foreach ((array) ($node['choices'] ?? []) as $c) {
                if (isset($c['next'])) {
                    $from[(string) $c['next']][] = $id;
                }
            }
        }
        $out = [];
        foreach ($nodes as $id => $node) {
            if (($node['kind'] ?? '') !== 'decision') {
                continue;
            }
            $seen = [$id => true];
            $queue = [$id];
            while ($queue !== []) {
                $cur = array_shift($queue);
                foreach ($from[$cur] ?? [] as $prev) {
                    if (isset($seen[$prev])) {
                        continue;
                    }
                    $seen[$prev] = true;
                    $img = self::cleanImage($nodes[$prev]['image'] ?? null);
                    if ($img !== null) {
                        $out[$id] = $img;
                        break 2;
                    }
                    $queue[] = $prev;
                }
            }
        }

        return $out;
    }

    /** The picture as the pro slide holds it: the stored record, without the parts a picture check marked (they become a hotspots slide). */
    private static function cleanImage(mixed $image): ?array
    {
        if (!is_array($image) || trim((string) ($image['url'] ?? '')) === '') {
            return null;
        }
        unset($image['hotspots'], $image['extra_images']);

        return $image;
    }

    /** @return array<int,array<string,mixed>>|null the marked parts of a picture, when there are enough to open */
    private static function spots(mixed $image): ?array
    {
        $spots = is_array($image) ? ($image['hotspots'] ?? null) : null;
        if (!is_array($spots) || count($spots) < ProScenarioFormat::MIN_SPOTS || count($spots) > ProScenarioFormat::MAX_SPOTS) {
            return null;
        }
        foreach ($spots as $s) {
            if (!is_array($s) || empty($s['id']) || trim((string) ($s['label'] ?? '')) === '' || trim((string) ($s['text'] ?? '')) === '') {
                return null;
            }
        }

        return array_values($spots);
    }

    /** The new number of the first slide of an old slide (for a `next` on a slide that is kept). */
    private static function entry(array $plan, array $old, int $oldN): ?int
    {
        foreach ($old as $i => $s) {
            if ((int) ($s['n'] ?? 0) === $oldN) {
                return self::firstNumber($plan[$i]);
            }
        }

        return null;
    }

    private static function firstNumber(array $p): int
    {
        return $p['kind'] === 'keep' ? $p['n'] : min($p['units']);
    }

    /**
     * @param array<int,array<string,mixed>> $slides
     * @return array{0:array<int|string,array<int,int>>,1:array<int|string,array<int,int>>} concept_slides and taught_by
     */
    private static function conceptIndex(array $slides): array
    {
        $seen = [];
        $taught = [];
        foreach ($slides as $s) {
            foreach ((array) ($s['concept_ids'] ?? []) as $id) {
                $seen[$id][] = $s['n'];
            }
            foreach ((array) ($s['taught_concept_ids'] ?? []) as $id) {
                $taught[$id][] = $s['n'];
            }
        }

        return [$seen, $taught];
    }
}
