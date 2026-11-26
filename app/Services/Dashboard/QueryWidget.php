<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * A NEW figure from the school's own data, described in a small fixed form and built by this class.
 *
 * The assistant never writes SQL. It describes what it wants (a table, one measure, one grouping, some filters, an
 * optional period) and everything in that description is checked here before a query is built:
 *   - the table must carry a school column and not be on the blocked list; the query is always limited to the signed-in
 *     user's school (and academic year, where the table has one) and never reads deleted rows;
 *   - every column named must exist and not look personal (names, phones, addresses, ids of documents, passwords, notes);
 *   - at most one lookup join, on columns that exist in both tables, again limited to the same school;
 *   - values are bound, never pasted in; the result is capped, and a result that amounts to a list of individuals is refused.
 * Only leadership roles may use it. The saved widget stores the description, not a query, and says in plain words how the
 * figure is worked out so the user can check it.
 */
class QueryWidget
{
    public const ROLES = ['admin', 'assistant_admin', 'principal', 'vice_principal', 'trustee'];

    private const MAX_ROWS = 30;

    private const TABLE_DENY = '/(password|token|_log$|^log|logs$|audit|session|otp|oauth|failed|jobs|migration|api_key|^ai_|hpbrain|credential|salary|payroll|bank|cache|queue|sms|whatsapp|email_|notification|telescope|webhook|secret|^api_details|gcm_users|^mcp_|form_submit|_bak|backup)/i';

    private const TABLE_DENY_PREFIX = '/^(h5p_|pal_|Z_|10_05|ontology_|gamma_|mobile_|lms_simulation|document_|concept_|learner_|hp_|hpc_|erptour|biomatrix|dynamic_dashboard|custom_module|master_fields|master_setup)/i';

    /** A column that could identify or reach a person, or hold secrets or free text. Never selected, filtered or grouped. */
    private const COLUMN_DENY = '/(password|passwd|pwd|token|secret|otp|api_?key|aadhar|aadhaar|adhar|pan_?(no|number)?$|passport|bank|account_?no|ifsc|iban|mobile|phone|contact|email|address|landmark|pincode|salary|ctc|dob|birth|fcm|device|ip_?address|latitude|longitude|photo|image|signature|(^|_)file(s)?($|_)|path|url|link|remark|comment|description|note|reason|first_?name|middle_?name|last_?name|full_?name|user_?name|father|mother|guardian|spouse|parent_?name)/i';

    /** Tables about people, where even a plain "name" column names a person. */
    private const PERSON_TABLE = '/(user|student|employee|staff|parent|guardian|visitor|enquiry|registration|teacher|person|applicant|alumni|donar|donor)/i';

    /** Tables that hold money, which only roles that already see the fees figures may query. */
    private const MONEY_TABLE = '/^(fees_|imprest|petty_cash|donation|inventory_.*(po|purchase|quotation))/i';

    /* ------------------------------------------------------------------ what exists */

    public static function allowedFor(string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    /** @return array<string, array<int, array{n:string,t:string}>> table => columns (only columns that may be used) */
    private static function schema(): array
    {
        return Cache::remember('dashboard_query_widget_schema_'.DB::getDatabaseName(), 3600, function () {
            $db = DB::getDatabaseName();
            $rows = DB::select('select table_name as t, column_name as c, data_type as d from information_schema.columns where table_schema = ? order by table_name, ordinal_position', [$db]);
            $all = [];
            foreach ($rows as $r) {
                $all[$r->t][] = ['n' => $r->c, 't' => strtolower($r->d)];
            }
            $out = [];
            foreach ($all as $table => $cols) {
                if (preg_match(self::TABLE_DENY, $table) || preg_match(self::TABLE_DENY_PREFIX, $table)) {
                    continue;
                }
                if (! in_array('sub_institute_id', array_column($cols, 'n'), true)) {
                    continue;
                }
                $out[$table] = $cols;
            }

            return $out;
        });
    }

    /** Names of the tables the assistant may be told about, for this role. */
    public static function tableNames(string $role): array
    {
        return array_values(array_filter(array_keys(self::schema()), fn ($t) => ! preg_match(self::MONEY_TABLE, $t) || in_array($role, ['admin', 'assistant_admin', 'principal', 'vice_principal', 'trustee'], true)));
    }

    private static function usable(string $table, array $col): bool
    {
        if (preg_match(self::COLUMN_DENY, $col['n'])) {
            return false;
        }
        if ($col['n'] === 'name' && preg_match(self::PERSON_TABLE, $table)) {
            return false;
        }

        return true;
    }

    /** @return array<string, array{t:string}> column => type, the usable ones only */
    private static function columns(string $table): array
    {
        $out = [];
        foreach (self::schema()[$table] ?? [] as $c) {
            if (self::usable($table, $c)) {
                $out[$c['n']] = ['t' => $c['t']];
            }
        }

        return $out;
    }

    private static function isDate(string $type): bool
    {
        return in_array($type, ['date', 'datetime', 'timestamp'], true);
    }

    private static function isNumber(string $type): bool
    {
        return in_array($type, ['int', 'bigint', 'smallint', 'tinyint', 'mediumint', 'decimal', 'float', 'double', 'numeric'], true);
    }

    /**
     * What the model is shown about the chosen tables: usable columns with their types, and for the few columns that
     * hold a small set of labels (a status, a type, a Y/N flag) the labels themselves, so a filter can be written right.
     */
    public static function describe(array $tables, string $role, int $tenant): string
    {
        $lines = [];
        foreach (array_slice($tables, 0, 3) as $table) {
            if (! in_array($table, self::tableNames($role), true)) {
                continue;
            }
            $cols = self::columns($table);
            $parts = [];
            $probed = 0;
            foreach ($cols as $name => $c) {
                $label = "{$name} ({$c['t']}";
                if (in_array($c['t'], ['varchar', 'char', 'enum'], true) && $probed < 8 && ! preg_match('/name|title|code|number|no$|^id|by$|_by|user|owner|teacher|student|person|staff|employee|assigned|allocated|created|updated|approver|incharge/i', $name)) {
                    $probed++;
                    try {
                        $vals = DB::table($table)->where('sub_institute_id', $tenant)->distinct()->limit(9)->pluck($name)->map(fn ($v) => mb_substr((string) $v, 0, 30))->all();
                        if (count($vals) <= 8 && $vals) {
                            $label .= '; values: '.implode(' | ', $vals);
                        }
                    } catch (Throwable) {
                    }
                }
                $parts[] = $label.')';
            }
            $note = match ($table) {
                'tblstudent' => ' [only students enrolled now are counted, automatically]',
                'tbluser' => ' [only active staff are counted, automatically; a missing department_id means no department recorded]',
                default => '',
            };
            $lines[] = "TABLE {$table}{$note}: ".implode(', ', $parts);
        }

        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------------ checking a description */

    /**
     * Check a description and return it cleaned. Anything outside the fixed form is refused with a sentence to show the user.
     *
     * @throws InvalidArgumentException
     */
    public static function validate(array $q, string $role): array
    {
        if (! self::allowedFor($role)) {
            throw new InvalidArgumentException('Building a new figure from the school data is only available to leadership roles.');
        }
        $table = (string) ($q['table'] ?? '');
        if (! in_array($table, self::tableNames($role), true)) {
            throw new InvalidArgumentException("I can't use \"{$table}\" for a widget.");
        }

        $join = null;
        if (! empty($q['join']) && is_array($q['join'])) {
            $jt = (string) ($q['join']['table'] ?? '');
            if (! in_array($jt, self::tableNames($role), true) || $jt === $table) {
                throw new InvalidArgumentException("I can't join \"{$jt}\" to \"{$table}\".");
            }
            $left = (string) ($q['join']['left'] ?? '');
            $right = (string) ($q['join']['right'] ?? '');
            if (! isset(self::columns($table)[$left]) || ! isset(self::columns($jt)[$right])) {
                throw new InvalidArgumentException('That join uses a column I cannot use.');
            }
            $join = ['table' => $jt, 'left' => $left, 'right' => $right];
        }

        // A column reference is "col" for the main table or "join.col" for the joined one.
        $ref = function (?string $c) use ($table, $join): ?array {
            if ($c === null || $c === '') {
                return null;
            }
            [$alias, $t, $col] = str_contains($c, '.') ? ['b', $join['table'] ?? null, explode('.', $c, 2)[1]] : ['a', $table, $c];
            $cols = $t ? self::columns($t) : [];
            if (! $t || ! isset($cols[$col])) {
                throw new InvalidArgumentException("I can't use the column \"{$c}\".");
            }

            return ['alias' => $alias, 'table' => $t, 'col' => $col, 'type' => $cols[$col]['t']];
        };

        $fn = (string) ($q['measure']['fn'] ?? 'count');
        if (! in_array($fn, ['count', 'count_distinct', 'sum', 'avg', 'min', 'max'], true)) {
            throw new InvalidArgumentException("I can't calculate \"{$fn}\".");
        }
        $measureCol = $ref($q['measure']['column'] ?? null);
        if ($fn !== 'count' && ! $measureCol) {
            throw new InvalidArgumentException('That calculation needs a column.');
        }
        if (in_array($fn, ['sum', 'avg'], true) && ! self::isNumber($measureCol['type'])) {
            throw new InvalidArgumentException('That column is not a number, so it cannot be added or averaged.');
        }

        $group = null;
        if (! empty($q['group']['column'])) {
            $g = $ref((string) $q['group']['column']);
            $bucket = in_array($q['group']['bucket'] ?? null, ['day', 'month', 'year'], true) ? $q['group']['bucket'] : null;
            if ($bucket && ! self::isDate($g['type'])) {
                $bucket = null;
            }
            $group = ['ref' => $g, 'bucket' => $bucket ?? (self::isDate($g['type']) ? 'month' : null)];
        }

        $filters = [];
        foreach (array_slice((array) ($q['filters'] ?? []), 0, 6) as $f) {
            $c = $ref((string) ($f['column'] ?? ''));
            $op = (string) ($f['op'] ?? '=');
            if (! in_array($op, ['=', '!=', '>', '>=', '<', '<=', 'in', 'like', 'is_null', 'not_null', 'between'], true)) {
                throw new InvalidArgumentException("I can't filter with \"{$op}\".");
            }
            $v = $f['value'] ?? null;
            if (in_array($op, ['in', 'between'], true)) {
                $v = array_slice(array_values((array) $v), 0, 20);
                if ($op === 'between' && count($v) !== 2) {
                    throw new InvalidArgumentException('A "between" filter needs two values.');
                }
            } elseif (! in_array($op, ['is_null', 'not_null'], true)) {
                $v = is_scalar($v) ? (string) $v : null;
                if ($v === null) {
                    throw new InvalidArgumentException('A filter is missing its value.');
                }
            }
            $filters[] = ['ref' => $c, 'op' => $op, 'value' => $v];
        }

        $period = null;
        if (! empty($q['period']['column'])) {
            $p = $ref((string) $q['period']['column']);
            if (! self::isDate($p['type'])) {
                throw new InvalidArgumentException('A period needs a date column.');
            }
            $kind = in_array($q['period']['kind'] ?? null, ['last_days', 'this_month', 'this_year', 'today'], true) ? $q['period']['kind'] : 'last_days';
            $period = ['ref' => $p, 'kind' => $kind, 'days' => max(1, min(3650, (int) ($q['period']['days'] ?? 30)))];
        }

        $unit = in_array($q['unit'] ?? null, ['count', 'rupees', 'percent'], true) ? $q['unit'] : 'count';

        return [
            'table' => $table, 'join' => $join,
            'measure' => ['fn' => $fn, 'ref' => $measureCol],
            'group' => $group, 'filters' => $filters, 'period' => $period,
            'all_years' => (bool) ($q['all_years'] ?? false),
            'order' => in_array($q['order'] ?? null, ['asc', 'desc', 'label'], true) ? $q['order'] : null,
            'limit' => max(3, min(self::MAX_ROWS, (int) ($q['limit'] ?? self::MAX_ROWS))),
            'unit' => $unit,
        ];
    }

    /** A saved description, stripped of the resolved column details, so it can be stored and compared. */
    public static function toStored(array $c): array
    {
        $ref = fn (?array $r) => $r ? ($r['alias'] === 'b' ? 'join.' : '').$r['col'] : null;

        return array_filter([
            'table' => $c['table'], 'join' => $c['join'],
            'measure' => ['fn' => $c['measure']['fn'], 'column' => $ref($c['measure']['ref'])],
            'group' => $c['group'] ? ['column' => $ref($c['group']['ref']), 'bucket' => $c['group']['bucket']] : null,
            'filters' => array_map(fn ($f) => ['column' => $ref($f['ref']), 'op' => $f['op'], 'value' => $f['value']], $c['filters']),
            'period' => $c['period'] ? ['column' => $ref($c['period']['ref']), 'kind' => $c['period']['kind'], 'days' => $c['period']['days']] : null,
            'all_years' => $c['all_years'], 'order' => $c['order'], 'limit' => $c['limit'], 'unit' => $c['unit'],
        ], fn ($v) => $v !== null && $v !== [] && $v !== false);
    }

    /** The figure in plain words, so the user can check what it actually counts. */
    public static function explain(array $c): string
    {
        $col = fn (?array $r) => $r ? $r['table'].'.'.$r['col'] : '';
        $m = $c['measure'];
        $what = match ($m['fn']) {
            'count' => 'Counts the rows of '.$c['table'],
            'count_distinct' => 'Counts the different values of '.$col($m['ref']).' in '.$c['table'],
            'sum' => 'Adds up '.$col($m['ref']),
            'avg' => 'Averages '.$col($m['ref']),
            'min' => 'Takes the smallest '.$col($m['ref']),
            'max' => 'Takes the largest '.$col($m['ref']),
        };
        $bits = [$what.' for this school'.($c['all_years'] ? '' : ' (this academic year, where the table has one)').($c['table'] === 'tblstudent' ? ', current students only' : ($c['table'] === 'tbluser' ? ', active staff only' : ''))];
        if ($c['join']) {
            $bits[] = "looked up in {$c['join']['table']} through {$c['join']['left']} = {$c['join']['right']} (rows with no match show as Not recorded)";
        }
        foreach ($c['filters'] as $f) {
            $bits[] = 'where '.$col($f['ref']).' '.str_replace(['is_null', 'not_null'], ['is empty', 'is filled'], $f['op']).(is_array($f['value']) ? ' '.implode(', ', $f['value']) : ($f['value'] !== null ? ' '.$f['value'] : ''));
        }
        if ($c['period']) {
            $bits[] = 'with '.$col($c['period']['ref']).' '.match ($c['period']['kind']) {
                'today' => 'today', 'this_month' => 'in this month', 'this_year' => 'in this year', default => 'in the last '.$c['period']['days'].' days',
            };
        }
        if ($c['group']) {
            $bits[] = 'grouped by '.$col($c['group']['ref']).($c['group']['bucket'] ? ' ('.$c['group']['bucket'].')' : '');
        }

        return implode(', ', $bits).'.';
    }

    /* ------------------------------------------------------------------ running it */

    /**
     * @return array{rows: array<int, array{label:string, value:float|int}>, unit:string, number: array{value:float|int|null, label:string}|null, as_of:?string}
     *
     * @throws InvalidArgumentException when the result would be a list of individuals
     */
    public static function run(array $c, int $tenant, $syear): array
    {
        $g = DB::getQueryGrammar();
        $w = fn (array $r) => $g->wrap($r['alias'].'.'.$r['col']);

        $base = function () use ($c, $tenant, $syear, $w, $g) {
            $q = DB::table($c['table'].' as a')->where('a.sub_institute_id', $tenant);
            $cols = array_column(self::schema()[$c['table']], 'n');
            if (! $c['all_years'] && in_array('syear', $cols, true)) {
                $q->where('a.syear', $syear);
            }
            if (in_array('deleted_at', $cols, true)) {
                $q->whereNull('a.deleted_at');
            }
            // "Students" and "staff" mean the people at school now, as on the Total students / Total staff cards, not everyone ever recorded.
            if ($c['table'] === 'tblstudent') {
                $q->whereIn('a.id', fn ($sub) => $sub->select('student_id')->from('tblstudent_enrollment')->where('sub_institute_id', $tenant)->where('syear', $syear)->whereNull('end_date'));
            }
            if ($c['table'] === 'tbluser' && in_array('status', $cols, true)) {
                $q->where('a.status', '1');
            }
            if ($c['join']) {
                $jcols = array_column(self::schema()[$c['join']['table']], 'n');
                $q->leftJoin($c['join']['table'].' as b', function ($j) use ($c, $tenant) {
                    $j->on('a.'.$c['join']['left'], '=', 'b.'.$c['join']['right'])->where('b.sub_institute_id', '=', $tenant);
                });
                if (in_array('deleted_at', $jcols, true)) {
                    $q->whereNull('b.deleted_at');
                }
            }
            foreach ($c['filters'] as $f) {
                $col = $w($f['ref']);
                $num = self::isNumber($f['ref']['type']);
                $val = fn ($v) => $num ? (float) $v : (string) $v;
                match ($f['op']) {
                    'is_null' => $q->whereRaw("({$col} IS NULL OR {$col} = '')"),
                    'not_null' => $q->whereRaw("({$col} IS NOT NULL AND {$col} <> '')"),
                    'in' => $q->whereIn(DB::raw($col), array_map($val, $f['value'])),
                    'between' => $q->whereBetween(DB::raw($col), [$val($f['value'][0]), $val($f['value'][1])]),
                    'like' => $q->whereRaw("{$col} LIKE ?", ['%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $f['value']).'%']),
                    default => $q->whereRaw("{$col} {$f['op']} ?", [$val($f['value'])]),
                };
            }
            if ($c['period']) {
                $col = $w($c['period']['ref']);
                $today = Carbon::today();
                [$from] = match ($c['period']['kind']) {
                    'today' => [$today->copy()], 'this_month' => [$today->copy()->startOfMonth()], 'this_year' => [$today->copy()->startOfYear()],
                    default => [$today->copy()->subDays($c['period']['days'] - 1)],
                };
                $q->whereRaw("DATE({$col}) >= ?", [$from->toDateString()])->whereRaw("DATE({$col}) <= ?", [$today->toDateString()]);
            }

            return $q;
        };

        $m = $c['measure'];
        $agg = match ($m['fn']) {
            'count' => 'COUNT(*)',
            'count_distinct' => 'COUNT(DISTINCT '.$w($m['ref']).')',
            default => strtoupper($m['fn']).'('.$w($m['ref']).')',
        };

        try {
            DB::statement('SET SESSION max_execution_time = 5000');
        } catch (Throwable) {
        }

        try {
            if (! $c['group']) {
                $v = $base()->selectRaw("{$agg} as v")->value('v');
                $value = $v === null ? null : round((float) $v, 2);

                return ['rows' => $value === null ? [] : [['label' => 'Total', 'value' => $value]], 'unit' => $c['unit'], 'number' => $value === null ? null : ['value' => $value, 'label' => 'Total'], 'as_of' => null];
            }

            $gr = $c['group']['ref'];
            $gc = $w($gr);
            $bucket = $c['group']['bucket'];
            $isGender = ! $bucket && strtolower($gr['col']) === 'gender';
            $expr = $isGender ? "CASE WHEN UPPER(TRIM({$gc})) IN ('M','MALE') THEN 'Male' WHEN UPPER(TRIM({$gc})) IN ('F','FEMALE') THEN 'Female' ELSE 'Not recorded' END" : ($bucket ? 'DATE_FORMAT('.$gc.", '".['day' => '%Y-%m-%d', 'month' => '%Y-%m', 'year' => '%Y'][$bucket]."')" : $gc);
            $q = $base()->selectRaw("{$expr} as k, {$agg} as v");
            if ($bucket) {
                $q->whereRaw("{$gc} > '1900-01-01'");
            }
            $q->groupBy(DB::raw($expr));
            $order = $c['order'] ?? ($bucket ? 'label' : 'desc');
            $limit = $c['limit'];
            $rows = $order === 'label' || $bucket
                ? $q->orderByRaw($bucket ? "k DESC" : 'k ASC')->limit($limit)->get()
                : $q->orderBy('v', $order)->limit($limit)->get();
            if ($bucket) {
                $rows = $rows->reverse()->values();
            }
            $rows = $rows->map(fn ($r) => ['label' => self::label($r->k, $bucket), 'value' => round((float) $r->v, 2)])->values()->all();

            // A result that is really a list of individuals (nearly every group has one row) is refused.
            if (! $bucket && $m['fn'] === 'count' && count($rows) >= 12 && count(array_filter($rows, fn ($r) => $r['value'] <= 1)) >= 0.9 * count($rows)) {
                throw new InvalidArgumentException('That breakdown is too detailed: it would list individuals instead of summarising. Group by something broader, like a class, type or month.');
            }

            // The total is worked out over everything that matched, not over the rows kept for display.
            $total = in_array($m['fn'], ['count', 'sum'], true) && ! $bucket ? round((float) $base()->selectRaw("{$agg} as v")->value('v'), 2) : null;

            return ['rows' => $rows, 'unit' => $c['unit'], 'number' => $rows ? ($total !== null && ! $bucket ? ['value' => $total, 'label' => 'Total'] : ['value' => end($rows)['value'], 'label' => 'Latest: '.end($rows)['label']]) : null, 'as_of' => null];
        } finally {
            try {
                DB::statement('SET SESSION max_execution_time = 0');
            } catch (Throwable) {
            }
        }
    }

    private static function label($k, ?string $bucket): string
    {
        if ($k === null || $k === '') {
            return 'Not recorded';
        }
        if ($bucket === 'month') {
            return Carbon::createFromFormat('Y-m', (string) $k)->format('M Y');
        }
        if ($bucket === 'day') {
            return Carbon::parse((string) $k)->format('d M');
        }

        return mb_substr((string) $k, 0, 40);
    }
}
