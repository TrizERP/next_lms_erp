<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One H5P Memory Game (H5P.MemoryGame).
 *
 * A row of `cards` is a PAIR, not a card -- see the migration. The deck the
 * player shuffles is twice as long as the relation.
 */
class H5pMemoryGame extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_memory_game';
    protected $guarded = [];

    public const REGISTRY_CODE = 'memory_game';

    /** Each face of a pair is one of these. */
    public const FACE_TYPES = ['text', 'image'];

    /** See the migration for what each measures. */
    public const SCORING_MODES = ['pairs', 'moves'];

    protected $casts = [
        'pairs_to_use' => 'integer',
        'active_pair_sets' => 'array',
        'allow_retry' => 'boolean',
        'use_grid' => 'boolean',
        'shuffle_cards' => 'boolean',
        'show_completion_screen' => 'boolean',
        'points_per_pair' => 'integer',
        'pass_percentage' => 'integer',
        'track_time' => 'boolean',
        'time_limit_seconds' => 'integer',
        'feedback_bands' => 'array',
        'published_at' => 'datetime',
    ];

    /** Every authored pair, across all sets, in author order. */
    public function cards(): HasMany
    {
        return $this->hasMany(H5pMemoryGameCard::class, 'memory_game_id')
            ->orderBy('pair_set')
            ->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * The pairs actually dealt in an attempt.
     *
     * Two filters in the order the author thinks about them: which SETS are in
     * play, then how many pairs to take from them. `pairs_to_use = 0` means
     * all of them. This is the one place the rule lives, so the player, the
     * builder and `maxScore()` cannot disagree about what a deck is.
     *
     * @return Collection<int,H5pMemoryGameCard>
     */
    public function activeCards(): Collection
    {
        $cards = $this->relationLoaded('cards') ? $this->cards : $this->cards()->get();

        $sets = $this->active_pair_sets;
        if (is_array($sets) && $sets !== []) {
            $wanted = array_map('intval', $sets);
            $cards = $cards->filter(fn (H5pMemoryGameCard $card) => in_array((int) $card->pair_set, $wanted, true));
        }

        $limit = (int) $this->pairs_to_use;
        if ($limit > 0) {
            $cards = $cards->take($limit);
        }

        return $cards->values();
    }

    /**
     * What a full-marks attempt is worth.
     *
     * Both scoring modes cap at the same number -- `moves` scales points down
     * from a perfect run, it does not award more than one pair's worth per
     * pair -- so max score does not branch on the mode.
     */
    public function maxScore(): int
    {
        return $this->activeCards()->count() * max(1, (int) $this->points_per_pair);
    }
}
