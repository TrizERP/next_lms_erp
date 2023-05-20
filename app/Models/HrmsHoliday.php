<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmsHoliday extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];
    protected $appends = ['department_name'];

    public function getDepartmentNameAttribute()
    {
        return HrmsDepartment::whereIn('id', explode(',',$this->department))->pluck('department')->implode(',');
    }
}
