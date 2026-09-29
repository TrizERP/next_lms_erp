<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\OrganizationIntelligence;
use App\Brain\Intelligence\OrganizationSignalRules;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainOrganizationIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'organization', 'staff');

        $analytics = new OrganizationIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new OrganizationSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · tbluser, hrms_departments, talent_job_postings, talent_job_applications, '
                .'s_users_skills, s_skill_matrix',
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
                'note' => 'Headcount, hiring and attrition are staff-body figures rather than academic-year figures, '
                    .'so none of them is scoped to the selected year — they are measured over rolling 30-day windows, '
                    .'matching the Employee Directory’s own KPI cards. Skill-coverage and proficiency trends are not '
                    .'shown because no historical snapshot exists to compute them against.',
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
     * DETERMINISTIC, NEVER MODEL OUTPUT.
     */
    private function summary(?array $pos, array $coverage, array $findings): array
    {
        if ($pos === null) {
            return [
                'available' => false,
                'reason' => $coverage['reason'] ?? 'No staff records exist for this institute.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        $sentences[] = "{$pos['activeStaff']} staff are active across {$pos['departmentsWithStaff']} of "
            ."{$pos['departments']} departments in the department master.";

        $sentences[] = "{$pos['newHires']} were hired and {$pos['attritionCount']} left in the last "
            .OrganizationIntelligence::RECENT_WINDOW_DAYS.' days'
            .($pos['attritionRate'] !== null ? ", an attrition rate of {$pos['attritionRate']}% of active staff." : '.');

        if ($pos['avgSkillCoverage'] !== null) {
            $sentences[] = "Across the institute, {$pos['coveredSkills']} of {$pos['requiredSkills']} required skills "
                ."are held by at least one active member of staff ({$pos['avgSkillCoverage']}%).";
        }

        $count = count($findings);
        $sentences[] = match ($count) {
            0 => 'No check found enough evidence to raise a finding.',
            1 => 'One finding below, with the figures it rests on.',
            default => "{$count} findings below, each with the figures it rests on.",
        };

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$pos['activeStaff']} active staff, {$pos['netGrowth']} net "
                .($pos['netGrowth'] >= 0 ? 'gained' : 'lost').' in the last '.OrganizationIntelligence::RECENT_WINDOW_DAYS.' days',
            'sentences' => $sentences,
        ];
    }

    private function position(?array $pos, array $coverage): ?array
    {
        if ($pos === null) {
            return ['available' => false, 'reason' => $coverage['reason'] ?? null, 'metrics' => []];
        }

        $days = OrganizationIntelligence::RECENT_WINDOW_DAYS;

        return [
            'available' => true,
            'reason' => null,
            'metrics' => [
                [
                    'key' => 'activeStaff',
                    'label' => 'Active staff',
                    'value' => $pos['activeStaff'],
                    'format' => 'count',
                    'hint' => "{$pos['activeStaffTrend']}% vs. the prior {$days} days",
                ],
                [
                    'key' => 'newHires',
                    'label' => 'New hires',
                    'value' => $pos['newHires'],
                    'format' => 'count',
                    'hint' => "Last {$days} days · {$pos['newHiresTrend']}% vs. the prior {$days} days",
                ],
                [
                    'key' => 'attritionCount',
                    'label' => 'Exits',
                    'value' => $pos['attritionCount'],
                    'format' => 'count',
                    'tone' => $pos['attritionTrend'] > 0 ? 'warning' : 'positive',
                    'hint' => "Last {$days} days · {$pos['attritionTrend']}% vs. the prior {$days} days",
                ],
                [
                    'key' => 'attritionRate',
                    'label' => 'Attrition rate',
                    'value' => $pos['attritionRate'],
                    'format' => 'percent',
                    'tone' => $pos['attritionRate'] !== null && $pos['attritionRate'] >= 5.0 ? 'warning' : 'positive',
                    'hint' => 'Exits in the last '.$days.' days over active staff',
                ],
                [
                    'key' => 'netGrowth',
                    'label' => 'Net headcount change',
                    'value' => $pos['netGrowth'],
                    'format' => 'count',
                    'tone' => $pos['netGrowth'] >= 0 ? 'positive' : 'warning',
                    'hint' => "New hires less exits, last {$days} days",
                ],
                [
                    'key' => 'growthRate',
                    'label' => 'Growth rate',
                    'value' => $pos['growthRate'],
                    'format' => 'percent',
                    'hint' => "Net headcount change over active staff, last {$days} days",
                ],
                [
                    'key' => 'departments',
                    'label' => 'Departments',
                    'value' => $pos['departments'],
                    'format' => 'count',
                    'hint' => "{$pos['departmentsWithStaff']} hold at least one active member of staff",
                ],
                [
                    'key' => 'avgSkillCoverage',
                    'label' => 'Skill coverage',
                    'value' => $pos['avgSkillCoverage'],
                    'format' => 'percent',
                    'hint' => $pos['requiredSkills'] !== null
                        ? "{$pos['coveredSkills']} of {$pos['requiredSkills']} required skills held by someone active"
                        : 'No skill-requirement register on file for this institute',
                ],
                [
                    'key' => 'avgProficiency',
                    'label' => 'Average proficiency',
                    'value' => $pos['avgProficiency'],
                    'format' => 'decimal',
                    'hint' => 'Average self/manager-rated skill level across the skill matrix',
                ],
            ],
        ];
    }

    private function breakdowns(OrganizationIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byDepartment = $analytics->byDepartment();
        $skillCoverage = $analytics->skillCoverageByDepartment();
        $stalled = $analytics->stalledPostings();

        return [
            [
                'key' => 'departments',
                'label' => 'By department',
                'description' => 'Active headcount and lifetime attrition rate per department, largest active '
                    .'headcount first.',
                'available' => $byDepartment !== [],
                'reason' => $byDepartment === [] ? 'No department master is on file for this institute.' : null,
                'primaryColumn' => 'activeStaff',
                'columns' => [
                    ['key' => 'activeStaff', 'label' => 'Active staff', 'format' => 'count'],
                    ['key' => 'allStaff', 'label' => 'All staff records', 'format' => 'count'],
                    ['key' => 'exits', 'label' => 'Exits (all time)', 'format' => 'count'],
                    ['key' => 'attritionRate', 'label' => 'Attrition rate', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['activeStaff'] === 0 ? 'No active staff' : null,
                    'values' => [
                        'activeStaff' => $row['activeStaff'],
                        'allStaff' => $row['allStaff'],
                        'exits' => $row['exits'],
                        'attritionRate' => $row['attritionRate'],
                    ],
                ], $byDepartment),
            ],
            [
                'key' => 'skill_coverage',
                'label' => 'Skill coverage by department',
                'description' => 'Of the skills marked required for the department, how many are held by at least '
                    .'one of its active staff. Departments with no required-skill rows on file are omitted — a '
                    .'coverage figure over zero requirements is undefined.',
                'available' => $skillCoverage !== [],
                'reason' => $skillCoverage === []
                    ? 'No department has both a required-skill row and a skill-matrix entry to compare it against.'
                    : null,
                'primaryColumn' => 'coverage',
                'columns' => [
                    ['key' => 'coverage', 'label' => 'Coverage', 'format' => 'percent'],
                    ['key' => 'coveredSkills', 'label' => 'Skills covered', 'format' => 'count'],
                    ['key' => 'requiredSkills', 'label' => 'Skills required', 'format' => 'count'],
                    ['key' => 'headcount', 'label' => 'Active staff', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['headcount'] < OrganizationIntelligence::MIN_DEPARTMENT_COHORT
                        ? 'Too few staff to compare against the institute'
                        : null,
                    'values' => [
                        'coverage' => $row['coverage'],
                        'coveredSkills' => $row['coveredSkills'],
                        'requiredSkills' => $row['requiredSkills'],
                        'headcount' => $row['headcount'],
                    ],
                ], $skillCoverage),
            ],
            [
                'key' => 'stalled_postings',
                'label' => 'Open postings past deadline',
                'description' => 'Job postings still open whose deadline has passed with no application marked '
                    .'hired against them, longest overdue first.',
                'available' => $stalled !== [],
                'reason' => $stalled === [] ? 'No open posting is past its deadline without a recorded hire.' : null,
                'primaryColumn' => 'daysOverdue',
                'columns' => [
                    ['key' => 'daysOverdue', 'label' => 'Days overdue', 'format' => 'count'],
                    ['key' => 'positions', 'label' => 'Positions', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => "{$row['title']} — {$row['departmentLabel']}",
                    'note' => 'Deadline '.$row['deadline'],
                    'values' => [
                        'daysOverdue' => $row['daysOverdue'],
                        'positions' => $row['positions'],
                    ],
                ], $stalled),
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
     * IDEMPOTENT — see {@see BrainAttendanceIntelligenceController::run()}.
     * Runs the whole pipeline rather than this module alone; wiring this
     * module's rule keys into the pipeline itself happens centrally, once the
     * module is registered.
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
