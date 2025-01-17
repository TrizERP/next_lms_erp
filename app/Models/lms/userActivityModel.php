<?php

namespace App\Models\lms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class userActivityModel extends Model
{
    use HasFactory;
     // Specify the table name
     protected $table = 'user_activities';
     // Disable mass assignment protection
     protected $guarded = []; 
}
