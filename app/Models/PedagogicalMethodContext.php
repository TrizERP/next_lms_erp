<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a teaching method IS: its definition, roles, stages and the instructions the AI is given for it.
 * Reference data, written only by the seeder or an administrator. Generation reads it and never writes it.
 */
class PedagogicalMethodContext extends Model
{
    protected $table = 'pedagogical_method_contexts';

    protected $fillable = [
        'method_key', 'method_name', 'method_definition', 'teaching_philosophy', 'core_principles', 'teacher_role', 'student_role',
        'teaching_strategies', 'workflow_stages', 'classroom_activities', 'questioning_strategies', 'assessment_strategy',
        'ai_generation_instructions', 'is_active',
    ];

    protected $casts = [
        'core_principles' => 'array',
        'teaching_strategies' => 'array',
        'workflow_stages' => 'array',
        'classroom_activities' => 'array',
        'questioning_strategies' => 'array',
        'assessment_strategy' => 'array',
        'is_active' => 'boolean',
    ];

    /** The eleven context fields every method must carry. */
    public const CONTEXT_FIELDS = [
        'method_definition', 'teaching_philosophy', 'core_principles', 'teacher_role', 'student_role', 'teaching_strategies',
        'workflow_stages', 'classroom_activities', 'questioning_strategies', 'assessment_strategy', 'ai_generation_instructions',
    ];
}
