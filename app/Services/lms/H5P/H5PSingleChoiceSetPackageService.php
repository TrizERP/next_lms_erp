<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pSingleChoiceSet;

/**
 * .h5p packages for Single Choice Set (H5P.SingleChoiceSet).
 *
 * NO MEDIA, like the arithmetic quiz and for the same structural reason: a
 * single choice question is a sentence and a list of sentences. H5P's own
 * editor for this type offers no image field either -- a question that needs a
 * picture is a Multiple Choice or an Image Hotspots activity, not this one.
 *
 * `rewriteMedia()` is therefore a genuine identity rather than an oversight,
 * and an exported package is a few kilobytes of JSON with no `content/images`
 * directory at all, which is a valid .h5p archive.
 *
 * Everything else the cycle does -- the manifest, the dependency closure, the
 * import bounds, the `mainLibrary` check, the traversal guard -- applies here
 * exactly as it does to the types that do carry files.
 */
class H5PSingleChoiceSetPackageService extends H5PContentPackageService
{
    public function __construct(private readonly H5PSingleChoiceSetBuilder $builder)
    {
    }

    protected function libraryKey(): string
    {
        return 'single_choice_set';
    }

    protected function mediaSubdir(): string
    {
        return 'single_choice_set';
    }

    protected function mediaPrefix(): string
    {
        return 'scs_';
    }

    protected function importedTitleFallback(): string
    {
        return 'Imported single choice set';
    }

    /**
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function export(H5pSingleChoiceSet $set): array
    {
        $caveat = $this->builder->exportCaveat($set);

        return $this->writePackage(
            $this->builder->build($set),
            (string) $set->title,
            (int) $set->id,
            $caveat !== null ? [$caveat] : []
        );
    }

    /** @param array<string,mixed> $content */
    protected function parseParams(array $content): array
    {
        return $this->builder->parse($content);
    }

    /**
     * No media. See the class header.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    protected function rewriteMedia(array $params, callable $rewrite): array
    {
        return $params;
    }
}
