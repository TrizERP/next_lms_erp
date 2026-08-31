<?php

namespace App\Domain\AI\Explanations;

use App\Domain\AI\Support\AiAuditLogger;
use App\Domain\Governance\GovernanceReport;
use App\Domain\Governance\GovernanceValidator;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the "why" — the sentence a teacher reads before deciding.
 *
 * The default path is deterministic: claims are assembled from evidence and the
 * narrative is composed from the claims. No model is involved, because none is
 * needed and a generated sentence could drift from the evidence it cites. Generation
 * is available (`storeGenerated`) for the cases where phrasing genuinely matters, and
 * it is held to exactly the same grounding rules — the narrative may be generated,
 * but the claims and their citations may not.
 *
 * An explanation that fails governance is still stored, with `governance_passed`
 * false and the report attached. Silently discarding it would hide the fact that the
 * system tried to assert something it could not support.
 */
class ExplanationBuilder
{
    public function __construct(
        private readonly GovernanceValidator $governance,
        private readonly AiAuditLogger $audit,
    ) {
    }

    /**
     * Compose and store an explanation from grounded claims.
     *
     * @param  array<int, array{claim:string, evidence_ids:array<int,int>, confidence?:float}>  $claims
     * @return array{id:int|null, governance:GovernanceReport, narrative:string}
     */
    public function build(
        int $caseId,
        array $claims,
        McpRequestContext $context,
        string $audience = 'teacher',
        ?string $subjectEntityKey = null,
        int|string|null $subjectId = null,
        ?string $subjectLabel = null,
        ?string $narrative = null
    ): array {
        $composed = $narrative ?? $this->governance->explain()->composeNarrative($claims, $subjectLabel);

        $draft = [
            'narrative' => $composed,
            'claims' => $claims,
            'audience' => $audience,
        ];

        $report = $this->governance->validateExplanation($draft, $context, $subjectEntityKey, $subjectId);

        $id = $this->persist($caseId, $draft, $report, $context, false, null, null);

        if (! $report->passed) {
            $this->audit->recordRejection(
                $report->reason() ?? 'Explanation failed governance validation.',
                $context,
                [
                    'related_type' => 'ai_explanations',
                    'related_id' => $id,
                    'subject_entity_key' => $subjectEntityKey,
                    'subject_id' => $subjectId,
                    'payload' => ['violations' => $report->violations, 'case_id' => $caseId],
                ]
            );
        } else {
            $this->audit->record(AiAuditLogger::EXPLANATION_BUILT, $context, [
                'actor_type' => 'system',
                'related_type' => 'ai_explanations',
                'related_id' => $id,
                'subject_entity_key' => $subjectEntityKey,
                'subject_id' => $subjectId,
                'message' => mb_substr($composed, 0, 200),
                'payload' => ['case_id' => $caseId, 'claim_count' => count($claims)],
            ]);
        }

        return ['id' => $id, 'governance' => $report, 'narrative' => $composed];
    }

    /**
     * Store an explanation whose narrative came from a model.
     *
     * The claims still have to be grounded, so a generated narrative cannot introduce
     * an assertion the evidence does not support — it can only reword one.
     */
    public function storeGenerated(
        int $caseId,
        string $narrative,
        array $claims,
        McpRequestContext $context,
        string $model,
        ?int $generationOutputId = null,
        string $audience = 'teacher',
        ?string $subjectEntityKey = null,
        int|string|null $subjectId = null
    ): array {
        $draft = [
            'narrative' => $narrative,
            'claims' => $claims,
            'audience' => $audience,
            'is_generated' => true,
        ];

        $report = $this->governance->validateExplanation($draft, $context, $subjectEntityKey, $subjectId);
        $id = $this->persist($caseId, $draft, $report, $context, true, $model, $generationOutputId);

        return ['id' => $id, 'governance' => $report, 'narrative' => $narrative];
    }

    /**
     * The latest passing explanation for a case, which is what the UI shows.
     */
    public function latestForCase(int $caseId, McpRequestContext $context, string $audience = 'teacher'): ?array
    {
        if (! Schema::hasTable('ai_explanations')) {
            return null;
        }

        $row = DB::table('ai_explanations')
            ->where('case_id', $caseId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('audience', $audience)
            ->where('governance_passed', true)
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            // Fall back to any audience before giving up — better a teacher-worded
            // explanation than none.
            $row = DB::table('ai_explanations')
                ->where('case_id', $caseId)
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->where('governance_passed', true)
                ->orderByDesc('id')
                ->first();
        }

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * Every explanation on a case, including the ones that failed — an audit needs
     * to see what the system tried to say, not just what it was allowed to.
     */
    public function allForCase(int $caseId, McpRequestContext $context): array
    {
        if (! Schema::hasTable('ai_explanations')) {
            return [];
        }

        return DB::table('ai_explanations')
            ->where('case_id', $caseId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => $this->hydrate($row))
            ->all();
    }

    private function persist(
        int $caseId,
        array $draft,
        GovernanceReport $report,
        McpRequestContext $context,
        bool $isGenerated,
        ?string $model,
        ?int $generationOutputId
    ): ?int {
        if (! Schema::hasTable('ai_explanations')) {
            return null;
        }

        return (int) DB::table('ai_explanations')->insertGetId([
            'case_id' => $caseId,
            'audience' => $draft['audience'] ?? 'teacher',
            'narrative' => $draft['narrative'] ?? '',
            'claims' => json_encode($draft['claims'] ?? []),
            'is_generated' => $isGenerated,
            'governance_passed' => $report->passed,
            'governance_report' => $report->toJson(),
            'generated_by_model' => $model,
            'generation_output_id' => $generationOutputId,
            'sub_institute_id' => $context->selectedInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hydrate(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'case_id' => (int) $row->case_id,
            'audience' => $row->audience,
            'narrative' => $row->narrative,
            'claims' => $row->claims ? json_decode($row->claims, true) : [],
            'is_generated' => (bool) $row->is_generated,
            'governance_passed' => (bool) $row->governance_passed,
            'governance_report' => $row->governance_report ? json_decode($row->governance_report, true) : null,
            'generated_by_model' => $row->generated_by_model,
            'created_at' => $row->created_at,
        ];
    }
}
