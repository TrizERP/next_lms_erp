<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The fixed list of things a user may ask the dashboard assistant to build a widget from.
 *
 * Nothing here is generated: every metric, every way of breaking it down and every chart is written out below, and
 * the assistant can only choose between them. A request for something that is not in this list is refused in plain
 * words, it is never turned into a query. Each metric names the roles that may use it, so a clerk cannot ask for
 * a figure a clerk's dashboard was never given, and every figure is read-only and scoped to the school and year.
 */
class CustomWidgetCatalog
{
    private const LEADERSHIP = ['admin', 'assistant_admin', 'principal', 'vice_principal', 'trustee'];

    private const MONEY = ['admin', 'assistant_admin', 'principal', 'vice_principal', 'trustee', 'clerk', 'assistant_clerk'];

    private const ADMISSIONS = ['admin', 'assistant_admin', 'principal', 'vice_principal', 'trustee', 'clerk', 'assistant_clerk'];

    /**
     * metric => label, unit, roles, dims. A dim says what the rows are, which charts suit it, and what the "number"
     * view means ('total' of the rows, or the 'latest' row).
     */
    public const METRICS = [
        'students' => [
            'label' => 'Students enrolled', 'unit' => 'count', 'roles' => self::LEADERSHIP,
            'keywords' => ['student', 'students', 'strength', 'enrol', 'enrolment', 'enrollment', 'boys', 'girls', 'gender'],
            'dims' => [
                'class' => ['label' => 'by class', 'charts' => ['bar', 'donut', 'number'], 'number' => 'total', 'keywords' => ['class', 'standard', 'grade']],
                'gender' => ['label' => 'by gender', 'charts' => ['donut', 'bar', 'number'], 'number' => 'total', 'keywords' => ['gender', 'boys', 'girls', 'male', 'female']],
            ],
        ],
        'attendance' => [
            'label' => 'Student attendance', 'unit' => 'percent', 'roles' => self::LEADERSHIP,
            'keywords' => ['attendance', 'present', 'absent', 'absence'],
            'dims' => [
                'class' => ['label' => 'by class (latest marked day)', 'charts' => ['bar'], 'number' => 'total', 'keywords' => ['class', 'standard', 'grade']],
                'day' => ['label' => 'day by day (last 14 days marked)', 'charts' => ['line', 'bar', 'number'], 'number' => 'latest', 'keywords' => ['day', 'daily', 'trend', 'week']],
            ],
        ],
        'fees' => [
            'label' => 'Fees collected', 'unit' => 'rupees', 'roles' => self::MONEY,
            'keywords' => ['fee', 'fees', 'collection', 'collected', 'receipt', 'money', 'revenue'],
            'dims' => [
                'month' => ['label' => 'month by month (last 6)', 'charts' => ['bar', 'line', 'number'], 'number' => 'latest', 'keywords' => ['month', 'monthly']],
                'day' => ['label' => 'day by day (last 14 days)', 'charts' => ['line', 'bar', 'number'], 'number' => 'total', 'keywords' => ['day', 'daily']],
            ],
        ],
        'enquiries' => [
            'label' => 'Admission enquiries', 'unit' => 'count', 'roles' => self::ADMISSIONS,
            'keywords' => ['enquiry', 'enquiries', 'inquiry', 'inquiries', 'admission', 'admissions', 'lead', 'leads'],
            'dims' => [
                'month' => ['label' => 'month by month (last 6)', 'charts' => ['bar', 'line', 'number'], 'number' => 'latest', 'keywords' => ['month', 'monthly']],
            ],
        ],
    ];

    public const CHARTS = ['number', 'bar', 'line', 'donut'];

    public const MAX_PER_USER = 12;

    /** What this role may build, in the shape the assistant is shown. */
    public static function forRole(string $role): array
    {
        $out = [];
        foreach (self::METRICS as $key => $m) {
            if (! in_array($role, $m['roles'], true)) {
                continue;
            }
            $out[$key] = [
                'label' => $m['label'], 'unit' => $m['unit'],
                'dims' => array_map(fn ($d) => ['label' => $d['label'], 'charts' => $d['charts']], $m['dims']),
            ];
        }

        return $out;
    }

    /**
     * Check a request against the catalog and return the cleaned spec. Anything not in the catalog, or not allowed
     * for this role, is refused: the assistant's output is never trusted as it comes.
     *
     * @throws InvalidArgumentException with a sentence fit to show the user
     */
    public static function validate(array $spec, string $role): array
    {
        $metric = (string) ($spec['metric'] ?? '');
        if (! isset(self::METRICS[$metric])) {
            throw new InvalidArgumentException("I can't build a widget from \"{$metric}\". I can build from: ".implode(', ', array_column(self::forRole($role), 'label')).'.');
        }
        $def = self::METRICS[$metric];
        if (! in_array($role, $def['roles'], true)) {
            throw new InvalidArgumentException("\"{$def['label']}\" is not part of your role's dashboard, so I can't build a widget from it.");
        }
        $dim = (string) ($spec['dim'] ?? array_key_first($def['dims']));
        if (! isset($def['dims'][$dim])) {
            throw new InvalidArgumentException("\"{$def['label']}\" can be shown ".implode(' or ', array_column($def['dims'], 'label')).'.');
        }
        $charts = $def['dims'][$dim]['charts'];
        $chart = (string) ($spec['chart'] ?? $charts[0]);
        if (! in_array($chart, $charts, true)) {
            $chart = $charts[0];
        }
        $title = trim(strip_tags((string) ($spec['title'] ?? '')));
        if ($title === '') {
            $title = $def['label'].' '.$def['dims'][$dim]['label'];
        }
        $order = in_array($spec['order'] ?? null, ['asc', 'desc'], true) ? $spec['order'] : 'natural';

        return [
            'metric' => $metric, 'dim' => $dim, 'chart' => $chart,
            'title' => mb_substr($title, 0, 60), 'order' => $order,
            'limit' => max(3, min(30, (int) ($spec['limit'] ?? 30))),
        ];
    }

    /**
     * The figures for one saved spec: rows of {label, value}, never invented. An empty `rows` means "no data".
     *
     * @return array{rows: array<int, array{label:string, value:float|int}>, unit:string, number: array{value:float|int|null, label:string}|null, as_of:?string}
     */
    public static function compute(array $spec, int $tenant, $syear): array
    {
        $def = self::METRICS[$spec['metric']];
        $rows = [];
        $asOf = null;

        switch ($spec['metric'].'/'.$spec['dim']) {
            case 'students/class':
                // Same population as the "Total students" card (an enrolment with no student record is not counted).
                foreach (DB::table('tblstudent_enrollment as se')->join('tblstudent as ts', 'ts.id', '=', 'se.student_id')->leftJoin('standard as s', 's.id', '=', 'se.standard_id')
                    ->selectRaw("COALESCE(s.name, 'Class not recorded') as label, COUNT(se.student_id) as value")
                    ->where('se.sub_institute_id', $tenant)->where('se.syear', $syear)->whereNull('se.end_date')
                    ->groupBy('s.id', 's.name', 's.sort_order')->orderByRaw('s.sort_order IS NULL, s.sort_order')->get() as $r) {
                    $rows[] = ['label' => (string) $r->label, 'value' => (int) $r->value];
                }
                break;

            case 'students/gender':
                $buckets = ['Male' => 0, 'Female' => 0, 'Not recorded' => 0];
                foreach (DB::table('tblstudent_enrollment as se')->join('tblstudent as ts', 'ts.id', '=', 'se.student_id')
                    ->where('se.sub_institute_id', $tenant)->where('se.syear', $syear)->whereNull('se.end_date')
                    ->selectRaw('ts.gender as g, COUNT(*) as n')->groupBy('ts.gender')->get() as $r) {
                    $clean = strtolower(preg_replace('/[^A-Za-z]/', '', (string) $r->g));
                    $key = in_array($clean, ['m', 'male'], true) ? 'Male' : (in_array($clean, ['f', 'female'], true) ? 'Female' : 'Not recorded');
                    $buckets[$key] += (int) $r->n;
                }
                foreach ($buckets as $label => $n) {
                    if ($n > 0) {
                        $rows[] = ['label' => $label, 'value' => $n];
                    }
                }
                break;

            case 'attendance/class':
                $day = DB::table('attendance_student')->where('sub_institute_id', $tenant)->where('syear', $syear)
                    ->whereDate('attendance_date', '<=', Carbon::today()->toDateString())
                    ->whereDate('attendance_date', '>=', Carbon::today()->subDays(29)->toDateString())->max('attendance_date');
                if ($day) {
                    $asOf = substr((string) $day, 0, 10);
                    foreach (DB::table('attendance_student as a')->join('standard as s', 's.id', '=', 'a.standard_id')
                        ->where('a.sub_institute_id', $tenant)->where('a.syear', $syear)->whereDate('a.attendance_date', $asOf)
                        ->selectRaw("s.name as label, s.sort_order as so, SUM(a.attendance_code = 'P') as p, SUM(a.attendance_code = 'A') as a")
                        ->groupBy('s.id', 's.name', 's.sort_order')->orderBy('s.sort_order')->get() as $r) {
                        $marked = (int) $r->p + (int) $r->a;
                        if ($marked > 0) {
                            $rows[] = ['label' => (string) $r->label, 'value' => round($r->p / $marked * 100, 1)];
                        }
                    }
                }
                break;

            case 'attendance/day':
                $by = DB::table('attendance_student')->where('sub_institute_id', $tenant)->where('syear', $syear)
                    ->whereDate('attendance_date', '>=', Carbon::today()->subDays(29)->toDateString())->whereDate('attendance_date', '<=', Carbon::today()->toDateString())
                    ->selectRaw("DATE(attendance_date) as d, SUM(attendance_code = 'P') as p, SUM(attendance_code = 'A') as a")->groupBy('d')->orderBy('d')->get();
                // A day nobody marked is left out, not drawn as 0%.
                foreach ($by->filter(fn ($r) => ((int) $r->p + (int) $r->a) > 0)->take(-14) as $r) {
                    $rows[] = ['label' => Carbon::parse($r->d)->format('d M'), 'value' => round($r->p / ((int) $r->p + (int) $r->a) * 100, 1)];
                    $asOf = (string) $r->d;
                }
                break;

            case 'fees/month':
            case 'fees/day':
                $monthly = $spec['dim'] === 'month';
                $from = $monthly ? Carbon::today()->startOfMonth()->subMonthsNoOverflow(5) : Carbon::today()->subDays(13);
                $fmt = $monthly ? '%Y-%m' : '%Y-%m-%d';
                $sum = [];
                $scope = ['sub_institute_id' => $tenant, 'syear' => $syear, 'is_deleted' => 'N'];
                foreach ([['fees_collect', 'amount'], ['fees_paid_other', 'actual_amountpaid']] as [$table, $col]) {
                    foreach (DB::table($table)->where($scope)->whereDate('receiptdate', '>=', $from->toDateString())->whereDate('receiptdate', '<=', Carbon::today()->toDateString())
                        ->selectRaw("DATE_FORMAT(receiptdate, '{$fmt}') as k, SUM({$col}) as v")->groupBy('k')->get() as $r) {
                        $sum[$r->k] = ($sum[$r->k] ?? 0) + (float) $r->v;
                    }
                }
                $rows = self::series($sum, $from, $monthly);
                break;

            case 'enquiries/month':
                $from = Carbon::today()->startOfMonth()->subMonthsNoOverflow(5);
                $sum = [];
                foreach (DB::table('admission_enquiry')->where('sub_institute_id', $tenant)->whereNull('deleted_at')
                    ->whereDate('created_on', '>=', $from->toDateString())->whereDate('created_on', '<=', Carbon::today()->toDateString())
                    ->selectRaw("DATE_FORMAT(created_on, '%Y-%m') as k, COUNT(*) as v")->groupBy('k')->get() as $r) {
                    $sum[$r->k] = (int) $r->v;
                }
                $rows = self::series($sum, $from, true);
                break;
        }

        // Order and trim, for the kinds of rows that have no natural order.
        if (! in_array($spec['dim'], ['month', 'day'], true) && in_array($spec['order'], ['asc', 'desc'], true)) {
            usort($rows, fn ($a, $b) => $spec['order'] === 'asc' ? $a['value'] <=> $b['value'] : $b['value'] <=> $a['value']);
        }
        // The number is worked out from every row, before the list is cut for display, so it matches the full figure.
        $all = $rows;
        $rows = array_slice($rows, 0, $spec['limit']);

        $number = null;
        if ($all) {
            $latest = $def['dims'][$spec['dim']]['number'] === 'latest';
            $number = $latest
                ? ['value' => end($all)['value'], 'label' => 'Latest: '.end($all)['label']]
                : ['value' => $def['unit'] === 'percent' ? round(array_sum(array_column($all, 'value')) / count($all), 1) : array_sum(array_column($all, 'value')), 'label' => $def['unit'] === 'percent' ? 'Average of the classes' : 'Total'];
        }

        return ['rows' => $rows, 'unit' => $def['unit'], 'number' => $number, 'as_of' => $asOf];
    }

    /** A run of months or days with the gaps filled by 0, but only when the school recorded anything in the window at all. */
    private static function series(array $sum, Carbon $from, bool $monthly): array
    {
        if (! $sum) {
            return [];
        }
        $rows = [];
        $cursor = $from->copy();
        $end = Carbon::today();
        while ($cursor->lte($end)) {
            $key = $cursor->format($monthly ? 'Y-m' : 'Y-m-d');
            $rows[] = ['label' => $cursor->format($monthly ? 'M Y' : 'd M'), 'value' => round($sum[$key] ?? 0, 2)];
            $monthly ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        return $rows;
    }
}
