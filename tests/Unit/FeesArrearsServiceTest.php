<?php

namespace Tests\Unit;

use App\Services\Mcp\FeesArrearsService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The arithmetic and the wording of the cohort arrears tool.
 *
 * Extends PHPUnit's TestCase rather than Tests\TestCase, matching LifecyclePipelineTest:
 * these exercise the service's own reasoning with no database and no container, so they
 * still run when the application cannot boot.
 *
 * The cohort query and the per-student lookup are deliberately *not* tested here — they
 * are thin wrappers over `FeesPendingService`, which is the estate's existing arrears
 * definition and has its own behaviour. What is tested is everything this class decides
 * for itself, and in particular the two sentences it must never say:
 *
 *   - "nobody owes anything" when in fact nothing could be looked up, and
 *   - a bounded sample presented as a school-wide figure.
 */
class FeesArrearsServiceTest extends TestCase
{
    private function service(): FeesArrearsService
    {
        // The constructor dependency is unused by the methods under test, so the service
        // is built without booting the container.
        return (new ReflectionClass(FeesArrearsService::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function call(string $method, array $args): mixed
    {
        $reflection = new ReflectionClass(FeesArrearsService::class);
        $handle = $reflection->getMethod($method);
        $handle->setAccessible(true);

        return $handle->invokeArgs($this->service(), $args);
    }

    // ---------------------------------------------------------------- arithmetic

    public function test_it_sums_remaining_across_pending_rows(): void
    {
        $total = $this->call('sumRemaining', [[
            ['remain' => 1500],
            ['remain' => 250.50],
        ]]);

        $this->assertSame(1750.5, $total);
    }

    /**
     * The fee tables hand back formatted strings. `(float) "1,200"` is 1.0, which would
     * understate a debt by three orders of magnitude and still look like a number.
     */
    public function test_it_survives_thousands_separators(): void
    {
        $total = $this->call('sumRemaining', [[
            ['remain' => '1,200'],
            ['remain' => '2 400'],
        ]]);

        $this->assertSame(3600.0, $total);
    }

    public function test_it_treats_objects_and_missing_keys_as_zero(): void
    {
        $total = $this->call('sumRemaining', [[
            (object) ['remain' => '100'],
            ['month' => 'April'],
        ]]);

        $this->assertSame(100.0, $total);
    }

    // ---------------------------------------------------------------- wording

    public function test_a_complete_sweep_with_no_arrears_says_so_plainly(): void
    {
        $summary = $this->call('summarise', [[], 25, 25, false, 0]);

        $this->assertSame('None of the 25 students checked has an outstanding balance.', $summary);
    }

    /**
     * The caveat that matters most. Twenty-five of 3,435 children is a 0.7% sample, and a
     * sentence that omits that is a school-wide claim.
     */
    public function test_a_truncated_sweep_says_it_is_not_the_whole_school(): void
    {
        $summary = $this->call('summarise', [[['outstanding' => 500.0]], 25, 3435, true, 0]);

        $this->assertStringContainsString('1 of 25 students checked has money outstanding', $summary);
        $this->assertStringContainsString('first 25 of 3435 students in scope', $summary);
        $this->assertStringContainsString('not the whole school', $summary);
    }

    public function test_partial_failures_are_excluded_from_the_denominator_and_named(): void
    {
        // 10 checked, 3 of them failed, so only 7 were actually answered.
        $summary = $this->call('summarise', [[['outstanding' => 10.0]], 10, 10, false, 3]);

        $this->assertStringContainsString('1 of 7 students checked has money outstanding', $summary);
        $this->assertStringContainsString('3 lookups failed and are excluded', $summary);
    }

    public function test_it_uses_singular_wording_for_one_failure(): void
    {
        $summary = $this->call('summarise', [[], 2, 2, false, 1]);

        $this->assertStringContainsString('1 lookup failed and is excluded', $summary);
    }
}
