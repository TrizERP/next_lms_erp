<?php

namespace Tests\Unit;

use App\Domain\AI\Agents\AgentRegistry;
use App\Domain\AI\Workspace\AiContext;
use App\Domain\AI\Workspace\CapabilityResolver;
use App\Domain\AI\Workspace\PageSnapshot;
use App\Domain\AI\Workspace\PageTypeResolver;
use App\Domain\AI\Workspace\RouteMatcher;
use App\Services\Mcp\McpRequestContext;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Page context: what the assistant knows about where the user is standing.
 *
 * Framework-free, like GovernanceKernelTest, so these run without booting the
 * application — which still cannot boot, for the pre-existing reason recorded there.
 *
 * The subject is the part of the brief that is easy to get quietly wrong: a page
 * snapshot that is unbounded ends up pushing a whole grid into a prompt, and a
 * suggestion engine that ignores the page shows the same four prompts everywhere. Both
 * failures are invisible until someone reads the payload, so they are asserted here.
 */
class PageContextTest extends TestCase
{
    // ---- The snapshot is bounded -------------------------------------------

    public function test_records_are_capped_but_the_true_total_is_kept(): void
    {
        $rows = [];

        for ($index = 1; $index <= 300; $index++) {
            $rows[] = ['id' => $index, 'label' => "Student {$index}"];
        }

        $snapshot = PageSnapshot::fromArray(['records' => $rows, 'record_count' => 300]);

        $this->assertCount(
            25,
            $snapshot->records,
            'A page must not be able to push its whole grid into a prompt.'
        );
        $this->assertSame(
            300,
            $snapshot->recordCount,
            'The window is capped, but the assistant still has to know the set is larger.'
        );
    }

    public function test_the_total_defaults_to_what_was_sent_when_the_page_does_not_report_one(): void
    {
        $snapshot = PageSnapshot::fromArray([
            'records' => [['id' => 1], ['id' => 2], ['id' => 3]],
        ]);

        $this->assertSame(3, $snapshot->recordCount);
    }

    public function test_non_scalar_values_are_dropped_rather_than_serialised(): void
    {
        $snapshot = PageSnapshot::fromArray([
            'records' => [[
                'id' => 7,
                'label' => 'Riya Sharma',
                'standard' => '8',
                'nested' => ['this' => 'should not survive'],
            ]],
        ]);

        $this->assertSame(['standard' => '8'], $snapshot->records[0]['attributes']);
    }

    public function test_filters_accept_both_the_terse_and_the_labelled_shape(): void
    {
        $terse = PageSnapshot::fromArray(['filters' => ['standard' => '8']]);
        $labelled = PageSnapshot::fromArray([
            'filters' => [['key' => 'standard', 'label' => 'Class', 'value' => '8']],
        ]);

        $this->assertSame('Standard: 8', $terse->describeFilters());
        $this->assertSame('Class: 8', $labelled->describeFilters());
    }

    public function test_an_unrecognised_page_type_is_dropped_not_passed_through(): void
    {
        // Suggestions branch on the type, so a typo must degrade to "no type" rather
        // than silently disabling every type-specific prompt.
        $this->assertNull(PageSnapshot::fromArray(['page_type' => 'grid'])->type);
        $this->assertSame('list', PageSnapshot::fromArray(['page_type' => 'LIST'])->type);
    }

    public function test_an_empty_snapshot_reports_itself_empty(): void
    {
        $this->assertTrue(PageSnapshot::fromArray([])->isEmpty());
        $this->assertFalse(PageSnapshot::fromArray(['page_title' => 'Fees'])->isEmpty());
    }

    // ---- Suggestions follow the page ---------------------------------------

    public function test_a_record_page_offers_prompts_about_that_record(): void
    {
        $suggestions = $this->derive($this->context(
            moduleKey: 'student',
            entityKey: 'student',
            entityId: 42,
            entityLabel: 'Riya Sharma',
        ));

        $prompts = array_column($suggestions, 'prompt');

        $this->assertStringContainsString('Riya Sharma', $prompts[0]);
        $this->assertContains('derived:summarise-record', array_column($suggestions, 'key'));
        $this->assertContains('derived:next-step', array_column($suggestions, 'key'));
    }

    public function test_a_filtered_list_offers_prompts_about_the_filtered_set(): void
    {
        $suggestions = $this->derive($this->context(
            moduleKey: 'students',
            page: PageSnapshot::fromArray([
                'page_type' => 'list',
                'filters' => ['standard' => '8'],
                'records' => [['id' => 1], ['id' => 2]],
                'record_count' => 42,
            ]),
        ));

        $keys = array_column($suggestions, 'key');
        $prompts = array_column($suggestions, 'prompt');

        $this->assertContains('derived:explain-filters', $keys);
        $this->assertContains('derived:summarise-list', $keys);
        $this->assertTrue(
            (bool) array_filter($prompts, fn ($prompt) => str_contains($prompt, '42')),
            'The prompt should name the real size of the result set.'
        );
        $this->assertTrue(
            (bool) array_filter($prompts, fn ($prompt) => str_contains($prompt, 'students')),
            'It should call them students, not "records".'
        );
    }

    public function test_a_selection_takes_precedence_over_the_list_it_came_from(): void
    {
        $suggestions = $this->derive($this->context(
            moduleKey: 'students',
            selectedRecords: [['entity' => 'student', 'id' => 1], ['entity' => 'student', 'id' => 2]],
            page: PageSnapshot::fromArray(['page_type' => 'list', 'record_count' => 42]),
        ));

        $keys = array_column($suggestions, 'key');

        $this->assertContains('derived:summarise-selection', $keys);
        $this->assertContains('derived:compare-selection', $keys);
        $this->assertLessThan(
            array_search('derived:summarise-list', $keys, true),
            array_search('derived:summarise-selection', $keys, true),
            'What the user ticked is a stronger signal than what the page happens to list.'
        );
    }

    public function test_a_metric_on_screen_becomes_a_question_about_that_number(): void
    {
        $suggestions = $this->derive($this->context(
            moduleKey: 'dashboard',
            page: PageSnapshot::fromArray([
                'page_type' => 'dashboard',
                'metrics' => [
                    ['key' => 'collection_rate', 'label' => 'Collection rate', 'value' => '64', 'unit' => '%'],
                    ['key' => 'attendance', 'label' => 'Attendance', 'value' => '91', 'unit' => '%'],
                    ['key' => 'admissions', 'label' => 'Admissions', 'value' => '12'],
                ],
            ]),
        ));

        $metricPrompts = array_filter(
            $suggestions,
            fn ($suggestion) => str_starts_with($suggestion['key'], 'derived:metric:')
        );

        $this->assertCount(
            2,
            $metricPrompts,
            'A dashboard with nine tiles must not crowd out everything else with nine prompts.'
        );
        $this->assertStringContainsString('64 %', array_values($metricPrompts)[0]['prompt']);
    }

    public function test_a_catalogue_offers_questions_built_from_its_own_options(): void
    {
        // The Course Catalog case: nothing selected, nothing filtered, but the page
        // knows which grades and categories exist. Without facets this page has no
        // page-specific material at all and falls through to the generic floor.
        $suggestions = $this->derive($this->context(
            moduleKey: 'course-master',
            page: PageSnapshot::fromArray([
                'page_type' => 'list',
                'record_count' => 47,
                'facets' => [
                    ['key' => 'grade', 'label' => 'Grade', 'values' => ['5', '6', '7'],
                        'question' => 'What courses are available for Grade {value}?'],
                    ['key' => 'category', 'label' => 'Category', 'values' => ['STEM Resources'],
                        'question' => 'Show me the courses available under {value}.'],
                ],
            ]),
        ));

        $prompts = array_column($suggestions, 'prompt');

        $this->assertContains('What courses are available for Grade 5?', $prompts);
        $this->assertContains('Show me the courses available under STEM Resources.', $prompts);
    }

    public function test_facets_are_taken_round_robin_so_every_dimension_appears(): void
    {
        // Reading one facet to exhaustion would push the second off the six-item cap,
        // and the user would never learn they can ask about a category.
        $suggestions = $this->derive($this->context(
            moduleKey: 'course-master',
            page: PageSnapshot::fromArray([
                'facets' => [
                    ['key' => 'grade', 'label' => 'Grade', 'values' => ['1', '2', '3', '4', '5', '6']],
                    ['key' => 'category', 'label' => 'Category', 'values' => ['STEM Resources']],
                ],
            ]),
        ));

        $keys = array_column($suggestions, 'key');
        $gradeFirst = array_search('derived:facet:grade:1', $keys, true);
        $categoryFirst = array_search('derived:facet:category:STEM Resources', $keys, true);

        $this->assertNotFalse($categoryFirst, 'The second facet must still be reached.');
        $this->assertSame(
            $gradeFirst + 1,
            $categoryFirst,
            'The second facet should follow the first value of the first, not its sixth.'
        );
    }

    public function test_a_filter_left_on_all_is_not_treated_as_a_filter(): void
    {
        // Otherwise an unfiltered catalogue is described as "filtered by Grade: all",
        // which makes the assistant sound like it is looking at a narrowed view.
        $snapshot = PageSnapshot::fromArray([
            'filters' => ['grade' => 'all', 'category' => 'Any', 'status' => 'active'],
        ]);

        $this->assertCount(1, $snapshot->filters);
        $this->assertSame('Status: active', $snapshot->describeFilters());
    }

    public function test_a_facet_value_of_all_is_dropped(): void
    {
        $snapshot = PageSnapshot::fromArray([
            'facets' => [['key' => 'grade', 'values' => ['all', '5', '5', '6']]],
        ]);

        // "all" removed, duplicate collapsed.
        $this->assertSame(['5', '6'], $snapshot->facets[0]['values']);
    }

    public function test_an_unmapped_page_still_gets_something_useful(): void
    {
        // The case that matters most: forty-odd route folders nobody has configured.
        $suggestions = $this->derive($this->context());

        $this->assertNotEmpty($suggestions);
        $this->assertContains('derived:page-help', array_column($suggestions, 'key'));
    }

    public function test_relationship_prompts_appear_only_where_the_capability_exists(): void
    {
        $without = $this->derive($this->context(
            moduleKey: 'student',
            entityKey: 'student',
            entityId: 1,
            capabilities: ['conversational' => true, 'ontology' => false],
        ));

        $with = $this->derive($this->context(
            moduleKey: 'student',
            entityKey: 'student',
            entityId: 1,
            capabilities: ['conversational' => true, 'ontology' => true],
        ));

        $this->assertNotContains('derived:related-records', array_column($without, 'key'));
        $this->assertContains('derived:related-records', array_column($with, 'key'));
    }

    // ---- Route matching -----------------------------------------------------

    public function test_a_specific_pattern_beats_the_module_it_sits_inside(): void
    {
        $matcher = new RouteMatcher();

        $result = $matcher->best(['/fees/**', '/fees/collect/:studentId'], '/fees/collect/42');

        $this->assertSame('/fees/collect/:studentId', $result['pattern']);
        $this->assertSame(['studentId' => '42'], $result['params']);
    }

    public function test_query_strings_and_trailing_slashes_do_not_change_the_module(): void
    {
        $matcher = new RouteMatcher();

        foreach (['/students', '/students/', '/students?standard=8'] as $route) {
            $this->assertTrue(
                $matcher->match('/students', $route)['matched'],
                "{$route} should resolve to the same module."
            );
        }
    }

    // ---- Helpers ------------------------------------------------------------

    /**
     * Invoke the derivation directly. It reads only the context object — no database,
     * no container — which is what makes it testable at this level.
     */
    /**
     * Built from the real config/ai.php, so these assertions cover the rules that
     * actually ship rather than a duplicate of them kept in the test.
     */
    private function pageTypes(): PageTypeResolver
    {
        static $rules = null;

        if ($rules === null) {
            $config = require __DIR__ . "/../../config/ai.php";
            $rules = $config["page_types"] ?? [];
        }

        return new PageTypeResolver(new RouteMatcher(), $rules);
    }

    private function derive(AiContext $context): array
    {
        // No setAccessible(): private methods are reflectively invocable as of PHP 8.1.
        $method = new ReflectionMethod(CapabilityResolver::class, 'derivedConversational');

        return $method->invoke(new CapabilityResolver(new AgentRegistry(), $this->pageTypes()), $context);
    }

    private function context(
        ?string $moduleKey = null,
        ?string $entityKey = null,
        int|string|null $entityId = null,
        ?string $entityLabel = null,
        array $selectedRecords = [],
        ?PageSnapshot $page = null,
        array $capabilities = ['conversational' => true],
    ): AiContext {
        return new AiContext(
            scope: new McpRequestContext(
                userId: 1,
                role: 'teacher',
                selectedInstituteId: 1,
                allowedInstituteIds: [1],
                userProfileId: 1,
                clientId: 1,
                academicYear: 2026,
                termId: 1,
                isAdmin: false,
                isStudent: false,
            ),
            route: '/whatever',
            moduleKey: $moduleKey,
            moduleLabel: $moduleKey ? ucfirst($moduleKey) : null,
            entityKey: $entityKey,
            entityId: $entityId,
            entityLabel: $entityLabel,
            selectedRecords: $selectedRecords,
            capabilities: $capabilities,
            page: $page ?? new PageSnapshot(),
        );
    }
    // ---- Page type drives capability ---------------------------------------

    public function test_page_type_is_inferred_from_the_route(): void
    {
        $resolver = $this->pageTypes();

        $this->assertSame("dashboard", $resolver->resolve("/lms/dashboard"));
        $this->assertSame("report", $resolver->resolve("/teacher_daily_report"));
        $this->assertSame("form", $resolver->resolve("/students/requests/new"));
        $this->assertSame("settings", $resolver->resolve("/settings/rights"));
        // No rule, no entity: the configured default.
        $this->assertSame("list", $resolver->resolve("/library"));
    }

    public function test_a_page_that_declares_its_type_is_believed(): void
    {
        $resolver = $this->pageTypes();

        // A page knows things the URL cannot — /library is a list until a drawer opens.
        $this->assertSame("form", $resolver->resolve("/library", "form"));
        // ...but only for a type that exists.
        $this->assertSame("list", $resolver->resolve("/library", "nonsense"));
    }

    public function test_a_resolved_record_makes_it_a_detail_page(): void
    {
        $resolver = $this->pageTypes();

        $this->assertSame("detail", $resolver->resolve("/fees/collect/42", null, true));
        $this->assertSame("list", $resolver->resolve("/fees/collect/42", null, false));
    }

    public function test_capabilities_follow_the_page_type(): void
    {
        $resolver = $this->pageTypes();

        // A form offers drafting help, not analysis; a dashboard the reverse.
        $this->assertTrue($resolver->capabilitiesFor("form")["generative"] ?? false);
        $this->assertArrayNotHasKey("agent", $resolver->capabilitiesFor("form"));
        $this->assertTrue($resolver->capabilitiesFor("dashboard")["agent"] ?? false);
    }

    public function test_every_page_type_with_an_analysis_action_names_a_template(): void
    {
        $resolver = $this->pageTypes();

        foreach (["dashboard", "report", "list", "detail"] as $type) {
            $action = $resolver->analysisFor($type);
            $this->assertIsArray($action, "{$type} should offer analysis.");
            $this->assertNotEmpty($action["template"]);
        }

        // A form is for writing, not analysing.
        $this->assertNull($resolver->analysisFor("form"));
    }
}