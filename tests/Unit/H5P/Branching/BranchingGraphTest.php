<?php

namespace Tests\Unit\H5P\Branching;

use App\Services\lms\H5P\BranchingGraph;
use App\Services\lms\H5P\BranchingPayload;
use App\Services\lms\H5P\HtmlLite;
use PHPUnit\Framework\TestCase;

/**
 * The rules a decision tree has to satisfy, and the HTML reduction applied to
 * what goes into one.
 *
 * NO DATABASE and no Laravel: this extends the plain PHPUnit TestCase on
 * purpose. `Tests\TestCase` boots the whole application, and a provider reads
 * `custom_module_tables` from the shared live database while it does -- see
 * phpunit.xml, whose sqlite lines are commented out. Everything under test here
 * is a pure function, so nothing needs the framework.
 */
class BranchingGraphTest extends TestCase
{
    /** @return array<string,mixed> */
    private function text(string $key, ?string $next, string $body = '<p>Some words that say something useful to a learner.</p>'): array
    {
        return ['ref' => $key, 'node_type' => 'text', 'title' => 'Screen ' . $key, 'body' => $body, 'next_ref' => $next, 'options' => []];
    }

    /** @param list<array{0:string,1:?string}> $choices text => next ref */
    private function decision(string $key, array $choices): array
    {
        return [
            'ref' => $key, 'node_type' => 'branching_question', 'title' => 'Decision ' . $key, 'body' => '<p>What do you do now?</p>',
            'options' => array_map(fn ($c) => ['text' => $c[0], 'next_ref' => $c[1], 'quality' => 'best', 'feedback_title' => 'Why', 'feedback_text' => '<p>Because of the idea.</p>'], $choices),
        ];
    }

    private function ending(string $key, int $score = 0): array
    {
        return ['ref' => $key, 'node_type' => 'ending', 'title' => 'Ending ' . $key, 'body' => '<p>This is where the path stops, and what it showed.</p>', 'score' => $score, 'options' => []];
    }

    /** @return list<array<string,mixed>> start -> decision -> two screens -> two endings */
    private function validTree(): array
    {
        return [
            $this->text('a', 'd'),
            $this->decision('d', [['Look closer', 'b'], ['Guess', 'c']]),
            $this->text('b', 'e1'),
            $this->text('c', 'e2'),
            $this->ending('e1', 100),
            $this->ending('e2', 40),
        ];
    }

    private function codes(array $report, string $level = 'errors'): array
    {
        return array_column($report[$level], 'code');
    }

    public function test_a_connected_tree_has_no_errors_and_its_paths_are_counted(): void
    {
        $report = BranchingPayload::analyse($this->validTree());

        $this->assertSame([], $report['errors']);
        $this->assertSame(2, $report['stats']['paths']);
        $this->assertSame(2, $report['stats']['endings_reached']);
        $this->assertSame(4, $report['stats']['longest_path']);
        $this->assertTrue($report['stats']['acyclic']);
    }

    public function test_a_node_nobody_can_reach_is_an_error(): void
    {
        $tree = $this->validTree();
        $tree[] = $this->text('orphan', 'e1');

        $report = BranchingPayload::analyse($tree);

        $this->assertContains('unreachable', $this->codes($report));
        $this->assertStringContainsString('Step 7', implode(' ', array_column($report['errors'], 'message')));
    }

    public function test_a_branch_into_nothing_is_an_error_not_silently_dropped(): void
    {
        $tree = $this->validTree();
        $tree[1] = $this->decision('d', [['Look closer', 'b'], ['Guess', 'missing']]);

        $report = BranchingPayload::analyse($tree);

        $this->assertContains('bad_target', $this->codes($report));
    }

    public function test_a_screen_with_no_next_and_a_one_choice_decision_are_errors(): void
    {
        $tree = $this->validTree();
        $tree[0] = $this->text('a', null);
        $tree[1] = $this->decision('d', [['Only choice', 'b']]);

        $codes = $this->codes(BranchingPayload::analyse($tree));

        $this->assertContains('missing_next', $codes);
        $this->assertContains('too_few_choices', $codes);
    }

    public function test_a_loop_with_no_way_out_is_a_dead_end(): void
    {
        $tree = [
            $this->text('a', 'b'),
            $this->text('b', 'c'),
            $this->text('c', 'b'),
            $this->ending('e1'),
        ];

        $codes = $this->codes(BranchingPayload::analyse($tree));

        $this->assertContains('dead_end', $codes);
        $this->assertContains('unreachable', $codes, 'the ending is not reachable either');
    }

    public function test_a_loop_that_can_still_finish_is_allowed_and_counted_once(): void
    {
        // "Try that decision again": d -> r -> d, or d -> e.
        $tree = [
            $this->text('a', 'd'),
            $this->decision('d', [['Retry', 'r'], ['Finish', 'e1']]),
            $this->text('r', 'd'),
            $this->ending('e1', 100),
        ];

        $report = BranchingPayload::analyse($tree);

        $this->assertSame([], $report['errors']);
        $this->assertFalse($report['stats']['acyclic']);
        $this->assertSame(1, $report['stats']['paths'], 'paths are simple: no node twice');
    }

    public function test_the_first_node_cannot_be_an_ending_and_a_scenario_needs_an_ending(): void
    {
        $this->assertContains('start_is_ending', $this->codes(BranchingPayload::analyse([$this->ending('e1'), $this->text('a', 'e1')])));
        $this->assertContains('no_ending', $this->codes(BranchingPayload::analyse([$this->text('a', 'a')])));
    }

    public function test_a_single_ending_and_pointless_branching_are_warnings(): void
    {
        $tree = [
            $this->text('a', 'd'),
            $this->decision('d', [['One', 'e1'], ['Two', 'e1']]),
            $this->ending('e1'),
        ];

        $report = BranchingPayload::analyse($tree);

        $this->assertSame([], $report['errors']);
        $this->assertContains('single_ending', $this->codes($report, 'warnings'));
        $this->assertContains('single_destination', $this->codes($report, 'warnings'));
    }

    public function test_a_knowledge_check_may_rejoin_without_a_warning(): void
    {
        $tree = [
            $this->text('a', 'd'),
            ['source_question_id' => 5] + $this->decision('d', [['Right', 'e1'], ['Wrong', 'e1']]),
            $this->ending('e1'),
        ];

        $this->assertNotContains('single_destination', $this->codes(BranchingPayload::analyse($tree), 'warnings'));
    }

    public function test_enumerated_paths_are_every_simple_route_to_an_ending(): void
    {
        $paths = BranchingGraph::enumeratePaths(BranchingPayload::graphNodes($this->validTree()));

        $this->assertSame([['a', 'd', 'b', 'e1'], ['a', 'd', 'c', 'e2']], $paths);
    }

    public function test_the_path_count_is_capped_so_a_pathological_tree_cannot_hang_a_save(): void
    {
        // 14 sequential two-way decisions rejoining each time: 2^14 = 16384 routes.
        $tree = [$this->text('a', 'd0')];
        for ($i = 0; $i < 14; $i++) {
            $next = $i === 13 ? 'e1' : 'd' . ($i + 1);
            $tree[] = $this->decision('d' . $i, [['x', 'm' . $i], ['y', 'n' . $i]]);
            $tree[] = $this->text('m' . $i, $next);
            $tree[] = $this->text('n' . $i, $next);
        }
        $tree[] = $this->ending('e1');

        $report = BranchingPayload::analyse($tree);

        $this->assertTrue($report['stats']['paths_capped']);
        $this->assertSame(BranchingGraph::PATH_CAP, $report['stats']['paths']);
    }

    public function test_a_picture_without_a_description_blocks_publishing_but_not_a_draft(): void
    {
        $tree = $this->validTree();
        $tree[0]['media_path'] = 'https://cdn.example.test/a.png';
        $tree[0]['media_alt'] = '';

        $this->assertContains('missing_alt', $this->codes(BranchingPayload::analyse($tree, true)));
        $draft = BranchingPayload::analyse($tree, false);
        $this->assertNotContains('missing_alt', $this->codes($draft));
        $this->assertContains('missing_alt', $this->codes($draft, 'warnings'));
    }

    public function test_empty_content_is_an_error_and_a_choice_without_feedback_a_warning(): void
    {
        $tree = $this->validTree();
        $tree[0]['body'] = '<p>  </p>';
        $tree[1]['options'][0]['feedback_text'] = '';
        $tree[1]['options'][0]['feedback_title'] = '';

        $report = BranchingPayload::analyse($tree);

        $this->assertContains('empty_screen', $this->codes($report));
        $this->assertContains('no_feedback', $this->codes($report, 'warnings'));
    }

    public function test_saved_rows_read_the_same_as_a_payload(): void
    {
        $rows = [
            ['id' => 11, 'node_type' => 'text', 'title' => 'Start', 'body' => '<p>Words enough here.</p>', 'next_node_id' => 12, 'options' => null, 'score' => 0],
            ['id' => 12, 'node_type' => 'ending', 'title' => 'Done', 'body' => '<p>Closing words here.</p>', 'next_node_id' => null, 'options' => null, 'score' => 80],
        ];

        $nodes = BranchingPayload::fromRows($rows);

        $this->assertSame('11', $nodes[0]['ref']);
        $this->assertSame('12', $nodes[0]['next_ref']);
        $this->assertSame([], BranchingPayload::analyse($nodes)['errors']);
    }

    public function test_html_is_reduced_to_what_a_learning_screen_needs(): void
    {
        $dirty = '<div style="color:red"><h1>Big</h1><p onclick="x()">Hello <b>there</b> <script>alert(1)</script></p>'
            . '<ul><li>One</li></ul><a href="javascript:alert(1)">bad</a> <a href="https://example.org/x">good</a><iframe src="//e"></iframe></div>';

        $clean = HtmlLite::clean($dirty);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('style=', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringContainsString('<h3>Big</h3>', $clean);
        $this->assertStringContainsString('<li>One</li>', $clean);
        $this->assertStringContainsString('href="https://example.org/x"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertSame('<p>Plain words.</p>', HtmlLite::clean('Plain words.'));
        $this->assertSame('', HtmlLite::clean(null));
    }

    public function test_loose_text_beside_a_table_becomes_its_own_paragraph(): void
    {
        // The shape of a bank question: a lead-in paragraph, loose words, then a table.
        $html = '<p>Look at this.</p> The table shows four models. Which is invalid? <table><tr><td>Model I</td></tr></table> After the table.';

        $clean = HtmlLite::clean($html);

        $this->assertSame(
            '<p>Look at this.</p><p>The table shows four models. Which is invalid?</p><table><tr><td>Model I</td></tr></table><p>After the table.</p>',
            preg_replace('/>\s+</', '><', $clean)
        );
    }

    public function test_emphasis_in_loose_text_stays_with_its_paragraph(): void
    {
        $this->assertSame('<p>A <strong>bold</strong> start.</p><ul><li>one</li></ul>', HtmlLite::clean('A <b>bold</b> start.<ul><li>one</li></ul>'));
    }
}
