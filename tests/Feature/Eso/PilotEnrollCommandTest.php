<?php

namespace Tests\Feature\Eso;

use App\Models\Eso\PilotEnrollment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pre-pilot readiness — pal:pilot-enroll. Exercised only against synthetic
 * fixtures created in this test, never against real students: this is the
 * command that will eventually enroll a real cohort, but this pass does not
 * run it for real (per "do not enroll real students automatically").
 */
class PilotEnrollCommandTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;

    private int $standardId;

    private int $chapterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Pilot Enroll Test School', 'ShortCode' => 'PEC' . random_int(1000, 9999),
            'ContactPerson' => 'x', 'Mobile' => '9999999999', 'Email' => 'x@example.com',
            'ReceiptHeader' => 'x', 'ReceiptAddress' => 'x', 'FeeEmail' => 'x@example.com',
            'ReceiptContact' => '9999999999', 'SortOrder' => '1', 'Logo' => '', 'created_at' => now(),
        ]);

        $this->standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1, 'name' => '9', 'short_name' => '9', 'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $this->chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => 1, 'standard_id' => $this->standardId, 'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'Pilot Enroll Test Chapter', 'created_at' => now(),
        ]);
    }

    private function makeEnrolledStudent(): int
    {
        $studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Cohort', 'last_name' => 'Student', 'sub_institute_id' => $this->subInstituteId,
            'file_size' => '', 'file_type' => '',
        ]);
        DB::table('tblstudent_enrollment')->insert([
            'student_id' => $studentId, 'standard_id' => $this->standardId, 'sub_institute_id' => $this->subInstituteId,
            'syear' => 2026,
        ]);

        return $studentId;
    }

    public function test_dry_run_reports_students_but_writes_nothing(): void
    {
        $student = $this->makeEnrolledStudent();

        $this->artisan('pal:pilot-enroll', [
            'chapterId' => $this->chapterId,
            'arm' => 'B',
            '--institute' => $this->subInstituteId,
            '--standard' => $this->standardId,
            '--syear' => 2026,
        ])->assertExitCode(0);

        $this->assertSame(0, PilotEnrollment::where('student_id', $student)->count());
    }

    public function test_confirm_flag_actually_enrolls_matching_students(): void
    {
        $student = $this->makeEnrolledStudent();

        $this->artisan('pal:pilot-enroll', [
            'chapterId' => $this->chapterId,
            'arm' => 'B',
            '--institute' => $this->subInstituteId,
            '--standard' => $this->standardId,
            '--syear' => 2026,
            '--cohort' => 'test-cohort',
            '--confirm' => true,
        ])->assertExitCode(0);

        $enrollment = PilotEnrollment::where('student_id', $student)->first();
        $this->assertNotNull($enrollment);
        $this->assertSame('B', $enrollment->arm);
        $this->assertSame('test-cohort', $enrollment->cohort_label);
        $this->assertSame(PilotEnrollment::STATUS_ACTIVE, $enrollment->status);
    }

    public function test_an_already_enrolled_student_is_not_enrolled_twice(): void
    {
        $student = $this->makeEnrolledStudent();
        PilotEnrollment::create([
            'student_id' => $student, 'sub_institute_id' => $this->subInstituteId,
            'chapter_id' => $this->chapterId, 'arm' => 'A', 'status' => PilotEnrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->artisan('pal:pilot-enroll', [
            'chapterId' => $this->chapterId,
            'arm' => 'B',
            '--institute' => $this->subInstituteId,
            '--standard' => $this->standardId,
            '--syear' => 2026,
            '--confirm' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, PilotEnrollment::where('student_id', $student)->count());
        $this->assertSame('A', PilotEnrollment::where('student_id', $student)->value('arm'), 'The original arm must not be overwritten.');
    }

    public function test_an_invalid_arm_value_fails_cleanly(): void
    {
        $this->artisan('pal:pilot-enroll', [
            'chapterId' => $this->chapterId,
            'arm' => 'C',
            '--institute' => $this->subInstituteId,
            '--standard' => $this->standardId,
        ])->assertExitCode(1);
    }

    public function test_the_unique_student_chapter_constraint_is_enforced_at_the_database_level(): void
    {
        $student = $this->makeEnrolledStudent();
        PilotEnrollment::create([
            'student_id' => $student, 'sub_institute_id' => $this->subInstituteId,
            'chapter_id' => $this->chapterId, 'arm' => 'A', 'status' => PilotEnrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PilotEnrollment::create([
            'student_id' => $student, 'sub_institute_id' => $this->subInstituteId,
            'chapter_id' => $this->chapterId, 'arm' => 'B', 'status' => PilotEnrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);
    }
}
