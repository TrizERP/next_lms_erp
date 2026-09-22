<?php

namespace App\Services\lms\H5P;

use App\Models\lms\h5p\H5pMemoryGame;

/**
 * Translates between h5p_memory_game rows and H5P.MemoryGame params.
 *
 * THE FORMAT MISMATCH THIS CLASS EXISTS TO ABSORB.
 *
 * H5P.MemoryGame is an IMAGE-PAIR game. Its card entry is:
 *
 *   {image: {path, mime}, imageAlt, matchImage: {...}, matchAlt, description}
 *
 * There is no text card. A pair with no `matchImage` is a card matched against
 * a copy of itself -- find the two identical pictures.
 *
 * This product's authoring supports text faces, because "word <-> picture" and
 * "term <-> definition" are the two decks a primary teacher actually asks for.
 * So a text face is exported two ways at once:
 *
 *   - as `imageAlt` / `matchAlt`, which is what a host's screen reader and its
 *     matched-pair caption will read, so the meaning survives; and
 *   - verbatim in `eduerpFaces`, an extension key a foreign host ignores and
 *     this importer reads back, so a round trip through THIS product is lossless.
 *
 * What a text face cannot do is render as text in a stock H5P host -- that
 * host has no card template for it and will show the alt text on an empty
 * tile. That is a real limit of the target format, it is reported to the
 * author as an export warning by H5PMemoryGamePackageService, and it is the
 * honest alternative to silently dropping half the deck.
 */
class H5PMemoryGameBuilder
{
    use ConvertsToH5PParams;

    private const REGISTRY_CODE = 'memory_game';

    // -----------------------------------------------------------------------
    // Build
    // -----------------------------------------------------------------------

    /**
     * Build the H5P.MemoryGame `params` object for one game.
     *
     * Only the ACTIVE pairs are exported -- the sets and the pair limit the
     * author chose. A package is the thing they publish, not their whole
     * working set, and `activeCards()` is the one place that rule lives.
     *
     * @return array<string,mixed>
     */
    public function build(H5pMemoryGame $game): array
    {
        $cards = [];
        foreach ($game->activeCards() as $card) {
            $entry = [
                'image' => $this->faceFile($card, 'front'),
                'imageAlt' => $this->faceAlt($card, 'front'),
                'description' => (string) ($card->match_description ?? ''),
                'subContentId' => $this->subContentId('memorygame:card', (int) $card->id),
                // Verbatim faces, so a re-import restores text sides exactly.
                'eduerpFaces' => [
                    'front' => $this->faceDescriptor($card, 'front'),
                    'back' => $this->faceDescriptor($card, 'back'),
                    'pairSet' => (int) $card->pair_set,
                ],
            ];

            // H5P omits matchImage when a pair is two copies of one picture.
            // Emitting an identical object instead would change the game.
            if ($this->facesDiffer($card)) {
                $entry['matchImage'] = $this->faceFile($card, 'back');
                $entry['matchAlt'] = $this->faceAlt($card, 'back');
            }

            $cards[] = $entry;
        }

        $params = [
            'cards' => $cards,
            'behaviour' => [
                'useGrid' => (bool) $game->use_grid,
                'allowRetry' => (bool) $game->allow_retry,
                // H5P counts TILES; the column counts pairs. See the migration.
                'numCardsToUse' => count($cards) * 2,
            ],
            'lookNFeel' => [
                'themeColor' => (string) ($game->theme_color ?: '#4f46e5'),
                'cardBack' => trim((string) ($game->card_back_image ?? '')) !== ''
                    ? $this->imageFile((string) $game->card_back_image)
                    : null,
            ],
            'l10n' => [
                'cardTurns' => 'Card turns',
                'timeSpent' => 'Time spent',
                'feedback' => trim((string) ($game->completion_message ?? '')) !== ''
                    ? (string) $game->completion_message
                    : 'Good work!',
                'tryAgain' => 'Reset',
                'closeLabel' => 'Close',
                'label' => 'Memory Game. Find the matching cards.',
                'done' => 'All of the cards have been found.',
                'cardPrefix' => 'Card %num:',
                'cardUnturned' => 'Unturned.',
                'cardTurned' => 'Turned.',
                'cardMatched' => 'Match found.',
            ],
            // Not part of H5P.MemoryGame: the scoring, timing and shuffle
            // decisions this product exposes and H5P does not model.
            'eduerpScoring' => [
                'scoringMode' => (string) ($game->scoring_mode ?: 'pairs'),
                'pointsPerPair' => max(1, (int) $game->points_per_pair),
                'passPercentage' => max(0, min(100, (int) $game->pass_percentage)),
                'trackTime' => (bool) $game->track_time,
                'timeLimitSeconds' => max(0, (int) $game->time_limit_seconds),
                'shuffleCards' => (bool) $game->shuffle_cards,
                'showCompletionScreen' => (bool) $game->show_completion_screen,
            ],
            'overallFeedback' => $this->feedbackBands($game->feedback_bands),
        ];

        return $this->mergePreservedKeys($params, $game->content_json);
    }

    /** True when the two faces are genuinely different tiles. */
    private function facesDiffer(object $card): bool
    {
        return $this->faceDescriptor($card, 'front') !== $this->faceDescriptor($card, 'back');
    }

    /**
     * One face, in this schema's own terms.
     *
     * @return array{type:string, text:string, image:string, alt:string}
     */
    private function faceDescriptor(object $card, string $side): array
    {
        return [
            'type' => (string) ($card->{$side . '_type'} ?: 'image'),
            'text' => (string) ($card->{$side . '_text'} ?? ''),
            'image' => (string) ($card->{$side . '_image'} ?? ''),
            'alt' => (string) ($card->{$side . '_alt'} ?? ''),
        ];
    }

    /**
     * The `image` object for a face.
     *
     * A text face has no file, and H5P's schema has no way to say so -- the
     * key is required. An empty path is what a host renders as a blank tile,
     * which is exactly what the export warning tells the author will happen.
     *
     * @return array<string,mixed>
     */
    private function faceFile(object $card, string $side): array
    {
        return $this->imageFile((string) ($card->{$side . '_image'} ?? ''));
    }

    /**
     * The alt text for a face -- and, for a text face, the text itself.
     *
     * This is the line that keeps a text deck meaningful in a foreign host:
     * the word is what a screen reader announces and what the matched-pair
     * caption shows, even though no tile renders it visually.
     */
    private function faceAlt(object $card, string $side): string
    {
        $alt = trim((string) ($card->{$side . '_alt'} ?? ''));
        if ($alt !== '') {
            return $alt;
        }

        return trim((string) ($card->{$side . '_text'} ?? ''));
    }

    /** @return array<string,mixed> */
    private function imageFile(string $path): array
    {
        return [
            'path' => $path,
            'mime' => $this->mimeFor($path),
            'copyright' => ['license' => 'U'],
        ];
    }

    // -----------------------------------------------------------------------
    // Parse
    // -----------------------------------------------------------------------

    /**
     * Read an H5P.MemoryGame params object into row payloads.
     *
     * @param  array<string,mixed>  $params
     * @return array{game: array<string,mixed>, cards: list<array<string,mixed>>}
     */
    public function parse(array $params): array
    {
        $behaviour = (array) ($params['behaviour'] ?? []);
        $look = (array) ($params['lookNFeel'] ?? []);
        $scoring = (array) ($params['eduerpScoring'] ?? []);
        $l10n = (array) ($params['l10n'] ?? []);
        $bands = (array) ($params['overallFeedback'] ?? []);

        $cards = [];
        foreach ((array) ($params['cards'] ?? []) as $index => $card) {
            if (! is_array($card)) {
                continue;
            }

            // Prefer this product's own verbatim faces when the package came
            // from here; fall back to H5P's image/matchImage otherwise.
            $faces = is_array($card['eduerpFaces'] ?? null) ? $card['eduerpFaces'] : null;

            $front = $faces['front'] ?? [
                'type' => 'image',
                'text' => '',
                'image' => (string) ($card['image']['path'] ?? ''),
                'alt' => (string) ($card['imageAlt'] ?? ''),
            ];

            // No matchImage means the pair is two copies of the same picture,
            // which is a legitimate H5P deck -- so the back face is the front.
            $back = $faces['back'] ?? (isset($card['matchImage'])
                ? [
                    'type' => 'image',
                    'text' => '',
                    'image' => (string) ($card['matchImage']['path'] ?? ''),
                    'alt' => (string) ($card['matchAlt'] ?? ''),
                ]
                : $front);

            $cards[] = [
                'pair_set' => max(1, (int) ($faces['pairSet'] ?? 1)),
                'front_type' => $this->faceType($front),
                'front_text' => (string) ($front['text'] ?? '') ?: null,
                'front_image' => (string) ($front['image'] ?? '') ?: null,
                'front_alt' => (string) ($front['alt'] ?? '') ?: null,
                'back_type' => $this->faceType($back),
                'back_text' => (string) ($back['text'] ?? '') ?: null,
                'back_image' => (string) ($back['image'] ?? '') ?: null,
                'back_alt' => (string) ($back['alt'] ?? '') ?: null,
                'match_description' => (string) ($card['description'] ?? '') ?: null,
                'sort_order' => $index,
            ];
        }

        $game = [
            'use_grid' => (bool) ($behaviour['useGrid'] ?? false),
            'allow_retry' => (bool) ($behaviour['allowRetry'] ?? true),
            // Every imported pair is active: the package IS the selection, so
            // re-applying a limit on top of it would deal a subset of a subset.
            'pairs_to_use' => 0,
            'active_pair_sets' => null,
            'theme_color' => (string) ($look['themeColor'] ?? '#4f46e5'),
            'card_back_image' => (string) ($look['cardBack']['path'] ?? '') ?: null,
            'completion_message' => (string) ($l10n['feedback'] ?? '') ?: null,
            'scoring_mode' => in_array($scoring['scoringMode'] ?? '', H5pMemoryGame::SCORING_MODES, true)
                ? (string) $scoring['scoringMode']
                : 'pairs',
            'points_per_pair' => max(1, (int) ($scoring['pointsPerPair'] ?? 1)),
            'pass_percentage' => (int) ($scoring['passPercentage'] ?? $this->passFromFeedback($bands)),
            'track_time' => (bool) ($scoring['trackTime'] ?? true),
            'time_limit_seconds' => max(0, (int) ($scoring['timeLimitSeconds'] ?? 0)),
            'shuffle_cards' => (bool) ($scoring['shuffleCards'] ?? true),
            'show_completion_screen' => (bool) ($scoring['showCompletionScreen'] ?? true),
            'feedback_bands' => $this->feedbackBands($bands),
        ];

        return ['game' => $game, 'cards' => $cards];
    }

    /**
     * A face with a file is an image face; a face with only words is a text
     * face. Trusting the stored `type` would let a package from here claim
     * `image` on a face whose file the import could not extract.
     *
     * @param  array<string,mixed>  $face
     */
    private function faceType(array $face): string
    {
        if (trim((string) ($face['image'] ?? '')) !== '') {
            return 'image';
        }

        return trim((string) ($face['text'] ?? '')) !== '' ? 'text' : 'image';
    }
}
