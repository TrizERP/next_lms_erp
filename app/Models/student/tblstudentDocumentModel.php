<?php

namespace App\Models\student;

use Illuminate\Database\Eloquent\Model;

class tblstudentDocumentModel extends Model
{
    protected $table = 'tblstudent_document';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'student_id',
        'document_type_id',
        'document_title',
        'file_name',
        'sub_institute_id',
        'created_on'
    ];
}
