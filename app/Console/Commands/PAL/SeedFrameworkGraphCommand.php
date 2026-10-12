<?php

namespace App\Console\Commands\PAL;

use App\Services\Neo4jService;
use Illuminate\Console\Command;

/**
 * Loads `database/neo4j/frameworks/*.cypher` into Neo4j — real,
 * honestly-sourced `(:Framework)-[:MAPS_TO]->(target)` mappings.
 *
 * `target` is NOT always `:Concept` — each file picks whichever real,
 * already-live label its framework actually corresponds to (ngss.cypher and
 * gardner.cypher target `:Concept`; casel.cypher targets
 * `:CoScholasticArea`; ncdg.cypher targets `:Occupation`/`:Exam`/`:Degree` in
 * the CAI-CORE career graph). See each file's own docblock for why, and
 * App\Services\PAL\Framework\FrameworkCatalogService::graphMappings() for the
 * framework-agnostic read that works across all of them.
 *
 * Same loading convention as `cai:seed-graph`
 * (App\Console\Commands\CareerIntelligence\SeedCareerGraphCommand), for the
 * same reason: each file MERGEs a `:Framework` node, then references that
 * SAME node again by its bare Cypher variable while walking its practices -
 * Bolt has no concept of a variable surviving across separate `run()` calls,
 * so each file must be sent as ONE statement, not split on `;`.
 *
 * Deliberately separate from `neo4j:load` / the live TableGraphProjection
 * sync pipeline (App\Services\Graph\GraphSchema's whitelist knows nothing
 * about `:Framework`, and does not need to - this is a one-off reference
 * seed, not an entity synced from a MariaDB table). Every node a file's
 * MAPS_TO edges land on is MATCHed, never MERGEd - they are real,
 * already-live nodes this command must find, not create.
 */
class SeedFrameworkGraphCommand extends Command
{
    protected $signature = 'pal:seed-framework-graph';

    protected $description = 'Load real, sourced Framework->target mappings (NGSS, Gardner, CASEL, NCDG) into the K12 Neo4j graph';

    public function handle(Neo4jService $neo4j): int
    {
        $base = database_path('neo4j/frameworks');
        $files = glob($base . '/*.cypher') ?: [];
        sort($files);

        if ($files === []) {
            $this->warn("No .cypher files found under {$base}");

            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $this->line("Running {$file}");
            $neo4j->run($this->stripComments($file));
        }

        $this->info('Seeded ' . count($files) . ' framework file(s).');

        return self::SUCCESS;
    }

    /**
     * Strip `//` comment lines and every `;` — see this class's own docblock
     * for why the whole file must become one continuous statement.
     */
    private function stripComments(string $path): string
    {
        $contents = file_get_contents($path);
        $lines = array_map(
            fn (string $line) => preg_match('/^\s*\/\//', $line) ? '' : $line,
            explode("\n", $contents)
        );

        return trim(str_replace(';', '', implode("\n", $lines)));
    }
}
