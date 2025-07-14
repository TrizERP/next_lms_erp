<?php

namespace App\Models\front_desk;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class classWorkModel extends Model
{
     use HasFactory,SoftDeletes;
    protected $table="classwork_attachment";
    protected $softDelete = true;

    public function student()
    {
        return $this->belongsTo(\App\Models\student\tblstudentModel::class, 'student_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\user\tbluserModel::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\user\tbluserModel::class, 'updated_by');
    }
}
