<?php

namespace Tests\Unit\H5P\Branching;

use App\Models\lms\h5p\H5pBranchingNode;
use App\Models\lms\h5p\H5pBranchingScenario;
use App\Services\lms\H5P\H5PBranchingScenarioBuilder;
use App\Services\lms\H5P\H5PBranchingScenarioPackageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Rows <-> H5P.BranchingScenario params, and the .h5p package around them.
 *
 * WHAT "COMPATIBLE" IS CHECKED AGAINST. The key sets below are transcribed from
 * the upstream semantics.json files (h5p/h5p-branching-scenario v1.11.1 and
 * h5p/h5p-branching-question v1.0.20, read 2026-10-10). A built params object
 * may contain only keys the official semantics define, plus keys this product
 * namespaces `eduerp*`. That is a structural check, not a run in a real H5P
 * host -- docs/h5p-branching-scenario.md says so -- but it is the check that
 * catches the realistic failure: a field renamed or invented here, which a
 * stock host would silently ignore.
 *
 * No database, no framework: see BootsMiniApp.
 */
class H5PBranchingScenarioBuilderTest extends TestCase
{
    use BootsMiniApp;

    private const SEMANTICS = [
        'branchingScenario' => ['title', 'startScreen', 'endScreens', 'content', 'scoringOptionGroup', 'behaviour', 'l10n'],
        'startScreen' => ['startScreenTitle', 'startScreenSubtitle', 'startScreenImage', 'startScreenAltText'],
        'endScreen' => ['endScreenTitle', 'endScreenSubtitle', 'endScreenImage', 'endScreenScore', 'contentId'],
        'content' => ['type', 'showContentTitle', 'proceedButtonText', 'forceContentFinished', 'nextContentId', 'feedback', 'contentBehaviour'],
        'feedback' => ['title', 'subtitle', 'image', 'endScreenScore'],
        'alternative' => ['text', 'nextContentId', 'feedback'],
        'scoringOptionGroup' => ['scoringOption', 'includeInteractionsScores'],
        'behaviour' => ['enableBackwardsNavigation', 'forceContentFinished', 'randomizeBranchingQuestions'],
        'l10n' => ['startScreenButtonText', 'endScreenButtonText', 'backButtonText', 'disableProceedButtonText', 'replayButtonText', 'scoreText', 'fullscreenAria'],
    ];

    protected function setUp(): void
    {
        $this->bootMiniApp();
    }

    protected function tearDown(): void
    {
        $this->shutDownMiniApp();
    }

    private function node(int $id, int $index, string $type, array $attributes): H5pBranchingNode
    {
        $node = new H5pBranchingNode($attributes + ['node_type' => $type, 'node_index' => $index, 'show_title' => true]);
        $node->id = $id;

        return $node;
    }

    private function scenario(): H5pBranchingScenario
    {
        $scenario = new H5pBranchingScenario([
            'title' => 'Is the lake water safe?',
            'start_title' => 'Is the lake water safe?',
            'start_subtitle' => '<p>You are testing water for your village.</p>',
            'learning_objectives' => ['Explain why a thermometer is used', 'Choose a fair test'],
            'scoring_option' => 'static-end-score',
            'pass_percentage' => 70,
            'enable_backwards_navigation' => true,
            'randomize_alternatives' => false,
        ]);
        $scenario->id = 7;

        $scenario->setRelation('nodes', new Collection([
            $this->node(1, 0, 'text', [
                'title' => 'Water in a lake', 'purpose' => 'situation', 'concept_id' => 11,
                'body' => '<p>You stand at the edge of the lake with a bucket and a thermometer.</p>',
                'media_path' => 'https://cdn.example.test/h5p_content/lake.png', 'media_alt' => 'A lake at dusk',
                'proceed_button_text' => 'Continue', 'next_node_id' => 2,
            ]),
            $this->node(2, 1, 'branching_question', [
                'title' => 'First check', 'purpose' => 'investigate', 'concept_id' => 11,
                'body' => '<p>What do you check first?</p>',
                'options' => [
                    ['text' => 'Measure the temperature', 'next_node_id' => 3, 'quality' => 'best', 'feedback_title' => 'Good thinking', 'feedback_text' => '<p>A thermometer gives a number you can compare.</p>', 'feedback_image' => '', 'feedback_image_alt' => ''],
                    ['text' => 'Taste the water', 'next_node_id' => 4, 'quality' => 'misstep', 'feedback_title' => 'Not safe', 'feedback_text' => '<p>Tasting can make you ill and tells you little.</p>', 'feedback_image' => '', 'feedback_image_alt' => ''],
                    ['text' => 'Guess from the colour', 'next_node_id' => 6, 'quality' => 'partial', 'feedback_title' => 'Only a start', 'feedback_text' => '<p>Colour is a clue, not a measurement.</p>', 'feedback_image' => '', 'feedback_image_alt' => ''],
                ],
            ]),
            $this->node(3, 2, 'text', ['title' => 'Reading the thermometer', 'purpose' => 'consequence', 'body' => '<p>The reading is steady.</p>', 'next_node_id' => 5, 'source_question_id' => 901]),
            $this->node(4, 3, 'text', ['title' => 'What went wrong', 'purpose' => 'remedial', 'body' => '<p>Taste is not evidence.</p>', 'next_node_id' => 3]),
            $this->node(5, 4, 'ending', ['title' => 'Sound reasoning', 'purpose' => 'summary', 'body' => '<p>You measured.</p><ul><li>Measure first</li></ul>', 'score' => 100]),
            $this->node(6, 5, 'ending', ['title' => 'Worth another look', 'purpose' => 'summary', 'body' => '<p>You guessed.</p>', 'score' => 55, 'media_path' => 'https://cdn.example.test/h5p_content/lake.png', 'media_alt' => 'A lake']),
        ]));

        return $scenario;
    }

    /** @return list<string> keys of $actual that neither the semantics nor the eduerp namespace allow */
    private function stray(array $actual, array $allowed): array
    {
        return array_values(array_filter(array_keys($actual), fn ($k) => ! in_array($k, $allowed, true) && ! str_starts_with((string) $k, 'eduerp')));
    }

    public function test_built_params_use_only_keys_the_official_semantics_define(): void
    {
        $params = (new H5PBranchingScenarioBuilder())->build($this->scenario());
        $bs = $params['branchingScenario'];
        $s = self::SEMANTICS;

        $this->assertSame([], $this->stray($bs, $s['branchingScenario']));
        $this->assertSame([], $this->stray($bs['startScreen'], $s['startScreen']));
        $this->assertSame([], $this->stray($bs['scoringOptionGroup'], $s['scoringOptionGroup']));
        $this->assertSame([], $this->stray($bs['behaviour'], $s['behaviour']));
        $this->assertSame([], $this->stray($bs['l10n'], $s['l10n']));
        $this->assertContains($bs['scoringOptionGroup']['scoringOption'], ['static-end-score', 'dynamic-score', 'no-score']);

        foreach ($bs['endScreens'] as $screen) {
            $this->assertSame([], $this->stray($screen, $s['endScreen']));
            $this->assertLessThan(0, $screen['contentId']);
        }

        foreach ($bs['content'] as $item) {
            $this->assertSame([], $this->stray($item, $s['content']));
            $this->assertSame([], $this->stray($item['feedback'], $s['feedback']));
            $this->assertContains($item['forceContentFinished'], ['useBehavioural', 'enabled', 'disabled']);
            $this->assertContains($item['type']['library'], ['H5P.AdvancedText 1.1', 'H5P.Image 1.1', 'H5P.BranchingQuestion 1.0']);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $item['type']['subContentId']);

            foreach ($item['type']['params']['branchingQuestion']['alternatives'] ?? [] as $alt) {
                $this->assertSame([], $this->stray($alt, $s['alternative']));
                $this->assertSame([], $this->stray($alt['feedback'], $s['feedback']));
                $this->assertIsInt($alt['nextContentId']);
            }
        }
    }

    public function test_endings_become_end_screens_reached_by_negative_ids(): void
    {
        $bs = (new H5PBranchingScenarioBuilder())->build($this->scenario())['branchingScenario'];

        $this->assertSame([-1, -2], array_column($bs['endScreens'], 'contentId'));
        $this->assertSame([100, 55], array_column($bs['endScreens'], 'endScreenScore'));
        $this->assertSame('<p>Sound reasoning</p>', $bs['endScreens'][0]['endScreenTitle']);
        $this->assertSame('https://cdn.example.test/h5p_content/lake.png', $bs['endScreens'][1]['endScreenImage']['path']);

        $alternatives = $bs['content'][2]['type']['params']['branchingQuestion']['alternatives'];
        $this->assertSame([3, 4, -2], array_column($alternatives, 'nextContentId'), 'a choice into an ending uses the ending\'s negative id');
        $this->assertSame(-1, $bs['content'][3]['nextContentId'], 'a screen leading to the first ending');
        $this->assertSame(3, $bs['content'][4]['nextContentId'], 'a remedial screen rejoining the main line');
    }

    public function test_a_screen_with_a_picture_and_words_is_two_entries_chained(): void
    {
        $content = (new H5PBranchingScenarioBuilder())->build($this->scenario())['branchingScenario']['content'];

        $this->assertCount(5, $content, 'four nodes, one of which is a picture plus words');
        $this->assertSame('H5P.Image 1.1', $content[0]['type']['library']);
        $this->assertSame('image/png', $content[0]['type']['params']['file']['mime']);
        $this->assertSame('A lake at dusk', $content[0]['type']['params']['alt']);
        $this->assertSame(1, $content[0]['nextContentId']);
        $this->assertTrue($content[0]['eduerp']['mergeWithNext']);
        $this->assertSame('H5P.AdvancedText 1.1', $content[1]['type']['library']);
        $this->assertSame(2, $content[1]['nextContentId'], 'the words lead on to the decision, which is the third entry');
    }

    public function test_objectives_are_added_to_the_start_screen_so_a_stock_host_shows_them(): void
    {
        $start = (new H5PBranchingScenarioBuilder())->build($this->scenario())['branchingScenario']['startScreen'];

        $this->assertStringContainsString('You are testing water for your village.', $start['startScreenSubtitle']);
        $this->assertStringContainsString('<li>Explain why a thermometer is used</li>', $start['startScreenSubtitle']);
    }

    public function test_a_round_trip_through_params_loses_nothing_this_product_keeps(): void
    {
        $builder = new H5PBranchingScenarioBuilder();
        $parsed = $builder->parse($builder->build($this->scenario()));

        $this->assertSame([], $parsed['warnings']);
        $this->assertSame('Is the lake water safe?', $parsed['scenario']['title']);
        $this->assertSame(['Explain why a thermometer is used', 'Choose a fair test'], $parsed['scenario']['learning_objectives']);
        $this->assertSame('<p>You are testing water for your village.</p>', $parsed['scenario']['start_subtitle'], 'the verbatim subtitle, without the appended list');
        $this->assertSame(70, $parsed['scenario']['pass_percentage']);
        $this->assertSame('static-end-score', $parsed['scenario']['scoring_option']);

        $byTitle = [];
        foreach ($parsed['nodes'] as $n) {
            $byTitle[$n['title']] = $n;
        }
        $this->assertCount(6, $parsed['nodes'], 'the picture entry folded back into its screen');

        $lake = $byTitle['Water in a lake'];
        $this->assertSame('text', $lake['node_type']);
        $this->assertSame('situation', $lake['purpose']);
        $this->assertSame(11, $lake['concept_id']);
        $this->assertSame('https://cdn.example.test/h5p_content/lake.png', $lake['media_path']);
        $this->assertSame('A lake at dusk', $lake['media_alt']);
        $this->assertStringContainsString('bucket', $lake['body']);

        $first = $byTitle['First check'];
        $this->assertSame('branching_question', $first['node_type']);
        $this->assertSame(['best', 'misstep', 'partial'], array_column($first['options'], 'quality'));
        $this->assertSame($byTitle['Reading the thermometer']['ref'], $first['options'][0]['next_ref']);
        $this->assertSame($byTitle['Worth another look']['ref'], $first['options'][2]['next_ref'], 'a choice into an ending resolves to the ending');
        $this->assertSame($lake['next_ref'], $first['ref']);

        $this->assertSame(901, $byTitle['Reading the thermometer']['source_question_id']);
        $this->assertSame($byTitle['Sound reasoning']['ref'], $byTitle['Reading the thermometer']['next_ref']);
        $this->assertSame(100, $byTitle['Sound reasoning']['score']);
        $this->assertSame('A lake', $byTitle['Worth another look']['media_alt']);
    }

    public function test_a_library_this_erp_cannot_play_is_kept_as_a_placeholder_and_reported(): void
    {
        $params = [
            'branchingScenario' => [
                'title' => 'Imported',
                'startScreen' => [],
                'endScreens' => [['endScreenTitle' => '<p>Done</p>', 'endScreenSubtitle' => '', 'endScreenScore' => 5, 'contentId' => -1]],
                'content' => [
                    ['type' => ['library' => 'H5P.Video 1.6', 'params' => [], 'metadata' => ['title' => 'Watch this']], 'nextContentId' => 1],
                    ['type' => ['library' => 'H5P.AdvancedText 1.1', 'params' => ['text' => '<p>Afterwards</p>'], 'metadata' => ['title' => 'After']], 'nextContentId' => -1],
                ],
                'scoringOptionGroup' => ['scoringOption' => 'dynamic-score'],
                'behaviour' => ['enableBackwardsNavigation' => true],
            ],
        ];

        $parsed = (new H5PBranchingScenarioBuilder())->parse($params);

        $this->assertCount(2, $parsed['warnings'], 'one for the video, one for the scoring mode');
        $this->assertStringContainsString('H5P.Video', implode(' ', $parsed['warnings']));
        $this->assertStringContainsString('points collected', implode(' ', $parsed['warnings']));
        $this->assertSame('static-end-score', $parsed['scenario']['scoring_option']);

        $placeholder = $parsed['nodes'][0];
        $this->assertSame('Watch this', $placeholder['title']);
        $this->assertSame('c1', $placeholder['next_ref'], 'the link out of it still connects');
        $this->assertSame('e0', $parsed['nodes'][1]['next_ref']);
    }

    public function test_content_that_is_not_a_branching_scenario_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);
        (new H5PBranchingScenarioBuilder())->parse(['presentation' => ['slides' => []]]);
    }

    public function test_a_package_exports_with_a_valid_manifest_and_imports_back_with_its_picture(): void
    {
        // The picture lives under the app's public directory, so export reads it from disk, not the network.
        mkdir($this->miniAppRoot . '/public/h5p_content', 0777, true);
        file_put_contents(
            $this->miniAppRoot . '/public/h5p_content/lake.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')
        );

        $service = new H5PBranchingScenarioPackageService(new H5PBranchingScenarioBuilder());
        $package = $service->export($this->scenario());

        $this->assertFileExists($package['path']);
        $this->assertStringEndsWith('.h5p', $package['filename']);
        $this->assertNotEmpty($package['warnings'], 'the picture-plus-words split is reported');
        $this->assertStringContainsString('picture step followed by the text step', implode(' ', $package['warnings']));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($package['path']));
        $manifest = json_decode($zip->getFromName('h5p.json'), true);
        $content = json_decode($zip->getFromName('content/content.json'), true);

        $this->assertSame('H5P.BranchingScenario', $manifest['mainLibrary']);
        $this->assertSame(['iframe'], $manifest['embedTypes']);
        $this->assertSame(
            ['machineName' => 'H5P.BranchingScenario', 'majorVersion' => 1, 'minorVersion' => 11],
            $manifest['preloadedDependencies'][0],
            'the main library comes first, as hosts install in order'
        );
        $names = array_column($manifest['preloadedDependencies'], 'machineName');
        foreach (['H5P.BranchingQuestion', 'H5P.AdvancedText', 'H5P.Image', 'FontAwesome'] as $needed) {
            $this->assertContains($needed, $names);
        }
        $this->assertMatchesRegularExpression('#^images/bs_[0-9a-f]{16}\.png$#', $content['branchingScenario']['content'][0]['type']['params']['file']['path'], 'media is packaged relative to content/');
        $this->assertNotFalse($zip->locateName('content/' . $content['branchingScenario']['content'][0]['type']['params']['file']['path']));
        $zip->close();

        // And back in.
        $upload = new UploadedFile($package['path'], $package['filename'], 'application/zip', null, true);
        $parsed = $service->import($upload, 1);

        $this->assertSame('Is the lake water safe?', $parsed['title']);
        $this->assertCount(6, $parsed['nodes']);
        $lake = array_values(array_filter($parsed['nodes'], fn ($n) => $n['title'] === 'Water in a lake'))[0];
        $this->assertStringStartsWith('https://app.example.test/h5p_content/branching_scenario/bs_', $lake['media_path'], 'imported media is served from this app, not the package-relative path');
        $this->assertFileExists($this->miniAppRoot . '/public/h5p_content/branching_scenario/' . basename($lake['media_path']));
    }

    public function test_a_package_of_another_library_is_refused(): void
    {
        $path = $this->miniAppRoot . '/other.h5p';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('h5p.json', json_encode(['title' => 'x', 'mainLibrary' => 'H5P.MemoryGame']));
        $zip->addFromString('content/content.json', '{}');
        $zip->close();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This importer accepts H5P.BranchingScenario');

        (new H5PBranchingScenarioPackageService(new H5PBranchingScenarioBuilder()))
            ->import(new UploadedFile($path, 'other.h5p', 'application/zip', null, true), 1);
    }
}
