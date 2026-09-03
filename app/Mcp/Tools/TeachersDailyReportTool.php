<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\TeacherActivityService;

/**
 * The daily-task report, as a tool.
 *
 * Note the description says what each flag *is* — whether a record exists for that date.
 * That matters because this is the one tool here whose output reads like a judgement of
 * people. A teacher with no flags may have been absent, may teach a subject that sets no
 * homework, or may simply not have logged anything yet; none of that is visible in the
 * row, and the answer should not imply otherwise.
 */
class TeachersDailyReportTool extends AbstractMcpTool
{
    public function __construct(private readonly TeacherActivityService $service)
    {
    }

    protected function name(): string
    {
        return 'teachers.daily_report';
    }

    protected function description(): string
    {
        return 'For a given date, which teachers recorded each daily task: marked attendance, '
            . 'assigned homework, checked homework, answered a parent, handled a leave request. '
            . 'Only teachers with timetabled periods this year are included. Each flag reports '
            . 'whether the record exists on that date — it is not a measure of quality or effort.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => [
                    'type' => 'string',
                    'description' => 'The day to report on, as YYYY-MM-DD. Defaults to today.',
                ],
                'teacher_id' => ['type' => 'integer', 'minimum' => 1],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive'],
                    'description' => '"active" for teachers who recorded at least one task, "inactive" for none.',
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 300, 'default' => 100],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function annotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'teacher_daily_report.index'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->dailyReport($context, $arguments);
    }
}
