<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The timetable, as the `timetable` table records it.
 *
 * ONE ROW IS ONE PERIOD OF ONE CLASS ON ONE WEEKDAY
 *
 * `timetable` carries the academic section, standard, division, batch, period, subject,
 * teacher and `week_day`, scoped by institute and academic year. A week for one class is
 * therefore period-count × weekday-count rows, and the whole school is a hundred thousand
 * of them — which is why every read here is filtered and counted before it is limited.
 *
 * `create_timetable` IS THE DRAFT, `timetable` IS THE PUBLISHED ONE
 *
 * The two have identical columns and the builder screen writes the first. Only `timetable`
 * is read here: a draft somebody is halfway through arranging is not the school's schedule,
 * and reporting one as if it were is how a teacher is told to be in a room they were never
 * assigned to.
 *
 * CONFLICTS ARE COUNTED, NOT JUDGED
 *
 * `conflicts()` finds one real, checkable thing: a teacher booked into two different
 * classes in the same period on the same weekday. That is derivable from the table with
 * certainty. It does not judge workload, fairness or gaps — the table records no room, no
 * teacher availability and no preference, so nothing here can say a timetable is good or
 * bad, only that it asks one person to be in two places.
 *
 * SCOPING
 *
 * `sub_institute_id` and `syear` from the caller's token on every read. The lookups —
 * period, subject, standard, division, academic section — are joined on institute too
 * where they carry it, so a renamed class in another school can never supply a name here.
 */
class TimetableService
{
    /** `week_day` as the table stores it, for reporting a name beside the number. */
    private const WEEKDAYS = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    ];

    /**
     * The published schedule, in weekday and period order.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function schedule(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('timetable')) {
            return ['count' => 0, 'periods' => [], 'note' => 'A timetable is not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        foreach ([
            'standard_id' => 't.standard_id',
            'division_id' => 't.division_id',
            'teacher_id' => 't.teacher_id',
            'subject_id' => 't.subject_id',
            'period_id' => 't.period_id',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        $weekday = $this->weekday($filters['week_day'] ?? null);

        if ($weekday !== null) {
            $query->where('t.week_day', $weekday);
        }

        // Counted before the limit. A class week is forty-odd rows and the school is a
        // hundred thousand, so a page read as a total would be wrong by three orders.
        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderBy('t.week_day')
            ->orderBy('p.sort_order')
            ->orderBy('std.name')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'periods' => $rows->map(fn ($row) => $this->map($row))->all(),
            'rule' => 'One row is one period of one class on one weekday, read from the published '
                .'timetable. Drafts in `create_timetable` are deliberately not included.',
        ];
    }

    /**
     * Teachers booked into two different classes in the same period on the same weekday.
     *
     * The one conflict this table can prove. A row pairs with another row only when the
     * teacher, the weekday and the period all match and the class differs — a teacher
     * taking two divisions together is recorded by the `merge` column and is not a clash,
     * so rows sharing a class are excluded.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function conflicts(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('timetable')) {
            return ['count' => 0, 'conflicts' => [], 'note' => 'A timetable is not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        // Grouped rather than self-joined: a teacher in three classes at once is one
        // finding with three bookings, and a self-join would report it as three findings.
        $query = $this->query($context)
            ->whereNotNull('t.teacher_id')
            ->where('t.teacher_id', '>', 0)
            ->selectRaw(
                "t.teacher_id, t.week_day, t.period_id,
                 COUNT(DISTINCT CONCAT_WS('-', t.standard_id, t.division_id)) AS classes,
                 COUNT(*) AS bookings,
                 p.title AS period_title, p.start_time, p.end_time,
                 CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS teacher_name,
                 GROUP_CONCAT(DISTINCT CONCAT_WS(' / ', std.name, div.name) ORDER BY std.name SEPARATOR ' | ') AS class_list,
                 GROUP_CONCAT(DISTINCT sub.subject_name ORDER BY sub.subject_name SEPARATOR ', ') AS subjects"
            )
            ->groupBy('t.teacher_id', 't.week_day', 't.period_id', 'p.title', 'p.start_time', 'p.end_time',
                'u.first_name', 'u.middle_name', 'u.last_name')
            ->havingRaw('COUNT(DISTINCT CONCAT_WS(?, t.standard_id, t.division_id)) > 1', ['-']);

        if (! empty($filters['teacher_id'])) {
            $query->where('t.teacher_id', (int) $filters['teacher_id']);
        }

        $weekday = $this->weekday($filters['week_day'] ?? null);

        if ($weekday !== null) {
            $query->where('t.week_day', $weekday);
        }

        $rows = $query->orderBy('t.week_day')->orderBy('t.period_id')->limit($limit)->get();

        return [
            'count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'conflicts' => $rows->map(static fn ($row) => [
                'teacher_id' => (int) $row->teacher_id,
                'teacher_name' => trim((string) $row->teacher_name) ?: null,
                'week_day' => (int) $row->week_day,
                'week_day_name' => self::WEEKDAYS[(int) $row->week_day] ?? null,
                'period_id' => (int) $row->period_id,
                'period' => $row->period_title,
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
                'classes_at_once' => (int) $row->classes,
                'bookings' => (int) $row->bookings,
                'classes' => $row->class_list,
                'subjects' => $row->subjects,
            ])->all(),
            'rule' => 'A conflict is one teacher booked into two or more DIFFERENT classes in the same '
                .'period on the same weekday. Two rows for the same class are a merged or split session, '
                .'not a clash. The table records no room and no teacher availability, so nothing else '
                .'about a timetable is judged here.',
        ];
    }

    /** A weekday number the table would recognise, or null for every day. */
    private function weekday(mixed $value): ?int
    {
        $given = trim((string) ($value ?? ''));

        if ($given === '') {
            return null;
        }

        if (ctype_digit($given)) {
            $day = (int) $given;

            return isset(self::WEEKDAYS[$day]) ? $day : null;
        }

        foreach (self::WEEKDAYS as $number => $name) {
            if (strcasecmp($name, $given) === 0) {
                return $number;
            }
        }

        return null;
    }

    /**
     * The timetable join, scoped at every hop that carries an institute.
     *
     * `subject` and `tbluser` are scoped too: a subject or a teacher belonging to another
     * school must never supply a name onto this school's schedule, even if an id collides.
     */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('timetable as t')
            ->leftJoin('period as p', function ($join) use ($institute) {
                $join->on('p.id', '=', 't.period_id')->where('p.sub_institute_id', '=', $institute);
            })
            ->leftJoin('subject as sub', function ($join) use ($institute) {
                $join->on('sub.id', '=', 't.subject_id')->where('sub.sub_institute_id', '=', $institute);
            })
            ->leftJoin('standard as std', function ($join) use ($institute) {
                $join->on('std.id', '=', 't.standard_id')->where('std.sub_institute_id', '=', $institute);
            })
            ->leftJoin('division as div', function ($join) use ($institute) {
                $join->on('div.id', '=', 't.division_id')->where('div.sub_institute_id', '=', $institute);
            })
            ->leftJoin('academic_section as sec', function ($join) use ($institute) {
                $join->on('sec.id', '=', 't.academic_section_id')->where('sec.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 't.teacher_id')->where('u.sub_institute_id', '=', $institute);
            })
            ->where('t.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            $query->where('t.syear', $context->academicYear);
        }

        return $query;
    }

    private function columns(): string
    {
        return "t.id, t.week_day, t.period_id, t.standard_id, t.division_id, t.subject_id,
                t.teacher_id, t.batch_id, t.merge,
                p.title AS period_title, p.start_time, p.end_time, p.sort_order,
                sub.subject_name, sub.subject_code,
                std.name AS standard_name, div.name AS division_name, sec.title AS section_title,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS teacher_name";
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        return [
            'entry_id' => (int) $row->id,
            'week_day' => (int) $row->week_day,
            'week_day_name' => self::WEEKDAYS[(int) $row->week_day] ?? null,
            'period_id' => $row->period_id === null ? null : (int) $row->period_id,
            'period' => $row->period_title,
            'start_time' => $row->start_time,
            'end_time' => $row->end_time,
            'section' => $row->section_title,
            'standard_id' => $row->standard_id === null ? null : (int) $row->standard_id,
            'standard_name' => $row->standard_name,
            'division_id' => $row->division_id === null ? null : (int) $row->division_id,
            'division_name' => $row->division_name,
            'subject_id' => $row->subject_id === null ? null : (int) $row->subject_id,
            'subject_name' => $row->subject_name,
            'subject_code' => $row->subject_code,
            'teacher_id' => $row->teacher_id === null ? null : (int) $row->teacher_id,
            // Null when the teacher is not a user of this institute — a record to correct,
            // not a name to borrow from elsewhere.
            'teacher_name' => trim((string) $row->teacher_name) ?: null,
            'batch_id' => $row->batch_id === null ? null : (int) $row->batch_id,
            'merged_session' => ! empty($row->merge),
        ];
    }
}
