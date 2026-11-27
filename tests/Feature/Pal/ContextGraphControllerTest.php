<?php

namespace Tests\Feature\Pal;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Closes a confirmed gap: prior to this test, no Feature test anywhere
 * referenced ContextGraphController/LearnerContextGraphService
 * (GET /api/pal/context-graph/{learnerId}), despite the endpoint being live
 * in production since 2026-11-05 (docs/context-graph/STUDENT_LEARNING_CONTEXT_STATUS.md §4).
 *
 * Seeding/token pattern mirrors PalTeacherScopingTest.php, the house
 * convention for this route group (DatabaseTransactions + hand-built JWTs,
 * no auth-mocking helper).
 */
class ContextGraphControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;
    private int $syear;
    private int $standardId;
    private int $divisionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Context Graph Test School',
            'ShortCode' => 'CGT' . random_int(1000, 9999),
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

        $this->syear = 2026;
        $this->standardId = random_int(100000, 199999);
        $this->divisionId = random_int(200000, 299999);

        DB::table('standard')->insert([
            'id' => $this->standardId,
            'name' => 'Context Graph Test Standard',
            'sub_institute_id' => $this->subInstituteId,
        ]);

        DB::table('division')->insert([
            'id' => $this->divisionId,
            'name' => 'Context Graph Test Division',
            'sub_institute_id' => $this->subInstituteId,
        ]);
    }

    private function createStudent(bool $withEnrollment = true): int
    {
        $studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'ContextGraph',
            'last_name' => 'Student',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        if ($withEnrollment) {
            DB::table('tblstudent_enrollment')->insert([
                'syear' => $this->syear,
                'student_id' => $studentId,
                'standard_id' => $this->standardId,
                'section_id' => $this->divisionId,
                'sub_institute_id' => $this->subInstituteId,
            ]);
        }

        return $studentId;
    }

    private function studentToken(int $studentId): string
    {
        return JWT::encode([
            'id' => $studentId,
            'sub_institute_id' => $this->subInstituteId,
            'is_admin' => 0,
            'is_student' => true,
            'client_id' => null,
        ], env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    private function teacherToken(int $teacherId): string
    {
        return JWT::encode([
            'id' => $teacherId,
            'sub_institute_id' => $this->subInstituteId,
            'is_admin' => 0,
            'is_student' => false,
            'client_id' => null,
        ], env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    private function adminToken(int $adminId): string
    {
        return JWT::encode([
            'id' => $adminId,
            'sub_institute_id' => $this->subInstituteId,
            'is_admin' => 1,
            'is_student' => false,
            'client_id' => null,
        ], env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));
    }

    public function test_fully_enrolled_student_happy_path(): void
    {
        $studentId = $this->createStudent();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken($studentId)])
            ->getJson("/api/pal/context-graph/{$studentId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.user_context.standard_name', 'Context Graph Test Standard');
        $response->assertJsonPath('data.user_context.division_name', 'Context Graph Test Division');
    }

    public function test_unknown_student_id_short_circuits_without_exception(): void
    {
        // The real short-circuit trigger is "no tblstudent row at all" --
        // resolveUserContext() returns null only in that case, not merely
        // for a student with no enrollment (see the next test).
        $unknownStudentId = 999999991;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken($unknownStudentId)])
            ->getJson("/api/pal/context-graph/{$unknownStudentId}");

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    public function test_student_with_no_enrollment_still_returns_a_degraded_context(): void
    {
        // A tblstudent row with no tblstudent_enrollment row does NOT
        // short-circuit the whole response (resolveUserContext() only
        // returns null when the student row itself is missing) -- it
        // degrades standard/division to "Unassigned" instead.
        $studentId = $this->createStudent(withEnrollment: false);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken($studentId)])
            ->getJson("/api/pal/context-graph/{$studentId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.user_context.standard_name', 'Unassigned');
    }

    public function test_tenant_without_curriculum_graph_degrades_layer_c_only(): void
    {
        // This standard/subject combination has no projected Neo4j concept
        // scope at all, so Layer C must report unavailable without the rest
        // of the payload (Layer A/B/D) failing.
        $studentId = $this->createStudent();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken($studentId)])
            ->getJson("/api/pal/context-graph/{$studentId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.knowledge_graph.available', false);
        $response->assertJsonPath('data.user_context.standard_name', 'Context Graph Test Standard');
    }

    public function test_student_cannot_access_another_students_context(): void
    {
        $ownStudentId = $this->createStudent();
        $otherStudentId = $this->createStudent();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken($ownStudentId)])
            ->getJson("/api/pal/context-graph/{$otherStudentId}");

        $response->assertStatus(403);
    }

    public function test_teacher_outside_class_scope_is_denied(): void
    {
        $studentId = $this->createStudent();
        $unassignedTeacherId = random_int(500000, 599999);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($unassignedTeacherId)])
            ->getJson("/api/pal/context-graph/{$studentId}");

        $response->assertStatus(403);
    }

    public function test_teacher_in_class_scope_is_allowed(): void
    {
        $studentId = $this->createStudent();
        $teacherId = random_int(300000, 399999);

        DB::table('class_teacher')->insert([
            'syear' => $this->syear,
            'sub_institute_id' => $this->subInstituteId,
            'grade_id' => 1,
            'standard_id' => $this->standardId,
            'division_id' => $this->divisionId,
            'teacher_id' => $teacherId,
            'created_at' => now(),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->getJson("/api/pal/context-graph/{$studentId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.available', true);
    }

    public function test_admin_is_unrestricted(): void
    {
        $studentId = $this->createStudent();
        $adminId = random_int(600000, 699999);

        // No class_teacher / timetable row for this admin at all.
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson("/api/pal/context-graph/{$studentId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.available', true);
    }
}
