<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\HrIntelligence;
use App\Brain\Intelligence\HrSignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainHrIntelligenceController extends Controller
{
    private string $tenantId = '';
    private ?string $syear = null;
    private string $actorId = '';
    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        // The L5 half of the payload, read back from the signal ledger this
        // module's findings were written to by the pipeline.
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'hr', 'staff');

        $analytics = new HrIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new HrSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();
        $punch = $analytics->punchProfile();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · tbluser, tbluserprofilemaster, hrms_emp_leaves, hrms_leave_types, hrms_attendances',
            'academicYear' => ['syear' => $this->syear],
            'coverage' => $coverage,
            'freshness' => [
                'positionLabel' => 'Read live at page load',
                // The ledger's own timestamp when the loop has run for this module,
                // falling back to now for the live per-request computation.
                'findingsRefreshedAt' => $loop->signalsRefreshedAt()
                    ?? ($coverage['available'] ? now()->toIso8601String() : null),
                'findingsLabel' => 'Computed on this request',
            ],
            'execution' => [
                'automated' => false,
                'note' => 'Employee, leave, and payroll metrics are evaluated live from institutional HR registers.',
            ],
            'summary' => $this->summary($pos, $coverage, $raised['findings'], $punch['reason']),
            'position' => $this->position($pos, $coverage, $punch),
            'breakdowns' => $this->breakdowns($analytics, $coverage),
            'findings' => $raised['findings'],
            'priorities' => $this->priorities($raised['findings']),
            'recommendations' => $loop->recommendations(),
            'decisionTrail' => $loop->decisionTrail(),
            'learning' => $loop->learning(),
            'dataQuality' => $analytics->dataQuality(),
            'ruleStatus' => $raised['ruleStatus'],
        ]));
    }

    /**
     * "What is happening", composed from the same figures the cards below show.
     *
     * DETERMINISTIC, NEVER MODEL OUTPUT. Where a register is not kept, the
     * sentence SAYS SO rather than reporting its absence as a zero — "no leave
     * was taken" and "we do not record leave" are different statements, and only
     * one of them is about the staff.
     */
    private function summary(?array $pos, array $coverage, array $findings, ?string $punchReason): array
    {
        if ($pos === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'No staff records exist for this institute.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        $sentences[] = "{$pos['activeStaff']} active staff across {$pos['roles']} roles"
            .($pos['inactiveStaff'] > 0
                ? ", from {$pos['staff']} staff records in total — the rest are no longer active."
                : '.');

        if ($pos['teachingShare'] !== null) {
            $sentences[] = "{$pos['teachingStaff']} of them hold a teaching role ({$pos['teachingShare']}%)"
                .($pos['femaleShare'] !== null
                    ? ", and the active body is {$pos['femaleShare']}% women and {$pos['maleShare']}% men."
                    : '.');
        }

        $sentences[] = match (true) {
            // NULL means no register, and that is what gets said.
            $pos['leaveDays'] === null => 'No staff leave register is kept for this academic year, so nothing on this '
                .'screen reports leave — that is an absence of records, not an absence of leave.',
            $pos['leaveDays'] === 0 => 'The staff leave register holds no days for this academic year.',
            default => "{$pos['leaveDays']} leave days were taken by {$pos['staffTakingLeave']} staff across "
                ."{$pos['leaveApplications']} applications, {$pos['avgLeaveDaysPerStaff']} days a head across the "
                .'active body.',
        };

        $sentences[] = match (true) {
            // NULL means no usable register. The reason is the analytics layer's
            // own words, because it knows which of four reasons applies.
            $pos['medianAttendance'] === null => $punchReason
                ?? 'No staff punch register is available for this academic year, so nothing on this screen reports '
                    .'staff attendance.',
            default => "The punch register covers {$pos['staffOnRegister']} people over {$pos['workingDays']} working "
                ."days — days the institute itself ran, read from the register rather than from a weekday convention. "
                ."The median member of staff was present on {$pos['medianDaysPresent']} of them "
                ."({$pos['medianAttendance']}%).",
        };

        if ($pos['missingContact'] > 0) {
            $sentences[] = "{$pos['missingContact']} active staff have neither an email nor a mobile on file, so "
                .'nothing the system sends reaches them.';
        }

        $count = count($findings);
        $sentences[] = match ($count) {
            0 => 'No check found enough evidence to raise a finding for this year.',
            1 => 'One finding below, with the figures it rests on.',
            default => "{$count} findings below, each with the figures it rests on.",
        };

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$pos['activeStaff']} active staff across {$pos['roles']} roles",
            'sentences' => $sentences,
        ];
    }

    private function position(?array $pos, array $coverage, array $punch): ?array
    {
        if ($pos === null) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        $sources = $coverage['sources'] ?? [];
        $noLeaveRegister = ! ($sources['leaveRegister'] ?? false);
        // The analytics layer's own words for why there is no attendance figure.
        // Four different reasons produce a null here and they are not the same.
        $punchHint = $punch['reason'] ?? 'No punch register for this year';

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'activeStaff',
                    'label' => 'Active staff',
                    'value' => $pos['activeStaff'],
                    'format' => 'count',
                    'hint' => $pos['inactiveStaff'] > 0
                        ? "{$pos['staff']} staff records in total"
                        : 'Every staff record is active',
                ],
                [
                    'key' => 'roles',
                    'label' => 'Roles',
                    'value' => $pos['roles'],
                    'format' => 'count',
                ],
                [
                    'key' => 'teachingShare',
                    'label' => 'Teaching roles',
                    'value' => $pos['teachingShare'],
                    'format' => 'percent',
                    'hint' => "{$pos['teachingStaff']} of {$pos['activeStaff']} active staff",
                ],
                [
                    'key' => 'femaleShare',
                    'label' => 'Women',
                    'value' => $pos['femaleShare'],
                    'format' => 'percent',
                    'hint' => $pos['genderUnrecorded'] > 0
                        ? "{$pos['genderUnrecorded']} staff have no gender recorded"
                        : 'Of active staff whose gender is recorded',
                ],
                [
                    'key' => 'leaveDays',
                    'label' => 'Leave days this year',
                    // NULL where no register is kept. An em dash, never a zero:
                    // "0 leave days" reads as a claim about the staff.
                    'value' => $pos['leaveDays'],
                    'format' => 'count',
                    'hint' => $noLeaveRegister
                        ? 'No staff leave register is kept for this year'
                        : "{$pos['staffTakingLeave']} staff across {$pos['leaveApplications']} applications",
                ],
                [
                    'key' => 'avgLeaveDaysPerStaff',
                    'label' => 'Leave days a head',
                    'value' => $pos['avgLeaveDaysPerStaff'],
                    'format' => 'decimal',
                    'hint' => $noLeaveRegister ? 'No register to compute this from' : 'Across all active staff',
                ],
                [
                    'key' => 'leaveWithoutPayDays',
                    'label' => 'Days without pay',
                    'value' => $pos['leaveWithoutPayDays'],
                    'format' => 'count',
                    'tone' => ($pos['leaveWithoutPayDays'] ?? 0) > 0 ? 'attention' : null,
                    'hint' => $noLeaveRegister ? 'No register to read this from' : 'Leave carrying a without-pay status',
                ],
                [
                    'key' => 'missingContact',
                    'label' => 'Unreachable staff',
                    'value' => $pos['missingContact'],
                    'format' => 'count',
                    'tone' => $pos['missingContact'] > 0 ? 'warning' : 'positive',
                    'hint' => 'Neither email nor mobile on file',
                ],
                [
                    'key' => 'medianAttendance',
                    'label' => 'Median staff attendance',
                    // NULL where the register is absent, unused or too small to
                    // describe a staff body — never a zero, which would read as
                    // a claim that nobody came in.
                    'value' => $pos['medianAttendance'],
                    'format' => 'percent',
                    'hint' => $pos['medianAttendance'] === null
                        ? $punchHint
                        : "Median {$pos['medianDaysPresent']} of {$pos['workingDays']} working days, across "
                            ."{$pos['staffOnRegister']} people on the register",
                ],
                [
                    'key' => 'workingDays',
                    'label' => 'Working days',
                    'value' => $pos['workingDays'],
                    'format' => 'count',
                    'hint' => $pos['workingDays'] === null
                        ? $punchHint
                        : 'Days the institute itself ran, read from its own register rather than from a weekday '
                            .'convention',
                ],
                [
                    'key' => 'openShifts',
                    'label' => 'Punch days never closed',
                    'value' => $pos['openShifts'],
                    'format' => 'count',
                    'tone' => ($pos['openShifts'] ?? 0) > 0 ? 'attention' : null,
                    'hint' => $pos['openShifts'] === null
                        ? $punchHint
                        : 'A punch-in with no punch-out, so no hours exist for that day',
                ],
                [
                    'key' => 'activeMissingFromRegister',
                    'label' => 'Active staff off the register',
                    'value' => $pos['activeMissingFromRegister'],
                    'format' => 'count',
                    'tone' => ($pos['activeMissingFromRegister'] ?? 0) > 0 ? 'attention' : null,
                    'hint' => $pos['activeMissingFromRegister'] === null
                        ? $punchHint
                        : 'Active staff with no punch record this year — usually exempt from punching, but that is '
                            .'nowhere recorded',
                ],
            ],
        ];
    }

    private function breakdowns(HrIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byRole = $analytics->byRole();
        $byLeaveType = $analytics->byLeaveType();
        $leaveByRole = $analytics->leaveByRole();
        $punchByRole = $analytics->punchesByRole();
        $bands = $analytics->attendanceBands();
        $punchReason = $analytics->punchProfile()['reason']
            ?? 'No staff punch register is available for this academic year.';
        $sources = $coverage['sources'] ?? [];

        $noRegisterReason = ($sources['yearWindow'] ?? false)
            ? 'This institute keeps no staff leave register for this academic year. That is an absence of records, '
                .'not an absence of leave.'
            : 'This institute has no term dates on file for this academic year, so leave — which is recorded by date '
                .'rather than by year — cannot be scoped to it.';

        return [
            [
                'key' => 'roles',
                'label' => 'By role',
                'description' => 'Active staff by the role they hold. Role names are read from the role master; a '
                    .'role missing from it is shown by its key rather than given a name.',
                'available' => $byRole !== [],
                'reason' => $byRole === [] ? 'No active staff member holds a role.' : null,
                'primaryColumn' => 'staff',
                'columns' => [
                    ['key' => 'staff', 'label' => 'Staff', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                    ['key' => 'femaleShare', 'label' => 'Women', 'format' => 'percent'],
                    ['key' => 'noContact', 'label' => 'Unreachable', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'tone' => $row['noContact'] > 0 ? 'attention' : null,
                    'note' => $row['staff'] < HrIntelligence::MIN_ROLE_COHORT
                        ? 'Too few staff to compare against the institute'
                        : null,
                    'values' => [
                        'staff' => $row['staff'],
                        'share' => $row['share'],
                        'femaleShare' => $row['femaleShare'],
                        'noContact' => $row['noContact'],
                    ],
                ], $byRole),
            ],
            [
                'key' => 'leave_types',
                'label' => 'By leave type',
                'description' => 'Leave taken inside this academic year’s own term dates, by the type it was taken '
                    .'under.',
                'available' => $byLeaveType !== [],
                'reason' => $byLeaveType === [] ? $noRegisterReason : null,
                'primaryColumn' => 'days',
                'columns' => [
                    ['key' => 'days', 'label' => 'Days', 'format' => 'count'],
                    ['key' => 'applications', 'label' => 'Applications', 'format' => 'count'],
                    ['key' => 'staff', 'label' => 'Staff', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'days' => $row['days'],
                        'applications' => $row['applications'],
                        'staff' => $row['staff'],
                    ],
                ], $byLeaveType),
            ],
            [
                'key' => 'leave_by_role',
                'label' => 'Leave by role',
                'description' => 'The same leave, divided by the role that took it, with days a head against the '
                    .'role’s own headcount — because cover is found inside a role rather than across the institute.',
                'available' => $leaveByRole !== [],
                'reason' => $leaveByRole === [] ? $noRegisterReason : null,
                'primaryColumn' => 'days',
                'columns' => [
                    ['key' => 'days', 'label' => 'Days', 'format' => 'count'],
                    ['key' => 'daysPerStaff', 'label' => 'Days a head', 'format' => 'decimal'],
                    ['key' => 'staff', 'label' => 'Staff who applied', 'format' => 'count'],
                    ['key' => 'headcount', 'label' => 'Headcount', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['headcount'] !== null && $row['headcount'] < HrIntelligence::MIN_ROLE_COHORT
                        ? 'Too few staff to compare against the institute'
                        : null,
                    'values' => [
                        'days' => $row['days'],
                        'daysPerStaff' => $row['daysPerStaff'],
                        'staff' => $row['staff'],
                        'headcount' => $row['headcount'],
                    ],
                ], $leaveByRole),
            ],
            [
                'key' => 'attendance_by_role',
                'label' => 'Attendance by role',
                'description' => 'Days present per role against the institute’s own working days. A median, never a '
                    .'list: approved leave, a school trip and a split week are indistinguishable in the punch '
                    .'register, so this says how often a role appears and not why.',
                'available' => $punchByRole !== [],
                'reason' => $punchByRole === [] ? $punchReason : null,
                'primaryColumn' => 'attendance',
                'columns' => [
                    ['key' => 'attendance', 'label' => 'Attendance', 'format' => 'percent'],
                    ['key' => 'medianDaysPresent', 'label' => 'Median days', 'format' => 'count'],
                    ['key' => 'staff', 'label' => 'On register', 'format' => 'count'],
                    ['key' => 'openShifts', 'label' => 'Days never closed', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'tone' => $row['attendance'] !== null && $row['attendance'] < HrIntelligence::LOW_ATTENDANCE_SHARE
                        ? 'attention'
                        : null,
                    // A cohort below the floor keeps its counts and loses its
                    // median, because the median of four people is four people.
                    'note' => $row['suppressed']
                        ? 'Fewer than '.HrIntelligence::MIN_ROLE_COHORT.' staff — no median is shown, because it '
                            .'would describe the individuals in it'
                        : null,
                    'values' => [
                        'attendance' => $row['attendance'],
                        'medianDaysPresent' => $row['medianDaysPresent'],
                        'staff' => $row['staff'],
                        'openShifts' => $row['openShifts'],
                    ],
                ], $punchByRole),
            ],
            [
                'key' => 'attendance_bands',
                'label' => 'How attendance spreads',
                'description' => 'How many people sit in each band of the institute’s working days. Bands rather than '
                    .'names: this answers how many people a pattern is about without answering which people.',
                'available' => $bands !== [],
                'reason' => $bands === [] ? $punchReason : null,
                'primaryColumn' => 'staff',
                'columns' => [
                    ['key' => 'staff', 'label' => 'Staff', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share of the register', 'format' => 'percent'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'tone' => $row['key'] === 'under_50' && $row['staff'] > 0 ? 'attention' : null,
                    'values' => ['staff' => $row['staff'], 'share' => $row['share']],
                ], $bands),
            ],
        ];
    }

    private function priorities(array $findings): array
    {
        $severe = array_values(array_filter(
            $findings,
            fn ($f) => in_array($f['severity'], ['critical', 'high', 'medium'], true)
        ));

        return array_map(fn ($f) => [
            'id' => $f['id'],
            'severity' => $f['severity'],
            'severityLabel' => $f['severityLabel'],
            'title' => $f['title'],
            'whatHappened' => $f['whatHappened'],
            'whyItMatters' => $f['whyItMatters'],
            'evidence' => $f['evidence'],
            'impact' => $f['impact'],
            'nextStep' => $f['recommendation'],
            'owner' => $f['owner'],
            'confidence' => $f['confidence'],
        ], array_slice($severe, 0, 5));
    }


    /**
     * Write this module's findings to the signal ledger, so they enter the
     * recommendation → decision → execution → outcome → learning loop.
     *
     * IDEMPOTENT. `SignalWriter` dedupes on (tenant, rule, year), so pressing
     * this twice refreshes the same signals with fresher figures rather than
     * duplicating them. It runs the WHOLE pipeline rather than this module
     * alone, because reasoning over a signal that has not been raised produces
     * nothing and the stages are not independent.
     */
    public function run(Request $request): JsonResponse
    {
        $this->scope($request);

        $result = (new IntelligencePipeline($this->tenantId, $this->syear))->run();

        return response()->json([
            'tenantId' => $this->tenantId,
            'syear' => $this->syear,
            'signalsCreated' => $result['rules']['signalsCreated'] ?? 0,
            'signalsRefreshed' => $result['rules']['signalsRefreshed'] ?? 0,
            'recommendations' => $result['reasoning']['recommendations'] ?? 0,
            // Signals whose rule has no approved cause reach evidence and stop
            // there. Reported rather than hidden: it is the honest count of
            // findings the engine will not explain.
            'undetermined' => $result['reasoning']['undetermined'] ?? 0,
            'elapsedMs' => $result['elapsedMs'] ?? null,
        ]);
    }

    private function scope(Request $request): void
    {
        $this->tenantId = (string) $request->attributes->get(
            'tenantId',
            $request->attributes->get('auth.tenantId')
        );

        $this->syear = AcademicYear::resolve($this->tenantId, $request->query('syear'));
        $this->actorId = (string) $request->attributes->get('auth.userId', '');
        $payload = (array) $request->attributes->get('brain.payload', []);
        $this->actorIsStudent = (bool) ($payload['is_student'] ?? false);
    }
}

