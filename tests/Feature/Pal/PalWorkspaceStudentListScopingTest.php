<?php

namespace Tests\Feature\Pal;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for PalWorkspaceController::students(): it has no
 * {learnerId} route param, so PalApiAuth's per-learner class_teacher/timetable
 * scoping (added earlier this session) never applies to it -- any staff
 * account could list the whole institute's roster regardless of which
 * classes they actually teach. Now scoped the same way directly in the
 * controller's query.
 */
class PalWorkspaceStudentListScopingTest extends TestCase
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
            'SchoolName' => 'Workspace Scoping Test School',
            'ShortCode' => 'WST' . random_int(1000, 9999),
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

        $studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Roster',
            'last_name' => 'Student',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        DB::table('tblstudent_enrollment')->insert([
            'syear' => $this->syear,
            'student_id' => $studentId,
            'standard_id' => $this->standardId,
            'section_id' => $this->divisionId,
            'sub_institute_id' => $this->subInstituteId,
        ]);
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

    public function test_unassigned_teacher_sees_an_empty_roster(): void
    {
        $unassignedTeacherId = random_int(700000, 799999);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($unassignedTeacherId)])
            ->getJson("/api/pal/workspace/students?syear={$this->syear}");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_assigned_class_teacher_sees_their_students(): void
    {
        $teacherId = random_int(800000, 899999);

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
            ->getJson("/api/pal/workspace/students?syear={$this->syear}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
