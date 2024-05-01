<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\student\tblstudentModel;

class WhatsappSentMessage extends Model
{
    use HasFactory;

    function student()
    {
        return $this->hasMany(tblstudentModel::class,'id','student_id');
    }
}