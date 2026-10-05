<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\DocumentTemplateIntelligence;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Document Templates Intelligence — the institute's own template library.
 *
 * ITS OWN CONTEXT, DELIBERATELY. This module previously resolved to the Result
 * contract through a shared route family, so opening Document Templates showed
 * marks. Templates are not marks; this endpoint reads the template tables and
 * nothing else.
 *
 * NO ACADEMIC YEAR. `template_master` carries no `syear` — a template is a
 * standing asset, not a per-year record — so figures are all-time for the
 * institute and the payload says so rather than implying the header filtered it.
 */
class BrainDocumentTemplateIntelligenceController extends Controller
{
    private string $tenantId = '';

    private ?string $syear = null;

    private string $actorId = '';

    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        $analytics = new DocumentTemplateIntelligence($this->tenantId);
        $coverage = $analytics->coverage();
        $position = $analytics->position();
        $raised = $analytics->findings();
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'document-templates', 'templates');

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · template_master, result_template_master',
            'academicYear' => ['syear' => $this->syear],
            'coverage' => $coverage,
            'freshness' => [
                'positionLabel' => 'Read live at page load',
                'findingsRefreshedAt' => $coverage['available'] ? now()->toIso8601String() : null,
            ],
            'execution' => [
                'automated' => false,
                'note' => 'Templates are standing assets with no academic year, so these are all-time institute '
                    .'figures. ai_templates is excluded: its rows carry no institute and are product defaults.',
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
            $p['templates'].' document template'.($p['templates'] === 1 ? '' : 's').' across '
                .$p['modulesCovered'].' module'.($p['modulesCovered'] === 1 ? '' : 's')
                .', authored by '.$p['authors'].' '.($p['authors'] === 1 ? 'person' : 'people').'.',
        ];

        if ($p['activeShare'] !== null) {
            $sentences[] = $p['active'].' of '.$p['templates'].' are marked active ('.$p['activeShare'].'%).';
        }

        $sentences[] = $p['reportCardTemplates'] > 0
            ? $p['reportCardTemplates'].' report-card layout'.($p['reportCardTemplates'] === 1 ? '' : 's').' are configured separately.'
            : 'No report-card layout is configured.';

        if ($p['emptyBody'] > 0) {
            $sentences[] = $p['emptyBody'].' template'.($p['emptyBody'] === 1 ? '' : 's').' have an empty body.';
        }

        $sentences[] = count($findings) === 0
            ? 'No check found enough evidence to raise a finding.'
            : count($findings).' finding'.(count($findings) === 1 ? '' : 's').' were raised, each with the rows behind it.';

        return [
            'available' => true,
            'reason' => null,
            'headline' => $p['templates'].' templates · '.$p['modulesCovered'].' modules covered',
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
                ['key' => 'templates', 'label' => 'Templates', 'value' => $p['templates'], 'format' => 'count'],
                ['key' => 'modulesCovered', 'label' => 'Modules covered', 'value' => $p['modulesCovered'], 'format' => 'count'],
                ['key' => 'active', 'label' => 'Marked active', 'value' => $p['active'], 'format' => 'count',
                    'hint' => 'of '.$p['templates'].' templates'],
                ['key' => 'activeShare', 'label' => 'Active share', 'value' => $p['activeShare'], 'format' => 'percent'],
                [
                    'key' => 'emptyBody', 'label' => 'Empty body', 'value' => $p['emptyBody'], 'format' => 'count',
                    'tone' => $p['emptyBody'] > 0 ? 'warning' : 'positive',
                ],
                [
                    'key' => 'reportCardTemplates', 'label' => 'Report-card layouts',
                    'value' => $p['reportCardTemplates'], 'format' => 'count',
                    'tone' => $p['reportCardTemplates'] > 0 ? 'positive' : 'warning',
                ],
                ['key' => 'authors', 'label' => 'Authors', 'value' => $p['authors'], 'format' => 'count'],
            ],
        ];
    }

    private function breakdowns(DocumentTemplateIntelligence $a, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byModule = $a->byModule();

        return [[
            'key' => 'modules',
            'label' => 'By module',
            'description' => 'Which modules have documents to offer, and how many. A module absent here has none.',
            'available' => $byModule !== [],
            'reason' => $byModule === [] ? 'No template names the module it belongs to.' : null,
            'primaryColumn' => 'templates',
            'columns' => [['key' => 'templates', 'label' => 'Templates', 'format' => 'count']],
            'rows' => array_map(fn ($m) => [
                'key' => $m['key'], 'label' => $m['label'], 'values' => ['templates' => $m['templates']],
            ], $byModule),
        ]];
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
