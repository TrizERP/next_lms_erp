<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pCoursePresentation;

/**
 * .h5p packages for Course Presentation (H5P.CoursePresentation).
 *
 * The media walk here is the deepest in the family, because a deck nests:
 *
 *   slides[] -> slideBackgroundSelector.imageSlideBackground
 *   slides[] -> elements[] -> action.params -> {file | sources[] | files[]}
 *   slides[] -> elements[] -> an INLINED drag question, which has a background
 *                             and an image per draggable of its own
 *
 * That last level is why this walks into `action.params.question` rather than
 * stopping at the element: an embedded drag and drop is a whole DragQuestion
 * params object, and its pictures are as much part of the package as the
 * slide's own.
 */
class H5PCoursePresentationPackageService extends H5PContentPackageService
{
    public function __construct(private readonly H5PCoursePresentationBuilder $builder)
    {
    }

    protected function libraryKey(): string
    {
        return 'course_presentation';
    }

    protected function mediaSubdir(): string
    {
        return 'course_presentation';
    }

    protected function mediaPrefix(): string
    {
        return 'cp_';
    }

    protected function importedTitleFallback(): string
    {
        return 'Imported presentation';
    }

    /**
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function export(H5pCoursePresentation $deck): array
    {
        $deck->loadMissing(['slides.elements']);
        $params = $this->builder->build($deck);

        return $this->writePackage(
            $params,
            (string) $deck->title,
            (int) $deck->id,
            $this->caveats($deck, $params)
        );
    }

    /**
     * @param  array<string,mixed>  $params
     * @return list<string>
     */
    private function caveats(H5pCoursePresentation $deck, array $params): array
    {
        $caveats = [];

        // An embedded activity whose row has gone. The builder already wrote a
        // placeholder into the params; this is what tells the author.
        $missing = 0;
        foreach ((array) ($params['presentation']['slides'] ?? []) as $slide) {
            foreach ((array) ($slide['elements'] ?? []) as $element) {
                if (isset($element['action']['params']['eduerpUnavailable'])) {
                    $missing++;
                }
            }
        }
        if ($missing > 0) {
            $caveats[] = sprintf(
                '%d embedded drag and drop %s could not be found and %s exported as an empty question.',
                $missing,
                $missing === 1 ? 'activity' : 'activities',
                $missing === 1 ? 'was' : 'were'
            );
        }

        // Per-slide branching. H5P.CoursePresentation has no field for it --
        // see H5PCoursePresentationBuilder's header -- so a deck that relies
        // on it plays in order elsewhere.
        $branching = $deck->slides->filter(fn ($slide) => $slide->next_slide_id !== null)->count();
        if ($branching > 0) {
            $caveats[] = sprintf(
                '%d %s a branching destination. H5P.CoursePresentation has no per-slide branching, so outside this ERP the deck plays in order. Re-imported here the branches are restored.',
                $branching,
                $branching === 1 ? 'slide has' : 'slides have'
            );
        }

        // Speaker notes are author-facing and are not learner content, so they
        // ride in the extension namespace rather than the deck body.
        $notes = $deck->slides->filter(fn ($slide) => trim((string) $slide->notes) !== '')->count();
        if ($notes > 0) {
            $caveats[] = sprintf(
                'Speaker notes on %d %s are carried as an extension and will not be shown by a host outside this ERP.',
                $notes,
                $notes === 1 ? 'slide' : 'slides'
            );
        }

        return $caveats;
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
        $slides = $params['presentation']['slides'] ?? [];
        if (! is_array($slides)) {
            return $params;
        }

        foreach ($slides as $slideIndex => $slide) {
            if (! is_array($slide)) {
                continue;
            }

            if (isset($slide['slideBackgroundSelector']['imageSlideBackground'])) {
                $node = $slide['slideBackgroundSelector']['imageSlideBackground'];
                if ($this->rewriteFileNode($node, $rewrite)) {
                    $slides[$slideIndex]['slideBackgroundSelector']['imageSlideBackground'] = $node;
                }
            }

            $elements = $slide['elements'] ?? [];
            if (! is_array($elements)) {
                continue;
            }

            foreach ($elements as $elementIndex => $element) {
                if (! is_array($element) || ! isset($element['action']['params'])) {
                    continue;
                }

                $elements[$elementIndex]['action']['params'] = $this->rewriteElementParams(
                    (array) $element['action']['params'],
                    $rewrite
                );
            }

            $slides[$slideIndex]['elements'] = $elements;
        }

        $params['presentation']['slides'] = $slides;

        return $params;
    }

    /**
     * Media inside one element's params, whichever library it is.
     *
     * Keyed by SHAPE rather than by library name: H5P.Image has `file`,
     * H5P.Video has `sources[]`, H5P.Audio has `files[]`, and a nested
     * DragQuestion has a `question` tree. Checking the shape means an element
     * whose library this ERP does not edit -- which an imported deck may well
     * contain -- still has its media packaged correctly.
     *
     * @param  array<string,mixed>  $p
     * @return array<string,mixed>
     */
    private function rewriteElementParams(array $p, callable $rewrite): array
    {
        // H5P.Image, and any library that borrows its shape.
        if (isset($p['file'])) {
            $this->rewriteFileNode($p['file'], $rewrite);
        }

        // H5P.Video sources, H5P.Audio files.
        foreach (['sources', 'files'] as $listKey) {
            if (is_array($p[$listKey] ?? null)) {
                foreach ($p[$listKey] as $i => $entry) {
                    if ($this->rewriteFileNode($entry, $rewrite)) {
                        $p[$listKey][$i] = $entry;
                    }
                }
            }
        }

        // A video poster is a second, easily forgotten image.
        if (isset($p['visuals']['poster'])) {
            $poster = $p['visuals']['poster'];
            if ($this->rewriteFileNode($poster, $rewrite)) {
                $p['visuals']['poster'] = $poster;
            }
        }

        // An inlined H5P.DragQuestion: its own background plus one image per
        // image draggable. Same two places H5PPackageService walks for a
        // standalone drag question.
        if (isset($p['question']) && is_array($p['question'])) {
            if (isset($p['question']['settings']['background'])) {
                $background = $p['question']['settings']['background'];
                if ($this->rewriteFileNode($background, $rewrite)) {
                    $p['question']['settings']['background'] = $background;
                }
            }

            $dragElements = $p['question']['task']['elements'] ?? [];
            if (is_array($dragElements)) {
                foreach ($dragElements as $i => $dragElement) {
                    if (isset($dragElement['type']['params']['file'])) {
                        $file = $dragElement['type']['params']['file'];
                        if ($this->rewriteFileNode($file, $rewrite)) {
                            $dragElements[$i]['type']['params']['file'] = $file;
                        }
                    }
                }
                $p['question']['task']['elements'] = $dragElements;
            }
        }

        return $p;
    }
}
