<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Lifecycle\Support\ToolAnswerComposer;
use App\Domain\AI\Modules\ModuleReadTools;
use App\Services\Mcp\LmsCourseSummaryService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The Teach/Learn course questions, checked against the database rather than against fixtures.
 *
 * READ-ONLY: every call here reads. Institute 1 / academic year 2022 is the estate's shared library.
 */
class LmsCourseQuestionsTest extends TestCase
{
    private function context(): McpRequestContext
    {
        return new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);
    }

    public function test_the_grade_8_course_count_matches_the_database(): void
    {
        $expected = DB::table('sub_std_map as m')
            ->join('standard as s', function ($join) {
                $join->on('s.id', '=', 'm.standard_id')->on('s.sub_institute_id', '=', 'm.sub_institute_id');
            })
            ->whereIn('m.sub_institute_id', [1])
            ->where('m.allow_content', 'Yes')
            ->where('s.name', '8')
            ->distinct()
            ->count(DB::raw("concat(m.subject_id, ':', m.standard_id, ':', ifnull(m.subject_category, ''))"));

        $payload = app(\App\Services\Mcp\LmsCourseCatalogService::class)
            ->catalogue($this->context(), ['standard_name' => '8', 'include_chapters' => false]);

        $this->assertGreaterThan(0, $expected);
        $this->assertSame($expected, $payload['count']);
        $this->assertCount($payload['count'], $payload['courses']);
    }

    public function test_a_board_prefixed_class_name_resolves_from_a_bare_number(): void
    {
        // Institute 47 names its classes "CBSE-8"; the question only says "Grade 8".
        $context = new McpRequestContext(1, 'admin', 47, [47], null, null, null, null, true, false);

        $payload = app(\App\Services\Mcp\LmsCourseCatalogService::class)
            ->catalogue($context, ['standard_name' => '8', 'include_chapters' => false]);

        $this->assertArrayNotHasKey('unresolved_filters', $payload);
        $this->assertGreaterThan(0, $payload['count']);
    }

    public function test_one_course_summary_lists_every_chapter_with_its_plan_state(): void
    {
        $service = app(LmsCourseSummaryService::class);
        $payload = $service->summary($this->context(), ['standard_name' => '8', 'question' => 'chapters of Grade 8 Science']);

        $this->assertArrayHasKey('chapters', $payload);
        $this->assertSame($payload['count'], count($payload['chapters']));
        $this->assertStringContainsString('Science', $payload['headline']);

        foreach ($payload['chapters'] as $chapter) {
            $this->assertContains($chapter['lesson_plan'], ['written', 'none written']);
        }
    }

    public function test_a_class_wide_summary_is_one_line_per_course(): void
    {
        $payload = app(LmsCourseSummaryService::class)->summary($this->context(), ['standard_name' => '8']);

        $this->assertArrayHasKey('courses', $payload);
        $this->assertArrayNotHasKey('chapters', $payload);
        $this->assertGreaterThan(1, $payload['count']);
    }

    public function test_an_unknown_class_is_reported_not_guessed(): void
    {
        $payload = app(LmsCourseSummaryService::class)->summary($this->context(), ['standard_name' => '99']);

        $this->assertArrayHasKey('unresolved_filters', $payload);
        $this->assertStringContainsString('could not find', $payload['headline']);
    }

    public function test_the_lesson_plan_figures_match_the_lesson_plan_table(): void
    {
        $row = DB::table('lms_lesson_plan')
            ->select('sub_institute_id', 'syear', 'standard_id', 'subject_id')
            ->selectRaw('count(*) c')
            ->groupBy('sub_institute_id', 'syear', 'standard_id', 'subject_id')
            ->orderByDesc('c')
            ->first();

        $this->assertNotNull($row, 'the estate holds no lesson plans to check against');

        $context = new McpRequestContext(1, 'admin', (int) $row->sub_institute_id, [(int) $row->sub_institute_id], null, null, (int) $row->syear, null, true, false);
        $payload = app(LmsCourseSummaryService::class)->summary($context, [
            'standard_id' => (int) $row->standard_id,
            'subject_id' => (int) $row->subject_id,
        ]);

        if (! isset($payload['chapters'])) {
            $this->markTestSkipped('That lesson plan\'s course is not a published course, so it is not summarised.');
        }

        $this->assertLessThanOrEqual((int) $row->c, array_sum(array_column($payload['chapters'], 'topics_planned')));
    }

    /** @return array<string, array{0:string,1:bool}> */
    public static function showAllPhrases(): array
    {
        return [
            'show all' => ['Show all Grade 8 courses', true],
            'show all, bare' => ['show all', true],
            'list every' => ['List every subject', true],
            'the rest' => ['show me the rest', true],
            'count' => ['How many courses are there for Grade 8?', false],
            'a single one' => ['Show the details of the first course', false],
            'no verb' => ['all courses', false],
        ];
    }

    #[DataProvider('showAllPhrases')]
    public function test_show_all_is_recognised_only_when_it_asks_for_everything(string $question, bool $expected): void
    {
        $this->assertSame($expected, ToolAnswerComposer::asksForEverything($question));
    }

    public function test_chapter_and_lesson_plan_words_point_at_the_course_summary_tool(): void
    {
        $tools = new ModuleReadTools();
        $readable = $tools->select(config('ai.lifecycle.modules.teach_learn.mcp_tools'), 50);

        $this->assertSame(
            'lms.course_summary',
            $tools->hintedTool('teach_learn', $readable, 'Summarise the chapters and lesson plan for this course')
        );
        $this->assertNull($tools->hintedTool('teach_learn', $readable, 'How many courses are there?'));
    }

    public function test_the_summary_tool_is_read_only_and_bound_to_the_course_modules(): void
    {
        foreach (['course', 'course-master', 'teach_learn'] as $module) {
            $this->assertContains('lms.course_summary', config("ai.lifecycle.modules.$module.mcp_tools"), $module);
        }

        $definition = app(\App\Mcp\ToolRegistry::class)->tool('lms.course_summary')->definition();

        $this->assertTrue($definition['annotations']['read_only']);
        $this->assertSame('read', $definition['annotations']['risk']);
    }

    public function test_pluralised_course_nouns_singularise_correctly(): void
    {
        $this->assertSame('course', \App\Domain\AI\Conversation\ResultSet::singularise('courses'));
        $this->assertSame('class', \App\Domain\AI\Conversation\ResultSet::singularise('classes'));
        $this->assertSame('status', \App\Domain\AI\Conversation\ResultSet::singularise('statuses'));
    }
}
