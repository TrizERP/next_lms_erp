<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiSop extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_sops';

    protected $fillable = [
        'sop_name',
        'department_id',
        'sop_content',
        'pdf_file_path',
        'pdf_url',
        'sub_institute_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'status',
    ];
}
