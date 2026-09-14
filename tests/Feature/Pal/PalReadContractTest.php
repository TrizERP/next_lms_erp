<?php

namespace Tests\Feature\Pal;

use App\Models\PAL\ContentMetadata;
use App\Models\PAL\MisconceptionCorrective;
use App\Models\PAL\MisconceptionLibrary;
use App\Models\PAL\QuestionMetadata;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Read contract scopes — forPal(), forExamination().
 *
 * Each consumer's fitness rule lives in one named scope so callers cannot
 * silently drift from the definition. The scopes are thin wrappers; the value
 * is that the intent is explicit in the query chain.
 */
class PalReadContractTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;

    private int $questionCounter = 9900000;

    private int $contentCounter = 9900000;

    private int $misconceptionCounter = 9900000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Read Contract School',
            'ShortCode' => 'RC' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'readcontract@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'readcontract@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);
    }

    private function questionRow(array $attributes): QuestionMetadata
    {
        return QuestionMetadata::create(array_merge([
            'question_id' => $this->questionCounter++,
            'sub_institute_id' => $this->subInstituteId,
            'quality_status' => 'approved',
        ], $attributes));
    }

    private function contentRow(array $attributes): ContentMetadata
    {
        return ContentMetadata::create(array_merge([
            'content_master_id' => $this->contentCounter++,
            'sub_institute_id' => $this->subInstituteId,
            'quality_status' => 'approved',
            'content_type' => 'concept',
        ], $attributes));
    }

    private function misconceptionRow(array $attributes): MisconceptionLibrary
    {
        return MisconceptionLibrary::create(array_merge([
            'sub_institute_id' => $this->subInstituteId,
            'quality_status' => 'approved',
            'tag' => 'rc_tag_' . $this->misconceptionCounter++,
        ], $attributes));
    }

    private function correctiveRow(array $attributes): MisconceptionCorrective
    {
        return MisconceptionCorrective::create(array_merge([
            'misconception_id' => $this->misconceptionCounter,
            'sub_institute_id' => $this->subInstituteId,
            'quality_status' => 'approved',
        ], $attributes));
    }

    // ── QuestionMetadata ──────────────────────────────────────────────────────

    public function test_forPal_returns_only_approved_items(): void
    {
        $approved = $this->questionRow(['quality_status' => 'approved']);
        $this->questionRow(['quality_status' => 'draft']);

        $this->assertTrue(
            QuestionMetadata::forTenant($this->subInstituteId)->forPal()->where('question_id', $approved->question_id)->exists()
        );
        $this->assertFalse(
            QuestionMetadata::forTenant($this->subInstituteId)->forPal()->where('question_id', $approved->question_id + 1)->exists()
        );
    }

    public function test_forPal_matches_servable_identically(): void
    {
        $this->questionRow(['quality_status' => 'approved']);
        $this->questionRow(['quality_status' => 'draft']);

        $this->assertSame(
            QuestionMetadata::forTenant($this->subInstituteId)->servable()->count(),
            QuestionMetadata::forTenant($this->subInstituteId)->forPal()->count()
        );
    }

    public function test_forExamination_requires_board_compliance(): void
    {
        $this->questionRow(['quality_status' => 'approved']);
        $boardFit = $this->questionRow(['quality_status' => 'approved', 'board' => 'CBSE', 'blueprint_category' => 'mcq', 'marks' => 1.0]);
        $this->questionRow(['quality_status' => 'approved', 'board' => 'CBSE', 'blueprint_category' => null, 'marks' => 1.0]);
        $this->questionRow(['quality_status' => 'approved', 'board' => 'CBSE', 'blueprint_category' => 'mcq', 'marks' => 0.0]);
        $this->questionRow(['quality_status' => 'draft', 'board' => 'CBSE', 'blueprint_category' => 'mcq', 'marks' => 1.0]);

        $this->assertTrue(
            QuestionMetadata::forTenant($this->subInstituteId)->forExamination()->where('question_id', $boardFit->question_id)->exists()
        );
        $this->assertSame(1, QuestionMetadata::forTenant($this->subInstituteId)->forExamination()->count());
    }

    public function test_forExamination_excludes_unapproved_even_if_board_fit(): void
    {
        $this->questionRow(['quality_status' => 'draft', 'board' => 'CBSE', 'blueprint_category' => 'mcq', 'marks' => 1.0]);

        $this->assertSame(0, QuestionMetadata::forTenant($this->subInstituteId)->forExamination()->count());
    }

    // ── ContentMetadata ───────────────────────────────────────────────────────

    public function test_forPal_on_content_returns_only_approved(): void
    {
        $approved = $this->contentRow(['quality_status' => 'approved']);
        $this->contentRow(['quality_status' => 'draft']);

        $this->assertTrue(
            ContentMetadata::forTenant($this->subInstituteId)->forPal()->where('content_master_id', $approved->content_master_id)->exists()
        );
        $this->assertFalse(
            ContentMetadata::forTenant($this->subInstituteId)->forPal()->where('content_master_id', $approved->content_master_id + 1)->exists()
        );
    }

    public function test_forPal_on_content_matches_servable(): void
    {
        $this->contentRow(['quality_status' => 'approved']);
        $this->contentRow(['quality_status' => 'draft']);

        $this->assertSame(
            ContentMetadata::forTenant($this->subInstituteId)->servable()->count(),
            ContentMetadata::forTenant($this->subInstituteId)->forPal()->count()
        );
    }

    // ── MisconceptionLibrary ──────────────────────────────────────────────────

    public function test_forPal_on_misconception_returns_only_approved(): void
    {
        $approved = $this->misconceptionRow(['quality_status' => 'approved']);
        $this->misconceptionRow(['quality_status' => 'draft']);

        $this->assertTrue(
            MisconceptionLibrary::forTenant($this->subInstituteId)->forPal()->where('tag', $approved->tag)->exists()
        );
        $this->assertFalse(
            MisconceptionLibrary::forTenant($this->subInstituteId)->forPal()->where('tag', 'rc_tag_' . ($this->misconceptionCounter + 1))->exists()
        );
    }

    // ── MisconceptionCorrective ───────────────────────────────────────────────

    public function test_forPal_on_corrective_returns_only_approved(): void
    {
        $approved = $this->correctiveRow(['quality_status' => 'approved']);
        $this->correctiveRow(['quality_status' => 'draft']);

        $this->assertTrue(
            MisconceptionCorrective::forTenant($this->subInstituteId)->forPal()->where('id', $approved->id)->exists()
        );
        $this->assertFalse(
            MisconceptionCorrective::forTenant($this->subInstituteId)->forPal()->where('id', $approved->id + 1)->exists()
        );
    }
}
