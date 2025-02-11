<?php

namespace App\Models\skill;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentLibrary extends Model
{
    use HasFactory;
    protected $table = "s_assessment_library";
    protected $fillable = [
        'title',
        'description',
        'total_questions',
        'attempted_users',
        'duration',
        'type',
        'level',
        'languages',
        'job_role'
    ];

    public $timestamps = false;
}