<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrmsLeaveType extends Model
{
    use HasFactory;

    public function setLeaveTypeIdAttribute()
    {
        return 'LTY' . $this->attributes['id'];
    }
}
