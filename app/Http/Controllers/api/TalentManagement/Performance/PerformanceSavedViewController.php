<?php

namespace App\Http\Controllers\api\TalentManagement\Performance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Performance\Concerns\ResolvesPerformanceContext;
use App\Models\AuditLog;
use App\Models\TalentManagement\PerformanceSavedView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Performance\PerformanceSavedViewController`.
 *
 * The "Saved Views" dropdown: named filter presets per tab.
 *
 * Server-side by design (user decision) so a preset follows the user across
 * devices and can be shared with the team via `is_shared`.
 *
 * `user_id` IS the owner here - a saved view belongs to whoever created it, so
 * the context actor is the correct owner. This is the deliberate exception to the
 * `user_id_target` rule; every other performance controller takes its subject
 * from the payload or the route.
 */
class PerformanceSavedViewController extends Controller
{
    use ResolvesPerformanceContext;

    /**
     * GET /api/performance/saved-views
     * Returns the caller's own views plus any view shared by a colleague.
     */
    public function index(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $actorId = $context['user_id'];
        $tab = $this->activePerfFilter($request->input('tab'));

        $rows = DB::table('s_performance_saved_views as v')
            ->leftJoin('tbluser as owner', 'owner.id', '=', 'v.user_id')
            ->where('v.sub_institute_id', $tenant)
            ->whereNull('v.deleted_at')
            ->when($tab, fn ($q) => $q->where('v.tab', $tab))
            ->where(function ($q) use ($actorId) {
                $q->where('v.is_shared', 1);

                if ($actorId) {
                    $q->orWhere('v.user_id', $actorId);
                }
            })
            ->orderByDesc('v.is_default')
            ->orderBy('v.name')
            ->get([
                'v.*',
                'owner.first_name as owner_first_name',
                'owner.last_name as owner_last_name',
                'owner.user_name as owner_user_name',
            ]);

        return $this->performanceResponse(
            $rows->map(fn ($row) => $this->present($row, $actorId))->all()
        );
    }

    /** POST /api/performance/saved-views */
    public function store(Request $request)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];

        if (!$context['user_id']) {
            return $this->performanceError('user_id is required to own a saved view', 400);
        }

        $validated = $request->validate([
            'name'       => 'required|string|max:191',
            'tab'        => 'nullable|in:goals,reviews,appraisals,compensation,bonus,calibration',
            'filters'    => 'nullable|array',
            'is_shared'  => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $tab = $validated['tab'] ?? 'reviews';

        $duplicate = PerformanceSavedView::where('sub_institute_id', $tenant)
            ->where('user_id', $context['user_id'])
            ->where('tab', $tab)
            ->where('name', $validated['name'])
            ->exists();

        if ($duplicate) {
            return $this->performanceError('You already have a saved view with this name on this tab', 422);
        }

        // Only one default per user per tab, otherwise the dropdown has no
        // single answer to "which view opens first".
        if (!empty($validated['is_default'])) {
            $this->clearDefault($tenant, (int) $context['user_id'], $tab);
        }

        $view = PerformanceSavedView::create([
            'sub_institute_id' => $tenant,
            'user_id'          => $context['user_id'],
            'name'             => $validated['name'],
            'tab'              => $tab,
            'filters'          => $validated['filters'] ?? [],
            'is_shared'        => (bool) ($validated['is_shared'] ?? false),
            'is_default'       => (bool) ($validated['is_default'] ?? false),
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]);

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'created_saved_view',
            'saved the view "' . $view->name . '" on the ' . $tab . ' tab',
            'saved_view',
            $view->id,
            $view->name
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'saved_view_created',
            'entity_type' => 'performance_saved_view',
            'entity_id'   => $view->id,
            'new_values'  => [
                'name'       => $view->name,
                'tab'        => $view->tab,
                'is_shared'  => (bool) $view->is_shared,
                'is_default' => (bool) $view->is_default,
            ],
        ]);

        return $this->performanceResponse($this->presentModel($view, $context['user_id']), 'View saved', 201);
    }

    /** PUT /api/performance/saved-views/{id} */
    public function update(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $view = PerformanceSavedView::where('sub_institute_id', $tenant)->find($id);

        if (!$view) {
            return $this->performanceError('Saved view not found', 404);
        }

        // A shared view is readable by everyone but editable only by its owner.
        if ($context['user_id'] && (int) $view->user_id !== (int) $context['user_id']) {
            return $this->performanceError('Only the owner can change this saved view', 403);
        }

        $validated = $request->validate([
            'name'       => 'sometimes|required|string|max:191',
            'filters'    => 'nullable|array',
            'is_shared'  => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $changes = $this->diffPerformanceChanges(
            $view->getOriginal(),
            $validated,
            ['name' => 'Name', 'is_shared' => 'Shared', 'is_default' => 'Default']
        );

        if (!empty($validated['is_default'])) {
            $this->clearDefault($tenant, (int) $view->user_id, (string) $view->tab, (int) $view->id);
        }

        $view->fill($validated);
        $view->updated_by = $context['user_id'];
        $view->save();

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'saved_view_updated',
            'entity_type' => 'performance_saved_view',
            'entity_id'   => $view->id,
            'new_values'  => $validated,
        ]);

        if (!empty($changes)) {
            $this->logPerformanceActivity(
                $tenant,
                $context['user_id'],
                'updated_saved_view',
                'updated the saved view "' . $view->name . '"',
                'saved_view',
                $view->id,
                $view->name,
                $changes
            );
        }

        return $this->performanceResponse($this->presentModel($view, $context['user_id']), 'Saved view updated');
    }

    /** DELETE /api/performance/saved-views/{id} */
    public function destroy(Request $request, $id)
    {
        $context = $this->performanceContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $tenant = $context['sub_institute_id'];
        $view = PerformanceSavedView::where('sub_institute_id', $tenant)->find($id);

        if (!$view) {
            return $this->performanceError('Saved view not found', 404);
        }

        if ($context['user_id'] && (int) $view->user_id !== (int) $context['user_id']) {
            return $this->performanceError('Only the owner can delete this saved view', 403);
        }

        $name = $view->name;

        $view->deleted_by = $context['user_id'];
        $view->save();
        $view->delete();

        $this->logPerformanceActivity(
            $tenant,
            $context['user_id'],
            'deleted_saved_view',
            'deleted the saved view "' . $name . '"',
            'saved_view',
            (int) $id,
            $name
        );

        AuditLog::record([
            'module'      => 'talent_management',
            'action'      => 'saved_view_deleted',
            'entity_type' => 'performance_saved_view',
            'entity_id'   => (int) $id,
            'new_values'  => ['name' => $name],
        ]);

        return $this->performanceResponse(['id' => (int) $id], 'Saved view deleted');
    }

    /* ------------------------------------------------------------------ */

    private function clearDefault(int $tenant, int $userId, string $tab, ?int $exceptId = null): void
    {
        DB::table('s_performance_saved_views')
            ->where('sub_institute_id', $tenant)
            ->where('user_id', $userId)
            ->where('tab', $tab)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_default' => 0, 'updated_at' => now()]);
    }

    private function present($row, ?int $actorId): array
    {
        $ownerName = trim(($row->owner_first_name ?? '') . ' ' . ($row->owner_last_name ?? ''));
        $ownerName = $ownerName !== '' ? $ownerName : ($row->owner_user_name ?? null);

        $filters = is_string($row->filters) ? json_decode($row->filters, true) : $row->filters;

        return [
            'id'          => (int) $row->id,
            'name'        => $row->name,
            'tab'         => $row->tab,
            'filters'     => is_array($filters) ? $filters : [],
            'is_shared'   => (bool) $row->is_shared,
            'is_default'  => (bool) $row->is_default,
            'user_id'     => (int) $row->user_id,
            'owner'       => $ownerName,
            'is_mine'     => $actorId !== null && (int) $row->user_id === $actorId,
            'created_at'  => $row->created_at,
        ];
    }

    private function presentModel(PerformanceSavedView $view, ?int $actorId): array
    {
        $row = DB::table('s_performance_saved_views as v')
            ->leftJoin('tbluser as owner', 'owner.id', '=', 'v.user_id')
            ->where('v.id', $view->id)
            ->first([
                'v.*',
                'owner.first_name as owner_first_name',
                'owner.last_name as owner_last_name',
                'owner.user_name as owner_user_name',
            ]);

        return $this->present($row, $actorId);
    }
}
