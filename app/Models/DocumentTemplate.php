<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $table = 'document_templates';

    protected $fillable = [
        'sub_institute_id',
        'name',
        'category',
        'description',
        'content',
        'version',
        'status',
        'syear',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'version' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function versions()
    {
        return $this->hasMany(DocumentTemplateVersion::class, 'document_template_id');
    }

    /** Tenant scope — every read path must go through this. */
    public function scopeForTenant($query, $subInstituteId)
    {
        return $query->where('sub_institute_id', (int) $subInstituteId);
    }
}
