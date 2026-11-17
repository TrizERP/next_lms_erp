<?php

namespace App\Services\StudyDeck\Branching\Pro;

use App\Services\StudyDeck\Story\StorySkeleton;

/**
 * The rules a pro Branching Scenario deck must meet before it is offered for review.
 *
 * Pure: it reads the slides and nothing else. Two layers:
 *
 *   SHAPE   what the player needs to draw each interaction. It mirrors interactionProblem() / branchProblem() in
 *           lms_k12/lib/study-deck/interactions.ts, so a deck this accepts is a deck the player can draw.
 *   GRAPH   what makes it a real branching scenario and not a row of static slides: every answer opens a slide of its
 *           own that names the answer it answers, every decision has a sound path, every slide can be reached from the
 *           start, and from every slide the learner can still reach the end (nobody is stranded or looped for ever).
 */
final class ProScenarioValidator
{
    /**
     * @param array<int,array<string,mixed>> $slides deck slides (n, title, image, interaction, next, ...)
     * @param bool $pictures require a readable picture on every explore, decision and outcome slide (a newly built deck); false for a deck
     *                       converted from stored content, where a scene may never have had one and the player draws the slide without
     * @param bool $pedagogy judge the choices (one sound answer among weaker ones, no giveaway wording): the rules for a deck built now. A deck
     *                       converted from stored content keeps the choices it was written with, and only has its links judged.
     * @return array<int,string> one sentence per problem; empty when the deck is sound
     */
    public static function check(array $slides, bool $pictures = true, bool $pedagogy = true): array
    {
        $errors = [];
        $slides = array_values($slides);
        if ($slides === []) {
            return ['The deck has no slides.'];
        }

        $byN = [];
        foreach ($slides as $s) {
            $n = (int) ($s['n'] ?? 0);
            if ($n < 1 || isset($byN[$n])) {
                $errors[] = 'Slide numbers must be 1, 2, 3 ... each used once (found ' . ($n ?: 'none') . ').';

                continue;
            }
            $byN[$n] = $s;
        }
        if ($errors !== []) {
            return $errors;
        }
        $last = max(array_keys($byN));

        $titles = [];
        foreach ($byN as $n => $s) {
            $i = $s['interaction'] ?? null;
            if (($s['slide_type'] ?? '') !== 'cover') {
                $t = self::norm((string) ($s['title'] ?? ''));
                if ($t !== '' && isset($titles[$t])) {
                    $errors[] = "Slide $n repeats the title of slide {$titles[$t]}.";
                }
                $titles[$t] ??= $n;
            }
            if (!is_array($i)) {
                continue;
            }
            foreach (self::shape($n, $i, $s, $pictures) as $e) {
                $errors[] = $e;
            }
            foreach (self::targets($n, $s, $byN) as $e) {
                $errors[] = $e;
            }
        }
        if ($errors !== []) {
            return $errors;   // the graph is only worth judging once every way on leads somewhere
        }

        foreach ($byN as $n => $s) {
            if (($s['interaction']['kind'] ?? null) === 'decision') {
                array_push($errors, ...self::decision($n, $s, $byN, $pedagogy));
            }
        }
        array_push($errors, ...self::reach($byN, $last));

        return $errors;
    }

    // ---- shape ---------------------------------------------------------------------------------------------------------------

    /** @return array<int,string> */
    private static function shape(int $n, array $i, array $slide, bool $pictures = true): array
    {
        $e = [];
        $at = "Slide $n";
        $kind = $i['kind'] ?? null;
        $image = is_array($slide['image'] ?? null) ? $slide['image'] : null;

        if ($pictures && in_array($kind, ['hotspots', 'decision', 'outcome'], true)) {
            if ($image === null || trim((string) ($image['url'] ?? '')) === '' || (int) ($image['width'] ?? 0) < 1 || (int) ($image['height'] ?? 0) < 1) {
                $e[] = "$at ($kind) has no readable picture; every explore, decision and outcome slide is drawn over one.";
            } elseif (trim((string) ($image['alt'] ?? '')) === '') {
                $e[] = "$at: the picture has no alternative text.";
            }
        }

        switch ($kind) {
            case 'hotspots':
                if ($image !== null && ($image['type'] ?? '') !== 'diagram' && empty($image['annotated'])) {
                    $e[] = "$at asks for hotspots on a picture that is neither a drawn diagram nor marked up.";
                }
                $spots = $i['spots'] ?? null;
                if (!is_array($spots) || count($spots) < ProScenarioFormat::MIN_SPOTS || count($spots) > ProScenarioFormat::MAX_SPOTS) {
                    $e[] = "$at needs " . ProScenarioFormat::MIN_SPOTS . ' to ' . ProScenarioFormat::MAX_SPOTS . ' hotspots.';
                    break;
                }
                $ids = [];
                foreach ($spots as $sp) {
                    $label = trim((string) ($sp['label'] ?? ''));
                    if (empty($sp['id']) || $label === '' || trim((string) ($sp['text'] ?? '')) === '') {
                        $e[] = "$at has a hotspot with no id, label or explanation.";
                        continue;
                    }
                    $ids[] = $sp['id'];
                    foreach (['x', 'y', 'w', 'h'] as $k) {
                        if (!is_int($sp[$k] ?? null) && !is_float($sp[$k] ?? null)) {
                            $e[] = "$at: hotspot \"$label\" has no $k.";
                            continue 2;
                        }
                    }
                    if ($sp['w'] < 4 || $sp['h'] < 4 || $sp['x'] - $sp['w'] / 2 < -0.5 || $sp['y'] - $sp['h'] / 2 < -0.5 || $sp['x'] + $sp['w'] / 2 > 100.5 || $sp['y'] + $sp['h'] / 2 > 100.5) {
                        $e[] = "$at: hotspot \"$label\" is outside its picture.";
                    }
                    if (isset($sp['pin']) && (!is_numeric($sp['pin']['x'] ?? null) || !is_numeric($sp['pin']['y'] ?? null) || $sp['pin']['x'] < 0 || $sp['pin']['x'] > 100 || $sp['pin']['y'] < 0 || $sp['pin']['y'] > 100)) {
                        $e[] = "$at: hotspot \"$label\" has a pin off the picture.";
                    }
                }
                if (count(array_unique($ids)) !== count($ids)) {
                    $e[] = "$at repeats a hotspot id.";
                }
                break;

            case 'decision':
                $options = $i['options'] ?? null;
                if (trim((string) ($i['question'] ?? '')) === '' || !is_array($options) || count($options) < ProScenarioFormat::MIN_OPTIONS || count($options) > ProScenarioFormat::MAX_OPTIONS) {
                    $e[] = "$at needs a question and " . ProScenarioFormat::MIN_OPTIONS . ' to ' . ProScenarioFormat::MAX_OPTIONS . ' answers.';
                    break;
                }
                $ids = [];
                foreach ($options as $o) {
                    if (empty($o['id']) || trim((string) ($o['text'] ?? '')) === '' || !is_int($o['goto'] ?? null) || $o['goto'] < 1) {
                        $e[] = "$at has an answer with no text or no slide to open.";
                        continue;
                    }
                    $ids[] = $o['id'];
                }
                if (count(array_unique($ids)) !== count($ids)) {
                    $e[] = "$at repeats an answer id.";
                }
                if (($slide['next'] ?? null) !== ProScenarioFormat::WAIT) {
                    $e[] = "$at: a decision's Continue must wait for the learner (next = -1), or the question could be skipped.";
                }
                break;

            case 'outcome':
                if (trim((string) ($i['what'] ?? '')) === '' || trim((string) ($i['why'] ?? '')) === '' || trim((string) ($i['choice']['text'] ?? '')) === '' || empty($i['choice']['id'])) {
                    $e[] = "$at needs the answer it follows, what happens and why.";
                }
                if (!in_array($i['tone'] ?? null, [ProScenarioFormat::TONE_SOUND, ProScenarioFormat::TONE_CAUTION], true)) {
                    $e[] = "$at has no tone (sound or caution).";
                }
                $actions = $i['actions'] ?? null;
                if (!is_array($actions) || $actions === []) {
                    $e[] = "$at has no way on.";
                    break;
                }
                foreach ($actions as $a) {
                    if (trim((string) ($a['label'] ?? '')) === '' || !is_int($a['goto'] ?? null) || $a['goto'] < 1) {
                        $e[] = "$at has a button that opens no slide.";
                    }
                }
                break;

            case 'flow':
                $steps = $i['steps'] ?? null;
                if (!is_array($steps) || count($steps) < 3) {
                    $e[] = "$at needs at least three steps.";
                    break;
                }
                $ids = [];
                foreach ($steps as $st) {
                    if (empty($st['id']) || trim((string) ($st['label'] ?? '')) === '') {
                        $e[] = "$at has a step with no label.";
                        continue;
                    }
                    $ids[] = $st['id'];
                }
                if (count(array_unique($ids)) !== count($ids)) {
                    $e[] = "$at repeats a step id.";
                }
                break;
        }

        return $e;
    }

    /**
     * Every slide a choice, a button or a slide's own Continue names has to exist (lib/study-deck/interactions.ts branchProblem()).
     *
     * @param array<int,array<string,mixed>> $byN
     * @return array<int,string>
     */
    private static function targets(int $n, array $slide, array $byN): array
    {
        $e = [];
        $next = $slide['next'] ?? null;
        if ($next !== null && $next !== ProScenarioFormat::WAIT && !isset($byN[(int) $next])) {
            $e[] = "Slide $n continues to slide $next, which does not exist.";
        }
        foreach (self::exits($slide) as [$what, $to]) {
            if (!isset($byN[$to])) {
                $e[] = "Slide $n: $what opens slide $to, which does not exist.";
            }
        }

        return $e;
    }

    // ---- graph ---------------------------------------------------------------------------------------------------------------

    /**
     * What a decision must be: each answer opens a slide of its own, an outcome for that very answer; and at least one answer is the
     * sound one (otherwise the learner could only ever be told they chose badly).
     *
     * @param array<int,array<string,mixed>> $byN
     * @return array<int,string>
     */
    private static function decision(int $n, array $slide, array $byN, bool $pedagogy = true): array
    {
        $e = [];
        $i = $slide['interaction'];
        $opened = [];
        $sound = [];
        foreach ($i['options'] as $o) {
            $to = (int) $o['goto'];
            $target = $byN[$to] ?? null;
            if (($target['interaction']['kind'] ?? null) !== 'outcome') {
                $e[] = "Slide $n: answer " . strtoupper((string) $o['id']) . " opens slide $to, which is not an outcome slide, so the learner would get no consequence or feedback.";
                continue;
            }
            if (isset($opened[$to])) {
                $e[] = "Slide $n: answers " . strtoupper($opened[$to]) . ' and ' . strtoupper((string) $o['id']) . " open the same slide $to; every answer needs its own consequence.";
            }
            $opened[$to] = (string) $o['id'];
            $c = $target['interaction']['choice'];
            if (($c['id'] ?? null) !== $o['id'] || self::norm((string) ($c['text'] ?? '')) !== self::norm((string) $o['text'])) {
                $e[] = "Slide $to does not describe the answer that opens it (slide $n, answer " . strtoupper((string) $o['id']) . ').';
            }
            if (($target['interaction']['tone'] ?? null) === ProScenarioFormat::TONE_SOUND) {
                $sound[] = $to;
            }
            // A way back to the question, so a learner can always choose again.
            if (!in_array($n, array_column((array) $target['interaction']['actions'], 'goto'), true)) {
                $e[] = "Slide $to has no way back to the question on slide $n.";
            }
        }
        if (!$pedagogy) {
            return $e;
        }
        if ($sound === []) {
            $e[] = "Slide $n: none of the answers leads to a sound outcome.";
        }
        if (count($sound) === count($i['options']) && count($i['options']) > 1) {
            $e[] = "Slide $n: every answer is sound, so there is no decision to make.";
        }
        // Wording that gives the answer away by itself (never, always, guess) on only some of the answers.
        $tell = array_values(array_filter($i['options'], fn ($o) => StorySkeleton::givesAway((string) $o['text'])));
        if ($tell !== [] && count($tell) < count($i['options'])) {
            $e[] = 'Slide ' . $n . ': the answer "' . mb_substr((string) $tell[0]['text'], 0, 50) . '" gives itself away with a word like never, always or guess.';
        }
        // The sound path has to carry on to the end without going back through this question.
        foreach ($sound as $to) {
            if (!self::reaches($to, max(array_keys($byN)), $byN, [$n])) {
                $e[] = "Slide $to is the sound outcome of slide $n but leads nowhere on, except back to the question.";
            }
        }

        return $e;
    }

    /**
     * Every slide can be reached from slide 1, and from every slide the last one can still be reached.
     *
     * @param array<int,array<string,mixed>> $byN
     * @return array<int,string>
     */
    private static function reach(array $byN, int $last): array
    {
        $e = [];
        $seen = self::walk(1, $byN);
        foreach (array_keys($byN) as $n) {
            if (!isset($seen[$n])) {
                $e[] = "Slide $n can never be reached from the start.";
            }
        }
        foreach (array_keys($byN) as $n) {
            if ($n !== $last && !self::reaches($n, $last, $byN)) {
                $e[] = "From slide $n the learner can never reach the end.";
            }
        }

        return $e;
    }

    /**
     * @param array<int,array<string,mixed>> $byN
     * @return array<int,true>
     */
    private static function walk(int $from, array $byN, array $without = []): array
    {
        $seen = [];
        $queue = [$from];
        while ($queue !== []) {
            $n = array_shift($queue);
            if (isset($seen[$n]) || in_array($n, $without, true) || !isset($byN[$n])) {
                continue;
            }
            $seen[$n] = true;
            foreach (self::successors($n, $byN) as $to) {
                $queue[] = $to;
            }
        }

        return $seen;
    }

    /** @param array<int,array<string,mixed>> $byN @param array<int,int> $without slides that may not be passed through */
    private static function reaches(int $from, int $to, array $byN, array $without = []): bool
    {
        return isset(self::walk($from, $byN, $without)[$to]);
    }

    /**
     * Where the learner can go from a slide: its buttons and answers, its Continue (the slide it names, or the next in order), never
     * past a decision that is waiting for an answer.
     *
     * @param array<int,array<string,mixed>> $byN
     * @return array<int,int>
     */
    private static function successors(int $n, array $byN): array
    {
        $s = $byN[$n];
        $to = array_column(self::exits($s), 1);
        $next = $s['next'] ?? null;
        if ($next === null) {
            if (isset($byN[$n + 1])) {
                $to[] = $n + 1;
            }
        } elseif ($next !== ProScenarioFormat::WAIT) {
            $to[] = (int) $next;
        }

        return array_values(array_unique(array_map('intval', $to)));
    }

    /** @return array<int,array{0:string,1:int}> [what, slide] for every answer and button of a slide */
    private static function exits(array $slide): array
    {
        $i = $slide['interaction'] ?? null;
        $out = [];
        if (is_array($i) && ($i['kind'] ?? null) === 'decision') {
            foreach ((array) $i['options'] as $o) {
                $out[] = ['answer ' . strtoupper((string) ($o['id'] ?? '?')), (int) ($o['goto'] ?? 0)];
            }
        } elseif (is_array($i) && ($i['kind'] ?? null) === 'outcome') {
            foreach ((array) $i['actions'] as $a) {
                $out[] = ['the "' . ($a['label'] ?? '?') . '" button', (int) ($a['goto'] ?? 0)];
            }
        }

        return $out;
    }

    private static function norm(string $s): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s)));
    }
}
