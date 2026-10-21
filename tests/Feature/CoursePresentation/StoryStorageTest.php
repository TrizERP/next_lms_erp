<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\Story\StoryTemplate;
use App\Services\StudyDeck\StudyDeckPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * A story lesson is stored as its OWN content item and can never hide, replace or alter a Classroom Presentation.
 * Throwaway sqlite and a fake object store; the shared database and the real bucket are never touched.
 */
class StoryStorageTest extends CoursePresentationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('content_master', function ($t) {
            $t->string('file_type')->nullable();
        });
        Storage::fake('digitalocean');
        config(['claude.source_label' => 'Claude AI']);
    }

    private function row(int $id, string $category, string $filename, int $show = 1): void
    {
        DB::table('content_master')->insert(['id' => $id, 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT,
            'content_category' => $category, 'title' => $filename, 'filename' => $filename, 'show_hide' => $show, 'source' => 'Claude AI', 'file_type' => 'pptx']);
    }

    private function service(): object
    {
        return new class extends ContentGenerationService {
            public function hide(int $chapter, int $tenant, int $keep, string $filename): int
            {
                return $this->hidePreviousStudyDecks($chapter, $tenant, $keep, $this->studyDeckLikePrefix($filename));
            }

            public function restore(int $id, int $chapter, int $tenant): bool
            {
                return $this->restoreStudyDeckRow($id, $chapter, $tenant);
            }
        };
    }

    private function visible(): array
    {
        return DB::table('content_master')->where('show_hide', 1)->orderBy('id')->pluck('filename', 'id')->all();
    }

    private function storyDeck(): array
    {
        $f = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/story-act-chapter8592.json'), true);

        return ['slides' => [['n' => 1, 'slide_type' => 'cover', 'interaction' => null], ['n' => 2, 'slide_type' => 'scenario', 'interaction' => $f['interaction']]]];
    }

    public function test_a_story_lesson_has_its_own_file_name_and_a_presentation_keeps_its_own(): void
    {
        $story = StudyDeckPublisher::filenameFor('Exploration', $this->storyDeck());
        $slides = StudyDeckPublisher::filenameFor('Exploration', ['slides' => [['n' => 1, 'slide_type' => 'cover', 'interaction' => null]]]);

        $this->assertStringStartsWith('study_story_exploration_', $story);
        $this->assertStringStartsWith('study_deck_exploration_', $slides);
        $this->assertTrue(StoryTemplate::isStory($this->storyDeck()));
        $this->assertFalse(StoryTemplate::isStory(['slides' => [['interaction' => ['kind' => 'scenario']]]]), 'an ordinary scenario is not a story lesson');
        // Both open in the study-deck player with their own content id.
        $this->assertSame('/student/study-deck/8592?content=70', ContentGenerationService::studyDeckDeepLink(['id' => 70, 'chapter_id' => 8592, 'filename' => $story]));
        $this->assertTrue(ContentGenerationService::isStudyDeckFilename($story));
    }

    public function test_the_real_publish_path_names_a_story_lesson_separately_and_reports_the_presentation_as_a_different_item(): void
    {
        $this->row(1, 'Classroom Presentation', 'study_deck_exploration_aaaa.pptx');
        $service = new ContentGenerationService();
        $input = ['chapter' => (object) ['id' => self::CHAPTER], 'chapter_name' => 'Exploration', 'sub_institute_id' => self::TENANT];

        // A dry run goes through the same prepare/name/lookup steps as the real publish and writes nothing.
        $noPictures = $this->storyDeck();
        foreach ($noPictures['slides'][1]['interaction']['nodes'] as &$n) { unset($n['image'], $n['extra_images']); }
        unset($n);
        $story = $service->publishStudyDeck($input, $noPictures, '<section class="cover"></section>', null, true);
        $this->assertSame(200, $story["http"], json_encode($story["body"]));
        $this->assertStringStartsWith('study_story_exploration_', $story['body']['data']['filename'] ?? $story['body']['filename']);
        $this->assertSame('would_create', $story['body']['status']);
        $this->assertNull($story['body']['content_id'], 'the presentation row is not what a story lesson matches');

        $slides = $service->publishStudyDeck($input, ['slides' => [['n' => 1, 'slide_type' => 'cover', 'interaction' => null]]], '<section class="cover"></section>', null, true);
        $this->assertStringStartsWith('study_deck_exploration_', $slides['body']['data']['filename'] ?? $slides['body']['filename']);
        $this->assertSame(['study_deck_exploration_aaaa.pptx'], array_values(DB::table('content_master')->pluck('filename')->all()), 'a dry run wrote nothing');
    }

    public function test_storing_a_story_lesson_never_hides_the_classroom_presentation(): void
    {
        $this->row(1, 'Classroom Presentation', 'study_deck_exploration_aaaa.pptx');
        $this->row(2, 'Interactive', 'study_story_exploration_bbbb.pptx');
        $hidden = $this->service()->hide(self::CHAPTER, self::TENANT, 2, 'study_story_exploration_bbbb.pptx');

        $this->assertSame(0, $hidden);
        $this->assertSame([1 => 'study_deck_exploration_aaaa.pptx', 2 => 'study_story_exploration_bbbb.pptx'], $this->visible());
    }

    public function test_a_new_story_lesson_replaces_only_an_older_story_lesson(): void
    {
        $this->row(1, 'Classroom Presentation', 'study_deck_exploration_aaaa.pptx');
        $this->row(2, 'Interactive', 'study_story_exploration_old.pptx');
        $this->row(3, 'Interactive', 'study_story_exploration_new.pptx');

        $this->assertSame(1, $this->service()->hide(self::CHAPTER, self::TENANT, 3, 'study_story_exploration_new.pptx'));
        $this->assertSame([1 => 'study_deck_exploration_aaaa.pptx', 3 => 'study_story_exploration_new.pptx'], $this->visible());
    }

    public function test_a_new_presentation_does_not_hide_a_story_lesson_and_restoring_keeps_the_kinds_apart(): void
    {
        $this->row(1, 'Classroom Presentation', 'study_deck_exploration_old.pptx');
        $this->row(2, 'Classroom Presentation', 'study_deck_exploration_new.pptx');
        $this->row(3, 'Interactive', 'study_story_exploration_s.pptx');

        $this->assertSame(1, $this->service()->hide(self::CHAPTER, self::TENANT, 2, 'study_deck_exploration_new.pptx'));
        $this->assertSame([2 => 'study_deck_exploration_new.pptx', 3 => 'study_story_exploration_s.pptx'], $this->visible());

        // Bringing the old presentation back hides the other presentation, and leaves the story alone.
        DB::table('content_master')->where('id', 1)->update(['show_hide' => 0]);
        $this->assertTrue($this->service()->restore(1, self::CHAPTER, self::TENANT));
        $this->assertSame([1 => 'study_deck_exploration_old.pptx', 3 => 'study_story_exploration_s.pptx'], $this->visible());
    }

    public function test_other_chapters_and_schools_are_never_touched(): void
    {
        $this->row(1, 'Interactive', 'study_story_exploration_old.pptx');
        DB::table('content_master')->insert(['id' => 9, 'chapter_id' => 9999, 'sub_institute_id' => self::TENANT, 'content_category' => 'Interactive', 'filename' => 'study_story_other_chapter.pptx', 'show_hide' => 1, 'source' => 'Claude AI', 'file_type' => 'pptx']);
        DB::table('content_master')->insert(['id' => 10, 'chapter_id' => self::CHAPTER, 'sub_institute_id' => self::OTHER_TENANT, 'content_category' => 'Interactive', 'filename' => 'study_story_other_school.pptx', 'show_hide' => 1, 'source' => 'Claude AI', 'file_type' => 'pptx']);
        $this->row(2, 'Interactive', 'study_story_exploration_new.pptx');

        $this->service()->hide(self::CHAPTER, self::TENANT, 2, 'study_story_exploration_new.pptx');

        $this->assertSame([2 => 'study_story_exploration_new.pptx', 9 => 'study_story_other_chapter.pptx', 10 => 'study_story_other_school.pptx'], $this->visible());
    }

    public function test_the_default_deck_stays_the_presentation_and_a_story_opens_only_by_its_content_id(): void
    {
        $this->row(1, 'Classroom Presentation', 'study_deck_exploration_aaaa.pptx');
        $this->row(2, 'Interactive', 'study_story_exploration_bbbb.pptx');
        $deck = fn (string $title) => json_encode(['version' => 3, 'chapter' => ['id' => 8592], 'slides' => [['n' => 1, 'title' => $title]], 'concepts' => [], 'outline' => []]);
        Storage::disk('digitalocean')->put(ContentGenerationService::studyDeckSidecarPath('study_deck_exploration_aaaa.pptx'), $deck('presentation'));
        Storage::disk('digitalocean')->put(ContentGenerationService::studyDeckSidecarPath('study_story_exploration_bbbb.pptx'), $deck('story'));
        DB::table('school_setup')->insert(['Id' => self::TENANT, 'is_Lms' => 'N']);
        DB::table('chapter_master')->insert(['id' => self::CHAPTER, 'chapter_name' => 'Exploration', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT]);

        $ask = fn (array $extra) => $this->postJson('/api/lms-study-deck', ['chapter_id' => self::CHAPTER, 'sub_institute_id' => self::TENANT] + $extra);

        $this->assertSame('presentation', $ask([])->assertOk()->json('data.slides.0.title'), 'asked for the chapter\'s deck without an id: still the presentation');
        $this->assertSame('story', $ask(['content_id' => 2])->assertOk()->json('data.slides.0.title'));
        $this->assertSame('presentation', $ask(['content_id' => 1])->assertOk()->json('data.slides.0.title'));

        // A hidden story lesson is not served.
        DB::table('content_master')->where('id', 2)->update(['show_hide' => 0]);
        $ask(['content_id' => 2])->assertStatus(404);
        // Nor another school's.
        DB::table('content_master')->where('id', 2)->update(['show_hide' => 1, 'sub_institute_id' => self::OTHER_TENANT]);
        $ask(['content_id' => 2])->assertStatus(404);
    }
}
