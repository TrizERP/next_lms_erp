<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\CommunicationIntelligence;
use App\Brain\Intelligence\CommunicationSignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainCommunicationIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'communication', 'inquiries');

        $analytics = new CommunicationIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new CommunicationSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $coverage['available'] ? $analytics->position()['metrics'] : null;

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · parent_communication + sms_sent_parents',
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
                'note' => 'Communication findings are evaluated per request from live parent messages and SMS logs.',
            ],
            'summary' => $this->summary($pos, $coverage, $raised['findings']),
            'position' => $this->position($pos, $coverage),
            'breakdowns' => $coverage['available'] ? $analytics->breakdowns() : [],
            'findings' => $raised['findings'],
            'priorities' => $coverage['available'] ? $analytics->priorities() : [],
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
                'reason' => $coverage['reason'] ?? 'No communication records in this academic year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];
        $sentences[] = "{$pos['totalCommunications']} multi-channel communications were logged ({$pos['totalInquiries']} parent inquiries, {$pos['totalSmsSent']} SMS notifications, and {$pos['totalWhatsAppSent']} WhatsApp messages).";
        $sentences[] = "Parent inquiry response rate is {$pos['responseRate']}%, with {$pos['repliedInquiries']} answered and {$pos['unrepliedInquiries']} pending reply.";

        $findingCount = count($findings);
        $sentences[] = $findingCount === 0
            ? 'Parent engagement response time and notification coverage meet institutional benchmarks.'
            : "{$findingCount} communication finding" . ($findingCount === 1 ? '' : 's') . ' raised with empirical evidence.';

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$pos['totalCommunications']} communications · {$pos['totalInquiries']} parent inquiries ({$pos['responseRate']}% replied) · {$pos['totalSmsSent']} SMS sent",
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
                    'key' => 'totalCommunications',
                    'label' => 'Total communications',
                    'value' => $pos['totalCommunications'],
                    'format' => 'count',
                ],
                [
                    'key' => 'totalInquiries',
                    'label' => 'Parent inquiries',
                    'value' => $pos['totalInquiries'],
                    'format' => 'count',
                ],
                [
                    'key' => 'repliedInquiries',
                    'label' => 'Answered inquiries',
                    'value' => $pos['repliedInquiries'],
                    'format' => 'count',
                ],
                [
                    'key' => 'unrepliedInquiries',
                    'label' => 'Pending reply',
                    'value' => $pos['unrepliedInquiries'],
                    'format' => 'count',
                    'tone' => $pos['unrepliedInquiries'] > 0 ? 'warning' : 'neutral',
                ],
                [
                    'key' => 'responseRate',
                    'label' => 'Inquiry response rate',
                    'value' => $pos['responseRate'],
                    'format' => 'percent',
                    'tone' => $pos['responseRate'] >= 80 ? 'positive' : 'warning',
                ],
                [
                    'key' => 'totalSmsSent',
                    'label' => 'Outbound SMS sent',
                    'value' => $pos['totalSmsSent'],
                    'format' => 'count',
                ],
                [
                    'key' => 'totalWhatsAppSent',
                    'label' => 'WhatsApp messages',
                    'value' => $pos['totalWhatsAppSent'],
                    'format' => 'count',
                ],
            ],
        ];
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
