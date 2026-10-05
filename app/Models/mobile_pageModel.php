<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class mobile_pageModel extends Model
{
    protected $table = 'mobile_pages';
    public $timestamps = false;

    protected $fillable = [
        'sub_institute_id',
        'name',
        'slug',
        'description',
        'status',
        'current_draft_version_id',
        'published_version_id',
        'created_by',
        'updated_by',
        'created_on',
        'updated_on',
    ];

    public function versions()
    {
        return $this->hasMany(mobile_page_versionModel::class, 'page_id')->orderByDesc('version_number');
    }

    public function draftVersion()
    {
        return $this->belongsTo(mobile_page_versionModel::class, 'current_draft_version_id');
    }

    public function publishedVersion()
    {
        return $this->belongsTo(mobile_page_versionModel::class, 'published_version_id');
    }
}
