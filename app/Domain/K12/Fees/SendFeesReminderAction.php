<?php

namespace App\Domain\K12\Fees;

use App\Domain\Workflow\Actions\WorkflowAction;
use App\Domain\Workflow\WorkflowRunContext;
use App\Services\Mcp\FeesReminderService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Sends the real fee reminder — the moment this workflow actually does something.
 *
 * Reached only through an `action` step, which the workflow engine will not run
 * without an approving human decision on the recommendation (`GovernanceValidator`).
 * This is what makes Fees Recommendation's approval different from Fees Intelligence's:
 * that one only records the decision for a person to act on later: this one acts.
 *
 * A student with no outstanding balance, no usable contact, or a channel that is not
 * configured for this institute is not an error — the approval is still honored and
 * the recommendation still completes; there is just nothing to send.
 */
class SendFeesReminderAction implements WorkflowAction
{
    public function __construct(private readonly FeesReminderService $reminders)
    {
    }

    public function key(): string
    {
        return 'send_fees_reminder';
    }

    public function label(): string
    {
        return 'Send fee reminder notice';
    }

    public function execute(
        array $config,
        array $state,
        WorkflowRunContext $run,
        McpRequestContext $scope
    ): array {
        // Re-running the same approved recommendation (a retry, a resumed run) must
        // not send a second reminder to the same family.
        if ($run->recommendationId !== null && $this->alreadyExecuted($run, $scope)) {
            return ['already_sent' => true, 'student_id' => $this->studentId($run, $state)];
        }

        $studentId = $this->studentId($run, $state);

        if ($studentId <= 0) {
            throw new RuntimeException('This reminder has no student.');
        }

        $arguments = [
            'student_ids' => [$studentId],
            'channel' => (string) ($config['channel'] ?? 'email'),
            'deadline' => now()->addDays(max(1, (int) ($config['deadline_in_days'] ?? 7)))->toDateString(),
        ];

        $preview = $this->reminders->preview($scope, $arguments);

        $outcome = ($preview['success'] ?? false)
            ? $this->reminders->send($scope, $arguments, $preview['data']['fingerprint'] ?? null)
            : $preview;

        $this->markExecuted($run, $scope);

        return [
            'student_id' => $studentId,
            'sent' => (bool) ($outcome['success'] ?? false),
            'reminder_result' => $outcome['data'] ?? $outcome['message'] ?? null,
        ];
    }

    /**
     * The workflow payload the recommendation carried, merged with anything earlier
     * steps added to the run state.
     */
    private function studentId(WorkflowRunContext $run, array $state): int
    {
        $payload = $run->input;

        if (isset($state['input']) && is_array($state['input'])) {
            $payload = array_merge($state['input'], $payload);
        }

        return (int) ($payload['student_id'] ?? 0);
    }

    private function alreadyExecuted(WorkflowRunContext $run, McpRequestContext $scope): bool
    {
        if (! Schema::hasTable('ai_recommendations')) {
            return false;
        }

        $status = DB::table('ai_recommendations')
            ->where('id', $run->recommendationId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->value('status');

        return $status === 'executed';
    }

    private function markExecuted(WorkflowRunContext $run, McpRequestContext $scope): void
    {
        if ($run->recommendationId === null || ! Schema::hasTable('ai_recommendations')) {
            return;
        }

        DB::table('ai_recommendations')
            ->where('id', $run->recommendationId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->update(['status' => 'executed', 'updated_at' => now()]);
    }
}
