<?php

namespace App\Http\Controllers\api\Platform;

use App\Models\Platform\PlatformWorkflow;
use App\Services\Platform\PlatformRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The Workflow service — the approval chains each component's actions run
 * through.
 *
 * A POINT IS NOT A CHAIN. config/platform_services.php declares the POINTS: the
 * places in each component where an action can pause for a sign-off, such as
 * `fees.concession.flow`. This controller manages the CHAINS a school defines
 * against those points, and a point may carry several — "Concession above
 * ₹10,000" with three steps and "Concession up to ₹10,000" with one are two
 * chains on the same point, told apart by their condition. The point is the
 * product's; the chain is the school's. That is the whole reason one is config
 * and the other is a table.
 *
 * `condition` IS NEVER EVALUATED HERE. It is stored exactly as the operator wrote
 * it and interpreted by the engine when a chain runs. A configuration endpoint
 * that quietly evaluated user-supplied expressions would be a code-execution
 * surface, and a nonsense condition should fail visibly in the engine rather than
 * be silently rewritten at save time.
 *
 * STATUS IS THREE-WAY ON PURPOSE. `draft` is being built and never runs;
 * `active` runs; `disabled` is kept but not running, so a school can switch a
 * chain off for a term without losing how it was set up. Deleting is the only
 * thing that loses work, which is why it is its own verb.
 *
 * WHAT IS NOT HERE: approvals in flight. Which record sits at which step, who
 * approved when and what they said is runtime, not configuration, and belongs in
 * its own table when the engine is built. Nothing in this controller is a record
 * of something that happened, which is exactly what makes these rows safe to
 * edit while work is in progress.
 */
class WorkflowController extends PlatformController
{
    /** A chain longer than this is a process problem, not a configuration one. */
    private const MAX_STEPS = 12;

    /**
     * GET /api/platform/workflow?module=&component=&flow_key=
     *
     * The workflow points in scope and the chains defined against them, so the
     * screen can show a point with no chain — which is most of them, and the
     * thing an administrator has come to fix.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        [$module, $component] = $this->readScope($request);

        $flowKey = trim((string) $request->query('flow_key', '')) ?: null;
        if ($flowKey !== null && $this->registry->workflowPoint($flowKey) === null) {
            return $this->fail("\"{$flowKey}\" is not a known workflow point.", 404);
        }

        $chains = PlatformWorkflow::forTenant($tenantId)
            ->forModule($module)
            ->forComponent($component)
            ->forFlow($flowKey)
            ->orderBy('flow_key')
            ->orderBy('id')
            ->get();

        $byFlow = $chains->groupBy('flow_key');

        $definitions = $this->registry->scope($this->registry->workflowPoints(), $module, $component);
        if ($flowKey !== null) {
            $definitions = array_intersect_key($definitions, [$flowKey => true]);
        }

        $points = [];
        foreach ($definitions as $key => $definition) {
            $componentKey = PlatformRegistry::componentOf($key);
            $flowChains = $byFlow->get($key, collect());

            $points[] = [
                'key' => $key,
                'module' => PlatformRegistry::moduleOf($key),
                'component' => $componentKey,
                'component_label' => $this->registry->components()[$componentKey]['label'] ?? $componentKey,
                'label' => $definition['label'] ?? $key,
                'description' => $definition['description'] ?? '',
                'subject' => $definition['subject'] ?? '',
                'suggested_steps' => $this->hydrateSteps((array) ($definition['suggested_steps'] ?? [])),
                'workflows' => $flowChains->map(fn (PlatformWorkflow $row) => $this->chainRow($row))->values()->all(),
            ];
        }

        return $this->ok([
            'points' => $points,
            'summary' => $this->summarise($points),
        ]);
    }

    /**
     * POST /api/platform/workflow
     *
     * Body: {"flow_key":"fees.concession.flow","name":"Concession above ₹10,000",
     *        "condition":"amount > 10000","status":"draft","steps":[...]}
     *
     * A NEW CHAIN DEFAULTS TO DRAFT even when the caller says nothing, so nothing
     * starts intercepting real records the moment somebody experiments with the
     * screen. Making it live is a separate, deliberate edit.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $flowKey = trim((string) $request->input('flow_key', ''));
        $definition = $this->registry->workflowPoint($flowKey);
        if ($definition === null) {
            return $this->fail('"'.($flowKey ?: '(none)').'" is not a known workflow point.', 404);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $name = (string) ($definition['label'] ?? 'Approval');
        }
        if (mb_strlen($name) > 150) {
            return $this->fail('Keep the name under 150 characters.');
        }

        $steps = $request->input('steps');
        if ($steps === null) {
            // Start from the registry's suggestion rather than an empty ladder:
            // a chain with no steps approves nothing and is the one shape that
            // is never what anybody meant.
            $steps = (array) ($definition['suggested_steps'] ?? []);
        }
        if (! is_array($steps)) {
            return $this->fail('steps must be a list.');
        }

        $validated = $this->validateSteps($steps);
        if (is_string($validated)) {
            return $this->fail($validated, 422);
        }

        $status = $this->readStatus($request, 'draft');
        if (is_array($status)) {
            return $this->fail($status['message'], 422);
        }

        $onReject = $this->readOnReject($request, 'return_to_requester');
        if (is_array($onReject)) {
            return $this->fail($onReject['message'], 422);
        }

        $actor = $this->actorLabel($request);

        $row = PlatformWorkflow::create([
            'sub_institute_id' => $tenantId,
            'flow_key' => $flowKey,
            'module' => PlatformRegistry::moduleOf($flowKey),
            'component' => PlatformRegistry::componentOf($flowKey),
            'name' => $name,
            'description' => (string) $request->input('description', ''),
            'status' => $status,
            'condition' => mb_substr(trim((string) $request->input('condition', '')), 0, 255),
            'steps' => $validated,
            'on_reject' => $onReject,
            'notify_requester' => $request->has('notify_requester')
                ? $request->boolean('notify_requester')
                : true,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        return $this->ok(['workflow' => $this->chainRow($row)], [], 201);
    }

    /**
     * PUT /api/platform/workflow/{id}
     *
     * Every field optional; absent means unchanged. `flow_key` is not editable —
     * a chain belongs to the point it was created against, and moving it would
     * silently change which action it governs. Delete and recreate instead.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        // Scoped by tenant, so another school's id reads as "not found" rather
        // than as "forbidden" — which would confirm the row exists.
        $row = PlatformWorkflow::forTenant($tenantId)->find($id);
        if ($row === null) {
            return $this->fail('That workflow no longer exists.', 404);
        }

        if ($request->has('name')) {
            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                return $this->fail('Give the workflow a name.');
            }
            if (mb_strlen($name) > 150) {
                return $this->fail('Keep the name under 150 characters.');
            }
            $row->name = $name;
        }

        if ($request->has('description')) {
            $row->description = (string) $request->input('description', '');
        }

        if ($request->has('condition')) {
            $row->condition = mb_substr(trim((string) $request->input('condition', '')), 0, 255);
        }

        if ($request->has('status')) {
            $status = $this->readStatus($request, $row->status);
            if (is_array($status)) {
                return $this->fail($status['message'], 422);
            }
            $row->status = $status;
        }

        if ($request->has('on_reject')) {
            $onReject = $this->readOnReject($request, $row->on_reject);
            if (is_array($onReject)) {
                return $this->fail($onReject['message'], 422);
            }
            $row->on_reject = $onReject;
        }

        if ($request->has('notify_requester')) {
            $row->notify_requester = $request->boolean('notify_requester');
        }

        if ($request->has('steps')) {
            $steps = $request->input('steps');
            if (! is_array($steps)) {
                return $this->fail('steps must be a list.');
            }
            $validated = $this->validateSteps($steps);
            if (is_string($validated)) {
                return $this->fail($validated, 422);
            }
            $row->steps = $validated;
        }

        // An active chain with no steps would approve everything it touched
        // without asking anybody. Refuse the combination however it is reached —
        // by emptying the steps or by activating an empty chain.
        if ($row->status === 'active' && count((array) $row->steps) === 0) {
            return $this->fail('An active workflow needs at least one step. Add a step, or leave it as a draft.', 422);
        }

        $row->updated_by = $this->actorLabel($request);
        $row->save();

        return $this->ok(['workflow' => $this->chainRow($row->refresh())]);
    }

    /**
     * DELETE /api/platform/workflow/{id}
     *
     * A hard delete. This row is policy, never a record of something that
     * happened, so there is no history to preserve — and a school that wants to
     * stop a chain without losing it has `disabled` for that.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return $this->unauthenticated($request);
        }

        $row = PlatformWorkflow::forTenant($tenantId)->find($id);
        if ($row === null) {
            return $this->fail('That workflow no longer exists.', 404);
        }

        $row->delete();

        return $this->ok(['deleted' => $id]);
    }

    // ── Validation ──────────────────────────────────────────────────────────

    /**
     * Validate and normalise a chain's steps.
     *
     * ORDER AND ID ARE ASSIGNED HERE, NOT ACCEPTED. The array order is what the
     * operator dragged into place, so `order` is derived from it — accepting a
     * client's `order` would let the two disagree, and then the ladder on screen
     * and the ladder that runs would be different ladders.
     *
     * @param  list<mixed>  $steps
     * @return list<array<string,mixed>>|string  the steps, or the problem
     */
    private function validateSteps(array $steps): array|string
    {
        if (count($steps) > self::MAX_STEPS) {
            return 'A workflow can have at most '.self::MAX_STEPS.' steps.';
        }

        $approverTypes = $this->registry->approverTypes();
        $escalations = $this->registry->escalationActions();

        $result = [];
        foreach (array_values($steps) as $index => $step) {
            $position = $index + 1;

            if (! is_array($step)) {
                return "Step {$position} is not an object.";
            }

            $name = trim((string) ($step['name'] ?? ''));
            if ($name === '') {
                return "Step {$position} needs a name.";
            }
            if (mb_strlen($name) > 120) {
                return "Step {$position}: keep the name under 120 characters.";
            }

            $type = trim((string) ($step['approver_type'] ?? ''));
            if (! array_key_exists($type, $approverTypes)) {
                return "Step {$position}: \"".($type ?: '(none)')."\" is not an approver type.";
            }

            $approver = trim((string) ($step['approver'] ?? ''));
            if (($approverTypes[$type]['needs_value'] ?? false) && $approver === '') {
                return "Step {$position}: choose who approves it.";
            }
            // The derived types resolve from the record at run time, so a value
            // stored against one would be read by nobody and believed by someone.
            if (! ($approverTypes[$type]['needs_value'] ?? false)) {
                $approver = '';
            }

            $sla = $step['sla_hours'] ?? 0;
            if (! is_numeric($sla) || (int) $sla < 0) {
                return "Step {$position}: the SLA must be a whole number of hours, 0 or more.";
            }
            // 90 days. Beyond that an SLA is not a service level, it is a way of
            // never admitting the step is stuck.
            if ((int) $sla > 2160) {
                return "Step {$position}: an SLA cannot be longer than 2160 hours (90 days).";
            }

            $onBreach = trim((string) ($step['on_breach'] ?? 'none'));
            if (! array_key_exists($onBreach, $escalations)) {
                return "Step {$position}: \"{$onBreach}\" is not an escalation action.";
            }
            if ($onBreach !== 'none' && (int) $sla === 0) {
                return "Step {$position}: set an SLA, or there is nothing for \"".$escalations[$onBreach]['label']."\" to happen after.";
            }

            $result[] = [
                // Stable across edits so the screen can key its rows, and so a
                // future engine can point at the step a record is sitting on.
                'id' => trim((string) ($step['id'] ?? '')) ?: 'stp_'.Str::lower(Str::random(10)),
                'order' => $position,
                'name' => $name,
                'approver_type' => $type,
                'approver' => mb_substr($approver, 0, 120),
                'sla_hours' => (int) $sla,
                'on_breach' => $onBreach,
                'allow_delegate' => (bool) ($step['allow_delegate'] ?? false),
                'require_comment' => (bool) ($step['require_comment'] ?? false),
            ];
        }

        return $result;
    }

    /** @return string|array{message:string} */
    private function readStatus(Request $request, string $fallback): string|array
    {
        $status = trim((string) $request->input('status', $fallback));
        if (! in_array($status, ['draft', 'active', 'disabled'], true)) {
            return ['message' => "\"{$status}\" is not a workflow status. Use draft, active or disabled."];
        }

        return $status;
    }

    /** @return string|array{message:string} */
    private function readOnReject(Request $request, string $fallback): string|array
    {
        $value = trim((string) $request->input('on_reject', $fallback));
        if (! in_array($value, ['return_to_requester', 'close'], true)) {
            return ['message' => "\"{$value}\" is not a rejection outcome. Use return_to_requester or close."];
        }

        return $value;
    }

    // ── Reading ─────────────────────────────────────────────────────────────

    /**
     * Fill a registry `suggested_steps` entry out to a full step.
     *
     * The config writes only what varies — name, approver, SLA — so the screen
     * receives a complete step it can render with the same component it uses for
     * a saved one.
     *
     * @param  list<array<string,mixed>>  $steps
     * @return list<array<string,mixed>>
     */
    private function hydrateSteps(array $steps): array
    {
        $result = [];
        foreach (array_values($steps) as $index => $step) {
            $result[] = [
                'id' => 'sug_'.($index + 1),
                'order' => $index + 1,
                'name' => (string) ($step['name'] ?? ''),
                'approver_type' => (string) ($step['approver_type'] ?? 'role'),
                'approver' => (string) ($step['approver'] ?? ''),
                'sla_hours' => (int) ($step['sla_hours'] ?? 0),
                'on_breach' => (string) ($step['on_breach'] ?? 'remind'),
                'allow_delegate' => (bool) ($step['allow_delegate'] ?? true),
                'require_comment' => (bool) ($step['require_comment'] ?? false),
            ];
        }

        return $result;
    }

    private function chainRow(PlatformWorkflow $row): array
    {
        $steps = (array) $row->steps;

        return [
            'id' => (int) $row->id,
            'flow_key' => $row->flow_key,
            'module' => $row->module,
            'component' => $row->component,
            'name' => $row->name,
            'description' => (string) $row->description,
            'status' => $row->status,
            'condition' => (string) $row->condition,
            'steps' => array_values($steps),
            'step_count' => count($steps),
            // The chain's total SLA, which is the number a principal asks about
            // ("how long can this take?") and which no single step shows.
            'total_sla_hours' => array_sum(array_map(
                static fn ($step) => (int) ($step['sla_hours'] ?? 0),
                $steps
            )),
            'on_reject' => $row->on_reject,
            'notify_requester' => (bool) $row->notify_requester,
            'created_at' => $row->created_at?->toIso8601String(),
            'created_by' => $row->created_by,
            'updated_at' => $row->updated_at?->toIso8601String(),
            'updated_by' => $row->updated_by,
        ];
    }

    /** @param list<array<string,mixed>> $points */
    private function summarise(array $points): array
    {
        $governed = 0;
        $active = 0;
        $draft = 0;
        $chains = 0;

        foreach ($points as $point) {
            $pointHasActive = false;
            foreach ($point['workflows'] as $chain) {
                $chains++;
                if ($chain['status'] === 'active') {
                    $active++;
                    $pointHasActive = true;
                }
                if ($chain['status'] === 'draft') {
                    $draft++;
                }
            }
            if ($pointHasActive) {
                $governed++;
            }
        }

        return [
            'points' => count($points),
            // Points with at least one ACTIVE chain. A point whose only chain is
            // a draft is not governed, and counting it as such is the flattering
            // number that hides the gap.
            'governed' => $governed,
            'workflows' => $chains,
            'active' => $active,
            'draft' => $draft,
        ];
    }
}
