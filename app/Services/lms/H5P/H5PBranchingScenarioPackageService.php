<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pBranchingNode;
use App\Models\lms\h5p\H5pBranchingScenario;

/**
 * .h5p packages for Branching Scenario (H5P.BranchingScenario).
 *
 * The media walk is shallow compared with a Course Presentation's, because a
 * scenario nests only the three libraries this product writes. Pictures live in
 * four places:
 *
 *   startScreen.startScreenImage
 *   endScreens[].endScreenImage
 *   content[].type.params.file                         (an H5P.Image entry)
 *   content[].type.params.branchingQuestion
 *       .alternatives[].feedback.image                 (a choice's feedback)
 *
 * `content[].type.params.file` is walked by SHAPE, not by library name, so a
 * foreign package's H5P.Video poster or H5P.Image inside a library this ERP
 * cannot play still has its media packaged rather than left pointing at a path
 * that no longer exists.
 */
class H5PBranchingScenarioPackageService extends H5PContentPackageService
{
    public function __construct(private readonly H5PBranchingScenarioBuilder $builder)
    {
    }

    protected function libraryKey(): string
    {
        return 'branching_scenario';
    }

    protected function mediaSubdir(): string
    {
        return 'branching_scenario';
    }

    protected function mediaPrefix(): string
    {
        return 'bs_';
    }

    protected function importedTitleFallback(): string
    {
        return 'Imported branching scenario';
    }

    /**
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function export(H5pBranchingScenario $scenario): array
    {
        // Not loadMissing(): that builds a query even when the relation is
        // already there, which makes an in-memory scenario need a connection.
        if (! $scenario->relationLoaded('nodes')) {
            $scenario->load('nodes');
        }
        $params = $this->builder->build($scenario);

        return $this->writePackage(
            $params,
            (string) $scenario->title,
            (int) $scenario->id,
            $this->caveats($scenario)
        );
    }

    /**
     * What this product knows that the package cannot say.
     *
     * @return list<string>
     */
    private function caveats(H5pBranchingScenario $scenario): array
    {
        $caveats = [];
        $nodes = $scenario->nodes;

        $split = $nodes->filter(
            fn (H5pBranchingNode $n) => ! $n->isEnding()
                && trim((string) $n->media_path) !== ''
                && ($n->isDecision() || BranchingPayload::plain($n->body ?? '') !== '')
        )->count();
        if ($split > 0) {
            $caveats[] = sprintf(
                '%d %s a picture together with text or a question. H5P has no such step, so %s exported as a picture step followed by the text step.',
                $split,
                $split === 1 ? 'step has' : 'steps have',
                $split === 1 ? 'it was' : 'they were'
            );
        }

        $rated = $nodes->filter(fn (H5pBranchingNode $n) => $n->isDecision())->sum(
            fn (H5pBranchingNode $n) => collect((array) ($n->options ?? []))->filter(fn ($o) => ! empty($o['quality']))->count()
        );
        if ($rated > 0) {
            $caveats[] = 'Each choice\'s rating (best, partial, misstep) and each step\'s teaching purpose are carried as an extension and will not be shown by a host outside this ERP.';
        }

        if (! empty($scenario->learning_objectives)) {
            $caveats[] = 'The learning objectives were added to the start screen as a list, because H5P has no field for them.';
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
        $bs = $params['branchingScenario'] ?? null;
        if (! is_array($bs)) {
            return $params;
        }

        if (isset($bs['startScreen']['startScreenImage'])) {
            $node = $bs['startScreen']['startScreenImage'];
            if ($this->rewriteFileNode($node, $rewrite)) {
                $bs['startScreen']['startScreenImage'] = $node;
            }
        }

        foreach ((array) ($bs['endScreens'] ?? []) as $i => $screen) {
            if (is_array($screen) && isset($screen['endScreenImage'])) {
                $node = $screen['endScreenImage'];
                if ($this->rewriteFileNode($node, $rewrite)) {
                    $bs['endScreens'][$i]['endScreenImage'] = $node;
                }
            }
        }

        foreach ((array) ($bs['content'] ?? []) as $i => $item) {
            if (! is_array($item) || ! isset($item['type']['params']) || ! is_array($item['type']['params'])) {
                continue;
            }
            $p = $item['type']['params'];

            if (isset($p['file'])) {
                $node = $p['file'];
                if ($this->rewriteFileNode($node, $rewrite)) {
                    $bs['content'][$i]['type']['params']['file'] = $node;
                }
            }

            foreach ((array) ($p['branchingQuestion']['alternatives'] ?? []) as $j => $alternative) {
                if (is_array($alternative) && isset($alternative['feedback']['image'])) {
                    $node = $alternative['feedback']['image'];
                    if ($this->rewriteFileNode($node, $rewrite)) {
                        $bs['content'][$i]['type']['params']['branchingQuestion']['alternatives'][$j]['feedback']['image'] = $node;
                    }
                }
            }
        }

        $params['branchingScenario'] = $bs;

        return $params;
    }
}
