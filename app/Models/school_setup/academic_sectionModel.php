<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class academic_sectionModel extends Model
{
    protected $table = "academic_section";
    protected $fillable = [
        'id',
        'sub_institute_id',
        'title',
        'short_name',
        'sort_order',
        'shift',
        'medium',
        'payment_link',
        'created_at',
        'updated_at'
    ];
}
