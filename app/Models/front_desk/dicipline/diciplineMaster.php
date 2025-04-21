<?php

namespace App\Models\front_desk\dicipline;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class diciplineMaster extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'dicipline_master';

}
