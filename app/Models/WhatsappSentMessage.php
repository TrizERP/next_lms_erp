<?php

namespace App\Models;

use App\Models\IncomingMessage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\student\tblstudentModel;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\standardModel;

class WhatsappSentMessage extends Model
{
    use HasFactory;
    // get student details by student id
    function student()
    {
        return $this->hasMany(tblstudentModel::class,'id','student_id');
    }
    // get standard details by standard id added on 22-08-2024
    function standard()
    {
        return $this->hasMany(standardModel::class,'id','standard_id');
    }
    // get division details by student id added on 22-08-2024
    function division()
    {
        return $this->hasMany(divisionModel::class,'id','division_id');
    }

    public function messages()
    {
        return $this->hasMany(IncomingMessage::class, 'whatsapp_number','whatsapp_number')->where([['is_seen',0],['type','incoming']]);
    }
}
