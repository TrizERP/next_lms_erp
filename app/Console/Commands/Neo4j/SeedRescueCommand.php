<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

/**
 * Implements decision CHAPTER-SOURCE.
 *
 * `chapter_master` holds 99 rows. The pre-migration graph held 5,536 :Chapter nodes,
 * 5,521 of whose ids exist nowhere in MariaDB — and 95-99% of questions, topics, content
 * and lesson plans point at that vanished id space (classification F1). Phase 3 destroyed
 * the graph, so `docs/neo4j-backup-2026-08-10/nodes_Chapter.csv` is the only remaining
 * copy. Seeding it repairs ~71% of the break.
 *
 * Every node created here is tagged `source='graph-rescue-2026-08-10'` so rescued
 * curriculum can always be told apart from what MariaDB can regenerate.
 *
 * MERGE on uid, so running before or after the MariaDB load gives the same result: the
 * 13 overlapping ids resolve to one node, with MariaDB's values winning if it loads last.
 */
class SeedRescueCommand extends Command
{
    protected $signature = 'neo4j:seed-rescue
        {--label=Chapter : which rescue set to seed}
        {--confirm       : required — writes to Neo4j}
        {--dry-run       : report what would happen and stop}';
    protected $description = 'Seed :Chapter from the Phase 0 rescue export (decision CHAPTER-SOURCE)';

    public function handle(): int
    {
        $label = $this->option('label');
        $dry = (bool) $this->option('dry-run');
        if (!$dry && !$this->option('confirm')) {
            $this->error('neo4j:seed-rescue writes to Neo4j. Re-run with --confirm (or --dry-run).');
            return 1;
        }

        $dir = config('neo4j_graph.rescue.dir');
        $spec = config("neo4j_graph.rescue.files.$label");
        if (!$spec) { $this->error("No rescue spec for :$label."); return 1; }
        $path = rtrim((string) $dir, "/\\") . DIRECTORY_SEPARATOR . $spec['csv'];
        if (!is_readable($path)) { $this->error("Rescue CSV not readable: $path"); return 1; }

        // The file has been deleted four times; refuse to seed a truncated copy.
        $lines = 0;
        $fh = fopen($path, 'r');
        while (fgets($fh) !== false) $lines++;
        fclose($fh);
        if (!empty($spec['lines']) && $lines !== (int) $spec['lines']) {
            $this->error("Rescue CSV is truncated — $lines lines, expected {$spec['lines']}. Refusing to seed.");
            return 1;
        }
        $this->info("Rescue export verified: $path ($lines lines)");

        $idCol = $spec['id_col'] ?? 'chId';
        $source = $spec['source'] ?? 'graph-rescue';

        // Is this label year-scoped? Must match the registry or the seeded uids will not
        // line up with the ones the MariaDB loader produces.
        $yearConst = true;
        foreach (config('neo4j_graph.tables', []) as $e) {
            if (($e['decision'] ?? '') === 'NODE' && ($e['label'] ?? '') === $label) {
                $yearConst = (($e['syear']['mode'] ?? '') === 'constant');
                break;
            }
        }
        if (!$yearConst) {
            $this->error("Label :$label is year-scoped in the registry. Rescue rows carry no reliable"
                . " syear, so seeding would create duplicates. Make it constant first.");
            return 1;
        }

        $fh = fopen($path, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '');
        $idx = array_flip($header);
        foreach ([$idCol, 'sub_institute_id'] as $need) {
            if (!isset($idx[$need])) { fclose($fh); $this->error("Column `$need` missing from the CSV."); return 1; }
        }

        $rows = []; $n = 0; $skipped = 0;
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $id = trim((string) ($r[$idx[$idCol]] ?? ''));
            $tenant = trim((string) ($r[$idx['sub_institute_id']] ?? ''));
            if ($id === '' || $tenant === '') { $skipped++; continue; }

            $props = ['source' => $source];
            foreach ($header as $i => $col) {
                if ($col === '_neo4jInternalId' || $col === 'syear' || $col === 'database_type') continue;
                $v = $r[$i] ?? null;
                if ($v !== null && $v !== '') $props[$col] = $v;
            }
            $rows[] = [
                'uid'    => sprintf('%s:%d:0:%s', $label, (int) $tenant, $id),
                'tenant' => (int) $tenant,
                'props'  => $props,
            ];
            $n++;
        }
        fclose($fh);

        $cypher = "UNWIND \$rows AS row
                   MERGE (c:`$label` {uid: row.uid})
                   ON CREATE SET c += row.props,
                                 c.sub_institute_id = toInteger(row.tenant),
                                 c.syear = 0
                   ON MATCH  SET c.also_in_rescue = true";

        if ($dry) {
            $this->line('[DRY RUN] ' . preg_replace('/\s+/', ' ', $cypher));
            $this->line('  rows: ' . number_format($n) . ($skipped ? ", skipped $skipped" : ''));
            $this->line('  first uid: ' . ($rows[0]['uid'] ?? '—'));
            return 0;
        }

        $client = ClientBuilder::create()
            ->withDriver('x', config('neo4j.uri'),
                Authenticate::basic(config('neo4j.username'), config('neo4j.password')))
            ->build();

        $before = (int) $client->run("MATCH (n:`$label`) RETURN count(n) AS c")->first()->get('c');
        foreach (array_chunk($rows, 400) as $chunk) {
            $client->run($cypher, ['rows' => $chunk]);
            $this->output->write('.');
        }
        $this->line('');
        $after = (int) $client->run("MATCH (n:`$label`) RETURN count(n) AS c")->first()->get('c');
        $rescued = (int) $client->run(
            "MATCH (n:`$label`) WHERE n.source = \$s RETURN count(n) AS c", ['s' => $source]
        )->first()->get('c');

        $this->line('');
        $this->info("Seeded :$label from the rescue export");
        $this->line('  CSV rows read      : ' . number_format($n) . ($skipped ? "  (skipped $skipped)" : ''));
        $this->line('  :' . $label . ' before   : ' . number_format($before));
        $this->line('  :' . $label . ' after    : ' . number_format($after));
        $this->line('  created by rescue  : ' . number_format($after - $before));
        $this->line('  tagged source=' . $source . ' : ' . number_format($rescued));
        return 0;
    }
}
