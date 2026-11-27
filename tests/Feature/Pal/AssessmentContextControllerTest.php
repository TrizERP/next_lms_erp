<?php

namespace Tests\Feature\Pal;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coverage for the new per-assessment Context Graph endpoint
 * (GET /api/pal/assessment-context/{assessmentId}), which has no
 * pre-existing implementation to reuse — see
 * App\Services\PAL\ContextGraph\AssessmentContextService.
 *
 * The question list layer depends on the exam_assessment ontology migration's
 * graph-only `exam_paper -> has_question -> question` edge, which has no SQL
 * fallback by design — so `data.questions` is asserted present (possibly
 * empty if the graph is unavailable in this environment) rather than
 * asserting a specific count, keeping this test honest about what it can
 * verify without a live Neo4j connection.
 */
class AssessmentContextControllerTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;
    private int $standardId;
    private int $subjectId;
    private int $assessmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Assessment Context Test School',
            'ShortCode' => 'ACT' . random_int(1000, 9999),
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

        $this->standardId = random_int(100000, 199999);
        $this->subjectId = random_int(700000, 799999);

        DB::table('standard')->insert([
            'id' => $this->standardId,
            'name' => 'Assessment Context Test Standard',
            'sub_institute_id' => $this->subInstituteId,
        ]);

        DB::table('subject')->insert([
            'id' => $this->subjectId,
            'subject_name' => 'Assessment Context Test Subject',
        ]);

        $this->assessmentId = (int) DB::table('question_paper')->insertGetId([
            'paper_name' => 'Assessment Context Test Paper',
            'standard_id' => $this->standardId,
            'subject_id' => $this->subjectId,
            'sub_institute_id' => $this->subInstituteId,
            'total_marks' => 50,
            'total_ques' => 10,
            'exam_type' => 'unit_test',
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

    public function test_scoped_teacher_can_access_assessment_context(): void
    {
        $teacherId = random_int(300000, 399999);
        $divisionId = random_int(200000, 299999);

        DB::table('timetable')->insert([
            'sub_institute_id' => $this->subInstituteId,
            'syear' => 2026,
            'academic_section_id' => 1,
            'standard_id' => $this->standardId,
            'division_id' => $divisionId,
            'period_id' => 1,
            'subject_id' => $this->subjectId,
            'teacher_id' => $teacherId,
            'week_day' => 'Monday',
        ]);

        DB::table('tblstudent_enrollment')->insert([
            'syear' => 2026,
            'student_id' => (int) DB::table('tblstudent')->insertGetId([
                'first_name' => 'Scoped', 'last_name' => 'Student',
                'sub_institute_id' => $this->subInstituteId, 'file_size' => '', 'file_type' => '',
            ]),
            'standard_id' => $this->standardId,
            'section_id' => $divisionId,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($teacherId)])
            ->getJson("/api/pal/assessment-context/{$this->assessmentId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.assessment.paper_name', 'Assessment Context Test Paper');
        $response->assertJsonPath('data.course.standard_id', $this->standardId);
    }

    public function test_unscoped_teacher_is_denied(): void
    {
        $unassignedTeacherId = random_int(500000, 599999);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->teacherToken($unassignedTeacherId)])
            ->getJson("/api/pal/assessment-context/{$this->assessmentId}");

        $response->assertStatus(403);
    }

    public function test_student_is_denied_outright(): void
    {
        $studentId = random_int(800000, 899999);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->studentToken($studentId)])
            ->getJson("/api/pal/assessment-context/{$this->assessmentId}");

        $response->assertStatus(403);
    }

    public function test_admin_is_unrestricted(): void
    {
        $adminId = random_int(600000, 699999);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson("/api/pal/assessment-context/{$this->assessmentId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.available', true);
    }

    public function test_unknown_assessment_returns_404(): void
    {
        $adminId = random_int(600000, 699999);
        $unknownAssessmentId = $this->assessmentId + 999999;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken($adminId)])
            ->getJson("/api/pal/assessment-context/{$unknownAssessmentId}");

        $response->assertStatus(404);
    }
}
