<?php

namespace Tests\Unit\StudyDeck;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\StudyDeckPublisher;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Publishing a study deck: pictures into the shared store, one content_master row, one deck file.
 *
 * NO DATABASE and NO real object store. phpunit.xml points at the live shared vivek_erp, so the database
 * calls are the service's own overridable seams (find / insert / hide previous), recorded here, and the object
 * store is Storage::fake('digitalocean'). What is tested is what the code decides to store, in which order, and
 * what it removes when something fails.
 */
class StudyDeckPublishTest extends TestCase
{
    private const BASE = 'https://s3-triz.fra1.digitaloceanspaces.com/';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('digitalocean');
        $this->dir = sys_get_temp_dir() . '/study-deck-publish-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/images', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/images/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir . '/images');
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** A real PNG of a given colour, written into the bundle; returns [relative path, sha1]. */
    private function picture(int $shade): array
    {
        $im = imagecreatetruecolor(32, 18);
        imagefill($im, 0, 0, imagecolorallocate($im, $shade, 80, 160));
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        $sha = sha1($bytes);
        file_put_contents($this->dir . "/images/$sha.png", $bytes);

        return ["images/$sha.png", $sha, strlen($bytes)];
    }

    /** @return array{0:array<string,mixed>,1:string} deck and presentation html with two pictures (one used twice) */
    private function bundle(array $over = []): array
    {
        [$a, $shaA] = $this->picture(10);
        [$b, $shaB] = $this->picture(200);
        $image = fn (string $url, string $sha, string $alt) => ['type' => 'diagram', 'url' => $url, 'sha1' => $sha, 'alt' => $alt, 'width' => 32, 'height' => 18, 'caption' => 'x', 'licence' => null, 'source_url' => null, 'creator' => null, 'attribution' => '', 'attribution_required' => false];
        $deck = [
            'version' => 3,
            'chapter' => ['id' => 8592, 'name' => 'Exploration: Entering the World of Secondary Science', 'standard_id' => 42, 'subject_id' => 3975],
            'slide_count' => 3,
            'slides' => [
                ['n' => 1, 'title' => 'Cover', 'image' => null],
                ['n' => 2, 'title' => 'One', 'image' => $image($a, $shaA, 'A diagram of one')],
                ['n' => 3, 'title' => 'Two', 'image' => $image($b, $shaB, 'A diagram of two') + ['dummy' => 1]],
            ],
        ];
        $html = '<section class="slide"><figure><img src="' . $a . '" alt="A diagram of one"></figure></section>'
            . '<section class="slide"><figure><img src="' . $b . '" alt="A diagram of two"></figure></section>'
            . '<section class="slide"><figure><img src="' . $a . '" alt="again"></figure></section>';

        return [array_replace_recursive($deck, $over), $html];
    }

    private function service(?StudyDeckPublisher $publisher = null): object
    {
        $publisher ??= new StudyDeckPublisher(Storage::disk('digitalocean'), self::BASE);

        return new class($publisher) extends ContentGenerationService {
            public array $inserted = [];

            public array $lookups = [];

            public array $hidden = [];

            public array $restored = [];

            public array $filesAtInsert = [];

            public ?object $existing = null;

            public bool $failInsert = false;

            public int $pdfRenders = 0;

            public bool $failPdf = false;

            public array $pdfDeck = [];

            public function __construct(private readonly StudyDeckPublisher $publisher)
            {
            }

            protected function studyDeckPublisher(): StudyDeckPublisher
            {
                return $this->publisher;
            }

            protected function findStudyDeckRow(int $chapterId, int $tenant, string $filename): ?object
            {
                $this->lookups[] = [$chapterId, $tenant, $filename];

                return $this->existing;
            }

            protected function insertContentRow(array $content, ?callable $inTransaction = null): int|string
            {
                $this->filesAtInsert = Storage::disk('digitalocean')->allFiles();
                if ($this->failInsert) {
                    throw new \RuntimeException('the database went away');
                }
                $this->inserted[] = $content;
                if ($inTransaction) {
                    $inTransaction(777);
                }

                return 777;
            }

            protected function hidePreviousStudyDecks(int $chapterId, int $tenant, int $keepId): int
            {
                $this->hidden[] = [$chapterId, $tenant, $keepId];

                return 1;
            }

            protected function restoreStudyDeckRow(int $id, int $chapterId, int $tenant): bool
            {
                $this->restored[] = [$id, $chapterId, $tenant];

                return true;
            }

            protected function renderStudyDeckPdf(array $deck): string
            {
                $this->pdfRenders++;
                $this->pdfDeck = $deck;
                if ($this->failPdf) {
                    throw new \RuntimeException('dompdf fell over');
                }

                return '%PDF-1.4 fake';
            }

            protected function resolvePresentationRenderer(bool $isPresentation): callable
            {
                return fn () => 'PPTX-BYTES';
            }
        };
    }

    private function input(array $over = []): array
    {
        $chapter = (object) ['id' => 8592, 'standard_id' => 42, 'subject_id' => 3975];

        return $over + [
            'chapter' => $chapter, 'chapter_name' => 'Exploration: Entering the World of Secondary Science', 'grade_id' => 12,
            'concept_id' => null, 'sub_institute_id' => 1, 'syear' => 2026, 'created_by' => 1, 'user_profile_name' => 'ADMIN',
        ];
    }

    private function files(): array
    {
        return Storage::disk('digitalocean')->allFiles();
    }

    // ---------------------------------------------------------------------------------------------------------
    // A. Images

    public function test_each_picture_is_stored_once_under_its_hash_and_the_deck_points_at_it(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();

        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(201, $r['http'], json_encode($r['body']));
        $images = array_filter($this->files(), fn ($f) => str_contains($f, '/studydeck/'));
        $this->assertCount(2, $images, 'the picture used twice is stored once');

        $sidecar = json_decode((string) Storage::disk('digitalocean')->get(ContentGenerationService::studyDeckSidecarPath($r['body']['data']['filename'])), true);
        foreach ([1, 2] as $i) {
            $image = $sidecar['slides'][$i]['image'];
            $this->assertStringStartsWith(self::BASE . 'public/lms_content_file/studydeck/', $image['url']);
            $this->assertArrayHasKey($image['asset_id'], $sidecar['assets'], 'the picture is referenced by its asset id');
            $asset = $sidecar['assets'][$image['asset_id']];
            $this->assertSame($image['url'], $asset['url']);
            $this->assertSame('image/png', $asset['mime']);
            $this->assertSame($asset['sha1'] . '.png', $asset['filename']);
            $this->assertSame('public/lms_content_file/studydeck/' . $asset['filename'], $asset['path']);
            $this->assertGreaterThan(0, $asset['bytes']);
            $this->assertSame($asset['bytes'], Storage::disk('digitalocean')->size($asset['path']));
            $this->assertNotEmpty($image['alt'], 'the alt text travels with the picture');
        }
    }

    public function test_the_stored_deck_and_presentation_refer_to_no_local_path(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();

        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);
        $json = (string) Storage::disk('digitalocean')->get(ContentGenerationService::studyDeckSidecarPath($r['body']['data']['filename']));

        $this->assertDoesNotMatchRegularExpression('~"url":\s*"images/~', $json);
        $this->assertStringNotContainsString(str_replace('\\', '/', $this->dir), $json);
        $this->assertStringNotContainsString('study-deck/chapter-', $json);
        $this->assertStringNotContainsString('localhost', $json);
        $this->assertStringNotContainsString('src="images/', $svc->inserted[0]['description']);
        $this->assertStringContainsString('src="' . self::BASE . 'public/lms_content_file/studydeck/', $svc->inserted[0]['description']);
    }

    public function test_publishing_again_reuses_every_picture_and_stores_nothing_new(): void
    {
        [$deck, $html] = $this->bundle();
        $this->service()->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);
        $before = $this->files();

        $svc = $this->service();
        $svc->existing = (object) ['id' => 777, 'url' => 'https://example/x.pptx', 'show_hide' => 1];
        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame('unchanged', $r['body']['status']);
        $this->assertSame([], $r['body']['images_uploaded']);
        $this->assertCount(2, $r['body']['images_reused']);
        $this->assertSame($before, $this->files());
        $this->assertSame([], $svc->inserted, 'no second row');
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();

        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', true);

        $this->assertSame(200, $r['http']);
        $this->assertSame('would_create', $r['body']['status']);
        $this->assertCount(2, $r['body']['images_uploaded'], 'it says what it would upload');
        $this->assertSame([], $this->files());
        $this->assertSame([], $svc->inserted);
    }

    public function test_a_picture_that_does_not_match_its_recorded_hash_is_refused_and_nothing_is_stored(): void
    {
        [$deck, $html] = $this->bundle();
        $deck['slides'][1]['image']['sha1'] = str_repeat('a', 40);
        $svc = $this->service();

        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(422, $r['http']);
        $this->assertStringContainsString('does not match the hash', $r['body']['message']);
        $this->assertSame([], $this->files());
        $this->assertSame([], $svc->inserted);
    }

    public function test_a_missing_picture_file_stops_the_publish_and_removes_what_was_already_stored(): void
    {
        [$deck, $html] = $this->bundle();
        unlink($this->dir . '/' . $deck['slides'][2]['image']['url']);
        $svc = $this->service();

        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(422, $r['http']);
        $this->assertStringContainsString('missing or empty', $r['body']['message']);
        $this->assertSame([], $this->files(), 'the first picture was stored, then removed again');
        $this->assertSame([], $svc->inserted);
    }

    public function test_a_picture_that_is_not_an_image_is_refused(): void
    {
        [$deck, $html] = $this->bundle();
        $sha = $deck['slides'][1]['image']['sha1'];
        file_put_contents($this->dir . "/images/$sha.png", 'not a picture');
        $deck['slides'][1]['image']['sha1'] = sha1('not a picture');

        $r = $this->service()->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(422, $r['http']);
        $this->assertStringContainsString('not a PNG, JPEG or WebP', $r['body']['message']);
    }

    public function test_pictures_that_live_somewhere_else_are_refused(): void
    {
        foreach (['http://localhost:3000/study-deck/chapter-8592/images/a.png', 'C:\\Users\\dev\\a.png', '/public/study-deck/a.png', 'https://example.org/a.png', '../a.png'] as $bad) {
            [$deck, $html] = $this->bundle();
            $deck['slides'][1]['image']['url'] = $bad;

            $r = $this->service()->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

            $this->assertSame(422, $r['http'], $bad);
            $this->assertSame([], $this->files(), $bad);
        }
    }

    public function test_a_stored_picture_that_is_not_really_there_is_caught(): void
    {
        [$deck, $html] = $this->bundle();
        $gone = self::BASE . 'public/lms_content_file/studydeck/' . str_repeat('b', 40) . '.png';
        // The presentation says the same as the deck, as it does in a real bundle.
        $html = str_replace($deck['slides'][1]['image']['url'], $gone, $html);
        $deck['slides'][1]['image']['url'] = $gone;

        $r = $this->service()->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(422, $r['http']);
        $this->assertSame([], $this->files(), 'the other picture that was uploaded is removed again');
        $this->assertStringContainsString('not in the shared store', $r['body']['message']);
    }

    public function test_a_local_path_hidden_in_the_deck_text_is_caught(): void
    {
        [$deck, $html] = $this->bundle();
        $deck['slides'][1]['title'] = 'See C:\\Users\\dev\\notes.txt';

        $r = $this->service()->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(422, $r['http']);
        $this->assertStringContainsString('local path or host', $r['body']['message']);
    }

    public function test_an_https_address_in_the_text_is_not_mistaken_for_a_local_path(): void
    {
        [$deck, $html] = $this->bundle();
        $deck['slides'][1]['title'] = 'Read more at https://example.org/a/b and http://example.org/c';

        $r = $this->service()->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(201, $r['http'], json_encode($r['body']));
    }

    // ---------------------------------------------------------------------------------------------------------
    // B. The content_master row

    public function test_the_row_follows_the_columns_every_other_generated_presentation_uses(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();

        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame('created', $r['body']['status']);
        $this->assertSame(777, $r['body']['content_id']);
        $row = $svc->inserted[0];
        $this->assertSame(12, $row['grade_id']);
        $this->assertSame(42, $row['standard_id']);
        $this->assertSame(3975, $row['subject_id']);
        $this->assertSame(8592, $row['chapter_id']);
        $this->assertSame(1, $row['sub_institute_id']);
        $this->assertSame('Classroom Presentation', $row['content_category']);
        $this->assertSame('Claude AI', $row['source']);
        $this->assertSame('pptx', $row['file_type']);
        $this->assertSame('1', $row['show_hide']);
        $this->assertNull($row['meta_tags'], 'the concept tagger reads this as text');
        $this->assertNull($row['topic_id']);
        $this->assertNull($row['concept_id']);
        $this->assertSame('/lms_content_file', $row['file_folder']);
        $this->assertSame('Exploration: Entering the World of Secondary Science Study Deck', $row['title']);
        $this->assertMatchesRegularExpression('/^study_deck_exploration_entering_the_world_of_secondary_science_[0-9a-f]{12}\.pptx$/', $row['filename']);
        $this->assertSame(self::fileUrl($row['filename']), $row['url'], 'the url the app already opens for a presentation row');
        $this->assertSame(strlen('PPTX-BYTES'), $row['file_size']);
        $this->assertArrayNotHasKey('content_type', $row, 'columns that do not exist on content_master stay out');
    }

    private static function fileUrl(string $filename): string
    {
        return Storage::disk('digitalocean')->url('public/lms_content_file/' . $filename);
    }

    public function test_the_files_are_stored_and_read_back_before_the_row_is_inserted(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();

        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $filename = $r['body']['data']['filename'];
        $this->assertContains('public/lms_content_file/' . $filename, $svc->filesAtInsert, 'the presentation is already stored');
        $this->assertContains(ContentGenerationService::studyDeckSidecarPath($filename), $svc->filesAtInsert, 'and so is the deck file');
        $this->assertContains(ContentGenerationService::studyDeckPdfPath($filename), $svc->filesAtInsert, 'and the PDF');
        $this->assertCount(5, $svc->filesAtInsert, 'two pictures, the presentation, the deck and the PDF');
    }

    public function test_the_tenant_is_part_of_the_lookup_and_the_row(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();

        $svc->publishStudyDeck($this->input(['sub_institute_id' => 7]), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(7, $svc->lookups[0][1], 'another school never finds this school\'s deck as its own');
        $this->assertSame(7, $svc->inserted[0]['sub_institute_id']);
    }

    // ---------------------------------------------------------------------------------------------------------
    // C. Idempotency and versions

    public function test_the_same_deck_has_the_same_name_and_a_changed_deck_does_not(): void
    {
        [$deck] = $this->bundle();
        $name = StudyDeckPublisher::filenameFor('Chapter one', $deck);

        $this->assertSame($name, StudyDeckPublisher::filenameFor('Chapter one', $deck));
        $deck['slides'][1]['title'] = 'Changed';
        $this->assertNotSame($name, StudyDeckPublisher::filenameFor('Chapter one', $deck));
    }

    public function test_storing_the_same_deck_twice_creates_one_row(): void
    {
        [$deck, $html] = $this->bundle();
        $first = $this->service();
        $first->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $second = $this->service();
        $second->existing = (object) ['id' => 777, 'url' => 'u', 'show_hide' => 1];
        $r = $second->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame($first->inserted[0]['filename'], $second->lookups[0][2], 'it looked for the very same name');
        $this->assertSame([], $second->inserted);
        $this->assertSame('unchanged', $r['body']['status']);
        $this->assertSame(777, $r['body']['content_id']);
    }

    public function test_a_stored_deck_whose_deck_file_went_missing_is_repaired_not_duplicated(): void
    {
        [$deck, $html] = $this->bundle();
        $first = $this->service();
        $r = $first->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);
        $sidecar = ContentGenerationService::studyDeckSidecarPath($r['body']['data']['filename']);
        Storage::disk('digitalocean')->delete($sidecar);

        $second = $this->service();
        $second->existing = (object) ['id' => 777, 'url' => 'u', 'show_hide' => 1];
        $r2 = $second->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame('repaired', $r2['body']['status']);
        $this->assertTrue(Storage::disk('digitalocean')->exists($sidecar));
        $this->assertSame([], $second->inserted);
    }

    public function test_an_older_deck_that_was_hidden_is_restored_not_inserted_again(): void
    {
        [$deck, $html] = $this->bundle();
        $this->service()->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $svc = $this->service();
        $svc->existing = (object) ['id' => 55, 'url' => 'u', 'show_hide' => 0];
        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame('repaired', $r['body']['status']);
        $this->assertSame([[55, 8592, 1]], $svc->restored);
        $this->assertSame([], $svc->inserted);
    }

    public function test_a_new_version_hides_the_previous_study_decks_in_the_same_transaction(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();

        $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame([[8592, 1, 777]], $svc->hidden, 'every other study deck of this chapter and school, keeping the new one');
    }

    // ---------------------------------------------------------------------------------------------------------
    // D. Failure safety

    public function test_when_the_row_cannot_be_inserted_every_file_this_run_stored_is_removed(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();
        $svc->failInsert = true;

        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(500, $r['http']);
        $this->assertStringContainsString('the database went away', $r['body']['message']);
        $this->assertSame([], $this->files(), 'no presentation, no deck file, and no picture left behind');
    }

    public function test_pictures_that_were_already_stored_by_another_deck_survive_a_failed_publish(): void
    {
        [$deck, $html] = $this->bundle();
        $this->service()->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);
        $pictures = array_values(array_filter($this->files(), fn ($f) => str_contains($f, '/studydeck/')));
        $this->assertCount(2, $pictures);

        $deck['slides'][1]['title'] = 'A changed deck';
        $svc = $this->service();
        $svc->failInsert = true;
        $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertEqualsCanonicalizing($pictures, array_values(array_filter($this->files(), fn ($f) => str_contains($f, '/studydeck/'))), 'only what the failed run created is removed; the first deck\'s pictures stay');
    }

    // ---------------------------------------------------------------------------------------------------------
    // E. How the list opens it

    public function test_a_study_deck_row_opens_the_interactive_player_and_other_rows_do_not(): void
    {
        $deck = ['id' => 61900, 'chapter_id' => 8592, 'filename' => 'study_deck_exploration_ab12cd34ef56.pptx'];

        $this->assertSame('/student/study-deck/8592?content=61900', ContentGenerationService::studyDeckDeepLink($deck));
        $this->assertNull(ContentGenerationService::studyDeckDeepLink(['id' => 61082, 'chapter_id' => 8592, 'filename' => 'classroom_presentation_exploration_1790256443.pptx']));
        $this->assertNull(ContentGenerationService::studyDeckDeepLink(['id' => 1, 'chapter_id' => 8592, 'filename' => 'study_deck_x.pdf']), 'only the presentation file');
        $this->assertNull(ContentGenerationService::studyDeckDeepLink(['id' => 1, 'chapter_id' => null, 'filename' => 'study_deck_x.pptx']));
        $this->assertNull(ContentGenerationService::studyDeckDeepLink(['id' => 1, 'chapter_id' => 8592, 'filename' => 'https://gamma.app/docs/x']));
    }

    // ---------------------------------------------------------------------------------------------------------
    // F. The classroom PDF: a second file of the SAME content item

    public function test_the_pdf_is_stored_beside_the_presentation_and_the_row_keeps_pointing_at_the_pptx(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();

        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $filename = $r['body']['data']['filename'];
        $pdfPath = ContentGenerationService::studyDeckPdfPath($filename);
        $this->assertSame('public/lms_content_file/' . substr($filename, 0, -5) . '.pdf', $pdfPath, 'same name, .pdf');
        $this->assertSame('%PDF-1.4 fake', Storage::disk('digitalocean')->get($pdfPath));
        $this->assertSame($pdfPath, $r['body']['data']['pdf_path']);
        $this->assertStringEndsWith(substr($pdfPath, strlen('public/')), $r['body']['data']['pdf_url']);

        $this->assertCount(1, $svc->inserted, 'one content item, not one per format');
        $row = $svc->inserted[0];
        $this->assertSame('pptx', $row['file_type'], 'the PPTX reference is untouched');
        $this->assertSame($filename, $row['filename']);
        $this->assertStringEndsWith('.pptx', $row['url']);
        $this->assertSame(strlen('PPTX-BYTES'), $row['file_size']);
        $this->assertSame(1, $svc->pdfRenders);
    }

    public function test_the_pdf_is_drawn_from_the_deck_that_is_stored_with_its_pictures_on_the_shared_store(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();

        $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $stored = json_decode((string) Storage::disk('digitalocean')->get(ContentGenerationService::studyDeckSidecarPath($svc->inserted[0]['filename'])), true);
        $this->assertSame($stored, $svc->pdfDeck, 'the PDF says what the interactive deck says');
        $this->assertStringContainsString(self::BASE . 'public/lms_content_file/studydeck/', (string) $svc->pdfDeck['slides'][1]['image']['url']);
    }

    public function test_a_pdf_that_cannot_be_made_stops_the_publish_and_leaves_nothing(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();
        $svc->failPdf = true;

        $r = $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(500, $r['http']);
        $this->assertStringContainsString('PDF could not be made', $r['body']['message']);
        $this->assertSame([], $this->files(), 'the pictures stored for this run are removed too');
        $this->assertSame([], $svc->inserted);
    }

    public function test_when_the_row_cannot_be_inserted_the_pdf_is_removed_with_the_rest(): void
    {
        [$deck, $html] = $this->bundle();
        $svc = $this->service();
        $svc->failInsert = true;

        $svc->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame([], array_values(array_filter($this->files(), fn ($f) => str_ends_with($f, '.pdf'))), 'no orphan PDF');
    }

    public function test_storing_the_same_deck_again_does_not_render_or_write_another_pdf(): void
    {
        [$deck, $html] = $this->bundle();
        $first = $this->service();
        $first->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);
        $before = $this->files();

        $second = $this->service();
        $second->existing = (object) ['id' => 777, 'url' => 'u', 'show_hide' => 1];
        $r = $second->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame('unchanged', $r['body']['status']);
        $this->assertSame(0, $second->pdfRenders, 'a valid PDF is reused');
        $this->assertSame($before, $this->files());
        $this->assertCount(1, array_filter($this->files(), fn ($f) => str_ends_with($f, '.pdf')));
    }

    public function test_a_deck_stored_before_the_pdf_existed_gets_it_under_the_same_row(): void
    {
        [$deck, $html] = $this->bundle();
        $first = $this->service();
        $r = $first->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);
        Storage::disk('digitalocean')->delete(ContentGenerationService::studyDeckPdfPath($r['body']['data']['filename']));

        $second = $this->service();
        $second->existing = (object) ['id' => 777, 'url' => 'u', 'show_hide' => 1];
        $r2 = $second->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertSame('repaired', $r2['body']['status']);
        $this->assertSame(777, $r2['body']['content_id']);
        $this->assertSame([], $second->inserted, 'no second content item');
        $this->assertSame(1, $second->pdfRenders);
        $this->assertTrue(Storage::disk('digitalocean')->exists(ContentGenerationService::studyDeckPdfPath($r['body']['data']['filename'])));
    }

    public function test_a_changed_deck_gets_its_own_pdf_beside_its_own_row(): void
    {
        [$deck, $html] = $this->bundle();
        $first = $this->service();
        $r1 = $first->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $deck['slides'][1]['title'] = 'A changed deck';
        $second = $this->service();
        $r2 = $second->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);

        $this->assertNotSame($r1['body']['data']['pdf_path'], $r2['body']['data']['pdf_path']);
        $this->assertCount(2, array_filter($this->files(), fn ($f) => str_ends_with($f, '.pdf')));
    }

    public function test_the_pdf_url_is_offered_only_for_a_study_deck_whose_pdf_is_really_stored(): void
    {
        $name = 'study_deck_exploration_ab12cd34ef56.pptx';
        $row = ['id' => 1, 'chapter_id' => 8592, 'filename' => $name];

        $this->assertNull(ContentGenerationService::studyDeckPdfUrl($row), 'nothing stored yet');

        Storage::disk('digitalocean')->put(ContentGenerationService::studyDeckPdfPath($name), '%PDF');
        $url = ContentGenerationService::studyDeckPdfUrl($row);
        $this->assertNotNull($url);
        $this->assertStringEndsWith('lms_content_file/study_deck_exploration_ab12cd34ef56.pdf', $url);

        Storage::disk('digitalocean')->put('public/lms_content_file/classroom_presentation_x_1.pdf', '%PDF');
        $this->assertNull(ContentGenerationService::studyDeckPdfUrl(['id' => 2, 'filename' => 'classroom_presentation_x_1.pptx']), 'other content never gets one');
        $this->assertNull(ContentGenerationService::studyDeckPdfUrl(['id' => 3, 'filename' => 'https://gamma.app/docs/x']));
        $this->assertNull(ContentGenerationService::studyDeckPdfUrl(['id' => 4]));
    }

    public function test_the_pdf_path_comes_only_from_the_row_name(): void
    {
        $this->assertSame('public/lms_content_file/study_deck_a.pdf', ContentGenerationService::studyDeckPdfPath('study_deck_a.pptx'));
        $this->assertNull(ContentGenerationService::studyDeckPdfUrl(['filename' => '../../etc/passwd']));
    }

    public function test_refreshing_replaces_the_stored_pdf_under_the_same_row_and_leaves_the_rest_alone(): void
    {
        [$deck, $html] = $this->bundle();
        $first = $this->service();
        $r = $first->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);
        $pdfPath = ContentGenerationService::studyDeckPdfPath($r['body']['data']['filename']);
        Storage::disk('digitalocean')->put($pdfPath, '%PDF-1.4 OLD LAYOUT');
        $before = array_values(array_filter($this->files(), fn ($f) => !str_ends_with($f, '.pdf')));

        $second = $this->service();
        $second->existing = (object) ['id' => 777, 'url' => 'u', 'show_hide' => 1];
        $r2 = $second->publishStudyDeck($this->input(['refresh_pdf' => true]), $deck, $html, $this->dir . '/images', false);

        $this->assertSame('repaired', $r2['body']['status']);
        $this->assertSame('%PDF-1.4 fake', Storage::disk('digitalocean')->get($pdfPath), 'the new PDF replaced the old one');
        $this->assertSame(1, $second->pdfRenders);
        $this->assertSame([], $second->inserted, 'no second content item');
        $this->assertSame($before, array_values(array_filter($this->files(), fn ($f) => !str_ends_with($f, '.pdf'))), 'presentation, deck file and pictures untouched');
        $this->assertCount(1, array_filter($this->files(), fn ($f) => str_ends_with($f, '.pdf')), 'still one PDF');
    }

    public function test_a_refresh_that_fails_leaves_the_stored_pdf_as_it_was(): void
    {
        [$deck, $html] = $this->bundle();
        $first = $this->service();
        $r = $first->publishStudyDeck($this->input(), $deck, $html, $this->dir . '/images', false);
        $pdfPath = ContentGenerationService::studyDeckPdfPath($r['body']['data']['filename']);
        Storage::disk('digitalocean')->put($pdfPath, '%PDF-1.4 STILL GOOD');

        $second = $this->service();
        $second->existing = (object) ['id' => 777, 'url' => 'u', 'show_hide' => 1];
        $second->failPdf = true;
        $r2 = $second->publishStudyDeck($this->input(['refresh_pdf' => true]), $deck, $html, $this->dir . '/images', false);

        $this->assertSame(500, $r2['http']);
        $this->assertSame('%PDF-1.4 STILL GOOD', Storage::disk('digitalocean')->get($pdfPath), 'the download keeps working');
    }
}
