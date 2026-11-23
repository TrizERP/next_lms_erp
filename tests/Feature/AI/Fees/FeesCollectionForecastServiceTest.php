<?php

namespace Tests\Feature\AI\Fees;

use App\Domain\AI\Signals\SignalStore;
use App\Domain\Fees\Collection\FeeArrearsDetector;
use App\Domain\Fees\Collection\FeesCollectionForecastService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FeesCollectionForecastService reads a student's own recorded
 * `fees_outstanding_balance` sweep history back from `ai_signals` and
 * computes a deterministic trend — never an estimate outside that
 * history. These tests write synthetic `ai_signals` rows for a real
 * student inside a transaction (rolled back automatically, same
 * convention as KnowledgeGraphMenuCategoryTest) rather than against any
 * live signal history, so this never depends on — or disturbs — whatever
 * real history a given installation happens to have.
 */
class FeesCollectionForecastServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $studentId;

    private int $subInstituteId;

    private McpRequestContext $scope;

    private FeesCollectionForecastService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $student = DB::table('tblstudent')->select('id', 'sub_institute_id')->whereNotNull('sub_institute_id')->first();

        if ($student === null) {
            $this->markTestSkipped('No students in this database to test against.');
        }

        $this->studentId = (int) $student->id;
        $this->subInstituteId = (int) $student->sub_institute_id;

        $this->scope = new McpRequestContext(
            userId: 1,
            role: 'admin',
            selectedInstituteId: $this->subInstituteId,
            allowedInstituteIds: [$this->subInstituteId],
            userProfileId: null,
            clientId: null,
            academicYear: null,
            termId: null,
            isAdmin: true,
            isStudent: false,
        );

        $this->service = app(FeesCollectionForecastService::class);
    }

    private function seedSignal(float $score, string $severity, \DateTimeInterface $detectedAt, string $status = 'resolved'): void
    {
        $now = now();

        DB::table('ai_signals')->insert([
            'signal_key' => FeeArrearsDetector::KEY,
            'domain' => 'k12',
            'subject_entity_key' => 'student',
            'subject_id' => $this->studentId,
            'subject_label' => 'Test fixture student',
            'score' => $score,
            'severity' => $severity,
            'confidence' => 1.0,
            'components' => json_encode([]),
            'context' => json_encode([]),
            'status' => $status,
            'detected_at' => $detectedAt,
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_no_history_is_reported_as_unavailable_with_a_reason(): void
    {
        $result = $this->service->forecastFor($this->scope, $this->studentId);

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('0 historical', $result['reason']);
    }

    public function test_a_single_history_point_is_still_not_enough(): void
    {
        $this->seedSignal(5000, 'moderate', now()->subDays(10));

        $result = $this->service->forecastFor($this->scope, $this->studentId);

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('1 historical', $result['reason']);
    }

    public function test_two_points_rising_is_reported_as_increasing_with_capped_confidence(): void
    {
        $this->seedSignal(5000, 'moderate', now()->subDays(30));
        $this->seedSignal(8000, 'moderate', now());

        $result = $this->service->forecastFor($this->scope, $this->studentId);

        $this->assertTrue($result['available']);
        $this->assertSame('increasing', $result['trend']);
        $this->assertSame(2, $result['history_points']);
        $this->assertSame(0.45, $result['confidence'], 'A two-point trend must never claim more confidence than the weakest tier.');
        $this->assertEquals(round((8000 - 5000) / 30, 4), $result['rate_per_day']);
        $this->assertEquals(round(((8000 - 5000) / 30) * 30, 2), $result['projected_30d_change']);
    }

    public function test_two_points_falling_is_reported_as_decreasing(): void
    {
        $this->seedSignal(9000, 'high', now()->subDays(20));
        $this->seedSignal(4000, 'moderate', now());

        $result = $this->service->forecastFor($this->scope, $this->studentId);

        $this->assertTrue($result['available']);
        $this->assertSame('decreasing', $result['trend']);
    }

    public function test_an_unchanged_balance_is_reported_as_flat(): void
    {
        $this->seedSignal(5000, 'moderate', now()->subDays(30));
        $this->seedSignal(5000, 'moderate', now());

        $result = $this->service->forecastFor($this->scope, $this->studentId);

        $this->assertTrue($result['available']);
        $this->assertSame('flat', $result['trend']);
    }

    public function test_confidence_grows_with_more_history_but_stays_capped(): void
    {
        $this->seedSignal(1000, 'low', now()->subDays(90));
        $this->seedSignal(2000, 'low', now()->subDays(60));
        $this->seedSignal(3000, 'moderate', now()->subDays(30));

        $result = $this->service->forecastFor($this->scope, $this->studentId);
        $this->assertSame(3, $result['history_points']);
        $this->assertSame(0.6, $result['confidence']);

        $this->seedSignal(4000, 'moderate', now());

        $result = $this->service->forecastFor($this->scope, $this->studentId);
        $this->assertSame(4, $result['history_points']);
        $this->assertSame(0.75, $result['confidence']);

        $this->seedSignal(5000, 'moderate', now()->addMinute());

        $result = $this->service->forecastFor($this->scope, $this->studentId);
        $this->assertSame(5, $result['history_points']);
        $this->assertSame(0.85, $result['confidence'], 'Confidence must never exceed MAX_CONFIDENCE regardless of how much history exists.');
    }

    public function test_history_for_a_different_signal_key_is_ignored(): void
    {
        DB::table('ai_signals')->insert([
            'signal_key' => 'some_unrelated_signal',
            'domain' => 'k12',
            'subject_entity_key' => 'student',
            'subject_id' => $this->studentId,
            'subject_label' => 'Test fixture student',
            'score' => 99999,
            'severity' => 'critical',
            'confidence' => 1.0,
            'components' => json_encode([]),
            'context' => json_encode([]),
            'status' => 'open',
            'detected_at' => now(),
            'sub_institute_id' => $this->subInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->forecastFor($this->scope, $this->studentId);

        $this->assertFalse($result['available'], 'A signal under an unrelated key must not be counted as fee-balance history.');
    }
}
