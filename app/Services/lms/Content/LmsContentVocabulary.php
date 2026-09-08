<?php

namespace App\Services\lms\Content;

use InvalidArgumentException;

/**
 * Closed-set validator for the LMS content governance vocabulary.
 *
 * Mirrors App\Services\PAL\Content\PalVocabulary, deliberately: the two files
 * guard different axes (governance vs pedagogy) but enforce the same rule, which
 * is that an unregistered value is a WRITE FAILURE, not a new category.
 *
 * That rule is the only thing standing between 31,385 content rows and 400
 * spellings of "teacher authored". It is enforced here rather than in the caller
 * so there is exactly one place to audit.
 */
class LmsContentVocabulary
{
    /** @return array<string,array{label:string,layer:string}> */
    public function ownership(): array
    {
        return (array) config('lms_content.ownership', []);
    }

    /** @return list<string> */
    public function ownershipKeys(): array
    {
        return array_keys($this->ownership());
    }

    /** @return list<string> */
    public function authoringModes(): array
    {
        return (array) config('lms_content.authoring_modes', []);
    }

    /** @return list<string> */
    public function visibilities(): array
    {
        return (array) config('lms_content.visibility', []);
    }

    /** @return list<string> */
    public function statuses(): array
    {
        return (array) config('lms_content.statuses', []);
    }

    /** @return array<string,array{table:string,key:string,label:string}> */
    public function entityTypes(): array
    {
        return (array) config('lms_content.entity_types', []);
    }

    /** @return list<string> */
    public function entityTypeKeys(): array
    {
        return array_keys($this->entityTypes());
    }

    /**
     * The tenant ids that represent the shared platform curriculum layer.
     *
     * @return list<int>
     */
    public function platformTenantIds(): array
    {
        return array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1]));
    }

    public function isPlatformTenant(int|string|null $subInstituteId): bool
    {
        if ($subInstituteId === null || $subInstituteId === '') {
            return false;
        }

        return in_array((int) $subInstituteId, $this->platformTenantIds(), true);
    }

    /**
     * The badge the API hands the frontend for a given ownership value.
     *
     * Returned so the UI never has to re-derive ownership from a string match —
     * the mistake that produced isTeacherTrainingContent() in page.tsx:544.
     */
    public function layerFor(string $ownership): string
    {
        // FAILS CLOSED. This previously defaulted to 'platform', which meant an
        // unregistered ownership string rendered in the UI as the most-trusted badge.
        // On a trust marker the safe default is "we do not know".
        return $this->ownership()[$ownership]['layer'] ?? 'unclassified';
    }

    /**
     * Resolve an entity type to its physical table, or throw.
     *
     * @return array{table:string,key:string,label:string}
     */
    public function entityType(string $entityType): array
    {
        $types = $this->entityTypes();

        if (! isset($types[$entityType])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown content entity_type "%s". Registered: %s. Add it to config/lms_content.php rather than passing it through.',
                $entityType,
                implode(', ', array_keys($types))
            ));
        }

        return $types[$entityType];
    }

    /**
     * Validate one field against its closed set.
     *
     * @throws InvalidArgumentException
     */
    public function assert(string $field, ?string $value): void
    {
        $allowed = match ($field) {
            'ownership'      => $this->ownershipKeys(),
            'authoring_mode' => $this->authoringModes(),
            'visibility'     => $this->visibilities(),
            'status'         => $this->statuses(),
            'entity_type'    => $this->entityTypeKeys(),
            default          => throw new InvalidArgumentException("No closed set is registered for field \"{$field}\"."),
        };

        // Nullable fields are legitimately absent; only a PRESENT value is checked.
        if ($value === null || $value === '') {
            if ($field === 'ownership' || $field === 'entity_type') {
                throw new InvalidArgumentException("Field \"{$field}\" is required and may not be blank.");
            }

            return;
        }

        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                'Value "%s" is not registered for "%s". Allowed: %s.',
                $value,
                $field,
                implode(', ', $allowed)
            ));
        }
    }
}
