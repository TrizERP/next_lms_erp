<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;

/**
 * One-time seed copy of the LMS+PAL-scoped slice of the K12 graph into a brand-new,
 * separate Neo4j instance dedicated to LMS+PAL work (`config('neo4j.targets.lms_pal')`).
 *
 * SOURCE (config('neo4j.uri'), the existing K12 graph) is READ-ONLY here — every
 * statement sent to it is a MATCH, never a write. TARGET is the only database this
 * command ever writes to.
 *
 * SCOPE. Both endpoints of a relationship must carry a label in SCOPE_LABELS for the
 * edge to be copied — computed live via labels(a)/labels(b), not a hand-enumerated
 * relationship-type list, so it can't silently miss a type the way a static list would.
 *
 * KEY STRATEGY. GraphSchema.php's declared merge key for a label does not always match
 * what the live PAL-coherence Cypher actually keys on (e.g. :Content is matched on plain
 * `id` in CoherenceGraphProjection::projectTeaches(), not the `contentNodeId` the schema
 * registry claims) — see [[neo4j-dual-key-graph]]. Re-deriving a "correct" business key
 * per label for a copy tool is fragile and unnecessary: this command instead copies each
 * node keyed on the SOURCE's own internal id (`id(n)`), stamped onto the target node as
 * `_migrated_from_id` and MERGEd on, so a re-run after an interruption never duplicates a
 * node. Every original property is copied as-is; whatever business key the node already
 * carries (chId, conceptId, uid, ...) comes along for free in `properties(n)`.
 */
class CopyLmsPalCommand extends Command
{
    protected $signature = 'neo4j:copy-lms-pal
        {--confirm     : required — acknowledges this writes to the target instance}
        {--dry-run     : print per-label SOURCE counts only, write nothing}
        {--batch=1000  : rows per page/transaction}
        {--backup-dir= : directory for a JSON copy of every node/edge before it is written to target}';

    protected $description = 'One-time copy of LMS+PAL-scoped nodes/edges from the K12 graph into the dedicated LMS+PAL Neo4j instance';

    /**
     * LMS core + the PAL coherence layer added 2026-09-28/29 + the anchor labels
     * (Student/Teacher) those edges attach to, per the approved migration plan.
     */
    private const SCOPE_LABELS = [
        // LMS core
        'Standard', 'Subject', 'Curriculum', 'Unit', 'Chapter', 'Concept',
        'Lesson', 'Assessment', 'Question', 'QuestionType',
        // PAL coherence layer (CoherenceGraphProjection, 2026-09-28/29)
        'Misconception', 'CorrectiveContent', 'ConceptNode', 'CurriculumOutcome', 'Content',
        // anchors, so HAS_MASTERY / TEACHES are not dropped
        'StuDetail', 'Teacher',
    ];

    private ClientInterface $source;
    private ?ClientInterface $target = null;

    /** @var array{uri:?string,username:?string,password:?string} */
    private array $sourceConn;
    /** @var array{uri:?string,username:?string,password:?string} */
    private array $targetConn;

    /** source id(n) => target id(n) */
    private array $idMap = [];

    public function handle(): int
    {
        if (!$this->option('confirm') && !$this->option('dry-run')) {
            $this->error('neo4j:copy-lms-pal WRITES TO THE TARGET INSTANCE. Re-run with --confirm (or --dry-run to preview).');
            return 1;
        }

        $targetUri = config('neo4j.targets.lms_pal.uri');
        $targetPass = config('neo4j.targets.lms_pal.password');
        if (!$this->option('dry-run') && (!$targetUri || !$targetPass)) {
            $this->error('NEO4J_LMSPAL_URI / NEO4J_LMSPAL_PASSWORD are not set — nothing to copy into.');
            $this->line('Fill them in once the new instance is provisioned (see the migration plan, Part A).');
            return 1;
        }

        $this->sourceConn = [
            'uri' => config('neo4j.uri'), 'username' => config('neo4j.username'), 'password' => config('neo4j.password'),
        ];
        $this->source = $this->client($this->sourceConn['uri'], $this->sourceConn['username'], $this->sourceConn['password'], 'source');

        if (!$this->option('dry-run')) {
            $this->targetConn = [
                'uri' => $targetUri, 'username' => config('neo4j.targets.lms_pal.username'), 'password' => $targetPass,
            ];
            $this->target = $this->client($this->targetConn['uri'], $this->targetConn['username'], $this->targetConn['password'], 'target');
        }

        $batch = max(1, (int) $this->option('batch'));
        $backupDir = $this->backupDir();
        if (!$this->option('dry-run') && !is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $this->info(($this->option('dry-run') ? '[DRY RUN] ' : '') . 'LMS+PAL scope: ' . implode(', ', self::SCOPE_LABELS));
        if (!$this->option('dry-run')) {
            $this->line('Backing up copied nodes/edges to: ' . $backupDir);
        }
        $this->line(str_repeat('-', 78));

        $nodeSummary = [];
        foreach (self::SCOPE_LABELS as $label) {
            $nodeSummary[$label] = $this->copyLabel($label, $batch, $backupDir);
        }

        $this->line(str_repeat('-', 78));
        $relSummary = [];
        if ($this->option('dry-run')) {
            $this->info('Dry run — skipping relationship copy (needs both endpoints already mapped in target).');
        } else {
            $relSummary = $this->copyRelationships($batch, $backupDir);
        }

        $this->line(str_repeat('-', 78));
        $mismatches = array_filter($nodeSummary, fn ($c) => $c['target'] !== null && $c['source'] !== $c['target']);
        if ($mismatches) {
            $this->error(count($mismatches) . ' label(s) have a source/target count mismatch — see above.');
            return 1;
        }
        $this->info(($this->option('dry-run') ? 'Would copy ' : 'Copied ') . count($nodeSummary) . ' label(s), '
            . count($relSummary) . ' relationship type(s).');

        return 0;
    }

    private function backupDir(): string
    {
        if ($dir = $this->option('backup-dir')) {
            return rtrim($dir, '/\\');
        }
        $base = env('NEO4J_RESCUE_DIR') ?: storage_path('app/neo4j-lmspal-backup');
        return rtrim($base, '/\\') . '/lms-pal-copy-' . date('Y-m-d-His');
    }

    /**
     * Copy every node of one label, paginated by internal id. Returns
     * ['source' => int, 'target' => int|null] (null in --dry-run).
     */
    private function copyLabel(string $label, int $batch, string $backupDir): array
    {
        $sourceCount = (int) $this->runOn('source', "MATCH (n:`$label`) RETURN count(n) AS c", [])->first()->get('c');

        if ($this->option('dry-run')) {
            $this->line(sprintf('  %-20s source=%s', $label, number_format($sourceCount)));
            return ['source' => $sourceCount, 'target' => null];
        }

        $fh = fopen("$backupDir/nodes_{$label}.jsonl", 'w');
        $skip = 0;
        $copied = 0;

        while (true) {
            $page = $this->runOn('source',
                "MATCH (n:`$label`) RETURN id(n) AS srcId, properties(n) AS props "
                . 'ORDER BY id(n) SKIP $skip LIMIT $limit',
                ['skip' => $skip, 'limit' => $batch]
            );
            if ($page->count() === 0) break;

            $rows = [];
            foreach ($page as $rec) {
                $srcId = (int) $rec->get('srcId');
                $props = $rec->get('props')->toArray();
                fwrite($fh, json_encode(['srcId' => $srcId, 'label' => $label, 'props' => $props]) . "\n");
                $rows[] = ['srcId' => $srcId, 'props' => $props];
            }

            $written = $this->runOn('target',
                "UNWIND \$rows AS row
                 MERGE (n:`$label` {_migrated_from_id: row.srcId})
                 SET n += row.props
                 RETURN row.srcId AS srcId, id(n) AS newId",
                ['rows' => $rows]
            );
            foreach ($written as $rec) {
                $this->idMap[(int) $rec->get('srcId')] = (int) $rec->get('newId');
            }

            $copied += count($rows);
            $skip += $batch;
            $this->output->write('.');
        }
        fclose($fh);
        $this->newLine();

        $targetCount = (int) $this->runOn('target', "MATCH (n:`$label`) RETURN count(n) AS c", [])->first()->get('c');
        $this->line(sprintf('  %-20s source=%s  target=%s%s', $label, number_format($sourceCount), number_format($targetCount),
            $sourceCount !== $targetCount ? '  <fg=red>MISMATCH</>' : ''));

        return ['source' => $sourceCount, 'target' => $targetCount];
    }

    /**
     * Copy every relationship whose BOTH endpoints carry a scope label, paginated by
     * the relationship's own internal id. Endpoints not present in $idMap (i.e. not
     * copied — should not happen given the WHERE clause, but guarded anyway) are
     * skipped rather than guessed at.
     *
     * @return array<string,int> relType => count copied
     */
    private function copyRelationships(int $batch, string $backupDir): array
    {
        $fh = fopen("$backupDir/relationships.jsonl", 'w');
        $skip = 0;
        $summary = [];

        while (true) {
            $page = $this->runOn('source',
                'MATCH (a)-[r]->(b)
                 WHERE any(l IN labels(a) WHERE l IN $labels) AND any(l IN labels(b) WHERE l IN $labels)
                 RETURN id(a) AS fromId, type(r) AS relType, id(b) AS toId, properties(r) AS props
                 ORDER BY id(r) SKIP $skip LIMIT $limit',
                ['labels' => self::SCOPE_LABELS, 'skip' => $skip, 'limit' => $batch]
            );
            if ($page->count() === 0) break;

            $byType = [];
            foreach ($page as $rec) {
                $fromId = (int) $rec->get('fromId');
                $toId = (int) $rec->get('toId');
                if (!isset($this->idMap[$fromId], $this->idMap[$toId])) {
                    continue; // endpoint was not copied — should not happen given the WHERE clause above
                }
                $relType = $rec->get('relType');
                $props = $rec->get('props')->toArray();
                fwrite($fh, json_encode(['fromId' => $fromId, 'type' => $relType, 'toId' => $toId, 'props' => $props]) . "\n");
                $byType[$relType][] = ['from' => $this->idMap[$fromId], 'to' => $this->idMap[$toId], 'props' => $props];
            }

            foreach ($byType as $relType => $rows) {
                $this->runOn('target',
                    "UNWIND \$rows AS row
                     MATCH (a) WHERE id(a) = row.from
                     MATCH (b) WHERE id(b) = row.to
                     MERGE (a)-[r:`$relType`]->(b)
                     SET r += row.props",
                    ['rows' => $rows]
                );
                $summary[$relType] = ($summary[$relType] ?? 0) + count($rows);
            }

            $skip += $batch;
            $this->output->write('.');
        }
        fclose($fh);
        $this->newLine();

        foreach ($summary as $relType => $n) {
            $this->line(sprintf('  -[:%-20s %s', "$relType]->", number_format($n)));
        }

        return $summary;
    }

    private function client(?string $uri, ?string $username, ?string $password, string $alias): ClientInterface
    {
        return ClientBuilder::create()
            ->withDriver($alias, $uri, Authenticate::basic((string) $username, (string) $password))
            ->build();
    }

    /**
     * Bolt drops occasionally over a long transfer (observed here: "Undefined property
     * StreamSocket::$stream" surfacing from a reset socket during disconnect()) — the same
     * failure mode `LoadCommand`/`CypherRunCommand` already guard against. Every statement
     * this command sends is either a plain MATCH (source) or an idempotent MERGE (target),
     * so replaying after reconnecting is always safe.
     */
    private function runOn(string $which, string $cypher, array $params, int $attempts = 10)
    {
        for ($i = 1; ; $i++) {
            try {
                return ($which === 'source' ? $this->source : $this->target)->run($cypher, $params);
            } catch (\Throwable $e) {
                if ($i >= $attempts) throw $e;
                $this->output->write('<fg=yellow>r</>');
                // Scales up to 6s by the later attempts — long enough to ride out a short-lived
                // rate-limit/ban on the remote side (observed: repeated resets clustered right
                // around the same point, not one-off blips), not just a network hiccup.
                usleep(1000000 * min($i, 6));
                try {
                    $conn = $which === 'source' ? $this->sourceConn : $this->targetConn;
                    $rebuilt = $this->client($conn['uri'], $conn['username'], $conn['password'], $which);
                    if ($which === 'source') { $this->source = $rebuilt; } else { $this->target = $rebuilt; }
                } catch (\Throwable $ignored) {
                    // retried again next loop
                }
            }
        }
    }
}
