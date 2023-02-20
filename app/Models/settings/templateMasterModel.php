<?php

namespace App\Models\settings;

use Illuminate\Database\Eloquent\Model;

class templateMasterModel extends Model
{
    protected $table = "template_master";
    public $timestamps = false;

    protected $fillable = [
        'id',
        'sub_institute_id',
        'module_name',
        'title',
        'html_content',
        'status',
        'created_by',
        'created_on'
    ];
}

?>

