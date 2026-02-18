<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'tblstudent';
    protected $fillable = ['first_name','last_name','dob','gender'];

    public function enrollments()
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id', 'id');
    }
}
