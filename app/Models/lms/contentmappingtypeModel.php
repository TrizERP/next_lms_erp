<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Model;

class contentmappingtypeModel extends Model
{
    protected $table = "content_mapping_type";
	public $timestamps = false;

    protected $fillable = [
        'id',
        'content_id',
        'mapping_type_id',
        'mapping_value_id'
    ];
}
