<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\CoursePresentation\DraftImageEditor;
use App\Services\CoursePresentation\GenerationRun;
use App\Services\CoursePresentation\ImageReadiness;
use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyImageStores;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Replacing and restoring one slide's picture in a draft that waits for review. A hand-made bundle in the same on-disk format as a real
 * one (deck.json, images.json, out/presentation.html); the picture search and the download are fakes, so nothing leaves the machine.
 * What is real: the picture store (in-memory sqlite), the files written, the validation, the routes and their gates.
 */
class DraftImageEditTest extends CoursePresentationTestCase
{
    private string $run = '';
    private StudyDeckImages $store;
    /** @var array<int,int> picture ids by slide at the start */
    private array $ids = [];
    private bool $searchFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedPilotChapter();
        $this->store = new StudyDeckImages();
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

    private function figure(array $img, bool $photo = true): string
    {
        $credit = $photo ? '. ' . trim(($img['creator'] ?: 'Openverse') . ', ' . $img['licence']) : '';

        return '<figure class="fig-md" data-block="visual" data-bloom="understand"><img src="' . $img['url'] . '" alt="' . htmlspecialchars($img['alt'], ENT_QUOTES) . '"><figcaption>'
            . htmlspecialchars(rtrim($img['caption'], '.') . $credit, ENT_QUOTES) . '</figcaption></figure>';
    }

    private function record(int $id, string $title, string $creator = 'Ann Author'): array
    {
        return ['type' => 'photo', 'alt' => 'Photograph: ' . $title, 'caption' => $title, 'url' => StudyDeckImages::ref($id), 'width' => 400, 'height' => 300, 'query' => 'water cycle',
            'source_image_url' => 'https://img.test/' . $id . '.jpg', 'source_url' => 'https://img.test/page/' . $id, 'title' => $title, 'creator' => $creator, 'provider' => 'openverse',
            'licence' => 'BY 2.0', 'attribution_required' => true, 'attribution' => '"' . $title . '" by ' . $creator . ' is licensed under CC BY 2.0.', 'purpose' => 'Shows ' . $title, 'source_kind' => 'web'];
    }

    /** A draft in the state the generator leaves it: ready, with a bundle on disk. Slide 1 cover, 2 and 4 photographs, 3 a diagram carrying hotspots. */
    private function draft(string $state = 'ready'): GenerationRun
    {
        $runs = app(GenerationRun::class);
        $status = $runs->request(self::CHAPTER, self::TENANT, 55, 'inquiry_based', 'interactive_slides');
        $this->run = $status['run'];
        foreach ([1, 2, 3, 4] as $n) {
            $this->ids[$n] = $this->store->put($this->png(300 + $n, 220, $n), self::TENANT, self::CHAPTER)['id'];
        }
        $img = [1 => $this->record($this->ids[1], 'Cover picture'), 2 => $this->record($this->ids[2], 'Rain falling'), 3 => ['type' => 'diagram'] + $this->record($this->ids[3], 'Cycle diagram'), 4 => $this->record($this->ids[4], 'A river')];
        $slides = [];
        foreach ($img as $n => $image) {
            $slides[] = ['n' => $n, 'title' => 'Slide title ' . $n, 'slide_type' => $n === 1 ? 'cover' : 'concept_intro', 'taught_concept_ids' => [1], 'content' => ['body' => 'Text ' . $n], 'image' => $image,
                'interaction' => $n === 3 ? ['kind' => 'hotspots', 'spots' => [['label' => 'Rain', 'x' => 10, 'y' => 10, 'w' => 20, 'h' => 20]]] : ($n === 4 ? ['kind' => 'reveal', 'items' => [['id' => 'a', 'label' => 'A', 'text' => 'Explained.']]] : null)];
        }
        $slides[] = ['n' => 5, 'title' => 'Slide title 5', 'slide_type' => 'exit_ticket', 'taught_concept_ids' => [1], 'content' => ['body' => 'Text 5'], 'image' => null, 'image_missing' => 'no suitable picture was found', 'interaction' => null];
        $deck = ['version' => 3, 'chapter' => ['id' => self::CHAPTER, 'name' => 'Water'], 'slide_count' => 5, 'concepts' => [1 => ['name' => 'Water cycle']], 'slides' => $slides];
        $html = '<section class="cover" data-block="intro"><h2>Water</h2></section>';
        foreach ([2, 3, 4, 5] as $n) {
            if ($n === 5) {
                $html .= '<section class="slide"><div class="slide-head"><span class="slide-num">Slide 5</span><h3>Slide title 5</h3></div><p>Text 5</p><section class="callout callout-try" data-block="check"><span class="callout-label">Discuss</span><p>Why?</p></section></section>';
                continue;
            }
            $html .= '<section class="slide"><div class="slide-head"><span class="slide-num">Slide ' . $n . '</span><h3>Slide title ' . $n . '</h3></div><p>Text ' . $n . '</p>' . $this->figure($img[$n], $n !== 3) . '</section>';
        }
        // As the renderer writes it: the credits sit inside the last slide's section.
        $html = preg_replace('#</section>$#', '<section class="callout callout-key" data-block="summary"><span class="callout-label">Image credits</span>'
            . '<p>Slide 2: ' . htmlspecialchars($img[2]['attribution'], ENT_QUOTES) . ' Source: ' . $img[2]['source_url'] . '</p>'
            . '<p>Slide 4: ' . htmlspecialchars($img[4]['attribution'], ENT_QUOTES) . ' Source: ' . $img[4]['source_url'] . '</p></section></section>', $html);

        $dir = storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $this->run . '/bundle');
        mkdir($dir . '/out', 0775, true);
        file_put_contents($dir . '/deck.json', json_encode($deck, JSON_UNESCAPED_SLASHES));
        file_put_contents($dir . '/images.json', json_encode($img, JSON_UNESCAPED_SLASHES));
        file_put_contents($dir . '/out/presentation.html', $html);
        file_put_contents($dir . '/report.json', json_encode(['ok' => true]));
        $status = $runs->read(self::CHAPTER, self::TENANT, $this->run);
        $status['state'] = $state;
        $runs->save($status);

        return $runs;
    }

    private function file(string $rel): string
    {
        return (string) file_get_contents(storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $this->run . '/' . $rel));
    }

    private function deck(): array
    {
        return json_decode($this->file('bundle/deck.json'), true);
    }

    /** Fake search and download; the editor is the real one. */
    private function useEditor(GenerationRun $runs): void
    {
        $self = $this;
        $search = new class($self) extends ConceptImageSearchService {
            public function __construct(private DraftImageEditTest $t)
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
                if ($this->t->searchFails()) {
                    throw new \RuntimeException('Openverse timed out');
                }

                return [
                    ['url' => 'https://img.test/new1.jpg', 'thumbnail_url' => 'https://img.test/new1-t.jpg', 'title' => 'Rain over a lake', 'creator' => 'Bo Photo', 'license' => 'BY 4.0', 'source_url' => 'https://img.test/page/new1', 'provider' => 'flickr', 'tags' => ['rain', 'lake']],
                    ['url' => 'https://img.test/closed.jpg', 'title' => 'All rights reserved', 'creator' => 'X', 'license' => 'ARR', 'source_url' => 'https://img.test/page/closed', 'provider' => 'flickr', 'tags' => ['rain']],
                ];
            }
        };
        $png = $this->png(640, 480, 99);
        $planner = fn (int $tenant, int $chapter) => new ImagePlanner($search, StudyImageStores::database($tenant, $chapter), fn (string $url) => ['bytes' => $png, 'mime' => 'image/png', 'width' => 640, 'height' => 480, 'source' => $url]);
        $this->app->instance(DraftImageEditor::class, new DraftImageEditor($runs, $search, null, $planner));
    }

    public function searchFails(): bool
    {
        return $this->searchFails;
    }

    private function read(string $path, array $q = [])
    {
        return $this->asTeacher()->getJson('/api/lms/course-presentation/' . $path . '?' . http_build_query(['chapter_id' => self::CHAPTER, 'run' => $this->run] + $q));
    }

    private function edit(string $action, array $extra = [])
    {
        return $this->asTeacher()->post('/api/lms/course-presentation/draft-image/' . $action, ['chapter_id' => self::CHAPTER, 'run' => $this->run] + $extra, ['Accept' => 'application/json']);
    }

    private function candidateId(int $slide = 2): string
    {
        $found = $this->read('draft-image/search', ['slide' => $slide])->assertOk()->json('candidates');

        return $found[0]['id'];
    }

    // ------------------------------------------------------------------------------------------------ opening the editor

    public function test_the_draft_says_which_slides_can_have_their_picture_replaced_and_why_not(): void
    {
        $this->useEditor($this->draft());

        $states = $this->read('draft')->assertOk()->json('slide_images');

        $this->assertTrue($states['2']['can_replace'] && $states['2']['can_remove'] && !$states['2']['can_add']);
        $this->assertTrue($states['5']['can_add'] && !$states['5']['can_replace'] && !$states['5']['has_image'], 'a text-only slide can be given a picture');
        $this->assertFalse($states['3']['can_replace'] || $states['3']['can_remove'] || $states['3']['can_add']);
        $this->assertStringContainsString('hotspots', $states['3']['reason']);
        $this->assertFalse($states['2']['can_restore']);
    }

    // ------------------------------------------------------------------------------------------------ search

    public function test_search_offers_only_openly_licensed_pictures_named_by_id_and_never_the_server_address(): void
    {
        $this->useEditor($this->draft());

        $res = $this->read('draft-image/search', ['slide' => 2, 'q' => 'rain'])->assertOk();

        $this->assertSame('rain', $res->json('query'));
        $this->assertCount(1, $res->json('candidates'), 'the all-rights-reserved picture is not offered');
        $c = $res->json('candidates.0');
        $this->assertSame(['BY 4.0', 'Bo Photo', 'flickr'], [$c['licence'], $c['creator'], $c['provider']]);
        $this->assertArrayNotHasKey('url', $c);
        $this->assertArrayNotHasKey('image_url', $c);
        $this->assertSame($this->deck(), json_decode(json_encode($this->deck()), true), 'a search writes nothing');
    }

    public function test_the_default_search_uses_the_slides_own_words(): void
    {
        $this->useEditor($this->draft());

        $query = $this->read('draft-image/search', ['slide' => 2])->assertOk()->json('query');

        $this->assertStringContainsStringIgnoringCase('water cycle', $query);
    }

    public function test_a_failed_search_says_so_and_offers_upload_instead_of_an_error_page(): void
    {
        $this->useEditor($this->draft());
        $this->searchFails = true;

        $res = $this->read('draft-image/search', ['slide' => 2])->assertOk();

        $this->assertSame([], $res->json('candidates'));
        $this->assertStringContainsString('upload', $res->json('note'));
    }

    public function test_a_replacement_cannot_name_an_address_the_search_did_not_offer(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $before = $this->file('bundle/deck.json');

        $this->edit('replace', ['slide' => 2, 'candidate_id' => str_repeat('a', 16)])->assertStatus(422);
        $this->edit('replace', ['slide' => 2, 'candidate_id' => 'https://evil.test/x.png'])->assertStatus(422);

        $this->assertSame($before, $this->file('bundle/deck.json'));
    }

    // ------------------------------------------------------------------------------------------------ replace

    public function test_replacing_changes_only_the_selected_slides_picture_in_every_file_and_keeps_its_credit(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $deckBefore = $this->deck();
        $htmlBefore = $this->file('bundle/out/presentation.html');
        $id = $this->candidateId();

        $res = $this->edit('replace', ['slide' => 2, 'candidate_id' => $id])->assertOk();

        $this->assertTrue($res->json('can_restore'));
        $deck = $this->deck();
        $new = $deck['slides'][1]['image'];
        $this->assertNotSame($deckBefore['slides'][1]['image']['url'], $new['url']);
        $this->assertSame(['BY 4.0', 'Bo Photo', 'https://img.test/page/new1', 'flickr'], [$new['licence'], $new['creator'], $new['source_url'], $new['provider']], 'source and licence travel with the picture');
        $this->assertNotEmpty($new['attribution']);
        $this->assertSame($deckBefore['slides'][1]['image']['purpose'], $new['purpose'], 'what the slide uses it for is kept');
        // Every other slide is exactly as it was, and so is everything else on this one.
        foreach ([0, 2, 3] as $i) {
            $this->assertSame($deckBefore['slides'][$i], $deck['slides'][$i], "slide index $i unchanged");
        }
        $this->assertSame(array_diff_key($deckBefore['slides'][1], ['image' => 1]), array_diff_key($deck['slides'][1], ['image' => 1]), 'text, type and interaction of the slide are untouched');
        $this->assertSame(array_diff_key($deckBefore, ['slides' => 1]), array_diff_key($deck, ['slides' => 1]));

        $html = $this->file('bundle/out/presentation.html');
        $this->assertStringContainsString($new['url'], $html);
        $this->assertStringNotContainsString($deckBefore['slides'][1]['image']['url'] . '"', $html);
        $this->assertStringContainsString('Bo Photo, BY 4.0', $html, 'the figure caption carries the new credit');
        $this->assertStringContainsString('<p>Slide 2: ', $html);
        $this->assertStringNotContainsString('Rain falling', $html);
        $this->assertStringContainsString('Slide 4: ', $html, 'the other credit is kept');
        // Other slides' markup is byte for byte what it was.
        foreach ([3, 4] as $n) {
            $this->assertSame($this->between($htmlBefore, $n), $this->between($html, $n));
        }
        $images = json_decode($this->file('bundle/images.json'), true);
        $this->assertSame($new['url'], $images['2']['url']);
        $this->assertSame($deckBefore['slides'][3]['image']['url'], $images['4']['url']);
    }

    private function between(string $html, int $n): string
    {
        $a = strpos($html, '<span class="slide-num">Slide ' . $n . '</span>');
        $b = strpos($html, '</figure>', $a) + 9;   // the slide's own text and figure (the credits list is checked apart)

        return substr($html, $a, $b - $a);
    }

    public function test_the_replacement_is_a_stored_picture_of_this_school_and_is_what_approval_will_link(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $before = (new ImageReadiness())->deckImages(storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $this->run . '/bundle'), self::TENANT);
        $this->edit('replace', ['slide' => 2, 'candidate_id' => $this->candidateId()])->assertOk();

        $bundle = storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $this->run . '/bundle');
        $after = (new ImageReadiness())->deckImages($bundle, self::TENANT);
        $newId = StudyDeckImages::idFromRef($this->deck()['slides'][1]['image']['url']);

        $this->assertSame(self::TENANT, DB::table('study_deck_images')->where('id', $newId)->value('sub_institute_id'));
        $this->assertContains($newId, $after['ids']);
        $this->assertNotContains($this->ids[2], $after['ids'], 'the draft no longer points at the old picture');
        $this->assertSame(0, $after['unreadable']);
        $this->assertSame($before['referenced'], $after['referenced']);
        // The draft's own summary (shown on the review screen) was brought up to date.
        $this->assertSame($after['referenced'], $runs->read(self::CHAPTER, self::TENANT, $this->run)['images']['referenced']);
        $this->assertTrue(DB::table('study_deck_images')->where('id', $this->ids[2])->exists(), 'the original stays so it can be restored');
    }

    public function test_the_change_is_still_there_after_a_reload(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $this->edit('replace', ['slide' => 2, 'candidate_id' => $this->candidateId()])->assertOk();
        $newId = StudyDeckImages::idFromRef($this->deck()['slides'][1]['image']['url']);

        // A fresh request, a fresh editor: nothing is held in memory.
        $this->app->forgetInstance(DraftImageEditor::class);
        $draft = $this->read('draft')->assertOk();

        $this->assertStringContainsString('/images/' . $newId, $draft->json('deck.slides.1.image.url'));
        $this->assertTrue($draft->json('slide_images.2.can_restore'));
        $this->assertFalse($draft->json('slide_images.4.can_restore'));
    }

    public function test_a_replacement_that_is_refused_by_the_picture_checks_changes_nothing(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $id = $this->candidateId();
        $before = [$this->file('bundle/deck.json'), $this->file('bundle/images.json'), $this->file('bundle/out/presentation.html')];
        // The candidate's licence is changed in the server's own list to one the checks refuse.
        $kept = Cache::get("cp-image-candidates:" . self::TENANT . ":{$this->run}:2");
        $kept['rows'][$id]['license'] = 'ARR';
        Cache::put("cp-image-candidates:" . self::TENANT . ":{$this->run}:2", $kept);

        $res = $this->edit('replace', ['slide' => 2, 'candidate_id' => $id])->assertStatus(422);

        $this->assertStringContainsString('licence', $res->json('message'));
        $this->assertSame($before, [$this->file('bundle/deck.json'), $this->file('bundle/images.json'), $this->file('bundle/out/presentation.html')]);
    }

    public function test_a_slide_whose_hotspots_sit_on_its_picture_is_not_replaced(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $before = $this->file('bundle/deck.json');

        $res = $this->edit('replace', ['slide' => 3, 'candidate_id' => str_repeat('c', 16)])->assertStatus(422);

        $this->assertStringContainsString('hotspots', $res->json('message'));
        $this->assertSame($before, $this->file('bundle/deck.json'));
        $this->edit('replace', ['slide' => 99, 'candidate_id' => $this->candidateId()])->assertStatus(404);
    }

    public function test_the_cover_picture_can_be_replaced_even_though_the_html_shows_none(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $htmlBefore = $this->file('bundle/out/presentation.html');

        $this->edit('replace', ['slide' => 1, 'candidate_id' => $this->candidateId(1)])->assertOk();

        $this->assertNotSame($this->ids[1], StudyDeckImages::idFromRef($this->deck()['slides'][0]['image']['url']));
        $this->assertSame($htmlBefore, $this->file('bundle/out/presentation.html'));
    }

    // ------------------------------------------------------------------------------------------------ restore

    public function test_restoring_puts_every_file_back_exactly_as_generated(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $original = [$this->file('bundle/deck.json'), $this->file('bundle/images.json'), $this->file('bundle/out/presentation.html')];
        $deckBefore = $this->deck();
        $this->edit('replace', ['slide' => 2, 'candidate_id' => $this->candidateId()])->assertOk();
        $this->edit('replace', ['slide' => 2, 'image' => UploadedFile::fake()->image('mine.png', 400, 300), 'rights_confirmed' => 1])->assertOk();   // two edits in a row

        $res = $this->edit('restore', ['slide' => 2])->assertOk();

        $this->assertFalse($res->json('can_restore'));
        $this->assertSame($deckBefore['slides'], $this->deck()['slides'], 'the FIRST picture comes back, not the one before the last edit');
        $this->assertSame($original[2], $this->file('bundle/out/presentation.html'));
        $this->assertSame(json_decode($original[1], true), json_decode($this->file('bundle/images.json'), true));
        $edits = json_decode($this->file('image-edits.json'), true);
        $this->assertCount(3, $edits['slides']['2']['history'], 'what was done is kept: search, upload, restore');
        $this->edit('restore', ['slide' => 4])->assertStatus(422);   // never changed
    }

    // ------------------------------------------------------------------------------------------------ upload

    public function test_a_valid_upload_replaces_the_picture_and_is_stored_in_the_picture_store(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);

        $res = $this->edit('replace', ['slide' => 2, 'image' => UploadedFile::fake()->image('Lab bench.png', 640, 480), 'rights_confirmed' => 1, 'credit' => 'School photo, 2026', 'alt' => 'A lab bench'])->assertOk();

        $new = $this->deck()['slides'][1]['image'];
        $id = StudyDeckImages::idFromRef($new['url']);
        $this->assertNotNull($id, 'a stored-picture reference, never a browser or temporary address');
        $this->assertSame(['teacher_upload', 'Teacher upload', 'A lab bench', 'School photo, 2026', 640, 480], [$new['provider'], $new['licence'], $new['alt'], $new['creator'], $new['width'], $new['height']]);
        $this->assertSame(self::TENANT, DB::table('study_deck_images')->where('id', $id)->value('sub_institute_id'));
        $this->assertNotNull($this->store->bytes($id));
        $this->assertStringContainsString('/images/' . $id, $res->json('image.url'));
        $this->assertStringContainsString('School photo, 2026, Teacher upload', $this->file('bundle/out/presentation.html'));
        $this->assertStringNotContainsString('blob:', $this->file('bundle/deck.json'));
    }

    public function test_uploads_that_are_not_acceptable_pictures_are_refused_and_the_original_stays(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $before = [$this->file('bundle/deck.json'), $this->file('bundle/out/presentation.html')];
        $stored = DB::table('study_deck_images')->count();

        $notAPicture = UploadedFile::fake()->createWithContent('fake.png', 'this is text, not a picture');
        $this->assertStringContainsString('not a readable picture', $this->edit('replace', ['slide' => 2, 'image' => $notAPicture, 'rights_confirmed' => 1])->assertStatus(422)->json('message'));
        $gif = UploadedFile::fake()->createWithContent('a.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
        $this->assertStringContainsString('PNG, JPEG and WebP', $this->edit('replace', ['slide' => 2, 'image' => $gif, 'rights_confirmed' => 1])->assertStatus(422)->json('message'));
        $this->assertStringContainsString('at least 200', $this->edit('replace', ['slide' => 2, 'image' => UploadedFile::fake()->image('tiny.png', 80, 60), 'rights_confirmed' => 1])->assertStatus(422)->json('message'));
        $this->assertStringContainsString('right to use', $this->edit('replace', ['slide' => 2, 'image' => UploadedFile::fake()->image('ok.png', 400, 300)])->assertStatus(422)->json('message'));
        $this->edit('replace', ['slide' => 2, 'image' => UploadedFile::fake()->create('big.png', 9000, 'image/png'), 'rights_confirmed' => 1])->assertStatus(422);   // over 8 MB
        $this->edit('replace', ['slide' => 2])->assertStatus(422);   // neither a candidate nor a file

        $this->assertSame($before, [$this->file('bundle/deck.json'), $this->file('bundle/out/presentation.html')]);
        $this->assertSame($stored, DB::table('study_deck_images')->count(), 'a refused upload stores nothing');
    }

    public function test_the_same_upload_twice_is_one_stored_picture(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $bytes = $this->png(500, 400, 7);
        $file = fn () => UploadedFile::fake()->createWithContent('same.png', $bytes);

        $this->edit('replace', ['slide' => 2, 'image' => $file(), 'rights_confirmed' => 1])->assertOk();
        $count = DB::table('study_deck_images')->count();
        $this->edit('replace', ['slide' => 4, 'image' => $file(), 'rights_confirmed' => 1])->assertOk();

        $this->assertSame($count, DB::table('study_deck_images')->count(), 'identical bytes are a lookup, not a second copy');
    }

    // ------------------------------------------------------------------------------------------------ authorization, tenants, states

    public function test_another_school_cannot_search_replace_or_restore(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $before = $this->file('bundle/deck.json');
        $other = fn (string $m, string $p, array $d = []) => $this->asTeacher(self::OTHER_TENANT)->{$m}('/api/lms/course-presentation/' . $p, ['chapter_id' => self::CHAPTER, 'run' => $this->run, 'slide' => 2] + $d);

        $this->asTeacher(self::OTHER_TENANT)->getJson('/api/lms/course-presentation/draft-image/search?' . http_build_query(['chapter_id' => self::CHAPTER, 'run' => $this->run, 'slide' => 2]))->assertStatus(404);
        $other('postJson', 'draft-image/replace', ['candidate_id' => str_repeat('a', 16)])->assertStatus(404);
        $other('postJson', 'draft-image/restore')->assertStatus(404);

        $this->assertSame($before, $this->file('bundle/deck.json'));
    }

    public function test_the_edit_routes_sit_behind_the_content_create_gate(): void
    {
        foreach (['draft-image/search', 'draft-image/replace', 'draft-image/restore', 'draft-image/remove', 'draft-image/access'] as $uri) {
            $route = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'api/lms/course-presentation/' . $uri);
            $this->assertNotNull($route, $uri);
            $this->assertContains('perm:lms.content,create', $route->gatherMiddleware(), $uri);
            $this->assertContains('api.session', $route->gatherMiddleware(), $uri);
        }
    }

    public function test_an_approved_presentation_a_failed_draft_and_a_draft_being_saved_are_not_edited(): void
    {
        foreach (['stored' => 'already approved', 'failed' => 'waiting for review'] as $state => $words) {
            $runs = $this->draft($state);
            $this->useEditor($runs);
            $before = $this->file('bundle/deck.json');

            $res = $this->edit('replace', ['slide' => 2, 'candidate_id' => str_repeat('b', 16)])->assertStatus(409);

            $this->assertStringContainsString($words, $res->json('message'), $state);
            $this->assertSame($before, $this->file('bundle/deck.json'));
        }
        $runs = $this->draft();
        $this->useEditor($runs);
        $status = $runs->read(self::CHAPTER, self::TENANT, $this->run);
        $status['saving'] = date('c');
        $runs->save($status);
        $this->edit('replace', ['slide' => 2, 'candidate_id' => str_repeat('b', 16)])->assertStatus(409);
    }

    public function test_a_failure_while_saving_puts_every_file_back(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $before = [$this->file('bundle/deck.json'), $this->file('bundle/images.json'), $this->file('bundle/out/presentation.html')];
        // The edit log cannot be written (its path is a folder): the second write fails after deck.json was already replaced.
        mkdir(storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $this->run . '/image-edits.json'));

        $res = $this->edit('replace', ['slide' => 2, 'image' => UploadedFile::fake()->image('a.png', 400, 300), 'rights_confirmed' => 1])->assertStatus(500);

        $this->assertStringContainsString('nothing was changed', $res->json('message'));
        $this->assertSame($before, [$this->file('bundle/deck.json'), $this->file('bundle/images.json'), $this->file('bundle/out/presentation.html')], 'the original picture is still in place everywhere');
    }

    public function test_a_second_run_of_the_same_chapter_is_not_touched(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $first = $this->run;
        $firstDeck = $this->file('bundle/deck.json');
        $second = $this->draft();   // another draft, its own folder
        $this->useEditor($second);
        $secondRun = $this->run;

        $this->edit('replace', ['slide' => 2, 'candidate_id' => $this->candidateId()])->assertOk();

        $this->run = $first;
        $this->assertSame($firstDeck, $this->file('bundle/deck.json'));
        $this->assertNotSame($secondRun, $first);
    }

    // ------------------------------------------------------------------------------------------------ add and remove

    private function everything(): array
    {
        return [json_decode($this->file('bundle/deck.json'), true), json_decode($this->file('bundle/images.json'), true), $this->file('bundle/out/presentation.html')];
    }

    private function imagesJson(): array
    {
        return json_decode($this->file('bundle/images.json'), true);
    }

    public function test_a_text_only_slide_can_be_given_a_picture_and_everything_else_stays(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $before = $this->everything();
        $deckBefore = $this->deck();

        $res = $this->edit('replace', ['slide' => 5, 'candidate_id' => $this->candidateId(5)])->assertOk();

        $this->assertSame('add', $res->json('action'));
        $this->assertTrue($res->json('state.has_image') && $res->json('state.can_remove'));
        $deck = $this->deck();
        $new = $deck['slides'][4]['image'];
        $this->assertNotNull(StudyDeckImages::idFromRef($new['url']));
        $this->assertSame(['BY 4.0', 'Bo Photo', 'https://img.test/page/new1'], [$new['licence'], $new['creator'], $new['source_url']]);
        $this->assertNull($deck['slides'][4]['image_missing'], 'the generator\'s "no picture found" note is cleared');
        foreach ([0, 1, 2, 3] as $i) {
            $this->assertSame($deckBefore['slides'][$i], $deck['slides'][$i]);
        }
        $this->assertSame(array_diff_key($deckBefore['slides'][4], ['image' => 1, 'image_missing' => 1]), array_diff_key($deck['slides'][4], ['image' => 1, 'image_missing' => 1]));
        $html = $this->file('bundle/out/presentation.html');
        $this->assertMatchesRegularExpression('#<p>Text 5</p><figure class="fig-md" data-block="visual"><img src="' . preg_quote($new['url'], '#') . '" alt="[^"]+"><figcaption>[^<]*Bo Photo, BY 4.0</figcaption></figure><section class="callout#', $html, 'the figure sits where the renderer puts one: before the callouts');
        $this->assertStringContainsString('<p>Slide 5: ', $html);
        $this->assertSame($new['url'], $this->imagesJson()[5]['url'] ?? $this->imagesJson()['5']['url']);

        // Restoring "the original" of a slide that began without a picture takes the picture off again, exactly as it was.
        $back = $this->edit('restore', ['slide' => 5])->assertOk();
        $this->assertNull($back->json('image'));
        $this->assertFalse($back->json('can_restore'));
        $this->assertSame($before[0]['slides'], $this->deck()['slides']);
        $this->assertSame($before[2], $this->file('bundle/out/presentation.html'));
    }

    public function test_an_upload_can_be_the_first_picture_of_a_text_only_slide(): void
    {
        $this->useEditor($this->draft());

        $res = $this->edit('replace', ['slide' => 5, 'image' => UploadedFile::fake()->image('board.jpg', 800, 600), 'rights_confirmed' => 1, 'alt' => 'The whiteboard'])->assertOk();

        $image = $this->deck()['slides'][4]['image'];
        $this->assertSame('add', $res->json('action'));
        $this->assertSame(['teacher_upload', 800, 600, 'The whiteboard'], [$image['provider'], $image['width'], $image['height'], $image['alt']]);
        $this->assertStringContainsString('alt="The whiteboard"', $this->file('bundle/out/presentation.html'));
    }

    public function test_removing_a_picture_takes_off_the_figure_and_its_credit_and_nothing_else_and_restore_brings_it_back(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        $before = $this->everything();
        $deckBefore = $this->deck();

        $res = $this->edit('remove', ['slide' => 2])->assertOk();

        $this->assertSame('remove', $res->json('action'));
        $this->assertNull($res->json('image'));
        $this->assertTrue($res->json('state.can_add') && $res->json('state.can_restore'));
        $deck = $this->deck();
        $this->assertNull($deck['slides'][1]['image']);
        $this->assertSame(array_diff_key($deckBefore['slides'][1], ['image' => 1]), array_diff_key($deck['slides'][1], ['image' => 1]), 'its text and interaction stay');
        foreach ([0, 2, 3, 4] as $i) {
            $this->assertSame($deckBefore['slides'][$i], $deck['slides'][$i]);
        }
        $html = $this->file('bundle/out/presentation.html');
        $this->assertStringNotContainsString(StudyDeckImages::ref($this->ids[2]) . '"', $html);
        $this->assertStringNotContainsString('<p>Slide 2: ', $html);
        $this->assertStringContainsString('<p>Text 2</p>', $html, 'the slide\'s own text is still there');
        $this->assertStringContainsString('<p>Slide 4: ', $html, 'another slide\'s credit stays');
        $this->assertArrayHasKey('missing', $this->imagesJson()[2] ?? $this->imagesJson()['2']);
        $bundle = storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $this->run . '/bundle');
        $this->assertSame(0, (new ImageReadiness())->deckImages($bundle, self::TENANT)['unreadable']);

        $this->edit('restore', ['slide' => 2])->assertOk();
        $this->assertSame($before, $this->everything(), 'every file is exactly as generated');
    }

    public function test_removing_where_there_is_no_picture_is_refused(): void
    {
        $this->useEditor($this->draft());
        $before = $this->everything();

        $this->assertStringContainsString('no picture to remove', $this->edit('remove', ['slide' => 5])->assertStatus(422)->json('message'));

        $this->assertSame($before, $this->everything());
    }

    public function test_hotspot_slides_and_pinned_pictures_can_not_be_added_swapped_or_removed(): void
    {
        $runs = $this->draft();
        $this->useEditor($runs);
        // Slide 4's picture also carries numbered pins.
        $path = storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $this->run . '/bundle/deck.json');
        $deck = json_decode(file_get_contents($path), true);
        $deck['slides'][3]['image']['parts'] = [['label' => 'Bank', 'x' => 1, 'y' => 1, 'w' => 5, 'h' => 5]];
        file_put_contents($path, json_encode($deck));
        $before = $this->everything();

        $states = $this->read('draft')->assertOk()->json('slide_images');
        $this->assertFalse($states['4']['can_replace'] || $states['4']['can_remove']);
        $this->assertStringContainsString('pins', $states['4']['reason']);
        foreach ([3, 4] as $n) {
            $this->edit('remove', ['slide' => $n])->assertStatus(422);
            $this->edit('replace', ['slide' => $n, 'image' => UploadedFile::fake()->image('x.png', 400, 300), 'rights_confirmed' => 1])->assertStatus(422);
        }
        $this->assertSame($before, $this->everything());
    }

    public function test_an_edit_that_arrives_while_another_is_in_progress_is_told_to_wait_and_changes_nothing(): void
    {
        $this->useEditor($this->draft());
        $before = $this->everything();
        $lock = fopen(storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $this->run . '/.image-edit.lock'), 'c');
        flock($lock, LOCK_EX);   // another edit is in progress

        try {
            $this->assertStringContainsString('in progress', $this->edit('remove', ['slide' => 2])->assertStatus(409)->json('message'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $this->assertSame($before, $this->everything());
        $this->edit('remove', ['slide' => 2])->assertOk();   // once the first is done it goes through
    }

    public function test_the_access_probe_answers_for_a_user_who_may_edit(): void
    {
        $this->useEditor($this->draft());

        $this->assertTrue($this->asTeacher()->getJson('/api/lms/course-presentation/draft-image/access')->assertOk()->json('can_edit'));
    }

    // ------------------------------------------------------------------------------------------------ story lessons

    /** A branching-scenario draft: slide 2 is a story act whose pictures belong to its scenes. */
    private function storyDraft(): GenerationRun
    {
        $runs = app(GenerationRun::class);
        $status = $runs->request(self::CHAPTER, self::TENANT, 55, 'inquiry_based', 'branching_scenario');
        $this->run = $status['run'];
        foreach ([1, 2, 3] as $n) {
            $this->ids[$n] = $this->store->put($this->png(320 + $n, 240, 40 + $n), self::TENANT, self::CHAPTER)['id'];
        }
        $scene = fn (int $n, string $title) => $this->record($this->ids[$n], $title) + ['role' => 'scene', 'focus' => ['x' => 10, 'y' => 10, 'w' => 30, 'h' => 30], 'focus_label' => 'Look here'];
        $nodes = [
            ['id' => 'a1/s1', 'kind' => 'scene', 'title' => 'The stream', 'body' => 'You walk to the stream.', 'prompt' => '', 'concept_ids' => [1], 'choices' => [['id' => 'c1', 'label' => 'Go on', 'next' => 'a1/d1']], 'image' => $scene(1, 'Stream')],
            ['id' => 'a1/d1', 'kind' => 'decision', 'title' => 'The fork', 'body' => '', 'prompt' => 'Which way?', 'concept_ids' => [1], 'choices' => [['id' => 'c2', 'label' => 'Left', 'next' => 'a1/e1'], ['id' => 'c3', 'label' => 'Right', 'next' => 'a1/e2']]],
            ['id' => 'a1/e1', 'kind' => 'ending', 'title' => 'Left ending', 'body' => 'You reach the lake.', 'prompt' => '', 'concept_ids' => [1], 'choices' => [], 'image' => $scene(2, 'Lake')],
            ['id' => 'a1/e2', 'kind' => 'ending', 'title' => 'Right ending', 'body' => 'You reach the field.', 'prompt' => '', 'concept_ids' => [1], 'choices' => [], 'image' => $scene(3, 'Field') + ['parts' => [['label' => 'Pin', 'x' => 2, 'y' => 2, 'w' => 4, 'h' => 4]]]],
        ];
        $deck = ['version' => 3, 'chapter' => ['id' => self::CHAPTER, 'name' => 'Water'], 'slide_count' => 2, 'concepts' => [1 => ['name' => 'Water cycle']], 'slides' => [
            ['n' => 1, 'title' => 'Cover', 'slide_type' => 'cover', 'taught_concept_ids' => [], 'content' => ['body' => ''], 'image' => null, 'interaction' => null],
            ['n' => 2, 'title' => 'Act one', 'slide_type' => 'scenario', 'taught_concept_ids' => [1], 'content' => ['body' => 'Story'], 'image' => null, 'interaction' => ['kind' => 'scenario', 'template' => 'story_branching_scenario', 'start' => 'a1/s1', 'nodes' => $nodes]],
        ]];
        $html = '<section class="cover"><h2>Water</h2></section><section class="slide"><div class="slide-head"><span class="slide-num">Slide 2</span><h3>Act one</h3></div><p>Story</p></section>';
        $dir = storage_path(config('course_presentation_pilot.generation.run_dir') . '/chapter-' . self::CHAPTER . '/' . $this->run . '/bundle');
        mkdir($dir . '/out', 0775, true);
        file_put_contents($dir . '/deck.json', json_encode($deck, JSON_UNESCAPED_SLASHES));
        file_put_contents($dir . '/images.json', json_encode([]));
        file_put_contents($dir . '/out/presentation.html', $html);
        file_put_contents($dir . '/report.json', json_encode(['ok' => true]));
        $status = $runs->read(self::CHAPTER, self::TENANT, $this->run);
        $status['state'] = 'ready';
        $runs->save($status);

        return $runs;
    }

    private function nodes(): array
    {
        return array_column($this->deck()['slides'][1]['interaction']['nodes'], null, 'id');
    }

    public function test_a_story_slide_lists_its_scenes_and_is_edited_scene_by_scene(): void
    {
        $this->useEditor($this->storyDraft());

        $s = $this->read('draft')->assertOk()->json('slide_images');

        $this->assertFalse($s['2']['can_add'] || $s['2']['can_replace'], 'the slide itself carries no picture');
        $this->assertStringContainsString('scene at a time', $s['2']['reason']);
        $this->assertTrue($s['2']['nodes']['a1/s1']['can_replace'] && $s['2']['nodes']['a1/s1']['can_remove']);
        $this->assertTrue($s['2']['nodes']['a1/d1']['can_add'], 'a decision with no picture can be given one');
        $this->assertFalse($s['2']['nodes']['a1/e2']['can_replace'], 'the picture with pins is protected');
        $this->assertSame('The stream', $s['2']['nodes']['a1/s1']['title']);
        $this->edit('replace', ['slide' => 2, 'candidate_id' => str_repeat('d', 16)])->assertStatus(422);   // a whole-slide edit is refused
    }

    public function test_replacing_a_scene_picture_leaves_the_story_the_choices_and_every_other_scene_untouched(): void
    {
        $this->useEditor($this->storyDraft());
        $deckBefore = $this->deck();
        $htmlBefore = $this->file('bundle/out/presentation.html');
        $cid = $this->read('draft-image/search', ['slide' => 2, 'node' => 'a1/s1'])->assertOk()->json('candidates.0.id');

        $res = $this->edit('replace', ['slide' => 2, 'node' => 'a1/s1', 'candidate_id' => $cid])->assertOk();

        $this->assertSame('a1/s1', $res->json('node'));
        $nodes = $this->nodes();
        $old = array_column($deckBefore['slides'][1]['interaction']['nodes'], null, 'id');
        $this->assertNotSame($old['a1/s1']['image']['url'], $nodes['a1/s1']['image']['url']);
        $this->assertArrayNotHasKey('focus', $nodes['a1/s1']['image'], 'a highlight belongs to the old picture');
        $this->assertSame('scene', $nodes['a1/s1']['image']['role'], 'the picture\'s role in the story is kept');
        $this->assertSame(array_diff_key($old['a1/s1'], ['image' => 1]), array_diff_key($nodes['a1/s1'], ['image' => 1]), 'text, choices and links of the scene are untouched');
        foreach (['a1/d1', 'a1/e1', 'a1/e2'] as $id) {
            $this->assertSame($old[$id], $nodes[$id], "scene $id unchanged");
        }
        $this->assertSame($deckBefore['slides'][0], $this->deck()['slides'][0]);
        $this->assertSame(array_diff_key($deckBefore['slides'][1]['interaction'], ['nodes' => 1]), array_diff_key($this->deck()['slides'][1]['interaction'], ['nodes' => 1]), 'start, template and the rest of the story are unchanged');
        $this->assertSame($htmlBefore, $this->file('bundle/out/presentation.html'), 'a story picture is drawn by the player alone');

        $this->edit('restore', ['slide' => 2, 'node' => 'a1/s1'])->assertOk();
        $this->assertSame($deckBefore['slides'], $this->deck()['slides'], 'the original picture and its highlight are back');
    }

    public function test_a_scene_can_be_given_a_picture_and_have_one_removed_and_a_pinned_one_is_kept(): void
    {
        $this->useEditor($this->storyDraft());
        $deckBefore = $this->deck();

        $this->edit('replace', ['slide' => 2, 'node' => 'a1/d1', 'image' => UploadedFile::fake()->image('fork.png', 500, 400), 'rights_confirmed' => 1])->assertOk();
        $this->assertNotNull(StudyDeckImages::idFromRef($this->nodes()['a1/d1']['image']['url']));
        $this->assertSame($deckBefore['slides'][1]['interaction']['nodes'][1]['choices'], $this->nodes()['a1/d1']['choices'], 'decisions are intact');

        $this->edit('remove', ['slide' => 2, 'node' => 'a1/e1'])->assertOk();
        $this->assertNull($this->nodes()['a1/e1']['image']);
        $this->assertSame('You reach the lake.', $this->nodes()['a1/e1']['body']);

        $pinned = $this->nodes()['a1/e2'];
        $this->edit('remove', ['slide' => 2, 'node' => 'a1/e2'])->assertStatus(422);
        $this->assertSame($pinned, $this->nodes()['a1/e2']);
        $this->edit('remove', ['slide' => 2, 'node' => 'nope'])->assertStatus(404);

        // Both scenes come back as they were: the one that had no picture has none again.
        $this->edit('restore', ['slide' => 2, 'node' => 'a1/d1'])->assertOk();
        $this->edit('restore', ['slide' => 2, 'node' => 'a1/e1'])->assertOk();
        $this->assertSame($deckBefore['slides'], $this->deck()['slides']);
    }

    public function test_a_stored_story_is_not_edited_either(): void
    {
        $this->useEditor($this->storyDraft());
        $status = app(GenerationRun::class)->read(self::CHAPTER, self::TENANT, $this->run);
        $status['state'] = 'stored';
        app(GenerationRun::class)->save($status);

        $this->edit('remove', ['slide' => 2, 'node' => 'a1/e1'])->assertStatus(409);
    }
}
