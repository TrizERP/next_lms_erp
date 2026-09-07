<?php

namespace Tests\Feature\Eso;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pre-pilot readiness — proves the "student entry point" cannot be used to
 * impersonate another learner by editing the `learnerId` in the URL/body.
 * The engine itself doesn't do this authorization — `pal.auth`
 * (PalApiAuth::authorizeLearner()) does, the same middleware every other
 * PAL V4 route already relies on — this test locks that guarantee in
 * specifically for the new `routes/pal_eso_api.php` routes, since nothing
 * exercised it there before. Same JWT-minting convention as
 * tests/Feature/Pal/PalMisconceptionAuthTest.php.
 */
class EsoAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private int $instituteA;

    private int $instituteB;

    private int $studentA;

    private int $studentB;

    private int $staffInInstituteA;

    private int $conceptId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituteA = $this->makeInstitute('ESO Auth Institute A');
        $this->instituteB = $this->makeInstitute('ESO Auth Institute B');
        $this->studentA = $this->makeStudent($this->instituteA);
        $this->studentB = $this->makeStudent($this->instituteB);
        $this->staffInInstituteA = $this->makeStaff($this->instituteA);

        $this->conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'ESO Auth Test Concept',
            'subject_id' => 1,
            'standard_id' => 1,
            'chapter_id' => 1,
            'sub_institute_id' => $this->instituteA,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);
    }

    private function makeInstitute(string $name): int
    {
        return (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => $name,
            'ShortCode' => strtoupper(substr($name, 0, 3)) . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'test@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'test@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);
    }

    private function makeStudent(int $subInstituteId): int
    {
        return (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Test',
            'last_name' => 'Student',
            'sub_institute_id' => $subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);
    }

    private function makeStaff(int $subInstituteId): int
    {
        return (int) DB::table('tbluser')->insertGetId([
            'user_name' => 'staff' . random_int(10000, 99999),
            'password' => bcrypt('secret'),
            'first_name' => 'Test',
            'last_name' => 'Staff',
            'email' => 'staff' . random_int(10000, 99999) . '@example.com',
            'mobile' => '9999999999',
            'user_profile_id' => 1,
            'join_year' => '2026',
            'sub_institute_id' => $subInstituteId,
            'is_admin' => 1,
            'status' => 1,
        ]);
    }

    private function tokenFor(int $userId, int $subInstituteId, bool $isStudent, int $isAdmin = 0): string
    {
        $payload = [
            'id' => $userId,
            'sub_institute_id' => $subInstituteId,
            'is_admin' => $isAdmin,
            'is_student' => $isStudent,
            'client_id' => null,
        ];

        return JWT::encode($payload, env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    public function test_a_student_can_read_their_own_next_action(): void
    {
        $token = $this->tokenFor($this->studentA, $this->instituteA, isStudent: true);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/eso/next-action/{$this->studentA}/{$this->conceptId}");

        $response->assertStatus(200);
    }

    public function test_a_student_cannot_read_another_students_next_action_by_editing_the_url(): void
    {
        $token = $this->tokenFor($this->studentA, $this->instituteA, isStudent: true);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/eso/next-action/{$this->studentB}/{$this->conceptId}");

        $response->assertStatus(403);
    }

    public function test_a_student_cannot_record_an_attempt_for_another_student(): void
    {
        $token = $this->tokenFor($this->studentA, $this->instituteA, isStudent: true);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/pal/eso/practice/{$this->studentB}/1/attempt", [
                'concept_id' => $this->conceptId,
                'answer_master_id' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_a_student_cannot_read_another_students_decision_log(): void
    {
        $token = $this->tokenFor($this->studentA, $this->instituteA, isStudent: true);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/eso/decision-log/{$this->studentB}/{$this->conceptId}");

        $response->assertStatus(403);
    }

    public function test_a_request_with_no_token_is_rejected(): void
    {
        $response = $this->getJson("/api/pal/eso/next-action/{$this->studentA}/{$this->conceptId}");

        $response->assertStatus(401);
    }

    // ── Adaptive Learning is student-only: a teacher/staff/admin must never
    //    be able to START or ADVANCE a student's session, even for a
    //    student genuinely within their own institute/class scope — that
    //    scope only ever governed read-only reporting (decision-log,
    //    Misconceptions, Pedagogy Engine), never execution. ─────────────

    public function test_staff_cannot_start_a_students_adaptive_learning_session_even_within_their_own_institute(): void
    {
        $token = $this->tokenFor($this->staffInInstituteA, $this->instituteA, isStudent: false, isAdmin: 1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/eso/next-action/{$this->studentA}/{$this->conceptId}");

        $response->assertStatus(403);
    }

    public function test_staff_cannot_open_a_students_knowledge_map_even_within_their_own_institute(): void
    {
        $token = $this->tokenFor($this->staffInInstituteA, $this->instituteA, isStudent: false, isAdmin: 1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/eso/knowledge-map/{$this->studentA}/{$this->conceptId}");

        $response->assertStatus(403);
    }

    public function test_staff_cannot_submit_a_diagnostic_for_a_student_in_their_own_institute(): void
    {
        $token = $this->tokenFor($this->staffInInstituteA, $this->instituteA, isStudent: false, isAdmin: 1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/pal/eso/diagnostic/{$this->studentA}/{$this->conceptId}/submit", [
                'responses' => [['node_id' => 1, 'answer_master_id' => 1]],
            ]);

        $response->assertStatus(403);
    }

    public function test_staff_cannot_record_a_practice_attempt_for_a_student_in_their_own_institute(): void
    {
        $token = $this->tokenFor($this->staffInInstituteA, $this->instituteA, isStudent: false, isAdmin: 1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/pal/eso/practice/{$this->studentA}/1/attempt", [
                'concept_id' => $this->conceptId,
                'answer_master_id' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_a_client_level_super_admin_still_cannot_start_a_students_adaptive_learning_session(): void
    {
        // is_admin === 2 is unrestricted under PalApiAuth's ordinary
        // institute/class scoping (used for reporting) — but the student-only
        // rule for starting/advancing a session applies unconditionally to
        // every non-student role, this one included.
        $token = $this->tokenFor(999999, $this->instituteA, isStudent: false, isAdmin: 2);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/eso/next-action/{$this->studentA}/{$this->conceptId}");

        $response->assertStatus(403);
    }

    public function test_staff_cannot_read_a_student_in_another_institute(): void
    {
        $token = $this->tokenFor($this->staffInInstituteA, $this->instituteA, isStudent: false, isAdmin: 1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/eso/next-action/{$this->studentB}/{$this->conceptId}");

        $response->assertStatus(403);
    }

    /**
     * Reporting is explicitly preserved: decision-log is NOT a
     * start/advance-a-session route, so ordinary staff/institute scoping
     * still governs it exactly as before — a teacher may still read (never
     * drive) a student's Adaptive Learning progress in their own institute.
     */
    public function test_staff_can_still_read_a_students_decision_log_in_their_own_institute(): void
    {
        $token = $this->tokenFor($this->staffInInstituteA, $this->instituteA, isStudent: false, isAdmin: 1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/eso/decision-log/{$this->studentA}/{$this->conceptId}");

        $response->assertStatus(200);
    }

    public function test_staff_still_cannot_read_a_students_decision_log_in_another_institute(): void
    {
        $token = $this->tokenFor($this->staffInInstituteA, $this->instituteA, isStudent: false, isAdmin: 1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/eso/decision-log/{$this->studentB}/{$this->conceptId}");

        $response->assertStatus(403);
    }

    /**
     * The chapter-concepts entry-point endpoint has no {learnerId} — it lists
     * chapter content, not per-learner state — so it should require only
     * authentication, not per-learner ownership.
     */
    public function test_chapter_concepts_requires_authentication_but_not_learner_ownership(): void
    {
        $unauthenticated = $this->getJson('/api/pal/eso/chapter-concepts/1014');
        $unauthenticated->assertStatus(401);

        $token = $this->tokenFor($this->studentA, $this->instituteA, isStudent: true);
        $authenticated = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/pal/eso/chapter-concepts/1014');
        $authenticated->assertStatus(200);
    }
}
