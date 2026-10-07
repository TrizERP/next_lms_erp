<?php

namespace Database\Seeders;

use App\Models\Platform\PlatformScheduledTask;
use App\Models\Platform\PlatformWorkflow;
use App\Services\Platform\WorkflowEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sample data for the platform engines. Everything is flagged is_sample = 1 so
 * the screens label it "Sample data". Idempotent: it does nothing if sample runs
 * already exist for the tenant. Never touches real rows.
 */
class PlatformEnginesSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = (int) DB::table('school_setup')->min('Id');
        if ($tenant <= 0 || DB::table('platform_workflow_runs')->where('sub_institute_id', $tenant)->where('is_sample', 1)->exists()) {
            return;
        }

        // An active chain to run against, only if the school has none for this point.
        $flow = 'students.transfer.flow';
        $chain = PlatformWorkflow::forTenant($tenant)->where('flow_key', $flow)->where('status', 'active')->first();
        if (! $chain) {
            $chain = PlatformWorkflow::create([
                'sub_institute_id' => $tenant, 'flow_key' => $flow, 'module' => 'students', 'component' => 'students.transfer',
                'name' => 'Sample: transfer certificate approval', 'description' => 'Created as sample data.',
                'status' => 'active', 'condition' => '', 'on_reject' => 'return_to_requester', 'notify_requester' => true,
                'created_by' => 'seeder', 'updated_by' => 'seeder',
                'steps' => [
                    ['id' => 'stp_sample0001', 'order' => 1, 'name' => 'Dues clearance', 'approver_type' => 'role', 'approver' => 'Accounts', 'sla_hours' => 24, 'on_breach' => 'none', 'allow_delegate' => false, 'require_comment' => false],
                    ['id' => 'stp_sample0002', 'order' => 2, 'name' => 'Principal approval', 'approver_type' => 'principal', 'approver' => '', 'sla_hours' => 48, 'on_breach' => 'none', 'allow_delegate' => true, 'require_comment' => true],
                ],
            ]);
        }

        $engine = app(WorkflowEngine::class);
        $make = fn (string $id, string $title) => $engine->start($tenant, $flow, 'sample_tc_request', $id, null, 'Sample requester', $title, [], true);

        $make('S-001', 'Sample: TC request, pending');
        $approved = $make('S-002', 'Sample: TC request, approved');
        $engine->act($tenant, $approved['id'], 'approve', 1, 'Sample approver', null);
        $engine->act($tenant, $approved['id'], 'approve', 1, 'Sample approver', 'Looks fine.');
        $rejected = $make('S-003', 'Sample: TC request, rejected');
        $engine->act($tenant, $rejected['id'], 'reject', 1, 'Sample approver', 'Dues pending.');

        // Delivery log: honest statuses, as the sender would have written them.
        $now = now();
        $rows = [
            ['fees.defaulter.overdue', 'web', 'sample.parent@example.com', 'sent', 1, null],
            ['fees.defaulter.overdue', 'email', 'sample.parent@example.com', 'sent', 1, null],
            ['fees.defaulter.overdue', 'sms', '+910000000000', 'skipped', 0, 'No active sms integration is configured for this school.'],
            ['fees.defaulter.overdue', 'whatsapp', '+910000000000', 'skipped', 0, 'No active whatsapp integration is configured for this school.'],
            ['lms.assignment.overdue', 'email', 'sample.student@example.com', 'failed', 2, 'Connection timed out (sample failure).'],
            ['lms.assignment.overdue', 'web', 'sample.student@example.com', 'sent', 1, null],
        ];
        foreach ($rows as [$event, $channel, $to, $status, $attempts, $error]) {
            DB::table('platform_notification_log')->insert([
                'sub_institute_id' => $tenant, 'event_key' => $event, 'channel' => $channel, 'recipient' => $to,
                'subject' => 'Sample notification', 'body' => 'Sample notification body.', 'status' => $status,
                'attempts' => $attempts, 'last_error' => $error, 'sent_at' => $status === 'sent' ? $now : null,
                'next_attempt_at' => $status === 'failed' ? $now->copy()->addMinutes(10) : null,
                'is_sample' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // One scheduled task row so the dispatcher has something real to run.
        PlatformScheduledTask::updateOrCreate(
            ['sub_institute_id' => $tenant, 'task_key' => 'communication.campaign.dispatch_queue'],
            ['module' => 'communication', 'component' => 'communication.campaign', 'minute' => '*/10', 'hour' => '*', 'day' => '*', 'month' => '*', 'day_of_week' => '*', 'disabled' => false, 'fail_delay' => 5, 'updated_by' => 'seeder']
        );

        // A real sample file so download works.
        $path = "platform-files/{$tenant}/sample-readme.txt";
        Storage::disk('local')->put($path, "Sample attachment.\nThis file was created as sample data.\n");
        DB::table('platform_file_attachments')->insert([
            'sub_institute_id' => $tenant, 'entity_type' => 'sample_tc_request', 'entity_id' => 'S-001',
            'original_name' => 'sample-readme.txt', 'mime' => 'text/plain', 'size' => Storage::disk('local')->size($path),
            'storage_path' => $path, 'version' => 1, 'uploaded_by_name' => 'Sample data', 'is_sample' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
