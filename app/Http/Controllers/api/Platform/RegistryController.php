<?php

namespace App\Http\Controllers\api\Platform;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/platform/registry
 *
 * The module and component catalogue the three screens render from — the same
 * one the write endpoints validate against.
 *
 * WHY THE FRONTEND FETCHES THIS INSTEAD OF SHIPPING ITS OWN COPY
 * Two copies of a contract drift, and the copy that drifts is always the one
 * doing the validating. With one endpoint, a screen physically cannot offer a
 * setting the API would refuse, and adding a module is one edit to
 * config/platform_services.php with no frontend release.
 *
 * IT REPORTS ITS OWN MISCONFIGURATION rather than hiding it. A component naming
 * a module nobody declared would otherwise show up as a row silently missing
 * from a screen — the kind of bug that survives for months. `problems` is served
 * alongside the payload so a working screen is not blacked out by one typo, and
 * the screen shows the list where an administrator will see it.
 */
class RegistryController extends PlatformController
{
    public function index(Request $request): JsonResponse
    {
        if ($this->tenantId($request) === null) {
            return $this->unauthenticated($request);
        }

        return $this->ok($this->registry->payload(), [
            'problems' => $this->registry->problems(),
        ]);
    }
}
