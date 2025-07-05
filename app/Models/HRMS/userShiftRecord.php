<?php

namespace App\Models\HRMS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class userShiftRecord extends Model
{
    use HasFactory,SoftDeletes;
    protected $table="tbluser_shift_records";
    protected $softDelete = true;
}
