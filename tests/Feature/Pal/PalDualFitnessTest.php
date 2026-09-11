<?php

namespace Tests\Feature\Pal;

use App\Models\PAL\QuestionMetadata;
use App\Services\PAL\Content\PalVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dual fitness for purpose — one item, two independent criteria.
 *
 * "Calibrated for PAL" and "compliant for a board paper" measure different
 * things. Collapsing them into a single quality score is what makes one shared
 * Question Intelligence Engine look impossible; kept apart, one engine serves
 * both consumers because each asks its own question of the same row.
 *
 * The four combinations below are the whole point, so each is asserted.
 */
class PalDualFitnessTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId = 0;

    private int $questionCounter = 8800000;

    /**
     * A metadata row with exactly the fields the test cares about.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function row(array $attributes): QuestionMetadata
    {
        return QuestionMetadata::create(array_merge([
            'question_id' => $this->questionCounter++,
            'sub_institute_id' => $this->subInstituteId,
            'quality_status' => 'approved',
        ], $attributes));
    }

    /** Psychometrics as pal:derive-irt writes them: all three together. */
    private function calibratedFields(): array
    {
        return [
            'discrimination_index' => 0.45,
            'response_count' => 120,
            'psychometrics_derived_at' => now(),
        ];
    }

    private function boardFields(): array
    {
        return [
            'board' => 'CBSE',
            'blueprint_category' => 'short_answer',
            'marks' => 3.0,
        ];
    }

    public function test_an_item_can_be_calibrated_without_being_placeable_in_a_paper(): void
    {
        $item = $this->row($this->calibratedFields());

        $this->assertTrue($item->isFitForPalDiagnostic());
        $this->assertFalse(
            $item->isFitForBoardExam(),
            'Strong discrimination says nothing about whether a blueprint has a slot for the item.'
        );
    }

    public function test_an_item_can_be_placeable_in_a_paper_without_being_calibrated(): void
    {
        $item = $this->row($this->boardFields());

        $this->assertTrue($item->isFitForBoardExam());
        $this->assertFalse(
            $item->isFitForPalDiagnostic(),
            'A valid 3-mark short answer that nobody has sat yet is board-fit and uncalibrated at the same time. Treating that as low quality would exclude every newly authored item from the paper it was written for.'
        );
    }

    public function test_an_item_can_satisfy_both_or_neither(): void
    {
        $both = $this->row(array_merge($this->calibratedFields(), $this->boardFields()));
        $this->assertTrue($both->isFitForPalDiagnostic());
        $this->assertTrue($both->isFitForBoardExam());

        $neither = $this->row([]);
        $this->assertFalse($neither->isFitForPalDiagnostic());
        $this->assertFalse($neither->isFitForBoardExam());
    }

    public function test_the_two_views_report_their_own_verdict_and_do_not_borrow_the_others(): void
    {
        $item = $this->row($this->calibratedFields());

        $this->assertTrue($item->palCalibration()['fit']);
        $this->assertFalse($item->boardCompliance()['fit']);

        // Each view carries only its own fields. A board consumer reading
        // psychometrics, or PAL reading marks, is the coupling this split
        // exists to prevent.
        $this->assertArrayNotHasKey('marks', $item->palCalibration());
        $this->assertArrayNotHasKey('discrimination_index', $item->boardCompliance());
    }

    /**
     * The one that guards a real drift risk: scopeCalibrated() is SQL and
     * isFitForPalDiagnostic() is PHP, so they cannot share an implementation.
     * They must still answer identically for every row.
     */
    public function test_the_sql_scope_and_the_php_predicate_never_disagree(): void
    {
        $cases = [
            'fully calibrated' => $this->calibratedFields(),
            'discrimination just below the gate' => ['discrimination_index' => 0.29, 'response_count' => 120, 'psychometrics_derived_at' => now()],
            'discrimination exactly at the gate' => ['discrimination_index' => 0.30, 'response_count' => 120, 'psychometrics_derived_at' => now()],
            'too few responses' => ['discrimination_index' => 0.45, 'response_count' => 5, 'psychometrics_derived_at' => now()],
            'responses exactly at the floor' => ['discrimination_index' => 0.45, 'response_count' => 30, 'psychometrics_derived_at' => now()],
            'hand-typed, never derived' => ['discrimination_index' => 0.45, 'response_count' => 120, 'psychometrics_derived_at' => null],
            'nothing at all' => [],
        ];

        foreach ($cases as $label => $fields) {
            $item = $this->row($fields);

            $viaScope = QuestionMetadata::query()
                ->where('id', $item->id)
                ->calibrated()
                ->exists();

            $this->assertSame(
                $viaScope,
                $item->isFitForPalDiagnostic(),
                "scopeCalibrated() and isFitForPalDiagnostic() disagree for: {$label}. They read the same config and must stay in step — a drift here means the diagnostic selects items it then reports as uncalibrated."
            );
        }
    }

    public function test_a_zero_mark_item_is_not_board_fit(): void
    {
        $item = $this->row(array_merge($this->boardFields(), ['marks' => 0]));

        $this->assertFalse(
            $item->isFitForBoardExam(),
            'A zero-mark question occupies a blueprint slot while contributing nothing to the paper total.'
        );

        $this->assertNotEmpty(PalVocabulary::validate(['marks' => 0]));
        $this->assertNotEmpty(PalVocabulary::validate(['marks' => -1]));
        $this->assertSame([], PalVocabulary::validate(['marks' => 0.5]));
    }

    public function test_blueprint_category_is_a_closed_set(): void
    {
        $this->assertNotEmpty(
            PalVocabulary::validate(['blueprint_category' => 'essay_question']),
            'An unregistered category would let an item be selected for a blueprint slot the board does not have.'
        );

        $this->assertSame([], PalVocabulary::validate(['blueprint_category' => 'case_based']));
        $this->assertTrue(PalVocabulary::isBlueprintCategory('competency_based'));
        $this->assertFalse(PalVocabulary::isBlueprintCategory('not_a_category'));
    }

    public function test_typical_marks_are_guidance_and_do_not_constrain_the_row(): void
    {
        $this->assertSame(3.0, PalVocabulary::typicalMarksFor('short_answer'));
        $this->assertNull(PalVocabulary::typicalMarksFor('not_a_category'));

        // A short answer carrying 2 marks in this year's paper is still valid —
        // boards vary the weighting, so the row wins over the guidance.
        $item = $this->row(array_merge($this->boardFields(), ['marks' => 2.0]));

        $this->assertTrue($item->isFitForBoardExam());
        $this->assertSame([], PalVocabulary::validate(['blueprint_category' => 'short_answer', 'marks' => 2.0]));
    }

    public function test_every_registered_blueprint_category_is_fully_specified(): void
    {
        foreach (config('pal_content.blueprint_categories') as $key => $category) {
            $this->assertArrayHasKey('label', $category, "{$key} has no label");
            $this->assertArrayHasKey('typical_marks', $category, "{$key} has no typical marks");
            $this->assertGreaterThan(0, $category['typical_marks'], "{$key} has non-positive typical marks");
        }
    }
}
