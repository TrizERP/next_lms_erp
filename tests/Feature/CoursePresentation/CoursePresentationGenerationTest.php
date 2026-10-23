<?php

namespace Tests\Feature\CoursePresentation;

use App\Jobs\GenerateCoursePresentationJob;
use App\Services\CoursePresentation\GenerationRun;
use App\Services\CoursePresentation\ModelAvailability;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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
                $out->writeln('Bundle: C:\\secret\\storage\\app\\somewhere');
                $out->writeln($message !== '' ? $message : '  validate Validation passed');
                $bundle = $args['--out'];
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
}
