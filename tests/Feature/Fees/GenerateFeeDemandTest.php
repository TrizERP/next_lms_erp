<?php

namespace Tests\Feature\Fees;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use function App\Helpers\FeeBreackoff;

/**
 * Track A / Day 11 — "Generate Fee Demand (single student / class)".
 *
 * This system has no separate "demand" table: a demand is computed live from
 * the fee structure (`fees_breackoff`) joined against a student's enrollment
 * (`tblstudent_enrollment`) for an academic year — see the reusable
 * `FeeBreackoff()` helper (app/Helpers/Helper.php), which is what
 * `fees_collect_controller::getBk()`/`PaidUnpaid()` and every fee report use
 * under the hood. So "generating a demand" for one student/class is the act
 * of enrolling that student against a fee structure that already exists for
 * their academic year — the enrollment IS the link the demand is computed
 * from.
 *
 * This test builds that minimum real chain (one class, one fee structure,
 * one student, one enrollment — all schema-correct: `grade_id` on
 * `standard`/`fees_breackoff`/`tblstudent_enrollment` is a foreign key to
 * `academic_section`, confirmed via `fees_breackoff_controller.php:68,384`
 * and the `FeeBreackoff()` join itself — NOT `grade_master`, which is an
 * unrelated curriculum-board concept), then calls the real `FeeBreackoff()`
 * helper — the exact function production code uses — and asserts it returns
 * a demand correctly tied to that student and that academic year.
 */
class GenerateFeeDemandTest extends TestCase
{
    use DatabaseTransactions;

    private int $institute;

    private int $syear;

    private int $sectionId; // academic_section.id — the "grade" fee structure/enrollment actually key off.

    private int $standardId; // the class, e.g. "Standard-1".

    private int $quotaId;

    private int $feeTypeId; // fees_title.id — the fee head.

    private int $monthId;

    private int $structureAmount = 5000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syear = (int) date('Y');
        $this->institute = $this->makeInstitute();
        $this->sectionId = $this->makeAcademicSection();
        $this->standardId = $this->makeStandard();
        $this->quotaId = $this->makeQuota();
        $this->makeAcademicYear();
        $this->feeTypeId = $this->makeFeeHead();
        $this->monthId = 4 * 10000 + $this->syear; // April, this syear — see fees_breackoff.month_id convention.
        $this->makeFeeStructure();
    }

    public function test_enrolling_a_student_generates_a_demand_correctly_linked_to_the_fee_structure_and_academic_year(): void
    {
        $studentId = $this->makeStudent();
        $this->enrolStudent($studentId);

        // FeeBreackoff() is the real helper fees_collect_controller::getBk()
        // and PaidUnpaid() call to compute a student's demand — exercising
        // it here proves the demand comes from production logic, not a
        // hand-rolled query.
        $demand = FeeBreackoff([$studentId], $this->standardId, $this->syear, $this->institute);

        $this->assertCount(1, $demand, 'expected exactly one month-bucket of demand for this student/class/syear');

        $row = $demand[0];
        $this->assertSame($studentId, (int) $row->student_id);
        $this->assertSame($this->standardId, (int) $row->standard_id);
        $this->assertSame($this->sectionId, (int) $row->grade_id);
        $this->assertSame($this->quotaId, (int) $row->student_quota);
        $this->assertSame($this->syear, (int) $row->syear);
        $this->assertSame($this->monthId, (int) $row->month_id);
        $this->assertSame($this->structureAmount, (int) $row->bkoff, 'demand amount must equal the fee structure amount');
    }

    public function test_a_student_not_enrolled_in_the_class_has_no_demand(): void
    {
        $studentId = $this->makeStudent();
        // Deliberately not enrolled — the fee structure exists for the class,
        // but nothing links this student to it yet.

        $demand = FeeBreackoff([$studentId], $this->standardId, $this->syear, $this->institute);

        $this->assertCount(0, $demand, 'an unenrolled student must not show a demand against another class\'s structure');
    }

    public function test_a_student_enrolled_in_a_different_class_does_not_pick_up_this_structure(): void
    {
        $otherStandardId = DB::table('standard')->insertGetId([
            'grade_id' => $this->sectionId,
            'name' => 'Standard-2 (Demand Test)',
            'short_name' => 'D2',
            'sort_order' => 2,
            'medium' => 'English',
            'sub_institute_id' => $this->institute,
            'created_at' => now(),
        ]);

        $studentId = $this->makeStudent();
        DB::table('tblstudent_enrollment')->insert([
            'syear' => $this->syear,
            'student_id' => $studentId,
            'grade_id' => $this->sectionId,
            'standard_id' => $otherStandardId,
            'section_id' => 0,
            'student_quota' => (string) $this->quotaId,
            'start_date' => "{$this->syear}-04-01",
            'term_id' => 1,
            'sub_institute_id' => $this->institute,
            'created_on' => now(),
        ]);

        // No $standard override here (unlike the other tests) - passing one
        // makes FeeBreackoff() match fees_breackoff.standard_id against that
        // literal value instead of the student's own enrollment, which is a
        // deliberate "what would this structure look like for a hypothetical
        // standard" mode used by the breakoff-edit UI, not what we're
        // testing. Omitting it exercises the student's actual enrolled class.
        $demand = FeeBreackoff([$studentId], null, $this->syear, $this->institute);

        $this->assertCount(0, $demand, 'a structure built for Standard-1 must not generate a demand for a Standard-2 student');
    }

    private function makeInstitute(): int
    {
        return (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'SYNTHETIC Fee Demand Test School',
            'ShortCode' => 'FDT' . random_int(1000, 9999),
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

    private function makeAcademicSection(): int
    {
        return (int) DB::table('academic_section')->insertGetId([
            'sub_institute_id' => $this->institute,
            'title' => 'Primary (Demand Test)',
            'short_name' => 'PRI',
            'sort_order' => 1,
            'shift' => 'Morning',
            'medium' => 'English',
            'payment_link' => '',
            'created_at' => now(),
        ]);
    }

    private function makeStandard(): int
    {
        return (int) DB::table('standard')->insertGetId([
            'grade_id' => $this->sectionId,
            'name' => 'Standard-1 (Demand Test)',
            'short_name' => 'D1',
            'sort_order' => 1,
            'medium' => 'English',
            'sub_institute_id' => $this->institute,
            'created_at' => now(),
        ]);
    }

    private function makeQuota(): int
    {
        return (int) DB::table('student_quota')->insertGetId([
            'title' => 'General',
            'sort_order' => 1,
            'sub_institute_id' => $this->institute,
            'created_by' => 0,
            'created_on' => now(),
        ]);
    }

    private function makeAcademicYear(): void
    {
        DB::table('academic_year')->insert([
            'term_id' => 1,
            'syear' => $this->syear,
            'sub_institute_id' => $this->institute,
            'title' => 'TERM-1',
            'short_name' => 'T-1',
            'sort_order' => 1,
            'start_date' => "{$this->syear}-04-01",
            'end_date' => ($this->syear + 1) . '-03-31',
            'post_start_date' => "{$this->syear}-04-01",
            'post_end_date' => ($this->syear + 1) . '-03-31',
            'does_grades' => 'Y',
            'does_exams' => 'Y',
            'created_at' => now(),
        ]);
    }

    private function makeFeeHead(): int
    {
        $lookup = DB::table('fees_title_master')->where('id', 2)->first(); // global "Tution Fee" lookup row.

        return (int) DB::table('fees_title')->insertGetId([
            'fees_title_id' => $lookup->id,
            'fees_title' => $lookup->fee_paid_title,
            'display_name' => $lookup->title,
            'mandatory' => 1,
            'sort_order' => 1,
            'syear' => $this->syear,
            'sub_institute_id' => $this->institute,
            'other_fee_id' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeFeeStructure(): void
    {
        DB::table('fees_breackoff')->insert([
            'syear' => $this->syear,
            'admission_year' => $this->syear,
            'fee_type_id' => $this->feeTypeId,
            'quota' => $this->quotaId,
            'grade_id' => $this->sectionId,
            'standard_id' => $this->standardId,
            'section_id' => 0,
            'month_id' => $this->monthId,
            'amount' => $this->structureAmount,
            'sub_institute_id' => $this->institute,
            'created_at' => now(),
        ]);
    }

    private function makeStudent(): int
    {
        return (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Demand',
            'last_name' => 'Test',
            'admission_year' => $this->syear,
            'sub_institute_id' => $this->institute,
            'status' => 1,
            'file_size' => '',
            'file_type' => '',
            'created_on' => now(),
        ]);
    }

    private function enrolStudent(int $studentId): void
    {
        DB::table('tblstudent_enrollment')->insert([
            'syear' => $this->syear,
            'student_id' => $studentId,
            'grade_id' => $this->sectionId,
            'standard_id' => $this->standardId,
            'section_id' => 0,
            'student_quota' => (string) $this->quotaId,
            'start_date' => "{$this->syear}-04-01",
            'term_id' => 1,
            'sub_institute_id' => $this->institute,
            'created_on' => now(),
        ]);
    }
}
