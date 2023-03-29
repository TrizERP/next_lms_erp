<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmitData extends Model
{
    protected $table = "form_submit_data";
    protected $fillable = [
        'id',
        'form_id',
        'user_id',
        'standard',
        'subject',
        'chapter',
        'form_data',
        'sub_institute_id',
        'created_at',
        'updated_at'
    ];
}
