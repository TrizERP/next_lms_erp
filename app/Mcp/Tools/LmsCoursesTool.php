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
 * chapters and topics are in them. A subject can sit on the timetable with nothing behind it,
 * and answering "what courses do we run" from the subject list would report content the
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
            . 'the class each is published for, and the chapters and topics inside them. Answers "which '
            . 'courses are available", "how many courses in Standard 6", "what chapters are in Science", '
            . 'and "what topics are in this chapter". Includes centrally published content the '
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
                    'description' => 'Exact or partial standard name, e.g. "6" or "CBSE-6".',
                ],
                'subject_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Restrict to one subject.'],
                'subject_name' => [
                    'type' => 'string',
                    'description' => 'Subject name filter, e.g. "Science" or "Mathematics".',
                ],
                'chapter_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Restrict to one chapter.'],
                'chapter_name' => [
                    'type' => 'string',
                    'description' => 'Chapter title or partial name, e.g. "The Wonderful World of Science".',
                ],
                'chapter_number' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => '1-based chapter number within the subject/course.',
                ],
                'mode' => [
                    'type' => 'string',
                    'enum' => ['courses', 'topics'],
                    'default' => 'courses',
                    'description' => 'Whether to query courses/chapters or topics within a chapter.',
                ],
                'query_type' => [
                    'type' => 'string',
                    'enum' => ['count', 'list'],
                    'description' => 'Whether the user asked for a count or a full list.',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Content category, e.g. "My Course" or "SEL". Omit for all.',
                ],
                'query' => ['type' => 'string', 'description' => 'Match part of a course or topic name.'],
                'include_chapters' => [
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Set false for a shorter answer when only the course list is needed.',
                ],
                'include_topics' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Set true to retrieve topics for the resolved chapter.',
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
                'validate_hierarchy' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'When true, validates the complete 5-tier content hierarchy (Standard -> Subject -> Chapter -> Topic -> Intelligence) and returns health metrics and detected gaps.',
                ],
                'force' => [
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Force revalidation even if content fingerprint is unchanged.',
                ],
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

        $mode = $arguments['mode'] ?? 'courses';
        $wantsTopics = $mode === 'topics'
            || ! empty($arguments['include_topics'])
            || ! empty($arguments['chapter_id']);

        if ($wantsTopics) {
            return $this->service->topics($context, $arguments);
        }

        return $this->service->catalogue($context, $arguments);
    }
}
