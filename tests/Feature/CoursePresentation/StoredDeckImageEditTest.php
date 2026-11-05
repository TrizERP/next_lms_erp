<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\ContentGenerationService;
use App\Services\CoursePresentation\DraftImageEditor;
use App\Services\CoursePresentation\GenerationRun;
use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyImageStores;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Changing ONE slide's picture in a presentation that is already approved and stored. A hand-made sidecar in the format the player reads, in a fake
 * object store; the picture search and the download are fakes, so nothing leaves the machine. What is real: the picture store (in-memory sqlite),
 * the sidecar write, the validation, the routes and their gates, and the player's own read endpoint (to prove a change survives a reload).
 */
class StoredDeckImageEditTest extends CoursePresentationTestCase
{
    protected bool $workflowPilot = true;

    private const CONTENT = 900;
    private const FILE = 'study_deck_inquiry_based__exploration_abc123.pptx';

    private StudyDeckImages $store;
    /** @var array<int,int> picture id by slide at the start */
    private array $ids = [];
    private bool $downloadFails = false;
    /** @var array<int,array<string,mixed>> */
    private array $found = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        // The live content_master has a file_type column; the shared test schema leaves it to the suites that need it.
        \Illuminate\Support\Facades\Schema::table('content_master', fn ($t) => $t->string('file_type')->nullable());
        $this->seedPilotChapter();
        Storage::fake('digitalocean');
        $this->store = new StudyDeckImages();
        $this->found = [
            ['url' => 'https://upload.test/wikipedia/commons/a/ab/Rain_over_a_lake.jpg', 'thumbnail_url' => 'https://upload.test/thumb/Rain.jpg', 'title' => 'Rain over a lake', 'creator' => 'Bo Photo', 'license' => 'BY-SA 4.0',
                'source_url' => 'https://commons.test/wiki/File:Rain_over_a_lake.jpg', 'provider' => 'wikimedia', 'width' => 4000, 'height' => 3000, 'tags' => ['rain', 'lake'], 'attribution' => '"Rain over a lake" by Bo Photo, CC BY-SA 4.0, via Wikimedia Commons.'],
            ['url' => 'https://upload.test/closed.jpg', 'title' => 'Rain closed', 'creator' => 'X', 'license' => 'ARR', 'source_url' => 'https://commons.test/closed', 'provider' => 'flickr', 'tags' => ['rain']],
        ];
    }

    private function png(int $w, int $h, int $seed): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, $seed % 255, ($seed * 7) % 255, ($seed * 13) % 255));
        imagestring($im, 5, 5, 5, 'pic' . $seed, imagecolorallocate($im, 255, 255, 255));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function photo(int $id, string $title): array
    {
        return ['type' => 'photo', 'alt' => 'Photograph: ' . $title, 'caption' => $title, 'url' => StudyDeckImages::ref($id), 'width' => 400, 'height' => 300, 'query' => 'water cycle',
            'source_image_url' => 'https://img.test/' . $id . '.jpg', 'source_url' => 'https://img.test/page/' . $id, 'title' => $title, 'creator' => 'Ann Author', 'provider' => 'openverse',
            'licence' => 'BY 2.0', 'attribution_required' => true, 'attribution' => '"' . $title . '" by Ann Author is licensed under CC BY 2.0.', 'purpose' => 'Shows ' . $title,
            'source_kind' => 'web', 'asset_id' => 'orig' . $id];
    }

    /**
     * Slide 1 cover, 2 a photograph, 3 a drawn fallback diagram, 4 a diagram with hotspots, 5 no picture, 6 a drawn diagram with three items opened
     * one at a time beside it: the magnifying glass and the compass have no picture of their own, the map has one.
     */
    private function storeDeck(): array
    {
        foreach ([2, 3, 4, 6, 7] as $n) {
            $this->ids[$n] = $this->store->put($this->png(300 + $n, 220, $n), self::TENANT, self::CHAPTER)['id'];
        }
        $diagram = ['type' => 'diagram', 'alt' => 'Diagram: Goals', 'caption' => 'Goals', 'url' => StudyDeckImages::ref($this->ids[3]), 'texts' => ['Goals', 'Goals'], 'sha1' => 'x', 'licence' => null, 'source_url' => null,
            'creator' => null, 'attribution' => '', 'attribution_required' => false, 'purpose' => 'Generated educational diagram for Goals', 'source_kind' => 'diagram', 'asset_id' => 'orig3'];
        $slides = [
            ['n' => 1, 'title' => 'Cover', 'slide_type' => 'cover', 'taught_concept_ids' => [1], 'content' => ['body' => 'Cover'], 'image' => null, 'workflow_stage' => null, 'interaction' => null],
            ['n' => 2, 'title' => 'Rain falling', 'slide_type' => 'concept_intro', 'taught_concept_ids' => [1], 'content' => ['body' => 'Text 2'], 'image' => $this->photo($this->ids[2], 'Rain falling'), 'workflow_stage' => 3, 'workflow_stage_name' => 'Explore', 'outcome_refs' => ['C1.1'], 'interaction' => null],
            ['n' => 3, 'title' => 'Our Exploration Goals', 'slide_type' => 'concept_intro', 'taught_concept_ids' => [1], 'content' => ['body' => 'Text 3'], 'image' => $diagram, 'workflow_stage' => 4, 'interaction' => null,
                'visual' => ['required' => true, 'role' => 'instrument', 'query' => 'compass on map', 'purpose' => 'Symbolizes the direction of the lesson.']],
            ['n' => 4, 'title' => 'Parts', 'slide_type' => 'concept_intro', 'taught_concept_ids' => [1], 'content' => ['body' => 'Text 4'], 'image' => ['type' => 'diagram'] + $this->photo($this->ids[4], 'Parts'), 'interaction' => ['kind' => 'hotspots', 'spots' => [['label' => 'Rain', 'x' => 10, 'y' => 10, 'w' => 20, 'h' => 20]]]],
            ['n' => 5, 'title' => 'Exit ticket', 'slide_type' => 'exit_ticket', 'taught_concept_ids' => [1], 'content' => ['body' => 'Text 5'], 'image' => null, 'image_missing' => 'no sufficiently relevant, openly licensed image found', 'interaction' => null],
            ['n' => 6, 'title' => 'The tools of an explorer', 'slide_type' => 'hook', 'taught_concept_ids' => [1], 'content' => ['body' => 'Text 6'], 'visual' => ['required' => true, 'role' => 'object', 'query' => 'explorer tools on a desk'],
                'image' => ['alt' => 'Diagram: The tools of an explorer', 'caption' => 'Tools', 'purpose' => 'Generated educational diagram for Tools', 'asset_id' => 'orig6'] + ['type' => 'diagram', 'url' => StudyDeckImages::ref($this->ids[6])],
                'interaction' => ['kind' => 'reveal', 'reason' => 'Tools', 'intro' => 'Select a tool.', 'wrapup' => 'Both help.', 'items' => [
                    ['id' => 'i1', 'label' => 'The magnifying glass', 'text' => 'Careful observation.'],
                    ['id' => 'i2', 'label' => 'The compass', 'text' => 'Purposeful direction.'],
                    ['id' => 'i3', 'label' => 'The map', 'text' => 'Where you are going.', 'image' => $this->photo($this->ids[7], 'A map')],
                ]]],
        ];
        $deck = ['version' => 3, 'chapter' => ['id' => self::CHAPTER, 'name' => 'Water'], 'chapter_id' => self::CHAPTER, 'slide_count' => 6, 'concepts' => [1 => ['name' => 'Water cycle']], 'slides' => $slides,
            'workflow' => ['method' => 'inquiry_based', 'stages' => [['number' => 3, 'name' => 'Explore']]], 'assets' => ['orig2' => ['image_id' => $this->ids[2]]]];

        DB::table('content_master')->insert(['id' => self::CONTENT, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
            'content_category' => 'Classroom Presentation', 'title' => 'Water', 'filename' => self::FILE, 'file_type' => 'pptx', 'show_hide' => 1, 'source' => config('claude.source_label', 'Claude AI')]);
        $this->store->link(self::CONTENT, array_values($this->ids), self::CHAPTER);
        Storage::disk('digitalocean')->put(ContentGenerationService::studyDeckSidecarPath(self::FILE), json_encode($deck, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $deck;
    }

    private function sidecarRaw(): string
    {
        return (string) Storage::disk('digitalocean')->get(ContentGenerationService::studyDeckSidecarPath(self::FILE));
    }

    private function sidecar(): array
    {
        return json_decode($this->sidecarRaw(), true);
    }

    /** Fake search and download; the editors are the real ones. */
    private function useEditors(): void
    {
        $self = $this;
        $search = new class($self) extends ConceptImageSearchService {
            public function __construct(private StoredDeckImageEditTest $t)
            {
            }

            public function available(): bool
            {
                return true;
            }

            public function forBatch(): static
            {
                return $this;
            }

            public function rankedImagesFor(string $query, int $limit = 5, ?float $minScore = null, ?string $source = null): array
            {
                return $this->t->found($query);
            }
        };
        // Each address downloads its own picture, as in life: two items given different photographs get two different stored pictures.
        $planner = fn (int $tenant, int $chapter) => new ImagePlanner($search, StudyImageStores::database($tenant, $chapter), fn (string $url) => $this->downloadFails ? null : ['bytes' => $this->png(640, 480, crc32($url) % 250), 'mime' => 'image/png', 'width' => 640, 'height' => 480, 'source' => $url]);
        $this->app->instance(DraftImageEditor::class, new DraftImageEditor(app(GenerationRun::class), $search, null, $planner));
    }

    /**
     * The photographs a search returns: a search for a tool finds that tool's own photograph, any other search finds the rain.
     *
     * @return array<int,array<string,mixed>>
     */
    public function found(string $query = ''): array
    {
        foreach (['magnifying' => 'Magnifying_glass', 'compass' => 'Compass'] as $word => $file) {
            if (stripos($query, $word) !== false) {
                return [['url' => "https://upload.test/wikipedia/commons/c/c1/$file.jpg", 'thumbnail_url' => "https://upload.test/thumb/$file.jpg", 'title' => str_replace('_', ' ', $file), 'creator' => 'Bo Photo', 'license' => 'BY-SA 4.0',
                    'source_url' => "https://commons.test/wiki/File:$file.jpg", 'provider' => 'wikimedia', 'width' => 3000, 'height' => 2000, 'tags' => [$word]]];
            }
        }

        return $this->found;
    }

    private function read(string $path, array $q = [], ?int $tenant = self::TENANT)
    {
        return $this->asTeacher($tenant)->getJson('/api/lms/course-presentation/stored-image/' . $path . '?' . http_build_query($q + ['chapter_id' => self::CHAPTER, 'content_id' => self::CONTENT]));
    }

    private function edit(string $action, array $extra = [])
    {
        return $this->asTeacher()->post('/api/lms/course-presentation/stored-image/' . $action, ['chapter_id' => self::CHAPTER, 'content_id' => self::CONTENT] + $extra, ['Accept' => 'application/json']);
    }

    private function candidateId(int $slide = 2): string
    {
        return $this->read('search', ['slide' => $slide, 'q' => 'rain'])->assertOk()->json('candidates.0.id');
    }

    /** What the player is sent when it opens the stored presentation (its own read endpoint). */
    private function playerDeck(): array
    {
        return $this->postJson('/api/lms-study-deck', ['chapter_id' => self::CHAPTER, 'content_id' => self::CONTENT, 'sub_institute_id' => self::TENANT])->assertOk()->json('data');
    }

    // ------------------------------------------------------------------------------------------------ opening the editor

    public function test_a_stored_presentation_says_which_pictures_can_be_changed_and_why_not(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $s = $this->read('states')->assertOk()->json('slide_images');

        $this->assertTrue($s['2']['can_replace'] && !$s['2']['can_add'] && $s['2']['kind'] === 'photo');
        $this->assertTrue($s['3']['can_replace'] && $s['3']['kind'] === 'diagram', 'a stand-in diagram can be swapped for a photograph');
        $this->assertTrue($s['5']['can_add'] && !$s['5']['has_image'] && $s['5']['kind'] === 'none', 'a slide with no photograph can be given one');
        $this->assertFalse($s['4']['can_replace'] || $s['4']['can_add']);
        $this->assertStringContainsString('hotspots', $s['4']['reason']);
        foreach ($s as $state) {
            $this->assertFalse($state['can_remove'], 'a stored slide is not left bare by a click');
            $this->assertFalse($state['can_restore'], 'nothing has been changed yet');
        }
    }

    public function test_it_works_for_the_pilot_chapter_and_school_only(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $this->read('states', [], 2)->assertStatus(404)->assertJsonPath('status_code', 0);   // another school cannot even see the chapter
        $this->asTeacher()->getJson('/api/lms/course-presentation/stored-image/states?' . http_build_query(['chapter_id' => 12345, 'content_id' => self::CONTENT]))->assertStatus(404);
        config(['pedagogical_workflow.scope.chapter_id' => 0]);   // the pilot is not this chapter any more
        $this->read('states')->assertStatus(403);
        $this->read('search', ['slide' => 2])->assertStatus(403);
        $this->edit('replace', ['slide' => 2, 'candidate_id' => str_repeat('a', 16)])->assertStatus(403);
    }

    public function test_a_content_item_of_another_chapter_or_kind_is_not_found(): void
    {
        $this->storeDeck();
        $this->useEditors();
        DB::table('content_master')->insert(['id' => 901, 'chapter_id' => 777, 'sub_institute_id' => self::TENANT, 'content_category' => 'Classroom Presentation', 'filename' => 'study_deck_x.pptx', 'file_type' => 'pptx']);
        DB::table('content_master')->insert(['id' => 902, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT, 'content_category' => 'Revision Notes', 'filename' => 'study_revision_x.pdf', 'file_type' => 'pdf']);

        $this->read('states', ['content_id' => 901])->assertStatus(404);
        $this->read('states', ['content_id' => 902])->assertStatus(404);
        $this->read('states', ['content_id' => 999999])->assertStatus(404);
    }

    // ------------------------------------------------------------------------------------------------ search

    public function test_search_offers_openly_licensed_photographs_with_evidence_and_never_the_server_address(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $before = $this->sidecarRaw();
        $images = DB::table('study_deck_images')->count();

        $res = $this->read('search', ['slide' => 2, 'q' => 'rain'])->assertOk();

        $this->assertSame('rain', $res->json('query'));
        $this->assertCount(1, $res->json('candidates'), 'the all-rights-reserved picture is not offered');
        $c = $res->json('candidates.0');
        $this->assertSame(['BY-SA 4.0', 'Bo Photo', 'wikimedia', 4000, 3000, ['rain']], [$c['licence'], $c['creator'], $c['provider'], $c['width'], $c['height'], $c['matches']]);
        $this->assertArrayNotHasKey('url', $c);
        $this->assertSame($before, $this->sidecarRaw(), 'a search writes nothing');
        $this->assertSame($images, DB::table('study_deck_images')->count());
    }

    public function test_the_default_search_uses_the_slides_own_title_and_concept(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $q = $this->read('search', ['slide' => 2])->assertOk()->json('query');

        $this->assertStringContainsString('Water', $q);
    }

    public function test_a_slide_with_a_picture_plan_searches_for_what_it_planned_and_offers_its_title_and_concept_in_one_click(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $res = $this->read('search', ['slide' => 3])->assertOk();

        $this->assertSame('compass on map', $res->json('query'), 'the concrete words the slide was planned with, not abstract concept names');
        $this->assertSame([['label' => 'Planned picture', 'query' => 'compass on map'], ['label' => 'Slide title', 'query' => 'Our Exploration Goals'], ['label' => 'Concept', 'query' => 'Water cycle']], $res->json('suggestions'));
        // A teacher's own words win, and the suggestions stay available.
        $this->assertSame('rain', $this->read('search', ['slide' => 3, 'q' => 'rain'])->json('query'));
    }

    public function test_a_slide_with_hotspots_cannot_be_searched_for(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $this->read('search', ['slide' => 4])->assertStatus(422)->assertJsonPath('status_code', 0);
    }

    // ------------------------------------------------------------------------------------------------ replacing, saving, reloading

    public function test_choosing_a_photograph_replaces_only_that_picture_and_keeps_its_source_licence_and_credit(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $before = $this->sidecar();

        $res = $this->edit('replace', ['slide' => 2, 'candidate_id' => $this->candidateId()])->assertOk();

        $this->assertSame(['replace', 2], [$res->json('action'), $res->json('slide')]);
        $this->assertTrue($res->json('can_restore'));
        $this->assertStringContainsString('/api/study-deck/images/', $res->json('image.url'), 'the browser is given a signed address, not a stored reference');

        $after = $this->sidecar();
        $img = $after['slides'][1]['image'];
        $newId = StudyDeckImages::idFromRef($img['url']);
        $this->assertNotNull($newId);
        $this->assertNotSame($this->ids[2], $newId);
        $this->assertSame(['photo', 'BY-SA 4.0', 'Bo Photo', 'wikimedia', 'https://commons.test/wiki/File:Rain_over_a_lake.jpg', 'web'], [$img['type'], $img['licence'], $img['creator'], $img['provider'], $img['source_url'], $img['source_kind']]);
        $this->assertStringContainsString('Bo Photo', $img['attribution']);
        $this->assertTrue($img['attribution_required']);
        $this->assertSame('Shows Rain falling', $img['purpose'], 'the picture keeps its role in the lesson');
        $this->assertNotSame('', $img['alt']);
        $this->assertSame(hash('sha256', (string) $this->store->bytes($newId)), $img['asset_id']);
        $this->assertArrayHasKey($img['asset_id'], $after['assets']);

        // Nothing but that picture (and the record of the edit) changed.
        $unchanged = fn (array $d) => array_merge(array_diff_key($d, ['image_edits' => 1, 'assets' => 1, 'slides' => 1]), ['slides' => array_values(array_filter($d['slides'], fn ($s) => $s['n'] !== 2))]);
        $this->assertSame($unchanged($before), $unchanged($after));
        $this->assertSame([3, 'Explore', ['C1.1']], [$after['slides'][1]['workflow_stage'], $after['slides'][1]['workflow_stage_name'], $after['slides'][1]['outcome_refs']], 'the workflow stage and outcome labels are untouched');

        // The original is kept: its record, its stored picture, and its link.
        $this->assertSame($this->ids[2], StudyDeckImages::idFromRef($after['image_edits']['2']['original']['url']));
        $this->assertNotNull($this->store->meta($this->ids[2]));
        $this->assertContains($this->ids[2], $this->store->linkedImageIds(self::CONTENT));
        $this->assertContains($newId, $this->store->linkedImageIds(self::CONTENT));
        // The untouched sidecar is also copied beside it, once.
        $this->assertSame($this->sidecarBackup(), json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function sidecarBackup(): string
    {
        return (string) Storage::disk('digitalocean')->get(preg_replace('/\.json$/', '.before-picture-edits.json', ContentGenerationService::studyDeckSidecarPath(self::FILE)));
    }

    public function test_the_new_picture_is_still_there_when_the_presentation_is_opened_again(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $this->edit('replace', ['slide' => 3, 'candidate_id' => $this->candidateId(3)])->assertOk();

        // A fresh read through the player's own endpoint: this is what a reload shows.
        $deck = $this->playerDeck();
        $slide = collect($deck['slides'])->firstWhere('n', 3);
        $this->assertSame('photo', $slide['image']['type'], 'the stand-in diagram is gone');
        $this->assertSame(['BY-SA 4.0', 'Bo Photo'], [$slide['image']['licence'], $slide['image']['creator']]);
        preg_match('#/study-deck/images/(\d+)#', $slide['image']['url'], $m);
        $this->assertSame(hash('sha256', (string) $this->store->bytes((int) $m[1])), $slide['image']['asset_id'], 'the address resolves to the new picture');
        $this->assertSame('Our Exploration Goals', $slide['title']);
        $this->assertSame(4, $slide['workflow_stage'], 'workflow labels survive the change');
        // ...and the states a reload would fetch agree.
        $this->assertTrue($this->read('states')->json('slide_images.3.can_restore'));
    }

    public function test_a_slide_with_no_photograph_can_be_given_one_and_the_missing_note_goes(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $res = $this->edit('replace', ['slide' => 5, 'candidate_id' => $this->candidateId(5)])->assertOk();

        $this->assertSame('add', $res->json('action'));
        $slide = $this->sidecar()['slides'][4];
        $this->assertNotNull($slide['image']);
        $this->assertNull($slide['image_missing']);
        $this->assertSame('web', $slide['image']['source_kind']);
    }

    public function test_choosing_a_picture_that_cannot_be_fetched_changes_nothing(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $id = $this->candidateId();
        $before = $this->sidecarRaw();
        $links = $this->store->linkedImageIds(self::CONTENT);
        $this->downloadFails = true;

        $this->edit('replace', ['slide' => 2, 'candidate_id' => $id])->assertStatus(422)->assertJsonPath('status_code', 0);

        $this->assertSame($before, $this->sidecarRaw(), 'the original picture is untouched');
        $this->assertSame($links, $this->store->linkedImageIds(self::CONTENT));
    }

    public function test_a_candidate_the_server_did_not_offer_is_refused(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $this->candidateId();

        $this->edit('replace', ['slide' => 2, 'candidate_id' => str_repeat('a', 16)])->assertStatus(422);
        $this->edit('replace', ['slide' => 2, 'candidate_id' => 'https://evil.test/x.jpg'])->assertStatus(422);
    }

    public function test_a_slide_whose_picture_carries_hotspots_is_not_changed(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $before = $this->sidecarRaw();

        $this->edit('replace', ['slide' => 4, 'candidate_id' => $this->candidateId()])->assertStatus(422);

        $this->assertSame($before, $this->sidecarRaw());
    }

    // ------------------------------------------------------------------------------------------------ uploading

    private function upload(int $w = 800, int $h = 600, array $extra = [], string $name = 'my lab.png')
    {
        $file = UploadedFile::fake()->createWithContent($name, $this->png($w, $h, 5));

        return $this->edit('replace', ['slide' => 2, 'image' => $file, 'rights_confirmed' => '1'] + $extra);
    }

    public function test_an_uploaded_picture_replaces_the_current_one_and_is_credited_as_the_teachers(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $res = $this->upload(800, 600, ['credit' => 'Ms Rao', 'alt' => 'Students at a lab bench'])->assertOk();

        $img = $this->sidecar()['slides'][1]['image'];
        $this->assertSame(['photo', 'teacher_upload', 'Teacher upload', 'Ms Rao', 'Students at a lab bench', 'teacher_upload'], [$img['type'], $img['provider'], $img['licence'], $img['creator'], $img['alt'], $img['source_kind']]);
        $this->assertSame('Shows Rain falling', $img['purpose']);
        $this->assertSame('replace', $res->json('action'));
        $this->assertSame(hash('sha256', $this->png(800, 600, 5)), $img['asset_id']);
    }

    public function test_an_upload_needs_the_right_to_use_it_and_a_usable_picture(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $before = $this->sidecarRaw();

        $this->edit('replace', ['slide' => 2, 'image' => UploadedFile::fake()->createWithContent('a.png', $this->png(800, 600, 1))])->assertStatus(422);   // rights not confirmed
        $this->upload(150, 150)->assertStatus(422);   // too small to be clear on a slide
        $this->edit('replace', ['slide' => 2, 'image' => UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1.4 not a picture'), 'rights_confirmed' => '1'])->assertStatus(422);

        $this->assertSame($before, $this->sidecarRaw());
    }

    // ------------------------------------------------------------------------------------------------ the original comes back

    public function test_the_original_can_be_restored_and_a_picture_never_changed_cannot(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $before = $this->sidecar();

        $this->edit('restore', ['slide' => 2])->assertStatus(422);   // never changed

        $this->edit('replace', ['slide' => 2, 'candidate_id' => $this->candidateId()])->assertOk();
        $res = $this->edit('restore', ['slide' => 2])->assertOk();

        $this->assertSame('restore', $res->json('action'));
        $this->assertFalse($res->json('can_restore'));
        $this->assertSame($before['slides'][1]['image'], $this->sidecar()['slides'][1]['image'], 'the original record, exactly as it was');
        $this->assertCount(2, $this->sidecar()['image_edits']['2']['history'], 'what was done is kept');
    }

    public function test_restoring_a_slide_that_began_without_a_picture_brings_back_the_missing_note(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $this->edit('replace', ['slide' => 5, 'candidate_id' => $this->candidateId(5)])->assertOk();
        $this->edit('restore', ['slide' => 5])->assertOk();

        $slide = $this->sidecar()['slides'][4];
        $this->assertNull($slide['image']);
        $this->assertSame('no sufficiently relevant, openly licensed image found', $slide['image_missing']);
    }

    public function test_the_first_original_is_kept_across_several_changes(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $before = $this->sidecar();

        $this->edit('replace', ['slide' => 2, 'candidate_id' => $this->candidateId()])->assertOk();
        $this->upload(700, 500)->assertOk();
        $this->edit('restore', ['slide' => 2])->assertOk();

        $this->assertSame($before['slides'][1]['image'], $this->sidecar()['slides'][1]['image']);
        $this->assertCount(3, $this->sidecar()['image_edits']['2']['history']);
    }

    // ------------------------------------------------------------------------------------------------ the pictures of a slide's items

    /** One item of slide 6 as the sidecar holds it. */
    private function item(array $sidecar, string $id): array
    {
        return collect($sidecar['slides'][5]['interaction']['items'])->firstWhere('id', $id);
    }

    private function itemCandidate(string $item, string $q): string
    {
        return $this->read('search', ['slide' => 6, 'item' => $item, 'q' => $q])->assertOk()->json('candidates.0.id');
    }

    private function editItem(string $action, string $item, array $extra = [])
    {
        return $this->edit($action, ['slide' => 6, 'item' => $item] + $extra);
    }

    private function itemPictureId(array $sidecar, string $id): ?int
    {
        return StudyDeckImages::idFromRef($this->item($sidecar, $id)['image']['url'] ?? null);
    }

    public function test_a_slide_whose_items_open_beside_its_picture_says_which_item_pictures_can_be_changed(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $s = $this->read('states')->assertOk()->json('slide_images');

        $items = $s['6']['items'];
        $this->assertSame(['i1', 'i2', 'i3'], array_keys($items));
        $this->assertTrue($items['i1']['can_add'] && !$items['i1']['has_image'] && $items['i1']['kind'] === 'none' && $items['i1']['title'] === 'The magnifying glass');
        $this->assertTrue($items['i2']['can_add'] && !$items['i2']['has_image']);
        $this->assertTrue($items['i3']['can_replace'] && !$items['i3']['can_add'] && $items['i3']['kind'] === 'photo');
        foreach ($items as $state) {
            $this->assertFalse($state['can_remove'] || $state['can_restore'], 'nothing is taken away, and nothing has been changed yet');
        }
        $this->assertTrue($s['6']['can_replace'], 'the slide keeps its own picture, changed separately');
        foreach (['1', '2', '3', '4', '5'] as $n) {
            $this->assertArrayNotHasKey('items', $s[$n], 'a slide with no items to open has no item pictures');
        }
    }

    public function test_an_item_is_searched_for_by_its_own_name_not_the_slides_topic(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $before = $this->sidecarRaw();

        $glass = $this->read('search', ['slide' => 6, 'item' => 'i1'])->assertOk();
        $compass = $this->read('search', ['slide' => 6, 'item' => 'i2'])->assertOk();

        $this->assertSame('magnifying glass', $glass->json('query'), 'the article is dropped');
        $this->assertSame('compass', $compass->json('query'));
        $this->assertSame([['label' => 'Item name', 'query' => 'compass'], ['label' => 'Slide picture', 'query' => 'explorer tools on a desk'], ['label' => 'Slide topic', 'query' => 'The tools of an explorer']], $compass->json('suggestions'), 'the name, what the slide\'s picture was planned to show, and the slide\'s topic');
        $this->assertNotSame($glass->json('candidates.0.id'), $compass->json('candidates.0.id'), 'each item is offered its own photographs');
        $this->assertSame('brass compass', $this->read('search', ['slide' => 6, 'item' => 'i2', 'q' => 'brass compass'])->json('query'), 'the teacher\'s own words win');
        $this->assertSame($before, $this->sidecarRaw(), 'a search writes nothing');
    }

    public function test_choosing_a_photograph_for_one_item_changes_that_item_only(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $before = $this->sidecar();

        $res = $this->editItem('replace', 'i1', ['candidate_id' => $this->itemCandidate('i1', 'magnifying glass')])->assertOk();

        $this->assertSame(['add', 'i1', 6], [$res->json('action'), $res->json('item'), $res->json('slide')]);
        $this->assertTrue($res->json('can_restore'));
        $this->assertStringContainsString('/api/study-deck/images/', $res->json('image.url'));

        $after = $this->sidecar();
        $glass = $this->item($after, 'i1');
        $this->assertNotNull($this->itemPictureId($after, 'i1'));
        $this->assertSame(['photo', 'BY-SA 4.0', 'Bo Photo', 'wikimedia', 'Magnifying glass'], [$glass['image']['type'], $glass['image']['licence'], $glass['image']['creator'], $glass['image']['provider'], $glass['image']['title']]);
        $this->assertSame($before['slides'][5]['image'], $after['slides'][5]['image'], 'the slide\'s own picture is untouched');
        $this->assertArrayNotHasKey('image', $this->item($after, 'i2'), 'the other tool is not given this picture');
        $this->assertSame($this->item($before, 'i3'), $this->item($after, 'i3'));
        $this->assertSame($this->item($before, 'i1')['text'], $glass['text'], 'the explanation is untouched');
        $this->assertArrayNotHasKey('6', $after['image_edits'], 'the slide\'s picture has no edit record: only the item was changed');
        $this->assertNull($after['image_edits']['6/i1']['original'], 'the item began without a picture, and that is what is kept');
        $this->assertContains($this->itemPictureId($after, 'i1'), $this->store->linkedImageIds(self::CONTENT));
        $this->assertSame($this->sidecarBackup(), json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'the untouched sidecar is kept beside it');
    }

    public function test_two_items_keep_their_own_pictures_and_each_restores_on_its_own(): void
    {
        $this->storeDeck();
        $this->useEditors();

        $this->editItem('replace', 'i1', ['candidate_id' => $this->itemCandidate('i1', 'magnifying glass')])->assertOk();
        $this->editItem('replace', 'i2', ['candidate_id' => $this->itemCandidate('i2', 'compass')])->assertOk();

        $both = $this->sidecar();
        $glass = $this->itemPictureId($both, 'i1');
        $compass = $this->itemPictureId($both, 'i2');
        $this->assertNotNull($glass);
        $this->assertNotNull($compass);
        $this->assertNotSame($glass, $compass, 'two items never share a picture');

        $res = $this->editItem('restore', 'i1')->assertOk();

        $this->assertSame(['restore', 'i1', null, false], [$res->json('action'), $res->json('item'), $res->json('image'), $res->json('can_restore')]);
        $after = $this->sidecar();
        $this->assertArrayNotHasKey('image', $this->item($after, 'i1'), 'the item is back to having none, as it began');
        $this->assertSame($compass, $this->itemPictureId($after, 'i2'), 'the other item keeps what was saved for it');
        $states = $this->read('states')->json('slide_images.6.items');
        $this->assertFalse($states['i1']['can_restore']);
        $this->assertTrue($states['i2']['can_restore']);
        $this->assertSame($both['slides'][5]['image'], $after['slides'][5]['image']);
        $this->assertCount(2, $after['image_edits']['6/i1']['history'], 'what was done is kept');
    }

    public function test_the_same_picture_is_not_given_to_two_items(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $this->editItem('replace', 'i1', ['candidate_id' => $this->itemCandidate('i1', 'magnifying glass')])->assertOk();
        $other = $this->itemCandidate('i2', 'magnifying glass');
        $before = $this->sidecarRaw();
        $links = $this->store->linkedImageIds(self::CONTENT);

        $res = $this->editItem('replace', 'i2', ['candidate_id' => $other])->assertStatus(422);

        $this->assertStringContainsString('The magnifying glass', $res->json('message'));
        $this->assertSame($before, $this->sidecarRaw());
        $this->assertSame($links, $this->store->linkedImageIds(self::CONTENT));
    }

    public function test_an_item_picture_is_still_there_when_the_presentation_is_opened_again(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $this->editItem('replace', 'i1', ['candidate_id' => $this->itemCandidate('i1', 'magnifying glass')])->assertOk();
        $this->editItem('replace', 'i2', ['candidate_id' => $this->itemCandidate('i2', 'compass')])->assertOk();

        $items = collect($this->playerDeck()['slides'])->firstWhere('n', 6)['interaction']['items'];

        $ids = [];
        foreach (['i1', 'i2', 'i3'] as $k => $id) {
            $img = $items[$k]['image'];
            $this->assertSame($id, $items[$k]['id']);
            $this->assertStringContainsString('/api/study-deck/images/', $img['url'], 'the player is given a signed address for every item picture');
            preg_match('#/study-deck/images/(\d+)#', $img['url'], $m);
            $ids[$id] = (int) $m[1];
        }
        $this->assertCount(3, array_unique($ids), 'each item shows its own picture');
        $this->assertSame(hash('sha256', (string) $this->store->bytes($ids['i2'])), $items[1]['image']['asset_id'], 'the address resolves to the compass picture');
        $this->assertTrue($this->read('states')->json('slide_images.6.items.i1.can_restore'), 'the states a reload would fetch agree');
    }

    public function test_an_item_that_began_with_a_picture_gets_exactly_that_picture_back(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $before = $this->sidecar();

        $res = $this->editItem('replace', 'i3', ['candidate_id' => $this->itemCandidate('i3', 'map')])->assertOk();
        $this->assertSame('replace', $res->json('action'));
        $this->assertNotSame($this->ids[7], $this->itemPictureId($this->sidecar(), 'i3'));

        $res = $this->editItem('restore', 'i3')->assertOk();

        $this->assertFalse($res->json('can_restore'));
        $this->assertSame($this->item($before, 'i3')['image'], $this->item($this->sidecar(), 'i3')['image'], 'the original record, exactly as it was');
        $this->assertNotNull($this->store->meta($this->ids[7]), 'the original picture was never deleted');
    }

    public function test_an_item_can_be_given_the_teachers_own_picture(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $file = UploadedFile::fake()->createWithContent('compass.png', $this->png(800, 600, 5));

        $res = $this->editItem('replace', 'i2', ['image' => $file, 'rights_confirmed' => '1', 'credit' => 'Ms Rao', 'alt' => 'A brass compass'])->assertOk();

        $img = $this->item($this->sidecar(), 'i2')['image'];
        $this->assertSame(['i2', 'add', 'teacher_upload', 'Ms Rao', 'A brass compass'], [$res->json('item'), $res->json('action'), $img['provider'], $img['creator'], $img['alt']]);
        $this->assertArrayNotHasKey('image', $this->item($this->sidecar(), 'i1'));
    }

    public function test_the_slides_own_picture_and_its_items_pictures_are_changed_separately(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $this->editItem('replace', 'i1', ['candidate_id' => $this->itemCandidate('i1', 'magnifying glass')])->assertOk();
        $glass = $this->itemPictureId($this->sidecar(), 'i1');

        $this->edit('replace', ['slide' => 6, 'candidate_id' => $this->candidateId(6)])->assertOk();

        $after = $this->sidecar();
        $this->assertSame('photo', $after['slides'][5]['image']['type']);
        $this->assertSame($glass, $this->itemPictureId($after, 'i1'), 'changing the slide\'s picture leaves the item\'s');
        $this->assertArrayHasKey('6', $after['image_edits'], 'the slide\'s own picture has its edit record');
        $this->assertArrayHasKey('6/i1', $after['image_edits'], 'and the item has its own');
        $this->edit('restore', ['slide' => 6])->assertOk();
        $this->assertSame($glass, $this->itemPictureId($this->sidecar(), 'i1'), 'and restoring the slide\'s picture leaves it too');
    }

    public function test_item_pictures_are_refused_where_the_slide_has_no_items_to_open_or_the_item_is_unknown(): void
    {
        $this->storeDeck();
        $this->useEditors();
        $before = $this->sidecarRaw();
        $id = $this->candidateId();

        $this->read('search', ['slide' => 6, 'item' => 'nope'])->assertStatus(404);
        $this->edit('replace', ['slide' => 6, 'item' => 'nope', 'candidate_id' => $id])->assertStatus(404);
        $this->edit('replace', ['slide' => 2, 'item' => 'i1', 'candidate_id' => $id])->assertStatus(422);   // a slide with no items
        $this->edit('replace', ['slide' => 4, 'item' => 'h1', 'candidate_id' => $id])->assertStatus(422);   // hotspots keep one picture
        $this->editItem('restore', 'i1')->assertStatus(422);   // never changed
        $this->editItem('replace', 'i1;drop', ['candidate_id' => $id])->assertStatus(422);   // not an item name
        $this->editItem('replace', 'i1', ['candidate_id' => str_repeat('a', 16)])->assertStatus(422);   // not offered by the search

        $this->assertSame($before, $this->sidecarRaw());
    }
}
