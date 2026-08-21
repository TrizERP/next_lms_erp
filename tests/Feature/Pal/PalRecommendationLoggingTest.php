<?php

namespace Tests\Feature\Pal;

use App\Services\PAL\Pedagogy\PedagogyOrchestrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression coverage for the Phase 18 fix: every pedagogy recommendation is
 * now logged with enough context to later evaluate whether it worked
 * (learner state before, what was recommended, what content was attached).
 * Guarded by Schema::hasTable('pal_recommendation_log') so it degrades to a
 * no-op until the pal_recommendation_log migration (2026_08_19_120000) is
 * run -- this test itself will not run migrations against a shared DB, so
 * it skips gracefully when the table is not yet present, same as the
 * ULU authorization tests.
 */
class PalRecommendationLoggingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_pedagogy_recommendation_is_logged_with_context(): void
    {
        if (! Schema::hasTable('pal_recommendation_log')) {
            $this->markTestSkipped('pal_recommendation_log migration is not applied on this environment.');
        }

        $learnerId = 950001;
        $conceptId = 950002;

        DB::table('pal_subjects')->insertOrIgnore([
            'id' => 950003,
            'name' => 'Recommendation Logging Test Subject',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pal_competencies')->insert([
            'learner_id' => $learnerId,
            'subject_id' => 950003,
            'concept_id' => null,
            'mastery_score' => 42.0,
            'bloom_level' => 1,
            'proficiency_trend' => 'stable',
            'last_assessed' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PedagogyOrchestrationService::class)->getRecommendation($learnerId, $conceptId, [
            'subject_id' => 950003,
            'sub_institute_id' => 1,
        ]);

        $log = DB::table('pal_recommendation_log')
            ->where('learner_id', $learnerId)
            ->where('concept_id', $conceptId)
            ->first();

        $this->assertNotNull($log, 'A recommendation must be logged.');
        $this->assertTrue((bool) $log->had_data_before);
        $this->assertEquals(42.0, (float) $log->mastery_before);
        $this->assertNotNull($log->pedagogy_type);
    }
}
