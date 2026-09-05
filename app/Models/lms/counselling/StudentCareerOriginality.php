<?php

namespace App\Models\lms\counselling;

use Illuminate\Database\Eloquent\Model;

class StudentCareerOriginality extends Model
{
    protected $table = 'student_career_originality';

    protected $fillable = [
        'student_id',
        'grade',
        'academic_year',
        'originality_statement',
        'originality_reason',
        'source',
        'is_current',
        'captured_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'captured_at' => 'datetime',
    ];
}
