<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class h5pFlashcard extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'h5p_flashcard';
    protected $guarded = [];
}
