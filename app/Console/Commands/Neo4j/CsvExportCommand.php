<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Export the CSVs the module Cypher scripts read.
 * ---------------------------------------------------------------------------
 *
 * `database/neo4j/cypher/*.cypher` is written in the k12_cypher dialect, so every
 * ingest statement begins
 *
 *     LOAD CSV WITH HEADERS FROM 'file:///<name>.csv' AS row
 *
 * On the Neo4j host that reads `<import dir>/<name>.csv`. From this machine
 * `neo4j:cypher` rewrites the prefix to `UNWIND $rows AS row` and streams the same
 * file over Bolt — which is only possible if the file exists locally. That is what
 * this command produces.
 *
 * WHY A LOCAL CSV AND NOT `INTO OUTFILE`
 * MariaDB writes OUTFILE on the *database* host (202.47.117.220); Neo4j reads
 * `file:///` on the *graph* host (202.47.117.61). They are different machines, so
 * the SQL-only route always needs a human to copy files between them. `--sql` still
 * emits that script for whoever wants it, but the default path needs no upload.
 *
 * SHAPE OF THE OUTPUT
 * Every value is written as a STRING, NULL as the empty string, CR/LF collapsed to a
 * space. That is exactly what `LOAD CSV` hands Cypher, so `toInteger(trim(row.x))`
 * behaves identically whether the file is read by the server or replayed by the
 * runner. Emitting typed values here would make the two paths diverge silently.
 *
 *     php artisan neo4j:csv-export --module=hr
 *     php artisan neo4j:csv-export --csv=timetable_agg
 *     php artisan neo4j:csv-export --module=hr --sql
 *
 * Read-only against MariaDB. Writes nothing to Neo4j.
 */
class CsvExportCommand extends Command
{
    protected $signature = 'neo4j:csv-export
        {--module= : module key from database/neo4j/modules.php}
        {--csv=    : a single CSV name instead of a whole module}
        {--sql     : also write the equivalent INTO OUTFILE script for the MySQL host}
        {--limit=0 : cap rows per CSV (smoke tests only — never for a real load)}';

    protected $description = 'Export the CSVs the Neo4j module scripts read (read-only against MariaDB)';

    /** Where the runner looks for them. `storage` is gitignored, so nothing is tracked. */
    public static function dir(): string
    {
        $dir = storage_path('app/neo4j-csv');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        return $dir;
    }

    public function handle(): int
    {
        $modules = require base_path('database/neo4j/modules.php');
        $module  = $this->option('module');
        $only    = $this->option('csv');

        if (!$module && !$only) {
            $this->error('Give --module= or --csv=.');
            $this->line('Modules: ' . implode(', ', array_keys($modules)));
            return 1;
        }

        // Build name => SQL. A single --csv is looked up across every module so the
        // caller does not have to remember which one owns it.
        $queries = [];
        if ($module) {
            if (!isset($modules[$module])) {
                $this->error("Unknown module '$module'. Known: " . implode(', ', array_keys($modules)));
                return 1;
            }
            $queries = $modules[$module]['csv'] ?? [];
            if ($only) {
                $queries = array_intersect_key($queries, [$only => true]);
            }
        } else {
            foreach ($modules as $m) {
                if (isset($m['csv'][$only])) { $queries = [$only => $m['csv'][$only]]; break; }
            }
        }

        if (!$queries) { $this->error('Nothing to export for that selection.'); return 1; }

        $dir = self::dir();
        $this->info('Exporting ' . count($queries) . ' CSV(s) to ' . $dir);
        $this->newLine();

        $failed = [];
        $total  = 0;

        foreach ($queries as $name => $sql) {
            [$rows, $ok] = $this->export($dir, $name, $sql);
            $total += $rows;
            if (!$ok) $failed[] = $name;
        }

        if ($this->option('sql') && $module) $this->writeOutfileScript($dir, $module, $queries);

        $this->newLine();
        $this->info('Total rows: ' . number_format($total));

        if ($failed) {
            // A failed export is not a warning to scroll past: the loader would read a
            // truncated file and report a clean "0 created" for rows that never arrived.
            $this->error(count($failed) . ' CSV(s) FAILED: ' . implode(', ', $failed));
            return 1;
        }
        return 0;
    }

    /** @return array{0:int,1:bool} rows written, success */
    private function export(string $dir, string $name, string $sql): array
    {
        $limit = (int) $this->option('limit');
        if ($limit > 0 && !preg_match('/\blimit\s+\d+\s*$/i', $sql)) $sql .= " LIMIT $limit";

        $file = "$dir/$name.csv";
        $fh   = fopen($file, 'w');
        $written = 0; $header = null;

        try {
            foreach (DB::cursor($sql) as $row) {
                $r = (array) $row;
                if ($header === null) { $header = array_keys($r); fputcsv($fh, $header, ',', '"', ''); }
                fputcsv($fh, array_map(function ($v) {
                    if ($v === null) return '';
                    // CR/LF inside a value split a row in two under LOAD CSV — that is what
                    // produced the 22 ragged rows in the curriculum load. Collapse them here
                    // so the file is safe on either path.
                    return str_replace(["\r\n", "\r", "\n"], ' ', (string) $v);
                }, $r), ',', '"', '');
                $written++;
            }
        } catch (\Throwable $e) {
            fclose($fh);
            $this->line(sprintf('  %-38s <fg=red>ERROR</> %s', $name, substr($e->getMessage(), 0, 100)));
            return [0, false];
        }
        fclose($fh);

        // A query that returns nothing writes a header-less 0-byte file. Say so rather
        // than letting the runner report "0 rows" as if the load were fine.
        $this->line(sprintf('  %-38s %10s rows%s', $name, number_format($written),
            $written === 0 ? '  <fg=yellow>(empty — check the source table)</>' : ''));

        return [$written, true];
    }

    /**
     * The same selects as `INTO OUTFILE`, for running on the MySQL host when someone
     * prefers the server-side LOAD CSV path. Not used by `neo4j:cypher`.
     */
    private function writeOutfileScript(string $dir, string $module, array $queries): void
    {
        $out = "-- =====================================================================\n"
             . "--  CSV export for Neo4j — module: $module\n"
             . "--  Run on the MySQL host as a user with FILE privilege, then copy each\n"
             . "--  .csv to the Neo4j import directory (/opt/neo4j-next_lms/import).\n"
             . "--  Generated by `php artisan neo4j:csv-export --module=$module --sql`.\n"
             . "-- =====================================================================\n\n";

        foreach ($queries as $name => $sql) {
            $out .= "-- $name.csv\n"
                 . rtrim(rtrim(trim($sql), ';')) . "\n"
                 . "INTO OUTFILE '/var/lib/mysql-files/$name.csv'\n"
                 . "FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '\\\\'\n"
                 . "LINES TERMINATED BY '\\n';\n\n";
        }

        $file = "$dir/$module.sql";
        file_put_contents($file, $out);
        $this->line("  <fg=cyan>wrote</> $file");
        $this->line('  <fg=yellow>note</> INTO OUTFILE emits no header row; the .cypher files expect one.');
    }
}
