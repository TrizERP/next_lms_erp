<?php

namespace App\Services\Platform;

use App\Models\Platform\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one way any module writes to the shared audit trail.
 *
 *   AuditTrail::record($tenantId, 'fees', 'fees.concession', 'approve',
 *       ['entity_type' => 'concession', 'entity_id' => 42,
 *        'before' => [...], 'after' => [...]], $request);
 *
 * APPEND-ONLY: there is deliberately no update or delete method here.
 *
 * NEVER BREAKS THE CALLER: an audit write that fails is logged and swallowed, so a
 * missing table cannot turn a successful save into a 500. The return value (the
 * new id, or null) says whether it landed.
 *
 * Callers must not pass credentials in before/after. As a backstop any key that
 * looks like a secret is replaced with '********' before storage.
 */
class AuditTrail
{
    private const SECRET_PATTERN = '/(password|passwd|secret|token|api_?key|private_?key|salt|credential)/i';

    /**
     * @param  array{entity_type?:?string,entity_id?:int|string|null,before?:mixed,after?:mixed,is_sample?:bool,actor_user_id?:?int,actor_name?:?string,created_at?:mixed}  $detail
     */
    public static function record(
        int $tenantId,
        string $module,
        ?string $component,
        string $action,
        array $detail = [],
        ?Request $request = null
    ): ?int {
        try {
            $auth = $request?->attributes->get('lms_auth');
            // `lms_auth` is set by LmsApiAuth only. Routes behind `api.session` (the alumni module among them) carry the verified
            // JWT user in the hydrated session instead, so fall back to that - otherwise every entry from those routes has no actor.
            $userId = $detail['actor_user_id']
                ?? (isset($auth['user_id']) && is_numeric($auth['user_id']) ? (int) $auth['user_id'] : self::sessionUserId($request));
            $actorName = $detail['actor_name'] ?? self::nameFor($userId);

            $row = PlatformAuditLog::create([
                'sub_institute_id' => $tenantId,
                'module' => mb_substr($module, 0, 64),
                'component' => $component !== null ? mb_substr($component, 0, 128) : null,
                'action' => mb_substr($action, 0, 64),
                'entity_type' => isset($detail['entity_type']) ? mb_substr((string) $detail['entity_type'], 0, 128) : null,
                'entity_id' => isset($detail['entity_id']) ? mb_substr((string) $detail['entity_id'], 0, 128) : null,
                'actor_user_id' => $userId,
                'actor_name' => $actorName,
                'before_json' => self::encode($detail['before'] ?? null),
                'after_json' => self::encode($detail['after'] ?? null),
                'ip' => $request?->ip(),
                'is_sample' => ! empty($detail['is_sample']) ? 1 : 0,
                'created_at' => $detail['created_at'] ?? now(),
            ]);

            return (int) $row->id;
        } catch (\Throwable $e) {
            Log::warning('AuditTrail::record failed: '.$e->getMessage());

            return null;
        }
    }

    private static function nameFor(?int $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        // joined in PHP, not with CONCAT_WS: that function does not exist in sqlite, where a failure here would silently drop the whole audit row
        try {
            $u = DB::table('tbluser')->where('id', $userId)->first(['first_name', 'last_name']);
        } catch (\Throwable $e) {
            $u = null;   // a name is a convenience: failing to look it up must never lose the audit row
        }
        $name = $u ? trim(trim((string) ($u->first_name ?? '')) . ' ' . trim((string) ($u->last_name ?? ''))) : '';

        return $name !== '' ? $name : "User {$userId}";
    }

    /** The verified user of an `api.session` request (the JWT's user_id), or null when there is none. */
    private static function sessionUserId(?Request $request): ?int
    {
        if ($request === null || ! $request->hasSession()) {
            return null;
        }
        $id = $request->session()->get('user_id');

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private static function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode(self::scrub($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? null : $json;
    }

    private static function scrub(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = is_string($k) && preg_match(self::SECRET_PATTERN, $k) ? '********' : self::scrub($v);
        }

        return $out;
    }
}
