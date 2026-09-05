<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A course-completion certificate minted by the LMS. See the migration
 * (`2026_09_05_220000_create_lms_certificates_table.php`) for why this is
 * distinct from `App\Models\Competency\CompetencyCertification`.
 */
class LmsCertificate extends Model
{
    use SoftDeletes;

    protected $table = 'lms_certificates';

    protected $fillable = [
        'user_id', 'course_id', 'enrollment_id', 'skill_id',
        'certificate_number', 'course_title', 'name', 'description', 'tags',
        'verification_code', 'issued_at', 'expires_at', 'status',
        'supersedes', 'superseded_by', 'reissued_at', 'reissued_by',
        'sub_institute_id', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'reissued_at' => 'datetime',
    ];
}
