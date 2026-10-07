<?php

namespace App\Http\Controllers\api\Platform;

use App\Models\Platform\PlatformAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shared audit trail — read side only.
 *
 * NO WRITE VERB EXISTS: the trail is append-only and is written exclusively by
 * App\Services\Platform\AuditTrail::record() from inside other modules' own write
 * paths. A route that created, edited or deleted an entry would let a caller
 * rewrite history, so none is offered.
 *
 * TENANCY comes from the verified token, never from input. Gated on
 * `perm:platform.audit,view` in routes/platform/audit_integrations.php.
 */
class AuditController extends PlatformController
{
    /**
     * GET /api/platform/audit
     *
     * Filters: module, component, action, actor (user id, or part of the name),
     * from, to (dates, inclusive), q (free text over entity, actor and action).
     * Newest first. page / per_page (default 25, max 100).
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $query = $this->filtered($request, $tenantId);
        if ($query instanceof JsonResponse) {
            return $query;
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $page = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);

        $rows = collect($page->items())->map(fn (PlatformAuditLog $row) => [
            'id' => (int) $row->id,
            'module' => $row->module,
            'component' => $row->component,
            'action' => $row->action,
            'entity_type' => $row->entity_type,
            'entity_id' => $row->entity_id,
            'actor_user_id' => $row->actor_user_id,
            'actor_name' => $row->actor_name,
            'before' => $row->before_json !== null ? json_decode($row->before_json, true) : null,
            'after' => $row->after_json !== null ? json_decode($row->after_json, true) : null,
            'ip' => $row->ip,
            'is_sample' => (bool) $row->is_sample,
            'created_at' => $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->toIso8601String() : null,
        ])->all();

        return $this->ok($rows, [
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/platform/audit/summary — counts by module and by action over the
     * same filter window (the module/action filters themselves are honoured too,
     * so the summary always describes exactly what the list shows).
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $base = $this->filtered($request, $tenantId);
        if ($base instanceof JsonResponse) {
            return $base;
        }

        $byModule = (clone $base)->selectRaw('module, COUNT(*) as total')
            ->groupBy('module')->orderByDesc('total')->pluck('total', 'module');
        $byAction = (clone $base)->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')->orderByDesc('total')->pluck('total', 'action');

        return $this->ok([
            'total' => (int) $byModule->sum(),
            'by_module' => $byModule->map(fn ($n) => (int) $n)->all(),
            'by_action' => $byAction->map(fn ($n) => (int) $n)->all(),
            'sample_rows' => (int) (clone $base)->where('is_sample', 1)->count(),
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Builder|JsonResponse */
    private function filtered(Request $request, int $tenantId)
    {
        $query = PlatformAuditLog::forTenant($tenantId);

        foreach (['module', 'component', 'action'] as $field) {
            $value = trim((string) $request->query($field, ''));
            if ($value !== '') {
                $query->where($field, $value);
            }
        }

        $actor = trim((string) $request->query('actor', ''));
        if ($actor !== '') {
            if (ctype_digit($actor)) {
                $query->where('actor_user_id', (int) $actor);
            } else {
                $query->where('actor_name', 'like', '%'.$this->escapeLike($actor).'%');
            }
        }

        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        try {
            if ($from !== '') {
                $query->where('created_at', '>=', \Illuminate\Support\Carbon::parse($from)->startOfDay());
            }
            if ($to !== '') {
                $query->where('created_at', '<=', \Illuminate\Support\Carbon::parse($to)->endOfDay());
            }
        } catch (\Throwable $e) {
            return $this->fail('from and to must be dates, for example 2026-10-01.', 422);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%'.$this->escapeLike($q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('entity_type', 'like', $like)
                    ->orWhere('entity_id', 'like', $like)
                    ->orWhere('actor_name', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('component', 'like', $like);
            });
        }

        return $query;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
