<?php

namespace App\Http\Controllers\Brain;

use App\Brain\Intelligence\ExecutionCenter;
use App\Brain\Intelligence\Uuid;
use App\Brain\Support\SchemaCache;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Execution Center: what was done about approved decisions, what changed, and what proves it.
 *
 * The tenant is taken from the verified session (`brain.tenant`), never from the request body, and
 * every query in ExecutionCenter is scoped by it. `show` and `linkEvidence` look the execution up
 * by (tenant, id), so another institute's execution id is a 404, not a leak.
 */
class BrainExecutionCenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $center = new ExecutionCenter($this->tenant($request));

        return response()->json($center->build([
            'page' => $request->query('page'),
            'perPage' => $request->query('perPage'),
            'status' => $request->query('status'),
            'sample' => $request->query('sample'),
            'q' => $request->query('q'),
        ]));
    }

    public function show(Request $request, string $tenantId, string $id): JsonResponse
    {
        $record = (new ExecutionCenter($this->tenant($request)))->find($id);

        return $record === null
            ? response()->json(['error' => 'execution_not_found'], 404)
            : response()->json($record);
    }

    /**
     * Link evidence to an execution: a person asserting "this shows the action was carried out".
     *
     * Narrow on purpose. Only evidence already attached to the SAME finding that produced the
     * execution can be linked (so an arbitrary evidence id from another case or institute is
     * refused), the link is idempotent, and nothing is created, inferred or verified automatically.
     * This is the missing writer for hpbrain_eso_execution_evidence: until now nothing in the
     * application ever inserted into it.
     */
    public function linkEvidence(Request $request, string $tenantId, string $id): JsonResponse
    {
        $tenant = $this->tenant($request);

        $v = Validator::make($request->all(), [
            'evidence_ids' => 'required|array|min:1|max:20',
            'evidence_ids.*' => 'required|string|max:36',
        ]);
        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }
        if (! SchemaCache::hasTable('hpbrain_eso_execution_evidence')) {
            return response()->json(['error' => 'brain_schema_missing', 'table' => 'hpbrain_eso_execution_evidence'], 503);
        }

        $center = new ExecutionCenter($tenant);
        $record = $center->find($id);
        if ($record === null) {
            return response()->json(['error' => 'execution_not_found'], 404);
        }

        $allowed = array_column($center->linkableEvidence($id), 'id');
        $requested = array_values(array_unique(array_map('strval', (array) $request->input('evidence_ids'))));
        $refused = array_values(array_diff($requested, $allowed));
        if ($refused !== []) {
            return response()->json(['error' => 'evidence_not_linkable', 'refused' => $refused,
                'message' => 'Only evidence from the finding that led to this execution can be linked to it.'], 422);
        }

        $now = now()->format('Y-m-d H:i:s');
        $linked = 0;
        DB::transaction(function () use ($tenant, $id, $requested, $now, &$linked) {
            foreach ($requested as $evidenceId) {
                $exists = DB::table('hpbrain_eso_execution_evidence')->where('tenant_id', $tenant)
                    ->where('execution_id', $id)->where('evidence_id', $evidenceId)->exists();
                if ($exists) {
                    continue;
                }
                DB::table('hpbrain_eso_execution_evidence')->insert([
                    'tenant_id' => $tenant, 'execution_id' => $id, 'evidence_id' => $evidenceId, 'linked_date' => $now,
                ]);
                $linked++;
            }
        });

        $this->audit($request, $tenant, 'execution.evidence_linked', $id, ['evidenceIds' => $requested, 'newlyLinked' => $linked]);

        return response()->json(['executionId' => $id, 'linked' => $linked, 'alreadyLinked' => count($requested) - $linked]);
    }

    private function tenant(Request $request): string
    {
        return (string) $request->attributes->get('tenantId', $request->attributes->get('auth.tenantId'));
    }

    private function audit(Request $request, string $tenant, string $action, string $entityId, array $changes): void
    {
        try {
            if (! SchemaCache::hasTable('hpbrain_audit_logs')) {
                return;
            }
            DB::table('hpbrain_audit_logs')->insert(SchemaCache::only('hpbrain_audit_logs', [
                'id' => Uuid::v4(),
                'tenant_id' => $tenant,
                'org_id' => 'org-'.$tenant,
                'entity_type' => 'Execution',
                'entity_id' => substr($entityId, 0, 36),
                'action' => $action,
                'actor_id' => (string) $request->attributes->get('auth.userId'),
                'actor_name' => (string) $request->attributes->get('auth.role'),
                'changes' => json_encode($changes),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'source' => 'lms',
                'status' => 'success',
                'created_at' => now(),
            ]));
        } catch (\Throwable) {
            // An audit failure must never undo the link the person just made.
        }
    }
}
