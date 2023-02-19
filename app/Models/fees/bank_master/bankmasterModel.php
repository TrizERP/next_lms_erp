<?php

namespace App\Models\fees\bank_master;

use Illuminate\Database\Eloquent\Model;

class bankmasterModel extends Model {
    protected $table = "bank_master";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'bank_name',
    ];
}
