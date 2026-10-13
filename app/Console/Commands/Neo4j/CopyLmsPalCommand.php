<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * One-time seed copy of the LMS+PAL-scoped slice of the K12 graph into a brand-new,
 * separate Neo4j instance dedicated to LMS+PAL work (`config('neo4j.targets.lms_pal')`).
 *
 * SOURCE (config('neo4j.uri')/'http_uri', the existing K12 graph) is READ-ONLY here —
 * every statement sent to it is a MATCH, never a write. TARGET is the only database this
 * command ever writes to.
 *
 * TRANSPORT: HTTP transaction API, not Bolt. The Bolt driver (laudis/neo4j-php-client +
 * stefanak-michal/bolt) proved unreliable for this transfer over this link — repeated
 * "Undefined property StreamSocket::$stream" / errno=10054 crashes, including runs that
 * died identically at the exact same resume point across 4 consecutive attempts with
 * ZERO successful retries out of 10. Direct comparison settled it: the identical query
 * (300 real StuDetail rows, full properties) against the identical server succeeded
 * instantly and reliably over HTTP every single time, while Bolt kept dying on it. Since
 * every diagnostic curl run throughout this migration was HTTP and never once failed,
 * this command now speaks HTTP exclusively — no persistent session to corrupt, no
 * driver-level connection-teardown bug to hit.
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
        {--backup-dir= : directory for a JSON copy of every node/edge before it is written to target}
        {--force=      : comma-separated labels to re-copy even when source/target counts already match}';

    protected $description = 'One-time copy of LMS+PAL-scoped nodes/edges from the K12 graph into the dedicated LMS+PAL Neo4j instance (HTTP transport)';

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
        // Topic (topic_master), added to live sync 2026-10-06 — see projectTopics()
        'Topic',
        // anchors, so HAS_MASTERY / TEACHES are not dropped
        'StuDetail', 'Teacher',
    ];

    /** @var array{http_uri:?string,username:?string,password:?string} */
    private array $sourceConn;
    /** @var array{http_uri:?string,username:?string,password:?string} */
    private array $targetConn;

    /** source id(n) => target id(n) */
    private array $idMap = [];

    public function handle(): int
    {
        if (!$this->option('confirm') && !$this->option('dry-run')) {
            $this->error('neo4j:copy-lms-pal WRITES TO THE TARGET INSTANCE. Re-run with --confirm (or --dry-run to preview).');
            return 1;
        }

        $targetHttp = config('neo4j.targets.lms_pal.http_uri');
        $targetPass = config('neo4j.targets.lms_pal.password');
        if (!$this->option('dry-run') && (!$targetHttp || !$targetPass)) {
            $this->error('NEO4J_LMSPAL_HTTP_URI / NEO4J_LMSPAL_PASSWORD are not set — nothing to copy into.');
            $this->line('Fill them in once the new instance is provisioned (see the migration plan, Part A).');
            return 1;
        }

        $this->sourceConn = [
            'http_uri' => config('neo4j.http_uri'), 'username' => config('neo4j.username'), 'password' => config('neo4j.password'),
        ];
        if (!$this->sourceConn['http_uri']) {
            $this->error('NEO4J_HTTP_URI is not set.');
            return 1;
        }

        if (!$this->option('dry-run')) {
            $this->targetConn = [
                'http_uri' => $targetHttp, 'username' => config('neo4j.targets.lms_pal.username'), 'password' => $targetPass,
            ];
            $this->ensureConstraints();
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

        $forced = array_filter(array_map('trim', explode(',', (string) $this->option('force'))));

        $nodeSummary = [];
        foreach (self::SCOPE_LABELS as $label) {
            $nodeSummary[$label] = $this->copyLabel($label, $batch, $backupDir, in_array($label, $forced, true));
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

    /**
     * Every MERGE in this command keys on `_migrated_from_id`, which starts out completely
     * unindexed on a fresh target — meaning every MERGE was a full label scan, growing
     * linearly slower as each label filled up. This is what actually caused the repeated
     * timeouts on the big labels (Question/Content/StuDetail) after they'd grown large
     * enough, not network flakiness — confirmed by zero indexes existing on target beyond
     * the two default LOOKUP ones. `IF NOT EXISTS` makes this safe to run on every restart.
     */
    private function ensureConstraints(): void
    {
        foreach (self::SCOPE_LABELS as $label) {
            $this->cypher('target',
                "CREATE CONSTRAINT IF NOT EXISTS ON (n:`$label`) ASSERT n._migrated_from_id IS UNIQUE"
            );
        }
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
    private function copyLabel(string $label, int $batch, string $backupDir, bool $force = false): array
    {
        $sourceCount = (int) $this->cypher('source', "MATCH (n:`$label`) RETURN count(n) AS c")[0]['c'];

        if ($this->option('dry-run')) {
            $this->line(sprintf('  %-20s source=%s', $label, number_format($sourceCount)));
            return ['source' => $sourceCount, 'target' => null];
        }

        // Every restart otherwise re-walks and re-MERGEs the WHOLE label from scratch, even one
        // already fully copied — wasting most of each retry re-doing finished work. When the
        // counts already match, skip the expensive paginated property transfer entirely and
        // just cheaply rebuild the id map (every already-copied target node already carries
        // _migrated_from_id, so no source read is even needed for this).
        //
        // --force bypasses this: a count match does NOT mean a label's properties are still
        // current — a source label whose rows were later enriched in place (new properties,
        // same row count, e.g. :Chapter after projectChapterIntelligence()) would otherwise
        // never get those new properties copied at all.
        $targetCountEarly = (int) $this->cypher('target', "MATCH (n:`$label`) RETURN count(n) AS c")[0]['c'];
        if ($sourceCount === $targetCountEarly && !$force) {
            $this->populateIdMapFromTarget($label, $batch);
            $this->line(sprintf('  %-20s source=%s  target=%s  <fg=cyan>(already matches, skipped)</>',
                $label, number_format($sourceCount), number_format($targetCountEarly)));
            return ['source' => $sourceCount, 'target' => $targetCountEarly];
        }
        if ($sourceCount === $targetCountEarly && $force) {
            $this->line(sprintf('  %-20s <fg=yellow>forced re-copy (counts already matched)</>', $label));
        }

        // Resume mid-label too, not just whole-label skip above — otherwise every restart of a
        // label that's PARTIALLY done redoes all its prior pages from SKIP 0. Pages are always
        // written in strict ascending id(n) order and a page either fully succeeds or the whole
        // command aborts, so "targetCountEarly rows already in target" reliably means "the first
        // targetCountEarly source rows by id(n) are already done" — safe to resume right there.
        // A forced re-copy always starts at 0 instead: the whole point is to re-send every row's
        // CURRENT properties, not just whatever was missing last time.
        $skipFrom = $force ? 0 : $targetCountEarly;
        $fh = fopen("$backupDir/nodes_{$label}.jsonl", $skipFrom > 0 ? 'a' : 'w');
        if ($skipFrom > 0) {
            $this->populateIdMapFromTarget($label, $batch);
            $this->line(sprintf('  %-20s <fg=cyan>resuming at %s of %s</>',
                $label, number_format($targetCountEarly), number_format($sourceCount)));
        }
        $skip = $skipFrom;
        $copied = 0;

        while (true) {
            $page = $this->cypher('source',
                "MATCH (n:`$label`) RETURN id(n) AS srcId, properties(n) AS props "
                . 'ORDER BY id(n) SKIP $skip LIMIT $limit',
                ['skip' => $skip, 'limit' => $batch]
            );
            if (count($page) === 0) break;

            $rows = [];
            foreach ($page as $rec) {
                $srcId = (int) $rec['srcId'];
                $props = $rec['props'];
                fwrite($fh, json_encode(['srcId' => $srcId, 'label' => $label, 'props' => $props]) . "\n");
                $rows[] = ['srcId' => $srcId, 'props' => $props];
            }

            $written = $this->cypher('target',
                "UNWIND \$rows AS row
                 MERGE (n:`$label` {_migrated_from_id: row.srcId})
                 SET n += row.props
                 RETURN row.srcId AS srcId, id(n) AS newId",
                ['rows' => $rows]
            );
            foreach ($written as $rec) {
                $this->idMap[(int) $rec['srcId']] = (int) $rec['newId'];
            }

            $copied += count($rows);
            $skip += $batch;
            $this->output->write('.');
        }
        fclose($fh);
        $this->newLine();

        $targetCount = (int) $this->cypher('target', "MATCH (n:`$label`) RETURN count(n) AS c")[0]['c'];
        $this->line(sprintf('  %-20s source=%s  target=%s%s', $label, number_format($sourceCount), number_format($targetCount),
            $sourceCount !== $targetCount ? '  <fg=red>MISMATCH</>' : ''));

        return ['source' => $sourceCount, 'target' => $targetCount];
    }

    /** Rebuild idMap entries for a label already fully present in target, with no source read. */
    private function populateIdMapFromTarget(string $label, int $batch): void
    {
        $skip = 0;
        while (true) {
            $page = $this->cypher('target',
                "MATCH (n:`$label`) WHERE n._migrated_from_id IS NOT NULL "
                . 'RETURN n._migrated_from_id AS srcId, id(n) AS newId ORDER BY id(n) SKIP $skip LIMIT $limit',
                ['skip' => $skip, 'limit' => $batch]
            );
            if (count($page) === 0) break;
            foreach ($page as $rec) {
                $this->idMap[(int) $rec['srcId']] = (int) $rec['newId'];
            }
            $skip += $batch;
        }
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
            $page = $this->cypher('source',
                'MATCH (a)-[r]->(b)
                 WHERE any(l IN labels(a) WHERE l IN $labels) AND any(l IN labels(b) WHERE l IN $labels)
                 RETURN id(a) AS fromId, type(r) AS relType, id(b) AS toId, properties(r) AS props
                 ORDER BY id(r) SKIP $skip LIMIT $limit',
                ['labels' => self::SCOPE_LABELS, 'skip' => $skip, 'limit' => $batch]
            );
            if (count($page) === 0) break;

            $byType = [];
            foreach ($page as $rec) {
                $fromId = (int) $rec['fromId'];
                $toId = (int) $rec['toId'];
                if (!isset($this->idMap[$fromId], $this->idMap[$toId])) {
                    continue; // endpoint was not copied — should not happen given the WHERE clause above
                }
                $relType = $rec['relType'];
                $props = $rec['props'];
                fwrite($fh, json_encode(['fromId' => $fromId, 'type' => $relType, 'toId' => $toId, 'props' => $props]) . "\n");
                $byType[$relType][] = ['from' => $this->idMap[$fromId], 'to' => $this->idMap[$toId], 'props' => $props];
            }

            foreach ($byType as $relType => $rows) {
                $this->cypher('target',
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

    /**
     * PHP cannot distinguish an empty map from an empty list — both are just `[]` — so an
     * empty `properties(r)` (any structural relationship with no properties, e.g. BELONGS_TO)
     * round-trips through json_encode as `[]` and Neo4j rejects it: "Expected row.props to be
     * a map, but it was List{}". Recursively force every empty array to an object so it
     * serializes as `{}`; non-empty arrays keep list-vs-map exactly as PHP already has them.
     */
    private function jsonSafe(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if ($value === []) return (object) [];
        $out = [];
        foreach ($value as $k => $v) { $out[$k] = $this->jsonSafe($v); }
        return array_is_list($value) ? $out : (object) $out;
    }

    /**
     * Run one Cypher statement over the HTTP transaction API (commit-immediately, single
     * statement) and return its rows as plain associative arrays (column => value).
     *
     * Retries a handful of times on a transport-level failure (timeout, connection reset) —
     * observed far less often over HTTP than Bolt ever managed here, but cheap insurance.
     * A Neo4j-level error (bad Cypher, constraint violation) is NOT retried — it throws
     * immediately, since retrying an identical bad statement can't succeed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cypher(string $which, string $statement, array $params = [], int $attempts = 5): array
    {
        $conn = $which === 'source' ? $this->sourceConn : $this->targetConn;
        $url = rtrim($conn['http_uri'], '/') . '/db/neo4j/tx/commit';

        for ($i = 1; ; $i++) {
            try {
                $response = Http::withBasicAuth($conn['username'], $conn['password'])
                    ->timeout(60)
                    ->asJson()
                    ->post($url, [
                        'statements' => [[
                            'statement' => $statement,
                            'parameters' => $this->jsonSafe($params),
                        ]],
                    ]);

                if ($response->failed()) {
                    throw new \RuntimeException("HTTP {$response->status()} from $which: " . substr($response->body(), 0, 300));
                }

                $json = $response->json();
                $errors = $json['errors'] ?? [];
                if ($errors) {
                    // A Cypher-level error — retrying the exact same statement would just fail
                    // the same way, so this is the one case runOn-style retrying never helped.
                    throw new \RuntimeException('Neo4j error from ' . $which . ': ' . json_encode($errors));
                }

                $result = $json['results'][0] ?? ['columns' => [], 'data' => []];
                $columns = $result['columns'];
                $rows = [];
                foreach ($result['data'] as $entry) {
                    $rows[] = array_combine($columns, $entry['row']);
                }

                if ($i > 1) { $this->output->write("<fg=green>OK({$i})</> "); }
                return $rows;
            } catch (\RuntimeException $e) {
                // Cypher-level error (not a transport failure) — fail immediately, don't retry.
                if (str_starts_with($e->getMessage(), 'Neo4j error')) throw $e;
                if ($i >= $attempts) throw $e;
                $this->output->write("<fg=yellow>r{$i}</> ");
                usleep(500000 * min($i, 4));
            } catch (\Throwable $e) {
                if ($i >= $attempts) throw $e;
                $this->output->write("<fg=yellow>r{$i}</> ");
                usleep(500000 * min($i, 4));
            }
        }
    }
}
