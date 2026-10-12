<?php

namespace Tests\Unit\H5P\Branching;

use App\Http\Controllers\lms\h5p\H5PBranchingScenarioController;
use App\Models\lms\h5p\H5pBranchingNode;
use App\Models\lms\h5p\H5pBranchingScenario;
use App\Services\lms\H5P\BranchingPayload;
use App\Services\lms\H5P\H5PBranchingScenarioBuilder;
use App\Services\lms\H5P\H5PBranchingScenarioPackageService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The controller's write paths, against the real migration, on a PRIVATE
 * in-memory SQLite database.
 *
 * The other suites in this folder prove the pure logic. This one proves that
 * rows really go in and come out the way that logic assumes: the migration
 * creates what the models expect, refs become ids, edges are remapped on
 * duplicate, a dangling ref is refused, and publishing reads the saved graph.
 *
 * It never touches the shared database. The connection is a Capsule of its own,
 * the models use it because it is the only resolver set, and the framework is
 * not booted (phpunit.xml points the default connection at the live
 * vivek_erp). What it does NOT cover is the HTTP layer -- request validation,
 * session tenancy, middleware -- which needs the booted app and so the live DB.
 */
class BranchingScenarioPersistenceTest extends TestCase
{
    use BootsMiniApp;

    private H5PBranchingScenarioController $controller;

    protected function setUp(): void
    {
        $this->bootMiniApp();

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // The migration uses the Schema facade.
        $app = \Illuminate\Container\Container::getInstance();
        $app->instance('db.schema', $capsule->schema());
        // ValidationException::withMessages() asks the container for a validator.
        $app->instance('validator', new \Illuminate\Validation\Factory(
            new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en')
        ));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $migration = require dirname(__DIR__, 4) . '/database/migrations/2026_10_10_120000_create_h5p_branching_scenario_tables.php';
        $migration->up();

        $builder = new H5PBranchingScenarioBuilder();
        $this->controller = new H5PBranchingScenarioController($builder, new H5PBranchingScenarioPackageService($builder));
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        $this->shutDownMiniApp();
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        $body = '<p>' . str_repeat('A model keeps only the details that the question needs. ', 3) . '</p>';
        $choice = fn (string $text, ?string $next, string $quality) => [
            'text' => $text, 'next_ref' => $next, 'quality' => $quality,
            'feedback_title' => 'What happens', 'feedback_text' => '<p>It leads on because the idea explains it.</p>',
        ];

        return [
            'title' => 'Is the map the land?',
            'nodes' => [
                ['ref' => 'n1', 'node_type' => 'text', 'purpose' => 'situation', 'title' => 'The walk', 'body' => '<div onclick="x()">' . $body . '</div><script>alert(1)</script>', 'next_ref' => 'n2', 'score' => 99, 'concept_id' => 4],
                ['ref' => 'n2', 'node_type' => 'branching_question', 'purpose' => 'investigate', 'title' => 'Decide', 'body' => '<p>What do you check first about the map?</p>', 'options' => [
                    $choice('Ask what it is for', 'n3', 'best'),
                    $choice('Expect every tree', 'n4', 'misstep'),
                ], 'source_question_id' => 55],
                ['ref' => 'n3', 'node_type' => 'text', 'purpose' => 'explanation', 'title' => 'A map is a model', 'body' => $body, 'next_ref' => 'n5'],
                ['ref' => 'n4', 'node_type' => 'text', 'purpose' => 'remedial', 'title' => 'Not an exact copy', 'body' => $body, 'next_ref' => 'n3'],
                ['ref' => 'n5', 'node_type' => 'ending', 'purpose' => 'summary', 'title' => 'Well reasoned', 'body' => $body . '<ul><li>One</li></ul>', 'score' => 100],
                ['ref' => 'n6', 'node_type' => 'ending', 'purpose' => 'summary', 'title' => 'Unreached', 'body' => $body, 'score' => 40],
            ],
        ];
    }

    /** A third choice into the ending nothing led to, so every node is reachable. */
    private function withALinkToTheLastEnding(array $payload): array
    {
        $payload['nodes'][1]['options'][] = [
            'text' => 'Guess from the colour', 'next_ref' => 'n6', 'quality' => 'partial',
            'feedback_title' => 'Only a start', 'feedback_text' => '<p>Colour is a clue, not a measurement.</p>',
        ];

        return $payload;
    }

    private function makeScenario(): H5pBranchingScenario
    {
        return H5pBranchingScenario::create([
            'title' => 'Is the map the land?', 'chapter_id' => 9, 'subject_id' => 2, 'standard_id' => 9, 'sub_institute_id' => 1,
            'status' => 'draft', 'scoring_option' => 'static-end-score', 'pass_percentage' => 60,
        ]);
    }

    private function call(string $method, mixed ...$args): mixed
    {
        $m = new ReflectionMethod($this->controller, $method);

        return $m->invoke($this->controller, ...$args);
    }

    private function save(array $payload): H5pBranchingScenario
    {
        $scenario = $this->makeScenario();
        $this->call('syncChildren', $scenario, $payload, 1, 5);

        return $scenario->load('nodes');
    }

    public function test_refs_become_ids_and_every_edge_points_at_a_saved_node(): void
    {
        $scenario = $this->save($this->payload());
        $nodes = $scenario->nodes->keyBy('title');

        $this->assertCount(6, $nodes);
        $this->assertSame([0, 1, 2, 3, 4, 5], $scenario->nodes->pluck('node_index')->all());
        $this->assertSame($nodes['A map is a model']->id, $nodes['Not an exact copy']->next_node_id, 'a node can lead back into an earlier one');
        $this->assertSame($nodes['Decide']->id, $nodes['The walk']->next_node_id);

        $options = $nodes['Decide']->options;
        $this->assertSame([$nodes['A map is a model']->id, $nodes['Not an exact copy']->id], array_column($options, 'next_node_id'));
        $this->assertSame(['best', 'misstep'], array_column($options, 'quality'));
        $this->assertSame(55, $nodes['Decide']->source_question_id);
        $this->assertSame(4, $nodes['The walk']->concept_id);
    }

    public function test_stored_html_is_reduced_and_only_an_ending_carries_a_score(): void
    {
        $nodes = $this->save($this->payload())->nodes->keyBy('title');

        $this->assertStringNotContainsString('script', $nodes['The walk']->body);
        $this->assertStringNotContainsString('onclick', $nodes['The walk']->body);
        $this->assertSame(0, $nodes['The walk']->score, 'a score sent on a screen must not inflate the maximum');
        $this->assertSame(100, $nodes['Well reasoned']->score);
    }

    public function test_the_best_ending_is_what_a_full_marks_attempt_is_worth(): void
    {
        $scenario = $this->save($this->payload());

        $this->assertSame(100, $scenario->maxScore());

        $scenario->scoring_option = 'no-score';
        $this->assertSame(0, $scenario->maxScore(), 'an unscored scenario is not marked');
    }

    public function test_a_choice_into_a_node_that_is_not_in_the_payload_is_refused_not_dropped(): void
    {
        $payload = $this->payload();
        $payload['nodes'][1]['options'][1]['next_ref'] = 'ghost';

        try {
            $this->save($payload);
            $this->fail('a dangling ref should have been refused');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('leads to a step that is not in this scenario', implode(' ', $e->errors()['nodes']));
        }
    }

    public function test_two_nodes_sharing_a_ref_are_refused(): void
    {
        $payload = $this->payload();
        $payload['nodes'][2]['ref'] = 'n1';

        $this->expectException(ValidationException::class);
        $this->save($payload);
    }

    public function test_a_draft_may_be_unfinished_but_publishing_reads_the_saved_graph(): void
    {
        // n6 is an ending nothing leads to, so the saved graph has an unreachable node.
        $scenario = $this->save($this->payload());

        $blocker = $this->call('publishBlocker', $scenario);

        $this->assertNotNull($blocker);
        $this->assertStringContainsString('cannot be reached from the start', $blocker);

        // Lead somewhere into it and the same scenario can be published.
        $payload = $this->payload();
        $payload = $this->withALinkToTheLastEnding($payload);
        $fixed = $this->save($payload);

        $this->assertNull($this->call('publishBlocker', $fixed), (string) $this->call('publishBlocker', $fixed));
    }

    public function test_a_screen_with_no_next_is_saved_in_a_draft_and_reported_at_publish(): void
    {
        $payload = $this->payload();
        $payload['nodes'][2]['next_ref'] = null;

        $scenario = $this->save($payload);

        $this->assertNull($scenario->nodes->firstWhere('title', 'A map is a model')->next_node_id);
        $this->assertStringContainsString('does not lead anywhere', (string) $this->call('publishBlocker', $scenario));
    }

    public function test_a_duplicate_remaps_every_edge_to_its_own_nodes(): void
    {
        $payload = $this->payload();
        $payload = $this->withALinkToTheLastEnding($payload);
        $original = $this->save($payload);

        $copy = $this->makeScenario();
        $this->call('duplicateChildren', $original, $copy, 1, 5);
        $copy->load('nodes');

        $this->assertCount(6, $copy->nodes);
        $originalIds = $original->nodes->pluck('id')->all();
        $copyIds = $copy->nodes->pluck('id')->all();
        $this->assertSame([], array_intersect($originalIds, $copyIds), 'no node is shared');

        foreach ($copy->nodes as $node) {
            if ($node->next_node_id !== null) {
                $this->assertContains($node->next_node_id, $copyIds, 'a copied screen leads into the copy');
            }
            foreach ((array) $node->options as $option) {
                $this->assertContains($option['next_node_id'], $copyIds, 'a copied choice leads into the copy');
            }
        }

        $this->assertNull($this->call('publishBlocker', $copy), 'the copy is as publishable as the original');
        $this->assertSame(
            BranchingPayload::analyse(BranchingPayload::fromRows($original->nodes))['stats']['paths'],
            BranchingPayload::analyse(BranchingPayload::fromRows($copy->nodes))['stats']['paths']
        );
    }

    public function test_a_saved_scenario_builds_h5p_params_whose_links_all_resolve(): void
    {
        $payload = $this->payload();
        $payload = $this->withALinkToTheLastEnding($payload);
        $scenario = $this->save($payload);
        $scenario->update(['title' => 'Is the map the land?', 'learning_objectives' => ['Say why a model leaves things out']]);

        $bs = (new H5PBranchingScenarioBuilder())->build($scenario->fresh('nodes'))['branchingScenario'];

        $entries = count($bs['content']);
        $screens = array_column($bs['endScreens'], 'contentId');
        $targets = [];
        foreach ($bs['content'] as $item) {
            $targets[] = $item['nextContentId'];
            foreach ($item['type']['params']['branchingQuestion']['alternatives'] ?? [] as $alt) {
                $targets[] = $alt['nextContentId'];
            }
        }
        foreach ($targets as $target) {
            $this->assertTrue($target < 0 ? in_array($target, $screens, true) : $target < $entries, 'link ' . $target . ' resolves');
        }
    }

    public function test_the_migration_is_reversible(): void
    {
        $migration = require dirname(__DIR__, 4) . '/database/migrations/2026_10_10_120000_create_h5p_branching_scenario_tables.php';
        $migration->down();

        $this->assertFalse(Capsule::schema()->hasTable('h5p_branching_nodes'));
        $this->assertFalse(Capsule::schema()->hasTable('h5p_branching_scenario'));
    }

    public function test_nodes_belong_to_one_scenario_only(): void
    {
        $a = $this->save($this->payload());
        $b = $this->save($this->payload());

        $this->assertSame(6, H5pBranchingNode::where('scenario_id', $a->id)->count());
        $this->assertSame(6, H5pBranchingNode::where('scenario_id', $b->id)->count());
        $this->assertSame(12, H5pBranchingNode::count());
    }
}
