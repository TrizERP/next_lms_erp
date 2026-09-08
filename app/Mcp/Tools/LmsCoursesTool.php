<?php

namespace App\Mcp\Tools;

use App\Mcp\AbstractMcpTool;
use App\Services\Mcp\LmsCourseCatalogService;
use App\Services\Mcp\McpRequestContext;

/**
 * What is actually teachable, as opposed to what is merely named.
 *
 * `academics.subjects` turns a subject named in a question into an id. This is the other
 * half: which of those subjects carry published content, for which class, and what
 * chapters are in them. A subject can sit on the timetable with nothing behind it, and
 * answering "what courses do we run" from the subject list would report content the
 * school does not have.
 */
class LmsCoursesTool extends AbstractMcpTool
{
    public function __construct(private readonly LmsCourseCatalogService $service)
    {
    }

    public function name(): string
    {
        return 'lms.courses';
    }

    public function description(): string
    {
        return 'The published LMS courses for this institute — the subjects that carry content, '
            . 'the class each is published for, and the chapters inside them. Answers "which '
            . 'courses are available", "what is in the Science course for Standard 9" and "how '
            . 'many chapters does this subject have". Includes centrally published content the '
            . 'institute inherits. For resolving a subject name to an id, use academics.subjects.';
    }

    protected function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'standard_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Restrict to one class.'],
                'standard_name' => [
                    'type' => 'string',
                    'description' => 'Exact standard name, e.g. "9". Use instead of standard_id.',
                ],
                'subject_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Restrict to one subject.'],
                'category' => [
                    'type' => 'string',
                    'description' => 'Content category, e.g. "My Course" or "SEL". Omit for all.',
                ],
                'query' => ['type' => 'string', 'description' => 'Match part of a course display name.'],
                'include_chapters' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Set false for a shorter answer when only the course list is needed.',
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
            ],
            'additionalProperties' => false,
        ];
    }

    protected function toolAnnotations(): array
    {
        return ['risk' => 'read', 'required_permission' => 'lms.read'];
    }

    public function execute(array $arguments, McpRequestContext $context): array
    {
        $this->authorize($context);

        return $this->service->catalogue($context, $arguments);
    }
}
