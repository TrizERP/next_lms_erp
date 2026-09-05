<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from hp_erp's `App\Models\lms_course_enroll\LmsCourseEnroll`.
 * SoftDeletes added (the source model relied on manual deleted_at writes
 * only); the underlying columns are identical, see
 * database/migrations/2026_09_05_220100_create_lms_course_enroll_table.php.
 */
class LmsCourseEnroll extends Model
{
    use SoftDeletes;

    protected $table = 'lms_course_enroll';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'start_date',
        'end_date',
        'sub_institute_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
