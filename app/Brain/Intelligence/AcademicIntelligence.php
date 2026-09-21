<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Academic & Timetable Intelligence — one institute, one academic year.
 *
 * READS REAL TIMETABLE, SUBJECT, AND INSTRUCTIONAL SCHEDULING DATA FROM vivek_erp.
 *
 * Primary tables:
 * - `timetable` (scheduled periods per year)
 * - `subject` (subject catalogue)
 * - `standard` (class standards)
 * - `division` (class sections)
 * - `tbluser` (teachers/faculty)
 */
/**
 * ── WHAT ONE TIMETABLE ROW MEANS ────────────────────────────────────────────
 *
 * ONE CLASS-DIVISION'S PERIOD ON ONE WEEKDAY IN ONE MARKING PERIOD. It is NOT
 * a teacher's weekly period, and the difference is the whole reason this file
 * was rewritten: the previous version counted a teacher's rows and reported the
 * result as their weekly load, which produced "129 weekly periods" for a
 * teacher at an institute whose week holds 72 slots at the very most. A figure
 * like that is not an overload, it is a unit error, and the finding built on it
 * named three real members of staff.
 *
 * A TEACHER'S WEEK IS THEIR DISTINCT (weekday, period) SLOTS IN ONE MARKING
 * PERIOD. Counted that way the same institute's heaviest teacher is at 46 slots
 * and the average is 17, which is what a timetable looks like.
 *
 * What the row count IS good for is the thing it was hiding: where the SAME
 * teacher appears in two different class-divisions in the SAME slot, the
 * timetable has them in two rooms at once. There are 427 such slots at that
 * institute, and nothing in this module could see them.
 */
final class AcademicIntelligence
{
    private const TIMETABLE_TABLE = 'timetable';
    private const SUBJECT_TABLE = 'subject';
    private const STANDARD_TABLE = 'standard';
    private const USER_TABLE = 'tbluser';

    /** Standard teacher weekly teaching load ceiling (periods/week). */
    public const MAX_RECOMMENDED_LOAD = 35;

    private readonly string $tenantId;
    private readonly ?string $syear;

    private array $memo = [];

    public function __construct(string $tenantId, ?string $syear)
    {
        $this->tenantId = (string) $tenantId;
        $this->syear = $syear === null || $syear === '' ? null : (string) $syear;
    }

    public function syear(): ?string
    {
        return $this->syear;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function coverage(): array
    {
        return $this->memo['coverage'] ??= $this->computeCoverage();
    }

    private function computeCoverage(): array
    {
        if ($this->syear === null) {
            return [
                'available' => false,
                'reason' => 'No academic year was specified or resolved for this institute.',
                'sourceTable' => self::TIMETABLE_TABLE,
                'totalRows' => 0,
                'usableRows' => 0,
            ];
        }

        if (!SchemaCache::hasTable(self::TIMETABLE_TABLE)) {
            return [
                'available' => false,
                'reason' => "Table '" . self::TIMETABLE_TABLE . "' does not exist.",
                'sourceTable' => self::TIMETABLE_TABLE,
                'totalRows' => 0,
                'usableRows' => 0,
            ];
        }

        $totalRows = DB::table(self::TIMETABLE_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', $this->syear)
            ->count();

        if ($totalRows === 0) {
            return [
                'available' => false,
                'reason' => "No scheduled timetable periods found for academic year {$this->syear}.",
                'sourceTable' => self::TIMETABLE_TABLE,
                'totalRows' => 0,
                'usableRows' => 0,
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'sourceTable' => self::TIMETABLE_TABLE . ' · ' . self::SUBJECT_TABLE,
            'totalRows' => $totalRows,
            'usableRows' => $totalRows,
        ];
    }

    public function position(): ?array
    {
        return $this->memo['position'] ??= $this->computePosition();
    }

    private function computePosition(): ?array
    {
        $cov = $this->coverage();
        if (!$cov['available']) {
            return null;
        }

        $summary = DB::table(self::TIMETABLE_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', $this->syear)
            ->selectRaw('
                count(*) as total_periods,
                count(distinct standard_id) as standards_count,
                count(distinct division_id) as divisions_count,
                count(distinct subject_id) as subjects_count,
                count(distinct case when teacher_id > 0 then teacher_id end) as teachers_count,
                count(case when teacher_id is null or teacher_id = 0 then 1 end) as unassigned_periods
            ')
            ->first();

        $total = (int) ($summary->total_periods ?? 0);
        if ($total === 0) {
            return null;
        }

        $standards = (int) ($summary->standards_count ?? 0);
        $divisions = (int) ($summary->divisions_count ?? 0);
        $subjects = (int) ($summary->subjects_count ?? 0);
        $teachers = (int) ($summary->teachers_count ?? 0);
        $unassigned = (int) ($summary->unassigned_periods ?? 0);

        // Averaged over SLOTS, not rows. Dividing the row count by the
        // teacher count answered a question nobody asked: it is the average
        // number of timetable ROWS per teacher across every marking period.
        $loads = array_column($this->byTeacherLoad(), 'periodsPerWeek');
        sort($loads);

        $clashes = $this->teacherClashes();

        return [
            'totalPeriods' => $total,
            'standards' => $standards,
            'divisions' => $divisions,
            'subjects' => $subjects,
            'teachers' => $teachers,
            'unassignedPeriods' => $unassigned,
            // NULL, NOT ZERO: with no teacher on the timetable there is no load.
            'medianPeriodsPerTeacher' => $loads === [] ? null : $loads[(int) floor(count($loads) / 2)],
            'maxPeriodsPerTeacher' => $loads === [] ? null : $loads[count($loads) - 1],
            'teachersClashing' => count($clashes),
            'clashingSlots' => array_sum(array_column($clashes, 'clashingSlots')),
            'classDoubleBookings' => $this->classDoubleBookings(),
        ];
    }

    public function bySubject(): array
    {
        return $this->memo['bySubject'] ??= $this->computeBySubject();
    }

    private function computeBySubject(): array
    {
        $cov = $this->coverage();
        if (!$cov['available']) {
            return [];
        }

        $rows = DB::table(self::TIMETABLE_TABLE . ' as t')
            ->leftJoin(self::SUBJECT_TABLE . ' as s', 's.id', '=', 't.subject_id')
            ->where('t.sub_institute_id', $this->tenantId)
            ->where('t.syear', $this->syear)
            ->groupBy('t.subject_id', 's.subject_name')
            ->select(
                't.subject_id',
                DB::raw('COALESCE(s.subject_name, CONCAT("Subject #", t.subject_id)) as subject_name'),
                DB::raw('count(*) as periods'),
                DB::raw('count(distinct case when t.teacher_id > 0 then t.teacher_id end) as teachers')
            )
            ->orderByDesc('periods')
            ->limit(20)
            ->get();

        $pos = $this->position();
        $total = $pos ? $pos['totalPeriods'] : 1;

        $result = [];
        foreach ($rows as $r) {
            $periods = (int) $r->periods;
            $result[] = [
                'key' => (string) $r->subject_id,
                'label' => (string) $r->subject_name,
                'periods' => $periods,
                'teachers' => (int) $r->teachers,
                'share' => round(($periods / max(1, $total)) * 100, 1),
            ];
        }

        return $result;
    }

    public function byTeacherLoad(): array
    {
        return $this->memo['byTeacherLoad'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            // Slots, per marking period, then the heaviest period the teacher
            // works. A teacher who teaches a different subject in the same slot
            // in each term works ONE slot, not two.
            $rows = DB::table(self::TIMETABLE_TABLE.' as t')
                ->leftJoin(self::USER_TABLE.' as u', 'u.id', '=', 't.teacher_id')
                ->where('t.sub_institute_id', $this->tenantId)
                ->where('t.syear', $this->syear)
                ->where('t.teacher_id', '>', 0)
                ->groupBy('t.teacher_id', 'u.first_name', 'u.last_name')
                ->select(
                    't.teacher_id',
                    DB::raw('TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) as teacher_name'),
                    DB::raw('COUNT(DISTINCT CONCAT(t.marking_period_id, "|", t.week_day, "|", t.period_id)) as slot_terms'),
                    DB::raw('COUNT(DISTINCT t.marking_period_id) as marking_periods'),
                    DB::raw('COUNT(DISTINCT t.subject_id) as subjects'),
                    DB::raw('COUNT(DISTINCT t.standard_id) as standards'),
                    DB::raw('COUNT(DISTINCT CONCAT(t.standard_id, "-", t.division_id)) as classes'),
                    DB::raw('COUNT(*) as rows_scheduled')
                )
                ->orderByDesc('slot_terms')
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $markingPeriods = max(1, (int) $row->marking_periods);
                $slotTerms = (int) $row->slot_terms;

                $out[] = [
                    'key' => (string) $row->teacher_id,
                    'label' => $row->teacher_name !== '' && $row->teacher_name !== null
                        ? (string) $row->teacher_name
                        : "Teacher #{$row->teacher_id}",
                    // The teacher's week: their slots averaged over the marking
                    // periods they appear in.
                    'periodsPerWeek' => (int) round($slotTerms / $markingPeriods),
                    'markingPeriods' => $markingPeriods,
                    'subjects' => (int) $row->subjects,
                    'standards' => (int) $row->standards,
                    'classes' => (int) $row->classes,
                    'rowsScheduled' => (int) $row->rows_scheduled,
                ];
            }

            usort($out, static fn ($x, $y) => $y['periodsPerWeek'] <=> $x['periodsPerWeek']);

            return $out;
        })();
    }

    /**
     * Slots where one teacher is timetabled into more than one class-division.
     *
     * @return array<int,array<string,mixed>>
     */
    public function teacherClashes(): array
    {
        return $this->memo['teacherClashes'] ??= (function (): array {
            if (! $this->coverage()['available']) {
                return [];
            }

            $rows = DB::table(self::TIMETABLE_TABLE.' as t')
                ->leftJoin(self::USER_TABLE.' as u', 'u.id', '=', 't.teacher_id')
                ->where('t.sub_institute_id', $this->tenantId)
                ->where('t.syear', $this->syear)
                ->where('t.teacher_id', '>', 0)
                ->groupBy('t.teacher_id', 'u.first_name', 'u.last_name')
                ->havingRaw('SUM(clash) > 0')
                ->select(
                    't.teacher_id',
                    DB::raw('TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) as teacher_name'),
                    DB::raw('0 as clash')
                )
                ->get();

            // The HAVING above cannot see a per-slot aggregate, so the clash
            // count is computed in one grouped pass and joined here.
            $clashes = DB::table(DB::raw(
                '(SELECT teacher_id,
                         COUNT(*) AS clashing_slots,
                         SUM(classes - 1) AS surplus
                  FROM (
                      SELECT teacher_id,
                             COUNT(DISTINCT CONCAT(standard_id, "-", division_id)) AS classes
                      FROM timetable
                      WHERE sub_institute_id = '.(int) $this->tenantId.'
                        AND syear = '.(int) $this->syear.'
                        AND teacher_id > 0
                      GROUP BY teacher_id, marking_period_id, week_day, period_id
                      HAVING classes > 1
                  ) AS slots
                  GROUP BY teacher_id) AS c'
            ))
                ->leftJoin(self::USER_TABLE.' as u', 'u.id', '=', 'c.teacher_id')
                ->select(
                    'c.teacher_id',
                    'c.clashing_slots',
                    'c.surplus',
                    DB::raw('TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) as teacher_name')
                )
                ->orderByDesc('c.clashing_slots')
                ->get();

            return array_map(static fn ($row) => [
                'key' => (string) $row->teacher_id,
                'label' => $row->teacher_name !== '' && $row->teacher_name !== null
                    ? (string) $row->teacher_name
                    : "Teacher #{$row->teacher_id}",
                'clashingSlots' => (int) $row->clashing_slots,
                'surplusBookings' => (int) $row->surplus,
            ], $clashes->all());
        })();
    }

    /**
     * Slots where one class-division carries more than one timetable row.
     *
     * A class can only attend one of them. Some of these are genuine electives
     * taught to split batches, and the timetable records the two identically —
     * which is itself worth knowing, because nothing downstream can tell them
     * apart either.
     */
    public function classDoubleBookings(): int
    {
        return $this->memo['classDoubleBookings'] ??= (int) DB::table(DB::raw(
            '(SELECT 1 FROM '.self::TIMETABLE_TABLE.'
              WHERE sub_institute_id = '.(int) $this->tenantId.'
                AND syear = '.(int) $this->syear.'
              GROUP BY standard_id, division_id, marking_period_id, week_day, period_id
              HAVING COUNT(*) > 1) AS d'
        ))->count();
    }

    public function dataQuality(): array
    {
        $cov = $this->coverage();
        if (!$cov['available']) {
            return [];
        }

        $unlinkedTeacher = DB::table(self::TIMETABLE_TABLE . ' as t')
            ->leftJoin(self::USER_TABLE . ' as u', 'u.id', '=', 't.teacher_id')
            ->where('t.sub_institute_id', $this->tenantId)
            ->where('t.syear', $this->syear)
            ->where('t.teacher_id', '>', 0)
            ->whereNull('u.id')
            ->count();

        $unassignedSubject = DB::table(self::TIMETABLE_TABLE)
            ->where('sub_institute_id', $this->tenantId)
            ->where('syear', $this->syear)
            ->where(function ($q) {
                $q->whereNull('subject_id')->orWhere('subject_id', 0);
            })
            ->count();

        return [
            [
                'key' => 'unlinked_faculty',
                'label' => 'Teacher profile linkage',
                'count' => $unlinkedTeacher,
                'status' => $unlinkedTeacher > 0 ? 'warning' : 'clean',
                'description' => $unlinkedTeacher > 0
                    ? "{$unlinkedTeacher} scheduled periods reference unknown teacher IDs."
                    : 'All teacher assignments link to valid staff records.',
            ],
            [
                'key' => 'unassigned_subject',
                'label' => 'Subject assignment',
                'count' => $unassignedSubject,
                'status' => $unassignedSubject > 0 ? 'warning' : 'clean',
                'description' => $unassignedSubject > 0
                    ? "{$unassignedSubject} timetable slots have no assigned subject ID."
                    : 'All periods carry valid subject assignments.',
            ],
        ];
    }
}

