<?php

namespace App\Console\Commands\Neo4j;

use App\Services\Neo4jService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only report by default: how many nodes of a label have no current SQL
 * row backing them - the opposite direction from `missingInGraph()` in
 * `ReconcileCommand`, which only ever finds SQL rows missing FROM the graph.
 *
 * Found during the 2026-09-28 sync-architecture review: several
 * pipeline-A-owned labels carry far more Neo4j nodes than their source
 * table currently has rows - most strikingly `:Chapter`, 7,981 nodes against
 * `chapter_master`'s current 467 rows. This is not a live-sync bug (the
 * trigger-driven outbox MERGEs on native id, it cannot create extras by
 * itself) - it is leftover population from the historical bulk-load
 * pipelines (`neo4j:load`, the non-uid `.cypher` modules under
 * `database/neo4j/cypher/`) that was never reconciled after `chapter_master`
 * was regenerated to its current, smaller Gen-2 set.
 *
 * NO DEFAULT DELETE FLAG. `--tag`/`--sweep` below exist for exactly ONE
 * pre-authorized cleanup (tenant-1 `:Chapter`, confirmed 2026-09-29 to be an
 * old, deliberately-abandoned syllabus - see the design review's
 * normalization plan) and require BOTH `--label=` and `--tenant=` explicitly;
 * there is no "tag everything excess" mode. Cleanup for any other
 * label/tenant needs the same per-tenant investigation tenant-1 got before
 * being run this way - see the tenant-195/72/1000 open question in the plan.
 * `--tag` only marks candidates (`SET`, nothing removed); `--sweep` only
 * ever deletes nodes a prior `--tag` run marked, never a fresh re-evaluation
 * at delete time, so nothing added or changed after tagging gets swept in by
 * accident.
 */
class NodeExcessAuditCommand extends Command
{
    protected $signature = 'neo4j:node-excess
        {--label= : only audit (or tag/sweep) this label}
        {--tag : mark excess nodes for this label+tenant as cleanup candidates - writes a property, deletes nothing}
        {--sweep : DETACH DELETE nodes previously marked by --tag for this label - deletes nothing not already tagged}
        {--tenant= : required with --tag; scopes tagging to this sub_institute_id only}';

    protected $description = 'Report on (or, narrowly, clean up) Neo4j nodes with no current SQL row backing them (the opposite of neo4j:reconcile)';

    /** label => [sqlTable, sqlIdColumn, neo4jKeyProperty] - both from GraphSchema::LABELS where applicable. */
    private const AUDITS = [
        'Chapter'    => ['chapter_master', 'id', 'chId'],
        'Unit'       => ['lms_units', 'id', 'unitId'],
        'Curriculum' => ['lms_curriculum', 'id', 'curriculumId'],
        'Question'   => ['lms_question_master', 'id', 'qId'],
        'Lesson'     => ['lms_lesson_plan', 'id', 'lessonId'],
        'Concept'    => ['lms_concept', 'id', 'conceptId'],
        'Content'    => ['content_master', 'id', 'id'],
    ];

    public function handle(Neo4jService $neo4j): int
    {
        if ($this->option('tag')) {
            return $this->tag($neo4j);
        }

        if ($this->option('sweep')) {
            return $this->sweep($neo4j);
        }

        return $this->report($neo4j);
    }

    private function report(Neo4jService $neo4j): int
    {
        $only = $this->option('label');
        $rows = [];

        foreach (self::AUDITS as $label => [$table, $column, $key]) {
            if ($only && strcasecmp($only, $label) !== 0) {
                continue;
            }

            $sqlIds = DB::table($table)->pluck($column)->map(fn ($v) => (string) $v)->all();
            $sqlCount = count($sqlIds);

            $neo4jTotal = (int) $neo4j->run("MATCH (n:`{$label}`) RETURN count(n) AS c")->first()->get('c');
            $keyed = (int) $neo4j->run("MATCH (n:`{$label}`) WHERE n.`{$key}` IS NOT NULL RETURN count(n) AS c")->first()->get('c');

            // Chunk the id list into the live graph rather than pulling every
            // node's key back to PHP - cheaper both directions at this scale.
            $matching = 0;
            foreach (array_chunk($sqlIds, 5000) as $chunk) {
                $matching += (int) $neo4j->run(
                    "MATCH (n:`{$label}`) WHERE toString(n.`{$key}`) IN \$ids RETURN count(n) AS c",
                    ['ids' => $chunk]
                )->first()->get('c');
            }

            $excess = $neo4jTotal - $matching;

            $rows[] = [
                $label,
                number_format($sqlCount),
                number_format($neo4jTotal),
                number_format($keyed),
                number_format($matching),
                number_format($excess),
                $neo4jTotal > 0 ? round(100 * $excess / $neo4jTotal, 1) . '%' : '0%',
            ];
        }

        if ($rows === []) {
            $this->error($only ? "Unknown or unaudited label: {$only}" : 'Nothing to audit.');

            return self::FAILURE;
        }

        $this->table(
            ['label', 'SQL rows', 'Neo4j nodes', 'has key prop', 'matches current SQL id', 'excess (no current SQL match)', 'excess %'],
            $rows
        );

        $this->newLine();
        $this->line('Report only - no nodes were changed. "excess" nodes are candidates for a deliberate, '
            . 'explicitly-scoped cleanup pass, not something this command removes automatically.');

        return self::SUCCESS;
    }

    /**
     * Mark (never delete) excess nodes for one label, scoped to one tenant.
     * Both are required on purpose - there is no "tag every excess node"
     * mode, because most labels/tenants have not individually been checked
     * the way tenant-1 Chapter was (see class docblock).
     */
    private function tag(Neo4jService $neo4j): int
    {
        $label = $this->option('label');
        $tenant = $this->option('tenant');

        if (! $label || ! isset(self::AUDITS[$label])) {
            $this->error('--tag requires --label= set to one of: ' . implode(', ', array_keys(self::AUDITS)));

            return self::FAILURE;
        }

        if ($tenant === null || $tenant === '') {
            $this->error('--tag requires --tenant= - there is no "tag everything" mode. See the class docblock.');

            return self::FAILURE;
        }

        [$table, $column, $key] = self::AUDITS[$label];
        $tenant = (int) $tenant;

        $currentIds = DB::table($table)->pluck($column)->map(fn ($v) => (string) $v)->all();

        $tagged = (int) $neo4j->run(
            "MATCH (n:`{$label}`) WHERE n.sub_institute_id = \$tenant AND NOT toString(n.`{$key}`) IN \$ids "
                . 'SET n.cleanup_reason = \'no current SQL match as of tag time\', n.audited_at = datetime() '
                . 'RETURN count(n) AS c',
            ['tenant' => $tenant, 'ids' => $currentIds]
        )->first()->get('c');

        $this->info("{$label} (tenant {$tenant}): {$tagged} node(s) tagged with cleanup_reason + audited_at. Nothing deleted.");
        $this->line("Run \"neo4j:node-excess --label={$label} --sweep --tenant={$tenant}\" to remove exactly these tagged nodes, "
            . 'or re-run the plain report first to sanity-check the count.');

        return self::SUCCESS;
    }

    /**
     * DETACH DELETE nodes a prior --tag run marked for this label (and,
     * optionally, this tenant) - never a fresh re-evaluation of what
     * currently looks excess, so nothing added or changed since tagging is
     * swept in by accident.
     */
    private function sweep(Neo4jService $neo4j): int
    {
        $label = $this->option('label');

        if (! $label || ! isset(self::AUDITS[$label])) {
            $this->error('--sweep requires --label= set to one of: ' . implode(', ', array_keys(self::AUDITS)));

            return self::FAILURE;
        }

        $tenant = $this->option('tenant');
        $params = [];
        $tenantClause = '';

        if ($tenant !== null && $tenant !== '') {
            $tenantClause = ' AND n.sub_institute_id = $tenant';
            $params['tenant'] = (int) $tenant;
        }

        $countCypher = "MATCH (n:`{$label}`) WHERE n.cleanup_reason IS NOT NULL{$tenantClause} RETURN count(n) AS c";

        $before = (int) $neo4j->run($countCypher, $params)->first()->get('c');

        if ($before === 0) {
            $this->info("No tagged {$label} nodes found" . ($tenant !== null && $tenant !== '' ? " for tenant {$tenant}" : '')
                . ' - nothing to sweep. Run --tag first.');

            return self::SUCCESS;
        }

        $this->warn("Deleting {$before} tagged {$label} node(s)" . ($tenant !== null && $tenant !== '' ? " (tenant {$tenant})" : '') . '...');

        $neo4j->run(
            "MATCH (n:`{$label}`) WHERE n.cleanup_reason IS NOT NULL{$tenantClause} DETACH DELETE n",
            $params
        );

        $after = (int) $neo4j->run($countCypher, $params)->first()->get('c');

        $this->info("{$before} node(s) deleted, {$after} tagged node(s) remain (should be 0).");

        return self::SUCCESS;
    }
}
