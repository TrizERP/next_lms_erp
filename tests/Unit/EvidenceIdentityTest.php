<?php

namespace Tests\Unit;

use App\Domain\AI\Evidence\EvidenceItem;
use PHPUnit\Framework\TestCase;

class EvidenceIdentityTest extends TestCase
{
    public function test_a_computed_measurement_has_a_stable_source_service_identity(): void
    {
        $item = EvidenceItem::fromComputation(
            kind: 'assessment_trend',
            subjectEntityKey: 'student',
            subjectId: 42,
            summary: 'Current assessment trend.',
            sourceService: 'AssessmentDeclineDetector',
            observedAt: '2026-09-01 10:00:00',
        );

        $this->assertSame('AssessmentDeclineDetector', $item->sourceService);
        $this->assertNull($item->sourceTable);
        $this->assertNull($item->sourceId);
    }
}
