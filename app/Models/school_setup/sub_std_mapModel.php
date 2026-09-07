<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class sub_std_mapModel extends Model
{
    // SoftDeletes added for the G2G LMS port (Package 1): the
    // 2026_09_05_220000_alter_sub_std_map_for_g2g_lms migration adds
    // `deleted_at`, and LearningCatalogController's destroy()/bulk() call
    // ->delete() expecting a soft delete, matching hp_erp's sub_std_mapModel.
    use SoftDeletes;

    protected $table = "sub_std_map";
    protected $fillable = [
        'id',
        'subject_id',
        'standard_id',
        'allow_grades',
        'elective_subject',
        'display_name',
        'load',
        'optional_type',
        'add_content',
        'allow_content',
        'content_quantity',
        'subject_category',
        'subject_code',
        'subject_type',
        'short_name',
        'display_image',
        'sort_order',
        'sub_institute_id',
        'status',
        'jobrole',
        'proficiency',
        'certificate_validity_months',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at'
    ];
}
