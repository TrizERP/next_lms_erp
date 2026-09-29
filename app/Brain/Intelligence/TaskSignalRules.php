<?php

namespace App\Brain\Intelligence;

/**
 * What the task figures mean, and what is worth someone's morning.
 *
 * Four checks, each gated on a cohort large enough to be a pattern rather than
 * a handful of rows — see {@see TaskIntelligence::MIN_COHORT}. Nothing here
 * names an individual by their overdue count; the workload-concentration rule
 * is the one exception, and it names an assignee only because carrying an
 * outsized share of the tenant's task volume is precisely the fact the rule
 * exists to report.
 */
final class TaskSignalRules
{
    /** Share of ON HOLD tasks in the total before stalled work is worth a finding. */
    private const ON_HOLD_ALERT_SHARE = 15.0;

    /** Share of all tasks with no assignee before it is a triage gap rather than a handful of drafts. */
    private const UNASSIGNED_ALERT_SHARE = 15.0;

    /** Delay categories named inside one finding's evidence before it stops being readable. */
    private const MAX_NAMED_IN_EVIDENCE = 5;

    public function __construct(
        private readonly TaskIntelligence $analytics,
        private readonly ?string $syear,
    ) {
    }

    /** @return array{findings:array<int,array<string,mixed>>,ruleStatus:array<int,array<string,mixed>>} */
    public function run(): array
    {
        if (! $this->analytics->coverage()['available']) {
            return ['findings' => [], 'ruleStatus' => []];
        }

        $rules = [
            'overdue_backlog' => [
                'Open tasks overdue against their due date',
                fn () => $this->overdueBacklog(),
            ],
            'workload_concentration' => [
                'One assignee carrying a disproportionate share of the workload',
                fn () => $this->workloadConcentration(),
            ],
            'stalled_on_hold' => [
                'Tasks stalled on hold',
                fn () => $this->stalledOnHold(),
            ],
            'unassigned_backlog' => [
                'Tasks with nobody assigned to them',
                fn () => $this->unassignedBacklog(),
            ],
        ];

        $findings = [];
        $ruleStatus = [];

        foreach ($rules as $key => [$label, $check]) {
            $raised = $check();
            $ruleStatus[] = [
                'key' => $key,
                'label' => $label,
                'checked' => true,
                'raised' => $raised !== [],
            ];
            foreach ($raised as $finding) {
                $findings[] = $finding;
            }
        }

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];
        usort($findings, function ($a, $b) use ($rank) {
            $bySeverity = ($rank[$a['severity']] ?? 5) <=> ($rank[$b['severity']] ?? 5);

            return $bySeverity !== 0
                ? $bySeverity
                : ($b['affected']['count'] ?? 0) <=> ($a['affected']['count'] ?? 0);
        });

        return ['findings' => $findings, 'ruleStatus' => $ruleStatus];
    }

    /* ------------------------------------------------------------ overdue */

    /** @return array<int,array<string,mixed>> */
    private function overdueBacklog(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['openTasks'] < TaskIntelligence::MIN_COHORT) {
            return [];
        }

        $share = $position['overdueShareOfOpen'];
        if ($share === null || $share < TaskIntelligence::OVERDUE_ALERT_SHARE) {
            return [];
        }

        $severe = $share >= (TaskIntelligence::OVERDUE_ALERT_SHARE * 2);

        return [[
            'id' => "task-overdue-backlog-{$this->syear}",
            'rule' => 'overdue_backlog',
            'severity' => $severe ? 'high' : 'medium',
            'severityLabel' => $severe ? 'High' : 'Medium',
            'title' => "{$position['overdueTasks']} open tasks are overdue ({$share}% of open work)",
            'whatHappened' => $this->sentence([
                "{$position['overdueTasks']} of the {$position['openTasks']} tasks that are not yet COMPLETED have a "
                    ."due date that has already passed — {$share}% of everything still open.",
                $position['avgOverdueDays'] !== null
                    ? "On average they are {$position['avgOverdueDays']} days past their due date."
                    : null,
            ]),
            'whyItMatters' => 'An overdue task does not resolve itself by staying open — every day it sits past its '
                .'due date is a day the plan behind it was wrong, and the next task queued behind it inherits the '
                .'delay.',
            'evidence' => array_filter([
                ['label' => 'Overdue', 'value' => (string) $position['overdueTasks']],
                ['label' => 'Open tasks', 'value' => (string) $position['openTasks']],
                ['label' => 'Share overdue', 'value' => "{$share}%"],
                $position['avgOverdueDays'] !== null
                    ? ['label' => 'Average days overdue', 'value' => "{$position['avgOverdueDays']}"]
                    : null,
            ]),
            'likelyCause' => 'A due date set without the effort behind it, a dependency that slipped, or work that '
                .'was simply never picked up. This figure shows the backlog, not which of those caused it.',
            'causeConfirmed' => false,
            'recommendation' => 'Triage the overdue list by assignee and due date before adding new work to anyone '
                .'who is already carrying it — see the workload breakdown below.',
            'owner' => 'Project / task owner',
            'priority' => $severe ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $position['overdueTasks'], 'total' => $position['openTasks'], 'unit' => 'tasks'],
            'impact' => [
                'value' => $position['overdueTasks'],
                'display' => (string) $position['overdueTasks'],
                'label' => 'overdue tasks',
            ],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ workload */

    /** @return array<int,array<string,mixed>> */
    private function workloadConcentration(): array
    {
        $assignees = array_values(array_filter($this->analytics->byAssignee(), fn ($a) => ! $a['unassigned']));
        if ($assignees === []) {
            return [];
        }

        $assignedTotal = array_sum(array_column($assignees, 'total'));
        if ($assignedTotal < TaskIntelligence::MIN_COHORT) {
            return [];
        }

        usort($assignees, static fn ($a, $b) => $b['total'] <=> $a['total']);
        $top = $assignees[0];

        if ($top['total'] < TaskIntelligence::MIN_COHORT) {
            return [];
        }

        $share = round($top['total'] / $assignedTotal * 100, 1);
        if ($share < TaskIntelligence::WORKLOAD_CONCENTRATION_SHARE) {
            return [];
        }

        $second = $assignees[1]['total'] ?? 0;

        return [[
            'id' => "task-workload-concentration-{$this->syear}",
            'rule' => 'workload_concentration',
            'severity' => $share >= 60.0 ? 'high' : 'medium',
            'severityLabel' => $share >= 60.0 ? 'High' : 'Medium',
            'title' => "{$top['label']} is carrying {$top['total']} of {$assignedTotal} assigned tasks ({$share}%)",
            'whatHappened' => $this->sentence([
                "{$top['label']} holds {$top['total']} tasks against ".count($assignees).' assignees sharing '
                    ."{$assignedTotal} assigned tasks this year — {$share}% of all assigned work.",
                $second > 0
                    ? "The next-busiest assignee holds {$second}."
                    : 'No other assignee holds a comparable number.',
                $top['overdue'] > 0
                    ? "{$top['overdue']} of {$top['label']}'s tasks are already overdue."
                    : null,
            ]),
            'whyItMatters' => 'Work concentrated this heavily on one person is a single point of failure for every '
                .'task queued behind it — their leave, their illness or their next reassignment moves the whole '
                .'backlog at once.',
            'evidence' => array_filter([
                ['label' => $top['label'], 'value' => "{$top['total']} tasks", 'note' => "{$share}% of assigned work"],
                ['label' => 'Assigned tasks tenant-wide', 'value' => (string) $assignedTotal],
                ['label' => 'Assignees', 'value' => (string) count($assignees)],
                $top['overdue'] > 0 ? ['label' => "{$top['label']}'s overdue tasks", 'value' => (string) $top['overdue']] : null,
            ]),
            'likelyCause' => 'A single specialist skill, a team that has not backfilled a role, or work simply routed '
                .'to whoever was available first. This figure shows the concentration, not which of those caused it.',
            'causeConfirmed' => false,
            'recommendation' => 'Review whether any of these tasks can be reassigned before the next planning cycle '
                .'adds more to the same person.',
            'owner' => 'Team lead / department head',
            'priority' => $share >= 60.0 ? 'high' : 'medium',
            'confidence' => ['band' => count($assignees) >= 5 ? 'High' : 'Medium', 'value' => count($assignees) >= 5 ? 0.85 : 0.65],
            'affected' => ['count' => $top['total'], 'total' => $assignedTotal, 'unit' => 'tasks'],
            'impact' => ['value' => $share, 'display' => "{$share}%", 'label' => 'of assigned work on one person'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* -------------------------------------------------------- stalled work */

    /** @return array<int,array<string,mixed>> */
    private function stalledOnHold(): array
    {
        $position = $this->analytics->position();
        if ($position === null || $position['total'] < TaskIntelligence::MIN_COHORT) {
            return [];
        }

        $onHold = $position['onHold'];
        if ($onHold < TaskIntelligence::MIN_COHORT) {
            return [];
        }

        $share = round($onHold / $position['total'] * 100, 1);
        if ($share < self::ON_HOLD_ALERT_SHARE) {
            return [];
        }

        $categories = $this->analytics->byDelayCategory();

        return [[
            'id' => "task-stalled-on-hold-{$this->syear}",
            'rule' => 'stalled_on_hold',
            'severity' => $share >= (self::ON_HOLD_ALERT_SHARE * 2) ? 'high' : 'medium',
            'severityLabel' => $share >= (self::ON_HOLD_ALERT_SHARE * 2) ? 'High' : 'Medium',
            'title' => "{$onHold} tasks ({$share}%) are ON HOLD",
            'whatHappened' => $this->sentence([
                "{$onHold} of {$position['total']} tasks this year are ON HOLD — {$share}% of everything raised.",
                $categories !== []
                    ? 'The largest reason recorded is '.$categories[0]['label']." ({$categories[0]['tasks']} tasks)."
                    : 'None of them carries a delay_category, so the reason for the hold is not on file.',
            ]),
            'whyItMatters' => 'A task on hold is neither progressing nor abandoned — it occupies a slot in the plan '
                .'without moving it forward, and it is invisible to a view that only looks at overdue or completed work.',
            'evidence' => array_merge(
                [
                    ['label' => 'On hold', 'value' => (string) $onHold],
                    ['label' => 'Total tasks', 'value' => (string) $position['total']],
                    ['label' => 'Share', 'value' => "{$share}%"],
                ],
                array_map(static fn ($c) => [
                    'label' => $c['label'],
                    'value' => "{$c['tasks']} tasks",
                ], array_slice($categories, 0, self::MAX_NAMED_IN_EVIDENCE)),
            ),
            'likelyCause' => 'A dependency that has not cleared, a resourcing gap, or a scope question waiting on a '
                .'decision. The delay_category column names the reason where it has been recorded.',
            'causeConfirmed' => false,
            'recommendation' => 'Work through the on-hold list by delay category, starting with the largest, rather '
                .'than by how long each task has been open.',
            'owner' => 'Project / task owner',
            'priority' => $share >= (self::ON_HOLD_ALERT_SHARE * 2) ? 'high' : 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.85],
            'affected' => ['count' => $onHold, 'total' => $position['total'], 'unit' => 'tasks'],
            'impact' => ['value' => $onHold, 'display' => (string) $onHold, 'label' => 'tasks stalled on hold'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* --------------------------------------------------------- unassigned */

    /** @return array<int,array<string,mixed>> */
    private function unassignedBacklog(): array
    {
        $unassigned = array_values(array_filter($this->analytics->byAssignee(), fn ($a) => $a['unassigned']));
        $position = $this->analytics->position();
        if ($position === null || $unassigned === []) {
            return [];
        }

        $count = $unassigned[0]['total'];
        if ($count < TaskIntelligence::MIN_COHORT) {
            return [];
        }

        $share = round($count / $position['total'] * 100, 1);
        if ($share < self::UNASSIGNED_ALERT_SHARE) {
            return [];
        }

        return [[
            'id' => "task-unassigned-backlog-{$this->syear}",
            'rule' => 'unassigned_backlog',
            'severity' => 'medium',
            'severityLabel' => 'Medium',
            'title' => "{$count} tasks ({$share}%) have nobody assigned",
            'whatHappened' => $this->sentence([
                "{$count} of {$position['total']} tasks this year carry no TASK_ALLOCATED_TO — {$share}% of "
                    .'everything raised.',
                $unassigned[0]['overdue'] > 0
                    ? "{$unassigned[0]['overdue']} of them are already overdue with nobody accountable for them."
                    : null,
            ]),
            'whyItMatters' => 'A task with no assignee will not surface in anyone\'s workload, in the productivity '
                .'report, or in the workload breakdown above — it is only visible to someone looking at the whole list.',
            'evidence' => array_filter([
                ['label' => 'Unassigned', 'value' => (string) $count],
                ['label' => 'Total tasks', 'value' => (string) $position['total']],
                ['label' => 'Share', 'value' => "{$share}%"],
                $unassigned[0]['overdue'] > 0
                    ? ['label' => 'Unassigned and overdue', 'value' => (string) $unassigned[0]['overdue']]
                    : null,
            ]),
            'likelyCause' => 'Tasks created ahead of a handover, imported in bulk without an owner, or dropped by an '
                .'assignee who was reassigned elsewhere. This figure shows the gap, not which of those caused it.',
            'causeConfirmed' => false,
            'recommendation' => 'Assign an owner to each of these tasks, starting with any that are already overdue.',
            'owner' => 'Project / task owner',
            'priority' => 'medium',
            'confidence' => ['band' => 'High', 'value' => 0.9],
            'affected' => ['count' => $count, 'total' => $position['total'], 'unit' => 'tasks'],
            'impact' => ['value' => $count, 'display' => (string) $count, 'label' => 'tasks with no owner'],
            'status' => 'open',
            'syear' => $this->syear,
        ]];
    }

    /* ------------------------------------------------------------ helpers */

    /** @param array<int,?string> $parts */
    private function sentence(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== ''));
    }
}
