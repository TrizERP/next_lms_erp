<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class tblclientModel extends Model
{
    protected $table = "tblclient";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'client_name',
        'short_code',
        'logo',
        'address',
        'city',
        'state',
        'country',
        'email',
        'contact_person',
        'contact_person_mobile',
        'contact_persoon_email',
        'trustee_name',
        'trustee_emai',
        'trustee_mobile',
        'number_of_schools',
        'db_host',
        'db_user',
        'db_password',
        'db_solution',
        'db_cms',
        'db_hrms',
        'db_library',
        'db_lms',
        'multischool',
        'total_student',
        'total_staff',
        'hrms_folder',
        'old_url',
        'created_on'
    ];
}
