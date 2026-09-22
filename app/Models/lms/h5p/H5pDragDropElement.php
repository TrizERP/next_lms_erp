<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A draggable -- text or image -- placed on the task.
 *
 * `drop_zone_ids` is the element side of the same mapping the zone holds. Both
 * sides are stored because the export format carries both, and because the
 * player needs the element side to know where a draggable may legally land
 * before anything has been dropped.
 */
class H5pDragDropElement extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_drag_drop_elements';
    protected $guarded = [];

    protected $casts = [
        'position_x' => 'float',
        'position_y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'multiple' => 'boolean',
        'infinite' => 'boolean',
        'drop_zone_ids' => 'array',
        'sort_order' => 'integer',
    ];

    public function dragDrop(): BelongsTo
    {
        return $this->belongsTo(H5pDragDrop::class, 'drag_drop_id');
    }
}
