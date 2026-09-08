<?php

namespace Tests\Unit;

use App\Models\lms\ContentProvenance;
use App\Services\lms\Content\ContentOwnershipDecorator;
use App\Services\lms\Content\ContentProvenanceService;
use App\Services\lms\Content\LmsContentVocabulary;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The "never a fork" guarantee, asserted rather than assumed.
 *
 * Tracker "Content & LMS Architecture" row 4 / Decision #37 requires that a teacher
 * with creation rights sees platform content PLUS their own additions layered on top,
 * and never a fork that replaces the default. That is a property of the read path, so
 * a convention or a code comment cannot enforce it - only a test can.
 *
 * NO DATABASE. phpunit.xml has its sqlite/:memory: lines commented out (:24-25), so
 * anything touching the DB in a test hits the LIVE shared vivek_erp used by 56
 * tenants. ContentProvenanceService is therefore stubbed with an anonymous subclass,
 * and only the pure overlay logic is exercised.
 */
class ContentOwnershipDecoratorTest extends TestCase
{
    /**
     * @param  list<array{entity_id:int,ownership:string,derived_from_entity_id:int|null}>  $rows
     */
    private function decoratorWithProvenance(array $rows): ContentOwnershipDecorator
    {
        $vocabulary = new LmsContentVocabulary();

        $models = collect($rows)->map(function (array $row) {
            $model = new ContentProvenance();
            $model->forceFill([
                'entity_type'            => 'content',
                'entity_id'              => $row['entity_id'],
                'ownership'              => $row['ownership'],
                'authoring_mode'         => $row['authoring_mode'] ?? 'imported',
                'derived_from_entity_id' => $row['derived_from_entity_id'] ?? null,
            ]);

            return $model;
        })->keyBy('entity_id');

        $provenance = new class($vocabulary, $models) extends ContentProvenanceService {
            public function __construct(LmsContentVocabulary $vocabulary, private Collection $rows)
            {
                parent::__construct($vocabulary);
            }

            public function forEntities(string $entityType, array $entityIds, int $tenantId): Collection
            {
                return $this->rows->only($entityIds);
            }
        };

        return new ContentOwnershipDecorator($vocabulary, $provenance);
    }

    public function test_a_teacher_overlay_never_removes_the_platform_item_it_derives_from(): void
    {
        $assets = [
            ['id' => 10, 'sub_institute_id' => 1,  'content_category' => 'My Course'],
            ['id' => 11, 'sub_institute_id' => 1,  'content_category' => 'My Course'],
            ['id' => 90, 'sub_institute_id' => 42, 'content_category' => 'My Course'],
        ];

        $decorator = $this->decoratorWithProvenance([
            ['entity_id' => 10, 'ownership' => 'platform', 'derived_from_entity_id' => null],
            ['entity_id' => 11, 'ownership' => 'platform', 'derived_from_entity_id' => null],
            // 90 is a teacher's own version OF platform item 10.
            ['entity_id' => 90, 'ownership' => 'teacher',  'derived_from_entity_id' => 10],
        ]);

        $platformBefore = 2;
        $result = $decorator->apply($assets, 42);

        $platformAfter = count(array_filter($result, static fn ($a) => $a['layer'] === 'platform'));

        // THE guarantee: overlaying does not reduce the platform layer.
        $this->assertSame($platformBefore, $platformAfter, 'A teacher overlay suppressed a platform item - that is a fork.');
        $this->assertCount(3, $result, 'Overlaying must not drop any asset.');

        $byId = collect($result)->keyBy('id');

        // The parent is still there, and knows it has an overlay.
        $this->assertSame('platform', $byId[10]['layer']);
        $this->assertSame([90], $byId[10]['overlay_entity_ids']);

        // The untouched platform sibling has no overlay.
        $this->assertSame([], $byId[11]['overlay_entity_ids']);

        // The teacher item is present in its own right and points back at its parent.
        $this->assertSame('mine', $byId[90]['layer']);
        $this->assertSame(10, $byId[90]['derived_from_entity_id']);
    }

    public function test_ownership_maps_to_the_layer_badge_the_frontend_reads(): void
    {
        $assets = [
            ['id' => 1, 'sub_institute_id' => 1,  'content_category' => 'My Course'],
            ['id' => 2, 'sub_institute_id' => 42, 'content_category' => 'Worksheet'],
            ['id' => 3, 'sub_institute_id' => 42, 'content_category' => 'Worksheet'],
        ];

        $result = $this->decoratorWithProvenance([
            ['entity_id' => 1, 'ownership' => 'platform'],
            ['entity_id' => 2, 'ownership' => 'school'],
            ['entity_id' => 3, 'ownership' => 'teacher'],
        ])->apply($assets, 42);

        $this->assertSame(['platform', 'school', 'mine'], array_column($result, 'layer'));
    }

    public function test_an_unbackfilled_row_is_reported_as_unclassified_not_guessed(): void
    {
        $assets = [
            ['id' => 7, 'sub_institute_id' => 1,  'content_category' => 'My Course'],
            ['id' => 8, 'sub_institute_id' => 42, 'content_category' => 'My Course'],
        ];

        // No provenance rows at all - the backfill has not run for this tenant.
        $result = $this->decoratorWithProvenance([])->apply($assets, 42);

        $byId = collect($result)->keyBy('id');

        // Platform tenancy is still derivable from the row itself.
        $this->assertSame('platform', $byId[7]['layer']);
        $this->assertNull($byId[7]['ownership']);

        // A tenant row with no provenance is NOT silently called 'school'. The gap
        // stays visible so a missing backfill is diagnosable rather than invisible.
        $this->assertSame('unclassified', $byId[8]['layer']);
        $this->assertNull($byId[8]['ownership']);
    }

    public function test_an_h5p_asset_resolves_provenance_by_its_real_key_not_its_namespaced_id(): void
    {
        // H5P assets carry a namespaced id so they can never collide with a
        // content_master id. (int) "h5p:scenario:7" is 0, which would silently look up
        // the wrong provenance row - so the real key is passed separately.
        $assets = [
            ['id' => 'h5p:scenario:7', 'h5p_source_id' => 7, 'sub_institute_id' => 1, 'format' => 'h5p'],
        ];

        $result = $this->decoratorWithProvenance([
            ['entity_id' => 7, 'ownership' => 'platform'],
        ])->apply($assets, 42, 'h5p');

        $this->assertSame('platform', $result[0]['layer']);
        $this->assertSame('platform', $result[0]['ownership'], 'H5P provenance must resolve via h5p_source_id.');
        $this->assertSame('h5p:scenario:7', $result[0]['id'], 'The namespaced id must survive untouched.');
    }

    public function test_an_empty_chapter_returns_an_empty_list(): void
    {
        $this->assertSame([], $this->decoratorWithProvenance([])->apply([], 42));
    }
}
