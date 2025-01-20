<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class masterSkill extends Model
{
    use HasFactory,SoftDeletes;
    protected $table="master_skills";
    protected $softDelete = true;
}
