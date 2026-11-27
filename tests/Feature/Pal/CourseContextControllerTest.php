<?php

namespace Tests\Feature\Pal;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coverage for the new course-grain Context Graph endpoint
 * (GET /api/pal/course-context/{standardId}/{subjectId}), which has no
 * pre-existing implementation to reuse — see
 * App\Services\PAL\ContextGraph\CourseContextService.
 */
class CourseContextControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;
    private int $syear;
    private int $standardId;
    private int $divisionId;
    private int $subjectId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Course Context Test School',
            'ShortCode' => 'CCT' . random_int(1000, 9999),
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
        $this->subjectId = random_int(700000, 799999);

        DB::table('standard')->insert([
            'id' => $this->standardId,
            'name' => 'Course Context Test Standard',
            'sub_institute_id' => $this->subInstituteId,
        ]);

        DB::table('subject')->insert([
            'id' => $this->subjectId,
            'subject_name' => 'Course Context Test Subject',
        ]);

        DB::table('tblstudent_enrollment')->insert([
            'syear' => $this->syear,
            'student_id' => (int) DB::table('tblstudent')->insertGetId([
                'first_name' => 'Roster', 'last_name' => 'Student',
                'sub_institute_id' => $this->subInstituteId, 'file_size' => '', 'file_type' => '',
            ]),
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

    public function test_scoped_teacher_can_access_course_context(): void
    {
        $teacherId = random_int(300000, 399999);

        DB::table('timetable')->insert([
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
            'academic_section_id' => 1,
            'standard_id' => $this->standardId,
            'division_id' => $this->divisionId,
            'period_id' => 1,
            'subject_id' => $this->subjectId,
            'teacher_id' => $teacherId,
            'week_day' => 'Monday',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->getJson("/api/pal/course-context/{$this->standardId}/{$this->subjectId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.standard.name', 'Course Context Test Standard');
        $response->assertJsonCount(1, 'data.enrolled_students');
    }

    public function test_unscoped_teacher_is_denied(): void
    {
        $unassignedTeacherId = random_int(500000, 599999);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($unassignedTeacherId)])
            ->getJson("/api/pal/course-context/{$this->standardId}/{$this->subjectId}");

        $response->assertStatus(403);
    }

    public function test_student_is_denied_outright(): void
    {
        $studentId = random_int(800000, 899999);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken($studentId)])
            ->getJson("/api/pal/course-context/{$this->standardId}/{$this->subjectId}");

        $response->assertStatus(403);
    }

    public function test_admin_is_unrestricted(): void
    {
        $adminId = random_int(600000, 699999);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson("/api/pal/course-context/{$this->standardId}/{$this->subjectId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.available', true);
    }
}
