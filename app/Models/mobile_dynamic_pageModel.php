<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class mobile_dynamic_pageModel extends Model
{
    protected $table = 'mobile_dynamic_page';
    public $timestamps = false;

    protected $fillable = [
        'sub_institute_id',
        'page_key',
        'title',
        'data_endpoint',
        'status',
        'created_on',
        'updated_on',
        'updated_by',
    ];

    public function fields()
    {
        return $this->hasMany(mobile_dynamic_page_fieldModel::class, 'page_id')->orderBy('sort_order');
    }
}
