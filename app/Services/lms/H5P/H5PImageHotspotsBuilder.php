<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pImageHotspots;

/**
 * Translates between h5p_image_hotspots rows and H5P.ImageHotspots params.
 *
 * Same job and same reason as H5PDragQuestionBuilder: the rows are the source
 * of truth and this product's player reads them directly. What needs params is
 * the file boundary -- export, import, and any future swap to the official
 * player, which this class makes a change of renderer rather than of schema.
 *
 * THE ONE THING WORTH KNOWING ABOUT THIS FORMAT
 *
 * A hotspot's popup is not a field. It is a LIST OF SUB-CONTENT:
 *
 *   hotspots[n].action = [ {library: "H5P.AdvancedText 1.1", params: {text}},
 *                          {library: "H5P.Image 1.1",        params: {file}} ]
 *
 * So "text popup", "image popup" and "rich content popup" are not three
 * shapes; they are one shape with one or two entries in `action`. That is why
 * `popup_type` maps to a list here and why parse() reads a list back rather
 * than looking for a field per kind -- a package authored in the official
 * editor will happily contain a popup with three entries, and this reads it.
 *
 * COORDINATES. H5P.ImageHotspots stores `position: {x, y}` as percentages of
 * the image, which is what the columns hold, so they pass straight through.
 */
class H5PImageHotspotsBuilder
{
    use ConvertsToH5PParams;

    private const REGISTRY_CODE = 'image_hotspots';

    private const TEXT_LIBRARY = 'H5P.AdvancedText 1.1';
    private const IMAGE_LIBRARY = 'H5P.Image 1.1';

    // -----------------------------------------------------------------------
    // Build
    // -----------------------------------------------------------------------

    /**
     * Build the H5P.ImageHotspots `params` object for one item.
     *
     * @return array<string,mixed>
     */
    public function build(H5pImageHotspots $item): array
    {
        $points = $item->relationLoaded('points') ? $item->points : $item->points()->get();

        $hotspots = [];
        foreach ($points as $index => $point) {
            $hotspots[] = [
                'position' => [
                    'x' => $this->num($point->position_x),
                    'y' => $this->num($point->position_y),
                ],
                'alwaysFullscreen' => false,
                'popupWidth' => max(10, min(100, (int) $point->popup_width)),
                'header' => (string) ($point->header ?? ''),
                // The accessible name, which the model guarantees is non-empty.
                'ariaLabel' => $point->accessibleName(),
                // Hover/focus text. H5P has no field for this, so it rides in
                // the extension namespace the spec reserves for exactly this
                // -- a host that does not know it ignores it, and a re-import
                // here reads it back.
                'eduerpTooltip' => (string) ($point->tooltip ?? ''),
                'eduerpIcon' => $this->iconDescriptor($item, $point),
                'action' => $this->popupAction($point),
            ];
        }

        $params = [
            'image' => $this->imageFile($item->background_image, $item->image_width, $item->image_height),
            'backgroundImageAltText' => (string) ($item->background_alt ?? ''),
            'hotspots' => $hotspots,
            'hotspotNumberLabel' => $item->show_hotspot_numbers ? 'Hotspot #num' : '',
            'header' => (string) ($item->task_description ?? ''),
            'iconType' => 'icon',
            'icon' => (string) ($item->default_icon ?: 'plus'),
            'color' => (string) ($item->default_icon_color ?: '#4f46e5'),
            'closeButtonLabel' => 'Close',
            'l10n' => [
                'close' => 'Close',
            ],
            // Not part of H5P.ImageHotspots. Carried so an export round trip
            // through this product does not lose the coverage scoring the
            // migration explains, and so a re-import restores it.
            'eduerpScoring' => [
                'pointsPerHotspot' => max(1, (int) $item->points_per_hotspot),
                'passPercentage' => max(0, min(100, (int) $item->pass_percentage)),
                'enableRetry' => (bool) $item->enable_retry,
                'singlePopupOpen' => (bool) $item->single_popup_open,
            ],
            'overallFeedback' => $this->feedbackBands($item->feedback_bands),
        ];

        return $this->mergePreservedKeys($params, $item->content_json);
    }

    /**
     * The `action` list for one hotspot.
     *
     * `rich` and `text` both render through H5P.AdvancedText -- the difference
     * is what the editor let the author put in it, not what the library does
     * with it. `image` emits H5P.Image, and an image popup that also carries a
     * caption emits both, in reading order.
     *
     * @return list<array<string,mixed>>
     */
    private function popupAction(object $point): array
    {
        $action = [];
        $body = trim((string) ($point->body_text ?? ''));
        $image = trim((string) ($point->popup_image ?? ''));

        if ($point->popup_type === 'image' && $image !== '') {
            $action[] = [
                'library' => self::IMAGE_LIBRARY,
                'subContentId' => $this->subContentId('imagehotspots:image', (int) $point->id),
                'params' => [
                    'file' => $this->imageFile($image),
                    'alt' => (string) ($point->popup_image_alt ?? ''),
                    'decorative' => false,
                ],
                'metadata' => ['contentType' => 'Image', 'license' => 'U', 'title' => $point->accessibleName()],
            ];
        }

        if ($body !== '') {
            $action[] = [
                'library' => self::TEXT_LIBRARY,
                'subContentId' => $this->subContentId('imagehotspots:text', (int) $point->id),
                'params' => [
                    // `text` is HTML in H5P.AdvancedText for every popup kind.
                    // A `text` popup is a single escaped paragraph; a `rich`
                    // one is the sanitised markup the editor produced.
                    'text' => $point->popup_type === 'rich' ? $body : '<p>' . e($body) . '</p>',
                ],
                'metadata' => ['contentType' => 'Text', 'license' => 'U', 'title' => $point->accessibleName()],
            ];
        }

        return $action;
    }

    /**
     * The per-hotspot icon, resolved against the item's defaults.
     *
     * Resolved HERE rather than left as nulls for the renderer to fall back
     * through, so the exported package is self-describing and the player, the
     * editor preview and an importing host all draw the same hotspot.
     *
     * @return array<string,mixed>
     */
    private function iconDescriptor(H5pImageHotspots $item, object $point): array
    {
        $custom = trim((string) ($point->icon_image ?? ''));

        return [
            'type' => $custom !== '' ? 'image' : 'icon',
            'name' => (string) ($point->icon_name ?: $item->default_icon ?: 'plus'),
            'color' => (string) ($point->icon_color ?: $item->default_icon_color ?: '#4f46e5'),
            'image' => $custom !== '' ? $custom : null,
        ];
    }

    /**
     * An H5P `file` object.
     *
     * Width and height are optional in the format and are included when known,
     * because a host that has them can reserve the box before the image loads
     * -- the same reason the columns exist.
     *
     * @return array<string,mixed>
     */
    private function imageFile(?string $path, ?int $width = null, ?int $height = null): array
    {
        $path = (string) ($path ?? '');
        $file = [
            'path' => $path,
            'mime' => $this->mimeFor($path),
            'copyright' => ['license' => 'U'],
        ];
        if ($width) {
            $file['width'] = $width;
        }
        if ($height) {
            $file['height'] = $height;
        }

        return $file;
    }

    // -----------------------------------------------------------------------
    // Parse
    // -----------------------------------------------------------------------

    /**
     * Read an H5P.ImageHotspots params object into row payloads.
     *
     * Nothing is written here -- the controller owns that, so an import stays
     * in one transaction with its audit entry.
     *
     * @param  array<string,mixed>  $params
     * @return array{item: array<string,mixed>, points: list<array<string,mixed>>}
     */
    public function parse(array $params): array
    {
        $scoring = (array) ($params['eduerpScoring'] ?? []);
        $bands = (array) ($params['overallFeedback'] ?? []);

        $item = [
            'task_description' => (string) ($params['header'] ?? ''),
            'background_image' => (string) ($params['image']['path'] ?? ''),
            'background_alt' => (string) ($params['backgroundImageAltText'] ?? ''),
            'image_width' => isset($params['image']['width']) ? (int) $params['image']['width'] : null,
            'image_height' => isset($params['image']['height']) ? (int) $params['image']['height'] : null,
            'default_icon' => (string) ($params['icon'] ?? 'plus'),
            'default_icon_color' => (string) ($params['color'] ?? '#4f46e5'),
            // H5P blanks this label to turn numbering off, which is the only
            // signal a foreign package gives us.
            'show_hotspot_numbers' => trim((string) ($params['hotspotNumberLabel'] ?? '')) !== '',
            'points_per_hotspot' => max(1, (int) ($scoring['pointsPerHotspot'] ?? 1)),
            'pass_percentage' => (int) ($scoring['passPercentage'] ?? $this->passFromFeedback($bands)),
            'enable_retry' => (bool) ($scoring['enableRetry'] ?? true),
            'single_popup_open' => (bool) ($scoring['singlePopupOpen'] ?? true),
            'feedback_bands' => $this->feedbackBands($bands),
        ];

        $points = [];
        foreach ((array) ($params['hotspots'] ?? []) as $index => $hotspot) {
            if (! is_array($hotspot)) {
                continue;
            }

            $popup = $this->parseAction((array) ($hotspot['action'] ?? []));
            $icon = (array) ($hotspot['eduerpIcon'] ?? []);

            $points[] = [
                'position_x' => $this->num($hotspot['position']['x'] ?? 50),
                'position_y' => $this->num($hotspot['position']['y'] ?? 50),
                'header' => (string) ($hotspot['header'] ?? ''),
                'popup_type' => $popup['type'],
                'body_text' => $popup['body'],
                'popup_image' => $popup['image'],
                'popup_image_alt' => $popup['image_alt'],
                'icon_name' => (string) ($icon['name'] ?? '') ?: null,
                'icon_color' => (string) ($icon['color'] ?? '') ?: null,
                'icon_image' => (string) ($icon['image'] ?? '') ?: null,
                'tooltip' => (string) ($hotspot['eduerpTooltip'] ?? '') ?: null,
                'aria_label' => (string) ($hotspot['ariaLabel'] ?? '') ?: null,
                'popup_width' => max(10, min(100, (int) ($hotspot['popupWidth'] ?? 40))),
                'sort_order' => $index,
            ];
        }

        return ['item' => $item, 'points' => $points];
    }

    /**
     * Collapse an `action` list back into this schema's one-popup-per-hotspot.
     *
     * A foreign package may hold several text entries or a library this
     * product has no editor for. Text is concatenated rather than dropped, and
     * an unrecognised library contributes nothing -- so an import loses the
     * ability to EDIT that piece, never the rest of the hotspot.
     *
     * @param  list<mixed>  $action
     * @return array{type:string, body:?string, image:?string, image_alt:?string}
     */
    private function parseAction(array $action): array
    {
        $texts = [];
        $image = null;
        $imageAlt = null;

        foreach ($action as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $library = (string) ($entry['library'] ?? '');
            $entryParams = (array) ($entry['params'] ?? []);

            if (str_starts_with($library, 'H5P.AdvancedText')) {
                $text = trim((string) ($entryParams['text'] ?? ''));
                if ($text !== '') {
                    $texts[] = $text;
                }
            } elseif (str_starts_with($library, 'H5P.Image')) {
                $image = (string) ($entryParams['file']['path'] ?? '') ?: $image;
                $imageAlt = (string) ($entryParams['alt'] ?? '') ?: $imageAlt;
            }
        }

        $body = $texts === [] ? null : implode("\n", $texts);

        // The type is INFERRED from what the popup actually holds, because a
        // foreign package has no `popup_type` to read. An imported body always
        // lands as `rich`: it is HTML, and calling it `text` would put markup
        // into a plain-text editor and escape it on the next save.
        $type = $image !== null ? 'image' : ($body !== null ? 'rich' : 'text');

        return ['type' => $type, 'body' => $body, 'image' => $image, 'image_alt' => $imageAlt];
    }
}
