<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class studentVaccinationModel extends Model
{
    protected $table = 'student_vaccination';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'syear',
        'marking_period_id',
        'doctor_name',
        'doctor_contact',
        'vaccination_type',
        'note',
        'date',
        'created_on',
        'created_by',
        'sub_institute_id'
    ];
}
