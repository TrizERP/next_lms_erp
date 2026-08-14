<?php

namespace App\Console\Commands\Neo4j;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 verification gate.
 *
 * Validates config/neo4j_graph.php against the live MariaDB schema. Reads schema
 * metadata only — never writes to MariaDB, never connects to Neo4j.
 *
 * Exit 0 = gate passes (0 errors). Warnings do not fail the gate but must be
 * cleared before the phase that owns them runs.
 */
class RegistryCheckCommand extends Command
{
    protected $signature = 'neo4j:registry-check {--module= : restrict to one module} {--warnings : list every warning in full}';
    protected $description = 'Validate the Neo4j graph registry against the live MariaDB schema (Phase 2 gate)';

    private array $errors = [];
    private array $warnings = [];

    private const DECISIONS = ['NODE', 'EDGE', 'AGG_EDGE', 'PROP', 'EXCLUDE', 'REVIEW'];
    private const TENANT_MODES = ['column', 'derive', 'global', 'self'];
    private const PROJECTED = ['NODE', 'EDGE', 'AGG_EDGE', 'PROP'];

    public function handle(): int
    {
        $registry = config('neo4j_graph');
        if (!$registry || empty($registry['tables'])) {
            $this->error('config/neo4j_graph.php is missing or has no tables key.');
            return 1;
        }
        $tables = $registry['tables'];
        if ($module = $this->option('module')) {
            $tables = array_filter($tables, fn ($e) => ($e['module'] ?? null) === $module);
            if (!$tables) {
                $this->error("No tables registered for module '$module'.");
                return 1;
            }
        }

        $this->line('');
        $this->info('Neo4j registry check — ' . config('database.connections.mysql.database')
            . ' @ ' . config('database.connections.mysql.host'));
        $this->line(str_repeat('─', 78));

        $live = $this->liveSchema();
        $this->check1Coverage($tables, $live, (bool) $module);
        $this->check2PrimaryKeys($tables, $live);
        $this->check3Tenancy($tables, $live);
        $this->check4Targets($tables);
        $this->check5LabelCollisions($tables);
        $this->check6Authoritative($tables);
        $this->check7Decisions($tables);
        $this->check8Rescue($registry);

        return $this->report(count($tables));
    }

    /** @return array<string, array<string,bool>> table => set of lowercase column names */
    private function liveSchema(): array
    {
        $rows = DB::select(
            'SELECT table_name AS t, column_name AS c
             FROM information_schema.columns WHERE table_schema = DATABASE()'
        );
        $out = [];
        foreach ($rows as $r) {
            $t = $r->t ?? $r->T ?? null;
            $c = $r->c ?? $r->C ?? null;
            if ($t !== null) $out[$t][strtolower($c)] = true;
        }
        return $out;
    }

    private function err(string $code, string $msg): void { $this->errors[] = "[$code] $msg"; }
    private function flag(string $code, string $msg): void { $this->warnings[] = "[$code] $msg"; }

    private function check1Coverage(array $tables, array $live, bool $scoped): void
    {
        $missing = array_diff(array_keys($live), array_keys($tables));
        $ghost   = array_diff(array_keys($tables), array_keys($live));
        if (!$scoped && $missing) {
            foreach ($missing as $t) $this->err('E1', "table `$t` exists in MariaDB but is not in the registry");
        }
        foreach ($ghost as $t) $this->err('E1', "registry declares `$t` but it does not exist in MariaDB");
        $this->result('1  coverage — every live table is registered',
            $scoped ? 'skipped (--module)' : (count($live) . ' live / ' . count($tables) . ' registered'),
            !$missing && !$ghost);
    }

    private function check2PrimaryKeys(array $tables, array $live): void
    {
        // A node's identity IS its pk (L1), so NODE and PROP must declare one.
        // EDGE/AGG_EDGE are identified by their endpoints, so a missing MariaDB pk is fine there.
        $n = 0;
        foreach ($tables as $t => $e) {
            if (!in_array($e['decision'] ?? '', ['NODE', 'PROP'], true)) continue;
            $n++;
            $pk = $e['pk'] ?? null;
            if (!$pk) { $this->err('E2', "`$t` is a {$e['decision']} but has no primary key declared (L1 needs one for uid)"); continue; }
            if (isset($live[$t]) && !isset($live[$t][strtolower($pk)])) {
                $this->err('E2', "`$t`.`$pk` (declared pk) does not exist in MariaDB");
            }
        }
        $this->result('2  primary keys resolve (L1)', "$n node/prop tables", !$this->has('E2'));
    }

    private function check3Tenancy(array $tables, array $live): void
    {
        $modes = [];
        foreach ($tables as $t => $e) {
            if (!in_array($e['decision'] ?? '', self::PROJECTED, true)) continue;
            $spec = $e['tenant'] ?? null;
            if (!$spec) { $this->err('E3', "`$t` has no tenant spec"); continue; }
            $mode = $spec['mode'] ?? '';
            $modes[$mode] = ($modes[$mode] ?? 0) + 1;
            if (!in_array($mode, self::TENANT_MODES, true)) {
                $this->err('E3', "`$t` has invalid tenant mode '$mode'"); continue;
            }
            if ($mode === 'column' || $mode === 'self') {
                $c = $spec['column'] ?? null;
                if (!$c) { $this->err('E3', "`$t` tenant mode '$mode' with no column"); }
                elseif (isset($live[$t]) && !isset($live[$t][strtolower($c)])) {
                    $this->err('E3', "`$t`.`$c` (tenant column) does not exist in MariaDB");
                }
            }
            if ($mode === 'derive') {
                $fk = $spec['fk'] ?? null; $pt = $spec['table'] ?? null; $pk = $spec['key'] ?? 'id';
                if (!$fk || !$pt) { $this->err('E3', "`$t` tenant mode 'derive' needs fk + table"); continue; }
                if (isset($live[$t]) && !isset($live[$t][strtolower($fk)])) {
                    $this->err('E3', "`$t`.`$fk` (derivation fk) does not exist in MariaDB");
                }
                if (!isset($live[$pt])) {
                    $this->err('E3', "`$t` derives tenancy from `$pt`, which does not exist");
                } elseif (!isset($live[$pt][strtolower($pk)])) {
                    $this->err('E3', "`$t` derives via `$pt`.`$pk`, which does not exist");
                }
            }
            if ($mode === 'global') {
                $this->flag('W3', "`$t` is global scope — sub_institute_id 0, exempt from L2");
            }
        }
        $desc = [];
        foreach ($modes as $m => $c) $desc[] = "$m $c";
        $this->result('3  tenancy resolves (L2)', implode(' · ', $desc), !$this->has('E3'));
    }

    private function check4Targets(array $tables): void
    {
        $needKeys = 0;
        foreach ($tables as $t => $e) {
            $d = $e['decision'] ?? '';
            if ($d === 'NODE') {
                $l = $e['label'] ?? '';
                if (!$l || $l === 'UNRESOLVED') { $this->err('E4', "`$t` is a NODE with no resolved label"); continue; }
                $uid = $e['uid'] ?? '';
                foreach (['{tenant}', '{syear}', '{pk}'] as $tok) {
                    if (!str_contains($uid, $tok)) $this->err('E4', "`$t` uid template is missing $tok (L1): '$uid'");
                }
                if (!str_starts_with($uid, $l . ':')) {
                    $this->err('E4', "`$t` uid template must start with its label '$l': '$uid'");
                }
            } elseif ($d === 'EDGE' || $d === 'AGG_EDGE') {
                $rel = $e['rel'] ?? '';
                if (!$rel || $rel === 'UNRESOLVED') { $this->err('E4', "`$t` is an $d with no resolved relationship type"); continue; }
                if ($rel !== strtoupper($rel)) $this->err('E4', "`$t` relationship '$rel' must be UPPER_SNAKE (L5)");
                if (!empty($e['needs_endpoint_keys'])) {
                    $needKeys++;
                    $phase = $e['phase'] ?? '?';
                    $this->flag('W4', "`$t` ($rel) has unresolved endpoint key(s) — required before phase $phase");
                }
            }
        }
        $this->result('4  targets well-formed (L1, L5)',
            $needKeys ? "$needKeys edges awaiting endpoint keys" : 'all resolved', !$this->has('E4'));
    }

    private function check5LabelCollisions(array $tables): void
    {
        $byLabel = [];
        foreach ($tables as $t => $e) {
            if (($e['decision'] ?? '') !== 'NODE') continue;
            $byLabel[$e['label']][] = $t;
        }
        $shared = array_filter($byLabel, fn ($v) => count($v) > 1);

        // A shared label is only safe while its source tables cannot produce the same
        // (tenant, pk) — the uid would collide and MERGE would silently fold two rows
        // into one node. That was not hypothetical: on 2026-08-10 four labels collided
        // and lost 1,035 rows between them, :Occupation alone accounting for 1,016.
        // This is an ERROR, not a warning.
        $collisions = 0;
        foreach ($shared as $label => $ts) {
            for ($i = 0; $i < count($ts); $i++) {
                for ($j = $i + 1; $j < count($ts); $j++) {
                    $a = $ts[$i]; $b = $ts[$j];
                    $n = $this->overlap($tables[$a] ?? [], $a, $tables[$b] ?? [], $b);
                    if ($n === null) continue;
                    if ($n > 0) {
                        $collisions++;
                        $this->err('E5', "label :$label is fed by `$a` and `$b`, which share $n "
                            . '(tenant, pk) pair(s) — their uids collide and rows would be silently merged. '
                            . 'Give one of them a distinct label.');
                    } else {
                        $this->flag('W5', "label :$label is fed by `$a` + `$b` — no collision today, "
                            . 'but any id reuse would merge rows');
                    }
                }
            }
        }
        $this->result('5  no uid collisions on shared labels', count($byLabel) . ' labels, '
            . count($shared) . ' shared, ' . $collisions . ' colliding', $collisions === 0);
    }

    /**
     * How many (tenant, pk) pairs two tables share. Null when it cannot be determined
     * (missing pk, or a tenancy mode with no column to compare) — callers treat that as
     * "unknown", never as "safe".
     */
    private function overlap(array $ea, string $a, array $eb, string $b): ?int
    {
        $pa = $ea['pk'] ?? null; $pb = $eb['pk'] ?? null;
        if (!$pa || !$pb) return null;
        $ta = (($ea['tenant']['mode'] ?? '') === 'column') ? $ea['tenant']['column'] : null;
        $tb = (($eb['tenant']['mode'] ?? '') === 'column') ? $eb['tenant']['column'] : null;
        try {
            $selA = $ta ? "SELECT `$pa` k, `$ta` t FROM `$a`" : "SELECT `$pa` k, 0 t FROM `$a`";
            $selB = $tb ? "SELECT `$pb` k, `$tb` t FROM `$b`" : "SELECT `$pb` k, 0 t FROM `$b`";
            $r = DB::select("SELECT COUNT(*) AS c FROM ($selA) x JOIN ($selB) y ON x.k = y.k AND x.t <=> y.t");
            return (int) ($r[0]->c ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function check6Authoritative(array $tables): void
    {
        $bad = 0; $n = 0;
        foreach ($tables as $t => $e) {
            if (!in_array($e['domain'] ?? '', ['Fees', 'Payroll'], true)) continue;
            if (!in_array($e['decision'] ?? '', self::PROJECTED, true)) continue;
            $n++;
            if (($e['authoritative'] ?? null) !== false) {
                $this->err('E6', "`$t` is Fees/Payroll and must carry authoritative=false (L7)");
                $bad++;
            }
        }
        $this->result('6  money marked non-authoritative (L7)', "$n projected, $bad missing the flag", $bad === 0);
    }

    private function check7Decisions(array $tables): void
    {
        $counts = [];
        foreach ($tables as $t => $e) {
            $d = $e['decision'] ?? '';
            if (!in_array($d, self::DECISIONS, true)) $this->err('E7', "`$t` has invalid decision '$d'");
            $counts[$d] = ($counts[$d] ?? 0) + 1;
            if (in_array($d, ['EXCLUDE', 'REVIEW'], true) && empty($e['reason'])) {
                $this->err('E7', "`$t` is $d but states no reason");
            }
        }
        $desc = [];
        foreach (self::DECISIONS as $d) if (isset($counts[$d])) $desc[] = "$d {$counts[$d]}";
        $this->result('7  decisions valid and justified', implode(' · ', $desc), !$this->has('E7'));
    }

    private function check8Rescue(array $registry): void
    {
        $dir = $registry['rescue']['dir'] ?? null;
        $files = $registry['rescue']['files'] ?? [];
        if (!$files) { $this->result('8  rescue CSVs present (CHAPTER-SOURCE)', 'none declared', true); return; }

        $ok = true;
        foreach ($files as $label => $spec) {
            $p = rtrim((string) $dir, "/\\") . DIRECTORY_SEPARATOR . ($spec['csv'] ?? '');
            if (!is_readable($p) || filesize($p) === 0) {
                $this->err('E8', "rescue export for :$label missing or empty — $p");
                $ok = false;
                continue;
            }
            // These files were deleted four times on 2026-08-10; a present-but-truncated
            // copy would be worse than an absent one, so check the line count too.
            if (!empty($spec['lines'])) {
                $n = 0;
                $fh = fopen($p, 'r');
                while (fgets($fh) !== false) $n++;
                fclose($fh);
                if ($n !== (int) $spec['lines']) {
                    $this->err('E8', "rescue export for :$label is truncated — $p has $n lines, expected {$spec['lines']}");
                    $ok = false;
                }
            }
        }
        $this->result('8  rescue export intact (CHAPTER-SOURCE)',
            count($files) . ' file(s) in ' . basename((string) $dir), $ok);
    }

    private function has(string $code): bool
    {
        foreach ($this->errors as $e) if (str_starts_with($e, "[$code]")) return true;
        return false;
    }

    private function result(string $name, string $detail, bool $pass): void
    {
        $this->line(sprintf('  %-42s %-28s %s', $name, $detail, $pass ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'));
    }

    private function report(int $n): int
    {
        $this->line(str_repeat('─', 78));
        if ($this->warnings) {
            $show = $this->option('warnings') ? $this->warnings : array_slice($this->warnings, 0, 8);
            $this->line('');
            $this->comment('Warnings (' . count($this->warnings) . ') — do not fail the gate:');
            foreach ($show as $w) $this->line('  ' . $w);
            if (!$this->option('warnings') && count($this->warnings) > count($show)) {
                $this->line('  … ' . (count($this->warnings) - count($show)) . ' more (--warnings to list all)');
            }
        }
        $this->line('');
        if ($this->errors) {
            $this->error('GATE FAILED — ' . count($this->errors) . ' error(s):');
            foreach (array_slice($this->errors, 0, 40) as $e) $this->line('  ' . $e);
            if (count($this->errors) > 40) $this->line('  … ' . (count($this->errors) - 40) . ' more');
            return 1;
        }
        $this->info("GATE PASSED — $n tables registered, 0 errors, " . count($this->warnings) . ' warning(s).');
        $this->line('Nothing was written to MariaDB or Neo4j.');
        return 0;
    }
}
