<?php

namespace Tests\Feature\Pal;

use App\Models\PAL\QuestionMetadata;
use App\Services\PAL\Examination\ExamBlueprintService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Board exam blueprint feasibility.
 *
 * The engine reports whether the tagged pool can fill a paper pattern and
 * where it is short. It deliberately emits no questions: selecting items needs
 * the PAL-vs-Examination reservation rule, which is still an open decision
 * (tracker #6).
 */
class PalExamBlueprintTest extends TestCase
{
    use DatabaseTransactions;

    private ExamBlueprintService $service;

    private int $subInstituteId;

    private int $standardId;

    private int $subjectId;

    private int $chapterId;

    private int $questionCounter = 9900000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ExamBlueprintService::class);

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Blueprint School',
            'ShortCode' => 'BP' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'blueprint@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'blueprint@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Blueprint Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $this->standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1,
            'name' => '10',
            'short_name' => '10',
            'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $this->chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $this->subjectId,
            'standard_id' => $this->standardId,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'Blueprint Chapter',
            'created_at' => now(),
        ]);

        // A tiny blueprint, so the fixtures needed to fill it stay readable.
        config()->set('pal_exam_blueprint.blueprints.TESTBOARD.small', [
            'label' => 'Small test paper',
            'total_marks' => 11,
            'sections' => [
                ['section' => 'A', 'blueprint_category' => 'mcq', 'count' => 2, 'marks_each' => 1],
                ['section' => 'B', 'blueprint_category' => 'short_answer', 'count' => 3, 'marks_each' => 3],
            ],
        ]);
    }

    /** A board-compliant tagged item. */
    private function item(string $category, float $marks, array $extra = []): QuestionMetadata
    {
        return QuestionMetadata::create(array_merge([
            'question_id' => $this->questionCounter++,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_ref_id' => $this->chapterId,
            'quality_status' => 'approved',
            'board' => 'TESTBOARD',
            'blueprint_category' => $category,
            'marks' => $marks,
        ], $extra));
    }

    private function feasibility(): array
    {
        return $this->service->feasibility(
            $this->subInstituteId,
            'TESTBOARD',
            $this->standardId,
            $this->subjectId,
            'small'
        );
    }

    public function test_an_empty_pool_reports_the_exact_shortfall_rather_than_just_failing(): void
    {
        $report = $this->feasibility();

        $this->assertFalse($report['feasible']);

        $sections = collect($report['sections'])->keyBy('blueprint_category');
        $this->assertSame(2, $sections['mcq']['shortfall']);
        $this->assertSame(3, $sections['short_answer']['shortfall']);

        $this->assertSame(
            11.0,
            $report['marks']['unfillable'],
            'The whole paper is unfillable, and saying so in marks is what tells authoring how much work is actually outstanding.'
        );
    }

    public function test_a_fully_stocked_pool_is_feasible(): void
    {
        $this->item('mcq', 1.0);
        $this->item('mcq', 1.0);
        $this->item('short_answer', 3.0);
        $this->item('short_answer', 3.0);
        $this->item('short_answer', 3.0);

        $report = $this->feasibility();

        $this->assertTrue($report['feasible']);
        $this->assertSame(0.0, $report['marks']['unfillable']);
    }

    public function test_an_item_worth_the_wrong_marks_cannot_fill_the_slot(): void
    {
        // Right category, wrong weight: a 5-mark short answer does not fit a
        // 3-mark slot without changing the paper.
        $this->item('short_answer', 5.0);
        $this->item('short_answer', 5.0);
        $this->item('short_answer', 5.0);

        $sections = collect($this->feasibility()['sections'])->keyBy('blueprint_category');

        $this->assertSame(0, $sections['short_answer']['available']);
        $this->assertSame(3, $sections['short_answer']['shortfall']);
    }

    public function test_an_unapproved_item_is_not_counted_as_available(): void
    {
        $this->item('mcq', 1.0, ['quality_status' => 'draft']);
        $this->item('mcq', 1.0);

        $sections = collect($this->feasibility()['sections'])->keyBy('blueprint_category');

        $this->assertSame(1, $sections['mcq']['available']);
        $this->assertSame(1, $sections['mcq']['shortfall']);
    }

    public function test_another_boards_items_do_not_fill_this_boards_paper(): void
    {
        $this->item('mcq', 1.0, ['board' => 'OTHERBOARD']);
        $this->item('mcq', 1.0, ['board' => 'OTHERBOARD']);

        $sections = collect($this->feasibility()['sections'])->keyBy('blueprint_category');

        $this->assertSame(0, $sections['mcq']['available']);
    }

    public function test_uncalibrated_items_still_fill_the_paper_and_are_reported_separately(): void
    {
        // Board-fit, no psychometrics — every newly authored item.
        $this->item('mcq', 1.0);
        $this->item('mcq', 1.0);

        $sections = collect($this->feasibility()['sections'])->keyBy('blueprint_category');

        $this->assertSame(2, $sections['mcq']['available']);
        $this->assertSame(0, $sections['mcq']['shortfall']);
        $this->assertSame(
            0,
            $sections['mcq']['calibrated_available'],
            'A paper made of uncalibrated items is still a valid paper. Requiring psychometrics here would exclude every newly authored question from the paper it was written for.'
        );
    }

    public function test_the_difficulty_mix_of_the_available_pool_is_reported(): void
    {
        $this->item('mcq', 1.0, ['difficulty_1_to_5' => 2]);
        $this->item('mcq', 1.0);

        $sections = collect($this->feasibility()['sections'])->keyBy('blueprint_category');

        $this->assertSame(1, $sections['mcq']['difficulty_available'][2]);
        $this->assertSame(
            1,
            $sections['mcq']['difficulty_available']['unrated'],
            'Unrated items are counted as unrated rather than folded into a band — most of the estate has no difficulty yet, and guessing one would fake the spread.'
        );
    }

    public function test_an_unknown_blueprint_is_reported_with_what_is_available(): void
    {
        $report = $this->service->feasibility(
            $this->subInstituteId,
            'TESTBOARD',
            $this->standardId,
            $this->subjectId,
            'no_such_pattern'
        );

        $this->assertSame('unknown_blueprint', $report['error']);
        $this->assertContains('small', $report['available_blueprints']);
    }

    public function test_a_section_naming_an_unregistered_category_is_a_config_error_not_an_empty_section(): void
    {
        config()->set('pal_exam_blueprint.blueprints.TESTBOARD.broken', [
            'label' => 'Broken pattern',
            'total_marks' => 5,
            'sections' => [
                ['section' => 'A', 'blueprint_category' => 'not_a_real_category', 'count' => 5, 'marks_each' => 1],
            ],
        ]);

        $report = $this->service->feasibility(
            $this->subInstituteId,
            'TESTBOARD',
            $this->standardId,
            $this->subjectId,
            'broken'
        );

        $this->assertSame('unregistered_category', $report['sections'][0]['error']);
        $this->assertFalse(
            $report['feasible'],
            'A typo in the pattern must not read as "we happen to have no items of this type".'
        );
    }

    public function test_the_section_total_is_reported_alongside_the_stated_total(): void
    {
        // The fixture's sections sum to 2*1 + 3*3 = 11, matching its stated
        // total. Both are reported so a pattern whose sections do not add up
        // is visible rather than silently trusted.
        $report = $this->feasibility();

        $this->assertSame(11.0, $report['marks']['blueprint_total']);
        $this->assertSame(11.0, $report['marks']['sections_total']);
    }

    public function test_the_report_never_returns_questions(): void
    {
        $this->item('mcq', 1.0);

        $encoded = json_encode($this->feasibility());

        $this->assertStringNotContainsString(
            'question_id',
            $encoded,
            'Selecting items requires the exam-reservation rule (#6), which is undecided. Emitting them here would settle that decision silently and risk serving a student an item they had already met as practice.'
        );
    }
}
