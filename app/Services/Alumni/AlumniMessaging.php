<?php

namespace App\Services\Alumni;

use App\Services\Platform\NotificationSender;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Alumni communication: audiences, consent, personalised messages and an honest history.
 *
 * DELIVERY IS THE PLATFORM'S. Everything goes through NotificationSender, which really sends web (in-app) and email,
 * and records sms / whatsapp / mobile as `skipped` with the reason until a provider integration is active. This class
 * copies that status and reason into alumni_message_log, so the history can say "skipped - no SMS provider configured"
 * and never claims a delivery nobody made. Email through a mailer set to `log` or `array` is likewise reported as skipped.
 *
 * CONSENT. A campaign reaches an alumnus on a channel only if they have consented to it (consent_email, consent_sms,
 * consent_whatsapp, consent_push for the mobile app); those who have not are counted as no_consent, not messaged.
 * Portal (web) messages involve no external contact details and go to every eligible alumnus. TRANSACTIONAL messages -
 * the outcome of a verification, a registration confirmation, a receipt - may use email and web without marketing
 * consent, because the person asked for the thing being confirmed; they still never reach an opted-out, blocked or
 * deceased alumnus, and SMS / WhatsApp / mobile always need consent.
 *
 * ELIGIBLE alumni are registered, verified or active. Opted-out, blocked, deceased, inactive and
 * pending-verification records are never in a campaign, whatever filters are chosen.
 *
 * IDEMPOTENCY. A log row is inserted BEFORE the send, guarded by two unique keys (see the migration), so a retried job or
 * a second run of a daily command skips what was already done instead of messaging twice.
 */
class AlumniMessaging
{
    /** key => label */
    public const TYPES = [
        'announcement' => 'General announcement', 'event_invitation' => 'Event invitation', 'event_reminder' => 'Event reminder',
        'birthday_wishes' => 'Birthday wishes', 'work_anniversary' => 'Work anniversary', 'graduation_anniversary' => 'Graduation anniversary',
        'newsletter' => 'Alumni newsletter', 'donation_campaign' => 'Donation campaign', 'job_opportunity' => 'Job opportunity',
        'career_opportunity' => 'Career opportunity', 'reunion' => 'Reunion', 'survey' => 'Survey',
        'emergency_notice' => 'Emergency or important notice', 'achievement_congratulations' => 'Achievement congratulations',
    ];

    /** Automatic, transactional notices (spec section 41) - not selectable for a campaign. */
    public const SYSTEM_TYPES = [
        'registration' => 'New registration', 'verification' => 'Profile verification', 'registration_confirmation' => 'Registration confirmation',
        'waitlist_promotion' => 'Waitlist promotion', 'mentorship_request' => 'Mentorship request',
        'donation_confirmation' => 'Donation confirmation', 'donation_receipt' => 'Donation receipt',
    ];

    public const ELIGIBLE_STATUSES = ['registered', 'verified', 'active'];

    /** Never contacted by anything, transactional or not. */
    private const NEVER = ['opted_out', 'blocked', 'deceased'];

    /** channel => [consent column or null, how to address them] */
    public const CHANNELS = [
        'email' => ['consent_email', 'email'],
        'sms' => ['consent_sms', 'mobile'],
        'whatsapp' => ['consent_whatsapp', 'mobile'],
        'mobile' => ['consent_push', 'alumni_code'],     // the app addresses a person by id; the device is resolved by the push provider
        'web' => [null, 'alumni_code'],                    // the in-app inbox
    ];

    private const PLACEHOLDERS = ['name', 'first_name', 'last_name', 'batch', 'passing_year', 'institute', 'alumni_code'];

    public const DEFAULT_TEMPLATES = [
        ['Birthday wishes', 'birthday_wishes', 'Happy birthday, {{first_name}}!', "Happy Birthday, {{name}}!\n\nWishing you success and happiness from {{institute}}."],
        ['Work anniversary', 'work_anniversary', 'Congratulations on {{years}} year(s), {{first_name}}', "Congratulations, {{name}}, on {{years}} year(s) at {{company}}!\n\nYour {{institute}} family is proud of you."],
        ['Graduation anniversary', 'graduation_anniversary', '{{years}} years since graduation', "Dear {{name}},\n\nIt is {{years}} year(s) since you left {{institute}}. We hope you are doing well and would love to hear from you."],
        ['Achievement congratulations', 'achievement_congratulations', 'Congratulations, {{first_name}}', "Dear {{name}},\n\nCongratulations on {{achievement}}. {{institute}} is proud of your achievement."],
        ['General announcement', 'announcement', 'An update from {{institute}}', "Dear {{name}},\n\n"],
        ['Event invitation', 'event_invitation', 'You are invited: {{event_name}}', "Dear {{name}},\n\nWe would be delighted to see you at {{event_name}} on {{event_date}} at {{event_venue}}.\n\n{{event_link}}"],
        ['Event reminder', 'event_reminder', 'Reminder: {{event_name}} on {{event_date}}', "Dear {{name}},\n\nA reminder that {{event_name}} is on {{event_date}} at {{event_venue}}."],
        ['Reunion reminder', 'reunion', 'Your batch reunion is coming up', "Dear {{name}},\n\nThe reunion for the batch of {{batch}} is on {{event_date}} at {{event_venue}}. We hope you can join."],
        ['Important notice', 'emergency_notice', 'Important notice from {{institute}}', "Dear {{name}},\n\n"],
    ];

    public function __construct(private AlumniSearch $search, private NotificationSender $sender)
    {
    }

    // ----------------------------------------------------------------- audiences

    /** Eligible alumni matching the filters (the same filters as the alumni list). */
    public function audience(int $tenant, array $filters): Builder
    {
        return $this->search->filtered($tenant, $filters)->whereIn('status', self::ELIGIBLE_STATUSES);
    }

    /** @return array{matched:int, channels: array<string, array{reachable:int, no_consent:int}>} */
    public function preview(int $tenant, array $filters, array $channels): array
    {
        $out = ['matched' => (int) $this->audience($tenant, $filters)->count(), 'channels' => []];
        foreach ($channels as $ch) {
            if (! isset(self::CHANNELS[$ch])) {
                continue;
            }
            [$consent, $contact] = self::CHANNELS[$ch];
            $q = $this->audience($tenant, $filters)->whereNotNull($contact)->where($contact, '!=', '');
            if ($consent !== null) {
                $q->where($consent, 1);
            }
            $reach = (int) $q->count();
            $out['channels'][$ch] = ['reachable' => $reach, 'no_consent' => $out['matched'] - $reach];
        }

        return $out;
    }

    /** A short sentence for the history list. */
    public function describeAudience(array $filters): string
    {
        $parts = [];
        $label = ['batch' => 'Batch', 'passing_year' => 'Passing year', 'passing_year_from' => 'Passing year from', 'passing_year_to' => 'Passing year to', 'country' => 'Country',
            'state' => 'State', 'city' => 'City', 'industry' => 'Industry', 'company' => 'Company', 'job_role' => 'Job role', 'skill' => 'Skill', 'course' => 'Course',
            'department' => 'Department', 'employment_status' => 'Employment', 'higher_education' => 'Higher education', 'campus' => 'Campus', 'admission_year' => 'Admission year'];
        foreach ($label as $k => $l) {
            if (isset($filters[$k]) && trim((string) $filters[$k]) !== '') {
                $parts[] = $l . ': ' . $filters[$k];
            }
        }

        return $parts === [] ? 'All eligible alumni' : implode(', ', $parts);
    }

    /** Only keys the search understands are kept. */
    public function cleanFilters(array $filters): array
    {
        $allowed = array_diff(AlumniSearch::FILTERS, ['q', 'status', 'source']);

        return array_filter(array_intersect_key($filters, array_flip($allowed)), fn ($v) => $v !== null && trim((string) $v) !== '');
    }

    // ----------------------------------------------------------------- templates

    public function ensureDefaultTemplates(int $tenant, ?int $userId = null): void
    {
        if (DB::table('alumni_message_templates')->where('sub_institute_id', $tenant)->exists()) {
            return;
        }
        $now = now();
        foreach (self::DEFAULT_TEMPLATES as [$name, $type, $subject, $body]) {
            DB::table('alumni_message_templates')->insert([
                'sub_institute_id' => $tenant, 'name' => $name, 'comm_type' => $type, 'subject' => $subject, 'body' => $body,
                'is_system' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    /** The tenant's template for a type (their edit wins), falling back to the built-in default text. */
    public function templateFor(int $tenant, string $type): array
    {
        $row = DB::table('alumni_message_templates')->where('sub_institute_id', $tenant)->where('comm_type', $type)->orderByDesc('is_system')->orderBy('id')->first();
        if ($row) {
            return [$row->subject, $row->body];
        }
        foreach (self::DEFAULT_TEMPLATES as [$name, $t, $subject, $body]) {
            if ($t === $type) {
                return [$subject, $body];
            }
        }

        return ['A message from {{institute}}', "Dear {{name}},\n\n"];
    }

    /**
     * Replace {{placeholders}}. Plain string substitution only - nothing in a template or a name is ever evaluated, and
     * unknown placeholders are removed rather than left in the message.
     */
    public function render(string $text, object $alumnus, array $extra = [], ?string $institute = null): string
    {
        $values = array_merge([
            'name' => $alumnus->full_name, 'first_name' => $alumnus->first_name, 'last_name' => (string) $alumnus->last_name,
            'batch' => (string) $alumnus->batch, 'passing_year' => (string) $alumnus->passing_year, 'alumni_code' => $alumnus->alumni_code,
            'institute' => $institute ?? $this->institute((int) $alumnus->sub_institute_id),
        ], array_map('strval', $extra));

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', fn ($m) => $values[strtolower($m[1])] ?? '', $text) ?? $text;
    }

    /**
     * The institute's name for the {{institute}} placeholder. Read fresh each time on purpose: a static cache would leak
     * between tests and go stale in a long-running queue worker after an institute is renamed. Callers that render many
     * messages (a campaign) fetch it once and pass it in.
     */
    public function institute(int $tenant): string
    {
        return Schema::hasTable('school_setup') ? (string) (DB::table('school_setup')->where('Id', $tenant)->value('SchoolName') ?: 'your school') : 'your school';
    }

    // -------------------------------------------------------------- channel truth

    /**
     * What each channel can really do for this institute right now, in words a coordinator can act on.
     *
     * @return array<string, array{state:string, note:string}> state: live | file_only | not_configured | driver_missing | off
     */
    public function channelStatus(int $tenant): array
    {
        $out = [];
        $switches = Schema::hasTable('platform_notification_channels') ? DB::table('platform_notification_channels')->where('sub_institute_id', $tenant)->pluck('enabled', 'channel') : collect();
        foreach (array_keys(self::CHANNELS) as $ch) {
            if (isset($switches[$ch]) && ! (bool) $switches[$ch]) {
                $out[$ch] = ['state' => 'off', 'note' => 'Switched off for this institute in notification settings.'];
                continue;
            }
            if ($ch === 'web') {
                $out[$ch] = ['state' => 'live', 'note' => 'Delivered to the in-app inbox.'];
            } elseif ($ch === 'email') {
                $mailer = (string) config('mail.default');
                $out[$ch] = in_array($mailer, ['log', 'array'], true)
                    ? ['state' => 'file_only', 'note' => "The mailer is set to '{$mailer}': messages are written to a file, not delivered."]
                    : ['state' => 'live', 'note' => 'Sent through the configured mailer.'];
            } else {
                $active = Schema::hasTable('platform_integration_configs')
                    && DB::table('platform_integration_configs')->where('sub_institute_id', $tenant)->where('category', $ch)->where('status', 'active')->exists();
                $out[$ch] = $active
                    ? ['state' => 'driver_missing', 'note' => 'An integration is active but no sending driver is installed yet, so messages are logged as skipped.']
                    : ['state' => 'not_configured', 'note' => 'No provider is configured, so messages are logged as skipped.'];
            }
        }

        return $out;
    }

    /**
     * Create a campaign and, unless it is a draft or scheduled for later, queue it. Shared by the compose screen and event
     * invitations so both follow the same checks.
     *
     * @param  array{name?:?string,comm_type:string,subject:string,body:string,channels:array<int,string>,filters:array<string,mixed>}  $d
     *
     * @throws AlumniRuleException when nobody would be reached
     */
    public function createCampaign(int $tenant, array $d, ?int $userId, bool $draft = false, ?string $scheduledAt = null): object
    {
        $channels = array_values(array_unique(array_intersect($d['channels'], array_keys(self::CHANNELS))));
        $filters = $this->cleanFilters($d['filters'] ?? []);

        if (! $draft) {
            $preview = $this->preview($tenant, $filters, $channels);
            if ($preview['matched'] === 0) {
                throw new AlumniRuleException('No eligible alumni match these filters.', 422, 'filters');
            }
            if (array_sum(array_column($preview['channels'], 'reachable')) === 0) {
                throw new AlumniRuleException('Nobody in this group can be reached on the chosen channels. Alumni need to have given consent, and a contact detail must be on file.', 422, 'channels');
            }
        }

        $status = $draft ? 'draft' : ($scheduledAt ? 'scheduled' : 'queued');
        $id = DB::table('alumni_campaigns')->insertGetId([
            'sub_institute_id' => $tenant, 'name' => trim((string) ($d['name'] ?? '')) ?: mb_substr((string) $d['subject'], 0, 190),
            'comm_type' => $d['comm_type'], 'subject' => $d['subject'], 'body' => $d['body'], 'channels' => implode(',', $channels),
            'audience' => json_encode($filters), 'audience_label' => $this->describeAudience($filters), 'status' => $status,
            'scheduled_at' => $scheduledAt ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : null,
            'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($status === 'queued') {
            \App\Jobs\SendAlumniCampaignJob::dispatch($id);
        }

        return DB::table('alumni_campaigns')->where('id', $id)->first();
    }

    // ------------------------------------------------------------------ sending

    /**
     * Run a campaign. Safe to call twice: the first call claims it, the second finds it already running or finished.
     *
     * @return array<string, int>|null the counts, or null when it was not claimed
     */
    public function runCampaign(int $campaignId): ?array
    {
        $claimed = DB::table('alumni_campaigns')->where('id', $campaignId)->whereIn('status', ['draft', 'scheduled', 'queued', 'failed'])
            ->update(['status' => 'sending', 'started_at' => now(), 'last_error' => null, 'updated_at' => now()]);
        if ($claimed !== 1) {
            return null;
        }
        $c = DB::table('alumni_campaigns')->where('id', $campaignId)->first();
        $tenant = (int) $c->sub_institute_id;
        $filters = (array) json_decode((string) $c->audience, true);
        $channels = array_values(array_intersect(explode(',', (string) $c->channels), array_keys(self::CHANNELS)));
        $institute = $this->institute($tenant);
        $counts = ['recipients' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'no_consent' => 0];

        try {
            $this->audience($tenant, $filters)->orderBy('id')->chunkById(200, function ($rows) use ($c, $tenant, $channels, $institute, &$counts) {
                $counts['recipients'] += $rows->count();
                foreach ($rows as $alumnus) {
                    foreach ($channels as $ch) {
                        [$consent, $contact] = self::CHANNELS[$ch];
                        if ($consent !== null && ! (int) $alumnus->{$consent}) {
                            $counts['no_consent']++;

                            continue;
                        }
                        $to = trim((string) $alumnus->{$contact});
                        if ($to === '') {
                            continue;   // no address on file for this channel: nothing to attempt, nothing to claim
                        }
                        $status = $this->deliver($tenant, (int) $c->id, $alumnus, (string) $c->comm_type, $ch, $to,
                            $this->render((string) $c->subject, $alumnus, [], $institute), $this->render((string) $c->body, $alumnus, [], $institute), null, false, (int) $c->created_by);
                        if ($status !== null) {
                            $counts[$status === 'sent' ? 'sent' : ($status === 'failed' ? 'failed' : 'skipped')]++;
                        }
                    }
                }
                DB::table('alumni_campaigns')->where('id', $c->id)->update([
                    'recipient_count' => $counts['recipients'], 'sent_count' => $counts['sent'], 'skipped_count' => $counts['skipped'],
                    'failed_count' => $counts['failed'], 'no_consent_count' => $counts['no_consent'], 'updated_at' => now(),
                ]);
            });
            DB::table('alumni_campaigns')->where('id', $campaignId)->update(['status' => 'sent', 'finished_at' => now(), 'updated_at' => now()]);
        } catch (Throwable $e) {
            DB::table('alumni_campaigns')->where('id', $campaignId)->update(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 500), 'finished_at' => now(), 'updated_at' => now()]);
            report($e);
        }

        return $counts;
    }

    /**
     * One automatic message to one alumnus (milestone, confirmation, receipt, reminder). Returns channel => status,
     * with `blocked` when the person must not be contacted at all.
     *
     * @param  array<int, string>|null  $channels  default: email + web for a transactional notice, every consented channel otherwise
     * @return array<string, string>
     */
    public function notify(int $tenant, int $alumniId, string $type, string $subject, string $body, string $dedupeKey, bool $transactional, array $extra = [], ?array $channels = null, ?int $userId = null): array
    {
        $alumnus = DB::table('alumni')->where('sub_institute_id', $tenant)->where('id', $alumniId)->first();
        if (! $alumnus || in_array($alumnus->status, self::NEVER, true)) {
            return ['_' => 'blocked'];
        }
        if (! $transactional && ! in_array($alumnus->status, self::ELIGIBLE_STATUSES, true)) {
            return ['_' => 'blocked'];
        }
        $institute = $this->institute($tenant);
        $subject = $this->render($subject, $alumnus, $extra, $institute);
        $body = $this->render($body, $alumnus, $extra, $institute);
        $channels ??= $transactional ? ['email', 'web'] : array_keys(self::CHANNELS);

        $result = [];
        foreach ($channels as $ch) {
            if (! isset(self::CHANNELS[$ch])) {
                continue;
            }
            [$consent, $contact] = self::CHANNELS[$ch];
            $needsConsent = $consent !== null && ! ($transactional && $ch === 'email');
            if ($needsConsent && ! (int) $alumnus->{$consent}) {
                $result[$ch] = 'no_consent';

                continue;
            }
            $to = trim((string) $alumnus->{$contact});
            if ($to === '') {
                $result[$ch] = 'no_address';

                continue;
            }
            $result[$ch] = $this->deliver($tenant, null, $alumnus, $type, $ch, $to, $subject, $body, $dedupeKey, $transactional, $userId) ?? 'already_sent';
        }

        return $result;
    }

    /**
     * Log, send, settle - one alumnus on one channel. Null when the unique key says it was already done.
     */
    private function deliver(int $tenant, ?int $campaignId, object $alumnus, string $type, string $channel, string $recipient, string $subject, string $body, ?string $dedupeKey, bool $transactional, ?int $userId): ?string
    {
        $row = ['sub_institute_id' => $tenant, 'campaign_id' => $campaignId, 'alumni_id' => (int) $alumnus->id, 'comm_type' => $type, 'channel' => $channel,
            'recipient' => mb_substr($recipient, 0, 191), 'subject' => mb_substr($subject, 0, 255), 'status' => 'queued', 'dedupe_key' => $dedupeKey,
            'is_transactional' => $transactional ? 1 : 0, 'sent_by' => $userId, 'created_at' => now(), 'updated_at' => now()];
        // the unique keys make a repeat a no-op: nothing is sent unless THIS call inserted the row
        if (DB::table('alumni_message_log')->insertOrIgnore($row) !== 1) {
            return null;
        }
        $logId = (int) DB::table('alumni_message_log')->where('sub_institute_id', $tenant)->where('alumni_id', $alumnus->id)->where('channel', $channel)
            ->when($campaignId !== null, fn ($q) => $q->where('campaign_id', $campaignId), fn ($q) => $q->where('dedupe_key', $dedupeKey))->value('id');

        try {
            $written = $this->sender->dispatch($tenant, 'alumni.' . $type, [['channel' => $channel === 'mobile' ? 'mobile' : $channel, 'recipient' => $recipient]], $subject, $body);
            $platform = $written[0] ?? null;
            $status = $platform['status'] ?? 'skipped';
            $reason = $platform ? ($platform['last_error'] ?? null) : "The '{$channel}' channel is not set up for this institute.";
            $platformId = $platform['id'] ?? null;
        } catch (Throwable $e) {
            [$status, $reason, $platformId] = ['failed', mb_substr($e->getMessage(), 0, 500), null];
        }
        if (! in_array($status, ['sent', 'skipped', 'failed', 'queued'], true)) {
            $status = 'skipped';
        }
        DB::table('alumni_message_log')->where('id', $logId)->update(['status' => $status, 'reason' => $reason ? mb_substr((string) $reason, 0, 500) : null, 'notification_log_id' => $platformId, 'updated_at' => now()]);

        return $status;
    }
}
