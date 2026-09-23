<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class mobile_dynamic_page_fieldModel extends Model
{
    protected $table = 'mobile_dynamic_page_field';
    public $timestamps = false;

    protected $fillable = [
        'page_id',
        'field_key',
        'display_key',
        'label',
        'field_type',
        'sort_order',
        'status',
        'created_on',
        'updated_on',
    ];

    public function page()
    {
        return $this->belongsTo(mobile_dynamic_pageModel::class, 'page_id');
    }
}
