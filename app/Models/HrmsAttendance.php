<?php

namespace App\Models;

use App\Models\user\tbluserModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrmsAttendance extends Model
{
    use HasFactory;
    public function getUser(){
        return $this->hasOne(tbluserModel::class,'id','user_id');
    }
}
