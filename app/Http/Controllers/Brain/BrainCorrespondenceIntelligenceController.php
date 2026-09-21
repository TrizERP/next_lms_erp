<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\CorrespondenceIntelligence;
use App\Brain\Intelligence\CorrespondenceSignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainCorrespondenceIntelligenceController extends Controller
{
    private string $tenantId = '';
    private ?string $syear = null;
    private string $actorId = '';
    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        $loop = new ModuleLoop($this->tenantId, $this->syear, 'correspondence', 'entries');

        $analytics = new CorrespondenceIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        // NOT gated on coverage. An entry filed to a location this institute
        // does not hold, and a number issued twice, are true of the records
        // whether or not the year holds enough letters to describe the office.
        $rules = new CorrespondenceSignalRules($analytics, $this->syear);
        $raised = $rules->run();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · inward, outward, place_master, physical_file_location',
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
                'note' => 'Correspondence findings are evaluated per request from the live inward and outward '
                    .'registers, scoped by the register’s own syear.',
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
     * DETERMINISTIC, NEVER MODEL OUTPUT. It never quotes what a letter said:
     * `title` and `description` are free text this module does not read.
     *
     * @param  array<string,mixed>  $coverage
     * @param  array<int,array<string,mixed>>  $findings
     * @return array<string,mixed>
     */
    private function summary(CorrespondenceIntelligence $analytics, array $coverage, array $findings): array
    {
        $shape = $analytics->shape();
        $count = count($findings);

        if (! $coverage['available']) {
            $sentences = [$coverage['reason'] ?? 'No correspondence for this academic year.'];

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
            'headline' => "{$shape['inward']} in · {$shape['outward']} out",
            'sentences' => $sentences,
        ];
    }

    /**
     * @param  array<string,mixed>  $coverage
     * @return array<string,mixed>
     */
    private function position(CorrespondenceIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        $metrics = $analytics->position()['metrics'];
        $shape = $analytics->shape();

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'inward',
                    'label' => 'Logged inward',
                    'value' => $metrics['inward'],
                    'format' => 'count',
                    'hint' => $coverage['period'] ?? null,
                ],
                [
                    'key' => 'outward',
                    'label' => 'Logged outward',
                    'value' => $metrics['outward'],
                    'format' => 'count',
                    'tone' => $shape['outward'] === 0 ? 'warning' : null,
                    'hint' => $shape['outward'] === 0
                        ? 'Nothing was logged outward this year'
                        : null,
                ],
                [
                    'key' => 'outwardShare',
                    'label' => 'Share logged outward',
                    'value' => $metrics['outwardShare'],
                    'format' => 'percent',
                    'tone' => ($metrics['outwardShare'] ?? 100) < 5.0 ? 'warning' : null,
                ],
                [
                    'key' => 'attachmentShare',
                    'label' => 'Inward entries holding a scan',
                    'value' => $metrics['attachmentShare'],
                    'format' => 'percent',
                ],
                [
                    'key' => 'placesUsed',
                    'label' => 'Senders on file',
                    'value' => $metrics['placesUsed'],
                    'format' => 'count',
                ],
                [
                    'key' => 'fileLocationsUsed',
                    'label' => 'File locations in use',
                    'value' => $metrics['fileLocationsUsed'],
                    'format' => 'count',
                    'tone' => $shape['locationsUnresolved'] > 0 ? 'warning' : null,
                    'hint' => $shape['locationsUnresolved'] > 0
                        ? "{$shape['locationsUnresolved']} entries name a location that is not on file"
                        : null,
                ],
                [
                    'key' => 'duplicateNumbers',
                    'label' => 'Numbers issued twice',
                    'value' => $metrics['duplicateNumbers'],
                    'format' => 'count',
                    'tone' => $shape['duplicateNumbers'] > 0 ? 'warning' : null,
                    'hint' => 'Counted within this academic year; numbering restarts each year by design',
                ],
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function breakdowns(CorrespondenceIntelligence $analytics): array
    {
        $byPlace = $analytics->byPlace();
        $byMonth = $analytics->byMonth();

        return [
            [
                'key' => 'by_place',
                'label' => 'Where correspondence comes from',
                'description' => 'The senders this institute logs most, resolved against its own place master. What '
                    .'each letter said is deliberately not read: the title and description columns are free text and '
                    .'a correspondence register holds matters about named staff and children.',
                'available' => $byPlace !== [],
                'reason' => $byPlace === []
                    ? 'No inward entry this year names a place this institute’s place master holds.'
                    : null,
                'primaryColumn' => 'entries',
                'columns' => [
                    ['key' => 'entries', 'label' => 'Entries', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                    ['key' => 'noAttachment', 'label' => 'No scan held', 'format' => 'count'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'entries' => $row['entries'],
                        'share' => $row['share'],
                        'noAttachment' => $row['noAttachment'],
                    ],
                ], $byPlace),
            ],
            [
                'key' => 'by_month',
                'label' => 'Across the year',
                'description' => 'When correspondence arrives, month by month.',
                'available' => $byMonth !== [],
                'reason' => $byMonth === [] ? 'No inward entry this year carries a usable date.' : null,
                'primaryColumn' => 'entries',
                'columns' => [
                    ['key' => 'entries', 'label' => 'Entries', 'format' => 'count'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['entries' => $row['entries']],
                ], $byMonth),
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
