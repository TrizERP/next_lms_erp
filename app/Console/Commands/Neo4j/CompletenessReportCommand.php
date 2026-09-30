<?php

namespace App\Console\Commands\Neo4j;

use App\Services\Graph\ProjectionRegistry;
use App\Services\Neo4jService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The self-service answer to "how do I check the sync is actually complete."
 *
 * `neo4j:node-excess` (existing) answers ONE direction well: Neo4j nodes with
 * no current SQL row behind them. This command is the fuller picture, in one
 * run, with no required arguments (`--tenant=` narrows section 1 to one
 * `sub_institute_id`'s share of each label; `--label=` narrows any section to
 * rows/edges matching that name):
 *
 *   1. NODE COVERAGE - for every table `ProjectionRegistry` knows how to
 *      resolve (both declarative `config('neo4j.projections.entities')`
 *      specs AND bespoke PHP classes - see below), SQL row/distinct-key
 *      count next to the live Neo4j node count for the label(s) it owns.
 *   2. TRIGGER/SPEC GAP - any table with a live DB trigger
 *      (`projections.triggered`) that `ProjectionRegistry::has()` cannot
 *      resolve at all. This is exactly the shape of the `lms_online_exam` bug
 *      found 2026-09-28 (trigger fires, queues a row, `ProjectionRegistry::
 *      for()` throws because nothing knows how to resolve it) - generalised
 *      into a standing check instead of a one-off fix, so a future instance
 *      of the same mistake shows up here instead of silently piling into
 *      `neo4j_sync_queue` as failures. That specific case is fixed now (a
 *      bespoke `ResultGraphProjection` claims it) - verified by re-running
 *      this exact section, not asserted.
 *
 *      BOTH sections above resolve tables through `ProjectionRegistry`, not
 *      by reading `config('neo4j.projections.entities')` directly - that
 *      config only lists tables served by the generic `TableGraphProjection`.
 *      Four tables (`tblstudent`, `tblstudent_enrollment`, `tbluser`,
 *      `lms_online_exam`) are correctly served by BESPOKE PHP classes instead
 *      (`StudentGraphProjection`, `StaffGraphProjection`, `ResultGraphProjection`)
 *      that `ProjectionRegistry` prefers over the config map. An earlier
 *      version of this command compared against the config map alone and
 *      flagged all four as gaps/mismatches - confirmed live 2026-09-29 to be
 *      false positives (`tbluser` splits into 4,654 `:Staff` + 118 `:Teacher`,
 *      exactly 4,772, the full table). Going through the registry is what
 *      actually matches what `GraphDrain`/`ProjectionRegistry::for()` does at
 *      sync time, so this report can't drift from reality the same way twice.
 *   3. PIPELINE-B EDGES - the bulk-Cypher relationships
 *      `CoherenceGraphProjection` owns (REQUIRES, CROSS_LINKS, TEACHES,
 *      ASSESSES's pipeline-B-owned slice, HAS_MASTERY, AFFECTS,
 *      CORRECTS_WITH, HAS_NODE, MASTERS_NODE, the Chapter/Topic-grain
 *      REQUIRES from `pal_learning_relations`) - nothing else reports these
 *      in one place. Every MATCH pattern below was copied from the exact
 *      Cypher `CoherenceGraphProjection` runs, not re-derived, so this
 *      cannot silently drift from what the projector actually writes the way
 *      a hand-reasoned equivalent query could.
 *   4. CONNECTIVITY SPOT-CHECKS - nodes of a key label with ZERO of an edge
 *      type they would be expected to carry (a Concept with no prerequisite
 *      edges at all in either direction, a Chapter with no concepts, etc).
 *      Not every zero is a bug - some concepts genuinely have no
 *      prerequisite - but a big or growing count here is the signal that
 *      caught the original Chapter uid/chId bug, generalised into something
 *      that runs on demand instead of only when someone happens to look.
 *
 * Nothing in this command writes to either database. It is pure reporting,
 * safe to run at any time, as often as wanted.
 */
class CompletenessReportCommand extends Command
{
    protected $signature = 'neo4j:completeness
        {--label= : only show this one label/edge row}
        {--tenant= : section 1 only - report this sub_institute_id\'s share of each label instead of the global count}';

    protected $description = 'Full sync-completeness report: node coverage, trigger/spec gaps, pipeline-B edge counts, and connectivity spot-checks - the self-service "is everything synced" check';

    /**
     * Every Cypher pattern here is copied verbatim (direction, key property)
     * from the method named in the comment - see CoherenceGraphProjection.php.
     */
    private const PIPELINE_B_EDGES = [
        'HAS_CONCEPT  (:Chapter)->(:Concept)'        => ['projectConcepts',          'MATCH (:Chapter)-[e:HAS_CONCEPT]->(:Concept) RETURN count(e) AS c'],
        'REQUIRES     (:Concept)->(:Concept)'        => ['projectRelations',         'MATCH (:Concept)-[e:REQUIRES]->(:Concept) RETURN count(e) AS c'],
        'CROSS_LINKS  (:Concept)->(:Concept)'        => ['projectRelations',         'MATCH (:Concept)-[e:CROSS_LINKS]->(:Concept) RETURN count(e) AS c'],
        'TEACHES      (:Content)->(:Concept)'        => ['projectTeaches',           'MATCH (:Content)-[e:TEACHES]->(:Concept) RETURN count(e) AS c'],
        'ASSESSES     (:Question)->(:Concept) [B]'   => ['projectAssesses',          'MATCH (:Question)-[e:ASSESSES]->(:Concept) WHERE e.quality_status IS NOT NULL RETURN count(e) AS c'],
        'ASSESSES     (:Question)->(:Concept) [A]'   => ['pipeline A (config/neo4j.php)', 'MATCH (:Question)-[e:ASSESSES]->(:Concept) WHERE e.quality_status IS NULL RETURN count(e) AS c'],
        'HAS_MASTERY  (:StuDetail)->(:Concept)'      => ['projectMastery',           'MATCH (:StuDetail)-[e:HAS_MASTERY]->(:Concept) RETURN count(e) AS c'],
        'REQUIRES     (:Chapter)->(:Chapter)'        => ['projectLearningRelations', 'MATCH (:Chapter)-[e:REQUIRES]->(:Chapter) RETURN count(e) AS c'],
        'REQUIRES     (:Topic)->(:Topic)'            => ['projectLearningRelations', 'MATCH (:Topic)-[e:REQUIRES]->(:Topic) RETURN count(e) AS c'],
        'AFFECTS      (:Misconception)->(:Concept)'  => ['projectMisconceptions',    'MATCH (:Misconception)-[e:AFFECTS]->(:Concept) RETURN count(e) AS c'],
        'CORRECTS_WITH(:Misconception)->(:Content)'  => ['projectMisconceptions',    'MATCH (:Misconception)-[e:CORRECTS_WITH]->(:Content) RETURN count(e) AS c'],
        'HAS_NODE     (:Concept)->(:ConceptNode)'    => ['projectConceptNodes',      'MATCH (:Concept)-[e:HAS_NODE]->(:ConceptNode) RETURN count(e) AS c'],
        'MASTERS_NODE (:StuDetail)->(:ConceptNode)'  => ['projectNodeMastery',       'MATCH (:StuDetail)-[e:MASTERS_NODE]->(:ConceptNode) RETURN count(e) AS c'],
    ];

    /** label => [cypher for "how many nodes of this label have zero of the named edge"] */
    private const CONNECTIVITY_CHECKS = [
        'Concept with no REQUIRES (either direction)' =>
            'MATCH (c:Concept) WHERE NOT (c)-[:REQUIRES]-() RETURN count(c) AS c',
        'Concept with no HAS_CONCEPT parent (orphaned from its Chapter)' =>
            'MATCH (c:Concept) WHERE NOT (:Chapter)-[:HAS_CONCEPT]->(c) RETURN count(c) AS c',
        'Chapter with no HAS_CONCEPT children' =>
            'MATCH (ch:Chapter) WHERE NOT (ch)-[:HAS_CONCEPT]->(:Concept) RETURN count(ch) AS c',
        'Question with no ASSESSES (any source)' =>
            'MATCH (q:Question) WHERE NOT (q)-[:ASSESSES]->() RETURN count(q) AS c',
        'Concept with no HAS_NODE (no K/A/S node breakdown)' =>
            'MATCH (c:Concept) WHERE NOT (c)-[:HAS_NODE]->(:ConceptNode) RETURN count(c) AS c',
    ];

    public function handle(Neo4jService $neo4j, ProjectionRegistry $registry): int
    {
        $only = $this->option('label');
        $tenant = $this->option('tenant');
        $tenant = ($tenant === null || $tenant === '') ? null : (int) $tenant;

        $this->nodeCoverage($neo4j, $registry, $only, $tenant);
        $this->triggerSpecGap($registry);
        $this->pipelineBEdges($neo4j, $only);
        $this->connectivity($neo4j, $only);

        return self::SUCCESS;
    }

    private function nodeCoverage(Neo4jService $neo4j, ProjectionRegistry $registry, ?string $only, ?int $tenant): void
    {
        $title = '1. Node coverage (via ProjectionRegistry - declarative + bespoke)';
        $title .= $tenant !== null ? ", tenant {$tenant} only" : '';
        $this->line("<fg=cyan;options=bold>{$title}</>");

        $entities = config('neo4j.projections.entities', []);
        $rows = [];
        $seenLabelSets = [];

        foreach ($registry->tables() as $table) {
            $projection = $registry->for($table);
            $labels = $projection->labels();

            if ($labels === []) {
                continue; // edges-only join table, no node to count
            }

            // A bespoke class can claim >1 table for the same label set
            // (StudentGraphProjection claims both tblstudent and
            // tblstudent_enrollment for [StuDetail, Student]) - report that
            // label set once, not once per table, or :StuDetail/:Student
            // would double-count across two identical rows.
            $labelKey = implode('+', $labels);

            if (isset($seenLabelSets[$labelKey])) {
                continue;
            }

            if ($only && stripos($labelKey, $only) === false) {
                continue;
            }

            $seenLabelSets[$labelKey] = true;

            // The SQL-side count is keyed on the table that carries the
            // MEANINGFUL row count for this label set - for a multi-table
            // bespoke projection that's the first (person-grain) table, e.g.
            // tblstudent for StudentGraphProjection, not the enrollment table.
            $sqlTable = $projection->tables()[0];
            $keyColumn = $entities[$sqlTable]['key_column'] ?? null;
            $displayTable = $sqlTable;

            // Whether THIS row can actually be tenant-scoped at all - decided
            // once, up front, and then applied identically to both the SQL
            // and the Neo4j side below. Scoping one side and not the other
            // (e.g. an unfiltered SQL count compared against a tenant-
            // filtered Neo4j count) is exactly how `lms_online_exam`/:Result
            // briefly looked like a 149,048-row gap on 2026-09-29 when it was
            // actually fully synced (149,052 vs 149,048, effectively even) -
            // that table simply has no `sub_institute_id` column to filter
            // on in the first place.
            $rowTenant = $tenant;

            if ($tenant !== null) {
                try {
                    DB::table($sqlTable)->where('sub_institute_id', $tenant)->limit(0)->count();
                } catch (\Throwable $e) {
                    $rowTenant = null;
                    $displayTable .= ' (no tenant column - global count, not tenant-scoped)';
                }
            }

            try {
                $query = DB::table($sqlTable);

                if ($rowTenant !== null) {
                    $query->where('sub_institute_id', $rowTenant);
                }

                $sqlCount = $keyColumn ? (int) $query->distinct()->count($keyColumn) : (int) $query->count();
            } catch (\Throwable $e) {
                $rows[] = [$labelKey, $displayTable, 'ERROR: ' . $e->getMessage(), '-', '-'];
                continue;
            }

            try {
                $neo4jCount = 0;

                foreach ($labels as $label) {
                    $cypher = $rowTenant !== null
                        ? "MATCH (n:`{$label}`) WHERE toString(n.sub_institute_id) = \$tenant RETURN count(n) AS c"
                        : "MATCH (n:`{$label}`) RETURN count(n) AS c";
                    $params = $rowTenant !== null ? ['tenant' => (string) $rowTenant] : [];
                    $neo4jCount += (int) $neo4j->run($cypher, $params)->first()->get('c');
                }
            } catch (\Throwable $e) {
                $rows[] = [$labelKey, $displayTable, (string) $sqlCount, 'ERROR: ' . $e->getMessage(), '-'];
                continue;
            }

            $diff = $neo4jCount - $sqlCount;
            $flag = $diff === 0 ? 'even' : ($diff > 0 ? "+{$diff} in Neo4j" : $diff . ' short in Neo4j');

            $rows[] = [$labelKey, $displayTable, number_format($sqlCount), number_format($neo4jCount), $flag];
        }

        $this->table(['label(s)', 'SQL table', 'SQL count', 'Neo4j nodes', 'diff'], $rows);
        $this->line('"diff" alone is not a verdict - a positive diff can be legitimate historical data '
            . '(see neo4j:node-excess for per-tenant investigation), a negative diff always means real rows have no graph node yet.'
            . ($tenant !== null ? ' Tenant filtering casts both sides to string (toString()) to sidestep int/string key drift seen elsewhere in this graph.' : ''));
        $this->newLine();
    }

    private function triggerSpecGap(ProjectionRegistry $registry): void
    {
        $this->line('<fg=cyan;options=bold>2. Trigger/spec gap (tables with a live trigger ProjectionRegistry cannot resolve at all)</>');

        $triggered = config('neo4j.projections.triggered', []);
        $missing = array_values(array_filter($triggered, fn ($t) => ! $registry->has($t)));

        if ($missing === []) {
            $this->info('None - every triggered table resolves via ProjectionRegistry (declarative or bespoke).');
        } else {
            $this->warn('These tables queue sync_log/neo4j_sync_queue rows that can NEVER resolve '
                . '(ProjectionRegistry::for() throws) - each one is a standing source of failed-queue rows:');
            foreach ($missing as $t) {
                $this->line("  - {$t}");
            }
        }

        $this->newLine();
    }

    private function pipelineBEdges(Neo4jService $neo4j, ?string $only): void
    {
        $this->line('<fg=cyan;options=bold>3. Pipeline-B edges (CoherenceGraphProjection - bulk Cypher MERGE)</>');

        $rows = [];

        foreach (self::PIPELINE_B_EDGES as $name => [$source, $cypher]) {
            if ($only && stripos($name, $only) === false) {
                continue;
            }

            try {
                $count = (int) $neo4j->run($cypher)->first()->get('c');
                $rows[] = [$name, $source, number_format($count)];
            } catch (\Throwable $e) {
                $rows[] = [$name, $source, 'ERROR: ' . $e->getMessage()];
            }
        }

        $this->table(['edge', 'written by', 'live count'], $rows);
        $this->line('These are not compared against an "expected" SQL count here (each method\'s own '
            . 'scoping/quality-filter logic decides what qualifies, and duplicating that logic in a '
            . 'second place risks the two silently drifting apart) - run `pal:coherence-sync --tenant= '
            . '--standard= --subject=` for a specific scope\'s dry-run comparison instead.');
        $this->newLine();
    }

    private function connectivity(Neo4jService $neo4j, ?string $only): void
    {
        $this->line('<fg=cyan;options=bold>4. Connectivity spot-checks</>');

        $rows = [];

        foreach (self::CONNECTIVITY_CHECKS as $name => $cypher) {
            if ($only && stripos($name, $only) === false) {
                continue;
            }

            try {
                $count = (int) $neo4j->run($cypher)->first()->get('c');
                $rows[] = [$name, number_format($count)];
            } catch (\Throwable $e) {
                $rows[] = [$name, 'ERROR: ' . $e->getMessage()];
            }
        }

        $this->table(['check', 'count'], $rows);
        $this->line('A non-zero count is not automatically a bug - some concepts genuinely have no '
            . 'prerequisite. Treat a LARGE or GROWING count as the signal worth investigating, the way '
            . 'the original Chapter uid/chId bug was found.');
    }
}
