<?php

namespace App\Domain\Workflow\Steps;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\Workflow\StepHandler;
use App\Domain\Workflow\StepResult;
use App\Domain\Workflow\WorkflowRunContext;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifies a person that something needs their attention.
 *
 * Consequential when the channel reaches outside the platform. Telling a teacher
 * in-app that a case is waiting is housekeeping; messaging a parent is an outward
 * communication that cannot be taken back, so it sits behind the approval gate like
 * any other consequential act.
 */
class NotifyStepHandler implements StepHandler
{
    /** Channels that leave the building. */
    private const EXTERNAL_CHANNELS = ['sms', 'email', 'whatsapp', 'push'];

    public function __construct(private readonly AiAuditLogger $audit)
    {
    }

    public function type(): string
    {
        return 'notify';
    }

    public function isConsequential(array $config): bool
    {
        $channel = strtolower((string) ($config['channel'] ?? 'in_app'));

        return in_array($channel, self::EXTERNAL_CHANNELS, true);
    }

    public function handle(
        array $config,
        array $state,
        WorkflowRunContext $run,
        McpRequestContext $scope
    ): StepResult {
        $channel = strtolower((string) ($config['channel'] ?? 'in_app'));
        $recipientId = $this->resolveRecipient($config, $state);
        $message = $this->renderMessage($config, $state);

        if ($message === '') {
            return StepResult::failed('This notify step has no message.');
        }

        // The existing `app_notification` table is student-addressed (STUDENT_ID,
        // uppercase columns), so it is used only when the recipient really is a
        // student. Staff are not notified through it: a teacher's queue is the list
        // of recommendations pending their approval, which they already see. Writing
        // a staff row into a student table would be worse than not writing one.
        $notificationId = null;
        $audience = strtolower((string) ($config['audience'] ?? 'staff'));

        if ($channel === 'in_app'
            && $audience === 'student'
            && $recipientId !== null
            && Schema::hasTable('app_notification')) {
            $notificationId = (int) DB::table('app_notification')->insertGetId([
                'NOTIFICATION_TYPE' => mb_substr((string) ($config['notification_type'] ?? 'AI_INSIGHT'), 0, 50),
                'NOTIFICATION_DATE' => now()->toDateString(),
                'STUDENT_ID' => $recipientId,
                'NOTIFICATION_DESCRIPTION' => $message,
                'STATUS' => 1,
                'SUB_INSTITUTE_ID' => $scope->selectedInstituteId,
                'SYEAR' => $scope->academicYear,
                'SCREEN_NAME' => $config['screen_name'] ?? null,
                'CREATED_AT' => now(),
                'CREATED_BY' => $scope->userId,
            ]);
        }

        $this->audit->record(AiAuditLogger::WORKFLOW_TRANSITION, $scope, [
            'related_type' => 'workflow_runs',
            'related_id' => $run->runId,
            'subject_entity_key' => $run->subjectEntityKey,
            'subject_id' => $run->subjectId,
            'message' => sprintf('Notified via %s.', $channel),
            'payload' => [
                'channel' => $channel,
                'audience' => $audience,
                'recipient_id' => $recipientId,
                'notification_id' => $notificationId,
            ],
        ]);

        return StepResult::completed([
            'channel' => $channel,
            'audience' => $audience,
            'recipient_id' => $recipientId,
            'notification_id' => $notificationId,
            // Staff in-app notification is the approval queue, not a table row, so
            // "delivered" reflects the audit record rather than a message being sent.
            'delivered' => $notificationId !== null || $channel !== 'in_app',
        ], 'Notification recorded.');
    }

    private function resolveRecipient(array $config, array $state): ?int
    {
        if (isset($config['recipient_id']) && is_numeric($config['recipient_id'])) {
            return (int) $config['recipient_id'];
        }

        $path = $config['recipient_from'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        $value = $state;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Substitute {{path.to.value}} from run state. Deliberately literal — no
     * expressions, no code, and an unresolved placeholder is left visible rather
     * than silently blanked, so a broken template is obvious.
     */
    private function renderMessage(array $config, array $state): string
    {
        $template = (string) ($config['message'] ?? '');

        if ($template === '' || ! str_contains($template, '{{')) {
            return $template;
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($matches) use ($state) {
            $value = $state;

            foreach (explode('.', $matches[1]) as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    return $matches[0];
                }

                $value = $value[$segment];
            }

            return is_scalar($value) ? (string) $value : $matches[0];
        }, $template) ?? $template;
    }
}
