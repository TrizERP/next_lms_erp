<?php

namespace App\Brain\Intelligence;

/**
 * What the staffing figures mean, and what is worth someone's morning.
 *
 * ── WHAT A RULE HERE MAY NOT DO ─────────────────────────────────────────────
 *
 * IT MAY NOT TREAT AN ABSENT REGISTER AS A ZERO. Most institutes in this
 * database keep no staff leave register at all. A rule that fires "no leave was
 * taken this year" against them would be asserting something about their staff
 * from the absence of a module, and every rule below therefore returns nothing
 * when its source is null rather than when its source is empty.
 *
 * IT MAY NOT COMPARE A ROLE OF THREE PEOPLE WITH ONE OF FOUR HUNDRED. Nothing
 * is compared below {@see HrIntelligence::MIN_ROLE_COHORT} staff, and findings
 * are ordered by how many people they touch rather than by the size of a
 * percentage.
 */
final class HrSignalRules
{
    /** Rows named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 6;

    /** Share of the year's leave days taken without pay before it is worth naming. */
    private const LWP_SHARE_ALERT = 15.0;

    /** How far a role's leave per head may sit above the institute's before it is named. */
    private const LEAVE_SPREAD_FACTOR = 1.6;

    /**
     * Share of punch days left unclosed before it stops being the ordinary
     * handful and starts being a reader nobody passes on the way out. Measured
     * at 6.4% and 10.4% at the two institutes that run a register.
     */
    private const OPEN_SHIFT_SHARE = 5.0;

    /** Share of active staff absent from the register before the register stops describing the staff body. */
    private const REGISTER_GAP_SHARE = 10.0;

    /** Percentage points below the institute median before a role is named. */
    private const ATTENDANCE_GAP_POINTS = 15.0;

    public function __construct(
        private readonly HrIntelligence $analytics,
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
            'unreachable_staff' => ['Active staff with no way to be contacted', fn () => $this->unreachableStaff()],
            'leave_concentration' => ['Roles taking markedly more leave per head', fn () => $this->leaveConcentration()],
            'leave_without_pay' => ['Leave taken without pay', fn () => $this->leaveWithoutPay()],
            'pending_leave' => ['Leave applications still awaiting a decision', fn () => $this->pendingLeave()],
            'service_records' => ['Active staff with no joining date on file', fn () => $this->serviceRecords()],
            'punch_open_shifts' => ['Punch days that were never closed', fn () => $this->openShifts()],
            'punch_register_gap' => ['Active staff who never appear on the punch register', fn () => $this->registerGap()],
            'punch_stale_staff_master' => ['Staff marked inactive who are still punching', fn () => $this->staleStaffMaster()],
            'punch_attendance_spread' => ['Roles present on markedly fewer days than the institute', fn () => $this->attendanceSpread()],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = ['key' => $key, 'label' => $label, 'checked' => true, 'raised' => $raised !== []];
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

    /* --------------------------------------------------------- reachability */

    /** @return array<int,array<string,mixed>> */
    private function unreachableStaff(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['activeStaff'] === 0) {
            return [];
        }

        $contact = $this->analytics->contactCompleteness();
        $unreachable = $contact['missingBoth'];
        if ($unreachable === 0) {
            return [];
        }

        $active = $position['activeStaff'];
        $share = round($unreachable / $active * 100, 1);

        $roles = array_values(array_filter($this->analytics->byRole(), static fn ($r) => $r['noContact'] > 0));
        usort($roles, static fn ($a, $b) => $b['noContact'] <=> $a['noContact']);

        return [[
            'id' => "hr-unreachable-{$this->syear}",
            'rule' => 'unreachable_staff',
            'severity' => $share >= HrIntelligence::CONTACT_GAP_SHARE ? 'high' : 'medium',
            'severityLabel' => $share >= HrIntelligence::CONTACT_GAP_SHARE ? 'High' : 'Medium',
            'title' => "{$unreachable} active staff have neither an email nor a mobile on file",
            'whatHappened' => $this->sentence([
                "{$unreachable} of {$active} active staff ({$share}%) have no email address and no mobile number "
                    .'recorded.',
                $roles !== []
                    ? "The most affected role is {$roles[0]['label']}, with {$roles[0]['noContact']} of "
                        ."{$roles[0]['staff']}."
                    : null,
                "A further {$contact['missingEmail']} have no email and {$contact['missingMobile']} have no mobile, "
                    .'but can still be reached the other way.',
            ]),
            'whyItMatters' => 'There is no route to these people through the system at all. A roster change, an '
                .'unscheduled closure or a password reset reaches everyone except them, and nobody finds out until '
                .'the day it matters.',
            'evidence' => array_merge(
                [
                    ['label' => 'Neither email nor mobile', 'value' => (string) $unreachable],
                    ['label' => 'Active staff', 'value' => (string) $active],
                    ['label' => 'Share', 'value' => "{$share}%"],
                ],
                array_map(static fn ($r) => [
                    'label' => $r['label'],
                    'value' => "{$r['noContact']} of {$r['staff']}",
                    'note' => 'unreachable',
                ], array_slice($roles, 0, self::MAX_NAMED_IN_EVIDENCE - 3)),
            ),
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Collect a mobile number for the named roles before the next all-staff notice goes '
                .'out — a mobile is the one route that works without an account.',
            'owner' => 'HR office',
            'priority' => $share >= HrIntelligence::CONTACT_GAP_SHARE ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.95],
            'affected' => ['count' => $unreachable, 'total' => $active, 'unit' => 'staff'],
            'impact' => ['value' => $unreachable, 'display' => (string) $unreachable, 'label' => 'staff unreachable'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ---------------------------------------------------------------- leave */

    /** @return array<int,array<string,mixed>> */
    private function leaveConcentration(): array
    {
        $position = $this->analytics->position();
        // NULL means no register, which is not the same as no leave. Nothing to
        // say either way.
        if ($position === null || $position['leaveDays'] === null || $position['avgLeaveDaysPerStaff'] === null) {
            return [];
        }

        $institute = $position['avgLeaveDaysPerStaff'];
        if ($institute <= 0.0) {
            return [];
        }

        $heavy = array_values(array_filter(
            $this->analytics->leaveByRole(),
            static fn ($r) => $r['headcount'] !== null
                && $r['headcount'] >= HrIntelligence::MIN_ROLE_COHORT
                && $r['daysPerStaff'] !== null
                && $r['daysPerStaff'] >= $institute * self::LEAVE_SPREAD_FACTOR,
        ));

        if ($heavy === []) {
            return [];
        }

        usort($heavy, static fn ($a, $b) => $b['days'] <=> $a['days']);
        $worst = $heavy[0];
        $staff = array_sum(array_column($heavy, 'headcount'));

        return [[
            'id' => "hr-leave-concentration-{$this->syear}",
            'rule' => 'leave_concentration',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => count($heavy) === 1
                ? "{$worst['label']} takes {$worst['daysPerStaff']} leave days a head against {$institute} institute-wide"
                : count($heavy).' roles take markedly more leave per head than the institute',
            'whatHappened' => $this->sentence([
                "Across the institute, {$position['leaveDays']} leave days were taken this year by "
                    ."{$position['staffTakingLeave']} staff — {$institute} days a head over "
                    ."{$position['activeStaff']} active staff.",
                count($heavy).' role'.(count($heavy) === 1 ? '' : 's').' with at least '
                    .HrIntelligence::MIN_ROLE_COHORT." staff sit at least {$this->factorWord()} above that.",
                "The heaviest is {$worst['label']}: {$worst['days']} days across {$worst['headcount']} staff, "
                    ."{$worst['daysPerStaff']} a head.",
            ]),
            'whyItMatters' => 'Leave has to be covered by whoever remains, and it is covered inside the role rather '
                .'than across the institute. A role taking half again the institute average is a role whose cover '
                .'burden falls on a smaller number of people than the headline figure suggests.',
            'evidence' => array_map(static fn ($r) => [
                'label' => $r['label'],
                'value' => "{$r['daysPerStaff']} days a head",
                'note' => "{$r['days']} days across {$r['headcount']} staff · {$r['applications']} applications",
            ], array_slice($heavy, 0, self::MAX_NAMED_IN_EVIDENCE)),
            // Explicitly a hypothesis. A leave pattern is not evidence about the
            // people who took the leave.
            'likelyCause' => 'Roles differ in entitlement, in how leave is recorded, and in how much of it is taken '
                .'as single days. A difference in days a head is not by itself evidence about the staff in the role.',
            'causeConfirmed' => false,
            'recommendation' => 'Check the entitlement attached to the named roles before reading this as a pattern '
                .'of absence — a role with a larger allowance will show a larger figure by design.',
            'owner' => 'HR office',
            'priority' => 'medium',
            'confidence' => $staff >= 50
                ? ['band' => 'Medium', 'value' => 0.7]
                : ['band' => 'Low', 'value' => 0.45],
            'affected' => ['count' => $staff, 'total' => $position['activeStaff'], 'unit' => 'staff'],
            'impact' => ['value' => $worst['days'], 'display' => (string) $worst['days'], 'label' => 'days in the heaviest role'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function leaveWithoutPay(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['leaveDays'] === null || $position['leaveDays'] === 0) {
            return [];
        }

        $lwp = $position['leaveWithoutPayDays'] ?? 0;
        if ($lwp === 0) {
            return [];
        }

        $share = round($lwp / $position['leaveDays'] * 100, 1);
        if ($share < self::LWP_SHARE_ALERT) {
            return [];
        }

        $types = array_values(array_filter($this->analytics->byLeaveType(), static fn ($t) => $t['days'] > 0));
        usort($types, static fn ($a, $b) => $b['days'] <=> $a['days']);

        return [[
            'id' => "hr-lwp-{$this->syear}",
            'rule' => 'leave_without_pay',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$lwp} of {$position['leaveDays']} leave days this year were taken without pay",
            'whatHappened' => $this->sentence([
                "{$lwp} days ({$share}%) of the {$position['leaveDays']} leave days recorded this year carry a "
                    .'without-pay status.',
                "{$position['staffTakingLeave']} staff took leave of some kind this year.",
            ]),
            'whyItMatters' => 'Leave without pay is usually entitlement that has run out rather than leave that was '
                .'chosen. A share this size is a signal about how the allowance is set or how the year fell, and it '
                .'is money staff did not receive.',
            'evidence' => array_merge(
                [
                    ['label' => 'Days without pay', 'value' => (string) $lwp],
                    ['label' => 'All leave days', 'value' => (string) $position['leaveDays']],
                    ['label' => 'Share', 'value' => "{$share}%"],
                ],
                array_map(static fn ($t) => [
                    'label' => $t['label'],
                    'value' => "{$t['days']} days",
                    'note' => "{$t['staff']} staff · {$t['applications']} applications",
                ], array_slice($types, 0, self::MAX_NAMED_IN_EVIDENCE - 3)),
            ),
            'likelyCause' => 'Entitlement exhausted before the year ended, or leave types that carry no paid '
                .'allowance by design. The register records the status, not the reason it was applied.',
            'causeConfirmed' => false,
            'recommendation' => 'Check when in the year the without-pay days fall: clustered at the end suggests the '
                .'allowance is set too low, spread evenly suggests a leave type that was never paid.',
            'owner' => 'HR office',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.85],
            'affected' => [
                'count' => $position['staffTakingLeave'],
                'total' => $position['activeStaff'],
                'unit' => 'staff',
            ],
            'impact' => ['value' => $lwp, 'display' => (string) $lwp, 'label' => 'unpaid leave days'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function pendingLeave(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['pendingLeave'] === null || $position['pendingLeave'] === 0) {
            return [];
        }

        $pending = $position['pendingLeave'];
        $applications = $position['leaveApplications'] ?? 0;

        return [[
            'id' => "hr-pending-leave-{$this->syear}",
            'rule' => 'pending_leave',
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => $pending === 1
                ? 'One leave application is still awaiting a decision'
                : "{$pending} leave applications are still awaiting a decision",
            'whatHappened' => "{$pending} of the {$applications} leave applications made this year "
                .($pending === 1 ? 'is' : 'are').' still marked pending.',
            'whyItMatters' => 'A pending application is a member of staff who does not know whether they are working '
                .'that day, and a day the timetable has not been covered for.',
            'evidence' => [
                ['label' => 'Pending', 'value' => (string) $pending],
                ['label' => 'Applications this year', 'value' => (string) $applications],
                ['label' => 'Staff who applied', 'value' => (string) ($position['staffTakingLeave'] ?? 0)],
            ],
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Clear the pending queue before the dates they cover arrive.',
            'owner' => 'HR office',
            'priority' => 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $pending, 'total' => $applications, 'unit' => 'applications'],
            'impact' => ['value' => $pending, 'display' => (string) $pending, 'label' => 'applications pending'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* -------------------------------------------------------------- records */

    /** @return array<int,array<string,mixed>> */
    private function serviceRecords(): array
    {
        $checks = [];
        foreach ($this->analytics->dataQuality()['checks'] ?? [] as $check) {
            $checks[$check['key']] = $check;
        }

        $noJoining = (int) ($checks['no_joining_date']['value'] ?? 0);
        $position = $this->analytics->position();
        $active = $position['activeStaff'] ?? 0;

        if ($noJoining === 0 || $active === 0) {
            return [];
        }

        $share = round($noJoining / $active * 100, 1);
        if ($share < 25.0) {
            return [];
        }

        $noQualification = (int) ($checks['no_qualification']['value'] ?? 0);

        return [[
            'id' => "hr-service-records-{$this->syear}",
            'rule' => 'service_records',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$noJoining} of {$active} active staff have no joining date on file",
            'whatHappened' => $this->sentence([
                "{$noJoining} active staff ({$share}%) have no joining date recorded.",
                $noQualification > 0 ? "{$noQualification} have no qualification recorded." : null,
            ]),
            'whyItMatters' => 'Length of service decides increments, gratuity and seniority, and a board inspection '
                .'asks for qualifications by name. With the fields empty, none of those can be answered from the '
                .'system — each one becomes a file somebody has to find.',
            'evidence' => array_values(array_filter([
                ['label' => 'No joining date', 'value' => (string) $noJoining],
                $noQualification > 0 ? ['label' => 'No qualification', 'value' => (string) $noQualification] : null,
                ['label' => 'Active staff', 'value' => (string) $active],
                ['label' => 'Share with no joining date', 'value' => "{$share}%"],
            ])),
            'likelyCause' => 'Staff records created for system access rather than as employment records, which is '
                .'the usual pattern when the HR module was adopted after the accounts were.',
            'causeConfirmed' => false,
            'recommendation' => 'Backfill joining dates from the appointment letters before the next increment '
                .'cycle, starting with the longest-serving staff.',
            'owner' => 'HR office',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $noJoining, 'total' => $active, 'unit' => 'staff'],
            'impact' => ['value' => $noJoining, 'display' => (string) $noJoining, 'label' => 'records incomplete'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------- punch register */

    /**
     * Days the register opened and never closed.
     *
     * This is a record to correct, not a judgement about anybody: a missing
     * closing punch usually means somebody left through a door without a reader.
     * It is here because hours cannot be computed for those days, so anything
     * paid on hours is silently short.
     *
     * @return array<int,array<string,mixed>>
     */
    private function openShifts(): array
    {
        $punch = $this->analytics->punchProfile();
        if (! $punch['available'] || (int) $punch['openShifts'] === 0) {
            return [];
        }

        $open = (int) $punch['openShifts'];
        $rows = (int) $punch['rows'];
        $share = round($open / $rows * 100, 1);
        if ($share < self::OPEN_SHIFT_SHARE) {
            return [];
        }

        // Roles ordered by the share of THEIR OWN punch days left open, so a
        // small role with a systematic problem is not hidden behind a large one.
        $roles = [];
        foreach ($this->analytics->punchesByRole() as $role) {
            if ($role['openShifts'] === 0 || $role['medianDaysPresent'] === null) {
                continue;
            }
            $roleRows = $role['staff'] * $role['medianDaysPresent'];
            if ($roleRows <= 0) {
                continue;
            }
            $roles[] = $role + ['openShare' => round(min($role['openShifts'], $roleRows) / $roleRows * 100, 1)];
        }
        usort($roles, static fn ($a, $b) => $b['openShare'] <=> $a['openShare']);

        $severity = $share >= self::OPEN_SHIFT_SHARE * 2 ? 'medium' : 'low';

        return [[
            'id' => "hr-open-shifts-{$this->syear}",
            'rule' => 'punch_open_shifts',
            'severity' => $severity,
            'severityLabel' => ucfirst($severity),
            'title' => "{$open} punch days this year have no closing punch",
            'whatHappened' => $this->sentence([
                "{$open} of the {$rows} punch days recorded this year ({$share}%) have a punch-in and no punch-out, "
                    .'so no span of hours exists for them.',
                $roles !== []
                    ? "The highest rate is {$roles[0]['label']}, where roughly {$roles[0]['openShare']}% of punch days "
                        .'are left open.'
                    : null,
                (int) $punch['outBeforeIn'] > 0
                    ? "A further {$punch['outBeforeIn']} days close before they open, which is a clock or device fault."
                    : null,
            ]),
            'whyItMatters' => 'Hours cannot be computed for a day that was never closed. Anything that pays on hours '
                .'or overtime reads these days as nothing, and the member of staff has no record that they were here '
                .'past the morning.',
            'evidence' => array_merge(
                [
                    ['label' => 'Punch days never closed', 'value' => (string) $open],
                    ['label' => 'Punch days this year', 'value' => (string) $rows],
                    ['label' => 'Share', 'value' => "{$share}%"],
                ],
                array_map(static fn ($r) => [
                    'label' => $r['label'],
                    'value' => "{$r['openShare']}% of punch days left open",
                    'note' => "{$r['openShifts']} open across {$r['staff']} staff",
                ], array_slice($roles, 0, self::MAX_NAMED_IN_EVIDENCE - 3)),
            ),
            'likelyCause' => 'An exit without a reader, a device that drops the second punch, or staff who are not '
                .'asked to punch out. The register records that the day never closed and not which of those it was.',
            'causeConfirmed' => false,
            'recommendation' => 'Take the role with the highest open rate and watch one day end. Whether the reader '
                .'is being passed, is failing, or was never expected at the exit decides which of three different '
                .'fixes this needs.',
            'owner' => 'HR office',
            'priority' => $severity === 'medium' ? 'medium' : 'low',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $open, 'total' => $rows, 'unit' => 'punch days'],
            'impact' => ['value' => $open, 'display' => (string) $open, 'label' => 'punch days with no hours'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * Active staff the register has never seen.
     *
     * Deliberately NOT called absence. Most of these people are exempt from
     * punching or based off site; the finding is that the system cannot tell
     * them apart from staff whose attendance is not being captured at all.
     *
     * @return array<int,array<string,mixed>>
     */
    private function registerGap(): array
    {
        $punch = $this->analytics->punchProfile();
        if (! $punch['available'] || $punch['activeMissingFromRegister'] === null) {
            return [];
        }

        $missing = (int) $punch['activeMissingFromRegister'];
        $active = (int) $punch['activeStaff'];
        if ($missing === 0 || $active === 0) {
            return [];
        }

        $share = round($missing / $active * 100, 1);
        if ($share < self::REGISTER_GAP_SHARE) {
            return [];
        }

        return [[
            'id' => "hr-register-gap-{$this->syear}",
            'rule' => 'punch_register_gap',
            'severity' => $share >= self::REGISTER_GAP_SHARE * 2 ? 'medium' : 'low',
            'severityLabel' => $share >= self::REGISTER_GAP_SHARE * 2 ? 'Medium' : 'Low',
            'title' => "{$missing} of {$active} active staff have no punch record this year at all",
            'whatHappened' => $this->sentence([
                "{$missing} active staff ({$share}%) appear nowhere in the punch register between the start and end "
                    .'of this academic year.',
                "The register covers {$punch['staff']} people over {$punch['workingDays']} working days.",
            ]),
            'whyItMatters' => 'Every attendance figure on this screen is drawn from the register, so it describes the '
                .'staff who use it and not the staff body. Until it is recorded who is exempt from punching, these '
                .'people look identical to staff whose attendance nobody is capturing.',
            'evidence' => [
                ['label' => 'Active staff with no punch record', 'value' => (string) $missing],
                ['label' => 'Active staff', 'value' => (string) $active],
                ['label' => 'Share', 'value' => "{$share}%"],
                ['label' => 'People on the register', 'value' => (string) $punch['staff']],
                ['label' => 'Working days derived from the register', 'value' => (string) $punch['workingDays']],
            ],
            'likelyCause' => 'Staff exempt from punching, based at another site, or appointed after the readers were '
                .'issued. The register records an absence of rows and cannot distinguish the three.',
            'causeConfirmed' => false,
            'recommendation' => 'Mark which roles are exempt from punching. It costs one afternoon and it is the '
                .'difference between an attendance figure that covers the staff body and one that covers whoever '
                .'happens to hold a card.',
            'owner' => 'HR office',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $missing, 'total' => $active, 'unit' => 'staff'],
            'impact' => ['value' => $missing, 'display' => (string) $missing, 'label' => 'staff outside the register'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * People the staff master calls inactive who are still punching in.
     *
     * @return array<int,array<string,mixed>>
     */
    private function staleStaffMaster(): array
    {
        $punch = $this->analytics->punchProfile();
        if (! $punch['available']) {
            return [];
        }

        $inactive = (int) $punch['inactiveOnRegister'];
        $register = (int) $punch['staff'];
        if ($inactive < HrIntelligence::MIN_ROLE_COHORT || $register === 0) {
            return [];
        }

        $share = round($inactive / $register * 100, 1);

        return [[
            'id' => "hr-stale-master-{$this->syear}",
            'rule' => 'punch_stale_staff_master',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$inactive} people punched in this year whom the staff master marks inactive",
            'whatHappened' => $this->sentence([
                "{$inactive} of the {$register} people on the punch register ({$share}%) have a staff record marked "
                    .'inactive.',
                (int) $punch['orphans'] > 0
                    ? "A further {$punch['orphans']} punched against a user id the staff master does not hold at all."
                    : null,
            ]),
            'whyItMatters' => 'The two records contradict each other and the contradiction points two ways. If these '
                .'people still work here, the master is wrong and they are missing from every headcount, payroll run '
                .'and inspection return. If they have left, the reader is still admitting them.',
            'evidence' => array_values(array_filter([
                ['label' => 'Inactive staff who punched', 'value' => (string) $inactive],
                ['label' => 'People on the register', 'value' => (string) $register],
                ['label' => 'Share of the register', 'value' => "{$share}%"],
                ['label' => 'Active staff in the master', 'value' => (string) $punch['activeStaff']],
                (int) $punch['orphans'] > 0
                    ? ['label' => 'Punches against an unknown user', 'value' => (string) $punch['orphans']]
                    : null,
            ])),
            // No hypothesis is offered, because the two readings need opposite
            // responses and nothing in either table decides between them.
            'likelyCause' => null,
            'causeConfirmed' => false,
            'recommendation' => 'Take the list to the office and settle each name one way or the other. Which of the '
                .'two answers it is decides whether this is a payroll problem or a door problem.',
            'owner' => 'HR office',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $inactive, 'total' => $register, 'unit' => 'staff'],
            'impact' => ['value' => $inactive, 'display' => (string) $inactive, 'label' => 'contradicted staff records'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /**
     * Roles the register shows present on markedly fewer days than the institute.
     *
     * ── WHY THIS IS NOT AN ABSENCE FINDING ──────────────────────────────────
     *
     * Approved leave, a school trip, part-time hours and a reader somebody never
     * uses are indistinguishable in this table. So the finding reports what the
     * REGISTER shows, offers the alternatives as alternatives, and asks for the
     * work pattern to be checked before anything else happens. It is also gated
     * at {@see HrIntelligence::MIN_ROLE_COHORT} so that it never describes a role
     * small enough to be one person.
     *
     * @return array<int,array<string,mixed>>
     */
    private function attendanceSpread(): array
    {
        $punch = $this->analytics->punchProfile();
        if (! $punch['available'] || $punch['medianAttendance'] === null) {
            return [];
        }

        $institute = (float) $punch['medianAttendance'];
        $workingDays = (int) $punch['workingDays'];

        $low = array_values(array_filter(
            $this->analytics->punchesByRole(),
            static fn ($r) => ! $r['suppressed']
                && $r['attendance'] !== null
                && $r['attendance'] < HrIntelligence::LOW_ATTENDANCE_SHARE
                && $institute - $r['attendance'] >= self::ATTENDANCE_GAP_POINTS,
        ));

        if ($low === []) {
            return [];
        }

        usort($low, static fn ($a, $b) => $b['staff'] <=> $a['staff']);
        $worst = $low[0];
        $staff = array_sum(array_column($low, 'staff'));

        return [[
            'id' => "hr-attendance-spread-{$this->syear}",
            'rule' => 'punch_attendance_spread',
            'severity' => 'low',
            'severityLabel' => 'Low',
            'title' => count($low) === 1
                ? "{$worst['label']} appears on {$worst['attendance']}% of working days against {$institute}% institute-wide"
                : count($low).' roles appear on markedly fewer working days than the institute',
            'whatHappened' => $this->sentence([
                "Across {$punch['staff']} people on the register, the median member of staff was present on "
                    ."{$punch['medianDaysPresent']} of {$workingDays} working days ({$institute}%).",
                count($low).' role'.(count($low) === 1 ? '' : 's').' with at least '
                    .HrIntelligence::MIN_ROLE_COHORT.' staff sit at least '.self::ATTENDANCE_GAP_POINTS
                    .' points below that.',
                "The largest is {$worst['label']}: a median of {$worst['medianDaysPresent']} days across "
                    ."{$worst['staff']} staff.",
            ]),
            'whyItMatters' => 'Cover is arranged inside a role rather than across the institute, so a role present on '
                .'half the days it is counted against is a cover burden carried by whoever in that role does turn up. '
                .'Whether it is that, or a work pattern nobody recorded, is the first thing to settle.',
            'evidence' => array_merge(
                [
                    ['label' => 'Institute median', 'value' => "{$punch['medianDaysPresent']} of {$workingDays} days"],
                    ['label' => 'Working days derived from the register', 'value' => (string) $workingDays],
                ],
                array_map(static fn ($r) => [
                    'label' => $r['label'],
                    'value' => "{$r['medianDaysPresent']} of {$workingDays} days ({$r['attendance']}%)",
                    'note' => "{$r['staff']} staff on the register",
                ], array_slice($low, 0, self::MAX_NAMED_IN_EVIDENCE - 2)),
            ),
            'likelyCause' => 'Part-time or shift hours, term-time-only appointments, duties carried out off site, or a '
                .'reader these staff do not pass. The register records days with a punch and none of the four.',
            'causeConfirmed' => false,
            'recommendation' => 'Check the contracted pattern for the named roles before reading this as attendance. '
                .'A role appointed for three days a week will sit here by design, and that is worth knowing before '
                .'anybody is spoken to.',
            'owner' => 'HR office',
            'priority' => 'low',
            'confidence' => $staff >= 20
                ? ['band' => 'Medium', 'value' => 0.6]
                : ['band' => 'Low', 'value' => 0.4],
            'affected' => ['count' => $staff, 'total' => (int) $punch['staff'], 'unit' => 'staff'],
            'impact' => [
                'value' => $staff,
                'display' => (string) $staff,
                'label' => 'staff in roles below the median',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ helpers */

    private function factorWord(): string
    {
        return HrIntelligence::MIN_ROLE_COHORT >= 0 && self::LEAVE_SPREAD_FACTOR >= 1.5
            ? 'half again'
            : 'materially';
    }

    /** @param array<int,?string> $parts */
    private function sentence(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }
}
