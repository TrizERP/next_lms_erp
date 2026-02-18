<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentEnrollment extends Model
{
    protected $table = 'tblstudent_enrollment';
    protected $fillable = ['student_id','standard_id','section_id'];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }
}
