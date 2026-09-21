<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pArithmeticQuiz;

/**
 * .h5p packages for Arithmetic Quiz (H5P.ArithmeticQuiz).
 *
 * The one type in this family with NO MEDIA. `rewriteMedia()` is therefore a
 * genuine identity rather than an oversight -- an arithmetic quiz is a rule
 * set, and an exported package is a few hundred bytes of JSON with no
 * `content/images` directory at all, which is a valid .h5p archive.
 *
 * It still goes through H5PContentPackageService, because everything ELSE the
 * cycle does -- the manifest, the dependency closure, the import bounds, the
 * mainLibrary check, the traversal guard -- applies to this type exactly as it
 * does to the others.
 */
class H5PArithmeticQuizPackageService extends H5PContentPackageService
{
    public function __construct(private readonly H5PArithmeticQuizBuilder $builder)
    {
    }

    protected function libraryKey(): string
    {
        return 'arithmetic_quiz';
    }

    protected function mediaSubdir(): string
    {
        return 'arithmetic_quiz';
    }

    protected function mediaPrefix(): string
    {
        return 'aq_';
    }

    protected function importedTitleFallback(): string
    {
        return 'Imported arithmetic quiz';
    }

    /**
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function export(H5pArithmeticQuiz $quiz): array
    {
        $caveat = $this->builder->exportCaveat($quiz);

        return $this->writePackage(
            $this->builder->build($quiz),
            (string) $quiz->title,
            (int) $quiz->id,
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
