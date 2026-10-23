<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\CoursePresentation\Templates\TextbookPicturePool;
use App\Services\StudyDeck\TextbookImages;
use Illuminate\Support\Facades\DB;

/**
 * The source-data preview: pictures only from the chapter's own textbook and only where one shows the concept, carried inside the preview
 * (nothing fetched, nothing stored), a plain statement where a concept has none, and an honest label that the preview does not change with
 * the teaching method. No database writes, no network.
 */
class PreviewPicturesTest extends CoursePresentationTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/preview-pics-' . bin2hex(random_bytes(4));
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

    private function picture(string $name, int $w = 1400, int $h = 900): void
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 240, 240, 240));
        for ($i = 0; $i < $w; $i += 7) {
            imageline($im, $i, 0, $w - 1 - $i, $h - 1, imagecolorallocate($im, ($i * 5) % 255, ($i * 9) % 255, ($i * 3) % 255));
        }
        imagepng($im, $this->dir . '/' . $name);
    }

    private function entry(string $file, string $caption, string $shows, array $keywords, int $chapter = self::CHAPTER): array
    {
        return ['file' => $file, 'page' => 7, 'chapter_id' => $chapter, 'caption' => $caption, 'shows' => $shows, 'keywords' => $keywords, 'usable' => true];
    }

    private function book(array $entries): TextbookImages
    {
        return new TextbookImages(['source' => ['key' => 'book', 'title' => 'The Textbook', 'publisher' => 'A Publisher'], 'images' => $entries], $this->dir);
    }

    private function concepts(array $names): array
    {
        $out = [];
        foreach ($names as $i => $n) {
            $out[100 + $i] = ['id' => 100 + $i, 'name' => $n, 'topic' => 'Some topic'];
        }

        return $out;
    }

    private function pictureCount(array $deck): int
    {
        return count(array_filter($deck['slides'], fn ($s) => !empty($s['image']['url'])));
    }

    public function test_a_picture_is_offered_only_for_the_concept_it_shows_and_is_carried_inside_the_preview(): void
    {
        $this->picture('a.png');
        $pool = (new TextbookPicturePool($this->book([$this->entry('a.png', 'An atom', 'A drawing of an atom with a nucleus and electrons', ['atom', 'nucleus', 'electrons'])])))
            ->forChapter(self::CHAPTER, $this->concepts(['Atom', 'Mole', 'Bond']));

        $this->assertSame([100], array_keys($pool['by_concept']));
        $this->assertSame(['Mole', 'Bond'], $pool['missing']);
        $img = $pool['by_concept'][100];
        $this->assertSame('textbook', $img['source_kind']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $img['url'], 'nothing is stored: the preview carries the picture itself');
        $decoded = imagecreatefromstring((string) base64_decode(substr($img['url'], strlen('data:image/jpeg;base64,'))));
        $this->assertNotFalse($decoded, 'the data address really is a picture');
        $this->assertLessThanOrEqual(900, max(imagesx($decoded), imagesy($decoded)), 'shrunk for a preview');
        $this->assertStringContainsString('page 7', $img['attribution']);
    }

    public function test_a_picture_serves_at_most_two_concepts_and_a_wider_scene_does_not_borrow_it(): void
    {
        $this->picture('a.png');
        $book = $this->book([$this->entry('a.png', 'An atom', 'A drawing of an atom', ['atom'])]);
        $three = (new TextbookPicturePool($book))->forChapter(self::CHAPTER, $this->concepts(['Atom', 'Atom', 'Atom']));

        $this->assertCount(TextbookPicturePool::MAX_USES, $three['by_concept']);
        $this->assertCount(1, $three['missing']);

        // "Atom bonding in salts" is a wider scene: one shared word is not evidence.
        $wide = (new TextbookPicturePool($book))->forChapter(self::CHAPTER, $this->concepts(['Atom bonding in salts']));
        $this->assertSame([], $wide['by_concept']);
    }

    public function test_with_no_textbook_configured_every_concept_is_reported_without_a_picture(): void
    {
        config(['course_presentation_pilot.textbook_images' => ['manifest' => '/nowhere.json', 'directory' => '']]);
        $pool = (new TextbookPicturePool())->forChapter(self::CHAPTER, $this->concepts(['Atom', 'Mole']));

        $this->assertSame([], $pool['by_concept']);
        $this->assertSame(['Atom', 'Mole'], $pool['missing']);
    }

    public function test_the_preview_endpoint_places_the_picture_states_the_gap_and_says_it_is_the_same_for_every_method(): void
    {
        $this->seedPilotChapter();
        $this->picture('a.png');
        file_put_contents($this->dir . '/manifest.json', json_encode(['source' => ['key' => 'book', 'title' => 'The Textbook', 'publisher' => 'A Publisher'], 'images' => [$this->entry('a.png', 'An atom', 'A drawing of an atom with a nucleus and electrons', ['atom', 'nucleus'])]]));
        config(['course_presentation_pilot.textbook_images' => ['manifest' => $this->dir . '/manifest.json', 'directory' => $this->dir]]);
        $rec = fn (string ...$n) => array_map(fn ($x) => ['strategy' => $x, 'why_effective' => 'x'], $n);
        DB::table('semantic_intelligence')->where('id', 13)->update(['full_intelegance_json' => json_encode(['concepts' => array_map(fn ($c) => ['concept' => ['concept_name' => $c], 'pedagogy_recommendations' => $rec('Activity Based Teaching', 'Inquiry Based Teaching')], ['Atom', 'Ion', 'Mole', 'Bond'])])]);

        $decks = [];
        foreach (['inquiry_based', 'experiential'] as $method) {
            $res = $this->asTeacher()->getJson('/api/lms/course-presentation/preview?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => $method, 'format' => 'interactive_slides']))->assertOk()->json();
            $this->assertFalse($res['method_specific']);
            $this->assertStringContainsString('same lesson text for every teaching method', $res['notice']);
            $this->assertSame(1, $res['pictures']['from_book_pictures']);
            $this->assertContains('Ion', $res['pictures']['concepts_without_picture']);
            $decks[$method] = $res['deck'];
        }
        // The preview is the same for both methods apart from slide order: same pictures, same gaps.
        $this->assertSame($this->pictureCount($decks['inquiry_based']), $this->pictureCount($decks['experiential']));
    }
}
