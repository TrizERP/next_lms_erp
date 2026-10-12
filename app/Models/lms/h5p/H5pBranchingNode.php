<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One node of a Branching Scenario: a screen of content, a decision, or an
 * ending. See the migration for why the three share a table.
 */
class H5pBranchingNode extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_branching_nodes';
    protected $guarded = [];

    /** A screen of content: explanation, situation, consequence, reflection. */
    public const TEXT = 'text';

    /** A decision. Maps to H5P.BranchingQuestion. */
    public const BRANCHING_QUESTION = 'branching_question';

    /** Where a path stops. Maps to an H5P end screen. */
    public const ENDING = 'ending';

    public const TYPES = [self::TEXT, self::BRANCHING_QUESTION, self::ENDING];

    /** How sound a choice is. Drives the recap a learner sees, not scoring. */
    public const QUALITIES = ['best', 'partial', 'misstep'];

    /** What a node is for. Metadata only; see the migration. */
    public const PURPOSES = [
        'situation', 'explanation', 'example', 'investigate', 'predict', 'apply',
        'check', 'consequence', 'remedial', 'reflection', 'summary',
    ];

    protected $casts = [
        'node_index' => 'integer',
        'show_title' => 'boolean',
        'next_node_id' => 'integer',
        'options' => 'array',
        'score' => 'integer',
        'concept_id' => 'integer',
        'source_question_id' => 'integer',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(H5pBranchingScenario::class, 'scenario_id');
    }

    public function isEnding(): bool
    {
        return $this->node_type === self::ENDING;
    }

    public function isDecision(): bool
    {
        return $this->node_type === self::BRANCHING_QUESTION;
    }

    /** The name shown in the editor's outline and in a learner's path recap. */
    public function displayTitle(): string
    {
        $title = trim((string) $this->title);

        return $title !== '' ? $title : 'Step ' . ((int) $this->node_index + 1);
    }
}
