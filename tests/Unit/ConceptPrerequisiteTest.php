<?php

namespace Tests\Unit;

use App\Models\LMS\ConceptPrerequisite;
use App\Services\Concept\GradeMap;
use PHPUnit\Framework\TestCase;

/**
 * The concept prerequisite map, checked as data and as rules.
 *
 * No database. The authored file is a PHP array and the checks are pure, so these
 * run anywhere - including a CI box with no MySQL - and they fail for the reason
 * they name rather than on a fixture.
 *
 * The data-file cases matter most: they run the same invariants the import and check
 * commands enforce, so a loop or a backwards link introduced while authoring fails in
 * under a second rather than against the shared estate.
 */
class ConceptPrerequisiteTest extends TestCase
{
    private const DATA = __DIR__.'/../../database/data/concept_prerequisites/';

    /** Mirrors PrereqImportCommand::MIN_REASON. */
    private const MIN_REASON = 40;

    /**
     * Every authored entry across every subject file.
     *
     * Discovered from the directory rather than listed, so a new subject file is
     * covered the moment it is added. Naming the files one by one is how the
     * Mathematics batch sat unchecked after it was written - the suite still only
     * read science.php and reported green.
     */
    private function entries(): array
    {
        $all = [];

        foreach (glob(self::DATA.'*.php') as $path) {
            foreach (require $path as $i => $entry) {
                $entry['_file'] = basename($path);
                $entry['_index'] = $i;
                $all[] = $entry;
            }
        }

        return $all;
    }

    /** Entries grouped by file, for the checks that must not mix subjects. */
    private function entriesByFile(): array
    {
        $byFile = [];

        foreach ($this->entries() as $entry) {
            $byFile[$entry['_file']][] = $entry;
        }

        return $byFile;
    }

    private function where(array $entry): string
    {
        return $entry['_file'].' #'.$entry['_index'];
    }

    // ── the vocabulary ──────────────────────────────────────────────────

    public function test_every_link_type_is_directed(): void
    {
        // A symmetric type in this list would let the table hold a two-row loop,
        // which is the exact fault the map exists to avoid.
        $this->assertSame(
            ['requires', 'builds_on', 'spiral', 'cross_subject'],
            ConceptPrerequisite::LINK_TYPES
        );
    }

    public function test_the_depth_guard_is_above_any_real_chain(): void
    {
        // Classes 6-10 give five grade steps; anything near the cap is a data fault,
        // not a long chain.
        $this->assertGreaterThan(10, ConceptPrerequisite::MAX_DEPTH);
    }

    // ── the authored data ───────────────────────────────────────────────

    public function test_every_link_carries_a_reason(): void
    {
        foreach ($this->entries() as $e) {
            $this->assertArrayHasKey('reason', $e, $this->where($e).' has no reason');
            $this->assertGreaterThanOrEqual(
                self::MIN_REASON,
                mb_strlen($e['reason']),
                $this->where($e).': a reason says what the learner cannot do without the prerequisite'
            );
            $this->assertNotEmpty($e['source'] ?? '', $this->where($e).' has no source reference');
        }
    }

    public function test_every_link_uses_the_shared_vocabulary(): void
    {
        foreach ($this->entries() as $e) {
            $this->assertContains($e['type'], ConceptPrerequisite::LINK_TYPES, $this->where($e).': unknown type');
            $this->assertIsBool($e['gate'], $this->where($e).': gate must be a boolean');
            $this->assertIsInt($e['concept'], $this->where($e).': concept must be an lms_concept id');
            $this->assertIsInt($e['prerequisite'], $this->where($e).': prerequisite must be an lms_concept id');
        }
    }

    public function test_nothing_is_its_own_prerequisite(): void
    {
        foreach ($this->entries() as $e) {
            $this->assertNotSame($e['concept'], $e['prerequisite'], $this->where($e).' links a concept to itself');
        }
    }

    public function test_no_pair_is_authored_twice(): void
    {
        $seen = [];

        foreach ($this->entries() as $e) {
            $key = $e['concept'].'<-'.$e['prerequisite'];
            $this->assertArrayNotHasKey($key, $seen, $this->where($e).' duplicates '.($seen[$key] ?? '?'));
            $seen[$key] = $this->where($e);
        }
    }

    public function test_no_pair_is_authored_in_both_directions(): void
    {
        // Two rows for one pair is a two-node loop, and the map would deadlock on it.
        $forward = [];

        foreach ($this->entries() as $e) {
            $forward[$e['concept'].'<-'.$e['prerequisite']] = true;
        }

        foreach ($this->entries() as $e) {
            $this->assertArrayNotHasKey(
                $e['prerequisite'].'<-'.$e['concept'],
                $forward,
                $this->where($e).' reverses another link between the same two concepts'
            );
        }
    }

    public function test_the_authored_map_is_acyclic(): void
    {
        $adjacency = [];

        foreach ($this->entries() as $e) {
            $adjacency[$e['prerequisite']][] = $e['concept'];
        }

        $this->assertSame([], $this->findCycle($adjacency), 'the authored map contains a prerequisite loop');
    }

    public function test_the_map_has_somewhere_to_start(): void
    {
        $all = [];
        $hasPrerequisite = [];

        foreach ($this->entries() as $e) {
            $all[$e['concept']] = true;
            $all[$e['prerequisite']] = true;
            $hasPrerequisite[$e['concept']] = true;
        }

        $roots = array_diff(array_keys($all), array_keys($hasPrerequisite));

        $this->assertNotEmpty($roots, 'a map with no starting concept cannot be entered by a learner');
    }

    public function test_at_least_one_concept_has_several_prerequisites(): void
    {
        // Many-to-many is the point; a map where every concept has exactly one
        // prerequisite is a list, not a map.
        $in = [];

        foreach ($this->entries() as $e) {
            $in[$e['concept']] = ($in[$e['concept']] ?? 0) + 1;
        }

        $this->assertNotEmpty(array_filter($in, fn ($n) => $n > 1));
    }

    public function test_at_least_one_concept_unlocks_several(): void
    {
        $out = [];

        foreach ($this->entries() as $e) {
            $out[$e['prerequisite']] = ($out[$e['prerequisite']] ?? 0) + 1;
        }

        $this->assertNotEmpty(array_filter($out, fn ($n) => $n > 1));
    }

    public function test_no_concept_is_over_coupled(): void
    {
        $in = [];

        foreach ($this->entries() as $e) {
            $in[$e['concept']] = ($in[$e['concept']] ?? 0) + 1;
        }

        foreach ($in as $conceptId => $count) {
            $this->assertLessThanOrEqual(
                ConceptPrerequisite::MAX_DIRECT_PREREQUISITES,
                $count,
                "concept {$conceptId} has {$count} direct prerequisites - it is probably too coarse and should be split"
            );
        }
    }

    // ── grade parsing ───────────────────────────────────────────────────

    /** @dataProvider standardNames */
    public function test_a_class_number_is_read_from_a_standard_name(?string $name, ?int $expected): void
    {
        $this->assertSame($expected, (new GradeMap())->fromName($name));
    }

    public static function standardNames(): array
    {
        return [
            'bare' => ['6', 6],
            'class' => ['Class 6', 6],
            'grade' => ['Grade 10', 10],
            'ordinal' => ['7th', 7],
            'roman' => ['Class IX', 9],
            'roman eight not six' => ['VIII', 8],
            'with stream' => ['Class 10 - Science', 10],
            'academic year does not win' => ['Class 6 2026-27', 6],
            'out of range' => ['Class 45', null],
            'no number' => ['Primary', null],
            'stray roman letter' => ['XAVIER', null],
            'null' => [null, null],
        ];
    }

    // ── helper ──────────────────────────────────────────────────────────

    /**
     * One cycle, or [] when the map is acyclic.
     *
     * Iterative three-colour DFS: the traversal must terminate on the input most
     * likely to contain a loop, which is precisely the one being tested.
     */
    private function findCycle(array $adjacency): array
    {
        $colour = [];

        foreach (array_keys($adjacency) as $start) {
            if (($colour[$start] ?? 0) !== 0) {
                continue;
            }

            $stack = [[$start, 0]];
            $path = [$start];
            $colour[$start] = 1;

            while ($stack !== []) {
                [$node, $i] = $stack[count($stack) - 1];
                $next = array_values($adjacency[$node] ?? []);

                if ($i >= count($next)) {
                    array_pop($stack);
                    array_pop($path);
                    $colour[$node] = 2;
                    continue;
                }

                $stack[count($stack) - 1][1] = $i + 1;
                $n = $next[$i];

                if (($colour[$n] ?? 0) === 1) {
                    return array_slice($path, (int) array_search($n, $path, true));
                }

                if (($colour[$n] ?? 0) === 0) {
                    $colour[$n] = 1;
                    $path[] = $n;
                    $stack[] = [$n, 0];
                }
            }
        }

        return [];
    }
}
