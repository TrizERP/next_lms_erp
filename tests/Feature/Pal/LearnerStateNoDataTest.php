<?php

namespace Tests\Feature\Pal;

use App\Services\PAL\Intelligence\LearnerStateEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the "0 mastery" vs "no data" fix in
 * LearnerStateEngine::inferCompetency()/getMasteryMap().
 *
 * Before this fix, a learner with zero pal_competencies rows (which was
 * every learner, since nothing wrote to that table -- see PalWritePathTest)
 * was reported as mastery_score = 0, indistinguishable from a learner who
 * was actually assessed and measured at 0% mastery. The spec explicitly
 * calls this out: "do not represent zero as a meaningful mastery score...
 * clearly distinguish '0 mastery' from 'no data'."
 */
class LearnerStateNoDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_learner_with_no_competency_rows_is_reported_as_no_data_not_zero_mastery(): void
    {
        $learnerId = 900001; // no pal_competencies rows exist for this id

        $state = app(LearnerStateEngine::class)->inferCompetency($learnerId);

        $this->assertFalse($state['has_data']);
        $this->assertSame(0.0, $state['mastery_score']);
        $this->assertNull($state['proficiency_trend']);
    }

    public function test_learner_with_a_competency_row_is_reported_as_having_data(): void
    {
        $learnerId = 900002;

        DB::table('pal_subjects')->insertOrIgnore([
            'id' => 555555,
            'name' => 'Test Subject For No-Data Check',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pal_competencies')->insert([
            'learner_id' => $learnerId,
            'subject_id' => 555555,
            'concept_id' => null,
            'mastery_score' => 42.5,
            'bloom_level' => 1,
            'proficiency_trend' => 'stable',
            'last_assessed' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $state = app(LearnerStateEngine::class)->inferCompetency($learnerId);

        $this->assertTrue($state['has_data']);
        $this->assertSame(42.5, $state['mastery_score']);
    }

    public function test_mastery_map_distinguishes_no_data_from_zero(): void
    {
        $learnerId = 900003;

        $map = app(LearnerStateEngine::class)->getMasteryMap($learnerId);

        $this->assertFalse($map['has_data']);
        $this->assertSame(0, $map['overall_mastery']);
    }
}
