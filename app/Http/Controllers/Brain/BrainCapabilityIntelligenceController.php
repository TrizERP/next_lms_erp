<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\CapabilityIntelligence;
use App\Brain\Intelligence\CapabilitySignalRules;
use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Capability Intelligence — skills, competencies and job-role mapping.
 *
 * Mirrors {@see BrainAttendanceIntelligenceController}'s shape exactly.
 * `'capability'` is not yet a key in `ModuleSignalBridge::MODULES`, so `run()`
 * below still drives the whole pipeline (the same call every module's `run()`
 * makes), but it will not write Capability signals to the ledger, and
 * `ModuleLoop`'s recommendations/decision-trail/learning sections will read
 * as empty for this module, until that registration is added centrally. The
 * live figures in `index()` — coverage, position, breakdowns, findings — do
 * not depend on that registration and are correct today.
 */
class BrainCapabilityIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'capability', 'job roles');

        $analytics = new CapabilityIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new CapabilitySignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'vivek_erp · s_user_jobrole, s_user_skill_jobrole, s_users_skills, s_competency_frameworks, '
                .'s_competency_certifications, s_competency_development_plans',
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
                'note' => 'None of the underlying tables carry an academic year, so every figure here is scoped by '
                    .'institute alone and is not comparable across years. The skill-mapping join reads the row that '
                    .'actually holds a job-role name at this institute, not the column named for it — see the class '
                    .'doc on CapabilityIntelligence.',
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
                'reason' => $coverage['reason'] ?? 'No capability data has been recorded for this institute.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        $sentences[] = "{$pos['jobRoles']} job roles are defined across {$pos['departments']} departments, of which "
            ."{$pos['jobRolesWithSkillMapping']} ({$pos['jobRoleSkillCoverage']}%) have at least one skill mapped to "
            .'them.';

        $sentences[] = "{$pos['competencies']} competencies are published, against {$pos['activeFrameworks']} active "
            ."framework".($pos['activeFrameworks'] === 1 ? '' : 's').".";

        $sentences[] = "{$pos['certifications']} certifications are currently valid and {$pos['developmentPlans']} "
            .'development plans are active.';

        $count = count($findings);
        $sentences[] = match ($count) {
            0 => 'No check found enough evidence to raise a finding.',
            1 => 'One finding below, with the figures it rests on.',
            default => "{$count} findings below, each with the figures it rests on.",
        };

        return [
            'available' => true,
            'reason' => null,
            'headline' => "{$pos['jobRoleSkillCoverage']}% of job roles have a skill mapped, across {$pos['jobRoles']} roles",
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
                    'key' => 'jobRoleSkillCoverage',
                    'label' => 'Job roles with a skill mapped',
                    'value' => $pos['jobRoleSkillCoverage'],
                    'format' => 'percent',
                    'hint' => "{$pos['jobRolesWithSkillMapping']} of {$pos['jobRoles']} roles",
                ],
                [
                    'key' => 'jobRoles',
                    'label' => 'Job roles',
                    'value' => $pos['jobRoles'],
                    'format' => 'count',
                    'hint' => "Across {$pos['departments']} departments",
                ],
                [
                    'key' => 'jobRolesWithoutSkillMapping',
                    'label' => 'Roles with no skill mapped',
                    'value' => $pos['jobRolesWithoutSkillMapping'],
                    'format' => 'count',
                    'tone' => $pos['jobRolesWithoutSkillMapping'] > 0 ? 'warning' : 'positive',
                ],
                [
                    'key' => 'competencies',
                    'label' => 'Total Competencies',
                    'value' => $pos['competencies'],
                    'format' => 'count',
                    'hint' => 'Published',
                ],
                [
                    'key' => 'activeFrameworks',
                    'label' => 'Active Frameworks',
                    'value' => $pos['activeFrameworks'],
                    'format' => 'count',
                    'hint' => "of {$pos['totalFrameworks']} on file",
                ],
                [
                    'key' => 'certifications',
                    'label' => 'Certifications',
                    'value' => $pos['certifications'],
                    'format' => 'count',
                    'hint' => "Valid, of {$pos['certificationsTotal']} on file",
                ],
                [
                    'key' => 'developmentPlans',
                    'label' => 'Development Plans',
                    'value' => $pos['developmentPlans'],
                    'format' => 'count',
                    'hint' => "Active, of {$pos['developmentPlansTotal']} on file",
                ],
            ],
        ];
    }

    private function breakdowns(CapabilityIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byDepartment = $analytics->byDepartment();

        return [
            [
                'key' => 'departments',
                'label' => 'By department',
                'description' => 'Weakest skill-mapping coverage first. Departments below '
                    .CapabilityIntelligence::MIN_DEPARTMENT_COHORT
                    .' job roles are shown here but are never compared against the institute in a finding.',
                'available' => $byDepartment !== [],
                'reason' => $byDepartment === [] ? 'No job role this institute carries a department.' : null,
                'primaryColumn' => 'skillCoverage',
                'columns' => [
                    ['key' => 'skillCoverage', 'label' => 'Skill coverage', 'format' => 'percent'],
                    ['key' => 'jobRoles', 'label' => 'Job roles', 'format' => 'count'],
                    ['key' => 'jobRolesWithSkillMapping', 'label' => 'Mapped', 'format' => 'count'],
                    ['key' => 'jobRolesWithoutSkillMapping', 'label' => 'Unmapped', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['jobRoles'] < CapabilityIntelligence::MIN_DEPARTMENT_COHORT
                        ? 'Too few roles to compare against the institute'
                        : null,
                    'values' => [
                        'skillCoverage' => $row['skillCoverage'],
                        'jobRoles' => $row['jobRoles'],
                        'jobRolesWithSkillMapping' => $row['jobRolesWithSkillMapping'],
                        'jobRolesWithoutSkillMapping' => $row['jobRolesWithoutSkillMapping'],
                    ],
                ], $byDepartment),
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
     * recommendation -> decision -> execution -> outcome -> learning loop.
     *
     * Same shape as every other module's `run()`. Until `'capability'` is
     * registered in `ModuleSignalBridge::MODULES`, this call runs the
     * pipeline for every module that IS registered and returns its totals —
     * it does not yet persist Capability's own signals. That registration is
     * one of the central wiring steps this controller intentionally leaves
     * untouched.
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
