<?php

namespace App\Models\lms\counselling;

use Illuminate\Database\Eloquent\Model;

class StudentAspiration extends Model
{
    protected $table = 'student_aspirations';

    protected $fillable = [
        'student_id',
        'grade',
        'academic_year',
        'occupation_id',
        'occupation_name',
        'expectation_age_30',
        'alternative_occupation_id',
        'alternative_occupation_name',
        'certainty',
        'certainty_reason',
        'parent_occupation_id',
        'parent_occupation_name',
        'preferred_stream',
        'preferred_education_route',
        'source',
        'is_current',
        'captured_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'certainty' => 'float',
        'captured_at' => 'datetime',
    ];
}
