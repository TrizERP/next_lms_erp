<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Audit-log viewer for the alumni module (spec section 40): who did what, to which record, and when.
 *
 *   GET alumni/audit            ?component= &action= &q= (actor or record) &from= &to= &page= &per_page=
 *   GET alumni/audit/{id}       one entry with its before / after detail
 *
 * Read-only, and behind the `audit` gate (Admin-type profiles): the log includes donation amounts, export filters and who
 * verified what, which is more than the `manage` role needs. It only ever shows this institute's alumni entries; the log
 * itself is written by AuditTrail and nothing here can alter or delete it. Entries older than the table's own retention are
 * simply not there - this class does not purge anything.
 */
class AlumniAuditApiController extends Controller
{
    use AlumniApiSupport;

    private const DETAIL_LIMIT = 4000;

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request, 'audit')) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'component' => 'nullable|string|max:128', 'action' => 'nullable|string|max:64', 'q' => 'nullable|string|max:120',
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $base = fn () => DB::table('platform_audit_log')->where('sub_institute_id', $t)->where('module', 'alumni');
        $q = $base()->when($request->input('component'), fn ($x, $c) => $x->where('component', $c))->when($request->input('action'), fn ($x, $a) => $x->where('action', $a))
            ->when($request->input('from'), fn ($x, $d) => $x->where('created_at', '>=', $d . ' 00:00:00'))->when($request->input('to'), fn ($x, $d) => $x->where('created_at', '<=', $d . ' 23:59:59'));
        if ($request->filled('q')) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))) . '%';
            $q->where(fn ($w) => $w->where('actor_name', 'like', $like)->orWhere('entity_id', 'like', $like)->orWhere('entity_type', 'like', $like));
        }
        $total = (clone $q)->count();
        $per = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->orderByDesc('id')->forPage($page, $per)->get(['id', 'created_at', 'actor_user_id', 'actor_name', 'component', 'action', 'entity_type', 'entity_id']);

        return $this->ok([
            'rows' => $rows, 'components' => $base()->distinct()->orderBy('component')->pluck('component'), 'actions' => $base()->distinct()->orderBy('action')->pluck('action'),
            'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request, 'audit')) {
            return $denied;
        }
        $row = DB::table('platform_audit_log')->where('sub_institute_id', $this->tenant($request))->where('module', 'alumni')->where('id', (int) $id)->first();
        if (! $row) {
            return $this->notFound('Audit entry');
        }
        $decode = function (?string $json) {
            if ($json === null || $json === '') {
                return null;
            }

            return strlen($json) > self::DETAIL_LIMIT ? ['truncated' => true, 'preview' => mb_substr($json, 0, self::DETAIL_LIMIT)] : json_decode($json, true);
        };

        return $this->ok([
            'id' => (int) $row->id, 'created_at' => $row->created_at, 'actor_user_id' => $row->actor_user_id, 'actor_name' => $row->actor_name, 'ip' => $row->ip,
            'component' => $row->component, 'action' => $row->action, 'entity_type' => $row->entity_type, 'entity_id' => $row->entity_id,
            'before' => $decode($row->before_json), 'after' => $decode($row->after_json),
        ]);
    }
}
