<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\StudentIntelligence;
use App\Brain\Intelligence\StudentSignalRules;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainStudentIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'student', 'students');

        $analytics = new StudentIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new StudentSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · tblstudent_enrollment, tblstudent, standard, division, student_quota',
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
                'note' => 'Student cohort metrics are synthesized live from official enrollment ledgers.',
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
     * DETERMINISTIC, NEVER MODEL OUTPUT. The class figure is the median rather
     * than the mean, because one standard held open for a handful of students
     * drags a mean well below every room a child actually sits in.
     */
    private function summary(?array $pos, array $coverage, array $findings): array
    {
        if ($pos === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'No students are enrolled for this academic year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        $sentences[] = "{$pos['students']} students are enrolled across {$pos['classes']} classes — "
            ."{$pos['standards']} standards divided into {$pos['sections']} sections.";

        if ($pos['medianClassSize'] !== null) {
            $sentences[] = "The median class holds {$pos['medianClassSize']} students and the largest holds "
                ."{$pos['largestClassSize']}"
                .($pos['crowdedClasses'] > 0
                    ? "; {$pos['crowdedClasses']} classes are above "
                        .StudentIntelligence::CROWDED_CLASS.' students.'
                    : '.');
        }

        if ($pos['femaleShare'] !== null) {
            $sentences[] = "The roll is {$pos['femaleShare']}% girls and {$pos['maleShare']}% boys"
                .($pos['genderUnrecorded'] > 0
                    ? ', computed over the students whose gender is recorded; '
                        .$pos['genderUnrecorded'].' student'.($pos['genderUnrecorded'] === 1 ? ' has' : 's have')
                        .' none.'
                    : '.');
        }

        // Retention is stated with what it cannot separate, because a school
        // whose leaving standard graduates a hundred students sees a hundred
        // non-returns in a year with no attrition at all.
        if ($pos['retentionRate'] !== null) {
            $previous = (int) $this->syear - 1;
            $sentences[] = "{$pos['returningStudents']} of the {$pos['previousYearRoll']} students enrolled in "
                ."{$previous} returned ({$pos['retentionRate']}%), and {$pos['newThisYear']} students are new this "
                .'year. Graduating students and transfers are not distinguished here.';
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
            'headline' => $pos['medianClassSize'] !== null
                ? "{$pos['students']} students in {$pos['classes']} classes, median class of {$pos['medianClassSize']}"
                : "{$pos['students']} students in {$pos['classes']} classes",
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
                    'key' => 'students',
                    'label' => 'Students enrolled',
                    'value' => $pos['students'],
                    'format' => 'count',
                    'hint' => $pos['previousYearRoll'] > 0
                        ? "{$pos['previousYearRoll']} were enrolled last year"
                        : 'No previous year on file to compare against',
                ],
                [
                    'key' => 'classes',
                    'label' => 'Classes',
                    'value' => $pos['classes'],
                    'format' => 'count',
                    // A class is the PAIR. Naming both parts in the hint is what
                    // stops the next reader dividing the roll by sections.
                    'hint' => "{$pos['standards']} standards across {$pos['sections']} sections",
                ],
                [
                    'key' => 'medianClassSize',
                    'label' => 'Median class',
                    'value' => $pos['medianClassSize'],
                    'format' => 'count',
                    'hint' => $pos['largestClassSize'] !== null
                        ? "The largest holds {$pos['largestClassSize']}"
                        : null,
                ],
                [
                    'key' => 'crowdedClasses',
                    'label' => 'Above '.StudentIntelligence::CROWDED_CLASS,
                    'value' => $pos['crowdedClasses'],
                    'format' => 'count',
                    'tone' => $pos['crowdedClasses'] > 0 ? 'attention' : 'positive',
                    'hint' => 'Classes over the crowding threshold',
                ],
                [
                    'key' => 'retentionRate',
                    'label' => 'Returned from last year',
                    // NULL where there is no previous year to divide by.
                    'value' => $pos['retentionRate'],
                    'format' => 'percent',
                    'tone' => $pos['retentionRate'] !== null && $pos['retentionRate'] < 90.0 ? 'attention' : null,
                    'hint' => $pos['retentionRate'] !== null
                        ? "{$pos['notReturning']} did not return, {$pos['newThisYear']} are new"
                        : 'No previous year on file',
                ],
                [
                    'key' => 'femaleShare',
                    'label' => 'Girls',
                    'value' => $pos['femaleShare'],
                    'format' => 'percent',
                    'hint' => $pos['genderUnrecorded'] > 0
                        ? "{$pos['genderUnrecorded']} students have no gender recorded"
                        : 'Of students whose gender is recorded',
                ],
                [
                    'key' => 'maleShare',
                    'label' => 'Boys',
                    'value' => $pos['maleShare'],
                    'format' => 'percent',
                ],
                [
                    'key' => 'newThisYear',
                    'label' => 'New this year',
                    'value' => $pos['newThisYear'],
                    'format' => 'count',
                    'hint' => 'Not on last year’s roll',
                ],
            ],
        ];
    }

    private function breakdowns(StudentIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byClass = $analytics->byClass();
        $byStandard = $analytics->byStandard();
        $byCohort = $analytics->byAdmissionCohort();
        $byQuota = $analytics->byQuota();

        return [
            [
                'key' => 'classes',
                'label' => 'By class',
                'description' => 'A class is a standard and a section together — the room a child actually sits in. '
                    .'Largest first.',
                'available' => $byClass !== [],
                'reason' => $byClass === [] ? 'No student this year is placed in a standard and section.' : null,
                'primaryColumn' => 'students',
                'columns' => [
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'female', 'label' => 'Girls', 'format' => 'count'],
                    ['key' => 'male', 'label' => 'Boys', 'format' => 'count'],
                    ['key' => 'femaleShare', 'label' => 'Girls', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'tone' => $row['students'] > StudentIntelligence::CROWDED_CLASS ? 'attention' : null,
                    'note' => $row['students'] < StudentIntelligence::MIN_CLASS_COHORT
                        ? 'Too few students to compare against the institute'
                        : null,
                    'values' => [
                        'students' => $row['students'],
                        'female' => $row['female'],
                        'male' => $row['male'],
                        'femaleShare' => $row['femaleShare'],
                    ],
                ], $byClass),
            ],
            [
                'key' => 'standards',
                'label' => 'By standard',
                'description' => 'The same students grouped by standard, with how evenly their sections are filled.',
                'available' => $byStandard !== [],
                'reason' => null,
                'primaryColumn' => 'students',
                'columns' => [
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'sections', 'label' => 'Sections', 'format' => 'count'],
                    ['key' => 'avgSectionSize', 'label' => 'Avg section', 'format' => 'decimal'],
                    ['key' => 'femaleShare', 'label' => 'Girls', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'students' => $row['students'],
                        'sections' => $row['sections'],
                        'avgSectionSize' => $row['avgSectionSize'],
                        'femaleShare' => $row['femaleShare'],
                    ],
                ], $byStandard),
            ],
            [
                'key' => 'cohorts',
                'label' => 'By admission year',
                'description' => 'The roll seen as intake history: a bulge is a cohort moving through the school, a '
                    .'thin year is one the school did not fill.',
                'available' => $byCohort !== [],
                'reason' => $byCohort === []
                    ? 'No student on this year’s roll carries an admission year in the student master.'
                    : null,
                'primaryColumn' => 'students',
                'columns' => [
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share of roll', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['students' => $row['students'], 'share' => $row['share']],
                ], $byCohort),
            ],
            [
                'key' => 'quotas',
                'label' => 'By admission quota',
                'description' => 'Quota names are read from the quota master; a quota missing from it is shown by its '
                    .'key rather than given a name.',
                'available' => count($byQuota) > 1,
                'reason' => count($byQuota) <= 1
                    ? 'This institute records a single admission quota, so there is nothing to compare across.'
                    : null,
                'primaryColumn' => 'students',
                'columns' => [
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share of roll', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['students' => $row['students'], 'share' => $row['share']],
                ], $byQuota),
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

