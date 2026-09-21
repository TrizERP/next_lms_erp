<?php

namespace App\Http\Controllers\lms\h5p;

use App\Models\lms\h5p\H5pMemoryGame;
use App\Models\lms\h5p\H5pMemoryGameCard;
use App\Services\lms\H5P\H5PMemoryGameBuilder;
use App\Services\lms\H5P\H5PMemoryGamePackageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * H5P Memory Game (H5P.MemoryGame).
 *
 * A saved `cards` entry is a PAIR with two independently typed faces -- see
 * the migration. The validation below is where that is enforced: a face typed
 * `text` must carry text and a face typed `image` must carry an image, which
 * is what stops a deck of blank tiles being publishable.
 */
class H5PMemoryGameController extends H5PContentTypeController
{
    public function __construct(
        private readonly H5PMemoryGameBuilder $builder,
        private readonly H5PMemoryGamePackageService $packages
    ) {
    }

    protected function modelClass(): string
    {
        return H5pMemoryGame::class;
    }

    protected function registryCode(): string
    {
        return 'memory_game';
    }

    protected function routePrefix(): string
    {
        return 'h5p_memory_game';
    }

    protected function payloadKey(): string
    {
        return 'memoryGame';
    }

    protected function listKey(): string
    {
        return 'memoryGameLists';
    }

    protected function label(): string
    {
        return 'Memory game';
    }

    protected function relations(): array
    {
        return ['cards'];
    }

    protected function viewPath(): string
    {
        return 'lms/h5p/memorygame';
    }

    protected function mediaRoles(): array
    {
        return ['card' => 'image', 'card_back' => 'image'];
    }

    protected function authoringDefaults(): array
    {
        return [
            'pairs_to_use' => 0,
            'allow_retry' => true,
            'use_grid' => false,
            'shuffle_cards' => true,
            'show_completion_screen' => true,
            'scoring_mode' => 'pairs',
            'points_per_pair' => 1,
            'pass_percentage' => 100,
            'track_time' => true,
            'time_limit_seconds' => 0,
            'theme_color' => '#4f46e5',
            'scoring_modes' => H5pMemoryGame::SCORING_MODES,
            'face_types' => H5pMemoryGame::FACE_TYPES,
        ];
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    protected function saveRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_description' => 'nullable|string',

            'pairs_to_use' => 'nullable|integer|min:0|max:500',
            'active_pair_sets' => 'nullable|array',
            'active_pair_sets.*' => 'integer|min:1|max:500',

            'allow_retry' => 'nullable|boolean',
            'use_grid' => 'nullable|boolean',
            'shuffle_cards' => 'nullable|boolean',
            'show_completion_screen' => 'nullable|boolean',
            'completion_message' => 'nullable|string|max:500',

            'scoring_mode' => 'nullable|in:' . implode(',', H5pMemoryGame::SCORING_MODES),
            'points_per_pair' => 'nullable|integer|min:1|max:100',
            'pass_percentage' => 'nullable|integer|min:0|max:100',

            'track_time' => 'nullable|boolean',
            // 4 hours. A limit longer than a school day is a limit nobody set
            // on purpose.
            'time_limit_seconds' => 'nullable|integer|min:0|max:14400',

            'theme_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'card_back_image' => 'nullable|string|max:2048',

            'feedback_bands' => 'nullable|array',
            'feedback_bands.*.from' => 'required|integer|min:0|max:100',
            'feedback_bands.*.to' => 'required|integer|min:0|max:100',
            'feedback_bands.*.feedback' => 'nullable|string|max:500',

            // Two pairs is the floor: one pair is a board with two tiles and
            // no memory in it.
            'cards' => 'required|array|min:2',
            'cards.*.pair_set' => 'nullable|integer|min:1|max:500',

            'cards.*.front_type' => 'required|in:' . implode(',', H5pMemoryGame::FACE_TYPES),
            'cards.*.front_text' => 'nullable|required_if:cards.*.front_type,text|string|max:500',
            'cards.*.front_image' => 'nullable|required_if:cards.*.front_type,image|string|max:2048',
            'cards.*.front_alt' => 'nullable|string|max:255',

            'cards.*.back_type' => 'required|in:' . implode(',', H5pMemoryGame::FACE_TYPES),
            'cards.*.back_text' => 'nullable|required_if:cards.*.back_type,text|string|max:500',
            'cards.*.back_image' => 'nullable|required_if:cards.*.back_type,image|string|max:2048',
            'cards.*.back_alt' => 'nullable|string|max:255',

            'cards.*.match_description' => 'nullable|string|max:500',
        ];
    }

    protected function attributesFrom(array $data, Request $request): array
    {
        $sets = $data['active_pair_sets'] ?? null;

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'task_description' => $data['task_description'] ?? '',
            'pairs_to_use' => $data['pairs_to_use'] ?? 0,
            // An empty array and null both mean "every set". Normalising to
            // null here means activeCards() has one case to handle, not two.
            'active_pair_sets' => is_array($sets) && $sets !== [] ? array_values(array_map('intval', $sets)) : null,
            'allow_retry' => $data['allow_retry'] ?? true,
            'use_grid' => $data['use_grid'] ?? false,
            'shuffle_cards' => $data['shuffle_cards'] ?? true,
            'show_completion_screen' => $data['show_completion_screen'] ?? true,
            'completion_message' => $data['completion_message'] ?? null,
            'scoring_mode' => $data['scoring_mode'] ?? 'pairs',
            'points_per_pair' => $data['points_per_pair'] ?? 1,
            'pass_percentage' => $data['pass_percentage'] ?? 100,
            'track_time' => $data['track_time'] ?? true,
            'time_limit_seconds' => $data['time_limit_seconds'] ?? 0,
            'theme_color' => $data['theme_color'] ?? '#4f46e5',
            'card_back_image' => $data['card_back_image'] ?? null,
            'feedback_bands' => $data['feedback_bands'] ?? null,
        ];
    }

    // -----------------------------------------------------------------------
    // Children
    // -----------------------------------------------------------------------

    protected function syncChildren(Model $item, array $data, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $audit = [
            'sub_institute_id' => $subInstituteId,
            'created_by' => $userId,
            'created_at' => now(),
        ];

        foreach (array_values($data['cards']) as $order => $card) {
            H5pMemoryGameCard::create($this->cardAttributes($card, $order) + [
                'memory_game_id' => $item->id,
            ] + $audit);
        }
    }

    /**
     * @param  array<string,mixed>  $card
     * @return array<string,mixed>
     */
    private function cardAttributes(array $card, int $order): array
    {
        $attributes = [
            'pair_set' => $card['pair_set'] ?? 1,
            'match_description' => $card['match_description'] ?? null,
            'sort_order' => $order,
        ];

        // Same rule as the hotspot popups: only the field the chosen face type
        // uses is kept, so a face switched from image to text does not carry a
        // picture nothing renders and an export does not package it.
        foreach (['front', 'back'] as $side) {
            $type = $card[$side . '_type'];
            $attributes[$side . '_type'] = $type;
            $attributes[$side . '_text'] = $type === 'text' ? ($card[$side . '_text'] ?? '') : null;
            $attributes[$side . '_image'] = $type === 'image' ? ($card[$side . '_image'] ?? '') : null;
            $attributes[$side . '_alt'] = $card[$side . '_alt'] ?? null;
        }

        return $attributes;
    }

    protected function duplicableColumns(): array
    {
        return [
            'description', 'task_description', 'pairs_to_use', 'active_pair_sets',
            'allow_retry', 'use_grid', 'shuffle_cards', 'show_completion_screen',
            'completion_message', 'scoring_mode', 'points_per_pair', 'pass_percentage',
            'track_time', 'time_limit_seconds', 'theme_color', 'card_back_image',
            'feedback_bands', 'standard_id', 'subject_id', 'chapter_id', 'syear',
        ];
    }

    protected function duplicateChildren(Model $original, Model $copy, int|string|null $subInstituteId, int|string|null $userId): void
    {
        foreach ($original->cards as $card) {
            H5pMemoryGameCard::create($card->only([
                'pair_set', 'front_type', 'front_text', 'front_image', 'front_alt',
                'back_type', 'back_text', 'back_image', 'back_alt',
                'match_description', 'sort_order',
            ]) + [
                'memory_game_id' => $copy->id,
                'sub_institute_id' => $subInstituteId,
                'created_by' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // Publish
    // -----------------------------------------------------------------------

    protected function publishBlocker(Model $item): ?string
    {
        $item->load('cards');

        $active = $item->activeCards();

        if ($active->count() < 2) {
            // Says which of the two reasons it is, because "add pairs" is
            // unhelpful advice to an author who has twenty and filtered to a
            // set that holds one.
            return $item->cards->count() < 2
                ? 'Add at least two pairs before publishing.'
                : 'The chosen pair sets and pair limit leave fewer than two pairs in play.';
        }

        $incomplete = $active->first(fn (H5pMemoryGameCard $card) => ! $card->isComplete());
        if ($incomplete !== null) {
            return sprintf('Pair %d is missing content on one side. Fill it in before publishing.', (int) $incomplete->sort_order + 1);
        }

        // An image deck with no alt text is a deck a learner using a screen
        // reader cannot play at all -- there is nothing else on the tile.
        $unlabelled = $active->first(function (H5pMemoryGameCard $card) {
            foreach (['front', 'back'] as $side) {
                if ($card->{$side . '_type'} === 'image' && trim((string) $card->{$side . '_alt'}) === '') {
                    return true;
                }
            }

            return false;
        });

        if ($unlabelled !== null) {
            return sprintf(
                'Pair %d has a picture with no description. Describe it before publishing, so the pair can be played by a screen reader.',
                (int) $unlabelled->sort_order + 1
            );
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // Params and packages
    // -----------------------------------------------------------------------

    protected function buildParams(Model $item): array
    {
        return $this->builder->build($item);
    }

    protected function exportPackage(Model $item): array
    {
        return $this->packages->export($item);
    }

    protected function parsePackage(UploadedFile $file, int|string|null $subInstituteId): array
    {
        return $this->packages->import($file, $subInstituteId);
    }

    protected function createFromImport(array $parsed, Request $request, int|string|null $subInstituteId, int|string|null $userId): Model
    {
        $game = H5pMemoryGame::create($parsed['game'] + [
            'title' => $parsed['title'],
            'description' => '',
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
            'chapter_id' => $request->chapter_id,
            'sub_institute_id' => $subInstituteId,
            'syear' => $request->input('syear'),
            'status' => 'draft',
            'library' => $this->libraryVersionString(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);

        $audit = ['sub_institute_id' => $subInstituteId, 'created_by' => $userId, 'created_at' => now()];
        foreach ($parsed['cards'] as $card) {
            H5pMemoryGameCard::create($card + ['memory_game_id' => $game->id] + $audit);
        }

        return $game;
    }
}
