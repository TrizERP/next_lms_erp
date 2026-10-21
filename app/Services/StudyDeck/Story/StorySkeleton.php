<?php

namespace App\Services\StudyDeck\Story;

use App\Services\StudyDeck\Branching\ScenarioBlueprint;
use App\Services\StudyDeck\DiagramRenderer;

/**
 * The SKELETON of one story act: which nodes there are, what each is for, which concepts each teaches and where each choice leads,
 * with no prose yet.
 *
 * WHY THE STRUCTURE IS DECIDED BEFORE ANY WORDS ARE WRITTEN is the same reason ScenarioBlueprint gives for the H5P pipeline: if one
 * call wrote the whole story, "every path ends, every branch connects, every path teaches every concept" would be a hope. Here the
 * skeleton is checked deterministically (StoryGraph::structure(), which runs the same graph rules as the H5P pipeline) and the model
 * is sent back with the exact errors until it passes; only then does the writer fill in text, and it cannot add, remove or rewire a
 * node. The vocabulary (node kinds, scene and decision purposes, choice qualities, ending outcomes) is ScenarioBlueprint's own.
 *
 * A normalised skeleton:
 *   {title, premise, nodes: [
 *      {id, kind: scene, purpose, concept_ids, covers, next, visual?}
 *      {id, kind: decision, purpose, concept_ids, asks, choices: [{id, intent, quality, next}], visual?}
 *      {id, kind: ending, purpose: summary, outcome, concept_ids, covers}]}
 *
 * PURE: arrays in, arrays out.
 */
final class StorySkeleton
{
    /** Decision purposes a story may use: the bank-question "check" of the H5P pipeline is not part of a story. */
    public const DECISION_PURPOSES = ['investigate', 'predict', 'apply'];

    /** Words that mark a choice as the wrong one before the learner has thought: a decision whose weak choice alone carries one is a quiz in story clothes. */
    public static function givesAway(string $text): bool
    {
        return (bool) preg_match('/\b(never|always|absolute(ly)?|unbreakable|blind(ly)?|randomly|guess(es|ing)?|untested)\b/i', $text);
    }

    /**
     * @param mixed $raw the decoded JSON reply
     * @param array{key:string,kind:string,concept_ids:array<int,int>,required_ids:array<int,int>} $act
     * @param array<string,mixed> $config StoryTemplate::resolve()['config']
     * @return array{skeleton:array<string,mixed>,errors:array<int,string>}
     */
    public static function check(mixed $raw, array $act, array $config): array
    {
        $errors = [];
        $raw = is_array($raw) ? $raw : [];
        $sk = ['title' => self::str($raw['title'] ?? ''), 'premise' => self::str($raw['premise'] ?? ''), 'nodes' => []];
        if ($sk['title'] === '') {
            $errors[] = 'Give the act a "title" (a short story title in sentence case).';
        } elseif (\App\Services\StudyDeck\CardTitle::isGeneric($sk['title']) || preg_match('/^(?:act|scene|part|story|level|chapter)\\s*#?\\d{1,3}$/iu', $sk['title'])) {
            $errors[] = 'The act title "' . $sk['title'] . '" is a placeholder; name the story.';
        }
        if ($sk['premise'] === '') {
            $errors[] = 'Give the act a "premise": one or two sentences on where the story is and what the learner is trying to do.';
        }

        $rawNodes = array_values(array_filter((array) ($raw['nodes'] ?? []), 'is_array'));
        if ($rawNodes === []) {
            return ['skeleton' => $sk, 'errors' => array_merge($errors, ['"nodes" is empty.'])];
        }

        $allowed = array_map('intval', $act['concept_ids']);
        $seen = [];
        foreach ($rawNodes as $i => $r) {
            $id = self::str($r['id'] ?? '');
            $kind = strtolower(self::str($r['kind'] ?? ''));
            $label = $id !== '' ? 'Node "' . $id . '"' : 'Node #' . ($i + 1);
            if ($id === '' || !preg_match('/^[A-Za-z0-9_\-]{1,24}$/', $id)) {
                $errors[] = $label . ' needs an "id" of letters, digits, - or _ (at most 24 characters).';
                continue;
            }
            if (isset($seen[$id])) {
                $errors[] = 'The id "' . $id . '" is used twice.';
                continue;
            }
            $seen[$id] = true;
            if (!in_array($kind, ScenarioBlueprint::KINDS, true)) {
                $errors[] = $label . ' has kind "' . $kind . '"; use scene, decision or ending.';
                continue;
            }

            $concepts = [];
            foreach ((array) ($r['concept_ids'] ?? []) as $c) {
                $c = (int) $c;
                if (!in_array($c, $allowed, true)) {
                    $errors[] = $label . ' cites concept ' . $c . ', which is not one of this act\'s concepts (' . implode(', ', $allowed) . ').';
                } else {
                    $concepts[$c] = $c;
                }
            }
            $node = ['id' => $id, 'kind' => $kind, 'purpose' => strtolower(self::str($r['purpose'] ?? '')), 'concept_ids' => array_values($concepts)];

            if ($kind === 'scene') {
                if (!in_array($node['purpose'], ScenarioBlueprint::SCENE_PURPOSES, true)) {
                    $errors[] = $label . ' is a scene with purpose "' . $node['purpose'] . '"; use ' . implode(', ', ScenarioBlueprint::SCENE_PURPOSES) . '.';
                }
                $node['covers'] = self::str($r['covers'] ?? '');
                $node['next'] = self::str($r['next'] ?? '') ?: null;
                if ($node['covers'] === '') {
                    $errors[] = $label . ' needs "covers": one sentence on what this scene shows or teaches.';
                }
                if ($node['concept_ids'] === [] && $node['purpose'] !== 'situation') {
                    $errors[] = $label . ' teaches no concept; list the concept_ids it explains (only the opening scene may teach none).';
                }
            } elseif ($kind === 'ending') {
                $node['purpose'] = 'summary';
                $node['outcome'] = strtolower(self::str($r['outcome'] ?? ''));
                $node['covers'] = self::str($r['covers'] ?? '');
                if (!in_array($node['outcome'], ScenarioBlueprint::OUTCOMES, true)) {
                    $errors[] = $label . ' is an ending with outcome "' . $node['outcome'] . '"; use ' . implode(', ', ScenarioBlueprint::OUTCOMES) . '.';
                }
                if ($node['covers'] === '') {
                    $errors[] = $label . ' needs "covers": what this path shows and what to take from it.';
                }
            } else {
                if (!in_array($node['purpose'], self::DECISION_PURPOSES, true)) {
                    $errors[] = $label . ' is a decision with purpose "' . $node['purpose'] . '"; use ' . implode(', ', self::DECISION_PURPOSES) . '.';
                }
                $node['asks'] = self::str($r['asks'] ?? '');
                if ($node['asks'] === '') {
                    $errors[] = $label . ' needs "asks": the decision put to the learner, in one sentence.';
                }
                $node['choices'] = [];
                $choices = array_values(array_filter((array) ($r['choices'] ?? []), 'is_array'));
                if (count($choices) < 2 || count($choices) > 4) {
                    $errors[] = $label . ' needs 2 to 4 "choices" (it has ' . count($choices) . ').';
                }
                $best = 0;
                foreach ($choices as $k => $c) {
                    $quality = strtolower(self::str($c['quality'] ?? ''));
                    if (!in_array($quality, ScenarioBlueprint::QUALITIES, true)) {
                        $errors[] = 'Choice ' . ($k + 1) . ' of ' . $label . ' has quality "' . $quality . '"; use best, partial or misstep.';
                    }
                    $best += $quality === 'best' ? 1 : 0;
                    $intent = self::str($c['intent'] ?? '');
                    if ($intent === '') {
                        $errors[] = 'Choice ' . ($k + 1) . ' of ' . $label . ' needs "intent": what this choice represents.';
                    }
                    $node['choices'][] = ['id' => $id . '.' . ($k + 1), 'intent' => $intent, 'quality' => $quality, 'next' => self::str($c['next'] ?? '') ?: null];
                }
                if ($choices !== [] && $best < 1) {
                    $errors[] = $label . ' needs at least one "best" choice.';
                }
                if ($choices !== [] && $best === count($choices)) {
                    $errors[] = $label . ' has only "best" choices; include a tempting less sound one (partial or misstep).';
                }
                // Plausible approaches, not a right answer and a wrong one: every decision has a "partial" choice (a reasonable approach with
                // a real cost or blind spot), and at most one "misstep".
                $qualities = array_count_values(array_column($node['choices'], 'quality'));
                $marked = array_values(array_filter($node['choices'], fn ($c) => self::givesAway($c['intent'])));
                if ($marked !== [] && count($marked) < count($node['choices'])) {
                    $errors[] = $label . ' gives away its weaker choice: "' . mb_substr($marked[0]['intent'], 0, 60) . '" uses a word like never, always, guess or blindly that the other choices do not. Word every intent as an approach a sensible student could take, in the same tone and similar length.';
                }
                if ($choices !== [] && ($qualities['partial'] ?? 0) < 1) {
                    $errors[] = $label . ' needs at least one "partial" choice: a reasonable approach that has a real cost or leaves something out, so the decision is a trade-off and not a right answer against a wrong one.';
                }
                if (($qualities['misstep'] ?? 0) > 1) {
                    $errors[] = $label . ' has more than one "misstep"; keep at most one, and make it a mistake a student would really make.';
                }
            }

            // Where a real picture could be found for the scene: a concrete thing that can be photographed (an object, an instrument, a place), 2 to 6 words.
            if ($kind !== 'decision') {
                $queries = [];
                foreach (array_slice((array) ($r['image_queries'] ?? (isset($r['image_query']) ? [$r['image_query']] : [])), 0, 2) as $q) {
                    $q = self::str($q);
                    $wc = count(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: []);
                    if ($q !== '' && $wc >= 1 && $wc <= 6 && !preg_match('/\d/', $q)) {
                        $queries[] = $q;
                    }
                }
                if ($queries !== []) {
                    $node['image_queries'] = $queries;
                }
            }

            // A scene that is a step of the scientific process shows the four-step sequence with its step in focus.
            $step = strtolower(self::str($r['process_step'] ?? ''));
            if ($step !== '') {
                if (!StoryProcess::valid($step)) {
                    $errors[] = $label . ' has process_step "' . $step . '"; use ' . implode(', ', array_keys(StoryProcess::STEPS)) . ' or leave it out.';
                } elseif (isset($r['visual']) && is_array($r['visual'])) {
                    $errors[] = $label . ' has both a process_step and a visual; a node shows one drawn diagram, so keep one of them.';
                } else {
                    $node['process_step'] = $step;
                }
            }

            // A drawn picture is a diagram built from what the chapter says; a photograph comes from the image search (image_queries).
            if (isset($r['visual']) && is_array($r['visual'])) {
                $spec = $r['visual'];
                if (($problem = DiagramRenderer::problems($spec)) !== []) {
                    $errors[] = $label . ' has a visual that cannot be drawn: ' . implode('; ', array_slice($problem, 0, 2)) . '.';
                } else {
                    $node['visual'] = $spec;
                }
            }
            $sk['nodes'][] = $node;
        }

        // A mistake must be explained, not just punished: a "misstep" or "partial" choice leads to a scene, never straight to an ending.
        $byId = array_column($sk['nodes'], null, 'id');
        foreach ($sk['nodes'] as $node) {
            foreach ((array) ($node['choices'] ?? []) as $c) {
                $target = $c['next'] !== null ? ($byId[$c['next']] ?? null) : null;
                if (in_array($c['quality'], ['misstep', 'partial'], true) && $target !== null && $target['kind'] === 'ending') {
                    $errors[] = 'Choice ' . $c['id'] . ' (' . $c['quality'] . ': "' . mb_substr((string) $c['intent'], 0, 60) . '") is not the best choice but leads straight to an ending (' . $c['next'] . '); send it to a scene that explains what happened (purpose consequence or remedial), and let that scene go on to an ending. Add that scene if there is none.';
                }
            }
        }
        // Every ending must be earned by some path. A skeleton that sends all branches through one closing scene reaches only one ending.
        $reach = [];
        $queue = [$sk['nodes'][0]['id'] ?? null];
        while (($id = array_shift($queue)) !== null) {
            if (isset($reach[$id]) || !isset($byId[$id])) {
                continue;
            }
            $reach[$id] = true;
            foreach (($byId[$id]['kind'] ?? '') === 'scene' ? [$byId[$id]['next'] ?? null] : array_column((array) ($byId[$id]['choices'] ?? []), 'next') as $t) {
                if ($t !== null && $t !== '') {
                    $queue[] = $t;
                }
            }
        }
        foreach ($sk['nodes'] as $n) {
            if ($n['kind'] === 'ending' && !isset($reach[$n['id']])) {
                array_unshift($errors, 'Ending ' . $n['id'] . ' is not reached by any path. Every ending must be earned by some paths: do not send every branch through one closing scene. Let the consequence scenes after the LAST decision (or a final decision of their own) lead to DIFFERENT endings, for example the scene after the best choice to the "best" ending and the scenes after the weaker choices to the "partial" or "revisit" ending.');
            }
        }
        $pictures = count(array_filter($sk['nodes'], fn ($n) => isset($n['visual']) || isset($n['process_step'])));
        [$plo, $phi] = $config['images'];
        if ($pictures > $phi) {
            $errors[] = "The act has $pictures visuals; the template allows at most $phi per act.";
        }

        // The structure, judged by the shared story rules (graph, merges, budgets, path-by-path coverage).
        $shape = ['nodes' => array_map(fn ($n) => [
            'id' => $n['id'], 'kind' => $n['kind'], 'concept_ids' => $n['concept_ids'],
            'choices' => $n['kind'] === 'scene' ? [['next' => $n['next']]] : array_map(fn ($c) => ['next' => $c['next']], (array) ($n['choices'] ?? [])),
        ], $sk['nodes'])];
        if (($sk['nodes'][0]['purpose'] ?? null) !== 'situation') {
            $errors[] = 'The act must open with a scene whose purpose is "situation": the introduction and the mission.';
        }
        $structure = StoryGraph::structure($shape, array_map('intval', $act['required_ids']), $config, $errors === []);
        $errors = array_merge($errors, $structure['errors']);

        return ['skeleton' => $sk, 'errors' => $errors];
    }

    private static function str(mixed $v): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : ''));
    }
}
