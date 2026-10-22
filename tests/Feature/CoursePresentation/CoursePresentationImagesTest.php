<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\CoursePresentation\GenerationRun;
use App\Services\CoursePresentation\ImageReadiness;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyDeckImageUrls;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Pictures of the Course Presentation workflow, on the EXISTING picture store (study_deck_images,
 * study_deck_image_links). The real StudyDeckImages and StudyDeckImageUrls classes run on throwaway sqlite; what is
 * asserted is the contract the workflow relies on: one row per school and picture, one link per picture and deck,
 * a school sees only its own pictures, a deck is not offered for review with a picture the library cannot return, and
 * a stored deck is checked for its links.
 */
class CoursePresentationImagesTest extends CoursePresentationTestCase
{
    private StudyDeckImages $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new StudyDeckImages();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    /** A real PNG; each shade is a different picture. */
    private function png(int $shade = 0, int $w = 8, int $h = 4): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, $shade % 256, intdiv($shade, 256) % 256, 90));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function stored(int $shade, int $tenant = self::TENANT, ?int $chapter = self::CHAPTER, ?string $url = null): array
    {
        return $this->store->put($this->png($shade), $tenant, $chapter, $url);
    }

    private function bundle(string $base, array $files): string
    {
        $dir = storage_path($this->runDir . '/' . $base);
        mkdir($dir, 0775, true);
        foreach ($files as $name => $data) {
            file_put_contents($dir . '/' . $name, json_encode($data));
        }

        return $dir;
    }

    // ------------------------------------------------------------------ the store's own rules (existing code)

    public function test_the_same_picture_is_one_row_per_school_and_a_second_school_holds_its_own(): void
    {
        $a = $this->stored(1);
        $again = $this->stored(1);
        $other = $this->stored(1, self::OTHER_TENANT);

        $this->assertTrue($a['created']);
        $this->assertFalse($again['created']);
        $this->assertSame($a['id'], $again['id'], 'identical bytes are a lookup, not a second copy');
        $this->assertNotSame($a['id'], $other['id'], 'two schools hold two rows');
        $this->assertSame(2, DB::table('study_deck_images')->count());
        $this->assertSame('study-deck-image:' . $a['id'], $a['ref']);
    }

    public function test_a_picture_that_is_not_a_picture_is_refused_and_stores_nothing(): void
    {
        foreach (['', 'not an image', str_repeat('x', 100)] as $bytes) {
            try {
                $this->store->put($bytes, self::TENANT, self::CHAPTER);
                $this->fail('accepted a non-picture');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('study_deck_images')->count());
    }

    public function test_a_deck_is_linked_to_each_picture_once_and_linking_again_changes_nothing(): void
    {
        $x = $this->stored(1);
        $y = $this->stored(2);

        $this->assertSame(2, $this->store->link(777, [$x['id'], $y['id'], $x['id']], self::CHAPTER), 'a repeated id in one call is one link');
        $this->assertSame(0, $this->store->link(777, [$x['id'], $y['id']], self::CHAPTER), 'linking again adds nothing');
        $this->assertSame(1, $this->store->link(778, [$x['id']], self::CHAPTER), 'another deck may use the same picture');
        $this->assertSame(3, DB::table('study_deck_image_links')->count());
        $this->assertEqualsCanonicalizing([$x['id'], $y['id']], $this->store->linkedImageIds(777));
        $this->assertSame([$x['id']], $this->store->usedElsewhere([$x['id'], $y['id']], 777));
    }

    public function test_a_school_sees_only_its_own_pictures_unless_it_has_the_lms_and_the_platform_library_is_school_1(): void
    {
        $mine = $this->stored(1, self::TENANT);
        $theirs = $this->stored(2, self::OTHER_TENANT);
        DB::table('school_setup')->insert([['Id' => self::TENANT, 'is_Lms' => 'Y'], ['Id' => self::OTHER_TENANT, 'is_Lms' => 'N'], ['Id' => 3, 'is_Lms' => 'Y']]);

        // School 2 (no LMS) sees its own only; school 3 (LMS) sees its own and the platform library (school 1).
        $this->assertSame([$theirs['id']], array_keys($this->store->visibleMetas([$mine['id'], $theirs['id']], self::OTHER_TENANT)));
        $this->assertSame([$mine['id']], array_keys($this->store->visibleMetas([$mine['id'], $theirs['id']], 3)));
        $this->assertNull($this->store->visibleMeta($theirs['id'], self::TENANT), 'school 1 cannot read school 2\'s picture');
    }

    public function test_the_viewer_gets_addresses_only_for_pictures_the_school_may_see(): void
    {
        $mine = $this->stored(1, self::TENANT);
        $theirs = $this->stored(2, self::OTHER_TENANT);
        $deck = ['slides' => [
            ['n' => 1, 'image' => ['url' => $mine['ref'], 'type' => 'diagram']],
            ['n' => 2, 'image' => ['url' => $theirs['ref'], 'type' => 'diagram']],
            ['n' => 3, 'image' => null],
        ]];

        $out = (new StudyDeckImageUrls())->hydrate($deck, self::TENANT);

        $this->assertStringContainsString('/study-deck/images/' . $mine['id'], $out['slides'][0]['image']['url']);
        $this->assertStringContainsString('signature=', $out['slides'][0]['image']['url'], 'a browser <img> cannot send a token, so the address is signed');
        $this->assertSame($theirs['ref'], $out['slides'][1]['image']['url'], 'another school\'s picture is left as a reference the browser cannot load');
        $this->assertNull($out['slides'][2]['image']);
    }

    // ------------------------------------------------------------------ the readiness report

    public function test_the_library_report_counts_only_this_chapter_and_this_school(): void
    {
        $this->seedPilotChapter();
        $a = $this->stored(1, self::TENANT, self::CHAPTER);
        $this->stored(2, self::TENANT, self::CHAPTER, 'https://commons.example/x.png');          // a photograph
        $this->stored(3, self::TENANT, 9999);                                                     // another chapter
        $this->stored(4, self::OTHER_TENANT, self::CHAPTER);                                      // another school
        $this->store->link(777, [$a['id']], self::CHAPTER);
        $chapter = (array) DB::table('chapter_master')->find(self::CHAPTER);

        $r = (new ImageReadiness())->forChapter($chapter, ['concepts' => []]);

        $this->assertSame(['stored' => 2, 'photographs' => 1, 'drawn_diagrams' => 1, 'used_by_a_stored_deck' => 1, 'not_used_by_any_stored_deck' => 1], $r['library']);
        $this->assertStringContainsString('never reused for a concept just because it is in the same chapter', $r['reuse_rule']);
    }

    public function test_the_earlier_draft_is_checked_against_the_library_and_unmet_photographs_are_named_without_inventing_records(): void
    {
        $this->seedPilotChapter();
        config(['course_presentation_pilot.earlier_bundle_base' => $this->runDir . '/earlier']);
        $x = $this->stored(1);
        $y = $this->stored(2);
        $this->bundle('earlier/chapter-' . self::CHAPTER, [
            'images.json' => [
                '2' => ['type' => 'diagram', 'url' => $x['ref'], 'photo_missing' => 'no sufficiently relevant, openly licensed image found (C:\\x\\storage\\y)'],
                '3' => ['type' => 'diagram', 'url' => $y['ref']],
                '4' => ['type' => 'diagram', 'url' => 'study-deck-image:424242'],            // a record that is not in the library
            ],
            'plan.json' => ['slides' => [['n' => 2, 'taught_concept_ids' => [1]], ['n' => 3, 'taught_concept_ids' => [2]], ['n' => 4, 'taught_concept_ids' => []]]],
        ]);
        $chapter = (array) DB::table('chapter_master')->find(self::CHAPTER);
        $raw = ['concepts' => [['id' => 1, 'name' => 'Atom'], ['id' => 2, 'name' => 'Ion']]];

        $d = (new ImageReadiness())->forChapter($chapter, $raw)['earlier_draft'];

        $this->assertTrue($d['found']);
        $this->assertSame([3, 3, 0, 2, 1], [$d['slides_with_a_picture'], $d['drawn_diagrams'], $d['photographs'], $d['found_in_the_library'], $d['not_found_in_the_library']]);
        $this->assertSame([['slide' => 2, 'concepts' => ['Atom'], 'reason' => 'no sufficiently relevant, openly licensed image found [folder]']], $d['photographs_not_found']);
        $this->assertStringContainsString('not stored', $d['status']);
        $this->assertSame(2, DB::table('study_deck_images')->count(), 'reporting creates no record');
        $this->assertSame(0, DB::table('study_deck_image_links')->count(), 'and no link');

        config(['course_presentation_pilot.earlier_bundle_base' => $this->runDir . '/nothing']);
        $this->assertSame(['found' => false], (new ImageReadiness())->forChapter($chapter, $raw)['earlier_draft']);
    }

    public function test_the_preflight_carries_the_picture_report_and_it_writes_nothing(): void
    {
        $this->seedPilotChapter();
        $this->stored(1);
        $before = [DB::table('study_deck_images')->count(), DB::table('study_deck_image_links')->count()];
        $json = $this->asTeacher()->getJson('/api/lms/course-presentation/preflight?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides']));
        $json->assertOk();
        $this->assertSame(1, $json->json('images.library.stored'));
        $this->assertSame($before, [DB::table('study_deck_images')->count(), DB::table('study_deck_image_links')->count()]);
    }

    // ------------------------------------------------------------------ draft validation and the link check

    private function draftDeck(array $refs, array $images = []): array
    {
        return [
            'deck.json' => ['version' => 3, 'slides' => array_map(fn ($ref, $i) => ['n' => $i + 1, 'image' => ['url' => $ref, 'type' => 'diagram', 'width' => 1280, 'height' => 720, 'alt' => 'A drawn diagram']], $refs, array_keys($refs))],
            'images.json' => $images,
            'report.json' => ['ok' => true, 'errors' => [], 'warnings' => [], 'stats' => ['slides' => count($refs), 'interactive_slides' => 1, 'concepts' => 4], 'teaching_method' => ['key' => 'inquiry_based', 'followed' => true]],
            'plan.json' => ['slides' => [['n' => 1, 'title' => 'One', 'slide_type' => 'cover']]],
        ];
    }

    public function test_every_picture_a_deck_names_is_checked_and_a_deck_with_an_unreadable_one_is_not_offered_for_review(): void
    {
        $this->seedPilotChapter();
        $x = $this->stored(1);
        $theirs = $this->stored(2, self::OTHER_TENANT);
        $good = $this->bundle('good', $this->draftDeck([$x['ref']], ['1' => ['type' => 'diagram', 'url' => $x['ref'], 'photo_missing' => 'no sufficiently relevant, openly licensed image found']]));
        $bad = $this->bundle('bad', $this->draftDeck([$x['ref'], 'study-deck-image:424242', $theirs['ref']]));
        $readiness = new ImageReadiness();

        $g = $readiness->deckImages($good, self::TENANT);
        $this->assertSame([1, 1, 0, 1, 0], [$g['referenced'], $g['resolved'], $g['unreadable'], $g['diagrams'], $g['photographs']]);
        $this->assertSame([['slide' => 1, 'reason' => 'no sufficiently relevant, openly licensed image found']], $g['photographs_not_found']);

        $b = $readiness->deckImages($bad, self::TENANT);
        $this->assertSame([3, 1, 2], [$b['referenced'], $b['resolved'], $b['unreadable']], 'a missing record and another school\'s picture are both unreadable');
    }

    private function runnerWith(array $files, bool $storeLinks, ?array &$log = null): GenerationRun
    {
        return new GenerationRun(function (string $command, array $args, BufferedOutput $out) use ($files, $storeLinks, &$log): int {
            $log[] = $command;
            if ($command === 'lms:generate-study-deck') {
                mkdir($args['--out'], 0775, true);
                foreach ($files as $name => $data) {
                    file_put_contents($args['--out'] . '/' . $name, json_encode($data));
                }

                return 0;
            }
            DB::table('content_master')->insert(['id' => 777, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
                'content_category' => 'Classroom Presentation', 'title' => 'Course Presentation', 'filename' => 'study_deck_8592_abc.pptx', 'show_hide' => 1]);
            if ($storeLinks) {
                $deck = json_decode((string) file_get_contents($args['--bundle'] . '/deck.json'), true);
                $ids = array_map(fn ($s) => StudyDeckImages::idFromRef($s['image']['url']), $deck['slides']);
                (new StudyDeckImages())->link(777, $ids, self::CHAPTER);
            }

            return 0;
            // The chapter's own concept list is not what these tests are about (it is tested in CoursePresentationRunSelectionTest).
        }, null, fn (int $chapterId): ?array => null);
    }

    public function test_a_draft_with_an_unreadable_picture_fails_and_one_with_readable_pictures_is_ready_with_a_report(): void
    {
        $this->seedPilotChapter();
        $x = $this->stored(1);
        $runs = $this->runnerWith($this->draftDeck([$x['ref'], 'study-deck-image:424242']), true);
        $id = $runs->request(self::CHAPTER, self::TENANT, 55, 'inquiry_based')['run'];
        $runs->execute(self::CHAPTER, self::TENANT, $id);
        $s = $runs->read(self::CHAPTER, self::TENANT, $id);
        $this->assertSame('failed', $s['state']);
        $this->assertStringContainsString('1 picture(s) that the library cannot give back (1 of 2 are readable)', $s['error']);
        $runs->discard(self::CHAPTER, self::TENANT, $id);

        $runs = $this->runnerWith($this->draftDeck([$x['ref']], ['1' => ['type' => 'diagram', 'url' => $x['ref'], 'photo_missing' => 'rejected on relevance review: no balloon']]), true);
        $id = $runs->request(self::CHAPTER, self::TENANT, 55, 'inquiry_based')['run'];
        $runs->execute(self::CHAPTER, self::TENANT, $id);
        $s = $runs->read(self::CHAPTER, self::TENANT, $id);
        $this->assertSame('ready', $s['state']);
        $this->assertSame(['referenced' => 1, 'resolved' => 1, 'unreadable' => 0, 'diagrams' => 1, 'photographs' => 0, 'photographs_not_found' => [['slide' => 1, 'reason' => 'rejected on relevance review: no balloon']]], $s['images']);
        $this->assertArrayNotHasKey('ids', $s['images'], 'the teacher never sees record ids');
    }

    public function test_approval_checks_that_the_store_linked_every_picture_to_the_stored_deck(): void
    {
        $this->seedPilotChapter();
        $x = $this->stored(1);
        $y = $this->stored(2);
        $files = $this->draftDeck([$x['ref'], $y['ref']]);

        $runs = $this->runnerWith($files, true);
        $id = $runs->request(self::CHAPTER, self::TENANT, 55, 'inquiry_based')['run'];
        $runs->execute(self::CHAPTER, self::TENANT, $id);
        $res = $runs->approve(self::CHAPTER, self::TENANT, $id, 55, 'Teacher');
        $this->assertSame('Stored.', $res['message']);
        $this->assertSame(['referenced' => 2, 'linked' => 2], $res['status']['stored']['images']);
        $this->assertSame(2, DB::table('study_deck_image_links')->where('content_id', 777)->count());

        // A store that left a picture unlinked is reported, not hidden: the library's clean-up could delete it.
        DB::table('content_master')->delete();
        DB::table('study_deck_image_links')->delete();
        $runs = $this->runnerWith($files, false);
        $id = $runs->request(self::CHAPTER, self::TENANT, 55, 'inquiry_based')['run'];
        $runs->execute(self::CHAPTER, self::TENANT, $id);
        $res = $runs->approve(self::CHAPTER, self::TENANT, $id, 55, 'Teacher');
        $this->assertStringContainsString('2 of 2 pictures are not linked to the stored deck', $res['message']);
        $this->assertSame(['referenced' => 2, 'linked' => 0], $res['status']['stored']['images']);
    }
}
