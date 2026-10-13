<?php

namespace Tests\Unit\StudyDeck;

use App\Services\ContentGenerationService;
use PHPUnit\Framework\TestCase;

/**
 * Storage rules for a study deck. No database and no Spaces: these read the code and
 * the pure path helper, because the rules are about what the code is allowed to write.
 */
class StudyDeckStorageTest extends TestCase
{
    public function test_the_interactive_deck_lives_beside_the_presentation_and_is_named_after_it(): void
    {
        $this->assertSame(
            'public/lms_content_file/classroom_presentation_exploration_1790000000.pptx.deck.json',
            ContentGenerationService::studyDeckSidecarPath('classroom_presentation_exploration_1790000000.pptx')
        );
    }

    public function test_no_meta_tags_marker_is_written_for_a_study_deck(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../app/Services/ContentGenerationService.php');

        $this->assertStringNotContainsString("'studydeck:v1'", $source);
        $this->assertStringContainsString("\$input['meta_tags'] = null;", $source);
    }

    public function test_the_study_deck_writes_no_h5p_rows_and_touches_no_question_columns(): void
    {
        foreach (glob(__DIR__ . '/../../../app/Services/StudyDeck/*.php') as $file) {
            $code = file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression('/h5p_(course_presentation|presentation_slides|slide_elements|content_type|content_id|payload)/', $code, basename($file) . ' must not name an h5p_* table or question column');
            $this->assertDoesNotMatchRegularExpression('/DB::table\([^)]*\)->(insert|update|delete)|->insert\(|->updateOrInsert\(/', $code, basename($file) . ' must not write to the database');
        }
    }
}
