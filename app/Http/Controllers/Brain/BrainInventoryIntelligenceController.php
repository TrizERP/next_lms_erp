<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\InventoryIntelligence;
use App\Brain\Intelligence\InventorySignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainInventoryIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'inventory', 'scans');

        $analytics = new InventoryIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new InventorySignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · item_scan_details, inventory_requisition_details',
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
                'note' => 'Every figure is read from this year’s stock-take scans at the moment the page loaded.',
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
     * DETERMINISTIC, NEVER MODEL OUTPUT. Where the outcome column was never
     * filled in, the sentence says the stock-take has no result — it does not
     * report the absence as a 0% verification rate, which is what this module
     * used to do.
     */
    private function summary(?array $pos, array $coverage, array $findings): array
    {
        if ($pos === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'No stock-take scans in this academic year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];
        $plural = static fn (int $n, string $one, string $many): string => $n === 1 ? $one : $many;

        // An institute that ran no stock-take at all and one whose stock-take
        // has no outcome recorded are different situations. Saying "0 scans
        // covering 0 codes, so the stock-take has no result" conflates them.
        if ($pos['scans'] === 0) {
            $sentences[] = 'No stock-take was recorded for this academic year.';
        } else {
            $sentences[] = "{$pos['scans']} barcode scans were recorded this year, covering {$pos['itemCodes']} "
                .'distinct item codes'
                .($pos['repeatScans'] > 0
                    ? " — {$pos['repeatScans']} scans repeat a code already counted."
                    : '.');

            $sentences[] = match (true) {
                // NULL means the outcome column is empty, not that nothing was found.
                $pos['verificationRate'] === null => 'No scan records whether the item was found, so the stock-take '
                    .'has no result: nothing here can say what was verified, and nothing can say what is missing.',
                $pos['outcomeCoverage'] !== null && $pos['outcomeCoverage'] < 100.0 => "{$pos['itemsFound']} of the "
                    ."{$pos['scansWithOutcome']} scans that record an outcome found the item "
                    ."({$pos['verificationRate']}%); the remaining scans carry no outcome and are excluded from that "
                    .'rate.',
                default => "{$pos['itemsFound']} of {$pos['scansWithOutcome']} scans found the item, a verification "
                    ."rate of {$pos['verificationRate']}%.",
            };
        }

        $sentences[] = $pos['itemsOnMaster'] === null
            ? 'The item master holds no rows for this institute, so a scanned code cannot be resolved to an item '
                .'name, a category or a reorder level. Nothing on this screen approximates one.'
            : $pos['itemsOnMaster'].' '.$plural($pos['itemsOnMaster'], 'item is', 'items are').' on the item master.';

        if ($pos['requisitions'] !== null) {
            $sentences[] = $pos['requisitions'].' requisition '
                .$plural($pos['requisitions'], 'line was', 'lines were').' raised this year.';
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
            'headline' => match (true) {
                $pos['scans'] === 0 && $pos['requisitions'] !== null =>
                    "{$pos['requisitions']} requisition lines, no stock-take this year",
                $pos['scans'] === 0 => 'No stock-take recorded this year',
                $pos['verificationRate'] !== null =>
                    "{$pos['itemCodes']} items scanned, {$pos['verificationRate']}% found",
                default => "{$pos['itemCodes']} items scanned, no outcome recorded",
            },
            'sentences' => $sentences,
        ];
    }

    private function position(?array $pos, array $coverage): ?array
    {
        if ($pos === null) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        $noOutcome = $pos['verificationRate'] === null;

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'itemCodes',
                    'label' => 'Items scanned',
                    'value' => $pos['itemCodes'],
                    'format' => 'count',
                    // The scan count is not the item count, and saying so here is
                    // what stops the next reader treating one as the other.
                    'hint' => "Across {$pos['scans']} scans",
                ],
                [
                    'key' => 'verificationRate',
                    'label' => 'Found',
                    // NULL where the outcome column is empty. An em dash, never a
                    // zero: "0% found" describes a catastrophe that did not happen.
                    'value' => $pos['verificationRate'],
                    'format' => 'percent',
                    'tone' => $noOutcome ? 'attention' : null,
                    'hint' => $noOutcome
                        ? 'No scan records whether the item was found'
                        : "Of {$pos['scansWithOutcome']} scans that record an outcome",
                ],
                [
                    'key' => 'itemsNotFound',
                    'label' => 'Not found',
                    'value' => $pos['itemsNotFound'],
                    'format' => 'count',
                    'tone' => ($pos['itemsNotFound'] ?? 0) > 0 ? 'warning' : null,
                    'hint' => $noOutcome ? 'No outcome recorded to read this from' : 'Scanned and not located',
                ],
                [
                    'key' => 'outcomeCoverage',
                    'label' => 'Scans with an outcome',
                    'value' => $pos['outcomeCoverage'],
                    'format' => 'percent',
                    'tone' => $pos['outcomeCoverage'] !== null && $pos['outcomeCoverage'] < 100.0
                        ? 'attention'
                        : 'positive',
                    'hint' => "{$pos['scansWithOutcome']} of {$pos['scans']} scans",
                ],
                [
                    'key' => 'repeatScans',
                    'label' => 'Repeat scans',
                    'value' => $pos['repeatScans'],
                    'format' => 'count',
                    'hint' => $pos['repeatShare'] !== null ? "{$pos['repeatShare']}% of scans" : null,
                ],
                [
                    'key' => 'requisitions',
                    'label' => 'Requisition lines',
                    'value' => $pos['requisitions'],
                    'format' => 'count',
                    'hint' => $pos['requisitions'] === null
                        ? 'No requisitions were raised this year'
                        : 'Raised this academic year',
                ],
                [
                    'key' => 'itemsOnMaster',
                    'label' => 'Items on the master',
                    'value' => $pos['itemsOnMaster'],
                    'format' => 'count',
                    'tone' => $pos['itemsOnMaster'] === null ? 'attention' : null,
                    'hint' => $pos['itemsOnMaster'] === null
                        ? 'A scanned code cannot be resolved to an item'
                        : 'Codes can be resolved against the master',
                ],
            ],
        ];
    }

    private function breakdowns(InventoryIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byOutcome = $analytics->byOutcome();
        $byPrefix = $analytics->byCodePrefix();
        $byRequisition = $analytics->byRequisitionStatus();

        return [
            [
                'key' => 'outcomes',
                'label' => 'By recorded outcome',
                'description' => 'The institute’s own outcome words, verbatim. This module does not decide that one '
                    .'of them means verified and another means missing.',
                'available' => $byOutcome !== [],
                // Omitted rather than drawn empty: a blank found/not-found table
                // reads as a result, and there is no result.
                'reason' => $byOutcome === []
                    ? 'No scan this year records an outcome, so there is nothing to divide by one.'
                    : null,
                'primaryColumn' => 'scans',
                'columns' => [
                    ['key' => 'scans', 'label' => 'Scans', 'format' => 'count'],
                    ['key' => 'codes', 'label' => 'Distinct codes', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'scans' => $row['scans'],
                        'codes' => $row['codes'],
                        'share' => $row['share'],
                    ],
                ], $byOutcome),
            ],
            [
                'key' => 'prefixes',
                'label' => 'By item-code prefix',
                'description' => 'Item codes group by their first two characters, and the grouping is real — one '
                    .'stock-take is overwhelmingly one prefix. What a prefix STANDS FOR is not recorded in any table '
                    .'this module can read, so it is shown as the prefix rather than given a category name.',
                'available' => count($byPrefix) > 1,
                'reason' => count($byPrefix) <= 1
                    ? 'Every item code this year shares a single prefix, so there is nothing to compare across.'
                    : null,
                'primaryColumn' => 'scans',
                'columns' => [
                    ['key' => 'scans', 'label' => 'Scans', 'format' => 'count'],
                    ['key' => 'codes', 'label' => 'Distinct codes', 'format' => 'count'],
                    ['key' => 'verificationRate', 'label' => 'Found', 'format' => 'percent'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'scans' => $row['scans'],
                        'codes' => $row['codes'],
                        'verificationRate' => $row['verificationRate'],
                        'share' => $row['share'],
                    ],
                ], $byPrefix),
            ],
            [
                'key' => 'requisitions',
                'label' => 'By requisition status',
                'description' => 'Requisition lines raised this year, by the status they are sitting in, with the '
                    .'quantity requested against the quantity approved.',
                'available' => $byRequisition !== [],
                'reason' => $byRequisition === []
                    ? 'No requisition was raised for this academic year.'
                    : null,
                'primaryColumn' => 'lines',
                'columns' => [
                    ['key' => 'lines', 'label' => 'Lines', 'format' => 'count'],
                    ['key' => 'requested', 'label' => 'Requested', 'format' => 'count'],
                    ['key' => 'approved', 'label' => 'Approved', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'lines' => $row['lines'],
                        'requested' => $row['requested'],
                        'approved' => $row['approved'],
                        'share' => $row['share'],
                    ],
                ], $byRequisition),
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

