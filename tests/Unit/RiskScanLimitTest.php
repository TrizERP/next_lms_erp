<?php

namespace Tests\Unit;

use App\Domain\AI\Lifecycle\Support\RiskScanLimit;
use PHPUnit\Framework\TestCase;

class RiskScanLimitTest extends TestCase
{
    public function test_it_honours_the_requested_top_count_without_exceeding_available_cases(): void
    {
        $this->assertSame(3, RiskScanLimit::fromQuestion('Show me the top 3 students at risk.', 4));
        $this->assertSame(2, RiskScanLimit::fromQuestion('Show me the top 9 students at risk.', 2));
    }

    public function test_it_uses_a_bounded_default_when_no_top_count_is_requested(): void
    {
        $this->assertSame(10, RiskScanLimit::fromQuestion('Which students are at risk?', 12));
        $this->assertSame(0, RiskScanLimit::fromQuestion('Which students are at risk?', 0));
    }
}
