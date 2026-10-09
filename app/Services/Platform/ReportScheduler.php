<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs scheduled reports. A schedule is due when its frequency has elapsed since
 * its last run. Each run renders the report to CSV, stores it as an attached file
 * (so it is versioned and downloadable from File storage) and tells the recipients
 * by email. The email carries the row count and where to find the file, not the
 * file itself: report data does not leave the platform in an email body.
 */
class ReportScheduler
{
    public function __construct(private ReportCatalog $catalog, private NotificationSender $sender)
    {
    }

    /** @return int schedules run */
    public function runDue(): int
    {
        $count = 0;
        foreach (DB::table('platform_report_schedules')->where('enabled', 1)->get() as $schedule) {
            if ($this->isDue($schedule)) {
                $this->run((int) $schedule->id);
                $count++;
            }
        }

        return $count;
    }

    /** @return array{status:string,message:string} */
    public function run(int $scheduleId, ?int $tenant = null): array
    {
        $q = DB::table('platform_report_schedules')->where('id', $scheduleId);
        if ($tenant !== null) {
            $q->where('sub_institute_id', $tenant);
        }
        $schedule = $q->first();
        if (! $schedule) {
            return ['status' => 'failed', 'message' => 'That schedule was not found.'];
        }

        try {
            $result = $this->catalog->run($schedule->report_key, (int) $schedule->sub_institute_id, (array) json_decode((string) $schedule->filters, true));
            $csv = $this->catalog->toCsv($result);

            $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', $schedule->name).'-'.now()->format('Ymd-His').'.csv';
            $path = "platform-files/{$schedule->sub_institute_id}/".bin2hex(random_bytes(12)).'.csv';
            Storage::disk('local')->put($path, $csv);
            $fileId = DB::table('platform_file_attachments')->insertGetId([
                'sub_institute_id' => $schedule->sub_institute_id, 'entity_type' => 'report_schedule', 'entity_id' => (string) $schedule->id,
                'original_name' => $name, 'mime' => 'text/csv', 'size' => strlen($csv), 'storage_path' => $path, 'version' => 1,
                'uploaded_by_name' => 'Scheduled report', 'is_sample' => $schedule->is_sample ? 1 : 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $rows = count($result['rows']);
            $recipients = array_values(array_filter((array) json_decode((string) $schedule->recipients, true)));
            if ($recipients) {
                $this->sender->dispatch(
                    (int) $schedule->sub_institute_id, 'reports.scheduled.dispatch', array_map(fn ($r) => ['channel' => 'email', 'recipient' => $r], $recipients),
                    "Scheduled report: {$schedule->name}",
                    "The report \"{$schedule->name}\" ran with {$rows} rows. Open File storage and look for the record type \"report_schedule\", record {$schedule->id}, to download {$name}.",
                    (bool) $schedule->is_sample
                );
            }

            $message = "{$rows} rows exported to {$name}.";
            DB::table('platform_report_schedules')->where('id', $schedule->id)->update([
                'last_run_at' => now(), 'last_run_status' => 'ok', 'last_run_message' => $message, 'last_file_id' => $fileId, 'updated_at' => now(),
            ]);

            return ['status' => 'ok', 'message' => $message];
        } catch (Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 400);
            DB::table('platform_report_schedules')->where('id', $schedule->id)->update([
                'last_run_at' => now(), 'last_run_status' => 'failed', 'last_run_message' => $message, 'updated_at' => now(),
            ]);

            return ['status' => 'failed', 'message' => $message];
        }
    }

    private function isDue(object $s): bool
    {
        if ($s->last_run_at === null) {
            return true;
        }
        $last = \Carbon\Carbon::parse($s->last_run_at);

        return match ($s->frequency) {
            'weekly' => $last->lte(now()->subWeek()),
            'monthly' => $last->lte(now()->subMonth()),
            default => $last->lte(now()->subDay()),
        };
    }
}
