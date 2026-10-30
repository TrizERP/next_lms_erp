<?php

namespace App\Http\Controllers\api\Platform;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The header bell's per-person feed.
 *
 * `/notifications/inbox` (EngineController) is the platform's tenant-wide admin feed and needs
 * `platform.notification`. This one answers "what is addressed to ME", for any signed-in user,
 * by merging three tables that each already hold per-person rows:
 *
 *   · app_notification          student mobile-app notices (circulars, homework, assignments)
 *   · pal_gamification_notifications   PAL badges, keyed by tblstudent id
 *   · hpbrain_notifications     Enterprise Brain notices for staff, keyed by tbluser id
 *
 * Identity and tenant come from the verified token, never from the request, so one user cannot
 * ask for another's rows. A student sees the first two; staff see the third. No endpoint here
 * writes anything, so a notice is never marked read server-side; `read` is reported where the
 * table carries it and null where it does not, and the bell falls back to its own last-seen time.
 */
class MyNotificationsController extends PlatformController
{
    private const PER_SOURCE = 15;

    private const TOTAL = 30;

    public function index(Request $request): JsonResponse
    {
        $tenant = $this->tenantId($request);
        $auth = $this->auth($request);
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($tenant === null || $userId <= 0) {
            return $this->unauthenticated($request);
        }

        $isStudent = filter_var($auth['is_student'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $rows = $isStudent
            ? array_merge($this->appNotices($tenant, $userId), $this->palNotices($userId))
            : array_merge($this->userNotices($tenant, $userId), $this->brainNotices($tenant, $userId), $this->platformNotices($tenant, $userId));

        usort($rows, fn (array $a, array $b) => strcmp($b['created_at'], $a['created_at']));

        return $this->ok(array_slice($rows, 0, self::TOTAL));
    }

    private function appNotices(int $tenant, int $studentId): array
    {
        if (! Schema::hasTable('app_notification')) {
            return [];
        }

        return DB::table('app_notification')
            ->where('STUDENT_ID', $studentId)->where('SUB_INSTITUTE_ID', $tenant)
            ->orderByDesc('ID')->limit(self::PER_SOURCE)
            ->get(['ID', 'NOTIFICATION_TYPE', 'NOTIFICATION_DESCRIPTION', 'NOTIFICATION_DATE', 'CREATED_AT'])
            ->map(fn ($r) => $this->row(
                'app:' . $r->ID,
                'app',
                (string) $r->NOTIFICATION_TYPE,
                (string) $r->NOTIFICATION_DESCRIPTION,
                $r->CREATED_AT ?: ($r->NOTIFICATION_DATE . ' 00:00:00'),
                null
            ))->all();
    }

    private function palNotices(int $studentId): array
    {
        if (! Schema::hasTable('pal_gamification_notifications')) {
            return [];
        }

        return DB::table('pal_gamification_notifications')
            ->where('learner_id', $studentId)
            ->orderByDesc('id')->limit(self::PER_SOURCE)
            ->get(['id', 'title', 'body', 'read_at', 'created_at'])
            ->map(fn ($r) => $this->row('pal:' . $r->id, 'pal', (string) $r->title, (string) $r->body, (string) $r->created_at, $r->read_at !== null))
            ->all();
    }

    /**
     * Notices raised for this person by background work (Question Paper processing). The only source
     * here with read state the bell can write back (POST /notifications/user/{id}/read).
     */
    private function userNotices(int $tenant, int $userId): array
    {
        if (! Schema::hasTable('user_notifications')) {
            return [];
        }

        return DB::table('user_notifications')
            ->where('sub_institute_id', $tenant)->where('user_id', $userId)
            ->orderByDesc('id')->limit(self::PER_SOURCE)
            ->get(['id', 'title', 'body', 'link', 'read_at', 'created_at'])
            ->map(fn ($r) => $this->row('user:' . $r->id, 'user', (string) $r->title, (string) $r->body, (string) $r->created_at, $r->read_at !== null, $r->link))
            ->all();
    }

    private function brainNotices(int $tenant, int $userId): array
    {
        if (! Schema::hasTable('hpbrain_notifications')) {
            return [];
        }

        return DB::table('hpbrain_notifications')
            ->where('tenant_id', (string) $tenant)->where('user_id', (string) $userId)
            ->orderByDesc('created_date')->limit(self::PER_SOURCE)
            ->get(['id', 'title', 'body', 'read_date', 'created_date'])
            ->map(fn ($r) => $this->row('brain:' . $r->id, 'brain', (string) $r->title, (string) $r->body, (string) $r->created_date, $r->read_date !== null))
            ->all();
    }

    /**
     * Notices the platform sent to this person (scheduler findings and the like): web rows addressed
     * to `user:<id>` in the notification log. Staff only; students never receive these.
     */
    private function platformNotices(int $tenant, int $userId): array
    {
        if (! Schema::hasTable('platform_notification_log')) {
            return [];
        }

        return DB::table('platform_notification_log')
            ->where('sub_institute_id', $tenant)->where('recipient', 'user:' . $userId)
            ->where('channel', 'web')->where('status', 'sent')
            ->orderByDesc('id')->limit(self::PER_SOURCE)
            ->get(['id', 'subject', 'body', 'created_at'])
            ->map(fn ($r) => $this->row('platform:' . $r->id, 'platform', (string) $r->subject, (string) $r->body, (string) $r->created_at, null))
            ->all();
    }

    private function row(string $key, string $source, string $subject, string $body, string $createdAt, ?bool $read, ?string $link = null): array
    {
        return [
            'key' => $key,
            'source' => $source,
            'subject' => $subject,
            'body' => Str::limit(trim(strip_tags($body)), 300),
            'created_at' => $createdAt,
            'read' => $read,
            'link' => $link,
        ];
    }
}
