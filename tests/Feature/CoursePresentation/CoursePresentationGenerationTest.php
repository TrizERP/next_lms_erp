<?php

namespace Tests\Feature\CoursePresentation;

use App\Jobs\GenerateCoursePresentationJob;
use App\Services\CoursePresentation\GenerationRun;
use App\Services\CoursePresentation\ModelAvailability;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Bus\UniqueLock;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Generate -> review -> approve for interactive slides, with the model and the two artisan commands replaced by
 * fakes: no model is called, no deck is stored in any real place. What is real is every rule of this feature: who
 * may start a run, what blocks it, where the draft lives, what the teacher sees, and what approval does.
 */
class CoursePresentationGenerationTest extends CoursePresentationTestCase
{
    /** @var array<int,array{0:string,1:array<string,mixed>}> every command the fake runner was asked to run */
    private array $commands = [];

    private bool $progressWasPersistedDuringGeneration = false;

    private ?string $stageDuringGeneration = null;

    protected function setUp(): void
    {
        parent::setUp();
        // The allowance on generate is asserted on the route below; these tests make many calls.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    /** @var array<string,mixed>|null the content_master columns the fake store inserts (default: a Classroom Presentation) */
    private ?array $storeRow = null;

    /** @var array<string,mixed>|null the deck.json the fake generator writes (default: an empty v3 deck) */
    private ?array $fakeDeck = null;

    private function deckWithCards(array $labels): array
    {
        $items = array_map(fn ($l, $i) => ['id' => 'k' . ($i + 1), 'label' => $l, 'text' => 'Some explanation of the idea number ' . ($i + 1) . '.'], $labels, array_keys($labels));

        return ['version' => 3, 'concepts' => ['1' => ['name' => 'Estimating']], 'slides' => [
            ['n' => 1, 'slide_type' => 'concept_intro', 'title' => 'Estimating', 'taught_concept_ids' => [1], 'content' => ['body' => '', 'explanations' => [['concept_id' => 1, 'text' => 'Estimating means finding a rough value.']], 'bullets' => []], 'image' => null,
                'interaction' => ['kind' => 'reveal', 'items' => $items]],
        ]];
    }

    /** @param int $generateCode exit code of lms:generate-study-deck; $reportOk whether the bundle it writes validates */
    private function fake(bool $model = true, int $generateCode = 0, bool $reportOk = true, bool $followed = true, int $storeCode = 0, string $message = ''): GenerationRun
    {
        $runs = new GenerationRun(function (string $command, array $args, BufferedOutput $out) use ($generateCode, $reportOk, $followed, $storeCode, $message): int {
            $this->commands[] = [$command, $args];
            if ($command === 'lms:generate-study-deck') {
                $out->writeln('  plan     35 slides planned');
                $bundle = $args['--out'];
                $liveStatus = json_decode((string) file_get_contents(dirname($bundle) . '/status.json'), true);
                $this->stageDuringGeneration = $liveStatus["stage"] ?? null;
                $this->progressWasPersistedDuringGeneration = in_array('plan     35 slides planned', (array) ($liveStatus['log'] ?? []), true);
                $out->writeln('Bundle: C:\\secret\\storage\\app\\somewhere');
                $out->writeln($message !== '' ? $message : '  validate Validation passed');
                @mkdir($bundle, 0775, true);
                file_put_contents($bundle . '/report.json', json_encode(['ok' => $reportOk, 'errors' => $reportOk ? [] : ['Numbers in slide text that are not in the chapter text: 124 (C:\\secret\\storage\\x)'], 'warnings' => ['w1'],
                    'stats' => ['slides' => 35, 'interactive_slides' => 33, 'concepts' => 42], 'teaching_method' => ['key' => $args['--teaching-method'], 'followed' => $followed, 'errors' => []]]));
                file_put_contents($bundle . '/plan.json', json_encode(['slides' => [['n' => 1, 'title' => 'Atoms in action', 'slide_type' => 'cover'], ['n' => 2, 'title' => 'Watch a reaction', 'slide_type' => 'scenario']]]));
                file_put_contents($bundle . '/deck.json', json_encode($this->fakeDeck ?? ['version' => 3]));

                return $generateCode;
            }
            if ($command === 'lms:store-study-deck' && $storeCode === 0) {
                $row = $this->storeRow ?? ['content_category' => 'Classroom Presentation', 'filename' => 'study_deck_8592_abc.pptx'];
                // Like the real command, a deck stored for a teaching method carries it in its file name.
                if (!empty($args['--teaching-method'])) {
                    $row['filename'] = preg_replace('/^(study_(?:deck|story)_)/', '${1}' . $args['--teaching-method'] . '__', $row['filename']);
                }
                DB::table('content_master')->insert($row + ['id' => 777, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
                    'title' => 'Course Presentation', 'show_hide' => 1]);
            }
            $out->writeln($storeCode === 0 ? 'stored' : 'refused');

            return $storeCode;
            // The chapter's own concept list is not what these tests are about (it is tested in CoursePresentationRunSelectionTest).
        }, null, fn (int $chapterId): ?array => null);
        $this->app->instance(GenerationRun::class, $runs);
        $this->app->instance(ModelAvailability::class, new class($model) extends ModelAvailability {
            public function __construct(private bool $yes)
            {
            }

            public function available(): bool
            {
                return $this->yes;
            }

            public function geminiAvailable(): bool
            {
                return $this->yes;
            }
        });

        return $runs;
    }

    private function ready(string $method = 'inquiry_based'): array
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();
        $run = $this->act('generate', $method)->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);

        return [$run, $runs];
    }

    public function test_a_draft_with_numbered_placeholder_cards_never_reaches_review(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();
        $this->fakeDeck = $this->deckWithCards(['Key idea 1', 'Key idea 2']);
        $run = $this->act('generate', 'inquiry_based')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);

        $res = $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=' . $run)->assertOk();
        $this->assertSame('failed', $res->json('run.state'));
        $this->assertStringContainsString('placeholder', $res->json('run.error'));
        $this->assertStringContainsString('Key idea 1', $res->json('run.error'));
    }

    public function test_a_draft_waiting_for_review_can_be_read_in_the_player_but_a_failed_or_foreign_one_cannot(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();

        // A failed draft is not offered for review.
        $this->fakeDeck = $this->deckWithCards(['Key idea 1', 'Key idea 2']);
        $failed = $this->act('generate', 'inquiry_based')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $failed);
        $this->asTeacher()->getJson('/api/lms/course-presentation/draft?chapter_id=' . self::CHAPTER . '&run=' . $failed)->assertStatus(404);
        $this->act('discard', 'inquiry_based', ['run' => $failed])->assertOk();

        // A validated draft is: its deck comes back, with a notice that nothing is stored, and the call writes nothing.
        $this->fakeDeck = $this->deckWithCards(['Reading a map', 'Using a scale']);
        $run = $this->act('generate', 'inquiry_based')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);
        $before = DB::table('content_master')->count();
        $res = $this->asTeacher()->getJson('/api/lms/course-presentation/draft?chapter_id=' . self::CHAPTER . '&run=' . $run)->assertOk();
        $this->assertSame(1, $res->json('status_code'));
        $this->assertStringContainsString('Nothing has been stored', $res->json('notice'));
        $this->assertSame(3, $res->json('deck.version'));
        $this->assertNotEmpty($res->json('deck.slides'));
        $this->assertSame($before, DB::table('content_master')->count(), 'reading a draft stores nothing');

        // A made-up or malformed run id is refused.
        $this->asTeacher()->getJson('/api/lms/course-presentation/draft?chapter_id=' . self::CHAPTER . '&run=r000000000000')->assertStatus(404);
        $this->asTeacher()->getJson('/api/lms/course-presentation/draft?chapter_id=' . self::CHAPTER . '&run=../../x')->assertStatus(422);
    }

    public function test_a_failed_run_can_be_reconciled_only_with_its_validated_stored_bundle(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();
        $this->fakeDeck = $this->deckWithCards(['Reading a map', 'Using a scale']) + ['chapter' => ['id' => self::CHAPTER]];

        $run = $this->act('generate', 'inquiry_based')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);
        $bundle = storage_path($this->runDir) . '/chapter-' . self::CHAPTER . '/' . $run . '/bundle';
        $statusPath = dirname($bundle) . '/status.json';
        $status = json_decode((string) file_get_contents($statusPath), true);
        $status['state'] = 'failed';
        file_put_contents($statusPath, json_encode($status));

        DB::table('content_master')->insert([
            'id' => 778, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER,
            'sub_institute_id' => self::TENANT, 'content_category' => 'Classroom Presentation', 'title' => 'Course Presentation',
            'filename' => 'study_deck_inquiry_based__reconciled.pptx', 'show_hide' => 1,
        ]);

        $wrong = $runs->reconcileStoredBundle(self::CHAPTER, self::TENANT, $run, 777, $bundle);
        $this->assertFalse($wrong['ok']);
        $this->assertSame('failed', $runs->read(self::CHAPTER, self::TENANT, $run)['state']);

        $result = $runs->reconcileStoredBundle(self::CHAPTER, self::TENANT, $run, 778, $bundle);
        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame('stored', $result['status']['state']);
        $this->assertSame(778, $result['status']['stored']['content_id']);
    }

    public function test_meaningful_headings_that_contain_a_number_are_accepted(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();
        $this->fakeDeck = $this->deckWithCards(["Newton's 2nd law", 'Step 2 of the water cycle']);
        $run = $this->act('generate', 'inquiry_based')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);

        $this->assertSame('ready', $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=' . $run)->json('run.state'));
    }

    public function test_a_ready_draft_changed_to_placeholder_cards_is_refused_at_approval(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();
        $this->fakeDeck = $this->deckWithCards(['Reading a map', 'Using a scale']);
        $run = $this->act('generate', 'inquiry_based')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);
        $bundle = storage_path((string) config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER) . '/' . $run . '/bundle';
        file_put_contents($bundle . '/deck.json', json_encode($this->deckWithCards(['Card 1', 'Card 2'])));

        $result = $runs->approve(self::CHAPTER, self::TENANT, $run, 55, 'Teacher');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('placeholder', $result['message']);
        $this->assertNotContains('lms:store-study-deck', array_column($this->commands, 0), 'nothing was stored');
    }

    private function storyDeck(bool $pictures = false): array
    {
        $f = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/story-act-chapter8592.json'), true);
        if (!$pictures) {
            foreach ($f['interaction']['nodes'] as $k => $n) {
                unset($f['interaction']['nodes'][$k]['image'], $f['interaction']['nodes'][$k]['extra_images']);
            }
        }

        return ['version' => 3, 'concepts' => ['7187' => ['name' => 'A'], '7188' => ['name' => 'B'], '7189' => ['name' => 'C'], '7190' => ['name' => 'D']], 'slides' => [
            ['n' => 1, 'slide_type' => 'cover', 'title' => 'Chapter', 'image' => null, 'interaction' => null, 'taught_concept_ids' => [], 'content' => ['body' => 'A story.', 'explanations' => [], 'bullets' => []]],
            ['n' => 2, 'slide_type' => 'scenario', 'title' => 'Predict the shot', 'image' => null, 'interaction' => $f['interaction'], 'taught_concept_ids' => $f['concept_ids'], 'content' => ['body' => 'A story.', 'explanations' => [], 'bullets' => []]],
        ]];
    }

    public function test_the_story_format_runs_the_same_pipeline_with_the_story_template_and_waits_for_review(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();
        $this->fakeDeck = $this->storyDeck();
        $run = $this->act('generate', 'experiential', [], 'branching_scenario')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);

        $gen = array_values(array_filter($this->commands, fn ($c) => $c[0] === 'lms:generate-study-deck'))[0][1];
        $this->assertSame('story_branching_scenario', $gen['--template']);
        $this->assertSame('experiential', $gen['--teaching-method']);
        $this->assertArrayNotHasKey('--ai-scene-art', $gen, 'story coverage uses the bounded photo/diagram ladder, not per-scene image-model calls');
        $res = $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=' . $run)->assertOk();
        $this->assertSame('ready', $res->json('run.state'), 'a story that passes the story rules is held for review, not stored');
        $this->assertSame('branching_scenario', $res->json('run.format'));
        $this->assertNotContains('lms:store-study-deck', array_column($this->commands, 0));
        $this->assertSame(0, DB::table('content_master')->count());

        // While a draft waits, another of the SAME teaching method and format cannot be started...
        $this->assertStringContainsString('already waiting for review', $this->act('generate', 'experiential', [], 'branching_scenario')->assertStatus(422)->json('message'));
        // ...but a draft of another format does not stand in its way (the story waits for review, nothing is being made).
        $this->act('generate', 'inquiry_based')->assertStatus(202);
    }

    public function test_a_story_draft_whose_scene_pictures_the_library_cannot_give_back_is_never_offered_for_review(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();
        $this->fakeDeck = $this->storyDeck(pictures: true);   // refers to library pictures 10, 9 and 5, which this school does not have here
        $run = $this->act('generate', 'experiential', [], 'branching_scenario')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);

        $res = $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=' . $run)->assertOk();
        $this->assertSame('failed', $res->json('run.state'));
        $this->assertStringContainsString('3 picture(s)', $res->json('run.error'));
    }

    public function test_approving_a_story_draft_stores_it_beside_the_presentation_and_never_over_it(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        // The chapter already has a Classroom Presentation, visible.
        DB::table('content_master')->insert(['id' => 500, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
            'content_category' => 'Classroom Presentation', 'title' => 'Exploration Study Deck', 'filename' => 'study_deck_8592_existing.pptx', 'show_hide' => 1]);
        $runs = $this->fake();
        Bus::fake();
        $this->fakeDeck = $this->storyDeck();
        $this->storeRow = ['content_category' => 'Interactive', 'filename' => 'study_story_8592_abc.pptx'];
        $run = $this->act('generate', 'experiential', [], 'branching_scenario')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);

        // A presentation being there is no reason to refuse the story (it is a different item), and storing it leaves the presentation visible.
        $res = $this->postRun('approve', $run, ['reviewed' => true])->assertOk();
        $this->assertContains('study_story_experiential__8592_abc.pptx', DB::table('content_master')->pluck('filename')->all());
        $this->assertSame(1, (int) DB::table('content_master')->where('id', 500)->value('show_hide'), 'the Classroom Presentation is untouched');
        $this->assertSame('/student/study-deck/8592?content=777', $res->json('run.stored.deep_link'));
        $this->assertContains('lms:store-study-deck', array_column($this->commands, 0));
    }

    public function test_a_second_story_lesson_is_a_duplicate_of_the_first_and_is_refused_at_approval(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();
        $this->fakeDeck = $this->storyDeck();
        $run = $this->act('generate', 'experiential', [], 'branching_scenario')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);
        DB::table('content_master')->insert(['id' => 600, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
            'content_category' => 'Interactive', 'title' => 'Exploration Story Lesson', 'filename' => 'study_story_experiential__8592_first.pptx', 'show_hide' => 1]);

        $this->postRun('approve', $run, ['reviewed' => true])->assertStatus(422);
        $this->assertNotContains('lms:store-study-deck', array_column($this->commands, 0), 'nothing was stored');
    }

    private function seedStrategiesForGeneration(): void
    {
        $rec = fn (string ...$names) => array_map(fn ($n) => ['strategy' => $n], $names);
        $entries = array_map(fn ($n) => ['concept' => ['concept_name' => $n], 'pedagogy_recommendations' => $rec('Inquiry Based Teaching', 'Activity Based Teaching')], ['Atom', 'Ion', 'Mole', 'Bond']);
        DB::table('semantic_intelligence')->where('id', 13)->update(['full_intelegance_json' => json_encode(['concepts' => $entries])]);
    }

    private function act(string $action, string $method = 'inquiry_based', array $extra = [], string $format = 'interactive_slides')
    {
        return $this->asTeacher()->postJson('/api/lms/course-presentation/' . $action, $extra + ['chapter_id' => self::CHAPTER, 'pedagogy' => $method, 'format' => $format]);
    }

    private function postRun(string $action, string $run, array $extra = [])
    {
        // Approve and discard name the teaching method and format the teacher is looking at; by default the run's own.
        $own = GenerationRun::validId($run) ? app(GenerationRun::class)->read(self::CHAPTER, self::TENANT, $run) : null;

        return $this->asTeacher()->postJson('/api/lms/course-presentation/' . $action, $extra + ['chapter_id' => self::CHAPTER, 'run' => $run,
            'pedagogy' => $own['method'] ?? 'inquiry_based', 'format' => $own['format'] ?? 'interactive_slides']);
    }

    public function test_generate_starts_one_background_draft_and_stores_nothing(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $this->fake();
        Bus::fake();

        $res = $this->act('generate')->assertStatus(202);

        $this->assertSame('queued', $res->json('run.state'));
        $this->assertSame('inquiry_based', $res->json('run.method'));
        Bus::assertDispatched(GenerateCoursePresentationJob::class, fn ($job) => $job->chapterId === self::CHAPTER && $job->tenant === self::TENANT && $job->run === $res->json('run.run'));
        $this->assertSame([], $this->commands, 'nothing runs inside the request');
        $this->assertSame(0, DB::table('content_master')->count());
        // What the teacher gets back carries no school, user, folder or command output.
        $this->assertSame([], array_intersect(['tenant', 'user_id', 'log', 'chapter_id', 'bundle'], array_keys($res->json('run'))));
    }

    public function test_a_draft_job_goes_to_its_own_queue_unless_the_driver_is_synchronous(): void
    {
        // `default` has no worker: a draft left there waited until it was marked "worker did not start".
        config(['queue.default' => 'database']);
        $job = new GenerateCoursePresentationJob(self::CHAPTER, self::TENANT, 'r0123456789ab');
        $this->assertSame('course_presentation', $job->connection);
        $this->assertSame('course-presentation', $job->queue);
        $this->assertGreaterThan($job->timeout, (int) config('queue.connections.course_presentation.retry_after'), 'a running draft must not be handed to a second worker');

        config(['queue.default' => 'sync']);
        $job = new GenerateCoursePresentationJob(self::CHAPTER, self::TENANT, 'r0123456789ab');
        $this->assertNull($job->connection, 'with the synchronous driver the controller still runs it after the response');
        $this->assertNull($job->queue);
    }

    public function test_progress_is_saved_while_the_generator_is_still_running(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();

        $run = $this->act('generate')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);

        $this->assertTrue($this->progressWasPersistedDuringGeneration);
    }

    public function test_an_abandoned_run_fails_and_no_longer_blocks_another_run(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();

        $run = $this->act('generate')->assertStatus(202)->json('run.run');
        $job = new GenerateCoursePresentationJob(self::CHAPTER, self::TENANT, $run);
        $uniqueLock = app(UniqueLock::class);
        $uniqueLock->release($job);
        $this->assertTrue($uniqueLock->acquire($job));
        $path = storage_path($this->runDir) . '/chapter-' . self::CHAPTER . '/' . $run . '/status.json';
        $status = json_decode((string) file_get_contents($path), true);
        $status['state'] = 'running';
        $status['updated_at'] = date('c', time() - 1860);
        file_put_contents($path, json_encode($status));

        $res = $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=' . $run)->assertOk();

        $this->assertSame('failed', $res->json('run.state'));
        $this->assertStringContainsString('stopped reporting progress', $res->json('run.error'));
        $this->assertNull($runs->inFlight(self::CHAPTER, self::TENANT));
        $this->assertTrue($uniqueLock->acquire($job), 'An abandoned run must release the job lock so a retry can be queued.');
        $uniqueLock->release($job);
    }

    public function test_generate_is_refused_when_no_model_is_reachable_and_says_so_without_naming_a_key(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $this->fake(model: false);
        Bus::fake();

        $res = $this->act('generate')->assertStatus(422);

        $this->assertStringContainsString('No model access is configured', $res->json('message'));
        $this->assertStringNotContainsString('ANTHROPIC', $res->getContent());
        Bus::assertNothingDispatched();
    }

    public function test_generate_is_refused_for_unsuitable_unsupported_missing_source_and_duplicate_cases(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $this->fake();
        Bus::fake();

        // Unsuitable for the chapter (and weak with slides).
        $this->act('generate', 'project_based')->assertStatus(422);
        // A format with no generator is not even an accepted value.
        $this->act('generate', 'inquiry_based', [], 'video_lesson')->assertStatus(422);
        // The story format needs a model like any other: with none configured it is refused, saying so.
        $this->fake(model: false);
        $this->assertStringContainsString('No model access', $this->act('generate', 'experiential', [], 'branching_scenario')->assertStatus(422)->json('message'));
        $this->fake(model: true);
        // A required source is missing.
        DB::table('document_extractions')->where('id', 81)->delete();
        $this->assertStringContainsString('Required source material is missing', $this->act('generate')->assertStatus(422)->json('message'));
        $this->seedPilotChapterTextbookAgain();
        // A presentation already stored for this teaching method is a duplicate.
        DB::table('content_master')->insert(['id' => 5, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT, 'content_category' => 'Classroom Presentation', 'title' => 'Existing', 'filename' => 'study_deck_inquiry_based__existing.pptx', 'show_hide' => 1]);
        $this->assertStringContainsString('would be a duplicate', $this->act('generate')->assertStatus(422)->json('message'));
        // A client cannot name sources.
        DB::table('content_master')->delete();
        $this->act('generate', 'inquiry_based', ['sources' => [1]])->assertStatus(422);
        Bus::assertNothingDispatched();
    }

    private function seedPilotChapterTextbookAgain(): void
    {
        DB::table('document_extractions')->insert(['id' => 81, 'document_type' => 'Chapter', 'chapter_id' => self::CHAPTER, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT, 'md_content' => str_repeat('Atoms combine. ', 400)]);
    }

    public function test_only_one_draft_at_a_time_until_it_is_discarded(): void
    {
        [$run, $runs] = $this->ready();
        $this->assertSame('ready', $runs->read(self::CHAPTER, self::TENANT, $run)['state']);

        $second = $this->act('generate')->assertStatus(422);
        $this->assertStringContainsString('already waiting for review', $second->json('message'));

        $this->postRun('discard', $run)->assertOk()->assertJsonPath('run.state', 'discarded');
        $this->act('generate')->assertStatus(202);
    }

    public function test_the_draft_is_built_by_the_existing_command_into_its_own_folder_with_the_chosen_method(): void
    {
        [$run] = $this->ready('experiential');

        [$command, $args] = $this->commands[0];
        $this->assertSame('lms:generate-study-deck', $command);
        $this->assertSame([self::CHAPTER, self::TENANT, 'experiential'], [$args['chapter'], $args['--tenant'], $args['--teaching-method']]);
        $out = str_replace('\\', '/', $args['--out']);
        $this->assertStringContainsString('/' . str_replace('\\', '/', $this->runDir) . '/chapter-' . self::CHAPTER . '/' . $run . '/bundle', $out);
        $this->assertStringNotContainsString('study-deck/chapter-', $out, 'never into the folder of the earlier, unstored deck');
        $this->assertCount(1, $this->commands, 'generating does not store');
        $this->assertSame(0, DB::table('content_master')->count());
    }

    public function test_plan_failure_exposes_the_actual_generation_reason_instead_of_a_generic_message(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake(generateCode: 1, reportOk: false, message: "Slide plan is invalid after 4 repair attempts:\\n- Slide 29 carries more than 3 concepts; split it.\\n- The plan asks for 26 visuals; ask for at most 24.");
        Bus::fake();
        $run = $this->act('generate')->assertStatus(202)->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);

        $error = $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=' . $run)->assertOk()->json('run.error');
        $this->assertStringContainsString('Slide 29 carries more than 3 concepts', $error);
        $this->assertStringContainsString('26 visuals', $error);
        $this->assertStringNotContainsString('Executor: gemini', $error);
        $this->assertStringNotContainsString('C:\\secret', $error);
    }

    public function test_interactive_slides_generation_requires_both_concept_and_every_slide_images(): void
    {
        [$run] = $this->ready('inquiry_based');
        [, $args] = $this->commands[array_key_last($this->commands)];
        $this->assertTrue($args['--require-concept-visuals']);
        $this->assertTrue($args['--require-every-slide-image']);
        $this->assertSame(35, $args['--max-visuals']);
    }

    public function test_a_validated_draft_is_ready_with_an_outline_and_nothing_internal(): void
    {
        [$run] = $this->ready();

        $res = $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=' . $run)->assertOk();
        $this->assertSame('ready', $res->json('run.state'));
        $this->assertSame(['slides' => 35, 'interactive_slides' => 33, 'concepts' => 42, 'warnings' => 1, 'method_followed' => true, 'quality_notes' => 0], $res->json('run.report'));
        $this->assertSame([['n' => 1, 'title' => 'Atoms in action', 'type' => 'cover'], ['n' => 2, 'title' => 'Watch a reaction', 'type' => 'scenario']], $res->json('run.outline'));
        // Only the pipeline's stage lines are shown as progress: the line that names a folder is not.
        $this->assertSame(['plan     35 slides planned', 'validate Validation passed'], $res->json('run.progress'));
        $body = $res->getContent();
        $this->assertStringNotContainsString('secret', $body);
        $this->assertStringNotContainsString('C:', $body);
        $this->assertStringNotContainsString('storage', $body);
        // Also the latest-run form.
        // Without a run id the status names the choice it is asked about, and answers with that choice's newest run only.
        $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER)->assertStatus(422);
        $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&pedagogy=inquiry_based&format=interactive_slides')->assertOk()->assertJsonPath('run.run', $run);
    }

    public function test_a_draft_that_fails_validation_or_ignores_the_method_is_failed_and_its_error_hides_server_paths(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake(reportOk: false, generateCode: 1);
        Bus::fake();
        $run = $this->act('generate')->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);
        $s = $runs->read(self::CHAPTER, self::TENANT, $run);
        $this->assertSame('failed', $s['state']);
        $this->assertStringContainsString('did not pass validation', $s['error']);
        $this->assertStringNotContainsString('secret', $s['error']);
        $this->assertStringContainsString('[folder]', $s['error']);
        $this->postRun('approve', $run, ['reviewed' => true])->assertStatus(422);

        // A deck that validates but does not follow the method is not ready either.
        $this->postRun('discard', $run)->assertOk();
        $runs2 = $this->fake(reportOk: true, followed: false);
        $run2 = $this->act('generate')->json('run.run');
        $runs2->execute(self::CHAPTER, self::TENANT, $run2);
        $this->assertSame('failed', $runs2->read(self::CHAPTER, self::TENANT, $run2)['state']);
    }

    public function test_approval_needs_the_teachers_review_and_then_stores_through_the_existing_command(): void
    {
        [$run] = $this->ready();

        $this->postRun('approve', $run)->assertStatus(422);                       // no confirmation
        $this->postRun('approve', $run, ['reviewed' => false])->assertStatus(422);
        $this->assertCount(1, $this->commands, 'nothing was stored');

        $res = $this->postRun('approve', $run, ['reviewed' => true])->assertOk();

        [$command, $args] = $this->commands[1];
        $this->assertSame('lms:store-study-deck', $command);
        $this->assertSame([self::CHAPTER, self::TENANT, 55], [$args['chapter'], $args['--tenant'], $args['--user']]);
        $this->assertStringEndsWith($run . '/bundle', str_replace('\\', '/', $args['--bundle']));
        $this->assertSame('stored', $res->json('run.state'));
        // The deep link is the study-deck viewer's, built from the stored row, never from the client.
        $this->assertSame('/student/study-deck/' . self::CHAPTER . '?content=777', $res->json('run.stored.deep_link'));
        // A second approval is refused: the draft is already stored.
        $this->postRun('approve', $run, ['reviewed' => true])->assertStatus(422);
        $this->assertCount(2, $this->commands);
    }

    public function test_approval_is_refused_if_a_presentation_was_stored_meanwhile_and_a_failed_store_keeps_the_draft(): void
    {
        [$run] = $this->ready();
        DB::table('content_master')->insert(['id' => 9, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
            'content_category' => 'Classroom Presentation', 'title' => 'Someone else', 'filename' => 'study_deck_inquiry_based__zzz.pptx', 'show_hide' => 1]);
        $this->assertStringContainsString('duplicate', $this->postRun('approve', $run, ['reviewed' => true])->assertStatus(422)->json('message'));
        $this->assertCount(1, $this->commands, 'the store command was not even called');

        DB::table('content_master')->delete();
        $this->fake(storeCode: 1);
        $this->postRun('approve', $run, ['reviewed' => true])->assertStatus(422);
        $this->assertSame('ready', $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=' . $run)->json('run.state'));
    }

    public function test_runs_are_found_only_under_their_own_chapter_and_school_and_ids_are_never_paths(): void
    {
        [$run] = $this->ready();
        $this->asTeacher(self::OTHER_TENANT)->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=' . $run)->assertStatus(404);
        $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=rffffffffffff')->assertStatus(404);
        foreach (['../../x', '..%2F..%2Fx', 'r123', 'R0123456789ab', 'r0123456789ab/../x'] as $bad) {
            $this->asTeacher()->getJson('/api/lms/course-presentation/status?chapter_id=' . self::CHAPTER . '&run=' . $bad)->assertStatus(422);
            $this->postRun('approve', $bad, ['reviewed' => true])->assertStatus(422);
            $this->postRun('discard', $bad)->assertStatus(422);
        }
        $this->assertFalse(GenerationRun::validId('r0123456789ab/../x'));
    }

    public function test_other_chapters_and_schools_cannot_generate_approve_or_discard(): void
    {
        [$run] = $this->ready();
        DB::table('chapter_master')->insert(['id' => 8615, 'chapter_name' => 'Other', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT]);

        $this->act('generate', 'inquiry_based', ['chapter_id' => 8615])->assertStatus(403);
        $this->postRun('approve', $run, ['chapter_id' => 8615, 'reviewed' => true])->assertStatus(403);
        $this->postRun('discard', $run, ['chapter_id' => 8615])->assertStatus(403);
        $this->asTeacher(self::OTHER_TENANT)->postJson('/api/lms/course-presentation/generate', ['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides'])->assertStatus(404);
    }

    public function test_the_routes_that_spend_or_store_carry_the_content_create_gate(): void
    {
        foreach (['generate', 'approve', 'discard'] as $action) {
            $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/lms/course-presentation/' . $action, 'POST'));
            $mw = $route->gatherMiddleware();
            $this->assertContains('perm:lms.content,create', $mw, $action);
            $this->assertContains('lms.auth', $mw, $action);
            $this->assertContains('api.session', $mw, $action);
        }
        $this->assertContains('throttle:10,1', Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/lms/course-presentation/generate', 'POST'))->gatherMiddleware());
    }

    public function test_the_fit_result_reports_generation_as_supported_only_when_everything_is_in_place(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $this->fake(model: false);
        $fit = fn () => $this->asTeacher()->getJson('/api/lms/course-presentation/fit?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides']))->json('fit');

        $f = $fit();
        $this->assertFalse($f['generation_supported']);
        $this->assertStringContainsString('No model access', implode(' ', $f['generation_blockers']));
        $this->assertStringNotContainsString('takes no teaching method', implode(' ', $f['generation_blockers']), 'slides do apply the method');

        $this->fake(model: true);
        $this->assertTrue($fit()['generation_supported']);
        $this->assertSame([], $fit()['generation_blockers']);

        // The story lesson is built by the same pipeline with the story template; like slides it needs a model, and says so when it has none.
        $story = fn () => $this->asTeacher()->getJson('/api/lms/course-presentation/fit?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => 'experiential', 'format' => 'branching_scenario']))->json('fit');
        $this->assertTrue($story()['generation_supported']);
        $this->assertTrue($story()['create']['available']);
        $this->assertSame('study_deck', $story()['viewer_type']);
        $this->fake(model: false);
        $this->assertFalse($story()['generation_supported']);
        $this->assertStringContainsString('No model access', implode(' ', $story()['generation_blockers']));
    }

    // ---- generation lifecycle, progress, estimate -------------------------------------------------------------------

    public function test_a_new_run_is_queued_with_no_percentage_and_says_whether_a_worker_is_there(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $this->fake();
        Bus::fake();

        config(['queue.default' => 'database']);
        $info = $this->act('generate')->assertStatus(202)->json('run.progress_info');

        $this->assertSame('queued', $info['lifecycle']);
        $this->assertSame(0, $info['percent']);
        $this->assertNull($info['started_at']);
        $this->assertFalse($info['estimate']['available'], 'no estimate before the run has started');
        $this->assertSame('not_detected', $info['queue']['worker'], 'no worker has reported in: the run says so instead of "waiting to start"');
        $this->assertSame('queued', $info['stage']);
        \App\Services\CoursePresentation\WorkerHeartbeat::beat(false);
        $this->assertSame('active', \App\Services\CoursePresentation\WorkerHeartbeat::state());
        \App\Services\CoursePresentation\WorkerHeartbeat::beat(true);
        $this->assertSame('busy', \App\Services\CoursePresentation\WorkerHeartbeat::state());
    }

    public function test_a_running_run_records_real_stages_and_times_and_finishes_with_its_duration(): void
    {
        [$run, $runs] = $this->ready();

        $this->assertSame('content', $this->stageDuringGeneration, 'the stage was saved while the pipeline was still running');
        $status = $runs->read(self::CHAPTER, self::TENANT, $run);
        $this->assertNotEmpty($status['started_at']);
        $this->assertNotEmpty($status['finished_at']);
        $this->assertGreaterThan(0, $status['duration_seconds']);
        $this->assertArrayHasKey('verify', $status['stage_times']);

        $info = $this->asTeacher()->getJson('/api/lms/course-presentation/status?' . http_build_query(['chapter_id' => self::CHAPTER, 'run' => $run, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides']))->json('run.progress_info');
        $this->assertSame('completed', $info['lifecycle']);
        $this->assertSame(100, $info['percent']);
        $this->assertSame($status['duration_seconds'], $info['duration_seconds']);
        $this->assertSame(['done'], array_values(array_unique(array_column($info['stages'], 'status'))));
    }

    public function test_stages_only_move_forward_and_a_stage_is_done_only_when_a_later_one_was_seen(): void
    {
        $s = ['format' => 'interactive_slides', 'stage' => 'prepare'];
        \App\Services\CoursePresentation\GenerationProgress::advance($s, ['load     Loading', 'plan     35 slides'], 1000);
        $this->assertSame('content', $s['stage']);
        \App\Services\CoursePresentation\GenerationProgress::advance($s, ['images   slide 1: drawn diagram', 'context  4 topics'], 2000);
        $this->assertSame('images', $s['stage'], 'a later line of an earlier stage never moves it back');
        $this->assertSame(date('c', 2000), $s['stage_times']['images']);
        \App\Services\CoursePresentation\GenerationProgress::advance($s, ['Executor: cli', 'Bundle: x'], 3000);
        $this->assertSame('images', $s['stage'], 'lines that are not stage lines change nothing');

        $info = \App\Services\CoursePresentation\GenerationProgress::describe($s + ['state' => 'running', 'created_at' => date('c', 900), 'started_at' => date('c', 1000), 'run' => 'r1'], null, 'busy', 2500);
        $by = array_column($info['stages'], 'status', 'key');
        $this->assertSame(['queued' => 'done', 'prepare' => 'done', 'content' => 'done', 'images' => 'current', 'interact' => 'pending', 'validate' => 'pending', 'verify' => 'pending', 'ready' => 'pending'], $by);
        $this->assertSame(37, $info['percent'], '3 of 8 stages done: stage-based, not a clock');
        $this->assertSame(1500, $info['elapsed_seconds']);
    }

    public function test_the_estimate_needs_enough_comparable_finished_runs_and_is_a_range(): void
    {
        $P = \App\Services\CoursePresentation\GenerationProgress::class;
        $run = fn (int $chapter, string $method, int $secs, string $format = 'interactive_slides') => ['chapter_id' => $chapter, 'method' => $method, 'format' => $format, 'state' => 'stored', 'duration_seconds' => $secs];

        $this->assertNull($P::comparable([$run(1, 'a', 600), $run(1, 'a', 700)], 1, 'a', 'interactive_slides'), 'two runs are not enough');
        $this->assertFalse($P::estimate(null, 100)['available'], 'no history: "Calculating estimated time…"');

        $h = $P::comparable([$run(1, 'a', 600), $run(1, 'a', 800), $run(1, 'a', 1000), $run(2, 'b', 90), $run(1, 'a', 500, 'branching_scenario')], 1, 'a', 'interactive_slides');
        $this->assertSame('this chapter, teaching method and format', $h['basis']);
        $this->assertCount(3, $h['durations'], 'another method, chapter or format is not evidence for this run');
        $e = $P::estimate($h, 300);
        $this->assertTrue($e['available']);
        $this->assertSame([400, 600], [$e['min_seconds'], $e['max_seconds']], 'a range from the middle half of earlier runs, minus the time already spent');
        $this->assertTrue($P::estimate($h, 5000)['overdue'], 'past the usual range it says so instead of counting down');

        $wider = $P::comparable([$run(2, 'a', 600), $run(3, 'a', 800), $run(4, 'a', 1000)], 1, 'a', 'interactive_slides');
        $this->assertSame('this teaching method and format', $wider['basis']);
    }

    public function test_a_failed_run_says_what_kind_of_failure_it_was_and_whether_trying_again_may_help(): void
    {
        $P = \App\Services\CoursePresentation\GenerationProgress::class;
        $this->assertSame(['quota', true], [$P::classifyError('Gemini daily quota exhausted')['kind'], $P::classifyError('429 Too Many Requests')['retryable']]);
        $this->assertSame('queue', $P::classifyError('The generation worker did not start within 30 minutes. Start a new draft.')['kind']);
        $this->assertSame('images', $P::classifyError('Every slide must have a readable image.')['kind']);
        $this->assertFalse($P::classifyError('The generated deck did not pass validation: x')['retryable']);
        $this->assertSame('unknown', $P::classifyError('Something odd')['kind']);
        $this->assertNull($P::classifyError(null));

        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake(generateCode: 1, reportOk: false);
        Bus::fake();
        $run = $this->act('generate')->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);
        $info = $runs->read(self::CHAPTER, self::TENANT, $run);
        $this->assertSame('failed', $info['state']);
        $summary = $this->asTeacher()->getJson('/api/lms/course-presentation/status?' . http_build_query(['chapter_id' => self::CHAPTER, 'run' => $run]))->json('run.progress_info');
        $this->assertSame('failed', $summary['lifecycle']);
        $this->assertNull($summary['percent']);
        $this->assertNotNull($summary['failure']);
        $this->assertNotEmpty($info['duration_seconds'], 'even a failed run records how long it ran');
    }

    public function test_a_finished_run_keeps_where_its_time_went_and_the_slides_command_is_time_boxed(): void
    {
        config(['course_presentation_pilot.generation.target_seconds' => 60, 'course_presentation_pilot.generation.hard_limit_seconds' => 240]);
        [$run, $runs] = $this->ready();

        $args = $this->commands[0][1];
        $this->assertSame([60, 240], [$args['--target-seconds'], $args['--hard-limit-seconds']]);
        $timings = $runs->read(self::CHAPTER, self::TENANT, $run)['timings'];
        $this->assertSame(60, $timings['target_seconds']);
        $this->assertIsInt($timings['queue_wait_seconds']);
        $this->assertArrayHasKey('content', $timings['stages']);
        $this->assertTrue($timings['within_target'], 'a fake run takes no time');
        $this->assertNull($timings['failure']);
    }

    public function test_a_draft_that_cannot_be_read_back_is_never_reported_ready(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $this->fakeDeck = [];
        $runs = $this->fake();
        Bus::fake();
        $run = $this->act('generate')->json('run.run');
        $runs->execute(self::CHAPTER, self::TENANT, $run);

        $status = $runs->read(self::CHAPTER, self::TENANT, $run);
        $this->assertSame('failed', $status['state']);
        $this->assertStringContainsString('could not be read back', $status['error']);
        $this->assertSame('failed', $status['state']);
        $this->assertNotNull($status['timings']['failure'], 'the failure is in the timings and the log');
    }

    public function test_a_second_approval_while_the_first_is_saving_is_refused_and_a_saving_run_reports_saving(): void
    {
        [$run, $runs] = $this->ready();
        $status = $runs->read(self::CHAPTER, self::TENANT, $run);
        $status['saving'] = date('c');
        $dir = storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $run . '/status.json');
        file_put_contents($dir, json_encode($status));

        $res = $this->postRun('approve', $run, ['reviewed' => true])->assertStatus(422);
        $this->assertStringContainsString('already being saved', $res->json('message'));
        $this->assertSame('saving', $res->json('run.progress_info.lifecycle'));
        $this->assertCount(1, $this->commands, 'the store command did not run a second time');
    }

    public function test_the_run_of_one_choice_is_never_reported_for_another(): void
    {
        [$run] = $this->ready('inquiry_based');

        $this->asTeacher()->getJson('/api/lms/course-presentation/status?' . http_build_query(['chapter_id' => self::CHAPTER, 'run' => $run, 'pedagogy' => 'experiential', 'format' => 'interactive_slides']))
            ->assertStatus(422);
        $this->asTeacher()->getJson('/api/lms/course-presentation/status?' . http_build_query(['chapter_id' => self::CHAPTER, 'run' => $run, 'pedagogy' => 'inquiry_based', 'format' => 'branching_scenario']))
            ->assertStatus(422);
    }

    // ---- AI Stack (Teach/Learn automations) starting the same branching-scenario draft -------------------------------

    public function test_ai_stack_starts_the_same_draft_as_create_and_stores_nothing(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $this->fake();
        Bus::fake();
        $starter = app(\App\Services\CoursePresentation\AutomationDraftStarter::class);

        $first = $starter->start(self::CHAPTER, self::TENANT, 55, 'Inquiry-based learning');

        $this->assertTrue($first['success'], (string) ($first['error'] ?? ''));
        $this->assertSame('draft_started', $first['status']);
        $run = app(GenerationRun::class)->read(self::CHAPTER, self::TENANT, $first['run']);
        $this->assertSame(['queued', 'inquiry_based', 'branching_scenario'], [$run['state'], $run['method'], $run['format']]);
        Bus::assertDispatched(GenerateCoursePresentationJob::class, fn ($job) => $job->chapterId === self::CHAPTER && $job->run === $first['run']);
        $this->assertSame([], $this->commands, 'nothing runs inside the request');
        $this->assertSame(0, DB::table('content_master')->count(), 'a draft stores nothing, replaces nothing and hides nothing');

        // Pressing it again points at the draft that is already there instead of starting a second.
        $again = $starter->start(self::CHAPTER, self::TENANT, 55, 'Inquiry-based learning');
        $this->assertSame(['draft_pending', $first['run']], [$again['status'], $again['run']]);
        Bus::assertDispatchedTimes(GenerateCoursePresentationJob::class, 1);
    }

    public function test_an_ai_stack_run_stores_itself_when_it_passes_and_a_create_button_run_still_waits_for_approval(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();
        $starter = app(\App\Services\CoursePresentation\AutomationDraftStarter::class);

        $started = $starter->start(self::CHAPTER, self::TENANT, 55, 'Inquiry-based learning', 'interactive_slides', 'Teacher');
        $runs->execute(self::CHAPTER, self::TENANT, $started['run']);
        $this->assertSame('ready', $runs->read(self::CHAPTER, self::TENANT, $started['run'])['state'], 'generated and validated');

        $stored = $runs->autoStore(self::CHAPTER, self::TENANT, $started['run']);
        $this->assertTrue($stored['ok'], (string) ($stored['message'] ?? ''));
        $status = $runs->read(self::CHAPTER, self::TENANT, $started['run']);
        $this->assertSame(['stored', 777], [$status['state'], $status['stored']['content_id']]);
        $this->assertSame(1, DB::table('content_master')->count());
        $this->assertSame('verified_existing', $starter->start(self::CHAPTER, self::TENANT, 55, 'Inquiry-based learning', 'interactive_slides')['status'], 'a second press reuses it');

        // A run started with the Create button carries no auto_store: autoStore leaves it for a teacher.
        $manual = $runs->request(self::CHAPTER, self::TENANT, 55, 'experiential', 'interactive_slides');
        $runs->execute(self::CHAPTER, self::TENANT, $manual['run']);
        $this->assertNull($runs->autoStore(self::CHAPTER, self::TENANT, $manual['run']));
        $this->assertSame('ready', $runs->read(self::CHAPTER, self::TENANT, $manual['run'])['state']);
        $this->assertSame(1, DB::table('content_master')->count());
    }

    public function test_when_the_store_step_refuses_an_ai_stack_run_the_reason_is_kept_and_nothing_is_stored(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake(storeCode: 1);
        Bus::fake();
        $started = app(\App\Services\CoursePresentation\AutomationDraftStarter::class)->start(self::CHAPTER, self::TENANT, 55, 'Inquiry-based learning', 'interactive_slides');
        $runs->execute(self::CHAPTER, self::TENANT, $started['run']);

        $this->assertFalse($runs->autoStore(self::CHAPTER, self::TENANT, $started['run'])['ok']);
        $status = $runs->read(self::CHAPTER, self::TENANT, $started['run']);
        $this->assertNotEmpty($status['auto_store_error']);
        $this->assertSame(0, DB::table('content_master')->count());
    }

    private function resourceStatus(array $items): array
    {
        $controller = app(\App\Http\Controllers\AI\TeachLearnCatalogueAgentController::class);
        $request = \Illuminate\Http\Request::create('/x', 'POST', ['items' => $items]);
        $request->attributes->set('mcp_auth', ['sub_institute_id' => (string) self::TENANT]);

        return $controller->resourceStatus($request)->getData(true);
    }

    public function test_the_resource_status_endpoint_reports_stored_running_and_failed_for_presentations_and_documents(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $runs = $this->fake();
        Bus::fake();
        $starter = app(\App\Services\CoursePresentation\AutomationDraftStarter::class);
        $run = $starter->start(self::CHAPTER, self::TENANT, 55, 'Inquiry-based learning', 'interactive_slides')['run'];
        $deck = ['kind' => 'presentation', 'chapter_id' => self::CHAPTER, 'run' => $run];
        $doc = ['kind' => 'document', 'chapter_id' => self::CHAPTER, 'document_kind' => 'revision_notes'];

        $json = $this->resourceStatus([$deck, $doc]);
        $items = $json['data']['items'] ?? $json['items'];
        $this->assertSame(['running', 'running'], [$items[0]['state'], $items[1]['state']]);

        // The deck is generated and stored by the job; the document's row appears when it is stored.
        $runs->execute(self::CHAPTER, self::TENANT, $run);
        $runs->autoStore(self::CHAPTER, self::TENANT, $run);
        DB::table('content_master')->insert(['id' => 950, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
            'content_category' => 'Revision Notes', 'filename' => 'study_revision_notes_8592_all_0123456789ab.pdf', 'title' => 'Revision Notes', 'show_hide' => 1]);
        $items = ($this->resourceStatus([$deck, $doc])['data'] ?? $this->resourceStatus([$deck, $doc]))['items'];
        $this->assertSame(['stored', 777], [$items[0]['state'], $items[0]['content_id']]);
        $this->assertSame(['stored', 950], [$items[1]['state'], $items[1]['content_id']]);

        // A failed document reports why; another school's chapter and an unknown kind are refused.
        DB::table('content_master')->where('id', 950)->delete();
        \Illuminate\Support\Facades\Cache::put(\App\Jobs\GenerateStudyDocumentJob::failureKey(self::TENANT, self::CHAPTER, 'revision_notes'), 'The revision notes failed validation.', 60);
        $payload = $this->resourceStatus([$doc, ['kind' => 'document', 'chapter_id' => self::CHAPTER, 'document_kind' => 'nonsense']]);
        $items = ($payload['data'] ?? $payload)['items'];
        $this->assertSame(['failed', 'The revision notes failed validation.'], [$items[0]['state'], $items[0]['error']]);
        $this->assertSame('failed', $items[1]['state']);
    }

    public function test_ai_stack_refuses_an_unknown_approach_and_reuses_a_stored_story_deck_of_the_same_approach(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $this->fake();
        Bus::fake();
        $starter = app(\App\Services\CoursePresentation\AutomationDraftStarter::class);

        $this->assertFalse($starter->start(self::CHAPTER, self::TENANT, 55, 'Made-up approach')['success']);
        $this->assertSame('inquiry_based', $starter->methodKey('Inquiry-based learning'));
        $this->assertNull($starter->methodKey('Inquiry-based'));

        DB::table('content_master')->insert(['id' => 900, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
            'content_category' => \App\Services\StudyDeck\Story\StoryTemplate::CATEGORY, 'filename' => 'study_story_inquiry_based__atoms_abc123.pptx', 'title' => 'Atoms Story Lesson', 'show_hide' => 1]);
        $reused = $starter->start(self::CHAPTER, self::TENANT, 55, 'Inquiry-based learning');
        $this->assertSame(['verified_existing', 900], [$reused['status'], $reused['content_id']]);
        // Another approach is a different deck: it is not "already there".
        $this->assertNull($starter->existing(self::CHAPTER, self::TENANT, 'Socratic questioning / guided discovery'));
        Bus::assertNotDispatched(GenerateCoursePresentationJob::class);
    }

    public function test_ai_stack_presentation_is_the_classroom_resource_slides_deck_and_duplicates_are_per_method_and_format(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategiesForGeneration();
        $this->fake();
        Bus::fake();
        $starter = app(\App\Services\CoursePresentation\AutomationDraftStarter::class);

        // An older deck stored WITHOUT a method (the kind that showed "Method/Format not configured") is not this choice.
        DB::table('content_master')->insert(['id' => 901, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
            'content_category' => 'Classroom Presentation', 'filename' => 'study_deck_atoms_abc123.pptx', 'title' => 'Atoms Study Deck', 'show_hide' => 1]);
        $this->assertNull($starter->existing(self::CHAPTER, self::TENANT, 'Inquiry-based learning', 'interactive_slides'));

        $started = $starter->start(self::CHAPTER, self::TENANT, 55, 'Inquiry-based learning', 'interactive_slides');
        $this->assertSame(['draft_started', 'interactive_slides'], [$started['status'], $started['format']]);
        $run = app(GenerationRun::class)->read(self::CHAPTER, self::TENANT, $started['run']);
        $this->assertSame(['inquiry_based', 'interactive_slides'], [$run['method'], $run['format']]);
        $this->assertSame(1, DB::table('content_master')->count(), 'the old deck is untouched and nothing new is stored');

        // A slides deck stored for this method is "already there" for slides, not for a story lesson.
        DB::table('content_master')->insert(['id' => 902, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
            'content_category' => 'Classroom Presentation', 'filename' => 'study_deck_inquiry_based__atoms_def456.pptx', 'title' => 'Atoms Study Deck - Inquiry-based learning', 'show_hide' => 1]);
        $this->assertSame(902, $starter->existing(self::CHAPTER, self::TENANT, 'Inquiry-based learning', 'interactive_slides')['id']);
        $this->assertNull($starter->existing(self::CHAPTER, self::TENANT, 'Inquiry-based learning', 'branching_scenario'));
    }

    public function test_the_ai_stack_study_document_job_is_unique_uses_the_worker_queue_and_refuses_what_is_not_its_own(): void
    {
        config(['queue.default' => 'database']);
        $job = new \App\Jobs\GenerateStudyDocumentJob(self::CHAPTER, self::TENANT, 'revision_notes', 55);
        $this->assertSame('course_presentation', $job->connection, 'the queue a worker already runs');
        $this->assertSame('study-document:1:8592:revision_notes', $job->uniqueId());
        $this->assertNotSame($job->uniqueId(), (new \App\Jobs\GenerateStudyDocumentJob(self::CHAPTER, self::TENANT, 'remedial', 55))->uniqueId(), 'one per kind');

        $this->seedPilotChapter();
        $generator = \Mockery::mock(\App\Services\ContentGenerationService::class);
        $generator->shouldNotReceive('generateStudyDocument');
        // Another school's chapter and a made-up kind are refused before anything is written or any model is called.
        (new \App\Jobs\GenerateStudyDocumentJob(self::CHAPTER, self::OTHER_TENANT, 'revision_notes'))->handle($generator);
        (new \App\Jobs\GenerateStudyDocumentJob(self::CHAPTER, self::TENANT, 'nonsense'))->handle($generator);
        $this->assertSame(0, DB::table('content_master')->count());
    }

    public function test_the_switch_opens_the_course_presentation_endpoints_to_other_chapters(): void
    {
        $this->seedPilotChapter();
        DB::table('chapter_master')->insert(['id' => 8615, 'chapter_name' => 'Other', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT]);
        $options = '/api/lms/course-presentation/options?chapter_id=8615';

        $this->asTeacher()->getJson($options)->assertStatus(403);
        config(['course_presentation_pilot.scope.all_chapters' => true]);
        $this->asTeacher()->getJson($options)->assertOk();
        // Opening the scope never opens another school's chapter.
        $this->asTeacher(self::OTHER_TENANT)->getJson($options)->assertStatus(404);
    }
}
