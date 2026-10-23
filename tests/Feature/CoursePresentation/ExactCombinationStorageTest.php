<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\ContentGenerationService;
use App\Services\CoursePresentation\GenerationRun;
use App\Services\StudyDeck\StudyDeckPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Each teaching method x content format is its OWN stored item. Storing, replacing, hiding, looking up and opening one
 * never touches, shows or substitutes another. Throwaway sqlite and a fake object store.
 */
class ExactCombinationStorageTest extends CoursePresentationTestCase
{
    private const BASE = '/api/lms/course-presentation/';

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('content_master', function ($t) {
            $t->string('file_type')->nullable();
        });
        Storage::fake('digitalocean');
        config(['claude.source_label' => 'Claude AI']);
    }

    private function row(int $id, string $category, string $filename, int $show = 1, ?int $tenant = null, ?int $chapter = null): void
    {
        DB::table('content_master')->insert(['id' => $id, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => $chapter ?? self::CHAPTER, 'sub_institute_id' => $tenant ?? self::TENANT,
            'content_category' => $category, 'title' => $filename, 'filename' => $filename, 'show_hide' => $show, 'source' => 'Claude AI', 'file_type' => 'pptx']);
    }

    /** The chapter's own analysis recommends inquiry-based and activity-based (experiential) teaching. */
    private function seedStrategies(): void
    {
        $rec = fn (string ...$names) => array_map(fn ($n) => ['strategy' => $n, 'why_effective' => 'x'], $names);
        $entries = array_map(fn ($n) => ['concept' => ['concept_name' => $n], 'pedagogy_recommendations' => $rec('Activity Based Teaching', 'Inquiry Based Teaching')], ['Atom', 'Ion', 'Mole', 'Bond']);
        DB::table('semantic_intelligence')->where('id', 13)->update(['full_intelegance_json' => json_encode(['concepts' => $entries])]);
    }

    private function service(): object
    {
        return new class extends ContentGenerationService {
            public function hide(int $chapter, int $tenant, int $keep, string $filename): int
            {
                return $this->hidePreviousStudyDecks($chapter, $tenant, $keep, $this->studyDeckLikePrefix($filename));
            }
        };
    }

    /** @return array<int,string> visible filenames by id */
    private function visible(): array
    {
        return DB::table('content_master')->where('show_hide', 1)->orderBy('id')->pluck('filename', 'id')->all();
    }

    public function test_the_teaching_method_is_part_of_the_stored_name_and_nothing_else_changes(): void
    {
        $deck = ['slides' => [['n' => 1, 'slide_type' => 'cover', 'interaction' => null]]];
        $legacy = StudyDeckPublisher::filenameFor('Global water resources', $deck);
        $inquiry = StudyDeckPublisher::filenameFor('Global water resources', $deck + ['variant' => ['teaching_method' => 'inquiry_based']]);
        $explicit = StudyDeckPublisher::filenameFor('Global water resources', $deck + ['variant' => ['teaching_method' => 'explicit_instruction']]);

        $this->assertStringStartsWith('study_deck_global_water_resources_', $legacy, 'a deck stored without a method keeps the name it always had');
        $this->assertStringStartsWith('study_deck_inquiry_based__global_water_resources_', $inquiry);
        $this->assertStringStartsWith('study_deck_explicit_instruction__global_water_resources_', $explicit);
        $this->assertNull(StudyDeckPublisher::variantOf($legacy));
        $this->assertSame('inquiry_based', StudyDeckPublisher::variantOf($inquiry));
        $this->assertSame('explicit_instruction', StudyDeckPublisher::variantOf($explicit));
        $this->assertTrue(ContentGenerationService::isStudyDeckFilename($inquiry), 'still a study deck to every existing check');
        $this->assertCount(3, array_unique([$legacy, $inquiry, $explicit]));

        // Only a plain key can ever reach a file name.
        foreach (['../x', 'A b', 'x__y', '', 'x_', '1x'] as $bad) {
            $this->assertNull(StudyDeckPublisher::methodOf(['variant' => ['teaching_method' => $bad]]), "'$bad'");
        }
        $this->assertStringStartsWith('study_story_', StudyDeckPublisher::filenameFor('X', ['variant' => ['teaching_method' => 'experiential'], 'slides' => [['interaction' => ['template' => \App\Services\StudyDeck\Story\StoryTemplate::KEY]]]]));
    }

    public function test_storing_a_deck_replaces_only_the_deck_of_the_same_method_and_format(): void
    {
        $this->row(1, 'Classroom Presentation', 'study_deck_inquiry_based__water_aaaa.pptx');
        $this->row(2, 'Classroom Presentation', 'study_deck_explicit_instruction__water_bbbb.pptx');
        $this->row(3, 'Interactive', 'study_story_inquiry_based__water_cccc.pptx');
        $this->row(4, 'Classroom Presentation', 'study_deck_water_dddd.pptx');          // stored before methods were recorded
        $this->row(5, 'Classroom Presentation', 'study_deck_inquiry_based__water_eeee.pptx', 1); // the newer deck of method A
        $this->row(6, 'Classroom Presentation', 'study_deck_inquiry_based__water_ffff.pptx', 1, self::OTHER_TENANT);
        $this->row(7, 'Classroom Presentation', 'study_deck_inquiry_based__water_gggg.pptx', 1, null, 9999);

        $hidden = $this->service()->hide(self::CHAPTER, self::TENANT, 5, 'study_deck_inquiry_based__water_eeee.pptx');

        $this->assertSame(1, $hidden);
        $this->assertSame([2, 3, 4, 5, 6, 7], array_keys($this->visible()), 'only the older deck of the same method and format was hidden');
    }

    public function test_a_deck_stored_without_a_method_never_hides_a_methods_deck(): void
    {
        $this->row(1, 'Classroom Presentation', 'study_deck_inquiry_based__water_aaaa.pptx');
        $this->row(2, 'Classroom Presentation', 'study_deck_water_oldold.pptx');
        $this->row(3, 'Classroom Presentation', 'study_deck_water_newnew.pptx');

        $this->service()->hide(self::CHAPTER, self::TENANT, 3, 'study_deck_water_newnew.pptx');

        $this->assertSame([1, 3], array_keys($this->visible()));
    }

    public function test_stored_lookup_is_exact_and_never_falls_back(): void
    {
        $this->row(1, 'Classroom Presentation', 'study_deck_inquiry_based__water_aaaa.pptx');
        $this->row(2, 'Interactive', 'study_story_inquiry_based__water_bbbb.pptx');
        $this->row(3, 'Classroom Presentation', 'study_deck_water_legacy.pptx');
        $runs = new GenerationRun();

        $this->assertSame(1, $runs->storedPresentation(self::CHAPTER, self::TENANT, 'interactive_slides', 'inquiry_based')['id']);
        $this->assertSame(2, $runs->storedPresentation(self::CHAPTER, self::TENANT, 'branching_scenario', 'inquiry_based')['id']);
        $this->assertSame(3, $runs->storedPresentation(self::CHAPTER, self::TENANT, 'interactive_slides')['id'], 'no method: only the deck stored without one');
        $this->assertNull($runs->storedPresentation(self::CHAPTER, self::TENANT, 'interactive_slides', 'explicit_instruction'), 'another method');
        $this->assertNull($runs->storedPresentation(self::CHAPTER, self::TENANT, 'branching_scenario', 'explicit_instruction'));
        $this->assertNull($runs->storedPresentation(self::CHAPTER, self::OTHER_TENANT, 'interactive_slides', 'inquiry_based'), 'another school');
        $this->assertNull($runs->storedPresentation(9999, self::TENANT, 'interactive_slides', 'inquiry_based'), 'another chapter');
        // A hidden deck is not stored as far as lookup is concerned.
        DB::table('content_master')->where('id', 1)->update(['show_hide' => 0]);
        $this->assertNull($runs->storedPresentation(self::CHAPTER, self::TENANT, 'interactive_slides', 'inquiry_based'));
    }

    public function test_preflight_lists_only_the_item_stored_for_the_exact_choice(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        $this->row(1, 'Classroom Presentation', 'study_deck_inquiry_based__water_aaaa.pptx');
        $this->row(2, 'Classroom Presentation', 'study_deck_experiential__water_bbbb.pptx');
        $this->row(3, 'Interactive', 'study_story_inquiry_based__water_cccc.pptx');
        $this->row(4, 'Classroom Presentation', 'study_deck_water_legacy.pptx');
        $ask = fn (string $method, string $format) => $this->asTeacher()->getJson(self::BASE . 'preflight?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => $method, 'format' => $format]))->assertOk()->json();

        $a = $ask('inquiry_based', 'interactive_slides');
        $this->assertSame([1], array_column($a['existing_resources'], 'ref'));
        $this->assertTrue($a['fit']['duplicate']);

        $b = $ask('experiential', 'interactive_slides');
        $this->assertSame([2], array_column($b['existing_resources'], 'ref'));

        $story = $ask('inquiry_based', 'branching_scenario');
        $this->assertSame([3], array_column($story['existing_resources'], 'ref'));

        // A combination nothing was stored for: nothing is listed (not the legacy deck, not another method) and Create is not blocked as a duplicate.
        $none = $ask('problem_based', 'interactive_slides');
        $this->assertSame([], $none['existing_resources']);
        $this->assertFalse($none['fit']['duplicate']);
        $noneStory = $ask('experiential', 'branching_scenario');
        $this->assertSame([], $noneStory['existing_resources']);
        $this->assertFalse($noneStory['fit']['duplicate']);
    }

    public function test_the_stored_list_of_another_school_is_never_shown(): void
    {
        $this->seedPilotChapter();
        $this->row(1, 'Classroom Presentation', 'study_deck_inquiry_based__water_aaaa.pptx', 1, self::OTHER_TENANT);
        $res = $this->asTeacher()->getJson(self::BASE . 'preflight?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides']))->assertOk()->json();

        $this->assertSame([], $res['existing_resources']);
        $this->assertFalse($res['fit']['duplicate']);
    }

    public function test_the_player_api_serves_a_method_deck_only_by_its_id_and_never_as_the_default(): void
    {
        $this->row(1, 'Classroom Presentation', 'study_deck_water_legacy.pptx');
        $this->row(2, 'Classroom Presentation', 'study_deck_inquiry_based__water_bbbb.pptx');
        $this->row(3, 'Classroom Presentation', 'study_deck_explicit_instruction__water_cccc.pptx');
        $deck = fn (string $title) => json_encode(['version' => 3, 'chapter' => ['id' => self::CHAPTER], 'slides' => [['n' => 1, 'title' => $title]], 'concepts' => [], 'outline' => []]);
        foreach ([1 => 'legacy', 2 => 'inquiry', 3 => 'explicit'] as $id => $title) {
            Storage::disk('digitalocean')->put(ContentGenerationService::studyDeckSidecarPath(DB::table('content_master')->where('id', $id)->value('filename')), $deck($title));
        }
        DB::table('school_setup')->insert(['Id' => self::TENANT, 'is_Lms' => 'N']);
        DB::table('chapter_master')->insert(['id' => self::CHAPTER, 'chapter_name' => 'Water', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT]);
        $ask = fn (array $extra) => $this->postJson('/api/lms-study-deck', ['chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT] + $extra);

        $this->assertSame('inquiry', $ask(['content_id' => 2])->assertOk()->json('data.slides.0.title'));
        $this->assertSame('explicit', $ask(['content_id' => 3])->assertOk()->json('data.slides.0.title'));
        $this->assertSame('legacy', $ask([])->assertOk()->json('data.slides.0.title'), 'asked for without an id: the deck stored without a method, never "the latest" of a method');

        // With only method decks stored there is no default deck at all.
        DB::table('content_master')->where('id', 1)->update(['show_hide' => 0]);
        $ask([])->assertStatus(404);
        $this->assertSame('inquiry', $ask(['content_id' => 2])->assertOk()->json('data.slides.0.title'));
    }
    public function test_the_shipped_scope_is_the_four_verified_std4_evs_chapters_of_school_341_and_nothing_near_them(): void
    {
        $scope = new \App\Services\CoursePresentation\PilotScope();
        $chapter = fn (int $id, int $standard = 4258, int $subject = 48385, int $school = 341) => ['id' => $id, 'standard_id' => $standard, 'subject_id' => $subject, 'sub_institute_id' => $school];

        foreach ([41675, 41676, 41677, 41678] as $id) {
            $this->assertTrue($scope->allows($chapter($id)), "chapter $id");
            $this->assertFalse($scope->allows($chapter($id, school: 1)), "chapter $id of another school");
            $this->assertFalse($scope->allows($chapter($id, school: 342)), "chapter $id of a neighbouring school");
            $this->assertFalse($scope->allows($chapter($id, standard: 4257)), "chapter $id with another standard");
            $this->assertFalse($scope->allows($chapter($id, subject: 48386)), "chapter $id with another subject");
        }
        $this->assertFalse($scope->allows($chapter(41679)), 'a neighbouring chapter');
        $this->assertFalse($scope->allows($chapter(41674)), 'a neighbouring chapter');
    }
    public function test_a_chapter_without_learning_outcomes_is_neither_blocked_nor_told_about_them(): void
    {
        $this->seedPilotChapter();
        $this->seedStrategies();
        $with = $this->asTeacher()->getJson(self::BASE . 'preflight?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides']))->assertOk()->json();
        $this->assertNotEmpty($with['coverage']['learning_outcomes'], 'a school that stored outcomes still sees the note');

        DB::table('lms_learning_outcomes')->delete();
        $res = $this->asTeacher()->getJson(self::BASE . 'preflight?' . http_build_query(['chapter_id' => self::CHAPTER, 'pedagogy' => 'inquiry_based', 'format' => 'interactive_slides']))->assertOk()->json();

        $this->assertNull($res['coverage']['learning_outcomes'], 'no note about outcomes the chapter does not have');
        $this->assertSame([], array_values(array_intersect(array_column($res['sources'], 'key'), ['outcomes', 'curriculum'])), 'neither listed as a missing source');
        $this->assertSame([], $res['fit']['missing_sources']);
        $this->assertStringNotContainsString('utcome', implode(' ', $res['fit']['generation_blockers']));
        $this->assertTrue($res['fit']['continue_allowed']);
    }
}
