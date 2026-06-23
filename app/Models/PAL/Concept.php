<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

class Concept extends Model
{
    protected $table = 'pal_concepts';
    
    protected $fillable = [
        'subject_id',
        'name',
        'description',
        'bloom_level',
        'abstractness_level',
        'requires_visual',
        'requires_manipulation',
        'requires_simulation',
        'recommended_pedagogy',
        'pedagogy_tags',
    ];
    
    protected $casts = [
        'bloom_level' => 'integer',
        'abstractness_level' => 'integer',
        'requires_visual' => 'boolean',
        'requires_manipulation' => 'boolean',
        'requires_simulation' => 'boolean',
        'pedagogy_tags' => 'array',
    ];
    
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
    
    public function competencies()
    {
        return $this->hasMany(Competency::class);
    }
    
    public function content()
    {
        return $this->hasMany(Content::class);
    }
}