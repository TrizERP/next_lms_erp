<?php

namespace Tests\Feature\Pal;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the Phase 8 fix: PedagogyOrchestrationService::getRecommendation()
 * previously stopped at a strategy name (recommended_pedagogy.type) with no
 * content attached -- a caller had to separately chain to /content/next-variant
 * (or, if they reached for the "obvious" /content/recommend endpoint or the
 * dead RecommendationEngine class, would get a fake AI-placeholder response
 * from the near-empty legacy pal_contents table).
 *
 * getRecommendation() now calls ContentIntelligenceService::selectOptimalContent()
 * (the real content_master-backed pipeline, with its own genuine deterministic
 * exhausted/escalation fallback) and returns the result under
 * content_recommendation in the same response.
 */
class PedagogyContentRecommendationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pedagogy_recommendation_includes_a_real_content_recommendation_block(): void
    {
        $studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Pedagogy',
            'last_name' => 'ContentTest',
            'sub_institute_id' => 1,
            'file_size' => '',
            'file_type' => '',
        ]);

        $token = JWT::encode([
            'id' => $studentId,
            'sub_institute_id' => 1,
            'is_admin' => 0,
            'is_student' => true,
            'client_id' => null,
        ], env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/pedagogy/recommend/{$studentId}/1");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = $response->json('data');

        $this->assertArrayHasKey('recommended_pedagogy', $data);
        $this->assertArrayHasKey(
            'content_recommendation',
            $data,
            'A pedagogy recommendation with no attached content is a strategy name, not a recommendation the student can act on.'
        );

        // The content pipeline always returns a structured result: either real
        // content, or a deterministic exhausted/escalation response -- never
        // silently absent.
        $content = $data['content_recommendation'];
        $this->assertTrue(
            array_key_exists('content', $content) || array_key_exists('legacy_fallback', $content),
            'content_recommendation must be the real content-pipeline result shape.'
        );
    }
}
