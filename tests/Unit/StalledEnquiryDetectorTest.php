<?php

namespace Tests\Unit;

use App\Domain\Admissions\Risk\StalledEnquiryDetector;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Services\Mcp\AdmissionMcpService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Carbon;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Which enquiries count as stalled.
 *
 * The Admission screens write an enquiry's status as `approve` or `cancel`. The detector's
 * closed list used to carry `approved` and not `approve`, so an enquiry the school had
 * already approved was flagged as a 469-day critical follow-up risk and led the analysis.
 * No database is touched: the admission service is replaced with the rows it would return.
 */
class StalledEnquiryDetectorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function detector(array $rows): StalledEnquiryDetector
    {
        Carbon::setTestNow('2026-10-13');

        $service = Mockery::mock(AdmissionMcpService::class);
        $service->shouldReceive('listEnquiries')->andReturn(['data' => ['enquiries' => $rows]]);
        $service->shouldReceive('validateConfirmation')->andReturn(['success' => false]);

        $thresholds = Mockery::mock(ThresholdRegistry::class);
        $thresholds->shouldReceive('classify')->andReturn('high');

        return new StalledEnquiryDetector($service, $thresholds);
    }

    private function scope(): McpRequestContext
    {
        return new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);
    }

    private function row(int $id, string $status, string $due): array
    {
        return [
            'enquiry_id' => $id,
            'enquiry_no' => (string) (2022000 + $id),
            'student_name' => 'Student '.$id,
            'status' => $status,
            'followup_date' => $due,
            'standard_name' => '9',
            'mobile' => '9000000000',
        ];
    }

    public function test_an_enquiry_the_screens_marked_approve_is_not_a_stalled_follow_up(): void
    {
        $detector = $this->detector([
            $this->row(1, 'approve', '2025-07-01'),
            $this->row(2, 'new', '2026-09-30'),
        ]);

        $signals = $detector->detect($this->scope());

        $this->assertCount(1, $signals);
        $this->assertSame(2, (int) $signals[0]->subjectId);
    }

    public function test_every_closed_spelling_is_left_alone_and_open_ones_are_not(): void
    {
        $rows = [];
        foreach (['approve', 'approved', 'converted', 'closed', 'cancel', 'CANCEL'] as $i => $status) {
            $rows[] = $this->row($i + 1, $status, '2026-09-01');
        }
        $rows[] = $this->row(10, 'new', '2026-09-01');
        $rows[] = $this->row(11, '', '2026-09-01');

        $signals = $this->detector($rows)->detect($this->scope());

        $this->assertEqualsCanonicalizing([10, 11], array_map(fn ($s) => (int) $s->subjectId, $signals));
    }

    public function test_coverage_counts_only_open_enquiries(): void
    {
        $coverage = $this->detector([
            $this->row(1, 'approve', '2025-07-01'),
            $this->row(2, 'new', '2026-09-30'),
            $this->row(3, 'new', '2026-12-31'),
        ])->coverage($this->scope());

        $this->assertSame(2, $coverage['open']);
        $this->assertSame(1, $coverage['overdue']);
    }
}
