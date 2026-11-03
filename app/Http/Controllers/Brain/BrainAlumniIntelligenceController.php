<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\AlumniIntelligence;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alumni Intelligence — one institute, all time.
 *
 * TENANT COMES FROM THE SIGNED TOKEN, never the URL: the route group applies
 * `brain.auth` and `brain.tenant`, so a request for another institute's alumni
 * is rejected before this class runs.
 *
 * THE YEAR IS RESOLVED BUT NOT APPLIED, and the screen says so. An alumnus has
 * left, so the register carries the batch (`passing_year`) rather than an
 * academic year to filter on; every figure is an institute total.
 */
class BrainAlumniIntelligenceController extends Controller
{
    private string $tenantId = '';

    private ?string $syear = null;

    private string $actorId = '';

    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        $analytics = new AlumniIntelligence($this->tenantId);
        $coverage = $analytics->coverage();
        $position = $analytics->position();
        $raised = $analytics->findings();
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'alumni', 'alumni');

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · alumni, alumni_career, alumni_mentor_profiles, alumni_mentorship_requests, alumni_events, alumni_donations',
            'academicYear' => ['syear' => $this->syear],
            'coverage' => $coverage,
            'freshness' => [
                'positionLabel' => 'Read live at page load',
                'findingsRefreshedAt' => $coverage['available'] ? now()->toIso8601String() : null,
            ],
            'execution' => [
                'automated' => false,
                'note' => 'Alumni are recorded by the batch they left with, not by academic year, so every figure '
                    .'is an all-time institute total rather than a figure for the year selected in the header.',
            ],
            'summary' => $this->summary($position, $coverage, $raised['findings']),
            'position' => $this->position($position, $coverage),
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

    private function summary(?array $p, array $coverage, array $findings): array
    {
        if ($p === null) {
            return ['available' => false, 'reason' => $coverage['reason'], 'headline' => null, 'sentences' => []];
        }

        $sentences = [
            $p['alumni'].' alumni are on record; '.$p['verified'].' ('.$p['verifiedRate'].'%) are verified and '
                .$p['awaiting'].' await verification.',
            $p['reachable'].' ('.$p['reachableRate'].'%) can be contacted — they have an address and consent on at least one channel.',
            $p['employmentRate'] !== null
                ? $p['employmentRate'].'% of the '.$p['employmentKnown'].' alumni with a recorded employment status are employed or running a business.'
                : 'No alumnus has an employment status recorded, so no employment rate can be computed.',
            $p['activeLast12'].' alumni ('.$p['activeRate'].'%) have recorded activity in the last 12 months.',
            $p['mentors'].' active mentor'.($p['mentors'] === 1 ? '' : 's').' and '.$p['openMentorRequests']
                .' open mentorship request'.($p['openMentorRequests'] === 1 ? '' : 's').'; '.$p['upcomingEvents']
                .' published event'.($p['upcomingEvents'] === 1 ? '' : 's').' coming up.',
            count($findings) === 0
                ? 'No check found enough evidence to raise a finding.'
                : count($findings).' finding'.(count($findings) === 1 ? '' : 's').' were raised, each with the rows behind it.',
        ];

        return [
            'available' => true,
            'reason' => null,
            'headline' => $p['alumni'].' alumni · '.$p['verifiedRate'].'% verified · '.$p['reachableRate'].'% reachable',
            'sentences' => $sentences,
        ];
    }

    private function position(?array $p, array $coverage): array
    {
        if ($p === null) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                ['key' => 'alumni', 'label' => 'Alumni', 'value' => $p['alumni'], 'format' => 'count'],
                [
                    'key' => 'verifiedRate', 'label' => 'Verified', 'value' => $p['verifiedRate'], 'format' => 'percent',
                    'tone' => $p['verifiedRate'] < 75 ? 'warning' : 'positive',
                    'hint' => $p['verified'].' of '.$p['alumni'].' records',
                ],
                [
                    'key' => 'reachableRate', 'label' => 'Reachable', 'value' => $p['reachableRate'], 'format' => 'percent',
                    'tone' => $p['reachableRate'] < 80 ? 'warning' : 'positive',
                    'hint' => 'address and consent on a channel',
                ],
                [
                    'key' => 'employmentRate', 'label' => 'Employed', 'value' => $p['employmentRate'], 'format' => 'percent',
                    'hint' => 'of '.$p['employmentKnown'].' with a status',
                ],
                [
                    'key' => 'activeRate', 'label' => 'Active (12 months)', 'value' => $p['activeRate'], 'format' => 'percent',
                    'tone' => $p['activeRate'] < 50 ? 'warning' : 'positive',
                    'hint' => $p['activeLast12'].' alumni',
                ],
                [
                    'key' => 'profileCompletion', 'label' => 'Profile completion', 'value' => $p['profileCompletion'], 'format' => 'percent',
                    'hint' => 'average across profiles',
                ],
                ['key' => 'mentors', 'label' => 'Active mentors', 'value' => $p['mentors'], 'format' => 'count'],
                ['key' => 'openMentorRequests', 'label' => 'Open mentor requests', 'value' => $p['openMentorRequests'], 'format' => 'count'],
                ['key' => 'upcomingEvents', 'label' => 'Upcoming events', 'value' => $p['upcomingEvents'], 'format' => 'count'],
                ['key' => 'donationsRaised', 'label' => 'Donations confirmed', 'value' => $p['donationsRaised'], 'format' => 'money', 'currency' => 'INR'],
            ],
        ];
    }

    private function breakdowns(AlumniIntelligence $a, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byBatch = $a->byBatch();
        $byEmployment = $a->byEmployment();

        return [
            [
                'key' => 'batches',
                'label' => 'By batch',
                'description' => 'Alumni per passing year, with how many are verified and employed.',
                'available' => $byBatch !== [],
                'reason' => $byBatch === [] ? 'No alumnus has a passing year recorded.' : null,
                'primaryColumn' => 'alumni',
                'columns' => [
                    ['key' => 'alumni', 'label' => 'Alumni', 'format' => 'count'],
                    ['key' => 'verified', 'label' => 'Verified', 'format' => 'count'],
                    ['key' => 'employed', 'label' => 'Employed', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($b) => [
                    'key' => $b['key'], 'label' => $b['label'],
                    'values' => ['alumni' => $b['alumni'], 'verified' => $b['verified'], 'employed' => $b['employed']],
                ], $byBatch),
            ],
            [
                'key' => 'employment',
                'label' => 'By employment status',
                'description' => 'What alumni are doing now, over those with a status recorded.',
                'available' => $byEmployment !== [],
                'reason' => $byEmployment === [] ? 'No alumnus has an employment status recorded.' : null,
                'primaryColumn' => 'alumni',
                'columns' => [['key' => 'alumni', 'label' => 'Alumni', 'format' => 'count']],
                'rows' => array_map(fn ($e) => [
                    'key' => $e['key'], 'label' => $e['label'], 'values' => ['alumni' => $e['alumni']],
                ], $byEmployment),
            ],
        ];
    }

    private function priorities(array $findings): array
    {
        return array_values(array_map(fn ($f) => [
            'id' => $f['id'], 'severity' => $f['severity'], 'severityLabel' => $f['severityLabel'],
            'title' => $f['title'], 'whatHappened' => $f['whatHappened'], 'whyItMatters' => $f['whyItMatters'],
            'evidence' => $f['evidence'], 'impact' => $f['impact'], 'nextStep' => $f['recommendation'],
            'owner' => $f['owner'], 'confidence' => $f['confidence'],
        ], array_filter($findings, fn ($f) => in_array($f['severity'], ['critical', 'high', 'medium'], true))));
    }

    /**
     * Writes this module's findings into the signal ledger so Recommendations,
     * Decisions and the learning loop have something to read. Runs the same
     * pipeline every module's "Analyse" runs; it is idempotent, so a second run
     * refreshes the signals rather than raising them again.
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
            // there. Reported rather than hidden.
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
