<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\TextbookImages;
use Tests\Feature\CoursePresentation\CoursePresentationTestCase;

/**
 * The picture ladder: the web first; the chapter's own textbook only when the search finds nothing suitable; a drawn diagram only
 * when the plan holds a verified spec; otherwise no picture, said plainly. A textbook picture is chosen from the manifest's
 * description of what it SHOWS, never from its file name. No database, no network.
 */
class TextbookImagesTest extends CoursePresentationTestCase
{
    use StudyDeckFixture;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/textbook-images-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** A real PNG with some detail, written into the picture folder. */
    private function picture(string $name, int $seed = 1, int $w = 420, int $h = 300): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 250, 250, 250));
        for ($i = 0; $i < $w; $i += 5) {
            imageline($im, $i, 0, $w - 1 - $i, $h - 1, imagecolorallocate($im, ($i * 7 * $seed) % 255, ($i * 3) % 255, ($i * 11) % 255));
        }
        imagepng($im, $this->dir . '/' . $name);
    }

    private function manifest(array $images): array
    {
        return ['source' => ['key' => 'evs-std4-water', 'title' => 'Level 4 Integrated Studies: Water', 'publisher' => 'Anand Niketan International'], 'images' => $images];
    }

    private function entry(string $file, int $chapter, string $shows, array $keywords, int $page = 16, bool $usable = true): array
    {
        return ['file' => $file, 'page' => $page, 'chapter_id' => $chapter, 'caption' => '', 'shows' => $shows, 'keywords' => $keywords, 'usable' => $usable];
    }

    private function planner(array $webImages, ?TextbookImages $book, int $chapter = 41676): ImagePlanner
    {
        $first = $webImages[0] ?? null;
        $planner = new ImagePlanner($this->imageSearch($first, array_slice($webImages, 1)), $this->memoryStore(), fn () => $this->detailed());

        return $planner->withTextbookImages($book, $chapter);
    }

    /** @return array{bytes:string,mime:string,width:int,height:int} */
    private function detailed(): array
    {
        $im = imagecreatetruecolor(400, 300);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        for ($i = 0; $i < 400; $i += 4) {
            imageline($im, $i, 0, 399 - $i, 299, imagecolorallocate($im, ($i * 7) % 255, ($i * 3) % 255, ($i * 11) % 255));
        }
        ob_start();
        imagepng($im);

        return ['bytes' => (string) ob_get_clean(), 'mime' => 'image/png', 'width' => 400, 'height' => 300];
    }

    private function slides(string $query = 'water strider surface tension', ?array $diagram = null): array
    {
        return [['n' => 1, 'slide_type' => 'concept_visual', 'teaches' => 'surface tension', 'visual' => ['required' => true, 'role' => 'object', 'query' => $query, 'purpose' => 'Shows an insect standing on water.']] + ($diagram ? ['diagram' => $diagram] : [])];
    }

    private function book(array $entries): TextbookImages
    {
        return new TextbookImages($this->manifest($entries), $this->dir);
    }

    public function test_a_good_web_picture_is_used_and_the_textbook_is_not(): void
    {
        $this->picture('p16_a.png');
        $book = $this->book([$this->entry('p16_a.png', 41676, 'A water strider standing on the surface of a pond', ['water strider', 'surface tension', 'insect'])]);
        $web = $this->goodImage(['title' => 'Water strider on surface tension', 'tags' => ['water', 'strider', 'surface', 'tension']]);

        $out = $this->planner([$web], $book)->plan($this->slides(), []);

        $this->assertSame(ImagePlanner::KIND_WEB, $out[1]['source_kind']);
        $this->assertNotSame('textbook', $out[1]['provider'] ?? null);
    }

    public function test_when_the_web_finds_nothing_the_textbook_picture_that_shows_the_idea_is_used_and_credited(): void
    {
        $this->picture('p16_a.png');
        $book = $this->book([$this->entry('p16_a.png', 41676, 'A water strider standing on the surface of a pond', ['water strider', 'surface tension', 'insect'])]);

        $out = $this->planner([], $book)->plan($this->slides(), []);

        $img = $out[1];
        $this->assertSame(ImagePlanner::KIND_TEXTBOOK, $img['source_kind']);
        $this->assertSame('photo', $img['type']);
        $this->assertSame(ImagePlanner::TEXTBOOK_LICENCE, $img['licence']);
        $this->assertTrue($img['attribution_required']);
        $this->assertStringContainsString('Level 4 Integrated Studies: Water', $img['attribution']);
        $this->assertStringContainsString('page 16', $img['attribution']);
        $this->assertStringStartsWith('study-deck-image:', $img['url'], 'stored once through the picture store, never hot-linked or left as a path');
        $this->assertSame('textbook:evs-std4-water#page-16', $img['source_url']);
        $this->assertTrue($img['review']['relevant']);
        $this->assertGreaterThanOrEqual(1, $img['match']['shared'], 'the deck validator needs keyword evidence');
        $this->assertNotSame('', $img['web_search'], 'why the web gave nothing is kept');
        $this->assertTrue((new ImagePlanner($this->imageSearch(null), $this->memoryStore()))->licenceAccepted($img['licence']));
    }

    public function test_a_picture_is_never_chosen_from_its_file_name(): void
    {
        // The file is NAMED after the scene, but the manifest says it shows something else.
        $this->picture('water_strider_surface_tension.png');
        $book = $this->book([$this->entry('water_strider_surface_tension.png', 41676, 'A large dam holding back a reservoir', ['dam', 'reservoir', 'hydro power'])]);

        $out = $this->planner([], $book)->plan($this->slides(), []);

        $this->assertSame(ImagePlanner::KIND_NONE, $out[1]['source_kind']);
        $this->assertArrayHasKey('missing', $out[1]);
    }

    public function test_one_shared_word_is_not_evidence(): void
    {
        $this->picture('p20.png');
        $book = $this->book([$this->entry('p20.png', 41676, 'A solar powered heating system on a roof', ['solar heater', 'roof', 'technology'])]);

        $out = $this->planner([], $book)->plan($this->slides('solar energy for light'), []);

        $this->assertSame(ImagePlanner::KIND_NONE, $out[1]['source_kind'], 'only "solar" is shared');
    }

    public function test_a_picture_of_another_chapter_or_marked_unusable_is_never_offered(): void
    {
        $this->picture('a.png');
        $this->picture('b.png', 2);
        $book = $this->book([
            $this->entry('a.png', 41675, 'A water strider standing on the surface of a pond', ['water strider', 'surface tension']),
            $this->entry('b.png', 41676, 'A water strider standing on the surface of a pond', ['water strider', 'surface tension'], 16, false),
        ]);

        $out = $this->planner([], $book, 41676)->plan($this->slides(), []);

        $this->assertSame(ImagePlanner::KIND_NONE, $out[1]['source_kind']);
    }

    public function test_the_diagram_comes_after_the_textbook_and_before_nothing(): void
    {
        $spec = ['layout' => 'flow', 'title' => 'How surface tension holds an insect', 'nodes' => ['Water molecules pull together', 'A thin skin forms on top', 'A light insect stays on the skin']];
        $this->picture('p16_a.png');
        $book = $this->book([$this->entry('p16_a.png', 41676, 'A water strider standing on the surface of a pond', ['water strider', 'surface tension'])]);

        // With a textbook picture available, it is used before the drawn diagram.
        $this->assertSame(ImagePlanner::KIND_TEXTBOOK, $this->planner([], $book)->plan($this->slides(diagram: $spec), [])[1]['source_kind']);
        // With none, the plan's own verified diagram spec is drawn.
        $drawn = $this->planner([], null)->plan($this->slides(diagram: $spec), [])[1];
        $this->assertSame(ImagePlanner::KIND_DIAGRAM, $drawn['source_kind'] ?? $drawn['type']);
        // With neither, the slide has no picture and says so.
        $none = $this->planner([], null)->plan($this->slides(), [])[1];
        $this->assertSame(ImagePlanner::KIND_NONE, $none['source_kind']);
        $this->assertNotSame('', (string) $none['missing']);
    }

    public function test_a_picture_is_not_used_twice_in_one_lesson(): void
    {
        $this->picture('p16_a.png');
        $book = $this->book([$this->entry('p16_a.png', 41676, 'A water strider standing on the surface of a pond', ['water strider', 'surface tension'])]);
        $planner = $this->planner([], $book);
        $slides = [
            ['n' => 1, 'slide_type' => 'concept_visual', 'visual' => ['required' => true, 'role' => 'object', 'query' => 'water strider surface tension', 'purpose' => 'Insect on water.']],
            ['n' => 2, 'slide_type' => 'concept_visual', 'visual' => ['required' => true, 'role' => 'object', 'query' => 'water strider surface tension', 'purpose' => 'Insect on water again.']],
        ];

        $out = $planner->plan($slides, []);

        $this->assertSame('textbook', $out[1]['source_kind']);
        $this->assertSame('none', $out[2]['source_kind'], 'the same textbook picture is not repeated');
    }

    public function test_the_pixel_check_can_refuse_a_textbook_picture(): void
    {
        $this->picture('p16_a.png');
        $book = $this->book([$this->entry('p16_a.png', 41676, 'A water strider standing on the surface of a pond', ['water strider', 'surface tension'])]);
        $planner = $this->planner([], $book)->withPixelJudge(fn () => ['ok' => false, 'reason' => 'shows a dam']);

        $this->assertSame('none', $planner->plan($this->slides(), [])[1]['source_kind']);
    }

    public function test_the_manifest_can_never_name_a_file_outside_its_folder(): void
    {
        file_put_contents(dirname($this->dir) . '/outside.png', $this->detailed()['bytes']);
        $book = $this->book([]);
        foreach (['../outside.png', '..\\outside.png', '/etc/passwd', 'sub/x.png', ''] as $name) {
            $this->assertNull($book->read(['file' => $name]), "'$name'");
        }
        @unlink(dirname($this->dir) . '/outside.png');
    }

    public function test_an_unreadable_or_tiny_picture_is_skipped(): void
    {
        file_put_contents($this->dir . '/broken.png', 'not an image');
        $this->picture('tiny.png', 1, 40, 40);
        $book = $this->book([
            $this->entry('broken.png', 41676, 'A water strider standing on the surface of a pond', ['water strider', 'surface tension']),
            $this->entry('tiny.png', 41676, 'A water strider standing on the surface of a pond', ['water strider', 'surface tension']),
        ]);

        $this->assertSame('none', $this->planner([], $book)->plan($this->slides(), [])[1]['source_kind']);
    }

    public function test_without_configuration_the_feature_is_off(): void
    {
        config(['course_presentation_pilot.textbook_images' => ['manifest' => '/nowhere/x.json', 'directory' => '']]);
        $this->assertNull(TextbookImages::fromConfig());
    }
    public function test_a_precise_subject_that_the_books_own_caption_names_is_enough(): void
    {
        $this->picture('p37.png');
        $entry = $this->entry('p37.png', 41678, 'Fishermen lifting a big net full of fish', ['overfishing', 'fishing net']);
        $entry['caption'] = 'Overfishing';

        // A narrow scene (one specific word in all) that the picture's own description names completely.
        $narrow = [['n' => 1, 'slide_type' => 'concept_visual', 'visual' => ['required' => true, 'role' => 'object', 'query' => 'overfishing', 'purpose' => 'Overfishing']]];
        $out = $this->planner([], $this->book([$entry]), 41678)->plan($narrow, []);

        $this->assertSame('textbook', $out[1]['source_kind']);

        // The same word inside a WIDER scene is not enough: one shared word is not evidence.
        $wide = [['n' => 1, 'slide_type' => 'concept_visual', 'teaches' => 'how fishing changes fish numbers in rivers and seas', 'visual' => ['required' => true, 'role' => 'object', 'query' => 'overfishing', 'purpose' => 'Shows the effect of fishing on populations.']]];
        $this->assertSame('none', $this->planner([], $this->book([$entry]), 41678)->plan($wide, [])[1]['source_kind']);
    }

    public function test_the_shipped_manifest_is_sound(): void
    {
        $manifest = json_decode((string) file_get_contents(base_path('content_bundles/textbook-images/evs-std4-water.json')), true);
        $this->assertIsArray($manifest, 'valid JSON');
        $chapters = array_map('intval', array_keys($manifest['source']['chapters']));
        $this->assertSame([41675, 41676, 41677, 41678], $chapters);

        $files = [];
        foreach ($manifest['images'] as $e) {
            $this->assertContains((int) $e['chapter_id'], $chapters, $e['file']);
            $this->assertNotContains($e['file'], $files, 'listed once');
            $files[] = $e['file'];
            $this->assertSame($e['file'], basename($e['file']), 'a plain file name');
            $this->assertMatchesRegularExpression('/^page_\d{2}_image_\d{2}\.png$/', $e['file']);
            // The picture is on a page of the chapter it is filed under.
            [$from, $to] = $manifest['source']['chapters'][(string) $e['chapter_id']]['pages'];
            $this->assertGreaterThanOrEqual($from, $e['page'], $e['file']);
            $this->assertLessThanOrEqual($to, $e['page'], $e['file']);
            $this->assertSame(sprintf('page_%02d_', $e['page']), substr($e['file'], 0, 8), 'the page matches the file it came from');
            // What it shows is a description of the picture, and it is searchable.
            $this->assertGreaterThan(30, mb_strlen($e['shows']), $e['file']);
            $this->assertNotEmpty($e['keywords'], $e['file']);
            $this->assertTrue($e['usable']);
        }
        $this->assertGreaterThanOrEqual(40, count($files));
        // Every chapter has pictures to offer.
        $byChapter = array_count_values(array_column($manifest['images'], 'chapter_id'));
        foreach ($chapters as $c) {
            $this->assertGreaterThanOrEqual(5, $byChapter[$c] ?? 0, "chapter $c");
        }
    }
    public function test_a_picture_matched_on_the_purpose_still_carries_keyword_evidence_for_the_deck_validator(): void
    {
        $this->picture('p21.png');
        $book = $this->book([$this->entry('p21.png', 41676, 'A cut-away diagram of a steam locomotive with piston and cylinder', ['steam engine', 'piston', 'cylinder'], 21)]);
        // The query shares nothing with the picture; the purpose and what the slide teaches do.
        $slides = [['n' => 1, 'slide_type' => 'concept_visual', 'teaches' => 'piston and cylinder of a steam engine', 'visual' => ['required' => true, 'role' => 'object', 'query' => 'machine parts', 'purpose' => 'Shows how the steam engine moves its piston.']]];

        $img = $this->planner([], $book)->plan($slides, [])[1];

        $this->assertSame('textbook', $img['source_kind']);
        $this->assertGreaterThanOrEqual(1, $img['match']['shared'], 'the deck validator rejects a photograph with no keyword evidence');
    }
}
