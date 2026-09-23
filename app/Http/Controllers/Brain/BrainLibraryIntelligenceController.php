<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\LibraryIntelligence;
use App\Brain\Intelligence\LibrarySignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainLibraryIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'library', 'titles');

        $analytics = new LibraryIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new LibrarySignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · library_book_circulations',
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
                'note' => 'Library circulation metrics are derived live from physical loan records.',
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
                'reason' => $coverage['reason'] ?? 'No library circulations in this academic year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];
        $sentences[] = "{$pos['totalCirculations']} book circulations were recorded across {$pos['distinctTitles']} distinct titles by {$pos['activeBorrowers']} student borrowers.";
        $sentences[] = "The overall circulation return rate is {$pos['returnRate']}%, with {$pos['activeLoans']} books currently on loan.";

        if ($pos['overdueCount'] > 0) {
            $sentences[] = "{$pos['overdueCount']} active loans are overdue past their return due date.";
        }

        $findingCount = count($findings);
        $sentences[] = $findingCount === 0
            ? 'Circulation turnover is healthy with no book contention bottlenecks.'
            : "{$findingCount} library finding" . ($findingCount === 1 ? '' : 's') . ' raised with empirical evidence.';

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$pos['totalCirculations']} circulations · {$pos['activeBorrowers']} active readers · {$pos['returnRate']}% return rate",
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
                    'key' => 'totalCirculations',
                    'label' => 'Total circulations',
                    'value' => $pos['totalCirculations'],
                    'format' => 'count',
                ],
                [
                    'key' => 'activeBorrowers',
                    'label' => 'Active borrowers',
                    'value' => $pos['activeBorrowers'],
                    'format' => 'count',
                ],
                [
                    'key' => 'distinctTitles',
                    'label' => 'Circulated titles',
                    'value' => $pos['distinctTitles'],
                    'format' => 'count',
                ],
                [
                    'key' => 'returnRate',
                    'label' => 'Return rate',
                    'value' => $pos['returnRate'],
                    'format' => 'percent',
                    'tone' => $pos['returnRate'] >= 85 ? 'positive' : 'warning',
                ],
                [
                    'key' => 'activeLoans',
                    'label' => 'Active loans',
                    'value' => $pos['activeLoans'],
                    'format' => 'count',
                ],
                [
                    'key' => 'overdueCount',
                    'label' => 'Overdue books',
                    'value' => $pos['overdueCount'],
                    'format' => 'count',
                    'tone' => $pos['overdueCount'] > 0 ? 'warning' : 'positive',
                ],
            ],
        ];
    }

    private function breakdowns(LibraryIntelligence $analytics, array $coverage): array
    {
        if (!$coverage['available']) {
            return [];
        }

        $byTitle = $analytics->byTitle();
        $byStatus = $analytics->byStatus();

        return [
            [
                'key' => 'titles',
                'label' => 'Top circulated titles',
                'description' => 'Most requested library books and current availability.',
                'available' => !empty($byTitle),
                'reason' => null,
                'primaryColumn' => 'issues',
                'columns' => [
                    ['key' => 'issues', 'label' => 'Total issues', 'format' => 'count'],
                    ['key' => 'author', 'label' => 'Author', 'format' => 'string'],
                    ['key' => 'onLoan', 'label' => 'On loan', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'issues' => $row['issues'],
                        'author' => $row['author'],
                        'onLoan' => $row['onLoan'],
                    ],
                ], $byTitle),
            ],
            [
                'key' => 'statuses',
                'label' => 'By circulation status',
                'description' => 'Breakdown of transactions: returned, active within deadline, and overdue.',
                'available' => !empty($byStatus),
                'reason' => null,
                'primaryColumn' => 'count',
                'columns' => [
                    ['key' => 'count', 'label' => 'Transactions', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'count' => $row['count'],
                        'share' => $row['share'],
                    ],
                ], $byStatus),
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

