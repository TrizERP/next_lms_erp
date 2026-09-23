<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\HomeworkIntelligence;
use App\Brain\Intelligence\HomeworkSignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainHomeworkIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'homework', 'assignments');

        $analytics = new HomeworkIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new HomeworkSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $coverage['available'] ? $analytics->position()['metrics'] : null;

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · homework, subject, standard, division, tblstudent (lms_assignment counted separately)',
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
                'note' => 'Homework findings are evaluated per request from live assignment ledgers.',
            ],
            'summary' => $this->summary($analytics, $pos, $coverage, $raised['findings']),
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
     * DETERMINISTIC, NEVER MODEL OUTPUT. The wording says SUBMITTED and never
     * COMPLETED, because `completion_status` holds 'Y' exactly when a submission
     * date is set and no row in this database carries a teacher review.
     */
    private function summary(HomeworkIntelligence $analytics, ?array $pos, array $coverage, array $findings): array
    {
        if ($pos === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'No homework was set in this academic year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [$analytics->position()['summary']];

        $assignments = (int) $pos['assignments'];
        if ($assignments > 0) {
            $sentences[] = "A separate assignment workflow holds {$assignments} record"
                .($assignments === 1 ? '' : 's').' for this year. It has its own submission model — the student '
                .'submits and the teacher returns — so it is counted here and never added into the figures above.';
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
            'headline' => "{$pos['childAssignments']} pieces of homework · {$pos['submissionRate']}% returned",
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
                    'key' => 'childAssignments',
                    'label' => 'Pieces of homework set',
                    'value' => $pos['childAssignments'],
                    'format' => 'count',
                    // The grain, stated on the card itself: this table writes one
                    // row per child, so the count is not a count of assignments.
                    'hint' => "One row per child — {$pos['students']} children across {$pos['classes']} "
                        .'class'.((int) $pos['classes'] === 1 ? '' : 'es'),
                ],
                [
                    'key' => 'submissionRate',
                    'label' => 'Marked returned',
                    'value' => $pos['submissionRate'],
                    'format' => 'percent',
                    // An unreliable rate is never shown as positive, whatever it
                    // says: the point of the flag is that the number is wrong.
                    'tone' => ! $pos['statusReliable']
                        ? 'warning'
                        : (($pos['submissionRate'] ?? 0) >= 75 ? 'positive' : 'warning'),
                    'hint' => $pos['statusReliable']
                        ? "{$pos['submitted']} of {$pos['childAssignments']} — a submission flag, not a teacher’s "
                            .'judgement'
                        : "Not reliable: {$pos['statusContradictions']} of {$pos['childAssignments']} rows carry a "
                            .'submission date and a status saying the work never came back',
                ],
                [
                    'key' => 'outstanding',
                    'label' => 'Never returned',
                    'value' => $pos['outstanding'],
                    'format' => 'count',
                    'tone' => ($pos['outstanding'] ?? 0) > 0 ? 'warning' : 'positive',
                ],
                [
                    'key' => 'reviewRate',
                    'label' => 'Reviewed by a teacher',
                    'value' => $pos['reviewRate'],
                    'format' => 'percent',
                    'tone' => ($pos['reviewRate'] ?? 0) > 0 ? null : 'warning',
                    'hint' => (int) $pos['reviewed'] === 0
                        ? 'No piece of work this year carries a reviewer, feedback or a remark'
                        : "{$pos['reviewed']} of {$pos['submitted']} submitted pieces",
                ],
                [
                    'key' => 'classReach',
                    'label' => 'Classes reached',
                    // NULL where the roll cannot be read — never 0%, which would
                    // say the module reaches nobody.
                    'value' => $pos['classReach'],
                    'format' => 'percent',
                    'tone' => ($pos['classReach'] ?? 100) < 50 ? 'warning' : null,
                    'hint' => $pos['classReach'] === null
                        ? 'The roll for this year could not be read, so reach cannot be computed'
                        : "{$pos['classes']} classes set homework this year",
                ],
                [
                    'key' => 'subjects',
                    'label' => 'Subjects',
                    'value' => $pos['subjects'],
                    'format' => 'count',
                ],
                [
                    'key' => 'assignments',
                    'label' => 'Separate assignment records',
                    'value' => $pos['assignments'],
                    'format' => 'count',
                    'hint' => 'A different table with a two-sided submission model — counted, never added in',
                ],
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function breakdowns(HomeworkIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $bySubject = $analytics->bySubject();
        $byClass = $analytics->byClass();
        $byMonth = $analytics->byMonth();

        $floor = 'Fewer than '.HomeworkIntelligence::MIN_CLASS_COHORT.' pieces of work — no rate is shown, because '
            .'it would describe the individual children in it';

        return [
            [
                'key' => 'by_subject',
                'label' => 'By subject',
                'description' => 'Homework set and returned, by the subject it was set for. Subject names come from '
                    .'the subject master; a subject missing from it is shown by its key rather than given a name.',
                'available' => $bySubject !== [],
                'reason' => $bySubject === [] ? 'No homework this year is linked to a subject.' : null,
                'primaryColumn' => 'set',
                'columns' => [
                    ['key' => 'set', 'label' => 'Set', 'format' => 'count'],
                    ['key' => 'submitted', 'label' => 'Returned', 'format' => 'count'],
                    ['key' => 'submissionRate', 'label' => 'Returned', 'format' => 'percent'],
                    ['key' => 'students', 'label' => 'Children', 'format' => 'count'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'tone' => $row['submissionRate'] !== null && $row['submissionRate'] < 60.0 ? 'attention' : null,
                    'note' => $row['suppressed'] ? $floor : null,
                    'values' => [
                        'set' => $row['set'],
                        'submitted' => $row['submitted'],
                        'submissionRate' => $row['submissionRate'],
                        'students' => $row['students'],
                    ],
                ], $bySubject),
            ],
            [
                'key' => 'by_class',
                'label' => 'By class',
                'description' => 'A class here is a standard AND its section, as it is everywhere else in this system '
                    .'— an institute with five standards across fifteen sections has fifteen classes, not five.',
                'available' => $byClass !== [],
                'reason' => $byClass === [] ? 'No homework this year is linked to a class.' : null,
                'primaryColumn' => 'set',
                'columns' => [
                    ['key' => 'set', 'label' => 'Set', 'format' => 'count'],
                    ['key' => 'submitted', 'label' => 'Returned', 'format' => 'count'],
                    ['key' => 'submissionRate', 'label' => 'Returned', 'format' => 'percent'],
                    ['key' => 'students', 'label' => 'Children', 'format' => 'count'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'tone' => $row['submissionRate'] !== null && $row['submissionRate'] < 60.0 ? 'attention' : null,
                    'note' => $row['suppressed'] ? $floor : null,
                    'values' => [
                        'set' => $row['set'],
                        'submitted' => $row['submitted'],
                        'submissionRate' => $row['submissionRate'],
                        'students' => $row['students'],
                    ],
                ], $byClass),
            ],
            [
                'key' => 'by_month',
                'label' => 'When it was set',
                'description' => 'Homework by the month it was set, so a term’s habit is distinguishable from a '
                    .'single week’s burst. A module used for one month is not a module in use.',
                'available' => $byMonth !== [],
                'reason' => $byMonth === [] ? 'No homework this year carries a date.' : null,
                'primaryColumn' => 'set',
                'columns' => [
                    ['key' => 'set', 'label' => 'Set', 'format' => 'count'],
                    ['key' => 'submitted', 'label' => 'Returned', 'format' => 'count'],
                    ['key' => 'submissionRate', 'label' => 'Returned', 'format' => 'percent'],
                    ['key' => 'students', 'label' => 'Children', 'format' => 'count'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['suppressed'] ? $floor : null,
                    'values' => [
                        'set' => $row['set'],
                        'submitted' => $row['submitted'],
                        'submissionRate' => $row['submissionRate'],
                        'students' => $row['students'],
                    ],
                ], $byMonth),
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function priorities(array $findings): array
    {
        $severe = array_values(array_filter(
            $findings,
            static fn ($f) => in_array($f['severity'], ['critical', 'high', 'medium'], true),
        ));

        return array_map(static fn ($f) => [
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

