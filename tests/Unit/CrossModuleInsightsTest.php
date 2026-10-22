<?php

namespace Tests\Unit;

use App\Brain\Intelligence\CrossModuleInsights as X;
use PHPUnit\Framework\TestCase;

class CrossModuleInsightsTest extends TestCase
{
    private function att(array $pairs): array
    {
        return array_map(fn ($l, $v) => ['label' => (string) $l, 'attendanceRate' => $v, 'students' => 30], array_keys($pairs), $pairs);
    }

    private function res(array $pairs): array
    {
        return array_map(fn ($l, $v) => ['label' => (string) $l, 'percentage' => $v, 'students' => 30], array_keys($pairs), $pairs);
    }

    public function test_class_labels_match_only_after_trim_case_and_spacing(): void
    {
        $this->assertSame('cbse-7', X::classKey('  CBSE-7 '));
        $this->assertSame('11 sci', X::classKey('11   Sci'));
        $j = X::joinClasses($this->att(['6' => 90.0, 'Std 7' => 88.0]), $this->res(['6' => 70.0, '7' => 65.0]));
        $this->assertCount(1, $j['matched']);
        $this->assertSame(['Std 7'], $j['attendanceOnly'], 'no fuzzy guessing');
        $this->assertSame(['7'], $j['resultsOnly']);
    }

    public function test_correlation_known_values(): void
    {
        $this->assertEqualsWithDelta(1.0, X::pearson([1, 2, 3, 4], [2, 4, 6, 8]), 1e-9);
        $this->assertEqualsWithDelta(-1.0, X::pearson([1, 2, 3], [9, 6, 3]), 1e-9);
        $this->assertNull(X::pearson([1, 1, 1], [1, 2, 3]), 'no variance is undefined, not zero');
        $this->assertNull(X::pearson([1], [1]));
        $this->assertEquals([1.0, 2.5, 2.5, 4.0], X::ranks([10, 20, 20, 30]));
        $this->assertEqualsWithDelta(1.0, X::spearman([1, 2, 3, 100], [1, 2, 3, 4]), 1e-9, 'rank-based: one extreme class does not break it');
    }

    public function test_a_positive_association_is_reported_as_association_never_as_cause(): void
    {
        $labels = ['1', '2', '3', '4', '5', '6', '7'];
        $att = array_combine($labels, [80.0, 82, 85, 88, 90, 92, 95]);
        $res = array_combine($labels, [50.0, 55, 58, 63, 66, 70, 75]);
        $i = X::attendanceVsResults($this->att($att), $this->res($res), 87.0, 62.0, '2025');

        $this->assertSame('supported', $i['status']);
        $this->assertEquals(1.0, $i['metrics']['spearman']);
        $this->assertSame('strong', $i['metrics']['strength']);
        $this->assertSame(7, $i['metrics']['classesCompared']);
        $this->assertStringContainsString('does not show that one causes the other', $i['explanation']);
        $this->assertNotEmpty($i['limitations']);
        $this->assertNotEmpty($i['sources']);
        $this->assertSame(['1', '2', '3'], $i['belowOnBoth']);
    }

    public function test_too_few_matched_classes_declines_instead_of_inventing_a_pattern(): void
    {
        $i = X::attendanceVsResults($this->att(['1' => 90.0, '2' => 80.0]), $this->res(['1' => 70.0, '2' => 60.0]), 85.0, 65.0, '2025');
        $this->assertSame('insufficient_data', $i['status']);
        $this->assertSame([], $i['metrics']);
    }

    public function test_missing_module_data_is_unavailable_with_a_reason(): void
    {
        $i = X::attendanceVsResults([], $this->res(['1' => 70.0]), null, 70.0, '2025');
        $this->assertSame('unavailable', $i['status']);
        $this->assertStringContainsString('attendance', $i['explanation']);
        $this->assertSame('unavailable', X::attendanceVsResults($this->att(['1' => 90.0]), [], 90.0, null, null)['status']);
    }

    public function test_no_variance_gives_a_plain_explanation_not_a_fake_coefficient(): void
    {
        $labels = ['1', '2', '3', '4', '5', '6'];
        $same = array_combine($labels, array_fill(0, 6, 90.0));
        $res = array_combine($labels, [50.0, 55, 58, 63, 66, 70]);
        $i = X::attendanceVsResults($this->att($same), $this->res($res), 90.0, 60.0, '2025');
        $this->assertSame('supported', $i['status']);
        $this->assertNull($i['metrics']['spearman']);
        $this->assertStringContainsString('do not differ enough', $i['explanation']);
    }

    public function test_a_negative_association_is_flagged_as_suspect_data(): void
    {
        $labels = ['1', '2', '3', '4', '5', '6'];
        $i = X::attendanceVsResults(
            $this->att(array_combine($labels, [95.0, 92, 90, 88, 85, 80])),
            $this->res(array_combine($labels, [50.0, 55, 58, 63, 66, 70])),
            90.0,
            60.0,
            '2025'
        );
        $this->assertLessThan(0, $i['metrics']['spearman']);
        $this->assertStringContainsString('check the sources', $i['explanation']);
    }
}
