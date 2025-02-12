<?php

namespace App\Models\skill;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class skill extends Model
{
    use HasFactory;
    protected $table = "master_skills";
    protected $fillable = ['id', 'name', 'category', 'sub_category','title','description','status','sub_institute_id'];
    public $timestamps = false;
}