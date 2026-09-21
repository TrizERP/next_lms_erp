<?php

namespace App\Console\Commands\Brain;

use App\Brain\Support\SchemaCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * What a module's tables actually hold, for one institute and one year.
 *
 * ── WHY THIS IS A COMMAND AND NOT SOMETHING A MODEL ANSWERS ─────────────────
 *
 * Writing an intelligence layer for a module starts with one question: which of
 * its tables genuinely have rows for this institute-year, and are those rows
 * usable? Nobody can answer that by reading the schema, and a model asked to
 * guess will produce a coverage object that looks right and is wrong — which is
 * the single most expensive failure mode here, because `coverage` is what every
 * empty state on the screen keys off.
 *
 * The Result module proved the point on its first run. `result_marks` — the
 * table its name promises is authoritative — holds 12 rows. The real data is in
 * `result_personalize_marks`, at 1.3 million. A generator working from names
 * alone would have built the entire module on the wrong table.
 *
 * ── THE CHECK THAT MATTERS MOST: KEYED vs TEXT-ONLY ─────────────────────────
 *
 * Row count is not usefulness. `result_personalize_marks` holds 160,115 rows
 * for institute 254 and every one of them has `student_id = NULL`,
 * `subject_id = NULL`, `exam_id = NULL` — a legacy import carrying only
 * denormalised text. Institute 47 has the same shape with 0 instead of NULL.
 * Those rows can still be counted and grouped by their text columns, but they
 * cannot be joined to the roll, to a teacher or to the class master, so a
 * screen that offers a per-class drill-down on them is offering something that
 * cannot work.
 *
 * So this command reports, for every id-like column, what share of the rows are
 * NULL or zero, and grades each table:
 *
 *   keyed      — foreign keys are populated; joins are safe.
 *   text-only  — rows exist, keys do not; aggregate on the text columns only.
 *   empty      — no rows for this institute-year.
 *   missing    — the table does not exist in this installation.
 *
 * ── USAGE ───────────────────────────────────────────────────────────────────
 *
 *   php artisan brain:profile-module result --tenant=195 --syear=2022
 *   php artisan brain:profile-module result                      # every tenant
 *   php artisan brain:profile-module --tables='%attendance%' --tenant=195
 *   php artisan brain:profile-module result --tenant=195 --json=storage/result.json
 *
 * The JSON is the generator's input. The table is for the human who has to
 * approve the contract.
 */
class ProfileModule extends Command
{
    protected $signature = 'brain:profile-module
        {module? : Module key from the catalogue below (result, attendance, fees, admissions, library, exam)}
        {--tables= : SQL LIKE pattern(s), comma separated, used instead of the catalogue}
        {--tenant= : sub_institute_id to scope counts to; omit to profile every tenant}
        {--syear= : academic year to scope counts to}
        {--min-rows=1 : hide tables with fewer than this many rows in scope}
        {--deep : also compute distinct counts and date ranges (slower on large tables)}
        {--json= : write the full profile to this path}';

    protected $description = 'Profile which of a module’s tables actually hold usable rows for an institute-year';

    /**
     * Candidate table patterns per module.
     *
     * DELIBERATELY OVER-INCLUSIVE. It is far cheaper to profile a table that
     * turns out to be irrelevant than to miss the one holding the data, which
     * is exactly the mistake `result_marks` invites.
     */
    private const CATALOGUE = [
        'result' => ['%result%', '%marks%', '%grade%', '%exam%'],
        'attendance' => ['%attendance%', '%leave%', '%holiday%'],
        'fees' => ['%fees%', '%fee_%', '%receipt%', '%payment%'],
        'admissions' => ['%admission%', '%enquiry%', '%enrol%'],
        'library' => ['%library%', '%book%', '%issue%'],
        'exam' => ['%exam%', '%question%', '%quiz%'],
        'student' => ['%student%', '%roll%'],
        'hostel' => ['%hostel%', '%room%'],
        'transport' => ['%transport%', '%vehicle%', '%route%'],
    ];

    /** Columns that scope a row to one institute / one year. */
    private const TENANT_COLUMNS = ['sub_institute_id', 'institute_id', 'SubInstituteId'];

    private const YEAR_COLUMNS = ['syear', 'academic_year', 'session_year'];

    public function handle(): int
    {
        $patterns = $this->resolvePatterns();
        if ($patterns === []) {
            $this->error('Give a module from the catalogue ('.implode(', ', array_keys(self::CATALOGUE)).') or --tables=.');

            return self::FAILURE;
        }

        $tenant = $this->option('tenant');
        $syear = $this->option('syear');
        $minRows = (int) $this->option('min-rows');

        $this->line('');
        $this->info('Profiling '.($this->argument('module') ?? 'custom pattern')
            .($tenant ? "  ·  institute {$tenant}" : '  ·  all institutes')
            .($syear ? "  ·  syear {$syear}" : '  ·  all years'));
        $this->line('');

        $tables = $this->candidateTables($patterns);
        $this->line('  '.count($tables).' candidate tables');
        $this->line('');

        $profile = [];
        $rows = [];

        foreach ($tables as $table) {
            $entry = $this->profileTable($table, $tenant, $syear);
            $profile[$table] = $entry;

            if ($entry['rowsInScope'] < $minRows && $entry['verdict'] !== 'missing') {
                continue;
            }

            $rows[] = [
                $table,
                number_format($entry['rowsTotal']),
                number_format($entry['rowsInScope']),
                $entry['tenantColumn'] ?? '—',
                $entry['yearColumn'] ?? '—',
                $entry['verdict'],
                $entry['note'],
            ];
        }

        // Most rows in scope first: the table a module is actually built on is
        // almost always the one with the most rows THAT ARE KEYED, and putting
        // the verdict beside the count is what makes that visible at a glance.
        usort($rows, fn ($a, $b) => (int) str_replace(',', '', $b[2]) <=> (int) str_replace(',', '', $a[2]));

        $this->table(
            ['table', 'rows (all)', 'rows (scope)', 'tenant col', 'year col', 'verdict', 'note'],
            $rows,
        );

        $this->summarise($profile);

        if ($this->option('json')) {
            $path = $this->option('json');
            file_put_contents($path, json_encode([
                'module' => $this->argument('module'),
                'tenant' => $tenant,
                'syear' => $syear,
                'generatedAt' => now()->toIso8601String(),
                'tables' => $profile,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line('');
            $this->info("Profile written to {$path}");
        }

        return self::SUCCESS;
    }

    /** @return string[] */
    private function resolvePatterns(): array
    {
        if ($this->option('tables')) {
            return array_map('trim', explode(',', (string) $this->option('tables')));
        }

        $module = $this->argument('module');

        return $module !== null ? (self::CATALOGUE[$module] ?? []) : [];
    }

    /** @return string[] */
    private function candidateTables(array $patterns): array
    {
        $schema = DB::getDatabaseName();
        $found = [];

        foreach ($patterns as $pattern) {
            $rows = DB::select(
                'SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_name LIKE ?',
                [$schema, $pattern],
            );

            foreach ($rows as $row) {
                // information_schema casing varies by driver version.
                $name = $row->table_name ?? $row->TABLE_NAME;
                $found[$name] = true;
            }
        }

        $tables = array_keys($found);
        sort($tables);

        return $tables;
    }

    private function profileTable(string $table, ?string $tenant, ?string $syear): array
    {
        if (! SchemaCache::hasTable($table)) {
            return [
                'rowsTotal' => 0, 'rowsInScope' => 0, 'tenantColumn' => null, 'yearColumn' => null,
                'verdict' => 'missing', 'note' => 'table not present', 'keyColumns' => [],
            ];
        }

        $tenantColumn = $this->firstPresent($table, self::TENANT_COLUMNS);
        $yearColumn = $this->firstPresent($table, self::YEAR_COLUMNS);

        $rowsTotal = (int) DB::table($table)->count();

        $scoped = DB::table($table);
        if ($tenant !== null && $tenantColumn !== null) {
            $scoped->where($tenantColumn, $tenant);
        }
        if ($syear !== null && $yearColumn !== null) {
            $scoped->where($yearColumn, $syear);
        }
        $rowsInScope = (int) $scoped->count();

        if ($rowsInScope === 0) {
            return [
                'rowsTotal' => $rowsTotal, 'rowsInScope' => 0,
                'tenantColumn' => $tenantColumn, 'yearColumn' => $yearColumn,
                'verdict' => 'empty',
                'note' => $rowsTotal > 0 ? 'has rows, none in this scope' : 'no rows at all',
                'keyColumns' => [],
            ];
        }

        $keyColumns = $this->profileKeyColumns($table, $tenant, $tenantColumn, $syear, $yearColumn, $rowsInScope);

        // A table whose foreign keys are entirely NULL or 0 still holds facts,
        // but none that can be joined. Saying so here is what stops a screen
        // offering a per-class drill-down that cannot resolve a class.
        $verdict = 'keyed';
        $note = '';

        if ($keyColumns !== []) {
            $dead = array_filter($keyColumns, fn ($c) => $c['unusablePercent'] >= 99.0);

            if (count($dead) === count($keyColumns)) {
                $verdict = 'text-only';
                $note = 'all '.count($keyColumns).' id columns are NULL/0 — aggregate on text columns only';
            } elseif ($dead !== []) {
                $verdict = 'partial';
                $note = implode(', ', array_map(fn ($c) => $c['column'].' unusable', $dead));
            }
        }

        // A table with no tenant column cannot be scoped, so its "rows in scope"
        // is really its whole row count. Left as `keyed` it outranks every
        // properly scoped table in the summary — on the Result module that put a
        // 2.4M-row shared table above the 10k rows the institute actually owns.
        // It gets its own verdict so the ranking cannot be fooled by it.
        if ($tenantColumn === null && $tenant !== null) {
            $verdict = 'unscoped';
            $note = 'NO TENANT COLUMN — count is every institute, not this one';
        } elseif ($verdict === 'keyed') {
            $note = $tenantColumn === null ? 'no tenant column (not scoped)' : 'joins are safe';
        }

        $entry = [
            'rowsTotal' => $rowsTotal,
            'rowsInScope' => $rowsInScope,
            'tenantColumn' => $tenantColumn,
            'yearColumn' => $yearColumn,
            'verdict' => $verdict,
            'note' => $note,
            'keyColumns' => $keyColumns,
        ];

        if ($this->option('deep')) {
            $entry['deep'] = $this->profileDeep($table, $tenant, $tenantColumn, $syear, $yearColumn);
        }

        return $entry;
    }

    /**
     * For each id-like column, what share of in-scope rows cannot use it.
     *
     * NULL AND 0 ARE BOTH COUNTED AS UNUSABLE, and that is the load-bearing
     * detail: institute 254 stores the absent key as NULL and institute 47
     * stores it as 0, so a check for only one of them passes half the broken
     * data straight through.
     */
    private function profileKeyColumns(
        string $table,
        ?string $tenant,
        ?string $tenantColumn,
        ?string $syear,
        ?string $yearColumn,
        int $rowsInScope,
    ): array {
        $schema = DB::getDatabaseName();
        $columns = DB::select(
            'SELECT column_name, data_type FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name LIKE ?',
            [$schema, $table, '%\_id'],
        );

        $profiled = [];

        foreach ($columns as $column) {
            $name = $column->column_name ?? $column->COLUMN_NAME;

            if (in_array($name, self::TENANT_COLUMNS, true)) {
                continue;
            }

            $query = DB::table($table);
            if ($tenant !== null && $tenantColumn !== null) {
                $query->where($tenantColumn, $tenant);
            }
            if ($syear !== null && $yearColumn !== null) {
                $query->where($yearColumn, $syear);
            }

            $unusable = (int) $query->where(function ($q) use ($name) {
                $q->whereNull($name)->orWhere($name, 0);
            })->count();

            $profiled[] = [
                'column' => $name,
                'unusable' => $unusable,
                'unusablePercent' => $rowsInScope > 0 ? round($unusable / $rowsInScope * 100, 1) : 0.0,
            ];
        }

        return $profiled;
    }

    private function profileDeep(
        string $table,
        ?string $tenant,
        ?string $tenantColumn,
        ?string $syear,
        ?string $yearColumn,
    ): array {
        $query = DB::table($table);
        if ($tenant !== null && $tenantColumn !== null) {
            $query->where($tenantColumn, $tenant);
        }
        if ($syear !== null && $yearColumn !== null) {
            $query->where($yearColumn, $syear);
        }

        $deep = [];

        foreach (['created_at', 'updated_at', 'created_on', 'exam_date'] as $dateColumn) {
            if (SchemaCache::hasColumn($table, $dateColumn)) {
                $range = (clone $query)
                    ->selectRaw("MIN({$dateColumn}) as first_seen, MAX({$dateColumn}) as last_seen")
                    ->first();
                $deep['dateRange'] = [
                    'column' => $dateColumn,
                    'first' => $range->first_seen ?? null,
                    'last' => $range->last_seen ?? null,
                ];
                break;
            }
        }

        return $deep;
    }

    private function firstPresent(string $table, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (SchemaCache::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The one paragraph a person needs before writing the contract.
     *
     * Names the biggest KEYED table rather than the biggest table, because the
     * biggest table is frequently a legacy import and building on it is the
     * mistake this whole command exists to prevent.
     */
    private function summarise(array $profile): void
    {
        // Only tables that are genuinely scoped to this institute can be the
        // grain. `unscoped` tables are excluded from the ranking entirely.
        $keyed = array_filter(
            $profile,
            fn ($e) => in_array($e['verdict'], ['keyed', 'partial'], true) && $e['tenantColumn'] !== null,
        );
        $textOnly = array_filter($profile, fn ($e) => $e['verdict'] === 'text-only');
        $unscoped = array_filter($profile, fn ($e) => $e['verdict'] === 'unscoped');

        uasort($keyed, fn ($a, $b) => $b['rowsInScope'] <=> $a['rowsInScope']);

        $this->line('');
        $this->info('Summary');

        if ($keyed === []) {
            $this->warn('  No keyed table has rows in this scope. There is nothing to build joins on.');
        } else {
            $primary = array_key_first($keyed);
            $this->line("  Largest keyed table : <fg=green>{$primary}</> ("
                .number_format($keyed[$primary]['rowsInScope']).' rows in scope)');
            $this->line('  That is the grain candidate. Confirm it against the module’s existing report screen');
            $this->line('  before writing the contract — row count alone does not make it authoritative.');
        }

        if ($textOnly !== []) {
            $this->line('');
            $this->warn('  '.count($textOnly).' table(s) hold rows with no usable foreign keys:');
            foreach (array_keys($textOnly) as $table) {
                $this->line("    - {$table}");
            }
            $this->line('  Report these in coverage as present-but-unjoinable rather than hiding them.');
        }

        if ($unscoped !== []) {
            $this->line('');
            $this->warn('  '.count($unscoped).' table(s) have no tenant column and were excluded from the ranking:');
            foreach (array_keys($unscoped) as $table) {
                $this->line("    - {$table}");
            }
            $this->line('  Reading one of these without another scoping join leaks other institutes’ rows.');
        }

        $this->line('');
    }
}
