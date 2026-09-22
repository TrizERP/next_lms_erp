<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One H5P Image Hotspots item (H5P.ImageHotspots).
 *
 * Shaped like H5pDragDrop and H5pTextActivity: guarded = [], soft deletes,
 * tenant column on the row, children eager-loadable because every read path
 * needs them -- a hotspot item with no hotspots is not renderable and not
 * publishable, so there is no read that wants the parent alone.
 */
class H5pImageHotspots extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_image_hotspots';
    protected $guarded = [];

    /** The registry code and the config/h5p_libraries.php key. */
    public const REGISTRY_CODE = 'image_hotspots';

    /** Popup bodies this type can hold. Validated against, never assumed. */
    public const POPUP_TYPES = ['text', 'image', 'rich'];

    /**
     * Hotspot glyphs the player knows how to draw.
     *
     * Lucide names, because the frontend renders them through the design
     * system's Icon component. An author may also supply `icon_image`, which
     * is a URL and wins over any of these.
     */
    public const ICONS = ['plus', 'info', 'circle-help', 'circle-alert', 'target', 'map-pin', 'star'];

    protected $casts = [
        'image_width' => 'integer',
        'image_height' => 'integer',
        'show_hotspot_numbers' => 'boolean',
        'points_per_hotspot' => 'integer',
        'pass_percentage' => 'integer',
        'enable_retry' => 'boolean',
        'single_popup_open' => 'boolean',
        'feedback_bands' => 'array',
        'published_at' => 'datetime',
    ];

    /** Hotspots in author order. */
    public function points(): HasMany
    {
        return $this->hasMany(H5pImageHotspotPoint::class, 'image_hotspots_id')->orderBy('sort_order');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * What a full-marks attempt is worth: every hotspot opened.
     *
     * See the migration for why coverage is the scoring for a type H5P itself
     * does not score. Reads the loaded relation when there is one so a list
     * page that eager-loaded points does not fire a query per row.
     */
    public function maxScore(): int
    {
        $count = $this->relationLoaded('points') ? $this->points->count() : $this->points()->count();

        return $count * max(1, (int) $this->points_per_hotspot);
    }
}
