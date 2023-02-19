<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class studentHomeworkModel extends Model {

	protected $table = "homework";

	public $timestamps = false;

    protected $fillable = [
        'id',
        'sub_institute_id',
        'syear',
        'student_id',
        'standard_id',
        'division_id',
        'subject_id',
        'title',
        'description',
        'date',
        'image',
        'image_size',
        'image_type',
        'type',
        'submission_date',
        'completion_status',
        'submission_remarks',
        'submission_image',
        'submission_image_size',
        'submission_image_type',
        'created_by',
        'created_ip',
        'created_on'
    ];
}
