<?php

namespace App\Http\Controllers\api\Platform;

use App\Services\Platform\PlatformRegistry;
use App\Services\QuestionPapers\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in person's own notifications (user_notifications), with read state.
 * Identity and school come from the verified token only.
 */
class UserNotificationController extends PlatformController
{
    public function __construct(PlatformRegistry $registry, private UserNotificationService $notifications)
    {
        parent::__construct($registry);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        [$tenant, $userId] = $this->who($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }

        // 0 rows means not yours or already read; both are fine from the caller's side.
        $this->notifications->markRead($tenant, $userId, $id);

        return response()->json(['status_code' => 1, 'message' => 'OK', 'data' => ['unread' => $this->notifications->unreadCount($tenant, $userId)]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        [$tenant, $userId] = $this->who($request);
        if ($tenant === null) {
            return $this->unauthenticated($request);
        }

        $this->notifications->markAllRead($tenant, $userId);

        return response()->json(['status_code' => 1, 'message' => 'OK', 'data' => ['unread' => 0]]);
    }

    /** @return array{0: ?int, 1: int} */
    private function who(Request $request): array
    {
        $userId = (int) ($this->auth($request)['user_id'] ?? 0);
        $tenant = $this->tenantId($request);

        return $tenant === null || $userId <= 0 ? [null, 0] : [$tenant, $userId];
    }
}
