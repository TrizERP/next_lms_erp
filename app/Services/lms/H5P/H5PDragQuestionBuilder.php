<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pDragDrop;

/**
 * Translates between this ERP's drag-and-drop rows and H5P.DragQuestion params.
 *
 * WHY THIS EXISTS AT ALL
 *
 * The rows in h5p_drag_drop* are the source of truth and this product's own
 * player reads them directly -- it does not need H5P params to render a task.
 * What needs them is the boundary: export to a .h5p package, import of one
 * authored elsewhere, and any future swap to the official H5P player, which
 * this class makes a change of renderer rather than a change of data model.
 *
 * THE ONE THING WORTH KNOWING ABOUT THE FORMAT
 *
 * H5P.DragQuestion does not reference elements and drop zones by id. It
 * references them by their INDEX in the params arrays, as strings:
 * `dropZones[2].correctElements = ["0","3"]` means "the first and fourth
 * element". So every build has to carry an id->index map and every parse has
 * to carry an index->id map, and neither can be skipped just because the
 * arrays happen to be in id order today. Both maps are built explicitly below
 * (`indexById`, and the `$byIndex` list in parse()) for exactly that reason.
 *
 * Geometry is stored here as a percentage of the canvas and H5P.DragQuestion
 * also uses percentages, so it passes straight through.
 */
class H5PDragQuestionBuilder
{
    /** Sub-library used to render a text draggable. */
    private const TEXT_LIBRARY = 'H5P.AdvancedText 1.1';

    /** Sub-library used to render an image draggable. */
    private const IMAGE_LIBRARY = 'H5P.Image 1.1';

    /**
     * Build the H5P.DragQuestion `params` object for one task.
     *
     * Any key the import round trip preserved but this schema does not model
     * is merged back in from the cached `content_json`, so a package authored
     * in the official editor does not quietly lose fields on re-export.
     *
     * @return array<string,mixed>
     */
    public function build(H5pDragDrop $task): array
    {
        $elements = $task->relationLoaded('elements') ? $task->elements : $task->elements()->get();
        $zones = $task->relationLoaded('zones') ? $task->zones : $task->zones()->get();

        // id -> params-array index, for both sides of the mapping.
        $elementIndex = $this->indexById($elements);
        $zoneIndex = $this->indexById($zones);

        $paramElements = [];
        foreach ($elements as $element) {
            $paramElements[] = [
                'x' => $this->num($element->position_x),
                'y' => $this->num($element->position_y),
                'width' => $this->num($element->width),
                'height' => $this->num($element->height),
                'type' => $this->elementType($element),
                // Element -> zones. Unmapped is legal: it is a distractor.
                'dropZones' => $this->mapIds($element->drop_zone_ids, $zoneIndex),
                'backgroundOpacity' => 0,
                'multiple' => (bool) $element->multiple,
            ];
        }

        $paramZones = [];
        foreach ($zones as $zone) {
            $paramZones[] = [
                'x' => $this->num($zone->position_x),
                'y' => $this->num($zone->position_y),
                'width' => $this->num($zone->width),
                'height' => $this->num($zone->height),
                // Zone -> elements. This is what scoring is checked against.
                'correctElements' => $this->mapIds($zone->correct_element_ids, $elementIndex),
                'showLabel' => (bool) $zone->show_label,
                'label' => $zone->label ? '<div>' . e($zone->label) . '</div>' : '',
                'tipsAndFeedback' => ['tip' => (string) ($zone->tip ?? '')],
                // single = one-to-one; cleared = one-to-many.
                'single' => (bool) $zone->single,
                'autoAlign' => (bool) $zone->auto_align,
                'backgroundOpacity' => 0,
            ];
        }

        $params = [
            'scoreShow' => 'Check',
            'tryAgain' => 'Retry',
            'showSolution' => 'Show solution',
            'question' => [
                'settings' => [
                    'size' => [
                        'width' => (int) $task->canvas_width,
                        'height' => (int) $task->canvas_height,
                    ],
                    'background' => $task->background_image
                        ? [
                            'path' => (string) $task->background_image,
                            'mime' => $this->mimeFor((string) $task->background_image),
                            'copyright' => ['license' => config('h5p_libraries.package.default_license', 'U')],
                            'width' => (int) $task->canvas_width,
                            'height' => (int) $task->canvas_height,
                        ]
                        : null,
                ],
                'task' => [
                    'elements' => $paramElements,
                    'dropZones' => $paramZones,
                ],
            ],
            'behaviour' => [
                'enableRetry' => (bool) $task->enable_retry,
                'enableCheckButton' => (bool) $task->enable_check,
                'enableSolutionsButton' => (bool) $task->enable_show_solution,
                'singlePoint' => (bool) $task->single_point,
                'applyPenalties' => (bool) $task->apply_penalties,
                'showScorePoints' => true,
                'dropZoneHighlighting' => 'dragging',
                'autoAlignSpacing' => 2,
                'enableFullScreen' => false,
                'showTitle' => false,
                'backgroundOpacity' => $task->background_opacity_full ? '' : '50',
            ],
            // The pass mark lives here in H5P: a single band whose `from` is
            // the percentage a learner must reach.
            'overallFeedback' => [
                [
                    'from' => 0,
                    'to' => max(0, (int) $task->pass_percentage - 1),
                    'feedback' => 'Keep practising -- try the ones that moved back.',
                ],
                [
                    'from' => (int) $task->pass_percentage,
                    'to' => 100,
                    'feedback' => 'Well done.',
                ],
            ],
            // Not part of H5P.DragQuestion's semantics. Namespaced under a
            // vendor key so an official host ignores it and a re-import here
            // can recover the pass mark exactly rather than inferring it.
            'eduerpExtensions' => [
                'passPercentage' => (int) $task->pass_percentage,
                'taskDescription' => (string) ($task->task_description ?? ''),
                // H5P.DragQuestion has no background fit of its own -- it draws
                // the background at the authored canvas size and leaves it
                // there. Carried here so a round trip through a package keeps
                // the author's choice instead of silently reverting to cover.
                'imageFit' => (string) ($task->image_fit ?: 'contain'),
            ],
        ];

        return $this->mergePreservedKeys($params, $task->content_json);
    }

    /**
     * Read an H5P.DragQuestion `params` object back into row payloads.
     *
     * Returns arrays ready for mass assignment, with `_ref` carrying each
     * item's params index so the caller can rebuild the id mapping once the
     * rows have real primary keys.
     *
     * @param  array<string,mixed>  $params
     * @return array{task: array<string,mixed>, elements: list<array<string,mixed>>, zones: list<array<string,mixed>>}
     */
    public function parse(array $params): array
    {
        $question = is_array($params['question'] ?? null) ? $params['question'] : [];
        $settings = is_array($question['settings'] ?? null) ? $question['settings'] : [];
        $taskNode = is_array($question['task'] ?? null) ? $question['task'] : [];
        $behaviour = is_array($params['behaviour'] ?? null) ? $params['behaviour'] : [];
        $extensions = is_array($params['eduerpExtensions'] ?? null) ? $params['eduerpExtensions'] : [];

        $size = is_array($settings['size'] ?? null) ? $settings['size'] : [];
        $background = is_array($settings['background'] ?? null) ? $settings['background'] : [];

        $elements = [];
        foreach (array_values((array) ($taskNode['elements'] ?? [])) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $type = is_array($raw['type'] ?? null) ? $raw['type'] : [];
            $library = (string) ($type['library'] ?? '');
            $typeParams = is_array($type['params'] ?? null) ? $type['params'] : [];
            $isImage = str_starts_with($library, 'H5P.Image');
            $file = is_array($typeParams['file'] ?? null) ? $typeParams['file'] : [];

            $elements[] = [
                '_ref' => (string) $index,
                'element_type' => $isImage ? 'image' : 'text',
                // Draggable text is authored as HTML by the official editor;
                // this product's authoring UI is plain text, so it is reduced
                // to text here rather than stored as markup it cannot edit.
                'text' => $isImage ? null : trim(strip_tags((string) ($typeParams['text'] ?? ''))),
                'image_path' => $isImage ? (string) ($file['path'] ?? '') : null,
                'image_alt' => $isImage ? (string) ($typeParams['alt'] ?? '') : null,
                'position_x' => $this->num($raw['x'] ?? 0),
                'position_y' => $this->num($raw['y'] ?? 0),
                'width' => $this->num($raw['width'] ?? 15),
                'height' => $this->num($raw['height'] ?? 10),
                'multiple' => (bool) ($raw['multiple'] ?? false),
                'sort_order' => $index,
                '_drop_zone_refs' => array_map('strval', (array) ($raw['dropZones'] ?? [])),
            ];
        }

        $zones = [];
        foreach (array_values((array) ($taskNode['dropZones'] ?? [])) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $tips = is_array($raw['tipsAndFeedback'] ?? null) ? $raw['tipsAndFeedback'] : [];

            $zones[] = [
                '_ref' => (string) $index,
                'label' => trim(strip_tags((string) ($raw['label'] ?? ''))),
                'tip' => (string) ($tips['tip'] ?? ''),
                'position_x' => $this->num($raw['x'] ?? 0),
                'position_y' => $this->num($raw['y'] ?? 0),
                'width' => $this->num($raw['width'] ?? 20),
                'height' => $this->num($raw['height'] ?? 20),
                'single' => (bool) ($raw['single'] ?? true),
                'auto_align' => (bool) ($raw['autoAlign'] ?? true),
                'show_label' => (bool) ($raw['showLabel'] ?? true),
                'sort_order' => $index,
                '_correct_element_refs' => array_map('strval', (array) ($raw['correctElements'] ?? [])),
            ];
        }

        // The pass mark: our own extension if the package carries it, else the
        // lower bound of the highest overallFeedback band, else 100.
        $pass = isset($extensions['passPercentage'])
            ? (int) $extensions['passPercentage']
            : $this->passFromFeedback((array) ($params['overallFeedback'] ?? []));

        return [
            'task' => [
                'canvas_width' => (int) ($size['width'] ?? 620),
                'canvas_height' => (int) ($size['height'] ?? 310),
                'background_image' => (string) ($background['path'] ?? '') ?: null,
                'image_fit' => in_array($extensions['imageFit'] ?? null, ['contain', 'cover', 'original', 'stretch'], true)
                    ? (string) $extensions['imageFit']
                    : 'contain',
                'task_description' => (string) ($extensions['taskDescription'] ?? ''),
                'pass_percentage' => max(0, min(100, $pass)),
                'enable_retry' => (bool) ($behaviour['enableRetry'] ?? true),
                'enable_check' => (bool) ($behaviour['enableCheckButton'] ?? true),
                'enable_show_solution' => (bool) ($behaviour['enableSolutionsButton'] ?? true),
                'single_point' => (bool) ($behaviour['singlePoint'] ?? false),
                'apply_penalties' => (bool) ($behaviour['applyPenalties'] ?? true),
                'background_opacity_full' => ($behaviour['backgroundOpacity'] ?? '') === '',
            ],
            'elements' => $elements,
            'zones' => $zones,
        ];
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * @param  iterable<object>  $rows
     * @return array<int|string,string> model id -> params index, as a string
     */
    private function indexById(iterable $rows): array
    {
        $map = [];
        $index = 0;
        foreach ($rows as $row) {
            $map[(string) $row->id] = (string) $index;
            $index++;
        }

        return $map;
    }

    /**
     * Translate stored ids into params indices, dropping anything that no
     * longer resolves -- a zone can reference an element the author has since
     * deleted, and emitting a dangling index would corrupt the package.
     *
     * @param  mixed  $ids
     * @param  array<int|string,string>  $map
     * @return list<string>
     */
    private function mapIds($ids, array $map): array
    {
        $out = [];
        foreach ((array) ($ids ?? []) as $id) {
            $key = (string) $id;
            if (isset($map[$key])) {
                $out[] = $map[$key];
            }
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function elementType(object $element): array
    {
        if ($element->element_type === 'image') {
            return [
                'library' => self::IMAGE_LIBRARY,
                'params' => [
                    'file' => [
                        'path' => (string) ($element->image_path ?? ''),
                        'mime' => $this->mimeFor((string) ($element->image_path ?? '')),
                        'copyright' => ['license' => config('h5p_libraries.package.default_license', 'U')],
                    ],
                    'alt' => (string) ($element->image_alt ?? ''),
                ],
                'subContentId' => $this->subContentId('element', (int) $element->id),
                'metadata' => [
                    'contentType' => 'Image',
                    'license' => config('h5p_libraries.package.default_license', 'U'),
                    'title' => (string) ($element->image_alt ?: 'Draggable image'),
                ],
            ];
        }

        return [
            'library' => self::TEXT_LIBRARY,
            'params' => ['text' => '<p>' . e((string) ($element->text ?? '')) . '</p>'],
            'subContentId' => $this->subContentId('element', (int) $element->id),
            'metadata' => [
                'contentType' => 'Text',
                'license' => config('h5p_libraries.package.default_license', 'U'),
                'title' => (string) ($element->text ?: 'Draggable text'),
            ],
        ];
    }

    /**
     * A stable UUIDv4-shaped sub-content id.
     *
     * H5P requires the format but not randomness, and deriving it from the row
     * means exporting the same task twice produces identical packages -- which
     * is what makes an export diffable and a re-import idempotent.
     */
    private function subContentId(string $kind, int $id): string
    {
        $hash = md5('eduerp:h5p:dragquestion:' . $kind . ':' . $id);

        return sprintf(
            '%s-%s-4%s-a%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }

    private function mimeFor(string $path): string
    {
        $extension = strtolower((string) pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }

    private function num(mixed $value): float
    {
        return round((float) $value, 4);
    }

    /** @param list<mixed> $bands */
    private function passFromFeedback(array $bands): int
    {
        $pass = 100;
        foreach ($bands as $band) {
            if (is_array($band) && (int) ($band['to'] ?? 0) >= 100 && isset($band['from'])) {
                $pass = (int) $band['from'];
            }
        }

        return $pass;
    }

    /**
     * Re-attach keys an imported package carried that this schema does not
     * model, without letting them overwrite anything it does.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function mergePreservedKeys(array $params, ?string $cachedJson): array
    {
        if (! $cachedJson) {
            return $params;
        }

        $cached = json_decode($cachedJson, true);
        if (! is_array($cached)) {
            return $params;
        }

        foreach ($cached as $key => $value) {
            if (! array_key_exists($key, $params)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}
