<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

/**
 * Run a k12-style .cypher module script against the live graph.
 * ---------------------------------------------------------------------------
 *
 * The scripts in `database/neo4j/cypher/` are written in exactly the dialect of
 * `k12_cypher.txt` / `reference_code.txt`, so they can be pasted into Neo4j Browser or
 * fed to cypher-shell on the server unchanged. This command runs the SAME file from
 * here, without anyone copying a CSV to the Neo4j host, by rewriting
 *
 *     LOAD CSV WITH HEADERS FROM 'file:///timetable_agg.csv' AS row
 *
 * into `UNWIND $rows AS row` and streaming `storage/app/neo4j-csv/timetable_agg.csv`
 * in batches over Bolt. Values are bound as strings, which is what LOAD CSV would have
 * produced, so `toInteger(trim(row.x))` means the same thing on both paths.
 *
 * ADDITIVE ONLY — WHAT THIS COMMAND WILL NOT DO
 * The existing graph (the 24 relationship types the k12 scripts built, and their nodes)
 * must not change. Two guards enforce it rather than trusting the scripts:
 *
 *   1. Any statement containing DELETE / REMOVE / DROP / DETACH is REFUSED before the
 *      run starts. A destructive verb in a module script is a bug, not an instruction.
 *   2. The count of every protected relationship type is taken before and after. Any
 *      that moves — except the ones the module manifest declares in `extends` — is
 *      reported as a failure with the delta.
 *
 *     php artisan neo4j:cypher --baseline               # snapshot the whole graph first
 *     php artisan neo4j:cypher --module=hr --dry-run    # print, connect for nothing
 *     php artisan neo4j:cypher --module=hr
 *     php artisan neo4j:cypher --module=hr --verify     # re-run only the verify section
 *     php artisan neo4j:cypher --file=database/neo4j/cypher/20_hr.cypher --from=12
 */
class CypherRunCommand extends Command
{
    protected $signature = 'neo4j:cypher
        {--module=    : module key from database/neo4j/modules.php}
        {--file=      : a .cypher file instead of a module}
        {--verify     : run only the file\'s verify section}
        {--dry-run    : print the statements and the row counts they would read, write nothing}
        {--from=1     : resume at statement N}
        {--batch=5000 : rows per UNWIND batch}
        {--baseline   : snapshot every label and relationship count to storage, then stop}';

    protected $description = 'Run a k12-style .cypher module script (additive; refuses destructive statements)';

    /**
     * The relationship types the reference scripts built. The owner's instruction is that
     * these must not be edited, deleted, renamed or recreated, so the run is aborted if a
     * count moves without the module declaring it.
     */
    public const PROTECTED_RELS = [
        'HAS_STUDENT', 'ENROLLED_IN', 'HAS_SUBJECT', 'HAS_ASSESSMENT', 'HAS_RESULT',
        'HAS_QUESTION', 'BELONGS_TO', 'HAS_CHAPTER', 'ASSESSES_CHAPTER', 'FOR_ASSESSMENT',
        'INCLUDES', 'BELONGS_TO_CURRICULUM', 'HAS_LESSON', 'COVERS', 'ASSESSES',
        'OCCURS_IN', 'TEACHES', 'REMEDIATES', 'ATTEMPTED', 'ATTENDED', 'MASTERS',
        'HAS_MISCONCEPTION', 'HAS_UNIT', 'PREREQUISITE_OF',
    ];

    /** Verbs that may never appear in a module script. */
    private const FORBIDDEN = ['DELETE', 'REMOVE', 'DROP', 'DETACH'];

    private $conn = null;

    public function handle(): int
    {
        if ($this->option('baseline')) return $this->baseline();

        $modules = require base_path('database/neo4j/modules.php');
        $module  = $this->option('module');
        $file    = $this->option('file');
        $extends = [];

        if ($module) {
            if (!isset($modules[$module])) {
                $this->error("Unknown module '$module'. Known: " . implode(', ', array_keys($modules)));
                return 1;
            }
            $file    = base_path('database/neo4j/cypher/' . $modules[$module]['file']);
            $extends = $modules[$module]['extends'] ?? [];
        }

        if (!$file) { $this->error('Give --module= or --file=.'); return 1; }
        if (!is_readable($file)) { $this->error("Cannot read $file"); return 1; }

        // `00_k12_reference.cypher` and `01_graph_repair_reference.cypher` are the record
        // of how the live graph was built, kept verbatim. Re-running either is an ingest,
        // not a no-op: both use bare SET, which overwrites properties on nodes that
        // already exist, and the repair file DELETEs 518,266 relationships.
        if (preg_match('/^0[01]_/', basename($file))) {
            $this->error(basename($file) . ' is reference only and is never executed.');
            $this->line('It records how the existing graph was built. Re-running it would');
            $this->line('overwrite live node properties — and 01_ deletes relationships.');
            return 1;
        }

        $statements = $this->parse(file_get_contents($file));
        if ($this->option('verify')) {
            $statements = array_values(array_filter($statements, fn ($s) => $s['section'] === 'verify'));
            if (!$statements) { $this->error('That file has no `// @section verify` block.'); return 1; }
        }

        // Refuse the whole file rather than the offending statement: a script that thinks
        // it may delete has been written against the wrong brief, and running the half of
        // it that is additive leaves the graph in a state nobody planned.
        foreach ($statements as $s) {
            foreach (self::FORBIDDEN as $verb) {
                if (preg_match('/\b' . $verb . '\b/i', $s['cypher'])) {
                    $this->error("Statement {$s['n']} contains $verb. Module scripts are additive; refusing the file.");
                    $this->line(substr(preg_replace('/\s+/', ' ', $s['cypher']), 0, 160));
                    return 1;
                }
            }
        }

        $this->info(basename($file) . ' — ' . count($statements) . ' statement(s)'
            . ($this->option('verify') ? ' (verify only)' : ''));
        if ($extends) $this->line('  may grow: ' . implode(', ', $extends));
        $this->newLine();

        if (!$this->option('dry-run')) $this->client();

        $before = $this->option('dry-run') ? [] : $this->protectedCounts();
        $from   = (int) $this->option('from');
        $batch  = (int) $this->option('batch');

        foreach ($statements as $s) {
            if ($s['n'] < $from) continue;
            try {
                $this->runStatement($s, $batch);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Statement {$s['n']} FAILED: " . substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 300));
                $this->line(substr(preg_replace('/\s+/', ' ', $s['cypher']), 0, 200));
                $this->line("Fix it, then resume:  php artisan neo4j:cypher --file=$file --from={$s['n']}");
                return 1;
            }
        }

        if ($this->option('dry-run')) { $this->newLine(); $this->info('Dry run — nothing was written.'); return 0; }

        return $this->assertProtected($before, $extends);
    }

    // ------------------------------------------------------------------ parsing

    /**
     * Split a .cypher file into statements.
     *
     * Naive `explode(';')` breaks on a semicolon inside a string literal or a comment,
     * and both occur in these scripts (displayLabel values, `//` notes). This walks the
     * text once, tracking quote and comment state, which is enough for the dialect —
     * there are no block comments or backtick-quoted names in these files.
     *
     * `// @section <name>` lines tag every statement that follows, so `--verify` can run
     * one part of the file.
     *
     * @return array<int, array{n:int, cypher:string, section:string, csv:?string}>
     */
    private function parse(string $text): array
    {
        $out = []; $buf = ''; $n = 0; $section = 'main'; $pendingSection = null;
        $len = strlen($text);
        $inS = false; $inD = false; $inC = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];

            if ($inC) {
                if ($ch === "\n") { $inC = false; $buf .= $ch; }
                continue;
            }
            if (!$inS && !$inD && $ch === '/' && ($i + 1 < $len) && $text[$i + 1] === '/') {
                // Capture a section marker before discarding the comment line.
                $eol  = strpos($text, "\n", $i);
                $line = substr($text, $i, ($eol === false ? $len : $eol) - $i);
                if (preg_match('/@section\s+([A-Za-z0-9_-]+)/', $line, $m)) {
                    $pendingSection = $m[1];
                }
                $inC = true; $i++;
                continue;
            }
            if (!$inD && $ch === "'"  && ($i === 0 || $text[$i - 1] !== '\\')) $inS = !$inS;
            elseif (!$inS && $ch === '"' && ($i === 0 || $text[$i - 1] !== '\\')) $inD = !$inD;

            if ($ch === ';' && !$inS && !$inD) {
                $cypher = trim($buf);
                $buf = '';
                if ($cypher === '') continue;
                if ($pendingSection !== null) { $section = $pendingSection; $pendingSection = null; }
                $out[] = $this->prepare(++$n, $cypher, $section);
                continue;
            }
            $buf .= $ch;
        }

        $cypher = trim($buf);
        if ($cypher !== '') {
            if ($pendingSection !== null) $section = $pendingSection;
            $out[] = $this->prepare(++$n, $cypher, $section);
        }
        return $out;
    }

    /**
     * Turn one server-side statement into one this client can run.
     *
     *   `:auto` / `USING PERIODIC COMMIT` are cypher-shell/server directives with no
     *   meaning over Bolt; the runner's own batching replaces them.
     *
     *   `LOAD CSV WITH HEADERS FROM 'file:///x.csv' AS row` becomes `UNWIND $rows AS row`,
     *   and the CSV name is remembered so execute() knows what to stream.
     */
    private function prepare(int $n, string $cypher, string $section): array
    {
        $cypher = preg_replace('/^\s*:auto\s+/i', '', $cypher);
        $cypher = preg_replace('/^\s*USING\s+PERIODIC\s+COMMIT(\s+\d+)?\s+/i', '', $cypher);

        $csv = null;
        $re  = "/LOAD\s+CSV\s+WITH\s+HEADERS\s+FROM\s+'file:\/\/\/(?:[^']*\/)?([A-Za-z0-9_.-]+)\.csv\s*'\s*AS\s+(\w+)/i";
        if (preg_match($re, $cypher, $m)) {
            $csv    = $m[1];
            $alias  = $m[2];
            $cypher = preg_replace($re, "UNWIND \$rows AS $alias", $cypher, 1);
        }

        return ['n' => $n, 'cypher' => trim($cypher), 'section' => $section, 'csv' => $csv];
    }

    // ------------------------------------------------------------------ execution

    private function runStatement(array $s, int $batch): void
    {
        $label = str_pad('[' . $s['n'] . '] ' . $this->summarise($s['cypher']), 62);

        if ($s['csv'] === null) {
            if ($this->option('dry-run')) { $this->line("$label <fg=cyan>(direct)</>"); return; }
            $t0  = microtime(true);
            $res = $this->cypher($s['cypher'], []);
            $this->line("$label " . $this->results($res) . sprintf(' <fg=gray>%.1fs</>', microtime(true) - $t0));
            return;
        }

        $file = CsvExportCommand::dir() . '/' . $s['csv'] . '.csv';
        if (!is_readable($file)) {
            throw new \RuntimeException("CSV not found: $file — run `php artisan neo4j:csv-export --csv={$s['csv']}` first.");
        }

        $fh = fopen($file, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '');
        if ($header === false) { fclose($fh); $this->line("$label <fg=yellow>empty CSV</>"); return; }

        if ($this->option('dry-run')) {
            fclose($fh);
            $this->line("$label <fg=cyan>{$s['csv']}.csv</> [" . implode(',', array_slice($header, 0, 6))
                . (count($header) > 6 ? ',…' : '') . ']');
            return;
        }

        $t0 = microtime(true); $rows = []; $read = 0; $summary = [];
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if (count($r) !== count($header)) continue;   // ragged row — skip, as LOAD CSV effectively does
            // Bind every value as a string. LOAD CSV does the same, so a script written for
            // the server behaves identically here.
            $rows[] = array_map(fn ($v) => (string) $v, array_combine($header, $r));
            $read++;
            if (count($rows) >= $batch) {
                $summary = $this->accumulate($summary, $this->cypher($s['cypher'], ['rows' => $rows]));
                $rows = []; $this->output->write('.');
            }
        }
        fclose($fh);
        if ($rows) $summary = $this->accumulate($summary, $this->cypher($s['cypher'], ['rows' => $rows]));

        $this->line(($read > $batch ? ' ' : '') . "$label " . number_format($read) . ' rows -> '
            . $this->summaryText($summary) . sprintf(' <fg=gray>%.1fs</>', microtime(true) - $t0));
    }

    /** First meaningful clause, for the progress line. */
    private function summarise(string $cypher): string
    {
        $flat = preg_replace('/\s+/', ' ', $cypher);
        if (preg_match('/CREATE (CONSTRAINT|INDEX) `?(\w+)`?/i', $flat, $m)) return strtolower($m[1]) . ' ' . $m[2];
        if (preg_match('/MERGE \((\w*):`?(\w+)`?[^)]*\)-\[.?:`?(\w+)`?/i', $flat, $m)) return "$m[2] -[$m[3]]->";
        if (preg_match('/MERGE \([^)]*\)-\[.?:`?(\w+)`?/i', $flat, $m)) return "-[$m[1]]->";
        if (preg_match('/MERGE \(\w*:`?(\w+)`?/i', $flat, $m)) return ':' . $m[1];
        if (preg_match('/^MATCH \(\w*:`?(\w+)`?/i', $flat, $m)) return 'match :' . $m[1];
        return substr($flat, 0, 40);
    }

    /** Render a statement's RETURN rows compactly. */
    private function results($res): string
    {
        $parts = [];
        foreach ($res as $rec) {
            $bits = [];
            foreach ($rec->keys() as $k) {
                $v = $rec->get($k);
                if (is_scalar($v) || $v === null) $bits[] = "$k=" . var_export($v, true);
            }
            if ($bits) $parts[] = implode(' ', $bits);
            if (count($parts) >= 3) { $parts[] = '…'; break; }
        }
        return $parts ? '<fg=green>' . implode(' | ', $parts) . '</>' : '<fg=gray>ok</>';
    }

    /** Sum the numeric RETURN columns across batches so a batched load reports one total. */
    private function accumulate(array $acc, $res): array
    {
        foreach ($res as $rec) {
            foreach ($rec->keys() as $k) {
                $v = $rec->get($k);
                if (is_int($v) || is_float($v)) $acc[$k] = ($acc[$k] ?? 0) + $v;
            }
        }
        return $acc;
    }

    private function summaryText(array $acc): string
    {
        if (!$acc) return '<fg=gray>ok</>';
        $bits = [];
        foreach ($acc as $k => $v) $bits[] = "$k=" . number_format($v);
        return '<fg=green>' . implode(' ', $bits) . '</>';
    }

    // ------------------------------------------------------------------ guards

    private function protectedCounts(): array
    {
        $out = [];
        foreach (self::PROTECTED_RELS as $rel) {
            $out[$rel] = (int) $this->cypher("MATCH ()-[r:`$rel`]->() RETURN count(r) AS c", [])->first()->get('c');
        }
        $out['__nodes'] = (int) $this->cypher('MATCH (n) RETURN count(n) AS c', [])->first()->get('c');
        return $out;
    }

    /**
     * The reference layer must come out of a run either identical or larger in exactly the
     * ways the module declared. A shrinking count means something matched and rewrote
     * existing data, which is the one outcome this whole design exists to prevent.
     */
    private function assertProtected(array $before, array $extends): int
    {
        $after = $this->protectedCounts();
        $bad = []; $grew = [];

        foreach ($before as $k => $was) {
            $now = $after[$k] ?? 0;
            if ($now === $was) continue;
            $delta = ($now > $was ? '+' : '') . number_format($now - $was);
            if ($k === '__nodes') {
                if ($now < $was) $bad[] = "nodes $was -> $now ($delta)";
                else $grew[] = "nodes $delta";
                continue;
            }
            if ($now > $was && in_array($k, $extends, true)) { $grew[] = "$k $delta"; continue; }
            $bad[] = "$k $was -> $now ($delta)";
        }

        $this->newLine();
        if ($grew) $this->line('<fg=cyan>grew as declared:</> ' . implode(', ', $grew));

        if ($bad) {
            $this->error('PROTECTED LAYER CHANGED — ' . implode('; ', $bad));
            $this->line('The reference nodes and relationships must not be modified. Investigate before re-running.');
            return 1;
        }
        $this->info('Protected layer unchanged. ' . count(self::PROTECTED_RELS) . ' relationship types verified.');
        return 0;
    }

    // ------------------------------------------------------------------ baseline

    private function baseline(): int
    {
        $this->client();
        $snap = ['taken_at' => date('c'), 'uri' => config('neo4j.uri'), 'labels' => [], 'relationships' => []];

        foreach ($this->cypher('MATCH (n) RETURN count(n) AS c', []) as $r) $snap['nodes'] = (int) $r->get('c');
        foreach ($this->cypher('MATCH ()-[r]->() RETURN count(r) AS c', []) as $r) $snap['relationships_total'] = (int) $r->get('c');
        foreach ($this->cypher('CALL db.labels() YIELD label RETURN label ORDER BY label', []) as $r) {
            $l = $r->get('label');
            $snap['labels'][$l] = (int) $this->cypher("MATCH (n:`$l`) RETURN count(n) AS c", [])->first()->get('c');
        }
        foreach ($this->cypher('CALL db.relationshipTypes() YIELD relationshipType AS t RETURN t ORDER BY t', []) as $r) {
            $t = $r->get('t');
            $snap['relationships'][$t] = (int) $this->cypher("MATCH ()-[x:`$t`]->() RETURN count(x) AS c", [])->first()->get('c');
        }

        $dir  = CsvExportCommand::dir();
        $file = "$dir/baseline-" . date('Y-m-d-His') . '.json';
        file_put_contents($file, json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        // A stable name as well, so a later run can diff without hunting for a timestamp.
        file_put_contents("$dir/baseline.json", json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info(sprintf('%s nodes / %s relationships / %d labels / %d relationship types',
            number_format($snap['nodes']), number_format($snap['relationships_total']),
            count($snap['labels']), count($snap['relationships'])));
        $this->line("Written to $file");
        return 0;
    }

    // ------------------------------------------------------------------ connection

    private function client()
    {
        return $this->conn = ClientBuilder::create()
            ->withDriver('k12', config('neo4j.uri'),
                Authenticate::basic(config('neo4j.username'), config('neo4j.password')))
            ->build();
    }

    /**
     * Bolt drops occasionally over a long load (errno 10054). Every statement here is an
     * idempotent MERGE, so replaying a batch after reconnecting is safe — the same
     * property `neo4j:load` relies on.
     */
    private function cypher(string $cypher, array $params, int $attempts = 4)
    {
        for ($i = 1; ; $i++) {
            try {
                return $this->conn->run($cypher, $params);
            } catch (\Throwable $e) {
                if ($i >= $attempts) throw $e;
                $this->output->write('<fg=yellow>r</>');
                usleep(400000 * $i);
                try { $this->client(); } catch (\Throwable $ignored) { /* retried next loop */ }
            }
        }
    }
}
