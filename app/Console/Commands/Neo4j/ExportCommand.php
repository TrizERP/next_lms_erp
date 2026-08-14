<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reads a module's tables out of MariaDB into CSV under storage/app/neo4j/<module>/.
 *
 * READ-ONLY on MariaDB (SELECT only) and never touches Neo4j. Re-running overwrites
 * the module folder, so an interrupted export is always safe to repeat.
 *
 * Tenancy (L2) is resolved here, in SQL, not in the loader: every exported row carries
 * a concrete _tenant and _syear, and rows that cannot resolve one are dropped and
 * counted rather than defaulted to 0.
 */
class ExportCommand extends Command
{
    protected $signature = 'neo4j:export
        {--module= : module key, e.g. foundation}
        {--table=  : a single table instead of a whole module}
        {--limit=0 : cap rows per table (0 = no cap), for smoke tests}';
    protected $description = 'Export a module from MariaDB to CSV for Neo4j loading (read-only)';

    public function handle(): int
    {
        $registry = config('neo4j_graph');
        $tables = $this->select($registry['tables'] ?? []);
        if ($tables === null) return 1;

        // With --table= and no --module=, fall back to the table's registered module so that
        // neo4j:load looks in the same folder this wrote to.
        $module = $this->option('module') ?: (reset($tables)['module'] ?? 'adhoc');
        $dir = storage_path("app/neo4j/$module");
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $this->info("Exporting " . count($tables) . " table(s) -> $dir");
        $this->line(str_repeat('─', 78));

        $totalRows = 0; $totalDropped = 0;
        foreach ($tables as $t => $e) {
            [$rows, $dropped] = $this->exportTable($t, $e, $dir);
            $totalRows += $rows; $totalDropped += $dropped;
        }

        $this->line(str_repeat('─', 78));
        $this->info("Exported " . number_format($totalRows) . " rows.");
        if ($totalDropped > 0) {
            $this->warn(number_format($totalDropped) . ' row(s) DROPPED — no resolvable tenant (L2). '
                . 'See the per-table counts above; this number must be recorded in the phase log.');
        }
        // A table that threw wrote a 0-byte CSV and loaded nothing. Exiting 0 here is how
        // chapter_master's 99 rows went missing through a whole export/load/verify cycle
        // without anyone noticing. An export with a failed table is a failed export.
        if ($this->failedTables) {
            $this->error(count($this->failedTables) . ' table(s) FAILED to export: '
                . implode(', ', $this->failedTables) . ' — their CSVs are empty and would load nothing.');
            return 1;
        }
        return 0;
    }

    /** Tables whose SELECT threw; see the exit-code note in handle(). */
    private array $failedTables = [];

    /**
     * Follows a `derive` chain to the table that actually holds the tenant/syear column.
     *
     * The chain can be longer than one hop — lms_lesson_plan_concepts derives from
     * lms_lesson_plan_periods, which itself derives from lms_intelligence_lesson_plans, and
     * only that last table has `sub_institute_id`/`syear`. Stops on a non-derive parent, an
     * unregistered parent, a broken link, or a cycle.
     *
     * @return array{0:string,1:string,2:string} [joinSql, finalAlias, finalColumn]
     */
    private function deriveChain(string $table, array $spec, string $prefix = '_p', string $aspect = 'tenant'): array
    {
        $col   = $aspect === 'tenant' ? 'sub_institute_id' : 'syear';
        $join  = '';
        $left  = "`$table`";
        $alias = $left;
        $seen  = [$table => true];
        $hop   = 0;

        while (($spec['mode'] ?? '') === 'derive') {
            $pt = $spec['table'] ?? null;
            $fk = $spec['fk'] ?? null;
            $pk = $spec['key'] ?? 'id';
            if (!$pt || !$fk || isset($seen[$pt])) break;      // broken link or cycle
            $seen[$pt] = true;
            $alias = $prefix . $hop++;
            $join .= " INNER JOIN `$pt` AS $alias ON $alias.`$pk` = $left.`$fk`";
            $left  = $alias;
            $spec  = config("neo4j_graph.tables.$pt.$aspect", ['mode' => 'column', 'column' => $col]);
        }
        if (($spec['mode'] ?? '') === 'column' && !empty($spec['column'])) $col = $spec['column'];
        return [$join, $alias, $col];
    }

    /** @return array<string,array>|null */
    private function select(array $all): ?array
    {
        if ($tbl = $this->option('table')) {
            if (!isset($all[$tbl])) { $this->error("`$tbl` is not in the registry."); return null; }
            return [$tbl => $all[$tbl]];
        }
        $module = $this->option('module');
        if (!$module) { $this->error('Pass --module= or --table=.'); return null; }
        $sel = array_filter($all, fn ($e) => ($e['module'] ?? null) === $module
            && in_array($e['decision'] ?? '', ['NODE', 'EDGE', 'AGG_EDGE'], true));
        if (!$sel) { $this->error("No projectable tables for module '$module'."); return null; }
        uasort($sel, fn ($a, $b) => ($a['phase'] ?? 99) <=> ($b['phase'] ?? 99));
        return $sel;
    }

    /** @return array{0:int,1:int} [rowsWritten, rowsDropped] */
    private function exportTable(string $t, array $e, string $dir): array
    {
        $tenant = $e['tenant'] ?? ['mode' => 'global'];
        $syear  = $e['syear'] ?? ['mode' => 'constant', 'value' => 0];

        $select = ["`$t`.*"];
        $join = '';
        // ORPHAN-TENANTS (decided 2026-08-10): a tenant must EXIST, not merely be
        // populated. `school_setup` holds 56 institutes but the data references 74-93
        // distinct sub_institute_id values, which left 734 Foundation nodes unable to
        // reach an :Institute (Phase 4 gate G1/G8/G10). Rows whose tenant has no
        // school_setup row are dropped here and counted — never defaulted to 0, and
        // never given a placeholder institute.
        $validateTenant = false;
        switch ($tenant['mode']) {
            case 'column':
                $select[] = "`$t`.`{$tenant['column']}` AS _tenant";
                $validateTenant = true;
                break;
            case 'self':
                // this table IS the institute register; nothing to validate against
                $select[] = "`$t`.`{$tenant['column']}` AS _tenant";
                break;
            case 'global':
                // reference data shared by all tenants — exempt from L2 by declaration
                $select[] = '0 AS _tenant';
                break;
            case 'derive':
                // A derive parent may itself derive: lms_lesson_plan_concepts ->
                // lms_lesson_plan_periods -> lms_intelligence_lesson_plans. Assuming a single
                // hop emitted `_p.sub_institute_id` against a table that has no such column,
                // killing the SELECT and exporting 0 of 537 rows. Walk to the end of the chain.
                [$join, $deriveAlias, $tenantCol] = $this->deriveChain($t, $tenant);
                $select[] = "$deriveAlias.`$tenantCol` AS _tenant";
                $validateTenant = true;
                break;
        }
        if ($validateTenant) {
            // Must use the alias the derive chain actually ended on — `_p` is only correct
            // for a single-hop chain, and hardcoding it reintroduced the same 1054 here.
            $col = $tenant['mode'] === 'derive'
                ? "$deriveAlias.`$tenantCol`"
                : "`$t`.`{$tenant['column']}`";
            $join .= " INNER JOIN `school_setup` AS _inst ON _inst.`Id` = $col";
        }

        // L2 — no edge may cross tenants. A hierarchy FK pointing at a parent in a
        // DIFFERENT tenant cannot be projected: the loader would look up a uid that does
        // not exist and silently drop the edge, leaving an orphan. Drop the row here and
        // count it instead. (Foundation has exactly one: batch 556 is tenant 62 but its
        // division_id 47 belongs to tenant 51 — both institutes are real.)
        //
        // This MUST distinguish a cross-tenant FK from a merely DANGLING one. An INNER JOIN
        // conflates them and deletes the node: chapter_master 8665-8676 and
        // document_extractions 130-142 all point at `subject` 5576, which exists in NO
        // tenant (max subject id is 5575), and 25 real rows were being dropped from the
        // projection for a fault that belongs to their parent, not to them. A dangling FK
        // is a G10 finding to be REPORTED, not a reason to lose the node — so keep the row,
        // let the edge fail to build, and drop ONLY when the parent exists somewhere else.
        $rescueBacked = array_keys(config('neo4j_graph.rescue.files', []));
        $where = [];
        $hi = 0;
        foreach (config('neo4j_graph.hierarchy', []) as $h) {
            if ($h['table'] !== $t || $h['fk'] === 'sub_institute_id') continue;
            // A parent that can be rescue-seeded exists in the GRAPH but not in MariaDB
            // (:Chapter — 5,532 of them). Validating such an FK in SQL would drop 95-99%
            // of topics, content and lesson plans for pointing at chapters MariaDB lost,
            // which is exactly what CHAPTER-SOURCE exists to repair. Let the loader
            // resolve these against the graph and count what genuinely fails.
            if (in_array($h['parent'], $rescueBacked, true)) continue;
            if ($h['parent'] === $h['child']) continue;              // self-referencing tree
            // The join compares the child's tenant column against the parent's; a table
            // whose tenancy is derived or global has no such column to compare.
            if (($tenant['mode'] ?? '') !== 'column') continue;
            $parentTable = $this->tableForLabel($h['parent']);
            if (!$parentTable) continue;
            // ...and the PARENT needs one too. `chapter_master.unit_id -> lms_units` derives
            // its tenancy, so `_h0.sub_institute_id` does not exist and the whole SELECT dies
            // with a 1054, leaving a 0-byte CSV. Checking only the child (above) is why
            // chapter_master silently exported 0 of its 99 rows.
            $parentEntry = config("neo4j_graph.tables.$parentTable", []);
            if (($parentEntry['tenant']['mode'] ?? '') !== 'column') continue;
            $alias = '_h' . $hi++;
            $any   = $alias . 'a';
            $fk    = "`$t`.`{$h['fk']}`";
            // _hN  — the parent in THIS row's tenant (the edge is only legal if this hits)
            // _hNa — the parent in ANY tenant (distinguishes cross-tenant from dangling)
            $join .= " LEFT JOIN `$parentTable` AS $alias"
                   . " ON $alias.`id` = $fk"
                   . " AND $alias.`sub_institute_id` = `$t`.`{$tenant['column']}`"
                   . " LEFT JOIN `$parentTable` AS $any ON $any.`id` = $fk";
            $where[] = "($fk IS NULL OR $fk = 0 OR $fk = ''"   // no FK to honour
                     . " OR $alias.`id` IS NOT NULL"           // parent in the same tenant
                     . " OR $any.`id` IS NULL)";               // parent nowhere — G10 reports it
            $validateTenant = true;
        }
        if (($syear['mode'] ?? '') === 'column') {
            $select[] = "`$t`.`{$syear['column']}` AS _syear";
        } elseif (($syear['mode'] ?? '') === 'derive') {
            // Same chain problem as the tenant above. Reuse the tenant chain's join when it
            // walks the same tables, so the row is not joined twice.
            if (isset($deriveAlias) && ($tenant['table'] ?? null) === ($syear['table'] ?? null)) {
                $select[] = "$deriveAlias.`syear` AS _syear";
            } else {
                [$sJoin, $sAlias, $sCol] = $this->deriveChain($t, $syear, '_sy', 'syear');
                $join .= $sJoin;
                $select[] = "$sAlias.`$sCol` AS _syear";
            }
        } else {
            $select[] = '0 AS _syear';
        }

        $limit = (int) $this->option('limit');
        $sql = 'SELECT ' . implode(', ', $select) . " FROM `$t`" . $join
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ($limit > 0 ? " LIMIT $limit" : '');

        $file = "$dir/$t.csv";
        $fh = fopen($file, 'w');
        $written = 0; $header = null;

        try {
            foreach (DB::cursor($sql) as $row) {
                $r = (array) $row;
                if ($header === null) { $header = array_keys($r); fputcsv($fh, $header, ',', '"', ''); }
                fputcsv($fh, array_map(fn ($v) => $v === null ? '' : (string) $v, $r), ',', '"', '');
                $written++;
            }
        } catch (\Throwable $ex) {
            fclose($fh);
            $this->failedTables[] = $t;
            $this->line(sprintf('  %-40s <fg=red>ERROR</> %s', $t, substr($ex->getMessage(), 0, 90)));
            return [0, 0];
        }
        fclose($fh);

        // Rows lost to a tenancy INNER JOIN — either the derivation parent is missing or
        // the tenant has no school_setup row. L2: never default a tenant to 0.
        $dropped = 0;
        if ($validateTenant && $limit === 0) {
            $total = (int) DB::table($t)->count();
            $dropped = max(0, $total - $written);
        }
        $this->line(sprintf('  %-40s %9s rows%s', $t, number_format($written),
            $dropped ? "  <fg=yellow>{$dropped} dropped (tenant does not exist)</>" : ''));
        return [$written, $dropped];
    }

    /** Which source table backs a node label (first match wins). */
    private function tableForLabel(string $label): ?string
    {
        foreach (config('neo4j_graph.tables', []) as $name => $e) {
            if (($e['decision'] ?? '') === 'NODE' && ($e['label'] ?? '') === $label) return $name;
        }
        return null;
    }
}
