<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pImageHotspots;

/**
 * .h5p packages for Image Hotspots (H5P.ImageHotspots).
 *
 * Only the four type-specific things are here; the cycle is in
 * H5PContentPackageService.
 *
 * MEDIA LIVES IN FOUR PLACES in an ImageHotspots params object, which is more
 * than any other type in this family: the background, each popup's H5P.Image
 * sub-content, and each hotspot's custom icon. Miss one and the package
 * exports with a working picture and a broken icon.
 */
class H5PImageHotspotsPackageService extends H5PContentPackageService
{
    public function __construct(private readonly H5PImageHotspotsBuilder $builder)
    {
    }

    protected function libraryKey(): string
    {
        return 'image_hotspots';
    }

    protected function mediaSubdir(): string
    {
        return 'image_hotspots';
    }

    protected function mediaPrefix(): string
    {
        return 'ih_';
    }

    protected function importedTitleFallback(): string
    {
        return 'Imported image hotspots';
    }

    /**
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function export(H5pImageHotspots $item): array
    {
        $item->loadMissing('points');

        return $this->writePackage(
            $this->builder->build($item),
            (string) $item->title,
            (int) $item->id
        );
    }

    /** @param array<string,mixed> $content */
    protected function parseParams(array $content): array
    {
        return $this->builder->parse($content);
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    protected function rewriteMedia(array $params, callable $rewrite): array
    {
        // 1. The background.
        if (isset($params['image'])) {
            $this->rewriteFileNode($params['image'], $rewrite);
        }

        $hotspots = $params['hotspots'] ?? [];
        if (! is_array($hotspots)) {
            return $params;
        }

        foreach ($hotspots as $index => $hotspot) {
            if (! is_array($hotspot)) {
                continue;
            }

            // 2. Every H5P.Image sub-content in the popup. A popup is a LIST,
            // so this cannot look at `action[0]` and stop -- an image popup
            // with a caption has the picture in one entry and the words in
            // another, in either order.
            $action = $hotspot['action'] ?? [];
            if (is_array($action)) {
                foreach ($action as $entryIndex => $entry) {
                    if (is_array($entry) && isset($entry['params']['file'])) {
                        $file = $entry['params']['file'];
                        if ($this->rewriteFileNode($file, $rewrite)) {
                            $action[$entryIndex]['params']['file'] = $file;
                        }
                    }
                }
                $hotspots[$index]['action'] = $action;
            }

            // 3. A custom hotspot icon, which is a bare URL on an extension key.
            $icon = $hotspot['eduerpIcon'] ?? null;
            if (is_array($icon)) {
                if ($this->rewriteStringPath($icon, 'image', $rewrite)) {
                    $hotspots[$index]['eduerpIcon'] = $icon;
                }
            }
        }

        $params['hotspots'] = $hotspots;

        return $params;
    }
}
