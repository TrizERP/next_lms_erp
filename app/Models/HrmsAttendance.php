<?php

namespace App\Models;

use App\Models\user\tbluserModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrmsAttendance extends Model
{
    use HasFactory;
    public function getUser(){
        return $this->hasOne(tbluserModel::class,'id','user_id')->where('status',1); // 23-04-24 by uma
    }

    // Get department through user relationship since department_id comes from user
    public function getDepartment(){
        return $this->hasOneThrough(
            HrmsDepartment::class,
            tbluserModel::class,
            'id', // Foreign key on users table
            'id', // Foreign key on departments table
            'user_id', // Local key on attendance table
            'department_id' // Local key on users table
        )->where('hrms_departments.status', 1); // 23-04-24 by uma
    }
}
