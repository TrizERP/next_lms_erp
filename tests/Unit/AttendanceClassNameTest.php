<?php

namespace Tests\Unit;

use App\Services\Mcp\AttendanceInsightService;
use PHPUnit\Framework\TestCase;

class AttendanceClassNameTest extends TestCase
{
    public function test_a_class_named_in_words_reduces_to_the_stored_name(): void
    {
        foreach (['Class 6', 'class-6', 'CLASS  6', 'Std. 6', 'Standard 6', 'Grade 6', '6', ' 6 ', 6] as $given) {
            $this->assertSame('6', AttendanceInsightService::standardName($given), (string) $given);
        }

        $this->assertSame('lkg', AttendanceInsightService::standardName('Class LKG'));
    }

    public function test_nothing_usable_is_no_filter(): void
    {
        foreach ([null, '', '   ', 'class', [], 1.5] as $given) {
            $this->assertNull(AttendanceInsightService::standardName($given));
        }
    }
}
