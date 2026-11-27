<?php

namespace Tests\Feature\Fees;

use App\Services\Platform\Fees\FeesFamilyGroupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeesCollectTestHelpers;
use Tests\TestCase;

/**
 * Family/sibling grouping by shared guardian mobile number — the only
 * real, live signal for this (see FeesFamilyGroupService's own docblock
 * for why). These tests exist to keep two things true as the grouping
 * logic changes: a shared mobile number alone groups students, but a
 * group with disagreeing father names or an exact duplicate name is
 * flagged rather than presented as an ordinary sibling group — and every
 * active student is accounted for exactly once across
 * grouped/ungrouped/no-contact.
 */
class FeesFamilyGroupServiceTest extends TestCase
{
    use DatabaseTransactions;
    use FeesCollectTestHelpers;

    private int $institute;

    private int $syear;

    private FeesFamilyGroupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syear = (int) date('Y');
        $this->institute = $this->makeFeesInstitute();
        $this->service = app(FeesFamilyGroupService::class);
    }

    private function studentWithFather(string $mobile, string $first, string $last, string $father): int
    {
        return (int) DB::table('tblstudent')->insertGetId([
            'first_name' => $first,
            'last_name' => $last,
            'father_name' => $father,
            'mobile' => $mobile,
            'admission_year' => $this->syear,
            'sub_institute_id' => $this->institute,
            'status' => 1,
            'file_size' => '',
            'file_type' => '',
            'created_on' => now(),
        ]);
    }

    public function test_two_students_sharing_a_mobile_with_the_same_guardian_are_grouped_with_no_flags(): void
    {
        $this->studentWithFather('9876543210', 'Riya', 'Shah', 'Parent A');
        $this->studentWithFather('9876543210', 'Rahul', 'Shah', 'Parent A');

        $summary = $this->service->summary($this->institute, $this->syear);

        $this->assertTrue($summary['available']);
        $this->assertSame(1, $summary['family_groups']);
        $this->assertSame(2, $summary['students_in_groups']);
        $this->assertSame(0, $summary['ambiguous']['mixed_guardian_groups']);
        $this->assertSame(0, $summary['ambiguous']['duplicate_name_groups']);
    }

    public function test_two_students_sharing_a_mobile_with_different_father_names_are_flagged_mixed_guardian(): void
    {
        $this->studentWithFather('9876500001', 'Amit', 'Mehta', 'Rakesh Mehta');
        $this->studentWithFather('9876500001', 'Priya', 'Verma', 'Sanjay Verma');

        $summary = $this->service->summary($this->institute, $this->syear);

        $this->assertSame(1, $summary['ambiguous']['mixed_guardian_groups']);
        $this->assertSame(2, $summary['ambiguous']['mixed_guardian_students']);
    }

    public function test_an_exact_duplicate_full_name_sharing_a_mobile_is_flagged_as_a_likely_duplicate_record(): void
    {
        $this->studentWithFather('9876500002', 'Karan', 'Joshi', 'Deepak Joshi');
        $this->studentWithFather('9876500002', 'Karan', 'Joshi', 'Deepak Joshi');

        $summary = $this->service->summary($this->institute, $this->syear);

        $this->assertSame(1, $summary['ambiguous']['duplicate_name_groups']);
        $this->assertSame(2, $summary['ambiguous']['duplicate_name_students']);
        $this->assertSame(0, $summary['ambiguous']['mixed_guardian_groups'], 'same father name on both rows must not also read as mixed guardian');
    }

    public function test_students_with_different_mobile_numbers_are_not_grouped(): void
    {
        $this->studentWithFather('9876500003', 'Neha', 'Patel', 'Parent X');
        $this->studentWithFather('9876500004', 'Dev', 'Rao', 'Parent Y');

        $summary = $this->service->summary($this->institute, $this->syear);

        $this->assertSame(0, $summary['family_groups']);
        $this->assertSame(0, $summary['students_in_groups']);
        $this->assertSame(2, $summary['ungrouped_students']);
    }

    public function test_every_active_student_is_accounted_for_exactly_once(): void
    {
        $this->studentWithFather('9876500005', 'Sibling', 'One', 'Parent Z');
        $this->studentWithFather('9876500005', 'Sibling', 'Two', 'Parent Z');
        $this->studentWithFather('9876500006', 'Only', 'Child', 'Parent Q');
        DB::table('tblstudent')->insertGetId([
            'first_name' => 'No', 'last_name' => 'Contact', 'mobile' => null,
            'admission_year' => $this->syear, 'sub_institute_id' => $this->institute,
            'status' => 1, 'file_size' => '', 'file_type' => '', 'created_on' => now(),
        ]);

        $summary = $this->service->summary($this->institute, $this->syear);
        $total = DB::table('tblstudent')
            ->where('sub_institute_id', $this->institute)
            ->where(function ($q) { $q->whereNull('student_inactive')->orWhere('student_inactive', '!=', 1); })
            ->count();

        $this->assertSame($total, $summary['students_in_groups'] + $summary['ungrouped_students'] + $summary['no_contact_students']);
        $this->assertSame(1, $summary['no_contact_students']);
    }

    public function test_a_shared_mobile_in_a_different_institute_does_not_leak_across_tenants(): void
    {
        $otherInstitute = $this->makeFeesInstitute();

        $this->studentWithFather('9876500007', 'This', 'Tenant', 'Parent Here');
        DB::table('tblstudent')->insertGetId([
            'first_name' => 'Other', 'last_name' => 'Tenant', 'mobile' => '9876500007',
            'father_name' => 'Parent There', 'admission_year' => $this->syear,
            'sub_institute_id' => $otherInstitute, 'status' => 1, 'file_size' => '', 'file_type' => '', 'created_on' => now(),
        ]);

        $summary = $this->service->summary($this->institute, $this->syear);

        $this->assertSame(0, $summary['family_groups'], 'one student in this tenant sharing a number with a student in another tenant is not a family group');
    }
}
