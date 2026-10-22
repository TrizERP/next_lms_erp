<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Modules\ModuleReadTools;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "How many courses are there for Grade 8?" must read the course catalogue filtered to class 8,
 * not the unfiltered subject list. READ-ONLY: only reads tool schemas.
 */
class ModuleReadQuestionArgumentsTest extends TestCase
{
    /** @return array<string, array{0:string,1:array<string,mixed>}> */
    public static function questions(): array
    {
        return [
            'grade' => ['How many courses are there for Grade 8?', ['standard_name' => '8']],
            'class' => ['Which courses run in class 10', ['standard_name' => '10']],
            'standard dash' => ['courses for standard-9', ['standard_name' => '9']],
            'no class' => ['How many courses are there?', []],
            'unrelated number' => ['List 8 courses', []],
        ];
    }

    /** @param array<string,mixed> $expected */
    #[DataProvider('questions')]
    public function test_a_class_in_the_question_filters_the_course_tool(string $question, array $expected): void
    {
        $this->assertSame($expected, (new ModuleReadTools())->argumentsFromQuestion('lms.courses', $question));
    }

    public function test_a_tool_without_a_class_argument_gets_none(): void
    {
        $this->assertSame([], (new ModuleReadTools())->argumentsFromQuestion('academics.subjects', 'courses for Grade 8'));
    }

    public function test_course_pages_lead_with_the_course_catalogue(): void
    {
        $this->assertSame('lms.courses', config('ai.lifecycle.modules.course.mcp_tools.0'));
        $this->assertSame('lms.courses', config('ai.lifecycle.modules.course-master.mcp_tools.0'));
    }
}
