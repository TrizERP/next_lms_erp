<?php

namespace App\Models\admission;

use Illuminate\Database\Eloquent\Model;

class admissionFollowUpModel extends Model
{
    protected $table = 'follow_up';

	public $timestamps = false;

    protected $fillable = [
        'sub_institute_id',
        'enquiry_id',
        'follow_up_date',
        'status',
        'remarks',
        'module_type',
    ];
}
