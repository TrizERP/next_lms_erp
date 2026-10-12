<?php

namespace Tests\Unit\H5P\Branching;

use App\Services\lms\H5P\BranchingPayload;
use PHPUnit\Framework\TestCase;

/**
 * The contract between the editor and the server about what a sound scenario is.
 *
 * The rules exist twice: BranchingGraph + BranchingPayload here, and `analyse()`
 * in the Next.js app's lib/h5p/branching-scenario.ts. They have to agree, or an
 * editor that says "ready" meets a publish that says "no". This test runs the
 * PHP rules over a fixed set of scenarios and compares the verdicts with
 * tests/Fixtures/branching-graph-golden.json; the frontend runs the same file
 * through its own implementation (lib/h5p/branching-scenario.test.ts).
 *
 * When a rule changes on purpose:
 *
 *   UPDATE_GOLDEN=1 vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
 *       tests/Unit/H5P/Branching/BranchingGoldenFixtureTest.php
 *
 * then copy the file to lms_k12/lib/h5p/fixtures/. Only error and warning CODES
 * and the structural statistics are compared, not message wording.
 */
class BranchingGoldenFixtureTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../../Fixtures/branching-graph-golden.json';

    private function text(string $ref, ?string $next, string $body = '<p>Words that teach something.</p>', array $more = []): array
    {
        return $more + ['ref' => $ref, 'node_type' => 'text', 'title' => 'Screen ' . $ref, 'body' => $body, 'next_ref' => $next, 'options' => []];
    }

    /** @param list<array{0:string,1:?string}> $choices */
    private function decision(string $ref, array $choices, array $more = []): array
    {
        return $more + [
            'ref' => $ref, 'node_type' => 'branching_question', 'title' => 'Decision ' . $ref, 'body' => '<p>What do you do?</p>',
            'options' => array_map(fn ($c) => ['text' => $c[0], 'next_ref' => $c[1], 'quality' => 'best', 'feedback_title' => 'Why', 'feedback_text' => '<p>Because of the idea.</p>'], $choices),
        ];
    }

    private function ending(string $ref, int $score = 0): array
    {
        return ['ref' => $ref, 'node_type' => 'ending', 'title' => 'Ending ' . $ref, 'body' => '<p>Where it stops.</p>', 'score' => $score, 'options' => []];
    }

    /** @return array<string,array{strict:bool,nodes:list<array<string,mixed>>}> */
    private function cases(): array
    {
        $tree = [
            $this->text('a', 'd'),
            $this->decision('d', [['Look closer', 'b'], ['Guess', 'c']]),
            $this->text('b', 'e1'),
            $this->text('c', 'e2'),
            $this->ending('e1', 100),
            $this->ending('e2', 40),
        ];
        $with = function (array $nodes, int $index, array $patch) {
            $nodes[$index] = array_replace($nodes[$index], $patch);

            return $nodes;
        };

        return [
            'sound tree' => ['strict' => true, 'nodes' => $tree],
            'orphan' => ['strict' => true, 'nodes' => array_merge($tree, [$this->text('x', 'e1')])],
            'branch into nothing' => ['strict' => true, 'nodes' => $with($tree, 1, $this->decision('d', [['Look closer', 'b'], ['Guess', 'ghost']]))],
            'missing next and one choice' => ['strict' => true, 'nodes' => $with($with($tree, 0, $this->text('a', null)), 1, $this->decision('d', [['Only', 'b']]))],
            'loop with no exit' => ['strict' => true, 'nodes' => [$this->text('a', 'b'), $this->text('b', 'c'), $this->text('c', 'b'), $this->ending('e1')]],
            'loop that can finish' => ['strict' => true, 'nodes' => [$this->text('a', 'd'), $this->decision('d', [['Retry', 'r'], ['Finish', 'e1']]), $this->text('r', 'd'), $this->ending('e1', 100)]],
            'starts with an ending' => ['strict' => true, 'nodes' => [$this->ending('e1'), $this->text('a', 'e1')]],
            'no ending' => ['strict' => true, 'nodes' => [$this->text('a', 'b'), $this->text('b', 'a')]],
            'single ending, one destination' => ['strict' => true, 'nodes' => [$this->text('a', 'd'), $this->decision('d', [['One', 'e1'], ['Two', 'e1']]), $this->ending('e1')]],
            'a check may rejoin' => ['strict' => true, 'nodes' => [$this->text('a', 'd'), $this->decision('d', [['Right', 'e1'], ['Wrong', 'e1']], ['source_question_id' => 5]), $this->ending('e1')]],
            'self loop' => ['strict' => true, 'nodes' => [$this->text('a', 'a'), $this->ending('e1')]],
            'picture without alt, publish' => ['strict' => true, 'nodes' => $with($tree, 0, ['media_path' => 'https://cdn.example.test/a.png', 'media_alt' => ''])],
            'picture without alt, draft' => ['strict' => false, 'nodes' => $with($tree, 0, ['media_path' => 'https://cdn.example.test/a.png', 'media_alt' => ''])],
            'empty screen and no feedback' => ['strict' => true, 'nodes' => $with($with($tree, 0, ['body' => '<p>  </p>']), 1, ['options' => [
                ['text' => 'Look closer', 'next_ref' => 'b', 'quality' => 'best', 'feedback_title' => '', 'feedback_text' => ''],
                ['text' => '', 'next_ref' => 'c', 'quality' => 'misstep', 'feedback_title' => 'x', 'feedback_text' => '<p>y</p>'],
            ]])],
            'empty' => ['strict' => true, 'nodes' => []],
            'a lot of branching' => ['strict' => true, 'nodes' => [
                $this->text('a', 'd0'),
                $this->decision('d0', [['x', 'm0'], ['y', 'n0']]), $this->text('m0', 'd1'), $this->text('n0', 'd1'),
                $this->decision('d1', [['x', 'm1'], ['y', 'n1']]), $this->text('m1', 'e1'), $this->text('n1', 'e2'),
                $this->ending('e1', 100), $this->ending('e2', 50),
            ]],
        ];
    }

    /** @return array<string,mixed> */
    private function verdict(array $case): array
    {
        $report = BranchingPayload::analyse($case['nodes'], $case['strict']);
        $codes = fn (array $list) => array_values(array_map(fn ($i) => $i['code'], $list));
        $stats = $report['stats'];

        return [
            'errors' => $this->sorted($codes($report['errors'])),
            'warnings' => $this->sorted($codes($report['warnings'])),
            'stats' => [
                'nodes' => $stats['nodes'], 'screens' => $stats['screens'], 'decisions' => $stats['decisions'],
                'endings' => $stats['endings'], 'paths' => $stats['paths'], 'longest_path' => $stats['longest_path'],
                'shortest_path' => $stats['shortest_path'], 'endings_reached' => $stats['endings_reached'],
                'acyclic' => $stats['acyclic'],
            ],
        ];
    }

    /** @param list<string> $list */
    private function sorted(array $list): array
    {
        sort($list);

        return $list;
    }

    public function test_the_php_rules_match_the_golden_file_the_editor_also_runs(): void
    {
        $golden = [];
        foreach ($this->cases() as $name => $case) {
            $golden[] = ['name' => $name, 'strict' => $case['strict'], 'nodes' => $case['nodes']] + $this->verdict($case);
        }

        if (getenv('UPDATE_GOLDEN') === '1') {
            file_put_contents(self::FIXTURE, json_encode($golden, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $this->markTestSkipped('Golden file rewritten. Copy it to lms_k12/lib/h5p/fixtures/ and re-run without UPDATE_GOLDEN.');
        }

        $this->assertFileExists(self::FIXTURE, 'Run once with UPDATE_GOLDEN=1 to create it.');
        $expected = json_decode((string) file_get_contents(self::FIXTURE), true);

        $this->assertSame(
            json_decode(json_encode($golden), true),
            $expected,
            'The rules changed. If that was intended: UPDATE_GOLDEN=1, then copy the file to the frontend and update its implementation to match.'
        );
    }

    public function test_the_golden_cases_cover_every_rule_the_graph_can_break(): void
    {
        $seen = [];
        foreach ($this->cases() as $case) {
            $verdict = $this->verdict($case);
            $seen = array_merge($seen, $verdict['errors'], $verdict['warnings']);
        }

        foreach ([
            'unreachable', 'bad_target', 'missing_next', 'too_few_choices', 'dead_end', 'start_is_ending', 'no_ending',
            'single_ending', 'single_destination', 'self_loop', 'missing_alt', 'empty_screen', 'no_feedback', 'empty_choice', 'empty',
        ] as $code) {
            $this->assertContains($code, $seen, "no golden case exercises '$code'");
        }
    }
}
