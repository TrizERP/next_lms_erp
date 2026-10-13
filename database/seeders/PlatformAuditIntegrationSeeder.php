<?php

namespace Database\Seeders;

use App\Models\Platform\PlatformAuditLog;
use App\Models\Platform\PlatformIntegrationConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Sample data for the audit trail and the Integrations store, so the screens have
 * something to show before real activity exists.
 *
 * Everything is marked is_sample = 1 so the UI can label it as sample data.
 * Credentials are obviously fake. Tenant = the lowest sub_institute_id found in
 * school_setup (an existing table); no tenant is invented.
 *
 * IDEMPOTENT: audit sample rows are only inserted when this tenant has none;
 * integrations are keyed on (tenant, provider_key) and an existing row (sample
 * or real) is never overwritten.
 */
class PlatformAuditIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = (int) (DB::table('school_setup')->min('Id') ?? 0);
        if ($tenantId <= 0) {
            $this->command?->warn('No tenant found in school_setup; nothing seeded.');

            return;
        }

        $this->seedAudit($tenantId);
        $this->seedIntegrations($tenantId);
    }

    private function seedAudit(int $tenantId): void
    {
        if (PlatformAuditLog::forTenant($tenantId)->where('is_sample', 1)->exists()) {
            $this->command?->info('Sample audit rows already present; skipped.');

            return;
        }

        // [module, component, action, entity_type, entity_id, actor, before, after, hours ago]
        $rows = [
            ['fees', 'fees.collection', 'create', 'receipt', 'FEE-2026-0912', 'Priya Nair', null, ['amount' => 12500, 'mode' => 'UPI'], 2],
            ['fees', 'fees.concession', 'approve', 'concession', '41', 'Anil Mehta', ['status' => 'pending'], ['status' => 'approved'], 5],
            ['fees', 'fees.collection', 'update', 'fee_structure', '7', 'Priya Nair', ['term2_due' => '2026-07-10'], ['term2_due' => '2026-07-15'], 9],
            ['fees', 'fees.refund', 'create', 'refund', 'RF-2026-0033', 'Anil Mehta', null, ['amount' => 3000], 20],
            ['admissions', 'admissions.application', 'create', 'application', 'ADM-2026-0421', 'Meera Shah', null, ['status' => 'submitted'], 3],
            ['admissions', 'admissions.application', 'update', 'application', 'ADM-2026-0421', 'Meera Shah', ['status' => 'submitted'], ['status' => 'confirmed'], 26],
            ['admissions', 'admissions.enquiry', 'delete', 'enquiry', '118', 'Meera Shah', ['name' => 'Duplicate enquiry'], null, 30],
            ['students', 'students.record', 'update', 'student', '2231', 'Kavita Rao', ['mobile' => '98xxxxxx01'], ['mobile' => '98xxxxxx77'], 6],
            ['students', 'students.transfer', 'create', 'transfer_certificate', 'TC-2026-014', 'Kavita Rao', null, ['reason' => 'Relocation'], 48],
            ['students', 'students.promotion', 'update', 'promotion_batch', 'P-2026', 'Kavita Rao', ['class' => 'Grade 5'], ['class' => 'Grade 6'], 72],
            ['attendance', 'attendance.daily', 'update', 'attendance_sheet', 'G6-A-2026-10-05', 'Rohan Desai', ['absent' => 3], ['absent' => 2], 4],
            ['attendance', 'attendance.leave', 'approve', 'leave_request', '88', 'Sunita Joshi', ['status' => 'pending'], ['status' => 'approved'], 11],
            ['examination', 'examination.marks', 'update', 'marks_entry', 'UT1-G7-MATH', 'Rohan Desai', ['published' => false], ['published' => true], 14],
            ['examination', 'examination.results', 'publish', 'result_set', 'TERM1-G7', 'Sunita Joshi', null, ['students' => 142], 52],
            ['hr', 'hr.leave', 'approve', 'leave_request', '310', 'Anil Mehta', ['status' => 'pending'], ['status' => 'approved'], 8],
            ['hr', 'hr.payroll', 'create', 'payroll_run', 'PAY-2026-09', 'Anil Mehta', null, ['staff' => 64], 96],
            ['library', 'library.circulation', 'create', 'issue', 'LIB-ISS-5521', 'Deepa Iyer', null, ['book' => 'Wings of Fire'], 7],
            ['library', 'library.fines', 'update', 'fine', '19', 'Deepa Iyer', ['status' => 'due'], ['status' => 'waived'], 33],
            ['transport', 'transport.routes', 'update', 'route', 'R-04', 'Imran Qureshi', ['stops' => 11], ['stops' => 12], 18],
            ['communication', 'communication.circular', 'create', 'circular', 'CIR-2026-072', 'Meera Shah', null, ['audience' => 'Parents, Grade 6-8'], 1],
            ['platform', 'platform.notification', 'update', 'notification_preference', 'fees.receipt.issued', 'Priya Nair', ['sms' => false], ['sms' => true], 10],
            ['platform', 'platform.scheduler', 'update', 'scheduled_task', 'fees.collection.gateway_reconcile', 'Anil Mehta', ['hour' => '2'], ['hour' => '3'], 15],
            ['platform', 'platform.workflow', 'create', 'workflow', '6', 'Anil Mehta', null, ['name' => 'Concession approval'], 22],
            ['platform', 'platform.integration', 'update', 'integration', 'sms_gateway', 'Anil Mehta', ['status' => 'inactive'], ['status' => 'active'], 36],
            ['compliance', 'compliance.documents', 'create', 'document', 'DOC-2026-208', 'Sunita Joshi', null, ['title' => 'Fire safety certificate'], 120],
        ];

        $now = now();
        foreach ($rows as $i => [$module, $component, $action, $type, $entity, $actor, $before, $after, $hoursAgo]) {
            PlatformAuditLog::create([
                'sub_institute_id' => $tenantId,
                'module' => $module,
                'component' => $component,
                'action' => $action,
                'entity_type' => $type,
                'entity_id' => $entity,
                'actor_user_id' => null,
                'actor_name' => $actor.' (sample)',
                'before_json' => $before !== null ? json_encode($before) : null,
                'after_json' => $after !== null ? json_encode($after) : null,
                'ip' => '10.0.0.'.(10 + $i),
                'is_sample' => 1,
                'created_at' => $now->copy()->subHours($hoursAgo)->subMinutes($i * 3),
            ]);
        }

        $this->command?->info('Seeded '.count($rows).' sample audit rows for tenant '.$tenantId.'.');
    }

    private function seedIntegrations(int $tenantId): void
    {
        $seal = fn (string $v) => 'enc:'.Crypt::encryptString($v);

        $defs = [
            ['sms_gateway', 'SMS gateway', 'sms', 'Transactional SMS for fee reminders and alerts.', 'inactive', [
                'api_url' => 'https://sms.sample-gateway.example/v1/send', 'sender_id' => 'SAMPLE',
                'api_key' => $seal('SAMPLE-NOT-A-REAL-KEY-0000'),
            ]],
            ['smtp_email', 'SMTP email', 'email', 'Outgoing email for receipts and circulars.', 'inactive', [
                'host' => 'smtp.sample-mail.example', 'port' => '587', 'username' => 'sample-user',
                'password' => $seal('sample-password-not-real'), 'from_address' => 'noreply@sample-school.example',
            ]],
            ['whatsapp_business', 'WhatsApp Business', 'whatsapp', 'WhatsApp templates for parent communication.', 'inactive', [
                'api_url' => 'https://wa.sample-provider.example/v1', 'phone_number_id' => '000000000000000',
                'access_token' => $seal('SAMPLE-WA-TOKEN-NOT-REAL'),
            ]],
            ['push_notifications', 'Push notifications', 'push', 'Mobile app push notifications.', 'inactive', [
                'project_id' => 'sample-project', 'server_key' => $seal('SAMPLE-PUSH-SERVER-KEY'),
            ]],
            ['online_fees_gateway', 'Online fees gateway', 'payment', 'Online fee payment gateway.', 'inactive', [
                'merchant_id' => 'MERCHANT-SAMPLE-000', 'api_key' => $seal('SAMPLE-PAY-KEY'),
                'api_secret' => $seal('SAMPLE-PAY-SECRET'),
            ]],
            ['biometric_attendance', 'Biometric attendance', 'biometric', 'Fingerprint and face attendance devices.', 'inactive', [
                'device_url' => 'https://biometric.sample-device.example/api', 'api_key' => $seal('SAMPLE-BIO-KEY'),
            ]],
        ];

        $created = 0;
        foreach ($defs as [$key, $name, $category, $description, $status, $config]) {
            if (PlatformIntegrationConfig::forTenant($tenantId)->where('provider_key', $key)->exists()) {
                continue;
            }
            PlatformIntegrationConfig::create([
                'sub_institute_id' => $tenantId,
                'provider_key' => $key,
                'display_name' => $name,
                'category' => $category,
                'description' => $description,
                'status' => $status,
                'config_json' => json_encode($config),
                'updated_by' => 'Sample data',
                'is_sample' => 1,
            ]);
            $created++;
        }

        $this->command?->info("Seeded {$created} sample integration configs for tenant {$tenantId}.");
    }
}
