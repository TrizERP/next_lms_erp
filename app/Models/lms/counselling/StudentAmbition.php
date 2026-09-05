<?php

namespace App\Models\lms\counselling;

use Illuminate\Database\Eloquent\Model;

class StudentAmbition extends Model
{
    protected $table = 'student_ambitions';

    protected $fillable = [
        'student_id',
        'grade',
        'academic_year',
        'ambition_statement',
        'ambition_reason',
        'source',
        'is_current',
        'captured_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'captured_at' => 'datetime',
    ];
}
