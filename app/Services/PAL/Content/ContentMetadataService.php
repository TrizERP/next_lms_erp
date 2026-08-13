<?php

namespace App\Services\PAL\Content;

use App\Models\PAL\ContentMetadata;
use App\Models\PAL\ContentReviewLog;
use App\Models\PAL\ConceptMetadata;
use App\Models\PAL\QuestionMetadata;
use Illuminate\Support\Facades\DB;

/**
 * Read/write gateway for the Content Intelligence metadata sidecars.
 *
 * Every write in the layer goes through here so that four rules hold everywhere
 * rather than being re-implemented per caller:
 *
 *   C2  the live content tables are never touched — only the sidecars
 *   C3  sub_institute_id is mandatory and 0 is only legal with scope='global'
 *   C4  quality_status transitions follow the spec §7.1 pipeline
 *   C5  a machine actor may never write a human-only status (e.g. 'approved')
 *
 * A violation throws. Metadata that silently half-writes is worse than a failed
 * batch: it produces confident, wrong routing later.
 */
class ContentMetadataService
{
    /** Entity type => [model class, owning key column, live table it annotates]. */
    protected const ENTITIES = [
        'question' => [QuestionMetadata::class, 'question_id', 'lms_question_master'],
        'content'  => [ContentMetadata::class, 'content_master_id', 'content_master'],
        'concept'  => [ConceptMetadata::class, 'concept_ref_id', 'lms_concept'],
    ];

    // ── Reads ────────────────────────────────────────────────────────────────

    /**
     * Metadata for one question, merged with the live row it annotates.
     */
    public function forQuestion(int $questionId, ?int $subInstituteId = null): array
    {
        $question = DB::table('lms_question_master')->where('id', $questionId)->first();

        if (! $question) {
            return ['found' => false, 'question_id' => $questionId];
        }

        $meta = QuestionMetadata::where('question_id', $questionId)
            ->forTenant($subInstituteId ?? (int) $question->sub_institute_id)
            ->first();

        return [
            'found' => true,
            'question_id' => $questionId,
            'sub_institute_id' => (int) $question->sub_institute_id,
            'source' => [
                'question_title' => $question->question_title,
                'concept' => $question->concept,
                'subconcept' => $question->subconcept,
                'chapter_id' => $question->chapter_id,
                'concept_id' => $question->concept_id,
                'subject_id' => $question->subject_id,
                'grade_id' => $question->grade_id,
                'hint_text' => $question->hint_text,
                'learning_outcome' => $question->learning_outcome,
            ],
            'metadata' => $meta,
            'tagged' => $meta !== null,
            'completeness' => $meta ? $this->completeness('question', $meta) : 0.0,
        ];
    }

    /**
     * Metadata for one content_master row, merged with the live row.
     */
    public function forContent(int $contentMasterId, ?int $subInstituteId = null): array
    {
        $content = DB::table('content_master')->where('id', $contentMasterId)->first();

        if (! $content) {
            return ['found' => false, 'content_master_id' => $contentMasterId];
        }

        $meta = ContentMetadata::where('content_master_id', $contentMasterId)
            ->forTenant($subInstituteId ?? (int) $content->sub_institute_id)
            ->first();

        return [
            'found' => true,
            'content_master_id' => $contentMasterId,
            'sub_institute_id' => (int) $content->sub_institute_id,
            'source' => [
                'title' => $content->title,
                'description' => $content->description,
                'file_type' => $content->file_type,
                'url' => $content->url,
                'content_category' => $content->content_category,
                'meta_tags' => $content->meta_tags,
                'basic_advance' => $content->basic_advance,
                'concept_id' => $content->concept_id,
                'chapter_id' => $content->chapter_id,
                'subject_id' => $content->subject_id,
            ],
            'metadata' => $meta,
            'tagged' => $meta !== null,
            'completeness' => $meta ? $this->completeness('content', $meta) : 0.0,
        ];
    }

    public function forConcept(int $conceptId, ?int $subInstituteId = null): array
    {
        $concept = DB::table('lms_concept')->where('id', $conceptId)->first();

        if (! $concept) {
            return ['found' => false, 'concept_ref_id' => $conceptId];
        }

        $meta = ConceptMetadata::where('concept_ref_id', $conceptId)
            ->forTenant($subInstituteId ?? (int) $concept->sub_institute_id)
            ->first();

        return [
            'found' => true,
            'concept_ref_id' => $conceptId,
            'sub_institute_id' => (int) $concept->sub_institute_id,
            'source' => [
                'name' => $concept->name,
                'description' => $concept->description,
                'subject_id' => $concept->subject_id,
                'chapter_id' => $concept->chapter_id,
                'standard_id' => $concept->standard_id,
                // Legacy mastery threshold — never overwritten (C2). The PAL V4
                // BKT gate lives in pal_concept_metadata.mastery_gate.
                'mastery_threshold' => $concept->mastery_threshold,
                'estimated_mastery_minutes' => $concept->estimated_mastery_minutes,
            ],
            'metadata' => $meta,
            'tagged' => $meta !== null,
        ];
    }

    // ── Writes ───────────────────────────────────────────────────────────────

    /**
     * Create or update a metadata row.
     *
     * @param  string  $entityType  question | content | concept
     * @param  int     $entityId    the live row's id
     * @param  array   $data        partial metadata payload
     * @param  string  $actorType   human | ai | derived | imported
     * @param  ?int    $actorId     user id, required for human actions
     *
     * @throws \InvalidArgumentException on any vocabulary, tenancy or status violation
     */
    public function upsert(
        string $entityType,
        int $entityId,
        array $data,
        int $subInstituteId,
        string $actorType = 'human',
        ?int $actorId = null
    ): object {
        [$modelClass, $keyColumn] = $this->entity($entityType);

        if (! in_array($actorType, config('pal_content.tagged_by', []), true)) {
            throw new \InvalidArgumentException("Unknown actor type '{$actorType}'.");
        }

        $scope = $data['scope'] ?? ($subInstituteId === 0 ? 'global' : 'tenant');

        // CONTENT LAW C3: undecidable tenancy is rejected, not defaulted.
        if ($subInstituteId === 0 && $scope !== 'global') {
            throw new \InvalidArgumentException(
                'sub_institute_id 0 is only legal with scope=global. Undecidable tenancy is rejected (C3).'
            );
        }

        $payload = array_merge($data, [
            'scope' => $scope,
            'sub_institute_id' => $subInstituteId,
        ]);

        $errors = PalVocabulary::validate($payload);
        if ($errors !== []) {
            throw new \InvalidArgumentException('Vocabulary validation failed: ' . implode(' | ', $errors));
        }

        $existing = $modelClass::where($keyColumn, $entityId)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        $fromStatus = $existing?->quality_status;
        $toStatus = $payload['quality_status'] ?? $fromStatus ?? 'draft';

        // CONTENT LAW C5 — two distinct prohibitions, both needed.
        if ($actorType !== 'human') {
            // (a) a machine may never STAMP a human-only status.
            if (! PalVocabulary::isMachineWritable($toStatus)) {
                throw new \InvalidArgumentException(
                    "Actor '{$actorType}' may not write quality_status '{$toStatus}'. "
                    . 'Machine-written tags are proposals and stay as draft (C5).'
                );
            }

            // (b) a machine may never MODIFY a row already sitting in one.
            // Checking only the transition would let a batch silently rewrite
            // the bloom level or misconception tags of already-approved content
            // — changing what learners are served with no human in the loop.
            if ($fromStatus !== null && ! PalVocabulary::isMachineWritable($fromStatus)) {
                throw new \InvalidArgumentException(
                    "Actor '{$actorType}' may not modify a row in quality_status '{$fromStatus}'. "
                    . 'Reviewed content changes only through human review (C5).'
                );
            }
        }

        if ($toStatus !== $fromStatus && ! PalVocabulary::canTransition($fromStatus, $toStatus)) {
            throw new \InvalidArgumentException(
                "Illegal QA transition '" . ($fromStatus ?? 'none') . "' -> '{$toStatus}' (spec §7.1)."
            );
        }

        $payload['quality_status'] = $toStatus;
        $payload['tagged_by'] = $payload['tagged_by'] ?? $actorType;

        // An approval must always be attributable to a person (plan §8, R4).
        if ($toStatus !== $fromStatus && ! PalVocabulary::isMachineWritable($toStatus)) {
            if ($actorId === null) {
                throw new \InvalidArgumentException("Status '{$toStatus}' requires a reviewer id.");
            }
            $payload['reviewed_by'] = $actorId;
            $payload['reviewed_at'] = now();
        }

        $changed = [];
        $row = DB::transaction(function () use (
            $modelClass, $keyColumn, $entityId, $subInstituteId, $payload, $existing, &$changed
        ) {
            if ($existing) {
                foreach ($payload as $k => $v) {
                    if (array_key_exists($k, $existing->getAttributes()) && $existing->{$k} != $v) {
                        $changed[] = $k;
                    }
                }
                $existing->fill($payload)->save();

                return $existing;
            }

            $changed = array_keys($payload);

            return $modelClass::create(array_merge($payload, [
                $keyColumn => $entityId,
                'sub_institute_id' => $subInstituteId,
            ]));
        });

        if ($fromStatus !== $toStatus || $changed !== []) {
            $this->log($entityType, $row->id, $subInstituteId, $fromStatus, $toStatus, $actorId, $actorType, $changed);
        }

        return $row;
    }

    /**
     * Move a metadata row through the QA pipeline (spec §7.1).
     *
     * Separated from upsert() so a reviewer console can approve without
     * resubmitting the whole payload.
     */
    public function transition(
        string $entityType,
        int $metadataId,
        string $toStatus,
        int $actorId,
        string $actorType = 'human',
        ?string $note = null
    ): object {
        [$modelClass] = $this->entity($entityType);

        $row = $modelClass::findOrFail($metadataId);
        $fromStatus = $row->quality_status;

        // Same two C5 prohibitions as upsert(): a machine may neither stamp a
        // human-only status nor move a row that is already in one.
        if ($actorType !== 'human') {
            if (! PalVocabulary::isMachineWritable($toStatus)) {
                throw new \InvalidArgumentException("Actor '{$actorType}' may not write '{$toStatus}' (C5).");
            }
            if (! PalVocabulary::isMachineWritable($fromStatus)) {
                throw new \InvalidArgumentException(
                    "Actor '{$actorType}' may not move a row out of '{$fromStatus}' (C5)."
                );
            }
        }

        if (! PalVocabulary::canTransition($fromStatus, $toStatus)) {
            throw new \InvalidArgumentException("Illegal QA transition '{$fromStatus}' -> '{$toStatus}'.");
        }

        $row->quality_status = $toStatus;
        if (! PalVocabulary::isMachineWritable($toStatus)) {
            $row->reviewed_by = $actorId;
            $row->reviewed_at = now();
        }
        $row->save();

        $this->log($entityType, $row->id, (int) $row->sub_institute_id, $fromStatus, $toStatus, $actorId, $actorType, [], $note);

        return $row;
    }

    /**
     * Bulk approval for the review console (plan P5: 50 items in one session).
     *
     * Each row is validated independently; one bad row does not sink the batch,
     * it is reported back so the reviewer sees exactly what failed.
     *
     * @param  array<int,int>  $metadataIds
     */
    public function bulkTransition(
        string $entityType,
        array $metadataIds,
        string $toStatus,
        int $actorId,
        ?string $note = null
    ): array {
        $ok = [];
        $failed = [];

        foreach ($metadataIds as $id) {
            try {
                $this->transition($entityType, (int) $id, $toStatus, $actorId, 'human', $note);
                $ok[] = (int) $id;
            } catch (\Throwable $e) {
                $failed[] = ['id' => (int) $id, 'error' => $e->getMessage()];
            }
        }

        return ['approved' => $ok, 'failed' => $failed, 'ok_count' => count($ok), 'fail_count' => count($failed)];
    }

    // ── Completeness / mandatory fields ──────────────────────────────────────

    /**
     * Mandatory fields before a row may reach 'approved' (spec Appendix).
     */
    public function mandatoryFields(string $entityType): array
    {
        return match ($entityType) {
            'question' => [
                'concept_ref_id', 'bloom_level', 'difficulty_1_to_5', 'pedagogy_mapping_id',
                'language', 'cultural_context', 'hpc_lens_primary', 'quality_status',
            ],
            'content' => [
                'concept_ref_id', 'content_type', 'variant_number', 'format',
                'bloom_level_served', 'difficulty_1_to_5', 'pedagogy_mapping_id',
                'language', 'cultural_context', 'hpc_lens_primary', 'quality_status',
            ],
            'concept' => ['concept_code', 'bloom_ceiling', 'hpc_lens', 'mastery_gate', 'quality_status'],
            default => [],
        };
    }

    /**
     * Which mandatory fields are still missing. The review console blocks
     * approval on a non-empty result.
     */
    public function missingMandatory(string $entityType, object $meta): array
    {
        $missing = [];
        foreach ($this->mandatoryFields($entityType) as $field) {
            $v = $meta->{$field} ?? null;
            if ($v === null || $v === '' || $v === []) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /** 0.0-1.0 share of mandatory fields present. Drives the console's progress bar. */
    public function completeness(string $entityType, object $meta): float
    {
        $fields = $this->mandatoryFields($entityType);
        if ($fields === []) {
            return 1.0;
        }

        $present = count($fields) - count($this->missingMandatory($entityType, $meta));

        return round($present / count($fields), 3);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    protected function entity(string $entityType): array
    {
        if (! isset(self::ENTITIES[$entityType])) {
            throw new \InvalidArgumentException(
                "Unknown entity type '{$entityType}'. Expected one of: " . implode(', ', array_keys(self::ENTITIES))
            );
        }

        return self::ENTITIES[$entityType];
    }

    protected function log(
        string $entityType,
        int $entityId,
        int $subInstituteId,
        ?string $from,
        string $to,
        ?int $actorId,
        string $actorType,
        array $changedFields = [],
        ?string $note = null
    ): void {
        ContentReviewLog::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'sub_institute_id' => $subInstituteId,
            'from_status' => $from,
            'to_status' => $to,
            'reviewed_by' => $actorId,
            'actor_type' => $actorType,
            'note' => $note,
            'changed_fields' => $changedFields ?: null,
        ]);
    }
}
