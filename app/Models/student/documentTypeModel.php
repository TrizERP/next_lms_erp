<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class documentTypeModel extends Model
{
    protected $table = 'student_document_type';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'document_type',
        'status',
        'created_at'
    ];
}
