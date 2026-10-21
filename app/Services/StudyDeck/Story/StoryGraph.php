<?php

namespace App\Services\StudyDeck\Story;

use App\Services\lms\H5P\BranchingGraph;

/**
 * The rules a story act (a Study Deck V3 `scenario` interaction made with the Story-Based Branching Scenario template) must meet
 * before a learner is given it. PURE: arrays in, arrays out, no database, so the same check can run in the deck validator, the
 * quality check, the generator and the tests.
 *
 * Two levels, one set of rules:
 *   - structure(): what a SKELETON (kinds, where each way on leads, which concepts each node teaches; no prose yet) must satisfy.
 *     The generator checks the skeleton with this BEFORE any prose is written, so the writer can never break a graph that was proved
 *     sound. The graph rules (a reference to a node that is not there, a node nobody can reach, a node from which no ending can be
 *     reached, a decision with fewer than two choices) are not written again: the story is mapped onto the nodes
 *     `App\Services\lms\H5P\BranchingGraph` already checks for the H5P branching pipeline, and that class judges them.
 *   - check(): structure() plus what only finished prose has: scene and choice wording, titles, mission, pictures.
 *
 * A story node:
 *   {id, kind: scene|decision|ending, title, body, prompt?, image?, concept_ids?: int[],
 *    choices: [{id, text, outcome, why, sound, next}]}
 * a scene has exactly one choice (its "Continue"), a decision two to four, an ending none. `outcome` is the story event that
 * follows a choice and `why` is the explanation, so two choices at a decision give two different events and explanations.
 */
final class StoryGraph
{
    public const SCENE = 'scene';

    public const DECISION = 'decision';

    public const ENDING = 'ending';

    public const KINDS = [self::SCENE, self::DECISION, self::ENDING];

    /**
     * The finished story: structure and prose.
     *
     * @param array<string,mixed> $interaction the slide's interaction
     * @param array<int,int> $requiredConceptIds concepts every path through the act must teach
     * @param array<string,mixed> $config StoryTemplate::resolve()['config']
     * @return array{errors:array<int,string>,stats:array<string,mixed>}
     */
    public static function check(array $interaction, array $requiredConceptIds, array $config): array
    {
        $nodes = array_values((array) ($interaction['nodes'] ?? []));
        $stats = ['nodes' => count($nodes), 'scenes' => 0, 'decisions' => 0, 'endings' => 0, 'paths' => 0, 'merges' => 0];

        if (($interaction['template'] ?? null) !== StoryTemplate::KEY) {
            return ['errors' => ['The interaction is not made with the "' . StoryTemplate::NAME . '" template.'], 'stats' => $stats];
        }
        if ($nodes === []) {
            return ['errors' => ['The story has no scenes.'], 'stats' => $stats];
        }

        $errors = [];
        $words = fn (string $s): int => count(preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        // Which nodes lead to each node: a node reached from two or more is a merge, and its text must say which branch the learner came by.
        $preds = [];
        foreach ($nodes as $node) {
            foreach ((array) ($node['choices'] ?? []) as $ch) {
                if (($ch['next'] ?? '') !== '' && ($ch['next'] ?? null) !== null) {
                    $preds[(string) $ch['next']][(string) ($node['id'] ?? '')] = true;
                }
            }
        }
        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            $kind = $node['kind'] ?? null;
            $choices = array_values((array) ($node['choices'] ?? []));
            if (!in_array($kind, self::KINDS, true)) {
                continue; // structure() names it
            }
            $explain = $node['explain'] ?? null;
            if ($kind === self::SCENE && !empty($node['concept_ids']) && !is_array($explain)) {
                $errors[] = "Scene $id teaches a concept but explains none of it; add an \"explain\" with the idea in plain language.";
            }
            if (is_array($explain)) {
                [$elo, $ehi] = $config['explain_words'];
                $ew = $words((string) ($explain['body'] ?? ''));
                if ($ew < $elo || $ew > $ehi) {
                    $errors[] = "The explanation in $id is $ew words; it must be $elo to $ehi.";
                }
            }
            $variants = array_values((array) ($node['variants'] ?? []));
            $from = array_keys($preds[$id] ?? []);
            if ($kind !== self::DECISION && count($from) >= 2) {
                $byAfter = [];
                foreach ($variants as $v) {
                    $byAfter[(string) ($v['after'] ?? '')][] = self::normText((string) ($v['body'] ?? ''));
                }
                foreach ($from as $p) {
                    if (empty($byAfter[$p])) {
                        $errors[] = "Node $id is reached from $p and from other scenes, so it needs a variant for the way the learner came from $p (what changes here because of that path).";
                    }
                }
            }
            if ($variants !== [] && ($kind === self::DECISION || count($from) < 2)) {
                $errors[] = "Node $id has variants but is not a scene or ending where branches meet.";
            }
            [$vlo, $vhi] = $config['variant_words'];
            $seenVariant = [];
            foreach ($variants as $v) {
                $vw = $words((string) ($v['body'] ?? ''));
                if ($vw < $vlo || $vw > $vhi) {
                    $errors[] = "A variant of $id is $vw words; it must be $vlo to $vhi.";
                }
                if (!in_array((string) ($v['after'] ?? ''), $from, true)) {
                    $errors[] = "A variant of $id names \"" . (string) ($v['after'] ?? '') . '", which does not lead to it.';
                }
                $seenVariant[] = self::normText((string) ($v['body'] ?? ''));
            }
            if (count(array_unique($seenVariant)) < count($seenVariant)) {
                $errors[] = "The variants of $id must differ: each says what changed on that path.";
            }
            [$min, $max] = $config['scene_words'];
            $n = $words((string) ($node['body'] ?? ''));
            if ($kind !== self::DECISION || $n > 0) {
                // A decision's body is only an optional set-up before the question, so it has a ceiling but no minimum.
                if (($kind !== self::DECISION && $n < $min) || $n > $max) {
                    $what = $kind === self::ENDING ? 'ending' : 'scene';
                    $errors[] = $n < $min
                        ? "Node $id narrates only $n words; a story $what needs at least $min. Add two or three sentences (what a character notices or says, why it matters) so it reaches about " . ($min + 25) . " words, and keep the rest as it is."
                        : "Node $id narrates $n words; a story $what needs at most $max. Shorten it.";
                }
            }
            $title = trim((string) ($node['title'] ?? ''));
            if ($title === '') {
                $errors[] = "Node $id has no title.";
            } elseif (preg_match('/^(?:scene|part|act|step|node|level|chapter|ending|decision)\\s*#?\\d{1,3}$/iu', $title)) {
                $errors[] = "The title \"$title\" of node $id is a placeholder; say what the scene is about.";
            }
            if ($kind === self::DECISION) {
                if (trim((string) ($node['prompt'] ?? '')) === '') {
                    $errors[] = "Decision $id has no question to decide.";
                }
                $sound = 0;
                $events = [];
                $whys = [];
                foreach ($choices as $ch) {
                    $sound += !empty($ch['sound']) ? 1 : 0;
                    foreach (['text' => 'words for the choice', 'outcome' => 'story event', 'why' => 'explanation'] as $f => $what) {
                        if (trim((string) ($ch[$f] ?? '')) === '') {
                            $errors[] = "A choice in decision $id has no $what.";
                        }
                    }
                    [$lo, $hi] = $config['choice_words'];
                    $t = $words((string) ($ch['text'] ?? ''));
                    if ($t > 0 && ($t < $lo || $t > $hi)) {
                        $errors[] = "A choice in decision $id is $t words; it must be $lo to $hi.";
                    }
                    [$lo, $hi] = $config['event_words'];
                    foreach (['outcome' => 'story event', 'why' => 'explanation'] as $f => $what) {
                        $t = $words((string) ($ch[$f] ?? ''));
                        if ($t > 0 && ($t < $lo || $t > $hi)) {
                            $errors[] = "The $what of a choice in decision $id is $t words; it must be $lo to $hi.";
                        }
                    }
                    $events[] = mb_strtolower(trim((string) ($ch['outcome'] ?? '')));
                    $whys[] = mb_strtolower(trim((string) ($ch['why'] ?? '')));
                }
                if ($sound < 1) {
                    $errors[] = "Decision $id has no sound choice.";
                }
                if (count(array_unique($events)) < count($events) || count(array_unique($whys)) < count($whys)) {
                    $errors[] = "The choices of decision $id must give different story events and different explanations.";
                }
            }
            $extras = array_values((array) ($node['extra_images'] ?? []));
            if (count($extras) > 2) {
                $errors[] = "Node $id has " . count($extras) . ' supporting pictures; at most two.';
            }
            if ($extras !== [] && empty($node['image'])) {
                $errors[] = "Node $id has supporting pictures but no main picture.";
            }
            foreach (array_merge(isset($node['image']) && is_array($node['image']) ? [$node['image']] : [], array_filter($extras, 'is_array')) as $img) {
                if (trim((string) ($img['url'] ?? '')) === '' || (int) ($img['width'] ?? 0) < 1 || (int) ($img['height'] ?? 0) < 1 || trim((string) ($img['alt'] ?? '')) === '') {
                    $errors[] = "A picture of $id needs an address, a size and alternative text.";
                }
                if (isset($img['focus']) && StoryPixelJudge::region($img['focus']) === null) {
                    $errors[] = "The highlighted part of a picture of $id is not a rectangle inside the picture.";
                }
            }
        }
        if ((string) ($interaction['start'] ?? '') !== (string) ($nodes[0]['id'] ?? '')) {
            $errors[] = 'The story must start at its first scene.';
        }
        if (empty($interaction['mission']['title']) || empty($interaction['mission']['body'])) {
            $errors[] = 'The story needs a mission (a title and what the learner is trying to do).';
        }
        $pictures = array_sum(array_map(fn ($n) => (empty($n['image']) ? 0 : 1) + count((array) ($n['extra_images'] ?? [])), $nodes));
        [$plo, $phi] = $config['images'];
        if ($pictures < $plo || $pictures > $phi) {
            $errors[] = "The act has $pictures pictures; the template allows $plo to $phi per act.";
        }

        $structure = self::structure($interaction, $requiredConceptIds, $config, $errors === []);

        return ['errors' => array_merge($errors, $structure['errors']), 'stats' => $structure['stats']];
    }

    private static function normText(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)));
    }

    /**
     * The structure alone: this is what the skeleton is held to before any prose is written.
     *
     * @param array<string,mixed> $interaction only `nodes[].{id,kind,concept_ids,choices[].next}` are read
     * @param array<int,int> $requiredConceptIds
     * @param array<string,mixed> $config
     * @param bool $coverage whether to also check path-by-path concept coverage (only meaningful when nothing else is wrong)
     * @return array{errors:array<int,string>,stats:array<string,mixed>}
     */
    public static function structure(array $interaction, array $requiredConceptIds, array $config, bool $coverage = true): array
    {
        $errors = [];
        $nodes = array_values((array) ($interaction['nodes'] ?? []));
        $stats = ['nodes' => count($nodes), 'scenes' => 0, 'decisions' => 0, 'endings' => 0, 'paths' => 0, 'merges' => 0];
        if ($nodes === []) {
            return ['errors' => ['The story has no scenes.'], 'stats' => $stats];
        }

        $index = [];
        foreach ($nodes as $i => $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id === '' || isset($index[$id])) {
                $errors[] = 'Node ' . ($i + 1) . ' needs its own unique id.';
                continue;
            }
            $index[$id] = $i;
        }
        foreach ($nodes as $i => $node) {
            $id = (string) ($node['id'] ?? '');
            $kind = $node['kind'] ?? null;
            $choices = array_values((array) ($node['choices'] ?? []));
            if (!in_array($kind, self::KINDS, true)) {
                $errors[] = "Node $id has kind \"" . (string) $kind . '"; it must be scene, decision or ending.';
                continue;
            }
            $stats[$kind . 's']++;
            if ($kind === self::SCENE && count($choices) !== 1) {
                $errors[] = "Scene $id must have exactly one way on (its Continue); it has " . count($choices) . '.';
            } elseif ($kind === self::DECISION && (count($choices) < 2 || count($choices) > 4)) {
                $errors[] = "Decision $id needs 2 to 4 choices; it has " . count($choices) . '.';
            } elseif ($kind === self::ENDING && $choices !== []) {
                $errors[] = "Ending $id must have no choices.";
            }
            if ($kind === self::DECISION) {
                $nexts = array_map(fn ($c) => (string) ($c['next'] ?? ''), $choices);
                if (count(array_unique($nexts)) < count($nexts)) {
                    $errors[] = "Two choices of decision $id lead to the same scene; each choice needs its own consequence scene, so what the learner decides changes what happens next.";
                }
            }
            foreach ($choices as $ch) {
                $next = $ch['next'] ?? null;
                if ($next === null || $next === '') {
                    $errors[] = "A choice in $id leads nowhere; only an ending may stop the story.";
                } elseif (!isset($index[(string) $next])) {
                    $errors[] = "A choice in $id leads to \"$next\", which does not exist.";
                } elseif ($index[(string) $next] <= $i) {
                    $errors[] = "A choice in $id leads back to \"$next\"; the story only moves forward.";
                }
            }
        }
        if (($nodes[0]['kind'] ?? null) !== self::SCENE) {
            $errors[] = 'The story must open with a scene (the introduction and mission), not a decision.';
        }

        // ---- size and balance
        foreach (['nodes' => $stats['nodes'], 'decisions' => $stats['decisions'], 'endings' => $stats['endings']] as $what => $n) {
            [$lo, $hi] = $config[$what];
            if ($n < $lo || $n > $hi) {
                $errors[] = "The story has $n $what; the template allows $lo to $hi per act.";
            }
        }
        $threes = count(array_filter($nodes, fn ($n) => ($n['kind'] ?? null) === self::DECISION && count((array) ($n['choices'] ?? [])) >= 3));
        $needThree = min((int) ($config['three_choice_min'] ?? 0), $stats['decisions']);
        if ($threes < $needThree) {
            $errors[] = "Only $threes of the act's decisions offer three or more choices; at least $needThree should, each a plausible approach with its own trade-off.";
        }
        if ($stats['nodes'] > 0 && $stats['decisions'] / $stats['nodes'] > $config['decision_share_max']) {
            $errors[] = 'Too many of the nodes are decisions (' . $stats['decisions'] . ' of ' . $stats['nodes'] . '); a story is mostly scenes, not a quiz.';
        }

        // ---- the graph, judged by the rules the H5P branching pipeline already uses
        $mapped = [];
        $in = [];
        foreach ($nodes as $node) {
            $kind = $node['kind'] ?? null;
            $targets = array_map(fn ($c) => ($c['next'] ?? null) === '' ? null : ($c['next'] ?? null), array_values((array) ($node['choices'] ?? [])));
            foreach ($targets as $t) {
                if ($t !== null) {
                    $in[(string) $t] = ($in[(string) $t] ?? 0) + 1;
                }
            }
            $key = (string) ($node['id'] ?? '');
            $mapped[] = match ($kind) {
                self::DECISION => ['key' => $key, 'type' => BranchingGraph::DECISION, 'label' => (string) ($node['title'] ?? $key), 'targets' => $targets],
                self::ENDING => ['key' => $key, 'type' => BranchingGraph::ENDING, 'label' => (string) ($node['title'] ?? $key)],
                default => ['key' => $key, 'type' => BranchingGraph::TEXT, 'label' => (string) ($node['title'] ?? $key), 'next' => $targets[0] ?? null],
            };
        }
        $analysis = BranchingGraph::analyse($mapped);
        foreach ($analysis['errors'] as $e) {
            $errors[] = $e['message'];
        }

        // ---- merges: branches that come back together
        $stats['merges'] = count(array_filter($in, fn ($n) => $n >= 2));
        if (!empty($config['merge_required']) && $stats['decisions'] >= 2 && $stats['merges'] < 1) {
            $errors[] = 'The branches never come back together; at least one scene must be reached from two different choices.';
        }

        // ---- every path teaches every required concept (a merge may do it for the paths that meet there)
        $paths = $errors === [] ? BranchingGraph::enumeratePaths($mapped) : [];
        $stats['paths'] = count($paths);
        $conceptsOf = [];
        foreach ($nodes as $node) {
            $conceptsOf[(string) ($node['id'] ?? '')] = array_map('intval', (array) ($node['concept_ids'] ?? []));
        }
        if ($coverage && $requiredConceptIds !== [] && $errors === []) {
            foreach ($paths as $path) {
                $taught = [];
                foreach ($path as $key) {
                    foreach ($conceptsOf[$key] ?? [] as $c) {
                        $taught[$c] = true;
                    }
                }
                $missing = array_values(array_diff(array_map('intval', $requiredConceptIds), array_keys($taught)));
                if ($missing !== []) {
                    $errors[] = 'The path ' . implode(' > ', $path) . ' never teaches concept ' . implode(', ', $missing) . '.';
                    break;
                }
            }
        }

        return ['errors' => $errors, 'stats' => $stats];
    }
}
