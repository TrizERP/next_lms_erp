<?php

namespace Tests\Feature\AI;

use App\Domain\AI\Templates\ReportDataSourceCatalog;
use App\Services\Mcp\AiReportGenerator;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A published report template is only usable if its data source is a real, read-only tool.
 * `k12.teach_learn.report` named `teach_learn.courses`, which did not exist, so Teach/Learn
 * silently dropped out of the modules a report can be generated for.
 *
 * READ-ONLY: reads template rows and tool schemas; nothing is generated or saved.
 */
class ReportTemplateDataSourceTest extends TestCase
{
    public function test_every_published_report_template_binds_to_a_registered_read_only_tool(): void
    {
        $catalog = app(ReportDataSourceCatalog::class);
        $broken = [];

        foreach (DB::table('ai_templates')->where('kind', 'report')->where('status', 'published')->get(['template_key', 'data_source']) as $row) {
            if (! $catalog->isBindable((string) $row->data_source)) {
                $broken[] = $row->template_key . ' -> ' . $row->data_source;
            }
        }

        $this->assertSame([], $broken, 'Report templates bound to a tool that does not exist or writes.');
    }

    public function test_teach_learn_is_a_module_a_report_can_be_generated_for(): void
    {
        $context = new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);

        $this->assertContains('teach_learn', app(AiReportGenerator::class)->supportedFor($context));
    }

    public function test_the_teach_learn_source_reads_the_same_courses_as_the_lms_source(): void
    {
        $context = new McpRequestContext(1, 'admin', 1, [1], null, null, 2022, null, true, false);
        $registry = app(\App\Mcp\ToolRegistry::class);

        $viaLms = $registry->tool('lms.courses')->execute(['standard_name' => '8', 'include_chapters' => false], $context);
        $viaTeachLearn = $registry->tool('teach_learn.courses')->execute(['standard_name' => '8', 'include_chapters' => false], $context);

        $this->assertSame($viaLms, $viaTeachLearn);
        $this->assertSame('read', $registry->tool('teach_learn.courses')->definition()['annotations']['risk']);
    }
}
