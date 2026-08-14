<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedagogyEngineSection extends Model
{
    protected $table = 'pal_pedagogy_engine_sections';

    protected $fillable = [
        'section_key',
        'name',
        'subtitle',
        'summary',
        'section_type',
        'badges',
        'implementation_status',
        'current_state',
        'gap',
        'ui_visible',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'badges' => 'array',
        'ui_visible' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(PedagogyEngineRule::class, 'section_key', 'section_key')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
