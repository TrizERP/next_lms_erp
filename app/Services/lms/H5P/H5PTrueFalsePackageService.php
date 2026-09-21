<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pTrueFalse;

/**
 * .h5p packages for True/False (H5P.TrueFalse).
 *
 * MEDIA APPEARS TWICE IN THESE PARAMS AND BOTH COPIES MUST BE REWRITTEN.
 *
 * The first question's image is written into `media.type` -- where the library
 * looks for it -- AND into `eduerpPool.questions[0].media`, where this
 * product's importer looks. They are two nodes carrying the same URL. Rewrite
 * only the first and a re-import gets an absolute URL back for question 1 and
 * packaged paths for the rest, which works for exactly as long as the
 * exporting server stays reachable and then silently does not.
 *
 * So both are walked, and the pool is walked in full. The rewrite callback is
 * content-addressed by path (H5PPackageArchive), so the same image referenced
 * from both nodes is packed once and both references land on the same file.
 */
class H5PTrueFalsePackageService extends H5PContentPackageService
{
    public function __construct(private readonly H5PTrueFalseBuilder $builder)
    {
    }

    protected function libraryKey(): string
    {
        return 'true_false';
    }

    protected function mediaSubdir(): string
    {
        return 'true_false';
    }

    protected function mediaPrefix(): string
    {
        return 'tf_';
    }

    protected function importedTitleFallback(): string
    {
        return 'Imported true or false activity';
    }

    /**
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function export(H5pTrueFalse $item): array
    {
        $caveat = $this->builder->exportCaveat($item);

        return $this->writePackage(
            $this->builder->build($item),
            (string) $item->title,
            (int) $item->id,
            $caveat !== null ? [$caveat] : []
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
        // 1. The library's own node, for the first question.
        $media = $params['media'] ?? null;
        if (is_array($media) && isset($media['type'])) {
            $params['media']['type'] = $this->rewriteImageNode($media['type'], $rewrite);
        }

        // 2. Every question in the pool, including the first -- see the class
        // header for why this is not "the rest".
        $pool = $params['eduerpPool'] ?? null;
        if (! is_array($pool) || ! isset($pool['questions']) || ! is_array($pool['questions'])) {
            return $params;
        }

        foreach ($pool['questions'] as $index => $question) {
            if (! is_array($question) || ! isset($question['media'])) {
                continue;
            }

            $pool['questions'][$index]['media'] = $this->rewriteImageNode($question['media'], $rewrite);
        }

        $params['eduerpPool'] = $pool;

        return $params;
    }

    /**
     * Rewrite the `path` inside one H5P.Image sub-content node.
     *
     * Returns the node unchanged when there is nothing to rewrite -- a
     * question with no picture, or a path the callback declined (a URL it
     * could not fetch on export, which keeps its absolute form and is
     * reported in `warnings` instead of being dropped).
     */
    private function rewriteImageNode(mixed $node, callable $rewrite): mixed
    {
        if (! is_array($node) || ! isset($node['params']['file'])) {
            return $node;
        }

        $file = $node['params']['file'];
        if ($this->rewriteFileNode($file, $rewrite)) {
            $node['params']['file'] = $file;
        }

        return $node;
    }
}
