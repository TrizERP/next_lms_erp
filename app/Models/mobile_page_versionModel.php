<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class mobile_page_versionModel extends Model
{
    protected $table = 'mobile_page_versions';
    public $timestamps = false;

    protected $fillable = [
        'page_id',
        'version_number',
        'layout_json',
        'status',
        'created_by',
        'created_on',
        'updated_on',
        'published_at',
        'published_by',
    ];

    public function page()
    {
        return $this->belongsTo(mobile_pageModel::class, 'page_id');
    }
}
