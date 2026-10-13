<?php

namespace App\Services\lms\H5P;

/**
 * The rules a decision tree has to satisfy before a learner can be given it.
 *
 * PURE: arrays in, arrays out, no database and no framework. That is deliberate
 * and load-bearing -- the same check runs in three places that must agree about
 * what a valid scenario is (the save/publish path of the controller, the
 * generator's blueprint check, and the generator's final validation), and a
 * check that needs a booted app cannot be unit-tested without touching the
 * shared database.
 *
 * A NODE, as this class sees it:
 *
 *   key      string  an id, or a client ref -- anything unique within the tree
 *   type     string  text | branching_question | ending
 *   label    string  what to call it in a message ("Step 3: Testing the water")
 *   next     ?string text only: the key it leads to
 *   targets  list<?string> branching_question only: one entry per choice
 *   check    bool    true for a knowledge check taken from the question bank
 *
 * WHAT COUNTS AS BROKEN, and why each is an error and not a warning:
 *
 *   - a reference to a node that is not there: the learner clicks and nothing
 *     happens;
 *   - a node nobody can reach: authored content no learner will ever see, which
 *     in a generated scenario means the model wrote a branch and then forgot to
 *     connect it;
 *   - a node from which no ending can be reached: a learner who arrives there
 *     is stuck, with no way to finish;
 *   - a decision with fewer than two choices: that is not a decision.
 *
 * Cycles are allowed -- H5P allows them, and "try that decision again" is a
 * legitimate loop -- provided every node can still reach an ending. Counting
 * paths is therefore done over SIMPLE paths (no node twice), and is capped so a
 * pathological hand-built tree cannot make a save hang.
 */
final class BranchingGraph
{
    public const TEXT = 'text';

    public const DECISION = 'branching_question';

    public const ENDING = 'ending';

    /** Upper bound on simple paths enumerated; beyond it the count is reported as capped. */
    public const PATH_CAP = 5000;

    /**
     * @param  list<array<string,mixed>>  $nodes  in authoring order; the first is the start
     * @return array{
     *     errors: list<array{code:string,node:?string,message:string}>,
     *     warnings: list<array{code:string,node:?string,message:string}>,
     *     stats: array<string,mixed>
     * }
     */
    public static function analyse(array $nodes): array
    {
        $errors = [];
        $warnings = [];
        $add = function (array &$into, string $code, ?string $node, string $message): void {
            $into[] = ['code' => $code, 'node' => $node, 'message' => $message];
        };

        $nodes = array_values($nodes);
        if ($nodes === []) {
            $add($errors, 'empty', null, 'Add at least one step before publishing.');

            return ['errors' => $errors, 'warnings' => $warnings, 'stats' => self::emptyStats()];
        }

        $byKey = [];
        foreach ($nodes as $node) {
            $key = (string) $node['key'];
            if (isset($byKey[$key])) {
                $add($errors, 'duplicate_key', $key, 'Two steps share the same id (' . $key . ').');
            }
            $byKey[$key] = $node;
        }
        $label = fn (string $key): string => (string) ($byKey[$key]['label'] ?? $key);

        // The start must be a place to be, not a place to stop.
        $startKey = (string) $nodes[0]['key'];
        if (($nodes[0]['type'] ?? null) === self::ENDING) {
            $add($errors, 'start_is_ending', $startKey, 'The first step is an ending. A scenario has to begin with content or a decision.');
        }

        $endings = array_values(array_filter($nodes, fn ($n) => ($n['type'] ?? null) === self::ENDING));
        if ($endings === []) {
            $add($errors, 'no_ending', null, 'Add at least one ending. Every path has to finish somewhere.');
        } elseif (count($endings) === 1) {
            $add($warnings, 'single_ending', null, 'There is only one ending, so every choice leads to the same place. Add an ending for a less successful path.');
        }

        // Edges, with the reasons a node is malformed named per node.
        $edges = [];
        foreach ($nodes as $node) {
            $key = (string) $node['key'];
            $type = (string) ($node['type'] ?? '');
            $edges[$key] = [];

            if ($type === self::TEXT) {
                $next = $node['next'] ?? null;
                if ($next === null || $next === '') {
                    $add($errors, 'missing_next', $key, $label($key) . ' does not lead anywhere. Choose the step that comes next.');
                } elseif (! isset($byKey[(string) $next])) {
                    $add($errors, 'bad_target', $key, $label($key) . ' leads to a step that does not exist.');
                } elseif ((string) $next === $key) {
                    $add($errors, 'self_loop', $key, $label($key) . ' leads back to itself.');
                } else {
                    $edges[$key][] = (string) $next;
                }
            } elseif ($type === self::DECISION) {
                $targets = array_values((array) ($node['targets'] ?? []));
                if (count($targets) < 2) {
                    $add($errors, 'too_few_choices', $key, $label($key) . ' needs at least two choices; with one there is no decision to make.');
                }
                foreach ($targets as $i => $target) {
                    if ($target === null || $target === '') {
                        $add($errors, 'missing_next', $key, 'Choice ' . ($i + 1) . ' of ' . $label($key) . ' does not lead anywhere.');
                    } elseif (! isset($byKey[(string) $target])) {
                        $add($errors, 'bad_target', $key, 'Choice ' . ($i + 1) . ' of ' . $label($key) . ' leads to a step that does not exist.');
                    } else {
                        $edges[$key][] = (string) $target;
                    }
                }

                $distinct = array_unique(array_filter($targets, fn ($t) => $t !== null && $t !== ''));
                if (count($targets) >= 2 && count($distinct) === 1 && empty($node['check'])) {
                    $add($warnings, 'single_destination', $key, 'Every choice in ' . $label($key) . ' leads to the same step, so the decision does not change the path.');
                }
            } elseif ($type !== self::ENDING) {
                $add($errors, 'unknown_type', $key, $label($key) . ' is of a kind this scenario does not support (' . ($type !== '' ? $type : 'none') . ').');
            }
        }

        $reachable = self::reachableFrom($startKey, $edges);
        foreach ($nodes as $node) {
            $key = (string) $node['key'];
            if (! isset($reachable[$key])) {
                $add($errors, 'unreachable', $key, $label($key) . ' cannot be reached from the start, so no learner would ever see it.');
            }
        }

        // Co-reachability: walk the edges backwards from the endings.
        $canFinish = self::canReachEnding($nodes, $edges);
        foreach ($nodes as $node) {
            $key = (string) $node['key'];
            if (isset($reachable[$key]) && ! isset($canFinish[$key])) {
                $add($errors, 'dead_end', $key, 'A learner who reaches ' . $label($key) . ' cannot get to an ending.');
            }
        }

        $paths = self::countPaths($startKey, $edges, $nodes);

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'stats' => [
                'nodes' => count($nodes),
                'screens' => count(array_filter($nodes, fn ($n) => ($n['type'] ?? null) === self::TEXT)),
                'decisions' => count(array_filter($nodes, fn ($n) => ($n['type'] ?? null) === self::DECISION)),
                'endings' => count($endings),
                'paths' => $paths['count'],
                'paths_capped' => $paths['capped'],
                'longest_path' => $paths['longest'],
                'shortest_path' => $paths['shortest'],
                'endings_reached' => $paths['endings_reached'],
                'acyclic' => self::isAcyclic($edges),
            ],
        ];
    }

    /**
     * Every simple path from the start to an ending, as lists of node keys.
     *
     * Capped at PATH_CAP; the caller can tell from the length. Used by tests
     * and by the generator report ("12 paths, 4 endings"), never by a learner's
     * route, which is decided one click at a time.
     *
     * @param  list<array<string,mixed>>  $nodes
     * @return list<list<string>>
     */
    public static function enumeratePaths(array $nodes, int $cap = self::PATH_CAP): array
    {
        $nodes = array_values($nodes);
        if ($nodes === []) {
            return [];
        }

        $byKey = [];
        foreach ($nodes as $node) {
            $byKey[(string) $node['key']] = $node;
        }
        $edges = self::edgesOf($nodes, $byKey);

        $out = [];
        $walk = function (string $key, array $path) use (&$walk, &$out, $edges, $byKey, $cap): void {
            if (count($out) >= $cap) {
                return;
            }
            $path[] = $key;
            if (($byKey[$key]['type'] ?? null) === self::ENDING) {
                $out[] = $path;

                return;
            }
            foreach (array_unique($edges[$key] ?? []) as $next) {
                if (! in_array($next, $path, true)) {
                    $walk($next, $path);
                }
            }
        };
        $walk((string) $nodes[0]['key'], []);

        return $out;
    }

    /**
     * Keys reachable from $start (inclusive).
     *
     * @param  array<string,list<string>>  $edges
     * @return array<string,true>
     */
    public static function reachableFrom(string $start, array $edges): array
    {
        $seen = [$start => true];
        $stack = [$start];
        while ($stack !== []) {
            $key = array_pop($stack);
            foreach ($edges[$key] ?? [] as $next) {
                if (! isset($seen[$next])) {
                    $seen[$next] = true;
                    $stack[] = $next;
                }
            }
        }

        return $seen;
    }

    /**
     * Keys from which at least one ending can be reached.
     *
     * @param  list<array<string,mixed>>  $nodes
     * @param  array<string,list<string>>  $edges
     * @return array<string,true>
     */
    private static function canReachEnding(array $nodes, array $edges): array
    {
        $reverse = [];
        foreach ($edges as $from => $targets) {
            foreach ($targets as $to) {
                $reverse[$to][] = $from;
            }
        }

        $ok = [];
        $stack = [];
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === self::ENDING) {
                $ok[(string) $node['key']] = true;
                $stack[] = (string) $node['key'];
            }
        }
        while ($stack !== []) {
            $key = array_pop($stack);
            foreach ($reverse[$key] ?? [] as $from) {
                if (! isset($ok[$from])) {
                    $ok[$from] = true;
                    $stack[] = $from;
                }
            }
        }

        return $ok;
    }

    /** @param array<string,list<string>> $edges */
    private static function isAcyclic(array $edges): bool
    {
        $state = [];
        $visit = function (string $key) use (&$visit, &$state, $edges): bool {
            $state[$key] = 1;
            foreach ($edges[$key] ?? [] as $next) {
                if (($state[$next] ?? 0) === 1) {
                    return false;
                }
                if (($state[$next] ?? 0) === 0 && ! $visit($next)) {
                    return false;
                }
            }
            $state[$key] = 2;

            return true;
        };
        foreach (array_keys($edges) as $key) {
            if (($state[$key] ?? 0) === 0 && ! $visit($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string,list<string>>  $edges
     * @param  list<array<string,mixed>>  $nodes
     * @return array{count:int,capped:bool,longest:int,shortest:int,endings_reached:int}
     */
    private static function countPaths(string $start, array $edges, array $nodes): array
    {
        $byKey = [];
        foreach ($nodes as $node) {
            $byKey[(string) $node['key']] = $node;
        }

        $count = 0;
        $longest = 0;
        $shortest = PHP_INT_MAX;
        $endings = [];
        $capped = false;

        $walk = function (string $key, array $path) use (&$walk, &$count, &$longest, &$shortest, &$endings, &$capped, $edges, $byKey): void {
            if ($capped) {
                return;
            }
            $path[] = $key;
            if (($byKey[$key]['type'] ?? null) === self::ENDING) {
                $count++;
                $longest = max($longest, count($path));
                $shortest = min($shortest, count($path));
                $endings[$key] = true;
                if ($count >= self::PATH_CAP) {
                    $capped = true;
                }

                return;
            }
            foreach (array_unique($edges[$key] ?? []) as $next) {
                if (! in_array($next, $path, true)) {
                    $walk($next, $path);
                }
            }
        };
        $walk($start, []);

        return [
            'count' => $count,
            'capped' => $capped,
            'longest' => $longest,
            'shortest' => $shortest === PHP_INT_MAX ? 0 : $shortest,
            'endings_reached' => count($endings),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $nodes
     * @param  array<string,array<string,mixed>>  $byKey
     * @return array<string,list<string>>
     */
    private static function edgesOf(array $nodes, array $byKey): array
    {
        $edges = [];
        foreach ($nodes as $node) {
            $key = (string) $node['key'];
            $edges[$key] = [];
            $targets = ($node['type'] ?? null) === self::TEXT
                ? [$node['next'] ?? null]
                : (($node['type'] ?? null) === self::DECISION ? (array) ($node['targets'] ?? []) : []);
            foreach ($targets as $target) {
                if ($target !== null && $target !== '' && isset($byKey[(string) $target])) {
                    $edges[$key][] = (string) $target;
                }
            }
        }

        return $edges;
    }

    /** @return array<string,mixed> */
    private static function emptyStats(): array
    {
        return [
            'nodes' => 0, 'screens' => 0, 'decisions' => 0, 'endings' => 0, 'paths' => 0,
            'paths_capped' => false, 'longest_path' => 0, 'shortest_path' => 0,
            'endings_reached' => 0, 'acyclic' => true,
        ];
    }
}
