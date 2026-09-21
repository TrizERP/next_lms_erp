<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One PAIR in a memory game: two independently typed faces plus the line shown
 * when they are matched.
 */
class H5pMemoryGameCard extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_memory_game_cards';
    protected $guarded = [];

    protected $casts = [
        'pair_set' => 'integer',
        'sort_order' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(H5pMemoryGame::class, 'memory_game_id');
    }

    /**
     * Is this pair renderable?
     *
     * A face typed `image` with no image and a face typed `text` with no text
     * are both a blank tile, and a blank tile matched against another blank
     * tile is a game the learner cannot lose or win. Publish checks this.
     */
    public function isComplete(): bool
    {
        return $this->faceIsComplete('front') && $this->faceIsComplete('back');
    }

    private function faceIsComplete(string $side): bool
    {
        $type = $this->{$side . '_type'};
        $value = $type === 'text' ? $this->{$side . '_text'} : $this->{$side . '_image'};

        return trim((string) $value) !== '';
    }
}
