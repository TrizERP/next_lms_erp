<?php

namespace App\Domain\AI\Evidence;

/**
 * One citable observation, with the provenance that makes it citable.
 *
 * The provenance fields are not decoration. `sourceTable` + `sourceId` are what let a
 * teacher click "why?" on a claim and land on the actual exam row or attendance
 * record. An evidence item that cannot say where it came from is, by design,
 * awkward to construct.
 *
 * `verified` defaults to false and `isGenerated` defaults to false. A collector that
 * reads a real table sets verified = true because the reading is deterministic; a
 * generation step sets isGenerated = true and leaves verified false, which is what
 * stops model output being cited as fact.
 */
final class EvidenceItem
{
    public function __construct(
        public readonly string $kind,
        public readonly string $subjectEntityKey,
        public readonly int|string $subjectId,
        public readonly string $summary,
        public readonly ?string $sourceTable = null,
        public readonly ?string $sourceColumn = null,
        public readonly int|string|null $sourceId = null,
        public readonly ?string $sourceService = null,
        public readonly ?string $observedAt = null,
        public readonly mixed $value = null,
        public readonly ?float $numericValue = null,
        public readonly ?string $unit = null,
        public readonly ?float $confidence = null,
        public readonly bool $isGenerated = false,
        public readonly bool $verified = false,
        public readonly ?string $evidenceKey = null,
    ) {
    }

    /**
     * Evidence read straight from a table. Deterministic, so it is born verified.
     */
    public static function fromRecord(
        string $kind,
        string $subjectEntityKey,
        int|string $subjectId,
        string $summary,
        string $sourceTable,
        int|string|null $sourceId = null,
        mixed $value = null,
        ?float $numericValue = null,
        ?string $observedAt = null,
        ?string $unit = null,
        ?string $sourceColumn = null,
    ): self {
        return new self(
            kind: $kind,
            subjectEntityKey: $subjectEntityKey,
            subjectId: $subjectId,
            summary: $summary,
            sourceTable: $sourceTable,
            sourceColumn: $sourceColumn,
            sourceId: $sourceId,
            observedAt: $observedAt,
            value: $value,
            numericValue: $numericValue,
            unit: $unit,
            confidence: 1.0,
            isGenerated: false,
            verified: true,
        );
    }

    /**
     * Evidence derived by a service from several records — a trend, a rate, an average.
     * Still factual, still verified, but its provenance is the computation.
     */
    public static function fromComputation(
        string $kind,
        string $subjectEntityKey,
        int|string $subjectId,
        string $summary,
        string $sourceService,
        mixed $value = null,
        ?float $numericValue = null,
        ?string $unit = null,
        ?float $confidence = 1.0,
        ?string $observedAt = null,
    ): self {
        return new self(
            kind: $kind,
            subjectEntityKey: $subjectEntityKey,
            subjectId: $subjectId,
            summary: $summary,
            sourceService: $sourceService,
            observedAt: $observedAt,
            value: $value,
            numericValue: $numericValue,
            unit: $unit,
            confidence: $confidence,
            isGenerated: false,
            verified: true,
        );
    }

    /**
     * Model output. Never verified on creation — a human or a deterministic check has
     * to promote it, and GroundedClaims refuses to cite it until then.
     */
    public static function fromGeneration(
        string $kind,
        string $subjectEntityKey,
        int|string $subjectId,
        string $summary,
        string $model,
        mixed $value = null,
        ?float $confidence = null,
    ): self {
        return new self(
            kind: $kind,
            subjectEntityKey: $subjectEntityKey,
            subjectId: $subjectId,
            summary: $summary,
            sourceService: $model,
            value: $value,
            confidence: $confidence,
            isGenerated: true,
            verified: false,
        );
    }

    public function toRow(int $subInstituteId, ?int $clientId = null, ?int $academicYear = null): array
    {
        return [
            'evidence_key' => $this->evidenceKey,
            'kind' => $this->kind,
            'subject_entity_key' => $this->subjectEntityKey,
            'subject_id' => $this->subjectId,
            'source_table' => $this->sourceTable,
            'source_column' => $this->sourceColumn,
            'source_id' => is_numeric($this->sourceId) ? (int) $this->sourceId : null,
            'source_service' => $this->sourceService,
            'observed_at' => $this->observedAt,
            'summary' => mb_substr($this->summary, 0, 500),
            'value' => $this->value === null ? null : json_encode($this->value),
            'numeric_value' => $this->numericValue,
            'unit' => $this->unit,
            'confidence' => $this->confidence,
            'is_generated' => $this->isGenerated,
            'verified' => $this->verified,
            'sub_institute_id' => $subInstituteId,
            'client_id' => $clientId,
            'academic_year' => $academicYear,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'subject_entity_key' => $this->subjectEntityKey,
            'subject_id' => $this->subjectId,
            'summary' => $this->summary,
            'source_table' => $this->sourceTable,
            'source_id' => $this->sourceId,
            'source_service' => $this->sourceService,
            'observed_at' => $this->observedAt,
            'numeric_value' => $this->numericValue,
            'unit' => $this->unit,
            'confidence' => $this->confidence,
            'is_generated' => $this->isGenerated,
            'verified' => $this->verified,
        ];
    }
}
