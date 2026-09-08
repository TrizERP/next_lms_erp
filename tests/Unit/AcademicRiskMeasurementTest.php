<?php

namespace Tests\Unit;

use App\Domain\K12\AcademicRisk\AssessmentScore;
use App\Domain\K12\AcademicRisk\AttendanceCode;
use PHPUnit\Framework\TestCase;

class AcademicRiskMeasurementTest extends TestCase
{
    public function test_attendance_rate_excludes_non_present_or_absent_codes(): void
    {
        $codes = ['P', 'A', 'L', 'H', null];
        $counted = array_values(array_filter($codes, fn ($code) => AttendanceCode::isCounted($code)));

        $this->assertSame(['P', 'A'], $counted);
        $this->assertTrue(AttendanceCode::isPresent(' p '));
        $this->assertTrue(AttendanceCode::isAbsent('a'));
    }

    public function test_assessment_score_requires_a_known_question_count(): void
    {
        $this->assertSame(0.75, AssessmentScore::ratio((object) ['total_right' => 3, 'total_wrong' => 1]));
        $this->assertNull(AssessmentScore::ratio((object) ['total_right' => 2, 'total_wrong' => 0]));
        $this->assertNull(AssessmentScore::ratio((object) ['obtain_marks' => 40]));
    }
}
