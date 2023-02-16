<?php

namespace App\Models\result\result_book_master;

use Illuminate\Database\Eloquent\Model;

class result_book_master extends Model
{
     protected $table = "result_book_master";
    protected $fillable = [
        'trust_id',
        'standard',
        'sub_institute_id',
    ];

}
