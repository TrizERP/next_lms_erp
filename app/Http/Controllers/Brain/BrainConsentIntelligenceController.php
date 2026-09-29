<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\ConsentIntelligence;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consent Intelligence — student consent records for one institute-year.
 *
 * `consent_master` carries both `sub_institute_id` and `syear`, so this module
 * is genuinely year-scoped and the header's selection filters every figure.
 *
 * THE STATUS BREAKDOWN IS GROUPED, NOT INTERPRETED. The meaning of the `status`
 * integer is set by the institute's own screens, so the payload reports the
 * distribution and names the codes rather than asserting which one means
 * approved. See the class doc on ConsentIntelligence.
 */
class BrainConsentIntelligenceController extends Controller
{
    private string $tenantId = '';

    private ?string $syear = null;

    private string $actorId = '';

    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        $analytics = new ConsentIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();
        $position = $analytics->position();
        $raised = $analytics->findings();
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'consent', 'consent records');

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · consent_master',
            'academicYear' => ['syear' => $this->syear],
            'coverage' => $coverage,
            'freshness' => [
                'positionLabel' => 'Read live at page load',
                'findingsRefreshedAt' => $coverage['available'] ? now()->toIso8601String() : null,
            ],
            'execution' => [
                'automated' => false,
                'note' => 'Consent rows carry an amount and an imprest head, so the totals here are money consented '
                    .'to rather than money collected. Status codes are grouped, not interpreted.',
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
            $p['records'].' consent record'.($p['records'] === 1 ? '' : 's').' covering '.$p['students']
                .' student'.($p['students'] === 1 ? '' : 's').' across '.$p['classes'].' class'
                .($p['classes'] === 1 ? '' : 'es').'.',
            $p['titles'].' distinct request'.($p['titles'] === 1 ? '' : 's').' were raised.',
        ];

        $sentences[] = $p['totalAmount'] > 0
            ? 'They carry '.number_format($p['totalAmount'], 2).' in total, of which '.$p['accountable']
                .' of '.$p['records'].' records are marked accountable.'
            : 'No amount is attached to any of these records — they are permission-only consent.';

        $sentences[] = count($findings) === 0
            ? 'No check found enough evidence to raise a finding.'
            : count($findings).' finding'.(count($findings) === 1 ? '' : 's').' were raised, each with the rows behind it.';

        return [
            'available' => true,
            'reason' => null,
            'headline' => $p['records'].' consent records · '.$p['students'].' students',
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
                ['key' => 'records', 'label' => 'Consent records', 'value' => $p['records'], 'format' => 'count'],
                ['key' => 'students', 'label' => 'Students covered', 'value' => $p['students'], 'format' => 'count'],
                ['key' => 'classes', 'label' => 'Classes', 'value' => $p['classes'], 'format' => 'count'],
                ['key' => 'titles', 'label' => 'Distinct requests', 'value' => $p['titles'], 'format' => 'count'],
                ['key' => 'totalAmount', 'label' => 'Amount consented', 'value' => $p['totalAmount'], 'format' => 'money', 'currency' => 'INR'],
                ['key' => 'meanAmount', 'label' => 'Average per record', 'value' => $p['meanAmount'], 'format' => 'money', 'currency' => 'INR'],
                [
                    'key' => 'accountable', 'label' => 'Marked accountable', 'value' => $p['accountable'], 'format' => 'count',
                    'tone' => $p['totalAmount'] > 0 && $p['accountable'] < $p['records'] ? 'warning' : 'positive',
                    'hint' => 'of '.$p['records'].' records',
                ],
            ],
        ];
    }

    private function breakdowns(ConsentIntelligence $a, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byTitle = $a->byTitle();
        $byClass = $a->byClass();
        $byStatus = $a->byStatus();

        return [
            [
                'key' => 'requests',
                'label' => 'By request',
                'description' => 'What parents are being asked to consent to, and how many students each reaches.',
                'available' => $byTitle !== [],
                'reason' => $byTitle === [] ? 'No consent record carries a title.' : null,
                'primaryColumn' => 'records',
                'columns' => [
                    ['key' => 'records', 'label' => 'Records', 'format' => 'count'],
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'currency' => 'INR'],
                ],
                'rows' => array_map(fn ($t) => [
                    'key' => $t['key'], 'label' => $t['label'],
                    'values' => ['records' => $t['records'], 'students' => $t['students'], 'amount' => $t['amount']],
                ], $byTitle),
            ],
            [
                'key' => 'classes',
                'label' => 'By class',
                'description' => 'Which classes consent has actually been raised in.',
                'available' => $byClass !== [],
                'reason' => $byClass === [] ? 'No consent record names a class.' : null,
                'primaryColumn' => 'records',
                'columns' => [
                    ['key' => 'records', 'label' => 'Records', 'format' => 'count'],
                    ['key' => 'students', 'label' => 'Students', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($c) => [
                    'key' => $c['key'], 'label' => $c['label'],
                    'values' => ['records' => $c['records'], 'students' => $c['students']],
                ], $byClass),
            ],
            [
                'key' => 'status',
                'label' => 'By status code',
                'description' => 'The distribution of the status column. The codes are the institute’s own and are '
                    .'reported rather than translated.',
                'available' => $byStatus !== [],
                'reason' => $byStatus === [] ? 'No consent record carries a status.' : null,
                'primaryColumn' => 'records',
                'columns' => [
                    ['key' => 'records', 'label' => 'Records', 'format' => 'count'],
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'money', 'currency' => 'INR'],
                ],
                'rows' => array_map(fn ($s) => [
                    'key' => $s['key'], 'label' => $s['label'],
                    'values' => ['records' => $s['records'], 'amount' => $s['amount']],
                ], $byStatus),
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
