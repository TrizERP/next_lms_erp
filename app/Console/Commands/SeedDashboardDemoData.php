<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills the thin spots of one school's data so the role dashboards have something real-looking to show.
 *
 * Insert-only, and every row it writes carries a tag ("demo-seed-YYYYMMDD") in a column of its own table, so --remove deletes
 * exactly those rows and nothing else. It never changes or deletes an existing row. Dry run unless --apply is given.
 *
 *   php artisan dashboard:seed-demo 1            what it WOULD add
 *   php artisan dashboard:seed-demo 1 --apply    add it
 *   php artisan dashboard:seed-demo 1 --remove   take every tagged row out again
 *
 * What it adds: attendance for every class for the last 14 school days (with some regular absentees, and a few classes left
 * unmarked today), fee receipts spread over the year, admission enquiries for the months that were empty, visitors, upcoming
 * exams and a few complaints. What it deliberately does NOT add: staff departments, payroll and enrolment dates, because those
 * would mean changing existing people's records.
 */
class SeedDashboardDemoData extends Command
{
    protected $signature = 'dashboard:seed-demo {tenant=1 : sub_institute_id} {--apply : write the rows (without this nothing is written)} {--remove : delete every row this command added} {--days=14 : school days of attendance} {--syear= : academic year (default: the one most students are in)}';

    protected $description = 'Add (or remove) tagged demo rows so the role dashboards show complete data';

    private const TAG_PREFIX = 'demo-seed-';

    /** table => [column holding the tag] */
    private const TAGS = [
        'attendance_student' => 'created_by',
        'fees_collect' => 'remarks',
        'admission_enquiry' => 'remarks',
        'visitor_master' => 'visitor_idcard',
        'exam_schedule' => 'file_name',
        'complaint' => 'CREATED_IP',
    ];

    private string $tag;

    private int $tenant;

    private int $syear;

    private bool $apply;

    /** @var array<string, int> */
    private array $counts = [];

    public function handle(): int
    {
        $this->tenant = (int) $this->argument('tenant');
        $this->apply = (bool) $this->option('apply');
        $this->tag = self::TAG_PREFIX.date('Ymd');

        if ($this->option('remove')) {
            return $this->remove();
        }

        // The academic year the school is working in now: the one whose dates include today (as set up in academic_year).
        $this->syear = (int) ($this->option('syear') ?: DB::table('academic_year')->where('sub_institute_id', $this->tenant)
            ->whereDate('start_date', '<=', Carbon::today()->toDateString())->whereDate('end_date', '>=', Carbon::today()->toDateString())->max('syear'));
        if ($this->syear === 0) {
            $this->error('Could not tell the current academic year from academic_year; pass --syear=.');

            return self::FAILURE;
        }
        mt_srand(20261126); // the same school always gets the same data

        $this->line(($this->apply ? 'APPLYING' : 'DRY RUN (nothing is written)')." for school {$this->tenant}, academic year {$this->syear}, tag {$this->tag}");

        $this->attendance();
        $this->feeReceipts();
        $this->enquiries();
        $this->visitors();
        $this->exams();
        $this->complaints();

        $this->newLine();
        foreach ($this->counts as $table => $n) {
            $this->line(sprintf('  %-22s %s %s rows', $table, $this->apply ? 'added' : 'would add', number_format($n)));
        }
        if (! $this->apply) {
            $this->warn('Nothing was written. Run again with --apply to add these rows; --remove takes them out again.');
        }

        return self::SUCCESS;
    }

    private function remove(): int
    {
        foreach (self::TAGS as $table => $column) {
            $q = DB::table($table)->where($column, 'like', self::TAG_PREFIX.'%');
            $n = (clone $q)->count();
            if ($this->apply && $n > 0) {
                $q->delete();
            }
            $this->line(sprintf('  %-22s %s %s rows', $table, $this->apply ? 'removed' : 'would remove', number_format($n)));
        }
        if (! $this->apply) {
            $this->warn('Nothing was deleted. Add --apply to really remove them.');
        }

        return self::SUCCESS;
    }

    /** Insert in chunks, keeping only columns the table really has. */
    private function insert(string $table, array $rows): void
    {
        $this->counts[$table] = ($this->counts[$table] ?? 0) + count($rows);
        if (! $this->apply || $rows === []) {
            return;
        }
        $cols = array_flip(Schema::getColumnListing($table));
        $rows = array_map(fn ($r) => array_intersect_key($r, $cols), $rows);
        foreach (array_chunk($rows, 800) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function pick(array $items)
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function chance(float $p): bool
    {
        return mt_rand() / mt_getrandmax() < $p;
    }

    /** The last N school days (every day but Sunday) up to and including today. */
    private function schoolDays(int $n): array
    {
        $days = [];
        for ($d = Carbon::today(); count($days) < $n; $d = $d->copy()->subDay()) {
            if ($d->dayOfWeek !== Carbon::SUNDAY) {
                $days[] = $d->copy();
            }
        }

        return array_reverse($days);
    }

    /* ------------------------------------------------------------------ attendance */

    private function attendance(): void
    {
        $days = $this->schoolDays((int) $this->option('days'));
        $students = DB::table('tblstudent_enrollment')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->whereNull('end_date')
            ->get(['student_id', 'standard_id', 'section_id']);
        $teacher = DB::table('class_teacher')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)
            ->get(['standard_id', 'division_id', 'teacher_id'])->mapWithKeys(fn ($r) => [$r->standard_id.'-'.$r->division_id => $r->teacher_id])->all();

        $from = $days[0]->toDateString();
        $have = [];
        foreach (DB::table('attendance_student')->where('sub_institute_id', $this->tenant)->where('syear', $this->syear)->whereDate('attendance_date', '>=', $from)->get(['student_id', 'attendance_date']) as $r) {
            $have[$r->student_id.'|'.substr((string) $r->attendance_date, 0, 10)] = true;
        }

        // A few classes have not marked attendance today: that is normal, and it gives "classes yet to mark" something to show.
        $sections = $students->map(fn ($s) => $s->standard_id.'-'.$s->section_id)->unique()->values()->all();
        shuffle($sections);
        $unmarkedToday = array_flip(array_slice($sections, 0, min(3, count($sections))));

        // Each student has their own tendency to be absent: most rarely, some often, a few most days.
        $tendency = [];
        foreach ($students as $s) {
            $r = mt_rand(1, 1000);
            $tendency[$s->student_id] = $r <= 10 ? 0.65 : ($r <= 70 ? 0.16 : 0.03);
        }
        $dayFactor = [];
        foreach ($days as $d) {
            $dayFactor[$d->toDateString()] = ($d->dayOfWeek === Carbon::MONDAY || $d->dayOfWeek === Carbon::SATURDAY ? 1.3 : 1.0) * (0.85 + 0.4 * mt_rand() / mt_getrandmax());
        }

        $now = now()->toDateTimeString();
        $rows = [];
        foreach ($days as $d) {
            $date = $d->toDateString();
            foreach ($students as $s) {
                if (isset($have[$s->student_id.'|'.$date])) {
                    continue;
                }
                $key = $s->standard_id.'-'.$s->section_id;
                if ($date === Carbon::today()->toDateString() && isset($unmarkedToday[$key])) {
                    continue;
                }
                $code = $this->chance(min($tendency[$s->student_id] * $dayFactor[$date], 0.9)) ? ($this->chance(0.2) ? 'L' : 'A') : 'P';
                $rows[] = [
                    'syear' => $this->syear, 'student_id' => $s->student_id, 'term_id' => null, 'attendance_date' => $date, 'attendance_code' => $code,
                    'teacher_id' => $teacher[$s->standard_id.'-'.$s->section_id] ?? null, 'user_group_id' => 1, 'created_on' => $now, 'created_by' => $this->tag,
                    'standard_id' => $s->standard_id, 'section_id' => $s->section_id, 'sub_institute_id' => $this->tenant,
                ];
            }
        }
        $this->insert('attendance_student', $rows);
    }

    /* ------------------------------------------------------------------ fee receipts */

    private function feeReceipts(): void
    {
        $students = DB::table('tblstudent_enrollment as se')->join('standard as s', 's.id', '=', 'se.standard_id')
            ->where('se.sub_institute_id', $this->tenant)->where('se.syear', $this->syear)->whereNull('se.end_date')
            ->get(['se.student_id', 'se.standard_id', 's.sort_order'])->all();
        if ($students === []) {
            return;
        }
        $next = (int) DB::table('fees_collect')->where('sub_institute_id', $this->tenant)->max(DB::raw('CAST(receipt_no AS UNSIGNED)')) + 1;
        $months = [[Carbon::today()->subMonths(5)->startOfMonth(), 0.22], [Carbon::today()->subMonths(4)->startOfMonth(), 0.2], [Carbon::today()->subMonths(3)->startOfMonth(), 0.2],
            [Carbon::today()->subMonths(2)->startOfMonth(), 0.14], [Carbon::today()->subMonths(1)->startOfMonth(), 0.12], [Carbon::today()->startOfMonth(), 0.12]];

        $rows = [];
        $count = 200;
        for ($i = 0; $i < $count; $i++) {
            $roll = mt_rand() / mt_getrandmax();
            $acc = 0.0;
            $start = $months[0][0];
            foreach ($months as [$m, $w]) {
                $acc += $w;
                if ($roll <= $acc) {
                    $start = $m;
                    break;
                }
            }
            $end = $start->copy()->endOfMonth()->min(Carbon::today());
            $date = $start->copy()->addDays(mt_rand(0, max(0, $start->diffInDays($end))));
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                $date->subDay();
            }
            $s = $students[mt_rand(0, count($students) - 1)];
            $amount = (int) (round((1800 + 350 * (int) $s->sort_order) / 100) * 100) + 100 * mt_rand(-4, 6);
            $mode = $this->chance(0.45) ? 'Cash' : ($this->chance(0.7) ? 'Online' : 'Cheque');
            $rows[] = [
                'student_id' => $s->student_id, 'standard_id' => $s->standard_id, 'term_id' => $this->pick([62022, 62022, 62022, 72022, 102022]), 'syear' => $this->syear,
                'sub_institute_id' => $this->tenant, 'receipt_no' => (string) ($next++), 'payment_mode' => $mode, 'receiptdate' => $date->toDateString(), 'amount' => $amount,
                'is_deleted' => 'N', 'remarks' => $this->tag,
            ];
        }
        $this->insert('fees_collect', $rows);
    }

    /* ------------------------------------------------------------------ enquiries */

    private function enquiries(): void
    {
        $classes = DB::table('standard')->where('sub_institute_id', $this->tenant)->whereRaw("name REGEXP '^[0-9]+$'")->pluck('id')->all();
        if ($classes === []) {
            return;
        }
        $sources = ['Newspaper Ad', 'Social Media', 'Website', 'Referral', 'Phone', 'Walk-in'];
        $rows = [];
        for ($i = 1; $i <= 36; $i++) {
            $created = Carbon::today()->subDays(mt_rand(0, 56))->setTime(mt_rand(9, 16), mt_rand(0, 59));
            $follow = $this->chance(0.45) ? Carbon::today()->addDays(mt_rand(0, 9)) : Carbon::today()->subDays(mt_rand(1, 14));
            $rows[] = [
                'enquiry_no' => 'DEMO-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'first_name' => 'Demo', 'last_name' => 'Enquiry '.$i, 'gender' => $this->pick(['M', 'F']),
                'syear' => $this->syear, 'source_of_enquiry' => $this->pick($sources), 'admission_standard' => (string) $this->pick($classes),
                'followup_date' => $follow->toDateString(), 'created_on' => $created->toDateTimeString(), 'sub_institute_id' => $this->tenant, 'remarks' => $this->tag,
            ];
        }
        $this->insert('admission_enquiry', $rows);
    }

    /* ------------------------------------------------------------------ visitors */

    private function visitors(): void
    {
        $purposes = ['Admission enquiry', 'Meet the principal', 'Fee payment', 'Parent meeting', 'Vendor visit', 'Document collection'];
        $from = ['Parent', 'Vendor', 'Guest', 'Alumni', 'Inspector'];
        $rows = [];
        $make = function (Carbon $day, int $n, bool $stillInside) use (&$rows, $purposes, $from) {
            $in = sprintf('%02d:%02d:00', mt_rand(9, 14), mt_rand(0, 59));
            $rows[] = [
                'appointment_type' => 'Walk-in', 'visitor_type' => null, 'name' => 'Demo Visitor '.$n, 'contact' => '0000000000', 'email' => 'demo@example.com',
                'coming_from' => $this->pick($from), 'relation' => 'Visitor', 'purpose' => $this->pick($purposes), 'visitor_idcard' => $this->tag, 'photo' => '',
                'file_size' => '', 'file_type' => '', 'meet_date' => $day->toDateString(), 'in_time' => $in,
                'out_time' => $stillInside ? null : date('H:i:s', strtotime($in) + mt_rand(15, 90) * 60), 'sub_institute_id' => $this->tenant,
            ];
        };
        $n = 0;
        foreach ($this->schoolDays(14) as $d) {
            $isToday = $d->isToday();
            $count = $isToday ? 8 : mt_rand(2, 7);
            for ($i = 0; $i < $count; $i++) {
                $make($d, ++$n, $isToday && $i < 3);
            }
        }
        $this->insert('visitor_master', $rows);
    }

    /* ------------------------------------------------------------------ exams */

    private function exams(): void
    {
        $classes = DB::table('standard')->where('sub_institute_id', $this->tenant)->whereRaw("name REGEXP '^[0-9]+$'")->orderBy('sort_order')->get(['id', 'name']);
        $papers = ['Unit Test 2 - Mathematics', 'Unit Test 2 - English', 'Unit Test 2 - Science', 'Unit Test 2 - Social Science', 'Unit Test 2 - Gujarati'];
        $rows = [];
        foreach ($classes as $k => $c) {
            $rows[] = [
                'syear' => $this->syear, 'standard_id' => $c->id, 'division_id' => null, 'title' => $papers[$k % count($papers)], 'file_name' => $this->tag, 'file_size' => '0',
                'file_type' => 'application/pdf', 'date_' => Carbon::today()->addDays(4 + (($k * 3) % 21))->toDateString(), 'sub_institute_id' => $this->tenant,
                'created_at' => now()->toDateTimeString(),
            ];
        }
        $this->insert('exam_schedule', $rows);
    }

    /* ------------------------------------------------------------------ complaints */

    private function complaints(): void
    {
        $items = [
            ['Bus arrives late in the morning', true], ['Classroom fans not working', true], ['Fee receipt shows wrong date', false],
            ['Playground needs repair', true], ['Library timings too short', false], ['Canteen hygiene', true],
        ];
        $rows = [];
        foreach ($items as $k => [$title, $open]) {
            $date = Carbon::today()->subDays(mt_rand(3, 80));
            $rows[] = [
                'SUB_INSTITUTE_ID' => $this->tenant, 'DATE' => $date->toDateTimeString(), 'TITLE' => $title, 'DESCRIPTION' => 'Demo entry added to complete the sample data.',
                'COMPLAINT_BY' => 'Demo Parent', 'COMPLAINT_SOLUTION' => $open ? null : 'Looked into and resolved.', 'COMPLAINT_SOLUTION_BY' => $open ? null : 'Principal',
                'CREATED_DATE' => $date->toDateTimeString(), 'CREATED_IP' => $this->tag, 'SYEAR' => $this->syear,
            ];
        }
        $this->insert('complaint', $rows);
    }
}
