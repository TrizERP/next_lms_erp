<?php

namespace Database\Seeders;

use App\Models\lms\h5p\H5pDragDrop;
use App\Models\lms\h5p\H5pDragDropElement;
use App\Models\lms\h5p\H5pDragDropZone;
use App\Services\lms\H5P\H5PDragQuestionBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Two working Drag and Drop activities, for demos and for checking the type
 * end to end on a fresh environment.
 *
 *     php artisan db:seed --class=H5PDragDropSampleSeeder
 *
 * Scoping comes from the environment so this can be pointed at whatever
 * chapter the person running it actually has:
 *
 *     H5P_SAMPLE_TENANT, H5P_SAMPLE_STANDARD, H5P_SAMPLE_SUBJECT, H5P_SAMPLE_CHAPTER
 *
 * The two samples are deliberately different shapes, because they are the two
 * cases most likely to be broken by a change and least likely to be noticed:
 *
 *   1. "Parts of a flower"  -- one-to-one, plus a distractor that belongs
 *                              nowhere. Checks that a draggable with no mapping
 *                              is droppable nowhere and scores nothing.
 *   2. "Sort the shapes"    -- one-to-many: two zones that each hold several
 *                              draggables, and one draggable that is correct in
 *                              both. Checks pair-based scoring.
 *
 * Re-running replaces the samples rather than adding more, so a demo database
 * does not accumulate copies. Only rows this seeder created are touched --
 * they are matched on title within the target chapter.
 */
class H5PDragDropSampleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('h5p_drag_drop')) {
            $this->command?->warn('h5p_drag_drop does not exist yet. Run the migrations first.');

            return;
        }

        $scope = [
            'sub_institute_id' => (int) env('H5P_SAMPLE_TENANT', 1),
            'standard_id' => (int) env('H5P_SAMPLE_STANDARD', 1),
            'subject_id' => (int) env('H5P_SAMPLE_SUBJECT', 1),
            'chapter_id' => (int) env('H5P_SAMPLE_CHAPTER', 1),
        ];

        $this->seedFlowerParts($scope);
        $this->seedShapeSort($scope);

        $this->command?->info('Seeded 2 sample drag and drop activities into chapter ' . $scope['chapter_id'] . '.');
    }

    /** One-to-one mapping, with one distractor. */
    private function seedFlowerParts(array $scope): void
    {
        $task = $this->replace($scope, [
            'title' => 'Parts of a flower',
            'description' => 'Sample content. Labelling task for a Grade 6 botany chapter.',
            'task_description' => 'Drag each label onto the correct part of the flower.',
            // No background image: the sample must seed on an environment with
            // no media, and the task works without one.
            'background_image' => null,
            'canvas_width' => 620,
            'canvas_height' => 340,
            'pass_percentage' => 75,
            'enable_retry' => true,
            'enable_show_solution' => true,
            'enable_check' => true,
            'single_point' => false,
            'apply_penalties' => true,
        ]);

        $petal = $this->element($task, $scope, ['text' => 'Petal', 'position_x' => 6, 'position_y' => 6, 'sort_order' => 0]);
        $stem = $this->element($task, $scope, ['text' => 'Stem', 'position_x' => 30, 'position_y' => 6, 'sort_order' => 1]);
        $root = $this->element($task, $scope, ['text' => 'Root', 'position_x' => 54, 'position_y' => 6, 'sort_order' => 2]);
        // Belongs in no zone. A learner who places it loses a mark when
        // penalties are on -- which is what makes the distractor do any work.
        $this->element($task, $scope, ['text' => 'Gill', 'position_x' => 78, 'position_y' => 6, 'sort_order' => 3]);

        $zones = [
            ['label' => 'Top of the plant', 'tip' => 'The colourful part that attracts insects.', 'x' => 8, 'correct' => [$petal]],
            ['label' => 'Middle of the plant', 'tip' => 'It holds the flower up.', 'x' => 38, 'correct' => [$stem]],
            ['label' => 'Below the soil', 'tip' => 'It takes in water.', 'x' => 68, 'correct' => [$root]],
        ];

        foreach ($zones as $order => $zone) {
            $this->zone($task, $scope, [
                'label' => $zone['label'],
                'tip' => $zone['tip'],
                'position_x' => $zone['x'],
                'position_y' => 55,
                'width' => 24,
                'height' => 30,
                'single' => true,
                'correct_element_ids' => array_map(fn ($e) => $e->id, $zone['correct']),
                'sort_order' => $order,
            ]);
        }

        $this->finalise($task);
    }

    /** One-to-many: zones that hold several items, and one item correct in two. */
    private function seedShapeSort(array $scope): void
    {
        $task = $this->replace($scope, [
            'title' => 'Sort the shapes',
            'description' => 'Sample content. One-to-many sorting task for a Grade 4 geometry chapter.',
            'task_description' => 'Drag each shape into every group it belongs to.',
            'background_image' => null,
            'canvas_width' => 620,
            'canvas_height' => 320,
            'pass_percentage' => 60,
            'enable_retry' => true,
            'enable_show_solution' => true,
            'enable_check' => true,
            'single_point' => false,
            // Off: a sorting task where an item legitimately belongs in two
            // groups punishes exploration if wrong tries subtract.
            'apply_penalties' => false,
        ]);

        $square = $this->element($task, $scope, ['text' => 'Square', 'position_x' => 6, 'position_y' => 6, 'multiple' => true, 'sort_order' => 0]);
        $rectangle = $this->element($task, $scope, ['text' => 'Rectangle', 'position_x' => 28, 'position_y' => 6, 'sort_order' => 1]);
        $triangle = $this->element($task, $scope, ['text' => 'Triangle', 'position_x' => 52, 'position_y' => 6, 'sort_order' => 2]);
        $circle = $this->element($task, $scope, ['text' => 'Circle', 'position_x' => 74, 'position_y' => 6, 'sort_order' => 3]);

        // A square is both four-sided and equal-sided, so it is correct in both
        // zones -- and is the only element marked `multiple`.
        $this->zone($task, $scope, [
            'label' => 'Four sides',
            'tip' => 'Count the straight edges.',
            'position_x' => 8, 'position_y' => 50, 'width' => 38, 'height' => 40,
            'single' => false,
            'correct_element_ids' => [$square->id, $rectangle->id],
            'sort_order' => 0,
        ]);

        $this->zone($task, $scope, [
            'label' => 'All sides equal',
            'tip' => 'Every edge is the same length.',
            'position_x' => 54, 'position_y' => 50, 'width' => 38, 'height' => 40,
            'single' => false,
            'correct_element_ids' => [$square->id],
            'sort_order' => 1,
        ]);

        // Triangle and circle are droppable but correct nowhere here.
        foreach ([$triangle, $circle] as $element) {
            $element->drop_zone_ids = $task->zones()->pluck('id')->all();
            $element->save();
        }

        $this->finalise($task);
    }

    // -----------------------------------------------------------------------

    /** @param array<string,mixed> $attributes */
    private function replace(array $scope, array $attributes): H5pDragDrop
    {
        H5pDragDrop::where($scope)->where('title', $attributes['title'])->forceDelete();

        return H5pDragDrop::create($attributes + $scope + [
            'status' => 'published',
            'published_at' => now(),
            'background_opacity_full' => true,
            'library' => 'H5P.DragQuestion 1.14',
            'created_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $attributes */
    private function element(H5pDragDrop $task, array $scope, array $attributes): H5pDragDropElement
    {
        return H5pDragDropElement::create([
            'drag_drop_id' => $task->id,
            'element_type' => 'text',
            'width' => 18,
            'height' => 11,
            'multiple' => false,
            'drop_zone_ids' => [],
            'sub_institute_id' => $scope['sub_institute_id'],
            'created_at' => now(),
        ] + $attributes);
    }

    /** @param array<string,mixed> $attributes */
    private function zone(H5pDragDrop $task, array $scope, array $attributes): H5pDragDropZone
    {
        $zone = H5pDragDropZone::create([
            'drag_drop_id' => $task->id,
            'auto_align' => true,
            'show_label' => true,
            'sub_institute_id' => $scope['sub_institute_id'],
            'created_at' => now(),
        ] + $attributes);

        // Keep the element side of the mapping in step. The app does this in
        // the controller; a seeder writing rows directly has to do it too, or
        // the samples would render with nothing droppable anywhere.
        foreach ((array) ($attributes['correct_element_ids'] ?? []) as $elementId) {
            $element = H5pDragDropElement::find($elementId);
            if (! $element) {
                continue;
            }
            $element->drop_zone_ids = array_values(array_unique([...($element->drop_zone_ids ?? []), $zone->id]));
            $element->save();
        }

        return $zone;
    }

    /** Refresh the derived params cache, as a real save would. */
    private function finalise(H5pDragDrop $task): void
    {
        $task->load(['zones', 'elements']);
        $task->forceFill([
            'content_json' => json_encode(
                app(H5PDragQuestionBuilder::class)->build($task),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
        ])->saveQuietly();
    }
}
