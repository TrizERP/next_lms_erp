<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\AttendanceIntelligence;
use App\Brain\Intelligence\AttendanceSignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainAttendanceIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'attendance', 'students');

        $analytics = new AttendanceIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new AttendanceSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · result_student_attendance_master, result_working_day_master, tblstudent_enrollment',
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
                'note' => 'Every rate is computed from days present over working days; the stored percentage column is not read, because it disagrees with the days on some institutes’ rows.',
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
     *
     * DETERMINISTIC, NEVER MODEL OUTPUT. The first sentence is about the ROLL
     * rather than the register, because a reader who is told "2,750 students
     * were evaluated" will read that as the school, and at one institute it is
     * three-quarters of it.
     */
    private function summary(?array $pos, array $coverage, array $findings): array
    {
        if ($pos === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'No attendance has been entered for this academic year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        $sentences[] = $pos['studentsOnRoll'] !== null && $pos['studentsWithoutRecord'] > 0
            ? "{$pos['students']} of the {$pos['studentsOnRoll']} students on this year's roll have an attendance "
                ."record; {$pos['studentsWithoutRecord']} have none, and every figure below is computed over the "
                .'students who do.'
            : "{$pos['students']} students have an attendance record across {$pos['standards']} classes.";

        if ($pos['attendanceRate'] !== null) {
            $sentences[] = "They attended {$pos['attendanceRate']}% of their working days — "
                ."{$pos['totalPresentDays']} days present out of {$pos['totalWorkingDays']} — with the median student "
                ."at {$pos['medianRate']}%.";
        }

        if ($pos['chronicAbsenceCount'] > 0) {
            $sentences[] = "{$pos['chronicAbsenceCount']} students ({$pos['chronicAbsenceShare']}%) are below the 75% "
                ."chronic line, and {$pos['severeAbsenceCount']} ({$pos['severeAbsenceShare']}%) are below 65%, where "
                ."the board's remediation provisions apply.";
        } else {
            $sentences[] = 'No student with a record is below the 75% chronic line.';
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
            'headline' => $pos['attendanceRate'] !== null
                ? "{$pos['attendanceRate']}% attendance across {$pos['students']} students with a record"
                : "{$pos['students']} students with an attendance record",
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
                    'key' => 'attendanceRate',
                    'label' => 'Attendance',
                    'value' => $pos['attendanceRate'],
                    'format' => 'percent',
                    'hint' => 'Days present over working days, summed across terms',
                ],
                [
                    'key' => 'students',
                    'label' => 'Students with a record',
                    'value' => $pos['students'],
                    'format' => 'count',
                    'hint' => $pos['studentsOnRoll'] !== null
                        ? "of {$pos['studentsOnRoll']} on this year's roll"
                        : 'No roll on file for this year to compare against',
                ],
                [
                    'key' => 'rollCoverage',
                    'label' => 'Roll covered',
                    // NULL where there is no roll to divide by. An em dash, not
                    // a zero: unknown coverage is not zero coverage.
                    'value' => $pos['rollCoverage'],
                    'format' => 'percent',
                    'tone' => $pos['rollCoverage'] !== null && $pos['rollCoverage'] < 90.0 ? 'warning' : 'positive',
                    'hint' => $pos['studentsWithoutRecord'] > 0
                        ? "{$pos['studentsWithoutRecord']} enrolled students have no attendance row"
                        : 'Every enrolled student has an attendance row',
                ],
                [
                    'key' => 'medianRate',
                    'label' => 'Median student',
                    'value' => $pos['medianRate'],
                    'format' => 'percent',
                    'hint' => 'Half the students attend more than this',
                ],
                [
                    'key' => 'chronicAbsence',
                    'label' => 'Below 75%',
                    'value' => $pos['chronicAbsenceCount'],
                    'format' => 'count',
                    'tone' => $pos['chronicAbsenceCount'] > 0 ? 'warning' : 'positive',
                    'hint' => "{$pos['chronicAbsenceShare']}% of students with a record",
                ],
                [
                    'key' => 'severeAbsence',
                    'label' => 'Below 65%',
                    'value' => $pos['severeAbsenceCount'],
                    'format' => 'count',
                    'tone' => $pos['severeAbsenceCount'] > 0 ? 'critical' : 'positive',
                    'hint' => 'Where board remediation provisions apply',
                ],
                [
                    'key' => 'standards',
                    'label' => 'Classes',
                    'value' => $pos['standards'],
                    'format' => 'count',
                ],
                [
                    'key' => 'terms',
                    'label' => 'Terms recorded',
                    'value' => $pos['terms'],
                    'format' => 'count',
                    'hint' => 'The register carries a term, not a date',
                ],
            ],
        ];
    }

    private function breakdowns(AttendanceIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byStandard = $analytics->byStandard();
        $byTier = $analytics->byTier();
        $byTerm = $analytics->byTerm();

        return [
            [
                'key' => 'standards',
                'label' => 'By class',
                'description' => 'Weakest attendance first. Classes below '
                    .AttendanceIntelligence::MIN_CLASS_COHORT
                    .' students are shown here but are never compared against the institute in a finding.',
                'available' => $byStandard !== [],
                'reason' => $byStandard === [] ? 'No attendance row this year carries a class.' : null,
                'primaryColumn' => 'attendanceRate',
                'columns' => [
                    ['key' => 'attendanceRate', 'label' => 'Attendance', 'format' => 'percent'],
                    ['key' => 'medianRate', 'label' => 'Median', 'format' => 'percent'],
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'chronicCount', 'label' => 'Below 75%', 'format' => 'count'],
                    ['key' => 'severeCount', 'label' => 'Below 65%', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['students'] < AttendanceIntelligence::MIN_CLASS_COHORT
                        ? 'Too few students to compare against the institute'
                        : null,
                    'values' => [
                        'attendanceRate' => $row['attendanceRate'],
                        'medianRate' => $row['medianRate'],
                        'students' => $row['students'],
                        'chronicCount' => $row['chronicCount'],
                        'severeCount' => $row['severeCount'],
                    ],
                ], $byStandard),
            ],
            [
                'key' => 'tiers',
                'label' => 'By attendance band',
                'description' => 'How the students with a record are distributed across the bands the boards use.',
                'available' => $byTier !== [],
                'reason' => null,
                'primaryColumn' => 'students',
                'columns' => [
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['students' => $row['students'], 'share' => $row['share']],
                ], $byTier),
            ],
            [
                'key' => 'terms',
                'label' => 'By term',
                'description' => 'The register carries a term rather than a date, so this is the finest trend the '
                    .'data supports — attendance week by week cannot be read from it.',
                'available' => count($byTerm) > 1,
                'reason' => count($byTerm) <= 1
                    ? 'Only one term has attendance recorded for this year, so there is nothing to compare across.'
                    : null,
                'primaryColumn' => 'attendanceRate',
                'columns' => [
                    ['key' => 'attendanceRate', 'label' => 'Attendance', 'format' => 'percent'],
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'presentDays', 'label' => 'Days present', 'format' => 'count'],
                    ['key' => 'workingDays', 'label' => 'Working days', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'attendanceRate' => $row['attendanceRate'],
                        'students' => $row['students'],
                        'presentDays' => $row['presentDays'],
                        'workingDays' => $row['workingDays'],
                    ],
                ], $byTerm),
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

