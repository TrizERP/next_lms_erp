<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\PettyCashIntelligence;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Petty Cash Intelligence — one institute, all time.
 *
 * TENANT COMES FROM THE SIGNED TOKEN, never the URL: the route group applies
 * `brain.auth` and `brain.tenant`, so a request for another institute's petty
 * cash is rejected before this class runs.
 *
 * THE YEAR IS RESOLVED BUT NOT APPLIED, and the screen says so. `petty_cash`
 * has no `syear` column — spending is not an academic-year fact in this schema —
 * so every figure is an all-time institute total. Silently ignoring the header's
 * year would let a reader believe they had filtered by it.
 */
class BrainPettyCashIntelligenceController extends Controller
{
    private string $tenantId = '';

    private ?string $syear = null;

    private string $actorId = '';

    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        $analytics = new PettyCashIntelligence($this->tenantId);
        $coverage = $analytics->coverage();
        $position = $analytics->position();
        $raised = $analytics->findings();
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'petty-cash', 'claims');

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · petty_cash, petty_cash_master',
            'academicYear' => ['syear' => $this->syear],
            'coverage' => $coverage,
            'freshness' => [
                'positionLabel' => 'Read live at page load',
                'findingsRefreshedAt' => $coverage['available'] ? now()->toIso8601String() : null,
            ],
            'execution' => [
                'automated' => false,
                'note' => 'Petty cash carries no academic year in this schema, so every figure is an all-time '
                    .'institute total rather than a figure for the year selected in the header.',
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
            $p['claims'].' petty-cash claim'.($p['claims'] === 1 ? '' : 's').' totalling '
                .number_format((float) $p['totalAmount'], 2).', filed by '.$p['spenders']
                .' '.($p['spenders'] === 1 ? 'person' : 'people').'.',
        ];

        if ($p['meanAmount'] !== null) {
            $sentences[] = 'The average claim is '.number_format((float) $p['meanAmount'], 2)
                .($p['largestAmount'] !== null
                    ? ', and the largest is '.number_format((float) $p['largestAmount'], 2).'.'
                    : '.');
        }

        $sentences[] = $p['headsUsed'].' of '.$p['headsConfigured'].' configured spending heads are in use.';
        $sentences[] = $p['withBill'].' of '.$p['claims'].' claims have a bill attached.';
        $sentences[] = count($findings) === 0
            ? 'No check found enough evidence to raise a finding.'
            : count($findings).' finding'.(count($findings) === 1 ? '' : 's').' were raised, each with the rows behind it.';

        return [
            'available' => true,
            'reason' => null,
            'headline' => $p['claims'].' claims · '.number_format((float) $p['totalAmount'], 2).' all time',
            'sentences' => $sentences,
        ];
    }

    private function position(?array $p, array $coverage): ?array
    {
        if ($p === null) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                ['key' => 'totalAmount', 'label' => 'Total claimed', 'value' => $p['totalAmount'], 'format' => 'money', 'currency' => 'INR'],
                ['key' => 'claims', 'label' => 'Claims', 'value' => $p['claims'], 'format' => 'count'],
                ['key' => 'meanAmount', 'label' => 'Average claim', 'value' => $p['meanAmount'], 'format' => 'money', 'currency' => 'INR'],
                ['key' => 'largestAmount', 'label' => 'Largest claim', 'value' => $p['largestAmount'], 'format' => 'money', 'currency' => 'INR'],
                ['key' => 'spenders', 'label' => 'Claimants', 'value' => $p['spenders'], 'format' => 'count'],
                [
                    'key' => 'withBill', 'label' => 'With a bill attached', 'value' => $p['withBill'], 'format' => 'count',
                    'tone' => $p['withBill'] < $p['claims'] ? 'warning' : 'positive',
                    'hint' => 'of '.$p['claims'].' claims',
                ],
                ['key' => 'headsUsed', 'label' => 'Spending heads used', 'value' => $p['headsUsed'], 'format' => 'count',
                    'hint' => 'of '.$p['headsConfigured'].' configured'],
            ],
        ];
    }

    private function breakdowns(PettyCashIntelligence $a, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $heads = $a->byHead();
        $spenders = $a->bySpender();

        return [
            [
                'key' => 'heads',
                'label' => 'By spending head',
                'description' => 'Where the float goes, grouped by the institute’s own petty-cash headings.',
                'available' => $heads !== [],
                'reason' => $heads === [] ? 'No claim is filed under a spending head.' : null,
                'primaryColumn' => 'amount',
                'columns' => [
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'currency' => 'INR'],
                    ['key' => 'claims', 'label' => 'Claims', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($h) => [
                    'key' => $h['key'], 'label' => $h['label'],
                    'values' => ['amount' => $h['amount'], 'claims' => $h['claims']],
                ], $heads),
            ],
            [
                'key' => 'spenders',
                'label' => 'By claimant',
                'description' => 'Who is spending the float. Names come from the staff directory.',
                'available' => $spenders !== [],
                'reason' => $spenders === [] ? 'No claim records who filed it.' : null,
                'primaryColumn' => 'amount',
                'columns' => [
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'currency' => 'INR'],
                    ['key' => 'claims', 'label' => 'Claims', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($s) => [
                    'key' => $s['key'], 'label' => $s['label'],
                    'values' => ['amount' => $s['amount'], 'claims' => $s['claims']],
                ], $spenders),
            ],
        ];
    }

    private function priorities(array $findings): array
    {
        return array_values(array_map(fn ($f) => [
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
        ], array_filter($findings, fn ($f) => in_array($f['severity'], ['critical', 'high', 'medium'], true))));
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
