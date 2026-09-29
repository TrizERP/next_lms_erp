<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\LmsActivityIntelligence;
use App\Brain\Intelligence\LmsActivitySignalRules;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LMS Activity Intelligence — what children were set to do, and whether the
 * published curriculum is what they were set to do it from.
 *
 * This is distinct from Teach/Learn, which is the content catalogue alone —
 * `content_master`/`sub_std_map` — and never reads what a learner did with any
 * of it. It is also distinct from Homework Intelligence, which this module
 * composes rather than duplicates: every submission figure here is read
 * straight from a real {@see \App\Brain\Intelligence\HomeworkIntelligence}
 * instance. See {@see LmsActivityIntelligence} for what the blend adds beyond
 * the two composed modules and why PAL and `lms_assignment` are not folded in.
 */
class BrainLmsActivityIntelligenceController extends Controller
{
    private string $tenantId = '';

    private ?string $syear = null;

    private string $actorId = '';

    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        $loop = new ModuleLoop($this->tenantId, $this->syear, 'lms-activity', 'submissions');

        $analytics = new LmsActivityIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        // Not gated on coverage as a whole: each rule decides for itself
        // whether it has enough of its own half to say anything, the same way
        // TeachLearnSignalRules does for the chapter check.
        $rules = new LmsActivitySignalRules($analytics, $this->syear);
        $raised = $rules->run();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · content_master, sub_std_map, homework, lms_assignment',
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
                'note' => 'This screen blends two real sources: the published content catalogue (content_master, '
                    .'sub_std_map) and homework activity (homework). The two are matched on the same (class, '
                    .'subject) pair, tenant- and year-scoped on both sides. There is no due date on homework, so '
                    .'turnaround is reported as a median number of days rather than an on-time rate.',
            ],
            'summary' => $this->summary($analytics, $coverage, $raised['findings']),
            'position' => $this->position($analytics, $coverage),
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
     * "What is happening", composed from the same figures the cards below
     * show. DETERMINISTIC, NEVER MODEL OUTPUT.
     *
     * @param  array<string,mixed>  $coverage
     * @param  array<int,array<string,mixed>>  $findings
     * @return array<string,mixed>
     */
    private function summary(LmsActivityIntelligence $analytics, array $coverage, array $findings): array
    {
        $count = count($findings);

        if (! $coverage['available']) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'Neither the content catalogue nor homework activity is '
                    .'available for this academic year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [$analytics->position()['summary']];

        $sentences[] = match ($count) {
            0 => 'No check found enough evidence to raise a finding for this year.',
            1 => 'One finding below, with the figures it rests on.',
            default => "{$count} findings below, each with the figures it rests on.",
        };

        $metrics = $analytics->position()['metrics'];
        $headlineParts = array_values(array_filter([
            isset($metrics['childAssignments']) && $metrics['childAssignments'] !== null
                ? "{$metrics['childAssignments']} homework set"
                : null,
            isset($metrics['contentActivityAlignment']) && $metrics['contentActivityAlignment'] !== null
                ? "{$metrics['contentActivityAlignment']}% content/activity alignment"
                : null,
        ]));

        return [
            'available' => true,
            'reason' => null,
            'headline' => $headlineParts !== [] ? implode(' · ', $headlineParts) : null,
            'sentences' => $sentences,
        ];
    }

    /**
     * @param  array<string,mixed>  $coverage
     * @return array<string,mixed>
     */
    private function position(LmsActivityIntelligence $analytics, array $coverage): array
    {
        $pos = $analytics->position();
        $metrics = $pos['metrics'];

        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'contentCoverage',
                    'label' => 'Curriculum covered',
                    'value' => $metrics['contentCoverage'] ?? null,
                    'format' => 'percent',
                    'hint' => 'Catalogue courses carrying published content this year',
                ],
                [
                    'key' => 'contentActivityAlignment',
                    'label' => 'Content used in homework',
                    'value' => $metrics['contentActivityAlignment'] ?? null,
                    'format' => 'percent',
                    'tone' => ($metrics['contentActivityAlignment'] ?? 100) < 50.0 ? 'warning' : null,
                    'hint' => 'Share of content-bearing courses that also carry homework this year',
                ],
                [
                    'key' => 'childAssignments',
                    'label' => 'Homework set',
                    'value' => $metrics['childAssignments'] ?? null,
                    'format' => 'count',
                    'hint' => 'One row per child per piece of work',
                ],
                [
                    'key' => 'submissionRate',
                    'label' => 'Submission rate',
                    'value' => $metrics['submissionRate'] ?? null,
                    'format' => 'percent',
                    'tone' => ($metrics['submissionRate'] ?? 100) < 50.0 ? 'warning' : null,
                    'hint' => ($metrics['statusReliable'] ?? true) === false
                        ? 'The status and submission date disagree on some rows — see record checks below'
                        : null,
                ],
                [
                    'key' => 'medianSubmissionLagDays',
                    'label' => 'Median turnaround',
                    'value' => $metrics['medianSubmissionLagDays'] ?? null,
                    'format' => 'count',
                    'hint' => 'Days between a piece of work being set and being submitted; no due date is recorded',
                ],
                [
                    'key' => 'contentWithoutActivity',
                    'label' => 'Content with no homework',
                    'value' => $metrics['contentWithoutActivity'] ?? null,
                    'format' => 'count',
                    'tone' => ($metrics['contentWithoutActivity'] ?? 0) > 0 ? 'warning' : null,
                    'hint' => 'Courses carrying published material and no homework this year',
                ],
                [
                    'key' => 'activityOutsideCatalogue',
                    'label' => 'Homework outside the catalogue',
                    'value' => $metrics['activityOutsideCatalogue'] ?? null,
                    'format' => 'count',
                    'hint' => 'Class/subject pairs carrying homework that the course catalogue does not offer',
                ],
                [
                    'key' => 'classReach',
                    'label' => 'Classes reached',
                    'value' => $metrics['classReach'] ?? null,
                    'format' => 'percent',
                    'hint' => 'Share of this year’s enrolled classes with at least one piece of homework',
                ],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $coverage
     * @return array<int,array<string,mixed>>
     */
    private function breakdowns(LmsActivityIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byClass = $analytics->byClass();

        return [
            [
                'key' => 'by_class',
                'label' => 'Content and activity by class',
                'description' => 'Catalogue courses, published content and homework activity, blended by class. A '
                    .'class can carry courses with no homework, homework with no catalogue course at all, or both — '
                    .'each state is shown rather than merged away.',
                'available' => $byClass !== [],
                'reason' => $byClass === [] ? 'No class carries a catalogue course or homework activity this year.' : null,
                'primaryColumn' => 'homeworkSet',
                'columns' => [
                    ['key' => 'courses', 'label' => 'Courses', 'format' => 'count'],
                    ['key' => 'withContent', 'label' => 'With content', 'format' => 'count'],
                    ['key' => 'contentCoverage', 'label' => 'Content coverage', 'format' => 'percent'],
                    ['key' => 'withContentNoActivity', 'label' => 'Content, no homework', 'format' => 'count'],
                    ['key' => 'homeworkSet', 'label' => 'Homework set', 'format' => 'count'],
                    ['key' => 'homeworkStudents', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'submissionRate', 'label' => 'Submission rate', 'format' => 'percent'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'courses' => $row['courses'],
                        'withContent' => $row['withContent'],
                        'contentCoverage' => $row['contentCoverage'],
                        'withContentNoActivity' => $row['withContentNoActivity'],
                        'homeworkSet' => $row['homeworkSet'],
                        'homeworkStudents' => $row['homeworkStudents'],
                        'submissionRate' => $row['submissionRate'],
                    ],
                ], $byClass),
            ],
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $findings
     * @return array<int,array<string,mixed>>
     */
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
     * Write this module's findings to the signal ledger. IDEMPOTENT —
     * `SignalWriter` dedupes on (tenant, rule, year).
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
