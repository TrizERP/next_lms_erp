<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class tblmenumasterModel extends Model
{
    protected $table = "tblmenumaster";

    protected $fillable = [
        'id',
        'name',
        'menu_title',
        'menu_sortorder',
        'description',
        'parent_menu_id',
        'level',
        'status',
        'sort_order',
        'link',
        'icon',
        'sub_institute_id',
        'client_id',
        'created_at',
        'updated_at',
        'menu_type',
        'site_map_name',
        'youtube_link',
        'pdf_link',
        'menu_path',
        'quick_menu',
        'dashboard_menu'
    ];
}
