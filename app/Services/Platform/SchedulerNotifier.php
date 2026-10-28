<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformScheduledTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a scheduled job's findings into in-app (web) notifications for STAFF.
 *
 * AUDIENCE comes from config `platform_services.scheduler_audiences` (task key => profile NAMES),
 * resolved per school through tbluser.user_profile_id -> tbluserprofilemaster.name. role_key is
 * NULL on every profile, so it is never used. Parents and students are never recipients: names
 * that look like them are dropped even if someone adds them to the config.
 *
 * ONLY WHEN SOMETHING CHANGED. Each web row stores `fp:<finding key>|<fingerprint>` in
 * platform_notification_log.provider_ref. A recipient is skipped when their most recent row for
 * the same event already carries the same fingerprint. A finding with count 0 sends nothing.
 *
 * NEVER BREAKS THE RUN. notify() swallows and logs every error; the dispatcher also wraps it.
 */
class SchedulerNotifier
{
    /** Profile names that must never receive scheduler notices, whatever the config says. */
    private const NEVER = ['student', 'students', 'parent', 'parents', 'guardian'];

    public function __construct(private ?NotificationSender $sender = null)
    {
    }

    /**
     * @param  list<array{key:string,count:int,severity:string,fingerprint:string,message:string}>  $findings
     * @return int notifications sent
     */
    public function notify(PlatformScheduledTask $task, array $findings): int
    {
        $sent = 0;

        try {
            $tenant = (int) $task->sub_institute_id;
            $taskKey = (string) $task->task_key;
            $active = array_values(array_filter($findings, fn ($f) => is_array($f) && (int) ($f['count'] ?? 0) > 0));
            if ($active === []) {
                return 0;
            }

            $users = $this->recipientUserIds($tenant, $this->audienceFor($taskKey));
            if ($users === []) {
                return 0;
            }

            foreach ($active as $finding) {
                $findingKey = (string) ($finding['key'] ?? 'finding');
                $eventKey = $taskKey.'.finding.'.$findingKey;
                $stamp = 'fp:'.$findingKey.'|'.(string) ($finding['fingerprint'] ?? $finding['count']);
                $label = (string) (((array) config('platform_services.tasks', []))[$taskKey]['label'] ?? $taskKey);
                $message = (string) ($finding['message'] ?? '');

                foreach ($users as $userId) {
                    $recipient = 'user:'.$userId;
                    try {
                        if ($this->lastStamp($tenant, $recipient, $eventKey) === $stamp) {
                            continue;
                        }
                        $this->sender()->dispatch(
                            $tenant, $eventKey,
                            [['channel' => 'web', 'recipient' => $recipient]],
                            $label, $message, false, $stamp
                        );
                        $sent++;
                    } catch (Throwable $e) {
                        Log::warning('SchedulerNotifier: could not notify '.$recipient.' for '.$eventKey.': '.$e->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('SchedulerNotifier failed for '.$task->task_key.': '.$e->getMessage());
        }

        return $sent;
    }

    /** @return list<string> staff profile names for this task, lower-cased, with student/parent names removed */
    public function audienceFor(string $taskKey): array
    {
        $names = (array) (config('platform_services.scheduler_audiences', [])[$taskKey] ?? []);
        $names = array_map(fn ($n) => mb_strtolower(trim((string) $n)), $names);

        return array_values(array_unique(array_filter($names, fn ($n) => $n !== '' && ! in_array($n, self::NEVER, true))));
    }

    /**
     * Active users of this school whose profile NAME is in the list (case-insensitive).
     *
     * @param  list<string>  $lowerNames
     * @return list<int>
     */
    public function recipientUserIds(int $tenant, array $lowerNames): array
    {
        if ($lowerNames === []) {
            return [];
        }

        return DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('u.sub_institute_id', $tenant)
            ->where('u.status', 1)
            ->whereIn(DB::raw('LOWER(TRIM(p.name))'), $lowerNames)
            ->distinct()
            ->pluck('u.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** provider_ref of the most recent web row for this tenant + recipient + event, or null. */
    public function lastStamp(int $tenant, string $recipient, string $eventKey): ?string
    {
        $ref = DB::table('platform_notification_log')
            ->where('sub_institute_id', $tenant)->where('recipient', $recipient)
            ->where('event_key', $eventKey)->where('channel', 'web')
            ->orderByDesc('id')->value('provider_ref');

        return $ref === null ? null : (string) $ref;
    }

    private function sender(): NotificationSender
    {
        return $this->sender ??= app(NotificationSender::class);
    }
}
