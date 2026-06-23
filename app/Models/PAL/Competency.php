<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

class Competency extends Model
{
    protected $table = 'pal_competencies';
    
    protected $fillable = [
        'learner_id',
        'subject_id',
        'concept_id',
        'mastery_score',
        'bloom_level',
        'proficiency_trend',
        'last_assessed',
    ];
    
    protected $casts = [
        'mastery_score' => 'float',
        'bloom_level' => 'integer',
    ];
    
    public function getMasteryStatus(): string
    {
        return match(true) {
            $this->mastery_score >= 80 => 'mastered',
            $this->mastery_score >= 50 => 'learning',
            default => 'new',
        };
    }
    
    public function concept()
    {
        return $this->belongsTo(Concept::class);
    }
    
    public function learner()
    {
        return $this->belongsTo(\App\Models\User::class, 'learner_id');
    }
    
    public function misconceptions()
    {
        return $this->hasMany(LearnerMisconception::class);
    }
}