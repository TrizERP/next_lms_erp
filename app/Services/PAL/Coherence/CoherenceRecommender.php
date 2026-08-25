<?php

namespace App\Services\PAL\Coherence;

/**
 * Picks the next concept a learner should work on, and says why.
 *
 * ---------------------------------------------------------------------------
 * THE `why` BLOCK IS PART OF THE CONTRACT
 * ---------------------------------------------------------------------------
 * `CoherenceMapController::next()` documents it that way and it is not
 * decoration: a recommendation a teacher cannot interrogate is one they will not
 * trust, and this map's edges are 100% AI-tagged drafts (measured 2026-08-24 -
 * all 1,601 rows in `pal_concept_relations` carry tagged_by='ai',
 * quality_status='draft'). Every response therefore carries the rule that fired,
 * the numbers it fired on, and the provenance of the edges it walked. A teacher
 * who disagrees can see exactly which edge to go and fix.
 *
 * ---------------------------------------------------------------------------
 * THE RULES, IN ORDER
 * ---------------------------------------------------------------------------
 * 1. CONSOLIDATE - something is in progress and close to its gate. Finishing a
 *    concept at 0.62/0.70 beats starting a new one; near-miss abandonment is
 *    the most common way mastery data goes stale.
 * 2. UNBLOCK     - the learner is blocked on something they tried. Send them to
 *    the deepest unmastered ancestor, not to the thing they failed.
 * 3. ADVANCE     - nothing in progress: take the highest-leverage `ready`
 *    concept, where leverage is what it unlocks, then authored priority, then
 *    teaching order.
 * 4. EXHAUSTED   - nothing is ready and nothing is blocked, i.e. everything is
 *    mastered, or the scope has no map at all. Both are reported honestly
 *    rather than as an arbitrary pick.
 *
 * Rule 2 sits BELOW rule 1 on purpose. A learner who is 0.65 on one concept and
 * blocked on another is better served finishing the first: it is one or two
 * questions of work, and it may itself be the blocker.
 */
class CoherenceRecommender
{
    /**
     * How close to the gate counts as "nearly there" for rule 1.
     *
     * 0.15 below the gate at a 0.70 gate means 0.55 and up. Below that,
     * finishing is not a couple of questions away and starting fresh on
     * something ready is the better use of the session.
     */
    private const CONSOLIDATE_WINDOW = 0.15;

    /** Attempts above which a concept counts as genuinely "in progress". */
    private const IN_PROGRESS_ATTEMPTS = 1;

    public function __construct(private readonly CoherenceMapRepository $map)
    {
    }

    /**
     * @return array{action: string, concept: array<string, mixed>|null, why: array<string, mixed>, content: array<int, array<string, mixed>>, questions: array<int, array<string, mixed>>, path: array<int, array<string, mixed>>}
     */
    public function nextBestAction(int $learnerId, int $standardId, int $subjectId): array
    {
        $readiness = $this->map->readiness($standardId, $subjectId, $learnerId);

        if ($readiness === []) {
            return $this->nothing(
                'no_map',
                'No coherence map is projected for this class and subject yet, so there is nothing to '
                . 'recommend from. Run pal:coherence-sync for this scope.'
            );
        }

        $graph = $this->map->map($standardId, $subjectId, $learnerId);
        $nodes = [];

        foreach ($graph['nodes'] as $n) {
            $nodes[(int) $n['id']] = $n;
        }

        // ── Rule 1: consolidate ──────────────────────────────────────
        $candidates = [];

        foreach ($readiness as $id => $r) {
            if ($r['state'] === 'mastered') {
                continue;
            }

            $attempts = (int) ($nodes[$id]['attempts'] ?? 0);
            $gap = $r['gate'] - $r['mastery'];

            if ($attempts >= self::IN_PROGRESS_ATTEMPTS && $gap > 0 && $gap <= self::CONSOLIDATE_WINDOW) {
                $candidates[$id] = $gap;
            }
        }

        if ($candidates !== []) {
            asort($candidates);
            $id = (int) array_key_first($candidates);
            $r = $readiness[$id];

            return $this->action('consolidate', $nodes[$id] ?? [], [
                'rule'    => 'consolidate',
                'because' => sprintf(
                    'Already at %.2f against a gate of %.2f after %d attempt(s) - %.2f short. '
                    . 'Finishing this is a shorter path than starting something new.',
                    $r['mastery'],
                    $r['gate'],
                    (int) ($nodes[$id]['attempts'] ?? 0),
                    $r['gate'] - $r['mastery']
                ),
                'mastery'  => $r['mastery'],
                'gate'     => $r['gate'],
                'attempts' => (int) ($nodes[$id]['attempts'] ?? 0),
                'unlocks'  => $r['unlocks'],
            ], $nodes);
        }

        // ── Rule 2: unblock ──────────────────────────────────────────
        // Only for concepts the learner has actually attempted. Everything in a
        // fresh scope is technically blocked; treating that as "unblock me"
        // would send a learner to a random root before they had tried anything.
        $blocked = [];

        foreach ($readiness as $id => $r) {
            if ($r['state'] !== 'blocked') {
                continue;
            }

            if ((int) ($nodes[$id]['attempts'] ?? 0) < self::IN_PROGRESS_ATTEMPTS) {
                continue;
            }

            $blocked[$id] = count($r['unmet']);
        }

        if ($blocked !== []) {
            // Fewest unmet prerequisites first: the nearest thing to unblocking.
            asort($blocked);
            $stuckOn = (int) array_key_first($blocked);
            $roots = $this->map->rootBlockers($stuckOn, $learnerId);

            if ($roots !== []) {
                $root = $roots[0];
                $rid = (int) $root['id'];

                return $this->action('unblock', $nodes[$rid] ?? $root, [
                    'rule'    => 'unblock',
                    'because' => sprintf(
                        '"%s" is out of reach: %d prerequisite(s) are not in place. The deepest one is '
                        . '"%s" at %.2f against a gate of %.2f, %d step(s) back. Nothing beneath it is '
                        . 'unmastered, so this is where the chain actually breaks.',
                        (string) ($nodes[$stuckOn]['name'] ?? ('concept ' . $stuckOn)),
                        count($readiness[$stuckOn]['unmet']),
                        $root['name'],
                        $root['mastery'],
                        $root['gate'],
                        $root['depth']
                    ),
                    'stuck_on' => [
                        'id'      => $stuckOn,
                        'name'    => (string) ($nodes[$stuckOn]['name'] ?? ''),
                        'mastery' => $readiness[$stuckOn]['mastery'],
                        'unmet'   => $readiness[$stuckOn]['unmet'],
                    ],
                    'mastery' => $root['mastery'],
                    'gate'    => $root['gate'],
                    'depth'   => $root['depth'],
                    'roots'   => $roots,
                ], $nodes);
            }
        }

        // ── Rule 3: advance ──────────────────────────────────────────
        $ready = [];

        foreach ($readiness as $id => $r) {
            if ($r['state'] === 'ready') {
                $ready[$id] = $r;
            }
        }

        if ($ready !== []) {
            $best = null;

            foreach ($ready as $id => $r) {
                $n = $nodes[$id] ?? [];

                $score = [
                    // Leverage first: what does mastering this open up.
                    $r['unlocks'],
                    // Then authored priority, when a human or the extraction set one.
                    (int) ($n['priority'] ?? 0),
                    // Then teaching order - earlier chapter wins, so the
                    // recommendation follows the syllabus rather than jumping.
                    -1 * (int) ($n['chapter_order'] ?? 999),
                    // Then a stable tiebreak, so two identical candidates do not
                    // swap places between requests.
                    -1 * $id,
                ];

                if ($best === null || $score > $best['score']) {
                    $best = ['id' => $id, 'score' => $score, 'readiness' => $r];
                }
            }

            $id = (int) $best['id'];
            $r = $best['readiness'];
            $n = $nodes[$id] ?? [];

            return $this->action('advance', $n, [
                'rule'    => 'advance',
                'because' => sprintf(
                    'Every prerequisite is in place, and mastering it opens up %d further concept(s) - '
                    . 'the highest leverage of the %d concept(s) currently ready.%s',
                    $r['unlocks'],
                    count($ready),
                    isset($n['chapter']) && $n['chapter'] !== null
                        ? ' It sits in "' . $n['chapter'] . '", chapter ' . ($n['chapter_order'] ?? '?') . '.'
                        : ''
                ),
                'mastery'      => $r['mastery'],
                'gate'         => $r['gate'],
                'unlocks'      => $r['unlocks'],
                'ready_pool'   => count($ready),
                'priority'     => $n['priority'] ?? null,
            ], $nodes);
        }

        // ── Rule 4: exhausted ────────────────────────────────────────
        $mastered = count(array_filter($readiness, fn ($r) => $r['state'] === 'mastered'));

        if ($mastered === count($readiness)) {
            return $this->nothing(
                'complete',
                sprintf('Every one of the %d mapped concepts is at or above its gate.', $mastered)
            );
        }

        // Nothing ready, nothing attempted-and-blocked: the map has no entry
        // point the learner can reach. That is a structural defect in the map,
        // not a state the learner can act on, so say so.
        return $this->nothing(
            'no_entry_point',
            sprintf(
                '%d of %d concepts are unmastered but none is reachable - every one has an unmet '
                . 'prerequisite. The map has no entry point for this learner; check '
                . '/api/pal/coherence/health for a cycle.',
                count($readiness) - $mastered,
                count($readiness)
            )
        );
    }

    // ══════════════════════════════════════════════════════════════════

    /**
     * Assemble the response: the concept, the reasoning, and what to actually
     * put in front of the learner.
     *
     * `content` and `questions` are fetched here rather than left to a second
     * call, so a client can submit an answer and advance in one round trip -
     * which is the whole point of the evidence endpoint returning `next`.
     *
     * @param  array<string, mixed>  $concept
     * @param  array<string, mixed>  $why
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    private function action(string $action, array $concept, array $why, array $nodes): array
    {
        $id = (int) ($concept['id'] ?? 0);

        // The prerequisite trail, named. A teacher reading "start at Integers"
        // wants to see what sits between there and where the learner was.
        $path = [];

        foreach ((array) ($concept['prereq_ids'] ?? []) as $pid) {
            $path[] = [
                'id'      => (int) $pid,
                'name'    => (string) ($nodes[$pid]['name'] ?? ''),
                'mastery' => $nodes[$pid]['mastery'] ?? null,
            ];
        }

        return [
            'action'  => $action,
            'concept' => $id === 0 ? null : [
                'id'            => $id,
                'name'          => (string) ($concept['name'] ?? ''),
                'code'          => $concept['code'] ?? null,
                'description'   => $concept['description'] ?? null,
                'chapter'       => $concept['chapter'] ?? null,
                'chapter_order' => $concept['chapter_order'] ?? null,
                'bloom'         => $concept['bloom'] ?? null,
                'minutes'       => $concept['minutes'] ?? null,
                'status'        => $concept['status'] ?? 'draft',
            ],
            'why'       => $why + [
                // Provenance, always. See the class docblock: nothing in this
                // map is human-approved yet and the client must be able to say so.
                'edge_provenance' => $this->provenance($concept),
            ],
            'content'   => $id === 0 ? [] : $this->map->contentFor($id, [], 5),
            'questions' => $id === 0 ? [] : $this->map->questionsFor($id, 5),
            'path'      => $path,
        ];
    }

    /**
     * @param  array<string, mixed>  $concept
     * @return array<string, mixed>
     */
    private function provenance(array $concept): array
    {
        return [
            'concept_status' => $concept['status'] ?? 'draft',
            'note' => ($concept['status'] ?? 'draft') === 'approved'
                ? 'This concept has been reviewed.'
                : 'This concept and the prerequisite edges around it are AI-suggested drafts and have '
                  . 'not been reviewed by curriculum staff.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nothing(string $action, string $message): array
    {
        return [
            'action'    => $action,
            'concept'   => null,
            'why'       => ['rule' => $action, 'because' => $message],
            'content'   => [],
            'questions' => [],
            'path'      => [],
        ];
    }
}
