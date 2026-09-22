<?php

namespace Tests\Unit;

use App\Models\lms\h5p\H5pDragDrop;
use App\Models\lms\h5p\H5pDragDropElement;
use App\Models\lms\h5p\H5pDragDropZone;
use App\Services\lms\H5P\H5PDragQuestionBuilder;
use App\Services\lms\H5P\H5PPackageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

/**
 * Covers the two things that can silently corrupt a drag-and-drop activity:
 * the id <-> index translation H5P.DragQuestion's format requires, and the
 * package round trip.
 *
 * No database. Models are built in memory with their relations set, which is
 * all the builder reads -- so these run anywhere, including a CI box with no
 * MySQL, and they fail for the reason they name rather than on a fixture.
 */
class H5PDragQuestionBuilderTest extends TestCase
{
    private function task(): H5pDragDrop
    {
        $task = new H5pDragDrop([
            'title' => 'Parts of a flower',
            'task_description' => 'Drag each label onto the correct part.',
            'background_image' => 'https://cdn.example.test/flower.png',
            'image_fit' => 'cover',
            'canvas_width' => 620,
            'canvas_height' => 310,
            'pass_percentage' => 70,
            'enable_retry' => true,
            'enable_show_solution' => true,
            'enable_check' => true,
            'single_point' => false,
            'apply_penalties' => true,
            'background_opacity_full' => true,
        ]);
        $task->id = 42;

        // Ids are deliberately NOT 0,1,2 and NOT in ascending order of use.
        // If anything in the builder confuses an id for an index, this catches
        // it -- with ids 0,1,2 the bug is invisible.
        $petal = new H5pDragDropElement([
            'element_type' => 'text',
            'text' => 'Petal',
            'position_x' => 5, 'position_y' => 5, 'width' => 18, 'height' => 10,
            'multiple' => false,
            'drop_zone_ids' => [907],
            'sort_order' => 0,
        ]);
        $petal->id = 501;

        $stem = new H5pDragDropElement([
            'element_type' => 'text',
            'text' => 'Stem',
            'position_x' => 25, 'position_y' => 5, 'width' => 18, 'height' => 10,
            'multiple' => true,
            'drop_zone_ids' => [903, 907],
            'sort_order' => 1,
        ]);
        $stem->id = 733;

        $distractor = new H5pDragDropElement([
            'element_type' => 'image',
            'image_path' => 'https://cdn.example.test/leaf.png',
            'image_alt' => 'Leaf',
            'position_x' => 45, 'position_y' => 5, 'width' => 16, 'height' => 16,
            'multiple' => false,
            'drop_zone_ids' => [],
            'sort_order' => 2,
        ]);
        $distractor->id = 812;

        $upper = new H5pDragDropZone([
            'label' => 'Upper part',
            'tip' => 'The colourful bit.',
            'position_x' => 10, 'position_y' => 55, 'width' => 25, 'height' => 25,
            'single' => true, 'auto_align' => true, 'show_label' => true,
            'correct_element_ids' => [501],
            'sort_order' => 0,
        ]);
        $upper->id = 903;

        $lower = new H5pDragDropZone([
            'label' => 'Lower part',
            'tip' => '',
            'position_x' => 50, 'position_y' => 55, 'width' => 25, 'height' => 25,
            'single' => false, 'auto_align' => true, 'show_label' => true,
            'correct_element_ids' => [733],
            'sort_order' => 1,
        ]);
        $lower->id = 907;

        $task->setRelation('elements', new Collection([$petal, $stem, $distractor]));
        $task->setRelation('zones', new Collection([$upper, $lower]));

        return $task;
    }

    public function test_it_converts_ids_into_params_indices_on_both_sides(): void
    {
        $params = app(H5PDragQuestionBuilder::class)->build($this->task());

        $elements = $params['question']['task']['elements'];
        $zones = $params['question']['task']['dropZones'];

        // Element 501 (index 0) belongs in zone 907, which is index 1.
        $this->assertSame(['1'], $elements[0]['dropZones']);
        // Element 733 (index 1) belongs in zones 903 and 907 -- indices 0 and 1.
        $this->assertSame(['0', '1'], $elements[1]['dropZones']);
        // The distractor is droppable nowhere.
        $this->assertSame([], $elements[2]['dropZones']);

        // Zone 903 (index 0) accepts element 501, which is index 0.
        $this->assertSame(['0'], $zones[0]['correctElements']);
        // Zone 907 (index 1) accepts element 733, which is index 1.
        $this->assertSame(['1'], $zones[1]['correctElements']);
    }

    public function test_it_drops_references_to_deleted_items(): void
    {
        $task = $this->task();
        // A zone left pointing at an element the author has since removed.
        $task->zones->first()->correct_element_ids = [501, 99999];

        $params = app(H5PDragQuestionBuilder::class)->build($task);

        $this->assertSame(
            ['0'],
            $params['question']['task']['dropZones'][0]['correctElements'],
            'A dangling id must not be emitted as an index into the elements array.'
        );
    }

    public function test_it_maps_element_types_to_the_official_sub_libraries(): void
    {
        $params = app(H5PDragQuestionBuilder::class)->build($this->task());
        $elements = $params['question']['task']['elements'];

        $this->assertSame('H5P.AdvancedText 1.1', $elements[0]['type']['library']);
        $this->assertStringContainsString('Petal', $elements[0]['type']['params']['text']);

        $this->assertSame('H5P.Image 1.1', $elements[2]['type']['library']);
        $this->assertSame('Leaf', $elements[2]['type']['params']['alt']);
        $this->assertSame('image/png', $elements[2]['type']['params']['file']['mime']);
    }

    public function test_behaviour_and_pass_mark_survive_a_parse(): void
    {
        $builder = app(H5PDragQuestionBuilder::class);
        $parsed = $builder->parse($builder->build($this->task()));

        $this->assertSame(70, $parsed['task']['pass_percentage']);
        $this->assertTrue($parsed['task']['enable_retry']);
        $this->assertTrue($parsed['task']['apply_penalties']);
        $this->assertFalse($parsed['task']['single_point']);
        $this->assertSame(620, $parsed['task']['canvas_width']);

        // The background fit is ours, not H5P.DragQuestion's, so it rides in
        // the vendor extensions block. A package that never carried one parses
        // as contain -- the mode that cannot crop a diagram.
        $this->assertSame('cover', $parsed['task']['image_fit']);
        $this->assertSame('contain', $builder->parse([])['task']['image_fit']);

        // Refs come back as the params indices, and the mapping is preserved.
        $this->assertSame(['1'], $parsed['elements'][0]['_drop_zone_refs']);
        $this->assertSame(['1'], $parsed['zones'][1]['_correct_element_refs']);

        // One-to-one vs one-to-many is not lost.
        $this->assertTrue($parsed['zones'][0]['single']);
        $this->assertFalse($parsed['zones'][1]['single']);
        $this->assertTrue($parsed['elements'][1]['multiple']);
    }

    public function test_sub_content_ids_are_stable_across_exports(): void
    {
        $builder = app(H5PDragQuestionBuilder::class);

        $first = $builder->build($this->task());
        $second = $builder->build($this->task());

        $this->assertSame(
            $first['question']['task']['elements'][0]['type']['subContentId'],
            $second['question']['task']['elements'][0]['type']['subContentId'],
            'Exporting the same task twice must produce the same package.'
        );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-a[0-9a-f]{3}-[0-9a-f]{12}$/',
            $first['question']['task']['elements'][0]['type']['subContentId']
        );
    }

    public function test_export_writes_a_manifest_with_the_full_dependency_closure(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('The zip extension is not enabled.');
        }

        $package = app(H5PPackageService::class)->export($this->task());

        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($package['path']) === true);

            $manifest = json_decode($zip->getFromName('h5p.json'), true);
            $content = json_decode($zip->getFromName('content/content.json'), true);
            $zip->close();

            $this->assertSame('H5P.DragQuestion', $manifest['mainLibrary']);
            $this->assertSame('Parts of a flower', $manifest['title']);

            $machineNames = array_column($manifest['preloadedDependencies'], 'machineName');
            $this->assertSame('H5P.DragQuestion', $machineNames[0], 'The main library must come first.');
            foreach (['jQuery.ui', 'H5P.Question', 'H5P.JoubelUI', 'H5P.Transition', 'FontAwesome'] as $required) {
                $this->assertContains($required, $machineNames);
            }

            $this->assertCount(3, $content['question']['task']['elements']);
            $this->assertCount(2, $content['question']['task']['dropZones']);

            // The two remote images could not be fetched in a test, so they are
            // reported rather than silently dropped.
            $this->assertNotEmpty($package['warnings']);
        } finally {
            @unlink($package['path']);
            @rmdir(dirname($package['path']));
        }
    }

    public function test_import_rejects_a_package_of_the_wrong_type(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('The zip extension is not enabled.');
        }

        $path = $this->writePackage(
            ['title' => 'A video', 'mainLibrary' => 'H5P.InteractiveVideo'],
            ['question' => ['task' => ['elements' => [], 'dropZones' => []]]]
        );

        $this->expectExceptionMessageMatches('/H5P\.InteractiveVideo.*H5P\.DragQuestion/s');

        try {
            app(H5PPackageService::class)->import(
                new UploadedFile($path, 'wrong.h5p', 'application/zip', null, true),
                1
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_import_round_trips_an_exported_package(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('The zip extension is not enabled.');
        }

        $package = app(H5PPackageService::class)->export($this->task());

        try {
            $parsed = app(H5PPackageService::class)->import(
                new UploadedFile($package['path'], 'flower.h5p', 'application/zip', null, true),
                1
            );

            $this->assertSame('Parts of a flower', $parsed['title']);
            $this->assertSame(70, $parsed['task']['pass_percentage']);
            $this->assertCount(3, $parsed['elements']);
            $this->assertCount(2, $parsed['zones']);

            // The mapping survived id -> index -> ref.
            $this->assertSame(['1'], $parsed['zones'][1]['_correct_element_refs']);
            $this->assertSame('Petal', $parsed['elements'][0]['text']);
            $this->assertSame('image', $parsed['elements'][2]['element_type']);
        } finally {
            @unlink($package['path']);
            @rmdir(dirname($package['path']));
        }
    }

    /**
     * @param  array<string,mixed>  $manifest
     * @param  array<string,mixed>  $content
     */
    private function writePackage(array $manifest, array $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'h5p') . '.h5p';

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('h5p.json', json_encode($manifest));
        $zip->addFromString('content/content.json', json_encode($content));
        $zip->close();

        return $path;
    }
}
