<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class castModel extends Model
{
    public $timestamps = false;

	protected $table = "caste";

    protected $fillable = [
        'id',
        'title',
        'sort_order',
        'sub_institute_id'
    ];
}
