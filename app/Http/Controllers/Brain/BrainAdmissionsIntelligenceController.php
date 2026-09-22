<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\AdmissionsIntelligence;
use App\Brain\Intelligence\AdmissionsSignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainAdmissionsIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'admissions', 'candidates');

        $analytics = new AdmissionsIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new AdmissionsSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · admission_registration_v1, new_admission_inquiry_registration',
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
                'note' => 'Admissions findings are evaluated per request from live candidate registration entries.',
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
     * DETERMINISTIC, NEVER MODEL OUTPUT. The confirmation codes are quoted as
     * the institute writes them; this module does not expand "C/A" into a phrase
     * the schema never defined.
     */
    private function summary(?array $pos, array $coverage, array $findings): array
    {
        if ($pos === null || $pos['registrations'] === 0) {
            return [
                'available' => false,
                'reason' => $coverage['reason']
                    ?? 'No admission registrations were recorded inside this academic year’s own dates.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        $sentences[] = "{$pos['registrations']} candidates registered inside this academic year’s own dates"
            .($pos['inquiries'] !== null ? ", against {$pos['inquiries']} inquiries." : '.');

        $sentences[] = "{$pos['confirmed']} were confirmed ({$pos['conversionRate']}%), "
            ."{$pos['notProceeding']} were recorded as not proceeding, and {$pos['undecided']} carry no outcome "
            .'either way.';

        $sentences[] = $pos['paymentRate'] !== null
            ? "{$pos['paid']} confirmed candidates have a fee recorded ({$pos['paymentRate']}% of those confirmed); "
                ."{$pos['confirmedUnpaid']} do not."
            : 'No confirmed candidate has a fee recorded, so the payment rate cannot be computed.';

        if ($pos['medianDaysToConfirm'] !== null) {
            $sentences[] = "Candidates who received an outcome waited a median of {$pos['medianDaysToConfirm']} days "
                .'between the interview and the confirmation.';
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
            'headline' => "{$pos['registrations']} registrations, {$pos['conversionRate']}% confirmed",
            'sentences' => $sentences,
        ];
    }

    private function position(?array $pos, array $coverage): ?array
    {
        if ($pos === null || $pos['registrations'] === 0) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'registrations',
                    'label' => 'Registrations',
                    'value' => $pos['registrations'],
                    'format' => 'count',
                    // Naming the window is what stops the next reader assuming
                    // this is the calendar year, which is what it used to be.
                    'hint' => 'Inside this academic year’s own term dates',
                ],
                [
                    'key' => 'conversionRate',
                    'label' => 'Confirmed',
                    'value' => $pos['conversionRate'],
                    'format' => 'percent',
                    'hint' => "{$pos['confirmed']} of {$pos['registrations']} registrations",
                ],
                [
                    'key' => 'undecided',
                    'label' => 'No outcome yet',
                    'value' => $pos['undecided'],
                    'format' => 'count',
                    'tone' => $pos['undecided'] > 0 ? 'warning' : 'positive',
                    'hint' => 'Neither confirmed nor declined',
                ],
                [
                    'key' => 'paymentRate',
                    'label' => 'Fee recorded',
                    // NULL where nothing is confirmed: a payment rate over no
                    // confirmations has no denominator.
                    'value' => $pos['paymentRate'],
                    'format' => 'percent',
                    'hint' => "{$pos['paid']} of {$pos['confirmed']} confirmed candidates",
                ],
                [
                    'key' => 'confirmedUnpaid',
                    'label' => 'Confirmed, no fee',
                    'value' => $pos['confirmedUnpaid'],
                    'format' => 'count',
                    'tone' => $pos['confirmedUnpaid'] > 0 ? 'attention' : 'positive',
                    'hint' => 'Seats held without a payment on file',
                ],
                [
                    'key' => 'notProceeding',
                    'label' => 'Not proceeding',
                    'value' => $pos['notProceeding'],
                    'format' => 'count',
                    'hint' => 'Codes '.implode(' or ', AdmissionsIntelligence::NOT_PROCEEDING_CODES),
                ],
                [
                    'key' => 'medianDaysToConfirm',
                    'label' => 'Days to confirm',
                    'value' => $pos['medianDaysToConfirm'],
                    'format' => 'count',
                    'hint' => 'Median, interview to confirmation',
                ],
                [
                    'key' => 'inquiries',
                    'label' => 'Inquiries',
                    'value' => $pos['inquiries'],
                    'format' => 'count',
                    'hint' => $pos['inquiries'] === null
                        ? 'No inquiry records for this year'
                        : 'Before registration',
                ],
            ],
        ];
    }

    private function breakdowns(AdmissionsIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $funnel = $analytics->funnel();
        $codes = $analytics->byConfirmationCode();
        $demand = $analytics->demandByStandard();

        return [
            [
                'key' => 'funnel',
                'label' => 'The funnel',
                'description' => 'Every stage is a subset of the one above it, and the last column is what was lost '
                    .'between them. An earlier version showed more candidates confirmed than interviewed, which is '
                    .'not a funnel.',
                'available' => $funnel !== [],
                'reason' => $funnel === [] ? 'No registration reached a stage this year.' : null,
                'primaryColumn' => 'candidates',
                'columns' => [
                    ['key' => 'candidates', 'label' => 'Candidates', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Of registrations', 'format' => 'percent'],
                    ['key' => 'lostFromPrevious', 'label' => 'Lost here', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'candidates' => $row['candidates'],
                        'share' => $row['share'],
                        // Null on the first stage — nothing was lost before it.
                        'lostFromPrevious' => $row['lostFromPrevious'],
                    ],
                ], $funnel),
            ],
            [
                'key' => 'codes',
                'label' => 'By confirmation code',
                'description' => 'The institute’s own codes, verbatim. The grouping beside each one is the grouping '
                    .'the LMS admission module itself applies when it decides which email a candidate receives — it '
                    .'is not an expansion of the code invented here.',
                'available' => $codes !== [],
                'reason' => $codes === [] ? 'No registration this year carries a confirmation code.' : null,
                'primaryColumn' => 'candidates',
                'columns' => [
                    ['key' => 'candidates', 'label' => 'Candidates', 'format' => 'count'],
                    ['key' => 'paid', 'label' => 'Fee recorded', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['meaning'],
                    'values' => [
                        'candidates' => $row['candidates'],
                        'paid' => $row['paid'],
                        'share' => $row['share'],
                    ],
                ], $codes),
            ],
            [
                'key' => 'demand',
                'label' => 'Demand by standard',
                'description' => 'The standard each inquiry names. This is the only field read from the inquiry '
                    .'table besides its row count — the rest of it is health, caste and identity data that no '
                    .'admissions question needs.',
                'available' => $demand !== [],
                'reason' => $demand === []
                    ? 'No admission inquiries are recorded for this academic year, so demand cannot be divided by '
                        .'standard. Registrations do not carry the standard applied for.'
                    : null,
                'primaryColumn' => 'inquiries',
                'columns' => [
                    ['key' => 'inquiries', 'label' => 'Inquiries', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['inquiries' => $row['inquiries'], 'share' => $row['share']],
                ], $demand),
            ],
        ];
    }

    private function priorities(array $findings): array
    {
        $severe = array_values(array_filter(
            $findings,
            fn ($f) => in_array($f['severity'], ['critical', 'high'], true),
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

