<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\TalentIntelligence;
use App\Brain\Intelligence\TalentSignalRules;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainTalentIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'talent', 'records');

        $analytics = new TalentIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new TalentSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · talent_job_postings, talent_job_applications, talent_offers, '
                .'talent_onboarding_journeys, talent_offboarding_cases, s_performance_reviews, s_mobility_*, '
                .'hrms_departments',
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
                'note' => 'HR activity is not scoped to an academic year in this schema, so every figure here is an '
                    .'all-time snapshot for the tenant rather than a per-year one.',
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
     * DETERMINISTIC, NEVER MODEL OUTPUT.
     */
    private function summary(?array $pos, array $coverage, array $findings): array
    {
        if ($pos === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'No talent management activity exists for this tenant.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        $sentences[] = "{$pos['postings']} job postings on file, {$pos['openPostings']} of them open, have drawn "
            ."{$pos['applications']} applications.";

        $sentences[] = $pos['applications'] > 0
            ? "{$pos['screeningApplications']} are still in screening, {$pos['interviewApplications']} are "
                ."interviewing and {$pos['hiredApplications']} have been hired."
            : 'No application has been recorded against any posting yet.';

        $sentences[] = "{$pos['activeOnboardingJourneys']} onboarding journeys and "
            ."{$pos['activeOffboardingCases']} offboarding cases are currently active, alongside "
            ."{$pos['activeMobilityRequests']} open internal mobility request(s).";

        if ($pos['totalPerformanceReviews'] > 0) {
            $sentences[] = "{$pos['pendingPerformanceReviews']} of {$pos['totalPerformanceReviews']} performance "
                ."reviews on file ({$pos['pendingReviewShare']}%) are still pending.";
        }

        $count = count($findings);
        $sentences[] = match ($count) {
            0 => 'No check found enough evidence to raise a finding.',
            1 => 'One finding below, with the figures it rests on.',
            default => "{$count} findings below, each with the figures it rests on.",
        };

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$pos['applications']} applications across {$pos['openPostings']} open postings",
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
                    'key' => 'openPostings',
                    'label' => 'Open postings',
                    'value' => $pos['openPostings'],
                    'format' => 'count',
                    'hint' => "of {$pos['postings']} postings on file",
                ],
                [
                    'key' => 'applications',
                    'label' => 'Applications',
                    'value' => $pos['applications'],
                    'format' => 'count',
                ],
                [
                    'key' => 'screeningApplications',
                    'label' => 'In screening',
                    'value' => $pos['screeningApplications'],
                    'format' => 'count',
                    'tone' => $pos['screeningApplications'] > 0 && $pos['interviewApplications'] === 0
                        && $pos['hiredApplications'] === 0 ? 'warning' : 'neutral',
                ],
                [
                    'key' => 'interviewApplications',
                    'label' => 'Interviewing',
                    'value' => $pos['interviewApplications'],
                    'format' => 'count',
                ],
                [
                    'key' => 'hiredApplications',
                    'label' => 'Hired',
                    'value' => $pos['hiredApplications'],
                    'format' => 'count',
                    'tone' => 'positive',
                ],
                [
                    'key' => 'screeningToInterviewRate',
                    'label' => 'Screening → interview or hire',
                    'value' => $pos['screeningToInterviewRate'],
                    'format' => 'percent',
                    'hint' => 'Share of screened/interviewed/hired applications that moved past screening',
                ],
                [
                    'key' => 'offersSent',
                    'label' => 'Offers sent',
                    'value' => $pos['offersSent'],
                    'format' => 'count',
                ],
                [
                    'key' => 'activeOnboardingJourneys',
                    'label' => 'Active onboarding',
                    'value' => $pos['activeOnboardingJourneys'],
                    'format' => 'count',
                ],
                [
                    'key' => 'activeMobilityRequests',
                    'label' => 'Active mobility requests',
                    'value' => $pos['activeMobilityRequests'],
                    'format' => 'count',
                    'hint' => 'Internal applications, transfers and promotions not yet resolved',
                ],
                [
                    'key' => 'activeOffboardingCases',
                    'label' => 'Open offboarding cases',
                    'value' => $pos['activeOffboardingCases'],
                    'format' => 'count',
                    'tone' => $pos['activeOffboardingCases'] > 0 ? 'warning' : 'positive',
                ],
                [
                    'key' => 'pendingPerformanceReviews',
                    'label' => 'Reviews pending',
                    'value' => $pos['pendingPerformanceReviews'],
                    'format' => 'count',
                    'tone' => $pos['pendingReviewShare'] !== null && $pos['pendingReviewShare'] >= 60.0
                        ? 'warning' : 'neutral',
                    'hint' => $pos['pendingReviewShare'] !== null
                        ? "{$pos['pendingReviewShare']}% of {$pos['totalPerformanceReviews']} reviews on file"
                        : null,
                ],
            ],
        ];
    }

    private function breakdowns(TalentIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byStatus = $analytics->byApplicationStatus();
        $byDepartment = $analytics->byDepartment();

        return [
            [
                'key' => 'applicationStatus',
                'label' => 'Applications by status',
                'description' => 'Every application this tenant has, grouped by its current funnel stage.',
                'available' => $byStatus !== [],
                'reason' => $byStatus === [] ? 'No application has been recorded for this tenant.' : null,
                'primaryColumn' => 'applications',
                'columns' => [
                    ['key' => 'applications', 'label' => 'Applications', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['applications' => $row['applications'], 'share' => $row['share']],
                ], $byStatus),
            ],
            [
                'key' => 'departments',
                'label' => 'By department',
                'description' => 'Open postings, active onboarding, open offboarding and pending reviews, by the '
                    .'department each one is assigned to. A department absent here has none of the four.',
                'available' => $byDepartment !== [],
                'reason' => $byDepartment === []
                    ? 'No open posting, active onboarding journey, open offboarding case or pending review carries '
                        .'a department for this tenant.'
                    : null,
                'primaryColumn' => 'totalActivity',
                'columns' => [
                    ['key' => 'totalActivity', 'label' => 'Total activity', 'format' => 'count'],
                    ['key' => 'openPostings', 'label' => 'Open postings', 'format' => 'count'],
                    ['key' => 'activeOnboarding', 'label' => 'Active onboarding', 'format' => 'count'],
                    ['key' => 'activeOffboarding', 'label' => 'Open offboarding', 'format' => 'count'],
                    ['key' => 'pendingReviews', 'label' => 'Reviews pending', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'totalActivity' => $row['totalActivity'],
                        'openPostings' => $row['openPostings'],
                        'activeOnboarding' => $row['activeOnboarding'],
                        'activeOffboarding' => $row['activeOffboarding'],
                        'pendingReviews' => $row['pendingReviews'],
                    ],
                ], $byDepartment),
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
     * duplicating them.
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
