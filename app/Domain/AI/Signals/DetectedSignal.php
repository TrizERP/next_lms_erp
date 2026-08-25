<?php

namespace App\Domain\AI\Signals;

use App\Domain\AI\Evidence\EvidenceItem;

/**
 * A signal as a detector produced it, before it is persisted.
 *
 * A signal carries its own evidence. That coupling is deliberate: a detector that
 * can raise "assessment performance is declining" but cannot say which three
 * assessments it looked at has not detected anything useful, and the case builder
 * would have nothing to cite.
 */
final class DetectedSignal
{
    /**
     * @param  array<int, EvidenceItem>  $evidence
     * @param  array<string, float|int|string>  $components  Per-contributor breakdown
     */
    public function __construct(
        public readonly string $signalKey,
        public readonly string $subjectEntityKey,
        public readonly int|string $subjectId,
        public readonly float $score,
        public readonly string $severity,
        public readonly array $evidence = [],
        public readonly array $components = [],
        public readonly ?float $confidence = null,
        public readonly ?string $subjectLabel = null,
        public readonly string $domain = 'k12',
        public readonly array $context = [],
        public readonly ?string $detectedAt = null,
    ) {
    }

    public function hasEvidence(): bool
    {
        return $this->evidence !== [];
    }

    public function evidenceCount(): int
    {
        return count($this->evidence);
    }

    /**
     * A short human sentence for the signal itself. Used as the case title seed and
     * as the fallback when no explanation has been built yet.
     */
    public function headline(): string
    {
        $label = $this->subjectLabel ?: ($this->subjectEntityKey . ' #' . $this->subjectId);
        $readable = ucfirst(str_replace('_', ' ', $this->signalKey));

        return sprintf('%s — %s (%s)', $label, $readable, $this->severity);
    }

    public function toRow(
        int $subInstituteId,
        ?int $clientId = null,
        ?int $academicYear = null,
        ?int $termId = null,
        ?int $runId = null
    ): array {
        return [
            'signal_key' => $this->signalKey,
            'domain' => $this->domain,
            'subject_entity_key' => $this->subjectEntityKey,
            'subject_id' => $this->subjectId,
            'subject_label' => $this->subjectLabel ? mb_substr($this->subjectLabel, 0, 200) : null,
            'score' => round($this->score, 4),
            'severity' => $this->severity,
            'confidence' => $this->confidence,
            'components' => json_encode($this->components),
            'context' => json_encode($this->context),
            'status' => 'open',
            'detected_at' => $this->detectedAt ?? now(),
            'detected_by_run_id' => $runId,
            'sub_institute_id' => $subInstituteId,
            'client_id' => $clientId,
            'academic_year' => $academicYear,
            'term_id' => $termId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function toArray(): array
    {
        return [
            'signal_key' => $this->signalKey,
            'subject_entity_key' => $this->subjectEntityKey,
            'subject_id' => $this->subjectId,
            'subject_label' => $this->subjectLabel,
            'score' => round($this->score, 4),
            'severity' => $this->severity,
            'confidence' => $this->confidence,
            'components' => $this->components,
            'evidence_count' => $this->evidenceCount(),
            'headline' => $this->headline(),
        ];
    }
}
