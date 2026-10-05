<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\AcademicIntelligence;
use App\Brain\Intelligence\AcademicSignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainAcademicIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'academic', 'periods');

        $analytics = new AcademicIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new AcademicSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · timetable',
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
                'note' => 'Academic scheduling metrics are evaluated directly from the live timetable master.',
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

    private function summary(?array $pos, array $coverage, array $findings): array
    {
        if ($pos === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'No academic scheduling data in this session.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        // ROWS, and said so. One timetable row is one class-division's period on
        // one weekday in one marking period — it is not a teacher's weekly
        // period, and calling it one is what produced "129 periods a week".
        $sentences[] = "{$pos['totalPeriods']} timetable rows cover {$pos['standards']} standards across "
            ."{$pos['divisions']} divisions and {$pos['subjects']} subjects.";

        $sentences[] = $pos['medianPeriodsPerTeacher'] !== null
            ? "{$pos['teachers']} teachers appear on it. The median teacher works "
                ."{$pos['medianPeriodsPerTeacher']} distinct periods a week and the heaviest works "
                ."{$pos['maxPeriodsPerTeacher']}."
            : "{$pos['teachers']} teachers appear on it.";

        if ($pos['clashingSlots'] > 0) {
            $sentences[] = "{$pos['clashingSlots']} weekly slots place one of {$pos['teachersClashing']} teachers in "
                .'more than one class at the same time.';
        }

        if ($pos['classDoubleBookings'] > 0) {
            $sentences[] = "{$pos['classDoubleBookings']} slots schedule two lessons for the same class-division at once.";
        }

        if ($pos['unassignedPeriods'] > 0) {
            $sentences[] = "{$pos['unassignedPeriods']} rows carry no teacher at all.";
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
            'headline' => $pos['medianPeriodsPerTeacher'] !== null
                ? "{$pos['teachers']} teachers, median week of {$pos['medianPeriodsPerTeacher']} periods"
                : "{$pos['teachers']} teachers on the timetable",
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
                    'key' => 'totalPeriods',
                    'label' => 'Timetable rows',
                    'value' => $pos['totalPeriods'],
                    'format' => 'count',
                    // Naming the grain in the hint is what stops the next reader
                    // dividing this by the teacher count and calling it a load.
                    'hint' => 'One class-division’s period on one weekday in one marking period',
                ],
                [
                    'key' => 'standards',
                    'label' => 'Standards',
                    'value' => $pos['standards'],
                    'format' => 'count',
                ],
                [
                    'key' => 'subjects',
                    'label' => 'Subjects taught',
                    'value' => $pos['subjects'],
                    'format' => 'count',
                ],
                [
                    'key' => 'teachers',
                    'label' => 'Teaching faculty',
                    'value' => $pos['teachers'],
                    'format' => 'count',
                ],
                [
                    'key' => 'medianPeriodsPerTeacher',
                    'label' => 'Median teacher week',
                    'value' => $pos['medianPeriodsPerTeacher'],
                    'format' => 'count',
                    'hint' => $pos['maxPeriodsPerTeacher'] !== null
                        ? "Distinct periods a week; the heaviest works {$pos['maxPeriodsPerTeacher']}"
                        : 'Distinct periods a week',
                ],
                [
                    'key' => 'clashingSlots',
                    'label' => 'Clashing slots',
                    'value' => $pos['clashingSlots'],
                    'format' => 'count',
                    'tone' => $pos['clashingSlots'] > 0 ? 'critical' : 'positive',
                    'hint' => "One teacher in two classes at once, across {$pos['teachersClashing']} teachers",
                ],
                [
                    'key' => 'classDoubleBookings',
                    'label' => 'Class double-bookings',
                    'value' => $pos['classDoubleBookings'],
                    'format' => 'count',
                    'tone' => $pos['classDoubleBookings'] > 0 ? 'warning' : 'positive',
                    'hint' => 'Two lessons scheduled for one class in one slot',
                ],
                [
                    'key' => 'unassignedPeriods',
                    'label' => 'Unassigned slots',
                    'value' => $pos['unassignedPeriods'],
                    'format' => 'count',
                    'tone' => $pos['unassignedPeriods'] > 0 ? 'warning' : 'positive',
                ],
            ],
        ];
    }

    private function breakdowns(AcademicIntelligence $analytics, array $coverage): array
    {
        if (!$coverage['available']) {
            return [];
        }

        $bySubject = $analytics->bySubject();
        $byTeacher = $analytics->byTeacherLoad();

        return [
            [
                'key' => 'subjects',
                'label' => 'By subject (Top 20)',
                'description' => 'Curriculum period distribution and faculty coverage per subject.',
                'available' => !empty($bySubject),
                'reason' => null,
                'primaryColumn' => 'periods',
                'columns' => [
                    ['key' => 'periods', 'label' => 'Weekly periods', 'format' => 'count'],
                    ['key' => 'teachers', 'label' => 'Faculty assigned', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Curriculum %', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'periods' => $row['periods'],
                        'teachers' => $row['teachers'],
                        'share' => $row['share'],
                    ],
                ], $bySubject),
            ],
            [
                'key' => 'teacher_loads',
                'label' => 'Teacher weeks',
                'description' => 'Distinct periods a week, counted inside one marking period — not timetable rows. '
                    .'A teacher who teaches a different subject in the same slot in each term works one period, not '
                    .'two, and the row count says otherwise.',
                'available' => ! empty($byTeacher),
                'reason' => empty($byTeacher) ? 'No teacher is assigned to a period this year.' : null,
                'primaryColumn' => 'periodsPerWeek',
                'columns' => [
                    ['key' => 'periodsPerWeek', 'label' => 'Periods a week', 'format' => 'count'],
                    ['key' => 'classes', 'label' => 'Classes', 'format' => 'count'],
                    ['key' => 'standards', 'label' => 'Standards', 'format' => 'count'],
                    ['key' => 'subjects', 'label' => 'Subjects', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['markingPeriods'] > 1
                        ? "Across {$row['markingPeriods']} marking periods"
                        : null,
                    'values' => [
                        'periodsPerWeek' => $row['periodsPerWeek'],
                        'classes' => $row['classes'],
                        'standards' => $row['standards'],
                        'subjects' => $row['subjects'],
                    ],
                ], $byTeacher),
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

