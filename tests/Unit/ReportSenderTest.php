<?php

namespace Tests\Unit;

use App\Services\Mcp\ReportSender;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * What a person actually receives when a report is sent.
 *
 * The property under test is a disclosure one, and it is the reason this class exists
 * at all: a fees arrears report is one document naming every family that owes money,
 * so the obvious implementation — mail the report to everybody in it — tells each of
 * those families about all of the others. Every test here is a way of asking "does
 * this recipient's notice contain anybody but them?".
 *
 * No database and no container: these exercise the composition rules only, so they
 * still run when the application cannot boot, and nothing here can send anything.
 */
class ReportSenderTest extends TestCase
{
    private function sender(): ReportSender
    {
        // The generator dependency is only reached through resolve(), which none of
        // these tests calls.
        return (new ReflectionClass(ReportSender::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function call(string $method, array $args): mixed
    {
        $handle = (new ReflectionClass(ReportSender::class))->getMethod($method);
        $handle->setAccessible(true);

        return $handle->invokeArgs($this->sender(), $args);
    }

    /** @return array{module: string, source: string, columns: array<int, string>} */
    private function figures(string $module = 'fees'): array
    {
        return [
            'module' => $module,
            'source' => 'fees.arrears',
            'columns' => ['student_id', 'student_name', 'enrollment_no', 'standard_name', 'outstanding', 'pending_items'],
        ];
    }

    public function test_a_notice_carries_only_its_own_recipients_figures(): void
    {
        $html = $this->call('notice', [
            $this->figures(),
            [
                'student_id' => 190488,
                'student_name' => 'Riya Mayur Patel',
                'enrollment_no' => 'ENR-2026-0041',
                'standard_name' => 'Grade 6',
                'outstanding' => 4200.0,
                'pending_items' => 2,
            ],
            'Riya Mayur Patel',
        ]);

        $this->assertStringContainsString('Riya Mayur Patel', $html);
        $this->assertStringContainsString('4200', $html);
        $this->assertStringContainsString('Grade 6', $html);

        // The disclosure test: a name from elsewhere in the same report must be absent.
        // This is what separates a notice from a mailed-out arrears register.
        $this->assertStringNotContainsString('Abhi D Raval', $html);
        $this->assertStringNotContainsString('Neha S Shah', $html);
    }

    public function test_internal_identifiers_are_not_printed_to_the_recipient(): void
    {
        $html = $this->call('notice', [
            $this->figures(),
            ['student_id' => 190488, 'student_name' => 'Riya Mayur Patel', 'outstanding' => 4200.0],
            'Riya Mayur Patel',
        ]);

        // The row's identity is how the system found this person; it is not information
        // for them, and printing it invites a parent to quote a primary key at the office.
        $this->assertStringNotContainsString('190488', $html);
        $this->assertStringNotContainsString('Student id', $html);
    }

    public function test_the_name_is_not_repeated_as_a_table_row(): void
    {
        $html = $this->call('notice', [
            $this->figures(),
            ['student_id' => 1, 'student_name' => 'Riya Mayur Patel', 'outstanding' => 100.0],
            'Riya Mayur Patel',
        ]);

        // Addressed once, in the salutation. A "Student name: Riya" row underneath reads
        // like a form letter that did not quite work.
        $this->assertSame(1, substr_count($html, 'Riya Mayur Patel'));
    }

    public function test_a_recipient_with_no_name_is_still_addressed(): void
    {
        $html = $this->call('notice', [
            $this->figures(),
            ['student_id' => 1, 'outstanding' => 100.0],
            '',
        ]);

        $this->assertStringContainsString('Dear Parent or guardian', $html);
    }

    public function test_empty_values_are_omitted_rather_than_shown_blank(): void
    {
        $html = $this->call('notice', [
            $this->figures(),
            ['student_id' => 1, 'standard_name' => null, 'enrollment_no' => '', 'outstanding' => 100.0],
            'Riya Mayur Patel',
        ]);

        // A notice claiming "Standard name:" with nothing after it looks like the school
        // has lost the record.
        $this->assertStringNotContainsString('Standard name', $html);
        $this->assertStringNotContainsString('Enrollment no', $html);
        $this->assertStringContainsString('Outstanding', $html);
    }

    public function test_values_are_escaped_before_they_reach_an_email_client(): void
    {
        $html = $this->call('notice', [
            $this->figures(),
            ['student_id' => 1, 'standard_name' => '<script>alert(1)</script>', 'outstanding' => 100.0],
            '<b>Riya</b>',
        ]);

        // An email client is one more renderer that would honour markup, so the report's
        // escaping discipline has to travel with the notice.
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>Riya</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_each_module_opens_with_something_true_about_itself(): void
    {
        $fees = $this->call('notice', [$this->figures('fees'), ['student_id' => 1, 'outstanding' => 1], 'A']);
        $attendance = $this->call('notice', [$this->figures('attendance'), ['student_id' => 1, 'present_days' => 40], 'A']);

        $this->assertStringContainsString('outstanding', $fees);
        $this->assertStringContainsString('attendance', $attendance);
        // Sending an attendance summary under a fees subject line is the kind of error
        // that generates phone calls.
        $this->assertSame('Your outstanding school fees', $this->call('subjectFor', ['fees', 'Report']));
        $this->assertSame('Your attendance record', $this->call('subjectFor', ['attendance', 'Report']));
    }

    public function test_a_row_is_reached_by_whichever_identity_it_carries(): void
    {
        // Fees and attendance rows are about enrolled students; an admissions enquiry is
        // not a student yet, and carries its own id instead.
        $this->assertSame('student_id:7', $this->call('identityOf', [['student_id' => 7]]));
        $this->assertSame('enquiry_id:9', $this->call('identityOf', [['enquiry_id' => 9]]));
        // A row naming nobody is skipped rather than mailed to a guess.
        $this->assertNull($this->call('identityOf', [['student_name' => 'Riya Mayur Patel']]));
    }

    public function test_only_a_real_address_counts_as_reachable(): void
    {
        $this->assertSame('parent@example.com', $this->call('validEmail', ['  parent@example.com  ']));
        // Every one of these has been seen in a contact column, and each would either
        // bounce or, worse, deliver to somebody unintended.
        $this->assertNull($this->call('validEmail', ['not-an-address']));
        $this->assertNull($this->call('validEmail', ['-']));
        $this->assertNull($this->call('validEmail', ['']));
        $this->assertNull($this->call('validEmail', [null]));
    }

    public function test_the_send_ceiling_is_an_accident_limit_not_a_page_size(): void
    {
        // Documented here because the number is a judgement, not a constraint: a report
        // resolving to more recipients than this is likelier a mis-scoped query than a
        // deliberate school-wide mailing, and the mistake cannot be recalled.
        $this->assertSame(200, ReportSender::MAX_RECIPIENTS);
    }
}
