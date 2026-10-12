<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One H5P Branching Scenario (H5P.BranchingScenario): a decision tree of
 * screens of content, decisions and endings.
 *
 * See the migration for the shape, and App\Services\lms\H5P\BranchingGraph for
 * the rules a tree has to satisfy before a learner can be given it.
 */
class H5pBranchingScenario extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_branching_scenario';
    protected $guarded = [];

    public const REGISTRY_CODE = 'branching_scenario';

    public const SCORING_OPTIONS = ['no-score', 'static-end-score'];

    protected $casts = [
        'learning_objectives' => 'array',
        'generation' => 'array',
        'pass_percentage' => 'integer',
        'enable_backwards_navigation' => 'boolean',
        'randomize_alternatives' => 'boolean',
        'published_at' => 'datetime',
    ];

    /** Nodes in authoring order. The first one is where the scenario starts. */
    public function nodes(): HasMany
    {
        return $this->hasMany(H5pBranchingNode::class, 'scenario_id')->orderBy('node_index');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * What a full-marks attempt is worth: the best ending.
     *
     * Static end scoring only awards what the ending reached carries, so the
     * ceiling is the largest of them. `no-score` scenarios are not marked at
     * all, which is a legitimate scenario -- a piece of teaching -- and why
     * publishing does not require a score.
     */
    public function maxScore(): int
    {
        if ($this->scoring_option === 'no-score') {
            return 0;
        }

        $nodes = $this->relationLoaded('nodes') ? $this->nodes : $this->nodes()->get();

        return (int) $nodes
            ->filter(fn (H5pBranchingNode $node) => $node->node_type === H5pBranchingNode::ENDING)
            ->max(fn (H5pBranchingNode $node) => (int) $node->score);
    }
}
