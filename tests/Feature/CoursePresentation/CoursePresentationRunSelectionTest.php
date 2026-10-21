<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\CoursePresentation\GenerationRun;
use App\Services\CoursePresentation\ModelAvailability;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A run belongs to ONE teaching method and ONE content format. What the screen shows, what blocks a new generation and what
 * approve or discard may touch is decided by that choice, never by "the chapter's latest run".
 *
 * Runs are made as the real service makes them (status files in a scratch folder); the model and both artisan commands are
 * fakes, the database is in-memory sqlite. Nothing here can reach a stored deck or a real draft.
 */
class CoursePresentationRunSelectionTest extends CoursePresentationTestCase
{
    private const SLIDES = ['inquiry_based', 'interactive_slides'];
    private const STORY = ['experiential', 'branching_scenario'];
    private const BASE = '/api/lms/course-presentation/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedPilotChapter();
        // The chapter's own intelligence recommends the strategies these choices need, so both are allowed to continue.
        $entries = array_map(fn ($n) => ['concept' => ['concept_name' => $n], 'pedagogy_recommendations' => [['strategy' => 'Inquiry Based Teaching'], ['strategy' => 'Activity Based Teaching']]], ['Atom', 'Ion', 'Mole', 'Bond']);
        \Illuminate\Support\Facades\DB::table('semantic_intelligence')->where('id', 13)->update(['full_intelegance_json' => json_encode(['concepts' => $entries])]);
        $this->app->instance(ModelAvailability::class, new class extends ModelAvailability {
            public function __construct()
            {
            }

            public function available(): bool
            {
                return true;
            }

            public function geminiAvailable(): bool
            {
                return true;
            }
        });
        $this->app->instance(GenerationRun::class, $this->service());
    }

    /** @param (callable(int):?array)|null $source the chapter's own concepts; default: could not be read */
    private function service(?callable $source = null, ?callable $deck = null): GenerationRun
    {
        return new GenerationRun(function (string $command, array $args, BufferedOutput $out) use ($deck): int {
            if ($command === 'lms:generate-study-deck') {
                @mkdir($args['--out'], 0775, true);
                file_put_contents($args['--out'] . '/report.json', json_encode(['ok' => true, 'errors' => [], 'warnings' => [], 'stats' => ['slides' => 3, 'interactive_slides' => 0, 'concepts' => 3],
                    'teaching_method' => ['key' => $args['--teaching-method'], 'followed' => true, 'errors' => []]]));
                file_put_contents($args['--out'] . '/plan.json', json_encode(['slides' => [['n' => 1, 'title' => 'Atoms', 'slide_type' => 'concept_intro']]]));
                file_put_contents($args['--out'] . '/deck.json', json_encode($deck ? $deck() : ['version' => 3]));
            }

            return 0;
        }, null, $source ? \Closure::fromCallable($source) : fn (int $chapterId): ?array => null);
    }

    /** A run as the service writes it, then given a state and a creation time so the order is under the test's control. */
    private function makeRun(array $choice, string $state, string $createdAt, int $tenant = self::TENANT, int $chapter = self::CHAPTER): string
    {
        [$method, $format] = $choice;
        $runs = app(GenerationRun::class);
        $id = $runs->request($chapter, $tenant, 55, $method, $format)['run'];
        $path = $this->statusFile($id, $chapter);
        $status = json_decode((string) file_get_contents($path), true);
        $status['state'] = $state;
        $status['created_at'] = $createdAt;
        $status['tenant'] = $tenant;
        file_put_contents($path, json_encode($status));

        return $id;
    }

    private function statusFile(string $run, int $chapter = self::CHAPTER): string
    {
        return storage_path($this->runDir) . '/chapter-' . $chapter . '/' . $run . '/status.json';
    }

    private function state(string $run): string
    {
        return json_decode((string) file_get_contents($this->statusFile($run)), true)['state'];
    }

    private function query(array $choice, array $extra = []): string
    {
        return http_build_query($extra + ['chapter_id' => self::CHAPTER, 'pedagogy' => $choice[0], 'format' => $choice[1]]);
    }

    private function choiceBody(array $choice, array $extra = []): array
    {
        return $extra + ['chapter_id' => self::CHAPTER, 'pedagogy' => $choice[0], 'format' => $choice[1]];
    }

    // -------------------------------------------------------------------------------------------------------------
    // 1. Which run belongs to which choice

    public function test_a_run_is_found_only_for_its_own_teaching_method_and_format(): void
    {
        $slides = $this->makeRun(self::SLIDES, 'ready', '2026-10-20T10:00:00+05:30');
        $story = $this->makeRun(self::STORY, 'ready', '2026-10-21T11:17:50+05:30'); // the newer one
        $runs = app(GenerationRun::class);

        $this->assertSame($story, $runs->latest(self::CHAPTER, self::TENANT)['run'], 'unfiltered: the chapter\'s newest, as before');
        $this->assertSame($story, $runs->latest(self::CHAPTER, self::TENANT, ...self::STORY)['run']);
        $this->assertSame($slides, $runs->latest(self::CHAPTER, self::TENANT, ...self::SLIDES)['run'], 'the newer story draft is NOT offered for the presentation');
        // Same format, other method: a different choice.
        $this->assertNull($runs->latest(self::CHAPTER, self::TENANT, 'socratic', 'interactive_slides'));
        // Same method, other format: a different choice.
        $this->assertNull($runs->latest(self::CHAPTER, self::TENANT, 'inquiry_based', 'branching_scenario'));
        $this->assertSame($story, $runs->active(self::CHAPTER, self::TENANT, ...self::STORY)['run']);
        $this->assertSame($slides, $runs->active(self::CHAPTER, self::TENANT, ...self::SLIDES)['run']);
    }

    public function test_a_run_with_no_recorded_format_counts_as_interactive_slides(): void
    {
        $id = $this->makeRun(self::SLIDES, 'ready', '2026-10-20T10:00:00+05:30');
        $path = $this->statusFile($id);
        $status = json_decode((string) file_get_contents($path), true);
        unset($status['format']);
        file_put_contents($path, json_encode($status));

        $runs = app(GenerationRun::class);
        $this->assertSame($id, $runs->latest(self::CHAPTER, self::TENANT, ...self::SLIDES)['run']);
        $this->assertNull($runs->latest(self::CHAPTER, self::TENANT, 'inquiry_based', 'branching_scenario'));
    }

    public function test_the_preflight_and_the_status_answer_with_the_chosen_choices_run_and_never_another(): void
    {
        $slides = $this->makeRun(self::SLIDES, 'ready', '2026-10-20T10:00:00+05:30');
        $story = $this->makeRun(self::STORY, 'ready', '2026-10-21T11:17:50+05:30');
        $before = [file_get_contents($this->statusFile($slides)), file_get_contents($this->statusFile($story))];

        $this->asTeacher()->getJson(self::BASE . 'preflight?' . $this->query(self::SLIDES))->assertOk()->assertJsonPath('run.run', $slides)->assertJsonPath('run.format', 'interactive_slides');
        $this->asTeacher()->getJson(self::BASE . 'preflight?' . $this->query(self::STORY))->assertOk()->assertJsonPath('run.run', $story)->assertJsonPath('run.format', 'branching_scenario');
        $this->asTeacher()->getJson(self::BASE . 'status?' . $this->query(self::SLIDES))->assertOk()->assertJsonPath('run.run', $slides);
        $this->asTeacher()->getJson(self::BASE . 'status?' . $this->query(self::STORY))->assertOk()->assertJsonPath('run.run', $story);
        // A choice nothing was made for has no run: the screen offers to create it, it does not borrow another format's draft.
        $this->asTeacher()->getJson(self::BASE . 'preflight?' . $this->query(['experiential', 'interactive_slides']))->assertOk()->assertJsonPath('run', null);
        $this->asTeacher()->getJson(self::BASE . 'status?' . $this->query(['socratic', 'branching_scenario']))->assertOk()->assertJsonPath('run', null);

        // Naming a run together with a choice it does not belong to is refused.
        $this->asTeacher()->getJson(self::BASE . 'status?' . $this->query(self::SLIDES, ['run' => $story]))->assertStatus(422);
        // Without a run the status must say which choice it is asked about.
        $this->asTeacher()->getJson(self::BASE . 'status?chapter_id=' . self::CHAPTER)->assertStatus(422);

        $this->assertSame($before, [file_get_contents($this->statusFile($slides)), file_get_contents($this->statusFile($story))], 'looking never changes a run');
    }

    public function test_what_is_already_stored_is_listed_and_counted_only_under_its_own_format(): void
    {
        $row = ['standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT, 'show_hide' => 1];
        \Illuminate\Support\Facades\DB::table('content_master')->insert([
            $row + ['id' => 501, 'content_category' => 'Classroom Presentation', 'title' => 'Exploration Study Deck', 'filename' => 'study_deck_exploration_a1b2c3d4e5f6.pptx'],
            $row + ['id' => 502, 'content_category' => 'Interactive', 'title' => 'Exploration story', 'filename' => 'study_story_exploration_a1b2c3d4e5f6.pptx'],
        ]);
        $preflight = fn (array $choice) => $this->asTeacher()->getJson(self::BASE . 'preflight?' . $this->query($choice))->assertOk()->json();

        $slides = $preflight(self::SLIDES);
        $this->assertSame([501], array_column($slides['existing_resources'], 'ref'), 'a stored story lesson is not shown under the presentation');
        $this->assertTrue($slides['fit']['duplicate']);

        $story = $preflight(self::STORY);
        $this->assertSame([502], array_column($story['existing_resources'], 'ref'), 'a stored presentation is not shown under the story lesson');
        $this->assertTrue($story['fit']['duplicate']);
        $this->assertStringContainsString('story lesson already exists', implode(' ', $story['fit']['generation_blockers']));

        // Only one of the two stored: the other format is free to be created.
        \Illuminate\Support\Facades\DB::table('content_master')->where('id', 502)->delete();
        $this->assertFalse($preflight(self::STORY)['fit']['duplicate']);
        $this->assertSame([], $preflight(self::STORY)['existing_resources']);
        $this->assertTrue($preflight(self::SLIDES)['fit']['duplicate']);
    }

    // -------------------------------------------------------------------------------------------------------------
    // 2. What blocks a new generation

    public function test_a_draft_waiting_for_review_blocks_only_its_own_teaching_method_and_format(): void
    {
        $this->makeRun(self::STORY, 'ready', '2026-10-21T11:17:50+05:30');
        $blockers = fn (array $choice) => $this->asTeacher()->getJson(self::BASE . 'fit?' . $this->query($choice))->assertOk()->json('fit.generation_blockers');

        $own = implode(' ', $blockers(self::STORY));
        $this->assertStringContainsString('already waiting for review', $own);
        $this->assertStringNotContainsString('waiting for review', implode(' ', $blockers(self::SLIDES)), 'a story draft does not block the presentation');
        $this->assertStringNotContainsString('waiting for review', implode(' ', $blockers(['inquiry_based', 'branching_scenario'])), 'nor the same format with another method');

        // And the other way round.
        $this->makeRun(self::SLIDES, 'ready', '2026-10-21T12:00:00+05:30');
        $this->assertStringContainsString('already waiting for review', implode(' ', $blockers(self::SLIDES)));
    }

    public function test_a_draft_being_made_blocks_every_generation_of_the_chapter_because_the_job_is_one_per_chapter(): void
    {
        $this->makeRun(self::STORY, 'running', '2026-10-21T11:17:50+05:30');
        $blockers = fn (array $choice) => implode(' ', $this->asTeacher()->getJson(self::BASE . 'fit?' . $this->query($choice))->assertOk()->json('fit.generation_blockers'));

        $this->assertStringContainsString('already running', $blockers(self::STORY));
        $other = $blockers(self::SLIDES);
        $this->assertStringContainsString('still being made', $other);
        $this->assertStringContainsString('Interactive branching scenario', $other, 'it says which draft it is waiting for');

        Bus::fake();
        $this->asTeacher()->postJson(self::BASE . 'generate', $this->choiceBody(self::SLIDES))->assertStatus(422);
        Bus::assertNothingDispatched();
    }

    public function test_another_choice_may_be_generated_while_a_draft_waits_for_review(): void
    {
        $story = $this->makeRun(self::STORY, 'ready', '2026-10-21T11:17:50+05:30');
        Bus::fake();

        $new = $this->asTeacher()->postJson(self::BASE . 'generate', $this->choiceBody(self::SLIDES))->assertStatus(202)->json('run');
        $this->assertSame('interactive_slides', $new['format']);
        $this->assertNotSame($story, $new['run']);
        $this->assertSame('ready', $this->state($story), 'the waiting story draft is untouched');
    }

    // -------------------------------------------------------------------------------------------------------------
    // 3. Approve and discard act on the choice the teacher is looking at

    public function test_approve_and_discard_name_the_choice_and_refuse_a_run_made_for_another_one(): void
    {
        $slides = $this->makeRun(self::SLIDES, 'ready', '2026-10-20T10:00:00+05:30');
        $story = $this->makeRun(self::STORY, 'ready', '2026-10-21T11:17:50+05:30');

        // The choice is part of the request.
        $this->asTeacher()->postJson(self::BASE . 'approve', ['chapter_id' => self::CHAPTER, 'run' => $story, 'reviewed' => true])->assertStatus(422);
        $this->asTeacher()->postJson(self::BASE . 'discard', ['chapter_id' => self::CHAPTER, 'run' => $story])->assertStatus(422);
        $this->asTeacher()->postJson(self::BASE . 'approve', $this->choiceBody(self::SLIDES, ['run' => $story, 'reviewed' => true, 'format' => 'made_up']))->assertStatus(422);

        // A run made for another choice is refused and left exactly as it was.
        $refused = $this->asTeacher()->postJson(self::BASE . 'approve', $this->choiceBody(self::SLIDES, ['run' => $story, 'reviewed' => true]))->assertStatus(422);
        $this->assertStringContainsString('different teaching method or format', $refused->json('message'));
        $this->asTeacher()->postJson(self::BASE . 'discard', $this->choiceBody(self::SLIDES, ['run' => $story]))->assertStatus(422);
        $this->asTeacher()->postJson(self::BASE . 'approve', $this->choiceBody(['experiential', 'interactive_slides'], ['run' => $slides, 'reviewed' => true]))->assertStatus(422);
        $this->assertSame('ready', $this->state($story));
        $this->assertSame('ready', $this->state($slides));

        // Its own choice may discard it, and only it.
        $this->asTeacher()->postJson(self::BASE . 'discard', $this->choiceBody(self::STORY, ['run' => $story]))->assertOk()->assertJsonPath('run.state', 'discarded');
        $this->assertSame('discarded', $this->state($story));
        $this->assertSame('ready', $this->state($slides), 'the other draft is preserved');
    }

    public function test_a_stale_run_id_cannot_override_the_current_selection_at_approval(): void
    {
        // The screen still holds the older presentation draft's id, but the teacher now looks at the story.
        $old = $this->makeRun(self::SLIDES, 'ready', '2026-10-20T16:41:38+05:30');
        $this->makeRun(self::STORY, 'ready', '2026-10-21T11:17:50+05:30');

        $this->asTeacher()->postJson(self::BASE . 'approve', $this->choiceBody(self::STORY, ['run' => $old, 'reviewed' => true]))->assertStatus(422)
            ->assertJsonPath('run.run', $old);
        $this->assertSame('ready', $this->state($old), 'nothing was stored or changed');
    }

    // -------------------------------------------------------------------------------------------------------------
    // 4. Tenant and chapter isolation

    public function test_runs_of_another_school_or_chapter_are_never_found_or_changed(): void
    {
        $mine = $this->makeRun(self::SLIDES, 'ready', '2026-10-21T11:17:50+05:30');
        $foreign = $this->makeRun(self::SLIDES, 'ready', '2026-10-21T12:00:00+05:30', self::OTHER_TENANT);
        $runs = app(GenerationRun::class);

        $this->assertSame($mine, $runs->latest(self::CHAPTER, self::TENANT, ...self::SLIDES)['run'], 'the other school\'s newer run is not this school\'s');
        $this->assertSame($foreign, $runs->latest(self::CHAPTER, self::OTHER_TENANT, ...self::SLIDES)['run']);
        $this->assertNull($runs->read(self::CHAPTER, self::OTHER_TENANT, $mine));
        $this->assertNull($runs->inFlight(self::CHAPTER, self::TENANT));

        // Another school cannot reach the chapter at all; another chapter is outside the pilot.
        \Illuminate\Support\Facades\DB::table("chapter_master")->insert(["id" => 8615, "chapter_name" => "Other", "standard_id" => self::STANDARD, "subject_id" => self::SUBJECT, "sub_institute_id" => self::TENANT]);
        $this->asTeacher(self::OTHER_TENANT)->postJson(self::BASE . 'discard', $this->choiceBody(self::SLIDES, ['run' => $mine]))->assertStatus(404);
        $this->asTeacher()->postJson(self::BASE . 'approve', $this->choiceBody(self::SLIDES, ['run' => $mine, 'reviewed' => true, 'chapter_id' => 8615]))->assertStatus(403);
        $this->asTeacher()->postJson(self::BASE . 'discard', $this->choiceBody(self::SLIDES, ['run' => $mine, 'chapter_id' => 8615]))->assertStatus(403);
        $this->assertSame('ready', $this->state($mine));
    }

    // -------------------------------------------------------------------------------------------------------------
    // 5. A complete chapter: concepts are compared with the chapter's own list

    /** A deck whose slides teach exactly these concepts (id => name). */
    private function deckTeaching(array $concepts): callable
    {
        return function () use ($concepts): array {
            $slides = [];
            $n = 0;
            foreach ($concepts as $id => $name) {
                $n++;
                $slides[] = ['n' => $n, 'slide_type' => 'concept_intro', 'title' => "How $name works in a real example", 'taught_concept_ids' => [$id], 'image' => null,
                    'content' => ['body' => '', 'bullets' => [], 'explanations' => [['concept_id' => $id, 'text' => "$name is explained here in plain words, with one idea about $name for the class."]]]];
            }

            return ['version' => 3, 'concepts' => array_map(fn ($name) => ['name' => $name], $concepts), 'slides' => $slides];
        };
    }

    private function generate(GenerationRun $runs, array $choice = self::SLIDES): array
    {
        $id = $runs->request(self::CHAPTER, self::TENANT, 55, $choice[0], $choice[1])['run'];
        $runs->execute(self::CHAPTER, self::TENANT, $id);

        return $runs->read(self::CHAPTER, self::TENANT, $id);
    }

    public function test_a_draft_that_teaches_every_concept_of_the_chapter_is_ready_and_says_so(): void
    {
        $chapter = [1 => 'Atom', 2 => 'Ion', 3 => 'Mole'];
        $status = $this->generate($this->service(fn () => $chapter, $this->deckTeaching($chapter)));

        $this->assertSame('ready', $status['state'], (string) $status['error']);
        $this->assertSame(['checked' => true, 'source_concepts' => 3, 'taught' => 3, 'missing' => []], $status['coverage']);
    }

    public function test_a_draft_that_leaves_out_a_concept_of_the_chapter_never_reaches_review(): void
    {
        // The deck's own concept map knows two concepts and teaches both; the chapter itself holds a third.
        $status = $this->generate($this->service(fn () => [1 => 'Atom', 2 => 'Ion', 3 => 'Mole'], $this->deckTeaching([1 => 'Atom', 2 => 'Ion'])));

        $this->assertSame('failed', $status['state']);
        $this->assertStringContainsString('No slide teaches the concept "Mole"', $status['error']);
        $this->assertSame(['Mole'], $status['coverage']['missing']);
        $this->assertSame(2, $status['coverage']['taught']);
        $this->assertSame(3, $status['coverage']['source_concepts']);
    }

    public function test_approval_checks_the_chapters_concepts_again_so_a_concept_added_meanwhile_blocks_it(): void
    {
        $chapter = [1 => 'Atom', 2 => 'Ion'];
        $now = $chapter;
        $runs = $this->service(function () use (&$now) { return $now; }, $this->deckTeaching($chapter));
        $this->app->instance(GenerationRun::class, $runs);
        $status = $this->generate($runs);
        $this->assertSame('ready', $status['state']);

        $now = $chapter + [3 => 'Mole']; // the chapter gained a concept after the draft was made
        $result = $runs->approve(self::CHAPTER, self::TENANT, $status['run'], 55, 'Teacher');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('No slide teaches the concept "Mole"', $result['message']);
        $this->assertSame('ready', $this->state($status['run']), 'the draft is kept, not discarded');
    }

    public function test_coverage_is_reported_as_not_checked_when_the_chapters_concepts_cannot_be_read(): void
    {
        foreach ([fn () => null, fn () => [], function () { throw new \RuntimeException('the database refused'); }] as $unreadable) {
            $status = $this->generate($this->service($unreadable, $this->deckTeaching([1 => 'Atom', 2 => 'Ion'])));

            $this->assertSame('ready', $status['state'], 'the deck is still held to its own concept map');
            $this->assertFalse($status['coverage']['checked'], 'it is never claimed to be verified against the chapter');
            $this->assertNull($status['coverage']['source_concepts']);
        }
    }

    public function test_the_coverage_is_part_of_what_the_teacher_sees_of_a_run(): void
    {
        $chapter = [1 => 'Atom', 2 => 'Ion'];
        $runs = $this->service(fn () => $chapter, $this->deckTeaching($chapter));
        $this->app->instance(GenerationRun::class, $runs);
        $status = $this->generate($runs, self::STORY);

        $this->asTeacher()->getJson(self::BASE . 'status?' . $this->query(self::STORY, ['run' => $status['run']]))->assertOk()
            ->assertJsonPath('run.coverage.checked', true)->assertJsonPath('run.coverage.source_concepts', 2)->assertJsonPath('run.coverage.taught', 2);
    }
}
