<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedagogyEngineRule extends Model
{
    protected $table = 'pal_pedagogy_engine_rules';

    protected $fillable = [
        'section_key',
        'rule_key',
        'group_label',
        'condition',
        'action',
        'pedagogy',
        'h5p_type',
        'scaffolding',
        'trigger_action',
        'reason',
        'implementation_status',
        'rule_meta',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'rule_meta' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(PedagogyEngineSection::class, 'section_key', 'section_key');
    }
}
