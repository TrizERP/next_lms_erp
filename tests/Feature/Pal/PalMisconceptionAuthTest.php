<?php

namespace Tests\Feature\Pal;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the PAL misconception/suggested-content auth fix:
 *  - routes/web.php previously exposed these 6 routes with zero auth middleware
 *  - palController previously trusted a client-supplied user_id/sub_institute_id
 *    over the authenticated identity (cross-student IDOR)
 *
 * Both are now closed by palController::resolveAuthorizedContext(), which mirrors
 * PalApiAuth's JWT + tenant/ownership scoping for the token (type=API) path and
 * always trusts the session identity for the web path.
 */
class PalMisconceptionAuthTest extends TestCase
{
    use DatabaseTransactions;

    private int $instituteA;
    private int $instituteB;
    private int $studentA;
    private int $studentB;
    private int $staffInInstituteA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instituteA = $this->makeInstitute('Institute A');
        $this->instituteB = $this->makeInstitute('Institute B');
        $this->studentA = $this->makeStudent($this->instituteA);
        $this->studentB = $this->makeStudent($this->instituteB);
        $this->staffInInstituteA = $this->makeStaff($this->instituteA, isAdmin: 1);
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

    private function makeStaff(int $subInstituteId, int $isAdmin): int
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
            'is_admin' => $isAdmin,
            'status' => 1,
        ]);
    }

    private function tokenFor(int $userId, int $subInstituteId, bool $isStudent, int $isAdmin = 0, ?int $clientId = null): string
    {
        $payload = [
            'id' => $userId,
            'sub_institute_id' => $subInstituteId,
            'is_admin' => $isAdmin,
            'is_student' => $isStudent,
            'client_id' => $clientId,
        ];

        return JWT::encode($payload, env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    public function test_misconception_endpoint_requires_authentication_for_token_callers(): void
    {
        $response = $this->getJson('/lms/misconception?type=API&chapter_id=1&user_id=' . $this->studentA);

        $response->assertStatus(401);
    }

    public function test_misconception_endpoint_rejects_invalid_token(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer not-a-real-token'])
            ->getJson('/lms/misconception?type=API&chapter_id=1&user_id=' . $this->studentA);

        $response->assertStatus(401);
    }

    public function test_student_cannot_read_another_students_misconception_data(): void
    {
        $token = $this->tokenFor($this->studentA, $this->instituteA, isStudent: true);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/lms/misconception?type=API&chapter_id=1&user_id=' . $this->studentB);

        $response->assertStatus(403);
    }

    public function test_student_can_read_own_misconception_data(): void
    {
        $token = $this->tokenFor($this->studentA, $this->instituteA, isStudent: true);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/lms/misconception?type=API&chapter_id=1&user_id=' . $this->studentA);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 1);
    }

    public function test_staff_cannot_read_misconception_data_for_student_in_another_institute(): void
    {
        $token = $this->tokenFor($this->staffInInstituteA, $this->instituteA, isStudent: false, isAdmin: 1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/lms/misconception?type=API&chapter_id=1&user_id=' . $this->studentB);

        $response->assertStatus(403);
    }

    public function test_staff_can_read_misconception_data_for_student_in_own_institute(): void
    {
        $token = $this->tokenFor($this->staffInInstituteA, $this->instituteA, isStudent: false, isAdmin: 1);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/lms/misconception?type=API&chapter_id=1&user_id=' . $this->studentA);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 1);
    }

    public function test_increment_content_visit_requires_authentication_for_token_callers(): void
    {
        $response = $this->postJson('/lms/increment-content-visit', [
            'type' => 'API',
            'user_id' => $this->studentA,
            'content_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_student_cannot_increment_content_visit_for_another_student(): void
    {
        $token = $this->tokenFor($this->studentA, $this->instituteA, isStudent: true);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/lms/increment-content-visit', [
                'type' => 'API',
                'user_id' => $this->studentB,
                'content_id' => 1,
            ]);

        $response->assertStatus(403);
    }

    public function test_web_session_route_ignores_client_supplied_user_id_override(): void
    {
        // Even if a logged-in web user passes a different user_id in the request,
        // the session identity must win -- the request override is ignored entirely
        // for session-authenticated (non-API) callers.
        $response = $this->withSession([
            'user_id' => $this->studentA,
            'sub_institute_id' => $this->instituteA,
            'syear' => '2026',
            'user_profile_id' => 1,
            // Bypasses checkPermission's menu-rights lookup so this test isolates
            // the auth-context fix rather than the separate permissions system.
            'user_profile_name' => 'Super Admin',
        ])->get('/lms/misconception?chapter_id=1&user_id=' . $this->studentB);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 1);
    }
}
