<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\PtmIntelligence;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PTM Intelligence — parent–teacher meetings for one institute-year.
 *
 * THE YEAR IS REAL HERE, unlike petty cash and templates: slots carry `syear`,
 * and bookings are dated through the slot they belong to. Every figure below is
 * therefore scoped to the year the header has selected.
 */
class BrainPtmIntelligenceController extends Controller
{
    private string $tenantId = '';

    private ?string $syear = null;

    private string $actorId = '';

    private bool $actorIsStudent = false;

    public function index(Request $request): JsonResponse
    {
        $this->scope($request);

        $analytics = new PtmIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();
        $position = $analytics->position();
        $raised = $analytics->findings();
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'ptm', 'meetings');

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · ptm_time_slots_master, ptm_booking_master',
            'academicYear' => ['syear' => $this->syear],
            'coverage' => $coverage,
            'freshness' => [
                'positionLabel' => 'Read live at page load',
                'findingsRefreshedAt' => $coverage['available'] ? now()->toIso8601String() : null,
            ],
            'execution' => [
                'automated' => false,
                'note' => 'Bookings carry no academic year of their own and are dated through the slot they belong '
                    .'to, so every booking figure here is scoped to the selected year by that join.',
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
            $p['slots'].' meeting slot'.($p['slots'] === 1 ? '' : 's').' were offered across '.$p['meetingDays']
                .' day'.($p['meetingDays'] === 1 ? '' : 's').' and '.$p['classes'].' class'
                .($p['classes'] === 1 ? '' : 'es').'.',
            $p['bookings'].' booking'.($p['bookings'] === 1 ? '' : 's').' were made by '.$p['students']
                .' student'.($p['students'] === 1 ? '' : 's').' with '.$p['teachers']
                .' teacher'.($p['teachers'] === 1 ? '' : 's').'.',
        ];

        if ($p['slotUptake'] !== null) {
            $sentences[] = 'Slot take-up is '.$p['slotUptake'].'%.';
        }

        $sentences[] = $p['attendanceRate'] !== null
            ? $p['attended'].' of the '.$p['attendanceRecorded'].' bookings with attendance recorded were attended ('
                .$p['attendanceRate'].'%).'
            : 'Attendance has not been recorded against any booking, so no attendance rate can be computed.';

        $sentences[] = count($findings) === 0
            ? 'No check found enough evidence to raise a finding.'
            : count($findings).' finding'.(count($findings) === 1 ? '' : 's').' were raised, each with the rows behind it.';

        return [
            'available' => true,
            'reason' => null,
            'headline' => $p['bookings'].' bookings against '.$p['slots'].' slots'
                .($p['slotUptake'] !== null ? ' · '.$p['slotUptake'].'% take-up' : ''),
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
                [
                    'key' => 'slotUptake', 'label' => 'Slot take-up', 'value' => $p['slotUptake'], 'format' => 'percent',
                    'tone' => $p['slotUptake'] !== null && $p['slotUptake'] < 50 ? 'warning' : 'positive',
                    'hint' => $p['slotsBooked'].' of '.$p['slots'].' slots booked',
                ],
                ['key' => 'bookings', 'label' => 'Bookings', 'value' => $p['bookings'], 'format' => 'count'],
                ['key' => 'students', 'label' => 'Students', 'value' => $p['students'], 'format' => 'count'],
                ['key' => 'teachers', 'label' => 'Teachers', 'value' => $p['teachers'], 'format' => 'count'],
                [
                    'key' => 'attendanceRate', 'label' => 'Attended', 'value' => $p['attendanceRate'], 'format' => 'percent',
                    'tone' => $p['attendanceRate'] !== null && $p['attendanceRate'] < 70 ? 'warning' : 'positive',
                    'hint' => 'of '.$p['attendanceRecorded'].' bookings with a mark',
                ],
                ['key' => 'slots', 'label' => 'Slots offered', 'value' => $p['slots'], 'format' => 'count'],
                ['key' => 'classes', 'label' => 'Classes', 'value' => $p['classes'], 'format' => 'count'],
                ['key' => 'meetingDays', 'label' => 'Meeting days', 'value' => $p['meetingDays'], 'format' => 'count'],
            ],
        ];
    }

    private function breakdowns(PtmIntelligence $a, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byClass = $a->byClass();
        $byDate = $a->byDate();

        return [
            [
                'key' => 'classes',
                'label' => 'By class',
                'description' => 'Slots offered against bookings taken, per class.',
                'available' => $byClass !== [],
                'reason' => $byClass === [] ? 'No slot names a class.' : null,
                'primaryColumn' => 'slots',
                'columns' => [
                    ['key' => 'slots', 'label' => 'Slots', 'format' => 'count'],
                    ['key' => 'bookings', 'label' => 'Bookings', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($c) => [
                    'key' => $c['key'], 'label' => $c['label'],
                    'values' => ['slots' => $c['slots'], 'bookings' => $c['bookings']],
                ], $byClass),
            ],
            [
                'key' => 'dates',
                'label' => 'By meeting day',
                'description' => 'When parents actually booked, across the meeting dates offered.',
                'available' => $byDate !== [],
                'reason' => $byDate === [] ? 'No booking resolves to a dated slot.' : null,
                'primaryColumn' => 'bookings',
                'columns' => [['key' => 'bookings', 'label' => 'Bookings', 'format' => 'count']],
                'rows' => array_map(fn ($d) => [
                    'key' => $d['key'], 'label' => $d['label'], 'values' => ['bookings' => $d['bookings']],
                ], $byDate),
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
