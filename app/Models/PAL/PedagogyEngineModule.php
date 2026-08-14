<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

class PedagogyEngineModule extends Model
{
    protected $table = 'pal_pedagogy_engine_modules';

    protected $fillable = [
        'slug',
        'module_name',
        'title',
        'description',
        'version',
        'source_label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
