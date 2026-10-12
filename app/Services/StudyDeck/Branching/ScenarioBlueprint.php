<?php

namespace App\Services\StudyDeck\Branching;

use App\Services\lms\H5P\BranchingGraph;

/**
 * The SKELETON of a scenario: which nodes there are, what each is for, and
 * where each choice leads -- with no prose yet.
 *
 * WHY THE STRUCTURE IS DECIDED BEFORE ANY WORDS ARE WRITTEN. If one model call
 * wrote the whole tree at once, "every branch connects, every path ends,
 * every wrong turn is explained" would be a hope. Splitting it in two makes it a
 * guarantee: the planner returns only this skeleton, this class checks it
 * deterministically (and the planner is sent back with the exact errors until it
 * passes), and only then does the writer fill in text. The writer is handed the
 * ids and cannot add, remove or rewire a node, so prose can never break the
 * graph the skeleton proved sound.
 *
 * A NORMALISED SKELETON:
 *
 *   {title, premise, objectives[], nodes: [
 *      {id, kind: scene,    purpose, concept_id, covers, next}
 *      {id, kind: decision, purpose, concept_id, asks,
 *         choices: [{id, intent, quality, next}]}
 *      {id, kind: decision, purpose: check, concept_id, question_id,
 *         on_correct, on_wrong}
 *      {id, kind: ending,   purpose: summary, concept_id, outcome, covers}
 *   ]}
 *
 * PURE: arrays in, arrays out.
 */
final class ScenarioBlueprint
{
    public const KINDS = ['scene', 'decision', 'ending'];

    public const SCENE_PURPOSES = ['situation', 'explanation', 'example', 'consequence', 'remedial', 'reflection', 'summary'];

    public const DECISION_PURPOSES = ['investigate', 'predict', 'apply', 'check'];

    public const QUALITIES = ['best', 'partial', 'misstep'];

    public const OUTCOMES = ['best', 'good', 'partial', 'revisit'];

    /**
     * How big a scenario of each length may be. The range the planner is TOLD
     * is the first pair; the bounds this class enforces are a little wider, so
     * a tree one node over its target is not thrown away.
     *
     * @var array<string,array<string,mixed>>
     */
    public const BUDGETS = [
        'short' => ['nodes' => [8, 13], 'decisions' => [2, 3], 'endings' => [2, 3], 'questions' => 1],
        'standard' => ['nodes' => [11, 17], 'decisions' => [3, 5], 'endings' => [3, 4], 'questions' => 2],
        'extended' => ['nodes' => [17, 26], 'decisions' => [5, 8], 'endings' => [4, 5], 'questions' => 4],
    ];

    /**
     * Normalise a model reply and list everything wrong with it.
     *
     * @param mixed $raw the decoded JSON
     * @return array{blueprint:array<string,mixed>,errors:list<string>}
     */
    public static function check(mixed $raw, SourcePack $pack, string $length): array
    {
        $budget = self::BUDGETS[$length] ?? self::BUDGETS['standard'];
        $errors = [];
        $raw = is_array($raw) ? $raw : [];

        $bp = [
            'title' => self::str($raw['title'] ?? ''),
            'premise' => self::str($raw['premise'] ?? ''),
            'objectives' => array_values(array_filter(array_map([self::class, 'str'], (array) ($raw['objectives'] ?? [])), fn ($o) => $o !== '')),
            'nodes' => [],
        ];

        if ($bp['title'] === '') {
            $errors[] = 'Give the scenario a "title".';
        }
        if ($bp['premise'] === '') {
            $errors[] = 'Give the scenario a "premise": one or two sentences on the real-world situation the learner steps into.';
        }
        if (count($bp['objectives']) < 2 || count($bp['objectives']) > 5) {
            $errors[] = '"objectives" must list 2 to 5 things the learner will be able to do.';
        }
        foreach ($bp['objectives'] as $i => $objective) {
            if (str_word_count($objective) > 28) {
                $errors[] = 'Objective ' . ($i + 1) . ' is ' . str_word_count($objective) . ' words. Shorten it to one sentence of at most 20 words that starts with a verb.';
            }
        }
        if (self::looksLikeTitleCase($bp['title'])) {
            $errors[] = 'Write the title in sentence case: only the first word and proper names start with a capital letter.';
        }

        $rawNodes = array_values(array_filter((array) ($raw['nodes'] ?? []), 'is_array'));
        if ($rawNodes === []) {
            return ['blueprint' => $bp, 'errors' => array_merge($errors, ['"nodes" is empty.'])];
        }

        $chosen = $pack->conceptIds();
        $banks = $pack->questionsById();
        $usedQuestions = [];
        $seen = [];

        foreach ($rawNodes as $i => $r) {
            $id = self::str($r['id'] ?? '');
            $kind = strtolower(self::str($r['kind'] ?? ''));
            $label = $id !== '' ? 'Node "' . $id . '"' : 'Node #' . ($i + 1);

            if ($id === '' || ! preg_match('/^[A-Za-z0-9_\-]{1,24}$/', $id)) {
                $errors[] = $label . ' needs an "id" of letters, digits, - or _ (at most 24 characters).';
                continue;
            }
            if (isset($seen[$id])) {
                $errors[] = 'The id "' . $id . '" is used twice.';
                continue;
            }
            $seen[$id] = true;

            if (! in_array($kind, self::KINDS, true)) {
                $errors[] = $label . ' has kind "' . $kind . '"; use scene, decision or ending.';
                continue;
            }

            $conceptId = isset($r['concept_id']) && $r['concept_id'] !== null && $r['concept_id'] !== '' ? (int) $r['concept_id'] : null;
            if ($conceptId !== null && ! in_array($conceptId, $chosen, true)) {
                $errors[] = $label . ' cites concept ' . $conceptId . ', which is not one of the chosen concepts (' . implode(', ', $chosen) . ').';
                $conceptId = null;
            }

            $node = ['id' => $id, 'kind' => $kind, 'purpose' => strtolower(self::str($r['purpose'] ?? '')), 'concept_id' => $conceptId];

            if ($kind === 'scene') {
                if (! in_array($node['purpose'], self::SCENE_PURPOSES, true)) {
                    $errors[] = $label . ' is a scene with purpose "' . $node['purpose'] . '"; use ' . implode(', ', self::SCENE_PURPOSES) . '.';
                }
                $node['covers'] = self::str($r['covers'] ?? '');
                $node['next'] = self::str($r['next'] ?? '') ?: null;
                if ($node['covers'] === '') {
                    $errors[] = $label . ' needs "covers": one sentence on what this screen teaches or establishes.';
                }
            } elseif ($kind === 'ending') {
                $node['purpose'] = 'summary';
                $node['outcome'] = strtolower(self::str($r['outcome'] ?? ''));
                $node['covers'] = self::str($r['covers'] ?? '');
                if (! in_array($node['outcome'], self::OUTCOMES, true)) {
                    $errors[] = $label . ' is an ending with outcome "' . $node['outcome'] . '"; use ' . implode(', ', self::OUTCOMES) . '.';
                }
                if ($node['covers'] === '') {
                    $errors[] = $label . ' needs "covers": what this path shows and what to take from it.';
                }
            } else {
                if (! in_array($node['purpose'], self::DECISION_PURPOSES, true)) {
                    $errors[] = $label . ' is a decision with purpose "' . $node['purpose'] . '"; use ' . implode(', ', self::DECISION_PURPOSES) . '.';
                }

                $questionId = isset($r['question_id']) && $r['question_id'] !== null && $r['question_id'] !== '' ? (int) $r['question_id'] : null;
                if ($questionId !== null) {
                    $node['purpose'] = 'check';
                    $node['question_id'] = $questionId;
                    $node['on_correct'] = self::str($r['on_correct'] ?? '') ?: null;
                    $node['on_wrong'] = self::str($r['on_wrong'] ?? '') ?: null;
                    if (! isset($banks[$questionId])) {
                        $errors[] = $label . ' uses question ' . $questionId . ', which is not one of the offered questions.';
                    } elseif (isset($usedQuestions[$questionId])) {
                        $errors[] = 'Question ' . $questionId . ' is used twice.';
                    } else {
                        $usedQuestions[$questionId] = true;
                        $node['concept_id'] = (int) $banks[$questionId]['concept_id'];
                    }
                    if ($node['on_correct'] === null || $node['on_wrong'] === null) {
                        $errors[] = $label . ' is a question check, so it needs "on_correct" and "on_wrong" (node ids).';
                    }
                } else {
                    if ($node['purpose'] === 'check') {
                        $errors[] = $label . ' has purpose "check" but no "question_id". A check uses an offered bank question; otherwise use investigate, predict or apply.';
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
                    foreach ($choices as $k => $c) {
                        $quality = strtolower(self::str($c['quality'] ?? ''));
                        if (! in_array($quality, self::QUALITIES, true)) {
                            $errors[] = 'Choice ' . ($k + 1) . ' of ' . $label . ' has quality "' . $quality . '"; use best, partial or misstep.';
                        }
                        $intent = self::str($c['intent'] ?? '');
                        if ($intent === '') {
                            $errors[] = 'Choice ' . ($k + 1) . ' of ' . $label . ' needs "intent": what this choice represents.';
                        }
                        $node['choices'][] = [
                            'id' => $id . '.' . ($k + 1),
                            'intent' => $intent,
                            'quality' => $quality,
                            'next' => self::str($c['next'] ?? '') ?: null,
                        ];
                    }
                    $qualities = array_column($node['choices'], 'quality');
                    if (count($qualities) >= 2 && ! in_array('best', $qualities, true)) {
                        $errors[] = $label . ' has no "best" choice. Every decision needs at least one sound choice.';
                    }
                    if (count($qualities) >= 2 && array_unique($qualities) === ['best']) {
                        $errors[] = $label . ' has only "best" choices. A decision needs a tempting choice that is not best.';
                    }
                }
            }

            $bp['nodes'][] = $node;
        }

        $errors = array_merge($errors, self::structure($bp, $pack, $budget));

        return ['blueprint' => $bp, 'errors' => array_values(array_unique($errors))];
    }

    /**
     * Whole-tree rules, on a skeleton whose nodes are individually well-formed.
     *
     * @param array<string,mixed> $bp
     * @param array<string,mixed> $budget
     * @return list<string>
     */
    private static function structure(array $bp, SourcePack $pack, array $budget): array
    {
        $errors = [];
        $nodes = $bp['nodes'];
        if ($nodes === []) {
            return $errors;
        }

        $byId = [];
        foreach ($nodes as $n) {
            $byId[$n['id']] = $n;
        }

        // The graph rules every scenario shares. The label is the id, so the
        // message names the node the planner has to fix.
        $graphNodes = array_map(fn ($n) => [
            'key' => $n['id'],
            'type' => match ($n['kind']) {
                'scene' => BranchingGraph::TEXT,
                'decision' => BranchingGraph::DECISION,
                default => BranchingGraph::ENDING,
            },
            'label' => 'Node "' . $n['id'] . '"',
            'next' => $n['next'] ?? null,
            'targets' => $n['kind'] === 'decision'
                ? (isset($n['question_id']) ? [$n['on_correct'] ?? null, $n['on_wrong'] ?? null] : array_map(fn ($c) => $c['next'], $n['choices'] ?? []))
                : [],
            'check' => isset($n['question_id']),
        ], $nodes);

        $graph = BranchingGraph::analyse($graphNodes);
        foreach ($graph['errors'] as $e) {
            $errors[] = $e['message'];
        }
        if (empty($graph['stats']['acyclic'])) {
            $errors[] = 'The links contain a loop. Links must only go forward: a node may not lead back to a node that leads to it.';
        }

        // Shape.
        $first = $nodes[0];
        if ($first['kind'] !== 'scene' || $first['purpose'] !== 'situation') {
            $errors[] = 'The first node must be a scene with purpose "situation" that puts the learner in the real-world case.';
        }

        $decisions = array_values(array_filter($nodes, fn ($n) => $n['kind'] === 'decision'));
        $endings = array_values(array_filter($nodes, fn ($n) => $n['kind'] === 'ending'));
        [$nMin, $nMax] = $budget['nodes'];
        [$dMin, $dMax] = $budget['decisions'];
        [$eMin, $eMax] = $budget['endings'];

        if (count($nodes) < $nMin - 2 || count($nodes) > $nMax + 3) {
            $errors[] = 'This scenario has ' . count($nodes) . ' nodes; aim for ' . $nMin . ' to ' . $nMax . '.';
        }
        if (count($decisions) < $dMin || count($decisions) > $dMax + 1) {
            $errors[] = 'This scenario has ' . count($decisions) . ' decisions; use ' . $dMin . ' to ' . $dMax . '.';
        }
        if (count($endings) < $eMin || count($endings) > $eMax + 1) {
            $errors[] = 'This scenario has ' . count($endings) . ' endings; use ' . $eMin . ' to ' . $eMax . '.';
        }

        $outcomes = array_column($endings, 'outcome');
        if ($endings !== [] && ! in_array('best', $outcomes, true)) {
            $errors[] = 'No ending has outcome "best". Reaching the best ending must be possible by deciding soundly.';
        }
        if ($endings !== [] && array_diff($outcomes, ['best']) === []) {
            $errors[] = 'Every ending is "best". Add an ending for a path where the decisions were weaker (outcome good, partial or revisit).';
        }

        $checks = count(array_filter($decisions, fn ($d) => isset($d['question_id'])));
        if ($checks > (int) $budget['questions']) {
            $errors[] = 'This scenario uses ' . $checks . ' bank questions; use at most ' . $budget['questions'] . '. It is a lesson, not a quiz.';
        }
        if (! array_filter($decisions, fn ($d) => in_array($d['purpose'], ['investigate', 'predict'], true))) {
            $errors[] = 'Include at least one decision with purpose "investigate" or "predict": a choice of what to look into or expect, before the answer is shown.';
        }
        if (! array_filter($nodes, fn ($n) => $n['kind'] === 'scene' && in_array($n['purpose'], ['consequence', 'remedial'], true))) {
            $errors[] = 'Include a scene with purpose "consequence" or "remedial" that explains what follows from a weaker choice.';
        }

        // A weaker choice must TEACH something, not only end.
        $explains = fn (?array $target) => $target !== null && (
            ($target['kind'] === 'scene' && in_array($target['purpose'], ['consequence', 'remedial'], true))
            || ($target['kind'] === 'ending' && in_array($target['outcome'] ?? '', ['partial', 'revisit'], true))
        );
        foreach ($decisions as $d) {
            if (isset($d['question_id'])) {
                if (! empty($d['on_wrong']) && isset($byId[$d['on_wrong']]) && ! $explains($byId[$d['on_wrong']])) {
                    $errors[] = 'Node "' . $d['id'] . '": "on_wrong" must lead to a scene with purpose consequence or remedial (or a partial/revisit ending), so a wrong answer is explained.';
                }
                continue;
            }
            foreach ($d['choices'] as $k => $c) {
                if ($c['quality'] === 'misstep' && ! empty($c['next']) && isset($byId[$c['next']]) && ! $explains($byId[$c['next']])) {
                    $errors[] = 'Choice ' . ($k + 1) . ' of node "' . $d['id'] . '" is a misstep, so it must lead to a scene with purpose consequence or remedial (or a partial/revisit ending) that explains what went wrong.';
                }
            }
        }

        // Every chosen concept is taught somewhere.
        $covered = array_unique(array_filter(array_column($nodes, 'concept_id')));
        $missing = array_diff($pack->conceptIds(), $covered);
        if ($missing !== []) {
            $names = array_map(fn ($id) => $pack->concepts[$id]['name'] . ' (' . $id . ')', $missing);
            $errors[] = 'These chosen concepts are not taught by any node: ' . implode(', ', $names) . '. Give each at least one node with its concept_id.';
        }

        return $errors;
    }

    /**
     * "Will It Clear the Rope? Modelling a Cricket Six" -- most of its longer words
     * capitalised. A proper name or two in a sentence-case title do not trip it.
     */
    public static function looksLikeTitleCase(string $title): bool
    {
        $all = array_values(array_filter(preg_split('/[^\p{L}\p{N}\']+/u', $title) ?: [], fn ($w) => $w !== ''));
        // The first word is capitalised in any title; it says nothing.
        array_shift($all);
        $words = array_values(array_filter($all, fn ($w) => mb_strlen($w) >= 4));
        if (count($words) < 3) {
            return false;
        }
        $capitalised = count(array_filter($words, fn ($w) => preg_match('/^\p{Lu}\p{Ll}/u', $w) === 1));

        return $capitalised >= 3 && $capitalised / count($words) >= 0.6;
    }

    /** @param mixed $value */
    private static function str(mixed $value): string
    {
        return is_scalar($value) ? trim((string) preg_replace('/\s+/u', ' ', (string) $value)) : '';
    }
}
