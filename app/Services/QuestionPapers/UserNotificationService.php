<?php

namespace App\Services\QuestionPapers;

use Illuminate\Support\Facades\DB;

/**
 * Writes and reads rows of user_notifications, the per-person feed behind the header bell.
 *
 * Every read and mutation is bound to (sub_institute_id, user_id) taken from the verified token by
 * the caller, so one person cannot read or mark another person's notice.
 */
class UserNotificationService
{
    /** @return bool true when a new row was written, false when this dedupe key already existed */
    public function notify(
        int $tenant,
        int $userId,
        string $type,
        string $severity,
        string $title,
        ?string $body,
        ?string $link,
        string $sourceType,
        string $sourceId,
        string $dedupeKey,
    ): bool {
        $now = now();

        return DB::table('user_notifications')->insertOrIgnore([
            'sub_institute_id' => $tenant,
            'user_id' => $userId,
            'type' => $type,
            'severity' => $severity,
            'title' => mb_substr($title, 0, 191),
            'body' => $body,
            'link' => $link,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'dedupe_key' => mb_substr($dedupeKey, 0, 120),
            'created_at' => $now,
            'updated_at' => $now,
        ]) > 0;
    }

    /** @return list<object> */
    public function recent(int $tenant, int $userId, int $limit = 15): array
    {
        return DB::table('user_notifications')
            ->where('sub_institute_id', $tenant)->where('user_id', $userId)
            ->orderByDesc('id')->limit($limit)
            ->get(['id', 'type', 'severity', 'title', 'body', 'link', 'read_at', 'created_at'])
            ->all();
    }

    public function unreadCount(int $tenant, int $userId): int
    {
        return DB::table('user_notifications')
            ->where('sub_institute_id', $tenant)->where('user_id', $userId)
            ->whereNull('read_at')->count();
    }

    public function markRead(int $tenant, int $userId, int $id): bool
    {
        return DB::table('user_notifications')
            ->where('id', $id)->where('sub_institute_id', $tenant)->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]) > 0;
    }

    public function markAllRead(int $tenant, int $userId): int
    {
        return DB::table('user_notifications')
            ->where('sub_institute_id', $tenant)->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }
}
