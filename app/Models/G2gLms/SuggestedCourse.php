<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A course recommendation surfaced against an employee (a gap, or an expiring certification). */
class SuggestedCourse extends Model
{
    use SoftDeletes;

    protected $table = 'suggested_course';

    protected $fillable = [
        'employee_id', 'course_id', 'course_name', 'task_id',
        'sub_institute_id', 'created_by', 'updated_by', 'deleted_by',
    ];
}
