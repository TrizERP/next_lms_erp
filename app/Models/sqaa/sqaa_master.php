<?php

namespace App\Models\sqaa;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sqaa_master extends Model
{
    protected $table = "sqaa_master";
    public $timestamps = false;
    protected $fillable = [
        'id',
        'title',
        'description',
        'parent_id',
        'level',
        'status',
        'sort_order',
        'sub_institute_id',
        'created_by',
        'created_at',
        'updated_at',        
        ];
}
