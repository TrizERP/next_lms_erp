<?php

namespace Tests\Unit\StudyDeck\Branching;

use App\Services\lms\H5P\BranchingGraph;
use App\Services\lms\H5P\BranchingPayload;
use App\Services\StudyDeck\Branching\BranchingScenarioService;
use App\Services\StudyDeck\Branching\ScenarioBlueprint;
use App\Services\StudyDeck\Branching\ScenarioValidator;
use App\Services\StudyDeck\Branching\SourcePack;
use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\QuestionSelector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\StudyDeck\StudyDeckFixture;

/**
 * The branching-scenario pipeline end to end, with a scripted model and the
 * synthetic chapter the study-deck tests use.
 *
 * NO DATABASE, no network, no model: every step that is code runs for real and
 * only the model's replies are canned. This extends the plain PHPUnit TestCase
 * on purpose -- `Tests\TestCase` boots Laravel, and a provider reads the shared
 * live database while it does.
 *
 * The fixture chapter has four concepts (Models, Ignoring details, Laws,
 * Theories) and, after QuestionSelector, two reusable choice questions: 101 on
 * Models and 103 on Ignoring details.
 */
class BranchingScenarioPipelineTest extends TestCase
{
    use StudyDeckFixture;

    // ---------------------------------------------------------------------
    // Scripts
    // ---------------------------------------------------------------------

    /** @return array<string,mixed> a skeleton that satisfies every rule, for the "short" size */
    private function blueprint(): array
    {
        return [
            'title' => 'Is the map the land?',
            'premise' => 'You are helping a friend plan a walk with a map.',
            'objectives' => ['Explain why a model leaves out details', 'Tell a law from a theory'],
            'nodes' => [
                ['id' => 's1', 'kind' => 'scene', 'purpose' => 'situation', 'concept_id' => 1, 'covers' => 'Meet the map and the walk', 'next' => 'd1'],
                ['id' => 'd1', 'kind' => 'decision', 'purpose' => 'investigate', 'concept_id' => 1, 'asks' => 'What to check first about the map', 'choices' => [
                    ['intent' => 'Ask what the map is for', 'quality' => 'best', 'next' => 's2'],
                    ['intent' => 'Expect every tree on the map', 'quality' => 'misstep', 'next' => 'r1'],
                ]],
                ['id' => 'r1', 'kind' => 'scene', 'purpose' => 'remedial', 'concept_id' => 1, 'covers' => 'A model is not an exact copy', 'next' => 's2'],
                ['id' => 's2', 'kind' => 'scene', 'purpose' => 'explanation', 'concept_id' => 2, 'covers' => 'Why a model ignores details', 'next' => 'd2'],
                ['id' => 'd2', 'kind' => 'decision', 'purpose' => 'check', 'question_id' => 103, 'on_correct' => 's3', 'on_wrong' => 'r2'],
                ['id' => 'r2', 'kind' => 'scene', 'purpose' => 'remedial', 'concept_id' => 2, 'covers' => 'Ignoring details is on purpose', 'next' => 's3'],
                ['id' => 's3', 'kind' => 'scene', 'purpose' => 'explanation', 'concept_id' => 3, 'covers' => 'A law describes a repeated pattern', 'next' => 'd3'],
                ['id' => 'd3', 'kind' => 'decision', 'purpose' => 'predict', 'concept_id' => 4, 'asks' => 'What explains the pattern', 'choices' => [
                    ['intent' => 'A theory explains why', 'quality' => 'best', 'next' => 'e1'],
                    ['intent' => 'The law explains why', 'quality' => 'partial', 'next' => 's4'],
                    ['intent' => 'Patterns need no explanation', 'quality' => 'misstep', 'next' => 'r3'],
                ]],
                ['id' => 's4', 'kind' => 'scene', 'purpose' => 'consequence', 'concept_id' => 4, 'covers' => 'A law says what, not why', 'next' => 'e2'],
                ['id' => 'r3', 'kind' => 'scene', 'purpose' => 'consequence', 'concept_id' => 4, 'covers' => 'Without a theory the pattern is a puzzle', 'next' => 'e3'],
                ['id' => 'e1', 'kind' => 'ending', 'purpose' => 'summary', 'concept_id' => 4, 'outcome' => 'best', 'covers' => 'You separated pattern from explanation'],
                ['id' => 'e2', 'kind' => 'ending', 'purpose' => 'summary', 'concept_id' => 3, 'outcome' => 'partial', 'covers' => 'You described but did not explain'],
                ['id' => 'e3', 'kind' => 'ending', 'purpose' => 'summary', 'concept_id' => 4, 'outcome' => 'revisit', 'covers' => 'Revisit laws and theories'],
            ],
        ];
    }

    /** Letters-only filler unique to a node, so two nodes never read as the same text. */
    private function token(string $id, int $k): string
    {
        return strtr($id, '0123456789', 'abcdefghij') . str_repeat(chr(97 + $k), 4);
    }

    private function prose(string $id): string
    {
        // A sentence of the chapter's own ideas, then a run of words no other node shares: real text differs node
        // to node, and ScenarioValidator rejects two screens that say the same thing.
        $own = implode(' ', array_map(fn ($k) => $this->token($id, $k), range(0, 17)));

        return 'A model keeps only the details that the question needs. A law describes a repeated pattern. '
            . 'Consider ' . $own . ' as you think about it.';
    }

    /** What a well-behaved writer returns for the nodes a prompt asks for. */
    private function writerReply(string $prompt, array $blueprint, array $over = []): string
    {
        preg_match_all('/^### (\S+) \((scene|decision|ending)/m', $prompt, $m);
        $byId = [];
        foreach ($blueprint['nodes'] as $n) {
            $byId[$n['id']] = $n;
        }

        $out = [];
        foreach ($m[1] as $id) {
            $n = $byId[$id];
            if ($n['kind'] === 'decision' && isset($n['question_id'])) {
                $out[$id] = ['title' => 'Check what you know', 'lead_in' => '<p>Before you go on, see if the idea holds.</p>'];
            } elseif ($n['kind'] === 'decision') {
                $out[$id] = [
                    'title' => 'Make a decision',
                    'question_html' => '<p>What would you do next, and what would you rely on to decide that?</p>',
                    'choices' => array_map(fn ($c, $k) => [
                        'text' => 'Choose ' . $this->token($id, $k + 4),
                        'feedback_title' => 'What happens',
                        'feedback_text' => '<p>This leads somewhere because ' . $this->token($id, $k + 8) . ' shows how a model keeps what the question needs and ignores the rest.</p>',
                    ], $n['choices'], array_keys($n['choices'])),
                ];
            } else {
                $out[$id] = ['title' => 'Step ' . strtr($id, '0123456789', 'abcdefghij'), 'body_html' => '<p>' . $this->prose($id) . '</p>' . ($n['kind'] === 'ending' ? '<ul><li>Keep what matters</li><li>Say why</li></ul>' : ''), 'visual' => $n['kind'] === 'scene' && $id === 's1' ? 'A simple sketch map' : null];
            }
        }

        return json_encode(['nodes' => array_replace_recursive($out, $over)]);
    }

    /**
     * A scripted model. $planner and $writer are closures (prompt, callNumber) => reply.
     * The model is told apart by its system prompt, as the real one is.
     */
    private function model(?callable $planner = null, ?callable $writer = null): object
    {
        $planner ??= fn () => json_encode($this->blueprint());
        $writer ??= fn (string $prompt) => $this->writerReply($prompt, $this->blueprint());

        return new class($planner, $writer) implements Completer {
            /** @var list<array{role:string,prompt:string}> */
            public array $calls = [];

            public function __construct(private $planner, private $writer)
            {
            }

            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                $role = str_contains($system, 'You design decision-based lessons') ? 'planner' : 'writer';
                $this->calls[] = ['role' => $role, 'prompt' => $prompt];
                $n = count(array_filter($this->calls, fn ($c) => $c['role'] === $role));

                return $role === 'planner' ? ($this->planner)($prompt, $n) : ($this->writer)($prompt, $n);
            }

            public function count(string $role): int
            {
                return count(array_filter($this->calls, fn ($c) => $c['role'] === $role));
            }
        };
    }

    private function generate(object $model, array $options = [], ?array $raw = null): array
    {
        return BranchingScenarioService::make($model)->fromRaw($raw ?? $this->raw(), $options + ['length' => 'short', 'concept_ids' => [1, 2, 3, 4]]);
    }

    private function pack(array $options = []): SourcePack
    {
        $raw = $this->raw();
        $context = (new ConceptContextBuilder())->assemble($raw);
        $eligible = (new QuestionSelector())->select($raw['questions'], 3, ['mcq', 'true_false'])['eligible'];

        return SourcePack::choose($context, $options + ['length' => 'short', 'concept_ids' => [1, 2, 3, 4]], $eligible);
    }

    // ---------------------------------------------------------------------
    // The whole pipeline
    // ---------------------------------------------------------------------

    public function test_a_well_formed_scenario_passes_and_is_saveable_as_it_stands(): void
    {
        $model = $this->model();
        $r = $this->generate($model);

        $this->assertSame([], $r['report']['errors']);
        $this->assertTrue($r['report']['ok']);
        $this->assertSame(1, $model->count('planner'));
        $this->assertSame(3, $model->count('writer'), '13 nodes, six to a call');

        $payload = $r['scenario'];
        $this->assertCount(13, $payload['nodes']);
        $this->assertSame('s1', $payload['nodes'][0]['ref'], 'the start stays first');
        $this->assertSame(['e1', 'e2', 'e3'], array_slice(array_column($payload['nodes'], 'ref'), -3), 'endings last, best first');
        $this->assertSame([100, 55, 30], array_column(array_slice($payload['nodes'], -3), 'score'));

        // The payload satisfies the very rules the editor and publish apply.
        $analysis = BranchingPayload::analyse($payload['nodes'], true);
        $this->assertSame([], $analysis['errors']);
        $this->assertSame(3, $analysis['stats']['decisions']);
        $this->assertSame(3, $analysis['stats']['endings']);
        // Two ways through the first decision, two through the check, three through the last: twelve routes, three endings.
        $this->assertSame(12, $analysis['stats']['paths']);
        $this->assertSame(3, $analysis['stats']['endings_reached']);
        $this->assertSame(['s1', 'd1', 's2', 'd2', 'r2', 's3', 'd3', 'e1'], BranchingGraph::enumeratePaths(BranchingPayload::graphNodes($payload['nodes']))[0] ?? null);

        $this->assertSame('static-end-score', $payload['scoring_option']);
        $this->assertSame([1, 2, 3, 4], $payload['generation']['concept_ids']);
        $this->assertSame([103], $payload['generation']['question_ids']);
        $this->assertSame([10, 11], $payload['generation']['topic_ids']);
        $this->assertSame(['Explain why a model leaves out details', 'Tell a law from a theory'], $payload['learning_objectives']);
        $this->assertSame('A simple sketch map', $r['report']['suggestions'][0]['idea'], 'picture ideas are offered, not invented as images');
    }

    public function test_a_bank_question_is_reproduced_verbatim_and_its_own_explanation_is_the_feedback(): void
    {
        $nodes = array_column($this->generate($this->model())['scenario']['nodes'], null, 'ref');
        $check = $nodes['d2'];

        $this->assertSame(103, $check['source_question_id']);
        $this->assertSame(2, $check['concept_id']);
        $this->assertStringContainsString('Why does a map ignore trees?', $check['body'], 'the stem, word for word');
        $this->assertStringContainsString('Before you go on', $check['body'], 'with the writer\'s connecting sentence in front');
        $this->assertSame(['First choice', 'Second choice'], array_column($check['options'], 'text'));
        $this->assertSame(['misstep', 'best'], array_column($check['options'], 'quality'));
        $this->assertSame(['r2', 's3'], array_column($check['options'], 'next_ref'), 'wrong goes to the remedial screen, right moves on');
        foreach ($check['options'] as $option) {
            $this->assertStringContainsString('The second choice is right because a model keeps only what the question needs.', $option['feedback_text']);
        }
    }

    public function test_the_scenario_teaches_it_is_not_a_collection_of_questions(): void
    {
        $r = $this->generate($this->model());
        $screens = array_filter($r['scenario']['nodes'], fn ($n) => $n['node_type'] === 'text');
        $decisions = array_filter($r['scenario']['nodes'], fn ($n) => $n['node_type'] === 'branching_question');

        $this->assertGreaterThan(count($decisions), count($screens));
        $this->assertGreaterThanOrEqual(300, $r['report']['stats']['teaching_words']);
        $this->assertSame(1, $r['report']['stats']['bank_questions']);
        $this->assertSame(4, $r['report']['stats']['concepts_covered']);
    }

    public function test_nothing_is_stored_or_read_beyond_the_rows_it_was_given(): void
    {
        // fromRaw takes rows and returns arrays: there is no connection to use. The assertion is that it ran at all
        // with the framework unbooted, which is what makes this suite safe against the shared database.
        $this->assertFalse(class_exists(\Illuminate\Foundation\Application::class, false) && \Illuminate\Container\Container::getInstance() instanceof \Illuminate\Foundation\Application);
        $this->assertTrue($this->generate($this->model())['report']['ok']);
    }

    // ---------------------------------------------------------------------
    // Stage 1: the skeleton
    // ---------------------------------------------------------------------

    public function test_a_skeleton_with_a_broken_link_is_sent_back_with_the_exact_problem(): void
    {
        $bad = $this->blueprint();
        $bad['nodes'][1]['choices'][1]['next'] = 'nowhere';

        $model = $this->model(fn (string $prompt, int $n) => json_encode($n === 1 ? $bad : $this->blueprint()));
        $r = $this->generate($model);

        $this->assertTrue($r['report']['ok']);
        $this->assertSame(2, $model->count('planner'));
        $repair = array_values(array_filter($model->calls, fn ($c) => $c['role'] === 'planner'))[1]['prompt'];
        $this->assertStringContainsString('YOUR PREVIOUS REPLY WAS', $repair);
        $this->assertStringContainsString('leads to a step that does not exist', $repair);
    }

    public function test_a_skeleton_that_never_passes_stops_the_run_with_the_problems_named(): void
    {
        $bad = $this->blueprint();
        $bad['nodes'][1]['choices'][1]['next'] = 'nowhere';
        $model = $this->model(fn () => json_encode($bad));

        try {
            $this->generate($model);
            $this->fail('expected the run to stop');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('could not be planned', $e->getMessage());
            $this->assertStringContainsString('leads to a step that does not exist', $e->getMessage());
        }

        $this->assertSame(3, $model->count('planner'), 'the first try and two repair rounds');
        $this->assertSame(0, $model->count('writer'), 'no prose is written for a structure that is not sound');
    }

    public function test_a_reply_that_is_not_json_counts_as_a_failed_attempt(): void
    {
        $model = $this->model(fn (string $p, int $n) => $n === 1 ? 'Sorry, here is some prose instead.' : json_encode($this->blueprint()));

        $this->assertTrue($this->generate($model)['report']['ok']);
        $this->assertSame(2, $model->count('planner'));
    }

    /** @return array<string,array{0:callable,1:string}> */
    public static function brokenSkeletons(): array
    {
        return [
            'no best choice' => [function (array $b) { $b['nodes'][1]['choices'][0]['quality'] = 'partial'; return $b; }, 'no "best" choice'],
            'a loop' => [function (array $b) { $b['nodes'][2]['next'] = 's1'; return $b; }, 'contain a loop'],
            'a misstep straight to a best ending' => [function (array $b) { $b['nodes'][1]['choices'][1]['next'] = 'e1'; return $b; }, 'must lead to a scene with purpose consequence or remedial'],
            'a wrong answer that is not explained' => [function (array $b) { $b['nodes'][5]['purpose'] = 'explanation'; return $b; }, '"on_wrong" must lead to a scene'],
            'an unknown concept' => [function (array $b) { $b['nodes'][0]['concept_id'] = 99; return $b; }, 'not one of the chosen concepts'],
            'a question that was not offered' => [function (array $b) { $b['nodes'][4]['question_id'] = 999; return $b; }, 'not one of the offered questions'],
            'opens without a situation' => [function (array $b) { $b['nodes'][0]['purpose'] = 'explanation'; return $b; }, 'must be a scene with purpose "situation"'],
            'only best endings' => [function (array $b) { $b['nodes'][11]['outcome'] = 'best'; $b['nodes'][12]['outcome'] = 'best'; return $b; }, 'Every ending is "best"'],
            'a concept left untaught' => [function (array $b) { foreach ($b['nodes'] as &$n) { if (($n['concept_id'] ?? null) === 3) { $n['concept_id'] = 4; } } return $b; }, 'not taught by any node'],
            'an orphan node' => [function (array $b) { $b['nodes'][] = ['id' => 'x1', 'kind' => 'scene', 'purpose' => 'explanation', 'concept_id' => 1, 'covers' => 'Unlinked', 'next' => 'e1']; return $b; }, 'cannot be reached from the start'],
            'a dead end' => [function (array $b) { $b['nodes'][8]['next'] = 'r3'; $b['nodes'][9]['next'] = 's4'; return $b; }, 'loop'],
            'a decision with one choice' => [function (array $b) { array_pop($b['nodes'][1]['choices']); return $b; }, 'needs 2 to 4 "choices"'],
            'a title in Title Case' => [function (array $b) { $b['title'] = 'Will It Clear the Rope? Modelling a Cricket Six'; return $b; }, 'Write the title in sentence case'],
            'an objective that is a paragraph' => [function (array $b) { $b['objectives'][0] = str_repeat('Learners will be able to explain the idea in some detail ', 4); return $b; }, 'Shorten it to one sentence'],
            'too many question checks' => [function (array $b) { $b['nodes'][2] = ['id' => 'r1', 'kind' => 'decision', 'purpose' => 'check', 'question_id' => 101, 'on_correct' => 's2', 'on_wrong' => 's2']; return $b; }, 'uses 2 bank questions'],
        ];
    }

    /**
     * @see brokenSkeletons()
     */
    #[DataProvider('brokenSkeletons')]
    public function test_each_structural_rule_rejects_what_it_exists_to_reject(callable $break, string $expected): void
    {
        $result = ScenarioBlueprint::check($break($this->blueprint()), $this->pack(), 'short');

        $this->assertStringContainsString($expected, implode("\n", $result['errors']));
    }

    public function test_the_good_skeleton_passes_every_rule(): void
    {
        $this->assertSame([], ScenarioBlueprint::check($this->blueprint(), $this->pack(), 'short')['errors']);
    }

    public function test_sentence_case_titles_with_proper_names_are_not_mistaken_for_title_case(): void
    {
        foreach (['Is the map the land?', 'Meghnad Saha and the colour of stars', 'Reading a thermometer', 'Models', 'How Newton saw the Moon fall'] as $title) {
            $this->assertFalse(ScenarioBlueprint::looksLikeTitleCase($title), $title);
        }
        foreach (['Will It Clear the Rope? Modelling a Cricket Six', 'The Water Cycle And Its Stages'] as $title) {
            $this->assertTrue(ScenarioBlueprint::looksLikeTitleCase($title), $title);
        }
    }

    // ---------------------------------------------------------------------
    // Stage 2: the words
    // ---------------------------------------------------------------------

    public function test_the_writer_cannot_add_remove_or_relink_a_node(): void
    {
        $model = $this->model(null, fn (string $prompt) => $this->writerReply($prompt, $this->blueprint(), [
            'zz' => ['title' => 'Smuggled', 'body_html' => '<p>Extra.</p>'],
        ]));

        $r = $this->generate($model);

        $this->assertTrue($r['report']['ok']);
        $this->assertNotContains('zz', array_column($r['scenario']['nodes'], 'ref'));
        $this->assertSame(array_column($this->blueprint()['nodes'], 'id'), array_intersect(array_column($this->blueprint()['nodes'], 'id'), array_column($r['scenario']['nodes'], 'ref')));
        // The links are the skeleton's, whatever the writer said.
        $links = array_column($r['scenario']['nodes'], 'next_ref', 'ref');
        $this->assertSame('d1', $links['s1']);
    }

    public function test_a_node_the_writer_leaves_out_is_asked_for_again(): void
    {
        $model = $this->model(null, function (string $prompt, int $n) {
            $reply = json_decode($this->writerReply($prompt, $this->blueprint()), true);
            if ($n === 1) {
                unset($reply['nodes']['s2']);
            }

            return json_encode($reply);
        });

        $r = $this->generate($model);

        $this->assertTrue($r['report']['ok']);
        $this->assertSame(4, $model->count('writer'), 'three chunks plus one re-ask');
        $reask = array_values(array_filter($model->calls, fn ($c) => $c['role'] === 'writer'))[1]['prompt'];
        $this->assertStringContainsString('no text was returned', $reask);
    }

    public function test_a_choice_count_that_does_not_match_the_skeleton_is_refused(): void
    {
        $model = $this->model(null, function (string $prompt, int $n) {
            $reply = json_decode($this->writerReply($prompt, $this->blueprint()), true);
            if ($n === 1) {
                array_pop($reply['nodes']['d1']['choices']);
            }

            return json_encode($reply);
        });

        $this->assertTrue($this->generate($model)['report']['ok']);
        $retry = array_values(array_filter($model->calls, fn ($c) => $c['role'] === 'writer'))[1]['prompt'];
        $this->assertStringContainsString('it needs exactly 2 choices', $retry);
    }

    // ---------------------------------------------------------------------
    // Validation and the targeted rewrite
    // ---------------------------------------------------------------------

    public function test_an_invented_number_is_caught_and_only_that_node_is_rewritten(): void
    {
        $model = $this->model(null, function (string $prompt, int $n) {
            $repairing = str_contains($prompt, 'YOUR PREVIOUS REPLY WAS');
            $over = $repairing ? [] : ['s1' => ['body_html' => '<p>' . $this->prose('s1') . ' The lake is 47 metres deep.</p>']];

            return $this->writerReply($prompt, $this->blueprint(), $over);
        });

        $r = $this->generate($model);

        $this->assertTrue($r['report']['ok'], implode('; ', $r['report']['errors']));
        $this->assertSame(4, $model->count('writer'), 'three chunks and one targeted rewrite');
        $fix = array_values(array_filter($model->calls, fn ($c) => $c['role'] === 'writer'))[3]['prompt'];
        $this->assertStringContainsString('47', $fix);
        $this->assertStringContainsString('### s1 (scene', $fix);
        $this->assertStringNotContainsString('### s2 (scene', $fix, 'a node that passed is not asked for again');
        $this->assertStringNotContainsString('47', json_encode($r['scenario']));
    }

    public function test_a_node_that_cannot_be_fixed_leaves_the_scenario_not_ready(): void
    {
        $model = $this->model(null, fn (string $prompt) => $this->writerReply($prompt, $this->blueprint(), [
            's1' => ['body_html' => '<p>' . $this->prose('s1') . ' The lake is 47 metres deep.</p>'],
        ]));

        $r = $this->generate($model);

        $this->assertFalse($r['report']['ok']);
        $this->assertStringContainsString('47', implode(' ', $r['report']['errors']));
        $this->assertArrayNotHasKey('by_node', $r['report'], 'the internal index is not part of the report');
    }

    public function test_the_validator_rejects_what_a_learner_would_be_misled_or_stuck_by(): void
    {
        $r = $this->generate($this->model());
        $payload = $r['scenario'];
        $pack = $this->pack();
        $validate = fn (array $p, array $options = []) => (new ScenarioValidator())->validate($p, $pack, $options)['errors'];
        $nodeAt = function (array $p, string $ref): int {
            foreach ($p['nodes'] as $i => $n) {
                if ($n['ref'] === $ref) {
                    return $i;
                }
            }
            $this->fail('no node ' . $ref);
        };

        $this->assertSame([], $validate($payload));

        // A screen too thin to teach anything.
        $p = $payload;
        $p['nodes'][$nodeAt($p, 's2')]['body'] = '<p>Models matter.</p>';
        $this->assertStringContainsString('too little to teach', implode(' ', $validate($p)));

        // The same feedback on every choice: the choice does not matter.
        $p = $payload;
        $same = $p['nodes'][$nodeAt($p, 'd1')]['options'][0]['feedback_text'];
        $p['nodes'][$nodeAt($p, 'd1')]['options'][1]['feedback_text'] = $same;
        $this->assertStringContainsString('the same feedback for every choice', implode(' ', $validate($p)));

        // A scolding tone.
        $p = $payload;
        $p['nodes'][$nodeAt($p, 'r1')]['body'] = str_replace('A model keeps', 'Oops, a model keeps', $p['nodes'][$nodeAt($p, 'r1')]['body']);
        $this->assertStringContainsString('explained, not scolded', implode(' ', $validate($p)));

        // Pointing at material the learner never sees.
        $p = $payload;
        $p['nodes'][$nodeAt($p, 's3')]['body'] = str_replace('A law describes', 'As the textbook says, a law describes', $p['nodes'][$nodeAt($p, 's3')]['body']);
        $this->assertStringContainsString('learner sees only this scenario', implode(' ', $validate($p)));

        // A screen that repeats another.
        $p = $payload;
        $p['nodes'][$nodeAt($p, 'r2')]['body'] = $p['nodes'][$nodeAt($p, 's2')]['body'];
        $this->assertStringContainsString('repeats', implode(' ', $validate($p)));

        // A concept no step teaches.
        $p = $payload;
        foreach ($p['nodes'] as &$n) {
            if (($n['concept_id'] ?? null) === 3) {
                $n['concept_id'] = 4;
            }
        }
        unset($n);
        $this->assertStringContainsString('is not taught by any step', implode(' ', $validate($p)));

        // A scenario whose title is one the chapter already has.
        $this->assertStringContainsString('already exists for this chapter', implode(' ', $validate($payload, ['existing' => [['title' => 'is the map the land?', 'concept_ids' => []]]])));
    }

    public function test_a_scenario_of_mostly_questions_is_not_a_lesson(): void
    {
        $payload = $this->generate($this->model())['scenario'];
        $pack = $this->pack();

        // Turn every screen but the first two into a decision-shaped node: a quiz in all but name.
        $kept = array_slice(array_filter($payload['nodes'], fn ($n) => $n['node_type'] === 'text'), 0, 2);
        $others = array_filter($payload['nodes'], fn ($n) => $n['node_type'] !== 'text');
        $payload['nodes'] = array_values(array_merge($kept, $others));

        $codes = array_column((new ScenarioValidator())->validate($payload, $pack)['issues'], 'code');

        $this->assertContains('too_many_questions', $codes);
    }

    // ---------------------------------------------------------------------
    // Choosing what a scenario rests on
    // ---------------------------------------------------------------------

    public function test_concepts_from_another_chapter_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('None of the chosen concepts belong to this chapter');

        $this->pack(['concept_ids' => [777, 778]]);
    }

    public function test_a_topic_chooses_its_concepts_and_unknown_ones_are_dropped(): void
    {
        $this->assertSame([1, 2], $this->pack(['topic_ids' => [10], 'concept_ids' => []])->conceptIds());
        $this->assertSame([3], $this->pack(['concept_ids' => [3, 777]])->conceptIds());
    }

    public function test_concepts_are_put_in_teaching_order_prerequisites_first(): void
    {
        $this->assertSame([1, 2, 3, 4], SourcePack::teachingOrder([4, 2, 3, 1], [
            ['concept_id' => 2, 'requires_id' => 1],
            ['concept_id' => 4, 'requires_id' => 3],
        ]));
        $this->assertSame([4, 1], SourcePack::teachingOrder([1, 4], [['concept_id' => 1, 'requires_id' => 4]]));
        // A cycle in the data does not hang or drop a concept.
        $this->assertSame([1, 2], SourcePack::teachingOrder([1, 2], [['concept_id' => 1, 'requires_id' => 2], ['concept_id' => 2, 'requires_id' => 1]]));
    }

    public function test_with_nothing_chosen_the_concepts_that_suit_a_decision_are_picked(): void
    {
        // "Models" has a recorded misconception and a relationship; the others have neither.
        $ranked = $this->pack(['length' => 'short', 'concept_ids' => []])->conceptIds();

        $this->assertCount(3, $ranked, 'a short scenario rests on three');
        $this->assertContains(1, $ranked);
    }

    public function test_more_than_eight_concepts_is_refused(): void
    {
        $raw = $this->raw();
        for ($i = 5; $i <= 12; $i++) {
            $raw['concepts'][] = ['id' => $i, 'name' => 'Extra ' . $i, 'description' => null, 'definition' => null, 'topic_id' => 10, 'estimated_mastery_minutes' => 5];
        }
        $context = (new ConceptContextBuilder())->assemble($raw);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at most 8 concepts');

        SourcePack::choose($context, ['concept_ids' => range(1, 12)], []);
    }

    public function test_excerpts_are_the_chapter_paragraphs_that_mention_the_concepts(): void
    {
        $raw = $this->raw();
        $raw['ground_truth'] = "A scientific model is a simplified representation of a real system, and a model ignores details.\n\n"
            . "The story of the printing press began in a workshop long ago and has nothing to do with the rest.\n\n"
            . "A law describes a repeated pattern that scientists keep finding across many experiments.";
        $pack = SourcePack::choose((new ConceptContextBuilder())->assemble($raw), ['concept_ids' => [1, 3]], []);

        $excerpt = $pack->excerpts([1, 3]);

        $this->assertStringContainsString('scientific model', $excerpt);
        $this->assertStringContainsString('repeated pattern', $excerpt);
        $this->assertStringNotContainsString('printing press', $excerpt);
    }

    public function test_a_chapter_without_text_is_refused_rather_than_written_from_memory(): void
    {
        $service = BranchingScenarioService::make($this->model());
        $loader = new class extends ConceptContextBuilder {
            public function load(int $chapterId, int $tenantId): array
            {
                return ['ground_truth' => '  '] + [];
            }
        };

        $service = new BranchingScenarioService($this->model(), $loader);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no chapter text');

        $service->generate(1, 1, ['length' => 'short']);
    }
}
