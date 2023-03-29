<?php

namespace App\Models\hostel_management;

use Illuminate\Database\Eloquent\Model;

class tblhostelRoomAllocationModel extends Model
{
    protected $table = "hostel_room_allocation";

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'user_group_id',
        'admission_category_id',
        'hostel_id',
        'room_id',
        'bed_no',
        'locker_no',
        'table_no',
        'bedsheet_no',
        'term_id',
        'syear',
        'sub_institute_id',
        'created_on'
    ];
}
