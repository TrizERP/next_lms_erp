<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * The minimum fee-collection fixture chain (institute → academic year →
 * fee head → fee structure → receipt book/template → enrolled student)
 * that `pay_fees()` needs to record a real payment, shared by the fee
 * tests that need to drive a real collection — not just read one.
 *
 * Lifted from CollectFeeTest's own private `make*` methods rather than
 * refactoring that file (it already passes; duplicating a dozen small
 * builders is a smaller risk than touching a working test).
 */
trait FeesCollectTestHelpers
{
    protected function makeFeesInstitute(): int
    {
        return (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'SYNTHETIC Fees Intelligence Test School',
            'ShortCode' => 'FIT'.random_int(1000, 9999),
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

    protected function makeFeesAcademicSection(int $institute): int
    {
        return (int) DB::table('academic_section')->insertGetId([
            'sub_institute_id' => $institute,
            'title' => 'Primary (Fees Intelligence Test)',
            'short_name' => 'PRI',
            'sort_order' => 1,
            'shift' => 'Morning',
            'medium' => 'English',
            'payment_link' => '',
            'created_at' => now(),
        ]);
    }

    protected function makeFeesStandard(int $institute, int $sectionId): int
    {
        return (int) DB::table('standard')->insertGetId([
            'grade_id' => $sectionId,
            'name' => 'Standard-1 (Fees Intelligence Test)',
            'short_name' => 'D1',
            'sort_order' => 1,
            'medium' => 'English',
            'sub_institute_id' => $institute,
            'created_at' => now(),
        ]);
    }

    protected function makeFeesQuota(int $institute): int
    {
        return (int) DB::table('student_quota')->insertGetId([
            'title' => 'General',
            'sort_order' => 1,
            'sub_institute_id' => $institute,
            'created_by' => 0,
            'created_on' => now(),
        ]);
    }

    protected function makeFeesUser(int $institute, int $syear): int
    {
        return (int) DB::table('tbluser')->insertGetId([
            'user_name' => 'fit_admin'.random_int(10000, 99999),
            'password' => bcrypt('secret'),
            'first_name' => 'Fees',
            'last_name' => 'Tester',
            'email' => 'fit'.random_int(10000, 99999).'@example.com',
            'mobile' => '9999999999',
            'user_profile_id' => 1,
            'join_year' => (string) $syear,
            'sub_institute_id' => $institute,
            'is_admin' => 1,
            'status' => 1,
        ]);
    }

    protected function makeFeesAcademicYear(int $institute, int $syear): void
    {
        DB::table('academic_year')->insert([
            'term_id' => 1,
            'syear' => $syear,
            'sub_institute_id' => $institute,
            'title' => 'TERM-1',
            'short_name' => 'T-1',
            'sort_order' => 1,
            'start_date' => "{$syear}-04-01",
            'end_date' => ($syear + 1).'-03-31',
            'post_start_date' => "{$syear}-04-01",
            'post_end_date' => ($syear + 1).'-03-31',
            'does_grades' => 'Y',
            'does_exams' => 'Y',
            'created_at' => now(),
        ]);
    }

    protected function makeFeesMapYearRow(int $institute, int $syear): void
    {
        DB::table('fees_map_years')->insert([
            'from_month' => 4,
            'to_month' => 3,
            'syear' => $syear,
            'sub_institute_id' => $institute,
            'type' => null,
            'created_at' => now(),
        ]);
    }

    protected function makeFeesHead(int $institute, int $syear): int
    {
        $lookup = DB::table('fees_title_master')->where('id', 2)->first(); // global "Tution Fee" lookup row.

        return (int) DB::table('fees_title')->insertGetId([
            'fees_title_id' => $lookup->id,
            'fees_title' => $lookup->fee_paid_title, // 'tution_fee' - a real fees_collect column.
            'display_name' => $lookup->title,
            'mandatory' => 1,
            'sort_order' => 1,
            'syear' => $syear,
            'sub_institute_id' => $institute,
            'other_fee_id' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function makeFeesStructure(int $institute, int $syear, int $sectionId, int $standardId, int $quotaId, int $feeTypeId, int $monthId, int $amount): void
    {
        DB::table('fees_breackoff')->insert([
            'syear' => $syear,
            'admission_year' => $syear,
            'fee_type_id' => $feeTypeId,
            'quota' => $quotaId,
            'grade_id' => $sectionId,
            'standard_id' => $standardId,
            'section_id' => 0,
            'month_id' => $monthId,
            'amount' => $amount,
            'sub_institute_id' => $institute,
            'created_at' => now(),
        ]);
    }

    protected function makeFeesReceiptBook(int $institute, int $syear, int $sectionId, int $standardId, int $feeTypeId): void
    {
        DB::table('fees_receipt_book_master')->insert([
            'syear' => $syear,
            'receipt_line_1' => 'Test Receipt',
            'receipt_prefix' => null,
            'sort_order' => 1,
            'last_receipt_number' => 0,
            'grade_id' => $sectionId,
            'standard_id' => $standardId,
            'fees_head_id' => $feeTypeId,
            'status' => 1,
            'sub_institute_id' => $institute,
            'created_on' => now(),
        ]);
    }

    protected function makeFeesReceiptTemplate(int $institute, int $syear): void
    {
        DB::table('fees_receipt_css')->insertOrIgnore([
            'receipt_id' => 'A5',
            'css' => '',
            'created_at' => now(),
        ]);

        DB::table('fees_config_master')->insert([
            'fees_receipt_template' => 'A5',
            'syear' => $syear,
            'sub_institute_id' => $institute,
            'created_on' => now(),
        ]);
    }

    protected function makeFeesStudent(int $institute, int $syear, ?string $firstName = null, ?string $lastName = null, ?string $mobile = null): int
    {
        return (int) DB::table('tblstudent')->insertGetId([
            'first_name' => $firstName ?? 'Fees',
            'last_name' => $lastName ?? 'Test',
            'admission_year' => $syear,
            'sub_institute_id' => $institute,
            'status' => 1,
            'mobile' => $mobile,
            'file_size' => '',
            'file_type' => '',
            'created_on' => now(),
        ]);
    }

    protected function enrolFeesStudent(int $studentId, int $institute, int $syear, int $sectionId, int $standardId, int $quotaId): void
    {
        DB::table('tblstudent_enrollment')->insert([
            'syear' => $syear,
            'student_id' => $studentId,
            'grade_id' => $sectionId,
            'standard_id' => $standardId,
            'section_id' => 0,
            'student_quota' => (string) $quotaId,
            'start_date' => "{$syear}-04-01",
            'term_id' => 1,
            'sub_institute_id' => $institute,
            'created_on' => now(),
        ]);
    }
}
