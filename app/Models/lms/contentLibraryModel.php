<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class contentLibraryModel extends Model
{
    use HasFactory;
     // Specify the table name
     protected $table = 'contents';
     // Disable mass assignment protection
     protected $guarded = []; 
}
