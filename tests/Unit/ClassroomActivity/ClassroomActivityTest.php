<?php

namespace Tests\Unit\ClassroomActivity;

use App\Http\Controllers\api\ApiLmsCourseController;
use App\Services\lms\Content\ClassroomActivityDocument;
use App\Services\lms\Content\ContentOwnershipDecorator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Unit\StudyDeck\Support\BootSafeTestCase;

/**
 * Interactive Classroom Activities are ONE content_master row each, in the existing 'Classroom Activity' category. These
 * tests run the real store command, the real read API and the real chapter listing against a throwaway sqlite database
 * (never the shared one) and prove: the bundles hold the PDF's steps in order and in its words, one row per activity, no
 * duplicate on a repeat or under another file name, nothing else is written, no invented concept link, tenant / chapter
 * isolation, and the PDF task-card rows that already exist are left alone.
 */
class ClassroomActivityTest extends BootSafeTestCase
{
    private const A1 = __DIR__ . '/../../../content_bundles/classroom-activity/g4-water-a1.json';

    private const B1 = __DIR__ . '/../../../content_bundles/classroom-activity/g4-water-b1.json';

    /** STC_EO_WATER_G4.pdf, pages 1-2, as printed. */
    private const A1_STEPS = [
        'Each group will be given 4 trays.',
        'Fill each tray with soil, sand, grass, and water, respectively.',
        'Check the initial temperature of all the materials.',
        'Put the trays under a lamp or in sunlight for 15 minutes.',
        'After heating, record the temperature of each surface.',
        'Now, remove the trays from the heat and place them in the shade.',
        'Wait for another 15 minutes and measure the new temperature of each tray.',
        'Write your observations on the sheet',
        'Discuss how these temperature changes help explain sea breezes and land breezes, and why places near the sea have milder weather.',
    ];

    /** STC_EO_WATER_G4.pdf, pages 3-4, as printed. */
    private const B1_STEPS = [
        'Open Google Earth on your device.',
        'Search for both countries on your cards—one water-rich and one water-poor.',
        'Zoom in and look for water bodies like rivers, lakes, or coastlines.',
        'Observe the landscape—is it green and forested, dry and desert-like, or mountainous?',
        'Read the information on both country cards carefully.',
        'With your group, compare these features:',
        'Use the Reflection Questions to guide your discussion.',
        'Write a short group report titled: “Why [Country A] is Water-Rich and [Country B] is Water-Poor”',
        'Present your report to the class.',
        'Show your Google Earth map and point out water features.',
        'Highlight the key facts and differences between the two countries.',
    ];

    private string $lastOutput = '';

    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        $db->statement('CREATE TABLE content_master (id INTEGER PRIMARY KEY AUTOINCREMENT, grade_id INT, standard_id INT, subject_id INT, chapter_id INT, topic_id INT NULL, concept_id INT NULL, title TEXT, description TEXT, file_folder TEXT, filename TEXT, url TEXT NULL, file_type TEXT, file_size TEXT, show_hide INT NULL, content_category TEXT, source TEXT, created_by INT, sub_institute_id INT, basic_advance TEXT, syear INT)');
        $db->statement('CREATE TABLE chapter_master (id INTEGER PRIMARY KEY, chapter_name TEXT, standard_id INT, subject_id INT, sub_institute_id INT, grade_id INT)');
        $db->statement('CREATE TABLE lms_concept (id INTEGER PRIMARY KEY, name TEXT, chapter_id INT, topic_id INT, sub_institute_id INT)');
        $db->statement('CREATE TABLE lms_flashcard (id INTEGER PRIMARY KEY, chapter_id INT, sub_institute_id INT, status INT)');
        $db->statement('CREATE TABLE school_setup (Id INTEGER PRIMARY KEY, is_Lms TEXT NULL)');
        $db->statement('CREATE TABLE standard (id INTEGER PRIMARY KEY, grade_id INT)');
        $db->table('school_setup')->insert([['Id' => 341, 'is_Lms' => 'N'], ['Id' => 342, 'is_Lms' => 'N']]);
        $db->table('standard')->insert(['id' => 4258, 'grade_id' => 1087]);
        foreach ([41675 => 'Global water resources', 41676 => 'Water properties'] as $id => $name) {
            $db->table('chapter_master')->insert(['id' => $id, 'chapter_name' => $name, 'standard_id' => 4258, 'subject_id' => 48385, 'sub_institute_id' => 341, 'grade_id' => 1087]);
        }
        $db->table('lms_concept')->insert(['id' => 46127, 'name' => 'Water-rich and water-poor countries', 'chapter_id' => 41675, 'topic_id' => 26315, 'sub_institute_id' => 341]);
    }

    /** @param list<string> $bundles */
    private function store(array $bundles = [self::A1, self::B1], array $options = []): int
    {
        $code = Artisan::call('lms:store-classroom-activity', ['bundles' => $bundles] + $options);
        $this->lastOutput = Artisan::output();

        return $code;
    }

    private function rows()
    {
        return DB::table('content_master')->where('filename', 'like', ClassroomActivityDocument::FILE_PREFIX . '%')->orderBy('id')->get();
    }

    private function ask(int $chapter, int $content, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/lms-classroom-activity', array_merge(['chapter_id' => $chapter, 'content_id' => $content, 'sub_institute_id' => 341], $extra));
    }

    private function bundle(string $path): array
    {
        return json_decode((string) file_get_contents($path), true);
    }

    public function test_the_bundles_keep_the_pdfs_steps_in_its_order_and_words(): void
    {
        $this->assertSame(self::A1_STEPS, array_column($this->bundle(self::A1)['steps'], 'text'));
        $this->assertSame(self::B1_STEPS, array_column($this->bundle(self::B1)['steps'], 'text'));
        $this->assertSame([], ClassroomActivityDocument::problems($this->bundle(self::A1)));
        $this->assertSame([], ClassroomActivityDocument::problems($this->bundle(self::B1)));

        $b1 = $this->bundle(self::B1);
        $this->assertSame(['Rainfall', 'Water sources (rivers, lakes, glaciers, coastlines)', 'Population', 'Climate (hot/dry, cool/wet, etc.)', 'Water availability'], $b1['steps'][5]['items']);
        $this->assertSame(['2–3 reasons why the first country has more water', '2–3 reasons why the second country has less water', '1 suggestion to help the water-poor country improve its water situation'], $b1['steps'][7]['items']);
    }

    public function test_only_the_activities_of_chapter_one_are_bundled_and_the_other_six_are_not(): void
    {
        $a1 = $this->bundle(self::A1);
        $b1 = $this->bundle(self::B1);

        $this->assertSame(['A1', 'B1'], [$a1['code'], $b1['code']]);
        $this->assertSame([41675, 41675], [$a1['scope']['chapter_id'], $b1['scope']['chapter_id']]);
        $this->assertSame(['G4_WATER_EO_STC_1', 'G4_WATER_EO_STC_2'], [$a1['references']['task_card'], $b1['references']['task_card']]);
        $files = array_map('basename', glob(__DIR__ . '/../../../content_bundles/classroom-activity/*.json') ?: []);
        sort($files);
        $this->assertSame(['g4-water-a1.json', 'g4-water-b1.json'], $files, 'A2, C2, A3 (chapter 3), B3 (chapter 2) and A4, A5 (chapter 4) are other chapters\' activities');
    }

    public function test_the_worksheets_ask_learners_to_record_and_carry_no_results(): void
    {
        $a1 = $this->bundle(self::A1);
        $temps = $a1['blocks'][0];
        $this->assertSame(['soil', 'sand', 'grass', 'water'], array_column($temps['rows'], 'id'));
        $this->assertSame(['initial', 'heated', 'cooled'], array_column($temps['columns'], 'id'));
        $this->assertArrayNotHasKey('values', $temps, 'no invented measurements');
        $this->assertArrayNotHasKey('answer', $a1['reflection'][0], 'the discussion prompt is not marked');

        $b1 = $this->bundle(self::B1);
        $rows = array_column($b1['blocks'][0]['rows'], 'label', 'id');
        foreach (['Rainfall', 'Population', 'Water availability'] as $feature) {
            $this->assertContains($feature, $rows);
        }
        $this->assertSame([], $b1['reflection'], 'the task card names Reflection Questions but does not print them, so none are invented');
    }

    public function test_each_activity_is_stored_in_exactly_one_row_and_nothing_else_is_written(): void
    {
        $this->assertSame(0, $this->store(), $this->lastOutput);

        $rows = $this->rows();
        $this->assertCount(2, $rows);
        $this->assertSame(2, DB::table('content_master')->count(), 'nothing else was written');
        foreach ($rows as $row) {
            $this->assertSame('Classroom Activity', $row->content_category);
            $this->assertSame(41675, (int) $row->chapter_id);
            $this->assertSame(341, (int) $row->sub_institute_id);
            $this->assertSame(4258, (int) $row->standard_id);
            $this->assertSame(48385, (int) $row->subject_id);
            $this->assertNull($row->topic_id, 'the chapter list only reads rows with no topic');
            $this->assertSame('interactive', $row->file_type);
        }
        $this->assertSame(['classroom_activity__g4_water_a1.json', 'classroom_activity__g4_water_b1.json'], $rows->pluck('filename')->all());
        $this->assertNull($rows[0]->concept_id, 'A1 matches no stored concept, so none is linked');
        $this->assertSame(46127, (int) $rows[1]->concept_id);
    }

    public function test_storing_again_writes_nothing_and_update_changes_the_same_row_in_place(): void
    {
        $this->store();
        $ids = $this->rows()->pluck('id')->all();

        $this->assertSame(0, $this->store());
        $this->assertSame($ids, $this->rows()->pluck('id')->all());

        DB::table('content_master')->where('id', $ids[0])->update(['description' => 'damaged']);
        $this->assertSame(0, $this->store([self::A1], ['--update' => true]));
        $this->assertSame($ids, $this->rows()->pluck('id')->all());
        $this->assertSame(self::A1_STEPS, array_column(ClassroomActivityDocument::decode($this->rows()->first()->description)['steps'], 'text'));
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->assertSame(0, $this->store([self::A1, self::B1], ['--dry-run' => true]));
        $this->assertSame(0, DB::table('content_master')->count());
        $this->assertStringContainsString('would INSERT one new content_master row', $this->lastOutput);
    }

    public function test_the_same_activity_code_under_another_file_name_is_refused(): void
    {
        $this->store([self::A1]);
        $copy = $this->bundle(self::A1);
        $copy['key'] = 'g4-water-a1-again';
        $path = (string) tempnam(sys_get_temp_dir(), 'ca-');
        file_put_contents($path, json_encode($copy));

        $this->assertSame(1, $this->store([$path]));
        $this->assertStringContainsString('already stores an activity with this code', $this->lastOutput);
        $this->assertCount(1, $this->rows());
        @unlink($path);
    }

    public function test_the_pdf_task_card_rows_that_already_exist_are_left_alone(): void
    {
        DB::table('content_master')->insert(['standard_id' => 4258, 'subject_id' => 48385, 'chapter_id' => 41675, 'title' => 'Chapter 1: Student task cards (A1, B1)', 'filename' => 'g4-water-ch1-student-task-cards-bae1dc3e.pdf', 'file_type' => 'pdf', 'show_hide' => 1, 'content_category' => 'Classroom Activity', 'source' => 'HES publisher material', 'sub_institute_id' => 341]);
        $before = (array) DB::table('content_master')->first();

        $this->assertSame(0, $this->store(), $this->lastOutput);

        $this->assertSame($before, (array) DB::table('content_master')->where('id', $before['id'])->first());
        $this->assertSame(3, DB::table('content_master')->count());
    }

    public function test_a_concept_that_is_not_of_the_chapter_stops_the_store_before_anything_is_written(): void
    {
        $b1 = $this->bundle(self::B1);
        $b1['links']['concept_id'] = 99999;
        $path = (string) tempnam(sys_get_temp_dir(), 'ca-');
        file_put_contents($path, json_encode($b1));

        $this->assertSame(1, $this->store([self::A1, $path]));

        $this->assertStringContainsString('is not a concept of chapter 41675', $this->lastOutput);
        $this->assertSame(0, DB::table('content_master')->count(), 'A1 was valid but nothing is written when any bundle fails');
        @unlink($path);
    }

    public function test_a_chapter_of_another_school_or_book_stops_the_store(): void
    {
        $a1 = $this->bundle(self::A1);
        $a1['scope']['standard_id'] = 4000;
        $path = (string) tempnam(sys_get_temp_dir(), 'ca-');
        file_put_contents($path, json_encode($a1));

        $this->assertSame(1, $this->store([$path]));
        $this->assertStringContainsString('is not a chapter of standard 4000', $this->lastOutput);
        $this->assertSame(0, DB::table('content_master')->count());
        @unlink($path);
    }

    public function test_opening_an_activity_reads_only_that_row_in_its_own_chapter_school_and_book(): void
    {
        $this->store();
        [$a1, $b1] = $this->rows()->all();

        $data = $this->ask(41675, (int) $a1->id)->assertOk()->assertJsonPath('status_code', 1)->json();
        $this->assertSame('A1', $data['data']['code']);
        $this->assertSame((int) $a1->id, $data['content_id']);
        $this->assertSame('B1', $this->ask(41675, (int) $b1->id)->assertOk()->json('data.code'));

        $this->ask(41676, (int) $a1->id)->assertStatus(404);
        $this->ask(41675, (int) $a1->id, ['sub_institute_id' => 342])->assertStatus(404);
        $this->postJson('/api/lms-classroom-activity', ['chapter_id' => 41675, 'sub_institute_id' => 341])->assertStatus(422);

        DB::table('content_master')->where('id', $a1->id)->update(['show_hide' => 0]);
        $this->ask(41675, (int) $a1->id)->assertStatus(404);
    }

    public function test_an_unreadable_stored_activity_says_so_instead_of_opening_half_an_activity(): void
    {
        $this->store([self::A1]);
        DB::table('content_master')->update(['description' => '{"type":"classroom_activity"']);

        $this->ask(41675, (int) $this->rows()->first()->id)->assertStatus(502)->assertJsonPath('status_code', 0);
    }

    public function test_the_chapter_list_shows_each_activity_under_classroom_activity_with_its_link_code_and_step_count(): void
    {
        DB::table('content_master')->insert(['standard_id' => 4258, 'subject_id' => 48385, 'chapter_id' => 41675, 'title' => 'Chapter 1: Student task cards (A1, B1)', 'filename' => 'g4-water-ch1-student-task-cards-bae1dc3e.pdf', 'file_type' => 'pdf', 'show_hide' => 1, 'content_category' => 'Classroom Activity', 'source' => 'HES publisher material', 'sub_institute_id' => 341]);
        $this->store();
        $this->mock(ContentOwnershipDecorator::class, fn ($mock) => $mock->shouldReceive('apply')->andReturnUsing(fn (array $assets) => $assets));

        $controller = app(ApiLmsCourseController::class);
        $list = new \ReflectionMethod($controller, 'getChapterContentCategories');
        $list->setAccessible(true);
        $categories = $list->invoke($controller, 41675, 48385, 4258, 341);

        $activities = array_values(array_filter($categories['Classroom Activity'] ?? [], fn ($a) => ClassroomActivityDocument::isActivityFilename($a['filename'] ?? null)));
        $this->assertCount(2, $activities);
        $this->assertSame(['A1', 'B1'], array_column($activities, 'activity_code'));
        $this->assertSame([9, 11], array_column($activities, 'activity_steps'));
        foreach ($activities as $activity) {
            $this->assertSame('/student/classroom-activity/41675?content=' . $activity['id'], $activity['deep_link']);
        }

        $all = $categories['Classroom Activity'];
        $this->assertCount(3, $all, 'the existing PDF task card row is still listed beside them');
        $pdf = array_values(array_filter($all, fn ($a) => str_ends_with($a['filename'], '.pdf')));
        $this->assertArrayNotHasKey('deep_link', $pdf[0]);

        $other = $list->invoke($controller, 41676, 48385, 4258, 341);
        $this->assertSame([], $other['Classroom Activity'] ?? [], 'a chapter the activity does not belong to lists nothing');
    }

    public function test_a_document_the_player_cannot_rely_on_is_refused(): void
    {
        $a1 = $this->bundle(self::A1);
        $this->assertSame([], ClassroomActivityDocument::problems($a1));

        $skipped = $a1;
        $skipped['steps'][2]['n'] = 5;
        $this->assertNotSame([], ClassroomActivityDocument::problems($skipped), 'steps keep the printed order');

        $dangling = $a1;
        $dangling['steps'][2]['uses'] = [['block' => 'nope']];
        $this->assertNotSame([], ClassroomActivityDocument::problems($dangling), 'a step must name a block that exists');

        $badColumn = $a1;
        $badColumn['steps'][2]['uses'] = [['block' => 'temps', 'columns' => ['nope']]];
        $this->assertNotSame([], ClassroomActivityDocument::problems($badColumn));

        $noObjective = $a1;
        $noObjective['intro']['objectives'] = [];
        $this->assertNotSame([], ClassroomActivityDocument::problems($noObjective));

        $this->assertNotSame([], ClassroomActivityDocument::problems(['type' => 'quiz']));
        $this->assertNotSame([], ClassroomActivityDocument::problems('nope'));
    }
}
