<?php

namespace App\Http\Controllers\api\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What the three platform-service controllers share: identity, tenancy, the
 * response envelope, and the rules about where a key may come from.
 *
 * TENANCY COMES FROM THE TOKEN, NEVER FROM INPUT. `sub_institute_id` is read off
 * the JWT that `lms.auth` decoded and put on the request. Reading it from the
 * body — as several older endpoints in this codebase do — is what makes tenancy
 * caller-controlled, and on THESE screens that would mean one school editing
 * another school's notification policy.
 *
 * NO IDENTITY MEANS NO ANSWER. `lms.auth` is in warn-only mode
 * (config lms_content.api_auth_enforce), so an unauthenticated request reaches
 * the controller with a null identity. Every other endpoint under that flag can
 * still do something sensible; these cannot. There is no honest tenant to scope
 * to, so the answer is 401 rather than a guess — refusing is safe here because
 * nothing in the product read these endpoints before today, so nothing can break
 * by starting strict. That is the opposite trade-off from the content routes,
 * and deliberately so.
 *
 * WRITES ARE GATED TWICE: `perm:platform.<service>,update` on the route decides,
 * and the route is the authority. The controllers do not re-check — one gate,
 * declared where a reader can see it next to the verb.
 */
abstract class PlatformController extends Controller
{
    public function __construct(protected PlatformRegistry $registry)
    {
    }

    /**
     * The decoded JWT identity, or null in warn-only mode with no token.
     *
     * @return array<string,mixed>|null
     */
    protected function auth(Request $request): ?array
    {
        return $request->attributes->get('lms_auth');
    }

    /** The institute every read and write is scoped to, or null when unknown. */
    protected function tenantId(Request $request): ?int
    {
        $auth = $this->auth($request);
        $tenant = $auth['sub_institute_id'] ?? null;

        return is_numeric($tenant) && (int) $tenant > 0 ? (int) $tenant : null;
    }

    /**
     * The 401 body for a request with no usable identity.
     *
     * Says which of the two problems it is — no token at all, or a token with no
     * institute — because they need different fixes and "unauthenticated" sends
     * an administrator to the wrong one.
     */
    protected function unauthenticated(Request $request): JsonResponse
    {
        $message = $this->auth($request) === null
            ? 'Sign in again — this request carried no valid token.'
            : 'Your session has no institute. Reselect the institute and try again.';

        return response()->json([
            'status_code' => 0,
            'message' => $message,
            'data' => null,
        ], 401);
    }

    /**
     * Who is making the change, as "Priya Nair (4821)".
     *
     * Denormalised into every row on purpose: the audit column has to keep
     * reading correctly after the user is renamed, transferred or deactivated,
     * and a join that resolves to a deleted row shows a blank where an
     * accountable name should be. One small query per write is a fair price.
     */
    protected function actorLabel(Request $request): ?string
    {
        $auth = $this->auth($request);
        $userId = (int) ($auth['user_id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }

        $name = DB::table('tbluser')
            ->where('id', $userId)
            ->selectRaw('TRIM(CONCAT_WS(" ", first_name, last_name)) as full_name')
            ->value('full_name');

        $name = is_string($name) ? trim($name) : '';

        return $name !== '' ? "{$name} ({$userId})" : "User {$userId}";
    }

    /**
     * The module and component filter, validated against the registry.
     *
     * An unknown filter is a 404 rather than an empty list: a screen asking about
     * a module that does not exist has a bug, and answering "no rows" hides it.
     *
     * @return array{0:?string,1:?string}
     */
    protected function readScope(Request $request): array
    {
        $module = trim((string) $request->query('module', '')) ?: null;
        $component = trim((string) $request->query('component', '')) ?: null;

        if ($module !== null && ! $this->registry->hasModule($module)) {
            abort(response()->json([
                'status_code' => 0,
                'message' => "\"{$module}\" is not a known module.",
                'data' => null,
            ], 404));
        }

        if ($component !== null && ! $this->registry->hasComponent($component)) {
            abort(response()->json([
                'status_code' => 0,
                'message' => "\"{$component}\" is not a known component.",
                'data' => null,
            ], 404));
        }

        return [$module, $component];
    }

    /** @param mixed $data */
    protected function ok($data, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $data,
        ], $extra), $status);
    }

    protected function fail(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status_code' => 0,
            'message' => $message,
            'data' => null,
        ], $extra), $status);
    }
}
