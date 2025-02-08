<?php

namespace App\Models\skill;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class matrix extends Model
{
    use HasFactory;
    protected $table = "s_skill_matrix";
    protected $fillable = ['user_id', 'skill_id', 'skill_level', 'interest_level'];
    public $timestamps = false;
}