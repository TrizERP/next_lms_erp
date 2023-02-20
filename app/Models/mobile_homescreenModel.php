<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class mobile_homescreenModel extends Model
{
    protected $table = "mobile_homescreen";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'sub_institute_id',
        'user_profile_id',
        'user_profile_name',
        'main_title',
        'menu_type',
        'main_title_color_code',
        'main_title_background_image',
        'sub_title_of_main',
        'sub_title_icon',
        'sub_title_api',
        'sub_title_api_param',
        'main_sort_order',
        'sub_title_sort_order',
        'screen_name',
        'status',
        'created_on',
        'updated_on',
        'updated_by',
        'updated_ip_address'
    ];
}
