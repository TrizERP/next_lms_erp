<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\CareerIntelligence;
use App\Brain\Intelligence\CareerSignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Career Intelligence — the Brain's module screen over the career evidence
 * ledger. Read-only against the Career tables; `run()` writes only to the
 * Brain's own signal ledger, exactly as every other module does.
 *
 * Deliberately separate from the counselling controllers and from
 * `App\CareerIntelligence\*`: those serve one student at a time, this one
 * reports on the cohort.
 */
class BrainCareerIntelligenceController extends Controller
{
    private string $tenantId = '';
    private ?string $syear = null;
    private string $actorId = '';
    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        $loop = new ModuleLoop($this->tenantId, $this->syear, 'career', 'students');

        $analytics = new CareerIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $raised = $coverage['available']
            ? (new CareerSignalRules($analytics, $this->syear))->run()
            : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · evidence_events · student_aspirations',
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
                'note' => 'Career evidence readiness is counted live from the evidence ledger. Nothing here collects evidence or contacts a student.',
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
                'reason' => $coverage['reason'] ?? 'No Grade 9-10 cohort to measure career evidence against.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [
            "{$pos['evidenced']} of {$pos['cohort']} Grade 9-10 students on the roll have at least one row in the career evidence ledger ({$pos['coverageRate']}%).",
            "The ledger holds {$pos['events']} rows for this cohort, {$pos['activeEvents']} of them standing; {$pos['supersededEvents']} have been superseded and {$pos['contestedEvents']} contested.",
        ];

        if ($pos['aspirants'] > 0) {
            $sentences[] = "{$pos['aspirants']} students have a current career aspiration on file; {$pos['aspirantsWithoutEvidence']} of them have no evidence.";
        }

        $n = count($findings);
        $sentences[] = $n === 0
            ? 'No career evidence finding crosses its threshold for this year.'
            : "{$n} career finding" . ($n === 1 ? '' : 's') . ' raised with the counts they rest on.';

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$pos['evidenced']} of {$pos['cohort']} students evidenced · {$pos['coverageRate']}% coverage · {$pos['events']} ledger rows",
            'sentences' => $sentences,
        ];
    }

    private function position(?array $pos, array $coverage): array
    {
        if ($pos === null) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                ['key' => 'cohort', 'label' => 'Grade 9-10 students', 'value' => $pos['cohort'], 'format' => 'count'],
                ['key' => 'evidenced', 'label' => 'With career evidence', 'value' => $pos['evidenced'], 'format' => 'count'],
                [
                    'key' => 'coverageRate',
                    'label' => 'Evidence coverage',
                    'value' => $pos['coverageRate'],
                    'format' => 'percent',
                    'tone' => $pos['coverageRate'] >= 60 ? 'positive' : 'warning',
                ],
                ['key' => 'events', 'label' => 'Ledger rows', 'value' => $pos['events'], 'format' => 'count'],
                ['key' => 'aspirants', 'label' => 'With an aspiration', 'value' => $pos['aspirants'], 'format' => 'count'],
                [
                    'key' => 'aspirantsWithoutEvidence',
                    'label' => 'Aspirations untested',
                    'value' => $pos['aspirantsWithoutEvidence'],
                    'format' => 'count',
                    'tone' => $pos['aspirantsWithoutEvidence'] > 0 ? 'warning' : 'positive',
                ],
            ],
        ];
    }

    private function breakdowns(CareerIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $bySource = $analytics->bySource();
        $byGrade = $analytics->byGrade();

        return [
            [
                'key' => 'sources',
                'label' => 'Evidence by source',
                'description' => 'Which adapter wrote the ledger rows for this cohort, and how many of them have since been superseded or contested.',
                'available' => ! empty($bySource),
                'reason' => empty($bySource) ? 'The ledger holds no rows for this cohort.' : null,
                'primaryColumn' => 'events',
                'columns' => [
                    ['key' => 'events', 'label' => 'Rows', 'format' => 'count'],
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'revised', 'label' => 'Superseded / contested', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($r) => [
                    'key' => $r['key'],
                    'label' => $r['label'],
                    'values' => [
                        'events' => $r['events'],
                        'students' => $r['students'],
                        'revised' => $r['revised'],
                        'share' => $r['share'],
                    ],
                ], $bySource),
            ],
            [
                'key' => 'grades',
                'label' => 'Coverage by grade',
                'description' => 'The Grade 9-10 cohort on the roll, and how many students in each grade have any career evidence.',
                'available' => ! empty($byGrade),
                'reason' => null,
                'primaryColumn' => 'students',
                'columns' => [
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'evidenced', 'label' => 'With evidence', 'format' => 'count'],
                    ['key' => 'withoutEvidence', 'label' => 'Without evidence', 'format' => 'count'],
                    ['key' => 'coverage', 'label' => 'Coverage', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($r) => [
                    'key' => $r['key'],
                    'label' => $r['label'],
                    'values' => [
                        'students' => $r['students'],
                        'evidenced' => $r['evidenced'],
                        'withoutEvidence' => $r['withoutEvidence'],
                        'coverage' => $r['coverage'],
                    ],
                ], $byGrade),
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
     * Write this module's findings to the Brain's signal ledger. Idempotent:
     * `SignalWriter` dedupes on (tenant, rule, year). Runs the whole pipeline
     * because reasoning over a signal that has not been raised produces nothing.
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
