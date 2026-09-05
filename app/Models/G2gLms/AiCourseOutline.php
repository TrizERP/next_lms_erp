<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A "Build with AI" generated outline, and the rendered Gamma deck built from it. */
class AiCourseOutline extends Model
{
    use SoftDeletes;

    protected $table = 'ai_course_outlines';

    protected $fillable = [
        'course_type', 'input_fields', 'configure_fields', 'outline',
        'presentation_platform', 'ai_model', 'slide_count', 'generation_id',
        'gamma_url', 'export_url', 'status', 'course_id',
        'sub_institute_id', 'created_by', 'updated_by', 'deleted_by',
    ];
}
