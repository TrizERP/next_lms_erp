<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

class LearnerState extends Model
{
    protected $table = 'pal_learner_states';
    
    protected $fillable = [
        'learner_id',
        'session_id',
        'competency_state',
        'behavioral_state',
        'motivational_state',
        'social_state',
        'contextual_state',
        'metacognition_state',
        'disengagement_risk',
    ];
    
    protected $casts = [
        'competency_state' => 'array',
        'behavioral_state' => 'array',
        'motivational_state' => 'array',
        'social_state' => 'array',
        'contextual_state' => 'array',
        'metacognition_state' => 'array',
        'disengagement_risk' => 'float',
    ];
    
    public function learner()
    {
        return $this->belongsTo(\App\Models\User::class, 'learner_id');
    }
}