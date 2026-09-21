<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\VisitorIntelligence;
use App\Brain\Intelligence\VisitorSignalRules;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainVisitorIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'visitor', 'visits');

        $analytics = new VisitorIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        // NOT gated on coverage. A visit filed under a type this institute does
        // not hold, and a row carrying no usable date at all, are true of the
        // records whether or not there are enough visits to describe the gate —
        // and the rows with no date are by definition inside no year window.
        $rules = new VisitorSignalRules($analytics, $this->syear);
        $raised = $rules->run();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · visitor_master, visitor_type',
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
                'note' => 'Visitor findings are evaluated per request from the live gate register, scoped to this '
                    .'institute’s own term dates.',
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
     * DETERMINISTIC, NEVER MODEL OUTPUT. It never reports how long a visit
     * lasted: the two time columns on this table are not on a consistent clock.
     * See VisitorIntelligence's class note.
     *
     * @param  array<string,mixed>  $coverage
     * @param  array<int,array<string,mixed>>  $findings
     * @return array<string,mixed>
     */
    private function summary(VisitorIntelligence $analytics, array $coverage, array $findings): array
    {
        $shape = $analytics->shape();
        $count = count($findings);

        if (! $coverage['available']) {
            $sentences = [$coverage['reason'] ?? 'No visitor records for this academic year.'];

            if ($count > 0) {
                $sentences[] = $count === 1
                    ? 'One finding below about the records themselves, with the figures it rests on.'
                    : "{$count} findings below about the records themselves, each with the figures it rests on.";
            }

            return [
                // A register with rows that belong to no year is still worth a
                // sentence; an institute that has never used the module is not.
                'available' => $shape['badDates'] > 0,
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

        $openShare = round($shape['open'] / $shape['visits'] * 100, 1);

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$shape['visits']} visitors · {$openShare}% never signed out",
            'sentences' => $sentences,
        ];
    }

    /**
     * @param  array<string,mixed>  $coverage
     * @return array<string,mixed>
     */
    private function position(VisitorIntelligence $analytics, array $coverage): array
    {
        $metrics = $analytics->position()['metrics'];
        $shape = $analytics->shape();

        if (! $coverage['available']) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'visits',
                    'label' => 'Visitors signed in',
                    'value' => $metrics['visits'],
                    'format' => 'count',
                    'hint' => $coverage['period'] ?? null,
                ],
                [
                    'key' => 'openVisits',
                    'label' => 'Never signed out',
                    'value' => $metrics['openVisits'],
                    'format' => 'count',
                    'tone' => $shape['open'] > 0 ? 'warning' : null,
                    'hint' => $shape['open'] > 0
                        ? 'The register does not show these visitors leaving'
                        : 'Every visitor was signed out again',
                ],
                [
                    'key' => 'openShare',
                    'label' => 'Share never signed out',
                    'value' => $metrics['openShare'],
                    'format' => 'percent',
                    'tone' => ($metrics['openShare'] ?? 0) >= 15.0 ? 'warning' : null,
                ],
                [
                    'key' => 'priorShare',
                    'label' => 'Arrived on a prior appointment',
                    // NULL where nobody recorded whether the visitor was
                    // expected — an unused field is not "0% expected".
                    'value' => $metrics['priorShare'],
                    'format' => 'percent',
                    'hint' => $shape['appointmentRecorded'] === 0
                        ? 'Not recorded on any visit this year'
                        : "Of {$shape['appointmentRecorded']} visits recording it",
                ],
                [
                    'key' => 'visitorTypes',
                    'label' => 'Visitor types in use',
                    'value' => $metrics['visitorTypes'],
                    'format' => 'count',
                    'tone' => $shape['typesResolved'] === 0 && $shape['typesUsed'] > 0 ? 'warning' : null,
                    'hint' => $shape['typesResolved'] === 0 && $shape['typesUsed'] > 0
                        ? "{$shape['typesUsed']} type ids are used and none is held by this institute"
                        : null,
                ],
                [
                    'key' => 'daysWithVisits',
                    'label' => 'Days with a visitor',
                    'value' => $metrics['daysWithVisits'],
                    'format' => 'count',
                ],
                [
                    'key' => 'averageVisitLength',
                    'label' => 'Average visit length',
                    // ALWAYS NULL, AT EVERY INSTITUTE. The entry and exit columns
                    // are bare times on an inconsistent clock; 12% of closed
                    // visits at one institute record an exit before the entry.
                    'value' => null,
                    'format' => 'duration',
                    'hint' => 'Not computable: entry and exit are stored as bare times with no date and no record of '
                        .'whether they are on a 12- or 24-hour clock',
                ],
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function breakdowns(VisitorIntelligence $analytics): array
    {
        $shape = $analytics->shape();
        $byType = $analytics->byType();
        $byMonth = $analytics->byMonth();

        return [
            [
                'key' => 'by_visitor_type',
                'label' => 'By kind of visitor',
                'description' => 'Who comes through the gate, and how much of each kind the register never signs out '
                    .'again. Types are resolved against this institute’s own visitor_type master and against no '
                    .'other, which is why this is empty where the register names another institute’s type ids.',
                'available' => $byType !== [],
                'reason' => $byType === []
                    ? ($shape['typesUsed'] > 0
                        ? 'This year’s visits name '.$shape['typesUsed'].' visitor-type id'
                            .($shape['typesUsed'] === 1 ? '' : 's').' and this institute’s visitor_type master holds '
                            .'none of them, so the kind of visitor cannot be named. They are deliberately not '
                            .'resolved against another institute’s master, which is where those ids exist.'
                        : 'No visitor type is recorded against this year’s visits.')
                    : null,
                'primaryColumn' => 'visits',
                'columns' => [
                    ['key' => 'visits', 'label' => 'Visits', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                    ['key' => 'openVisits', 'label' => 'Never signed out', 'format' => 'count'],
                    ['key' => 'openShare', 'label' => 'Open share', 'format' => 'percent'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'tone' => ($row['openShare'] ?? 0) >= 50.0 ? 'attention' : null,
                    'note' => ($row['openShare'] ?? 0) >= 50.0
                        ? 'More than half of these visits were never closed'
                        : null,
                    'values' => [
                        'visits' => $row['visits'],
                        'share' => $row['share'],
                        'openVisits' => $row['openVisits'],
                        'openShare' => $row['openShare'],
                    ],
                ], $byType),
            ],
            [
                'key' => 'by_month',
                'label' => 'Across the year',
                'description' => 'When the gate is busy, month by month through this institute’s own academic year.',
                'available' => $byMonth !== [],
                'reason' => $byMonth === [] ? 'No visits fall inside this academic year’s term dates.' : null,
                'primaryColumn' => 'visits',
                'columns' => [
                    ['key' => 'visits', 'label' => 'Visits', 'format' => 'count'],
                    ['key' => 'openVisits', 'label' => 'Never signed out', 'format' => 'count'],
                ],
                'rows' => array_map(static fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => [
                        'visits' => $row['visits'],
                        'openVisits' => $row['openVisits'],
                    ],
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
     * Write this module's findings to the signal ledger, so they enter the
     * recommendation → decision → execution → outcome → learning loop.
     *
     * IDEMPOTENT. `SignalWriter` dedupes on (tenant, rule, year), so pressing
     * this twice refreshes the same signals with fresher figures rather than
     * duplicating them.
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
