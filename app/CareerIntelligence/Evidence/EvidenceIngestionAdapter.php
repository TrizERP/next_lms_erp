<?php

namespace App\CareerIntelligence\Evidence;

/**
 * One implementation per source_type (assessment, pal, teacher_diary, …).
 * Each adapter is the only code allowed to know both its source's schema and
 * the evidence_events shape — same separation of concerns as
 * App\CareerIntelligence\Ingestion\SubjectEnrolmentAdapter.
 *
 * Adapter law (evidence_events migration doc comment): an adapter may only
 * assert what its source can defend. Enforce this IN the adapter — e.g. a
 * pure correctness signal must never write a BEHAVIOUR/ATTITUDE claim.
 */
interface EvidenceIngestionAdapter
{
    /**
     * Ingest fresh evidence for one student. Never mutates an existing row;
     * superseding an old row means inserting a new one and flipping the old
     * row's contested/superseded_by.
     *
     * @return int[] evidence_id of every row written this run
     */
    public function ingest(string $studentId, string $academicYear): array;
}
