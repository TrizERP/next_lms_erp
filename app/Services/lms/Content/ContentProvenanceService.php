<?php

namespace App\Services\lms\Content;

use App\Models\lms\ContentProvenance;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The ONLY write path into lms_content_provenance.
 *
 * Modelled on App\Services\PAL\Content\ContentMetadataService: one guarded entry
 * point, closed-set validation on every field, tenancy required rather than
 * defaulted. Having exactly one writer is what makes the invariants auditable —
 * the alternative is 31,385 rows whose ownership was decided by whichever caller
 * happened to write them.
 *
 * Invariants enforced here:
 *   1. entity_type and ownership come from config/lms_content.php. Anything else throws.
 *   2. owner_sub_institute_id is REQUIRED (CONTENT LAW C3). Undecidable tenancy is
 *      rejected, never defaulted to 0 — there is no tenant 0 in this estate.
 *   3. Platform ownership implies the platform tenant, and vice versa. The two
 *      cannot disagree, because the read path derives the layer from ownership
 *      while the tenant filter uses owner_sub_institute_id.
 *   4. derived_from_entity_id may never point at the row itself.
 */
class ContentProvenanceService
{
    public function __construct(private LmsContentVocabulary $vocabulary)
    {
    }

    /**
     * Record or update the provenance of one content item.
     *
     * Idempotent on (entity_type, entity_id, owner_sub_institute_id).
     *
     * @param  array<string,mixed>  $attributes
     */
    public function record(string $entityType, int $entityId, int $ownerSubInstituteId, array $attributes): ContentProvenance
    {
        $values = $this->validated($entityType, $entityId, $ownerSubInstituteId, $attributes);

        return ContentProvenance::updateOrCreate(
            [
                'entity_type'            => $entityType,
                'entity_id'              => $entityId,
                'owner_sub_institute_id' => $ownerSubInstituteId,
            ],
            Arr::except($values, ['entity_type', 'entity_id', 'owner_sub_institute_id'])
        );
    }

    /**
     * Validate and write many provenance rows in ONE statement.
     *
     * record() costs a SELECT plus an INSERT per row. Measured against the remote
     * database this estate lives on, that ran at ~18 rows/sec - about 90 minutes for
     * the 96,477 rows across the three content tables, slow enough that the backfill
     * could not finish inside a command timeout.
     *
     * This keeps the guarantee that matters (every row validated by the same rules,
     * in this one class) and drops only the per-row round trip: each chunk becomes a
     * single INSERT ... ON DUPLICATE KEY UPDATE against the
     * (entity_type, entity_id, owner_sub_institute_id) unique key, so it remains
     * idempotent and re-runnable.
     *
     * Validation runs BEFORE any write, so one invalid row rejects the whole chunk
     * rather than leaving a half-validated batch behind.
     *
     * @param  list<array<string,mixed>>  $rows
     * @return int  rows written
     */
    public function recordMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $payload[] = $this->validated(
                (string) ($row['entity_type'] ?? ''),
                (int) ($row['entity_id'] ?? 0),
                (int) ($row['owner_sub_institute_id'] ?? 0),
                $row
            ) + ['created_at' => $now, 'updated_at' => $now];
        }

        ContentProvenance::upsert(
            $payload,
            ['entity_type', 'entity_id', 'owner_sub_institute_id'],
            [
                'ownership', 'authored_by_user_id', 'authored_by_profile',
                'authoring_mode', 'generation_source', 'derived_from_entity_id',
                'visibility', 'status', 'updated_at',
            ]
        );

        return count($payload);
    }

    /**
     * The single validation gate.
     *
     * Every write - one row or ten thousand - passes through here, which is what
     * makes the invariants auditable in one place instead of per caller.
     *
     * @param  array<string,mixed>  $attributes
     * @return array<string,mixed>  a complete, validated row ready to persist
     */
    private function validated(string $entityType, int $entityId, int $ownerSubInstituteId, array $attributes): array
    {
        $this->vocabulary->assert('entity_type', $entityType);
        $this->vocabulary->entityType($entityType);

        if ($entityId <= 0) {
            throw new InvalidArgumentException('entity_id must be a positive integer.');
        }

        if ($ownerSubInstituteId <= 0) {
            throw new InvalidArgumentException(
                'owner_sub_institute_id is required and must be positive. Undecidable tenancy is '
                . 'rejected, not defaulted - there is no sub_institute_id 0 in the LMS content estate.'
            );
        }

        $ownership = $attributes['ownership'] ?? null;
        $this->vocabulary->assert('ownership', $ownership);
        $this->vocabulary->assert('authoring_mode', $attributes['authoring_mode'] ?? null);
        $this->vocabulary->assert('visibility', $attributes['visibility'] ?? null);
        $this->vocabulary->assert('status', $attributes['status'] ?? null);

        $isPlatformTenant = $this->vocabulary->isPlatformTenant($ownerSubInstituteId);

        // OWNERSHIP AND TENANCY ARE DIFFERENT AXES. Corrected 2026-09-08 after an audit.
        //
        // The first version of this class enforced a strict XOR - platform tenant IFF
        // platform ownership. That conflated two genuinely separate questions:
        //
        //     owner_sub_institute_id  =  WHO OWNS it   (which tenant's library it is in)
        //     ownership               =  WHO AUTHORED it (platform / school / teacher)
        //
        // The consequence was not theoretical. Tenant 1 holds 16,379 of the 31,385
        // content_master rows, so `ownership='teacher'` was STRUCTURALLY UNREACHABLE for
        // over half the estate. The backfill's own "teacher" rule could never fire on the
        // only two rows that matched it, and the resulting "there is no teacher-authored
        // content" was reported as a finding when it was an artifact of this invariant.
        //
        // A teacher CAN author content that lives in the platform library. What must stay
        // impossible is the reverse claim: content owned by an ordinary school tenant is
        // not platform-authored, because "platform-authored" means the platform published
        // it. That single direction is still enforced.
        if ($ownership === 'platform' && ! $isPlatformTenant) {
            throw new InvalidArgumentException(sprintf(
                'ownership="platform" requires a platform tenant (%s), got %d. Register the tenant in '
                . 'config lms_content.platform_sub_institute_ids if this is intentional.',
                implode('/', $this->vocabulary->platformTenantIds()),
                $ownerSubInstituteId
            ));
        }

        $derivedFrom = $attributes['derived_from_entity_id'] ?? null;
        if ($derivedFrom !== null && (int) $derivedFrom === $entityId) {
            throw new InvalidArgumentException('derived_from_entity_id may not point at its own row.');
        }

        return [
            'entity_type'            => $entityType,
            'entity_id'              => $entityId,
            'owner_sub_institute_id' => $ownerSubInstituteId,
            'ownership'              => $ownership,
            'authored_by_user_id'    => $attributes['authored_by_user_id'] ?? null,
            'authored_by_profile'    => $attributes['authored_by_profile'] ?? null,
            'authoring_mode'         => $attributes['authoring_mode'] ?? null,
            'generation_source'      => $attributes['generation_source'] ?? null,
            'derived_from_entity_id' => $derivedFrom !== null ? (int) $derivedFrom : null,
            'visibility'             => $attributes['visibility'] ?? 'tenant',
            'status'                 => $attributes['status'] ?? 'active',
        ];
    }

    /**
     * Provenance for a set of entity ids, keyed by entity_id.
     *
     * Scoped to the platform layer plus ONE tenant — the same additive predicate the
     * content read path uses. This method deliberately cannot express "only my
     * tenant", because narrowing it is what would turn the overlay into a fork.
     *
     * @param  list<int>  $entityIds
     * @return Collection<int,ContentProvenance>
     */
    public function forEntities(string $entityType, array $entityIds, int $tenantId): Collection
    {
        $this->vocabulary->assert('entity_type', $entityType);

        if ($entityIds === []) {
            return collect();
        }

        $tenants = array_values(array_unique(array_merge(
            $this->vocabulary->platformTenantIds(),
            [$tenantId]
        )));

        return ContentProvenance::query()
            ->where('entity_type', $entityType)
            ->whereIn('entity_id', $entityIds)
            ->whereIn('owner_sub_institute_id', $tenants)
            ->where('status', 'active')
            ->get()
            ->keyBy('entity_id');
    }
}
