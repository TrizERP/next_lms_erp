<?php

namespace App\Services\PAL\Coherence\Concerns;

/**
 * The one set of traversals the coherence readers share.
 *
 * WHY THIS IS A TRAIT AND NOT FOUR PRIVATE METHODS
 * Two classes now answer "what must be learned before this?" over the same data:
 * CoherenceMapRepository (reading the Neo4j projection, concept ids) and
 * CurriculumGraphBuilder (reading MariaDB directly, typed node refs). If they walk
 * the graph differently they disagree about the same curriculum, and the symptom is
 * a concept whose depth on the map does not match the depth the ESO gate used to
 * decide whether a learner may reach it. One implementation is what keeps them
 * honest - the same reasoning that put TokenisesTitles in this directory.
 *
 * WHY EVERY WALK HERE IS CYCLE-SAFE
 * The prerequisite graph in this database is NOT acyclic. Measured 2026-08-24,
 * `pal_concept_relations` holds 41 reciprocal `requires` pairs, 6 of them inside a
 * single projected scope. Anything here that assumed a DAG would either not
 * terminate or silently drop an arm of the graph, so every method carries an
 * explicit visited-set and DEPTH_CAP is a runaway guard on top of it. The longest
 * genuine prerequisite chain measured live is 6.
 *
 * KEYS MAY BE INT OR STRING
 * The Neo4j reader keys adjacency by concept id; the MariaDB reader keys by a typed
 * ref like "concept:6512", because its graph spans four node kinds and an integer id
 * is only unique within one of them. Every comparison here is strict, so the two
 * key spaces never silently collide.
 */
trait WalksPrerequisiteGraph
{
    /**
     * Hard ceiling on closure depth. Not a pedagogy decision - a guard, so a cycle
     * that slips past the visited-set cannot spin.
     */
    private const DEPTH_CAP = 24;

    /**
     * Every node that lies on a prerequisite ring.
     *
     * Iterative Tarjan-style DFS: when a back edge reaches a node still on the
     * stack, everything on the stack from that node onwards is on the ring.
     * Iterative rather than recursive because a deep chain on a large scope would
     * otherwise hit PHP's stack.
     *
     * @param  array<int|string, array<int, int|string>>  $requires
     * @return array<int, int|string>
     */
    protected function cycleNodes(array $requires): array
    {
        $state = [];      // 0 unvisited, 1 on stack, 2 done
        $onCycle = [];

        foreach (array_keys($requires) as $start) {
            if (($state[$start] ?? 0) !== 0) {
                continue;
            }

            // Each frame: [node, remaining neighbours]
            $stack = [[$start, array_values($requires[$start] ?? [])]];
            $path = [$start];
            $state[$start] = 1;

            while ($stack !== []) {
                $top = count($stack) - 1;

                if ($stack[$top][1] === []) {
                    $state[$stack[$top][0]] = 2;
                    array_pop($stack);
                    array_pop($path);

                    continue;
                }

                $next = array_shift($stack[$top][1]);
                $s = $state[$next] ?? 0;

                if ($s === 1) {
                    // Back edge: mark the ring from $next to the top of $path.
                    $from = array_search($next, $path, true);

                    if ($from !== false) {
                        foreach (array_slice($path, $from) as $n) {
                            $onCycle[$n] = $n;
                        }
                    }

                    continue;
                }

                if ($s === 2) {
                    continue;
                }

                $state[$next] = 1;
                $path[] = $next;
                $stack[] = [$next, array_values($requires[$next] ?? [])];
            }
        }

        return array_values($onCycle);
    }

    /**
     * Longest prerequisite chain beneath each node - the layout's progression axis.
     *
     * Memoised depth-first, with cycle nodes pinned to their shallowest possible
     * value: a node on a ring has no well-defined depth, and letting the walk chase
     * it produces a different answer depending on where the traversal started.
     * Pinning keeps the layout stable across requests, which matters more than being
     * philosophically right about a cycle.
     *
     * @param  array<int|string, array<int, int|string>>  $requires
     * @param  array<int, int|string>  $cycleNodes
     * @return array<int|string, int>
     */
    protected function depths(array $requires, array $cycleNodes): array
    {
        $onCycle = array_flip($cycleNodes);
        $depth = [];

        $resolve = function ($node, array $seen) use (&$resolve, $requires, $onCycle, &$depth): int {
            if (isset($depth[$node])) {
                return $depth[$node];
            }

            if (isset($seen[$node]) || count($seen) > self::DEPTH_CAP) {
                return 0;
            }

            $seen[$node] = true;
            $best = 0;

            foreach ($requires[$node] ?? [] as $prereq) {
                if (isset($onCycle[$prereq]) && isset($onCycle[$node])) {
                    // Same ring: do not descend, or the two nodes define each
                    // other's depth and the answer depends on traversal order.
                    continue;
                }

                $best = max($best, 1 + $resolve($prereq, $seen));
            }

            return $depth[$node] = $best;
        };

        foreach (array_keys($requires) as $node) {
            $resolve($node, []);
        }

        return $depth;
    }

    /**
     * Transitive closure of one node over an adjacency map, cycle-safe.
     *
     * @param  array<int|string, array<int, int|string>>  $adj
     * @return array<int|string, int|string>
     */
    protected function walk($from, array $adj): array
    {
        $seen = [];
        $queue = array_values($adj[$from] ?? []);

        while ($queue !== []) {
            $n = array_pop($queue);

            if (isset($seen[$n]) || $n === $from) {
                continue;
            }

            $seen[$n] = $n;

            foreach ($adj[$n] ?? [] as $next) {
                if (! isset($seen[$next])) {
                    $queue[] = $next;
                }
            }
        }

        return $seen;
    }

    /**
     * Closure of one node WITH the shortest distance to each member - breadth
     * first, so `depth` means "how far back" and not "how the DFS happened to
     * arrive".
     *
     * @param  array<int|string, array<int, int|string>>  $adj
     * @return array<int|string, int> node => distance
     */
    protected function walkWithDepth($from, array $adj): array
    {
        $dist = [];
        $frontier = array_values($adj[$from] ?? []);
        $d = 1;

        while ($frontier !== [] && $d <= self::DEPTH_CAP) {
            $next = [];

            foreach ($frontier as $n) {
                if ($n === $from || isset($dist[$n])) {
                    continue;
                }

                $dist[$n] = $d;

                foreach ($adj[$n] ?? [] as $further) {
                    if (! isset($dist[$further]) && $further !== $from) {
                        $next[] = $further;
                    }
                }
            }

            $frontier = $next;
            $d++;
        }

        return $dist;
    }
}
