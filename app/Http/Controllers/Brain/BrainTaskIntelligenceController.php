<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use App\Brain\Intelligence\ModuleLoop;
use App\Brain\Intelligence\ModulePayload;
use App\Brain\Intelligence\TaskIntelligence;
use App\Brain\Intelligence\TaskSignalRules;
use App\Brain\Support\AcademicYear;
use App\Brain\Support\LmsOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrainTaskIntelligenceController extends Controller
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
        $loop = new ModuleLoop($this->tenantId, $this->syear, 'tasks', 'tasks');

        $analytics = new TaskIntelligence($this->tenantId, $this->syear);
        $coverage = $analytics->coverage();

        $rules = new TaskSignalRules($analytics, $this->syear);
        $raised = $coverage['available'] ? $rules->run() : ['findings' => [], 'ruleStatus' => []];

        $pos = $analytics->position();

        return response()->json(ModulePayload::normalize([
            'tenantId' => $this->tenantId,
            'organization' => LmsOrganization::displayNameFor($this->tenantId, $this->actorId, $this->actorIsStudent),
            'source' => 'task, task_management_project_tasks',
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
                'note' => 'This table has no completed_at column; average cycle time uses updated_at as a stand-in '
                    .'for when a task was last moved, the same proxy WorkspaceController already reports "completed '
                    .'this month" from — see dataQuality for how many completed tasks it could not be computed for.',
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
                'reason' => $coverage['reason'] ?? 'No task records were found for this academic year.',
                'headline' => null,
                'sentences' => [],
            ];
        }

        $sentences = [];

        $sentences[] = "{$pos['total']} tasks are on record for this year across {$pos['assignees']} assignees, "
            ."{$pos['completed']} of them ({$pos['completionRate']}%) marked COMPLETED.";

        $sentences[] = $pos['openTasks'] > 0
            ? "{$pos['openTasks']} tasks are still open — {$pos['pending']} pending, {$pos['inProgress']} in "
                ."progress, {$pos['onHold']} on hold."
            : 'No task is currently open; every task on record has been completed.';

        if ($pos['overdueTasks'] > 0) {
            $sentences[] = "{$pos['overdueTasks']} of the open tasks ({$pos['overdueShareOfOpen']}%) are overdue"
                .($pos['avgOverdueDays'] !== null ? ", averaging {$pos['avgOverdueDays']} days past their due date." : '.');
        } else {
            $sentences[] = 'No open task is past its due date.';
        }

        if ($pos['avgCycleTimeDays'] !== null) {
            $sentences[] = "Completed tasks with a usable timestamp took {$pos['avgCycleTimeDays']} days on average "
                .'from creation to completion (an estimate — see the execution note above).';
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
            'headline' => "{$pos['completionRate']}% completion across {$pos['total']} tasks",
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
                    'key' => 'total',
                    'label' => 'Tasks',
                    'value' => $pos['total'],
                    'format' => 'count',
                    'hint' => "Across {$pos['assignees']} assignees",
                ],
                [
                    'key' => 'completionRate',
                    'label' => 'Completion rate',
                    'value' => $pos['completionRate'],
                    'format' => 'percent',
                    'hint' => "{$pos['completed']} of {$pos['total']} tasks marked COMPLETED",
                ],
                [
                    'key' => 'openTasks',
                    'label' => 'Open tasks',
                    'value' => $pos['openTasks'],
                    'format' => 'count',
                    'hint' => "{$pos['pending']} pending · {$pos['inProgress']} in progress · {$pos['onHold']} on hold",
                ],
                [
                    'key' => 'overdueTasks',
                    'label' => 'Overdue',
                    'value' => $pos['overdueTasks'],
                    'format' => 'count',
                    'tone' => $pos['overdueTasks'] > 0 ? 'warning' : 'positive',
                    'hint' => $pos['overdueShareOfOpen'] !== null
                        ? "{$pos['overdueShareOfOpen']}% of open tasks"
                        : 'No open tasks to measure against',
                ],
                [
                    'key' => 'avgOverdueDays',
                    'label' => 'Average days overdue',
                    'value' => $pos['avgOverdueDays'],
                    'format' => 'decimal',
                    'hint' => 'Across tasks currently past their due date',
                ],
                [
                    'key' => 'avgOpenTaskAgeDays',
                    'label' => 'Average age of open tasks',
                    'value' => $pos['avgOpenTaskAgeDays'],
                    'format' => 'decimal',
                    'hint' => 'Days since created, for tasks not yet COMPLETED',
                ],
                [
                    'key' => 'avgCycleTimeDays',
                    'label' => 'Average cycle time',
                    'value' => $pos['avgCycleTimeDays'],
                    'format' => 'decimal',
                    'hint' => 'Created to completed, estimated from updated_at — see the execution note',
                ],
                [
                    'key' => 'onHold',
                    'label' => 'On hold',
                    'value' => $pos['onHold'],
                    'format' => 'count',
                    'tone' => $pos['onHold'] > 0 ? 'warning' : 'positive',
                ],
            ],
        ];
    }

    private function breakdowns(TaskIntelligence $analytics, array $coverage): array
    {
        if (! $coverage['available']) {
            return [];
        }

        $byAssignee = $analytics->byAssignee();
        $byStatus = $analytics->byStatus();
        $byPriority = $analytics->byPriority();
        $byDelayCategory = $analytics->byDelayCategory();

        return [
            [
                'key' => 'assignees',
                'label' => 'By assignee',
                'description' => 'Heaviest workload first, including "Unassigned" as its own row where tasks carry '
                    .'no TASK_ALLOCATED_TO.',
                'available' => $byAssignee !== [],
                'reason' => $byAssignee === [] ? 'No task this year carries an assignee group.' : null,
                'primaryColumn' => 'total',
                'columns' => [
                    ['key' => 'total', 'label' => 'Tasks', 'format' => 'count'],
                    ['key' => 'completionRate', 'label' => 'Completion', 'format' => 'percent'],
                    ['key' => 'overdue', 'label' => 'Overdue', 'format' => 'count'],
                    ['key' => 'shareOfTenantVolume', 'label' => 'Share of all tasks', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'note' => $row['total'] < TaskIntelligence::MIN_COHORT ? 'Too few tasks to compare' : null,
                    'values' => [
                        'total' => $row['total'],
                        'completionRate' => $row['completionRate'],
                        'overdue' => $row['overdue'],
                        'shareOfTenantVolume' => $row['shareOfTenantVolume'],
                    ],
                ], $byAssignee),
            ],
            [
                'key' => 'status',
                'label' => 'By status',
                'description' => 'How tasks this year are distributed across the four system status categories.',
                'available' => $byStatus !== [],
                'reason' => null,
                'primaryColumn' => 'tasks',
                'columns' => [
                    ['key' => 'tasks', 'label' => 'Tasks', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['tasks' => $row['tasks'], 'share' => $row['share']],
                ], $byStatus),
            ],
            [
                'key' => 'priority',
                'label' => 'By priority',
                'description' => 'Grouped on whatever task_type actually holds for this tenant — the system '
                    .'priorities plus any custom name the tenant has added.',
                'available' => $byPriority !== [],
                'reason' => $byPriority === [] ? 'No task this year carries a priority.' : null,
                'primaryColumn' => 'tasks',
                'columns' => [
                    ['key' => 'tasks', 'label' => 'Tasks', 'format' => 'count'],
                    ['key' => 'share', 'label' => 'Share', 'format' => 'percent'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['tasks' => $row['tasks'], 'share' => $row['share']],
                ], $byPriority),
            ],
            [
                'key' => 'delayCategory',
                'label' => 'On hold, by reason',
                'description' => 'Why work currently on hold stalled, where delay_category was recorded.',
                'available' => $byDelayCategory !== [],
                'reason' => $byDelayCategory === [] ? 'No task this year is currently ON HOLD.' : null,
                'primaryColumn' => 'tasks',
                'columns' => [
                    ['key' => 'tasks', 'label' => 'Tasks', 'format' => 'count'],
                ],
                'rows' => array_map(fn ($row) => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'values' => ['tasks' => $row['tasks']],
                ], $byDelayCategory),
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
