<?php

namespace App\Console\Commands\Neo4j;

use App\Services\Graph\GraphDrain;
use App\Services\Graph\GraphSchema;
use App\Services\Graph\ProjectionRegistry;
use App\Services\Neo4jService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Answer the only question that matters about a sync: is anything missing?
 *
 * The live pipeline can be healthy — queue empty, every row SUCCESS — while the
 * graph is still wrong, because a row that never produced an event looks
 * exactly like a row that synced perfectly. That is precisely how this failed:
 * on 2026-08-21 `sync_log` held 9,257 rows, all SUCCESS, zero pending, zero
 * failed, and tblstudent#281472 was nonetheless absent from Neo4j. Nothing in
 * the pipeline could have told you, because the pipeline was never asked.
 *
 * This asks. It compares MariaDB against Neo4j row by row and, with --fix,
 * re-enqueues whatever is missing through the ordinary projection path.
 *
 *   php artisan neo4j:reconcile
 *   php artisan neo4j:reconcile --entity=tblstudent --fix
 *   php artisan neo4j:reconcile --entity=tblstudent --tenant=1 --limit=500 --fix
 *   php artisan neo4j:reconcile --since=2026-08-01 --fix --dry-run
 *
 * Run it nightly. A drift of zero is the only evidence that the sync works.
 *
 * REPLACES `neo4j:backfill-students`, which did the same job for one entity:
 * select students absent from the graph, push them through the same projection,
 * drain. Keeping both meant two implementations of "which rows are missing" that
 * could disagree — and the old one could not answer the question at all, it only
 * ever re-pushed rows whether or not they were already there. Its --tenant,
 * --limit and --dry-run options are carried over here.
 */
class ReconcileCommand extends Command
{
    protected $signature = 'neo4j:reconcile
                            {--entity=* : Restrict to these MariaDB tables}
                            {--tenant= : Restrict to one sub_institute_id}
                            {--limit=2000 : Newest rows to check per entity}
                            {--since= : Only rows created on/after this date, where the table records one}
                            {--fix : Re-enqueue and drain whatever is missing}
                            {--dry-run : With --fix, list what would be repaired and write nothing}
                            {--relationships : Audit every K12 edge type instead of node counts}';

    protected $description = 'Compare MariaDB against Neo4j and repair anything the sync missed';

    /**
     * table => [Neo4j label, column holding that label's graph key].
     *
     * The declarative entities are read from config; the three bespoke ones are
     * named here because their key is not a plain primary key of the table they
     * are reconciled from.
     */
    private const BESPOKE = [
        'tblstudent'            => ['StuDetail', 'id'],
        'tblstudent_enrollment' => ['Student', 'id'],
        'lms_online_exam'       => ['Result', 'id'],
    ];

    /**
     * Every edge k12_cypher.txt builds, with the reason it cannot be synced
     * where that applies. Verified column-by-column against `vivek_erp` on
     * 2026-08-21.
     *
     * [source label, type, target label, why-it-cannot-be-synced or null]
     */
    private const K12_EDGES = [
        ['StuDetail', 'HAS_STUDENT', 'Student', null],
        ['Student', 'ENROLLED_IN', 'Standard', null],
        ['Standard', 'HAS_SUBJECT', 'Subject', null],
        ['Subject', 'HAS_CHAPTER', 'Chapter', null],
        ['Unit', 'HAS_CHAPTER', 'Chapter', null],
        ['Chapter', 'HAS_LESSON', 'Lesson', null],
        ['Curriculum', 'HAS_UNIT', 'Unit', null],
        ['Curriculum', 'INCLUDES', 'Subject', null],
        ['Subject', 'BELONGS_TO_CURRICULUM', 'Curriculum', null],
        ['Subject', 'HAS_ASSESSMENT', 'Assessment', null],
        ['Assessment', 'HAS_QUESTION', 'Question', null],
        ['Question', 'BELONGS_TO', 'Chapter', null],
        ['Question', 'ASSESSES', 'Concept', null],
        ['Chapter', 'HAS_CONCEPT', 'Concept', null],
        ['Student', 'HAS_RESULT', 'Result', null],
        ['Result', 'FOR_ASSESSMENT', 'Assessment', null],
        ['Student', 'ATTEMPTED', 'Assessment', null],

        ['Lesson', 'COVERS', 'Concept', 'lms_concept has no lesson_id; chapter_id is the only working join, so HAS_CONCEPT carries this instead'],
        ['Assessment', 'ASSESSES', 'Concept', 'question_paper has no concept_id; ASSESSES hangs off lms_question_master.concept_id instead'],
        ['Assessment', 'ASSESSES_CHAPTER', 'Chapter', 'derived post-pass over Assessment->Question->Chapter, not a row-level FK'],
        ['Concept', 'PREREQUISITE_OF', 'Concept', 'derived by pairing concepts in one chapter; not a MariaDB relationship'],
        ['Student', 'ATTENDED', 'Lesson', 'k12 builds this from standard_id equality across ALL students and lessons - a cross join, not a row event'],
        ['Student', 'MASTERS', 'Concept', 'computed from result aggregates; owned by the PAL coherence pass, not this outbox'],
        ['Student', 'HAS_MISCONCEPTION', 'Misconception', 'lms_misconceptions does not exist in this database'],
        ['Misconception', 'OCCURS_IN', 'Concept', 'lms_misconceptions does not exist in this database'],
        ['LearningContent', 'TEACHES', 'Concept', 'suggested_content has no concept_id/title/modality columns'],
        ['LearningContent', 'REMEDIATES', 'Misconception', 'suggested_content has no misconception_id column'],
    ];

    /**
     * K12 labels that this sync deliberately does NOT maintain, and why.
     *
     * All of them hold nodes today, put there once by the CSV ingest. Nothing
     * keeps them current, and nothing can, because the table k12_cypher.txt
     * reads them from is not in this database. Listed explicitly so the report
     * says "unmaintained, here is why" instead of silently omitting them —
     * silence is what let the student gap go unnoticed for months.
     *
     * [label, table k12_cypher.txt expects, what is actually wrong]
     */
    private const K12_LABELS_WITHOUT_SOURCE = [
        ['Misconception', 'lms_misconceptions', 'table does not exist; misconceptions live in pal_misconceptions / pal_misconception_library on the PAL model'],
        ['LearningContent', 'suggested_content', 'table exists but has no concept_id, misconception_id, title, modality or bloom_level'],
        ['LearningObjects', 'lms_learning_objectives', 'table does not exist'],
        ['CompetencyStandards', 'lms_competency_standards', 'table does not exist'],
        ['ChapterStandardMap', 'lms_chapter_standard_map', 'table does not exist'],
        ['AssessmentTypology', 'lms_assessment_typology', 'table does not exist'],
    ];

    /** Tables that record when a row was created, for --since. */
    private const CREATED_COLUMN = [
        'chapter_master'      => 'created_at',
        'lms_concept'         => 'created_at',
        'lms_curriculum'      => 'created_at',
        'lms_units'           => 'created_at',
        'lms_online_exam'     => 'created_at',
        'question_paper'      => 'created_on',
        'lms_question_master' => 'created_on',
        'standard'            => 'created_at',
        'sub_std_map'         => 'created_at',
    ];

    public function handle(ProjectionRegistry $registry, GraphDrain $drain, Neo4jService $neo4j): int
    {
        if ($this->option('relationships')) {
            return $this->auditRelationships($neo4j);
        }

        $targets = $this->targets($registry);

        if ($targets === []) {
            $this->error('No matching entity. Known: ' . implode(', ', array_keys($this->allTargets())));

            return self::FAILURE;
        }

        $rows = [];
        $totalMissing = 0;
        $totalFixed = 0;

        foreach ($targets as $table => [$label, $keyColumn]) {
            try {
                $candidates = $this->candidates($table, $label, $keyColumn);
            } catch (Throwable $e) {
                $rows[] = [$table, $label, '-', '-', 'ERROR: ' . $e->getMessage()];

                continue;
            }

            if ($candidates === []) {
                $rows[] = [$table, $label, 0, 0, '-'];

                continue;
            }

            $missing = $this->missingInGraph($neo4j, $label, $candidates);
            $totalMissing += count($missing);

            $fixed = 0;

            if ($missing !== [] && $this->option('fix')) {
                $fixed = $this->repair($registry, $drain, $table, $label, $missing);
                $totalFixed += $fixed;
            }

            $rows[] = [
                $table,
                $label,
                count($candidates),
                count($missing),
                $this->action($missing, $fixed),
            ];
        }

        $this->table(['table', 'label', 'checked', 'missing', 'action'], $rows);
        $this->unmaintainedLabels($neo4j);
        $this->backlog();

        if ($totalMissing === 0) {
            $this->info('No drift: every row checked is in the graph.');

            return self::SUCCESS;
        }

        $this->warn("{$totalMissing} row(s) missing from the graph" . ($this->option('fix') ? ", {$totalFixed} re-synced." : '.'));

        // A clean --fix run is a success; a report-only run that found drift is
        // a failure, so it can be alerted on from cron.
        return ($this->option('fix') && $totalFixed >= $totalMissing) ? self::SUCCESS : self::FAILURE;
    }

    // -----------------------------------------------------------------------

    /**
     * Every relationship type k12_cypher.txt describes, and whether the live
     * sync can actually produce it.
     *
     * Three outcomes, and the difference matters when reading the report:
     *
     *   SYNCED    a projection emits it, so new rows create it automatically.
     *   STATIC    it is in the graph only because the CSV ingest built it once;
     *             nothing maintains it, so it goes stale silently.
     *   NO SOURCE the columns or tables it needs do not exist in this database,
     *             so it cannot be built from MariaDB as things stand.
     */
    private function auditRelationships(Neo4jService $neo4j): int
    {
        $live = $this->liveEdgeCounts($neo4j);
        $produced = $this->producedEdges();
        $rows = [];
        $synced = 0;

        foreach (self::K12_EDGES as [$src, $type, $tgt, $note]) {
            $key = $src . '|' . $type . '|' . $tgt;
            $count = $live[$key] ?? 0;
            $producer = $produced[$key] ?? null;

            if ($producer !== null) {
                $status = 'SYNCED';
                $synced++;
            } elseif ($note !== null) {
                $status = 'NO SOURCE';
            } else {
                $status = $count > 0 ? 'STATIC' : 'ABSENT';
            }

            $rows[] = [
                '(' . $src . ')-[:' . $type . ']->(' . $tgt . ')',
                number_format($count),
                $producer ?? '-',
                $status,
                $note === null ? '' : substr($note, 0, 62),
            ];
        }

        $this->table(['edge', 'in graph', 'produced by', 'status', 'why not'], $rows);

        $known = array_flip(array_map(
            fn ($e) => $e[0] . '|' . $e[1] . '|' . $e[2],
            self::K12_EDGES
        ));
        $unlisted = array_diff_key($live, $known);
        arsort($unlisted);

        if ($unlisted !== []) {
            $this->line('');
            $this->line('In the graph but outside the K12 set (ERP migration, PAL, or drift):');

            foreach (array_slice($unlisted, 0, 10, true) as $key => $count) {
                [$a, $r, $b] = explode('|', $key);
                $this->line(sprintf('  %-50s %s', '(' . $a . ')-[:' . $r . ']->(' . $b . ')', number_format($count)));
            }

            if (count($unlisted) > 10) {
                $this->line('  ... and ' . (count($unlisted) - 10) . ' more');
            }
        }

        $this->line('');
        $this->info($synced . ' of ' . count(self::K12_EDGES) . ' K12 edge types are maintained by the live sync.');

        return self::SUCCESS;
    }

    /** @return array<string, int> "Src|REL|Tgt" => count */
    private function liveEdgeCounts(Neo4jService $neo4j): array
    {
        $counts = [];

        foreach ($neo4j->run(
            'MATCH (a)-[r]->(b) RETURN labels(a)[0] AS s, type(r) AS t, labels(b)[0] AS g, count(*) AS c'
        ) as $row) {
            $counts[$row->get('s') . '|' . $row->get('t') . '|' . $row->get('g')] = (int) $row->get('c');
        }

        return $counts;
    }

    /**
     * Edges the live sync emits, read from the projection specs themselves so
     * this report cannot drift from what the code actually does.
     *
     * @return array<string, string> "Src|REL|Tgt" => producing table
     */
    private function producedEdges(): array
    {
        $produced = [
            // Bespoke projections declare their edges in code, not config.
            'StuDetail|HAS_STUDENT|Student'    => 'tblstudent',
            'Student|ENROLLED_IN|Standard'     => 'tblstudent',
            'Student|HAS_RESULT|Result'        => 'lms_online_exam',
            'Result|FOR_ASSESSMENT|Assessment' => 'lms_online_exam',
            'Student|ATTEMPTED|Assessment'     => 'lms_online_exam',
        ];

        foreach ((array) config('neo4j.projections.entities', []) as $table => $spec) {
            foreach ($spec['relationships'] ?? [] as $rel) {
                $produced[$rel['from'][0] . '|' . $rel['type'] . '|' . $rel['to'][0]] = $table;
            }
        }

        return $produced;
    }

    /**
     * The K12 labels nothing maintains, with their live node counts.
     */
    private function unmaintainedLabels(Neo4jService $neo4j): void
    {
        $rows = [];

        foreach (self::K12_LABELS_WITHOUT_SOURCE as [$label, $table, $reason]) {
            $count = 0;

            foreach ($neo4j->run('MATCH (n:`' . $label . '`) RETURN count(n) AS c') as $r) {
                $count = (int) $r->get('c');
            }

            $rows[] = [$label, $table, number_format($count), $reason];
        }

        $this->line('');
        $this->line('K12 labels with no live source - present in the graph, never updated:');
        $this->table(['label', 'expected table', 'nodes', 'why it cannot sync'], $rows);
    }

    private function action(array $missing, int $fixed): string
    {
        if ($missing === []) {
            return '-';
        }

        if (! $this->option('fix')) {
            return 'run with --fix';
        }

        return $this->option('dry-run') ? 'dry run' : "{$fixed} re-synced";
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function allTargets(): array
    {
        $targets = self::BESPOKE;

        foreach ((array) config('neo4j.projections.entities', []) as $table => $spec) {
            if (isset($targets[$table])) {
                continue;
            }

            // An edge-only spec (a join table such as `hrms_emp_leaves`) owns no
            // label, so there is no node population to count rows against. Its
            // edges are still audited by --relationships, which reads
            // `producedEdges()` rather than this map.
            if (($spec['edges_only'] ?? false) === true || ! isset($spec['label'])) {
                continue;
            }

            $targets[$table] = [$spec['label'], $spec['key_column'] ?? 'id'];
        }

        return $targets;
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function targets(ProjectionRegistry $registry): array
    {
        $all = array_filter(
            $this->allTargets(),
            fn ($table) => $registry->has($table),
            ARRAY_FILTER_USE_KEY
        );

        $wanted = (array) $this->option('entity');

        return $wanted === []
            ? $all
            : array_intersect_key($all, array_flip($wanted));
    }

    /**
     * The newest distinct graph keys worth checking.
     *
     * Newest first because that is where drift appears: a sync that stops
     * working stops working from a point in time onward.
     *
     * @return int[]
     */
    private function candidates(string $table, string $label, string $keyColumn): array
    {
        $createdColumn = self::CREATED_COLUMN[$table] ?? null;

        $rows = DB::table($table)
            ->select($keyColumn . ' as graph_key')
            ->distinct()
            ->whereNotNull($keyColumn)
            ->where($keyColumn, '>', 0)
            ->when(
                $this->option('since') && $createdColumn && $this->hasColumn($table, $createdColumn),
                fn ($q) => $q->where($createdColumn, '>=', $this->option('since'))
            )
            ->when(
                $this->option('tenant') && $this->hasColumn($table, 'sub_institute_id'),
                fn ($q) => $q->where('sub_institute_id', (int) $this->option('tenant'))
            )
            ->orderByDesc($keyColumn)
            ->limit((int) $this->option('limit'))
            ->get();

        return $rows->map(fn ($row) => (int) $row->graph_key)->all();
    }

    /**
     * Which candidates have no node.
     *
     * MUST CHECK BOTH KEY CONVENTIONS. The graph carries legacy-keyed and
     * uid-keyed nodes side by side, and a row present only as `Unit:1:0:22` is
     * present — reporting it missing would invite --fix to MERGE a second,
     * legacy-keyed node for the same row. That is defect D2, the exact
     * duplication the graph is being migrated out of, and reconcile creating it
     * wholesale would be far worse than the drift it set out to fix.
     *
     * The uid's tenant segment is NOT reconstructed here. `lms_units` has no
     * `sub_institute_id` column at all, so an early version defaulted it to 0,
     * looked for `Unit:0:0:22`, missed all 60 real `Unit:1:0:22` nodes and
     * reported every one of them missing. Instead the id is parsed back out of
     * the uid server-side — `Label:tenant:syear:id`, so segment 3 — which needs
     * no tenant and cannot be fooled by one.
     *
     * @param  int[]  $ids  candidate graph keys
     * @return int[]
     */
    private function missingInGraph(Neo4jService $neo4j, string $label, array $ids): array
    {
        GraphSchema::assertLabel($label);
        $key = GraphSchema::key($label);

        $found = [];

        foreach ($neo4j->run(
            "MATCH (n:`{$label}`) WHERE n.`{$key}` IN \$ids RETURN n.`{$key}` AS k",
            ['ids' => $ids]
        ) as $row) {
            $found[(int) $row->get('k')] = true;
        }

        // Deliberately NOT gated on GraphSchema::hasUidFallback(). That flag
        // answers a different question — "can an edge resolve this label by uid
        // at link time", which :Lesson fails only because its uid is year-scoped
        // and the outbox carries no syear. Presence is not link-time: the uid is
        // read here, not rebuilt, so the year is right there in the string. With
        // the flag, :Lesson's 1,807 uid-keyed nodes were invisible and every one
        // of them looked missing.
        foreach ($neo4j->run(
            "MATCH (n:`{$label}`) WHERE n.uid IS NOT NULL
             WITH toInteger(split(n.uid, ':')[3]) AS uidId
             WHERE uidId IN \$ids
             RETURN DISTINCT uidId AS k",
            ['ids' => $ids]
        ) as $row) {
            $found[(int) $row->get('k')] = true;
        }

        return array_values(array_filter($ids, fn ($id) => ! isset($found[$id])));
    }

    /**
     * Re-enqueue missing nodes through the normal projection, then drain them,
     * so a repaired row is byte-identical to a live-synced one.
     *
     * @param  int[]  $missing
     */
    private function repair(ProjectionRegistry $registry, GraphDrain $drain, string $table, string $label, array $missing): int
    {
        $projection = $registry->for($table);

        if ($this->option('dry-run')) {
            foreach (array_slice($missing, 0, 20) as $nodeId) {
                $this->line("  would re-sync {$table}#{$nodeId} as :{$label}");
            }

            if (count($missing) > 20) {
                $this->line('  ... and ' . (count($missing) - 20) . ' more');
            }

            return 0;
        }

        $fixed = 0;

        foreach (array_chunk($missing, 50) as $chunk) {
            $log = [];
            $queue = [];

            foreach ($chunk as $nodeId) {
                try {
                    $ids = $projection->enqueueNode($label, $nodeId);
                    $log = array_merge($log, $ids['log']);
                    $queue = array_merge($queue, $ids['queue']);

                    if ($ids['log'] !== []) {
                        $fixed++;
                    }
                } catch (Throwable $e) {
                    $this->error("  {$table}#{$nodeId}: {$e->getMessage()}");
                }
            }

            if ($log !== [] || $queue !== []) {
                $drain->flush(['log' => $log, 'queue' => $queue]);
            }
        }

        return $fixed;
    }

    private function backlog(): void
    {
        $depth = GraphDrain::depth();

        $failedNodes = DB::table('sync_log')->where('status', 'FAILED')->count();
        $failedRels = DB::table('neo4j_sync_queue')->where('status', 'failed')->count();

        $this->line("Backlog:  {$depth['nodes']} node(s), {$depth['rels']} relationship(s) pending");

        if ($failedNodes > 0 || $failedRels > 0) {
            $this->warn("Exhausted: {$failedNodes} node row(s), {$failedRels} relationship row(s) marked FAILED");
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) !== null;
    }
}
