<?php

namespace App\Services\lms\Content;

/**
 * Stamps the ownership layer onto an already-fetched list of content assets.
 *
 * Delivers tracker "Content & LMS Architecture" row 4 / Decision #37:
 * "a teacher with creation rights sees default content PLUS their own additions
 *  layered on top - never a fork that replaces the default."
 *
 * WHY A DECORATOR AND NOT AN EXTRACTION OF THE READ QUERY
 * The obvious design is to move ApiLmsCourseController::getChapterContentCategories()
 * wholesale into a service. That was tried and rejected: the controller is under
 * active concurrent development (it grew from 958 to 2,096 lines while this work was
 * in progress, adding a source filter, a lms_concept join, URL resolution and a
 * description-derived concept fallback). Lifting that query into a service would fork
 * it, and the copy would silently drift from whatever the team lands next.
 *
 * So this class deliberately owns NO SQL for reading content. It takes the rows the
 * controller already fetched and adds provenance to them. The controller keeps one
 * source of truth for WHICH rows are returned; this class is the single source of
 * truth for WHO owns them.
 *
 * The overlay invariant is asserted in tests/Unit/ContentOwnershipDecoratorTest.php
 * rather than left to convention, because "never a fork" is a property of the output
 * and only a test can hold it.
 */
class ContentOwnershipDecorator
{
    public function __construct(
        private LmsContentVocabulary $vocabulary,
        private ContentProvenanceService $provenance
    ) {
    }

    /**
     * Add ownership fields to each asset in a flat content list.
     *
     * Adds, per asset:
     *   ownership              platform | school | teacher | null (not yet backfilled)
     *   layer                  platform | school | mine | unclassified  (the UI badge)
     *   authoring_mode         generate | upload | manual | imported | null
     *   derived_from_entity_id the platform item this one extends, if any
     *   overlay_entity_ids     items that extend THIS one (empty for most)
     *
     * Every input asset is returned. Nothing is filtered, reordered or replaced -
     * that is what makes this an overlay rather than a fork.
     *
     * @param  list<array<string,mixed>>  $assets  rows from content_master
     * @return list<array<string,mixed>>
     */
    public function apply(array $assets, int|string|null $tenantId, string $entityType = 'content'): array
    {
        if ($assets === []) {
            return [];
        }

        $tenantId = (int) $tenantId;

        $ids = array_values(array_filter(array_map(
            fn ($a) => $this->provenanceKey($a),
            $assets
        )));

        $provenance = $ids === []
            ? collect()
            : $this->provenance->forEntities($entityType, $ids, $tenantId);

        // Which items in this list are overlays, and what do they extend?
        $overlaysByParent = [];
        foreach ($assets as $asset) {
            $row = $provenance->get($this->provenanceKey($asset));
            $parent = $row?->derived_from_entity_id;
            if ($parent) {
                $overlaysByParent[(int) $parent][] = $this->provenanceKey($asset);
            }
        }

        $out = [];
        foreach ($assets as $asset) {
            $id = $this->provenanceKey($asset);
            $row = $id === null ? null : $provenance->get($id);

            if ($row) {
                $asset['ownership'] = $row->ownership;
                $asset['layer'] = $this->vocabulary->layerFor($row->ownership);
                $asset['authoring_mode'] = $row->authoring_mode;
                $asset['derived_from_entity_id'] = $row->derived_from_entity_id;
            } else {
                // No provenance row yet - the backfill has not reached this tenant, or
                // the row was written by a path that predates the sidecar. Platform
                // tenancy is still derivable from the row itself; anything else is
                // reported as 'unclassified' rather than guessed, so a missing backfill
                // stays diagnosable instead of silently becoming 'school'.
                $asset['ownership'] = null;
                $asset['layer'] = $this->vocabulary->isPlatformTenant($asset['sub_institute_id'] ?? null)
                    ? 'platform'
                    : 'unclassified';
                $asset['authoring_mode'] = null;
                $asset['derived_from_entity_id'] = null;
            }

            // A parent always stays in the list; it merely learns it has overlays.
            $asset['overlay_entity_ids'] = $id === null ? [] : ($overlaysByParent[$id] ?? []);

            $out[] = $asset;
        }

        return $out;
    }

    /**
     * The numeric key this asset is tracked under in lms_content_provenance.
     *
     * content_master rows use their own id. H5P assets carry a NAMESPACED string id
     * ("h5p:scenario:7") so they can never be mistaken for a content_master row, and
     * expose the real primary key separately as h5p_source_id. Casting the namespaced
     * form with (int) would silently yield 0 and look up the wrong row, so the key is
     * resolved explicitly rather than coerced.
     */
    private function provenanceKey(array $asset): ?int
    {
        if (isset($asset['h5p_source_id'])) {
            return (int) $asset['h5p_source_id'];
        }

        $id = $asset['id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }
}
