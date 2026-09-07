<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** "Course X must be finished before course Y." One row per prerequisite link. */
class LmsCoursePrerequisite extends Model
{
    use SoftDeletes;

    protected $table = 'lms_course_prerequisites';

    protected $fillable = ['course_id', 'prerequisite_course_id', 'sub_institute_id', 'created_by'];
}
