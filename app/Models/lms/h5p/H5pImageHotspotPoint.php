<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One hotspot on an H5P Image Hotspots item.
 *
 * Positions are percentages of the background, cast to float so arithmetic in
 * PHP and in the player agree -- a decimal column read back as a string would
 * make `position_x > 50` a string comparison.
 */
class H5pImageHotspotPoint extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'h5p_image_hotspot_points';
    protected $guarded = [];

    protected $casts = [
        'position_x' => 'float',
        'position_y' => 'float',
        'popup_width' => 'integer',
        'sort_order' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(H5pImageHotspots::class, 'image_hotspots_id');
    }

    /**
     * The accessible name for this hotspot, which is never empty.
     *
     * Falls back header -> "Hotspot <n>", so a hotspot labelled only by its
     * number on screen still announces as something a learner can act on.
     */
    public function accessibleName(): string
    {
        foreach ([$this->aria_label, $this->header] as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return 'Hotspot ' . ((int) $this->sort_order + 1);
    }
}
