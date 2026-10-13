<?php

namespace Tests\Feature\Pal;

use App\Models\PAL\DiagnosticAttempt;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * End-to-end coverage for the "already attempted" gate on
 * palController::diagnosticStart().
 *
 * Uses the known-good full-coverage fixture (student 282260, sub_institute
 * 341, chapter 8677 - every concept has ESO nodes and 222 node-mapped
 * questions, confirmed live: 61+ real submitted attempts already exist for
 * this pair) rather than constructing a synthetic chapter, because building
 * a paper at all requires DiagnosticQuestionSelector's full DoK/g_difficulty
 * ladder over a real question bank - not worth re-deriving in a fixture when
 * a real, already-covered chapter exists. DatabaseTransactions rolls back
 * every row this test writes or changes.
 *
 * type=API requests go through SessionMiddleware's JWT path, not a plain
 * session cookie (see HydratesLegacyApiSession) - a bearer token is
 * required, following the same recipe PalMisconceptionAuthTest uses.
 */
class DiagnosticAlreadyAttemptedGateTest extends TestCase
{
    use DatabaseTransactions;

    private const STUDENT_ID = 282260;
    private const SUB_INSTITUTE_ID = 341;
    private const CHAPTER_ID = 8677;
    private const SYEAR = 2026;

    protected function setUp(): void
    {
        parent::setUp();

        // The live fixture can carry a stray in_progress attempt from past
        // manual QA. resume() always wins over the gate by design, so a
        // leftover one here would make this test exercise resume() instead
        // of the gate it is meant to prove - clear it first.
        DiagnosticAttempt::forStudent(self::STUDENT_ID)
            ->forChapter(self::CHAPTER_ID)
            ->where('status', DiagnosticAttempt::STATUS_IN_PROGRESS)
            ->delete();
    }

    private function authHeaders(): array
    {
        $token = JWT::encode([
            'id' => self::STUDENT_ID,
            'sub_institute_id' => self::SUB_INSTITUTE_ID,
            'is_admin' => 0,
            'is_student' => true,
            'client_id' => null,
        ], env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));

        return ['Authorization' => 'Bearer ' . $token];
    }

    private function url(string $path, array $extra = []): string
    {
        $query = array_merge(['type' => 'API', 'syear' => self::SYEAR], $extra);

        return $path . '?' . http_build_query($query);
    }

    public function test_reopening_a_chapter_with_a_submitted_attempt_shows_the_gate_not_a_new_paper(): void
    {
        $existingSubmitted = DiagnosticAttempt::forStudent(self::STUDENT_ID)
            ->forChapter(self::CHAPTER_ID)->submitted()->count();
        $this->assertGreaterThan(0, $existingSubmitted, 'Fixture must already have a submitted attempt.');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson($this->url('/lms/pal/diagnostic/chapter/' . self::CHAPTER_ID));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame('already_attempted', $data['attempt_status']);
        $this->assertNull($data['attempt_id']);
        $this->assertNotNull($data['previous_attempt']);
        $this->assertSame($existingSubmitted, $data['previous_attempt']['attempt_number']);
    }

    public function test_retake_param_bypasses_the_gate_and_draws_a_genuinely_new_attempt(): void
    {
        $priorLatestId = DiagnosticAttempt::forStudent(self::STUDENT_ID)
            ->forChapter(self::CHAPTER_ID)->submitted()->orderByDesc('id')->value('id');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson($this->url('/lms/pal/diagnostic/chapter/' . self::CHAPTER_ID, ['retake' => 1]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame('new', $data['attempt_status']);
        $this->assertNotNull($data['attempt_id']);
        $this->assertNotSame($priorLatestId, $data['attempt_id']);
        $this->assertNotEmpty($data['questions'], 'Chapter 8677 has full MCQ coverage and must draw a real paper.');

        $fresh = DiagnosticAttempt::find($data['attempt_id']);
        $this->assertNotNull($fresh);
        $this->assertSame(DiagnosticAttempt::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNull($fresh->attempt_number, 'An in_progress attempt is never numbered.');
    }

    public function test_a_plain_reopen_after_a_retake_resumes_the_in_progress_attempt_not_the_gate(): void
    {
        $started = $this->withHeaders($this->authHeaders())
            ->getJson($this->url('/lms/pal/diagnostic/chapter/' . self::CHAPTER_ID, ['retake' => 1]))
            ->json();

        // No retake param this time - resume() must win over the gate even
        // though a submitted attempt also exists for this student+chapter.
        $response = $this->withHeaders($this->authHeaders())
            ->getJson($this->url('/lms/pal/diagnostic/chapter/' . self::CHAPTER_ID));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame('resumed', $data['attempt_status']);
        $this->assertTrue($data['resumed']);
        $this->assertSame($started['attempt_id'], $data['attempt_id']);
    }

    public function test_submitting_the_retaken_attempt_numbers_it_one_past_the_prior_count(): void
    {
        $existingSubmitted = DiagnosticAttempt::forStudent(self::STUDENT_ID)
            ->forChapter(self::CHAPTER_ID)->submitted()->count();

        $started = $this->withHeaders($this->authHeaders())
            ->getJson($this->url('/lms/pal/diagnostic/chapter/' . self::CHAPTER_ID, ['retake' => 1]))
            ->json();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson($this->url('/lms/pal/diagnostic/attempt/' . $started['attempt_id'] . '/submit'), [
                'answers' => [],
            ]);

        $response->assertOk();
        $result = $response->json('result');

        $this->assertSame($existingSubmitted + 1, $result['attempt_number']);

        // And the gate, reopened now, must describe THIS attempt.
        $reopened = $this->withHeaders($this->authHeaders())
            ->getJson($this->url('/lms/pal/diagnostic/chapter/' . self::CHAPTER_ID))
            ->json();

        $this->assertSame('already_attempted', $reopened['attempt_status']);
        $this->assertSame($started['attempt_id'], $reopened['previous_attempt']['attempt_id']);
        $this->assertSame($existingSubmitted + 1, $reopened['previous_attempt']['attempt_number']);
    }
}
