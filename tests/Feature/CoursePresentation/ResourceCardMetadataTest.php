<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\ContentGenerationService;
use App\Services\CoursePresentation\GenerationRun;

/**
 * What the Classroom Resource cards say about a stored deck: its teaching method and content format come from its own file
 * name (never guessed), and its generation run from the run that recorded storing it. Scratch folder and in-memory sqlite only.
 */
class ResourceCardMetadataTest extends CoursePresentationTestCase
{
    private function writeRun(string $run, array $over = []): void
    {
        $dir = storage_path($this->runDir . '/chapter-' . self::CHAPTER . '/' . $run);
        @mkdir($dir, 0775, true);
        file_put_contents($dir . '/status.json', json_encode($over + [
            'run' => $run, 'chapter_id' => self::CHAPTER, 'tenant' => self::TENANT, 'state' => 'stored', 'method' => 'inquiry_based',
            'format' => 'interactive_slides', 'created_at' => '2026-10-20T10:00:00+00:00', 'stored' => ['content_id' => 70],
        ]));
    }

    public function test_method_and_format_are_read_from_the_file_name(): void
    {
        $slides = ContentGenerationService::studyDeckResource(['filename' => 'study_deck_inquiry_based__global_water_resources_0123456789ab.pptx']);
        $this->assertSame(['teaching_method' => 'inquiry_based', 'content_format' => 'interactive_slides'], $slides);

        $story = ContentGenerationService::studyDeckResource(['filename' => 'study_story_inquiry_based__global_water_resources_0123456789ab.pptx']);
        $this->assertSame(['teaching_method' => 'inquiry_based', 'content_format' => 'branching_scenario'], $story);
    }

    public function test_a_deck_stored_without_a_method_has_none_and_other_rows_have_no_resource(): void
    {
        $this->assertSame(['teaching_method' => null, 'content_format' => 'interactive_slides'],
            ContentGenerationService::studyDeckResource(['filename' => 'study_deck_global_water_resources_0123456789ab.pptx']));
        $this->assertNull(ContentGenerationService::studyDeckResource(['filename' => 'classroom_presentation_x_1.pptx']));
        $this->assertNull(ContentGenerationService::studyDeckResource(['filename' => 'study_deck_x.pdf']));
        $this->assertNull(ContentGenerationService::studyDeckResource([]));
    }

    public function test_every_teaching_method_has_a_short_card_name(): void
    {
        $this->assertSame(array_keys(config('course_presentation_pilot.pedagogies')), array_keys(config('course_presentation_pilot.pedagogy_short')));
        $this->assertSame('Inquiry-Based', config('course_presentation_pilot.pedagogy_short.inquiry_based'));
    }

    public function test_the_run_that_stored_an_item_is_found_by_content_id_only(): void
    {
        $this->writeRun('r000000000001', ['stored' => ['content_id' => 70]]);
        $this->writeRun('r000000000002', ['format' => 'branching_scenario', 'created_at' => '2026-10-21T10:00:00+00:00', 'stored' => ['content_id' => 71]]);
        $this->writeRun('r000000000003', ['state' => 'ready', 'created_at' => '2026-10-22T10:00:00+00:00', 'stored' => ['content_id' => 72]]);
        $runs = new GenerationRun();

        $this->assertSame('r000000000001', $runs->runStoring(self::CHAPTER, self::TENANT, 70));
        $this->assertSame('r000000000002', $runs->runStoring(self::CHAPTER, self::TENANT, 71));
        $this->assertNull($runs->runStoring(self::CHAPTER, self::TENANT, 72), 'a draft that is not stored has stored nothing');
        $this->assertNull($runs->runStoring(self::CHAPTER, self::TENANT, 99), 'a hand-stored item has no run');
        $this->assertNull($runs->runStoring(self::CHAPTER, 2, 70), 'another school never sees this run');
        $this->assertNull($runs->runStoring(9999, self::TENANT, 70), 'another chapter never sees this run');
    }
}
