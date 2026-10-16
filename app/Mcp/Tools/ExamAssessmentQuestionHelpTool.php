<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\ExamQuestionHelpService;
use App\Services\Mcp\McpRequestContext;

/**
 * One question of an exam, with its options, the topic it tests and - where the teacher allows
 * it - the correct answer and the explanation the teacher wrote.
 *
 * The only Exam & Assessment tool a student may call. Every other tool in the module reads
 * cohort figures and stays staff-only; this one reads a single question and refuses anything
 * the student could not already open (see ExamQuestionHelpService).
 */
class ExamAssessmentQuestionHelpTool extends AbstractMcpTool
{
    public function __construct(private readonly ExamQuestionHelpService $service)
    {
    }

    public function name(): string
    {
        return 'exam_assessment.question_help';
    }

    public function description(): string
    {
        return 'One question of an online exam the caller is attempting: its text, options and the topic it '
            . 'belongs to, plus - when the teacher allows students to see answers - the correct answer and the '
            . 'teacher\'s explanation. Name the question by id, by its number on the exam screen, or by its words.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'paper_id' => ['type' => 'integer', 'minimum' => 1],
                'question_id' => ['type' => 'integer', 'minimum' => 1],
                'question_number' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
                'question_text' => ['type' => 'string', 'maxLength' => 1000],
            ],
            'required' => ['paper_id'],
            'additionalProperties' => false,
        ];
    }

    protected function allowedRoles(): array
    {
        return ['admin', 'staff', 'student'];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'exam_assessment.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->help($context, $arguments);
    }
}
