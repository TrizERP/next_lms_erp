<?php

namespace App\Models\inward_outward;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class inwardModel extends Model
{
    protected $table = "inward";
    
    protected $fillable = [
        'id',
        'sub_institute_id',
        'syear',
        'place_id',
        'file_location_id',
        'inward_number',
        'title',
        'description',
        'attachment',
        'attachment_size',
        'attachment_type',
        'acedemic_year',
        'inward_date',
        'created_at',
        'updated_at'
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(place_masterModel::class);
    }

    public function file_location(): BelongsTo
    {
        return $this->belongsTo(physical_file_locationModel::class);
    }
}
