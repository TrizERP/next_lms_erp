<?php

namespace Tests\Unit\VocabularyClass;

use App\Http\Controllers\api\ApiLmsCourseController;
use App\Services\lms\Content\ContentOwnershipDecorator;
use App\Services\lms\Content\VocabularyClassDeck;
use App\Services\StudyDeck\StudyDeckImages;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Unit\StudyDeck\Support\BootSafeTestCase;

/**
 * The Vocabulary Class is ONE content_master row holding the whole deck. These tests run the real store command and the
 * real read API against a throwaway sqlite database (never the shared one) with the object store and the picture check
 * faked, and prove: one row (not one per card), every card in it, no duplicate on a repeat, tenant and book isolation.
 */
class VocabularyClassTest extends BootSafeTestCase
{
    private const BUNDLE = __DIR__ . '/../../../content_bundles/vocabulary-class/g4-water.json';

    private const IMAGES = __DIR__ . '/../../../storage/app/vocabulary-class/g4-water/images';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('digitalocean', ['url' => 'https://cdn.example.test']);
        config(['app.url' => 'https://lms.example.test']);

        $db = DB::connection();
        $db->statement('CREATE TABLE study_deck_images (id INTEGER PRIMARY KEY AUTOINCREMENT, sub_institute_id INT NOT NULL, chapter_id INT NULL, sha256 TEXT NOT NULL, mime_type TEXT, format TEXT, byte_size INT, width INT, height INT, source_url_hash TEXT NULL, source_url TEXT NULL, data BLOB NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (sub_institute_id, sha256))');
        $db->statement('CREATE TABLE study_deck_image_links (id INTEGER PRIMARY KEY AUTOINCREMENT, image_id INT NOT NULL, content_id INT NOT NULL, chapter_id INT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE (image_id, content_id))');
        $db->statement('CREATE TABLE content_master (id INTEGER PRIMARY KEY AUTOINCREMENT, grade_id INT, standard_id INT, subject_id INT, chapter_id INT, topic_id INT NULL, concept_id INT NULL, title TEXT, description TEXT, file_folder TEXT, filename TEXT, url TEXT NULL, file_type TEXT, file_size TEXT, show_hide INT NULL, content_category TEXT, source TEXT, created_by INT, sub_institute_id INT, basic_advance TEXT, syear INT)');
        $db->statement('CREATE TABLE chapter_master (id INTEGER PRIMARY KEY, chapter_name TEXT, standard_id INT, subject_id INT, sub_institute_id INT, grade_id INT)');
        $db->statement('CREATE TABLE school_setup (Id INTEGER PRIMARY KEY, is_Lms TEXT NULL)');
        $db->statement('CREATE TABLE standard (id INTEGER PRIMARY KEY, grade_id INT)');
        $db->table('school_setup')->insert([['Id' => 341, 'is_Lms' => 'N'], ['Id' => 342, 'is_Lms' => 'N']]);
        $db->table('standard')->insert(['id' => 4258, 'grade_id' => 1087]);
        foreach ([41675 => 'Global water resources', 41676 => 'Water properties', 41677 => 'Water and climate', 41678 => 'Life in water', 41679 => 'A later chapter'] as $id => $name) {
            $db->table('chapter_master')->insert(['id' => $id, 'chapter_name' => $name, 'standard_id' => 4258, 'subject_id' => 48385, 'sub_institute_id' => 341, 'grade_id' => 1087]);
        }
    }

    private function store(array $options = []): int
    {
        $code = Artisan::call('lms:store-vocabulary-class', ['bundle' => self::BUNDLE, '--images' => self::IMAGES] + $options);
        $this->lastOutput = Artisan::output();

        return $code;
    }

    private string $lastOutput = '';

    private function rows()
    {
        return DB::table('content_master')->where('filename', 'like', VocabularyClassDeck::FILE_PREFIX . '%')->get();
    }

    private function ask(int $chapter, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/lms-vocabulary-class', ['chapter_id' => $chapter, 'sub_institute_id' => 341] + $extra);
    }

    public function test_the_reviewed_bundle_holds_the_whole_pdf_and_names_what_it_left_out(): void
    {
        $bundle = json_decode((string) file_get_contents(self::BUNDLE), true);

        $this->assertSame(26, VocabularyClassDeck::cardCount($bundle));
        $this->assertSame([7, 7, 4, 8], array_map(fn ($t) => count($t['cards']), $bundle['topics']));
        $this->assertSame([41675, 41676, 41677, 41678], VocabularyClassDeck::chapterIds($bundle));
        $this->assertSame('rainwater harvesting', $bundle['source']['omitted'][0]['term']);
        // Source card numbers run in the PDF's order inside every topic.
        foreach ($bundle['topics'] as $topic) {
            $numbers = array_column($topic['cards'], 'sourceNumber');
            $sorted = $numbers;
            sort($sorted);
            $this->assertSame($sorted, $numbers, $topic['code']);
        }
    }

    public function test_the_whole_deck_is_stored_in_exactly_one_row_with_every_card_in_it(): void
    {
        $this->assertSame(0, $this->store(), $this->lastOutput);

        $rows = $this->rows();
        $this->assertCount(1, $rows, 'one row for the whole deck, never one per card');
        $row = $rows->first();
        $this->assertSame(41675, (int) $row->chapter_id);
        $this->assertSame('Vocabulary Cards', $row->content_category);
        $this->assertSame(341, (int) $row->sub_institute_id);
        $this->assertSame(1, DB::table('content_master')->count(), 'nothing else was written');

        $deck = VocabularyClassDeck::decode($row->description);
        $this->assertNotNull($deck);
        $this->assertSame(26, VocabularyClassDeck::cardCount($deck));
        $terms = collect($deck['topics'])->flatMap(fn ($t) => array_column($t['cards'], 'term'))->all();
        $this->assertContains('glacier', $terms);
        $this->assertContains('ecosystem', $terms);
        $this->assertNotContains('rainwater harvesting', $terms);
        $glacier = $deck['topics'][0]['cards'][0];
        $this->assertSame('A glacier is a large, slow-moving mass of ice formed from layers of compacted snow.', $glacier['definition']);
    }

    public function test_every_picture_is_stored_in_the_database_and_nothing_goes_to_a_folder_or_bucket(): void
    {
        $this->assertSame(0, $this->store(), $this->lastOutput);
        $row = $this->rows()->first();
        $deck = VocabularyClassDeck::decode($row->description);

        $pictures = collect($deck['topics'])->flatMap(fn ($t) => array_column($t['cards'], 'image'))->filter()->values();
        $this->assertCount(11, $pictures);
        foreach ($pictures as $image) {
            $id = StudyDeckImages::idFromRef($image['src']);
            $this->assertNotNull($id, 'the deck holds a reference, not a link');
            $this->assertNotSame('', $image['alt']);
            $bytes = (new StudyDeckImages())->bytes($id);
            $this->assertStringStartsWith("\xFF\xD8", (string) $bytes, 'the picture itself is in the database');
        }
        $this->assertSame(11, DB::table('study_deck_images')->where('sub_institute_id', 341)->count());
        $this->assertSame(11, DB::table('study_deck_image_links')->where('content_id', $row->id)->count());
        $this->assertSame([], Storage::disk('digitalocean')->allFiles(), 'no copy is kept in the object store');
    }

    public function test_opening_the_deck_turns_picture_references_into_addresses_the_browser_can_load(): void
    {
        $this->store();

        $deck = $this->ask(41675)->assertOk()->json('data');
        $sources = collect($deck['topics'])->flatMap(fn ($t) => array_column(array_filter(array_column($t['cards'], 'image')), 'src'));

        $this->assertCount(11, $sources);
        foreach ($sources as $src) {
            $this->assertStringContainsString('/api/study-deck/images/', $src);
            $this->assertStringContainsString('signature=', $src);
        }
    }

    public function test_updating_a_row_that_kept_its_pictures_in_the_bucket_moves_them_into_the_database_and_removes_the_old_copies(): void
    {
        Storage::disk('digitalocean')->put('public/lms_content_file/vocabulary_class_g4_water_old_1234567890.jpg', 'x');
        $this->store();
        $row = $this->rows()->first();
        $deck = VocabularyClassDeck::decode($row->description);
        $deck['topics'][0]['cards'][2]['image']['src'] = 'https://cdn.example.test/public/lms_content_file/vocabulary_class_g4_water_old_1234567890.jpg';
        DB::table('content_master')->where('id', $row->id)->update(['description' => json_encode($deck)]);

        $this->assertSame(0, $this->store(['--update' => true]), $this->lastOutput);

        $this->assertSame([], Storage::disk('digitalocean')->allFiles());
        $after = VocabularyClassDeck::decode($this->rows()->first()->description);
        $this->assertNotNull(StudyDeckImages::idFromRef($after['topics'][0]['cards'][2]['image']['src']));
        $this->assertSame(11, DB::table('study_deck_images')->count(), 'the same pictures are found again, not stored twice');
    }

    public function test_storing_again_writes_nothing_and_update_changes_the_same_row_in_place(): void
    {
        $this->store();
        $id = $this->rows()->first()->id;

        $this->assertSame(0, $this->store());
        $this->assertCount(1, $this->rows());

        DB::table('content_master')->where('id', $id)->update(['description' => 'damaged']);
        $this->assertSame(0, $this->store(['--update' => true]));
        $this->assertCount(1, $this->rows());
        $this->assertSame($id, $this->rows()->first()->id);
        $this->assertSame(26, VocabularyClassDeck::cardCount(VocabularyClassDeck::decode($this->rows()->first()->description)));
    }

    public function test_a_dry_run_writes_and_uploads_nothing(): void
    {
        $this->assertSame(0, $this->store(['--dry-run' => true]));

        $this->assertSame(0, DB::table('content_master')->count());
        $this->assertSame([], Storage::disk('digitalocean')->allFiles());
    }

    public function test_a_picture_that_is_not_a_picture_stops_the_store_before_anything_is_written(): void
    {
        $dir = sys_get_temp_dir() . '/vc-images-' . uniqid();
        mkdir($dir);
        foreach (glob(self::IMAGES . '/*.jpg') as $file) {
            copy($file, $dir . '/' . basename($file));
        }
        file_put_contents($dir . '/page_10_image_03.jpg', 'not a picture');

        $code = Artisan::call('lms:store-vocabulary-class', ['bundle' => self::BUNDLE, '--images' => $dir]);

        $this->assertSame(1, $code);
        $this->assertSame(0, DB::table('content_master')->count());
        $this->assertSame(0, DB::table('study_deck_images')->count());
        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function test_the_deck_opens_for_every_chapter_it_names_and_for_no_other(): void
    {
        $this->store();
        $id = (int) $this->rows()->first()->id;

        foreach ([41675, 41676, 41677, 41678] as $chapter) {
            $res = $this->ask($chapter, ['content_id' => $id]);
            $res->assertOk()->assertJsonPath('status_code', 1)->assertJsonPath('content_id', $id);
            $this->assertSame(26, VocabularyClassDeck::cardCount($res->json('data')));
        }

        $this->ask(41679)->assertStatus(404);
        // Another school asks for the same chapter and the same row: it is not theirs.
        $this->postJson('/api/lms-vocabulary-class', ['chapter_id' => 41676, 'sub_institute_id' => 342, 'content_id' => $id])->assertStatus(404);
    }

    public function test_a_chapter_list_gets_the_deck_row_only_from_the_book_and_school_it_belongs_to(): void
    {
        $this->store();

        $in = fn (int $chapter, int $subject, int $standard, array $tenants) => count(VocabularyClassDeck::siblingRows($chapter, $subject, $standard, $tenants));
        $this->assertSame(1, $in(41676, 48385, 4258, [341]));
        $this->assertSame(0, $in(41675, 48385, 4258, [341]), 'its own chapter already lists it');
        $this->assertSame(0, $in(41679, 48385, 4258, [341]), 'a chapter the deck does not name');
        $this->assertSame(0, $in(41676, 48385, 4258, [342]), 'another school');
        $this->assertSame(0, $in(41676, 99999, 4258, [341]), 'another subject');
        $this->assertSame(0, $in(41676, 48385, 4000, [341]), 'another standard');
    }

    public function test_the_chapter_list_shows_the_one_deck_row_under_vocabulary_cards_in_each_of_its_chapters(): void
    {
        $this->store();
        $id = (int) $this->rows()->first()->id;
        DB::statement('CREATE TABLE lms_concept (id INTEGER PRIMARY KEY, name TEXT)');
        DB::statement('CREATE TABLE lms_flashcard (id INTEGER PRIMARY KEY, chapter_id INT, sub_institute_id INT, status INT)');

        // Ownership stamping reads tables this test does not need; it only adds fields, so a pass-through stands in.
        $this->mock(ContentOwnershipDecorator::class, fn ($mock) => $mock->shouldReceive('apply')->andReturnUsing(fn (array $assets) => $assets));

        $controller = app(ApiLmsCourseController::class);
        $list = new \ReflectionMethod($controller, 'getChapterContentCategories');
        $list->setAccessible(true);

        foreach ([41675, 41676, 41677, 41678] as $chapter) {
            $categories = $list->invoke($controller, $chapter, 48385, 4258, 341);
            $decks = array_values(array_filter($categories['Vocabulary Cards'] ?? [], fn ($a) => ($a['filename'] ?? '') === 'vocabulary_class__g4_water.json'));

            $this->assertCount(1, $decks, 'chapter ' . $chapter);
            $this->assertSame($id, (int) $decks[0]['id']);
            $this->assertSame('/student/vocabulary-class/' . $chapter . '?content=' . $id, $decks[0]['deep_link']);
        }

        $other = $list->invoke($controller, 41679, 48385, 4258, 341);
        $this->assertSame([], $other['Vocabulary Cards'] ?? [], 'a chapter the deck does not name lists nothing');
    }

    public function test_a_hidden_deck_is_not_opened_and_nothing_is_generated_when_none_is_stored(): void
    {
        $this->ask(41676)->assertStatus(404)->assertJsonPath('status_code', 0);

        $this->store();
        DB::table('content_master')->update(['show_hide' => 0]);
        $this->ask(41676)->assertStatus(404);
        $this->assertSame(1, DB::table('content_master')->count());
    }

    public function test_an_unreadable_stored_deck_says_so_instead_of_opening_half_a_deck(): void
    {
        $this->store();
        DB::table('content_master')->update(['description' => '{"type":"vocabulary_class"']);

        $this->ask(41676)->assertStatus(502)->assertJsonPath('status_code', 0);
    }

    public function test_a_deck_the_player_cannot_rely_on_is_refused(): void
    {
        $card = ['id' => 'a', 'term' => 'glacier', 'definition' => 'Ice.', 'image' => null];
        $deck = ['type' => 'vocabulary_class', 'version' => 1, 'title' => 'T', 'topics' => [['id' => 't', 'chapter_id' => 1, 'title' => 'Topic', 'cards' => [$card]]]];
        $this->assertSame([], VocabularyClassDeck::problems($deck));

        $dup = $deck;
        $dup['topics'][0]['cards'][] = $card;
        $this->assertNotSame([], VocabularyClassDeck::problems($dup), 'duplicate card id');

        $noDefinition = $deck;
        $noDefinition['topics'][0]['cards'][0]['definition'] = ' ';
        $this->assertNotSame([], VocabularyClassDeck::problems($noDefinition));

        $noAddress = $deck;
        $noAddress['topics'][0]['cards'][0]['image'] = ['file' => 'x.png', 'alt' => 'A picture'];
        $this->assertNotSame([], VocabularyClassDeck::problems($noAddress), 'a picture with no stored address');

        $this->assertNotSame([], VocabularyClassDeck::problems(['type' => 'quiz']));
        $this->assertNotSame([], VocabularyClassDeck::problems('nope'));
    }
}
