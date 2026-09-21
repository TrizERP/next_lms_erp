<?php

namespace App\Http\Controllers\lms\h5p;

use App\Models\lms\h5p\H5pImageHotspotPoint;
use App\Models\lms\h5p\H5pImageHotspots;
use App\Services\lms\H5P\H5PImageHotspotsBuilder;
use App\Services\lms\H5P\H5PImageHotspotsPackageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * H5P Image Hotspots (H5P.ImageHotspots).
 *
 * The cycle is H5PContentTypeController's. What is here is what this type
 * genuinely is: a background, and hotspots on it whose popups can be text, an
 * image or rich content.
 *
 * WRITES ARE JSON, NOT MULTIPART, for the reason H5PDragDropController gives:
 * an item is a background plus an array of positioned children with nested
 * settings, and flattening that into `points[0][icon_color]` form keys would
 * be unreadable on both sides. Images are uploaded once by `media()` and
 * carried as URLs afterwards, so a save -- including an autosave -- costs no
 * image traffic.
 *
 * NOT TO BE CONFUSED WITH `scenario_based`. That is the older, simpler
 * `image_hotspot` type over h5p_scenarios, and it is untouched by this
 * controller. See the migration for why they are separate.
 */
class H5PImageHotspotsController extends H5PContentTypeController
{
    public function __construct(
        private readonly H5PImageHotspotsBuilder $builder,
        private readonly H5PImageHotspotsPackageService $packages
    ) {
    }

    protected function modelClass(): string
    {
        return H5pImageHotspots::class;
    }

    protected function registryCode(): string
    {
        return 'image_hotspots';
    }

    protected function routePrefix(): string
    {
        return 'h5p_image_hotspots';
    }

    protected function payloadKey(): string
    {
        return 'imageHotspots';
    }

    protected function listKey(): string
    {
        return 'imageHotspotsLists';
    }

    protected function label(): string
    {
        return 'Image hotspots activity';
    }

    protected function relations(): array
    {
        return ['points'];
    }

    protected function viewPath(): string
    {
        return 'lms/h5p/imagehotspots';
    }

    protected function mediaRoles(): array
    {
        return [
            'background' => 'image',
            'popup' => 'image',
            // A custom glyph in place of one of the built-in icons.
            'icon' => 'image',
        ];
    }

    protected function authoringDefaults(): array
    {
        return [
            'default_icon' => 'plus',
            'default_icon_color' => '#4f46e5',
            'show_hotspot_numbers' => true,
            'points_per_hotspot' => 1,
            'pass_percentage' => 100,
            'enable_retry' => true,
            'single_popup_open' => true,
            'popup_width' => 40,
            'icons' => H5pImageHotspots::ICONS,
            'popup_types' => H5pImageHotspots::POPUP_TYPES,
        ];
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    protected function saveRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_description' => 'nullable|string',

            'background_image' => 'required|string|max:2048',
            'background_alt' => 'nullable|string|max:255',
            'image_width' => 'nullable|integer|min:1|max:20000',
            'image_height' => 'nullable|integer|min:1|max:20000',

            'default_icon' => 'nullable|string|in:' . implode(',', H5pImageHotspots::ICONS),
            // Hex only. A named CSS colour would render differently in the
            // editor, the player and an exported package.
            'default_icon_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'show_hotspot_numbers' => 'nullable|boolean',

            'points_per_hotspot' => 'nullable|integer|min:1|max:100',
            'pass_percentage' => 'nullable|integer|min:0|max:100',
            'enable_retry' => 'nullable|boolean',
            'single_popup_open' => 'nullable|boolean',

            'feedback_bands' => 'nullable|array',
            'feedback_bands.*.from' => 'required|integer|min:0|max:100',
            'feedback_bands.*.to' => 'required|integer|min:0|max:100',
            'feedback_bands.*.feedback' => 'nullable|string|max:500',

            'points' => 'required|array|min:1',
            'points.*.position_x' => 'required|numeric|min:0|max:100',
            'points.*.position_y' => 'required|numeric|min:0|max:100',
            'points.*.header' => 'nullable|string|max:255',
            'points.*.popup_type' => 'required|in:' . implode(',', H5pImageHotspots::POPUP_TYPES),

            // A text or rich popup with no body is an empty box; an image
            // popup with no image is the same. required_if catches each,
            // which is what stops a published item containing a hotspot that
            // opens onto nothing.
            'points.*.body_text' => 'nullable|required_if:points.*.popup_type,text|required_if:points.*.popup_type,rich|string|max:20000',
            'points.*.popup_image' => 'nullable|required_if:points.*.popup_type,image|string|max:2048',
            'points.*.popup_image_alt' => 'nullable|string|max:255',

            'points.*.icon_name' => 'nullable|string|in:' . implode(',', H5pImageHotspots::ICONS),
            'points.*.icon_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'points.*.icon_image' => 'nullable|string|max:2048',
            'points.*.tooltip' => 'nullable|string|max:255',
            'points.*.aria_label' => 'nullable|string|max:255',
            'points.*.popup_width' => 'nullable|integer|min:10|max:100',
        ];
    }

    protected function attributesFrom(array $data, Request $request): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'task_description' => $data['task_description'] ?? '',
            'background_image' => $data['background_image'],
            'background_alt' => $data['background_alt'] ?? null,
            'image_width' => $data['image_width'] ?? null,
            'image_height' => $data['image_height'] ?? null,
            'default_icon' => $data['default_icon'] ?? 'plus',
            'default_icon_color' => $data['default_icon_color'] ?? '#4f46e5',
            'show_hotspot_numbers' => $data['show_hotspot_numbers'] ?? true,
            'points_per_hotspot' => $data['points_per_hotspot'] ?? 1,
            'pass_percentage' => $data['pass_percentage'] ?? 100,
            'enable_retry' => $data['enable_retry'] ?? true,
            'single_popup_open' => $data['single_popup_open'] ?? true,
            'feedback_bands' => $data['feedback_bands'] ?? null,
        ];
    }

    // -----------------------------------------------------------------------
    // Children
    // -----------------------------------------------------------------------

    protected function syncChildren(Model $item, array $data, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $audit = [
            'sub_institute_id' => $subInstituteId,
            'created_by' => $userId,
            'created_at' => now(),
        ];

        foreach (array_values($data['points']) as $order => $point) {
            H5pImageHotspotPoint::create($this->pointAttributes($point, $order) + [
                'image_hotspots_id' => $item->id,
            ] + $audit);
        }
    }

    /**
     * @param  array<string,mixed>  $point
     * @return array<string,mixed>
     */
    private function pointAttributes(array $point, int $order): array
    {
        $type = $point['popup_type'];

        return [
            'position_x' => $point['position_x'],
            'position_y' => $point['position_y'],
            'header' => $point['header'] ?? null,
            'popup_type' => $type,
            // Only the field the chosen popup kind uses is kept. Storing the
            // other one too would mean a hotspot switched from image back to
            // text still carried a picture nothing renders, and an export
            // that packaged it.
            'body_text' => in_array($type, ['text', 'rich'], true) ? ($point['body_text'] ?? '') : null,
            'popup_image' => $type === 'image' ? ($point['popup_image'] ?? '') : null,
            'popup_image_alt' => $type === 'image' ? ($point['popup_image_alt'] ?? null) : null,
            'icon_name' => $point['icon_name'] ?? null,
            'icon_color' => $point['icon_color'] ?? null,
            'icon_image' => $point['icon_image'] ?? null,
            'tooltip' => $point['tooltip'] ?? null,
            'aria_label' => $point['aria_label'] ?? null,
            'popup_width' => $point['popup_width'] ?? 40,
            'sort_order' => $order,
        ];
    }

    protected function duplicableColumns(): array
    {
        return [
            'description', 'task_description', 'background_image', 'background_alt',
            'image_width', 'image_height', 'default_icon', 'default_icon_color',
            'show_hotspot_numbers', 'points_per_hotspot', 'pass_percentage',
            'enable_retry', 'single_popup_open', 'feedback_bands',
            'standard_id', 'subject_id', 'chapter_id', 'syear',
        ];
    }

    protected function duplicateChildren(Model $original, Model $copy, int|string|null $subInstituteId, int|string|null $userId): void
    {
        foreach ($original->points as $point) {
            H5pImageHotspotPoint::create($point->only([
                'position_x', 'position_y', 'header', 'popup_type', 'body_text',
                'popup_image', 'popup_image_alt', 'icon_name', 'icon_color',
                'icon_image', 'tooltip', 'aria_label', 'popup_width', 'sort_order',
            ]) + [
                'image_hotspots_id' => $copy->id,
                'sub_institute_id' => $subInstituteId,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // Publish
    // -----------------------------------------------------------------------

    protected function publishBlocker(Model $item): ?string
    {
        $item->load('points');

        if (trim((string) $item->background_image) === '') {
            return 'Add a background image before publishing.';
        }

        if ($item->points->isEmpty()) {
            return 'Add at least one hotspot before publishing.';
        }

        // An image with no alt text is unusable by a learner using a screen
        // reader, and this type is nothing BUT an image. Publish is the right
        // place to insist, because a draft is allowed to be unfinished.
        if (trim((string) $item->background_alt) === '') {
            return 'Describe the background image before publishing, so it can be read by a screen reader.';
        }

        $empty = $item->points->first(function (H5pImageHotspotPoint $point) {
            return $point->popup_type === 'image'
                ? trim((string) $point->popup_image) === ''
                : trim((string) $point->body_text) === '';
        });

        if ($empty !== null) {
            return sprintf('Hotspot %d opens onto nothing. Add its content before publishing.', (int) $empty->sort_order + 1);
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // Params and packages
    // -----------------------------------------------------------------------

    protected function buildParams(Model $item): array
    {
        return $this->builder->build($item);
    }

    protected function exportPackage(Model $item): array
    {
        return $this->packages->export($item);
    }

    protected function parsePackage(UploadedFile $file, int|string|null $subInstituteId): array
    {
        return $this->packages->import($file, $subInstituteId);
    }

    protected function createFromImport(array $parsed, Request $request, int|string|null $subInstituteId, int|string|null $userId): Model
    {
        $item = H5pImageHotspots::create($parsed['item'] + [
            'title' => $parsed['title'],
            'description' => '',
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
            'sub_institute_id' => $subInstituteId,
            'syear' => $request->input('syear'),
            // An imported item always lands as a draft. The teacher checks it
            // against this chapter before students see it.
            'status' => 'draft',
            'library' => $this->libraryVersionString(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $audit = ['sub_institute_id' => $subInstituteId, 'created_by' => $userId, 'created_at' => now()];
        foreach ($parsed['points'] as $point) {
            H5pImageHotspotPoint::create($point + ['image_hotspots_id' => $item->id] + $audit);
        }

        return $item;
    }
}
