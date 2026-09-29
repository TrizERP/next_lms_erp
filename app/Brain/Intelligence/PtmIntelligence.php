<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Parent–teacher meetings for one institute and academic year.
 *
 * ── TWO TABLES, ONLY ONE OF WHICH CARRIES THE YEAR ──────────────────────────
 *
 * `ptm_time_slots_master` is the OFFER: a slot on a date, for a class-division,
 * with `syear` and `sub_institute_id`.
 *
 * `ptm_booking_master` is the TAKE-UP: a student booked with a teacher against
 * a slot. It carries `SUB_INSTITUTE_ID` (uppercase) but NO `syear`.
 *
 * SO BOOKINGS ARE YEAR-SCOPED THROUGH THEIR SLOT, not by a column of their own.
 * Counting bookings by tenant alone would mix every year the institute has ever
 * run PTMs into whichever year the header happens to show — which is why every
 * booking figure below joins the slot table rather than filtering in place.
 *
 * ── ATTENDANCE IS A SEPARATE FACT FROM CONFIRMATION ─────────────────────────
 *
 * `CONFIRM_STATUS` says the booking was confirmed; `PTM_ATTENDED_STATUS` says
 * somebody turned up. A parent who confirmed and did not attend is the single
 * most useful thing this module knows, and it is only visible because the two
 * are kept apart here.
 */
final class PtmIntelligence
{
    private const SLOTS = 'ptm_time_slots_master';

    private const BOOKINGS = 'ptm_booking_master';

    /** Slot take-up below this share is reported. */
    private const LOW_UPTAKE = 50.0;

    /*
     * ── THE ATTENDANCE COLUMN HOLDS WORDS, NOT FLAGS ────────────────────────
     *
     * `PTM_ATTENDED_STATUS` is a VARCHAR whose live values are 'Yes' (627),
     * 'No' (154), the UI placeholder '--Select Status--' (6) and NULL (2).
     *
     * THE LISTS BELOW ARE DELIBERATELY STRING-ONLY. An earlier version included
     * the integers 1 and 0 for safety, and that was the bug: MySQL comparing a
     * VARCHAR column against a list containing an integer coerces the column to
     * a number, so 'Yes' became 0 and matched nothing. The screen then reported
     * every single booking as a no-show — a confidently wrong claim about
     * parents who had in fact turned up.
     *
     * THE PLACEHOLDER IS NEITHER. '--Select Status--' means the teacher opened
     * the dropdown and did not choose, which is "not marked", not "did not
     * attend". It is excluded from both lists so it lands in the unmarked count
     * where it belongs.
     */
    private const ATTENDED_VALUES = ['Yes', 'yes', 'YES', 'Y', 'y'];

    private const NOT_ATTENDED_VALUES = ['No', 'no', 'NO', 'N', 'n'];

    private array $memo = [];

    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $syear,
    ) {
    }

    private function slots()
    {
        $q = DB::table(self::SLOTS)->where(self::SLOTS.'.sub_institute_id', $this->tenantId);

        return $this->syear !== null && $this->syear !== ''
            ? $q->where(self::SLOTS.'.syear', $this->syear)
            : $q;
    }

    /** Bookings for this institute-year, reached through the slot that dates them. */
    private function bookings()
    {
        $q = DB::table(self::BOOKINGS)
            ->join(self::SLOTS, self::SLOTS.'.id', '=', self::BOOKINGS.'.TIME_SLOT_ID')
            ->where(self::BOOKINGS.'.SUB_INSTITUTE_ID', $this->tenantId)
            ->where(self::SLOTS.'.sub_institute_id', $this->tenantId);

        return $this->syear !== null && $this->syear !== ''
            ? $q->where(self::SLOTS.'.syear', $this->syear)
            : $q;
    }

    private function memo(string $k, callable $fn)
    {
        return $this->memo[$k] ??= $fn();
    }

    public function coverage(): array
    {
        return $this->memo('coverage', function () {
            if (! SchemaCache::hasTable(self::SLOTS)) {
                return $this->unavailable('This installation has no '.self::SLOTS.' table.');
            }

            if ($this->syear === null || $this->syear === '') {
                return $this->unavailable('No academic year is selected, and PTM slots are recorded per year.');
            }

            $slots = (int) $this->slots()->count();
            $bookings = SchemaCache::hasTable(self::BOOKINGS) ? (int) $this->bookings()->count() : 0;

            if ($slots === 0 && $bookings === 0) {
                return $this->unavailable(
                    "No parent–teacher meeting slots were scheduled for academic year {$this->syear}."
                );
            }

            return [
                'available' => true,
                'reason' => null,
                'syear' => $this->syear,
                'sources' => [
                    'slots' => $slots > 0,
                    'bookings' => $bookings > 0,
                    'attendanceRecorded' => $this->attended() + $this->notAttended() > 0,
                ],
                'counts' => [
                    'slots' => $slots,
                    'bookings' => $bookings,
                ],
            ];
        });
    }

    private function unavailable(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'syear' => $this->syear, 'sources' => [], 'counts' => []];
    }

    private function attended(): int
    {
        return $this->memo('attended', function () {
            if (! SchemaCache::hasTable(self::BOOKINGS)) {
                return 0;
            }

            return (int) $this->bookings()
                ->whereIn(self::BOOKINGS.'.PTM_ATTENDED_STATUS', self::ATTENDED_VALUES)
                ->count();
        });
    }

    private function notAttended(): int
    {
        return $this->memo('notAttended', function () {
            if (! SchemaCache::hasTable(self::BOOKINGS)) {
                return 0;
            }

            return (int) $this->bookings()
                ->whereIn(self::BOOKINGS.'.PTM_ATTENDED_STATUS', self::NOT_ATTENDED_VALUES)
                ->count();
        });
    }

    public function position(): ?array
    {
        return $this->memo('position', function () {
            if (! $this->coverage()['available']) {
                return null;
            }

            $s = $this->slots()->selectRaw(
                'COUNT(*) AS n, COUNT(DISTINCT standard_id) AS classes,
                 COUNT(DISTINCT ptm_date) AS days, MIN(ptm_date) AS first_d, MAX(ptm_date) AS last_d'
            )->first();

            $b = SchemaCache::hasTable(self::BOOKINGS)
                ? $this->bookings()->selectRaw(
                    'COUNT(*) AS n,
                     COUNT(DISTINCT '.self::BOOKINGS.'.STUDENT_ID) AS students,
                     COUNT(DISTINCT '.self::BOOKINGS.'.TEACHER_ID) AS teachers,
                     COUNT(DISTINCT '.self::BOOKINGS.'.TIME_SLOT_ID) AS slots_used'
                )->first()
                : null;

            $slots = (int) ($s->n ?? 0);
            $bookings = (int) ($b->n ?? 0);
            $slotsUsed = (int) ($b->slots_used ?? 0);
            $attended = $this->attended();
            $recorded = $attended + $this->notAttended();

            return [
                'slots' => $slots,
                'bookings' => $bookings,
                'slotsBooked' => $slotsUsed,
                // Undefined rather than 0% when no slot was offered at all.
                'slotUptake' => $slots > 0 ? round($slotsUsed / $slots * 100, 1) : null,
                'students' => (int) ($b->students ?? 0),
                'teachers' => (int) ($b->teachers ?? 0),
                'classes' => (int) ($s->classes ?? 0),
                'meetingDays' => (int) ($s->days ?? 0),
                'attended' => $attended,
                'attendanceRecorded' => $recorded,
                // Only meaningful over bookings whose attendance was actually marked.
                'attendanceRate' => $recorded > 0 ? round($attended / $recorded * 100, 1) : null,
                'firstMeetingOn' => $s->first_d ?? null,
                'lastMeetingOn' => $s->last_d ?? null,
            ];
        });
    }

    /** Slots and bookings per class. */
    public function byClass(): array
    {
        return $this->memo('byClass', function () {
            if (! $this->coverage()['available']) {
                return [];
            }

            $slots = $this->slots()
                ->selectRaw('standard_id, COUNT(*) AS n')
                ->groupBy('standard_id')->get()->keyBy('standard_id');

            $booked = SchemaCache::hasTable(self::BOOKINGS)
                ? $this->bookings()
                    ->selectRaw(self::SLOTS.'.standard_id AS standard_id, COUNT(*) AS n')
                    ->groupBy(self::SLOTS.'.standard_id')->get()->keyBy('standard_id')
                : collect();

            return $slots->map(fn ($row, $id) => [
                'key' => (string) $id,
                'label' => 'Class '.$id,
                'slots' => (int) $row->n,
                'bookings' => (int) ($booked[$id]->n ?? 0),
            ])->values()->sortByDesc('slots')->values()->all();
        });
    }

    /** Bookings by the meeting date they fall on. */
    public function byDate(): array
    {
        return $this->memo('byDate', function () {
            if (! $this->coverage()['available'] || ! SchemaCache::hasTable(self::BOOKINGS)) {
                return [];
            }

            return $this->bookings()
                ->selectRaw(self::SLOTS.'.ptm_date AS d, COUNT(*) AS n')
                ->groupBy(self::SLOTS.'.ptm_date')
                ->orderBy('d')
                ->get()
                ->map(fn ($r) => [
                    'key' => (string) $r->d,
                    'label' => (string) $r->d,
                    'bookings' => (int) $r->n,
                ])->all();
        });
    }

    public function dataQuality(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $p = $this->position();
        $unmarked = max(0, $p['bookings'] - $p['attendanceRecorded']);
        $noTeacher = SchemaCache::hasTable(self::BOOKINGS)
            ? (int) $this->bookings()->where(function ($q) {
                $q->whereNull(self::BOOKINGS.'.TEACHER_ID')->orWhere(self::BOOKINGS.'.TEACHER_ID', 0);
            })->count()
            : 0;
        $emptySlots = max(0, $p['slots'] - $p['slotsBooked']);

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'attendance_unmarked', 'label' => 'Bookings with no attendance mark',
                    'value' => $unmarked, 'format' => 'count',
                    'sharePercent' => $p['bookings'] > 0 ? round($unmarked / $p['bookings'] * 100, 2) : null,
                    'state' => $unmarked > 0 ? 'attention' : 'ok',
                    'note' => $unmarked > 0
                        ? 'Attendance was never recorded for these bookings, so they are excluded from the attendance rate rather than counted as absent.'
                        : 'Every booking has an attendance mark.',
                ],
                [
                    'key' => 'slots_unbooked', 'label' => 'Slots nobody booked',
                    'value' => $emptySlots, 'format' => 'count',
                    'sharePercent' => $p['slots'] > 0 ? round($emptySlots / $p['slots'] * 100, 2) : null,
                    'state' => $emptySlots > 0 ? 'attention' : 'ok',
                    'note' => $emptySlots > 0
                        ? 'Teacher time was offered and not taken up.'
                        : 'Every slot offered was booked.',
                ],
                [
                    'key' => 'no_teacher', 'label' => 'Bookings with no teacher',
                    'value' => $noTeacher, 'format' => 'count',
                    'sharePercent' => $p['bookings'] > 0 ? round($noTeacher / $p['bookings'] * 100, 2) : null,
                    'state' => $noTeacher > 0 ? 'attention' : 'ok',
                    'note' => $noTeacher > 0
                        ? 'These bookings name no teacher, so they cannot be attributed to a member of staff.'
                        : 'Every booking names a teacher.',
                ],
            ],
        ];
    }

    /** @return array{findings: array, ruleStatus: array} */
    public function findings(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $p = $this->position();
        $findings = [];
        $status = [];

        // 1. Slots offered that nobody booked.
        $raised = false;
        if ($p['slotUptake'] !== null && $p['slots'] > 0 && $p['slotUptake'] < self::LOW_UPTAKE) {
            $raised = true;
            $empty = $p['slots'] - $p['slotsBooked'];
            $findings[] = $this->finding(
                'ptm-low-uptake',
                $p['slotUptake'] < 25 ? 'high' : 'medium',
                'Only '.$p['slotUptake'].'% of PTM slots were booked',
                $p['slotsBooked'].' of '.$p['slots'].' slots offered for '.$this->syear.' were taken up, leaving '
                    .$empty.' unbooked.',
                'Unbooked slots are teacher time already committed. Low take-up usually reflects how the meeting was '
                    .'communicated rather than parent disinterest.',
                [
                    ['label' => 'Slots offered', 'value' => (string) $p['slots']],
                    ['label' => 'Slots booked', 'value' => (string) $p['slotsBooked']],
                    ['label' => 'Take-up', 'value' => $p['slotUptake'].'%'],
                    ['label' => 'Meeting days', 'value' => (string) $p['meetingDays']],
                ],
                'Parents not notified, or slots released too close to the date.',
                'Check when the booking window opened relative to the meeting date.',
                'Class teacher',
                ['band' => 'High', 'value' => 0.88],
                ['count' => $empty, 'total' => $p['slots'], 'unit' => 'slots'],
            );
        }
        $status[] = ['key' => 'slot_uptake', 'label' => 'Slot take-up', 'checked' => $p['slotUptake'] !== null, 'raised' => $raised];

        // 2. Confirmed bookings where nobody turned up.
        $raised = false;
        if ($p['attendanceRate'] !== null && $p['attendanceRecorded'] > 0 && $p['attendanceRate'] < 70.0) {
            $raised = true;
            $missed = $p['attendanceRecorded'] - $p['attended'];
            $findings[] = $this->finding(
                'ptm-no-show',
                $p['attendanceRate'] < 50 ? 'high' : 'medium',
                $missed.' booked meetings were not attended',
                'Of '.$p['attendanceRecorded'].' bookings with attendance recorded, '.$p['attended']
                    .' were attended — '.$p['attendanceRate'].'%.',
                'A booking a parent made and did not keep is a different problem from a slot never booked: the intent '
                    .'was there and something stopped them.',
                [
                    ['label' => 'Attendance rate', 'value' => $p['attendanceRate'].'%'],
                    ['label' => 'Attended', 'value' => (string) $p['attended']],
                    ['label' => 'Did not attend', 'value' => (string) $missed],
                    ['label' => 'Attendance recorded on', 'value' => $p['attendanceRecorded'].' of '.$p['bookings'].' bookings'],
                ],
                'Reminders not sent, or the meeting time clashing with working hours.',
                'Compare the no-show list against the reminders the Communication module sent.',
                'Class teacher',
                ['band' => 'Medium', 'value' => 0.78],
                ['count' => $missed, 'total' => $p['attendanceRecorded'], 'unit' => 'meetings'],
            );
        }
        $status[] = ['key' => 'attendance', 'label' => 'Meetings attended', 'checked' => $p['attendanceRate'] !== null, 'raised' => $raised];

        // 3. Attendance never marked at all.
        $unmarked = max(0, $p['bookings'] - $p['attendanceRecorded']);
        $raised = false;
        if ($p['bookings'] > 0 && $unmarked / max(1, $p['bookings']) >= 0.5) {
            $raised = true;
            $findings[] = $this->finding(
                'ptm-unmarked',
                'medium',
                $unmarked.' of '.$p['bookings'].' bookings have no attendance mark',
                'Attendance was never recorded against these bookings, so no attendance rate can include them.',
                'Without the mark the module cannot tell a meeting that happened from one that did not, and the '
                    .'attendance rate above is computed only over the bookings that were marked.',
                [
                    ['label' => 'Unmarked bookings', 'value' => (string) $unmarked],
                    ['label' => 'Total bookings', 'value' => (string) $p['bookings']],
                ],
                'Teachers not completing the attendance step after the meeting.',
                'Ask class teachers to close off the attendance list for this round.',
                'Examination cell',
                ['band' => 'High', 'value' => 0.9],
                ['count' => $unmarked, 'total' => $p['bookings'], 'unit' => 'bookings'],
            );
        }
        $status[] = ['key' => 'attendance_marked', 'label' => 'Attendance recorded', 'checked' => true, 'raised' => $raised];

        return ['findings' => $findings, 'ruleStatus' => $status];
    }

    private function finding(
        string $id, string $severity, string $title, string $what, string $why,
        array $evidence, string $cause, string $recommendation, string $owner,
        array $confidence, array $affected,
    ): array {
        return [
            'id' => $id,
            'severity' => $severity,
            'severityLabel' => ucfirst($severity),
            'title' => $title,
            'whatHappened' => $what,
            'whyItMatters' => $why,
            'evidence' => $evidence,
            'likelyCause' => $cause,
            'causeConfirmed' => false,
            'recommendation' => $recommendation,
            'owner' => $owner,
            'priority' => $severity,
            'confidence' => $confidence,
            'affected' => $affected,
            'impact' => null,
            'status' => 'open',
        ];
    }
}
