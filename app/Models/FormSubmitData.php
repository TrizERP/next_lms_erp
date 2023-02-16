<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmitData extends Model
{
    protected $table = "form_submit_data";
    protected $fillable = [
        'form_id',
        'user_id',
        'sub_institute_id',
        'form_data',
    ];
}
