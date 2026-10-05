<?php

namespace App\Brain\Intelligence;

/**
 * What the staff attendance figures mean, and what is worth someone's morning.
 *
 * Same shape as {@see AttendanceSignalRules}: one finding per pattern rather
 * than one per department, nothing raised below a cohort small enough that the
 * finding would really be about the people in it, and every rule reads only
 * what {@see StaffAttendanceIntelligence} already computed — no rule here
 * queries the database on its own.
 *
 * ── WHY NO RULE NAMES A DEPARTMENT'S WORST ATTENDER ─────────────────────────
 *
 * {@see StaffAttendanceIntelligence} never computes a per-person figure in the
 * first place — see its class note — so there is nothing for a rule here to
 * surface even if it tried. A department-gap finding names the department and
 * the count below its own thresholds, never a person.
 */
final class StaffAttendanceSignalRules
{
    /** A department must trail the institute average by this much before it is a gap rather than noise. */
    private const DEPARTMENT_GAP_POINTS = 10.0;

    /** Institute-wide share of on-register staff that is chronic before it is worth an alert. */
    private const CHRONIC_SHARE_ALERT = 10.0;

    /** Share of active staff missing from the register entirely before it is a finding rather than a handful of joiners. */
    private const ROSTER_GAP_SHARE = 10.0;

    /** Share of punch rows with no recorded punch-out before it is a device/process problem worth raising. */
    private const OPEN_SHIFT_SHARE_ALERT = 20.0;

    /** Departments named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    public function __construct(
        private readonly StaffAttendanceIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'chronic_staff_attendance' => [
                'Staff attending fewer than half the institute\'s working days',
                fn () => $this->chronicStaffAttendance(),
            ],
            'active_staff_missing_from_register' => [
                'Active staff with no punch record this year',
                fn () => $this->activeStaffMissingFromRegister(),
            ],
            'department_attendance_gap' => [
                'Departments attending materially less than the institute',
                fn () => $this->departmentAttendanceGap(),
            ],
            'open_shift_share' => [
                'Punch rows with no recorded punch-out',
                fn () => $this->openShiftShare(),
            ],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = [
                'key' => $key,
                'label' => $label,
                'checked' => true,
                'raised' => $raised !== [],
            ];
            foreach ($raised as $finding) {
                $findings[] = $finding;
            }
        }

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, function ($a, $b) use ($rank) {
            $bySeverity = ($rank[$a['severity']] ?? 5) <=> ($rank[$b['severity']] ?? 5);

            return $bySeverity !== 0
                ? $bySeverity
                : ($b['affected']['count'] ?? 0) <=> ($a['affected']['count'] ?? 0);
        });

        return ['findings' => $findings, 'ruleStatus' => $ruleStatus];
    }

    /* --------------------------------------------------------------- L3 rules */

    /** @return array<int,array<string,mixed>> */
    private function chronicStaffAttendance(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['staffOnRegister'] === 0) {
            return [];
        }

        if ($position['chronicStaffShare'] < self::CHRONIC_SHARE_ALERT) {
            return [];
        }

        return [[
            'id' => "staff-attendance-chronic-{$this->syear}",
            'rule' => 'chronic_staff_attendance',
            'severity' => 'high',
            'severityLabel' => 'High',
            'title' => "{$position['chronicStaffCount']} staff are present on fewer than half the institute's working days",
            'whatHappened' => "{$position['chronicStaffCount']} of {$position['staffOnRegister']} staff on the punch "
                ."register ({$position['chronicStaffShare']}%) punched on fewer than "
                .StaffAttendanceIntelligence::CHRONIC_THRESHOLD."% of the {$position['workingDays']} days the "
                .'institute ran this year. A further '.max(0, $position['irregularStaffCount'] - $position['chronicStaffCount'])
                .' are between that line and '.StaffAttendanceIntelligence::IRREGULAR_THRESHOLD.'%.',
            'whyItMatters' => 'This register does not see approved leave — that is a separate table this screen does '
                .'not join — so a figure this low is not explained by ordinary leave-taking. It is either leave that is '
                .'not being recorded against the punch device, or attendance that genuinely is this low.',
            'evidence' => [
                ['label' => 'Below 50% of working days', 'value' => (string) $position['chronicStaffCount']],
                ['label' => 'Staff on the register', 'value' => (string) $position['staffOnRegister']],
                ['label' => 'Share', 'value' => "{$position['chronicStaffShare']}%"],
                ['label' => "Institute's working days this year", 'value' => (string) $position['workingDays']],
            ],
            'likelyCause' => 'Leave that is approved but not reflected against this punch device, a role that is not '
                .'expected to punch daily (field staff, contract staff), or genuine irregular attendance. This data '
                .'cannot distinguish between them.',
            'causeConfirmed' => false,
            'recommendation' => 'Cross-check this list against approved leave for the same window before treating any '
                .'of it as unauthorised absence.',
            'owner' => 'HR',
            'priority' => 'high',
            'confidence' => ['band' => 'Medium', 'value' => 0.7],
            'affected' => [
                'count' => $position['chronicStaffCount'],
                'total' => $position['staffOnRegister'],
                'unit' => 'staff',
            ],
            'impact' => [
                'value' => $position['chronicStaffCount'],
                'display' => (string) $position['chronicStaffCount'],
                'label' => 'staff below the chronic line',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function activeStaffMissingFromRegister(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['activeStaff'] === 0) {
            return [];
        }

        $missing = $position['activeMissingFromRegister'];
        if ($missing === 0) {
            return [];
        }

        $share = round($missing / $position['activeStaff'] * 100, 1);
        if ($share < self::ROSTER_GAP_SHARE) {
            return [];
        }

        return [[
            'id' => "staff-attendance-roster-gap-{$this->syear}",
            'rule' => 'active_staff_missing_from_register',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$missing} active staff have no punch record this year",
            'whatHappened' => "{$position['activeStaff']} staff are marked active for this institute and "
                ."{$position['staffOnRegister']} appear on the punch register on its own working days, leaving "
                ."{$missing} ({$share}%) with none.",
            'whyItMatters' => 'Every attendance figure this screen reports — including the '
                ."{$position['averageAttendanceRate']}% average — is computed over the staff who have a punch. Staff "
                .'missing from the register entirely are not counted as absent by any figure above; they are simply '
                .'not in them.',
            'evidence' => [
                ['label' => 'Active staff', 'value' => (string) $position['activeStaff']],
                ['label' => 'On the punch register', 'value' => (string) $position['staffOnRegister']],
                ['label' => 'With no punch', 'value' => (string) $missing],
                ['label' => 'Share of active staff', 'value' => "{$share}%"],
            ],
            'likelyCause' => 'A role that does not use the punch device (off-site, field, or leadership staff not '
                .'expected to punch), or a device enrolment that was never completed. This data cannot distinguish '
                .'between them.',
            'causeConfirmed' => false,
            'recommendation' => 'Confirm which of these staff are expected to punch at all before assuming the gap is '
                .'a device or enrolment problem.',
            'owner' => 'HR',
            'priority' => 'medium',
            'confidence' => ['band' => 'Medium', 'value' => 0.65],
            'affected' => ['count' => $missing, 'total' => $position['activeStaff'], 'unit' => 'staff'],
            'impact' => ['value' => $missing, 'display' => (string) $missing, 'label' => 'staff unaccounted for'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function departmentAttendanceGap(): array
    {
        $position = $this->analytics->position();
        if ($position === null) {
            return [];
        }

        $instituteRate = $position['averageAttendanceRate'];
        $findings = [];

        foreach ($this->analytics->byDepartment() as $department) {
            if ($department['staff'] < StaffAttendanceIntelligence::MIN_DEPARTMENT_COHORT) {
                continue;
            }

            $gap = round($instituteRate - $department['averageAttendanceRate'], 1);
            if ($gap < self::DEPARTMENT_GAP_POINTS) {
                continue;
            }

            $findings[] = [
                'id' => "staff-attendance-department-gap-{$department['key']}-{$this->syear}",
                'rule' => 'department_attendance_gap',
                'severity' => $gap >= self::DEPARTMENT_GAP_POINTS * 1.5 ? 'high' : 'medium',
                'severityLabel' => $gap >= self::DEPARTMENT_GAP_POINTS * 1.5 ? 'High' : 'Medium',
                'title' => "{$department['label']} attends {$department['averageAttendanceRate']}% against "
                    ."{$instituteRate}% across the institute",
                'whatHappened' => "{$department['staff']} staff in {$department['label']} averaged "
                    ."{$department['averageAttendanceRate']}% of the institute's working days, {$gap} points below "
                    ."the institute's {$instituteRate}%. {$department['chronicCount']} of them are below the chronic "
                    .'line.',
                'whyItMatters' => 'A gap this size against the same institute in the same window is not explained by '
                    .'the calendar — every department shares it. It is localised to this department.',
                'evidence' => [
                    ['label' => 'This department', 'value' => "{$department['averageAttendanceRate']}%", 'note' => "{$department['staff']} staff"],
                    ['label' => 'Institute', 'value' => "{$instituteRate}%"],
                    ['label' => 'Gap', 'value' => "{$gap} points"],
                    ['label' => 'Median here', 'value' => "{$department['medianAttendanceRate']}%"],
                    ['label' => 'Below the chronic line', 'value' => (string) $department['chronicCount']],
                ],
                'likelyCause' => 'A shift pattern, a leave register not reflected against this device, or a rostering '
                    .'practice particular to this department. This data shows the gap, not the cause.',
                'causeConfirmed' => false,
                'recommendation' => 'Check this department\'s rostering and approved leave before drawing any '
                    .'conclusion about the people in it.',
                'owner' => 'HR with the department head',
                'priority' => $gap >= self::DEPARTMENT_GAP_POINTS * 1.5 ? 'high' : 'medium',
                'confidence' => $department['staff'] >= 25 ? ['band' => 'Medium', 'value' => 0.7] : ['band' => 'Low', 'value' => 0.5],
                'affected' => ['count' => $department['staff'], 'total' => $position['staffOnRegister'], 'unit' => 'staff'],
                'impact' => [
                    'value' => $department['chronicCount'],
                    'display' => (string) $department['chronicCount'],
                    'label' => 'staff below the chronic line',
                ],
                'status' => 'open',
                'syear' => $this->syear,
            ];
        }

        usort($findings, static fn ($a, $b) => $b['affected']['count'] <=> $a['affected']['count']);

        return array_slice($findings, 0, self::MAX_NAMED_IN_EVIDENCE);
    }

    /** @return array<int,array<string,mixed>> */
    private function openShiftShare(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['openShiftShare'] === null) {
            return [];
        }

        if ($position['openShiftShare'] < self::OPEN_SHIFT_SHARE_ALERT) {
            return [];
        }

        return [[
            'id' => "staff-attendance-open-shifts-{$this->syear}",
            'rule' => 'open_shift_share',
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => "{$position['openShiftShare']}% of punch rows this year carry no punch-out",
            'whatHappened' => "{$position['openShiftRows']} of {$position['totalPunchRows']} punch rows "
                ."({$position['openShiftShare']}%) record a punch-in with no matching punch-out.",
            'whyItMatters' => 'A punch-in with no punch-out understates working hours wherever they are calculated '
                .'from this table, and at this share it looks like a device or process gap rather than isolated '
                .'forgetfulness.',
            'evidence' => [
                ['label' => 'Rows with no punch-out', 'value' => (string) $position['openShiftRows']],
                ['label' => 'Total punch rows', 'value' => (string) $position['totalPunchRows']],
                ['label' => 'Share', 'value' => "{$position['openShiftShare']}%"],
            ],
            'likelyCause' => 'A gate or device that only captures entry, or a shift pattern the exit device does not '
                .'cover. This data cannot say which.',
            'causeConfirmed' => false,
            'recommendation' => 'Check whether one gate, shift, or device accounts for most of these before treating '
                .'it as an institute-wide pattern.',
            'owner' => 'HR / facilities',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $position['openShiftRows'], 'total' => $position['totalPunchRows'], 'unit' => 'punch rows'],
            'impact' => null,
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }
}
