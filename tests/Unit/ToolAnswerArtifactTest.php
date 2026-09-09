<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\ToolAnswerComposer;
use App\Services\Mcp\McpRequestContext;
use PHPUnit\Framework\TestCase;

/**
 * How a turn that saved a document reports itself.
 *
 * The report generator is the first tool in this estate whose result *is* an artifact
 * rather than a list of rows, and the list-based composer had no idea what to do with
 * one: the only list in its payload is `columns`, so a successfully saved report was
 * headlined "No columns matched" — the turn whose entire point is the document it
 * created, described to the user as having found nothing.
 *
 * These tests pin the three things that has to get right: an artifact is named, a
 * refusal is still a refusal, and a list of column names never poses as an answer.
 */
class ToolAnswerArtifactTest extends TestCase
{
    private function context(array $stepResults): StageContext
    {
        $scope = new McpRequestContext(
            userId: 1,
            role: 'admin',
            selectedInstituteId: 1,
            allowedInstituteIds: [1],
            userProfileId: null,
            clientId: null,
            academicYear: 2022,
            termId: null,
            isAdmin: true,
            isStudent: false
        );

        $context = new StageContext(
            question: 'generate a report of pending admission enquiries',
            scope: $scope,
            module: new ModuleCapability(key: 'admissions', label: 'Admissions')
        );

        $context->set('mcp_step_results', $stepResults);

        return $context;
    }

    private function composer(): ToolAnswerComposer
    {
        return new ToolAnswerComposer(new AnswerComposer());
    }

    /** The payload `ai.templates.generate` returns on success. */
    private function saved(int $rows = 10): array
    {
        return [
            'success' => true,
            'message' => 'Report saved.',
            'data' => [
                'module' => 'admissions',
                'template_id' => 118,
                'title' => 'Admissions report — 10 rows, 7 Sep 2026',
                'row_count' => $rows,
                'columns' => ['student_name', 'status', 'mobile'],
                'source_tool' => 'admissions.listEnquiries',
                'template_link' => '/ai-reports/118',
            ],
        ];
    }

    public function test_a_saved_report_is_named_rather_than_tabulated(): void
    {
        $context = $this->context([$this->saved()]);

        $this->assertTrue($this->composer()->hasResults($context));

        $this->composer()->compose($context);

        $this->assertSame('Report saved — 10 rows.', $context->headline());
        $this->assertStringNotContainsStringIgnoringCase('no columns', (string) $context->headline());
    }

    public function test_the_link_is_offered_as_a_link_and_not_only_as_prose(): void
    {
        $context = $this->context([$this->saved()]);
        $this->composer()->compose($context);

        // The panel renders `links`, so a report reachable only from inside a sentence
        // is a report the reader has to copy a path out of.
        $this->assertSame('/ai-reports/118', $context->links()['report_link'] ?? null);
        $this->assertSame(118, $context->links()['report_id'] ?? null);

        $body = json_encode($context->sections(), JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('/ai-reports/118', $body);
        $this->assertStringContainsString('admissions.listEnquiries', $body);
    }

    public function test_a_refused_generation_is_not_treated_as_a_saved_report(): void
    {
        // "No admissions records matched" comes back as a refusal carrying no id. It
        // must fall through to the paths that report an empty result honestly, rather
        // than being announced as a document somebody can open.
        $context = $this->context([[
            'success' => false,
            'message' => 'No admissions records matched, so no report was created.',
            'data' => null,
            'error' => ['code' => 'no_rows', 'details' => []],
        ]]);

        $this->composer()->compose($context);

        $this->assertNull($context->headline());
        $this->assertSame([], $context->links());
    }

    public function test_a_list_of_column_names_never_becomes_the_answer(): void
    {
        // The same payload with the artifact keys removed: what is left is a list of
        // strings, and it must not be mistaken for a row list of length three.
        $context = $this->context([[
            'success' => true,
            'message' => 'Shape only.',
            'data' => ['columns' => ['student_name', 'status', 'mobile']],
        ]]);

        $this->assertFalse($this->composer()->hasResults($context));
    }

    public function test_an_empty_row_list_is_still_an_answer(): void
    {
        // The behaviour the scalar-list filter must not break: a final step that found
        // nothing has to stay visible, or an earlier step becomes "the answer".
        $context = $this->context([[
            'success' => true,
            'message' => 'None found.',
            'data' => ['enquiries' => []],
        ]]);

        $this->assertTrue($this->composer()->hasResults($context));

        $this->composer()->compose($context);

        $this->assertSame('No enquiries matched.', $context->headline());
    }

    public function test_real_rows_are_still_tabulated_as_before(): void
    {
        $context = $this->context([[
            'success' => true,
            'message' => 'Found.',
            'data' => [
                'count' => 2,
                'enquiries' => [
                    ['student_name' => 'Riya Mayur Patel', 'status' => 'Enquiry'],
                    ['student_name' => 'Abhi D Raval', 'status' => 'Confirmed'],
                ],
            ],
        ]]);

        $this->composer()->compose($context);

        $this->assertSame('2 enquiries.', $context->headline());
        $this->assertStringContainsString('Riya Mayur Patel', json_encode($context->sections()));
    }
}
