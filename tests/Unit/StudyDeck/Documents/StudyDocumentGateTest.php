<?php

namespace Tests\Unit\StudyDeck\Documents;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\Documents\DocumentKind;
use PHPUnit\Framework\TestCase;
use Tests\Unit\H5P\Branching\BootsMiniApp;

/**
 * Which chapters are written as study documents (revision notes, a remedial class, classroom activities).
 *
 * The gate is its own list, `claude.study_document_chapter_ids`, so opening these three to every chapter does not also move
 * presentations and every other content type from Gamma/Gemini to Claude (that is `claude.chapter_ids`). No database and no
 * model: the gate is decided from configuration alone.
 */
class StudyDocumentGateTest extends TestCase
{
    use BootsMiniApp;

    protected function setUp(): void
    {
        $this->bootMiniApp();
    }

    protected function tearDown(): void
    {
        $this->shutDownMiniApp();
    }

    private function service(): ContentGenerationService
    {
        return new ContentGenerationService();
    }

    public function test_every_chapter_is_served_by_a_star_without_serving_the_other_content_types(): void
    {
        config(['claude.study_documents' => true, 'claude.chapter_ids' => '', 'claude.study_document_chapter_ids' => '*']);

        $service = $this->service();

        $this->assertTrue($service->servesStudyDocuments(8593));
        $this->assertTrue($service->servesStudyDocuments(41675));
        $this->assertFalse($service->handles(8593), 'presentations and the rest must keep their own gate');
        $this->assertSame(DocumentKind::RevisionNotes, $service->studyDocumentKindFor('Revision Notes', 8593));
        $this->assertSame(DocumentKind::Remedial, $service->studyDocumentKindFor('Remedial Class', 41675));
        $this->assertSame(DocumentKind::Activities, $service->studyDocumentKindFor('Classroom Activity', 8593));
        $this->assertNull($service->studyDocumentKindFor('Presentation', 8593));
    }

    public function test_a_list_serves_only_the_chapters_in_it(): void
    {
        config(['claude.study_documents' => true, 'claude.chapter_ids' => '', 'claude.study_document_chapter_ids' => '8592, 8593']);

        $service = $this->service();

        $this->assertTrue($service->servesStudyDocuments(8593));
        $this->assertFalse($service->servesStudyDocuments(8614));
        $this->assertNull($service->studyDocumentKindFor('Revision Notes', 8614));
    }

    public function test_an_empty_list_serves_none_but_a_chapter_claude_already_serves_keeps_them(): void
    {
        config(['claude.study_documents' => true, 'claude.chapter_ids' => '8592', 'claude.study_document_chapter_ids' => '']);

        $service = $this->service();

        $this->assertFalse($service->servesStudyDocuments(8593));
        $this->assertTrue($service->servesStudyDocuments(8592), 'opening study documents to more chapters never removes them from one that had them');
    }

    public function test_the_switch_turns_every_chapter_back_to_the_single_prompt_documents(): void
    {
        config(['claude.study_documents' => false, 'claude.chapter_ids' => '*', 'claude.study_document_chapter_ids' => '*']);

        $this->assertNull($this->service()->studyDocumentKindFor('Revision Notes', 8593));
    }

    public function test_a_chapter_id_that_is_not_positive_is_never_served(): void
    {
        config(['claude.study_documents' => true, 'claude.chapter_ids' => '*', 'claude.study_document_chapter_ids' => '*']);

        $this->assertFalse($this->service()->servesStudyDocuments(0));
        $this->assertFalse($this->service()->servesStudyDocuments(-3));
    }
}
