<?php

namespace App\Domain\AI\Signals;

use App\Domain\AI\Evidence\EvidenceStore;
use App\Domain\AI\Support\AiAuditLogger;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persistence for signals — the thing the estate was missing.
 *
 * PredictiveInterventionEngine computed risk perfectly well and then threw it away,
 * which meant no history, no trend, no "when did this start", and nothing for an
 * outcome to be measured against. Storing signals is what turns a score into a
 * timeline.
 *
 * Re-detection updates the open signal rather than inserting a second one, so a
 * student who has been at risk for three weeks has one signal with a moving score,
 * not twenty-one duplicates.
 */
class SignalStore
{
    public function __construct(
        private readonly EvidenceStore $evidenceStore,
        private readonly AiAuditLogger $audit,
    ) {
    }

    /**
     * Persist a detected signal and its evidence together.
     *
     * @return array{signal_id:int|null, evidence_ids:array<int,int>}
     */
    public function store(DetectedSignal $signal, McpRequestContext $context, ?int $runId = null): array
    {
        if (! Schema::hasTable('ai_signals')) {
            return ['signal_id' => null, 'evidence_ids' => []];
        }

        $evidenceIds = $this->evidenceStore->storeMany($signal->evidence, $context);

        $existing = $this->findOpen($signal, $context);
        $row = $signal->toRow(
            $context->selectedInstituteId,
            $context->clientId,
            $context->academicYear,
            $context->termId,
            $runId
        );

        if ($existing !== null) {
            unset($row['created_at']);
            DB::table('ai_signals')->where('id', $existing)->update($row);
            $signalId = $existing;
        } else {
            $signalId = (int) DB::table('ai_signals')->insertGetId($row);
        }

        $this->audit->record(AiAuditLogger::SIGNAL_DETECTED, $context, [
            'actor_type' => 'system',
            'subject_entity_key' => $signal->subjectEntityKey,
            'subject_id' => $signal->subjectId,
            'related_type' => 'ai_signals',
            'related_id' => $signalId,
            'message' => $signal->headline(),
            'payload' => [
                'signal_key' => $signal->signalKey,
                'score' => $signal->score,
                'severity' => $signal->severity,
                'evidence_ids' => $evidenceIds,
                'updated_existing' => $existing !== null,
            ],
        ]);

        return ['signal_id' => $signalId, 'evidence_ids' => $evidenceIds];
    }

    /**
     * @param  array<int, DetectedSignal>  $signals
     * @return array<int, array{signal_id:int|null, evidence_ids:array<int,int>}>
     */
    public function storeMany(array $signals, McpRequestContext $context, ?int $runId = null): array
    {
        return array_map(
            fn (DetectedSignal $signal) => $this->store($signal, $context, $runId),
            $signals
        );
    }

    /**
     * Open signals for a tenant, optionally narrowed. This is what the
     * "which students are at risk?" question actually reads.
     */
    public function open(
        McpRequestContext $context,
        ?string $signalKey = null,
        ?string $minSeverity = null,
        int $limit = 100
    ): array {
        if (! Schema::hasTable('ai_signals')) {
            return [];
        }

        $query = DB::table('ai_signals')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('status', 'open');

        if ($signalKey !== null) {
            $query->where('signal_key', $signalKey);
        }

        if ($context->academicYear !== null) {
            $query->where(function ($inner) use ($context) {
                $inner->whereNull('academic_year')->orWhere('academic_year', $context->academicYear);
            });
        }

        if ($minSeverity !== null) {
            $query->whereIn('severity', $this->severitiesAtLeast($minSeverity));
        }

        return $query->orderByDesc('score')
            ->orderByDesc('detected_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row))
            ->all();
    }

    /**
     * A subject's signal history — the trend that persistence exists to make possible.
     */
    public function historyFor(
        string $subjectEntityKey,
        int|string $subjectId,
        McpRequestContext $context,
        int $limit = 50
    ): array {
        if (! Schema::hasTable('ai_signals')) {
            return [];
        }

        return DB::table('ai_signals')
            ->where('subject_entity_key', $subjectEntityKey)
            ->where('subject_id', $subjectId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->orderByDesc('detected_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row))
            ->all();
    }

    public function markCased(array $signalIds, int $caseId): void
    {
        if ($signalIds === [] || ! Schema::hasTable('ai_signals')) {
            return;
        }

        DB::table('ai_signals')
            ->whereIn('id', $signalIds)
            ->update(['status' => 'cased', 'updated_at' => now()]);

        if (Schema::hasTable('ai_case_signals')) {
            $rows = array_map(fn ($signalId) => [
                'case_id' => $caseId,
                'signal_id' => $signalId,
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], array_unique($signalIds));

            DB::table('ai_case_signals')->insertOrIgnore($rows);
        }
    }

    public function resolve(array $signalIds, McpRequestContext $context): int
    {
        if ($signalIds === [] || ! Schema::hasTable('ai_signals')) {
            return 0;
        }

        return DB::table('ai_signals')
            ->whereIn('id', $signalIds)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * An existing open signal of the same kind for the same subject, if any.
     */
    private function findOpen(DetectedSignal $signal, McpRequestContext $context): ?int
    {
        $query = DB::table('ai_signals')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('signal_key', $signal->signalKey)
            ->where('subject_entity_key', $signal->subjectEntityKey)
            ->where('subject_id', $signal->subjectId)
            ->whereIn('status', ['open', 'cased']);

        if ($context->academicYear !== null) {
            $query->where(function ($inner) use ($context) {
                $inner->whereNull('academic_year')->orWhere('academic_year', $context->academicYear);
            });
        }

        $id = $query->orderByDesc('id')->value('id');

        return $id ? (int) $id : null;
    }

    /** @return array<int, string> */
    private function severitiesAtLeast(string $minSeverity): array
    {
        $ladder = ['low', 'moderate', 'high', 'critical'];
        $index = array_search(strtolower($minSeverity), $ladder, true);

        return $index === false ? $ladder : array_slice($ladder, $index);
    }

    private function hydrate(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'signal_key' => $row->signal_key,
            'domain' => $row->domain,
            'subject_entity_key' => $row->subject_entity_key,
            'subject_id' => $row->subject_id,
            'subject_label' => $row->subject_label,
            'score' => $row->score === null ? null : (float) $row->score,
            'severity' => $row->severity,
            'confidence' => $row->confidence === null ? null : (float) $row->confidence,
            'components' => $row->components ? json_decode($row->components, true) : [],
            'context' => $row->context ? json_decode($row->context, true) : [],
            'status' => $row->status,
            'detected_at' => $row->detected_at,
            'resolved_at' => $row->resolved_at,
        ];
    }
}
