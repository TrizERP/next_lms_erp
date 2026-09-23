<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\StaffAttendanceIntelligence;
use App\Brain\Intelligence\StaffAttendanceSignalRules;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff Attendance Intelligence — the HRIT punch register read as a module in
 * its own right, distinct from {@see BrainAttendanceIntelligenceController}
 * (pupils) and from the broader {@see BrainHrIntelligenceController} (staff
 * roll, leave, payroll periods). Same envelope shape as every other Brain
 * module — see {@see ModulePayload} for what the contract requires.
 */
class BrainStaffAttendanceIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'staff-attendance', 'staff');

        $analytics = new StaffAttendanceIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new StaffAttendanceSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · hrms_attendances, tbluser, hrms_departments',
            'academicYear' => ['syear' => $this->syear],
            'coverage' => $coverage,
            'freshness' => [
                'positionLabel' => 'Read live at page load',
                'findingsRefreshedAt' => $loop->signalsRefreshedAt()
                    ?? ($coverage['available'] ? now()->toIso8601String() : null),
                'findingsLabel' => 'Computed on this request',
            ],
            'execution' => [
                'automated' => false,
                'note' => 'Attendance is computed against the institute\'s own working-day calendar, derived from who '
                    .'actually punched this year rather than assumed from the weekday — the register does not net off '
                    .'approved leave, so a low figure means "did not punch", not "was absent without leave".',
            ],
            'summary' => $this->summary($pos, $coverage, $raised['findings']),
            'position' => $this->position($pos, $coverage),
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
     * Deterministic, never model output — same convention as every other
     * module controller in this namespace.
     */
    private function summary(?array $pos, array $coverage, array $findings): array
    {
        if ($pos === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'No staff attendance could be computed for this academic year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        $sentences[] = $pos['activeMissingFromRegister'] > 0
            ? "{$pos['staffOnRegister']} of the {$pos['activeStaff']} active staff have a punch on the institute's "
                ."{$pos['workingDays']} working days this year; {$pos['activeMissingFromRegister']} have none, and "
                .'every figure below is computed over the staff who do.'
            : "{$pos['staffOnRegister']} active staff have a punch record across the institute's "
                ."{$pos['workingDays']} working days this year.";

        $sentences[] = "They punched on {$pos['averageAttendanceRate']}% of those days on average, with the median "
            ."member of staff at {$pos['medianAttendanceRate']}%.";

        $sentences[] = $pos['chronicStaffCount'] > 0
            ? "{$pos['chronicStaffCount']} staff ({$pos['chronicStaffShare']}%) are below "
                .StaffAttendanceIntelligence::CHRONIC_THRESHOLD.'% — present on fewer than half the institute\'s '
                ."working days — and a further ".max(0, $pos['irregularStaffCount'] - $pos['chronicStaffCount'])
                .' are between that line and '.StaffAttendanceIntelligence::IRREGULAR_THRESHOLD.'%.'
            : 'No member of staff with a punch record is below the '.StaffAttendanceIntelligence::CHRONIC_THRESHOLD.'% chronic line.';

        $count = count($findings);
        $sentences[] = match ($count) {
            0 => 'No check found enough evidence to raise a finding for this year.',
            1 => 'One finding below, with the figures it rests on.',
            default => "{$count} findings below, each with the figures it rests on.",
        };

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$pos['averageAttendanceRate']}% average staff attendance across {$pos['staffOnRegister']} staff on the register",
            'sentences' => $sentences,
        ];
    }

    private function position(?array $pos, array $coverage): ?array
    {
        if ($pos === null) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'averageAttendanceRate',
                    'label' => 'Average attendance',
                    'value' => $pos['averageAttendanceRate'],
                    'format' => 'percent',
                    'hint' => "Punches over the institute's {$pos['workingDays']} own working days this year",
                ],
                [
                    'key' => 'staffOnRegister',
                    'label' => 'Staff on the register',
                    'value' => $pos['staffOnRegister'],
                    'format' => 'count',
                    'hint' => "of {$pos['activeStaff']} active staff",
                ],
                [
                    'key' => 'activeMissingFromRegister',
                    'label' => 'Active staff with no punch',
                    'value' => $pos['activeMissingFromRegister'],
                    'format' => 'count',
                    'tone' => $pos['activeMissingFromRegister'] > 0 ? 'warning' : 'positive',
                    'hint' => $pos['activeMissingFromRegister'] > 0
                        ? 'Not counted as absent by any figure here — simply not in them'
                        : 'Every active member of staff has a punch this year',
                ],
                [
                    'key' => 'medianAttendanceRate',
                    'label' => 'Median staff member',
                    'value' => $pos['medianAttendanceRate'],
                    'format' => 'percent',
                    'hint' => 'Half the staff on the register punch more than this',
                ],
                [
                    'key' => 'irregularStaffCount',
                    'label' => 'Below '.StaffAttendanceIntelligence::IRREGULAR_THRESHOLD.'%',
                    'value' => $pos['irregularStaffCount'],
                    'format' => 'count',
                    'tone' => $pos['irregularStaffCount'] > 0 ? 'warning' : 'positive',
                    'hint' => "{$pos['irregularStaffShare']}% of staff on the register",
                ],
                [
                    'key' => 'chronicStaffCount',
                    'label' => 'Below '.StaffAttendanceIntelligence::CHRONIC_THRESHOLD.'%',
                    'value' => $pos['chronicStaffCount'],
                    'format' => 'count',
                    'tone' => $pos['chronicStaffCount'] > 0 ? 'critical' : 'positive',
                    'hint' => 'This register does not net off approved leave',
                ],
                [
                    'key' => 'workingDays',
                    'label' => "Institute's working days",
                    'value' => $pos['workingDays'],
                    'format' => 'count',
                    'hint' => "Derived from who actually punched, not assumed from the calendar",
                ],
                [
                    'key' => 'openShiftShare',
                    'label' => 'Rows with no punch-out',
                    'value' => $pos['openShiftShare'],
                    'format' => 'percent',
                    'tone' => $pos['openShiftShare'] !== null && $pos['openShiftShare'] > 20.0 ? 'warning' : null,
                    'hint' => "{$pos['openShiftRows']} of {$pos['totalPunchRows']} punch rows",
                ],
                [
                    'key' => 'departmentsWithData',
                    'label' => 'Departments with attendance data',
                    'value' => $pos['departmentsWithData'],
                    'format' => 'count',
                ],
            ],
        ];
    }

    private function breakdowns(StaffAttendanceIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byDepartment = $analytics->byDepartment();
        $byBand = $analytics->byAttendanceBand();

        return [
            [
                'key' => 'departments',
                'label' => 'By department',
                'description' => 'Weakest attendance first. Departments below '
                    .StaffAttendanceIntelligence::MIN_DEPARTMENT_COHORT
                    .' staff are shown here but are never compared against the institute in a finding. Staff with no '
                    .'department on file do not appear in any row — see the data-quality ledger for how many that is.',
                'available' => $byDepartment !== [],
                'reason' => $byDepartment === []
                    ? 'No active member of staff on the register carries a department on file.'
                    : null,
                'primaryColumn' => 'averageAttendanceRate',
                'columns' => [
                    ['key' => 'averageAttendanceRate', 'label' => 'Average attendance', 'format' => 'percent'],
                    ['key' => 'medianAttendanceRate', 'label' => 'Median', 'format' => 'percent'],
                    ['key' => 'staff', 'label' => 'Staff', 'format' => 'count'],
                    ['key' => 'irregularCount', 'label' => "Below ".StaffAttendanceIntelligence::IRREGULAR_THRESHOLD.'%', 'format' => 'count'],
                    ['key' => 'chronicCount', 'label' => "Below ".StaffAttendanceIntelligence::CHRONIC_THRESHOLD.'%', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['staff'] < StaffAttendanceIntelligence::MIN_DEPARTMENT_COHORT
                        ? 'Too few staff to compare against the institute'
                        : null,
                    'values' => [
                        'averageAttendanceRate' => $row['averageAttendanceRate'],
                        'medianAttendanceRate' => $row['medianAttendanceRate'],
                        'staff' => $row['staff'],
                        'irregularCount' => $row['irregularCount'],
                        'chronicCount' => $row['chronicCount'],
                    ],
                ], $byDepartment),
            ],
            [
                'key' => 'bands',
                'label' => 'By attendance band',
                'description' => 'How the active, on-register staff body spreads across attendance bands. Bands, '
                    .'never a list — no figure on this screen names an individual member of staff.',
                'available' => $byBand !== [],
                'reason' => $byBand === [] ? 'No staff attendance band could be computed for this year.' : null,
                'primaryColumn' => 'staff',
                'columns' => [
                    ['key' => 'staff', 'label' => 'Staff', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['staff' => $row['staff'], 'share' => $row['share']],
                ], $byBand),
            ],
        ];
    }

    private function priorities(array $findings): array
    {
        $severe = array_values(array_filter(
            $findings,
            fn ($f) => in_array($f['severity'], ['critical', 'high'], true)
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
     * Runs the WHOLE pipeline, same as every other module controller's run()
     * — see {@see BrainAttendanceIntelligenceController::run()}. This module's
     * own rule catalogue is not yet registered inside that pipeline's rule
     * set; that wiring is done centrally, separately from this controller.
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
