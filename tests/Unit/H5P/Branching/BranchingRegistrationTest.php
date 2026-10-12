<?php

namespace Tests\Unit\H5P\Branching;

use App\Services\lms\Content\H5PContentAdapter;
use PHPUnit\Framework\TestCase;

/**
 * The places a native H5P type has to be written down, and that they agree.
 *
 * Adding a type touches six places that nothing connects at compile time: the
 * migration's table names, the PAL registry (which is what makes the hub card
 * appear), the library manifest (which is what an exported package declares),
 * the routes, the content adapter (which is what puts a published item into the
 * chapter's content list) and, in the other repo, the route map. A typo in any
 * one is a card that opens a 404 or an item that never shows. This reads the
 * actual files and checks the names line up.
 *
 * No database and no framework: config files are plain arrays, the routes file
 * is read as text.
 */
class BranchingRegistrationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 4);
    }

    /** @return array<string,mixed> */
    private function implementation(): array
    {
        $config = require $this->root . '/config/pal_h5p.php';

        return $config['h5p_types']['branching_scenario']['implementation'];
    }

    public function test_the_registry_row_is_native_and_names_the_tables_the_migration_creates(): void
    {
        $implementation = $this->implementation();
        $migration = (string) file_get_contents($this->root . '/database/migrations/2026_10_10_120000_create_h5p_branching_scenario_tables.php');

        $this->assertSame('native', $implementation['status']);
        $this->assertStringContainsString("Schema::create('" . $implementation['source_table'] . "'", $migration);
        $this->assertStringContainsString("Schema::create('" . $implementation['child_table'] . "'", $migration);
        $this->assertStringContainsString("'" . $implementation['child_foreign_key'] . "'", $migration);
        $this->assertSame('step', $implementation['child_label']);
        $this->assertSame('Interactive Content', $implementation['module_category']);
    }

    public function test_every_column_the_hub_reads_exists_in_the_migration(): void
    {
        $migration = (string) file_get_contents($this->root . '/database/migrations/2026_10_10_120000_create_h5p_branching_scenario_tables.php');

        foreach ($this->implementation()['columns'] as $role => $column) {
            // `soft_delete` and the timestamps come from helpers rather than a literal column name.
            if (in_array($column, ['deleted_at', 'created_at'], true)) {
                $this->assertStringContainsString($column === 'deleted_at' ? 'softDeletes()' : 'timestamps()', $migration, $role);

                continue;
            }
            $this->assertMatchesRegularExpression('/\$table->\w+\(\'' . preg_quote($column, '/') . '\'/', $migration, "the hub reads `$column` as $role");
        }
    }

    public function test_the_library_manifest_matches_what_was_read_from_upstream(): void
    {
        $libraries = require $this->root . '/config/h5p_libraries.php';
        $library = $libraries['libraries']['branching_scenario'];

        $this->assertSame('H5P.BranchingScenario', $library['machine_name']);
        $this->assertSame([1, 11], [$library['major_version'], $library['minor_version']]);
        $this->assertSame(['iframe'], $library['embed_types']);

        $names = array_column($library['dependencies'], 'machineName');
        // Exactly the libraries the builder writes: nothing it never emits, nothing it emits that is missing.
        $this->assertEqualsCanonicalizing(['H5P.BranchingQuestion', 'H5P.AdvancedText', 'H5P.Image', 'FontAwesome'], $names);
    }

    public function test_the_routes_are_declared_before_the_resource_that_would_swallow_them(): void
    {
        $routes = (string) file_get_contents($this->root . '/routes/lms.php');

        $this->assertStringContainsString("'h5p_branching_scenario' => [H5PBranchingScenarioController::class, 'media' => true]", $routes);
        $sources = strpos($routes, "Route::get('h5p_branching_scenario/sources'");
        $generate = strpos($routes, "Route::post('h5p_branching_scenario/generate'");
        $loop = strpos($routes, "'h5p_branching_scenario' => [H5PBranchingScenarioController::class");

        $this->assertNotFalse($sources);
        $this->assertNotFalse($generate);
        $this->assertLessThan($loop, $sources, '`sources` would be read as an id by the resource `show` route');
        $this->assertLessThan($loop, $generate);
    }

    public function test_generation_is_billable_so_it_is_rate_limited_and_the_middleware_exists(): void
    {
        $routes = (string) file_get_contents($this->root . '/routes/lms.php');
        $kernel = (string) file_get_contents($this->root . '/app/Http/Kernel.php');

        $this->assertMatchesRegularExpression("/h5p_branching_scenario\/generate'.*?->middleware\('throttle\.contentgen:\d+,\d+'\)/s", $routes);
        $this->assertStringContainsString("'throttle.contentgen' =>", $kernel);
    }

    public function test_a_published_scenario_surfaces_in_the_chapter_content_list_and_a_draft_does_not(): void
    {
        // SOURCES is private on purpose; the test reads it the way the adapter itself does.
        $sources = (new \ReflectionClassConstant(H5PContentAdapter::class, 'SOURCES'))->getValue();
        $source = $sources['branching_scenario'];

        $this->assertSame($this->implementation()['source_table'], $source['table']);
        $this->assertTrue($source['published_only'], 'a draft must not reach students through the content list');
        $this->assertSame('/h5p/h5p_branching_scenario', $source['route']);
        $this->assertSame('branching_scenario', $source['h5p_type']);
    }

    public function test_the_registry_route_name_resolves_to_the_route_the_frontend_maps(): void
    {
        $this->assertSame('h5p_branching_scenario.index', $this->implementation()['route']);

        // The frontend's H5P_ROUTE_MAP key for this type, in the sibling repository when it is checked out beside this one.
        $map = dirname($this->root) . '/lms_k12/app/h5p/data/h5p.ts';
        if (! is_file($map)) {
            $this->markTestSkipped('The frontend repository is not beside this one.');
        }
        $this->assertStringContainsString("'h5p_branching_scenario.index': '/h5p/h5p_branching_scenario'", (string) file_get_contents($map));
    }
}
