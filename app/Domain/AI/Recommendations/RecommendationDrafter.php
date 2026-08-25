<?php

namespace App\Domain\AI\Recommendations;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\Governance\GovernanceReport;
use App\Domain\Governance\GovernanceValidator;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drafts recommendations — and never does more than draft them.
 *
 * The method is called `draft` rather than `create` on purpose. Whatever an agent
 * concludes, what lands in the table is a proposal in `pending_approval`, waiting on
 * a person. There is no code path here that produces an approved recommendation;
 * approval is DecisionGate's job and requires a human user id.
 *
 * A draft that fails governance is still written, in `draft` status with the report
 * attached, so a reviewer can see what was proposed and why it was refused.
 */
class RecommendationDrafter
{
    public function __construct(
        private readonly GovernanceValidator $governance,
        private readonly AiAuditLogger $audit,
    ) {
    }

    /**
     * @param  array{
     *   case_id:int, explanation_id?:int|null, action_type:string, title:string,
     *   body?:string|null, rationale?:string|null, subject_entity_key:string,
     *   subject_id:int|string, confidence?:float, risk_level?:string,
     *   is_consequential?:bool, evidence_ids?:array, eso_binding?:array,
     *   workflow_key?:string|null, workflow_payload?:array|null, domain?:string
     * }  $draft
     * @param  array<int, string>  $authorizedWorkflowKeys
     * @return array{id:int|null, governance:GovernanceReport, status:string}
     */
    public function draft(
        array $draft,
        McpRequestContext $context,
        array $authorizedWorkflowKeys = [],
        ?int $runId = null
    ): array {
        $report = $this->governance->validateRecommendation($draft, $context, $authorizedWorkflowKeys);
        $normalized = $this->governance->recommend()->normalizeForPersistence($draft, $report);

        $id = $this->persist($draft, $normalized, $context, $runId);

        if ($report->passed) {
            $this->audit->record(AiAuditLogger::RECOMMENDATION_DRAFTED, $context, [
                'actor_type' => 'system',
                'related_type' => 'ai_recommendations',
                'related_id' => $id,
                'subject_entity_key' => $draft['subject_entity_key'] ?? null,
                'subject_id' => $draft['subject_id'] ?? null,
                'message' => mb_substr((string) ($draft['title'] ?? ''), 0, 200),
                'payload' => [
                    'case_id' => $draft['case_id'] ?? null,
                    'action_type' => $draft['action_type'] ?? null,
                    'risk_level' => $draft['risk_level'] ?? 'low',
                    'status' => $normalized['status'],
                    'workflow_key' => $draft['workflow_key'] ?? null,
                ],
            ]);
        } else {
            $this->audit->recordRejection(
                $report->reason() ?? 'Recommendation failed governance validation.',
                $context,
                [
                    'related_type' => 'ai_recommendations',
                    'related_id' => $id,
                    'subject_entity_key' => $draft['subject_entity_key'] ?? null,
                    'subject_id' => $draft['subject_id'] ?? null,
                    'payload' => [
                        'violations' => $report->violations,
                        'case_id' => $draft['case_id'] ?? null,
                    ],
                ]
            );
        }

        return ['id' => $id, 'governance' => $report, 'status' => $normalized['status']];
    }

    public function find(int $recommendationId, McpRequestContext $context): ?array
    {
        if (! Schema::hasTable('ai_recommendations')) {
            return null;
        }

        $row = DB::table('ai_recommendations')
            ->where('id', $recommendationId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->first();

        return $row ? $this->hydrate($row) : null;
    }

    public function forCase(int $caseId, McpRequestContext $context): array
    {
        if (! Schema::hasTable('ai_recommendations')) {
            return [];
        }

        return DB::table('ai_recommendations')
            ->where('case_id', $caseId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => $this->hydrate($row))
            ->all();
    }

    /**
     * The approval queue: what is waiting on a human right now.
     */
    public function pendingApproval(McpRequestContext $context, int $limit = 50): array
    {
        if (! Schema::hasTable('ai_recommendations')) {
            return [];
        }

        return DB::table('ai_recommendations')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('status', 'pending_approval')
            ->where('governance_passed', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('confidence')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row))
            ->all();
    }

    /**
     * Supersede earlier open drafts for the same subject and action, so a teacher's
     * queue shows the current proposal rather than a week of accumulated ones.
     */
    public function supersedePrevious(
        string $subjectEntityKey,
        int|string $subjectId,
        string $actionType,
        int $keepRecommendationId,
        McpRequestContext $context
    ): int {
        if (! Schema::hasTable('ai_recommendations')) {
            return 0;
        }

        return DB::table('ai_recommendations')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('subject_entity_key', $subjectEntityKey)
            ->where('subject_id', $subjectId)
            ->where('action_type', $actionType)
            ->where('id', '!=', $keepRecommendationId)
            ->whereIn('status', ['draft', 'pending_approval'])
            ->update(['status' => 'superseded', 'updated_at' => now()]);
    }

    private function persist(
        array $draft,
        array $normalized,
        McpRequestContext $context,
        ?int $runId
    ): ?int {
        if (! Schema::hasTable('ai_recommendations')) {
            return null;
        }

        return (int) DB::table('ai_recommendations')->insertGetId([
            'recommendation_reference' => $this->nextReference(),
            'case_id' => $draft['case_id'] ?? null,
            'explanation_id' => $draft['explanation_id'] ?? null,
            'domain' => $draft['domain'] ?? 'k12',
            'action_type' => (string) ($draft['action_type'] ?? 'unspecified'),
            'title' => mb_substr((string) ($draft['title'] ?? 'Recommendation'), 0, 300),
            'body' => $draft['body'] ?? null,
            'rationale' => $draft['rationale'] ?? null,
            'subject_entity_key' => $draft['subject_entity_key'] ?? 'student',
            'subject_id' => $draft['subject_id'] ?? 0,
            'confidence' => $draft['confidence'] ?? null,
            'risk_level' => strtolower((string) ($draft['risk_level'] ?? 'low')),
            'is_consequential' => $normalized['is_consequential'],
            'requires_approval' => $normalized['requires_approval'],
            'verb' => $normalized['verb'],
            'evidence_ids' => $normalized['evidence_ids'],
            'eso_binding' => isset($draft['eso_binding']) ? json_encode($draft['eso_binding']) : null,
            'governance_passed' => $normalized['governance_passed'],
            'governance_report' => $normalized['governance_report'],
            'workflow_key' => $draft['workflow_key'] ?? null,
            'workflow_payload' => isset($draft['workflow_payload']) ? json_encode($draft['workflow_payload']) : null,
            'status' => $normalized['status'],
            'expires_at' => $draft['expires_at'] ?? null,
            'created_by_run_id' => $runId,
            'sub_institute_id' => $context->selectedInstituteId,
            'client_id' => $context->clientId,
            'academic_year' => $context->academicYear,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nextReference(): string
    {
        $prefix = sprintf('REC-%d-', now()->year);

        $last = DB::table('ai_recommendations')
            ->where('recommendation_reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('recommendation_reference');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function hydrate(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'reference' => $row->recommendation_reference,
            'case_id' => $row->case_id ? (int) $row->case_id : null,
            'explanation_id' => $row->explanation_id ? (int) $row->explanation_id : null,
            'domain' => $row->domain,
            'action_type' => $row->action_type,
            'title' => $row->title,
            'body' => $row->body,
            'rationale' => $row->rationale,
            'subject_entity_key' => $row->subject_entity_key,
            'subject_id' => $row->subject_id,
            'confidence' => $row->confidence === null ? null : (float) $row->confidence,
            'risk_level' => $row->risk_level,
            'is_consequential' => (bool) $row->is_consequential,
            'requires_approval' => (bool) $row->requires_approval,
            'evidence_ids' => $row->evidence_ids ? json_decode($row->evidence_ids, true) : [],
            'eso_binding' => $row->eso_binding ? json_decode($row->eso_binding, true) : null,
            'governance_passed' => (bool) $row->governance_passed,
            'governance_report' => $row->governance_report ? json_decode($row->governance_report, true) : null,
            'workflow_key' => $row->workflow_key,
            'workflow_payload' => $row->workflow_payload ? json_decode($row->workflow_payload, true) : null,
            'status' => $row->status,
            'expires_at' => $row->expires_at,
            'created_at' => $row->created_at,
        ];
    }
}
