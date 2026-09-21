<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pMemoryGame;
use App\Models\lms\h5p\H5pMemoryGameCard;

/**
 * .h5p packages for Memory Game (H5P.MemoryGame).
 *
 * Media sits in four places: each pair's two faces, each pair's two verbatim
 * `eduerpFaces` copies of the same URLs, and the deck's card-back image. The
 * verbatim copies have to be rewritten too, or a package re-imported here
 * restores faces pointing at a server the importing school cannot reach.
 */
class H5PMemoryGamePackageService extends H5PContentPackageService
{
    public function __construct(private readonly H5PMemoryGameBuilder $builder)
    {
    }

    protected function libraryKey(): string
    {
        return 'memory_game';
    }

    protected function mediaSubdir(): string
    {
        return 'memory_game';
    }

    protected function mediaPrefix(): string
    {
        return 'mg_';
    }

    protected function importedTitleFallback(): string
    {
        return 'Imported memory game';
    }

    /**
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function export(H5pMemoryGame $game): array
    {
        $game->loadMissing('cards');

        return $this->writePackage(
            $this->builder->build($game),
            (string) $game->title,
            (int) $game->id,
            $this->caveats($game)
        );
    }

    /**
     * Format limits worth telling the author about at download time.
     *
     * H5P.MemoryGame has no text card. A deck built on words survives the
     * round trip through this product exactly, and renders as blank tiles with
     * correct alt text in a stock H5P host -- see H5PMemoryGameBuilder's
     * header. Saying so on the way out is the difference between a known
     * limitation and a bug report.
     *
     * @return list<string>
     */
    private function caveats(H5pMemoryGame $game): array
    {
        $textFaces = $game->activeCards()->filter(
            fn (H5pMemoryGameCard $card) => $card->front_type === 'text' || $card->back_type === 'text'
        )->count();

        if ($textFaces === 0) {
            return [];
        }

        return [sprintf(
            '%d %s a text side. H5P.MemoryGame has no text card, so outside this ERP those tiles show as blank with the word as alt text. Re-imported here they are restored in full.',
            $textFaces,
            $textFaces === 1 ? 'pair has' : 'pairs have'
        )];
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
        $cards = $params['cards'] ?? [];
        if (is_array($cards)) {
            foreach ($cards as $index => $card) {
                if (! is_array($card)) {
                    continue;
                }

                foreach (['image', 'matchImage'] as $key) {
                    if (isset($card[$key])) {
                        $node = $card[$key];
                        if ($this->rewriteFileNode($node, $rewrite)) {
                            $cards[$index][$key] = $node;
                        }
                    }
                }

                // The verbatim faces carry the same URLs a second time. They
                // are what a re-import reads, so leaving them absolute would
                // make the round trip lossy in exactly the case it exists for.
                $faces = $card['eduerpFaces'] ?? null;
                if (is_array($faces)) {
                    foreach (['front', 'back'] as $side) {
                        if (is_array($faces[$side] ?? null)) {
                            $face = $faces[$side];
                            if ($this->rewriteStringPath($face, 'image', $rewrite)) {
                                $faces[$side] = $face;
                            }
                        }
                    }
                    $cards[$index]['eduerpFaces'] = $faces;
                }
            }
            $params['cards'] = $cards;
        }

        if (isset($params['lookNFeel']['cardBack'])) {
            $back = $params['lookNFeel']['cardBack'];
            if ($this->rewriteFileNode($back, $rewrite)) {
                $params['lookNFeel']['cardBack'] = $back;
            }
        }

        return $params;
    }
}
