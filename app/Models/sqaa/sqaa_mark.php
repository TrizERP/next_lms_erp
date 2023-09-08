<?php

namespace App\Models\sqaa;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sqaa_mark extends Model
{
    protected $table = "sqaa_marks";
    public $timestamps = false;
    protected $fillable = [
        'id',
        'menu_id',
        'mark',    
        'created_by',            
        'sub_institute_id',
        'created_at',
        'updated_at',        
        ];
}
