<?php

namespace Tests\Feature\Pal;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage proving the H5P xAPI ingest pipeline
 * (App\Services\PAL\H5P\H5PXapiPipeline, POST /api/pal/h5p/xapi) actually
 * accepts and records a statement shaped exactly like the frontend now sends
 * it (see lms_k12 app/h5p/data/h5p.ts postH5pXapiStatement(), wired into the
 * flashcard and MCQ players this session).
 *
 * Before this session, this pipeline was real backend infrastructure with
 * zero traffic -- no player anywhere called it. This test is the backend
 * half of proving that gap is closed: a real statement, in the real shape
 * the real frontend now emits, produces a real stored event.
 */
class H5pFrontendTelemetryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_frontend_shaped_answered_statement_is_accepted_and_recorded(): void
    {
        $learnerId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'H5P',
            'last_name' => 'Telemetry',
            'sub_institute_id' => 1,
            'file_size' => '',
            'file_type' => '',
        ]);

        $token = JWT::encode([
            'id' => $learnerId,
            'sub_institute_id' => 1,
            'is_admin' => 0,
            'is_student' => true,
            'client_id' => null,
        ], env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));

        // The exact shape postH5pXapiStatement() in app/h5p/data/h5p.ts builds.
        $statement = [
            'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/answered'],
            'object' => ['id' => 'flash_cards:999999'],
            'timestamp' => now()->toIso8601String(),
            'context' => [
                'extensions' => [
                    'chapter_id' => '1',
                    'subject_id' => '1',
                    'standard_id' => '1',
                ],
            ],
            'result' => [
                'success' => true,
                'response' => 'my answer',
            ],
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/pal/h5p/xapi', ['learner_id' => $learnerId, 'statement' => $statement]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $eventId = $response->json('data.event_id');
        $this->assertNotNull($eventId, 'A real telemetry event must be created and its id returned.');

        $stored = DB::table('pal_telemetry_events')->where('id', $eventId)->first();
        $this->assertNotNull($stored);
        $this->assertSame($learnerId, (int) $stored->actor_id);
    }
}
