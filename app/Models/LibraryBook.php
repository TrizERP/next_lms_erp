<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LibraryBook extends Model
{
    use HasFactory,SoftDeletes;
    protected $guarded = ['id'];

    public function items(){
        return $this->hasMany(LibraryItem::class,'book_id');
    }
    public function book_circulations(){
        return $this->hasMany(LibraryBookCirculation::class,'book_id');
    }
}
