<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The institute's document template library, read from vivek_erp.
 *
 * ── WHY THIS IS NOT RESULT INTELLIGENCE ─────────────────────────────────────
 *
 * The `document-templates` menu used to resolve to the Result contract, because
 * the registry matcher recognised it by a route family shared with report cards.
 * The two are different things: Result Intelligence is about marks, this is
 * about the HTML documents an institute has authored and which modules they
 * cover. A bursar opening Document Templates and being shown pass rates is the
 * failure that mapping produced.
 *
 * ── THE GRAIN ───────────────────────────────────────────────────────────────
 *
 * ONE ROW IS ONE TEMPLATE. `template_master` holds the general library keyed by
 * `module_name`; `result_template_master` holds the report-card layouts, which
 * are counted separately because they belong to a different authoring screen.
 *
 * ── ai_templates IS DELIBERATELY EXCLUDED ───────────────────────────────────
 *
 * It has a `sub_institute_id` column, but every one of its 143 rows leaves it
 * empty — they are product-shipped prompt templates, not this institute's
 * documents. Counting them would put another tenant's rows (and Anthropic's
 * defaults) into an institute's own total.
 */
final class DocumentTemplateIntelligence
{
    private const TEMPLATES = 'template_master';

    private const RESULT_TEMPLATES = 'result_template_master';

    private array $memo = [];

    public function __construct(private readonly string $tenantId)
    {
    }

    private function templates()
    {
        return DB::table(self::TEMPLATES)->where('sub_institute_id', $this->tenantId);
    }

    private function memo(string $k, callable $fn)
    {
        return $this->memo[$k] ??= $fn();
    }

    public function coverage(): array
    {
        return $this->memo('coverage', function () {
            if (! SchemaCache::hasTable(self::TEMPLATES)) {
                return $this->unavailable('This installation has no '.self::TEMPLATES.' table.');
            }

            $t = $this->templates()->selectRaw(
                'COUNT(*) AS n, COUNT(DISTINCT module_name) AS mods'
            )->first();

            $rows = (int) ($t->n ?? 0);
            $reportCards = $this->reportCardCount();

            if ($rows === 0 && $reportCards === 0) {
                return $this->unavailable('No document templates have been authored for this institute.');
            }

            return [
                'available' => true,
                'reason' => null,
                'syear' => null,
                'sources' => [
                    'templates' => $rows > 0,
                    'reportCardTemplates' => $reportCards > 0,
                ],
                'counts' => [
                    'templates' => $rows,
                    'modulesCovered' => (int) ($t->mods ?? 0),
                    'reportCardTemplates' => $reportCards,
                ],
            ];
        });
    }

    private function unavailable(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'syear' => null, 'sources' => [], 'counts' => []];
    }

    private function reportCardCount(): int
    {
        return $this->memo('reportCards', function () {
            if (! SchemaCache::hasTable(self::RESULT_TEMPLATES)) {
                return 0;
            }

            return (int) DB::table(self::RESULT_TEMPLATES)->where('sub_institute_id', $this->tenantId)->count();
        });
    }

    /** Templates whose body is empty — authored but never written. */
    private function emptyBodyCount(): int
    {
        return $this->memo('emptyBody', fn () => (int) $this->templates()
            ->where(function ($q) {
                $q->whereNull('html_content')->orWhere('html_content', '');
            })->count());
    }

    private function activeCount(): int
    {
        return $this->memo('active', function () {
            if (! SchemaCache::hasColumn(self::TEMPLATES, 'status')) {
                return 0;
            }

            return (int) $this->templates()->whereIn('status', [1, '1', 'active', 'Active'])->count();
        });
    }

    public function position(): ?array
    {
        return $this->memo('position', function () {
            if (! $this->coverage()['available']) {
                return null;
            }

            $t = $this->templates()->selectRaw(
                'COUNT(*) AS n, COUNT(DISTINCT module_name) AS mods,
                 COUNT(DISTINCT created_by) AS authors,
                 MAX(created_on) AS last_on'
            )->first();

            $total = (int) ($t->n ?? 0);
            $active = $this->activeCount();

            return [
                'templates' => $total,
                'active' => $active,
                // Undefined rather than 0% when there is nothing to take a share of.
                'activeShare' => $total > 0 ? round($active / $total * 100, 1) : null,
                'modulesCovered' => (int) ($t->mods ?? 0),
                'authors' => (int) ($t->authors ?? 0),
                'reportCardTemplates' => $this->reportCardCount(),
                'emptyBody' => $this->emptyBodyCount(),
                'lastAuthoredOn' => $t->last_on ?? null,
            ];
        });
    }

    /** Templates grouped by the module they belong to. */
    public function byModule(): array
    {
        return $this->memo('byModule', function () {
            if (! $this->coverage()['available']) {
                return [];
            }

            return $this->templates()
                ->selectRaw('module_name, COUNT(*) AS n')
                ->groupBy('module_name')
                ->orderByRaw('n DESC')
                ->get()
                ->map(fn ($r) => [
                    'key' => (string) ($r->module_name ?? 'none'),
                    'label' => $r->module_name !== null && $r->module_name !== ''
                        ? (string) $r->module_name : 'Not assigned to a module',
                    'templates' => (int) $r->n,
                ])->all();
        });
    }

    public function dataQuality(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'], 'checks' => []];
        }

        $total = (int) $coverage['counts']['templates'];
        $empty = $this->emptyBodyCount();
        $noModule = (int) $this->templates()->where(function ($q) {
            $q->whereNull('module_name')->orWhere('module_name', '');
        })->count();
        $noTitle = (int) $this->templates()->where(function ($q) {
            $q->whereNull('title')->orWhere('title', '');
        })->count();

        return [
            'available' => true,
            'reason' => null,
            'checks' => [
                [
                    'key' => 'empty_body', 'label' => 'Templates with no content', 'value' => $empty, 'format' => 'count',
                    'sharePercent' => $total > 0 ? round($empty / $total * 100, 2) : null,
                    'state' => $empty > 0 ? 'attention' : 'ok',
                    'note' => $empty > 0
                        ? 'These rows exist in the library but have an empty body, so they render as a blank document.'
                        : 'Every template has a body.',
                ],
                [
                    'key' => 'no_module', 'label' => 'Templates with no module', 'value' => $noModule, 'format' => 'count',
                    'sharePercent' => $total > 0 ? round($noModule / $total * 100, 2) : null,
                    'state' => $noModule > 0 ? 'attention' : 'ok',
                    'note' => $noModule > 0
                        ? 'A template with no module cannot be offered from any module’s screen.'
                        : 'Every template names the module it belongs to.',
                ],
                [
                    'key' => 'no_title', 'label' => 'Templates with no title', 'value' => $noTitle, 'format' => 'count',
                    'sharePercent' => $total > 0 ? round($noTitle / $total * 100, 2) : null,
                    'state' => $noTitle > 0 ? 'attention' : 'ok',
                    'note' => $noTitle > 0 ? 'Untitled templates are indistinguishable in a picker.' : 'Every template is titled.',
                ],
            ],
        ];
    }

    /** @return array{findings: array, ruleStatus: array} */
    public function findings(): array
    {
        $coverage = $this->coverage();
        if (! $coverage['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $p = $this->position();
        $findings = [];
        $status = [];

        // 1. Templates authored but left empty.
        $empty = $p['emptyBody'];
        $raised = $empty > 0;
        if ($raised) {
            $findings[] = $this->finding(
                'doc-templates-empty',
                $empty >= max(1, (int) round($p['templates'] * 0.3)) ? 'high' : 'medium',
                $empty.' of '.$p['templates'].' templates have an empty body',
                'These rows appear in every template picker but produce a blank document when selected.',
                'A template that renders blank is worse than one that is missing: the person selecting it believes '
                    .'a document was produced.',
                [
                    ['label' => 'Empty templates', 'value' => (string) $empty],
                    ['label' => 'Total templates', 'value' => (string) $p['templates']],
                ],
                'Started in the editor and never saved with content.',
                'Fill or remove these templates so the picker only offers documents that render.',
                'Administration',
                ['band' => 'High', 'value' => 0.92],
                ['count' => $empty, 'total' => $p['templates'], 'unit' => 'templates'],
            );
        }
        $status[] = ['key' => 'empty_templates', 'label' => 'Templates with no content', 'checked' => true, 'raised' => $raised];

        // 2. The library serves very few modules.
        $raised = false;
        if ($p['templates'] > 0 && $p['modulesCovered'] <= 2) {
            $raised = true;
            $findings[] = $this->finding(
                'doc-templates-narrow',
                'low',
                'The template library covers only '.$p['modulesCovered'].' module'.($p['modulesCovered'] === 1 ? '' : 's'),
                $p['templates'].' templates are spread across '.$p['modulesCovered'].' module'
                    .($p['modulesCovered'] === 1 ? '' : 's').'.',
                'Templates are offered per module, so modules with none fall back to whatever staff paste in by hand.',
                [
                    ['label' => 'Modules covered', 'value' => (string) $p['modulesCovered']],
                    ['label' => 'Templates', 'value' => (string) $p['templates']],
                ],
                'The library was set up for one workflow and not extended.',
                'Identify which modules staff currently produce documents for by hand.',
                'Administration',
                ['band' => 'Medium', 'value' => 0.7],
                ['count' => $p['modulesCovered'], 'total' => null, 'unit' => 'modules'],
            );
        }
        $status[] = ['key' => 'module_coverage', 'label' => 'Module coverage', 'checked' => true, 'raised' => $raised];

        // 3. No report-card layout, which the Result module needs to publish.
        $raised = $p['reportCardTemplates'] === 0;
        if ($raised) {
            $findings[] = $this->finding(
                'doc-templates-no-report-card',
                'medium',
                'No report-card template is configured',
                'The institute has '.$p['templates'].' general templates but no row in '.self::RESULT_TEMPLATES.'.',
                'Report cards are rendered from their own template table. Without one, the Result module has no '
                    .'layout to publish against.',
                [
                    ['label' => 'Report-card templates', 'value' => '0'],
                    ['label' => 'General templates', 'value' => (string) $p['templates']],
                ],
                'Report cards produced outside the system, or the module not yet in use.',
                'Confirm with the examination cell whether report cards are published from this system.',
                'Examination cell',
                ['band' => 'High', 'value' => 0.88],
                ['count' => 0, 'total' => null, 'unit' => 'templates'],
            );
        }
        $status[] = ['key' => 'report_card', 'label' => 'Report-card layout present', 'checked' => true, 'raised' => $raised];

        return ['findings' => $findings, 'ruleStatus' => $status];
    }

    private function finding(
        string $id, string $severity, string $title, string $what, string $why,
        array $evidence, string $cause, string $recommendation, string $owner,
        array $confidence, array $affected,
    ): array {
        return [
            'id' => $id,
            'severity' => $severity,
            'severityLabel' => ucfirst($severity),
            'title' => $title,
            'whatHappened' => $what,
            'whyItMatters' => $why,
            'evidence' => $evidence,
            'likelyCause' => $cause,
            'causeConfirmed' => false,
            'recommendation' => $recommendation,
            'owner' => $owner,
            'priority' => $severity,
            'confidence' => $confidence,
            'affected' => $affected,
            'impact' => null,
            'status' => 'open',
        ];
    }
}
