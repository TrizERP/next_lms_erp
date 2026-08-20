<?php

namespace Tests\Feature\Pal;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for the Phase 3 fix: PalApiAuth previously scoped
 * staff/teachers to their whole institute -- any teacher could reach any
 * student's PAL data institute-wide, not just their assigned classes. The
 * rest of the LMS (Helper::SearchStudent, studentAttendanceController, etc.)
 * restricts teacher access via class_teacher (homeroom) / timetable (subject
 * teacher) joined to tblstudent_enrollment; PalApiAuth now applies the same
 * two-source scoping for the 'staff' role. Institute-level admins (is_admin
 * >= 1) are unaffected -- they keep full institute access.
 */
class PalTeacherScopingTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;
    private int $syear;
    private int $standardId;
    private int $divisionId;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Teacher Scoping Test School',
            'ShortCode' => 'TST' . random_int(1000, 9999),
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

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Scoped',
            'last_name' => 'Student',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        DB::table('tblstudent_enrollment')->insert([
            'syear' => $this->syear,
            'student_id' => $this->studentId,
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

    public function test_homeroom_class_teacher_can_access_their_assigned_student(): void
    {
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
            ->getJson("/api/pal/learner-state/{$this->studentId}");

        $response->assertStatus(200);
    }

    public function test_subject_teacher_via_timetable_can_access_their_students(): void
    {
        $teacherId = random_int(400000, 499999);

        DB::table('timetable')->insert([
            'sub_institute_id' => $this->subInstituteId,
            'syear' => $this->syear,
            'academic_section_id' => 1,
            'standard_id' => $this->standardId,
            'division_id' => $this->divisionId,
            'period_id' => 1,
            'subject_id' => 1,
            'teacher_id' => $teacherId,
            'week_day' => 'Monday',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->getJson("/api/pal/learner-state/{$this->studentId}");

        $response->assertStatus(200);
    }

    public function test_teacher_with_no_class_or_timetable_assignment_is_denied(): void
    {
        $unassignedTeacherId = random_int(500000, 599999);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($unassignedTeacherId)])
            ->getJson("/api/pal/learner-state/{$this->studentId}");

        $response->assertStatus(403);
    }

    public function test_institute_admin_bypasses_class_scoping(): void
    {
        $adminId = random_int(600000, 699999);

        $token = JWT::encode([
            'id' => $adminId,
            'sub_institute_id' => $this->subInstituteId,
            'is_admin' => 1,
            'is_student' => false,
            'client_id' => null,
        ], env('JWT_SECRET'), env('JWT_ALGO', 'HS256'));

        // No class_teacher / timetable row for this admin at all.
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/pal/learner-state/{$this->studentId}");

        $response->assertStatus(200);
    }
}
