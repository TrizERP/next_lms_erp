<?php

namespace App\Domain\AI\Cases;

use App\Domain\AI\Evidence\EvidenceStore;
use App\Domain\AI\Signals\DetectedSignal;
use App\Domain\AI\Signals\SignalStore;
use App\Domain\AI\Signals\ThresholdRegistry;
use App\Domain\AI\Support\AiAuditLogger;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns signals into cases.
 *
 * A case is the unit a human reviews, so the decision of *when* to open one matters
 * as much as how. Opening a case for every low signal would bury teachers; opening
 * none would waste the detection. The rule here: a case is opened when the combined
 * severity is actionable (high or critical, per ThresholdRegistry — the same bands
 * PAL already uses), or when several moderate signals coincide on one subject.
 *
 * Re-analysis updates the open case rather than opening a second one. A student is
 * one conversation, not a new ticket every night.
 */
class CaseBuilder
{
    public function __construct(
        private readonly SignalStore $signalStore,
        private readonly EvidenceStore $evidenceStore,
        private readonly ThresholdRegistry $thresholds,
        private readonly AiAuditLogger $audit,
    ) {
    }

    /**
     * Several moderate signals on one subject are worth a look even when none is
     * individually actionable.
     */
    private const CORROBORATION_COUNT = 2;

    /**
     * Open (or update) a case from one subject's signals.
     *
     * @param  array<int, DetectedSignal>  $signals   All signals for this subject
     * @param  array<int, int>  $signalIds            Their persisted ids
     * @param  array<int, int>  $evidenceIds          Evidence ids gathered for them
     * @return int|null The case id, or null when no case was warranted
     */
    public function buildFromSignals(
        string $caseType,
        array $signals,
        array $signalIds,
        array $evidenceIds,
        McpRequestContext $context,
        ?int $runId = null
    ): ?int {
        if ($signals === [] || ! Schema::hasTable('ai_cases')) {
            return null;
        }

        if (! $this->warrantsCase($signals, $context)) {
            return null;
        }

        $primary = $this->primarySignal($signals);
        $severity = $this->combinedSeverity($signals);
        $priority = $this->priorityScore($signals);

        $existing = $this->findOpenCase($caseType, $primary, $context);

        if ($existing !== null) {
            DB::table('ai_cases')->where('id', $existing)->update([
                'severity' => $severity,
                'priority_score' => $priority,
                'summary' => $this->summarize($signals),
                'context' => json_encode($this->buildContext($signals)),
                'updated_at' => now(),
            ]);

            $this->signalStore->markCased($signalIds, $existing);
            $this->evidenceStore->attachToCase($existing, $evidenceIds);

            return $existing;
        }

        $caseId = (int) DB::table('ai_cases')->insertGetId([
            'case_reference' => $this->nextReference(),
            'case_type' => $caseType,
            'domain' => $primary->domain,
            'title' => mb_substr($this->title($primary, $severity), 0, 300),
            'summary' => $this->summarize($signals),
            'subject_entity_key' => $primary->subjectEntityKey,
            'subject_id' => $primary->subjectId,
            'subject_label' => $primary->subjectLabel ? mb_substr($primary->subjectLabel, 0, 200) : null,
            'severity' => $severity,
            'priority_score' => $priority,
            'status' => 'open',
            'opened_by_run_id' => $runId,
            'opened_at' => now(),
            'context' => json_encode($this->buildContext($signals)),
            'sub_institute_id' => $context->selectedInstituteId,
            'client_id' => $context->clientId,
            'academic_year' => $context->academicYear,
            'term_id' => $context->termId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->signalStore->markCased($signalIds, $caseId);
        $this->evidenceStore->attachToCase($caseId, $evidenceIds);

        $this->audit->record(AiAuditLogger::CASE_OPENED, $context, [
            'actor_type' => 'system',
            'subject_entity_key' => $primary->subjectEntityKey,
            'subject_id' => $primary->subjectId,
            'related_type' => 'ai_cases',
            'related_id' => $caseId,
            'message' => $this->title($primary, $severity),
            'payload' => [
                'case_type' => $caseType,
                'severity' => $severity,
                'priority_score' => $priority,
                'signal_ids' => $signalIds,
                'evidence_count' => count($evidenceIds),
            ],
        ]);

        return $caseId;
    }

    /**
     * Record a hypothesis against a case, with the evidence for and against it.
     * Hypotheses are kept separate from claims: a hypothesis may be refuted, whereas
     * a claim in an explanation must already be grounded.
     */
    public function addHypothesis(
        int $caseId,
        string $statement,
        McpRequestContext $context,
        ?string $rationale = null,
        array $supportingEvidenceIds = [],
        array $contradictingEvidenceIds = [],
        ?float $confidence = null
    ): ?int {
        if (! Schema::hasTable('ai_hypotheses')) {
            return null;
        }

        $status = match (true) {
            $contradictingEvidenceIds !== [] && $supportingEvidenceIds === [] => 'refuted',
            $supportingEvidenceIds !== [] && $contradictingEvidenceIds === [] => 'supported',
            $supportingEvidenceIds !== [] => 'inconclusive',
            default => 'proposed',
        };

        return (int) DB::table('ai_hypotheses')->insertGetId([
            'case_id' => $caseId,
            'statement' => mb_substr($statement, 0, 500),
            'rationale' => $rationale,
            'confidence' => $confidence,
            'status' => $status,
            'supporting_evidence_ids' => json_encode(array_values($supportingEvidenceIds)),
            'contradicting_evidence_ids' => json_encode(array_values($contradictingEvidenceIds)),
            'sub_institute_id' => $context->selectedInstituteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function find(int $caseId, McpRequestContext $context): ?array
    {
        if (! Schema::hasTable('ai_cases')) {
            return null;
        }

        $row = DB::table('ai_cases')
            ->where('id', $caseId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->first();

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * The case list a teacher or admin actually opens.
     */
    public function list(
        McpRequestContext $context,
        ?string $caseType = null,
        ?string $status = null,
        ?string $minSeverity = null,
        int $limit = 50
    ): array {
        if (! Schema::hasTable('ai_cases')) {
            return [];
        }

        $query = DB::table('ai_cases')
            ->where('sub_institute_id', $context->selectedInstituteId);

        if ($caseType !== null) {
            $query->where('case_type', $caseType);
        }

        $query->where('status', $status ?? 'open');

        if ($minSeverity !== null) {
            $ladder = ['low', 'moderate', 'high', 'critical'];
            $index = array_search(strtolower($minSeverity), $ladder, true);
            if ($index !== false) {
                $query->whereIn('severity', array_slice($ladder, $index));
            }
        }

        if ($context->academicYear !== null) {
            $query->where(function ($inner) use ($context) {
                $inner->whereNull('academic_year')->orWhere('academic_year', $context->academicYear);
            });
        }

        return $query->orderByDesc('priority_score')
            ->orderByDesc('opened_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->hydrate($row))
            ->all();
    }

    public function updateStatus(int $caseId, string $status, McpRequestContext $context): bool
    {
        if (! Schema::hasTable('ai_cases')) {
            return false;
        }

        $payload = ['status' => $status, 'updated_at' => now()];

        if (in_array($status, ['closed', 'dismissed'], true)) {
            $payload['closed_at'] = now();
        }

        $updated = DB::table('ai_cases')
            ->where('id', $caseId)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->update($payload);

        if ($updated > 0 && in_array($status, ['closed', 'dismissed'], true)) {
            $this->audit->record(AiAuditLogger::CASE_CLOSED, $context, [
                'related_type' => 'ai_cases',
                'related_id' => $caseId,
                'message' => 'Case ' . $status . '.',
            ]);
        }

        return $updated > 0;
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<int, DetectedSignal>  $signals
     */
    private function warrantsCase(array $signals, McpRequestContext $context): bool
    {
        $moderateOrAbove = 0;

        foreach ($signals as $signal) {
            if ($this->thresholds->isActionable($signal->severity)) {
                return true;
            }

            if ($this->thresholds->severityRank($signal->severity) >= 2) {
                $moderateOrAbove++;
            }
        }

        return $moderateOrAbove >= self::CORROBORATION_COUNT;
    }

    /** @param array<int, DetectedSignal> $signals */
    private function primarySignal(array $signals): DetectedSignal
    {
        usort($signals, function (DetectedSignal $a, DetectedSignal $b) {
            $rank = $this->thresholds->severityRank($b->severity) <=> $this->thresholds->severityRank($a->severity);

            return $rank !== 0 ? $rank : ($b->score <=> $a->score);
        });

        return $signals[0];
    }

    /** @param array<int, DetectedSignal> $signals */
    private function combinedSeverity(array $signals): string
    {
        $highest = 'low';

        foreach ($signals as $signal) {
            if ($this->thresholds->severityRank($signal->severity) > $this->thresholds->severityRank($highest)) {
                $highest = $signal->severity;
            }
        }

        return $highest;
    }

    /**
     * Priority blends the strongest signal with how much corroboration there is, so a
     * student flagged by three detectors outranks one flagged by a single higher score.
     *
     * @param  array<int, DetectedSignal>  $signals
     */
    private function priorityScore(array $signals): float
    {
        $max = 0.0;
        $sum = 0.0;

        foreach ($signals as $signal) {
            $max = max($max, $signal->score);
            $sum += $signal->score;
        }

        $corroboration = min(1.0, ($sum - $max) / max(1, count($signals) - 1 ?: 1));

        return round(min(1.0, ($max * 0.75) + ($corroboration * 0.25)), 4);
    }

    private function title(DetectedSignal $signal, string $severity): string
    {
        $label = $signal->subjectLabel ?: ($signal->subjectEntityKey . ' #' . $signal->subjectId);
        $readable = ucfirst(str_replace('_', ' ', $signal->signalKey));

        return sprintf('%s — %s risk (%s)', $label, $readable, $severity);
    }

    /** @param array<int, DetectedSignal> $signals */
    private function summarize(array $signals): string
    {
        $parts = [];

        foreach ($signals as $signal) {
            $parts[] = sprintf(
                '%s at %.2f (%s), from %d evidence item%s',
                str_replace('_', ' ', $signal->signalKey),
                $signal->score,
                $signal->severity,
                $signal->evidenceCount(),
                $signal->evidenceCount() === 1 ? '' : 's'
            );
        }

        return ucfirst(implode('; ', $parts)) . '.';
    }

    /** @param array<int, DetectedSignal> $signals */
    private function buildContext(array $signals): array
    {
        return [
            'signals' => array_map(fn (DetectedSignal $signal) => $signal->toArray(), $signals),
            'built_at' => now()->toIso8601String(),
        ];
    }

    private function findOpenCase(string $caseType, DetectedSignal $signal, McpRequestContext $context): ?int
    {
        $query = DB::table('ai_cases')
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where('case_type', $caseType)
            ->where('subject_entity_key', $signal->subjectEntityKey)
            ->where('subject_id', $signal->subjectId)
            ->whereIn('status', ['open', 'analysing', 'awaiting_decision', 'in_progress']);

        if ($context->academicYear !== null) {
            $query->where(function ($inner) use ($context) {
                $inner->whereNull('academic_year')->orWhere('academic_year', $context->academicYear);
            });
        }

        $id = $query->orderByDesc('id')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * References are human-quotable ("case CASE-2026-000123"), so they must be stable
     * and readable rather than a UUID.
     */
    private function nextReference(): string
    {
        $year = now()->year;
        $prefix = sprintf('CASE-%d-', $year);

        $last = DB::table('ai_cases')
            ->where('case_reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('case_reference');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function hydrate(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'reference' => $row->case_reference,
            'case_type' => $row->case_type,
            'domain' => $row->domain,
            'title' => $row->title,
            'summary' => $row->summary,
            'subject_entity_key' => $row->subject_entity_key,
            'subject_id' => $row->subject_id,
            'subject_label' => $row->subject_label,
            'severity' => $row->severity,
            'priority_score' => $row->priority_score === null ? null : (float) $row->priority_score,
            'status' => $row->status,
            'opened_at' => $row->opened_at,
            'closed_at' => $row->closed_at,
            'context' => $row->context ? json_decode($row->context, true) : [],
        ];
    }
}
