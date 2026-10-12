<?php

namespace App\Console\Commands;

use App\Jobs\SendAlumniCampaignJob;
use App\Services\Alumni\AlumniEventReminders;
use App\Services\Alumni\AlumniImportService;
use App\Services\Alumni\AlumniMilestoneService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The alumni module's scheduled work. One command, one task per run, registered in the console kernel. Every task is
 * idempotent (safe to run twice, or to re-run after a crash) and prints what it did so the scheduler log is readable.
 *
 *   php artisan alumni:run scheduled            start campaigns whose scheduled time has arrived
 *   php artisan alumni:run milestones           birthdays, work anniversaries, graduation anniversaries
 *   php artisan alumni:run event-reminders      remind registered alumni of events starting soon
 *   php artisan alumni:run expire-opportunities mark published opportunities past their last date as expired
*   php artisan alumni:run auth-purge           delete used/expired sign-in tokens and old sessions (alumni portal)
 *   php artisan alumni:run import-purge         forget abandoned import uploads and old import row data (personal data does not linger)
 *
 * Options: --date=YYYY-MM-DD (milestones and event-reminders; default today)  --tenant=ID (only that institute)
 */
class AlumniScheduledJobs extends Command
{
    protected $signature = 'alumni:run {task : scheduled | milestones | event-reminders | expire-opportunities | import-purge | auth-purge} {--date= : YYYY-MM-DD, default today} {--tenant= : only this institute}';

    protected $description = 'Run an alumni module scheduled task';

    public function handle(AlumniMilestoneService $milestones, AlumniEventReminders $reminders, AlumniImportService $imports): int
    {
        return match ($this->argument('task')) {
            'scheduled' => $this->scheduled(),
            'milestones' => $this->milestones($milestones),
            'event-reminders' => $this->eventReminders($reminders),
            'expire-opportunities' => $this->expire(),
            'import-purge' => $this->importPurge($imports),
            'auth-purge' => $this->authPurge(),
            default => $this->unknown(),
        };
    }

    private function authPurge(): int
    {
        $r = app(\App\Services\Alumni\AlumniAuthService::class)->purge();
        $this->info("Auth purge: {$r['tokens']} old one-time token(s) and {$r['sessions']} old login session(s) deleted.");

        return self::SUCCESS;
    }

    private function importPurge(AlumniImportService $imports): int
    {
        $r = $imports->purge();
        $this->info("Import purge: {$r['abandoned_uploads']} abandoned upload(s) discarded, personal data cleared from {$r['rows_cleared']} old row(s).");

        return self::SUCCESS;
    }

    private function unknown(): int
    {
        $this->error('Unknown task. Use: scheduled, milestones, event-reminders, expire-opportunities, import-purge or auth-purge.');

        return self::FAILURE;
    }

    private function scheduled(): int
    {
        $due = DB::table('alumni_campaigns')->where('status', 'scheduled')->where('scheduled_at', '<=', now())->orderBy('id')->limit(50)->pluck('id');
        foreach ($due as $id) {
            // claim by status so two overlapping runs cannot both dispatch it
            if (DB::table('alumni_campaigns')->where('id', $id)->where('status', 'scheduled')->update(['status' => 'queued', 'updated_at' => now()]) === 1) {
                SendAlumniCampaignJob::dispatch((int) $id);
                $this->line("Campaign {$id} started.");
            }
        }
        $this->info($due->count() . ' scheduled campaign(s) due.');

        return self::SUCCESS;
    }

    private function tenants(string $table = 'alumni'): array
    {
        return $this->option('tenant') ? [(int) $this->option('tenant')] : DB::table($table)->distinct()->pluck('sub_institute_id')->map(fn ($t) => (int) $t)->all();
    }

    private function date(): Carbon
    {
        return $this->option('date') ? Carbon::parse((string) $this->option('date')) : now();
    }

    private function milestones(AlumniMilestoneService $service): int
    {
        $date = $this->date();
        foreach ($this->tenants() as $t) {
            $r = $service->run($t, $date);
            $this->line("Institute {$t} on {$date->toDateString()}: birthdays {$r['birthday_wishes']}, work anniversaries {$r['work_anniversary']}, graduation anniversaries {$r['graduation_anniversary']} (sent {$r['sent']}, skipped {$r['skipped']}, not contacted {$r['not_contacted']}).");
        }

        return self::SUCCESS;
    }

    private function eventReminders(AlumniEventReminders $service): int
    {
        $date = $this->date();
        foreach ($this->tenants('alumni_events') as $t) {
            $r = $service->run($t, $date);
            $this->line("Institute {$t} on {$date->toDateString()}: {$r['events']} event(s) due a reminder, {$r['alumni']} alumni (sent {$r['sent']}, skipped {$r['skipped']}, not contacted {$r['not_contacted']}).");
        }

        return self::SUCCESS;
    }

    private function expire(): int
    {
        $n = DB::table('alumni_opportunities')->where('status', 'published')->whereNotNull('last_date')->where('last_date', '<', now()->toDateString())
            ->update(['status' => 'expired', 'updated_at' => now()]);
        $this->info("{$n} opportunity(ies) expired.");

        return self::SUCCESS;
    }
}
