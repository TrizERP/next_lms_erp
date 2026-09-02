<?php

namespace App\Console\Commands\CareerIntelligence;

use App\Services\Neo4jService;
use Illuminate\Console\Command;

/**
 * Loads the CAI-CORE graph (constraints + one .cypher file per seeded
 * occupation) into Neo4j. Deliberately separate from `neo4j:load` /
 * `neo4j:reset-graph` etc. — those belong to the unrelated K12 curriculum
 * projection pipeline (App\Services\Graph\*), which syncs a different set of
 * entities under the `uid` key convention. This command owns only
 * database/neo4j/cai/, keyed on occupation_id/code/exam_id/degree_id/
 * policy_id as constrained in constraints.cypher.
 *
 * Idempotent: every statement is MERGE, so re-running is safe.
 *
 * IMPORTANT — constraints vs. occupation files are run differently:
 *   - constraints.cypher: each `CREATE CONSTRAINT ...;` is independent DDL
 *     and Neo4j requires these run one at a time, so this file IS split on
 *     `;` and each statement sent separately.
 *   - occupations/*.cypher: these files declare a node with MERGE, then
 *     reference that SAME node again by its bare variable name (e.g.
 *     `MERGE (o:Occupation {...})` followed later by
 *     `MERGE (o)-[:REQUIRES_STREAM]->(st)`). Bolt has no concept of a
 *     variable persisting across separate `run()` calls — splitting this on
 *     `;` silently creates disconnected, label-less nodes instead of
 *     reconnecting to the ones already created (this shipped once, was
 *     caught in verification, and the resulting orphan nodes were deleted —
 *     see the CAI Phase 3 report). Each occupation file MUST be sent to
 *     Neo4j as ONE single statement so its variable bindings stay in scope
 *     for the whole file.
 */
class SeedCareerGraphCommand extends Command
{
    protected $signature = 'cai:seed-graph';

    protected $description = 'Load the Career Intelligence graph (constraints + occupation seeds) into Neo4j';

    public function handle(Neo4jService $neo4j): int
    {
        $base = database_path('neo4j/cai');

        $this->line('Running constraints.cypher');
        foreach ($this->splitStatements($base . '/constraints.cypher') as $statement) {
            $neo4j->run($statement);
        }

        $occupationFiles = glob($base . '/occupations/*.cypher') ?: [];
        sort($occupationFiles);

        foreach ($occupationFiles as $file) {
            $this->line("Running {$file}");
            $neo4j->run($this->stripComments($file));
        }

        $this->info('Seeded ' . count($occupationFiles) . ' occupation file(s).');

        return self::SUCCESS;
    }

    /**
     * Strip `//` comment lines and EVERY `;` (Bolt rejects a query containing
     * more than one statement — "Expected exactly one statement per query" —
     * so the semicolons the file uses to visually separate MERGE clauses must
     * go; Cypher clauses don't need them, only the file's own readability
     * does). What's left is one continuous multi-clause statement.
     */
    private function stripComments(string $path): string
    {
        $withoutComments = $this->withoutCommentLines($path);

        return trim(str_replace(';', '', $withoutComments));
    }

    /**
     * Split a DDL file into individual statements (one CREATE CONSTRAINT
     * per call — required for Neo4j, and safe here since constraint
     * statements never reference a variable from another statement).
     *
     * @return string[]
     */
    private function splitStatements(string $path): array
    {
        $withoutComments = $this->withoutCommentLines($path);

        return array_values(array_filter(array_map(
            'trim',
            explode(';', $withoutComments)
        )));
    }

    private function withoutCommentLines(string $path): string
    {
        $contents = file_get_contents($path);
        $lines = array_map(
            fn (string $line) => preg_match('/^\s*\/\//', $line) ? '' : $line,
            explode("\n", $contents)
        );

        return implode("\n", $lines);
    }
}
