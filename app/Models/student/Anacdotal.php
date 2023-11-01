<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anacdotal extends Model
{
    use HasFactory;

    protected $table = 'student_anacdotal';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'syear',
        'sub_institute_id',
        'student_id',
        'place',
        'date',
        'time',
        'observation',
        'observer_name',
        'life_skills',
        'life_values',
        'created_at'
    ];
}
