<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplateVersion extends Model
{
    use HasFactory;

    protected $table = 'document_template_versions';

    protected $fillable = [
        'document_template_id',
        'sub_institute_id',
        'name',
        'content',
        'version',
        'created_by',
    ];

    protected $casts = [
        'document_template_id' => 'integer',
        'sub_institute_id' => 'integer',
        'version' => 'integer',
        'created_by' => 'integer',
    ];

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }
}
