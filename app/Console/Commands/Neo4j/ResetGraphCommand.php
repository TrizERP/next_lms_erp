<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

/**
 * Phase 3 — wipe & schema reset. THE ONE DESTRUCTIVE STEP.
 *
 * Deletes every node and relationship, drops the old constraints and indexes (which
 * key on the pre-migration convention, defect D2), and creates one `uid` uniqueness
 * constraint per label in the registry.
 *
 * Refuses to run unless the irreplaceable rescue export is present and non-empty:
 * the graph holds 5,521 :Chapter nodes and 86,265 question->chapter edges that exist
 * nowhere in MariaDB, so this command destroys the only other copy.
 *
 * Never touches MariaDB.
 */
class ResetGraphCommand extends Command
{
    protected $signature = 'neo4j:reset-graph
        {--confirm            : required — this DELETES THE ENTIRE GRAPH}
        {--backup=            : folder holding the rescue export (required unless --skip-backup-check)}
        {--skip-backup-check  : dangerous; only when you have verified the backup yourself}
        {--batch=10000        : nodes deleted per transaction}
        {--dry-run            : report what would happen and stop}';
    protected $description = 'Phase 3: wipe the graph and create uid constraints (DESTRUCTIVE)';

    /** Files that cannot be regenerated once the graph is gone. */
    private const RESCUE = [
        'nodes_Chapter.csv', 'nodes_Question.csv',
        'rels_BELONGS_TO.csv', 'rels_HAS_CHAPTER.csv',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if (!$dry && !$this->option('confirm')) {
            $this->error('neo4j:reset-graph DELETES THE ENTIRE GRAPH. Re-run with --confirm.');
            return 1;
        }
        if (!$this->guardBackup($dry)) return 1;

        $client = ClientBuilder::create()
            ->withDriver('x', config('neo4j.uri'),
                Authenticate::basic(config('neo4j.username'), config('neo4j.password')))
            ->build();

        $nodes = (int) $client->run('MATCH (n) RETURN count(n) AS c')->first()->get('c');
        $rels  = (int) $client->run('MATCH ()-[r]->() RETURN count(r) AS c')->first()->get('c');
        $this->line('');
        $this->info('Graph before: ' . number_format($nodes) . ' nodes / ' . number_format($rels) . ' relationships');

        $labels = $this->labels();
        $this->line('Registry declares ' . count($labels) . ' node labels.');

        if ($dry) {
            $this->warn('[DRY RUN] would delete every node and relationship, drop all constraints '
                . 'and indexes, then create ' . count($labels) . ' uid uniqueness constraints.');
            return 0;
        }

        $this->line(str_repeat('─', 70));
        $this->deleteAll($client, $nodes);
        $this->dropOldSchema($client);
        $created = $this->createConstraints($client, $labels);
        $this->line(str_repeat('─', 70));

        return $this->gate($client, $created);
    }

    /** Phase 3 must refuse to run without the backup (runbook §8). */
    private function guardBackup(bool $dry): bool
    {
        if ($this->option('skip-backup-check')) {
            $this->warn('Backup check SKIPPED by flag. The rescue export is unverified.');
            return true;
        }
        $dir = $this->option('backup');
        if (!$dir) {
            $this->error('Pass --backup=<folder> holding the rescue export, or --skip-backup-check.');
            $this->line('Phase 3 destroys the only copy of 5,521 :Chapter nodes absent from MariaDB.');
            return false;
        }
        $missing = [];
        foreach (self::RESCUE as $f) {
            $p = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . $f;
            if (!is_readable($p) || filesize($p) === 0) $missing[] = $f;
        }
        if ($missing) {
            $this->error('REFUSING TO WIPE — rescue export incomplete in ' . $dir);
            foreach ($missing as $f) $this->line("  missing or empty: $f");
            return false;
        }
        $this->info('Rescue export verified in ' . $dir . ' (' . count(self::RESCUE) . ' files).');
        return true;
    }

    /** @return string[] distinct node labels from the registry */
    private function labels(): array
    {
        $out = [];
        foreach (config('neo4j_graph.tables', []) as $e) {
            if (($e['decision'] ?? '') === 'NODE' && !empty($e['label']) && $e['label'] !== 'UNRESOLVED') {
                $out[$e['label']] = true;
            }
        }
        $labels = array_keys($out);
        sort($labels);
        return $labels;
    }

    private function deleteAll($client, int $expected): void
    {
        $batch = max(1000, (int) $this->option('batch'));
        $deleted = 0;
        $this->output->write('  deleting ');
        do {
            $n = (int) $client->run(
                'MATCH (n) WITH n LIMIT $b DETACH DELETE n RETURN count(*) AS c',
                ['b' => $batch]
            )->first()->get('c');
            $deleted += $n;
            if ($n) $this->output->write('.');
        } while ($n > 0);
        $this->line('');
        $this->line('  deleted ' . number_format($deleted) . ' nodes (expected ' . number_format($expected) . ')');
    }

    private function dropOldSchema($client): void
    {
        $dropped = 0;
        foreach ($client->run('SHOW CONSTRAINTS YIELD name RETURN name') as $r) {
            $name = $r->get('name');
            try { $client->run('DROP CONSTRAINT `' . $name . '`'); $dropped++; }
            catch (\Throwable $e) { $this->warn("  could not drop constraint $name: " . substr($e->getMessage(), 0, 80)); }
        }
        $this->line('  dropped ' . $dropped . ' old constraint(s)');

        $di = 0;
        foreach ($client->run('SHOW INDEXES YIELD name, type RETURN name, type') as $r) {
            if ($r->get('type') === 'LOOKUP') continue;   // built-in token lookup, leave it
            $name = $r->get('name');
            try { $client->run('DROP INDEX `' . $name . '`'); $di++; }
            catch (\Throwable $e) { /* constraint-backed indexes vanish with their constraint */ }
        }
        $this->line('  dropped ' . $di . ' old index(es)');
    }

    /** @param string[] $labels */
    private function createConstraints($client, array $labels): int
    {
        $made = 0;
        foreach ($labels as $l) {
            $name = 'uid_' . strtolower(preg_replace('/[^A-Za-z0-9]/', '_', $l));
            try {
                $client->run("CREATE CONSTRAINT `$name` IF NOT EXISTS FOR (n:`$l`) REQUIRE n.uid IS UNIQUE");
                $made++;
            } catch (\Throwable $e) {
                $this->error("  constraint for :$l failed — " . substr($e->getMessage(), 0, 100));
            }
        }
        $this->line('  created ' . $made . ' uid uniqueness constraint(s)');
        return $made;
    }

    private function gate($client, int $expectedConstraints): int
    {
        $nodes = (int) $client->run('MATCH (n) RETURN count(n) AS c')->first()->get('c');
        $rels  = (int) $client->run('MATCH ()-[r]->() RETURN count(r) AS c')->first()->get('c');
        $cons  = 0;
        foreach ($client->run('SHOW CONSTRAINTS YIELD name RETURN count(*) AS c') as $r) $cons = (int) $r->get('c');

        $this->line('');
        $this->info('PHASE 3 GATE');
        $ok1 = $nodes === 0; $ok2 = $rels === 0; $ok3 = $cons === $expectedConstraints && $cons > 0;
        $this->line(sprintf('  MATCH (n) RETURN count(n)        = %-10s %s', number_format($nodes), $ok1 ? 'PASS' : 'FAIL'));
        $this->line(sprintf('  MATCH ()-[r]->() RETURN count(r) = %-10s %s', number_format($rels), $ok2 ? 'PASS' : 'FAIL'));
        $this->line(sprintf('  constraints created              = %-10s %s', $cons, $ok3 ? 'PASS' : 'FAIL'));
        $this->line('');

        if ($ok1 && $ok2 && $ok3) { $this->info('GATE PASSED — graph empty, schema reset.'); return 0; }
        $this->error('GATE FAILED — do not proceed to Phase 4.');
        return 1;
    }
}
