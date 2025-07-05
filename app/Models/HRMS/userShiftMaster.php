<?php

namespace App\Models\HRMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class userShiftMaster extends Model
{
    use HasFactory,SoftDeletes;
    protected $table="tbluser_shift_master";
    protected $softDelete = true;
}
