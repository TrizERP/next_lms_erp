<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\TeachLearnIntelligence;
use App\Brain\Intelligence\TeachLearnSignalRules;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Teach/Learn Intelligence — the curriculum content catalogue.
 *
 * Teach/Learn is `tblmenumaster` row 269 under "LMS + PAL", whose two live
 * screens are LMS Global Mapping and Course Catalog. It is therefore the
 * catalogue of what is taught and the material published against it — not PAL,
 * not homework, not exams, each of which has its own owner in the registry.
 *
 * See `TeachLearnIntelligence` for what this module deliberately cannot say and
 * why, including the chapter spine that does not resolve and the institute-1
 * shared library that is never counted as a tenant's own coverage.
 */
class BrainTeachLearnIntelligenceController extends Controller
{
    private string $tenantId = '';

    private ?string $syear = null;

    private string $actorId = '';

    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        $loop = new ModuleLoop($this->tenantId, $this->syear, 'teach-learn', 'items');

        $analytics = new TeachLearnIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        // NOT gated on coverage as a whole. A chapter id that resolves to
        // nothing is true of the records whether or not the year holds enough
        // content to describe the curriculum — but each rule gates itself, and
        // the ones that need a rate refuse to run without one.
        $rules = new TeachLearnSignalRules($analytics, $this->syear);
        $raised = $rules->run();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · sub_std_map, content_master, chapter_master, standard',
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
                'note' => 'Teach/Learn findings are evaluated per request from the live course catalogue and content '
                    .'library. The catalogue is not year-scoped — sub_std_map carries no syear — while the content '
                    .'is, so the year in the header applies to what was published, not to what is offered.',
            ],
            'summary' => $this->summary($analytics, $coverage, $raised['findings']),
            'position' => $this->position($analytics, $coverage),
            'breakdowns' => $this->breakdowns($analytics),
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
     * DETERMINISTIC, NEVER MODEL OUTPUT. It never names a file: titles,
     * filenames and URLs are not read by this module.
     *
     * @param  array<string,mixed>  $coverage
     * @param  array<int,array<string,mixed>>  $findings
     * @return array<string,mixed>
     */
    private function summary(TeachLearnIntelligence $analytics, array $coverage, array $findings): array
    {
        $shape = $analytics->shape();
        $count = count($findings);

        if (! $coverage['available']) {
            $sentences = [$coverage['reason'] ?? 'No teaching content for this academic year.'];

            // The catalogue is real even where the content is not, and it is
            // the one thing worth saying in that state.
            if ($shape['courses'] > 0) {
                $sentences[] = "The catalogue itself is intact: {$shape['courses']} "
                    .($shape['courses'] === 1 ? 'course is' : 'courses are')
                    .' mapped for this institute. No coverage rate is shown, because a rate over no published '
                    .'content is undefined rather than zero.';
            }

            if ($count > 0) {
                $sentences[] = $count === 1
                    ? 'One finding below about the records themselves, with the figures it rests on.'
                    : "{$count} findings below about the records themselves, each with the figures it rests on.";
            }

            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? null,
                'headline' => null,
                'sentences' => $sentences,
            ];
        }

        $sentences = [$analytics->position()['summary']];

        $sentences[] = match ($count) {
            0 => 'No check found enough evidence to raise a finding for this year.',
            1 => 'One finding below, with the figures it rests on.',
            default => "{$count} findings below, each with the figures it rests on.",
        };

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$shape['coursesWithContent']} of {$shape['courses']} courses · {$shape['items']} items",
            'sentences' => $sentences,
        ];
    }

    /**
     * @param  array<string,mixed>  $coverage
     * @return array<string,mixed>
     */
    private function position(TeachLearnIntelligence $analytics, array $coverage): array
    {
        $shape = $analytics->shape();

        if (! $coverage['available']) {
            // The catalogue count is still reported — it is measured and it is
            // true — while every rate stays absent rather than zero.
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? null,
                'metrics' => $shape['courses'] > 0 ? [
                    [
                        'key' => 'courses',
                        'label' => 'Courses in the catalogue',
                        'value' => $shape['courses'],
                        'format' => 'count',
                        'hint' => 'Not year-scoped: sub_std_map carries no academic year',
                    ],
                ] : [],
            ];
        }

        $metrics = $analytics->position()['metrics'];

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'courses',
                    'label' => 'Courses in the catalogue',
                    'value' => $metrics['courses'],
                    'format' => 'count',
                    'hint' => 'One subject taught to one class. Not year-scoped — sub_std_map carries no syear',
                ],
                [
                    'key' => 'coursesWithContent',
                    'label' => 'Courses with content this year',
                    'value' => $metrics['coursesWithContent'],
                    'format' => 'count',
                ],
                [
                    'key' => 'contentCoverage',
                    'label' => 'Curriculum covered',
                    'value' => $metrics['contentCoverage'],
                    'format' => 'percent',
                    'tone' => ($metrics['contentCoverage'] ?? 100) < 50.0 ? 'warning' : null,
                    'hint' => $shape['coursesWithoutContent'] > 0
                        ? "{$shape['coursesWithoutContent']} courses carry nothing for {$this->syear}"
                        : null,
                ],
                [
                    'key' => 'items',
                    'label' => 'Items published this year',
                    'value' => $metrics['items'],
                    'format' => 'count',
                    'hint' => $coverage['period'] ?? null,
                ],
                [
                    'key' => 'formats',
                    'label' => 'Formats in use',
                    'value' => $metrics['formats'],
                    'format' => 'count',
                    'hint' => $shape['topFormat'] !== null && $shape['items'] > 0
                        ? 'Most common: '.$shape['topFormat']
                        : null,
                ],
                [
                    'key' => 'hidden',
                    'label' => 'Published but hidden',
                    'value' => $metrics['hidden'],
                    'format' => 'count',
                    'tone' => $shape['hidden'] > 0 ? 'warning' : null,
                    'hint' => $shape['hidden'] > 0
                        ? 'Prepared material learners cannot currently reach'
                        : null,
                ],
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function breakdowns(TeachLearnIntelligence $analytics): array
    {
        $byClass = $analytics->byClass();
        $byFormat = $analytics->byFormat();
        $byYear = $analytics->byYear();

        return [
            [
                'key' => 'by_class',
                'label' => 'Curriculum coverage by class',
                'description' => 'How much of each class’s course list carries published material this year, '
                    .'resolved against this institute’s own class master. No chapter figures appear anywhere on this '
                    .'screen: the chapter ids this content carries do not resolve against this institute’s chapter '
                    .'master, and the record checks below say by how much.',
                'available' => $byClass !== [],
                'reason' => $byClass === []
                    ? 'No course in this institute’s catalogue resolves to a class its own standard master holds.'
                    : null,
                'primaryColumn' => 'courses',
                'columns' => [
                    ['key' => 'courses', 'label' => 'Courses', 'format' => 'count'],
                    ['key' => 'withContent', 'label' => 'With content', 'format' => 'count'],
                    ['key' => 'coverage', 'label' => 'Covered', 'format' => 'percent'],
                    ['key' => 'items', 'label' => 'Items', 'format' => 'count'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'courses' => $row['courses'],
                        'withContent' => $row['withContent'],
                        'coverage' => $row['coverage'],
                        'items' => $row['items'],
                    ],
                ], $byClass),
            ],
            [
                'key' => 'by_format',
                'label' => 'What the material is',
                'description' => 'Published items by file format. The format token is the only thing read from a '
                    .'content row — titles, filenames and links name real teaching files and are never returned.',
                'available' => $byFormat !== [],
                'reason' => $byFormat === [] ? 'No content was published for this year.' : null,
                'primaryColumn' => 'items',
                'columns' => [
                    ['key' => 'items', 'label' => 'Items', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                    ['key' => 'hidden', 'label' => 'Hidden', 'format' => 'count'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'items' => $row['items'],
                        'share' => $row['share'],
                        'hidden' => $row['hidden'],
                    ],
                ], $byFormat),
            ],
            [
                'key' => 'by_year',
                'label' => 'Publishing history',
                'description' => 'What this institute has added in each year it has used the library. Deliberately '
                    .'spans every year rather than the selected one — whether this year is quiet is only answerable '
                    .'against what this institute normally adds, never against another school.',
                'available' => $byYear !== [],
                'reason' => $byYear === []
                    ? 'This institute has never published teaching content in any year.'
                    : null,
                'primaryColumn' => 'items',
                'columns' => [
                    ['key' => 'items', 'label' => 'Items', 'format' => 'count'],
                    ['key' => 'courses', 'label' => 'Courses touched', 'format' => 'count'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['current'] ? $row['label'].' (selected)' : $row['label'],
                    'values' => [
                        'items' => $row['items'],
                        'courses' => $row['courses'],
                    ],
                ], $byYear),
            ],
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $findings
     * @return array<int,array<string,mixed>>
     */
    private function priorities(array $findings): array
    {
        $severe = array_values(array_filter(
            $findings,
            static fn ($f) => in_array($f['severity'], ['critical', 'high', 'medium'], true),
        ));

        return array_map(static fn ($f) => [
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
     * Write this module's findings to the signal ledger. IDEMPOTENT —
     * `SignalWriter` dedupes on (tenant, rule, year).
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
