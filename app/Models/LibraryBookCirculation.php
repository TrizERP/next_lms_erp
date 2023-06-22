<?php

namespace App\Models;

use App\Models\student\tblstudentModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LibraryBookCirculation extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function student(){
        return $this->belongsTo(tblstudentModel::class);
    }
    
    public function book(){
        return $this->belongsTo(LibraryBook::class);
    }
}
