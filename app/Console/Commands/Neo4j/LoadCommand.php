<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

/**
 * Loads exported CSVs into Neo4j.
 *
 * Every write is a MERGE on uid (PROJECTION LAW L1), so an interrupted load is safe
 * to re-run — already-loaded rows match, missing ones are created. CREATE is never
 * used: it would duplicate on a re-run.
 *
 * Refuses to run without --confirm, because loading before Phase 3's wipe seeds nodes
 * under the old key convention (defect D2).
 */
class LoadCommand extends Command
{
    protected $signature = 'neo4j:load
        {--module=          : module key to load}
        {--table=           : a single table instead of a whole module}
        {--batch=1000       : rows per transaction}
        {--confirm          : required — acknowledges this writes to Neo4j}
        {--prune            : DELETE nodes the current export no longer produces}
        {--dry-run          : print the Cypher for the first row and stop}';
    protected $description = 'Load exported CSVs into Neo4j via MERGE on uid (idempotent)';

    public function handle(): int
    {
        if (!$this->option('confirm') && !$this->option('dry-run')) {
            $this->error('neo4j:load WRITES TO NEO4J. Re-run with --confirm (or --dry-run to preview).');
            $this->line('Do not run this before Phase 3 (wipe & schema reset) has completed.');
            return 1;
        }

        $registry = config('neo4j_graph');
        $all = $registry['tables'] ?? [];
        $module = $this->option('module');
        $single = $this->option('table');

        if ($single) {
            if (!isset($all[$single])) { $this->error("`$single` is not in the registry."); return 1; }
            $tables = [$single => $all[$single]];
            $module = $all[$single]['module'] ?? 'adhoc';
        } elseif ($module) {
            $tables = array_filter($all, fn ($e) => ($e['module'] ?? null) === $module
                && in_array($e['decision'] ?? '', ['NODE', 'EDGE', 'AGG_EDGE'], true));
        } else {
            $this->error('Pass --module= or --table=.');
            return 1;
        }
        // nodes before edges — an edge cannot MERGE onto a node that does not exist yet
        uasort($tables, fn ($a, $b) => [($a['decision'] === 'NODE' ? 0 : 1), $a['phase'] ?? 99]
            <=> [($b['decision'] === 'NODE' ? 0 : 1), $b['phase'] ?? 99]);

        $dir = storage_path("app/neo4j/$module");
        if (!is_dir($dir)) { $this->error("No export folder: $dir — run neo4j:export --module=$module first."); return 1; }

        $client = $this->option('dry-run') ? null : $this->client();
        $batch = max(1, (int) $this->option('batch'));

        $this->info(($this->option('dry-run') ? '[DRY RUN] ' : '') . 'Loading module ' . $module);
        $this->line(str_repeat('─', 78));

        $total = 0;
        foreach ($tables as $t => $e) {
            $file = "$dir/$t.csv";
            if (!is_readable($file)) { $this->line(sprintf('  %-40s <fg=yellow>no CSV, skipped</>', $t)); continue; }
            if (($e['decision'] ?? '') === 'NODE') {
                $total += $this->loadNodes($client, $t, $e, $file, $batch);
                continue;
            }
            if (!empty($e['needs_endpoint_keys'])) {
                $this->line(sprintf('  %-40s <fg=yellow>skipped — endpoint keys unresolved (phase %s)</>',
                    $t, $e['phase'] ?? '?'));
                continue;
            }
            if (($e['decision'] ?? '') === 'AGG_EDGE' && empty($e['aggregate']['group_by'])) {
                $this->line(sprintf('  %-40s <fg=yellow>skipped — AGG_EDGE has no GROUP BY defined (L4)</>', $t));
                continue;
            }
            $total += $this->loadEdges($client, $t, $e, $file, $batch);
        }

        if (!$this->option('dry-run')) {
            $total += $this->loadHierarchy($dir);
            $total += $this->loadScoped($dir);
            $total += $this->loadRescueHierarchy();
            if ($this->option('prune')) $this->pruneStale($dir, $tables);
        }

        $this->line(str_repeat('─', 78));
        $this->info(($this->option('dry-run') ? 'Would load ' : 'Loaded ') . number_format($total) . ' item(s).');
        return 0;
    }

    /**
     * Hierarchy edges come from FK columns on entity tables, not junction tables — the
     * Phase 1 classification missed these entirely and Phase 4's G8 gate caught it.
     * Only runs for entries whose child CSV is present in this module's export.
     */
    private function loadHierarchy(string $dir): int
    {
        $spec = config('neo4j_graph.hierarchy', []);
        if (!$spec) return 0;
        $made = 0;
        foreach ($spec as $h) {
            $file = "$dir/{$h['table']}.csv";
            if (!is_readable($file)) continue;
            if (!empty($h['by_id'])) continue;          // handled by loadScoped()

            $childEntry = config("neo4j_graph.tables.{$h['table']}");
            $childPk = $childEntry['pk'] ?? 'id';

            $fh = fopen($file, 'r');
            $header = fgetcsv($fh, 0, ',', '"', '');
            $idx = array_flip($header ?: []);
            if (!isset($idx[$h['fk']], $idx[$childPk])) { fclose($fh); continue; }

            $rows = []; $skipped = 0; $n = 0;
            while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
                $parentId = trim((string) ($r[$idx[$h['fk']]] ?? ''));
                if ($parentId === '' || $parentId === '0') { $skipped++; continue; }
                $tenant = (int) ($r[$idx['_tenant']] ?? 0);
                $syear  = (int) ($r[$idx['_syear']] ?? 0);
                // :Institute is keyed on the tenant itself (uid Institute:{id}:0:{id}), so
                // when the fk IS sub_institute_id the parent's tenant is that value. For any
                // other parent the tenant is the child row's own tenant.
                $parentTenant = ($h['fk'] === 'sub_institute_id') ? (int) $parentId : $tenant;
                $rows[] = [
                    'p' => $this->uid($h['parent'], $parentTenant, $syear, $parentId),
                    'c' => $this->uid($h['child'], $tenant, $syear, $r[$idx[$childPk]]),
                ];
                $n++;
            }
            fclose($fh);
            if (!$rows) continue;

            $cypher = "UNWIND \$rows AS row
                       MATCH (p:`{$h['parent']}` {uid: row.p})
                       MATCH (c:`{$h['child']}`  {uid: row.c})
                       MERGE (p)-[:`{$h['rel']}`]->(c)";
            foreach (array_chunk($rows, 400) as $chunk) $this->cypher($cypher, ['rows' => $chunk]);

            $count = (int) $this->cypher("MATCH (:`{$h['parent']}`)-[r:`{$h['rel']}`]->() RETURN count(r) AS c", [])
                ->first()->get('c');
            $lost = $n - $count;
            $this->line(sprintf('  %-40s %9s -[:%s]->%s%s', $h['table'] . ' (fk)', number_format($count), $h['rel'],
                $skipped ? "  <fg=yellow>{$skipped} null fk</>" : '',
                $lost > 0 ? "  <fg=yellow>{$lost} parent missing</>" : ''));
            $made += $count;
        }
        return $made;
    }

    /**
     * Parent edges for rescue-seeded nodes.
     *
     * loadHierarchy() is driven by the child table's exported CSV. A rescue-seeded node has
     * no MariaDB row and therefore no CSV line, so it never gets a parent — all 5,532
     * :Chapter nodes came from the rescue CSV, which is how curriculum ended up with 41,277
     * edges pointing OUT of :Chapter and exactly 0 pointing in, leaving the whole module
     * detached from Foundation. The rescue CSV did carry the FK columns, so build the edges
     * from the node properties instead. uid() is reused verbatim so the key convention
     * cannot drift from the CSV path.
     */
    private function loadRescueHierarchy(): int
    {
        $rescue = array_keys(config('neo4j_graph.rescue.files', []));
        if (!$rescue) return 0;

        $made = 0;
        foreach (config('neo4j_graph.hierarchy', []) as $h) {
            if (!in_array($h['child'], $rescue, true)) continue;
            if ($h['parent'] === $h['child']) continue;          // self-referencing tree

            // Match the rescue TAG, not merely "source is set" — several tables carry their
            // own `source` column (content_master has 31,362 rows of it).
            $res = $this->cypher(
                "MATCH (c:`{$h['child']}`)
                 WHERE c.source IN \$tags AND c.`{$h['fk']}` IS NOT NULL
                   AND toInteger(c.`{$h['fk']}`) > 0
                 RETURN c.uid AS uid, toString(toInteger(c.`{$h['fk']}`)) AS fk",
                ['tags' => array_values(array_filter(array_map(
                    fn ($f) => $f['source'] ?? null, config('neo4j_graph.rescue.files', []))))]);

            $rows = []; $n = 0;
            foreach ($res as $r) {
                $uid = (string) $r->get('uid');
                $fk  = (string) $r->get('fk');
                // uid is "<Label>:<tenant>:<syear>:<pk>" — taking tenant/syear from the key
                // itself avoids depending on which columns the rescue CSV happened to carry.
                $p = explode(':', $uid, 4);
                if (count($p) !== 4) continue;
                $tenant = (int) $p[1];
                $syear  = (int) $p[2];
                $parentTenant = ($h['fk'] === 'sub_institute_id') ? (int) $fk : $tenant;
                $rows[] = ['p' => $this->uid($h['parent'], $parentTenant, $syear, $fk), 'c' => $uid];
                $n++;
            }
            if (!$rows) continue;

            $rel = $h['rel'];
            $before = (int) $this->cypher(
                "MATCH (:`{$h['parent']}`)-[r:`$rel`]->(:`{$h['child']}`) RETURN count(r) AS c", [])->first()->get('c');

            $cypher = "UNWIND \$rows AS row
                       MATCH (p:`{$h['parent']}` {uid: row.p})
                       MATCH (c:`{$h['child']}`  {uid: row.c})
                       MERGE (p)-[:`$rel`]->(c)";
            foreach (array_chunk($rows, 400) as $chunk) $this->cypher($cypher, ['rows' => $chunk]);

            $after = (int) $this->cypher(
                "MATCH (:`{$h['parent']}`)-[r:`$rel`]->(:`{$h['child']}`) RETURN count(r) AS c", [])->first()->get('c');

            // "missing" must be measured, not inferred from (n - created). This pass is
            // idempotent and not module-scoped, so on a re-run every edge already exists and
            // created is 0 — reporting that as "5501 parent missing" said the load had failed
            // when it had in fact succeeded on an earlier invocation.
            $missing = 0;
            foreach (array_chunk($rows, 400) as $chunk) {
                $missing += (int) $this->cypher(
                    "UNWIND \$rows AS row
                     OPTIONAL MATCH (p:`{$h['parent']}` {uid: row.p})
                     WITH row WHERE p IS NULL
                     RETURN count(*) AS c", ['rows' => $chunk])->first()->get('c');
            }
            $created = $after - $before;
            $this->line(sprintf('  %-40s %9s -[:%s]->  %s linked%s  <fg=cyan>(rescue)</>',
                $h['child'] . '.' . $h['fk'], number_format($created), $rel,
                number_format($n - $missing),
                $missing > 0 ? "  <fg=yellow>{$missing} parent missing</>" : ''));
            $made += $created;
        }
        return $made;
    }

    /**
     * Hierarchy edges whose parent is addressed BY ID rather than by a uid built from the
     * child's tenant — MAPPINGTYPE-SCOPE (approved 2026-08-12).
     *
     * `lms_mapping_type` is tenant-GLOBAL; :Chapter and :Topic are tenant-scoped. So the
     * standard path in loadHierarchy(), which builds the parent uid as
     * "<Parent>:<child tenant>:<syear>:<fk>", would look up "Chapter:0:0:44" and match
     * nothing. The parent's tenant has to be discovered from the parent itself.
     *
     * Resolution is on the uid TAIL, not on the `id` property: the 5,519 rescue-seeded
     * :Chapter carry `chId`, not `id`, so an id-keyed match would silently miss exactly the
     * set CHAPTER-SOURCE exists to provide. The tail is verified unique per label (0
     * collisions across 5,625 :Chapter and 13,561 :Topic), so a single pk resolves to at
     * most one node and this cannot fan out the way HAS_RESULT did in D10 — the guard
     * below drops any pk that resolves to more than one node rather than trusting that.
     */
    private function loadScoped(string $dir): int
    {
        $made = 0;
        foreach (config('neo4j_graph.hierarchy', []) as $h) {
            if (empty($h['by_id'])) continue;
            $file = "$dir/{$h['table']}.csv";
            if (!is_readable($file)) continue;

            // pk tail -> uid, for every node of the parent label
            $map = []; $ambiguous = [];
            foreach ($this->cypher("MATCH (n:`{$h['parent']}`) RETURN n.uid AS uid", []) as $r) {
                $uid = (string) $r->get('uid');
                $p = explode(':', $uid, 4);
                if (count($p) !== 4) continue;
                $pk = $p[3];
                if (isset($map[$pk])) { $ambiguous[$pk] = true; continue; }
                $map[$pk] = $uid;
            }

            $childEntry = config("neo4j_graph.tables.{$h['table']}", []);
            $childPk = $childEntry['pk'] ?? 'id';
            $fh = fopen($file, 'r');
            $header = fgetcsv($fh, 0, ',', '"', '');
            $idx = array_flip($header ?: []);
            if (!isset($idx[$h['fk']], $idx[$childPk])) { fclose($fh); continue; }

            $cols = count($header);
            $rows = []; $nullFk = 0; $missing = 0; $skipped = 0;
            while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
                if (count($r) !== $cols) continue;
                $fk = trim((string) ($r[$idx[$h['fk']]] ?? ''));
                if ($fk === '' || $fk === '0') { $nullFk++; continue; }
                $fk = (string) (int) $fk;
                if (isset($ambiguous[$fk])) { $skipped++; continue; }   // never fan out
                if (!isset($map[$fk]))      { $missing++; continue; }   // G10 reports it
                $rows[] = [
                    'p' => $map[$fk],
                    'c' => $this->uid($h['child'], (int) ($r[$idx['_tenant']] ?? 0),
                                      (int) ($r[$idx['_syear']] ?? 0), $r[$idx[$childPk]]),
                ];
            }
            fclose($fh);
            if (!$rows) continue;

            $cypher = "UNWIND \$rows AS row
                       MATCH (c:`{$h['child']}`)  WHERE c.uid = row.c
                       MATCH (p:`{$h['parent']}`) WHERE p.uid = row.p
                       MERGE (c)-[:`{$h['rel']}`]->(p)";
            foreach (array_chunk($rows, 400) as $chunk) $this->cypher($cypher, ['rows' => $chunk]);

            $this->line(sprintf('  %-40s %9s -[:%s]->%s%s%s%s',
                $h['table'] . '.' . $h['fk'], number_format(count($rows)), $h['rel'], $h['parent'],
                $nullFk  ? "  <fg=yellow>{$nullFk} null fk</>" : '',
                $missing ? "  <fg=yellow>{$missing} parent missing</>" : '',
                $skipped ? "  <fg=red>{$skipped} ambiguous pk SKIPPED</>" : ''));
            $made += count($rows);
        }
        return $made;
    }

    /**
     * Delete nodes that the CURRENT export no longer produces.
     *
     * The loader is MERGE-only, which makes it idempotent for inserts but gives it no way
     * to ever remove anything. Two things therefore accumulate silently:
     *
     *   1. rows DELETED from MariaDB after a load (document_extractions 150 and 152), and
     *   2. rows an EARLIER, looser export filter admitted and the current one does not.
     *
     * Both show up as G9 count drift that re-running export+load can never clear, because
     * nothing in the pipeline deletes. Observed: 13 stale :Extraction nodes surviving the
     * fix to the cross-tenant join, against a load that was otherwise exactly correct.
     *
     * Safety rules, in order:
     *   - opt-in only (--prune); this is the one part of the loader that destroys data
     *   - a label is pruned ONLY if every table feeding it is in this module and exported.
     *     17 labels are fed by more than one table (`cast` + `caste` -> :Caste); pruning
     *     from a partial view of a shared label would delete the other table's nodes.
     *   - rescue-seeded nodes are never pruned — they have no MariaDB row by definition,
     *     which is the entire point of CHAPTER-SOURCE.
     */
    private function pruneStale(string $dir, array $tables): void
    {
        $all = config('neo4j_graph.tables', []);
        $rescueTags = array_values(array_filter(array_map(
            fn ($f) => $f['source'] ?? null, config('neo4j_graph.rescue.files', []))));

        // label => every table in the WHOLE registry that feeds it
        $feeders = [];
        foreach ($all as $name => $e) {
            if (($e['decision'] ?? '') === 'NODE' && !empty($e['label'])) $feeders[$e['label']][] = $name;
        }

        $labels = [];
        foreach ($tables as $t => $e) {
            if (($e['decision'] ?? '') === 'NODE' && !empty($e['label'])) $labels[$e['label']] = true;
        }

        foreach (array_keys($labels) as $label) {
            $expected = [];
            $complete = true;
            foreach ($feeders[$label] ?? [] as $t) {
                $file = "$dir/$t.csv";
                if (!isset($tables[$t]) || !is_readable($file)) { $complete = false; break; }
                foreach ($this->uidsInCsv($file, $all[$t] ?? [], $label) as $u) $expected[$u] = true;
            }
            if (!$complete) {
                $this->line(sprintf('  %-40s <fg=yellow>prune skipped — :%s is also fed by a table outside this export</>',
                    'prune', $label));
                continue;
            }

            $stale = [];
            $res = $this->cypher(
                "MATCH (n:`$label`) WHERE NOT coalesce(n.source,'') IN \$tags RETURN n.uid AS uid",
                ['tags' => $rescueTags]);
            foreach ($res as $r) {
                $u = (string) $r->get('uid');
                if (!isset($expected[$u])) $stale[] = $u;
            }
            if (!$stale) continue;

            foreach (array_chunk($stale, 400) as $chunk) {
                $this->cypher('UNWIND $uids AS u MATCH (n:`' . $label . '` {uid: u}) DETACH DELETE n',
                    ['uids' => $chunk]);
            }
            $this->line(sprintf('  %-40s %9s :%s <fg=red>PRUNED (no longer in the export)</>',
                'prune', number_format(count($stale)), $label));
        }
    }

    /** The uids a CSV would produce — same formula as loadNodes, ragged rows skipped alike. */
    private function uidsInCsv(string $file, array $e, string $label): array
    {
        $pk = $e['pk'] ?? 'id';
        $fh = fopen($file, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '');
        if (!$header) { fclose($fh); return []; }
        $idx = array_flip($header);
        if (!isset($idx[$pk])) { fclose($fh); return []; }
        $cols = count($header);
        $out = [];
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if (count($r) !== $cols) continue;
            $out[] = sprintf('%s:%s:%s:%s', $label,
                (int) ($r[$idx['_tenant']] ?? 0), (int) ($r[$idx['_syear']] ?? 0), $r[$idx[$pk]]);
        }
        fclose($fh);
        return $out;
    }

    /** Rebuilt on demand by run(), so a dropped socket does not kill a long load. */
    private $conn = null;

    private function client()
    {
        return $this->conn = ClientBuilder::create()
            ->withDriver('x', config('neo4j.uri'),
                Authenticate::basic(config('neo4j.username'), config('neo4j.password')))
            ->build();
    }

    /**
     * Bolt over a long load drops occasionally (observed: errno 10054, "connection
     * forcibly closed"). Every write is an idempotent MERGE, so replaying a batch after
     * reconnecting is always safe — that is exactly the property the runbook relies on.
     */
    private function cypher(string $cypher, array $params, int $attempts = 4)
    {
        for ($i = 1; ; $i++) {
            try {
                return $this->conn->run($cypher, $params);
            } catch (\Throwable $e) {
                if ($i >= $attempts) throw $e;
                $this->output->write("<fg=yellow>r</>");   // reconnecting
                usleep(400000 * $i);
                try { $this->client(); } catch (\Throwable $ignored) { /* retried next loop */ }
            }
        }
    }

    private function loadNodes($client, string $t, array $e, string $file, int $batch): int
    {
        $label = $e['label'];
        $pk = $e['pk'];
        // L1: uid is the ONLY MERGE key. L5: cast at the boundary.
        $cypher = "UNWIND \$rows AS row
                   MERGE (n:`$label` {uid: row.uid})
                   SET n += row.props,
                       n.sub_institute_id = toInteger(row.tenant),
                       n.syear = toInteger(row.syear)
                   // A rescue-seeded node that a MariaDB row now lands on is no longer
                   // rescued — it is sourced. 13 of chapter_master's 87 uids already exist
                   // as rescue nodes; leaving the marker on them makes G9 (which counts
                   // `source IS NULL`) under-count and report a drift that is not real.
                   FOREACH (_ IN CASE WHEN n.source IN \$rescueTags THEN [1] ELSE [] END |
                            REMOVE n.source)";
        $rescueTags = array_values(array_filter(array_map(
            fn ($f) => $f['source'] ?? null, config('neo4j_graph.rescue.files', []))));

        $fh = fopen($file, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '');
        if (!$header) { fclose($fh); return 0; }
        $idx = array_flip($header);
        if (!isset($idx[$pk])) {
            fclose($fh);
            $this->line(sprintf('  %-40s <fg=red>pk `%s` not in CSV</>', $t, $pk));
            return 0;
        }

        $cols = count($header);
        $rows = []; $n = 0; $ragged = 0;
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            // A row whose field count does not match the header has shifted columns: the
            // pk, tenant and syear all read the wrong values and produce a corrupt uid
            // (observed: `Lesson:0:0:tag"": ""CBSE-4`). Never guess at a misaligned row —
            // skip it and report the count.
            if (count($r) !== $cols) { $ragged++; continue; }
            $tenant = $r[$idx['_tenant']] ?? 0;
            $syear  = $r[$idx['_syear']] ?? 0;
            $props = [];
            foreach ($header as $i => $col) {
                if ($col === '_tenant' || $col === '_syear') continue;
                $props[$col] = $r[$i] ?? null;
            }
            if (($e['tenant']['mode'] ?? '') === 'global') $props['scope'] = 'global';
            if (($e['authoritative'] ?? null) === false)   $props['authoritative'] = false;
            if (!empty($e['pk_is_natural']))               $props['pk_is_natural'] = true;

            $rows[] = [
                'uid'    => sprintf('%s:%s:%s:%s', $label, (int) $tenant, (int) $syear, $r[$idx[$pk]]),
                'tenant' => (int) $tenant,
                'syear'  => (int) $syear,
                'props'  => $props,
            ];
            $n++;

            if ($this->option('dry-run')) {
                $this->line("  $t:");
                $this->line('    ' . preg_replace('/\s+/', ' ', $cypher));
                $this->line('    first uid: ' . $rows[0]['uid']);
                fclose($fh);
                return $n;
            }
            if (count($rows) >= $batch) { $this->cypher($cypher, ["rows" => $rows, "rescueTags" => $rescueTags]); $rows = []; $this->output->write('.'); }
        }
        if ($rows && !$this->option('dry-run')) $this->cypher($cypher, ["rows" => $rows, "rescueTags" => $rescueTags]);
        fclose($fh);

        $this->line(sprintf('  %-40s %9s :%s%s', $t, number_format($n), $label,
            $ragged ? "  <fg=red>{$ragged} ragged row(s) SKIPPED</>" : ''));
        return $n;
    }

    /**
     * Edges MATCH both endpoints by uid and MERGE the relationship — never MERGE the
     * endpoints into existence. A row whose endpoint is missing is skipped and counted,
     * which is how defect D9 (7,674 questions pointing at chapters that were never
     * created) is prevented rather than silently recreated.
     *
     * MATCH on uid only, never on a bare FK: matching Student on a non-unique student_id
     * is what produced the HAS_RESULT fan-out (D10, 75% of results attached to >1 student).
     */
    private function loadEdges($client, string $t, array $e, string $file, int $batch): int
    {
        $rel = $e['rel'];
        $fromLabel = $e['from']['label']; $fromKey = $e['from']['key'];
        $toLabel   = $e['to']['label'];   $toKey   = $e['to']['key'];

        $cypher = "UNWIND \$rows AS row
                   MATCH (a:`$fromLabel` {uid: row.from_uid})
                   MATCH (b:`$toLabel`   {uid: row.to_uid})
                   MERGE (a)-[r:`$rel`]->(b)
                   SET r += row.props";

        $fh = fopen($file, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '');
        if (!$header) { fclose($fh); return 0; }
        $idx = array_flip($header);
        foreach ([$fromKey, $toKey] as $k) {
            if (!isset($idx[$k])) {
                fclose($fh);
                $this->line(sprintf('  %-40s <fg=red>endpoint key `%s` not in CSV</>', $t, $k));
                return 0;
            }
        }

        $rows = []; $n = 0; $skipped = 0;
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $tenant = (int) ($r[$idx['_tenant']] ?? 0);
            $syear  = (int) ($r[$idx['_syear']] ?? 0);
            $fromId = $r[$idx[$fromKey]] ?? '';
            $toId   = $r[$idx[$toKey]] ?? '';
            if ($fromId === '' || $toId === '' || $fromId === '0' || $toId === '0') { $skipped++; continue; }

            $props = ['syear' => $syear, 'sub_institute_id' => $tenant];
            if (($e['authoritative'] ?? null) === false) $props['authoritative'] = false;

            $rows[] = [
                'from_uid' => $this->uid($fromLabel, $tenant, $syear, $fromId),
                'to_uid'   => $this->uid($toLabel,   $tenant, $syear, $toId),
                'props'    => $props,
            ];
            $n++;

            if ($this->option('dry-run')) {
                $this->line("  $t:");
                $this->line('    ' . preg_replace('/\s+/', ' ', $cypher));
                $this->line('    from: ' . $rows[0]['from_uid'] . '  ->  to: ' . $rows[0]['to_uid']);
                fclose($fh);
                return $n;
            }
            if (count($rows) >= $batch) { $this->cypher($cypher, ['rows' => $rows]); $rows = []; $this->output->write('.'); }
        }
        if ($rows && !$this->option('dry-run')) $this->cypher($cypher, ['rows' => $rows]);
        fclose($fh);

        // How many rows actually became edges? A gap means an endpoint did not exist.
        $made = 0;
        if (!$this->option('dry-run')) {
            $made = (int) $this->cypher("MATCH ()-[r:`$rel`]->() RETURN count(r) AS c", [])->first()->get('c');
        }
        $lost = $n - $made;
        $this->line(sprintf('  %-40s %9s -[:%s]->%s%s', $t, number_format($made), $rel,
            $skipped ? "  <fg=yellow>{$skipped} null fk</>" : '',
            $lost > 0 ? "  <fg=yellow>{$lost} endpoint missing</>" : ''));
        return $made;
    }

    /**
     * Build a target node's uid the same way its own loader did, or the MATCH silently
     * finds nothing and the edge is dropped.
     *
     * Two overrides matter:
     *   - not year-scoped  -> syear segment is 0 regardless of the source row's year
     *   - global scope     -> tenant segment is 0; shared reference data (e.g. the
     *                         :MappingType taxonomy) is not stored per tenant
     */
    private function uid(string $label, int $tenant, int $syear, string $pk): string
    {
        foreach (config('neo4j_graph.tables', []) as $e) {
            if (($e['decision'] ?? '') === 'NODE' && ($e['label'] ?? '') === $label) {
                $sy = (($e['syear']['mode'] ?? '') === 'constant') ? (int) ($e['syear']['value'] ?? 0) : $syear;
                $tn = (($e['tenant']['mode'] ?? '') === 'global') ? 0 : $tenant;
                return sprintf('%s:%d:%d:%s', $label, $tn, $sy, $pk);
            }
        }
        return sprintf('%s:%d:%d:%s', $label, $tenant, $syear, $pk);
    }
}
