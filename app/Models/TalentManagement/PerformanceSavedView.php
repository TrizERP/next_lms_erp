<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceSavedView`.
 *
 * A named filter preset behind the "Saved Views" dropdown.
 */
class PerformanceSavedView extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's_performance_saved_views';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'user_id'          => 'integer',
        'filters'          => 'array',
        'is_shared'        => 'boolean',
        'is_default'       => 'boolean',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];
}
