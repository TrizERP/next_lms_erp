<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use HasFactory;
    protected $fillable = [
    'type', 'author', 'image', 'title', 'description', 'slug',
    'meta_title', 'meta_description', 'meta_keyword', 'status'
];
}


