<?php

namespace App\Domain\AI\Evidence;

use App\Domain\AI\Support\AiAuditLogger;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persistence for evidence.
 *
 * Two behaviours worth knowing about:
 *
 *  - Deduplication. The same fact observed twice should be one row, not two, or a
 *    case's evidence count inflates and RecommendVerb's "enough evidence" check
 *    becomes meaningless. Items are matched on (subject, kind, source, observation).
 *
 *  - Verification is a separate, explicit act. `verify()` exists so promoting
 *    generated evidence to citable is always a deliberate, attributed event — never
 *    a side effect of storing it.
 */
class EvidenceStore
{
    public function __construct(private readonly AiAuditLogger $audit)
    {
    }

    /**
     * Store a batch, reusing existing rows where the same observation is already held.
     *
     * @param  array<int, EvidenceItem>  $items
     * @return array<int, int> Evidence row ids, in the order given
     */
    public function storeMany(array $items, McpRequestContext $context): array
    {
        if ($items === [] || ! Schema::hasTable('ai_evidence')) {
            return [];
        }

        $ids = [];

        foreach ($items as $item) {
            $ids[] = $this->store($item, $context);
        }

        $ids = array_values(array_filter($ids));

        if ($ids !== []) {
            $this->audit->record(AiAuditLogger::EVIDENCE_COLLECTED, $context, [
                'actor_type' => 'system',
                'message' => sprintf('Collected %d evidence items.', count($ids)),
                'payload' => ['evidence_ids' => $ids],
            ]);
        }

        return $ids;
    }

    public function store(EvidenceItem $item, McpRequestContext $context): ?int
    {
        if (! Schema::hasTable('ai_evidence')) {
            return null;
        }

        $existing = $this->findDuplicate($item, $context);

        if ($existing !== null) {
            return $existing;
        }

        $row = $item->toRow(
            $context->selectedInstituteId,
            $context->clientId,
            $context->academicYear
        );

        return (int) DB::table('ai_evidence')->insertGetId($row);
    }

    /**
     * Promote evidence to citable. Attributed and timestamped, because
     * GroundedClaims treats verified evidence as fact and an unattributed promotion
     * would be a hole in that guarantee.
     */
    public function verify(array $evidenceIds, McpRequestContext $context, ?string $note = null): int
    {
        if ($evidenceIds === [] || ! Schema::hasTable('ai_evidence')) {
            return 0;
        }

        $updated = DB::table('ai_evidence')
            ->whereIn('id', $evidenceIds)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('verified', false)
            ->update([
                'verified' => true,
                'verified_by' => $context->userId,
                'verified_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->audit->record('evidence.verified', $context, [
                'actor_type' => 'user',
                'message' => $note ?? sprintf('Verified %d evidence items.', $updated),
                'payload' => ['evidence_ids' => array_values($evidenceIds)],
            ]);
        }

        return $updated;
    }

    /**
     * Evidence about one subject, newest first.
     */
    public function forSubject(
        string $subjectEntityKey,
        int|string $subjectId,
        McpRequestContext $context,
        ?string $kind = null,
        int $limit = 50
    ): array {
        if (! Schema::hasTable('ai_evidence')) {
            return [];
        }

        $query = DB::table('ai_evidence')
            ->where('subject_entity_key', $subjectEntityKey)
            ->where('subject_id', $subjectId)
            ->where('sub_institute_id', $context->selectedInstituteId);

        if ($kind !== null) {
            $query->where('kind', $kind);
        }

        return $query->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row))
            ->all();
    }

    /**
     * Evidence attached to a case, with its role in that case.
     */
    public function forCase(int $caseId, McpRequestContext $context): array
    {
        if (! Schema::hasTable('ai_case_evidence') || ! Schema::hasTable('ai_evidence')) {
            return [];
        }

        return DB::table('ai_case_evidence')
            ->join('ai_evidence', 'ai_evidence.id', '=', 'ai_case_evidence.evidence_id')
            ->where('ai_case_evidence.case_id', $caseId)
            ->where('ai_evidence.sub_institute_id', $context->selectedInstituteId)
            ->orderByDesc('ai_evidence.observed_at')
            ->select('ai_evidence.*', 'ai_case_evidence.role', 'ai_case_evidence.weight')
            ->get()
            ->map(fn ($row) => $this->hydrate($row) + [
                'role' => $row->role,
                'weight' => (float) $row->weight,
            ])
            ->all();
    }

    public function attachToCase(int $caseId, array $evidenceIds, string $role = 'supporting'): void
    {
        if ($evidenceIds === [] || ! Schema::hasTable('ai_case_evidence')) {
            return;
        }

        $rows = [];

        foreach (array_unique($evidenceIds) as $evidenceId) {
            $rows[] = [
                'case_id' => $caseId,
                'evidence_id' => $evidenceId,
                'role' => $role,
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Re-attaching the same evidence is harmless and expected on re-analysis.
        DB::table('ai_case_evidence')->insertOrIgnore($rows);
    }

    /**
     * Matching an existing observation. Source id is the strongest signal; where a
     * computation produced the evidence there is no source row, so the observation
     * date and kind stand in.
     */
    private function findDuplicate(EvidenceItem $item, McpRequestContext $context): ?int
    {
        $query = DB::table('ai_evidence')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('subject_entity_key', $item->subjectEntityKey)
            ->where('subject_id', $item->subjectId)
            ->where('kind', $item->kind);

        if ($item->sourceTable !== null && $item->sourceId !== null) {
            $query->where('source_table', $item->sourceTable)
                ->where('source_id', $item->sourceId);
        } elseif ($item->observedAt !== null) {
            $query->where('observed_at', $item->observedAt)
                ->where('source_service', $item->sourceService);
        } else {
            // Nothing distinctive enough to dedupe on — store it.
            return null;
        }

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    private function hydrate(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'kind' => $row->kind,
            'summary' => $row->summary,
            'subject_entity_key' => $row->subject_entity_key,
            'subject_id' => $row->subject_id,
            'source' => [
                'table' => $row->source_table,
                'id' => $row->source_id,
                'service' => $row->source_service,
            ],
            'observed_at' => $row->observed_at,
            'value' => $row->value ? json_decode($row->value, true) : null,
            'numeric_value' => $row->numeric_value === null ? null : (float) $row->numeric_value,
            'unit' => $row->unit,
            'confidence' => $row->confidence === null ? null : (float) $row->confidence,
            'is_generated' => (bool) $row->is_generated,
            'verified' => (bool) $row->verified,
        ];
    }
}
