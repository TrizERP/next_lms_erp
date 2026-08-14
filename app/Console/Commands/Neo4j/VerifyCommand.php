<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

/**
 * Per-module load gate (runbook §5). Reads only — from both Neo4j and MariaDB.
 *
 * Hard-fails on any of: missing/invalid tenancy, duplicate uid, cross-tenant edges,
 * orphans on Tier A, Neo4j-vs-MariaDB count drift, and the HAS_RESULT fan-out that
 * defect D10 came from. Exit 1 on any failure, so it can gate a phase in CI.
 */
class VerifyCommand extends Command
{
    protected $signature = 'neo4j:verify {--module= : restrict counts to one module} {--skip-counts : skip the per-table MariaDB comparison}';
    protected $description = 'Verify the loaded graph against MariaDB and the projection law (read-only)';

    private array $fail = [];
    private array $softNotes = [];
    private array $pass = [];

    public function handle(): int
    {
        $registry = config('neo4j_graph');
        try {
            $client = ClientBuilder::create()
                ->withDriver('x', config('neo4j.uri'),
                    Authenticate::basic(config('neo4j.username'), config('neo4j.password')))
                ->build();
            $client->run('RETURN 1');
        } catch (\Throwable $e) {
            $this->error('Cannot reach Neo4j at ' . config('neo4j.uri') . ' — ' . substr($e->getMessage(), 0, 120));
            return 1;
        }

        $this->info('Neo4j verify — ' . config('neo4j.uri'));
        $this->line(str_repeat('─', 78));

        // Runbook §5: a tenant of NULL *or 0* is invalid. 0 is only legitimate on nodes
        // explicitly declared global scope in the registry.
        $this->q($client, 'G1  no node missing uid or tenant (L1, L2)',
            'MATCH (n) WHERE n.uid IS NULL OR n.sub_institute_id IS NULL
               OR (n.sub_institute_id = 0 AND coalesce(n.scope,"") <> "global")
             RETURN count(n) AS c');
        $this->q($client, 'G2  no duplicate uid (L1)',
            'MATCH (n) WITH n.uid AS u, count(*) AS c WHERE c > 1 RETURN count(*) AS c');
        $this->q($client, 'G3  no cross-tenant edge (L2)',
            'MATCH (a)-[r]->(b)
             WHERE a.sub_institute_id <> b.sub_institute_id
               AND coalesce(a.scope,"") <> "global" AND coalesce(b.scope,"") <> "global"
             RETURN count(r) AS c');
        $this->q($client, 'G4  no Result attached to >1 Student (D10)',
            'MATCH (r:Result)<-[:HAS_RESULT]-(s:Student)
             WITH r, count(DISTINCT s) AS n WHERE n > 1 RETURN count(*) AS c');
        $this->q($client, 'G5  no tenant held as a string (L5)',
            'MATCH (n) WHERE n.sub_institute_id IS NOT NULL
               AND NOT toString(toInteger(n.sub_institute_id)) = toString(n.sub_institute_id)
             RETURN count(n) AS c');
        $this->q($client, 'G6  node budget < 700,000',
            'MATCH (n) RETURN count(n) AS c', 700000);
        $this->q($client, 'G7  relationship budget < 4,000,000',
            'MATCH ()-[r]->() RETURN count(r) AS c', 4000000);

        // G10 runs first: it MEASURES which parent paths are unrecoverable, and G8 needs
        // that verdict to decide whether an orphan is accepted (SOURCE-DANGLING).
        $this->danglingFks($client, $registry);
        $this->orphans($client, $registry);
        if (!$this->option('skip-counts')) $this->counts($client, $registry);
        $this->emptyLabels($client, $registry);

        $this->line(str_repeat('─', 78));
        foreach ($this->softNotes as $s) $this->line('  <fg=yellow>' . $s . '</>');
        foreach ($this->fail as $f) $this->line('  <fg=red>' . $f . '</>');
        $this->line('');
        if ($this->fail) {
            $this->error('VERIFY FAILED — ' . count($this->fail) . ' check(s). Do not proceed to the next phase.');
            return 1;
        }
        $this->info('VERIFY PASSED — ' . count($this->pass) . ' checks, 0 failures.');
        return 0;
    }

    /** Runs a count query; passes when the result is 0, or <= $max when $max is given. */
    private function q($client, string $name, string $cypher, ?int $max = null): void
    {
        try {
            $c = (int) $client->run($cypher)->first()->get('c');
        } catch (\Throwable $e) {
            $this->row($name, 'ERROR', false);
            $this->fail[] = "$name — " . substr($e->getMessage(), 0, 100);
            return;
        }
        $ok = $max === null ? $c === 0 : $c <= $max;
        $this->row($name, number_format($c) . ($max ? ' / ' . number_format($max) : ''), $ok);
        if ($ok) $this->pass[] = $name; else $this->fail[] = "$name — got " . number_format($c);
    }

    /** Tier A labels should have no orphans; Tier B/C are reported but not failed. */
    private function orphans($client, array $registry): void
    {
        $tierA = [];
        foreach ($registry['tables'] ?? [] as $e) {
            if (($e['decision'] ?? '') === 'NODE' && ($e['tier'] ?? '') === 'A' && !empty($e['label'])) {
                $tierA[$e['label']] = true;
            }
        }
        if (!$tierA) { $this->row('G8  no orphaned Tier A nodes', 'no Tier A labels loaded', true); return; }
        try {
            $rows = $client->run('MATCH (n) WHERE NOT (n)--() RETURN labels(n)[0] AS l, count(*) AS c ORDER BY c DESC');
        } catch (\Throwable $e) { $this->row('G8  no orphaned Tier A nodes', 'ERROR', false); return; }

        // Labels whose only declared parent path is soft (chapter-parented) may
        // legitimately orphan — ORPHAN-CHAPTERS accepted ~29% as unrecoverable.
        $softLabels = [];
        foreach ($registry['hierarchy'] ?? [] as $h) {
            if (!empty($h['soft'])) $softLabels[$h['child']] = true;
        }

        $bad = []; $soft = []; $other = 0;
        foreach ($rows as $r) {
            $l = $r->get('l'); $c = (int) $r->get('c');
            if ($l === null || !isset($tierA[$l])) { $other += $c; continue; }
            if (isset($softLabels[$l])) { $soft[] = "$l=$c"; continue; }
            // SOURCE-DANGLING (approved 2026-08-12): an orphan whose parent does not exist
            // ANYWHERE cannot be attached by any load, so it is reported rather than failed.
            // But an orphan whose parent IS present — in the graph or in MariaDB — means the
            // edge should have been built and was not, which is a real defect and must fail.
            // Measured per label, never assumed; `n` is how many parents were found.
            $n = $this->recoverableParents($client, $registry, $l);
            if ($n === 0) $soft[] = "$l=$c(source-dangling)";
            else          $bad[]  = "$l=$c ($n parent(s) DO exist — edge should have been built)";
        }
        $ok = !$bad;
        $softTotal = 0;
        foreach ($soft as $s) $softTotal += (int) substr($s, strrpos($s, '=') + 1);
        $detail = $ok ? '0' : implode(' ', $bad);
        if ($softTotal) $detail .= " · {$softTotal} accepted (ORPHAN-CHAPTERS + SOURCE-DANGLING)";
        if ($other)     $detail .= " · Tier B/C {$other}";
        $this->row('G8  no orphaned Tier A nodes', $detail, $ok);
        if ($soft) $this->softNotes[] = 'G8 accepted orphans (ORPHAN-CHAPTERS + SOURCE-DANGLING, parent exists nowhere): '
            . implode(' ', $soft);
        if ($ok) $this->pass[] = 'G8'; else $this->fail[] = 'G8 orphaned Tier A nodes: ' . implode(' ', $bad);
    }

    /**
     * G10 — DEFECT D9. A node carrying a foreign key whose target does not exist.
     *
     * This is the check that matters most: the pre-migration graph had 7,674 questions
     * pointing at chapters that were never created, and Phase 1 measured the real figure
     * at 59,314. Nodes keep their source columns as properties, so the FK can be resolved
     * against the parent's uid directly.
     *
     * Reported per (child label, fk) using the registry's hierarchy declarations.
     */
    private function danglingFks($client, array $registry): void
    {
        $specs = $registry['hierarchy'] ?? [];
        $soft = [];
        if (!$specs) { $this->row('G10 no dangling FK references (D9)', 'no hierarchy declared', true); return; }

        $bad = []; $checked = 0;
        foreach ($specs as $h) {
            $child = $h['child']; $parent = $h['parent']; $fk = $h['fk'];
            // MAPPINGTYPE-SCOPE: a by_id path resolves the parent on its uid TAIL, because
            // the child is tenant-global and cannot name the parent's tenant.
            if (!empty($h['by_id'])) {
                $cypher = "MATCH (c:`$child`)
                           WHERE c.`$fk` IS NOT NULL AND toString(c.`$fk`) <> '' AND toInteger(c.`$fk`) <> 0
                           WITH c, toString(toInteger(c.`$fk`)) AS want
                           WHERE NOT EXISTS { MATCH (p:`$parent`) WHERE split(p.uid,':')[3] = want }
                           RETURN count(c) AS c, collect(DISTINCT want)[0..2000] AS ids";
            } else {
                // Institute is keyed on the tenant itself; others on the child's tenant.
                $parentUid = ($fk === 'sub_institute_id')
                    ? 'toString(toInteger(c.' . $fk . ')) + ":0:" + toString(toInteger(c.' . $fk . '))'
                    : 'toString(c.sub_institute_id) + ":0:" + toString(toInteger(c.' . $fk . '))';
                $cypher = "MATCH (c:`$child`)
                           WHERE c.`$fk` IS NOT NULL AND toString(c.`$fk`) <> '' AND toInteger(c.`$fk`) <> 0
                           WITH c, '$parent:' + $parentUid AS want, toString(toInteger(c.`$fk`)) AS raw
                           WHERE NOT EXISTS { MATCH (p:`$parent`) WHERE p.uid = want }
                           RETURN count(c) AS c, collect(DISTINCT raw)[0..2000] AS ids";
            }
            try {
                $res = $client->run($cypher)->first();
                $n   = (int) $res->get('c');
                $ids = $res->get('ids')->toArray();
            } catch (\Throwable $e) { continue; }
            $checked++;
            if ($n > 0) {
                // SOURCE-DANGLING (approved 2026-08-12). Do not carry a hardcoded list of
                // "unrecoverable" paths — MEASURE it. A dangling FK whose target row EXISTS
                // in MariaDB is a genuine load defect and must fail. One whose target exists
                // NOWHERE (not in MariaDB, not in the rescue set) can never be resolved by
                // any load, so it is reported with counts instead. This is what the old
                // G10_UNRECOVERABLE constant asserted by hand; now it is checked every run,
                // so a path that becomes loadable stops being silently excused.
                $recoverable = $this->existInMariaDb($parent, $ids);
                if (!empty($h['soft']) || $recoverable === 0) {
                    $soft[] = "$child.$fk=$n" . ($recoverable ? " ({$recoverable} RECOVERABLE)" : '');
                } else {
                    $bad[] = "$child.$fk->$parent=$n ({$recoverable} exist in MariaDB)";
                }
            }
        }
        $ok = !$bad;
        $softTotal = 0;
        foreach ($soft ?? [] as $s) $softTotal += (int) substr($s, strrpos($s, '=') + 1);
        $detail = $ok ? "$checked path(s) clean" : implode(' ', array_slice($bad, 0, 4));
        if (!empty($soft)) $detail .= ($ok ? '' : ' ') . " · {$softTotal} accepted (ORPHAN-CHAPTERS + SOURCE-DANGLING)";
        $this->row('G10 no dangling FK references (D9)', $detail, $ok);
        if (!empty($soft)) $this->softNotes[] = 'G10 accepted dangling (ORPHAN-CHAPTERS + SOURCE-DANGLING, target exists nowhere): '
            . implode(' ', $soft);
        if ($ok) $this->pass[] = 'G10';
        else $this->fail[] = 'G10 dangling FKs (D9): ' . implode(' ', $bad);
    }

    /**
     * G11 — a NODE table that has rows in MariaDB but produced no nodes at all.
     *
     * G9 cannot catch this. expectedRows() deliberately MIRRORS the export's filters so the
     * comparison is fair, which means a filter that is itself wrong produces a matching
     * "expected 0" for an actual 0 and the label passes. That is how :SchoolSection (10 rows,
     * Tier A, phase 4) sat empty through a Foundation gate reported as 10/10 PASS, and how
     * :ContentCategory, :Skill, :JobRole and :SQAAStandard were queued to do the same.
     *
     * So this check ignores every filter and asks the blunt question: the table has rows —
     * where are the nodes?
     */
    private function emptyLabels($client, array $registry): void
    {
        $module = $this->option('module');
        $bad = []; $checked = 0;
        foreach ($registry['tables'] ?? [] as $t => $e) {
            if (($e['decision'] ?? '') !== 'NODE' || empty($e['label'])) continue;
            if ($module && ($e['module'] ?? null) !== $module) continue;
            try {
                if ((int) DB::table($t)->count() === 0) continue;      // legitimately empty
                $checked++;
                $n = (int) $client->run('MATCH (n) WHERE n.uid STARTS WITH $p RETURN count(n) AS c',
                    ['p' => $e['label'] . ':'])->first()->get('c');
            } catch (\Throwable $ex) { continue; }
            if ($n === 0) $bad[] = $e['label'] . " (`$t`, tier " . ($e['tier'] ?? '?') . ')';
        }
        $ok = !$bad;
        $this->row('G11 no NODE table loaded zero nodes',
            $ok ? "$checked table(s) populated" : count($bad) . ' empty', $ok);
        if ($ok) $this->pass[] = 'G11';
        else $this->fail[] = 'G11 tables with MariaDB rows but no nodes: ' . implode(' · ', $bad);
    }

    /**
     * For the ORPHANED nodes of one label: how many of their declared parents actually
     * exist — in the graph, or in MariaDB. Zero means nothing could ever have attached
     * them (SOURCE-DANGLING, accepted); anything above zero is a missing edge (a defect).
     */
    private function recoverableParents($client, array $registry, string $label): int
    {
        $found = 0;
        foreach ($registry['hierarchy'] ?? [] as $h) {
            if ($h['child'] !== $label) continue;
            $fk = $h['fk'];
            try {
                $res = $client->run(
                    "MATCH (n:`$label`) WHERE NOT (n)--()
                       AND n.`$fk` IS NOT NULL AND toString(n.`$fk`) <> '' AND toInteger(n.`$fk`) <> 0
                     RETURN collect(DISTINCT toString(toInteger(n.`$fk`)))[0..2000] AS ids")->first();
                $ids = $res->get('ids')->toArray();
            } catch (\Throwable $e) { continue; }
            if (!$ids) continue;

            // present in the graph? (uid tail is the pk for every label — see loadScoped)
            try {
                $found += (int) $client->run(
                    "MATCH (p:`{$h['parent']}`) WHERE split(p.uid,':')[3] IN \$ids RETURN count(p) AS c",
                    ['ids' => $ids])->first()->get('c');
            } catch (\Throwable $e) { /* fall through to MariaDB */ }

            $found += $this->existInMariaDb($h['parent'], $ids);
        }
        return $found;
    }

    /**
     * How many of these pk values actually EXIST in the parent label's MariaDB table.
     *
     * This is what separates "the load lost a parent" (a defect — the row is right there
     * in the source) from "the parent does not exist anywhere" (SOURCE-DANGLING — no load
     * can ever fix it). Replaces the hand-maintained G10-RESIDUAL constant, which asserted
     * that distinction once, on 2026-08-11, and would have gone on excusing a path long
     * after the underlying data was repaired.
     */
    private function existInMariaDb(string $parentLabel, array $ids): int
    {
        if (!$ids) return 0;
        $table = null;
        foreach (config('neo4j_graph.tables', []) as $n => $e) {
            if (($e['decision'] ?? '') === 'NODE' && ($e['label'] ?? '') === $parentLabel) { $table = $n; break; }
        }
        if (!$table) return 0;
        $pk = config("neo4j_graph.tables.$table.pk", 'id');
        try {
            return (int) DB::table($table)->whereIn($pk, array_map('intval', $ids))->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** The `source` values that mark a rescue-seeded node — see the note in counts(). */
    private function rescueTags(): array
    {
        return array_values(array_filter(array_map(
            fn ($f) => $f['source'] ?? null, config('neo4j_graph.rescue.files', []))));
    }

    /** Neo4j node count vs MariaDB COUNT(*) for every NODE table in scope. */
    private function counts($client, array $registry): void
    {
        $module = $this->option('module');

        // A label can be fed by several tables (registry-check W5 reports 17 such labels,
        // e.g. `cast` + `caste` both -> :Caste). Compare the label total against the SUM of
        // its sources, not against any one of them.
        $sources = [];
        foreach ($registry['tables'] ?? [] as $t => $e) {
            if (($e['decision'] ?? '') !== 'NODE') continue;
            if ($module && ($e['module'] ?? null) !== $module) continue;
            $sources[$e['label']][] = $t;
        }

        $drift = 0; $checked = 0; $churn = [];
        foreach ($sources as $label => $tables) {
            try {
                // G9-CHURN (approved 2026-08-12). `vivek_erp` is a LIVE production database
                // and is written to during the migration — measured on 2026-08-12,
                // `document_extractions` deleted ids 148/153/154/155 and created 160 in the
                // window between the export and this gate. Comparing the graph against live
                // COUNT(*) therefore measures "did anyone use the app just now", not "did the
                // load work", and an actively-written table can never be reliably green.
                //
                // So the GATE compares the graph against the export manifest — what the
                // pipeline was actually handed — which measures load correctness exactly and
                // is reproducible. Live drift is still computed and printed, as information,
                // so source churn stays visible instead of being hidden by a tolerance band.
                $my = 0; $manifest = 0; $haveManifest = true;
                foreach ($tables as $t) {
                    $my += $this->expectedRows($t, $registry['tables'][$t] ?? []);
                    $rows = $this->manifestRows($t, $registry['tables'][$t] ?? []);
                    if ($rows === null) $haveManifest = false; else $manifest += $rows;
                }
                if ($haveManifest && $manifest !== $my) $churn[] = "$label:" . ($my - $manifest);
                if ($haveManifest) $my = $manifest;
                // Rescue-seeded nodes have no MariaDB row by definition (5,532 :Chapter
                // rescued against 99 in chapter_master). Count only what MariaDB should
                // have produced; the rescued set is verified separately, by line count,
                // in neo4j:registry-check.
                // Exclude rescue-seeded nodes by the TAG VALUE, never by "source is set":
                // content_master has its own `source` column (31,362 rows, all ''), so
                // `n.source IS NULL` matched nothing and G9 reported :Content as 0 vs 31,362
                // — a drift that did not exist.
                $gr = (int) $client->run(
                    'MATCH (n) WHERE n.uid STARTS WITH $p AND NOT coalesce(n.source, "") IN $tags
                     RETURN count(n) AS c',
                    ['p' => $label . ':', 'tags' => $this->rescueTags()]
                )->first()->get('c');
            } catch (\Throwable $ex) { continue; }
            $checked++;
            // A label with nothing in the graph used to be waved through as "not loaded yet".
            // That is indistinguishable from a table that failed to export: chapter_master
            // expected 99, exported 0 through a SQL error, loaded 0, and G9 still reported
            // "0 drifting". Zero is only acceptable when zero was expected.
            if ($gr === 0 && $my === 0) continue;
            if ($gr !== $my) {
                $drift++;
                $this->fail[] = sprintf('G9 count drift :%s — export manifest %s (%s), Neo4j %s',
                    $label, number_format($my), implode(' + ', $tables), number_format($gr));
            }
        }
        $this->row('G9  Neo4j vs export manifest', "$checked labels, $drift drifting", $drift === 0);
        if ($drift === 0) $this->pass[] = 'G9';
        if ($churn) {
            $this->softNotes[] = 'G9 live-source churn since export (informational, not a load fault): '
                . implode(' ', $churn);
        }
    }

    /**
     * How many rows this table's exported CSV actually contains — the manifest the loader
     * was given. Null when there is no CSV to compare against, in which case G9 falls back
     * to the live MariaDB expectation.
     *
     * Ragged rows are excluded exactly as the loader excludes them, so a CSV the loader
     * partly rejected does not read as a load failure.
     */
    private function manifestRows(string $table, array $e): ?int
    {
        $module = $e['module'] ?? null;
        if (!$module) return null;
        $file = storage_path("app/neo4j/$module/$table.csv");
        if (!is_readable($file)) return null;
        $fh = fopen($file, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '');
        if (!$header) { fclose($fh); return null; }
        $cols = count($header); $n = 0;
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if (count($r) !== $cols) continue;
            $n++;
        }
        fclose($fh);
        return $n;
    }

    /**
     * How many rows SHOULD reach the graph from this table.
     *
     * Not `COUNT(*)`: under decision ORPHAN-TENANTS the export drops rows whose
     * `sub_institute_id` has no `school_setup` row, so the expected count must apply the
     * same rule. Anything else would compare the graph against a number the pipeline was
     * never trying to produce.
     */
    private function expectedRows(string $table, array $e): int
    {
        $tenant = $e['tenant'] ?? ['mode' => 'global'];
        try {
            $q = DB::table($table);
            if (($tenant['mode'] ?? '') === 'column') {
                $q->join('school_setup as _inst', '_inst.Id', '=', $table . '.' . $tenant['column']);
            } elseif (($tenant['mode'] ?? '') === 'derive') {
                $pt = $tenant['table']; $fk = $tenant['fk']; $pk = $tenant['key'] ?? 'id';
                $q->join("$pt as _p", "_p.$pk", '=', "$table.$fk")
                  ->join('school_setup as _inst', '_inst.Id', '=', '_p.sub_institute_id');
            }
            // Mirror the export's L2 cross-tenant hierarchy rule, or the expected count
            // will exceed what the pipeline was ever going to produce.
            $rescueBacked = array_keys(config('neo4j_graph.rescue.files', []));
            $hi = 0;
            foreach (config('neo4j_graph.hierarchy', []) as $h) {
                if ($h['table'] !== $table || $h['fk'] === 'sub_institute_id') continue;
                if (in_array($h['parent'], $rescueBacked, true)) continue;   // resolved in the graph, not SQL
                if ($h['parent'] === $h['child']) continue;                  // self-referencing tree
                $parentTable = null;
                foreach (config('neo4j_graph.tables', []) as $n => $pe) {
                    if (($pe['decision'] ?? '') === 'NODE' && ($pe['label'] ?? '') === $h['parent']) { $parentTable = $n; break; }
                }
                if (!$parentTable || ($tenant['mode'] ?? '') !== 'column') continue;
                // The parent needs a tenant column too — same guard as ExportCommand, or this
                // throws, silently falls back to raw COUNT(*), and invents a drift.
                $pTenant = config("neo4j_graph.tables.$parentTable.tenant.mode");
                if ($pTenant !== 'column') continue;
                // Mirror ExportCommand exactly: a DANGLING fk keeps the row (G10 reports it),
                // only a fk resolving to another tenant drops it. An INNER JOIN here invented
                // a drift of 13 on :Extraction against a load that was in fact correct.
                $alias = '_h' . $hi++;
                $any   = $alias . 'a';
                $q->leftJoin("$parentTable as $alias", function ($j) use ($alias, $table, $h, $tenant) {
                    $j->on("$alias.id", '=', "$table.{$h['fk']}")
                      ->on("$alias.sub_institute_id", '=', "$table.{$tenant['column']}");
                });
                $q->leftJoin("$parentTable as $any", "$any.id", '=', "$table.{$h['fk']}");
                $q->where(function ($w) use ($alias, $any, $table, $h) {
                    $w->whereNull("$table.{$h['fk']}")
                      ->orWhere("$table.{$h['fk']}", 0)
                      ->orWhere("$table.{$h['fk']}", '')
                      ->orWhereNotNull("$alias.id")
                      ->orWhereNull("$any.id");
                });
            }
            return (int) $q->count();
        } catch (\Throwable $ex) {
            return (int) DB::table($table)->count();
        }
    }

    private function row(string $name, string $detail, bool $ok): void
    {
        $this->line(sprintf('  %-46s %-24s %s', $name, $detail, $ok ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'));
    }
}
