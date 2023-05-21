<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmsEmpLeave extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function leave_type()
    {
        return $this->belongsTo(HrmsLeaveType::class);
    }
}
