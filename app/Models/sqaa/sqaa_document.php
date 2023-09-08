<?php

namespace App\Models\sqaa;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class sqaa_document extends Model
{
    protected $table = "sqaa_documents";
    public $timestamps = false;
    protected $fillable = [
        'id',
        'menu_id',
        'document_id',    
        'title',    
        'reasons',
        'availability',            
        'file',            
        'sub_institute_id',
        'created_by',
        'created_at',
        'updated_at',        
        ];
}
