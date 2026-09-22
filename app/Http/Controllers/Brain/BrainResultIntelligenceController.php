<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\ResultIntelligence;
use App\Brain\Intelligence\ResultSignalRules;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Result Intelligence — one institute, one academic year.
 *
 * ── THIS EMITS THE CANONICAL SHAPE ──────────────────────────────────────────
 *
 * Unlike BrainFeesIntelligenceController, which predates it, this returns
 * `ModuleIntelligencePayload` directly — the shape declared in
 * lms_k12/components/intelligence/module/payload.ts. The frontend contract for
 * this module is therefore ~80 lines with no adapter: `load` is a bare
 * brainFetch. That is the pattern every module after Fees follows.
 *
 * ── TENANT AND YEAR ARE NOT THE CALLER'S TO CHOOSE ──────────────────────────
 *
 * The route group applies `brain.auth` and `brain.tenant`, so the tenant comes
 * from the signed token. The year is validated by AcademicYear::resolve against
 * that institute's own rows, so the worst a caller can do with `?syear=` is
 * look at a different year of their own school.
 *
 * ── THE ACTION LOOP ─────────────────────────────────────────────────────────
 *
 * `recommendations`, `decisionTrail` and `learning` are read back from the
 * signal ledger through `ModuleLoop`. Result findings reach it via
 * `ModuleSignalBridge`, which `IntelligencePipeline` runs alongside the LMS and
 * fee rules — so a result finding reaches a recommendation by exactly the route
 * a fee finding does.
 *
 * WHAT IS STILL HONESTLY EMPTY. Until someone presses "Analyse this year", the
 * ledger holds nothing for this module and all three sections report that in
 * words. A recommendation whose rule has no approved cause in `RuleCatalogue`
 * carries `why => null` rather than a composed explanation, and a decision
 * nobody has taken is absent rather than shown as pending. Emitting a fabricated
 * recommendation so the section looks populated is precisely the failure this
 * whole layer was built to avoid.
 */
class BrainResultIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'result', 'students');

        $analytics = new ResultIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new ResultSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $position = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · result_personalize_marks',
            'academicYear' => ['syear' => $this->syear],
            'coverage' => $coverage,
            'freshness' => [
                'positionLabel' => 'Read live at page load',
                // The ledger's own timestamp once the loop has run for this
                // module, falling back to now for the live per-request
                // computation that always happens.
                'findingsRefreshedAt' => $loop->signalsRefreshedAt()
                    ?? ($coverage['available'] ? now()->toIso8601String() : null),
                'findingsLabel' => 'Computed on this request',
            ],
            'execution' => [
                'automated' => false,
                'note' => 'Figures are read live at page load. Findings reach the signal ledger when the analysis is run, which is what gives them recommendations and a decision trail.',
            ],
            'summary' => $this->summary($analytics, $position, $raised['findings']),
            'position' => $this->position($position, $coverage),
            'breakdowns' => $this->breakdowns($analytics, $coverage),
            'findings' => $raised['findings'],
            // Priorities are the severe findings, not a second computation:
            // deriving them here rather than re-running the rules guarantees the
            // two sections can never disagree with each other.
            'priorities' => $this->priorities($raised['findings']),
            'recommendations' => $loop->recommendations(),
            'decisionTrail' => $loop->decisionTrail(),
            'learning' => $loop->learning(),
            'dataQuality' => $analytics->dataQuality(),
            'ruleStatus' => $raised['ruleStatus'],
        ]));
    }

    /* ============================================================== assembly */

    /**
     * "What is happening", composed from the figures.
     *
     * DETERMINISTIC AND REPRODUCIBLE — assembled from the same numbers the cards
     * below show, never from a model. Two requests against unchanged data
     * produce the same sentences.
     */
    private function summary(ResultIntelligence $analytics, ?array $position, array $findings): array
    {
        if ($position === null) {
            return [
                'available' => false,
                'reason' => $analytics->coverage()['reason'],
                'headline' => null,
                'sentences' => [],
            ];
        }

        $threshold = ResultIntelligence::THRESHOLD;
        $sentences = [];

        $sentences[] = "{$position['students']} students were assessed across {$position['subjects']} subjects and "
            ."{$position['classes']} classes, from {$position['markEntries']} mark entries.";

        if ($position['meanPercentage'] !== null) {
            $sentences[] = "The mark-weighted average is {$position['meanPercentage']}%"
                .($position['medianStudentPercentage'] !== null
                    ? ", and the median student sits at {$position['medianStudentPercentage']}%."
                    : '.');
        }

        if ($position['studentsBelowThresholdShare'] !== null) {
            $sentences[] = "{$position['studentsBelowThreshold']} students ({$position['studentsBelowThresholdShare']}%) "
                ."have a year total below {$threshold}%.";
        }

        $previous = $analytics->previousYearMean();
        if ($previous !== null && $position['meanPercentage'] !== null) {
            $delta = round($position['meanPercentage'] - $previous, 1);
            $direction = $delta >= 0 ? 'up' : 'down';
            $sentences[] = 'Against '.$analytics->previousYear().", that is {$direction} "
                .abs($delta).' points.';
        }

        $sentences[] = count($findings) === 0
            ? 'No check found enough evidence to raise a finding for this year.'
            : count($findings).' finding'.(count($findings) === 1 ? '' : 's').' were raised, each with the figures behind it.';

        return [
            'available' => true,
            'reason' => null,
            'headline' => $position['meanPercentage'] !== null
                ? "{$position['students']} students assessed · {$position['meanPercentage']}% school average"
                : "{$position['students']} students assessed",
            'sentences' => $sentences,
        ];
    }

    private function position(?array $position, array $coverage): ?array
    {
        if ($position === null) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        $threshold = ResultIntelligence::THRESHOLD;

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'meanPercentage', 'label' => 'School average', 'value' => $position['meanPercentage'],
                    'format' => 'percent', 'hint' => 'mark-weighted across every subject',
                ],
                [
                    'key' => 'students', 'label' => 'Students assessed', 'value' => $position['students'],
                    'format' => 'count',
                ],
                [
                    'key' => 'subjects', 'label' => 'Subjects', 'value' => $position['subjects'], 'format' => 'count',
                ],
                [
                    'key' => 'classes', 'label' => 'Classes', 'value' => $position['classes'], 'format' => 'count',
                ],
                [
                    'key' => 'belowThreshold', 'label' => "Below {$threshold}%",
                    'value' => $position['studentsBelowThreshold'], 'format' => 'count',
                    'tone' => $position['studentsBelowThreshold'] > 0 ? 'medium' : 'positive',
                    'hint' => $position['studentsBelowThresholdShare'] !== null
                        ? $position['studentsBelowThresholdShare'].'% of the cohort'
                        : null,
                ],
                [
                    'key' => 'median', 'label' => 'Median student', 'value' => $position['medianStudentPercentage'],
                    'format' => 'percent',
                ],
                [
                    'key' => 'markEntries', 'label' => 'Mark entries', 'value' => $position['markEntries'],
                    'format' => 'count', 'hint' => 'one per student, subject and exam',
                ],
                [
                    'key' => 'exams', 'label' => 'Exam components', 'value' => $position['exams'], 'format' => 'count',
                ],
            ],
        ];
    }

    private function breakdowns(ResultIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $percentColumn = [['key' => 'percentage', 'label' => 'Average', 'format' => 'percent']];

        $byClass = $analytics->byClass();
        $bySubject = $analytics->bySubject();
        $byExam = $analytics->byExam();

        return [
            [
                'key' => 'classes',
                'label' => 'By class',
                'description' => 'Weakest first. The average is mark-weighted, so a class sitting more papers is not '
                    .'advantaged by it.',
                'available' => $byClass !== [],
                'reason' => null,
                'primaryColumn' => 'percentage',
                'columns' => array_merge($percentColumn, [
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'subjects', 'label' => 'Subjects', 'format' => 'count'],
                    ['key' => 'entries', 'label' => 'Entries', 'format' => 'count'],
                ]),
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'percentage' => $row['percentage'],
                        'students' => $row['students'],
                        'subjects' => $row['subjects'],
                        'entries' => $row['entries'],
                    ],
                ], $byClass),
            ],
            [
                'key' => 'subjects',
                'label' => 'By subject',
                'description' => 'Across every class that sits the subject.',
                'available' => $bySubject !== [],
                'reason' => null,
                'primaryColumn' => 'percentage',
                'columns' => array_merge($percentColumn, [
                    ['key' => 'classes', 'label' => 'Classes', 'format' => 'count'],
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'entries', 'label' => 'Entries', 'format' => 'count'],
                ]),
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'percentage' => $row['percentage'],
                        'classes' => $row['classes'],
                        'students' => $row['students'],
                        'entries' => $row['entries'],
                    ],
                ], $bySubject),
            ],
            [
                'key' => 'exams',
                'label' => 'By exam component',
                // Said plainly because it changes how the row is read: these are
                // not all academic papers, and a reader comparing "Attendance"
                // to "S.A-II" as if they were would draw a wrong conclusion.
                'description' => 'Includes non-academic components such as attendance and notebook marks, which are '
                    .'recorded here the same way papers are.',
                'available' => $byExam !== [],
                'reason' => null,
                'primaryColumn' => 'entries',
                'columns' => [
                    ['key' => 'entries', 'label' => 'Entries', 'format' => 'count'],
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'percentage', 'label' => 'Average', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'entries' => $row['entries'],
                        'students' => $row['students'],
                        'percentage' => $row['percentage'],
                    ],
                ], $byExam),
            ],
        ];
    }

    /** The findings a person should look at first — severe ones, in order. */
    private function priorities(array $findings): array
    {
        $severe = array_values(array_filter(
            $findings,
            fn ($finding) => in_array($finding['severity'], ['critical', 'high'], true),
        ));

        return array_map(fn ($finding) => [
            'id' => $finding['id'],
            'severity' => $finding['severity'],
            'severityLabel' => $finding['severityLabel'],
            'title' => $finding['title'],
            'whatHappened' => $finding['whatHappened'],
            'whyItMatters' => $finding['whyItMatters'],
            'evidence' => $finding['evidence'],
            'impact' => $finding['impact'],
            'nextStep' => $finding['recommendation'],
            'owner' => $finding['owner'],
            'confidence' => $finding['confidence'],
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
