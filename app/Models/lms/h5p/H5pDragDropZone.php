<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A drop target drawn on the task background.
 *
 * `correct_element_ids` holds the element ids this zone accepts. With `single`
 * set the zone is one-to-one (it takes exactly one draggable, whichever of the
 * accepted ones is dropped first); cleared, it is one-to-many and every mapped
 * element belongs here.
 */
class H5pDragDropZone extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_drag_drop_zones';
    protected $guarded = [];

    protected $casts = [
        'position_x' => 'float',
        'position_y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'single' => 'boolean',
        'auto_align' => 'boolean',
        'show_label' => 'boolean',
        'correct_element_ids' => 'array',
        'sort_order' => 'integer',
    ];

    public function dragDrop(): BelongsTo
    {
        return $this->belongsTo(H5pDragDrop::class, 'drag_drop_id');
    }
}
