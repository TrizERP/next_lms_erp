<?php

namespace App\Models\PAL;

use Illuminate\Database\Eloquent\Model;

class LearningSession extends Model
{
    protected $table = 'pal_learning_sessions';
    
    protected $fillable = [
        'learner_id',
        'subject_id',
        'status',
        'difficulty_level',
        'duration_minutes',
        'interaction_count',
        'engagement_score',
        'mastery_score',
        'device_type',
        'initiated_by',
    ];
    
    protected $casts = [
        'difficulty_level' => 'integer',
        'duration_minutes' => 'integer',
        'interaction_count' => 'integer',
        'engagement_score' => 'float',
        'mastery_score' => 'float',
    ];
    
    public function learner() { return $this->belongsTo(\App\Models\User::class, 'learner_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function events() { return $this->hasMany(SessionEvent::class); }
}

class SessionEvent extends Model
{
    protected $table = 'pal_session_events';
    
    protected $fillable = [
        'learner_id',
        'session_id',
        'event_type',
        'event_data',
        'duration_seconds',
        'response_time_ms',
        'metadata',
    ];
    
    protected $casts = [
        'event_data' => 'array',
        'metadata' => 'array',
        'duration_seconds' => 'integer',
        'response_time_ms' => 'integer',
    ];
    
    public function session() { return $this->belongsTo(LearningSession::class); }
}

class AssessmentResult extends Model
{
    protected $table = 'pal_assessment_results';
    
    protected $fillable = [
        'learner_id',
        'question_id',
        'is_correct',
        'response_time_ms',
    ];
    
    protected $casts = ['is_correct' => 'boolean', 'response_time_ms' => 'integer'];
}

class Misconception extends Model
{
    protected $table = 'pal_misconceptions';
    
    protected $fillable = [
        'concept_id',
        'pattern',
        'category',
        'root_cause',
        'frequency',
    ];
    
    protected $casts = ['frequency' => 'integer'];
}

class LearnerMisconception extends Model
{
    protected $table = 'pal_learner_misconceptions';
    
    protected $fillable = [
        'learner_id',
        'concept_id',
        'misconception_id',
        'status',
        'severity',
    ];
    
    public function misconception() { return $this->belongsTo(Misconception::class); }
}

class Remediation extends Model
{
    protected $table = 'pal_remediations';
    
    protected $fillable = [
        'misconception_id',
        'type',
        'content',
        'pedagogy',
        'effectiveness_score',
        'status',
    ];
    
    protected $casts = ['effectiveness_score' => 'float'];
}

class Content extends Model
{
    protected $table = 'pal_contents';
    
    protected $fillable = [
        'concept_id',
        'title',
        'body',
        'content_type',
        'format',
        'difficulty_level',
        'bloom_level',
        'pedagogy_tags',
        'h5p_type',
        'status',
    ];
    
    protected $casts = [
        'difficulty_level' => 'integer',
        'bloom_level' => 'integer',
        'pedagogy_tags' => 'array',
    ];
}

class ContentRecommendation extends Model
{
    protected $table = 'pal_content_recommendations';
    
    protected $fillable = ['learner_id', 'content_id', 'event_type', 'engagement_score'];
    protected $casts = ['engagement_score' => 'integer'];
}

class TelemetryEvent extends Model
{
    protected $table = 'pal_telemetry_events';
    
    protected $fillable = [
        'actor_id',
        'verb',
        'object_id',
        'context_id',
        'result',
        'raw_statement',
        'timestamp',
        'duration_seconds',
    ];
    
    protected $casts = ['result' => 'array', 'raw_statement' => 'array', 'duration_seconds' => 'integer'];
}

class Reflection extends Model
{
    protected $table = 'pal_reflections';
    
    protected $fillable = ['learner_id', 'session_id', 'concept_id', 'content_id', 'prompt_type', 'learner_response', 'quality_score', 'context_data'];
    protected $casts = ['session_id' => 'integer', 'concept_id' => 'integer', 'content_id' => 'integer', 'quality_score' => 'integer', 'context_data' => 'array'];
}

class PedagogyEffectiveness extends Model
{
    protected $table = 'pal_pedagogy_effectiveness';
    
    protected $fillable = ['learner_id', 'session_id', 'concept_id', 'content_id', 'pedagogy_type', 'outcome', 'effectiveness_score', 'context_data'];
    protected $casts = [
        'session_id' => 'integer',
        'concept_id' => 'integer',
        'content_id' => 'integer',
        'effectiveness_score' => 'float',
        'context_data' => 'array',
    ];
}

class LearnerPreference extends Model
{
    protected $table = 'pal_learner_preferences';
    
    protected $fillable = ['learner_id', 'pref_key', 'pref_value'];
}

class Subject extends Model
{
    protected $table = 'pal_subjects';
    protected $fillable = ['name', 'description', 'grade_level'];
}

// ==================== ADDITIONAL MODELS (added for full PAL support) ====================

class CollaborationActivity extends Model
{
    protected $table = 'pal_collaboration_activities';

    protected $fillable = [
        'learner_id',
        'activity_type',
        'peer_count',
        'engagement_score',
    ];

    protected $casts = [
        'peer_count' => 'integer',
        'engagement_score' => 'float',
    ];
}

class ClassroomActivity extends Model
{
    protected $table = 'pal_classroom_activities';

    protected $fillable = [
        'learner_id',
        'participation_score',
    ];

    protected $casts = ['participation_score' => 'float'];
}

class Discussion extends Model
{
    protected $table = 'pal_discussions';

    protected $fillable = [
        'learner_id',
        'content',
        'upvotes',
    ];

    protected $casts = ['upvotes' => 'integer'];
}

class GroupActivity extends Model
{
    protected $table = 'pal_group_activities';

    protected $fillable = [
        'learner_id',
        'performance_score',
    ];

    protected $casts = ['performance_score' => 'float'];
}

class SelfCorrection extends Model
{
    protected $table = 'pal_self_corrections';

    protected $fillable = [
        'learner_id',
        'successful',
    ];

    protected $casts = ['successful' => 'boolean'];
}

class LearningPlan extends Model
{
    protected $table = 'pal_learning_plans';

    protected $fillable = [
        'learner_id',
        'plan_data',
    ];

    protected $casts = ['plan_data' => 'array'];
}

class StrategySelection extends Model
{
    protected $table = 'pal_strategy_selections';

    protected $fillable = [
        'learner_id',
        'strategy_type',
    ];
}

class LearningJournal extends Model
{
    protected $table = 'pal_learning_journals';

    protected $fillable = [
        'learner_id',
        'entry',
    ];
}

class LearningEvent extends Model
{
    protected $table = 'pal_learning_events';

    protected $fillable = [
        'learner_id',
        'event_type',
        'event_data',
    ];

    protected $casts = ['event_data' => 'array'];
}

class RemediationSession extends Model
{
    protected $table = 'pal_remediation_sessions';

    protected $fillable = [
        'learner_id',
        'misconception_id',
        'status',
        'attempts',
        'success_rate',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'success_rate' => 'float',
    ];
}

class LearningPattern extends Model
{
    protected $table = 'pal_learning_patterns';

    protected $fillable = [
        'learner_id',
        'pattern_type',
        'pattern_data',
        'confidence',
    ];

    protected $casts = [
        'pattern_data' => 'array',
        'confidence' => 'float',
    ];
}
