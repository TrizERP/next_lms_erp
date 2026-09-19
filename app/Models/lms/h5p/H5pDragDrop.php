<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One H5P Drag and Drop task (H5P.DragQuestion).
 *
 * Follows the same shape as h5pFlashcard / H5pInteractiveVideo: guarded = [],
 * soft deletes, tenant column on the row. The two child relations are eager
 * loadable because every read path this type has (list, show, export, the PAL
 * registry projection) needs them.
 */
class H5pDragDrop extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_drag_drop';
    protected $guarded = [];

    protected $casts = [
        'canvas_width' => 'integer',
        'canvas_height' => 'integer',
        'pass_percentage' => 'integer',
        'enable_retry' => 'boolean',
        'enable_show_solution' => 'boolean',
        'enable_check' => 'boolean',
        'single_point' => 'boolean',
        'apply_penalties' => 'boolean',
        'background_opacity_full' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function zones(): HasMany
    {
        return $this->hasMany(H5pDragDropZone::class, 'drag_drop_id')->orderBy('sort_order');
    }

    public function elements(): HasMany
    {
        return $this->hasMany(H5pDragDropElement::class, 'drag_drop_id')->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
