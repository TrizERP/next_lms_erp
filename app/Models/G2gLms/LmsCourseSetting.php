<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Course Builder's per-course authoring settings — one row per sub_std_map course. */
class LmsCourseSetting extends Model
{
    use SoftDeletes;

    protected $table = 'lms_course_settings';

    protected $fillable = [
        'course_id', 'sequential_unlock', 'sub_institute_id',
        'description', 'duration_minutes', 'language', 'is_mandatory', 'discussion_enabled', 'visibility',
        'passing_score', 'max_attempts',
        'issue_certificate', 'certificate_template', 'recert_alerts',
        'enrollment_rule', 'restrict_departments', 'restrict_roles', 'available_from', 'available_until',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'sequential_unlock' => 'boolean',
        'is_mandatory' => 'boolean',
        'discussion_enabled' => 'boolean',
        'issue_certificate' => 'boolean',
        'recert_alerts' => 'boolean',
        'restrict_departments' => 'array',
        'restrict_roles' => 'array',
        'available_from' => 'date',
        'available_until' => 'date',
    ];
}
